<?php
// Small, explainable routing engine. No remote AI, diagnosis, transcript or appointment writes.
function chat_normalize(string $text): string {
    $text=mb_strtolower($text,'UTF-8');
    $text=strtr($text,['ي'=>'ی','ى'=>'ی','ك'=>'ک','ۀ'=>'ہ','ة'=>'ہ','ؤ'=>'و','أ'=>'ا','إ'=>'ا','آ'=>'ا','ئ'=>'ی']);
    $text=preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{0640}\x{200C}\x{200D}]/u','',$text);
    return trim(preg_replace('/\s+/u',' ',preg_replace('/[^\p{L}\p{N}\s]/u',' ',$text)));
}
function chat_words(string $text): array {
    $stop=explode(' ','i have a an the my me is are am has had and or for of in on to with which who should visit need doctor please feel feeling it this that problem concern pain hurts hurt hurting hai hain ho mujhe mera mere meri mein main ko ka ki ke se dard please مجھے میرے میری میرا میں ہے ہیں کو کا کی کے سے درد مجھے');
    return array_values(array_unique(array_diff(explode(' ',chat_normalize($text)),$stop,[''])));
}
function chat_similarity(string $a,string $b): float {
    if($a===$b) return 1;
    // PHP levenshtein is byte-based: apply spelling tolerance to Latin words only.
    if(!preg_match('/^[a-z]{5,24}$/D',$a) || !preg_match('/^[a-z]{5,24}$/D',$b)) return 0;
    $length=max(strlen($a),strlen($b));
    if(abs(strlen($a)-strlen($b))>2) return 0;
    $distance=levenshtein($a,$b);
    return $distance===1 ? .9 : ($length>=9 && $distance===2 ? .82 : 0);
}
function chat_score(string $message,string $pattern): float {
    $pattern=chat_normalize($pattern); $wanted=chat_words($pattern); $words=chat_words($message);
    if(!$wanted || !$words) return 0;
    if(str_contains(' '.$message.' ',' '.$pattern.' ')) return .90+min(.09,count($wanted)*.03);
    $matched=0;
    foreach($wanted as $word) { $best=0; foreach($words as $candidate) $best=max($best,chat_similarity($word,$candidate)); $matched+=$best; }
    // 75% pattern coverage + 10% whole-query overlap; an exact phrase is stronger.
    return .75*$matched/count($wanted)+.10*$matched/max(count($words),count($wanted));
}
function chat_specialties(): array { static $list; return $list ??= require __DIR__.'/chatbot-specialties.php'; }
function chat_specialty_label(string $specialty): string { return language()==='ur' ? (chat_specialties()[$specialty][0]??$specialty) : $specialty; }
function chat_specialty_aliases(string $specialty): array {
    $s=chat_specialties()[$specialty]??null;
    return $s ? array_merge([$specialty,$s[0]],$s[1]) : [$specialty];
}
function chat_specialty_matches(string $registered,string $wanted): bool {
    $registered=' '.chat_normalize($registered).' ';
    foreach(chat_specialty_aliases($wanted) as $alias) if(str_contains($registered,' '.chat_normalize($alias).' ')) return true;
    return false;
}
function chat_emergency(string $message): bool {
    // Always checked before the editable bank, intent, clarification and rate limits.
    // Conservative: negated/historical mentions can still show a safety warning.
    $patterns=[
        'chest pain','chestpain','chest pressure','chest tightness','seene mein dard','seene mein shadeed dard','seena dard','سینے میں درد','سینے میں شدید درد','سینہ درد',
        'cannot breathe','can t breathe','cant breathe','difficulty breathing','breathing difficulty','shortness of breath','struggling to breathe','not breathing','choking',
        'saans nahi','sans nahi','saans lene mein mushkil','saans lene mein takleef','saans lenay mein takleef','saans ki takleef','سانس نہیں','سانس لینے میں مشکل','سانس لینے میں دشواری','سانس کی تکلیف',
        'unconscious','unresponsive','not responding','passed out','loss of consciousness','behosh','be hosh','بے ہوش','بےہوش',
        'face drooping','drooping face','slurred speech','one sided weakness','arm weakness','cannot speak','stroke','chehra tedha','bol nahi','چہرہ ٹیڑھا','بول نہیں','ایک طرف کمزوری','فالج',
        'severe bleeding','bleeding heavily','seizure','worst headache','sudden severe headache','sudden vision loss','شدید خون','اچانک شدید سر درد','دورہ پڑ',
    ];
    $normal=chat_normalize($message);
    foreach($patterns as $pattern) if(chat_score($normal,$pattern)>=.82) return true;
    return false;
}
function chat_response(string $type,string $message): array {
    return ['type'=>$type,'message'=>$message,'doctors'=>[],'choices'=>[],'disclaimer'=>t('chat.disclaimer')];
}
function chat_emergency_response(): array { return chat_response('emergency',t('chat.emergency')); }
function chat_doctors(string $specialty): array {
    $matches=[];
    // Read existing rows every request. Never copy names to the knowledge bank or a cache.
    foreach(query('SELECT id,name,specialization,qualifications,photo FROM doctors WHERE active=1 AND archived_at IS NULL ORDER BY name,id')->fetchAll() as $doctor) {
        if(!chat_specialty_matches($doctor['specialization'],$specialty)) continue;
        $matches[]=$doctor;
    }
    // Keep response size and database work bounded for a small XAMPP directory.
    $result=[];
    foreach(array_slice($matches,0,6) as $doctor) {
        $id=(int)$doctor['id']; $hours=[];
        foreach(query('SELECT * FROM weekly_schedules WHERE doctor_id=? ORDER BY weekday',[$id])->fetchAll() as $s) {
            $hours[]=weekday_label((int)$s['weekday']).': '.time_label($s['consultation_start']).' – '.time_label($s['consultation_end']);
        }
        $next=null;
        for($i=0;$i<=$GLOBALS['config']['booking_days'];$i++) {
            $date=date('Y-m-d',strtotime('+'.$i.' days'));
            $state=booking_state($id,$date);
            if($state['open']) { $next=['date'=>$date,'label'=>date_label($date),'start'=>time_label($state['schedule']['consultation_start'])]; break; }
        }
        $result[]=['id'=>$id,'name'=>$doctor['name'],'specialization'=>t($doctor['specialization']),
            'qualifications'=>$doctor['qualifications'],'photo'=>doctor_photo($doctor),'schedule'=>$hours,
            'availability'=>$next ? t('chat.next_date',['date'=>$next['label'],'time'=>$next['start']]) : t('chat.no_dates'),
            'bookable'=>$next!==null,'booking_url'=>url('book.php?doctor='.$id),
            'booking_label'=>$next ? t('Book appointment') : t('chat.view_dates')];
    }
    return $result;
}
function chat_reply(string $message,array &$context): array {
    if(mb_strlen($message)>500 || trim($message)==='') throw new DomainException(t('chat.input_limit'));
    if(chat_emergency($message)) { $context=[]; return chat_emergency_response(); }
    $normal=chat_normalize($message);
    if(($context['expires']??0)<time()) $context=[];
    // No prescriptions or diagnostic answers, even if a routing keyword also appears.
    foreach(['medicine','medication','dosage','dose','prescription','diagnose','diagnosis','antibiotic','dawai','dawa','دوائی','دوا','خوراک','تشخیص'] as $term) {
        if(str_contains(' '.$normal.' ',' '.chat_normalize($term).' ')) return chat_response('scope',t('chat.scope'));
    }
    foreach(['how to book','book appointment','booking process','appointment kaise','اپائنٹمنٹ کیسے'] as $term) {
        if(str_contains($normal,chat_normalize($term))) return chat_response('help',t('chat.booking_help'));
    }
    if(in_array($normal,['hello','hi','salam','assalam o alaikum','السلام علیکم','سلام'],true)) return chat_response('welcome',t('chat.welcome'));
    $rows=query('SELECT * FROM chatbot_knowledge WHERE active=1 ORDER BY id')->fetchAll();
    $rank=[];
    foreach($rows as $entry) {
        $score=0;
        foreach(explode("\n",$entry['patterns']) as $pattern) $score=max($score,chat_score($normal,$pattern));
        $patternScore=$score;
        if(!$entry['emergency']) foreach(chat_specialty_aliases($entry['specialty']) as $alias) $score=max($score,chat_score($normal,$alias));
        $viaSpecialty=$score>$patternScore;
        if($entry['emergency'] && $score>=.76) { $context=[]; return chat_emergency_response(); }
        if($entry['emergency']) continue;
        // Child-specific wording wins over the overlapping generic symptom example.
        $child=preg_match('/(?:^| )(child|baby|kid|children|bachay|bache|bacha|بچے|بچہ|بچی)(?: |$)/u',$normal);
        if($child && $score>=.76) $score+= $entry['specialty']==='Pediatrics' ? .16 : -.16;
        if($score>=.76 && (!isset($rank[$entry['specialty']]) || $rank[$entry['specialty']]['score']<$score || ($rank[$entry['specialty']]['score']===$score && !$viaSpecialty))) $rank[$entry['specialty']]=['entry'=>$entry,'score'=>$score,'via_specialty'=>$viaSpecialty];
    }
    uasort($rank,fn($a,$b)=>$b['score']<=>$a['score']);
    $rank=array_values($rank);
    // Context is only entry IDs + expiry, never the patient's text. Re-read enabled entries.
    if(!$rank && preg_match('/^(when|available|availability|schedule|timings|book|which doctor|yes|kab|waqt|اوقات|کب|دستیاب)/u',$normal) && !empty($context['entry_id'])) {
        foreach($rows as $entry) if((int)$entry['id']===$context['entry_id'] && !$entry['emergency']) $rank[]=['entry'=>$entry,'score'=>1];
    }
    if(!$rank) { $context=[]; return chat_response('fallback',t('chat.fallback')); }
    if(count($rank)>1 && $rank[0]['score']-$rank[1]['score']<.12) {
        $context=[];
        $reply=chat_response('clarify',t('chat.clarify'));
        foreach(array_slice($rank,0,3) as $candidate) $reply['choices'][]=['label'=>chat_specialty_label($candidate['entry']['specialty']),'message'=>$candidate['entry']['specialty']];
        return $reply;
    }
    $entry=$rank[0]['entry'];
    $context=['entry_id'=>(int)$entry['id'],'expires'=>time()+300];
    $reply=chat_response('recommendation',!empty($rank[0]['via_specialty']) ? t('chat.routing',['specialty'=>chat_specialty_label($entry['specialty'])]) : $entry['answer_'.language()]);
    $reply['specialty']=$entry['specialty'];
    $reply['doctors']=chat_doctors($entry['specialty']);
    $reply['followup']=$entry['followup_'.language()];
    $reply['notice']=$reply['doctors'] ? t('chat.availability_note') : t('chat.no_doctor');
    return $reply;
}
function chat_save_entry(array $data,int $actorId): int {
    if(!one("SELECT id FROM staff_users WHERE id=? AND role='admin' AND active=1",[$actorId])) throw new DomainException(t('chat.admin_only'));
    $id=(int)($data['id']??0);
    $limits=['question_en'=>160,'question_ur'=>160,'patterns'=>3000,'specialty'=>100,'answer_en'=>1000,'answer_ur'=>1000,'followup_en'=>200,'followup_ur'=>200];
    $values=[];
    foreach($limits as $key=>$limit) {
        if(!is_string($data[$key]??'')) throw new DomainException(t('chat.invalid_entry'));
        $value=trim($data[$key]??'');
        if(mb_strlen($value)>$limit || ($value==='' && !str_starts_with($key,'followup'))) throw new DomainException(t('chat.invalid_entry'));
        $values[$key]=$value;
    }
    $patterns=array_values(array_filter(array_map('trim',preg_split('/\R/u',$values['patterns']))));
    if(count($patterns)>30 || !$patterns) throw new DomainException(t('chat.pattern_limit'));
    foreach($patterns as $pattern) if(!chat_words($pattern) || mb_strlen($pattern)>150) throw new DomainException(t('chat.pattern_limit'));
    $values['patterns']=implode("\n",$patterns);
    $values['emergency']=isset($data['emergency'])?1:0; $values['active']=isset($data['active'])?1:0;
    if($id) {
        if(!one('SELECT id FROM chatbot_knowledge WHERE id=?',[$id])) throw new DomainException(t('chat.entry_missing'));
        query('UPDATE chatbot_knowledge SET '.implode(',',array_map(fn($key)=>$key.'=?',array_keys($values))).' WHERE id=?',array_merge(array_values($values),[$id]));
    } else {
        if((int)one('SELECT COUNT(*) n FROM chatbot_knowledge')['n']>=500) throw new DomainException(t('chat.bank_limit'));
        query('INSERT INTO chatbot_knowledge ('.implode(',',array_keys($values)).') VALUES ('.implode(',',array_fill(0,count($values),'?')).')',array_values($values));
        $id=(int)db()->lastInsertId();
    }
    return $id;
}

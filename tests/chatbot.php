<?php
if(PHP_SAPI!=='cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/includes/bootstrap.php';
require dirname(__DIR__).'/includes/chatbot.php';
require dirname(__DIR__).'/includes/migrations.php';
$testDatabase='doctor_hospital_chat_test_'.bin2hex(random_bytes(6));
$config['db_name']=$testDatabase;
$server=new PDO("mysql:host={$config['db_host']};port={$config['db_port']};charset=utf8mb4",$config['db_user'],$config['db_password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$passed=0;
function check(bool $ok,string $message): void { if(!$ok) throw new RuntimeException('FAIL: '.$message); $GLOBALS['passed']++; echo 'PASS: '.$message."\n"; }
function reply(string $message): array { $context=[]; return chat_reply($message,$context); }
try {
    $server->exec(str_replace('doctor_hospital','`'.$testDatabase.'`',file_get_contents(dirname(__DIR__).'/database/schema.sql')));
    migrate_database();
    check((int)one('SELECT COUNT(*) n FROM chatbot_knowledge')['n']===51,'51 bilingual knowledge entries seeded');
    check(one("SELECT answer_en FROM chatbot_knowledge WHERE seed_key='emergency-chest'")['answer_en']===t('chat.emergency'),'Emergency bank entries store urgent guidance, not routine routing');
    $patterns=0; foreach(query('SELECT patterns FROM chatbot_knowledge')->fetchAll() as $row) $patterns+=count(explode("\n",$row['patterns']));
    check($patterns>=200,'At least 200 English, Urdu and Roman Urdu question patterns');
    $cases=[
        'I have a headache and feel dizzy. Which doctor should I visit?'=>'General Medicine',
        'I have a toothache'=>'Dentistry','My teeth are hurting'=>'Dentistry','I need a dentist'=>'Dentistry','Which doctor treats dental pain?'=>'Dentistry',
        'I have a toothace'=>'Dentistry','My stomch hurts'=>'Gastroenterology',
        'Mere pait mein dard hai'=>'Gastroenterology','میرے پیٹ میں درد ہے۔'=>'Gastroenterology',
        'mere sar mein dard hai'=>'General Medicine','میرے سر میں درد ہے'=>'General Medicine',
        'I have itchy skin'=>'Dermatology','skin rash'=>'Dermatology','جلد پر دانے ہیں'=>'Dermatology',
        'My child has fever'=>'Pediatrics','bachay ko bukhar hai'=>'Pediatrics',
        'I need a cardiologist'=>'Cardiology','routine heart checkup'=>'Cardiology',
        'diabetes follow up'=>'Endocrinology','pregnancy checkup'=>'Gynecology',
    ];
    foreach($cases as $message=>$specialty) { $r=reply($message); check(($r['specialty']??'')===$specialty,'Routing: '.$message); }
    check(reply('What is the weather in Lahore?')['type']==='fallback','Unknown nonmedical query gets a safe fallback');
    check(reply('Give me medicine for headache')['type']==='scope','Medication query does not return treatment');
    check(reply('How to book an appointment?')['type']==='help','Booking intent explains existing workflow');
    $r=reply('toothache and skin rash'); check($r['type']==='clarify' && count($r['choices'])===2 && !$r['doctors'],'Ambiguous specialties ask one clarification without guessing');
    $emergencies=['severe chest pain','I cant breathe','difficulty breathing','face drooping and arm weakness','unconscious','seene mein dard hai','saans nahi aa rahi','سانس لینے میں مشکل ہے','سینے میں درد ہے','بے ہوش','chest pian','chest pain and toothache','No chest pain'];
    foreach($emergencies as $message) { $r=reply($message); check($r['type']==='emergency' && !$r['doctors'] && !$r['choices'],'Immediate emergency route: '.$message); }
    query('UPDATE chatbot_knowledge SET active=0 WHERE emergency=1');
    check(reply('chest pain')['type']==='emergency','Built-in emergency safety survives disabled bank entries');
    foreach(['سینے میں شدید درد ہے','seene mein shadeed dard hai','saans lenay mein takleef hai','سانس لینے میں دشواری ہے','chestpain'] as $message) {
        check(reply($message)['type']==='emergency','Built-in emergency wording without bank: '.$message);
    }
    query("INSERT INTO staff_users (name,username,password_hash,role) VALUES ('QA','chat_admin',?,'admin')",[password_hash(bin2hex(random_bytes(12)),PASSWORD_DEFAULT)]); $admin=(int)db()->lastInsertId();
    query("INSERT INTO staff_users (name,username,password_hash,role) VALUES ('QA','chat_reception',?,'receptionist')",[password_hash(bin2hex(random_bytes(12)),PASSWORD_DEFAULT)]); $reception=(int)db()->lastInsertId();
    $entry=one("SELECT * FROM chatbot_knowledge WHERE seed_key='acne'");
    $data=$entry; unset($data['emergency']); $data['answer_en']='Edited routing answer'; $data['active']=1;
    try { chat_save_entry($data,$reception); check(false,'Receptionist edit blocked'); } catch(DomainException $e) { check(true,'Receptionist cannot edit bank service'); }
    chat_save_entry($data,$admin); migrate_database();
    check(one('SELECT answer_en FROM chatbot_knowledge WHERE id=?',[$entry['id']])['answer_en']==='Edited routing answer','Repeat migration preserves edited knowledge answers');
    check((int)one('SELECT COUNT(*) n FROM chatbot_knowledge')['n']===51 && (int)one('SELECT COUNT(*) n FROM chatbot_knowledge WHERE emergency=1 AND active=1')['n']===0,'Repeat migration does not duplicate or re-enable entries');
    $r=reply('acne'); check(!$r['doctors'] && $r['notice']===t('chat.no_doctor'),'No registered specialist is reported honestly');
    query("INSERT INTO doctors (name,specialization,qualifications,phone) VALUES ('Live Skin Doctor','Dermatologist','MBBS','03001234567')"); $doctor=(int)db()->lastInsertId();
    $r=reply('acne'); check(count($r['doctors'])===1 && $r['doctors'][0]['id']===$doctor,'New doctor appears automatically via specialization alias');
    check(!$r['doctors'][0]['bookable'] && !$r['doctors'][0]['schedule'],'Unscheduled doctor does not claim availability');
    $date=date('Y-m-d',strtotime('+1 day')); $weekday=(int)date('N',strtotime($date));
    $s=['booking_open'=>'09:00','booking_close'=>'16:00','consultation_start'=>'18:30','consultation_end'=>'18:35','duration_minutes'=>5];
    save_schedule($doctor,$weekday,$s);
    $r=reply('acne'); check($r['doctors'][0]['bookable'] && str_contains($r['doctors'][0]['availability'],date_label($date)),'Live schedule creates an actual bookable date');
    check($r['doctors'][0]['booking_url']===url('book.php?doctor='.$doctor),'Card reuses existing booking workflow');
    $s['consultation_start']='19:00';$s['consultation_end']='19:05';save_schedule($doctor,$weekday,$s);
    check(str_contains(reply('acne')['doctors'][0]['availability'],'7:00 PM'),'Schedule update reflected on next response');
    $context=[]; chat_reply('acne',$context); $r=chat_reply('when available?',$context);
    check($r['specialty']==='Dermatology' && array_keys($context)===['entry_id','expires'],'Follow-up uses minimal routing context, no transcript');
    $context['expires']=time()-1; check(chat_reply('when available?',$context)['type']==='fallback','Expired context does not infer an old concern');
    manage_doctor($doctor,'archive',$admin);check(!reply('acne')['doctors'],'Archived doctor disappears immediately');
    manage_doctor($doctor,'restore',$admin);check(count(reply('acne')['doctors'])===1,'Restored doctor reappears immediately');
    query("UPDATE doctors SET specialization='Dentist' WHERE id=?",[$doctor]);check(!reply('acne')['doctors'] && reply('toothache')['doctors'][0]['id']===$doctor,'Specialization edits automatically change matching');
    query('UPDATE doctors SET active=0 WHERE id=?',[$doctor]);check(!reply('toothache')['doctors'],'Inactive doctor is excluded');query('UPDATE doctors SET active=1 WHERE id=?',[$doctor]);
    // Fill/cancel a one-slot clinic; existing booking rules decide the displayed date.
    $a=book_appointment($doctor,$date,'QA patient','03001234567','',bin2hex(random_bytes(32)));
    check(!str_contains(reply('toothache')['doctors'][0]['availability'],date_label($date)),'Full date is not advertised as bookable');
    change_status((int)$a['id'],'Cancelled');mark_unavailable($doctor,$date,'QA absence');
    check(!str_contains(reply('toothache')['doctors'][0]['availability'],date_label($date)),'Unavailable date is not advertised as bookable');
    $_SESSION['language']='ur';$r=reply('دانت میں درد');
    check(str_contains($r['message'],'دانت') && $r['doctors'][0]['name']==='Live Skin Doctor','Urdu response preserves actual doctor name');
    $_SESSION['language']='en';
    $data['patterns']='unmatchedspecialconcern';unset($data['active']);chat_save_entry($data,$admin);
    check(reply('unmatchedspecialconcern')['type']==='fallback','Disabled entry is excluded from matching');
    try { reply(str_repeat('a',501)); check(false,'Input length'); } catch(DomainException $e) { check(true,'Oversized patient input rejected'); }
    echo "\n$passed chatbot assertions passed.\n";
} catch(Throwable $e) { fwrite(STDERR,(string)$e."\n"); $failed=true; }
finally { if(preg_match('/^doctor_hospital_chat_test_[a-f0-9]{12}$/D',$testDatabase)) $server->exec('DROP DATABASE `'.$testDatabase.'`'); }
exit(!empty($failed)?1:0);

<?php
function migrate_chatbot(): void {
    db()->exec(file_get_contents(dirname(__DIR__).'/database/migrations/002_chatbot_knowledge.sql'));
    $specialties=require __DIR__.'/chatbot-specialties.php';
    $seeds=require dirname(__DIR__).'/database/chatbot-seeds.php';
    $english=require dirname(__DIR__).'/lang/chatbot-en.php';
    $urdu=require dirname(__DIR__).'/lang/chatbot-ur.php';
    db()->beginTransaction();
    try {
        foreach($seeds as [$key,$en,$ur,$specialty,$patterns]) {
            // INSERT IGNORE preserves edits and enabled/disabled state on later upgrades.
            $emergency=str_starts_with($key,'emergency-');
            $answerEn='For '.$en.', a doctor in '.$specialty.' may be a suitable starting point. A clinician can assess your concern.';
            $answerUr=$ur.' کے لیے '.$specialties[$specialty][0].' کے ڈاکٹر سے ابتدائی مشورہ لیا جا سکتا ہے۔ معالج آپ کی کیفیت کا جائزہ لے گا۔';
            if($emergency) { $answerEn=$english['chat.emergency']; $answerUr=$urdu['chat.emergency']; }
            query('INSERT IGNORE INTO chatbot_knowledge (seed_key,question_en,question_ur,patterns,specialty,answer_en,answer_ur,emergency) VALUES (?,?,?,?,?,?,?,?)',[$key,$en,$ur,str_replace('|',"\n",$patterns),$specialty,$answerEn,$answerUr,(int)$emergency]);
        }
        db()->commit();
    } catch(Throwable $e) { db()->rollBack(); throw $e; }
}

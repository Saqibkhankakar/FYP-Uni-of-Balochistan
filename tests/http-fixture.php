<?php
// Temporary fixtures for HTTP smoke tests; never accessible over HTTP.
if(PHP_SAPI!=='cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/includes/bootstrap.php';
$input=json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR);
$prefix=(string)($input['prefix']??'');
if(!preg_match('/^qa_[a-f0-9]{12}$/D',$prefix)) exit(2);
if(($input['action']??'')==='create') {
    $initial=false;
    if(!one("SELECT id FROM staff_users WHERE role='admin' LIMIT 1")) {
        $pipes=[]; $process=proc_open([PHP_BINARY,dirname(__DIR__).'/scripts/create-admin.php','--stdin'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
        fwrite($pipes[0],json_encode(['name'=>$prefix.' admin','username'=>$prefix.'_admin','password'=>$input['password']])); fclose($pipes[0]);
        $out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
        if(proc_close($process)!==0) throw new RuntimeException($err.$out);
        $initial=true;
    }
    foreach($initial?['receptionist']:['admin','receptionist'] as $role) query('INSERT INTO staff_users (name,username,password_hash,role,phone) VALUES (?,?,?,?,?)',[$prefix.' '.$role,$prefix.'_'.$role,password_hash($input['password'],PASSWORD_DEFAULT),$role,'03001234567']);
    echo json_encode(['ok'=>true,'initial_admin_tested'=>$initial]);
} elseif(($input['action']??'')==='cleanup') {
    $doctors=query('SELECT id,photo FROM doctors WHERE name IN (?,?)',[$prefix.' Doctor',$prefix.' Empty'])->fetchAll();
    foreach($doctors as $d) {
        query('DELETE FROM appointments WHERE doctor_id=?',[$d['id']]); query('DELETE FROM appointment_days WHERE doctor_id=?',[$d['id']]); query('DELETE FROM weekly_schedules WHERE doctor_id=?',[$d['id']]); query('DELETE FROM unavailable_dates WHERE doctor_id=?',[$d['id']]); query('DELETE FROM doctors WHERE id=?',[$d['id']]);
        if(preg_match('~^assets/uploads/[a-f0-9]{40}\.jpg$~',$d['photo']??'')) @unlink(dirname(__DIR__).'/'.$d['photo']);
    }
    query('DELETE FROM staff_users WHERE username IN (?,?,?)',[$prefix.'_admin',$prefix.'_receptionist',$prefix.'_created']);
    query('DELETE FROM chatbot_knowledge WHERE question_en=?',[$prefix.' routing']);
    foreach(['127.0.0.1','::1'] as $address) query('DELETE FROM rate_limits WHERE bucket=?',[hash('sha256','chatbot|'.$address)]);
    query('DELETE FROM rate_limits WHERE bucket IN (?,?)',[hash('sha256','login|127.0.0.1'),hash('sha256','booking|127.0.0.1')]);
    echo json_encode(['ok'=>true]);
}

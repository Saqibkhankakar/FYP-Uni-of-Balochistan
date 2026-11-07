<?php
// Real MySQL integration tests. Uses a randomly named disposable database only.
if(PHP_SAPI!=='cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/includes/bootstrap.php';
$testDatabase='doctor_hospital_test_'.bin2hex(random_bytes(6));
$config['db_name']=$testDatabase;
$server=new PDO("mysql:host={$config['db_host']};port={$config['db_port']};charset=utf8mb4",$config['db_user'],$config['db_password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$passed=0;
function check(bool $condition,string $message): void {
    if(!$condition) throw new RuntimeException('FAIL: '.$message);
    $GLOBALS['passed']++; echo 'PASS: '.$message."\n";
}
function rejects(callable $callback,string $message): void {
    try { $callback(); } catch(DomainException $e) { check(true,$message); return; }
    check(false,$message);
}
function booking(int $doctor,string $date,string $name='Test Patient',?string $key=null): array {
    return book_appointment($doctor,$date,$name,'03001234567','', $key??bin2hex(random_bytes(32)));
}
try {
    $server->exec(str_replace('doctor_hospital','`'.$testDatabase.'`',file_get_contents(dirname(__DIR__).'/database/schema.sql')));
    check(one('SELECT DATABASE() n')['n']===$testDatabase,'Real database connection uses isolated test database');
    query("INSERT INTO doctors (name,specialization,qualifications,phone) VALUES ('Test Doctor','Medicine','MBBS','03001234567')");
    $id=(int)db()->lastInsertId(); $date=date('Y-m-d',strtotime('+1 day')); $weekday=(int)date('N',strtotime($date));
    $schedule=['booking_open'=>'09:00','booking_close'=>'16:00','consultation_start'=>'18:30','consultation_end'=>'18:45','duration_minutes'=>5];
    save_schedule($id,$weekday,$schedule); save_schedule($id,$weekday,$schedule);
    check((int)one('SELECT COUNT(*) n FROM weekly_schedules')['n']===1,'Repeated weekday save updates without duplicate schedules');
    check(capacity($schedule)===3,'Capacity is consultation minutes divided by slot duration');
    $large=$schedule; $large['consultation_end']='21:30'; check(capacity($large)===36,'Three hours at five minutes gives 36 positions');
    save_schedule($id,(int)date('N'),$schedule);
    check(booking_state($id,today(),new DateTimeImmutable(today().' 08:59:59'))['open']===false,'Same-day booking rejected before opening');
    check(booking_state($id,today(),new DateTimeImmutable(today().' 09:00:00'))['open']===true,'Same-day booking opens at configured time');
    check(booking_state($id,today(),new DateTimeImmutable(today().' 15:59:59'))['open']===true,'Same-day booking allowed before cutoff');
    check(booking_state($id,today(),new DateTimeImmutable(today().' 16:00:00'))['open']===false,'Cutoff is exclusive at the exact closing time');
    check(booking_state($id,$date,new DateTimeImmutable(today().' 23:00:00'))['open']===true,'Advance bookings allowed outside same-day window');
    rejects(fn()=>booking($id,date('Y-m-d',strtotime('-1 day'))),'Past bookings rejected');
    rejects(fn()=>booking($id,date('Y-m-d',strtotime('+31 days'))),'Bookings beyond 30 days rejected');
    rejects(fn()=>booking($id,date('Y-m-d',strtotime('+2 days'))),'Non-working weekday rejected');
    rejects(fn()=>phone_number('12345'),'Invalid Pakistani phone rejected');
    check(phone_number('+92 300 1234567')==='03001234567','International Pakistani phone normalized');
    $key=bin2hex(random_bytes(32)); $a=booking($id,$date,'First Patient',$key); $b=booking($id,$date,'Second Patient'); $c=booking($id,$date,'Third Patient');
    check([$a['token_number'],$b['token_number'],$c['token_number']]===[1,2,3],'Tokens assigned in booking order');
    check([$a['estimated_time'],$b['estimated_time'],$c['estimated_time']]===['18:30:00','18:35:00','18:40:00'],'Estimated consultation times calculated correctly');
    check(strlen($a['confirmation_token'])===64 && $a['confirmation_token']!==$b['confirmation_token'],'Private confirmation tokens are distinct random 256-bit values');
    check(booking($id,$date,'First Patient',$key)['id']===$a['id'],'Duplicate submission returns the same appointment even at capacity');
    rejects(fn()=>booking($id,$date),'Full capacity rejected');
    rejects(fn()=>book_appointment($id,$date,'Walk In','03001234567','',bin2hex(random_bytes(32)),'walk-in'),'Walk-ins cannot exceed capacity');
    rejects(fn()=>mark_unavailable($id,$date,'Leave'),'Absence cannot silently invalidate existing patients');
    $edited=$schedule; $edited['consultation_start']='19:00'; $edited['consultation_end']='20:00'; save_schedule($id,$weekday,$edited);
    check(schedule_for($id,$date)['consultation_start']==='18:30:00','Booked date retains snapshot after weekly schedule edit');
    change_status((int)$b['id'],'Cancelled'); $replacement=booking($id,$date,'Replacement');
    check($replacement['token_number']===4 && $replacement['position_number']===2 && $replacement['estimated_time']==='18:35:00','Cancelled position reused with a new monotonic token');
    $original=one('SELECT * FROM appointments WHERE id=?',[$c['id']]);
    check($original['token_number']===3 && $original['estimated_time']==='18:40:00','Existing tokens and estimates do not move after cancellation');
    rejects(fn()=>change_status((int)$b['id'],'Arrived'),'Cancelled booking cannot reclaim an occupied position');
    try {
        query("INSERT INTO appointments (day_id,doctor_id,patient_name,patient_phone,appointment_date,token_number,position_number,estimated_time,reference,confirmation_token,submission_key) VALUES (?,?,?,?,?,?,?,?,?,?,?)",[$a['day_id'],$id,'Collision','03001234567',$date,99,1,'18:30:00','TEST-COLLISION',bin2hex(random_bytes(32)),bin2hex(random_bytes(32))]);
        check(false,'Database unique constraint rejects double booking');
    } catch(PDOException $e) { check($e->getCode()==='23000','Database unique constraint rejects double booking'); }
    change_status((int)$a['id'],'Arrived'); check(one('SELECT status FROM appointments WHERE id=?',[$a['id']])['status']==='Arrived','Queue arrival status saved');
    rejects(fn()=>change_status((int)$a['id'],'In Consultation'),'Future appointment cannot be called into consultation');
    $pastDay=today();
    query('INSERT INTO appointment_days (doctor_id,appointment_date,booking_open,booking_close,consultation_start,consultation_end,duration_minutes,capacity,next_token) VALUES (?,?,?,?,?,?,?,?,?)',[$id,$pastDay,'00:00','23:00','23:00','23:30',5,6,3]);
    $dayId=(int)db()->lastInsertId(); $ids=[];
    for($n=1;$n<=2;$n++) { query('INSERT INTO appointments (day_id,doctor_id,patient_name,patient_phone,appointment_date,token_number,position_number,estimated_time,reference,confirmation_token,submission_key) VALUES (?,?,?,?,?,?,?,?,?,?,?)',[$dayId,$id,'Queue '.$n,'03001234567',today(),$n,$n,'23:00','QUEUE-'.$n,bin2hex(random_bytes(32)),bin2hex(random_bytes(32))]); $ids[]=(int)db()->lastInsertId(); }
    change_status($ids[0],'In Consultation'); rejects(fn()=>change_status($ids[1],'In Consultation'),'Only one current consultation per doctor and date');
    change_status($ids[0],'Completed'); change_status($ids[1],'In Consultation'); check(one('SELECT status FROM appointments WHERE id=?',[$ids[1]])['status']==='In Consultation','Completing a consultation allows the next token');
    $absentDate=date('Y-m-d',strtotime('+7 days')); mark_unavailable($id,$absentDate,'Test leave'); rejects(fn()=>booking($id,$absentDate),'Doctor unavailable date rejected');
    query('UPDATE doctors SET active=0 WHERE id=?',[$id]); rejects(fn()=>booking($id,date('Y-m-d',strtotime('+8 days'))),'Inactive doctor cannot accept bookings'); query('UPDATE doctors SET active=1 WHERE id=?',[$id]);
    // Independent PHP processes exercise real concurrent transactions against one doctor/date.
    $concurrentDate=date('Y-m-d',strtotime('+8 days')); $jobs=[];
    for($i=0;$i<8;$i++) {
        $pipes=[]; $process=proc_open([PHP_BINARY,__DIR__.'/worker.php',$testDatabase,(string)$id,$concurrentDate], [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
        fclose($pipes[0]); $jobs[]=[$process,$pipes];
    }
    $tokens=[];
    foreach($jobs as [$process,$pipes]) { $out=stream_get_contents($pipes[1]); $err=stream_get_contents($pipes[2]); fclose($pipes[1]);fclose($pipes[2]); $code=proc_close($process); if($code!==0) throw new RuntimeException('Concurrency worker failed: '.$err.$out); $tokens[]=(int)trim($out); }
    sort($tokens); check($tokens===range(1,8),'Eight simultaneous requests get eight distinct sequential tokens');
    $positions=query('SELECT position_number FROM appointments WHERE doctor_id=? AND appointment_date=?',[$id,$concurrentDate])->fetchAll(PDO::FETCH_COLUMN);
    check(count(array_unique($positions))===8,'Concurrent requests cannot share consultation positions');
    save_schedule($id,$weekday,$schedule);
    $capacityDate=date('Y-m-d',strtotime('+15 days')); $jobs=[];
    for($i=0;$i<8;$i++) {
        $pipes=[]; $process=proc_open([PHP_BINARY,__DIR__.'/worker.php',$testDatabase,(string)$id,$capacityDate,'allow-full'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
        fclose($pipes[0]); $jobs[]=[$process,$pipes];
    }
    $accepted=[]; $full=0;
    foreach($jobs as [$process,$pipes]) { $out=trim(stream_get_contents($pipes[1])); $err=stream_get_contents($pipes[2]); fclose($pipes[1]);fclose($pipes[2]); if(proc_close($process)!==0) throw new RuntimeException($err); if($out==='FULL') $full++; else $accepted[]=(int)$out; }
    sort($accepted); check($accepted===[1,2,3] && $full===5,'Eight simultaneous requests for three positions accept exactly three');
    query("INSERT INTO doctors (name,specialization,qualifications,phone) VALUES ('Second Doctor','Medicine','MBBS','03001234567')"); $second=(int)db()->lastInsertId(); save_schedule($second,$weekday,$schedule);
    check(booking($second,$date)['token_number']===1,'Each doctor has an independent queue starting at one');
    $noShow=one('SELECT id FROM appointments WHERE doctor_id=? AND appointment_date=? ORDER BY token_number LIMIT 1',[$id,$capacityDate]);
    change_status((int)$noShow['id'],'No-show'); change_status((int)$noShow['id'],'Arrived');
    check(one('SELECT status FROM appointments WHERE id=?',[$noShow['id']])['status']==='Arrived','No-show patient can return without changing their position');
    $_SERVER['REMOTE_ADDR']='127.0.0.22'; rate_limit('test',2,60); rate_limit('test',2,60); rejects(fn()=>rate_limit('test',2,60),'Database-backed rate limit blocks excess attempts');
    // Safe doctor lifecycle checks use only this disposable database.
    query("INSERT INTO staff_users (name,username,password_hash,role) VALUES ('Lifecycle Admin','lifecycle_admin',?,'admin')",[password_hash(bin2hex(random_bytes(24)),PASSWORD_DEFAULT)]);
    $adminId=(int)db()->lastInsertId();
    query("INSERT INTO staff_users (name,username,password_hash,role) VALUES ('Lifecycle Reception','lifecycle_reception',?,'receptionist')",[password_hash(bin2hex(random_bytes(24)),PASSWORD_DEFAULT)]);
    $receptionId=(int)db()->lastInsertId();
    query("INSERT INTO doctors (name,specialization,qualifications,phone) VALUES ('No history','Medicine','MBBS','03001234567')");
    $emptyDoctor=(int)db()->lastInsertId(); save_schedule($emptyDoctor,$weekday,$schedule); mark_unavailable($emptyDoctor,$date,'Leave');
    rejects(fn()=>manage_doctor($emptyDoctor,'delete',$receptionId),'Receptionist cannot delete through the management service');
    rejects(fn()=>manage_doctor($emptyDoctor,'archive',$receptionId),'Receptionist cannot archive through the management service');
    rejects(fn()=>manage_doctor($emptyDoctor,'restore',$receptionId),'Receptionist cannot restore through the management service');
    manage_doctor($emptyDoctor,'delete',$adminId);
    check(!one('SELECT id FROM doctors WHERE id=?',[$emptyDoctor]) && !one('SELECT id FROM weekly_schedules WHERE doctor_id=?',[$emptyDoctor]) && !one('SELECT id FROM unavailable_dates WHERE doctor_id=?',[$emptyDoctor]),'Unused doctor and dependent schedules can be permanently deleted');
    $historyBefore=one('SELECT COUNT(*) n FROM appointments')['n'];
    rejects(fn()=>manage_doctor($second,'delete',$adminId),'Any appointment history prevents permanent doctor deletion');
    rejects(fn()=>manage_doctor($second,'archive',$adminId),'Unresolved future bookings block archiving');
    $secondBooking=one('SELECT * FROM appointments WHERE doctor_id=?',[$second]);
    change_status((int)$secondBooking['id'],'No-show');
    rejects(fn()=>manage_doctor($second,'archive',$adminId),'Future no-show cannot bypass archive protection');
    change_status((int)$secondBooking['id'],'Cancelled');
    manage_doctor($second,'archive',$adminId);
    $archived=one('SELECT * FROM doctors WHERE id=?',[$second]);
    check($archived['archived_at']!==null && $archived['active']===0,'Archiving stores archive marker and closes public booking');
    check(one('SELECT COUNT(*) n FROM appointments')['n']===$historyBefore,'Archiving does not remove patient records');
    check(one('SELECT confirmation_token FROM appointments WHERE id=?',[$secondBooking['id']])['confirmation_token']===$secondBooking['confirmation_token'],'Historical private confirmation link survives archiving');
    rejects(fn()=>booking($second,$date),'Archived doctor rejects new bookings');
    query('UPDATE doctors SET active=1 WHERE id=?',[$second]);
    rejects(fn()=>booking($second,$date),'Archive flag blocks bookings even if active is set by another writer');
    manage_doctor($second,'restore',$adminId);
    check(booking($second,$date)['token_number']===2,'Restore reopens booking while preserving token counter and cancelled history');
    require dirname(__DIR__).'/includes/migrations.php';
    $beforeMigration=query('SELECT * FROM doctors ORDER BY id')->fetchAll();
    migrate_database(); migrate_database();
    check(query('SELECT * FROM doctors ORDER BY id')->fetchAll()===$beforeMigration,'Repeated additive migration preserves existing doctor rows');
    // Simulate a populated pre-upgrade schema only inside this disposable database.
    db()->exec('ALTER TABLE doctors DROP COLUMN archived_at');
    $legacyDoctors=query('SELECT * FROM doctors ORDER BY id')->fetchAll();
    $legacyAppointments=query('SELECT * FROM appointments ORDER BY id')->fetchAll();
    migrate_database();
    $upgradedDoctors=query('SELECT * FROM doctors ORDER BY id')->fetchAll();
    foreach ($upgradedDoctors as &$row) { check($row['archived_at']===null,'New archive marker defaults to NULL for an existing doctor'); unset($row['archived_at']); } unset($row);
    check($upgradedDoctors===$legacyDoctors && query('SELECT * FROM appointments ORDER BY id')->fetchAll()===$legacyAppointments,'Migration of a populated legacy schema preserves all doctor and appointment data');
    $_SESSION['language']='ur';
    check(language()==='ur' && direction()==='rtl' && t('Full name')==='پورا نام','Urdu helper selects translated text and RTL');
    check(str_contains(short_date('2026-10-28'),'بدھ') && str_contains(short_date('2026-10-28'),'اکتوبر'),'Urdu dates translate weekday and month while preserving readable digits');
    rejects(fn()=>phone_number('123'),'Urdu validation still enforces phone rules');
    $_SESSION['language']='en';
    check(t('Full name')==='Full name' && direction()==='ltr','English helper restores original wording and LTR');
    echo "\n$passed integration assertions passed.\n";
} catch(Throwable $e) { fwrite(STDERR,(string)$e."\n"); $failed=true; }
finally {
    if(preg_match('/^doctor_hospital_test_[a-f0-9]{12}$/D',$testDatabase)) $server->exec('DROP DATABASE `'.$testDatabase.'`');
}
exit(!empty($failed)?1:0);

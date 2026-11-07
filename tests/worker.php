<?php
if(PHP_SAPI!=='cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/includes/bootstrap.php';
if(!preg_match('/^doctor_hospital_test_[a-f0-9]{12}$/D',$argv[1]??'')) exit(2);
$config['db_name']=$argv[1];
try { $a=book_appointment((int)$argv[2],$argv[3],'Concurrent patient','03001234567','',bin2hex(random_bytes(32))); echo $a['token_number']; }
catch(DomainException $e) { if(($argv[4]??'')==='allow-full' && str_contains($e->getMessage(),'Fully Booked')) { echo 'FULL'; exit; } fwrite(STDERR,(string)$e); exit(1); }
catch(Throwable $e) { fwrite(STDERR,(string)$e); exit(1); }

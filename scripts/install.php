<?php
// CLI only. No web installer or permanent default credentials.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/includes/bootstrap.php';
try {
    foreach (['pdo_mysql','mbstring','fileinfo','gd'] as $extension) if (!extension_loaded($extension)) throw new RuntimeException("Enable PHP extension: $extension");
    if (!preg_match('/^[a-zA-Z0-9_]+$/D',$config['db_name'])) throw new RuntimeException('Use only letters, numbers and underscores in the database name.');
    $pdo=new PDO("mysql:host={$config['db_host']};port={$config['db_port']};charset=utf8mb4",$config['db_user'],$config['db_password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $sql=file_get_contents(dirname(__DIR__).'/database/schema.sql');
    $sql=str_replace('doctor_hospital','`'.$config['db_name'].'`',$sql);
    $pdo->exec($sql);
    require dirname(__DIR__).'/includes/migrations.php';
    migrate_database();
    echo "Database schema installed. Existing data was preserved.\n";
    if(in_array('--demo',$argv,true)) {
        db()->exec(file_get_contents(dirname(__DIR__).'/database/demo_data.sql'));
        // PHP generates private secrets and dates. Plain SQL imports never use predictable confirmation links.
        $doctor=one("SELECT id FROM doctors WHERE name='Dr. Ahmed Khan' ORDER BY id LIMIT 1");
        $date=null;
        foreach(upcoming_dates((int)$doctor['id']) as $candidate=>$state) if($candidate>today() && $state['open']) { $date=$candidate; break; }
        if($date && !one("SELECT id FROM appointments WHERE source='demo' LIMIT 1")) {
            foreach([['Ali Khan','03001234567'],['Bilal Ahmed','03011234567'],['Usman Khan','03021234567'],['Hamza Ali','03031234567']] as [$name,$phone]) {
                book_appointment((int)$doctor['id'],$date,$name,$phone,'Fictional demonstration appointment',bin2hex(random_bytes(32)),'demo');
            }
            echo "Four fictional demonstration appointments created for $date.\n";
        }
        echo "Fictional doctors and weekly schedules installed. No staff passwords were created.\n";
    }
    echo "Next: create the first administrator with scripts/create-admin.ps1 (Windows) or scripts/create-admin.php.\n";
} catch(Throwable $e) { fwrite(STDERR,'Setup failed: '.$e->getMessage()."\n"); exit(1); }

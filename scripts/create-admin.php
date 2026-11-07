<?php
if(PHP_SAPI!=='cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/includes/bootstrap.php';
try {
    if(one("SELECT id FROM staff_users WHERE role='admin' LIMIT 1")) throw new RuntimeException('An administrator already exists. This first-admin setup is closed.');
    if(in_array('--stdin',$argv,true)) {
        $input=json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR);
    } else {
        if(PHP_OS_FAMILY==='Windows') throw new RuntimeException('Use powershell -ExecutionPolicy Bypass -File scripts/create-admin.ps1 so the password is masked.');
        $input=[];
        echo 'Administrator name: '; $input['name']=trim(fgets(STDIN));
        echo 'Username: '; $input['username']=trim(fgets(STDIN));
        echo 'Password (12+ characters, hidden): '; system('stty -echo');
        try { $input['password']=rtrim(fgets(STDIN),"\r\n"); } finally { system('stty echo'); echo "\n"; }
    }
    $name=required_text((string)($input['name']??''),'Name'); $username=trim((string)($input['username']??'')); $password=(string)($input['password']??'');
    if(!preg_match('/^[a-zA-Z0-9._-]{3,80}$/D',$username)) throw new RuntimeException('Username must contain 3–80 letters, numbers, dots, underscores or hyphens.');
    if(strlen($password)<12 || strlen($password)>72) throw new RuntimeException('Password must be 12–72 bytes.');
    query("INSERT INTO staff_users (name,username,password_hash,role) VALUES (?,?,?,'admin')",[$name,$username,password_hash($password,PASSWORD_DEFAULT)]);
    unset($password,$input);
    echo "Administrator created. Sign in at /login.php?role=admin using the credentials you chose.\n";
} catch(Throwable $e) { fwrite(STDERR,$e->getMessage()."\n"); exit(1); }

<?php
require __DIR__.'/includes/bootstrap.php';
require __DIR__.'/includes/chatbot.php';
header('Content-Type: application/json; charset=utf-8');
function chat_json(array $data,int $status=200): never {
    http_response_code($status);
    echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
if($_SERVER['REQUEST_METHOD']!=='POST') { header('Allow: POST'); chat_json(['error'=>t('chat.post_only')],405); }
if(!is_string($_POST['csrf']??null) || !hash_equals(csrf(),$_POST['csrf'])) chat_json(['error'=>t('chat.expired')],403);
if((int)($_SERVER['CONTENT_LENGTH']??0)>8192) chat_json(['error'=>t('chat.input_limit')],413);
if(($_POST['action']??'')==='reset') { unset($_SESSION['chat_context']); chat_json(['ok'=>true]); }
$message=$_POST['message']??null;
if(!is_string($message) || !mb_check_encoding($message,'UTF-8') || trim($message)==='' || mb_strlen($message)>500) chat_json(['error'=>t('chat.input_limit')],422);
// Fixed emergency rules remain usable if the bank/database is unavailable or rate-limited.
if(chat_emergency($message)) { unset($_SESSION['chat_context']); chat_json(chat_emergency_response()); }
try { rate_limit('chatbot',20,60); }
catch(DomainException $e) { header('Retry-After: 60'); chat_json(['error'=>t('chat.rate_limit')],429); }
catch(Throwable $e) { chat_json(['error'=>t('chat.error')],503); }
try {
    $context=$_SESSION['chat_context']??[];
    $reply=chat_reply($message,$context);
    $_SESSION['chat_context']=$context;
    chat_json($reply);
} catch(Throwable $e) {
    // Do not log submitted messages or exceptions containing SQL arguments.
    error_log('Hospital assistant unavailable: '.get_class($e));
    chat_json(['error'=>t('chat.error')],503);
}

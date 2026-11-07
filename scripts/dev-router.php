<?php
// Optional local PHP server router. XAMPP uses the included .htaccess files instead.
$path=rawurldecode(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH) ?? '/');
if(preg_match('~(^|/)(\.|includes/|lang/|database/|scripts/|tests/|config[^/]*\.php|README\.md|TESTING\.md|UPGRADE\.md|CHATBOT\.md)~i',$path) || str_contains($path,'..')) { http_response_code(404); exit('Not found'); }
$file=realpath(dirname(__DIR__).$path);
if($file && str_starts_with($file,dirname(__DIR__).DIRECTORY_SEPARATOR) && is_file($file)) return false;
if($path==='/') { require dirname(__DIR__).'/index.php'; return true; }
http_response_code(404); echo 'Not found';

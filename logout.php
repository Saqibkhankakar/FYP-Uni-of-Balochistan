<?php
require __DIR__ . '/includes/bootstrap.php';
if ($_SERVER['REQUEST_METHOD']!=='POST') { http_response_code(405); header('Allow: POST'); exit; }
verify_csrf();
$selectedLanguage=language();
$_SESSION=['language'=>$selectedLanguage];
session_regenerate_id(true);
redirect('login.php');

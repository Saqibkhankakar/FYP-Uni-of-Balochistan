<?php
if(PHP_SAPI!=='cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/includes/bootstrap.php';
$en=require dirname(__DIR__).'/lang/en.php';
$ur=require dirname(__DIR__).'/lang/ur.php';
$errors=[]; $references=0;
if(array_keys($en)!==array_keys($ur)) $errors[]='Catalogue keys/order differ.';
foreach($en as $key=>$text) {
    preg_match_all('/\{[a-z_]+\}/',$text,$english); preg_match_all('/\{[a-z_]+\}/',$ur[$key]??'',$urdu);
    sort($english[0]); sort($urdu[0]);
    if($english[0]!==$urdu[0]) $errors[]='Placeholder mismatch: '.$key;
    if(trim($ur[$key]??'')==='') $errors[]='Empty Urdu translation: '.$key;
}
$iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__),FilesystemIterator::SKIP_DOTS));
foreach($iterator as $file) {
    if($file->getExtension()!=='php' || str_contains($file->getPathname(),DIRECTORY_SEPARATOR.'tests'.DIRECTORY_SEPARATOR) || str_contains($file->getPathname(),DIRECTORY_SEPARATOR.'lang'.DIRECTORY_SEPARATOR)) continue;
    $tokens=token_get_all(file_get_contents($file->getPathname()));
    foreach($tokens as $i=>$token) {
        if(!is_array($token)||$token[0]!==T_STRING||$token[1]!=='t') continue;
        $j=$i+1; while(isset($tokens[$j])&&is_array($tokens[$j])&&$tokens[$j][0]===T_WHITESPACE)$j++;
        if(($tokens[$j]??null)!=='(')continue;
        $j++; while(isset($tokens[$j])&&is_array($tokens[$j])&&$tokens[$j][0]===T_WHITESPACE)$j++;
        if(is_array($tokens[$j]??null)&&$tokens[$j][0]===T_CONSTANT_ENCAPSED_STRING) {
            $raw=$tokens[$j][1]; if($raw[0]!=="'")continue;
            $key=str_replace(["\\'","\\\\"],["'","\\"],substr($raw,1,-1)); $references++;
            if(!array_key_exists($key,$en)||!array_key_exists($key,$ur)) $errors[]=$file->getFilename().': missing '.$key;
        }
    }
}
if($errors) { fwrite(STDERR,implode("\n",$errors)."\n");exit(1); }
echo count($en)." bilingual entries validated; $references literal translation references covered; parameter placeholders match.\n";

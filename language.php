<?php
require __DIR__ . '/includes/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); header('Allow: POST'); exit; }
verify_csrf();
$lang = (string)($_POST['language'] ?? '');
if (!in_array($lang, ['en','ur'], true)) { http_response_code(422); exit(e(t('Choose English or Urdu.'))); }
$return = language_return_path((string)($_POST['return'] ?? url('/')));
$parts = parse_url($return);
if (($parts['path'] ?? '') === url('book.php') && isset($_POST['booking_draft'])) {
    parse_str($parts['query'] ?? '', $params);
    $doctorId = (int)($params['doctor'] ?? 0);
    $draft = json_decode((string)$_POST['booking_draft'], true);
    if ($doctorId > 0 && is_array($draft)) {
        $safe = [];
        foreach (['name'=>100,'phone'=>20,'reason'=>500,'date'=>10,'submission_key'=>64] as $field=>$max) {
            if (isset($draft[$field]) && is_string($draft[$field])) $safe[$field] = mb_substr($draft[$field],0,$max);
        }
        $_SESSION['booking_drafts'][$doctorId] = $safe;
        // Bound memory without putting patient details in URLs or browser storage.
        if (count($_SESSION['booking_drafts']) > 10) unset($_SESSION['booking_drafts'][array_key_first($_SESSION['booking_drafts'])]);
    }
}
$_SESSION['language'] = $lang;
header('Location: ' . $return, true, 303);
exit;

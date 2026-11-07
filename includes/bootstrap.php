<?php
declare(strict_types=1);
$config = require dirname(__DIR__) . '/config.php';
date_default_timezone_set($config['timezone']);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/i18n.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/booking.php';
require_once __DIR__ . '/doctor-management.php';
if (PHP_SAPI !== 'cli') {
    ini_set('session.use_strict_mode', '1');
    session_name('doctor_hospital_session');
    session_set_cookie_params(['httponly' => true, 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'samesite' => 'Lax', 'path' => ($config['base_url'] ?: '') . '/']);
    session_start();
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: no-referrer');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
    header('Cache-Control: no-store');
    set_exception_handler(function (Throwable $e) {
        error_log((string)$e);
        http_response_code(500);
        // The error page must not call staff() or query the database again.
        $errorTitle=t('We couldn’t complete that request.');
        $errorMessage=t('Please try again. For a new installation, follow the database setup steps in README.md and check your local database settings.');
        require __DIR__ . '/error-page.php';
    });
}

<?php
// Copy config.example.php to config.local.php to override these local XAMPP defaults.
$config = [
    'db_host' => '127.0.0.1', 'db_port' => '3306', 'db_name' => 'doctor_hospital',
    'db_user' => 'root', 'db_password' => '', 'base_url' => '/doctor-hospital',
    'timezone' => 'Asia/Karachi', 'booking_days' => 30,
];
if (is_file(__DIR__ . '/config.local.php')) {
    $config = array_replace($config, require __DIR__ . '/config.local.php');
}
return $config;

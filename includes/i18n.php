<?php
// English source phrases are stable keys; parameterized messages use named keys.
function language(): string { return ($_SESSION['language'] ?? 'en') === 'ur' ? 'ur' : 'en'; }
function direction(): string { return language() === 'ur' ? 'rtl' : 'ltr'; }
function t(string $key, array $parameters = []): string {
    static $catalogues = [];
    $lang = language();
    $catalogues[$lang] ??= require dirname(__DIR__) . '/lang/' . $lang . '.php';
    $text = $catalogues[$lang][$key] ?? $key;
    foreach ($parameters as $name=>$value) $text = str_replace('{' . $name . '}', (string)$value, $text);
    return $text;
}
function ltr(string $value): string { return language() === 'ur' ? "\u{2066}" . $value . "\u{2069}" : $value; }
function short_date(string $date): string {
    $stamp = strtotime($date);
    if (language() === 'en') return date('D, d M', $stamp);
    return weekday_label((int)date('N', $stamp)) . '، ' . ltr(date('d', $stamp)) . ' ' . t(date('F', $stamp));
}
function weekday_label(int $weekday, bool $short = false): string {
    $name = weekdays()[$weekday];
    return language() === 'ur' ? t($name) : ($short ? substr($name, 0, 3) : $name);
}
function language_return_path(string $path): string {
    $parts = parse_url($path);
    $base = rtrim($GLOBALS['config']['base_url'], '/');
    if ($parts === false || isset($parts['scheme']) || isset($parts['host']) || str_contains($path, "\r") || str_contains($path, "\n") || str_contains($path, '\\')) return url('/');
    $relative = substr($parts['path'] ?? '', strlen($base));
    $allowed = ['/','/index.php','/doctors.php','/book.php','/appointment.php','/login.php','/admin/index.php','/admin/doctors.php','/admin/schedules.php','/admin/receptionists.php','/admin/chatbot.php','/reception/index.php','/reception/queue.php','/reception/print.php'];
    if (!str_starts_with($parts['path'] ?? '', $base . '/') || !in_array($relative, $allowed, true)) return url('/');
    return $base . $relative . (isset($parts['query']) ? '?' . $parts['query'] : '');
}

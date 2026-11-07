<?php
function e(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function url(string $path = ''): string { return rtrim($GLOBALS['config']['base_url'], '/') . '/' . ltrim($path, '/'); }
function redirect(string $path): never { header('Location: ' . url($path), true, 303); exit; }
function today(): string { return date('Y-m-d'); }
function time_label(string $time): string { return ltr(date('g:i A', strtotime($time))); }
function date_label(string $date): string { return short_date($date) . ' ' . ltr(date('Y', strtotime($date))); }
function weekdays(): array { return [1=>'Monday',2=>'Tuesday',3=>'Wednesday',4=>'Thursday',5=>'Friday',6=>'Saturday',7=>'Sunday']; }
function csrf(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(32)); }
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . e(csrf()) . '">'; }
function verify_csrf(): void {
    if (!is_string($_POST['csrf'] ?? null) || !hash_equals(csrf(), $_POST['csrf'])) {
        // Apache needs an explicit reason phrase for this nonstandard status.
        header('HTTP/1.1 419 Page Expired'); exit(t('Your form has expired. Go back, refresh the page, and try again.'));
    }
}
function flash(string $message): void { $_SESSION['flash'] = $message; }
function valid_date(string $date): bool {
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}
function phone_number(string $phone): string {
    $phone = preg_replace('/[\s()\-]/', '', trim($phone));
    if (str_starts_with($phone, '+92')) $phone = '0' . substr($phone, 3);
    elseif (str_starts_with($phone, '0092')) $phone = '0' . substr($phone, 4);
    if (!preg_match('/^03[0-9]{9}$/D', $phone)) throw new DomainException(t('Enter a valid Pakistani mobile number, such as 03001234567.'));
    return $phone;
}
function required_text(string $value, string $label, int $max = 100): string {
    $value = trim($value);
    if ($value === '' || mb_strlen($value) > $max) throw new DomainException(t('validation.required', ['label'=>t($label),'max'=>$max]));
    return $value;
}
function status_badge(string $status): string { return '<span class="status status-' . e(strtolower(str_replace(' ', '-', $status))) . '">' . e(t($status)) . '</span>'; }
function doctor_photo(array $doctor): string { return url($doctor['photo'] ?: 'assets/images/doctor-placeholder.svg'); }
function rate_limit(string $action, int $max, int $seconds): void {
    // REMOTE_ADDR is intentional: never trust a user-supplied forwarding header.
    $key = hash('sha256', $action . '|' . ($_SERVER['REMOTE_ADDR'] ?? 'local'));
    query('DELETE FROM rate_limits WHERE expires_at < NOW()');
    query('INSERT INTO rate_limits (bucket, attempts, expires_at) VALUES (?, 1, DATE_ADD(NOW(), INTERVAL ? SECOND)) ON DUPLICATE KEY UPDATE attempts=attempts+1', [$key, $seconds]);
    if ((int)one('SELECT attempts FROM rate_limits WHERE bucket=?', [$key])['attempts'] > $max) {
        throw new DomainException(t('Too many attempts. Please wait a few minutes before trying again.'));
    }
}
function upload_photo(?array $file): ?string {
    if (!$file || $file['error'] === UPLOAD_ERR_NO_FILE) return null;
    if ($file['error'] !== UPLOAD_ERR_OK || $file['size'] > 2 * 1024 * 1024) throw new DomainException(t('Upload a JPG, PNG or WebP photo smaller than 2 MB.'));
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $extensions = ['image/jpeg'=>'jpg', 'image/png'=>'png', 'image/webp'=>'webp'];
    $size = getimagesize($file['tmp_name']);
    if (!isset($extensions[$mime]) || !$size || $size[0] > 4000 || $size[1] > 4000) throw new DomainException(t('Use a valid JPG, PNG or WebP image, up to 4000 × 4000 pixels.'));
    // Re-encode the upload so metadata and embedded non-image payloads are discarded.
    $image = imagecreatefromstring(file_get_contents($file['tmp_name']));
    if (!$image) throw new DomainException(t('This image could not be read.'));
    $path = 'assets/uploads/' . bin2hex(random_bytes(20)) . '.jpg';
    $ok = imagejpeg($image, dirname(__DIR__) . '/' . $path, 88);
    imagedestroy($image);
    if (!$ok) throw new DomainException(t('Photo could not be saved. Check the uploads folder permissions.'));
    return $path;
}

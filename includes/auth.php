<?php
function staff(): ?array {
    if (empty($_SESSION['staff_id'])) return null;
    $user = one('SELECT id,name,username,role,active FROM staff_users WHERE id=? AND active=1', [$_SESSION['staff_id']]);
    if (!$user || time() - ($_SESSION['last_seen'] ?? 0) > 7200) {
        unset($_SESSION['staff_id']); return null;
    }
    $_SESSION['last_seen'] = time();
    return $user;
}
function require_role(string ...$roles): array {
    $user = staff();
    if (!$user) redirect('login.php');
    if (!in_array($user['role'], $roles, true)) { http_response_code(403); exit(t('You do not have permission to access this page.')); }
    return $user;
}
function login_staff(string $username, string $password, string $role): bool {
    $user = one('SELECT * FROM staff_users WHERE username=?', [trim($username)]);
    // Fixed dummy hash avoids a fast exit for unknown usernames; it is not a staff password.
    $hash = $user['password_hash'] ?? '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
    $valid = password_verify($password, $hash);
    if (!$valid || !$user || !$user['active'] || $user['role'] !== $role) return false;
    session_regenerate_id(true);
    $_SESSION['staff_id'] = $user['id'];
    $_SESSION['last_seen'] = time();
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
    if (password_needs_rehash($hash, PASSWORD_DEFAULT)) query('UPDATE staff_users SET password_hash=? WHERE id=?', [password_hash($password, PASSWORD_DEFAULT), $user['id']]);
    return true;
}

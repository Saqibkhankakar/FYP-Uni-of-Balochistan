<?php
require __DIR__ . '/includes/bootstrap.php';
$role=($_GET['role'] ?? $_POST['role'] ?? '')==='admin' ? 'admin' : 'receptionist';
$error=null;
if ($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf();
    try { rate_limit('login', 15, 900); if (!login_staff((string)($_POST['username'] ?? ''),(string)($_POST['password'] ?? ''),$role)) throw new DomainException(t('Unable to sign in. Check your credentials, selected role, and account status.')); redirect($role==='admin' ? 'admin/index.php' : 'reception/index.php'); }
    catch (DomainException $e) { $error=$e->getMessage(); }
}
$title=t('Staff login'); require __DIR__ . '/includes/header.php';
?>
<main id="main" class="login-wrap container"><section class="login-card panel"><span class="brand-mark mb-4">+</span><div class="eyebrow"><?= e(t('DOCTOR HOSPITAL · STAFF PORTAL')) ?></div><h1><?= e(t('Welcome back.')) ?></h1><p class="muted"><?= e(t('Sign in to keep care moving.')) ?></p><div class="login-tabs"><a class="<?= $role==='receptionist'?'active':'' ?>" href="?role=receptionist"><?= e(t('Receptionist')) ?></a><a class="<?= $role==='admin'?'active':'' ?>" href="?role=admin"><?= e(t('Super Admin')) ?></a></div><?php if($error): ?><div class="alert alert-danger" role="alert"><?= e($error) ?></div><?php endif; ?><form method="post"><?= csrf_field() ?><input type="hidden" name="role" value="<?= e($role) ?>"><label for="username" class="form-label"><?= e(t('Username')) ?></label><input class="form-control mb-3" id="username" name="username" autocomplete="username" required maxlength="80" value="<?= e($_POST['username'] ?? '') ?>"><label for="password" class="form-label"><?= e(t('Password')) ?></label><input class="form-control mb-4" id="password" type="password" name="password" autocomplete="current-password" required><button class="btn btn-primary w-100"><?= e(t('auth.sign_in',['role'=>t($role==='admin'?'Super Admin':'Receptionist')])) ?></button></form><p class="small muted mt-4 mb-0"><?= e(t('Patients don’t need an account.')) ?> <a href="<?= e(url('doctors.php')) ?>"><?= e(t('Book an appointment')) ?></a></p></section></main><?php require __DIR__ . '/includes/footer.php'; ?>

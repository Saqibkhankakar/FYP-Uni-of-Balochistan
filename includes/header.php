
<?php
$currentStaff = staff();

$isStaffPage =
    str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/admin/') ||
    str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/reception/');
?>

<!doctype html>
<html lang="<?= language() ?>" dir="<?= direction() ?>">

<head>
    <meta charset="utf-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1">

    <meta name="theme-color" content="#791f35">

    <title>
        <?= e($title ?? t('Care that puts you first')) ?>
        <?= e(t('· Al-Shifa Hospital')) ?>
    </title>

    <!-- Favicon -->
    <link
        rel="icon"
        href="<?= e(url('assets/images/favicon.svg')) ?>"
        type="image/svg+xml"
    >

    <!-- Bootstrap CSS -->
    <link
        rel="stylesheet"
        href="<?= e(url(
            language() === 'ur'
                ? 'assets/vendor/bootstrap.rtl.min.css'
                : 'assets/vendor/bootstrap.min.css'
        )) ?>"
    >

    <!-- Custom CSS -->
    <link
        rel="stylesheet"
        href="<?= e(url('assets/css/style.css')) ?>"
    >

    <link
        rel="stylesheet"
        href="<?= e(url('assets/css/rtl.css')) ?>"
    >

    <!-- Bootstrap JavaScript -->
    <script
        defer
        src="<?= e(url('assets/vendor/bootstrap.bundle.min.js')) ?>">
    </script>

    <!-- Main JavaScript -->
    <script
        defer
        src="<?= e(url('assets/js/app.js')) ?>">
    </script>

    <!-- Chatbot Assets -->
    <?php if (
        !$isStaffPage &&
        in_array(
            basename($_SERVER['SCRIPT_NAME'] ?? ''),
            ['index.php', 'doctors.php', 'book.php'],
            true
        )
    ): ?>

        <link
            rel="stylesheet"
            href="<?= e(url('assets/css/chatbot.css')) ?>"
        >

        <script
            defer
            src="<?= e(url('assets/js/chatbot.js')) ?>">
        </script>

    <?php endif; ?>

</head>

<body
    class="<?= $isStaffPage ? 'staff-body' : '' ?>"
    data-required-message="<?= e(t('Please fill in this field.')) ?>"
    data-invalid-message="<?= e(t('Please check this value.')) ?>"
>

    <!-- Language Selection Bar -->
    <div class="language-bar no-print">
        <div class="container">

            <form
                method="post"
                action="<?= e(url('language.php')) ?>"
                class="language-switcher"
                aria-label="<?= e(t('Choose language')) ?>"
            >

                <?= csrf_field() ?>

                <input
                    type="hidden"
                    name="return"
                    value="<?= e(language_return_path(
                        $_SERVER['REQUEST_URI'] ?? url('/')
                    )) ?>"
                >

                <!-- English -->
                <button
                    type="submit"
                    name="language"
                    value="en"
                    lang="en"
                    dir="ltr"
                    aria-pressed="<?= language() === 'en' ? 'true' : 'false' ?>"
                >
                    English
                </button>

                <span aria-hidden="true">|</span>

                <!-- Urdu -->
                <button
                    type="submit"
                    name="language"
                    value="ur"
                    lang="ur"
                    dir="rtl"
                    aria-pressed="<?= language() === 'ur' ? 'true' : 'false' ?>"
                >
                    اردو
                </button>

            </form>
        </div>
    </div>

    <!-- Skip Navigation -->
    <a class="skip-link" href="#main">
        <?= e(t('Skip to content')) ?>
    </a>

    <!-- Top Information Bar -->
    <div class="topbar no-print">
        <div class="container d-flex justify-content-between gap-3">

            <span>
                <?= e(t('Thoughtful care. Closer to you.')) ?>
            </span>

            <span>
                <?= e(t('Pakistan')) ?>

                <span class="topbar-dot">•</span>

                <?= e(t('All timings in PKT')) ?>
            </span>

        </div>
    </div>

    <!-- Main Header -->
    <header class="site-header no-print">

        <nav
            class="navbar navbar-expand-lg container"
            aria-label="<?= e(t('Main navigation')) ?>"
        >

            <!-- Brand -->
            <a class="brand" href="<?= e(url('/')) ?>">

                <span class="brand-mark">+</span>

                <span>
                    <?= e(t('Al-Shifa Hospital')) ?>

                    <small>
                        <?= e(t('Patel Road, Quetta')) ?>
                    </small>
                </span>

            </a>

            <!-- Mobile Navigation Toggle -->
            <button
                class="navbar-toggler"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#mainNav"
                aria-controls="mainNav"
                aria-expanded="false"
                aria-label="<?= e(t('Toggle navigation')) ?>"
            >
                <span class="navbar-toggler-icon"></span>
            </button>

            <!-- Navigation Links -->
            <div class="collapse navbar-collapse" id="mainNav">

                <div class="navbar-nav ms-auto align-items-lg-center gap-lg-4">

                    <a
                        class="nav-link"
                        href="<?= e(url('/')) ?>"
                    >
                        <?= e(t('Home')) ?>
                    </a>

                    <a
                        class="nav-link"
                        href="<?= e(url('doctors.php')) ?>"
                    >
                        <?= e(t('Our doctors')) ?>
                    </a>

                    <a
                        class="nav-link"
                        href="<?= e(url('index.php#how-it-works')) ?>"
                    >
                        <?= e(t('How to book')) ?>
                    </a>

                    <!-- Logged-in Staff -->
                    <?php if ($currentStaff): ?>

                        <a
                            class="btn btn-outline-primary btn-sm"
                            href="<?= e(url(
                                $currentStaff['role'] === 'admin'
                                    ? 'admin/index.php'
                                    : 'reception/index.php'
                            )) ?>"
                        >
                            <?= e(t('Staff dashboard')) ?>
                        </a>

                        <form
                            method="post"
                            action="<?= e(url('logout.php')) ?>"
                        >

                            <?= csrf_field() ?>

                            <button
                                class="nav-link"
                                type="submit"
                            >
                                <?= e(t('Sign out')) ?>
                            </button>

                        </form>

                    <!-- Public Visitors -->
                    <?php else: ?>

                        <a
                            class="nav-link staff-link"
                            href="<?= e(url('login.php')) ?>"
                        >
                            <?= e(t('Staff login ↗')) ?>
                        </a>

                        <a
                            class="btn btn-primary btn-sm"
                            href="<?= e(url('doctors.php')) ?>"
                        >
                            <?= e(t('Book an appointment')) ?>
                        </a>

                    <?php endif; ?>

                </div>
            </div>

        </nav>
    </header>

    <!-- Staff Navigation -->
    <?php if ($isStaffPage && $currentStaff): ?>

        <div class="staff-nav no-print">

            <div class="container d-flex flex-wrap align-items-center gap-2">

                <!-- Staff Role -->
                <span class="staff-label">
                    <?= e(t(
                        $currentStaff['role'] === 'admin'
                            ? 'SUPER ADMIN'
                            : 'RECEPTION'
                    )) ?>
                </span>

                <!-- Super Admin Navigation -->
                <?php if ($currentStaff['role'] === 'admin'): ?>

                    <a href="<?= e(url('admin/index.php')) ?>">
                        <?= e(t('Overview')) ?>
                    </a>

                    <a href="<?= e(url('admin/doctors.php')) ?>">
                        <?= e(t('Doctors')) ?>
                    </a>

                    <a href="<?= e(url('admin/schedules.php')) ?>">
                        <?= e(t('Schedules')) ?>
                    </a>

                    <a href="<?= e(url('admin/receptionists.php')) ?>">
                        <?= e(t('Receptionists')) ?>
                    </a>

                    <a href="<?= e(url('admin/chatbot.php')) ?>">
                        <?= e(t('chat.bank')) ?>
                    </a>

                <?php endif; ?>

                <!-- Reception Navigation -->
                <a href="<?= e(url('reception/index.php')) ?>">
                    <?= e(t('Patient queues')) ?>
                </a>

                <!-- Current Staff Name -->
                <span class="ms-auto small muted">
                    <?= e($currentStaff['name']) ?>
                </span>

            </div>
        </div>

    <?php endif; ?>

    <!-- Flash Success Message -->
    <?php if (!empty($_SESSION['flash'])): ?>

        <div class="container mt-3 no-print">

            <div class="alert alert-success" role="status">
                <?= e($_SESSION['flash']) ?>
            </div>

        </div>

        <?php unset($_SESSION['flash']); ?>

    <?php endif; ?>


<?php
require __DIR__ . '/includes/bootstrap.php';

$title = t('Care that puts you first');

$doctors = query(
    'SELECT * FROM doctors
     WHERE active = 1 AND archived_at IS NULL
     ORDER BY id
     LIMIT 3'
)->fetchAll();

require __DIR__ . '/includes/header.php';
?>

<main id="main">

    <!-- Hero Section -->
    <section class="hero">
        <div class="container">
            <div class="row align-items-center g-5">

                <div class="col-lg-6">
                    <div class="eyebrow hero-eyebrow">
                        <span class="live-dot"></span>
                        <?= e(t('YOUR HEALTH, OUR PRIORITY')) ?>
                    </div>

                    <h1>
                        <?= e(t('Good care begins')) ?><br>
                        <?= e(t('with a')) ?>
                        <em><?= e(t('simple step.')) ?></em>
                    </h1>

                    <p class="hero-copy">
                        <?= e(t('Find your doctor. Choose your day. Get your token.')) ?>
                        <br class="d-none d-md-block">
                        <?= e(t('A more comfortable hospital visit starts right here.')) ?>
                    </p>

                    <div class="d-flex flex-wrap gap-3">
                        <a href="<?= e(url('doctors.php')) ?>"
                           class="btn btn-primary btn-lg">
                            <?= e(t('Find a doctor')) ?>
                            <span aria-hidden="true">↗</span>
                        </a>

                        <a href="#how-it-works"
                           class="btn btn-light btn-lg">
                            <?= e(t('How it works')) ?>
                            <span aria-hidden="true">↓</span>
                        </a>
                    </div>

                    <div class="hero-reassurance">
                        <span class="check-circle">✓</span>
                        <?= e(t('No account needed')) ?>

                        <span class="check-circle">✓</span>
                        <?= e(t('Your own queue token')) ?>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="hero-art">
                        <span class="art-kicker">
                            <?= e(t('CARE WITH A HUMAN TOUCH')) ?>
                        </span>

                        <img
                            src="<?= e(url('assets/images/care.svg')) ?>"
                            alt="<?= e(t('Illustration of a doctor ready to welcome a patient')) ?>"
                            width="560"
                            height="430"
                        >

                        <div class="care-note">
                            <span class="note-icon">✓</span>

                            <div>
                                <strong>
                                    <?= e(t('Your visit, made simpler.')) ?>
                                </strong>

                                <small>
                                    <?= e(t('A clear token. A little less waiting.')) ?>
                                </small>
                            </div>
                        </div>

                        <span class="art-caption">
                            <?= e(t('A little planning. A lot more peace of mind.')) ?>
                        </span>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <!-- Trust Strip -->
    <section class="trust-strip">
        <div class="container">

            <div>
                <span>01</span>
                <strong>
                    <?= e(t('Doctors you can meet')) ?>
                </strong>
                <small>
                    <?= e(t('Browse specialties & schedules')) ?>
                </small>
            </div>

            <div>
                <span>02</span>
                <strong>
                    <?= e(t('Booking without the hassle')) ?>
                </strong>
                <small>
                    <?= e(t('Just your name & mobile number')) ?>
                </small>
            </div>

            <div>
                <span>03</span>
                <strong>
                    <?= e(t('Know your place in the queue')) ?>
                </strong>
                <small>
                    <?= e(t('Get a token & estimated time')) ?>
                </small>
            </div>

        </div>
    </section>

    <!-- Doctors Section -->
    <section class="section container">

        <div class="section-heading">
            <div>
                <div class="eyebrow">
                    <?= e(t('THE PEOPLE BEHIND YOUR CARE')) ?>
                </div>

                <h2>
                    <?= e(t('Find the right doctor for you.')) ?>
                </h2>
            </div>

            <a class="text-link"
               href="<?= e(url('doctors.php')) ?>">
                <?= e(t('Meet all doctors ↗')) ?>
            </a>
        </div>

        <div class="row g-4">

            <?php foreach ($doctors as $doctor): ?>
                <div class="col-md-6 col-lg-4">
                    <?php
                    require __DIR__ . '/includes/doctor-card.php';
                    ?>
                </div>
            <?php endforeach; ?>

            <?php if (!$doctors): ?>
                <div class="col-12">
                    <div class="empty-state">
                        <?= e(t('Our doctor directory is being prepared. Please check back soon.')) ?>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </section>

    <!-- How It Works Section -->
    <section class="how-section" id="how-it-works">
        <div class="container">

            <div class="section-heading">
                <div>
                    <div class="eyebrow">
                        <?= e(t('SIMPLE FROM THE START')) ?>
                    </div>

                    <h2>
                        <?= e(t('Your appointment, in three steps.')) ?>
                    </h2>
                </div>

                <p class="muted">
                    <?= e(t('No registration. No long forms.')) ?>
                    <br>
                    <?= e(t('Just a simpler way to book.')) ?>
                </p>
            </div>

            <div class="row g-4">

                <!-- Step 1 -->
                <div class="col-md-4">
                    <div class="step-card">
                        <span>
                            <?= e(t('01 / CHOOSE')) ?>
                        </span>

                        <h3>
                            <?= e(t('Find your specialist')) ?>
                        </h3>

                        <p>
                            <?= e(t('Browse our doctors and their weekly schedules to choose the care you need.')) ?>
                        </p>
                    </div>
                </div>

                <!-- Step 2 -->
                <div class="col-md-4">
                    <div class="step-card">
                        <span>
                            <?= e(t('02 / BOOK')) ?>
                        </span>

                        <h3>
                            <?= e(t('Pick a day that suits you')) ?>
                        </h3>

                        <p>
                            <?= e(t('Select an available date and enter your name and Pakistani mobile number.')) ?>
                        </p>
                    </div>
                </div>

                <!-- Step 3 -->
                <div class="col-md-4">
                    <div class="step-card">
                        <span>
                            <?= e(t('03 / VISIT')) ?>
                        </span>

                        <h3>
                            <?= e(t('Bring your appointment slip')) ?>
                        </h3>

                        <p>
                            <?= e(t('Save your private link or print your slip. Arrive 10–15 minutes before your estimated time.')) ?>
                        </p>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <!-- Final Call to Action -->
    <section class="container section">
        <div class="care-banner">

            <div>
                <span class="eyebrow">
                    <?= e(t('WE’RE HERE FOR YOU')) ?>
                </span>

                <h2>
                    <?= e(t('Make time for your health.')) ?>
                </h2>

                <p>
                    <?= e(t('Your next step towards feeling better can start today.')) ?>
                </p>
            </div>

            <a class="btn btn-white"
               href="<?= e(url('doctors.php')) ?>">
                <?= e(t('Book your visit ↗')) ?>
            </a>

        </div>
    </section>

</main>

<?php require __DIR__ . '/includes/footer.php'; ?>

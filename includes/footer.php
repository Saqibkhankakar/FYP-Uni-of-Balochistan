
<!-- Footer Section -->
<footer class="site-footer no-print">
    <div class="container">

        <!-- Main Footer Content -->
        <div class="footer-main">

            <!-- Hospital Brand -->
            <a class="brand" href="<?= e(url('/')) ?>">

                <span class="brand-mark">+</span>

                <span>
                    <?= e(t('Al-Shifa Hospital')) ?>

                    <small>
                        <?= e(t('Patel Road, Quetta')) ?>
                    </small>
                </span>

            </a>

            <!-- Hospital Description -->
            <p>
                <?= e(t('Less waiting. More care.')) ?>
                <br>

                <span class="muted">
                    <?= e(t('Book your visit in a few simple steps.')) ?>
                </span>
            </p>

            <!-- Footer Navigation -->
            <div>

                <a href="<?= e(url('doctors.php')) ?>">
                    <?= e(t('Find a doctor')) ?>
                </a>

                <a href="<?= e(url('login.php')) ?>">
                    <?= e(t('Staff portal')) ?>
                </a>

            </div>

        </div>

        <!-- Footer Bottom -->
        <div class="footer-bottom">

            <!-- Copyright -->
            <span>
                © <?= date('Y') ?>
                <?= e(t('Al-Shifa Hospital')) ?>
            </span>

            <!-- Medical Disclaimer -->
            <span>
                <?= e(t('Appointment times are estimates. For an emergency, seek immediate medical care.')) ?>
            </span>

        </div>

    </div>
</footer>

<!-- Chatbot Widget -->
<?php require __DIR__ . '/chatbot-widget.php'; ?>

</body>
</html>

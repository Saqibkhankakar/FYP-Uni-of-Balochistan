<?php
function migrate_database(): void {
    // Additive and repeatable. No existing rows, statuses, tokens or times change.
    $lock = one("SELECT GET_LOCK('doctor_hospital_archive_migration',10) acquired");
    if ((int)$lock['acquired'] !== 1) throw new RuntimeException('Could not acquire migration lock. Try again.');
    try {
        if (!one("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='doctors' AND COLUMN_NAME='archived_at'")) {
            db()->exec('ALTER TABLE doctors ADD COLUMN archived_at DATETIME NULL DEFAULT NULL AFTER active');
        }
        require_once __DIR__.'/chatbot-migration.php';
        migrate_chatbot();
    } finally { query("SELECT RELEASE_LOCK('doctor_hospital_archive_migration')"); }
}

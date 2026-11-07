-- Existing installations: run php scripts/migrate.php for an idempotent migration.
-- Manual alternative (run exactly once after selecting the hospital database):
ALTER TABLE doctors ADD COLUMN archived_at DATETIME NULL DEFAULT NULL AFTER active;

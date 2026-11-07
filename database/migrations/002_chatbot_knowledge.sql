-- Additive and repeatable. Run scripts/migrate.php to also seed the bilingual bank.
CREATE TABLE IF NOT EXISTS chatbot_knowledge (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    seed_key VARCHAR(80) NULL UNIQUE,
    question_en VARCHAR(160) NOT NULL,
    question_ur VARCHAR(160) NOT NULL,
    patterns TEXT NOT NULL,
    specialty VARCHAR(100) NOT NULL,
    answer_en TEXT NOT NULL,
    answer_ur TEXT NOT NULL,
    followup_en VARCHAR(200) NOT NULL DEFAULT '',
    followup_ur VARCHAR(200) NOT NULL DEFAULT '',
    emergency TINYINT(1) NOT NULL DEFAULT 0,
    active TINYINT(1) NOT NULL DEFAULT 1,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE DATABASE IF NOT EXISTS doctor_hospital CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE doctor_hospital;

CREATE TABLE IF NOT EXISTS staff_users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    username VARCHAR(80) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin','receptionist') NOT NULL,
    phone VARCHAR(15) NOT NULL DEFAULT '',
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS doctors (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    specialization VARCHAR(100) NOT NULL,
    qualifications VARCHAR(200) NOT NULL,
    phone VARCHAR(15) NOT NULL,
    photo VARCHAR(200) DEFAULT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX (active, specialization)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS weekly_schedules (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    doctor_id INT UNSIGNED NOT NULL,
    weekday TINYINT UNSIGNED NOT NULL COMMENT '1=Monday, 7=Sunday',
    booking_open TIME NOT NULL,
    booking_close TIME NOT NULL,
    consultation_start TIME NOT NULL,
    consultation_end TIME NOT NULL,
    duration_minutes SMALLINT UNSIGNED NOT NULL,
    UNIQUE KEY doctor_weekday (doctor_id, weekday),
    FOREIGN KEY (doctor_id) REFERENCES doctors(id),
    CHECK (weekday BETWEEN 1 AND 7),
    CHECK (duration_minutes BETWEEN 1 AND 120),
    CHECK (booking_open < booking_close),
    CHECK (booking_close <= consultation_start),
    CHECK (consultation_start < consultation_end)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS unavailable_dates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    doctor_id INT UNSIGNED NOT NULL,
    unavailable_date DATE NOT NULL,
    reason VARCHAR(200) NOT NULL DEFAULT '',
    UNIQUE KEY doctor_absence (doctor_id, unavailable_date),
    FOREIGN KEY (doctor_id) REFERENCES doctors(id)
) ENGINE=InnoDB;

-- A small snapshot per booked date preserves estimates when weekly schedules change.
CREATE TABLE IF NOT EXISTS appointment_days (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    doctor_id INT UNSIGNED NOT NULL,
    appointment_date DATE NOT NULL,
    booking_open TIME NOT NULL,
    booking_close TIME NOT NULL,
    consultation_start TIME NOT NULL,
    consultation_end TIME NOT NULL,
    duration_minutes SMALLINT UNSIGNED NOT NULL,
    capacity SMALLINT UNSIGNED NOT NULL,
    next_token INT UNSIGNED NOT NULL DEFAULT 1,
    UNIQUE KEY doctor_date (doctor_id, appointment_date),
    FOREIGN KEY (doctor_id) REFERENCES doctors(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS appointments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    day_id INT UNSIGNED NOT NULL,
    doctor_id INT UNSIGNED NOT NULL,
    patient_name VARCHAR(100) NOT NULL,
    patient_phone VARCHAR(15) NOT NULL,
    reason VARCHAR(500) NOT NULL DEFAULT '',
    appointment_date DATE NOT NULL,
    token_number INT UNSIGNED NOT NULL,
    position_number SMALLINT UNSIGNED NOT NULL,
    estimated_time TIME NOT NULL,
    reference VARCHAR(24) NOT NULL UNIQUE,
    confirmation_token CHAR(64) NOT NULL UNIQUE,
    submission_key CHAR(64) NOT NULL UNIQUE,
    status ENUM('Booked','Arrived','In Consultation','Completed','No-show','Cancelled') NOT NULL DEFAULT 'Booked',
    source ENUM('online','walk-in','demo') NOT NULL DEFAULT 'online',
    created_by INT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    active_position SMALLINT UNSIGNED GENERATED ALWAYS AS (CASE WHEN status = 'Cancelled' THEN NULL ELSE position_number END) STORED,
    serving_day INT UNSIGNED GENERATED ALWAYS AS (CASE WHEN status = 'In Consultation' THEN day_id ELSE NULL END) STORED,
    UNIQUE KEY day_token (day_id, token_number),
    UNIQUE KEY occupied_position (day_id, active_position),
    UNIQUE KEY one_patient_served (serving_day),
    INDEX queue_lookup (doctor_id, appointment_date, token_number),
    INDEX patient_search (patient_phone),
    FOREIGN KEY (day_id) REFERENCES appointment_days(id),
    FOREIGN KEY (doctor_id) REFERENCES doctors(id),
    FOREIGN KEY (created_by) REFERENCES staff_users(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS rate_limits (
    bucket CHAR(64) PRIMARY KEY,
    attempts INT UNSIGNED NOT NULL DEFAULT 0,
    expires_at DATETIME NOT NULL,
    INDEX (expires_at)
) ENGINE=InnoDB;

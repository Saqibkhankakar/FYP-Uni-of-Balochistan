-- Fictional people only. Safe to import again; existing doctors/schedules are preserved.
-- Run php scripts/install.php --demo to ALSO generate current/future sample appointments
-- through the real booking service, with cryptographically random confirmation secrets.
INSERT INTO doctors (name,specialization,qualifications,phone,photo)
SELECT 'Dr. Ahmed Khan','General Medicine','MBBS, FCPS (Medicine)','03001234567','assets/images/doctor-placeholder.svg'
WHERE NOT EXISTS (SELECT 1 FROM doctors WHERE name='Dr. Ahmed Khan');
INSERT INTO doctors (name,specialization,qualifications,phone,photo)
SELECT 'Dr. Ayesha Malik','Dermatology','MBBS, FCPS (Dermatology)','03011234567','assets/images/doctor-ayesha.svg'
WHERE NOT EXISTS (SELECT 1 FROM doctors WHERE name='Dr. Ayesha Malik');
INSERT INTO doctors (name,specialization,qualifications,phone,photo)
SELECT 'Dr. Omar Farooq','Cardiology','MBBS, FCPS (Cardiology)','03021234567','assets/images/doctor-omar.svg'
WHERE NOT EXISTS (SELECT 1 FROM doctors WHERE name='Dr. Omar Farooq');
INSERT INTO doctors (name,specialization,qualifications,phone,photo)
SELECT 'Dr. Sara Hassan','Pediatrics','MBBS, FCPS (Pediatrics)','03031234567','assets/images/doctor-ayesha.svg'
WHERE NOT EXISTS (SELECT 1 FROM doctors WHERE name='Dr. Sara Hassan');

INSERT IGNORE INTO weekly_schedules (doctor_id,weekday,booking_open,booking_close,consultation_start,consultation_end,duration_minutes)
SELECT d.id, w.day_number,'09:00:00','16:00:00','18:30:00','21:30:00',5
FROM doctors d CROSS JOIN (SELECT 1 day_number UNION SELECT 3 UNION SELECT 5) w WHERE d.name='Dr. Ahmed Khan';
INSERT IGNORE INTO weekly_schedules (doctor_id,weekday,booking_open,booking_close,consultation_start,consultation_end,duration_minutes)
SELECT d.id, w.day_number,'09:00:00','15:00:00','17:00:00','20:00:00',10
FROM doctors d CROSS JOIN (SELECT 2 day_number UNION SELECT 4 UNION SELECT 6) w WHERE d.name='Dr. Ayesha Malik';
INSERT IGNORE INTO weekly_schedules (doctor_id,weekday,booking_open,booking_close,consultation_start,consultation_end,duration_minutes)
SELECT d.id, w.day_number,'08:00:00','14:00:00','16:00:00','19:00:00',15
FROM doctors d CROSS JOIN (SELECT 1 day_number UNION SELECT 2 UNION SELECT 4 UNION SELECT 6) w WHERE d.name='Dr. Omar Farooq';
INSERT IGNORE INTO weekly_schedules (doctor_id,weekday,booking_open,booking_close,consultation_start,consultation_end,duration_minutes)
SELECT d.id, w.day_number,'09:00:00','16:00:00','18:00:00','21:00:00',10
FROM doctors d CROSS JOIN (SELECT 1 day_number UNION SELECT 3 UNION SELECT 5 UNION SELECT 6) w WHERE d.name='Dr. Sara Hassan';

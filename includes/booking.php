<?php
function schedule_for(int $doctorId, string $date): ?array {
    $snapshot = one('SELECT * FROM appointment_days WHERE doctor_id=? AND appointment_date=?', [$doctorId, $date]);
    if ($snapshot) return $snapshot;
    return one('SELECT * FROM weekly_schedules WHERE doctor_id=? AND weekday=?', [$doctorId, (int)date('N', strtotime($date))]);
}
function capacity(array $schedule): int {
    return (int)floor((strtotime($schedule['consultation_end']) - strtotime($schedule['consultation_start'])) / (60 * (int)$schedule['duration_minutes']));
}
function booking_state(int $doctorId, string $date, ?DateTimeImmutable $now = null): array {
    $now ??= new DateTimeImmutable();
    if (!valid_date($date) || $date < $now->format('Y-m-d')) return ['open'=>false, 'label'=>t('Past date')];
    if ($date > $now->modify('+' . $GLOBALS['config']['booking_days'] . ' days')->format('Y-m-d')) return ['open'=>false, 'label'=>t('Outside booking period')];
    $doctor = one('SELECT active,archived_at FROM doctors WHERE id=?', [$doctorId]);
    if (!$doctor || !$doctor['active'] || $doctor['archived_at']) return ['open'=>false, 'label'=>t('Unavailable')];
    if (one('SELECT id FROM unavailable_dates WHERE doctor_id=? AND unavailable_date=?', [$doctorId, $date])) return ['open'=>false, 'label'=>t('Doctor unavailable')];
    $schedule = schedule_for($doctorId, $date);
    if (!$schedule) return ['open'=>false, 'label'=>t('Not a working day')];
    $used = (int)one("SELECT COUNT(*) AS n FROM appointments WHERE doctor_id=? AND appointment_date=? AND status <> 'Cancelled'", [$doctorId, $date])['n'];
    $remaining = capacity($schedule) - $used;
    $state = ['open'=>true, 'label'=>t('Available'), 'schedule'=>$schedule, 'remaining'=>max(0, $remaining)];
    if ($date === $now->format('Y-m-d')) {
        $clock = $now->format('H:i:s');
        if ($clock < $schedule['booking_open']) return array_replace($state, ['open'=>false, 'label'=>t('booking.opens', ['time'=>time_label($schedule['booking_open'])])]);
        if ($clock >= $schedule['booking_close']) return array_replace($state, ['open'=>false, 'label'=>t('Booking closed')]);
    }
    if ($remaining <= 0) return array_replace($state, ['open'=>false, 'label'=>t('Fully Booked')]);
    return $state;
}
function upcoming_dates(int $doctorId): array {
    $dates = [];
    for ($i=0; $i <= $GLOBALS['config']['booking_days']; $i++) {
        $date = date('Y-m-d', strtotime("+$i days"));
        if (schedule_for($doctorId, $date)) $dates[$date] = booking_state($doctorId, $date);
    }
    return $dates;
}
function book_appointment(int $doctorId, string $date, string $name, string $phone, string $reason, string $submission, string $source = 'online', ?int $staffId = null): array {
    $name = required_text($name, 'Full name');
    $phone = phone_number($phone);
    if (mb_strlen($reason) > 500) throw new DomainException(t('Reason must be at most 500 characters.'));
    if (!preg_match('/^[a-f0-9]{64}$/D', $submission)) throw new DomainException(t('Refresh the form and try again.'));
    $pdo = db();
    $pdo->beginTransaction();
    try {
        // Every queue/schedule mutation takes this same parent lock. Parallel requests serialize here.
        if (!one('SELECT id FROM doctors WHERE id=? FOR UPDATE', [$doctorId])) throw new DomainException(t('Doctor not found.'));
        $existing = one('SELECT * FROM appointments WHERE submission_key=?', [$submission]);
        if ($existing) { $pdo->commit(); return $existing; }
        $state = booking_state($doctorId, $date);
        if (!$state['open']) throw new DomainException(t('booking.unavailable', ['status'=>$state['label']]));
        $s = $state['schedule'];
        query('INSERT IGNORE INTO appointment_days (doctor_id,appointment_date,booking_open,booking_close,consultation_start,consultation_end,duration_minutes,capacity) VALUES (?,?,?,?,?,?,?,?)', [$doctorId,$date,$s['booking_open'],$s['booking_close'],$s['consultation_start'],$s['consultation_end'],$s['duration_minutes'],capacity($s)]);
        $day = one('SELECT * FROM appointment_days WHERE doctor_id=? AND appointment_date=? FOR UPDATE', [$doctorId, $date]);
        $used = query("SELECT position_number FROM appointments WHERE day_id=? AND status <> 'Cancelled'", [$day['id']])->fetchAll(PDO::FETCH_COLUMN);
        $position = 1;
        while (in_array($position, $used)) $position++;
        if ($position > (int)$day['capacity']) throw new DomainException(t('Fully Booked. Please choose another date.'));
        $estimate = date('H:i:s', strtotime($day['consultation_start']) + ($position-1) * (int)$day['duration_minutes'] * 60);
        $privateToken = bin2hex(random_bytes(32));
        $reference = 'DH-' . strtoupper(bin2hex(random_bytes(6)));
        query('INSERT INTO appointments (day_id,doctor_id,patient_name,patient_phone,reason,appointment_date,token_number,position_number,estimated_time,reference,confirmation_token,submission_key,source,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)', [$day['id'],$doctorId,$name,$phone,trim($reason),$date,$day['next_token'],$position,$estimate,$reference,$privateToken,$submission,$source,$staffId]);
        $id = (int)$pdo->lastInsertId();
        query('UPDATE appointment_days SET next_token=next_token+1 WHERE id=?', [$day['id']]);
        $appointment = one('SELECT * FROM appointments WHERE id=?', [$id]);
        $pdo->commit();
        return $appointment;
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
}
function change_status(int $id, string $newStatus, ?int $expectedDoctor = null): void {
    $transitions = [
        'Booked'=>['Arrived','In Consultation','No-show','Cancelled'],
        'Arrived'=>['In Consultation','No-show','Cancelled'],
        'In Consultation'=>['Completed','Arrived'],
        'No-show'=>['Arrived','Cancelled'],
        'Completed'=>[], 'Cancelled'=>[],
    ];
    $a = one('SELECT doctor_id FROM appointments WHERE id=?', [$id]);
    if (!$a || ($expectedDoctor !== null && (int)$a['doctor_id'] !== $expectedDoctor)) throw new DomainException(t('Appointment not found.'));
    db()->beginTransaction();
    try {
        one('SELECT id FROM doctors WHERE id=? FOR UPDATE', [$a['doctor_id']]);
        $a = one('SELECT * FROM appointments WHERE id=? FOR UPDATE', [$id]);
        if (!in_array($newStatus, $transitions[$a['status']] ?? [], true)) throw new DomainException(t('That status change is not allowed. Refresh the queue.'));
        if ($newStatus === 'In Consultation' && $a['appointment_date'] !== today()) throw new DomainException(t('Only today’s appointments can be called into consultation.'));
        if ($newStatus === 'In Consultation' && one("SELECT id FROM appointments WHERE day_id=? AND status='In Consultation'", [$a['day_id']])) throw new DomainException(t('Complete the current consultation before calling another patient.'));
        query('UPDATE appointments SET status=? WHERE id=?', [$newStatus, $id]);
        db()->commit();
    } catch (Throwable $e) { db()->rollBack(); throw $e; }
}
function save_schedule(int $doctorId, int $weekday, array $data): void {
    if ($weekday < 1 || $weekday > 7) throw new DomainException(t('Choose a valid weekday.'));
    foreach (['booking_open','booking_close','consultation_start','consultation_end'] as $key) {
        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/D', $data[$key] ?? '')) throw new DomainException(t('Enter valid schedule times.'));
    }
    $duration = filter_var($data['duration_minutes'] ?? '', FILTER_VALIDATE_INT);
    if (!$duration || $duration > 120 || $duration < 1) throw new DomainException(t('Consultation duration must be 1–120 minutes.'));
    if ($data['booking_open'] >= $data['booking_close'] || $data['booking_close'] > $data['consultation_start'] || $data['consultation_start'] >= $data['consultation_end']) throw new DomainException(t('Use opening < cutoff ≤ consultation start < consultation end. Overnight schedules are not supported.'));
    $data['duration_minutes'] = $duration;
    if (capacity($data) < 1) throw new DomainException(t('The consultation period must contain at least one appointment.'));
    db()->beginTransaction();
    try {
        if (!one('SELECT id FROM doctors WHERE id=? FOR UPDATE', [$doctorId])) throw new DomainException(t('Doctor not found.'));
        query('INSERT INTO weekly_schedules (doctor_id,weekday,booking_open,booking_close,consultation_start,consultation_end,duration_minutes) VALUES (?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE booking_open=VALUES(booking_open),booking_close=VALUES(booking_close),consultation_start=VALUES(consultation_start),consultation_end=VALUES(consultation_end),duration_minutes=VALUES(duration_minutes)', [$doctorId,$weekday,$data['booking_open'],$data['booking_close'],$data['consultation_start'],$data['consultation_end'],$duration]);
        db()->commit();
    } catch (Throwable $e) { db()->rollBack(); throw $e; }
}
function mark_unavailable(int $doctorId, string $date, string $reason): void {
    if (!valid_date($date) || $date < today()) throw new DomainException(t('Choose today or a future date.'));
    if (mb_strlen($reason) > 200) throw new DomainException(t('Reason must be at most 200 characters.'));
    db()->beginTransaction();
    try {
        one('SELECT id FROM doctors WHERE id=? FOR UPDATE', [$doctorId]);
        if (one("SELECT id FROM appointments WHERE doctor_id=? AND appointment_date=? AND status <> 'Cancelled' LIMIT 1", [$doctorId, $date])) throw new DomainException(t('This date has appointments. Contact the patients and cancel their bookings in the queue before marking the doctor absent.'));
        query('INSERT INTO unavailable_dates (doctor_id,unavailable_date,reason) VALUES (?,?,?) ON DUPLICATE KEY UPDATE reason=VALUES(reason)', [$doctorId, $date, trim($reason)]);
        db()->commit();
    } catch (Throwable $e) { db()->rollBack(); throw $e; }
}

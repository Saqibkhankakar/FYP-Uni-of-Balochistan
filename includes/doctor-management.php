<?php
function unresolved_doctor_bookings(int $doctorId): int {
    // A future visit must be cancelled, not marked a premature no-show/completion.
    return (int)one("SELECT COUNT(*) n FROM appointments WHERE doctor_id=? AND
        ((appointment_date > ? AND status <> 'Cancelled') OR
         (appointment_date = ? AND status IN ('Booked','Arrived','In Consultation')))",
        [$doctorId, today(), today()])['n'];
}

function manage_doctor(int $doctorId, string $action, int $actorId): string {
    if (!one("SELECT id FROM staff_users WHERE id=? AND role='admin' AND active=1", [$actorId])) {
        throw new DomainException(t('Only the Super Admin can manage doctor records.'));
    }
    if (!in_array($action, ['delete','archive','restore'], true)) throw new DomainException(t('Unknown action.'));
    db()->beginTransaction();
    try {
        // Same parent lock as booking: a doctor cannot disappear during allocation.
        $doctor = one('SELECT * FROM doctors WHERE id=? FOR UPDATE', [$doctorId]);
        if (!$doctor) throw new DomainException(t('Doctor not found.'));
        if ($action === 'delete') {
            if (one('SELECT id FROM appointments WHERE doctor_id=? LIMIT 1', [$doctorId])) {
                throw new DomainException(t('This doctor has appointment history. Archive the doctor instead; patient records cannot be deleted.'));
            }
            // Never delete from appointments. Foreign keys remain a second safeguard.
            query('DELETE FROM unavailable_dates WHERE doctor_id=?', [$doctorId]);
            query('DELETE FROM weekly_schedules WHERE doctor_id=?', [$doctorId]);
            query('DELETE FROM appointment_days WHERE doctor_id=?', [$doctorId]);
            query('DELETE FROM doctors WHERE id=?', [$doctorId]);
            $message = t('Doctor permanently deleted. No patient records were removed.');
        } elseif ($action === 'archive') {
            $unresolved = unresolved_doctor_bookings($doctorId);
            if ($unresolved) throw new DomainException(t('archive.unresolved', ['count'=>$unresolved]));
            query('UPDATE doctors SET archived_at=COALESCE(archived_at,NOW()),active=0 WHERE id=?', [$doctorId]);
            $message = t('Doctor archived. Historical appointments and slips remain available to authorized staff.');
        } else {
            query('UPDATE doctors SET archived_at=NULL,active=1 WHERE id=?', [$doctorId]);
            $message = t('Doctor restored and available for booking according to the saved schedule.');
        }
        db()->commit();
    } catch (Throwable $e) { if (db()->inTransaction()) db()->rollBack(); throw $e; }
    if ($action === 'delete' && preg_match('~^assets/uploads/[a-f0-9]{40}\.jpg$~', $doctor['photo'] ?? '') &&
        !one('SELECT id FROM doctors WHERE photo=? LIMIT 1', [$doctor['photo']])) {
        $photoPath = dirname(__DIR__) . '/' . $doctor['photo'];
        if (is_file($photoPath)) unlink($photoPath);
    }
    return $message;
}

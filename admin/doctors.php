<?php
require dirname(__DIR__).'/includes/bootstrap.php'; $actor=require_role('admin'); $title=t('Manage doctors'); $error=null;
if ($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf();
    try {
        if (in_array($_POST['action'] ?? '', ['delete','archive','restore'], true)) {
            flash(manage_doctor((int)($_POST['id'] ?? 0), (string)$_POST['action'], (int)$actor['id']));
            redirect('admin/doctors.php');
        }
        $id=(int)($_POST['id'] ?? 0); $name=required_text((string)($_POST['name'] ?? ''),'Doctor name');
        $specialization=required_text((string)($_POST['specialization'] ?? ''),'Specialization');
        $qualifications=required_text((string)($_POST['qualifications'] ?? ''),'Qualifications',200);
        $phone=phone_number((string)($_POST['phone'] ?? '')); $active=isset($_POST['active']) ? 1 : 0;
        $photo=upload_photo($_FILES['photo'] ?? null);
        db()->beginTransaction();
        try {
            if ($id) {
                $stored=one('SELECT id,archived_at FROM doctors WHERE id=? FOR UPDATE',[$id]);
                if (!$stored) throw new DomainException(t('Doctor not found.'));
                if ($stored['archived_at']) $active=0; // Editing cannot silently restore an archived doctor.
                query('UPDATE doctors SET name=?,specialization=?,qualifications=?,phone=?,active=?,photo=COALESCE(?,photo) WHERE id=?',[$name,$specialization,$qualifications,$phone,$active,$photo,$id]);
            } else { query('INSERT INTO doctors (name,specialization,qualifications,phone,active,photo) VALUES (?,?,?,?,?,?)',[$name,$specialization,$qualifications,$phone,$active,$photo]); }
            db()->commit();
        } catch (Throwable $e) { db()->rollBack(); throw $e; }
        flash(t('Doctor saved. Existing appointments remain in the staff queues.')); redirect('admin/doctors.php');
    } catch (DomainException $e) { $error=$e->getMessage(); }
}
$edit=isset($_GET['edit']) ? one('SELECT * FROM doctors WHERE id=?',[(int)$_GET['edit']]) : null;
if ($error && !in_array($_POST['action'] ?? '', ['delete','archive','restore'], true)) $edit=array_replace($_POST,['active'=>isset($_POST['active']) ? 1 : 0]);
$doctors=query('SELECT d.*, (SELECT COUNT(*) FROM appointments a WHERE a.doctor_id=d.id) appointment_count FROM doctors d ORDER BY archived_at IS NOT NULL, active DESC,name')->fetchAll(); require dirname(__DIR__).'/includes/header.php';
?>
<main id="main" class="container section"><div class="section-heading"><div><div class="eyebrow"><?= e(t('YOUR CARE TEAM')) ?></div><h1><?= e(t('Doctor management')) ?></h1><p class="muted"><?= e(t('Keep your directory and doctor details up to date.')) ?></p></div></div><?php if($error): ?><div class="alert alert-danger" role="alert"><?= e($error) ?></div><?php endif; ?><div class="row g-4"><div class="col-xl-4"><form class="panel" method="post" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="id" value="<?= e($edit['id'] ?? 0) ?>"><h2><?= e(t(!empty($edit['id'])?'Edit doctor':'Add a doctor')) ?></h2><label for="name" class="form-label"><?= e(t('Doctor name')) ?></label><input id="name" name="name" class="form-control mb-3" required maxlength="100" value="<?= e($edit['name'] ?? '') ?>" placeholder="<?= e(t('Dr. Ahmed Khan')) ?>"><label for="specialization" class="form-label"><?= e(t('Specialization')) ?></label><input id="specialization" name="specialization" class="form-control mb-3" required maxlength="100" value="<?= e($edit['specialization'] ?? '') ?>"><label for="qualifications" class="form-label"><?= e(t('Qualifications')) ?></label><input id="qualifications" name="qualifications" class="form-control mb-3" required maxlength="200" value="<?= e($edit['qualifications'] ?? '') ?>"><label for="phone" class="form-label"><?= e(t('Mobile number')) ?></label><input id="phone" type="tel" name="phone" class="form-control mb-3" required maxlength="20" value="<?= e($edit['phone'] ?? '') ?>"><label for="photo" class="form-label"><?= e(t('Doctor photo')) ?></label><input id="photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp" class="form-control"><p class="form-text"><?= e(t('JPG, PNG or WebP · up to 2 MB. Leave blank to keep the current photo.')) ?></p><div class="form-check my-4"><input class="form-check-input" type="checkbox" id="active" name="active" <?= ($edit['active'] ?? 1)?'checked':'' ?>><label for="active" class="form-check-label"><?= e(t('Active and visible to patients')) ?></label></div><button class="btn btn-primary"><?= e(t('Save doctor')) ?></button><?php if($edit): ?><a class="btn btn-light" href="<?= e(url('admin/doctors.php')) ?>"><?= e(t('New doctor')) ?></a><?php endif; ?></form></div><div class="col-xl-8"><div class="panel"><h2><?= e(t('All doctors')) ?> <span class="muted h6">(<?= count($doctors) ?>)</span></h2><p class="small muted"><?= e(t('Delete is available only for doctors with no appointment history. Archive preserves all records; resolve future appointments first. Restore reopens booking.')) ?></p><div class="table-responsive"><table class="table align-middle"><thead><tr><th><?= e(t('Doctor')) ?></th><th><?= e(t('Contact')) ?></th><th><?= e(t('Status')) ?></th><th><?= e(t('Manage')) ?></th></tr></thead><tbody><?php foreach($doctors as $d): ?><tr><td><div class="person-cell"><img src="<?= e(doctor_photo($d)) ?>" alt=""><div><strong><?= e($d['name']) ?></strong><small><?= e(t($d['specialization'])) ?></small></div></div></td><td dir="ltr"><?= e($d['phone']) ?></td><td><?= status_badge($d['archived_at']?'Archived':($d['active']?'Active':'Inactive')) ?></td><td><a href="?edit=<?= e($d['id']) ?>"><?= e(t('Edit')) ?></a> <span class="muted">·</span> <a href="<?= e(url('admin/schedules.php?doctor='.$d['id'])) ?>"><?= e(t('Schedule')) ?></a>
<div class="doctor-actions">
<?php if ($d['archived_at']): ?>
<form method="post" data-confirm="<?= e(t('Restore this doctor and reopen booking according to the saved schedule?')) ?>">
<?= csrf_field() ?><input type="hidden" name="id" value="<?= e($d['id']) ?>"><input type="hidden" name="action" value="restore"><button class="btn btn-outline-primary btn-sm"><?= e(t('Restore doctor')) ?></button></form>
<?php else: ?>
<form method="post" data-confirm="<?= e(t('Archive this doctor? Booking will close. Historical records will stay available. Unresolved appointments must be handled first.')) ?>">
<?= csrf_field() ?><input type="hidden" name="id" value="<?= e($d['id']) ?>"><input type="hidden" name="action" value="archive"><button class="btn btn-outline-primary btn-sm"><?= e(t('Archive doctor')) ?></button></form>
<?php endif; ?>
<?php if (!(int)$d['appointment_count']): ?>
<form method="post" data-confirm="<?= e(t('Permanently delete this doctor and their schedules? This cannot be undone. No patient records will be deleted.')) ?>">
<?= csrf_field() ?><input type="hidden" name="id" value="<?= e($d['id']) ?>"><input type="hidden" name="action" value="delete"><button class="btn btn-outline-danger btn-sm"><?= e(t('Delete doctor')) ?></button></form>
<?php else: ?><small class="muted"><?= e(t('Appointment history is protected. Use Archive instead of Delete.')) ?></small><?php endif; ?>
<?php $unresolved=unresolved_doctor_bookings((int)$d['id']); if ($unresolved): ?><p class="archive-warning"><?= e(t('archive.unresolved',['count'=>$unresolved])) ?></p><a href="<?= e(url('reception/queue.php?doctor='.$d['id'].'&date='.one("SELECT MIN(appointment_date) next_date FROM appointments WHERE doctor_id=? AND ((appointment_date>? AND status <> 'Cancelled') OR (appointment_date=? AND status IN ('Booked','Arrived','In Consultation')))",[$d['id'],today(),today()])['next_date'])) ?>"><?= e(t('Resolve appointments ↗')) ?></a><?php endif; ?>
</div></td></tr><?php endforeach; ?><?php if(!$doctors): ?><tr><td colspan="4" class="py-5 muted"><?= e(t('Add your first doctor to get started.')) ?></td></tr><?php endif; ?></tbody></table></div></div></div></div></main><?php require dirname(__DIR__).'/includes/footer.php'; ?>

<?php
require dirname(__DIR__).'/includes/bootstrap.php';
require dirname(__DIR__).'/includes/chatbot.php';
$actor=require_role('admin'); $title=t('chat.bank'); $error=null;
if($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf();
    try { chat_save_entry($_POST,(int)$actor['id']); flash(t('chat.saved')); redirect('admin/chatbot.php'); }
    catch(DomainException $e) { $error=$e->getMessage(); }
}
$edit=isset($_GET['edit'])?one('SELECT * FROM chatbot_knowledge WHERE id=?',[(int)$_GET['edit']]):null;
if($error) $edit=array_replace(array_filter($_POST,'is_string'),['active'=>isset($_POST['active'])?1:0,'emergency'=>isset($_POST['emergency'])?1:0]);
$search=mb_substr(trim(is_string($_GET['q']??null)?$_GET['q']:''),0,100);
$entries=query('SELECT * FROM chatbot_knowledge WHERE question_en LIKE ? OR question_ur LIKE ? OR specialty LIKE ? OR patterns LIKE ? ORDER BY id DESC',array_fill(0,4,'%'.$search.'%'))->fetchAll();
require dirname(__DIR__).'/includes/header.php';
?>
<main id="main" class="container section">
  <div class="section-heading"><div><div class="eyebrow"><?= e(t('chat.local_label')) ?></div><h1><?= e(t('chat.bank')) ?></h1><p class="muted"><?= e(t('chat.bank_intro')) ?></p></div></div>
  <?php if($error): ?><div class="alert alert-danger" role="alert"><?= e($error) ?></div><?php endif; ?>
  <p class="notice"><?= e(t('chat.editor_safety')) ?></p>
  <div class="row g-4"><div class="col-xl-5">
    <form class="panel" method="post">
      <?= csrf_field() ?><input type="hidden" name="id" value="<?= e($edit['id']??0) ?>">
      <h2><?= e(t($edit?'chat.edit':'chat.add')) ?></h2>
      <?php foreach(['question_en'=>['chat.question_en',160],'question_ur'=>['chat.question_ur',160],'specialty'=>['chat.specialty',100]] as $field=>[$label,$max]): ?>
        <label class="form-label" for="<?= $field ?>"><?= e(t($label)) ?></label><input id="<?= $field ?>" name="<?= $field ?>" class="form-control mb-3" dir="<?= str_ends_with($field,'_ur')?'rtl':'ltr' ?>" maxlength="<?= $max ?>" value="<?= e($edit[$field]??'') ?>" <?= $field==='specialty'?'list="chat-specialties"':'' ?> required>
      <?php endforeach; ?>
      <datalist id="chat-specialties"><?php foreach(array_keys(chat_specialties()) as $specialty): ?><option value="<?= e($specialty) ?>"><?= e(chat_specialty_label($specialty)) ?></option><?php endforeach; ?></datalist>
      <p class="form-text"><?= e(t('chat.specialty_hint')) ?></p>
      <?php foreach(['patterns'=>['chat.patterns',3000,6],'answer_en'=>['chat.answer_en',1000,3],'answer_ur'=>['chat.answer_ur',1000,3],'followup_en'=>['chat.followup_en',200,2],'followup_ur'=>['chat.followup_ur',200,2]] as $field=>[$label,$max,$rows]): ?>
        <label class="form-label" for="<?= $field ?>"><?= e(t($label)) ?></label><textarea id="<?= $field ?>" name="<?= $field ?>" class="form-control mb-3" dir="<?= str_ends_with($field,'_ur')?'rtl':($field==='patterns'?'auto':'ltr') ?>" maxlength="<?= $max ?>" rows="<?= $rows ?>" <?= str_starts_with($field,'followup')?'':'required' ?>><?= e($edit[$field]??'') ?></textarea>
      <?php endforeach; ?>
      <p class="form-text"><?= e(t('chat.pattern_hint')) ?></p>
      <div class="form-check mb-3"><input class="form-check-input" type="checkbox" id="emergency" name="emergency" <?= !empty($edit['emergency'])?'checked':'' ?>><label class="form-check-label" for="emergency"><?= e(t('chat.emergency_flag')) ?></label></div>
      <div class="form-check mb-3"><input class="form-check-input" type="checkbox" id="active" name="active" <?= ($edit['active']??1)?'checked':'' ?>><label class="form-check-label" for="active"><?= e(t('chat.enabled')) ?></label></div>
      <button class="btn btn-primary"><?= e(t('chat.save')) ?></button> <a class="btn btn-light" href="<?= e(url('admin/chatbot.php')) ?>"><?= e(t('chat.add')) ?></a>
    </form>
  </div><div class="col-xl-7"><div class="panel">
    <form method="get" class="d-flex gap-2 mb-4"><label class="visually-hidden" for="q"><?= e(t('chat.search')) ?></label><input class="form-control" id="q" name="q" value="<?= e($search) ?>" maxlength="100" placeholder="<?= e(t('chat.search')) ?>"><button class="btn btn-outline-primary"><?= e(t('chat.search_button')) ?></button></form>
    <div class="table-responsive"><table class="table align-middle"><thead><tr><th><?= e(t('chat.question')) ?></th><th><?= e(t('chat.specialty')) ?></th><th><?= e(t('Status')) ?></th><th><?= e(t('Manage')) ?></th></tr></thead><tbody>
    <?php foreach($entries as $entry): ?><tr><td><strong><?= e($entry['question_'.language()]) ?></strong><small class="d-block muted"><?= e($entry['answer_'.language()]) ?></small></td><td><?= e(chat_specialty_label($entry['specialty'])) ?></td><td><?= status_badge($entry['active']?'Active':'Inactive') ?><?php if($entry['emergency']): ?><small class="d-block danger-link"><?= e(t('chat.emergency_flag')) ?></small><?php endif; ?></td><td><a href="?edit=<?= e($entry['id']) ?>"><?= e(t('Edit')) ?></a></td></tr><?php endforeach; ?>
    <?php if(!$entries): ?><tr><td colspan="4"><?= e(t('chat.empty_bank')) ?></td></tr><?php endif; ?>
    </tbody></table></div>
  </div></div></div>
</main>
<?php require dirname(__DIR__).'/includes/footer.php'; ?>

<?php
// Public patient pages only. No widget on staff pages, private slips or staff login.
$chatPage=basename($_SERVER['SCRIPT_NAME']??'');
if(!$isStaffPage && in_array($chatPage,['index.php','doctors.php','book.php'],true)):
?>
<div id="hospital-chat" class="hospital-chat no-print" data-endpoint="<?= e(url('chatbot.php')) ?>" data-csrf="<?= e(csrf()) ?>" data-error="<?= e(t('chat.error')) ?>" data-loading="<?= e(t('chat.loading')) ?>" data-input-limit="<?= e(t('chat.input_limit')) ?>" data-you="<?= e(t('chat.you')) ?>" data-assistant="<?= e(t('chat.title')) ?>">
  <button id="chat-launcher" class="btn btn-primary chat-launcher" type="button" aria-controls="chat-panel" aria-expanded="false"><span aria-hidden="true">✦</span> <?= e(t('chat.title')) ?></button>
  <section id="chat-panel" class="chat-panel" role="dialog" aria-modal="false" aria-labelledby="chat-title" hidden>
    <div class="chat-heading"><div><h2 id="chat-title"><?= e(t('chat.title')) ?></h2><p><?= e(t('chat.local_label')) ?></p></div><button id="chat-close" type="button" aria-label="<?= e(t('chat.close')) ?>">×</button></div>
    <p class="chat-safety"><?= e(t('chat.disclaimer')) ?> <strong><?= e(t('chat.urgent_note')) ?></strong></p>
    <div id="chat-messages" class="chat-messages" role="log" aria-live="polite" aria-relevant="additions" tabindex="0" aria-label="<?= e(t('chat.conversation')) ?>"><div class="chat-bubble chat-assistant"><?= e(t('chat.welcome')) ?></div></div>
    <div id="chat-suggestions" class="chat-suggestions" aria-label="<?= e(t('chat.suggestions')) ?>">
      <?php foreach(['chat.ask_head','chat.ask_skin','chat.ask_stomach'] as $suggestion): ?><button type="button" data-chat-question="<?= e(t($suggestion)) ?>"><?= e(t($suggestion)) ?></button><?php endforeach; ?>
    </div>
    <p id="chat-status" class="chat-status" role="status"></p>
    <form id="chat-form" class="chat-form"><label for="chat-input" class="visually-hidden"><?= e(t('chat.input_label')) ?></label><input id="chat-input" name="message" type="text" dir="auto" maxlength="500" required autocomplete="off" placeholder="<?= e(t('chat.input_label')) ?>"><button class="btn btn-primary" type="submit"><?= e(t('chat.send')) ?></button></form>
    <div class="chat-bottom"><span><?= e(t('chat.privacy')) ?></span><button id="chat-reset" type="button"><?= e(t('chat.reset')) ?></button></div>
  </section>
</div>
<?php endif; ?>

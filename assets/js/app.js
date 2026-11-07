'use strict';
document.querySelectorAll('[data-print]').forEach(button => button.addEventListener('click', () => window.print()));
document.querySelectorAll('[data-confirm]').forEach(form => form.addEventListener('submit', event => {
  if (!window.confirm(form.dataset.confirm)) event.preventDefault();
}));
document.querySelectorAll('.language-switcher').forEach(form => form.addEventListener('submit', () => {
  const booking = document.getElementById('patient-booking-form');
  if (!booking) return;
  const draft = {};
  const values = new FormData(booking);
  ['name', 'phone', 'reason', 'date', 'submission_key'].forEach(key => { draft[key] = values.get(key) || ''; });
  const field = document.createElement('input');
  field.type = 'hidden'; field.name = 'booking_draft'; field.value = JSON.stringify(draft);
  form.appendChild(field);
}));
document.querySelectorAll('input, select, textarea').forEach(field => {
  field.addEventListener('invalid', () => {
    field.setCustomValidity(field.validity.valueMissing ? document.body.dataset.requiredMessage : document.body.dataset.invalidMessage);
  });
  field.addEventListener('input', () => field.setCustomValidity(''));
  field.addEventListener('change', () => field.setCustomValidity(''));
});
document.getElementById('copy-link')?.addEventListener('click', async () => {
  const status = document.getElementById('copy-status');
  try {
    await navigator.clipboard.writeText(window.location.href);
    status.textContent = document.getElementById('copy-link').dataset.success;
  } catch {
    status.textContent = document.getElementById('copy-link').dataset.fallback;
  }
});

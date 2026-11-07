'use strict';
(() => {
  const root = document.getElementById('hospital-chat');
  if (!root) return;
  const launcher = document.getElementById('chat-launcher');
  const panel = document.getElementById('chat-panel');
  const form = document.getElementById('chat-form');
  const input = document.getElementById('chat-input');
  const messages = document.getElementById('chat-messages');
  const status = document.getElementById('chat-status');
  const reset = document.getElementById('chat-reset');
  const welcome = messages.firstElementChild.cloneNode(true);
  let busy = false;
  function show(open) {
    panel.hidden = !open;
    launcher.setAttribute('aria-expanded', String(open));
    if (open) input.focus(); else launcher.focus();
  }
  launcher.addEventListener('click', () => show(panel.hidden));
  document.getElementById('chat-close').addEventListener('click', () => show(false));
  panel.addEventListener('keydown', event => { if (event.key === 'Escape') { event.stopPropagation(); show(false); } });
  function element(tag, text, className = '') {
    const node = document.createElement(tag);
    node.textContent = text;
    node.className = className;
    return node;
  }
  function bubble(text, patient = false, emergency = false) {
    const node = element('div', text, 'chat-bubble ' + (patient ? 'chat-patient' : 'chat-assistant') + (emergency ? ' chat-emergency' : ''));
    node.dir = 'auto';
    node.setAttribute('aria-label', patient ? root.dataset.you : root.dataset.assistant);
    if (emergency) node.setAttribute('role', 'alert');
    messages.appendChild(node);
    while (messages.children.length > 20) messages.firstElementChild.remove();
    return node;
  }
  function localURL(value) {
    const target = new URL(value, window.location.href);
    if (target.origin !== window.location.origin || !['http:', 'https:'].includes(target.protocol)) throw new Error('Invalid link');
    return target.href;
  }
  async function request(data) {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), 15000);
    try {
      const response = await fetch(root.dataset.endpoint, {
        method: 'POST', credentials: 'same-origin', signal: controller.signal,
        body: new URLSearchParams({csrf: root.dataset.csrf, ...data}),
        headers: {'Accept': 'application/json'}
      });
      const result = await response.json();
      if (!response.ok) {
        const error = new Error(result.error || root.dataset.error);
        error.userMessage = true;
        throw error;
      }
      return result;
    } finally { clearTimeout(timer); }
  }
  function setBusy(value) {
    busy = value;
    root.querySelectorAll('button:not(#chat-close):not(#chat-launcher)').forEach(button => { button.disabled = value; });
    form.setAttribute('aria-busy', String(value));
  }
  async function send(text) {
    text = text.trim();
    if (busy || !text) return;
    if (Array.from(text).length > 500) { status.textContent = root.dataset.inputLimit; return; }
    bubble(text, true); input.value = ''; setBusy(true); status.textContent = root.dataset.loading;
    messages.scrollTop = messages.scrollHeight;
    try {
      const result = await request({message: text});
      const node = bubble(result.message, false, result.type === 'emergency');
      for (const choice of result.choices || []) {
        const button = element('button', choice.label, 'chat-choice'); button.type = 'button';
        button.addEventListener('click', () => send(choice.message)); node.appendChild(button);
      }
      for (const doctor of result.doctors || []) {
        const card = element('article', '', 'chat-doctor');
        const image = document.createElement('img'); image.src = localURL(doctor.photo); image.alt = ''; image.loading = 'lazy';
        card.append(image, element('h3', doctor.name), element('p', doctor.specialization));
        if (doctor.qualifications) card.appendChild(element('p', doctor.qualifications, 'chat-qualification'));
        if (doctor.schedule.length) {
          const hours = element('ul', '', 'chat-hours');
          doctor.schedule.forEach(line => hours.appendChild(element('li', line))); card.appendChild(hours);
        }
        card.appendChild(element('p', doctor.availability, 'chat-availability'));
        const link = element('a', doctor.booking_label, 'btn btn-outline-primary btn-sm'); link.href = localURL(doctor.booking_url); card.appendChild(link);
        node.appendChild(card);
      }
      if (result.notice) node.appendChild(element('p', result.notice, 'chat-notice'));
      if (result.followup) node.appendChild(element('p', result.followup));
      status.textContent = '';
    } catch (error) {
      // Never render raw HTML or server exception details. The endpoint sends translated safe errors.
      status.textContent = error.userMessage ? error.message : root.dataset.error;
    } finally {
      setBusy(false); messages.scrollTop = messages.scrollHeight;
      if (!panel.hidden) input.focus();
    }
  }
  form.addEventListener('submit', event => { event.preventDefault(); send(input.value); });
  root.querySelectorAll('[data-chat-question]').forEach(button => button.addEventListener('click', () => send(button.dataset.chatQuestion)));
  reset.addEventListener('click', async () => {
    if (busy) return;
    setBusy(true);
    try { await request({action: 'reset'}); messages.replaceChildren(welcome.cloneNode(true)); input.value = ''; status.textContent = ''; }
    catch { status.textContent = root.dataset.error; }
    finally { setBusy(false); input.focus(); }
  });
})();

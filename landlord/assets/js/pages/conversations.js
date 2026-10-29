/* Chat with tenants: conversation list on the left, messages on the right (one at a time on phones). */

import { api } from '../api.js';
import { html, setHtml, $, $$, icon, toast, pageHeader, avatar, timeAgo, fmtDateTime, openModal } from '../ui.js';

const POLL_MS = 5000;

const photo = (c, size = 'h-10 w-10') => (c.photo
  ? html`<img src="${c.photo}" alt="" class="${size} shrink-0 rounded-xl object-cover" />`
  : avatar(c.name, size));

function bubble(m) {
  return html`
    <div class="flex ${m.mine ? 'justify-end' : 'justify-start'}" data-msg="${m.id}">
      <div class="max-w-[80%]">
        <p class="message-bubble ${m.mine ? 'bg-primary text-white' : 'bg-slate-100 text-slate-800'}">${m.body}</p>
        <p class="mt-1 text-[11px] text-slate-400 ${m.mine ? 'text-right' : ''}" title="${fmtDateTime(m.created_at)}">${timeAgo(m.created_at)}${m.mine && m.read ? ' · Seen' : ''}</p>
      </div>
    </div>`;
}

export async function render(view, ctx) {
  const container = document.createElement('div');
  view.replaceChildren(container);
  setHtml(container, html`
    ${pageHeader({
      title: 'Chat',
      description: 'Send messages to your tenants. They can read and reply from the student page (My Reservations › Chat).',
      actions: html`<button type="button" class="btn btn-primary" data-new-chat>${icon('plus')} New chat</button>`,
    })}
    <div class="card grid overflow-hidden md:grid-cols-[18rem_1fr]" style="height: calc(100vh - 15rem); min-height: 28rem;">
      <aside class="flex min-h-0 flex-col border-r border-slate-100" data-list-pane>
        <div class="border-b border-slate-100 p-3"><input type="search" class="form-control" placeholder="Search tenant…" data-search aria-label="Search conversations" /></div>
        <ul class="flex-1 overflow-y-auto" data-list><li class="p-4"><div class="skeleton h-12"></div></li></ul>
      </aside>
      <section class="hidden min-h-0 flex-col md:flex" data-thread-pane>
        <div class="flex flex-1 items-center justify-center p-8 text-center text-sm text-slate-400" data-placeholder>
          <div><span class="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-slate-100 text-slate-400">${icon('chat', 'h-6 w-6')}</span>
          <p class="mt-3">Choose a conversation, or start a new chat with a tenant.</p></div>
        </div>
      </section>
    </div>`);

  const listEl = $('[data-list]', container);
  const listPane = $('[data-list-pane]', container);
  const threadPane = $('[data-thread-pane]', container);
  let conversations = [];
  let active = null; // { id|null, tenantId, name, photo, phone, email }
  let lastId = 0;
  let filter = '';
  let timer = null;
  let sending = false;

  const isMobile = () => !window.matchMedia('(min-width: 768px)').matches;
  function showThreadPane(show) {
    if (!isMobile()) { threadPane.classList.add('md:flex'); return; }
    listPane.classList.toggle('hidden', show);
    threadPane.classList.toggle('hidden', !show);
    threadPane.classList.toggle('flex', show);
  }

  function renderList() {
    const items = conversations.filter((c) => !filter || `${c.name} ${c.houses || ''}`.toLowerCase().includes(filter));
    if (!items.length) {
      setHtml(listEl, html`<li class="px-4 py-10 text-center text-sm text-slate-400">${conversations.length ? 'No match.' : 'No conversations yet. Press "New chat".'}</li>`);
      return;
    }
    setHtml(listEl, items.map((c) => html`
      <li>
        <button type="button" class="flex w-full items-center gap-3 border-b border-slate-50 px-4 py-3 text-left transition hover:bg-slate-50 ${active?.id === c.id ? 'bg-primary-50' : ''}" data-open="${c.id}">
          ${photo(c)}
          <span class="min-w-0 flex-1">
            <span class="flex items-center justify-between gap-2">
              <span class="truncate text-sm ${c.unread ? 'font-bold' : 'font-semibold'} text-ink">${c.name}</span>
              <span class="shrink-0 text-[11px] text-slate-400">${timeAgo(c.last_message_at)}</span>
            </span>
            <span class="flex items-center justify-between gap-2">
              <span class="truncate text-xs ${c.unread ? 'font-semibold text-ink' : 'text-slate-500'}">${c.last_message ? `${c.last_from_me ? 'You: ' : ''}${c.last_message}` : (c.houses || 'No messages yet')}</span>
              ${c.unread ? html`<span class="nav-badge">${c.unread}</span>` : ''}
            </span>
          </span>
        </button>
      </li>`));
  }

  async function loadList() {
    try {
      const res = await api.chat.list();
      if (!ctx.isCurrent()) return;
      conversations = res.data;
      renderList();
    } catch (error) {
      setHtml(listEl, html`<li class="p-4 text-sm text-rose-600">${error.message}</li>`);
    }
  }

  const threadSub = () => html`${active.phone ? html`<a class="hover:underline" href="tel:${active.phone}">${active.phone}</a>` : ''}${active.phone && active.houses ? ' · ' : ''}${active.houses || ''}`;

  function renderThread() {
    setHtml(threadPane, html`
      <div class="flex items-center gap-3 border-b border-slate-100 px-4 py-3">
        <button type="button" class="icon-btn md:hidden" data-back aria-label="Back to conversations">${icon('chevronLeft')}</button>
        ${photo(active)}
        <div class="min-w-0 flex-1">
          <p class="truncate font-semibold text-ink">${active.name}</p>
          <p class="truncate text-xs text-slate-500" data-thread-sub>${threadSub()}</p>
        </div>
      </div>
      <div class="flex-1 space-y-3 overflow-y-auto bg-slate-50/50 p-4" data-messages>
        ${active.id ? html`<div class="skeleton h-10 w-1/2"></div>` : html`<p class="py-10 text-center text-sm text-slate-400">Say hello to ${active.name}.</p>`}
      </div>
      <form class="flex items-end gap-2 border-t border-slate-100 p-3" data-composer>
        <label class="sr-only" for="chatInput">Message</label>
        <textarea id="chatInput" rows="1" maxlength="2000" class="form-control max-h-32 resize-none" placeholder="Write a message… (Enter to send, Shift+Enter for a new line)" required></textarea>
        <button type="submit" class="btn btn-primary shrink-0" aria-label="Send">${icon('send')}<span class="hidden sm:inline">Send</span></button>
      </form>`);
    showThreadPane(true);
    $('#chatInput', threadPane).focus();
  }

  function appendMessages(messages, { replace = false } = {}) {
    const box = $('[data-messages]', threadPane);
    if (!box) return;
    const nearBottom = box.scrollHeight - box.scrollTop - box.clientHeight < 80;
    if (replace) {
      setHtml(box, messages.length ? messages.map(bubble) : html`<p class="py-10 text-center text-sm text-slate-400">No messages yet. Say hello to ${active.name}.</p>`);
    } else if (messages.length) {
      $('p.py-10', box)?.remove();
      box.insertAdjacentHTML('beforeend', messages.filter((m) => !$(`[data-msg="${m.id}"]`, box)).map((m) => bubble(m).toString()).join(''));
    }
    if (messages.length) lastId = Math.max(lastId, ...messages.map((m) => m.id));
    if (replace || nearBottom || messages.some((m) => m.mine)) box.scrollTop = box.scrollHeight;
  }

  async function openConversation(conversation) {
    active = { ...conversation };
    lastId = 0;
    renderThread();
    renderList();
    ctx.setParams({ c: conversation.id });
    try {
      const res = await api.chat.messages(conversation.id);
      Object.assign(active, res.conversation);
      const sub = $('[data-thread-sub]', threadPane);
      if (sub) setHtml(sub, threadSub());
      appendMessages(res.data, { replace: true });
      conversation.unread = 0;
      renderList();
      window.dispatchEvent(new CustomEvent('bh:data-changed'));
    } catch (error) {
      toast(error.message, 'error');
    }
  }

  function startNew(contact) {
    const existing = conversations.find((c) => c.other_id === contact.id);
    if (existing) { openConversation(existing); return; }
    active = { id: null, tenantId: contact.id, name: contact.name, photo: null, houses: contact.houses };
    lastId = 0;
    renderThread();
    renderList();
  }

  async function poll() {
    if (document.visibilityState !== 'visible' || !ctx.isCurrent()) return;
    if (active?.id) {
      try {
        const res = await api.chat.messages(active.id, lastId);
        appendMessages(res.data);
      } catch { /* try again next time */ }
    }
    loadList();
  }

  async function send(text) {
    if (sending || !text.trim()) return;
    sending = true;
    try {
      const res = await api.chat.send(active.id ? { conversation_id: active.id, body: text } : { tenant_id: active.tenantId, body: text });
      if (!active.id) {
        active.id = res.conversation.id;
        ctx.setParams({ c: active.id });
        appendMessages([res.data], { replace: true });
      } else {
        appendMessages([res.data]);
      }
      loadList();
    } catch (error) {
      toast(error.message, 'error');
      throw error;
    } finally {
      sending = false;
    }
  }

  async function openNewChatPicker() {
    let contacts = [];
    try { contacts = await api.chat.contacts(); } catch (error) { toast(error.message, 'error'); return; }
    const modal = openModal({
      title: 'New chat',
      subtitle: 'Your tenants and students who reserved or messaged you.',
      body: contacts.length ? html`<ul class="divide-y divide-slate-100 rounded-2xl border border-slate-100">${contacts.map((c) => html`
        <li><button type="button" class="flex w-full items-center gap-3 px-4 py-3 text-left hover:bg-slate-50" data-contact="${c.id}">
          ${avatar(c.name)}<span class="min-w-0 flex-1"><span class="block truncate font-semibold text-ink">${c.name}</span>
          <span class="block truncate text-xs text-slate-500">${c.relation}${c.houses ? ` · ${c.houses}` : ''}</span></span>${icon('chevronRight', 'h-4 w-4 text-slate-400')}</button></li>`)}</ul>`
        : html`<p class="py-8 text-center text-sm text-slate-500">You can chat with students after they reserve a room or send you a message.</p>`,
    });
    $$('[data-contact]', modal.el).forEach((btn) => btn.addEventListener('click', () => {
      modal.close();
      startNew(contacts.find((c) => String(c.id) === btn.dataset.contact));
    }));
  }

  container.addEventListener('click', (event) => {
    const open = event.target.closest('[data-open]');
    if (open) { openConversation(conversations.find((c) => String(c.id) === open.dataset.open)); return; }
    if (event.target.closest('[data-new-chat]')) { openNewChatPicker(); return; }
    if (event.target.closest('[data-back]')) { active = null; showThreadPane(false); renderList(); }
  });
  container.addEventListener('submit', async (event) => {
    if (!event.target.matches('[data-composer]')) return;
    event.preventDefault();
    const input = $('#chatInput', container);
    const text = input.value;
    input.value = '';
    try { await send(text); } catch { input.value = text; }
    input.focus();
  });
  container.addEventListener('keydown', (event) => {
    if (event.target.id === 'chatInput' && event.key === 'Enter' && !event.shiftKey) {
      event.preventDefault();
      event.target.form.requestSubmit();
    }
  });
  $('[data-search]', container).addEventListener('input', (e) => { filter = e.target.value.trim().toLowerCase(); renderList(); });

  await loadList();
  // Open from a link: #/chat?tenant=13 (from My Tenants / Payments) or #/chat?c=4
  const tenantId = Number(ctx.params.get('tenant'));
  const conversationId = Number(ctx.params.get('c'));
  if (conversationId && conversations.some((c) => c.id === conversationId)) {
    openConversation(conversations.find((c) => c.id === conversationId));
  } else if (tenantId) {
    const existing = conversations.find((c) => c.other_id === tenantId);
    if (existing) openConversation(existing);
    else {
      const contact = (await api.chat.contacts().catch(() => [])).find((c) => c.id === tenantId);
      if (contact) startNew(contact);
      else toast('You can chat with this student after they reserve a room or message you.', 'error');
    }
  } else if (!isMobile() && conversations.length) {
    openConversation(conversations[0]);
  }

  timer = setInterval(poll, POLL_MS);
  return () => clearInterval(timer);
}


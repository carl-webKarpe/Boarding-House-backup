/* Messages from students ("Contact Landlord"): read, reply, close. */

import { api } from '../api.js';
import { html, $, icon, badge, fmtDateTime, timeAgo, toast, openModal, avatar, withLoading } from '../ui.js';
import { createListPage, actionButton } from '../../../../admin/assets/js/pages/_list.js';

async function openMessage(id, list) {
  const m = await api.messages.get(id);
  list.reload();
  window.dispatchEvent(new CustomEvent('bh:data-changed'));

  const modal = openModal({
    title: m.sender_name,
    subtitle: `About ${m.house_name}${m.room_number ? ` · ${m.room_number}` : ''}`,
    size: 'lg',
    body: html`
      <div class="flex flex-wrap gap-x-4 gap-y-1 text-sm text-slate-500">
        <a class="inline-flex items-center gap-1 font-semibold text-primary hover:underline" href="mailto:${m.sender_email}">${icon('mail', 'h-4 w-4')} ${m.sender_email}</a>
        ${m.sender_phone ? html`<a class="inline-flex items-center gap-1 font-semibold text-primary hover:underline" href="tel:${m.sender_phone}">${icon('phone', 'h-4 w-4')} ${m.sender_phone}</a>` : ''}
        <span>${m.is_registered ? 'Registered student' : 'Visitor (no account)'}</span>
      </div>
      <div class="mt-4 space-y-3">
        <div class="max-w-[90%]">
          <p class="message-bubble bg-slate-100 text-slate-700">${m.message}</p>
          <p class="mt-1 text-[11px] text-slate-400">${fmtDateTime(m.created_at)}</p>
        </div>
        ${m.reply ? html`
          <div class="ml-auto max-w-[90%] text-right">
            <p class="message-bubble bg-primary-50 text-left text-slate-700">${m.reply}</p>
            <p class="mt-1 text-[11px] text-slate-400">Your reply · ${fmtDateTime(m.replied_at)}</p>
          </div>` : ''}
      </div>
      ${m.status === 'closed' ? html`<p class="mt-5 rounded-xl bg-slate-50 p-3 text-sm text-slate-500">This conversation is closed.</p>` : html`
        <form id="replyForm" class="mt-5" novalidate>
          <label class="form-label" for="replyText">${m.reply ? 'Send a new reply' : 'Your reply'}</label>
          <textarea id="replyText" rows="4" maxlength="2000" class="form-control" required placeholder="Answer the student's question…"></textarea>
          <p class="form-hint">${m.is_registered ? 'The student sees your reply under My Reservations and gets a notification.' : 'This visitor has no account - also contact them by email or phone.'}</p>
        </form>`}`,
    footer: html`
      <button type="button" class="btn btn-secondary" data-close>Close</button>
      ${m.status !== 'closed' ? html`<button type="button" class="btn btn-ghost" data-close-conversation>Close conversation</button>
        <button type="submit" form="replyForm" class="btn btn-primary">${icon('message')} Send reply</button>` : ''}`,
  });

  $('#replyForm', modal.el)?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const text = $('#replyText', modal.el).value.trim();
    if (!text) { toast('Write a reply first.', 'error'); return; }
    try {
      await withLoading($('[form="replyForm"]', modal.el), async () => {
        const res = await api.messages.reply(m.id, text);
        toast(res.message);
      });
      modal.close();
      list.changed();
    } catch (error) {
      toast(error.message, 'error');
    }
  });
  $('[data-close-conversation]', modal.el)?.addEventListener('click', async () => {
    try {
      const res = await api.messages.close(m.id);
      toast(res.message);
      modal.close();
      list.changed();
    } catch (error) {
      toast(error.message, 'error');
    }
  });
}

export async function render(view, ctx) {
  createListPage(view, ctx, {
    title: 'Inquiries',
    description: 'Questions students and visitors sent with "Contact Landlord" on your listings. For ongoing conversations with your tenants, use Chat.',
    tabKey: 'status',
    tabs: (c) => [
      { value: '', label: 'All', count: c.all_messages },
      { value: 'new', label: 'Open', count: c.open_messages },
      { value: 'replied', label: 'Replied', count: c.replied },
      { value: 'closed', label: 'Closed', count: c.closed },
    ],
    searchPlaceholder: 'Search…',
    fetch: async (query) => {
      const res = await api.messages.list({ status: query.status });
      const q = String(query.q || '').toLowerCase();
      return { ...res, data: res.data.filter((m) => !q || `${m.sender_name} ${m.sender_email} ${m.house_name} ${m.message}`.toLowerCase().includes(q)) };
    },
    columns: () => [
      {
        key: 'sender_name', label: 'From', primary: true,
        render: (m) => html`<div class="flex items-center gap-3">${avatar(m.sender_name)}<div class="min-w-0">
          <p class="truncate ${m.status === 'new' ? 'font-bold' : 'font-semibold'} text-ink">${m.sender_name}</p>
          <p class="truncate text-xs text-slate-500">${m.house_name}${m.room_number ? ` · ${m.room_number}` : ''}</p></div></div>`,
      },
      { key: 'message', label: 'Message', render: (m) => html`<p class="max-w-xs truncate text-sm text-slate-600">${m.message}</p>` },
      { key: 'status', label: 'Status', render: (m) => badge(m.status) },
      { key: 'created_at', label: 'Received', render: (m) => timeAgo(m.created_at) },
    ],
    actions: (m) => actionButton('open', m.id, 'eye', 'Open message'),
    emptyState: () => html`
      <div class="flex flex-col items-center px-6 py-14 text-center">
        <span class="grid h-14 w-14 place-items-center rounded-2xl bg-slate-100 text-slate-400">${icon('message', 'h-6 w-6')}</span>
        <p class="mt-4 font-semibold text-ink">No messages</p>
        <p class="mt-1 max-w-sm text-sm text-slate-500">When students use "Contact Landlord" on your rooms, their messages appear here.</p>
      </div>`,
    onAction: (action, id, row, list) => openMessage(id, list),
  });
}

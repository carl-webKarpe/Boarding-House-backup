/* Notifications centre. */

import { api } from '../api.js';
import { html, setHtml, icon, $, $$, timeAgo, fmtDateTime, toast, pageHeader, tabs, pagination, skeletonRows, errorState, emptyState } from '../ui.js';

const TYPE_STYLES = {
  user_registered: ['users', 'bg-sky-50 text-sky-600'],
  landlord_registered: ['landlord', 'bg-violet-50 text-violet-600'],
  listing_submitted: ['house', 'bg-amber-50 text-amber-600'],
  listing_pending: ['clock', 'bg-amber-50 text-amber-600'],
  booking_created: ['calendar', 'bg-teal-50 text-teal-600'],
  system: ['info', 'bg-slate-100 text-slate-600'],
};

const safeLink = (link) => (typeof link === 'string' && link.startsWith('#/') ? link : '');

export async function render(view, ctx) {
  const container = document.createElement('div');
  view.replaceChildren(container);
  const state = { filter: ctx.params.get('filter') === 'unread' ? 'unread' : 'all', page: Number(ctx.params.get('page')) || 1 };

  setHtml(container, html`
    ${pageHeader({
      title: 'Notifications',
      description: 'New registrations, listings waiting for approval and other important events.',
      actions: html`
        <button type="button" class="btn btn-secondary" data-clear>${icon('trash')} Clear read</button>
        <button type="button" class="btn btn-primary" data-read-all>${icon('check')} Mark all as read</button>`,
    })}
    <div class="card overflow-hidden">
      <div class="border-b border-slate-100 p-4 sm:p-5" data-tabs></div>
      <div data-body>${skeletonRows(6)}</div>
      <div data-pagination></div>
    </div>`);

  const body = $('[data-body]', container);

  const load = async () => {
    ctx.setParams({ filter: state.filter === 'unread' ? 'unread' : '', page: state.page > 1 ? state.page : '' });
    let res;
    try {
      res = await api.notifications.list({ filter: state.filter, page: state.page });
    } catch (error) {
      setHtml(body, errorState(error.message));
      return;
    }
    if (!ctx.isCurrent()) return;

    setHtml($('[data-tabs]', container), tabs([
      { value: 'all', label: 'All' },
      { value: 'unread', label: 'Unread', count: res.unread },
    ], state.filter));

    setHtml(body, res.data.length ? html`<ul class="divide-y divide-slate-100">${res.data.map((n) => {
      const [iconName, color] = TYPE_STYLES[n.type] || TYPE_STYLES.system;
      const link = safeLink(n.link);
      return html`
        <li class="flex gap-4 px-4 py-4 sm:px-5 ${n.is_read ? '' : 'bg-primary-50/50'}">
          <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl ${color}">${icon(iconName, 'h-5 w-5')}</span>
          <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
              <p class="font-semibold text-ink">${n.title}</p>
              ${n.is_read ? '' : html`<span class="badge badge-green no-dot">New</span>`}
            </div>
            <p class="mt-0.5 text-sm text-slate-600">${n.message}</p>
            <p class="mt-1 text-xs text-slate-400" title="${fmtDateTime(n.created_at)}">${timeAgo(n.created_at)}</p>
          </div>
          <div class="flex shrink-0 items-start gap-1">
            ${link ? html`<a href="${link}" class="btn btn-secondary btn-sm" data-open="${n.id}">Open</a>` : ''}
            <button type="button" class="icon-btn" data-toggle="${n.id}" data-read="${n.is_read ? '1' : '0'}" title="${n.is_read ? 'Mark as unread' : 'Mark as read'}" aria-label="${n.is_read ? 'Mark as unread' : 'Mark as read'}">${icon(n.is_read ? 'mail' : 'check')}</button>
            <button type="button" class="icon-btn danger" data-delete="${n.id}" title="Delete" aria-label="Delete notification">${icon('trash')}</button>
          </div>
        </li>`;
    })}</ul>` : emptyState({ title: state.filter === 'unread' ? 'No unread notifications' : 'No notifications', message: 'You are all caught up.', iconName: 'bell' }));
    setHtml($('[data-pagination]', container), pagination(res.meta));
  };

  const changed = () => {
    window.dispatchEvent(new CustomEvent('bh:data-changed'));
    return load();
  };

  container.addEventListener('click', async (event) => {
    const t = event.target;
    try {
      if (t.closest('[data-tab]')) { state.filter = t.closest('[data-tab]').dataset.tab; state.page = 1; await load(); }
      else if (t.closest('[data-page]') && !t.closest('[data-page]').disabled) { state.page = Number(t.closest('[data-page]').dataset.page); await load(); }
      else if (t.closest('[data-toggle]')) { const b = t.closest('[data-toggle]'); await api.notifications.markRead(b.dataset.toggle, b.dataset.read !== '1'); await changed(); }
      else if (t.closest('[data-delete]')) { await api.notifications.remove(t.closest('[data-delete]').dataset.delete); toast('Notification deleted.'); await changed(); }
      else if (t.closest('[data-open]')) { api.notifications.markRead(t.closest('[data-open]').dataset.open).then(() => window.dispatchEvent(new CustomEvent('bh:data-changed'))).catch(() => {}); }
      else if (t.closest('[data-read-all]')) { await api.notifications.markAllRead(); toast('All notifications marked as read.'); await changed(); }
      else if (t.closest('[data-clear]')) { const r = await api.notifications.clearRead(); toast(r.message); await changed(); }
      else if (t.closest('[data-retry]')) { await load(); }
    } catch (error) {
      toast(error.message, 'error');
    }
  });

  await load();
}

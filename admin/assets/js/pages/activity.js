/* System activity log (audit trail). */

import { api } from '../api.js';
import { html, setHtml, icon, $, debounce, fmtDateTime, timeAgo, pageHeader, pagination, skeletonRows, errorState, emptyState, badge, downloadCsv, toast } from '../ui.js';
import { activityStyle } from './dashboard.js';

const ACTION_FILTERS = [
  { value: 'register', label: 'Registrations' },
  { value: 'login', label: 'Logins' },
  { value: 'listing', label: 'Listings' },
  { value: 'room', label: 'Rooms' },
  { value: 'booking', label: 'Bookings' },
  { value: 'user', label: 'User management' },
  { value: 'settings', label: 'Settings' },
];

export async function render(view, ctx) {
  const container = document.createElement('div');
  view.replaceChildren(container);
  const state = {
    q: ctx.params.get('q') || '',
    action: ctx.params.get('action') || '',
    date_from: ctx.params.get('date_from') || '',
    date_to: ctx.params.get('date_to') || '',
    page: Number(ctx.params.get('page')) || 1,
  };

  setHtml(container, html`
    ${pageHeader({
      title: 'Activity Log',
      description: 'A record of important actions: registrations, logins, approvals and changes made by administrators.',
      actions: html`<button type="button" class="btn btn-secondary" data-export>${icon('download')} Export CSV</button>`,
    })}
    <div class="card overflow-hidden">
      <div class="grid gap-2 border-b border-slate-100 p-4 sm:grid-cols-2 sm:p-5 lg:grid-cols-[1fr_12rem_10rem_10rem]">
        <input type="search" class="form-control" data-q placeholder="Search description, user or IP…" aria-label="Search activity" value="${state.q}" />
        <select class="form-control" data-action-filter aria-label="Activity type">
          <option value="">All activity</option>
          ${ACTION_FILTERS.map((a) => html`<option value="${a.value}" ${a.value === state.action ? 'selected' : ''}>${a.label}</option>`)}
        </select>
        <input type="date" class="form-control" data-from aria-label="From date" value="${state.date_from}" />
        <input type="date" class="form-control" data-to aria-label="To date" value="${state.date_to}" />
      </div>
      <div data-body>${skeletonRows(8)}</div>
      <div data-pagination></div>
    </div>`);

  const body = $('[data-body]', container);
  let requestId = 0;

  const load = async () => {
    const id = ++requestId;
    ctx.setParams({ ...state, page: state.page > 1 ? state.page : '' });
    let res;
    try {
      res = await api.activity.list({ ...state, per_page: 20 });
    } catch (error) {
      setHtml(body, errorState(error.message));
      return;
    }
    if (id !== requestId || !ctx.isCurrent()) return;
    setHtml(body, res.data.length ? html`<ul class="divide-y divide-slate-100">${res.data.map((a) => {
      const [iconName, color] = activityStyle(a.action);
      return html`
        <li class="flex items-start gap-4 px-4 py-3.5 sm:px-5">
          <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl ${color}">${icon(iconName, 'h-4 w-4')}</span>
          <div class="min-w-0 flex-1">
            <p class="text-sm font-medium text-ink">${a.description}</p>
            <p class="mt-0.5 flex flex-wrap items-center gap-x-2 text-xs text-slate-500">
              <span>${a.actor}</span>${a.role ? badge(a.role) : ''}<span>·</span><span>${a.ip_address || '—'}</span>
            </p>
          </div>
          <time class="shrink-0 text-right text-xs text-slate-400" datetime="${a.created_at}" title="${fmtDateTime(a.created_at)}">${timeAgo(a.created_at)}<br class="hidden sm:block"><span class="hidden sm:inline">${fmtDateTime(a.created_at)}</span></time>
        </li>`;
    })}</ul>` : emptyState({ title: 'No activity found', message: 'Try a different search or date range.', iconName: 'activity' }));
    setHtml($('[data-pagination]', container), pagination(res.meta));
  };

  const reset = () => { state.page = 1; load(); };
  $('[data-q]', container).addEventListener('input', debounce((e) => { state.q = e.target.value.trim(); reset(); }, 300));
  $('[data-action-filter]', container).addEventListener('change', (e) => { state.action = e.target.value; reset(); });
  $('[data-from]', container).addEventListener('change', (e) => { state.date_from = e.target.value; reset(); });
  $('[data-to]', container).addEventListener('change', (e) => { state.date_to = e.target.value; reset(); });
  container.addEventListener('click', (event) => {
    const pageBtn = event.target.closest('[data-page]');
    if (pageBtn && !pageBtn.disabled) { state.page = Number(pageBtn.dataset.page); load(); }
    if (event.target.closest('[data-retry]')) load();
  });
  $('[data-export]', container).addEventListener('click', async () => {
    try {
      const rows = [];
      for (let page = 1; page <= 20; page++) {
        const res = await api.activity.list({ ...state, page, per_page: 100 });
        rows.push(...res.data);
        if (page >= res.meta.total_pages) break;
      }
      downloadCsv(`activity-log-${new Date().toISOString().slice(0, 10)}.csv`, ['Date', 'User', 'Role', 'Action', 'Description', 'IP address'],
        rows.map((a) => [a.created_at, a.actor, a.role || '', a.action, a.description, a.ip_address || '']));
      toast(`Exported ${rows.length} entries.`);
    } catch (error) {
      toast(error.message, 'error');
    }
  });

  await load();
}

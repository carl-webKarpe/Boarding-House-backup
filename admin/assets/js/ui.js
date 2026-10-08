/* ==========================================================================
   Shared UI toolkit for the admin dashboard.

   Security note: always build markup with the `html` tagged template.
   Every ${value} is HTML-escaped automatically, so data typed by users
   (names, listing titles, notes) can never inject scripts.
   ========================================================================== */

/* ---------------------------------------------------------------------- *
 * Safe HTML templating
 * ---------------------------------------------------------------------- */
class SafeHtml {
  constructor(value) { this.value = value; }
  toString() { return this.value; }
}

const ESCAPES = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;', '`': '&#96;' };
export const escapeHtml = (value) => String(value ?? '').replace(/[&<>"'`]/g, (c) => ESCAPES[c]);

function renderValue(value) {
  if (value === null || value === undefined || value === false) return '';
  if (value instanceof SafeHtml) return value.value;
  if (Array.isArray(value)) return value.map(renderValue).join('');
  return escapeHtml(value);
}

/** Tagged template: html`<p>${userText}</p>` escapes userText. */
export function html(strings, ...values) {
  let out = strings[0];
  values.forEach((value, i) => { out += renderValue(value) + strings[i + 1]; });
  return new SafeHtml(out);
}

/** Marks trusted markup (icons, already-built html) as safe. Never pass user data. */
export const raw = (markup) => new SafeHtml(markup);

export function setHtml(element, content) {
  element.innerHTML = renderValue(content);
  return element;
}

export const $ = (selector, root = document) => root.querySelector(selector);
export const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];

export function debounce(fn, wait = 300) {
  let timer;
  return (...args) => {
    clearTimeout(timer);
    timer = setTimeout(() => fn(...args), wait);
  };
}

/* ---------------------------------------------------------------------- *
 * Icons (stroke icons, 24x24, Lucide-style paths)
 * ---------------------------------------------------------------------- */
const ICON_PATHS = {
  dashboard: '<rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>',
  users: '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
  tenant: '<path d="M22 10 12 5 2 10l10 5 10-5Z"/><path d="M6 12v5c3 2 9 2 12 0v-5"/>',
  landlord: '<path d="M15 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0Z"/><path d="M3 21v-1a6 6 0 0 1 9.5-4.9"/><path d="m16 19 2 2 4-4"/>',
  house: '<path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-5v-6h-6v6H4a1 1 0 0 1-1-1Z"/>',
  door: '<path d="M13 4h3a2 2 0 0 1 2 2v14"/><path d="M2 20h3"/><path d="M13 20h9"/><path d="M10 12v.01"/><path d="M13 4.56v16.16a.5.5 0 0 1-.6.49L5 19.5V5.6a1 1 0 0 1 .8-.98l6-1.2a1 1 0 0 1 1.2.98Z"/>',
  bed: '<path d="M2 4v16"/><path d="M2 8h18a2 2 0 0 1 2 2v10"/><path d="M2 17h20"/><path d="M6 8v9"/>',
  calendar: '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
  chart: '<path d="M3 3v18h18"/><path d="M7 15v2M11 11v6M15 7v10M19 12v5"/>',
  bell: '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>',
  settings: '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1Z"/>',
  activity: '<path d="M22 12h-4l-3 9L9 3l-3 9H2"/>',
  logout: '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5M21 12H9"/>',
  plus: '<path d="M12 5v14M5 12h14"/>',
  eye: '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>',
  edit: '<path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/>',
  trash: '<path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m3 0v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M10 11v6M14 11v6"/>',
  check: '<path d="M20 6 9 17l-5-5"/>',
  x: '<path d="M18 6 6 18M6 6l12 12"/>',
  ban: '<circle cx="12" cy="12" r="10"/><path d="m4.9 4.9 14.2 14.2"/>',
  unlock: '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 9.9-1"/>',
  search: '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
  filter: '<path d="M22 3H2l8 9.46V19l4 2v-8.54Z"/>',
  download: '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m7 10 5 5 5-5M12 15V3"/>',
  refresh: '<path d="M21 12a9 9 0 1 1-3-6.7L21 8"/><path d="M21 3v5h-5"/>',
  info: '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/>',
  alert: '<path d="m21.73 18-8-14a2 2 0 0 0-3.46 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4M12 17h.01"/>',
  checkCircle: '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="M22 4 12 14.01l-3-3"/>',
  clock: '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
  pin: '<path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/>',
  phone: '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.79 19.79 0 0 1 2.12 4.18 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92Z"/>',
  mail: '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 6L2 7"/>',
  image: '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.09-3.09a2 2 0 0 0-2.82 0L6 21"/>',
  file: '<path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5Z"/><path d="M14 2v6h6"/>',
  tag: '<path d="M12 2H2v10l9.29 9.29a1 1 0 0 0 1.41 0l8.59-8.59a1 1 0 0 0 0-1.41Z"/><circle cx="7" cy="7" r="1.5"/>',
  shield: '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/>',
  key: '<circle cx="7.5" cy="15.5" r="5.5"/><path d="m21 2-9.6 9.6M15.5 7.5l3 3L22 7l-3-3"/>',
  trendUp: '<path d="m22 7-8.5 8.5-5-5L2 17"/><path d="M16 7h6v6"/>',
  chevronLeft: '<path d="m15 18-6-6 6-6"/>',
  chevronRight: '<path d="m9 18 6-6-6-6"/>',
  sort: '<path d="m7 15 5 5 5-5M7 9l5-5 5 5"/>',
  wrench: '<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76Z"/>',
  message: '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2Z"/>',
  user: '<circle cx="12" cy="8" r="4"/><path d="M4 21v-1a7 7 0 0 1 16 0v1"/>',
  upload: '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m17 8-5-5-5 5M12 3v12"/>',
  map: '<path d="m3 6 6-3 6 3 6-3v15l-6 3-6-3-6 3Z"/><path d="M9 3v15M15 6v15"/>',
  star: '<path d="m12 2 3.1 6.3 6.9 1-5 4.9 1.2 6.8L12 17.8 5.8 21l1.2-6.8-5-4.9 6.9-1Z"/>',
  sparkles: '<path d="m12 3-1.9 5.8a2 2 0 0 1-1.3 1.3L3 12l5.8 1.9a2 2 0 0 1 1.3 1.3L12 21l1.9-5.8a2 2 0 0 1 1.3-1.3L21 12l-5.8-1.9a2 2 0 0 1-1.3-1.3Z"/>',
};

export function icon(name, className = '') {
  const paths = ICON_PATHS[name] || ICON_PATHS.info;
  return raw(`<svg class="${escapeHtml(className)}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${paths}</svg>`);
}

/* ---------------------------------------------------------------------- *
 * Formatting
 * ---------------------------------------------------------------------- */
const parseDate = (value) => (value ? new Date(String(value).replace(' ', 'T')) : null);

export function fmtDate(value) {
  const d = parseDate(value);
  return d && !Number.isNaN(d.getTime()) ? d.toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' }) : '—';
}

export function fmtDateTime(value) {
  const d = parseDate(value);
  return d && !Number.isNaN(d.getTime())
    ? d.toLocaleString('en-PH', { year: 'numeric', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' })
    : '—';
}

export function timeAgo(value) {
  const d = parseDate(value);
  if (!d || Number.isNaN(d.getTime())) return '—';
  const seconds = Math.round((Date.now() - d.getTime()) / 1000);
  if (seconds < 45) return 'just now';
  const units = [['year', 31536000], ['month', 2592000], ['week', 604800], ['day', 86400], ['hour', 3600], ['minute', 60]];
  for (const [unit, size] of units) {
    const amount = Math.floor(Math.abs(seconds) / size);
    if (amount >= 1) return `${amount} ${unit}${amount > 1 ? 's' : ''} ago`;
  }
  return 'just now';
}

export const money = (value) => (value === null || value === undefined || value === '' ? '—' : `₱${Number(value).toLocaleString('en-PH', { maximumFractionDigits: 0 })}`);
export const num = (value) => Number(value || 0).toLocaleString('en-PH');
export const titleCase = (value) => String(value || '').replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());

/* ---------------------------------------------------------------------- *
 * Badges
 * ---------------------------------------------------------------------- */
const BADGE_COLORS = {
  active: 'green', approved: 'green', available: 'green', verified: 'green', completed: 'blue',
  pending: 'amber', maintenance: 'amber', unverified: 'slate',
  disabled: 'red', rejected: 'red', cancelled: 'red',
  inactive: 'slate', occupied: 'violet',
  tenant: 'blue', landlord: 'violet', admin: 'slate', super_admin: 'slate',
  // Landlord dashboard
  pending_approval: 'amber', fully_occupied: 'violet', temporarily_unavailable: 'slate', unavailable: 'amber',
  new: 'blue', read: 'slate', replied: 'green', closed: 'slate',
};
const BADGE_LABELS = { super_admin: 'Super Admin', admin: 'Administrator', new: 'Unread' };

export function badge(status, label) {
  const color = BADGE_COLORS[status] || 'slate';
  return html`<span class="badge badge-${color}">${label || BADGE_LABELS[status] || titleCase(status)}</span>`;
}

export function avatar(name, size = 'h-9 w-9') {
  const initials = String(name || '?').split(/\s+/).filter(Boolean).slice(0, 2).map((p) => p[0].toUpperCase()).join('');
  const palette = ['bg-emerald-100 text-emerald-700', 'bg-sky-100 text-sky-700', 'bg-amber-100 text-amber-700', 'bg-violet-100 text-violet-700', 'bg-rose-100 text-rose-700', 'bg-teal-100 text-teal-700'];
  const color = palette[[...String(name || '')].reduce((sum, c) => sum + c.charCodeAt(0), 0) % palette.length];
  return html`<span class="grid ${size} shrink-0 place-items-center rounded-xl text-xs font-bold ${color}" aria-hidden="true">${initials || '?'}</span>`;
}

/* ---------------------------------------------------------------------- *
 * Toasts
 * ---------------------------------------------------------------------- */
export function toast(message, type = 'success') {
  const root = document.getElementById('toastRoot');
  if (!root) return;
  const styles = { success: ['bg-emerald-600', 'checkCircle'], error: ['bg-rose-600', 'alert'], info: ['bg-slate-800', 'info'] };
  const [bg, iconName] = styles[type] || styles.info;
  const el = document.createElement('div');
  el.className = `toast ${bg}`;
  el.setAttribute('role', type === 'error' ? 'alert' : 'status');
  setHtml(el, html`${icon(iconName)}<span>${message}</span>`);
  root.appendChild(el);
  setTimeout(() => {
    el.classList.add('leaving');
    setTimeout(() => el.remove(), 220);
  }, type === 'error' ? 6000 : 3500);
}

/* ---------------------------------------------------------------------- *
 * Modals
 * ---------------------------------------------------------------------- */
const SIZES = { sm: 'sm:max-w-md', md: 'sm:max-w-xl', lg: 'sm:max-w-3xl', xl: 'sm:max-w-5xl' };

/**
 * Opens a modal dialog. `footer` is optional markup for the action row.
 * Returns { el, body, close }.
 */
export function openModal({ title, subtitle = '', body, footer = '', size = 'md', onClose } = {}) {
  const previousFocus = document.activeElement;
  const wrapper = document.createElement('div');
  wrapper.className = 'modal-backdrop';
  const titleId = `modal-title-${Date.now()}`;
  setHtml(wrapper, html`
    <div class="modal-dialog ${SIZES[size] || SIZES.md}" role="dialog" aria-modal="true" aria-labelledby="${titleId}">
      <div class="flex items-start gap-3 border-b border-slate-100 px-5 py-4 sm:px-6">
        <div class="min-w-0 flex-1">
          <h2 id="${titleId}" class="font-display text-lg font-semibold text-ink">${title}</h2>
          ${subtitle ? html`<p class="mt-0.5 text-sm text-slate-500">${subtitle}</p>` : ''}
        </div>
        <button type="button" class="icon-btn" data-close aria-label="Close">${icon('x')}</button>
      </div>
      <div class="modal-body flex-1 overflow-y-auto px-5 py-5 sm:px-6">${body}</div>
      ${footer ? html`<div class="modal-footer flex flex-wrap justify-end gap-2 border-t border-slate-100 px-5 py-4 sm:px-6">${footer}</div>` : ''}
    </div>`);

  let closed = false;
  const close = () => {
    if (closed) return;
    closed = true;
    document.removeEventListener('keydown', onKey);
    wrapper.remove();
    if (!document.querySelector('.modal-backdrop')) document.body.style.overflow = '';
    previousFocus?.focus?.();
    onClose?.();
  };
  const onKey = (event) => {
    if (event.key === 'Escape' && wrapper === document.querySelector('.modal-backdrop:last-of-type')) close();
    if (event.key === 'Tab') trapFocus(event, wrapper);
  };

  wrapper.addEventListener('mousedown', (event) => { if (event.target === wrapper) close(); });
  $$('[data-close]', wrapper).forEach((btn) => btn.addEventListener('click', close));
  document.addEventListener('keydown', onKey);
  document.getElementById('modalRoot').appendChild(wrapper);
  document.body.style.overflow = 'hidden';

  const firstField = wrapper.querySelector('.modal-body input:not([type=hidden]), .modal-body select, .modal-body textarea');
  (firstField || wrapper.querySelector('[data-close]')).focus();

  return { el: wrapper, body: $('.modal-body', wrapper), close };
}

function trapFocus(event, container) {
  const focusable = $$('a[href], button:not([disabled]), input:not([disabled]):not([type=hidden]), select, textarea, [tabindex]:not([tabindex="-1"])', container);
  if (!focusable.length) return;
  const first = focusable[0];
  const last = focusable[focusable.length - 1];
  if (event.shiftKey && document.activeElement === first) { last.focus(); event.preventDefault(); }
  else if (!event.shiftKey && document.activeElement === last) { first.focus(); event.preventDefault(); }
}

/** Promise-based confirmation dialog. Resolves true when confirmed. */
export function confirmDialog({ title, message, confirmText = 'Confirm', danger = false, iconName } = {}) {
  return new Promise((resolve) => {
    let result = false;
    const modal = openModal({
      title,
      size: 'sm',
      body: html`
        <div class="flex gap-4">
          <span class="grid h-11 w-11 shrink-0 place-items-center rounded-2xl ${danger ? 'bg-rose-50 text-rose-600' : 'bg-primary-50 text-primary'}">${icon(iconName || (danger ? 'alert' : 'info'), 'h-5 w-5')}</span>
          <p class="pt-1 text-sm leading-relaxed text-slate-600">${message}</p>
        </div>`,
      footer: html`
        <button type="button" class="btn btn-secondary" data-close>Cancel</button>
        <button type="button" class="btn ${danger ? 'btn-danger' : 'btn-primary'}" data-confirm>${confirmText}</button>`,
      onClose: () => resolve(result),
    });
    const confirmBtn = $('[data-confirm]', modal.el);
    confirmBtn.addEventListener('click', () => { result = true; modal.close(); });
    confirmBtn.focus();
  });
}

/* ---------------------------------------------------------------------- *
 * Page building blocks
 * ---------------------------------------------------------------------- */
export function pageHeader({ title, description = '', actions = '' }) {
  return html`
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
      <div>
        <h1 class="font-display text-2xl font-semibold text-ink">${title}</h1>
        ${description ? html`<p class="mt-1 text-sm text-slate-500">${description}</p>` : ''}
      </div>
      ${actions ? html`<div class="flex flex-wrap gap-2">${actions}</div>` : ''}
    </div>`;
}

export function emptyState({ title = 'Nothing here yet', message = '', iconName = 'search', action = '' } = {}) {
  return html`
    <div class="flex flex-col items-center justify-center px-6 py-14 text-center">
      <span class="grid h-14 w-14 place-items-center rounded-2xl bg-slate-100 text-slate-400">${icon(iconName, 'h-6 w-6')}</span>
      <p class="mt-4 font-semibold text-ink">${title}</p>
      ${message ? html`<p class="mt-1 max-w-sm text-sm text-slate-500">${message}</p>` : ''}
      ${action ? html`<div class="mt-4">${action}</div>` : ''}
    </div>`;
}

export function errorState(message, retryAttr = 'data-retry') {
  return html`
    <div class="card flex flex-col items-center px-6 py-14 text-center">
      <span class="grid h-14 w-14 place-items-center rounded-2xl bg-rose-50 text-rose-500">${icon('alert', 'h-6 w-6')}</span>
      <p class="mt-4 font-semibold text-ink">Could not load this page</p>
      <p class="mt-1 max-w-md text-sm text-slate-500">${message}</p>
      <button type="button" class="btn btn-secondary mt-5" ${raw(retryAttr)}>${icon('refresh')} Try again</button>
    </div>`;
}

export function skeletonRows(count = 5) {
  return html`<div class="space-y-3 p-5">${Array.from({ length: count }, () => html`<div class="skeleton h-12"></div>`)}</div>`;
}

export function tabs(items, active) {
  return html`<div class="tabs" role="tablist">${items.map((item) => html`
    <button type="button" role="tab" aria-selected="${item.value === active}" class="tab ${item.value === active ? 'is-active' : ''}" data-tab="${item.value}">
      ${item.label}${item.count !== undefined ? html`<span class="count">${num(item.count)}</span>` : ''}
    </button>`)}</div>`;
}

/* ---------------------------------------------------------------------- *
 * Data table: a table on tablets/desktops, stacked cards on phones.
 *
 * columns: [{ key, label, render(row), sort, className, primary, hideOnMobile }]
 * ---------------------------------------------------------------------- */
export function dataTable({ columns, rows, actions, sort = {}, empty }) {
  if (!rows.length) return empty || emptyState();

  const sortIcon = (col) => (sort.sort === col.sort ? (sort.order === 'asc' ? '▲' : '▼') : '');
  const header = columns.map((col) => html`
    <th class="${col.className || ''}" ${col.sort ? raw(`aria-sort="${sort.sort === col.sort ? (sort.order === 'asc' ? 'ascending' : 'descending') : 'none'}"`) : ''}>
      ${col.sort ? html`<button type="button" class="sort-btn" data-sort="${col.sort}">${col.label} <span aria-hidden="true">${sortIcon(col)}</span></button>` : col.label}
    </th>`);

  const cell = (col, row) => (col.render ? col.render(row) : row[col.key] ?? '—');
  const primaryCol = columns.find((c) => c.primary) || columns[0];

  return html`
    <div class="hidden overflow-x-auto md:block">
      <table class="data-table">
        <thead><tr>${header}${actions ? html`<th class="text-right">Actions</th>` : ''}</tr></thead>
        <tbody>${rows.map((row) => html`
          <tr>
            ${columns.map((col) => html`<td class="${col.className || ''}">${cell(col, row)}</td>`)}
            ${actions ? html`<td class="text-right"><div class="inline-flex items-center gap-1">${actions(row)}</div></td>` : ''}
          </tr>`)}
        </tbody>
      </table>
    </div>
    <ul class="divide-y divide-slate-100 md:hidden">${rows.map((row) => html`
      <li class="p-4">
        <div class="mb-3">${cell(primaryCol, row)}</div>
        <dl class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
          ${columns.filter((c) => c !== primaryCol && !c.hideOnMobile).map((col) => html`
            <div class="min-w-0"><dt class="text-xs text-slate-400">${col.label}</dt><dd class="mt-0.5 truncate">${cell(col, row)}</dd></div>`)}
        </dl>
        ${actions ? html`<div class="mt-3 flex flex-wrap gap-1 border-t border-slate-100 pt-3">${actions(row)}</div>` : ''}
      </li>`)}
    </ul>`;
}

export function pagination(meta) {
  if (!meta || meta.total === 0) return '';
  const from = (meta.page - 1) * meta.per_page + 1;
  const to = Math.min(meta.total, meta.page * meta.per_page);
  return html`
    <div class="flex flex-col items-center justify-between gap-3 border-t border-slate-100 px-4 py-3 text-sm text-slate-500 sm:flex-row sm:px-5">
      <p>Showing <strong class="text-ink">${num(from)}–${num(to)}</strong> of <strong class="text-ink">${num(meta.total)}</strong></p>
      <div class="flex items-center gap-2">
        <button type="button" class="btn btn-secondary btn-sm" data-page="${meta.page - 1}" ${meta.page <= 1 ? raw('disabled') : ''}>${icon('chevronLeft')} Prev</button>
        <span class="px-1">Page ${meta.page} of ${meta.total_pages}</span>
        <button type="button" class="btn btn-secondary btn-sm" data-page="${meta.page + 1}" ${meta.page >= meta.total_pages ? raw('disabled') : ''}>Next ${icon('chevronRight')}</button>
      </div>
    </div>`;
}

/* ---------------------------------------------------------------------- *
 * Forms
 *
 * field: { name, label, type: text|email|tel|number|password|select|textarea|date|checkboxes,
 *          options: [{value,label}], required, placeholder, hint, span: 1|2, min, max, step }
 * ---------------------------------------------------------------------- */
export function formFields(fields, values = {}) {
  return html`<div class="grid gap-4 sm:grid-cols-2">${fields.map((field) => formField(field, values[field.name]))}</div>`;
}

function formField(field, value) {
  const id = `f-${field.name}`;
  const span = field.span === 2 || field.type === 'textarea' || field.type === 'checkboxes' ? 'sm:col-span-2' : '';
  const required = field.required ? raw('required') : '';
  const common = raw(`id="${id}" name="${escapeHtml(field.name)}"`);
  let control;

  if (field.type === 'select') {
    control = html`<select ${common} class="form-control" ${required}>
      ${field.placeholder !== undefined ? html`<option value="">${field.placeholder}</option>` : ''}
      ${field.options.map((opt) => html`<option value="${opt.value}" ${String(opt.value) === String(value ?? '') ? raw('selected') : ''}>${opt.label}</option>`)}
    </select>`;
  } else if (field.type === 'textarea') {
    control = html`<textarea ${common} rows="${field.rows || 3}" class="form-control" placeholder="${field.placeholder || ''}" ${required}>${value ?? ''}</textarea>`;
  } else if (field.type === 'checkboxes') {
    const selected = new Set((value || []).map(String));
    control = html`<div class="flex flex-wrap gap-2" id="${id}">${field.options.map((opt) => html`
      <label class="check-pill"><input type="checkbox" name="${field.name}" value="${opt.value}" ${selected.has(String(opt.value)) ? raw('checked') : ''} /> ${opt.label}</label>`)}</div>`;
  } else {
    const attrs = ['min', 'max', 'step', 'maxlength', 'autocomplete']
      .filter((a) => field[a] !== undefined)
      .map((a) => `${a}="${escapeHtml(field[a])}"`).join(' ');
    control = html`<input ${common} type="${field.type || 'text'}" value="${value ?? ''}" class="form-control" placeholder="${field.placeholder || ''}" ${raw(attrs)} ${required} />`;
  }

  return html`
    <div class="${span}">
      ${field.type === 'checkboxes' ? html`<p class="form-label">${field.label}</p>` : html`<label for="${id}" class="form-label">${field.label}${field.required ? html`<span class="text-rose-500"> *</span>` : ''}</label>`}
      ${control}
      ${field.hint ? html`<p class="form-hint">${field.hint}</p>` : ''}
      <p class="form-error hidden" data-error-for="${field.name}"></p>
    </div>`;
}

/** Reads a form into a plain object. Checkbox groups become arrays. */
export function readForm(form) {
  const data = {};
  const groups = new Set($$('input[type=checkbox]', form).map((c) => c.name));
  new FormData(form).forEach((value, key) => {
    if (groups.has(key)) (data[key] ||= []).push(value);
    else data[key] = typeof value === 'string' ? value.trim() : value;
  });
  groups.forEach((name) => { if (!(name in data)) data[name] = []; });
  return data;
}

export function clearErrors(form) {
  $$('.is-invalid', form).forEach((el) => el.classList.remove('is-invalid'));
  $$('[data-error-for]', form).forEach((el) => { el.textContent = ''; el.classList.add('hidden'); });
}

/** Shows API validation errors ({ field: message }) under the matching fields. */
export function showErrors(form, errors = {}) {
  clearErrors(form);
  let first = null;
  Object.entries(errors).forEach(([name, message]) => {
    const holder = $(`[data-error-for="${CSS.escape(name)}"]`, form);
    const input = form.elements[name];
    if (holder) { holder.textContent = message; holder.classList.remove('hidden'); }
    if (input?.classList) { input.classList.add('is-invalid'); first ||= input; }
  });
  first?.focus();
}

/** Disables a button and shows a spinner while `task` runs. */
export async function withLoading(button, task) {
  const original = button.innerHTML;
  button.disabled = true;
  button.innerHTML = '<span class="h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white"></span> Saving…';
  try {
    return await task();
  } finally {
    button.disabled = false;
    button.innerHTML = original;
  }
}

/**
 * Opens a form in a modal and wires up submission + error display.
 * `onSubmit(data)` should call the API; returning resolves and closes.
 */
export function formModal({ title, subtitle, fields, values = {}, submitText = 'Save', size = 'md', onSubmit, extra = '' }) {
  const formId = `form-${Date.now()}`;
  const modal = openModal({
    title,
    subtitle,
    size,
    body: html`<form id="${formId}" novalidate>${formFields(fields, values)}${extra}</form>`,
    footer: html`
      <button type="button" class="btn btn-secondary" data-close>Cancel</button>
      <button type="submit" form="${formId}" class="btn btn-primary">${submitText}</button>`,
  });

  const form = $(`#${formId}`, modal.el);
  const submitBtn = $(`[form="${formId}"]`, modal.el);
  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    clearErrors(form);
    try {
      await withLoading(submitBtn, () => onSubmit(readForm(form), form));
      modal.close();
    } catch (error) {
      if (error.errors && Object.keys(error.errors).length) showErrors(form, error.errors);
      toast(error.message || 'Could not save.', 'error');
    }
  });

  return { ...modal, form };
}

/** Small "label: value" description list used in detail views. */
export function details(items) {
  return html`<dl class="grid gap-x-6 gap-y-4 sm:grid-cols-2">${items.filter(Boolean).map(([label, value]) => html`
    <div class="min-w-0"><dt class="text-xs font-medium uppercase tracking-wide text-slate-400">${label}</dt><dd class="mt-1 break-words text-sm text-ink">${value === '' || value === null || value === undefined ? '—' : value}</dd></div>`)}</dl>`;
}

/** Downloads rows as a CSV file (opens in Excel). */
export function downloadCsv(filename, headers, rows) {
  const escape = (v) => {
    const s = String(v ?? '');
    // Prevent spreadsheet formula injection.
    const safe = /^[=+\-@\t\r]/.test(s) ? `'${s}` : s;
    return /[",\n]/.test(safe) ? `"${safe.replace(/"/g, '""')}"` : safe;
  };
  const csv = [headers, ...rows].map((r) => r.map(escape).join(',')).join('\r\n');
  const blob = new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8' });
  const link = document.createElement('a');
  link.href = URL.createObjectURL(blob);
  link.download = filename;
  link.click();
  setTimeout(() => URL.revokeObjectURL(link.href), 1000);
}

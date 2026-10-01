/* ==========================================================================
   Admin dashboard bootstrap: layout, navigation, routing, global search,
   notifications dropdown and logout.

   Routes are URL hashes, e.g. #/users?role=tenant&page=2, so every view can
   be bookmarked and the browser Back button works.
   ========================================================================== */

import { api } from './api.js';
import { html, setHtml, icon, $, $$, debounce, toast, confirmDialog, timeAgo, errorState, badge } from './ui.js';
import { destroyCharts } from './charts.js';

const NAV = [
  {
    title: 'Overview',
    items: [{ path: 'dashboard', label: 'Dashboard', icon: 'dashboard' }],
  },
  {
    title: 'Management',
    items: [
      { path: 'users', label: 'Users', icon: 'users' },
      { path: 'landlords', label: 'Landlords', icon: 'landlord', badge: 'landlords' },
      { path: 'boarding-houses', label: 'Boarding Houses', icon: 'house', badge: 'listings' },
      { path: 'rooms', label: 'Rooms', icon: 'bed' },
      { path: 'bookings', label: 'Bookings', icon: 'calendar', badge: 'bookings' },
    ],
  },
  {
    title: 'Insights',
    items: [
      { path: 'reports', label: 'Reports', icon: 'chart' },
      { path: 'notifications', label: 'Notifications', icon: 'bell', badge: 'notifications' },
      { path: 'activity', label: 'Activity Log', icon: 'activity' },
    ],
  },
  {
    title: 'System',
    items: [{ path: 'settings', label: 'Settings', icon: 'settings' }],
  },
];

const PAGES = {
  dashboard: () => import('./pages/dashboard.js'),
  users: () => import('./pages/users.js'),
  landlords: () => import('./pages/users.js'),
  'boarding-houses': () => import('./pages/boarding-houses.js'),
  rooms: () => import('./pages/rooms.js'),
  bookings: () => import('./pages/bookings.js'),
  reports: () => import('./pages/reports.js'),
  notifications: () => import('./pages/notifications.js'),
  activity: () => import('./pages/activity.js'),
  settings: () => import('./pages/settings.js'),
};

const TITLES = Object.fromEntries(NAV.flatMap((s) => s.items).map((i) => [i.path, i.label]));
const view = document.getElementById('view');
const badges = { landlords: 0, listings: 0, bookings: 0, notifications: 0 };

let currentPath = null;
let currentCleanup = null;
let renderToken = 0;

/* ---------------------------------------------------------------------- *
 * Routing
 * ---------------------------------------------------------------------- */
function parseHash() {
  const hash = window.location.hash.replace(/^#\/?/, '');
  const [path, query = ''] = hash.split('?');
  return { path: PAGES[path] ? path : 'dashboard', params: new URLSearchParams(query) };
}

export function navigate(path, params = {}) {
  const query = new URLSearchParams(Object.entries(params).filter(([, v]) => v !== '' && v !== null && v !== undefined)).toString();
  window.location.hash = `#/${path}${query ? `?${query}` : ''}`;
}

/** Updates the URL query without re-rendering (pages keep their own state). */
function setParams(path, params) {
  const query = new URLSearchParams(Object.entries(params).filter(([, v]) => v !== '' && v !== null && v !== undefined)).toString();
  history.replaceState(null, '', `#/${path}${query ? `?${query}` : ''}`);
}

async function renderRoute() {
  const { path, params } = parseHash();
  const token = ++renderToken;

  if (typeof currentCleanup === 'function') currentCleanup();
  currentCleanup = null;
  destroyCharts();
  currentPath = path;
  highlightNav(path);
  closeSidebar();
  document.title = `${TITLES[path] || 'Dashboard'} | Admin · Boarding House Rental System`;
  setHtml(view, html`<div class="flex items-center justify-center py-24"><span class="h-8 w-8 animate-spin rounded-full border-[3px] border-primary/20 border-t-primary"></span></div>`);

  try {
    const page = await PAGES[path]();
    if (token !== renderToken) return;
    const ctx = {
      path,
      params,
      navigate,
      setParams: (p) => setParams(path, p),
      refreshBadges,
      isCurrent: () => token === renderToken,
    };
    currentCleanup = await page.render(view, ctx);
    view.focus({ preventScroll: true });
    window.scrollTo({ top: 0 });
  } catch (error) {
    if (token !== renderToken) return;
    console.error(error);
    setHtml(view, errorState(error.message || 'Unexpected error.'));
    $('[data-retry]', view)?.addEventListener('click', renderRoute);
  }
}

/* ---------------------------------------------------------------------- *
 * Sidebar
 * ---------------------------------------------------------------------- */
function renderNav() {
  setHtml($('#sidebarNav'), NAV.map((section) => html`
    <div>
      <p class="nav-section-title">${section.title}</p>
      <div class="space-y-1">${section.items.map((item) => html`
        <a href="#/${item.path}" class="nav-link" data-nav="${item.path}" title="${item.label}">
          <span class="nav-icon">${icon(item.icon)}</span>
          <span class="sidebar-label">${item.label}</span>
          ${item.badge ? html`<span class="nav-badge hidden" data-badge="${item.badge}"></span>` : ''}
        </a>`)}</div>
    </div>`));
  $$('[data-nav]').forEach((link) => link.addEventListener('click', () => {
    // Clicking the active page again reloads it.
    if (link.dataset.nav === currentPath && window.location.hash === `#/${currentPath}`) renderRoute();
  }));
  $$('[data-action="logout"] .nav-icon').forEach((el) => setHtml(el, icon('logout')));
}

function highlightNav(path) {
  $$('[data-nav]').forEach((link) => {
    const active = link.dataset.nav === path;
    link.classList.toggle('is-active', active);
    if (active) link.setAttribute('aria-current', 'page');
    else link.removeAttribute('aria-current');
  });
}

const sidebar = $('#sidebar');
const overlay = $('#sidebarOverlay');
const toggleBtn = $('#sidebarToggle');
const isDesktop = () => window.matchMedia('(min-width: 1024px)').matches;

function openSidebar() {
  sidebar.classList.add('is-open');
  overlay.classList.remove('hidden');
  toggleBtn.setAttribute('aria-expanded', 'true');
}

function closeSidebar() {
  if (isDesktop()) return;
  sidebar.classList.remove('is-open');
  overlay.classList.add('hidden');
  toggleBtn.setAttribute('aria-expanded', 'false');
}

function initSidebar() {
  try {
    if (localStorage.getItem('bh-admin-sidebar') === 'collapsed') document.body.classList.add('sidebar-collapsed');
  } catch { /* storage unavailable */ }

  toggleBtn.addEventListener('click', () => {
    if (isDesktop()) {
      const collapsed = document.body.classList.toggle('sidebar-collapsed');
      try { localStorage.setItem('bh-admin-sidebar', collapsed ? 'collapsed' : 'expanded'); } catch { /* ignore */ }
    } else if (sidebar.classList.contains('is-open')) {
      closeSidebar();
    } else {
      openSidebar();
    }
  });
  overlay.addEventListener('click', closeSidebar);
  $('#sidebarClose').addEventListener('click', closeSidebar);
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeSidebar(); });
}

/* ---------------------------------------------------------------------- *
 * Dropdowns (notifications, profile, search)
 * ---------------------------------------------------------------------- */
function initDropdown(button, panel, onOpen) {
  button.addEventListener('click', (event) => {
    event.stopPropagation();
    const opening = panel.classList.contains('hidden');
    closeDropdowns();
    if (opening) {
      panel.classList.remove('hidden');
      button.setAttribute('aria-expanded', 'true');
      onOpen?.();
    }
  });
  panel.addEventListener('click', (event) => event.stopPropagation());
}

function closeDropdowns() {
  $$('.dropdown-panel').forEach((p) => p.classList.add('hidden'));
  $$('[aria-expanded][aria-haspopup]').forEach((b) => b.setAttribute('aria-expanded', 'false'));
}

document.addEventListener('click', closeDropdowns);
document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeDropdowns(); });
window.addEventListener('hashchange', closeDropdowns);

/* Notifications bell */
const safeLink = (link) => (typeof link === 'string' && link.startsWith('#/') ? link : '#/notifications');

async function loadNotificationPanel() {
  const panel = $('#notifPanel');
  setHtml(panel, html`<div class="p-6 text-center text-sm text-slate-400">Loading…</div>`);
  try {
    const res = await api.notifications.list({ per_page: 6 });
    updateBadge('notifications', res.unread);
    setHtml(panel, html`
      <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
        <p class="font-semibold">Notifications</p>
        ${res.unread ? html`<button type="button" class="text-xs font-semibold text-primary hover:underline" data-read-all>Mark all read</button>` : ''}
      </div>
      <div class="max-h-96 overflow-y-auto">
        ${res.data.length ? res.data.map((n) => html`
          <a href="${safeLink(n.link)}" data-notif="${n.id}" class="flex gap-3 border-b border-slate-50 px-4 py-3 hover:bg-slate-50 ${n.is_read ? '' : 'bg-primary-50/60'}">
            <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full ${n.is_read ? 'bg-transparent' : 'bg-primary'}"></span>
            <span class="min-w-0">
              <span class="block text-sm font-semibold text-ink">${n.title}</span>
              <span class="mt-0.5 block text-xs text-slate-500">${n.message}</span>
              <span class="mt-1 block text-[11px] text-slate-400">${timeAgo(n.created_at)}</span>
            </span>
          </a>`) : html`<p class="px-4 py-10 text-center text-sm text-slate-400">You're all caught up.</p>`}
      </div>
      <a href="#/notifications" class="block rounded-b-2xl px-4 py-3 text-center text-sm font-semibold text-primary hover:bg-slate-50">View all notifications</a>`);

    $('[data-read-all]', panel)?.addEventListener('click', async () => {
      await api.notifications.markAllRead();
      updateBadge('notifications', 0);
      loadNotificationPanel();
    });
    $$('[data-notif]', panel).forEach((link) => link.addEventListener('click', () => {
      closeDropdowns();
      api.notifications.markRead(link.dataset.notif).then((r) => updateBadge('notifications', r.unread)).catch(() => {});
    }));
  } catch (error) {
    setHtml(panel, html`<p class="p-6 text-center text-sm text-rose-500">${error.message}</p>`);
  }
}

/* Global search */
function initSearch() {
  const input = $('#globalSearch');
  const results = $('#globalSearchResults');

  const run = debounce(async () => {
    const q = input.value.trim();
    if (q.length < 2) { results.classList.add('hidden'); return; }
    try {
      const data = await api.search(q);
      const groups = [
        ['Users', data.users, (u) => html`<a class="menu-item flex items-center justify-between gap-2" href="#/users?view=${u.id}"><span class="truncate"><strong>${u.name}</strong> <span class="text-slate-400">${u.email}</span></span>${badge(u.role, u.role_label)}</a>`],
        ['Boarding houses', data.boarding_houses, (h) => html`<a class="menu-item flex items-center justify-between gap-2" href="#/boarding-houses?view=${h.id}"><span class="truncate"><strong>${h.name}</strong> <span class="text-slate-400">${h.city}</span></span>${badge(h.status)}</a>`],
        ['Rooms', data.rooms, (r) => html`<a class="menu-item flex items-center justify-between gap-2" href="#/rooms?view=${r.id}"><span class="truncate"><strong>Room ${r.room_number}</strong> <span class="text-slate-400">${r.boarding_house_name}</span></span>${badge(r.status)}</a>`],
        ['Bookings', data.bookings, (b) => html`<a class="menu-item flex items-center justify-between gap-2" href="#/bookings?view=${b.id}"><span><strong>${b.code}</strong> <span class="text-slate-400">${b.tenant_name}</span></span>${badge(b.status)}</a>`],
      ].filter(([, items]) => items.length);

      setHtml(results, groups.length
        ? html`<div class="p-2">${groups.map(([title, items, row]) => html`<p class="px-3 pb-1 pt-2 text-[11px] font-semibold uppercase tracking-wider text-slate-400">${title}</p>${items.map(row)}`)}</div>`
        : html`<p class="px-4 py-6 text-center text-sm text-slate-400">No results for “${q}”.</p>`);
      results.classList.remove('hidden');
      $$('a', results).forEach((a) => a.addEventListener('click', () => { results.classList.add('hidden'); input.value = ''; }));
    } catch (error) {
      toast(error.message, 'error');
    }
  }, 250);

  input.addEventListener('input', run);
  input.addEventListener('focus', () => { if (input.value.trim().length >= 2) run(); });
  input.addEventListener('click', (e) => e.stopPropagation());
  results.addEventListener('click', (e) => e.stopPropagation());
  input.addEventListener('keydown', (e) => { if (e.key === 'Escape') { results.classList.add('hidden'); input.blur(); } });
  document.addEventListener('keydown', (e) => {
    if (e.key === '/' && !['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement?.tagName)) {
      e.preventDefault();
      input.focus();
    }
  });
}

/* ---------------------------------------------------------------------- *
 * Badges (pending approvals, pending bookings, unread notifications)
 * ---------------------------------------------------------------------- */
function updateBadge(key, value) {
  badges[key] = Number(value) || 0;
  $$(`[data-badge="${key}"]`).forEach((el) => {
    el.textContent = badges[key] > 99 ? '99+' : String(badges[key]);
    el.classList.toggle('hidden', !badges[key]);
  });
  if (key === 'notifications') {
    const bell = $('#notifBadge');
    bell.textContent = badges.notifications > 9 ? '9+' : String(badges.notifications);
    bell.classList.toggle('hidden', !badges.notifications);
  }
}

async function refreshBadges() {
  try {
    const [stats, notifications] = await Promise.all([api.stats(3), api.notifications.list({ per_page: 1 })]);
    updateBadge('landlords', stats.cards.pending_approvals.landlords);
    updateBadge('listings', stats.cards.pending_approvals.listings);
    updateBadge('bookings', stats.cards.pending_bookings);
    updateBadge('notifications', notifications.unread);
  } catch { /* badges are optional */ }
}

/* ---------------------------------------------------------------------- *
 * Logout
 * ---------------------------------------------------------------------- */
function initLogout() {
  $$('[data-action="logout"]').forEach((btn) => btn.addEventListener('click', async () => {
    closeDropdowns();
    const ok = await confirmDialog({
      title: 'Log out?',
      message: 'You will be signed out of the admin dashboard and returned to the login page.',
      confirmText: 'Log out',
      danger: true,
      iconName: 'logout',
    });
    if (ok) $('#logoutForm').submit();
  }));
}

/* ---------------------------------------------------------------------- *
 * Start
 * ---------------------------------------------------------------------- */
renderNav();
initSidebar();
initDropdown($('#notifButton'), $('#notifPanel'), loadNotificationPanel);
initDropdown($('#profileButton'), $('#profilePanel'));
initSearch();
initLogout();
window.addEventListener('hashchange', renderRoute);
window.addEventListener('bh:data-changed', refreshBadges);
renderRoute();
refreshBadges();
setInterval(() => { if (document.visibilityState === 'visible') refreshBadges(); }, 60000);

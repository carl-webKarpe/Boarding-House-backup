/* ==========================================================================
   Landlord dashboard bootstrap: layout, navigation, routing, notifications
   and logout. Reuses the admin dashboard's UI toolkit (ui.js) and styles so
   both dashboards look and behave the same.

   Routes are URL hashes, e.g. #/rooms?house_id=3, so every view can be
   bookmarked and the browser Back button works.
   ========================================================================== */

import { api } from './api.js';
import { html, setHtml, icon, $, $$, confirmDialog, timeAgo, errorState, assetUrl } from './ui.js';

const NAV = [
  {
    title: 'Overview',
    items: [{ path: 'dashboard', label: 'Dashboard', icon: 'dashboard' }],
  },
  {
    title: 'Listings',
    items: [
      { path: 'houses', label: 'My Boarding Houses', icon: 'house' },
      { path: 'add-house', label: 'Add Boarding House', icon: 'plus' },
      { path: 'rooms', label: 'Room Management', icon: 'bed' },
    ],
  },
  {
    title: 'Students',
    items: [
      { path: 'reservations', label: 'Reservations', icon: 'calendar', badge: 'reservations' },
      { path: 'tenants', label: 'My Tenants', icon: 'users' },
      { path: 'payments', label: 'Payments', icon: 'wallet', badge: 'payments' },
      { path: 'chat', label: 'Chat', icon: 'chat', badge: 'chat' },
      { path: 'messages', label: 'Inquiries', icon: 'message', badge: 'messages' },
    ],
  },
  {
    title: 'Account',
    items: [{ path: 'profile', label: 'Profile', icon: 'user' }],
  },
];

const PAGES = {
  dashboard: () => import('./pages/dashboard.js'),
  houses: () => import('./pages/houses.js'),
  'add-house': () => import('./pages/house-form.js'),
  'edit-house': () => import('./pages/house-form.js'),
  rooms: () => import('./pages/rooms.js'),
  reservations: () => import('./pages/reservations.js'),
  tenants: () => import('./pages/tenants.js'),
  payments: () => import('./pages/payments.js'),
  chat: () => import('./pages/chat.js'),
  messages: () => import('./pages/messages.js'),
  profile: () => import('./pages/profile.js'),
};

const TITLES = { ...Object.fromEntries(NAV.flatMap((s) => s.items).map((i) => [i.path, i.label])), 'edit-house': 'Edit Boarding House' };
const NAV_ALIAS = { 'edit-house': 'houses' };
const NOTIFICATION_LINKS = { booking_created: '#/reservations', inquiry: '#/messages', listing_status: '#/houses', account: '#/profile', chat: '#/chat' };

const view = document.getElementById('view');
const badges = { reservations: 0, messages: 0, payments: 0, chat: 0, notifications: 0 };

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
  const hash = `#/${path}${query ? `?${query}` : ''}`;
  if (window.location.hash === hash) renderRoute();
  else window.location.hash = hash;
}

function setParams(path, params) {
  const query = new URLSearchParams(Object.entries(params).filter(([, v]) => v !== '' && v !== null && v !== undefined)).toString();
  history.replaceState(null, '', `#/${path}${query ? `?${query}` : ''}`);
}

async function renderRoute() {
  const { path, params } = parseHash();
  const token = ++renderToken;

  if (typeof currentCleanup === 'function') currentCleanup();
  currentCleanup = null;
  // Dialogs belong to the page they were opened on.
  $$('#modalRoot .modal-backdrop').forEach((el) => el.remove());
  document.body.style.overflow = '';
  currentPath = path;
  highlightNav(NAV_ALIAS[path] || path);
  closeSidebar();
  document.title = `${TITLES[path] || 'Dashboard'} | Landlord · Boarding House Rental System`;
  $('#pageTitle').textContent = TITLES[path] || 'Dashboard';
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
    if (localStorage.getItem('bh-landlord-sidebar') === 'collapsed') document.body.classList.add('sidebar-collapsed');
  } catch { /* storage unavailable */ }

  toggleBtn.addEventListener('click', () => {
    if (isDesktop()) {
      const collapsed = document.body.classList.toggle('sidebar-collapsed');
      try { localStorage.setItem('bh-landlord-sidebar', collapsed ? 'collapsed' : 'expanded'); } catch { /* ignore */ }
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
 * Dropdowns (notifications, profile)
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

async function loadNotificationPanel() {
  const panel = $('#notifPanel');
  setHtml(panel, html`<div class="p-6 text-center text-sm text-slate-400">Loading…</div>`);
  try {
    const res = await api.notifications.list({ per_page: 8 });
    updateBadge('notifications', res.unread);
    setHtml(panel, html`
      <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
        <p class="font-semibold">Notifications</p>
        ${res.unread ? html`<button type="button" class="text-xs font-semibold text-primary hover:underline" data-read-all>Mark all read</button>` : ''}
      </div>
      <div class="max-h-96 overflow-y-auto rounded-b-2xl">
        ${res.data.length ? res.data.map((n) => html`
          <a href="${NOTIFICATION_LINKS[n.type] || '#/dashboard'}" data-notif="${n.id}" class="flex gap-3 border-b border-slate-50 px-4 py-3 hover:bg-slate-50 ${n.is_read ? '' : 'bg-primary-50/60'}">
            <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full ${n.is_read ? 'bg-transparent' : 'bg-primary'}"></span>
            <span class="min-w-0">
              <span class="block text-sm font-semibold text-ink">${n.title}</span>
              <span class="mt-0.5 block text-xs text-slate-500">${n.message}</span>
              <span class="mt-1 block text-[11px] text-slate-400">${timeAgo(n.created_at)}</span>
            </span>
          </a>`) : html`<p class="px-4 py-10 text-center text-sm text-slate-400">You're all caught up.</p>`}
      </div>`);

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

/* ---------------------------------------------------------------------- *
 * Badges (pending reservations, unread messages, notifications)
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
    const [stats, notifications] = await Promise.all([api.stats(), api.notifications.list({ per_page: 1 })]);
    updateBadge('reservations', stats.cards.pending_reservations);
    updateBadge('messages', stats.cards.unread_messages);
    updateBadge('payments', stats.cards.unpaid_this_month);
    updateBadge('chat', stats.cards.unread_chat);
    updateBadge('notifications', notifications.unread);
  } catch { /* badges are optional */ }
}

/** Shows the landlord's profile photo in the header once it is known. */
function showAvatar(url) {
  const holder = $('[data-landlord-avatar]');
  if (!holder || !url) return;
  const img = document.createElement('img');
  img.src = url;
  img.alt = '';
  img.className = 'h-full w-full object-cover';
  holder.replaceChildren(img);
}

/* ---------------------------------------------------------------------- *
 * Logout
 * ---------------------------------------------------------------------- */
function initLogout() {
  $$('[data-action="logout"]').forEach((btn) => btn.addEventListener('click', async () => {
    closeDropdowns();
    const ok = await confirmDialog({
      title: 'Log out?',
      message: 'You will be signed out of the landlord dashboard and returned to the login page.',
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
initLogout();
window.addEventListener('hashchange', renderRoute);
window.addEventListener('bh:data-changed', refreshBadges);
renderRoute();
refreshBadges();
api.profile.get().then((p) => p.avatar_path && showAvatar(assetUrl(p.avatar_path))).catch(() => {});
window.addEventListener('bh:avatar', (event) => showAvatar(event.detail));
setInterval(() => { if (document.visibilityState === 'visible') refreshBadges(); }, 60000);

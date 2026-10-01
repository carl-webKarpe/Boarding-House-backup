/* Dashboard overview: statistic cards, charts, approvals queue, recent activity. */

import { api } from '../api.js';
import { html, setHtml, icon, $, num, timeAgo, fmtDate, errorState } from '../ui.js';
import { lineChart, horizontalBarChart, chartTable, BRAND } from '../charts.js';

export const ACTIVITY_STYLES = {
  register: ['users', 'bg-sky-50 text-sky-600'],
  login: ['key', 'bg-slate-100 text-slate-500'],
  listing_create: ['house', 'bg-amber-50 text-amber-600'],
  listing_approve: ['check', 'bg-emerald-50 text-emerald-600'],
  listing_reject: ['x', 'bg-rose-50 text-rose-600'],
  listing_update: ['house', 'bg-slate-100 text-slate-600'],
  listing_delete: ['trash', 'bg-rose-50 text-rose-600'],
  room: ['bed', 'bg-violet-50 text-violet-600'],
  booking: ['calendar', 'bg-teal-50 text-teal-600'],
  user_disable: ['ban', 'bg-rose-50 text-rose-600'],
  user_delete: ['trash', 'bg-rose-50 text-rose-600'],
  user: ['users', 'bg-slate-100 text-slate-600'],
  settings: ['settings', 'bg-slate-100 text-slate-600'],
  password: ['key', 'bg-slate-100 text-slate-600'],
  document: ['file', 'bg-slate-100 text-slate-600'],
};

export function activityStyle(action) {
  if (ACTIVITY_STYLES[action]) return ACTIVITY_STYLES[action];
  const prefix = Object.keys(ACTIVITY_STYLES).find((key) => action.startsWith(key));
  return ACTIVITY_STYLES[prefix] || ['activity', 'bg-slate-100 text-slate-600'];
}

export function activityTimeline(items) {
  if (!items.length) return html`<p class="py-8 text-center text-sm text-slate-400">No activity recorded yet.</p>`;
  return html`<ol class="timeline">${items.map((item) => {
    const [iconName, color] = activityStyle(item.action);
    return html`
      <li class="timeline-item">
        <span class="timeline-dot ${color}">${icon(iconName)}</span>
        <div class="min-w-0 pt-0.5">
          <p class="text-sm font-medium text-ink">${item.description}</p>
          <p class="mt-0.5 text-xs text-slate-400"><time datetime="${item.created_at}" title="${item.created_at}">${timeAgo(item.created_at)}</time> · ${item.actor}</p>
        </div>
      </li>`;
  })}</ol>`;
}

function statCard({ label, value, iconName, tone, sub, href }) {
  return html`
    <a href="${href}" class="card stat-card fade-up block p-5">
      <div class="flex items-start justify-between gap-3">
        <p class="text-sm font-medium text-slate-500">${label}</p>
        <span class="grid h-10 w-10 place-items-center rounded-xl ${tone}">${icon(iconName, 'h-5 w-5')}</span>
      </div>
      <p class="mt-3 font-display text-3xl font-semibold text-ink" data-count="${value}">${num(value)}</p>
      <p class="mt-1 flex items-center gap-1 text-xs text-slate-500">${sub}</p>
    </a>`;
}

const growth = (n) => (n > 0
  ? html`<span class="inline-flex items-center gap-1 font-semibold text-emerald-700">${icon('trendUp', 'h-3.5 w-3.5')} +${num(n)}</span> this month`
  : html`<span>No new this month</span>`);

function animateCounts(root) {
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
  root.querySelectorAll('[data-count]').forEach((el) => {
    const target = Number(el.dataset.count);
    const start = performance.now();
    const step = (now) => {
      const progress = Math.min(1, (now - start) / 700);
      el.textContent = num(Math.round(target * (1 - (1 - progress) ** 3)));
      if (progress < 1) requestAnimationFrame(step);
    };
    requestAnimationFrame(step);
  });
}

export async function render(container, ctx) {
  const load = async () => {
    let data;
    try {
      data = await api.stats(6);
    } catch (error) {
      setHtml(container, errorState(error.message));
      $('[data-retry]', container).addEventListener('click', load);
      return;
    }
    if (!ctx.isCurrent()) return;

    const c = data.cards;
    const charts = data.charts;
    const today = new Date().toLocaleDateString('en-PH', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });

    setHtml(container, html`
      <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <p class="text-sm text-slate-500">${today}</p>
          <h1 class="mt-1 font-display text-2xl font-semibold text-ink">Dashboard Overview</h1>
        </div>
        <div class="flex flex-wrap gap-2">
          <a href="#/reports" class="btn btn-secondary">${icon('chart')} Reports</a>
          <a href="#/boarding-houses?status=pending" class="btn btn-primary">${icon('checkCircle')} Review approvals${c.pending_approvals.listings ? html` <span class="rounded-full bg-white/20 px-2 text-xs">${c.pending_approvals.listings}</span>` : ''}</a>
        </div>
      </div>

      <section aria-label="Statistics" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-6">
        ${statCard({ label: 'Total Tenants', value: c.tenants.total, iconName: 'tenant', tone: 'bg-sky-50 text-sky-600', sub: growth(c.tenants.this_month), href: '#/users?role=tenant' })}
        ${statCard({ label: 'Total Landlords', value: c.landlords.total, iconName: 'landlord', tone: 'bg-violet-50 text-violet-600', sub: growth(c.landlords.this_month), href: '#/landlords' })}
        ${statCard({ label: 'Boarding Houses', value: c.boarding_houses.total, iconName: 'house', tone: 'bg-emerald-50 text-emerald-600', sub: growth(c.boarding_houses.this_month), href: '#/boarding-houses' })}
        ${statCard({ label: 'Available Rooms', value: c.available_rooms, iconName: 'door', tone: 'bg-teal-50 text-teal-600', sub: html`${num(c.rooms.beds - c.rooms.occupants)} open beds`, href: '#/rooms?status=available' })}
        ${statCard({ label: 'Occupied Rooms', value: c.occupied_rooms, iconName: 'bed', tone: 'bg-indigo-50 text-indigo-600', sub: html`${c.occupancy_rate}% of beds occupied`, href: '#/rooms?status=occupied' })}
        ${statCard({ label: 'Pending Approvals', value: c.pending_approvals.total, iconName: 'clock', tone: 'bg-amber-50 text-amber-600', sub: html`${num(c.pending_approvals.listings)} listings · ${num(c.pending_approvals.landlords)} landlords`, href: '#/boarding-houses?status=pending' })}
      </section>

      <section class="mt-6 grid gap-6 xl:grid-cols-3">
        <div class="card fade-up p-5 xl:col-span-2">
          <div class="flex items-start justify-between gap-3">
            <div>
              <h2 class="font-semibold text-ink">User registrations</h2>
              <p class="text-xs text-slate-500">New tenants and landlords per month (last 6 months)</p>
            </div>
          </div>
          <div class="chart-box mt-4 h-72"><canvas id="chartRegistrations" role="img" aria-label="Line chart of new tenant and landlord registrations per month"></canvas></div>
          ${chartTable(['Month', 'Tenants', 'Landlords'], charts.labels.map((label, i) => [label, charts.registrations.tenants[i], charts.registrations.landlords[i]]))}
        </div>

        <div class="card fade-up p-5">
          <h2 class="font-semibold text-ink">Booking statistics</h2>
          <p class="text-xs text-slate-500">All booking records by status</p>
          <div class="chart-box mt-4 h-56"><canvas id="chartBookings" role="img" aria-label="Bar chart of bookings by status"></canvas></div>
          ${chartTable(['Status', 'Bookings'], Object.entries(charts.bookings).map(([k, v]) => [k[0].toUpperCase() + k.slice(1), v]))}
          <a href="#/bookings?status=pending" class="mt-3 inline-flex items-center gap-1 text-sm font-semibold text-primary hover:underline">${num(c.pending_bookings)} bookings waiting ${icon('chevronRight', 'h-4 w-4')}</a>
        </div>
      </section>

      <section class="mt-6 grid gap-6 lg:grid-cols-2 xl:grid-cols-3">
        <div class="card fade-up p-5">
          <h2 class="font-semibold text-ink">Room availability</h2>
          <p class="text-xs text-slate-500">${num(c.rooms.total)} rooms · ${num(c.rooms.beds)} beds</p>
          <div class="chart-box mt-4 h-44"><canvas id="chartRooms" role="img" aria-label="Bar chart of rooms by availability"></canvas></div>
          ${chartTable(['Status', 'Rooms'], Object.entries(charts.rooms).map(([k, v]) => [k[0].toUpperCase() + k.slice(1), v]))}
        </div>

        <div class="card fade-up p-5">
          <h2 class="font-semibold text-ink">Boarding house statistics</h2>
          <p class="text-xs text-slate-500">Listings by approval status</p>
          <div class="chart-box mt-4 h-44"><canvas id="chartHouses" role="img" aria-label="Bar chart of boarding houses by status"></canvas></div>
          ${chartTable(['Status', 'Boarding houses'], Object.entries(charts.boarding_houses).map(([k, v]) => [k[0].toUpperCase() + k.slice(1), v]))}
        </div>

        <div class="card fade-up p-5 lg:col-span-2 xl:col-span-1">
          <div class="flex items-center justify-between">
            <h2 class="font-semibold text-ink">Waiting for approval</h2>
            <a href="#/boarding-houses?status=pending" class="text-xs font-semibold text-primary hover:underline">View all</a>
          </div>
          ${data.pending_listings.length ? html`
            <ul class="mt-3 divide-y divide-slate-100">${data.pending_listings.map((h) => html`
              <li>
                <a href="#/boarding-houses?view=${h.id}" class="flex items-center gap-3 rounded-xl px-2 py-3 hover:bg-slate-50">
                  <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-amber-50 text-amber-600">${icon('house', 'h-5 w-5')}</span>
                  <span class="min-w-0 flex-1">
                    <span class="block truncate text-sm font-semibold text-ink">${h.name}</span>
                    <span class="block truncate text-xs text-slate-500">${h.owner_name} · ${h.city} · ${fmtDate(h.created_at)}</span>
                  </span>
                  ${icon('chevronRight', 'h-4 w-4 text-slate-300')}
                </a>
              </li>`)}</ul>`
            : html`<p class="py-10 text-center text-sm text-slate-400">${icon('checkCircle', 'mx-auto mb-2 h-8 w-8 text-emerald-400')} No listings are waiting for approval.</p>`}
        </div>
      </section>

      <section class="card fade-up mt-6 p-5">
        <div class="mb-4 flex items-center justify-between">
          <div>
            <h2 class="font-semibold text-ink">Recent activities</h2>
            <p class="text-xs text-slate-500">Latest events across the system</p>
          </div>
          <a href="#/activity" class="btn btn-secondary btn-sm">View all</a>
        </div>
        ${activityTimeline(data.recent_activities)}
      </section>`);

    animateCounts(container);

    lineChart($('#chartRegistrations', container), charts.labels, [
      { label: 'Tenants', data: charts.registrations.tenants },
      { label: 'Landlords', data: charts.registrations.landlords },
    ]);
    horizontalBarChart($('#chartBookings', container), ['Pending', 'Approved', 'Cancelled', 'Completed'],
      ['pending', 'approved', 'cancelled', 'completed'].map((k) => charts.bookings[k]), { color: BRAND });
    horizontalBarChart($('#chartRooms', container), ['Available', 'Occupied', 'Maintenance'],
      ['available', 'occupied', 'maintenance'].map((k) => charts.rooms[k]), { color: BRAND });
    horizontalBarChart($('#chartHouses', container), ['Approved', 'Pending approval', 'Rejected', 'Inactive'],
      ['approved', 'pending', 'rejected', 'inactive'].map((k) => charts.boarding_houses[k]), { color: BRAND });
  };

  await load();
}

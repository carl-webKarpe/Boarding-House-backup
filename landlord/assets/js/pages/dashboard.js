/* Landlord overview: statistic cards, recent listings, recent reservations. */

import { api } from '../api.js';
import { html, setHtml, icon, num, fmtDate, timeAgo, badge, money, pageHeader, emptyState, listingBadge, thumb } from '../ui.js';

function statCard({ label, value, iconName, tone, sub, href }) {
  return html`
    <a href="${href}" class="card stat-card fade-up block p-5">
      <div class="flex items-start justify-between gap-3">
        <p class="text-sm font-medium text-slate-500">${label}</p>
        <span class="grid h-10 w-10 place-items-center rounded-xl ${tone}">${icon(iconName, 'h-5 w-5')}</span>
      </div>
      <p class="mt-3 font-display text-3xl font-semibold text-ink">${num(value)}</p>
      <p class="mt-1 text-xs text-slate-500">${sub}</p>
    </a>`;
}

function verificationNotice() {
  const status = document.body.dataset.verification;
  if (status === 'verified') return '';
  const rejected = status === 'rejected';
  return html`
    <div class="mb-6 flex gap-3 rounded-2xl border ${rejected ? 'border-rose-200 bg-rose-50 text-rose-800' : 'border-amber-200 bg-amber-50 text-amber-800'} p-4 text-sm">
      ${icon(rejected ? 'alert' : 'clock', 'mt-0.5 h-5 w-5 shrink-0')}
      <p>${rejected
        ? 'Your landlord account was not verified. Update your profile and contact the administrator.'
        : 'Your landlord account is waiting for verification by the administrator. You can already prepare your listings; they are published after approval.'}</p>
    </div>`;
}

export async function render(view) {
  const data = await api.stats();
  const c = data.cards;

  setHtml(view, html`
    ${pageHeader({
      title: 'Dashboard',
      description: 'An overview of your boarding houses, rooms and reservations.',
      actions: html`<a href="#/add-house" class="btn btn-primary">${icon('plus')} Add Boarding House</a>`,
    })}
    ${verificationNotice()}

    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="Summary">
      ${statCard({ label: 'Total Boarding Houses', value: c.boarding_houses, iconName: 'house', tone: 'bg-emerald-50 text-emerald-600', href: '#/houses',
        sub: c.pending_approval ? `${num(c.published)} published · ${num(c.pending_approval)} waiting for approval` : `${num(c.published)} published` })}
      ${statCard({ label: 'Total Available Rooms', value: c.available_rooms, iconName: 'bed', tone: 'bg-sky-50 text-sky-600', href: '#/rooms',
        sub: `${num(c.open_slots)} open slot${c.open_slots === 1 ? '' : 's'} in ${num(c.rooms)} room${c.rooms === 1 ? '' : 's'}` })}
      ${statCard({ label: 'Total Reserved Rooms', value: c.reserved_rooms, iconName: 'door', tone: 'bg-violet-50 text-violet-600', href: '#/reservations?status=approved',
        sub: 'Rooms with a pending or approved reservation' })}
      ${statCard({ label: 'Pending Reservations', value: c.pending_reservations, iconName: 'calendar', tone: 'bg-amber-50 text-amber-600', href: '#/reservations?status=pending',
        sub: c.unread_messages ? `${num(c.unread_messages)} unread message${c.unread_messages === 1 ? '' : 's'}` : 'Waiting for your answer' })}
    </section>

    <section class="mt-6 grid gap-6 xl:grid-cols-2">
      <div class="card overflow-hidden">
        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
          <h2 class="font-display text-base font-semibold">Recent Listings</h2>
          <a href="#/houses" class="text-sm font-semibold text-primary hover:underline">View all</a>
        </div>
        ${data.recent_listings.length ? html`<ul class="divide-y divide-slate-100">${data.recent_listings.map((h) => html`
          <li>
            <a href="#/houses?view=${h.id}" class="flex items-center gap-3 px-5 py-3 hover:bg-slate-50">
              ${thumb(h.cover_image, h.name)}
              <span class="min-w-0 flex-1">
                <span class="block truncate font-semibold text-ink">${h.name}</span>
                <span class="block truncate text-xs text-slate-500">${[h.barangay, h.city].filter(Boolean).join(', ')} · ${num(h.room_count)} room${h.room_count === 1 ? '' : 's'}${h.min_price !== null ? ` · from ${money(h.min_price)}` : ''}</span>
                <span class="mt-0.5 block text-[11px] text-slate-400">Added ${fmtDate(h.created_at)}</span>
              </span>
              ${listingBadge(h.display_status)}
            </a>
          </li>`)}</ul>`
        : emptyState({ title: 'No boarding houses yet', message: 'Add your first boarding house with photos, rooms and amenities.', iconName: 'house',
          action: html`<a href="#/add-house" class="btn btn-primary">${icon('plus')} Add Boarding House</a>` })}
      </div>

      <div class="card overflow-hidden">
        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
          <h2 class="font-display text-base font-semibold">Recent Reservations</h2>
          <a href="#/reservations" class="text-sm font-semibold text-primary hover:underline">View all</a>
        </div>
        ${data.recent_reservations.length ? html`<ul class="divide-y divide-slate-100">${data.recent_reservations.map((r) => html`
          <li>
            <a href="#/reservations?view=${r.id}" class="flex items-center gap-3 px-5 py-3 hover:bg-slate-50">
              <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-teal-50 text-teal-600">${icon('calendar', 'h-5 w-5')}</span>
              <span class="min-w-0 flex-1">
                <span class="block truncate font-semibold text-ink">${r.tenant_name}</span>
                <span class="block truncate text-xs text-slate-500">${r.house_name} · ${r.room_number} · ${num(r.occupants_count)} person${r.occupants_count === 1 ? '' : 's'}</span>
                <span class="mt-0.5 block text-[11px] text-slate-400">${r.code} · ${timeAgo(r.booking_date)}${r.move_in_date ? ` · move-in ${fmtDate(r.move_in_date)}` : ''}</span>
              </span>
              ${badge(r.status)}
            </a>
          </li>`)}</ul>`
        : emptyState({ title: 'No reservations yet', message: 'Reservations from students appear here once your listings are published.', iconName: 'calendar' })}
      </div>
    </section>`);
}

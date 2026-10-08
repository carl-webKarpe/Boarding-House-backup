/* Reservations for the landlord's rooms: approve, reject, complete, cancel. */

import { api } from '../api.js';
import { html, $, icon, badge, money, num, fmtDate, fmtDateTime, toast, openModal, details, avatar, withLoading } from '../ui.js';
import { createListPage, actionButton } from '../../../../admin/assets/js/pages/_list.js';

const ACTIONS = {
  approved: { label: 'Approve', title: 'Approve reservation?', text: 'The student gets the reserved places in the room and the room availability updates automatically.', icon: 'check', tone: 'btn-primary' },
  rejected: { label: 'Reject', title: 'Reject reservation?', text: 'The student is told the reservation was not accepted. No room slot is used.', icon: 'ban', tone: 'btn-danger' },
  completed: { label: 'Mark completed', title: 'Mark as completed?', text: 'Use this when the student has moved out. Their places in the room are freed.', icon: 'checkCircle', tone: 'btn-secondary' },
  cancelled: { label: 'Cancel', title: 'Cancel reservation?', text: 'The reservation is cancelled and the student’s places in the room are freed.', icon: 'x', tone: 'btn-danger' },
};

/** Confirms a status change with an optional note for the student. */
function changeStatus(reservation, status, onDone) {
  const a = ACTIONS[status];
  const modal = openModal({
    title: a.title,
    subtitle: `${reservation.code} · ${reservation.tenant_name} · ${reservation.house_name} ${reservation.room_number}`,
    size: 'sm',
    body: html`
      <p class="text-sm text-slate-600">${a.text}</p>
      ${status === 'approved' && reservation.occupants_count > reservation.available_slots ? html`
        <p class="mt-3 rounded-xl bg-rose-50 p-3 text-sm text-rose-700">Only ${num(reservation.available_slots)} slot(s) are free in this room, but the student asked for ${num(reservation.occupants_count)}.</p>` : ''}
      <label class="form-label mt-4" for="statusNote">Note to the student (optional)</label>
      <textarea id="statusNote" rows="3" maxlength="500" class="form-control" placeholder="${status === 'approved' ? 'e.g. Please bring 1 month advance and deposit on move-in day.' : 'e.g. Sorry, the room was taken.'}"></textarea>`,
    footer: html`
      <button type="button" class="btn btn-secondary" data-close>Back</button>
      <button type="button" class="btn ${a.tone === 'btn-secondary' ? 'btn-primary' : a.tone}" data-confirm>${icon(a.icon)} ${a.label}</button>`,
  });
  $('[data-confirm]', modal.el).addEventListener('click', async (event) => {
    try {
      await withLoading(event.currentTarget, async () => {
        const res = await api.reservations.update(reservation.id, { status, note: $('#statusNote', modal.el).value.trim() });
        toast(res.message || `Reservation ${status}.`);
      });
      modal.close();
      onDone?.();
    } catch (error) {
      toast(error.message, 'error');
    }
  });
}

async function openDetails(id, list) {
  const r = await api.reservations.get(id);
  const modal = openModal({
    title: `Reservation ${r.code}`,
    subtitle: `Sent ${fmtDateTime(r.booking_date)}`,
    size: 'lg',
    body: html`
      <div class="mb-5 flex items-center gap-3">
        ${avatar(r.tenant_name, 'h-12 w-12')}
        <div class="min-w-0 flex-1"><p class="font-semibold text-ink">${r.tenant_name}</p><p class="text-sm text-slate-500">${r.email} · ${r.phone || '—'}</p></div>
        ${badge(r.status)}
      </div>
      ${details([
        ['Boarding house', r.house_name],
        ['Room', `${r.room_number} · ${r.room_type_label}`],
        ['Rent', money(r.price)],
        ['Preferred move-in date', fmtDate(r.move_in_date)],
        ['Number of occupants', num(r.occupants_count)],
        ['Free slots in the room now', `${num(r.available_slots)} of ${num(r.capacity)}`],
        ['Contact number', r.phone ? html`<a class="font-semibold text-primary hover:underline" href="tel:${r.phone}">${r.phone}</a>` : ''],
        ['Email', html`<a class="font-semibold text-primary hover:underline" href="mailto:${r.email}">${r.email}</a>`],
      ])}
      ${r.message ? html`<h3 class="mt-6 text-sm font-semibold">Message from the student</h3><p class="message-bubble mt-2 bg-slate-50 text-slate-700">${r.message}</p>` : ''}
      ${r.notes ? html`<h3 class="mt-5 text-sm font-semibold">Your note</h3><p class="message-bubble mt-2 bg-primary-50 text-slate-700">${r.notes}</p>` : ''}`,
    footer: r.next_statuses.length
      ? html`<button type="button" class="btn btn-secondary" data-close>Close</button>${r.next_statuses.map((s) => html`<button type="button" class="btn ${ACTIONS[s].tone}" data-next="${s}">${icon(ACTIONS[s].icon)} ${ACTIONS[s].label}</button>`)}`
      : html`<button type="button" class="btn btn-secondary" data-close>Close</button>`,
  });
  modal.el.querySelectorAll('[data-next]').forEach((btn) => btn.addEventListener('click', () => {
    modal.close();
    changeStatus(r, btn.dataset.next, () => list.changed());
  }));
}

export async function render(view, ctx) {
  createListPage(view, ctx, {
    title: 'Reservations',
    description: 'Reservation requests from students for your rooms. Approve or reject pending requests.',
    tabKey: 'status',
    tabs: (c) => [
      { value: '', label: 'All', count: c.all_reservations },
      { value: 'pending', label: 'Pending', count: c.pending },
      { value: 'approved', label: 'Approved', count: c.approved },
      { value: 'rejected', label: 'Rejected', count: c.rejected },
      { value: 'cancelled', label: 'Cancelled', count: c.cancelled },
      { value: 'completed', label: 'Completed', count: c.completed },
    ],
    searchPlaceholder: 'Search student, boarding house or room…',
    fetch: (query) => api.reservations.list(query),
    columns: () => [
      {
        key: 'tenant_name', label: 'Student', primary: true,
        render: (r) => html`<div class="flex items-center gap-3">${avatar(r.tenant_name)}<div class="min-w-0">
          <p class="truncate font-semibold text-ink">${r.tenant_name}</p><p class="truncate text-xs text-slate-500">${r.code} · ${r.phone || r.email}</p></div></div>`,
      },
      { key: 'room', label: 'Room', render: (r) => html`<p class="font-medium">${r.house_name}</p><p class="text-xs text-slate-500">${r.room_number} · ${r.room_type_label}</p>` },
      { key: 'move_in_date', label: 'Move-in', render: (r) => fmtDate(r.move_in_date) },
      { key: 'occupants_count', label: 'Persons', render: (r) => num(r.occupants_count) },
      { key: 'status', label: 'Status', render: (r) => badge(r.status) },
      { key: 'booking_date', label: 'Sent', hideOnMobile: true, render: (r) => fmtDate(r.booking_date) },
    ],
    actions: (r) => html`
      ${actionButton('view', r.id, 'eye', 'View details')}
      ${r.next_statuses.includes('approved') ? actionButton('approved', r.id, 'check', 'Approve', 'text-emerald-600 hover:bg-emerald-50') : ''}
      ${r.next_statuses.includes('rejected') ? actionButton('rejected', r.id, 'ban', 'Reject', 'text-rose-600 hover:bg-rose-50') : ''}
      ${r.next_statuses.includes('completed') ? actionButton('completed', r.id, 'checkCircle', 'Mark completed') : ''}
      ${r.next_statuses.includes('cancelled') ? actionButton('cancelled', r.id, 'x', 'Cancel', 'text-rose-600 hover:bg-rose-50') : ''}`,
    emptyState: (state) => html`
      <div class="flex flex-col items-center px-6 py-14 text-center">
        <span class="grid h-14 w-14 place-items-center rounded-2xl bg-slate-100 text-slate-400">${icon('calendar', 'h-6 w-6')}</span>
        <p class="mt-4 font-semibold text-ink">${state.status || state.q ? 'No reservations here' : 'No reservations yet'}</p>
        <p class="mt-1 max-w-sm text-sm text-slate-500">Students reserve rooms from the room listing page once your boarding house is approved and available.</p>
      </div>`,
    onAction: async (action, id, row, list) => {
      if (action === 'view') await openDetails(id, list);
      else if (ACTIONS[action]) changeStatus(row, action, () => list.changed());
    },
    onReady: (list) => {
      const viewId = Number(ctx.params.get('view'));
      if (viewId) openDetails(viewId, list).catch((e) => toast(e.message, 'error'));
    },
  });
}

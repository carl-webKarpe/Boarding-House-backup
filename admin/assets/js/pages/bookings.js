/* Booking / rental records: approve, cancel, complete. */

import { api } from '../api.js';
import { html, icon, badge, money, fmtDate, fmtDateTime, toast, confirmDialog, openModal, formModal, details, avatar, $, $$, downloadCsv } from '../ui.js';
import { createListPage, actionButton } from './_list.js';

const STATUS_OPTIONS = [
  { value: 'pending', label: 'Pending' },
  { value: 'approved', label: 'Approved' },
  { value: 'cancelled', label: 'Cancelled' },
  { value: 'completed', label: 'Completed' },
];

const TRANSITIONS = {
  approved: { title: 'Approve booking?', text: 'The tenant gets a slot in this room and the room availability updates automatically.', button: 'Approve booking', icon: 'check', danger: false },
  cancelled: { title: 'Cancel booking?', text: 'The booking will be cancelled. If it was approved, the tenant’s slot is released.', button: 'Cancel booking', icon: 'x', danger: true },
  completed: { title: 'Mark as completed?', text: 'Use this when the rental period has ended. The tenant’s slot is released.', button: 'Mark completed', icon: 'checkCircle', danger: false },
};

export async function render(view, ctx) {
  const changeStatus = async (booking, status, list, modal) => {
    const t = TRANSITIONS[status];
    const ok = await confirmDialog({
      title: t.title,
      message: `${booking.code} · ${booking.tenant_name} · ${booking.boarding_house_name} room ${booking.room_number}. ${t.text}`,
      confirmText: t.button,
      danger: t.danger,
      iconName: t.icon,
    });
    if (!ok) return;
    const res = await api.bookings.update(booking.id, { status });
    toast(`Booking ${booking.code} ${status}.`);
    modal?.close();
    list.changed();
    return res;
  };

  const openCreate = async (list) => {
    const [tenants, rooms] = await Promise.all([api.options('tenants'), api.options('rooms')]);
    formModal({
      title: 'New booking',
      subtitle: 'Record a booking on behalf of a tenant (for walk-ins or phone reservations).',
      size: 'lg',
      fields: [
        { name: 'tenant_id', label: 'Tenant', type: 'select', required: true, span: 2, placeholder: 'Select a tenant', options: tenants.map((t) => ({ value: t.id, label: t.label })) },
        { name: 'room_id', label: 'Room (available rooms only)', type: 'select', required: true, span: 2, placeholder: 'Select a room', options: rooms.map((r) => ({ value: r.id, label: r.label })) },
        { name: 'move_in_date', label: 'Move-in date', type: 'date' },
        { name: 'status', label: 'Status', type: 'select', options: STATUS_OPTIONS.slice(0, 2), required: true },
        { name: 'notes', label: 'Notes', type: 'textarea', maxlength: 500 },
      ],
      values: { status: 'pending' },
      submitText: 'Create booking',
      onSubmit: async (data) => {
        const res = await api.bookings.create(data);
        toast(res.message);
        list.changed();
      },
    });
  };

  const openEdit = (booking, list) => {
    formModal({
      title: `Edit ${booking.code}`,
      subtitle: `${booking.tenant_name} · ${booking.boarding_house_name} room ${booking.room_number}`,
      fields: [
        { name: 'status', label: 'Status', type: 'select', options: STATUS_OPTIONS, required: true },
        { name: 'move_in_date', label: 'Move-in date', type: 'date' },
        { name: 'notes', label: 'Notes', type: 'textarea', maxlength: 500 },
      ],
      values: booking,
      onSubmit: async (data) => {
        const res = await api.bookings.update(booking.id, data);
        toast(res.message);
        list.changed();
      },
    });
  };

  const remove = async (booking, list) => {
    const ok = await confirmDialog({
      title: 'Delete booking record?',
      message: `${booking.code} will be removed permanently. Prefer “Cancel” to keep a history of the request.`,
      confirmText: 'Delete',
      danger: true,
      iconName: 'trash',
    });
    if (!ok) return;
    const res = await api.bookings.remove(booking.id);
    toast(res.message);
    list.changed();
  };

  const actionsFor = (b) => [
    b.status === 'pending' ? ['approved', 'check', 'Approve', 'success'] : null,
    b.status === 'approved' ? ['completed', 'checkCircle', 'Mark completed', 'success'] : null,
    b.status === 'pending' || b.status === 'approved' ? ['cancelled', 'x', 'Cancel', 'danger'] : null,
  ].filter(Boolean);

  const showDetails = async (id, list) => {
    const b = await api.bookings.get(id);
    const modal = openModal({
      title: `Booking ${b.code}`,
      subtitle: `Requested ${fmtDateTime(b.booking_date)}`,
      size: 'md',
      body: html`
        <div class="mb-5">${badge(b.status)}</div>
        ${details([
          ['Tenant', html`<a href="#/users?view=${b.tenant_id}" class="font-medium text-primary hover:underline" data-close>${b.tenant_name}</a>`],
          ['Contact', [b.tenant_email, b.tenant_contact].filter(Boolean).join(' · ')],
          ['Boarding house', html`<a href="#/boarding-houses?view=${b.boarding_house_id}" class="font-medium text-primary hover:underline" data-close>${b.boarding_house_name}</a>`],
          ['Room', `Room ${b.room_number} · ${b.room_type}`],
          ['Monthly rent', money(b.price)],
          ['Room occupancy', `${b.occupants}/${b.capacity} (${b.room_status})`],
          ['Move-in date', b.move_in_date ? fmtDate(b.move_in_date) : ''],
          ['Last updated', fmtDateTime(b.updated_at || b.created_at)],
        ])}
        ${b.notes ? html`<h3 class="mb-1 mt-5 text-sm font-semibold">Notes</h3><p class="text-sm text-slate-600">${b.notes}</p>` : ''}`,
      footer: html`
        ${actionsFor(b).map(([status, iconName, label]) => html`<button type="button" class="btn ${status === 'cancelled' ? 'btn-secondary text-rose-600' : 'btn-primary'}" data-status="${status}">${icon(iconName)} ${label}</button>`)}
        <button type="button" class="btn btn-secondary" data-edit>${icon('edit')} Edit</button>
        <button type="button" class="btn btn-secondary" data-close>Close</button>`,
    });
    $$('[data-status]', modal.el).forEach((btn) => btn.addEventListener('click', () => changeStatus(b, btn.dataset.status, list, modal).catch((e) => toast(e.message, 'error'))));
    $('[data-edit]', modal.el).addEventListener('click', () => { modal.close(); openEdit(b, list); });
  };

  const list = createListPage(view, ctx, {
    title: 'Bookings',
    description: 'Rental and reservation records. Approving a booking fills a slot in the room automatically.',
    headerActions: html`
      <button type="button" class="btn btn-secondary" data-export>${icon('download')} Export CSV</button>
      <button type="button" class="btn btn-primary" data-add>${icon('plus')} New booking</button>`,
    tabKey: 'status',
    tabs: (c) => [
      { value: '', label: 'All', count: c.all_bookings },
      { value: 'pending', label: 'Pending', count: c.pending },
      { value: 'approved', label: 'Approved', count: c.approved },
      { value: 'completed', label: 'Completed', count: c.completed },
      { value: 'cancelled', label: 'Cancelled', count: c.cancelled },
    ],
    defaults: { sort: 'booking_date', order: 'desc' },
    searchPlaceholder: 'Search by booking ID, tenant, boarding house or room…',
    fetch: (query) => api.bookings.list(query),
    columns: () => [
      { key: 'code', label: 'Booking ID', sort: 'id', primary: false, render: (b) => html`<span class="font-mono text-xs font-semibold text-slate-600">${b.code}</span>` },
      {
        key: 'tenant', label: 'Tenant', sort: 'tenant', primary: true,
        render: (b) => html`<div class="flex min-w-0 items-center gap-3">${avatar(b.tenant_name)}<div class="min-w-0"><p class="truncate font-semibold text-ink">${b.tenant_name}</p><p class="truncate text-xs text-slate-500 md:hidden">${b.code}</p><p class="hidden truncate text-xs text-slate-500 md:block">${b.tenant_email}</p></div></div>`,
      },
      { key: 'house', label: 'Boarding house', sort: 'house', render: (b) => b.boarding_house_name },
      { key: 'room', label: 'Room', render: (b) => html`${b.room_number} <span class="text-xs capitalize text-slate-400">${b.room_type}</span>` },
      { key: 'booking_date', label: 'Date', sort: 'booking_date', render: (b) => html`<span title="${fmtDateTime(b.booking_date)}">${fmtDate(b.booking_date)}</span>` },
      { key: 'status', label: 'Status', sort: 'status', render: (b) => badge(b.status) },
    ],
    actions: (b) => html`
      ${actionButton('view', b.id, 'eye', 'View details')}
      ${actionsFor(b).map(([status, iconName, label, tone]) => actionButton(status, b.id, iconName, label, tone))}
      ${actionButton('edit', b.id, 'edit', 'Edit')}
      ${actionButton('delete', b.id, 'trash', 'Delete', 'danger')}`,
    onAction: (action, id, row, listRef) => {
      if (TRANSITIONS[action]) return changeStatus(row, action, listRef);
      return ({
        view: () => showDetails(id, listRef),
        edit: () => openEdit(row, listRef),
        delete: () => remove(row, listRef),
      })[action]?.();
    },
    emptyState: () => html`<div class="py-14 text-center text-sm text-slate-500">No bookings match this filter.</div>`,
    onReady: (listRef) => {
      const viewId = Number(ctx.params.get('view'));
      if (viewId) showDetails(viewId, listRef).catch((e) => toast(e.message, 'error'));
    },
  });

  view.querySelector('[data-add]').addEventListener('click', () => openCreate(list).catch((e) => toast(e.message, 'error')));
  view.querySelector('[data-export]').addEventListener('click', async () => {
    try {
      const query = { ...list.state, page: 1, per_page: 100 };
      const all = [];
      for (let page = 1; page <= 50; page++) {
        const res = await api.bookings.list({ ...query, page });
        all.push(...res.data);
        if (page >= res.meta.total_pages) break;
      }
      downloadCsv(`bookings-${new Date().toISOString().slice(0, 10)}.csv`,
        ['Booking ID', 'Tenant', 'Email', 'Boarding house', 'Room', 'Monthly rent', 'Booking date', 'Move-in date', 'Status'],
        all.map((b) => [b.code, b.tenant_name, b.tenant_email, b.boarding_house_name, b.room_number, b.price, b.booking_date, b.move_in_date || '', b.status]));
      toast(`Exported ${all.length} bookings.`);
    } catch (e) {
      toast(e.message, 'error');
    }
  });
}

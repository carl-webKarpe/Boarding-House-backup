/* Rooms: availability, pricing, capacity and amenities. Also hosts the
   amenity catalogue manager. */

import { api } from '../api.js';
import { html, icon, badge, money, num, fmtDateTime, toast, confirmDialog, openModal, formModal, details, setHtml, $, $$, titleCase, escapeHtml } from '../ui.js';
import { createListPage, actionButton } from './_list.js';

const TYPE_OPTIONS = [
  { value: 'solo', label: 'Solo room' },
  { value: 'shared', label: 'Shared room' },
  { value: 'dormitory', label: 'Dormitory' },
  { value: 'studio', label: 'Studio' },
];
const STATUS_OPTIONS = [
  { value: 'available', label: 'Available' },
  { value: 'occupied', label: 'Occupied / full' },
  { value: 'maintenance', label: 'Under maintenance' },
];

function occupancyBar(room) {
  const pct = room.capacity ? Math.round((room.occupants / room.capacity) * 100) : 0;
  return html`
    <div class="min-w-[7rem]">
      <div class="flex justify-between text-xs"><span class="font-medium text-ink">${room.occupants}/${room.capacity}</span><span class="text-slate-400">${room.available_slots} open</span></div>
      <div class="mt-1 h-1.5 rounded-full bg-slate-100" role="meter" aria-valuemin="0" aria-valuemax="${room.capacity}" aria-valuenow="${room.occupants}" aria-label="Occupancy">
        <div class="h-1.5 rounded-full ${pct >= 100 ? 'bg-violet-500' : 'bg-primary'}" style="width:${pct}%"></div>
      </div>
    </div>`;
}

async function roomFields() {
  const [houses, amenities] = await Promise.all([api.options('boarding_houses'), api.options('amenities')]);
  return [
    { name: 'boarding_house_id', label: 'Boarding house', type: 'select', required: true, placeholder: 'Select a boarding house', span: 2, options: houses.map((h) => ({ value: h.id, label: h.label })) },
    { name: 'room_number', label: 'Room number', required: true, maxlength: 20, placeholder: 'e.g. 101' },
    { name: 'room_type', label: 'Room type', type: 'select', required: true, options: TYPE_OPTIONS },
    { name: 'price', label: 'Monthly price (₱)', type: 'number', required: true, min: 1, step: 50 },
    { name: 'deposit', label: 'Deposit (₱)', type: 'number', min: 0, step: 50 },
    { name: 'capacity', label: 'Capacity (beds)', type: 'number', required: true, min: 1, max: 50 },
    { name: 'occupants', label: 'Current occupants', type: 'number', min: 0, max: 50, hint: 'Approving bookings updates this automatically.' },
    { name: 'size_sqm', label: 'Size (sqm)', type: 'number', min: 1, step: 0.5 },
    { name: 'status', label: 'Availability', type: 'select', options: STATUS_OPTIONS, hint: 'Available/occupied is set from occupancy unless under maintenance.' },
    { name: 'description', label: 'Description', type: 'textarea', maxlength: 500 },
    { name: 'amenity_ids', label: 'Amenities', type: 'checkboxes', options: amenities.map((a) => ({ value: a.id, label: a.label })) },
  ];
}

function manageAmenities(list) {
  const modal = openModal({
    title: 'Amenities',
    subtitle: 'The features landlords can tick for each room.',
    size: 'md',
    body: html`
      <form data-add-amenity class="flex gap-2">
        <label class="sr-only" for="newAmenity">New amenity</label>
        <input id="newAmenity" name="name" class="form-control" maxlength="60" placeholder="e.g. Study Lounge" required />
        <button type="submit" class="btn btn-primary">${icon('plus')} Add</button>
      </form>
      <ul class="mt-4 divide-y divide-slate-100 rounded-xl border border-slate-200" data-amenity-list><li class="p-4 text-sm text-slate-400">Loading…</li></ul>`,
    footer: html`<button type="button" class="btn btn-secondary" data-close>Done</button>`,
  });

  const listEl = $('[data-amenity-list]', modal.el);
  const load = async () => {
    const res = await api.amenities.list();
    setHtml(listEl, res.data.map((a) => html`
      <li class="flex items-center gap-3 px-4 py-2.5">
        <span class="flex-1 text-sm font-medium">${a.name}</span>
        <span class="text-xs text-slate-400">${num(a.room_count)} rooms</span>
        <button type="button" class="icon-btn" data-rename="${a.id}" data-name="${a.name}" aria-label="Rename ${a.name}">${icon('edit')}</button>
        <button type="button" class="icon-btn danger" data-delete="${a.id}" data-name="${a.name}" data-count="${a.room_count}" aria-label="Delete ${a.name}">${icon('trash')}</button>
      </li>`));
    $$('[data-rename]', listEl).forEach((btn) => btn.addEventListener('click', () => {
      formModal({
        title: 'Rename amenity',
        size: 'sm',
        fields: [{ name: 'name', label: 'Amenity name', required: true, maxlength: 60, span: 2 }],
        values: { name: btn.dataset.name },
        onSubmit: async (data) => {
          const r = await api.amenities.update(btn.dataset.rename, data);
          toast(r.message);
          load();
          list.reload();
        },
      });
    }));
    $$('[data-delete]', listEl).forEach((btn) => btn.addEventListener('click', async () => {
      const ok = await confirmDialog({
        title: 'Delete amenity?',
        message: `“${btn.dataset.name}” will be removed from ${btn.dataset.count} room(s).`,
        confirmText: 'Delete',
        danger: true,
      });
      if (!ok) return;
      try {
        const r = await api.amenities.remove(btn.dataset.delete);
        toast(r.message);
        load();
        list.reload();
      } catch (e) { toast(e.message, 'error'); }
    }));
  };

  $('[data-add-amenity]', modal.el).addEventListener('submit', async (event) => {
    event.preventDefault();
    const input = event.target.elements.name;
    try {
      const r = await api.amenities.create({ name: input.value.trim() });
      toast(r.message);
      input.value = '';
      load();
    } catch (e) { toast(e.message, 'error'); }
  });

  load().catch((e) => setHtml(listEl, html`<li class="p-4 text-sm text-rose-500">${e.message}</li>`));
}

export async function render(view, ctx) {
  const houseFilter = ctx.params.get('boarding_house_id');

  const openForm = async (room, list) => {
    const creating = !room;
    formModal({
      title: creating ? 'Add room' : `Edit room ${room.room_number}`,
      subtitle: creating ? '' : room.boarding_house_name,
      fields: await roomFields(),
      values: creating
        ? { room_type: 'shared', capacity: 2, occupants: 0, status: 'available', boarding_house_id: houseFilter || '', amenity_ids: [] }
        : room,
      size: 'lg',
      submitText: creating ? 'Create room' : 'Save changes',
      onSubmit: async (data) => {
        data.amenity_ids = (data.amenity_ids || []).map(Number);
        const res = creating ? await api.rooms.create(data) : await api.rooms.update(room.id, data);
        toast(res.message);
        list.changed();
      },
    });
  };

  const remove = async (room, list) => {
    const ok = await confirmDialog({
      title: 'Delete room?',
      message: `Room ${room.room_number} at ${room.boarding_house_name} will be deleted. Rooms with booking history cannot be deleted — set them to maintenance instead.`,
      confirmText: 'Delete room',
      danger: true,
      iconName: 'trash',
    });
    if (!ok) return;
    const res = await api.rooms.remove(room.id);
    toast(res.message);
    list.changed();
  };

  const toggleMaintenance = async (room, list) => {
    const toMaintenance = room.status !== 'maintenance';
    const res = await api.rooms.update(room.id, { status: toMaintenance ? 'maintenance' : 'available' });
    toast(toMaintenance ? 'Room marked as under maintenance.' : 'Room is back in service.');
    list.changed();
    return res;
  };

  const showDetails = async (id, list) => {
    const room = await api.rooms.get(id);
    const modal = openModal({
      title: `Room ${room.room_number}`,
      subtitle: room.boarding_house_name,
      size: 'md',
      body: html`
        <div class="mb-5 flex flex-wrap gap-2">${badge(room.status)} ${badge(room.boarding_house_status, `Listing ${room.boarding_house_status}`)}</div>
        ${details([
          ['Room type', titleCase(room.room_type)],
          ['Monthly price', money(room.price)],
          ['Deposit', money(room.deposit)],
          ['Size', room.size_sqm ? `${room.size_sqm} sqm` : ''],
          ['Occupancy', occupancyBar(room)],
          ['Location', room.city],
          ['Last updated', fmtDateTime(room.updated_at || room.created_at)],
        ])}
        ${room.description ? html`<p class="mt-5 text-sm text-slate-600">${room.description}</p>` : ''}
        <h3 class="mb-2 mt-5 text-sm font-semibold">Amenities</h3>
        ${room.amenities.length ? html`<div class="flex flex-wrap gap-2">${room.amenities.map((a) => html`<span class="badge badge-slate no-dot">${a}</span>`)}</div>` : html`<p class="text-sm text-slate-400">None listed.</p>`}`,
      footer: html`
        <a href="#/bookings?q=${encodeURIComponent(room.boarding_house_name)}" class="btn btn-secondary" data-close>${icon('calendar')} Bookings</a>
        <button type="button" class="btn btn-secondary" data-edit>${icon('edit')} Edit</button>
        <button type="button" class="btn btn-secondary" data-close>Close</button>`,
    });
    $('[data-edit]', modal.el).addEventListener('click', () => { modal.close(); openForm(room, list); });
  };

  const list = createListPage(view, ctx, {
    title: 'Rooms',
    description: houseFilter ? 'Rooms in the selected boarding house.' : 'Monitor availability, prices and amenities of every room.',
    headerActions: html`
      ${houseFilter ? html`<a href="#/rooms" class="btn btn-secondary">Show all rooms</a>` : ''}
      <button type="button" class="btn btn-secondary" data-amenities>${icon('tag')} Amenities</button>
      <button type="button" class="btn btn-primary" data-add>${icon('plus')} Add room</button>`,
    tabKey: 'status',
    tabs: (c) => [
      { value: '', label: 'All rooms', count: c.all_rooms },
      { value: 'available', label: 'Available', count: c.available },
      { value: 'occupied', label: 'Occupied', count: c.occupied },
      { value: 'maintenance', label: 'Maintenance', count: c.maintenance },
    ],
    filters: [{ name: 'room_type', label: 'Type', options: TYPE_OPTIONS }],
    fixed: houseFilter ? { boarding_house_id: houseFilter } : {},
    keepInUrl: houseFilter ? { boarding_house_id: houseFilter } : {},
    defaults: { sort: 'created_at', order: 'desc' },
    searchPlaceholder: 'Search by room number, boarding house or city…',
    fetch: (query) => api.rooms.list(query),
    columns: () => [
      {
        key: 'room_number', label: 'Room', sort: 'room_number', primary: true,
        render: (r) => html`<div class="flex items-center gap-3"><span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-slate-100 text-sm font-bold text-slate-600">${r.room_number}</span><div class="min-w-0"><p class="truncate font-semibold text-ink">${r.boarding_house_name}</p><p class="text-xs capitalize text-slate-500">${r.room_type} · ${r.city}</p></div></div>`,
      },
      { key: 'price', label: 'Price', sort: 'price', className: 'num', render: (r) => html`<span class="font-semibold">${money(r.price)}</span><span class="text-xs text-slate-400">/mo</span>` },
      { key: 'capacity', label: 'Occupancy', sort: 'capacity', render: occupancyBar },
      {
        key: 'amenities', label: 'Amenities', hideOnMobile: true,
        render: (r) => (r.amenities.length
          ? html`<span title="${escapeHtml(r.amenities.join(', '))}">${r.amenities.slice(0, 2).join(', ')}${r.amenities.length > 2 ? html` <span class="text-slate-400">+${r.amenities.length - 2}</span>` : ''}</span>`
          : html`<span class="text-slate-400">—</span>`),
      },
      { key: 'status', label: 'Status', sort: 'status', render: (r) => badge(r.status) },
    ],
    actions: (r) => html`
      ${actionButton('view', r.id, 'eye', 'View details')}
      ${actionButton('edit', r.id, 'edit', 'Edit')}
      ${actionButton('maintenance', r.id, 'wrench', r.status === 'maintenance' ? 'Back in service' : 'Mark under maintenance')}
      ${actionButton('delete', r.id, 'trash', 'Delete', 'danger')}`,
    onAction: (action, id, row, listRef) => ({
      view: () => showDetails(id, listRef),
      edit: () => openForm(row, listRef),
      maintenance: () => toggleMaintenance(row, listRef),
      delete: () => remove(row, listRef),
    })[action]?.(),
    emptyState: () => html`<div class="py-14 text-center text-sm text-slate-500">No rooms match this filter.</div>`,
    onReady: (listRef) => {
      const viewId = Number(ctx.params.get('view'));
      if (viewId) showDetails(viewId, listRef).catch((e) => toast(e.message, 'error'));
    },
  });

  view.querySelector('[data-add]').addEventListener('click', () => openForm(null, list).catch((e) => toast(e.message, 'error')));
  view.querySelector('[data-amenities]').addEventListener('click', () => manageAmenities(list));
}

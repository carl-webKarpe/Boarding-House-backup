/* My Boarding Houses: the landlord's own listings with view, edit, rooms, status and delete. */

import { api } from '../api.js';
import {
  html, setHtml, icon, $, $$, num, fmtDate, money, badge, toast, confirmDialog, openModal, details, withLoading,
  listingBadge, thumb, priceRange, assetUrl, AVAILABILITY_OPTIONS,
} from '../ui.js';
import { createListPage, actionButton } from '../../../../admin/assets/js/pages/_list.js';

const STATUS_FILTER = [
  { value: 'pending_approval', label: 'Pending Approval' },
  { value: 'available', label: 'Available' },
  { value: 'fully_occupied', label: 'Fully Occupied' },
  { value: 'temporarily_unavailable', label: 'Temporarily Unavailable' },
  { value: 'rejected', label: 'Rejected' },
];

/** Gallery: big photo with clickable thumbnails. */
function gallery(images) {
  if (!images.length) {
    return html`<div class="grid aspect-[16/9] place-items-center rounded-2xl bg-slate-100 text-slate-400">${icon('image', 'h-8 w-8')}</div>`;
  }
  return html`
    <div data-gallery>
      <img data-main src="${assetUrl(images[0].file_path)}" alt="" class="aspect-[16/9] w-full rounded-2xl object-cover transition-opacity duration-300" />
      ${images.length > 1 ? html`<div class="mt-2 flex gap-2 overflow-x-auto pb-1">${images.map((img, i) => html`
        <button type="button" data-thumb="${assetUrl(img.file_path)}" class="shrink-0 overflow-hidden rounded-xl ring-2 ${i === 0 ? 'ring-primary' : 'ring-transparent'}" aria-label="Show photo ${i + 1}">
          <img src="${assetUrl(img.file_path)}" alt="" class="h-14 w-20 object-cover" loading="lazy" />
        </button>`)}</div>` : ''}
    </div>`;
}

export function wireGallery(root) {
  const main = $('[data-main]', root);
  $$('[data-thumb]', root).forEach((btn) => btn.addEventListener('click', () => {
    main.style.opacity = '0.2';
    setTimeout(() => { main.src = btn.dataset.thumb; main.style.opacity = '1'; }, 150);
    $$('[data-thumb]', root).forEach((b) => b.classList.replace('ring-primary', 'ring-transparent'));
    btn.classList.replace('ring-transparent', 'ring-primary');
  }));
}

export async function openHouseDetails(id, ctx, list) {
  const house = await api.houses.get(id);
  const modal = openModal({
    title: house.name,
    subtitle: [house.barangay, house.city, house.province].filter(Boolean).join(', '),
    size: 'lg',
    body: html`
      ${house.status === 'rejected' && house.rejection_reason ? html`
        <div class="mb-4 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">
          <strong>Not approved:</strong> ${house.rejection_reason}<br />Edit the listing to fix it; it goes back to the administrator for approval.
        </div>` : ''}
      ${gallery(house.images)}
      <div class="mt-5 flex flex-wrap items-center gap-2">${listingBadge(house.display_status)}
        <span class="text-xs text-slate-400">Added ${fmtDate(house.created_at)}${house.updated_at ? ` · updated ${fmtDate(house.updated_at)}` : ''}</span></div>
      <div class="mt-5">${details([
        ['Complete address', house.address],
        ['Barangay', house.barangay],
        ['Municipality / Province', [house.city, house.province].filter(Boolean).join(', ')],
        ['Landmark / location', house.location_note],
        ['Nearby school', house.nearby_school],
        ['Distance from school', house.distance_note],
        ['Contact person', house.contact_name],
        ['Contact number', house.contact_number],
        ['Email', house.contact_email],
        ['Map', house.map_url ? html`<a href="${house.map_url}" target="_blank" rel="noopener" class="font-semibold text-primary hover:underline">Open map</a>` : ''],
      ])}</div>
      ${house.description ? html`<h3 class="mt-6 text-sm font-semibold">Description</h3><p class="mt-1 whitespace-pre-line text-sm text-slate-600">${house.description}</p>` : ''}
      ${house.house_rules ? html`<h3 class="mt-5 text-sm font-semibold">House rules</h3><p class="mt-1 whitespace-pre-line text-sm text-slate-600">${house.house_rules}</p>` : ''}
      <h3 class="mt-6 text-sm font-semibold">Rooms (${num(house.rooms.length)})</h3>
      ${house.rooms.length ? html`<ul class="mt-2 divide-y divide-slate-100 rounded-2xl border border-slate-100">${house.rooms.map((r) => html`
        <li class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 text-sm">
          <span><strong>${r.room_number}</strong> <span class="text-slate-500">· ${r.room_type_label} · ${money(r.price)}${r.room_type === 'solo' ? '' : '/person'}</span></span>
          <span class="flex items-center gap-2 text-xs text-slate-500">${num(r.available_slots)} of ${num(r.capacity)} slot${r.capacity === 1 ? '' : 's'} free ${badge(r.status === 'maintenance' ? 'unavailable' : r.status, r.status === 'maintenance' ? 'Unavailable' : '')}</span>
        </li>`)}</ul>` : html`<p class="mt-1 text-sm text-slate-400">No rooms yet.</p>`}`,
    footer: html`
      <a href="#/rooms?house_id=${house.id}" class="btn btn-secondary" data-close>${icon('bed')} Manage Rooms</a>
      <a href="#/edit-house?id=${house.id}" class="btn btn-primary" data-close>${icon('edit')} Edit</a>`,
  });
  wireGallery(modal.el);
  return modal;
}

export function openStatusModal(house, onDone) {
  const pending = house.status === 'pending';
  const modal = openModal({
    title: 'Update listing status',
    subtitle: house.name,
    body: html`
      ${pending ? html`<p class="mb-4 rounded-2xl bg-amber-50 p-3 text-sm text-amber-800">${icon('clock', 'mr-1 inline h-4 w-4')} This listing is <strong>Pending Approval</strong> by the administrator. The status you choose applies once it is approved.</p>` : ''}
      <form id="statusForm" class="space-y-2">${AVAILABILITY_OPTIONS.map((o) => html`
        <label class="choice-card">
          <input type="radio" name="availability_status" value="${o.value}" ${house.availability_status === o.value ? 'checked' : ''} />
          <span><span class="block font-semibold text-ink">${o.label}</span><span class="block text-xs text-slate-500">${o.hint}</span></span>
        </label>`)}</form>`,
    footer: html`
      <button type="button" class="btn btn-secondary" data-close>Cancel</button>
      <button type="submit" form="statusForm" class="btn btn-primary">Save status</button>`,
  });
  const form = $('#statusForm', modal.el);
  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    const value = new FormData(form).get('availability_status');
    try {
      await withLoading($('[form="statusForm"]', modal.el), async () => {
        const res = await api.houses.setStatus(house.id, value);
        toast(res.message);
      });
      modal.close();
      onDone?.();
    } catch (error) {
      toast(error.message, 'error');
    }
  });
}

export async function deleteHouse(house, onDone) {
  const ok = await confirmDialog({
    title: 'Delete this boarding house?',
    message: `"${house.name}" and all its rooms and photos will be removed permanently. Students will no longer see it. This cannot be undone.`,
    confirmText: 'Delete permanently',
    danger: true,
    iconName: 'trash',
  });
  if (!ok) return;
  const res = await api.houses.remove(house.id);
  toast(res.message || 'Boarding house deleted.');
  onDone?.();
}

export async function render(view, ctx) {
  createListPage(view, ctx, {
    title: 'My Boarding Houses',
    description: 'Only you can see and change these listings.',
    headerActions: html`<a href="#/add-house" class="btn btn-primary">${icon('plus')} Add Boarding House</a>`,
    searchPlaceholder: 'Search by name, barangay or address…',
    filters: [{ name: 'status', label: 'Status', options: STATUS_FILTER }],
    fetch: (query) => api.houses.list(query),
    columns: () => [
      {
        key: 'name', label: 'Boarding house', primary: true,
        render: (h) => html`<div class="flex items-center gap-3">${thumb(h.cover_image, h.name)}<div class="min-w-0">
          <p class="truncate font-semibold text-ink">${h.name}</p>
          <p class="truncate text-xs text-slate-500">${icon('pin', 'mr-0.5 inline h-3 w-3')}${h.barangay || h.address || '—'}${h.city ? `, ${h.city}` : ''}</p>
          <p class="text-[11px] text-slate-400">Added ${fmtDate(h.created_at)}</p></div></div>`,
      },
      { key: 'price', label: 'Monthly rent', className: 'whitespace-nowrap', render: (h) => priceRange(h.min_price, h.max_price) },
      { key: 'room_types', label: 'Room type', render: (h) => (h.room_types.length ? html`<span class="text-sm">${h.room_types.map((t) => t.replace(' Room', '')).join(' · ')}</span>` : html`<span class="text-slate-400">No rooms yet</span>`) },
      { key: 'available_rooms', label: 'Available', className: 'whitespace-nowrap', render: (h) => html`<span class="font-semibold">${num(h.available_rooms)}</span><span class="text-slate-400"> / ${num(h.room_count)}</span>` },
      { key: 'status', label: 'Status', render: (h) => html`${listingBadge(h.display_status)}${h.pending_reservations ? html`<span class="mt-1 block text-[11px] text-amber-700">${num(h.pending_reservations)} pending reservation${h.pending_reservations === 1 ? '' : 's'}</span>` : ''}` },
    ],
    actions: (h) => html`
      ${actionButton('view', h.id, 'eye', 'View')}
      ${actionButton('edit', h.id, 'edit', 'Edit')}
      ${actionButton('rooms', h.id, 'bed', 'Manage rooms')}
      ${actionButton('status', h.id, 'tag', 'Update status')}
      ${actionButton('delete', h.id, 'trash', 'Delete', 'text-rose-600 hover:bg-rose-50')}`,
    emptyState: (state) => html`
      <div class="flex flex-col items-center px-6 py-14 text-center">
        <span class="grid h-14 w-14 place-items-center rounded-2xl bg-primary-50 text-primary">${icon('house', 'h-6 w-6')}</span>
        <p class="mt-4 font-semibold text-ink">${state.q || state.status ? 'No boarding houses match your filters' : 'You have no boarding houses yet'}</p>
        <p class="mt-1 max-w-sm text-sm text-slate-500">${state.q || state.status ? 'Try another search or status.' : 'Add your first boarding house with photos, rooms, amenities and location.'}</p>
        ${state.q || state.status ? '' : html`<a href="#/add-house" class="btn btn-primary mt-4">${icon('plus')} Add Boarding House</a>`}
      </div>`,
    onAction: async (action, id, row, list) => {
      if (action === 'view') await openHouseDetails(id, ctx, list);
      if (action === 'edit') ctx.navigate('edit-house', { id });
      if (action === 'rooms') ctx.navigate('rooms', { house_id: id });
      if (action === 'status') openStatusModal(row, () => list.changed());
      if (action === 'delete') await deleteHouse(row, () => list.changed());
    },
    onReady: (list) => {
      const viewId = Number(ctx.params.get('view'));
      if (viewId) openHouseDetails(viewId, ctx, list).catch((e) => toast(e.message, 'error'));
    },
  });
}

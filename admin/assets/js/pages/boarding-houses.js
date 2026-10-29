/* Boarding house listings: review, approve/reject, edit, photos, delete. */

import { api } from '../api.js';
import { html, raw, icon, badge, money, num, fmtDate, fmtDateTime, toast, confirmDialog, openModal, formModal, details, $, $$, withLoading } from '../ui.js';
import { createListPage, actionButton } from './_list.js';

const STATUS_OPTIONS = [
  { value: 'pending', label: 'Pending approval' },
  { value: 'approved', label: 'Approved' },
  { value: 'rejected', label: 'Rejected' },
  { value: 'inactive', label: 'Inactive' },
];

const priceRange = (h) => {
  if (h.min_price === null) return html`<span class="text-slate-400">No rooms</span>`;
  return h.min_price === h.max_price ? money(h.min_price) : html`<span class="whitespace-nowrap">${money(h.min_price)} – ${money(h.max_price)}</span>`;
};

const houseCell = (h) => html`
  <div class="flex min-w-0 items-center gap-3">
    ${h.cover_image
      ? html`<img src="../${h.cover_image}" alt="" class="h-11 w-11 shrink-0 rounded-xl object-cover" loading="lazy" />`
      : html`<span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-emerald-50 text-emerald-600">${icon('house', 'h-5 w-5')}</span>`}
    <div class="min-w-0">
      <p class="truncate font-semibold text-ink">${h.name}</p>
      <p class="truncate text-xs text-slate-500">${[h.barangay, h.city].filter(Boolean).join(', ')}</p>
    </div>
  </div>`;

async function houseFields() {
  const landlords = await api.options('landlords');
  return [
    { name: 'name', label: 'Boarding house name', required: true, span: 2, maxlength: 150 },
    { name: 'landlord_id', label: 'Owner / landlord', type: 'select', required: true, placeholder: 'Select a landlord', options: landlords.map((l) => ({ value: l.id, label: l.label + (l.status === 'disabled' ? ' (disabled)' : '') })) },
    { name: 'status', label: 'Status', type: 'select', options: STATUS_OPTIONS, required: true },
    { name: 'description', label: 'Description', type: 'textarea', maxlength: 5000, placeholder: 'What makes this place good for students?' },
    { name: 'address', label: 'Street address', required: true, maxlength: 255, placeholder: 'House no. and street' },
    { name: 'barangay', label: 'Barangay', maxlength: 100 },
    { name: 'city', label: 'City / municipality', required: true, maxlength: 100 },
    { name: 'province', label: 'Province', maxlength: 100 },
    { name: 'nearby_school', label: 'Nearest school', maxlength: 150, placeholder: 'e.g. Surigao del Norte State University' },
    { name: 'contact_number', label: 'Contact number', type: 'tel', placeholder: '09XXXXXXXXX' },
    { name: 'contact_email', label: 'Contact email', type: 'email' },
    { name: 'latitude', label: 'Latitude', type: 'number', step: 'any', min: -90, max: 90, hint: 'Optional — used for the map.' },
    { name: 'longitude', label: 'Longitude', type: 'number', step: 'any', min: -180, max: 180 },
    { name: 'house_rules', label: 'House rules', type: 'textarea', maxlength: 5000, placeholder: 'Curfew, visitors, payment due dates…' },
  ];
}

export async function render(view, ctx) {
  const openForm = async (house, list) => {
    const creating = !house;
    formModal({
      title: creating ? 'Add boarding house' : `Edit ${house.name}`,
      subtitle: creating ? 'Listings created by an administrator can be published right away.' : `Added ${fmtDate(house.created_at)}`,
      fields: await houseFields(),
      values: creating ? { status: 'approved', province: 'Surigao del Norte' } : house,
      size: 'lg',
      submitText: creating ? 'Create listing' : 'Save changes',
      onSubmit: async (data) => {
        if (!creating && data.status === 'rejected' && house.status !== 'rejected') {
          throw Object.assign(new Error('Use the Reject button so you can give the landlord a reason.'), { errors: { status: 'Use Reject instead.' } });
        }
        const res = creating ? await api.boardingHouses.create(data) : await api.boardingHouses.update(house.id, data);
        toast(res.message);
        list.changed();
      },
    });
  };

  const approve = async (house, list, modal) => {
    const ok = await confirmDialog({
      title: 'Approve listing?',
      message: `“${house.name}” will be published and students can start booking its rooms. The landlord will be notified.`,
      confirmText: 'Approve listing',
      iconName: 'checkCircle',
    });
    if (!ok) return;
    const res = await api.boardingHouses.update(house.id, { status: 'approved' });
    toast(res.message);
    modal?.close();
    list.changed();
  };

  const reject = (house, list, modal) => {
    formModal({
      title: 'Reject listing',
      subtitle: house.name,
      size: 'md',
      fields: [{ name: 'rejection_reason', label: 'Reason for rejection', type: 'textarea', required: true, maxlength: 255, placeholder: 'Tell the landlord what to fix, e.g. “Please upload a clear copy of your business permit.”' }],
      submitText: 'Reject listing',
      onSubmit: async (data) => {
        const res = await api.boardingHouses.update(house.id, { status: 'rejected', rejection_reason: data.rejection_reason });
        toast(res.message);
        modal?.close();
        list.changed();
      },
    });
  };

  const setInactive = async (house, list, modal) => {
    const deactivate = house.status !== 'inactive';
    const ok = await confirmDialog({
      title: deactivate ? 'Deactivate listing?' : 'Re-activate listing?',
      message: deactivate
        ? `“${house.name}” will be hidden from students. Existing bookings are kept.`
        : `“${house.name}” will be visible to students again.`,
      confirmText: deactivate ? 'Deactivate' : 'Re-activate',
      danger: deactivate,
    });
    if (!ok) return;
    const res = await api.boardingHouses.update(house.id, { status: deactivate ? 'inactive' : 'approved' });
    toast(res.message);
    modal?.close();
    list.changed();
  };

  const remove = async (house, list) => {
    const ok = await confirmDialog({
      title: 'Delete boarding house?',
      message: `This permanently deletes “${house.name}”, its ${house.room_count} room(s) and photos. Listings with booking history cannot be deleted — deactivate them instead.`,
      confirmText: 'Delete permanently',
      danger: true,
      iconName: 'trash',
    });
    if (!ok) return;
    const res = await api.boardingHouses.remove(house.id);
    toast(res.message);
    list.changed();
  };

  const gallery = (house) => html`
    <div class="grid grid-cols-2 gap-2 sm:grid-cols-4" data-gallery>
      ${house.images.map((img) => html`
        <figure class="group relative aspect-[4/3] overflow-hidden rounded-xl bg-slate-100">
          <img src="../${img.file_path}" alt="Photo of ${house.name}" class="h-full w-full object-cover" loading="lazy" />
          ${img.is_cover ? html`<span class="badge badge-green no-dot absolute left-2 top-2">Cover</span>` : ''}
          <button type="button" class="icon-btn danger absolute right-2 top-2 bg-white/90 opacity-100 sm:opacity-0 sm:group-hover:opacity-100" data-remove-image="${img.id}" aria-label="Remove photo">${icon('trash')}</button>
        </figure>`)}
      <label class="flex aspect-[4/3] cursor-pointer flex-col items-center justify-center gap-1 rounded-xl border-2 border-dashed border-slate-200 text-xs font-medium text-slate-500 hover:border-primary hover:text-primary">
        ${icon('image', 'h-6 w-6')} Add photos
        <input type="file" accept="image/jpeg,image/png,image/webp" multiple class="sr-only" data-upload />
      </label>
    </div>
    <p class="form-hint">JPG, PNG or WebP, up to 5MB each. The first photo becomes the cover.</p>`;

  const showDetails = async (id, list) => {
    const house = await api.boardingHouses.get(id);
    const occupancy = house.total_capacity ? Math.round((house.total_occupants / house.total_capacity) * 100) : 0;
    const modal = openModal({
      title: house.name,
      subtitle: [house.barangay, house.city, house.province].filter(Boolean).join(', '),
      size: 'xl',
      body: html`
        <div class="mb-5 flex flex-wrap items-center gap-2">${badge(house.status, house.status === 'pending' ? 'Pending approval' : '')}
          ${house.status === 'rejected' && house.rejection_reason ? html`<span class="text-sm text-rose-600">Reason: ${house.rejection_reason}</span>` : ''}</div>

        <div class="grid gap-4 sm:grid-cols-3">
          <div class="rounded-2xl bg-slate-50 p-4"><p class="text-xs text-slate-500">Rooms</p><p class="mt-1 text-xl font-semibold">${num(house.available_rooms)} <span class="text-sm font-normal text-slate-500">of ${num(house.room_count)} available</span></p></div>
          <div class="rounded-2xl bg-slate-50 p-4"><p class="text-xs text-slate-500">Monthly rent</p><p class="mt-1 text-xl font-semibold">${priceRange(house)}</p></div>
          <div class="rounded-2xl bg-slate-50 p-4"><p class="text-xs text-slate-500">Occupancy</p><p class="mt-1 text-xl font-semibold">${occupancy}% <span class="text-sm font-normal text-slate-500">${num(house.total_occupants)}/${num(house.total_capacity)} beds</span></p></div>
        </div>

        <h3 class="mb-2 mt-6 text-sm font-semibold text-ink">Photos</h3>
        <div data-gallery-wrap>${gallery(house)}</div>

        <h3 class="mb-3 mt-6 text-sm font-semibold text-ink">Details</h3>
        ${details([
          ['Owner / landlord', html`<a class="font-medium text-primary hover:underline" href="#/landlords?view=${house.owner_user_id}" data-close>${house.owner_name}</a><br><span class="text-xs text-slate-500">${house.owner_email}</span>`],
          ['Contact', [house.contact_number, house.contact_email].filter(Boolean).join(' · ') || house.owner_contact],
          ['Address', [house.address, house.barangay, house.city, house.province].filter(Boolean).join(', ')],
          ['Nearest school', house.nearby_school],
          ['Amenities', house.amenities.length ? house.amenities.join(', ') : ''],
          ['Date added', fmtDateTime(house.created_at)],
          house.approved_at ? ['Approved', fmtDateTime(house.approved_at)] : null,
          house.latitude ? ['Map', html`<a class="text-primary hover:underline" target="_blank" rel="noopener" href="https://www.google.com/maps?q=${house.latitude},${house.longitude}">Open in Google Maps</a>`] : null,
        ])}
        ${house.description ? html`<h3 class="mb-1 mt-6 text-sm font-semibold text-ink">Description</h3><p class="whitespace-pre-line text-sm text-slate-600">${house.description}</p>` : ''}
        ${house.house_rules ? html`<h3 class="mb-1 mt-6 text-sm font-semibold text-ink">House rules</h3><p class="whitespace-pre-line text-sm text-slate-600">${house.house_rules}</p>` : ''}

        <div class="mb-2 mt-6 flex items-center justify-between">
          <h3 class="text-sm font-semibold text-ink">Rooms (${house.rooms.length})</h3>
          <a href="#/rooms?boarding_house_id=${house.id}" class="text-xs font-semibold text-primary hover:underline" data-close>Manage rooms</a>
        </div>
        ${house.rooms.length ? html`
          <div class="overflow-x-auto rounded-xl border border-slate-200"><table class="data-table">
            <thead><tr><th>Room</th><th>Type</th><th class="num">Price</th><th class="num">Occupants</th><th>Status</th></tr></thead>
            <tbody>${house.rooms.map((r) => html`<tr><td class="font-medium">${r.room_number}</td><td class="capitalize">${r.room_type}</td><td class="num">${money(r.price)}</td><td class="num">${r.occupants}/${r.capacity}</td><td>${badge(r.status)}</td></tr>`)}</tbody>
          </table></div>` : html`<p class="text-sm text-slate-400">No rooms added yet.</p>`}`,
      footer: html`
        ${house.status === 'pending' || house.status === 'rejected' ? html`<button type="button" class="btn btn-primary" data-approve>${icon('check')} Approve</button>` : ''}
        ${house.status === 'pending' ? html`<button type="button" class="btn btn-secondary text-rose-600" data-reject>${icon('x')} Reject</button>` : ''}
        ${house.status === 'approved' || house.status === 'inactive' ? html`<button type="button" class="btn btn-secondary" data-inactive>${house.status === 'inactive' ? 'Re-activate' : 'Deactivate'}</button>` : ''}
        <button type="button" class="btn btn-secondary" data-edit>${icon('edit')} Edit</button>
        <button type="button" class="btn btn-secondary" data-close>Close</button>`,
    });

    $('[data-approve]', modal.el)?.addEventListener('click', () => approve(house, list, modal));
    $('[data-reject]', modal.el)?.addEventListener('click', () => reject(house, list, modal));
    $('[data-inactive]', modal.el)?.addEventListener('click', () => setInactive(house, list, modal));
    $('[data-edit]', modal.el).addEventListener('click', () => { modal.close(); openForm(house, list); });

    const wrap = $('[data-gallery-wrap]', modal.el);
    const bindGallery = (current) => {
      $('[data-upload]', wrap).addEventListener('change', async (event) => {
        const files = event.target.files;
        if (!files.length) return;
        const label = event.target.closest('label');
        label.classList.add('pointer-events-none', 'opacity-60');
        try {
          const res = await api.boardingHouses.uploadImages(current.id, files);
          toast(res.message);
          current.images = (await api.boardingHouses.get(current.id)).images;
          wrap.innerHTML = String(gallery(current));
          bindGallery(current);
          list.reload();
        } catch (error) {
          toast(error.message, 'error');
          label.classList.remove('pointer-events-none', 'opacity-60');
        }
      });
      $$('[data-remove-image]', wrap).forEach((btn) => btn.addEventListener('click', async () => {
        const ok = await confirmDialog({ title: 'Remove photo?', message: 'The photo will be deleted from this listing.', confirmText: 'Remove', danger: true });
        if (!ok) return;
        try {
          await withLoading(btn, () => api.boardingHouses.removeImage(btn.dataset.removeImage));
          current.images = (await api.boardingHouses.get(current.id)).images;
          wrap.innerHTML = String(gallery(current));
          bindGallery(current);
          list.reload();
        } catch (error) {
          toast(error.message, 'error');
        }
      }));
    };
    bindGallery(house);
  };

  const list = createListPage(view, ctx, {
    title: 'Boarding Houses',
    description: 'Review new listings, keep information accurate and control what students can see.',
    headerActions: html`<button type="button" class="btn btn-primary" data-add>${icon('plus')} Add boarding house</button>`,
    tabKey: 'status',
    tabs: (c) => [
      { value: '', label: 'All', count: c.all_houses },
      { value: 'pending', label: 'Pending', count: c.pending },
      { value: 'approved', label: 'Approved', count: c.approved },
      { value: 'rejected', label: 'Rejected', count: c.rejected },
      { value: 'inactive', label: 'Inactive', count: c.inactive },
    ],
    defaults: { sort: 'created_at', order: 'desc' },
    searchPlaceholder: 'Search by name, address, city or owner…',
    fetch: (query) => api.boardingHouses.list(query),
    columns: () => [
      { key: 'name', label: 'Boarding house', sort: 'name', primary: true, render: houseCell },
      { key: 'owner_name', label: 'Owner', render: (h) => h.owner_name },
      { key: 'rooms', label: 'Rooms', sort: 'rooms', render: (h) => html`<span class="whitespace-nowrap"><span class="font-medium">${num(h.available_rooms)}</span><span class="text-slate-400"> / ${num(h.room_count)} free</span></span>` },
      { key: 'price', label: 'Monthly rent', sort: 'price', render: priceRange },
      { key: 'status', label: 'Status', sort: 'status', render: (h) => badge(h.status) },
      { key: 'created_at', label: 'Date added', sort: 'created_at', render: (h) => fmtDate(h.created_at), hideOnMobile: true },
    ],
    actions: (h) => html`
      ${actionButton('view', h.id, 'eye', 'View details')}
      ${h.status === 'pending' || h.status === 'rejected' ? actionButton('approve', h.id, 'check', 'Approve', 'success') : ''}
      ${h.status === 'pending' ? actionButton('reject', h.id, 'x', 'Reject', 'danger') : ''}
      ${actionButton('edit', h.id, 'edit', 'Edit')}
      ${actionButton('delete', h.id, 'trash', 'Delete', 'danger')}`,
    onAction: (action, id, row, listRef) => ({
      view: () => showDetails(id, listRef),
      approve: () => approve(row, listRef),
      reject: () => reject(row, listRef),
      edit: () => openForm(row, listRef),
      delete: () => remove(row, listRef),
    })[action]?.(),
    emptyState: (state) => html`<div class="py-14 text-center text-sm text-slate-500">${state.status === 'pending' ? raw('🎉 No listings are waiting for approval.') : 'No boarding houses match this filter.'}</div>`,
    onReady: (listRef) => {
      const viewId = Number(ctx.params.get('view'));
      if (viewId) showDetails(viewId, listRef).catch((e) => toast(e.message, 'error'));
    },
  });

  view.querySelector('[data-add]').addEventListener('click', () => openForm(null, list).catch((e) => toast(e.message, 'error')));
}

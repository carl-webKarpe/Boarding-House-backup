/* Room Management: add, edit, delete rooms, change availability, upload room photos. */

import { api } from '../api.js';
import {
  html, setHtml, icon, $, num, money, badge, toast, confirmDialog, openModal, withLoading, thumb, savedPhotos, createImagePicker,
} from '../ui.js';
import { createListPage, actionButton } from '../../../../admin/assets/js/pages/_list.js';
import { mountRoomForm } from './room-form.js';

const AVAILABILITY_BADGE = {
  available: ['available', 'Available'],
  full: ['occupied', 'Full'],
  unavailable: ['unavailable', 'Unavailable'],
};

/** Room add/edit dialog. */
async function openRoomModal({ room = null, houses, houseId = '', onSaved }) {
  if (!room && !houses.length) {
    toast('Add a boarding house first, then add its rooms.', 'error');
    return;
  }
  const modal = openModal({
    title: room ? `Edit ${room.room_number}` : 'Add room',
    subtitle: room ? room.house_name : 'The room is added to the boarding house you choose.',
    size: 'lg',
    body: html`
      ${room ? '' : html`
        <div class="mb-5">
          <label class="form-label" for="roomHouse">Boarding house <span class="text-rose-500">*</span></label>
          <select id="roomHouse" class="form-control">
            ${houses.length > 1 ? html`<option value="">Choose a boarding house</option>` : ''}
            ${houses.map((h) => html`<option value="${h.id}" ${String(h.id) === String(houseId) ? 'selected' : ''}>${h.label}</option>`)}
          </select>
          <p class="form-error hidden" data-error-for="house_id"></p>
        </div>`}
      <div data-room-holder><div class="skeleton h-40"></div></div>`,
    footer: html`
      <button type="button" class="btn btn-secondary" data-close>Cancel</button>
      <button type="button" class="btn btn-primary" data-save-room>${icon('check')} ${room ? 'Save changes' : 'Add room'}</button>`,
  });

  const roomForm = await mountRoomForm($('[data-room-holder]', modal.el), { room, showQuantity: !room, idPrefix: 'm' });
  if (room) wireSavedRoomPhotos(modal.el, room);

  $('[data-save-room]', modal.el).addEventListener('click', async (event) => {
    const { data, errors } = roomForm.read();
    const houseSelect = $('#roomHouse', modal.el);
    const houseError = $('[data-error-for="house_id"]', modal.el);
    if (houseSelect && !houseSelect.value) {
      houseError.textContent = 'Choose the boarding house.';
      houseError.classList.remove('hidden');
      houseSelect.classList.add('is-invalid');
      return;
    }
    if (roomForm.showErrors(errors)) return;

    const files = roomForm.files();
    try {
      await withLoading(event.currentTarget, async () => {
        const res = room ? await api.rooms.update(room.id, data) : await api.rooms.create({ ...data, house_id: houseSelect.value });
        const ids = room ? [room.id] : res.data.ids;
        const failures = [];
        for (const id of ids) {
          for (const file of files) {
            try { await api.rooms.uploadImages(id, [file]); } catch (e) { failures.push(e.message); }
          }
        }
        if (failures.length) toast(`Room saved, but some photos were not uploaded: ${[...new Set(failures)].join(' ')}`, 'error');
        else toast(res.message);
      });
      modal.close();
      onSaved?.();
    } catch (error) {
      roomForm.showErrors(error.errors || {});
      toast(error.message, 'error');
    }
  });
}

/** Delete / refresh the saved photos shown inside an edit dialog. */
function wireSavedRoomPhotos(root, room) {
  const holder = $('[data-saved-photos]', root);
  holder?.addEventListener('click', async (event) => {
    const btn = event.target.closest('[data-delete-image]');
    if (!btn) return;
    const ok = await confirmDialog({ title: 'Delete this photo?', message: 'The photo is removed from the room permanently.', confirmText: 'Delete photo', danger: true, iconName: 'trash' });
    if (!ok) return;
    try {
      const res = await api.rooms.removeImage(Number(btn.dataset.deleteImage));
      toast(res.message || 'Photo deleted.');
      const fresh = await api.rooms.get(room.id);
      setHtml(holder, savedPhotos(fresh.images));
    } catch (error) {
      toast(error.message, 'error');
    }
  });
}

/** Quick photo manager for a room. */
async function openPhotosModal(roomId, onDone) {
  const room = await api.rooms.get(roomId);
  const modal = openModal({
    title: `Photos · ${room.room_number}`,
    subtitle: room.house_name,
    size: 'lg',
    body: html`
      <div data-saved-photos>${savedPhotos(room.images)}</div>
      <div class="mt-5" data-picker></div>`,
    footer: html`
      <button type="button" class="btn btn-secondary" data-close>Close</button>
      <button type="button" class="btn btn-primary" data-upload>${icon('upload')} Upload photos</button>`,
    onClose: onDone,
  });
  const picker = createImagePicker($('[data-picker]', modal.el), { title: 'Add room photos', max: 10 });
  wireSavedRoomPhotos(modal.el, room);
  $('[data-upload]', modal.el).addEventListener('click', async (event) => {
    const files = picker.files();
    if (!files.length) { toast('Choose at least one photo first.', 'error'); return; }
    try {
      await withLoading(event.currentTarget, async () => {
        for (const file of files) await api.rooms.uploadImages(room.id, [file]);
      });
      toast(`${files.length} photo${files.length === 1 ? '' : 's'} uploaded.`);
      picker.clear();
      const fresh = await api.rooms.get(room.id);
      setHtml($('[data-saved-photos]', modal.el), savedPhotos(fresh.images));
    } catch (error) {
      toast(error.message, 'error');
    }
  });
}

export async function render(view, ctx) {
  const houses = await api.options('houses');
  const houseId = ctx.params.get('house_id') || '';

  createListPage(view, ctx, {
    title: 'Room Management',
    description: 'Rooms of your boarding houses. Students only see rooms of approved, available listings.',
    headerActions: html`<button type="button" class="btn btn-primary" data-add-room>${icon('plus')} Add room</button>`,
    searchPlaceholder: 'Search room name…',
    filters: [
      { name: 'house_id', label: 'Boarding house', options: houses.map((h) => ({ value: h.id, label: h.label })) },
      { name: 'availability', label: 'Availability', options: [{ value: 'available', label: 'Available' }, { value: 'full', label: 'Full' }, { value: 'unavailable', label: 'Unavailable' }] },
    ],
    fetch: async (query) => {
      const res = await api.rooms.list({ house_id: query.house_id });
      const q = String(query.q || '').toLowerCase();
      const data = res.data.filter((r) => (!q || `${r.room_number} ${r.house_name}`.toLowerCase().includes(q))
        && (!query.availability || r.availability === query.availability));
      return { data };
    },
    columns: () => [
      {
        key: 'room_number', label: 'Room', primary: true,
        render: (r) => html`<div class="flex items-center gap-3">${thumb(r.photo, r.room_number)}<div class="min-w-0">
          <p class="truncate font-semibold text-ink">${r.room_number}</p>
          <p class="truncate text-xs text-slate-500">${r.house_name}</p></div></div>`,
      },
      { key: 'room_type', label: 'Type', render: (r) => r.room_type_label },
      { key: 'price', label: 'Rent', render: (r) => html`${money(r.price)}<span class="text-xs text-slate-400">${r.room_type === 'solo' || r.room_type === 'studio' ? '/month' : '/person'}</span>` },
      { key: 'slots', label: 'Slots', render: (r) => html`<span class="font-semibold">${num(r.available_slots)}</span><span class="text-slate-400"> free of ${num(r.capacity)}</span>` },
      {
        key: 'availability', label: 'Availability',
        render: (r) => html`${badge(...AVAILABILITY_BADGE[r.availability])}${r.pending_reservations ? html`<span class="mt-1 block text-[11px] text-amber-700">${num(r.pending_reservations)} pending</span>` : ''}`,
      },
      { key: 'amenities', label: 'Amenities', hideOnMobile: true, render: (r) => html`<span class="text-xs text-slate-500">${r.amenities.length ? r.amenities.slice(0, 3).join(', ') + (r.amenities.length > 3 ? ` +${r.amenities.length - 3}` : '') : '—'}</span>` },
    ],
    actions: (r) => html`
      ${actionButton('edit', r.id, 'edit', 'Edit room')}
      ${actionButton('photos', r.id, 'image', 'Room photos')}
      ${actionButton('toggle', r.id, r.availability === 'unavailable' ? 'unlock' : 'ban', r.availability === 'unavailable' ? 'Make available' : 'Make unavailable')}
      ${actionButton('delete', r.id, 'trash', 'Delete room', 'text-rose-600 hover:bg-rose-50')}`,
    emptyState: (state) => html`
      <div class="flex flex-col items-center px-6 py-14 text-center">
        <span class="grid h-14 w-14 place-items-center rounded-2xl bg-primary-50 text-primary">${icon('bed', 'h-6 w-6')}</span>
        <p class="mt-4 font-semibold text-ink">${houses.length ? (state.q || state.availability ? 'No rooms match your filters' : 'No rooms yet') : 'Add a boarding house first'}</p>
        <p class="mt-1 max-w-sm text-sm text-slate-500">${houses.length ? 'Add rooms with prices, amenities and photos so students can reserve them.' : 'Rooms belong to a boarding house.'}</p>
        ${houses.length
          ? html`<button type="button" class="btn btn-primary mt-4" data-add-room>${icon('plus')} Add room</button>`
          : html`<a href="#/add-house" class="btn btn-primary mt-4">${icon('plus')} Add Boarding House</a>`}
      </div>`,
    onAction: async (action, id, row, list) => {
      if (action === 'edit') {
        const room = await api.rooms.get(id);
        await openRoomModal({ room, houses, onSaved: () => list.changed() });
      }
      if (action === 'photos') await openPhotosModal(id, () => list.reload());
      if (action === 'toggle') {
        const next = row.availability === 'unavailable' ? 'available' : 'unavailable';
        const ok = await confirmDialog({
          title: next === 'available' ? 'Make this room available?' : 'Make this room unavailable?',
          message: next === 'available'
            ? `${row.room_number} will be shown to students again.`
            : `${row.room_number} will be hidden from students (for repairs or when closed). Existing reservations are kept.`,
          confirmText: next === 'available' ? 'Make available' : 'Make unavailable',
          iconName: next === 'available' ? 'unlock' : 'ban',
        });
        if (!ok) return;
        const res = await api.rooms.setAvailability(id, next);
        toast(res.message);
        list.changed();
      }
      if (action === 'delete') {
        const ok = await confirmDialog({
          title: 'Delete this room?',
          message: `${row.room_number} (${row.house_name}) and its photos will be removed permanently.`,
          confirmText: 'Delete room',
          danger: true,
          iconName: 'trash',
        });
        if (!ok) return;
        const res = await api.rooms.remove(id);
        toast(res.message || 'Room deleted.');
        list.changed();
      }
    },
    onReady: (list) => {
      // The list's own element is new on every visit, so this listener never piles up.
      view.firstElementChild.addEventListener('click', (event) => {
        if (!event.target.closest('[data-add-room]')) return;
        const current = view.querySelector('[data-filter="house_id"]')?.value || houseId;
        openRoomModal({ houses, houseId: current, onSaved: () => list.changed() });
      });
      if (ctx.params.get('add') === '1') openRoomModal({ houses, houseId, onSaved: () => list.changed() });
    },
  });
}

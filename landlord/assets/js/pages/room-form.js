/* ==========================================================================
   Room form, shared by "Add Boarding House" (several room cards) and
   Room Management (add / edit one room in a modal).

   Single Room: monthly price for the whole room + vacant / occupied.
   Shared Room: price per person, number of occupants, available slots.
   ========================================================================== */

import { api } from '../api.js';
import { html, setHtml, icon, $, $$, toast, escapeHtml, createImagePicker, savedPhotos, ROOM_TYPE_OPTIONS, formRoomType } from '../ui.js';

let amenityCache = null;

/** Standard amenities + this landlord's custom ones (cached for the page visit). */
export async function loadAmenities(force = false) {
  if (!amenityCache || force) amenityCache = await api.options('amenities');
  return amenityCache;
}

/** Converts saved room data into the form's terms. */
function toFormValues(room) {
  if (!room) {
    return { room_type: 'single', capacity: 2, available_slots: 1, single_state: 'vacant', availability: 'available', quantity: 1, amenity_ids: [] };
  }
  const type = formRoomType(room.room_type);
  const unavailable = room.status === 'maintenance';
  return {
    ...room,
    room_type: type,
    single_state: unavailable ? 'unavailable' : room.available_slots > 0 ? 'vacant' : 'occupied',
    availability: unavailable ? 'unavailable' : 'available',
  };
}

const field = (name, label, control, { hint = '', required = false, span = '' } = {}) => html`
  <div class="${span}">
    <label class="form-label" for="${name}">${label}${required ? html`<span class="text-rose-500"> *</span>` : ''}</label>
    ${control}
    ${hint ? html`<p class="form-hint">${hint}</p>` : ''}
    <p class="form-error hidden" data-error-for="${name}"></p>
  </div>`;

/**
 * Renders one room form into `holder`.
 *  - room: saved room (edit) or null (new)
 *  - showQuantity: "How many rooms like this?" (new rooms only)
 *  - showPhotos: photo picker (and saved photos when editing)
 * Returns { read(): {data, errors}, files(): File[], el }.
 */
export async function mountRoomForm(holder, { room = null, showQuantity = true, showPhotos = true, idPrefix = 'room' } = {}) {
  const amenities = await loadAmenities();
  const v = toFormValues(room);
  const id = (name) => `${idPrefix}-${name}`;
  const selected = new Set((v.amenity_ids || []).map(Number));

  setHtml(holder, html`
    <div class="space-y-5" data-room-form>
      <div class="grid gap-4 sm:grid-cols-2">
        ${field(id('room_number'), 'Room name / number', html`<input id="${id('room_number')}" name="room_number" class="form-control" maxlength="50" required value="${v.room_number || ''}" placeholder="e.g. Room 1, Room A, Upper Floor 2" />`, { required: true })}
        ${showQuantity ? field(id('quantity'), 'How many rooms like this?', html`<input id="${id('quantity')}" name="quantity" type="number" min="1" max="20" class="form-control" value="${v.quantity}" />`, { hint: 'Adds identical rooms numbered automatically (e.g. Room 1, Room 2, Room 3).' }) : ''}
      </div>

      <fieldset>
        <legend class="form-label">Room type <span class="text-rose-500">*</span></legend>
        <div class="grid gap-2 sm:grid-cols-2">${ROOM_TYPE_OPTIONS.map((o) => html`
          <label class="choice-card">
            <input type="radio" name="${id('type')}" value="${o.value}" data-room-type ${v.room_type === o.value ? 'checked' : ''} />
            <span><span class="block font-semibold text-ink">${o.label}</span><span class="block text-xs text-slate-500">${o.hint}</span></span>
          </label>`)}</div>
      </fieldset>

      <div class="grid gap-4 sm:grid-cols-2">
        ${field(id('price'), html`<span data-price-label>Monthly rent (₱)</span>`, html`<input id="${id('price')}" name="price" type="number" min="100" max="100000" step="50" class="form-control" required value="${v.price ?? ''}" placeholder="e.g. 2500" />`, { required: true })}
        ${field(id('deposit'), 'Deposit (₱, optional)', html`<input id="${id('deposit')}" name="deposit" type="number" min="0" step="50" class="form-control" value="${v.deposit || ''}" placeholder="0" />`)}

        <div data-single-only class="sm:col-span-2">
          ${field(id('single_state'), 'Availability', html`
            <select id="${id('single_state')}" name="single_state" class="form-control">
              <option value="vacant" ${v.single_state === 'vacant' ? 'selected' : ''}>Available (vacant)</option>
              <option value="occupied" ${v.single_state === 'occupied' ? 'selected' : ''}>Occupied</option>
              <option value="unavailable" ${v.single_state === 'unavailable' ? 'selected' : ''}>Not available (repairs / closed)</option>
            </select>`)}
        </div>

        <div data-shared-only>
          ${field(id('capacity'), 'Number of occupants (capacity)', html`<input id="${id('capacity')}" name="capacity" type="number" min="2" max="20" class="form-control" value="${v.room_type === 'shared' ? v.capacity : 2}" />`, { required: true, hint: 'How many people the room is for.' })}
        </div>
        <div data-shared-only>
          ${field(id('available_slots'), 'Available slots', html`<input id="${id('available_slots')}" name="available_slots" type="number" min="0" max="20" class="form-control" value="${v.room_type === 'shared' ? v.available_slots : 1}" />`, { required: true, hint: 'Beds still free for new tenants.' })}
        </div>
        <div data-shared-only class="sm:col-span-2">
          ${field(id('availability'), 'Availability', html`
            <select id="${id('availability')}" name="availability" class="form-control">
              <option value="available" ${v.availability === 'available' ? 'selected' : ''}>Open for reservations</option>
              <option value="unavailable" ${v.availability === 'unavailable' ? 'selected' : ''}>Not available (repairs / closed)</option>
            </select>`)}
        </div>
        ${field(id('size_sqm'), 'Room size (m², optional)', html`<input id="${id('size_sqm')}" name="size_sqm" type="number" min="1" max="1000" step="0.5" class="form-control" value="${v.size_sqm ?? ''}" />`)}
      </div>

      <fieldset>
        <legend class="form-label">Amenities</legend>
        <div class="amenity-grid" data-amenities>${amenities.map((a) => html`
          <label class="check-pill"><input type="checkbox" value="${a.id}" data-amenity ${selected.has(a.id) ? 'checked' : ''} /> ${a.name}</label>`)}</div>
        <div class="mt-3 flex gap-2">
          <label class="sr-only" for="${id('custom')}">Custom amenity</label>
          <input id="${id('custom')}" class="form-control" maxlength="60" placeholder="Add another amenity (e.g. Rooftop, Water dispenser)" data-custom-input />
          <button type="button" class="btn btn-secondary shrink-0" data-custom-add>${icon('plus')} Add</button>
        </div>
        <p class="form-hint">Custom amenities are saved to your list so you can reuse them.</p>
      </fieldset>

      ${field(id('description'), 'Room description', html`<textarea id="${id('description')}" name="description" rows="5" maxlength="5000" class="form-control" placeholder="Describe the room: bed size, windows, what's included in the rent, curfew for this room, and other details students should know.">${v.description || ''}</textarea>`)}

      ${showPhotos ? html`
        <div>
          <p class="form-label">Room photos</p>
          ${room ? html`<div class="mb-3" data-saved-photos>${savedPhotos(room.images || [])}</div>` : ''}
          <div data-room-photos></div>
        </div>` : ''}
    </div>`);

  const root = $('[data-room-form]', holder);
  const picker = showPhotos ? createImagePicker($('[data-room-photos]', root), { title: room ? 'Add more room photos' : 'Add room photos', max: 10 }) : null;

  // Show the right fields for the room type.
  const syncType = () => {
    const type = $('[data-room-type]:checked', root)?.value || 'single';
    $$('[data-single-only]', root).forEach((el) => el.classList.toggle('hidden', type !== 'single'));
    $$('[data-shared-only]', root).forEach((el) => el.classList.toggle('hidden', type !== 'shared'));
    $('[data-price-label]', root).textContent = type === 'shared' ? 'Price per person / month (₱)' : 'Monthly rent (₱)';
  };
  $$('[data-room-type]', root).forEach((r) => r.addEventListener('change', syncType));
  syncType();

  // Custom amenities: saved right away and ticked.
  const addCustom = async () => {
    const input = $('[data-custom-input]', root);
    const name = input.value.trim().replace(/\s+/g, ' ');
    if (!name) return;
    try {
      const res = await api.addAmenity(name);
      const amenity = res.data;
      amenityCache = null;
      let box = $(`[data-amenity][value="${amenity.id}"]`, root);
      if (!box) {
        const label = document.createElement('label');
        label.className = 'check-pill';
        label.innerHTML = `<input type="checkbox" value="${Number(amenity.id)}" data-amenity /> ${escapeHtml(amenity.name)}`;
        $('[data-amenities]', root).appendChild(label);
        box = $('input', label);
      }
      box.checked = true;
      input.value = '';
      toast(res.message);
    } catch (error) {
      toast(error.message, 'error');
    }
  };
  $('[data-custom-add]', root).addEventListener('click', addCustom);
  $('[data-custom-input]', root).addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); addCustom(); } });

  /** Reads and checks the form. Returns { data, errors } in API terms. */
  const read = () => {
    const val = (name) => (root.querySelector(`[name="${name}"]`)?.value ?? '').trim();
    const type = $('[data-room-type]:checked', root)?.value || 'single';
    const errors = {};
    const data = {
      room_number: val('room_number'),
      room_type: type,
      price: val('price'),
      deposit: val('deposit'),
      size_sqm: val('size_sqm'),
      description: val('description'),
      amenity_ids: $$('[data-amenity]:checked', root).map((c) => Number(c.value)),
    };
    if (showQuantity) data.quantity = val('quantity') || '1';

    if (type === 'single') {
      const state = val('single_state');
      data.capacity = 1;
      data.available_slots = state === 'occupied' ? 0 : 1;
      data.availability = state === 'unavailable' ? 'unavailable' : 'available';
    } else {
      data.capacity = val('capacity');
      data.available_slots = val('available_slots');
      data.availability = val('availability');
      if (!(Number(data.capacity) >= 2 && Number(data.capacity) <= 20)) errors.capacity = 'A shared room is for 2 to 20 occupants.';
      if (data.available_slots === '' || Number(data.available_slots) < 0) errors.available_slots = 'Enter the number of free slots.';
      else if (Number(data.available_slots) > Number(data.capacity)) errors.available_slots = 'Cannot be more than the number of occupants.';
    }

    if (!data.room_number) errors.room_number = 'Enter a room name or number.';
    if (!(Number(data.price) >= 100 && Number(data.price) <= 100000)) errors.price = 'Enter a rent between ₱100 and ₱100,000.';
    if (showQuantity && !(Number(data.quantity) >= 1 && Number(data.quantity) <= 20)) errors.quantity = 'Between 1 and 20.';
    return { data, errors };
  };

  /** Shows errors ({ field: message }) from read() or the API under the fields. */
  const showErrors = (errors) => {
    $$('[data-error-for]', root).forEach((el) => { el.textContent = ''; el.classList.add('hidden'); });
    $$('.is-invalid', root).forEach((el) => el.classList.remove('is-invalid'));
    let first = null;
    Object.entries(errors || {}).forEach(([name, message]) => {
      const holderEl = $(`[data-error-for="${id(name)}"]`, root);
      const input = $(`#${id(name)}`, root);
      if (holderEl) { holderEl.textContent = message; holderEl.classList.remove('hidden'); }
      if (input) { input.classList.add('is-invalid'); first ||= input; }
    });
    first?.focus();
    return Boolean(first);
  };

  return { el: root, read, showErrors, files: () => (picker ? picker.files() : []), clearFiles: () => picker?.clear() };
}

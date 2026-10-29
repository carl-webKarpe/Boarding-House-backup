/* ==========================================================================
   Add / Edit Boarding House.

   Add:  details -> photos -> rooms (with amenities and room photos), saved
         one step after another with a progress dialog.
   Edit: the same details form; photos can be added, deleted or chosen as the
         main photo; rooms are managed in Room Management.
   ========================================================================== */

import { api } from '../api.js';
import {
  html, setHtml, icon, $, $$, toast, confirmDialog, openModal, pageHeader, listingBadge,
  createImagePicker, savedPhotos, AVAILABILITY_OPTIONS,
} from '../ui.js';
import { mountRoomForm, loadAmenities } from './room-form.js';

const DEFAULT_SCHOOL = 'Siargao Island Institute of Technology (SIIT)';
const SCHOOL_POINT = [9.760829, 126.047129];

/* ---------------------------------------------------------------------- *
 * Map helpers
 * ---------------------------------------------------------------------- */
function mapEmbedUrl(lat, lng) {
  const [la, ln] = Number.isFinite(lat) && Number.isFinite(lng) ? [lat, lng] : SCHOOL_POINT;
  const bbox = [ln - 0.006, la - 0.004, ln + 0.006, la + 0.004].map((n) => n.toFixed(6)).join(',');
  return `https://www.openstreetmap.org/export/embed.html?bbox=${bbox}&layer=mapnik&marker=${la.toFixed(6)},${ln.toFixed(6)}`;
}

/** Reads coordinates from a Google Maps / OpenStreetMap link, if it has any. */
export function coordsFromLink(link) {
  const text = decodeURIComponent(String(link || ''));
  const patterns = [/@(-?\d+\.\d+),\s*(-?\d+\.\d+)/, /!3d(-?\d+\.\d+)!4d(-?\d+\.\d+)/, /[?&](?:q|query|ll|center|destination)=(-?\d+\.\d+),\s*(-?\d+\.\d+)/, /mlat=(-?\d+\.\d+)&mlon=(-?\d+\.\d+)/, /^\s*(-?\d+\.\d+),\s*(-?\d+\.\d+)\s*$/];
  for (const re of patterns) {
    const m = text.match(re);
    if (m) {
      const lat = Number(m[1]);
      const lng = Number(m[2]);
      if (Math.abs(lat) <= 90 && Math.abs(lng) <= 180) return [lat, lng];
    }
  }
  return null;
}

/* ---------------------------------------------------------------------- *
 * Markup
 * ---------------------------------------------------------------------- */
const section = (number, title, description, body, id = '') => html`
  <section class="card form-section p-5 sm:p-6" ${id ? html`id="${id}"` : ''}>
    <div class="mb-5 flex items-start gap-3">
      <span class="section-number">${number}</span>
      <div><h2 class="font-display text-lg font-semibold text-ink">${title}</h2>${description ? html`<p class="text-sm text-slate-500">${description}</p>` : ''}</div>
    </div>
    ${body}
  </section>`;

const input = (name, label, { value = '', type = 'text', required = false, hint = '', placeholder = '', maxlength = 255, span = '', readonly = false, extra = '' } = {}) => html`
  <div class="${span}">
    <label class="form-label" for="h-${name}">${label}${required ? html`<span class="text-rose-500"> *</span>` : ''}</label>
    <input id="h-${name}" name="${name}" type="${type}" class="form-control ${readonly ? 'bg-slate-50 text-slate-500' : ''}" value="${value ?? ''}" placeholder="${placeholder}"
      ${maxlength ? html`maxlength="${maxlength}"` : ''} ${required ? 'required' : ''} ${readonly ? html`readonly tabindex="-1"` : ''} ${extra} />
    ${hint ? html`<p class="form-hint">${hint}</p>` : ''}
    <p class="form-error hidden" data-error-for="${name}"></p>
  </div>`;

function barangaySelect(groups, selectedId) {
  return html`
    <div>
      <label class="form-label" for="h-barangay_id">Barangay <span class="text-rose-500">*</span></label>
      <select id="h-barangay_id" name="barangay_id" class="form-control" required>
        <option value="">Choose a barangay</option>
        ${groups.map((g) => html`<optgroup label="${g.municipality}, ${g.province}">${g.barangays.map((b) => html`
          <option value="${b.id}" data-municipality="${g.municipality}" data-province="${g.province}" ${b.id === selectedId ? 'selected' : ''}>${b.name}</option>`)}</optgroup>`)}
      </select>
      <p class="form-hint">Not in the list? Ask the administrator to add your barangay.</p>
      <p class="form-error hidden" data-error-for="barangay_id"></p>
    </div>`;
}

/* ---------------------------------------------------------------------- *
 * Progress dialog for the step-by-step save
 * ---------------------------------------------------------------------- */
function progressDialog(steps) {
  const modal = openModal({
    title: 'Saving your boarding house',
    subtitle: 'Please keep this page open.',
    size: 'sm',
    body: html`<ol class="progress-steps">${steps.map((s, i) => html`
      <li data-step="${i}" data-state="waiting"><span data-step-icon class="grid h-6 w-6 place-items-center rounded-full bg-slate-100 text-xs">${i + 1}</span><span data-step-text>${s}</span></li>`)}</ol>`,
  });
  $('[data-close]', modal.el)?.classList.add('hidden');
  const set = (i, state, text) => {
    const li = $(`[data-step="${i}"]`, modal.el);
    if (!li) return;
    li.dataset.state = state;
    const iconHolder = $('[data-step-icon]', li);
    if (state === 'active') setHtml(iconHolder, html`<span class="h-4 w-4 animate-spin rounded-full border-2 border-primary/30 border-t-primary"></span>`);
    if (state === 'done') { iconHolder.className = 'grid h-6 w-6 place-items-center rounded-full bg-emerald-100 text-emerald-700'; setHtml(iconHolder, icon('check', 'h-3.5 w-3.5')); }
    if (state === 'error') { iconHolder.className = 'grid h-6 w-6 place-items-center rounded-full bg-rose-100 text-rose-600'; setHtml(iconHolder, icon('x', 'h-3.5 w-3.5')); }
    if (text) $('[data-step-text]', li).textContent = text;
  };
  return { ...modal, set };
}

/* ---------------------------------------------------------------------- *
 * Page
 * ---------------------------------------------------------------------- */
export async function render(view, ctx) {
  const editId = ctx.path === 'edit-house' ? Number(ctx.params.get('id')) : 0;
  const [groups, profile, house] = await Promise.all([
    api.options('barangays'),
    api.profile.get(),
    editId ? api.houses.get(editId) : Promise.resolve(null),
    loadAmenities(true),
  ]);
  const editing = Boolean(house);
  const v = house || {
    contact_name: profile.full_name,
    contact_number: profile.contact_number,
    contact_email: profile.email,
    nearby_school: DEFAULT_SCHOOL,
    availability_status: 'available',
  };

  const container = document.createElement('div');
  view.replaceChildren(container);

  setHtml(container, html`
    ${pageHeader({
      title: editing ? `Edit: ${house.name}` : 'Add Boarding House',
      description: editing
        ? 'Changes are saved to the database and shown to students right away.'
        : 'Fill in the details students need. Fields marked * are required.',
      actions: editing ? html`${listingBadge(house.display_status)}<a href="#/rooms?house_id=${house.id}" class="btn btn-secondary">${icon('bed')} Manage Rooms</a>` : '',
    })}
    ${editing && house.status === 'rejected' && house.rejection_reason ? html`
      <div class="mb-6 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">${icon('alert', 'mr-1 inline h-4 w-4')}
        <strong>Not approved:</strong> ${house.rejection_reason} · Saving your changes sends the listing back for approval.</div>` : ''}

    <form id="houseForm" novalidate class="space-y-6">
      ${section(1, 'Photos', 'A clear main photo gets more students to open your listing.', html`
        ${editing ? html`<div class="mb-4"><p class="form-label">Saved photos</p><div data-saved-house-photos>${savedPhotos(house.images, { cover: true })}</div></div>` : ''}
        <div class="grid gap-5 lg:grid-cols-2">
          <div><p class="form-label">${editing ? 'Replace main photo' : html`Main photo <span class="text-rose-500">*</span>`}</p><div data-cover-picker></div>
            <p class="form-error hidden" data-error-for="cover"></p></div>
          <div><p class="form-label">${editing ? 'Add more photos' : 'Additional photos'}</p><div data-more-picker></div></div>
        </div>`)}

      ${section(2, 'Boarding house information', '', html`
        <div class="grid gap-4 sm:grid-cols-2">
          ${input('name', 'Boarding house name', { value: v.name, required: true, maxlength: 150, span: 'sm:col-span-2', placeholder: 'e.g. Lime Haven Boarding House' })}
          ${input('contact_name', 'Landlord / contact name', { value: v.contact_name, required: true, maxlength: 160 })}
          ${input('contact_number', 'Contact number', { value: v.contact_number, required: true, type: 'tel', maxlength: 20, placeholder: '09XXXXXXXXX' })}
          ${input('contact_email', 'Email address', { value: v.contact_email, required: true, type: 'email', maxlength: 150, span: 'sm:col-span-2' })}
        </div>`)}

      ${section(3, 'Location', 'Where students can find the boarding house.', html`
        <div class="grid gap-4 sm:grid-cols-3">
          ${barangaySelect(groups, v.barangay_id)}
          ${input('municipality', 'Municipality', { value: v.city, readonly: true, maxlength: 0, hint: 'Filled in from the barangay.' })}
          ${input('province', 'Province', { value: v.province, readonly: true, maxlength: 0 })}
          ${input('address', 'Complete address', { value: v.address, required: true, span: 'sm:col-span-3', placeholder: 'House no., street / purok' })}
          ${input('location_note', 'Location / landmark', { value: v.location_note, span: 'sm:col-span-3', placeholder: 'e.g. Beside the barangay hall, blue gate' })}
          ${input('nearby_school', 'Nearby school', { value: v.nearby_school, maxlength: 150, span: 'sm:col-span-2' })}
          ${input('distance_note', 'Distance from school', { value: v.distance_note, maxlength: 150, placeholder: 'e.g. 5 minutes walk from SIIT' })}
        </div>
        <div class="mt-5 grid gap-4 lg:grid-cols-2">
          <div class="space-y-4">
            ${input('map_url', 'Google Maps link', { value: v.map_url, type: 'url', maxlength: 500, placeholder: 'https://maps.app.goo.gl/… or https://www.google.com/maps/…',
              hint: 'Open Google Maps, find the boarding house, tap Share, copy the link and paste it here.' })}
            <div class="grid grid-cols-2 gap-3">
              ${input('latitude', 'Latitude', { value: v.latitude, type: 'number', maxlength: 0, extra: html`step="any" min="-90" max="90"` })}
              ${input('longitude', 'Longitude', { value: v.longitude, type: 'number', maxlength: 0, extra: html`step="any" min="-180" max="180"` })}
            </div>
            <div class="flex flex-wrap gap-2">
              <button type="button" class="btn btn-secondary btn-sm" data-use-location>${icon('pin')} Use my current location</button>
              <button type="button" class="btn btn-ghost btn-sm" data-clear-location>Clear map pin</button>
            </div>
            <p class="form-hint">Coordinates put the boarding house on the students' map and calculate the distance to SIIT. If the link contains coordinates they are filled in automatically.</p>
          </div>
          <div>
            <iframe class="map-frame" data-map title="Map preview" loading="lazy" src="${mapEmbedUrl(v.latitude, v.longitude)}"></iframe>
            <p class="mt-1 text-xs text-slate-400" data-map-caption>${Number.isFinite(v.latitude) ? 'Pin shows the saved location.' : 'No location yet - the map shows SIIT.'}</p>
          </div>
        </div>`)}

      ${section(4, 'Description and house rules', '', html`
        <div class="grid gap-4">
          <div>
            <label class="form-label" for="h-description">Description</label>
            <textarea id="h-description" name="description" rows="6" maxlength="5000" class="form-control" placeholder="What makes your boarding house a good place for students: security, quiet study areas, nearby stores and transport, what the rent includes…">${v.description || ''}</textarea>
            <p class="form-error hidden" data-error-for="description"></p>
          </div>
          <div>
            <label class="form-label" for="h-house_rules">House rules</label>
            <textarea id="h-house_rules" name="house_rules" rows="4" maxlength="5000" class="form-control" placeholder="e.g. Curfew 10 PM. No smoking. Visitors until 8 PM. Electricity is billed separately.">${v.house_rules || ''}</textarea>
          </div>
        </div>`)}

      ${section(5, 'Listing status', editing ? '' : 'You can change this anytime from My Boarding Houses.', html`
        <div class="grid gap-2 md:grid-cols-3">${AVAILABILITY_OPTIONS.map((o) => html`
          <label class="choice-card">
            <input type="radio" name="availability_status" value="${o.value}" ${v.availability_status === o.value ? 'checked' : ''} />
            <span><span class="block font-semibold text-ink">${o.label}</span><span class="block text-xs text-slate-500">${o.hint}</span></span>
          </label>`)}</div>
        ${!editing || house.status === 'pending' ? html`<p class="mt-3 text-xs text-slate-500">${icon('info', 'mr-1 inline h-3.5 w-3.5')}New listings may show <strong>Pending Approval</strong> until the administrator approves them.</p>` : ''}`)}

      ${editing ? section(6, 'Rooms', '', html`
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <p class="text-sm text-slate-600">This boarding house has <strong>${house.rooms.length}</strong> room${house.rooms.length === 1 ? '' : 's'}. Add, edit, delete rooms and upload room photos in Room Management.</p>
          <a href="#/rooms?house_id=${house.id}" class="btn btn-secondary shrink-0">${icon('bed')} Manage Rooms</a>
        </div>`) : section(6, 'Rooms', 'Add each kind of room you rent out. Use "How many rooms like this?" for identical rooms.', html`
        <div data-room-cards></div>
        <p class="form-error hidden" data-error-for="rooms"></p>
        <button type="button" class="btn btn-secondary mt-4 w-full sm:w-auto" data-add-room>${icon('plus')} Add another room</button>`, 'rooms')}

      <div class="save-bar flex flex-col-reverse gap-2 sm:flex-row sm:items-center sm:justify-end">
        <a href="#/houses" class="btn btn-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary" data-save>${icon('check')} ${editing ? 'Save changes' : 'Save boarding house'}</button>
      </div>
    </form>`);

  const form = $('#houseForm', container);
  const coverPicker = createImagePicker($('[data-cover-picker]', form), { multiple: false, title: editing ? 'Choose a new main photo' : 'Choose the main photo' });
  const morePicker = createImagePicker($('[data-more-picker]', form), { multiple: true, max: 15, title: 'Add more photos' });

  /* Barangay -> municipality and province */
  const barangay = form.elements.barangay_id;
  const syncBarangay = () => {
    const opt = barangay.selectedOptions[0];
    form.elements.municipality.value = opt?.dataset.municipality || '';
    form.elements.province.value = opt?.dataset.province || '';
  };
  barangay.addEventListener('change', syncBarangay);
  if (barangay.value) syncBarangay();

  /* Map preview */
  const mapFrame = $('[data-map]', form);
  const mapCaption = $('[data-map-caption]', form);
  const updateMap = () => {
    const lat = Number.parseFloat(form.elements.latitude.value);
    const lng = Number.parseFloat(form.elements.longitude.value);
    const ok = Number.isFinite(lat) && Number.isFinite(lng) && Math.abs(lat) <= 90 && Math.abs(lng) <= 180;
    mapFrame.src = mapEmbedUrl(ok ? lat : NaN, ok ? lng : NaN);
    mapCaption.textContent = ok ? `Pin at ${lat.toFixed(5)}, ${lng.toFixed(5)}` : 'No location yet - the map shows SIIT.';
  };
  let mapTimer;
  ['latitude', 'longitude'].forEach((name) => form.elements[name].addEventListener('input', () => { clearTimeout(mapTimer); mapTimer = setTimeout(updateMap, 500); }));
  form.elements.map_url.addEventListener('change', () => {
    const coords = coordsFromLink(form.elements.map_url.value);
    if (coords) {
      [form.elements.latitude.value, form.elements.longitude.value] = coords.map((n) => n.toFixed(7));
      updateMap();
      toast('Location found in the link and pinned on the map.', 'info');
    }
  });
  $('[data-use-location]', form).addEventListener('click', () => {
    if (!navigator.geolocation) { toast('Your browser cannot share its location.', 'error'); return; }
    toast('Getting your location…', 'info');
    navigator.geolocation.getCurrentPosition((pos) => {
      form.elements.latitude.value = pos.coords.latitude.toFixed(7);
      form.elements.longitude.value = pos.coords.longitude.toFixed(7);
      updateMap();
      toast('Location pinned. Make sure you are at the boarding house.');
    }, () => toast('Could not get your location. Allow location access or paste a Google Maps link.', 'error'), { enableHighAccuracy: true, timeout: 15000 });
  });
  $('[data-clear-location]', form).addEventListener('click', () => {
    form.elements.latitude.value = '';
    form.elements.longitude.value = '';
    updateMap();
  });

  /* Saved photos (edit) */
  const savedHolder = $('[data-saved-house-photos]', form);
  const reloadSavedPhotos = async () => {
    const fresh = await api.houses.get(house.id);
    house.images = fresh.images;
    setHtml(savedHolder, savedPhotos(house.images, { cover: true }));
  };
  savedHolder?.addEventListener('click', async (event) => {
    const coverBtn = event.target.closest('[data-cover-image]');
    const deleteBtn = event.target.closest('[data-delete-image]');
    try {
      if (coverBtn) {
        const res = await api.houses.setCover(Number(coverBtn.dataset.coverImage));
        toast(res.message || 'Main photo changed.');
        await reloadSavedPhotos();
      } else if (deleteBtn) {
        const ok = await confirmDialog({ title: 'Delete this photo?', message: 'The photo is removed from your listing permanently.', confirmText: 'Delete photo', danger: true, iconName: 'trash' });
        if (!ok) return;
        const res = await api.houses.removeImage(Number(deleteBtn.dataset.deleteImage));
        toast(res.message || 'Photo deleted.');
        await reloadSavedPhotos();
      }
    } catch (error) {
      toast(error.message, 'error');
    }
  });

  /* Room cards (add only) */
  const roomCards = [];
  const cardsHolder = $('[data-room-cards]', form);
  const renumber = () => roomCards.forEach((card, i) => {
    $('[data-room-title]', card.el).textContent = `Room ${i + 1}`;
    $('[data-remove-room]', card.el).classList.toggle('hidden', roomCards.length === 1);
  });
  const addRoomCard = async () => {
    const el = document.createElement('div');
    el.className = 'room-card fade-up';
    const key = `r${Date.now().toString(36)}${Math.random().toString(36).slice(2, 6)}`;
    setHtml(el, html`
      <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3 sm:px-5">
        <p class="font-semibold text-ink" data-room-title>Room</p>
        <button type="button" class="btn btn-ghost btn-sm text-rose-600" data-remove-room>${icon('trash')} Remove</button>
      </div>
      <div class="p-4 sm:p-5" data-room-body></div>`);
    cardsHolder.appendChild(el);
    const formApi = await mountRoomForm($('[data-room-body]', el), { idPrefix: key });
    const card = { el, form: formApi };
    roomCards.push(card);
    $('[data-remove-room]', el).addEventListener('click', async () => {
      const ok = await confirmDialog({ title: 'Remove this room?', message: 'The details you typed for this room will be cleared.', confirmText: 'Remove', danger: true, iconName: 'trash' });
      if (!ok) return;
      roomCards.splice(roomCards.indexOf(card), 1);
      el.remove();
      renumber();
    });
    renumber();
    return card;
  };
  if (!editing) {
    await addRoomCard();
    $('[data-add-room]', form).addEventListener('click', async () => {
      const card = await addRoomCard();
      card.el.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
  }

  /* Unsaved changes warning */
  let dirty = false;
  form.addEventListener('input', () => { dirty = true; });
  const beforeUnload = (e) => { if (dirty) { e.preventDefault(); e.returnValue = ''; } };
  window.addEventListener('beforeunload', beforeUnload);

  /* Validation */
  const fieldError = (name, message) => {
    const holder = $(`[data-error-for="${name}"]`, form);
    if (holder) { holder.textContent = message; holder.classList.remove('hidden'); }
    const el = form.elements[name];
    if (el?.classList) el.classList.add('is-invalid');
  };
  const clearAllErrors = () => {
    $$('[data-error-for]', form).forEach((el) => { el.textContent = ''; el.classList.add('hidden'); });
    $$('.is-invalid', form).forEach((el) => el.classList.remove('is-invalid'));
  };

  const readHouse = () => {
    const get = (n) => (form.elements[n]?.value ?? '').trim();
    return {
      name: get('name'),
      contact_name: get('contact_name'),
      contact_number: get('contact_number'),
      contact_email: get('contact_email'),
      barangay_id: get('barangay_id'),
      address: get('address'),
      location_note: get('location_note'),
      nearby_school: get('nearby_school'),
      distance_note: get('distance_note'),
      map_url: get('map_url'),
      latitude: get('latitude'),
      longitude: get('longitude'),
      description: get('description'),
      house_rules: get('house_rules'),
      availability_status: form.querySelector('[name="availability_status"]:checked')?.value || 'available',
    };
  };

  const validate = (data) => {
    const errors = {};
    if (!data.name) errors.name = 'Enter the boarding house name.';
    if (!data.contact_name) errors.contact_name = 'Enter the landlord or contact name.';
    if (!/^(09\d{9}|\+639\d{9})$/.test(data.contact_number.replace(/[\s-]/g, ''))) errors.contact_number = 'Enter a mobile number like 09171234567.';
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(data.contact_email)) errors.contact_email = 'Enter a valid email address.';
    if (!data.barangay_id) errors.barangay_id = 'Choose the barangay.';
    if (!data.address) errors.address = 'Enter the complete address.';
    if (data.map_url && !/^https?:\/\//i.test(data.map_url)) errors.map_url = 'Paste a full link that starts with https://';
    if ((data.latitude === '') !== (data.longitude === '')) errors[data.latitude === '' ? 'latitude' : 'longitude'] = 'Enter both latitude and longitude, or leave both empty.';
    if (!editing && !coverPicker.files().length) errors.cover = 'Choose a main photo of the boarding house.';
    return errors;
  };

  /* Save */
  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    clearAllErrors();
    const data = readHouse();
    const errors = validate(data);
    Object.entries(errors).forEach(([name, message]) => fieldError(name, message));

    let roomErrors = false;
    const rooms = roomCards.map((card) => {
      const result = card.form.read();
      if (card.form.showErrors(result.errors) || Object.keys(result.errors).length) roomErrors = true;
      return { ...result, files: card.form.files() };
    });
    if (!editing && !roomCards.length) {
      fieldError('rooms', 'Add at least one room.');
      roomErrors = true;
    }

    if (Object.keys(errors).length || roomErrors) {
      toast('Please check the highlighted fields.', 'error');
      const first = $('.is-invalid, [data-error-for]:not(.hidden)', form);
      first?.scrollIntoView({ behavior: 'smooth', block: 'center' });
      return;
    }

    const saveBtn = $('[data-save]', form);
    saveBtn.disabled = true;
    try {
      if (editing) await saveEdit(data);
      else await saveNew(data, rooms);
    } finally {
      saveBtn.disabled = false;
    }
  });

  async function uploadHousePhotos(houseId, progress, stepIndex) {
    const cover = coverPicker.files()[0];
    const more = morePicker.files();
    const total = (cover ? 1 : 0) + more.length;
    let done = 0;
    const failures = [];
    const tick = () => progress?.set(stepIndex, 'active', `Uploading photos (${done}/${total})`);
    tick();
    // One photo per request keeps each upload small (PHP limits the request size).
    if (cover) {
      try { await api.houses.uploadImages(houseId, { cover }); } catch (e) { failures.push(e.message); }
      done += 1; tick();
    }
    for (const file of more) {
      try { await api.houses.uploadImages(houseId, { images: [file] }); } catch (e) { failures.push(e.message); }
      done += 1; tick();
    }
    return failures;
  }

  async function saveEdit(data) {
    try {
      const res = await api.houses.update(house.id, data);
      const failures = await uploadHousePhotos(house.id);
      dirty = false;
      if (failures.length) toast(`Details saved, but some photos were not uploaded: ${failures.join(' ')}`, 'error');
      else toast(res.message || 'Boarding house updated.');
      window.dispatchEvent(new CustomEvent('bh:data-changed'));
      ctx.navigate('houses', { view: house.id });
    } catch (error) {
      if (error.errors) Object.entries(error.errors).forEach(([name, message]) => fieldError(name, message));
      toast(error.message, 'error');
    }
  }

  async function saveNew(data, rooms) {
    const roomCount = rooms.reduce((sum, r) => sum + Number(r.data.quantity || 1), 0);
    const progress = progressDialog(['Saving boarding house details', 'Uploading photos', `Adding ${roomCount} room${roomCount === 1 ? '' : 's'}`, 'Finishing']);
    let houseId = 0;
    try {
      progress.set(0, 'active');
      const created = await api.houses.create(data);
      houseId = created.data.id;
      progress.set(0, 'done');
      dirty = false;

      const photoFailures = await uploadHousePhotos(houseId, progress, 1);
      progress.set(1, photoFailures.length ? 'error' : 'done', photoFailures.length ? `Photos: ${photoFailures.length} not uploaded` : 'Photos uploaded');

      const roomFailures = [];
      for (const [i, room] of rooms.entries()) {
        progress.set(2, 'active', `Adding rooms (${i + 1}/${rooms.length})`);
        try {
          const res = await api.rooms.create({ ...room.data, house_id: houseId });
          for (const roomId of res.data.ids) {
            for (const file of room.files) {
              try { await api.rooms.uploadImages(roomId, [file]); } catch (e) { roomFailures.push(e.message); }
            }
          }
        } catch (error) {
          roomFailures.push(`${room.data.room_number}: ${error.message}`);
        }
      }
      progress.set(2, roomFailures.length ? 'error' : 'done', roomFailures.length ? 'Some rooms need attention' : 'Rooms added');
      progress.set(3, 'done', 'Saved to the database');

      setTimeout(() => progress.close(), 600);
      window.dispatchEvent(new CustomEvent('bh:data-changed'));
      const problems = [...photoFailures, ...roomFailures];
      if (problems.length) {
        toast(`The boarding house was saved, but: ${problems.join(' ')} Fix it in Room Management.`, 'error');
        ctx.navigate('rooms', { house_id: houseId });
      } else {
        toast(created.message);
        ctx.navigate('houses', { view: houseId });
      }
    } catch (error) {
      progress.set(houseId ? 3 : 0, 'error', error.message);
      setTimeout(() => progress.close(), 1200);
      if (!houseId && error.errors) Object.entries(error.errors).forEach(([name, message]) => fieldError(name, message));
      toast(error.message, 'error');
      if (houseId) ctx.navigate('edit-house', { id: houseId });
    }
  }

  return () => window.removeEventListener('beforeunload', beforeUnload);
}


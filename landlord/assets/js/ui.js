/* ==========================================================================
   Landlord UI helpers. Re-exports the admin dashboard toolkit (safe html
   templates, modals, toasts, tables, forms) and adds what the landlord pages
   need: photo pickers with previews, listing status badges, room labels.
   ========================================================================== */

import { html, setHtml, icon, badge, toast, $ } from '../../../admin/assets/js/ui.js';

export * from '../../../admin/assets/js/ui.js';

/** Turns a stored path ("uploads/listings/x.jpg") into a URL usable from /landlord/. */
export const assetUrl = (path) => (!path ? '' : /^https?:\/\//i.test(path) ? path : `../${String(path).split('/').map(encodeURIComponent).join('/')}`);

/** The status a landlord sees: Pending Approval / Available / Fully Occupied / Temporarily Unavailable / Rejected. */
export const listingBadge = (status) => (status ? badge(status.key, status.label) : '');

export const AVAILABILITY_OPTIONS = [
  { value: 'available', label: 'Available', hint: 'Open for reservations. Students can reserve rooms with free slots.' },
  { value: 'fully_occupied', label: 'Fully Occupied', hint: 'Still shown to students, marked FULL. No new reservations.' },
  { value: 'temporarily_unavailable', label: 'Temporarily Unavailable', hint: 'Hidden from students until you set it back to Available.' },
];

export const ROOM_TYPE_OPTIONS = [
  { value: 'single', label: 'Single Room', hint: 'One tenant. Monthly price for the whole room.' },
  { value: 'shared', label: 'Shared Room', hint: 'Several tenants. Price is per person per month.' },
];

/** "single" / "shared" for the form, from the database room type. */
export const formRoomType = (roomType) => (roomType === 'solo' || roomType === 'studio' ? 'single' : 'shared');

/* ---------------------------------------------------------------------- *
 * Photo validation and pickers
 * ---------------------------------------------------------------------- */
export const IMAGE_RULES = {
  types: ['image/jpeg', 'image/png', 'image/webp'],
  extensions: ['jpg', 'jpeg', 'png', 'webp'],
  maxBytes: 5 * 1024 * 1024,
  accept: '.jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp',
};

/** Returns an error message, or '' when the file is a usable photo. */
export function imageError(file) {
  const ext = String(file.name).split('.').pop().toLowerCase();
  if (!IMAGE_RULES.extensions.includes(ext) || (file.type && !IMAGE_RULES.types.includes(file.type))) {
    return `${file.name}: only JPG, JPEG, PNG or WebP photos are allowed.`;
  }
  if (file.size > IMAGE_RULES.maxBytes) return `${file.name}: the photo is larger than 5 MB.`;
  if (file.size === 0) return `${file.name}: the file is empty.`;
  return '';
}

const readAsDataUrl = (file) => new Promise((resolve, reject) => {
  const reader = new FileReader();
  reader.onload = () => resolve(reader.result);
  reader.onerror = () => reject(reader.error);
  reader.readAsDataURL(file);
});

/**
 * A drop zone with live previews. Invalid files are refused with a toast.
 *  - multiple: allow several photos; max: maximum number of new photos
 *  - title / hint: text in the drop zone
 * Returns { files(): File[], clear(), el }.
 */
export function createImagePicker(holder, { multiple = true, max = 10, title = 'Add photos', hint = '', onChange } = {}) {
  let files = [];
  const inputId = `pick-${Math.random().toString(36).slice(2)}`;

  setHtml(holder, html`
    <div class="photo-picker">
      <label for="${inputId}" class="photo-drop" tabindex="0" role="button">
        <span class="grid h-11 w-11 place-items-center rounded-2xl bg-primary-50 text-primary">${icon('upload', 'h-5 w-5')}</span>
        <span class="mt-2 text-sm font-semibold text-ink">${title}</span>
        <span class="mt-0.5 text-xs text-slate-500">${hint || `JPG, PNG or WebP · up to 5 MB each${multiple ? ` · up to ${max} photos` : ''}`}</span>
        <span class="mt-0.5 text-xs text-slate-400">Click to choose, or drag photos here</span>
      </label>
      <input id="${inputId}" type="file" class="sr-only" accept="${IMAGE_RULES.accept}" ${multiple ? 'multiple' : ''} />
      <div class="photo-grid mt-3" data-previews></div>
    </div>`);

  const input = $('input[type=file]', holder);
  const drop = $('.photo-drop', holder);
  const previews = $('[data-previews]', holder);

  async function renderPreviews() {
    const urls = await Promise.all(files.map(readAsDataUrl));
    setHtml(previews, files.map((file, i) => html`
      <figure class="photo-thumb">
        <img src="${urls[i]}" alt="Preview of ${file.name}" />
        <button type="button" class="photo-remove" data-remove="${i}" aria-label="Remove ${file.name}">${icon('x', 'h-3.5 w-3.5')}</button>
        <figcaption>${file.name}</figcaption>
      </figure>`));
    onChange?.(files);
  }

  function add(list) {
    const errors = [];
    const incoming = [...list].filter((file) => {
      const error = imageError(file);
      if (error) errors.push(error);
      return !error;
    });
    if (errors.length) toast(errors.join(' '), 'error');
    if (!incoming.length) return;
    if (!multiple) {
      files = [incoming[0]];
    } else {
      const room = max - files.length;
      if (incoming.length > room) toast(`You can add up to ${max} photos here.`, 'error');
      files = files.concat(incoming.slice(0, Math.max(0, room)));
    }
    renderPreviews();
  }

  input.addEventListener('change', () => { add(input.files); input.value = ''; });
  drop.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); input.click(); } });
  ['dragenter', 'dragover'].forEach((type) => drop.addEventListener(type, (e) => { e.preventDefault(); drop.classList.add('is-dragging'); }));
  ['dragleave', 'drop'].forEach((type) => drop.addEventListener(type, (e) => { e.preventDefault(); drop.classList.remove('is-dragging'); }));
  drop.addEventListener('drop', (e) => add(e.dataTransfer.files));
  previews.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-remove]');
    if (!btn) return;
    files.splice(Number(btn.dataset.remove), 1);
    renderPreviews();
  });

  return {
    el: holder,
    files: () => [...files],
    clear: () => { files = []; renderPreviews(); },
  };
}

/** Grid of photos already saved, with optional "cover" and delete buttons. */
export function savedPhotos(images, { cover = false } = {}) {
  if (!images.length) return html`<p class="text-sm text-slate-400">No photos uploaded yet.</p>`;
  return html`<div class="photo-grid">${images.map((img) => html`
    <figure class="photo-thumb">
      <img src="${assetUrl(img.file_path)}" alt="" loading="lazy" />
      ${img.is_cover ? html`<span class="photo-cover-tag">Main photo</span>` : ''}
      <div class="photo-actions">
        ${cover && !img.is_cover ? html`<button type="button" class="photo-action" data-cover-image="${img.id}">Set as main</button>` : ''}
        <button type="button" class="photo-action text-rose-600" data-delete-image="${img.id}">Delete</button>
      </div>
    </figure>`)}</div>`;
}

/** Small summary helper for prices. */
export const priceRange = (min, max) => {
  const fmt = (v) => `₱${Number(v).toLocaleString('en-PH', { maximumFractionDigits: 0 })}`;
  if (min === null || min === undefined) return '—';
  return min === max || max === null || max === undefined ? fmt(min) : `${fmt(min)} – ${fmt(max)}`;
};

export const thumb = (path, label = '') => (path
  ? html`<img src="${assetUrl(path)}" alt="${label}" class="h-14 w-20 shrink-0 rounded-xl object-cover" loading="lazy" />`
  : html`<span class="grid h-14 w-20 shrink-0 place-items-center rounded-xl bg-slate-100 text-slate-400">${icon('image', 'h-5 w-5')}</span>`);


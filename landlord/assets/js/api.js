/* ==========================================================================
   Landlord API client - the only file that talks to /api/landlord/.

   The server checks ownership on every call, so a landlord can only ever
   read or change their own boarding houses, rooms, reservations and messages.
   ========================================================================== */

const API_BASE = '../api/landlord/';
const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

export class ApiError extends Error {
  constructor(message, status, errors = {}) {
    super(message);
    this.status = status;
    this.errors = errors;
  }
}

function buildUrl(endpoint, query = {}) {
  const url = new URL(API_BASE + endpoint, window.location.href);
  Object.entries(query).forEach(([key, value]) => {
    if (value !== undefined && value !== null && value !== '') url.searchParams.set(key, value);
  });
  return url;
}

/**
 * @param {string} method  GET | POST | PUT | DELETE
 * @param {string} endpoint e.g. "rooms.php"
 * @param {{query?: object, body?: object|FormData}} options
 */
export async function request(method, endpoint, { query, body } = {}) {
  const headers = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
  if (method !== 'GET') headers['X-CSRF-Token'] = csrfToken();

  let payload;
  if (body instanceof FormData) {
    payload = body;
  } else if (body !== undefined) {
    headers['Content-Type'] = 'application/json';
    payload = JSON.stringify(body);
  }

  let response;
  try {
    response = await fetch(buildUrl(endpoint, query), { method, headers, body: payload, credentials: 'same-origin' });
  } catch {
    throw new ApiError('Cannot reach the server. Check that the website server is running.', 0);
  }

  if (response.status === 413) {
    throw new ApiError('The photos are too large to send at once. Use smaller photos (5 MB or less each).', 413);
  }

  let json = null;
  try {
    json = await response.json();
  } catch {
    throw new ApiError(`The server returned an unexpected response (HTTP ${response.status}).`, response.status);
  }

  if (response.status === 401) {
    window.location.href = '../html/loginform.html';
    throw new ApiError(json?.message || 'Please log in again.', 401);
  }

  if (!response.ok || !json?.success) {
    throw new ApiError(json?.message || 'Request failed.', response.status, json?.errors || {});
  }

  return json;
}

const filesForm = (fields) => {
  const form = new FormData();
  Object.entries(fields).forEach(([name, files]) => [].concat(files || []).forEach((file) => form.append(name, file)));
  return form;
};

export const api = {
  stats: () => request('GET', 'stats.php').then((r) => r.data),
  options: (type) => request('GET', 'options.php', { query: { type } }).then((r) => r.data),
  addAmenity: (name) => request('POST', 'options.php', { query: { type: 'amenities' }, body: { name } }),

  houses: {
    list: (query = {}) => request('GET', 'boarding-houses.php', { query }),
    get: (id) => request('GET', 'boarding-houses.php', { query: { id } }).then((r) => r.data),
    create: (body) => request('POST', 'boarding-houses.php', { body }),
    update: (id, body) => request('PUT', 'boarding-houses.php', { query: { id }, body }),
    setStatus: (id, availability_status) => request('PUT', 'boarding-houses.php', { query: { id, action: 'status' }, body: { availability_status } }),
    remove: (id) => request('DELETE', 'boarding-houses.php', { query: { id } }),
    /** cover: File|null, images: File[] */
    uploadImages: (id, { cover = null, images = [] }) => request('POST', 'boarding-houses.php', {
      query: { id, action: 'images' },
      body: filesForm({ cover: cover ? [cover] : [], 'images[]': images }),
    }),
    setCover: (imageId) => request('PUT', 'boarding-houses.php', { query: { image_id: imageId, action: 'cover' }, body: {} }),
    removeImage: (imageId) => request('DELETE', 'boarding-houses.php', { query: { image_id: imageId } }),
  },

  rooms: {
    list: (query = {}) => request('GET', 'rooms.php', { query }),
    get: (id) => request('GET', 'rooms.php', { query: { id } }).then((r) => r.data),
    create: (body) => request('POST', 'rooms.php', { body }),
    update: (id, body) => request('PUT', 'rooms.php', { query: { id }, body }),
    setAvailability: (id, availability) => request('PUT', 'rooms.php', { query: { id, action: 'availability' }, body: { availability } }),
    remove: (id) => request('DELETE', 'rooms.php', { query: { id } }),
    uploadImages: (id, images) => request('POST', 'rooms.php', { query: { id, action: 'images' }, body: filesForm({ 'images[]': images }) }),
    removeImage: (imageId) => request('DELETE', 'rooms.php', { query: { image_id: imageId } }),
  },

  reservations: {
    list: (query = {}) => request('GET', 'reservations.php', { query }),
    get: (id) => request('GET', 'reservations.php', { query: { id } }).then((r) => r.data),
    update: (id, body) => request('PUT', 'reservations.php', { query: { id }, body }),
  },

  messages: {
    list: (query = {}) => request('GET', 'messages.php', { query }),
    get: (id) => request('GET', 'messages.php', { query: { id } }).then((r) => r.data),
    reply: (id, reply) => request('PUT', 'messages.php', { query: { id }, body: { reply } }),
    close: (id) => request('PUT', 'messages.php', { query: { id, action: 'close' }, body: {} }),
  },

  tenants: {
    list: (query = {}) => request('GET', 'tenants.php', { query }),
    get: (id) => request('GET', 'tenants.php', { query: { id } }).then((r) => r.data),
  },

  payments: {
    list: (query = {}) => request('GET', 'payments.php', { query }),
    save: (body) => request('POST', 'payments.php', { body }),
    remove: (id) => request('DELETE', 'payments.php', { query: { id } }),
  },

  chat: {
    list: () => request('GET', 'chat.php'),
    contacts: () => request('GET', 'chat.php', { query: { type: 'contacts' } }).then((r) => r.data),
    messages: (conversationId, after = 0) => request('GET', 'chat.php', { query: { conversation_id: conversationId, after } }),
    send: (body) => request('POST', 'chat.php', { body }),
  },

  profile: {
    get: () => request('GET', 'profile.php').then((r) => r.data),
    save: (body) => request('PUT', 'profile.php', { body }),
    uploadPhoto: (file) => request('POST', 'profile.php', { query: { action: 'photo' }, body: filesForm({ photo: [file] }) }),
    changePassword: (body) => request('PUT', 'profile.php', { query: { action: 'password' }, body }),
  },

  notifications: {
    list: (query = {}) => request('GET', 'notifications.php', { query }),
    markRead: (id) => request('PUT', 'notifications.php', { query: { id }, body: {} }),
    markAllRead: () => request('PUT', 'notifications.php', { query: { action: 'read_all' }, body: {} }),
  },
};

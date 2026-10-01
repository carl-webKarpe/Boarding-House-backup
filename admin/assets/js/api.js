/* ==========================================================================
   API client - the only file that talks to the backend.

   Every page calls these functions instead of using fetch() directly, so the
   data source can change (different URL, auth tokens, a new backend) by
   editing this one file.
   ========================================================================== */

const API_BASE = '../api/admin/';
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
 * @param {string} endpoint e.g. "users.php"
 * @param {{query?: object, body?: object|FormData}} options
 * @returns {Promise<{data: any, message: string, meta?: object, counts?: object}>}
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
    throw new ApiError('Cannot reach the server. Check that Apache (XAMPP) is running.', 0);
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

/** Standard CRUD helpers for a REST resource file. */
function resource(endpoint) {
  return {
    list: (query = {}) => request('GET', endpoint, { query }),
    get: (id) => request('GET', endpoint, { query: { id } }).then((r) => r.data),
    create: (body) => request('POST', endpoint, { body }),
    update: (id, body) => request('PUT', endpoint, { query: { id }, body }),
    remove: (id) => request('DELETE', endpoint, { query: { id } }),
  };
}

export const api = {
  stats: (months = 6) => request('GET', 'stats.php', { query: { months } }).then((r) => r.data),
  reports: (months = 6) => request('GET', 'reports.php', { query: { months } }).then((r) => r.data),
  search: (q) => request('GET', 'search.php', { query: { q } }).then((r) => r.data),
  options: (type, q = '') => request('GET', 'options.php', { query: { type, q } }).then((r) => r.data),
  documentUrl: (id) => buildUrl('documents.php', { id }).toString(),

  users: resource('users.php'),
  boardingHouses: {
    ...resource('boarding-houses.php'),
    uploadImages: (id, files) => {
      const form = new FormData();
      [...files].forEach((file) => form.append('images[]', file));
      return request('POST', 'boarding-houses.php', { query: { id, action: 'images' }, body: form });
    },
    removeImage: (imageId) => request('DELETE', 'boarding-houses.php', { query: { image_id: imageId } }),
  },
  rooms: resource('rooms.php'),
  amenities: resource('amenities.php'),
  bookings: resource('bookings.php'),
  activity: { list: (query = {}) => request('GET', 'activity.php', { query }) },

  notifications: {
    list: (query = {}) => request('GET', 'notifications.php', { query }),
    markRead: (id, isRead = true) => request('PUT', 'notifications.php', { query: { id }, body: { is_read: isRead } }),
    markAllRead: () => request('PUT', 'notifications.php', { query: { action: 'read_all' }, body: {} }),
    remove: (id) => request('DELETE', 'notifications.php', { query: { id } }),
    clearRead: () => request('DELETE', 'notifications.php', { query: { action: 'clear_read' } }),
  },

  settings: {
    get: () => request('GET', 'settings.php').then((r) => r.data),
    save: (body) => request('PUT', 'settings.php', { body }),
  },

  profile: {
    get: () => request('GET', 'profile.php').then((r) => r.data),
    save: (body) => request('PUT', 'profile.php', { body }),
    changePassword: (body) => request('PUT', 'profile.php', { query: { action: 'password' }, body }),
    savePreferences: (body) => request('PUT', 'profile.php', { query: { action: 'preferences' }, body }),
  },
};

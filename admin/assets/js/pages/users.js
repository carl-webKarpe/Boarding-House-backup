/* Users & Landlords management. The "landlords" route reuses this page with
   landlord-specific columns (business, listings, verification). */

import { api } from '../api.js';
import { html, icon, avatar, badge, fmtDate, fmtDateTime, timeAgo, num, toast, confirmDialog, openModal, formModal, details, $, titleCase } from '../ui.js';
import { createListPage, actionButton } from './_list.js';

const STATUS_OPTIONS = [
  { value: 'active', label: 'Active' },
  { value: 'pending', label: 'Pending' },
  { value: 'disabled', label: 'Disabled' },
];
const ROLE_OPTIONS = [
  { value: 'tenant', label: 'Tenant / Student' },
  { value: 'landlord', label: 'Landlord' },
  { value: 'admin', label: 'Administrator' },
  { value: 'super_admin', label: 'Super Admin' },
];
const VERIFICATION_OPTIONS = [
  { value: 'pending', label: 'Pending verification' },
  { value: 'verified', label: 'Verified' },
  { value: 'rejected', label: 'Rejected' },
  { value: 'unverified', label: 'Unverified' },
];

const userCell = (u) => html`
  <div class="flex min-w-0 items-center gap-3">
    ${avatar(u.full_name)}
    <div class="min-w-0">
      <p class="truncate font-semibold text-ink">${u.full_name}</p>
      <p class="truncate text-xs text-slate-500">${u.email}</p>
    </div>
  </div>`;

function userFields(isLandlordPage, creating) {
  return [
    { name: 'first_name', label: 'First name', required: true, maxlength: 80 },
    { name: 'last_name', label: 'Last name', required: true, maxlength: 80 },
    { name: 'middle_name', label: 'Middle name', maxlength: 80 },
    { name: 'username', label: 'Username', required: true, hint: '5–20 letters, numbers or underscores', maxlength: 20, autocomplete: 'off' },
    { name: 'email', label: 'Email (Gmail)', type: 'email', required: true, maxlength: 255 },
    { name: 'contact_number', label: 'Contact number', type: 'tel', placeholder: '09XXXXXXXXX', maxlength: 13 },
    { name: 'address', label: 'Address', span: 2, maxlength: 255 },
    isLandlordPage
      ? { name: 'business_name', label: 'Boarding house / business name', maxlength: 150 }
      : { name: 'role', label: 'Role', type: 'select', options: ROLE_OPTIONS, required: true },
    { name: 'status', label: 'Account status', type: 'select', options: STATUS_OPTIONS, required: true },
    isLandlordPage ? { name: 'verification_status', label: 'Verification', type: 'select', options: VERIFICATION_OPTIONS } : null,
    {
      name: 'password',
      label: creating ? 'Password' : 'New password',
      type: 'password',
      required: creating,
      autocomplete: 'new-password',
      hint: creating ? 'At least 8 characters with upper & lower case, a number and a symbol.' : 'Leave blank to keep the current password.',
    },
  ].filter(Boolean);
}

export async function render(view, ctx) {
  const isLandlordPage = ctx.path === 'landlords';

  const openForm = (user, list) => {
    const creating = !user;
    formModal({
      title: creating ? (isLandlordPage ? 'Add landlord' : 'Add user') : `Edit ${user.full_name}`,
      subtitle: creating ? 'Create an account on behalf of a user.' : `${user.role_label} · registered ${fmtDate(user.created_at)}`,
      fields: userFields(isLandlordPage, creating),
      values: creating ? { role: 'tenant', status: 'active', verification_status: 'verified' } : user,
      size: 'lg',
      submitText: creating ? 'Create account' : 'Save changes',
      onSubmit: async (data) => {
        if (isLandlordPage) data.role = 'landlord';
        if (!creating && !data.password) delete data.password;
        const res = creating ? await api.users.create(data) : await api.users.update(user.id, data);
        toast(res.message);
        list.changed();
      },
    });
  };

  const setStatus = async (user, status, list) => {
    const disabling = status === 'disabled';
    const ok = await confirmDialog({
      title: disabling ? 'Disable account?' : 'Enable account?',
      message: disabling
        ? `${user.full_name} will no longer be able to log in. Their records stay in the system and you can enable the account again at any time.`
        : `${user.full_name} will be able to log in again.`,
      confirmText: disabling ? 'Disable account' : 'Enable account',
      danger: disabling,
      iconName: disabling ? 'ban' : 'unlock',
    });
    if (!ok) return;
    const res = await api.users.update(user.id, { status });
    toast(res.message);
    list.changed();
  };

  const setVerification = async (user, verification, list, modal) => {
    const verifying = verification === 'verified';
    const ok = await confirmDialog({
      title: verifying ? 'Verify landlord?' : 'Reject verification?',
      message: verifying
        ? `Confirm that you reviewed ${user.full_name}'s documents. The landlord account will be activated and verified.`
        : `${user.full_name} will be marked as not verified and the account set to pending.`,
      confirmText: verifying ? 'Verify landlord' : 'Reject',
      danger: !verifying,
      iconName: verifying ? 'shield' : 'x',
    });
    if (!ok) return;
    const res = await api.users.update(user.id, { verification_status: verification, status: verifying ? 'active' : 'pending' });
    toast(res.message);
    modal?.close();
    list.changed();
  };

  const remove = async (user, list) => {
    const ok = await confirmDialog({
      title: 'Delete account?',
      message: `This permanently deletes ${user.full_name} (${user.email})${user.house_count ? ` and their ${user.house_count} boarding house listing(s)` : ''}. Accounts with booking history cannot be deleted — disable them instead.`,
      confirmText: 'Delete permanently',
      danger: true,
      iconName: 'trash',
    });
    if (!ok) return;
    const res = await api.users.remove(user.id);
    toast(res.message);
    list.changed();
  };

  const showDetails = async (id, list) => {
    const user = await api.users.get(id);
    const isLandlord = user.role === 'landlord';
    const modal = openModal({
      title: user.full_name,
      subtitle: `${user.role_label} · @${user.username}`,
      size: 'lg',
      body: html`
        <div class="mb-5 flex flex-wrap items-center gap-2">
          ${badge(user.status)} ${badge(user.role, user.role_label)}
          ${isLandlord ? badge(user.verification_status, `${titleCase(user.verification_status)}${user.verification_status === 'pending' ? ' verification' : ''}`) : ''}
        </div>
        ${details([
          ['Email', user.email],
          ['Contact number', user.contact_number],
          ['Address', user.address],
          ['Gender', user.gender ? titleCase(user.gender) : ''],
          ['Date of birth', user.birth_date ? fmtDate(user.birth_date) : ''],
          isLandlord ? ['Business name', user.business_name] : null,
          ['Registered', fmtDateTime(user.created_at)],
          ['Last login', user.last_login_at ? `${fmtDateTime(user.last_login_at)} (${timeAgo(user.last_login_at)})` : 'Never'],
        ])}

        <h3 class="mt-6 text-sm font-semibold text-ink">Verification documents</h3>
        ${user.documents.length ? html`
          <ul class="mt-2 grid gap-2 sm:grid-cols-2">${user.documents.map((d) => html`
            <li><a href="${api.documentUrl(d.id)}" target="_blank" rel="noopener" class="flex items-center gap-3 rounded-xl border border-slate-200 px-3 py-2 hover:border-primary hover:bg-primary-50">
              ${icon('file', 'h-5 w-5 text-slate-400')}
              <span class="min-w-0"><span class="block truncate text-sm font-medium">${titleCase(d.doc_type)}</span><span class="block truncate text-xs text-slate-500">${d.original_name}</span></span>
            </a></li>`)}</ul>`
          : html`<p class="mt-2 text-sm text-slate-400">No documents uploaded.</p>`}

        ${isLandlord ? html`
          <h3 class="mt-6 text-sm font-semibold text-ink">Boarding houses (${user.boarding_houses.length})</h3>
          ${user.boarding_houses.length ? html`<ul class="mt-2 divide-y divide-slate-100 rounded-xl border border-slate-200">${user.boarding_houses.map((h) => html`
            <li><a href="#/boarding-houses?view=${h.id}" class="flex items-center justify-between gap-3 px-3 py-2.5 text-sm hover:bg-slate-50" data-close>
              <span class="min-w-0"><span class="block truncate font-medium">${h.name}</span><span class="text-xs text-slate-500">${h.city} · ${h.available_rooms}/${h.room_count} rooms available</span></span>${badge(h.status)}
            </a></li>`)}</ul>` : html`<p class="mt-2 text-sm text-slate-400">No listings yet.</p>`}` : ''}

        ${user.role === 'tenant' ? html`
          <h3 class="mt-6 text-sm font-semibold text-ink">Recent bookings</h3>
          ${user.bookings.length ? html`<ul class="mt-2 divide-y divide-slate-100 rounded-xl border border-slate-200">${user.bookings.map((b) => html`
            <li><a href="#/bookings?view=${b.id}" class="flex items-center justify-between gap-3 px-3 py-2.5 text-sm hover:bg-slate-50" data-close>
              <span class="min-w-0"><span class="block truncate font-medium">${b.boarding_house} · Room ${b.room_number}</span><span class="text-xs text-slate-500">${fmtDate(b.booking_date)}</span></span>${badge(b.status)}
            </a></li>`)}</ul>` : html`<p class="mt-2 text-sm text-slate-400">No bookings yet.</p>`}` : ''}`,
      footer: html`
        ${isLandlord && user.verification_status !== 'verified' ? html`<button type="button" class="btn btn-primary" data-verify>${icon('shield')} Verify landlord</button>` : ''}
        ${isLandlord && user.verification_status === 'pending' ? html`<button type="button" class="btn btn-secondary" data-reject>Reject</button>` : ''}
        <button type="button" class="btn btn-secondary" data-edit>${icon('edit')} Edit</button>
        <button type="button" class="btn btn-secondary" data-close>Close</button>`,
    });
    $('[data-edit]', modal.el).addEventListener('click', () => { modal.close(); openForm(user, list); });
    $('[data-verify]', modal.el)?.addEventListener('click', () => setVerification(user, 'verified', list, modal));
    $('[data-reject]', modal.el)?.addEventListener('click', () => setVerification(user, 'rejected', list, modal));
  };

  const columns = () => {
    const cols = [
      { key: 'name', label: isLandlordPage ? 'Landlord' : 'User', sort: 'name', primary: true, render: userCell },
    ];
    if (isLandlordPage) {
      cols.push(
        { key: 'business_name', label: 'Business', render: (u) => u.business_name || '—' },
        { key: 'house_count', label: 'Boarding houses', className: 'num', render: (u) => num(u.house_count) },
        { key: 'verification_status', label: 'Verification', render: (u) => badge(u.verification_status) },
      );
    } else {
      cols.push({ key: 'role', label: 'Role', sort: 'role', render: (u) => badge(u.role, u.role_label) });
    }
    cols.push(
      { key: 'contact_number', label: 'Contact', render: (u) => u.contact_number || '—' },
      { key: 'status', label: 'Status', sort: 'status', render: (u) => badge(u.status) },
      { key: 'created_at', label: 'Registered', sort: 'created_at', render: (u) => html`<span title="${fmtDateTime(u.created_at)}">${fmtDate(u.created_at)}</span>` },
    );
    return cols;
  };

  const list = createListPage(view, ctx, {
    title: isLandlordPage ? 'Landlords' : 'Users',
    description: isLandlordPage
      ? 'Verify landlord accounts and see how many boarding houses each one manages.'
      : 'Manage tenant, landlord and administrator accounts.',
    headerActions: html`<button type="button" class="btn btn-primary" data-add>${icon('plus')} ${isLandlordPage ? 'Add landlord' : 'Add user'}</button>`,
    tabKey: isLandlordPage ? 'verification' : 'role',
    tabs: (counts) => (isLandlordPage
      ? [{ value: '', label: 'All landlords' }, { value: 'pending', label: 'Pending verification' }, { value: 'verified', label: 'Verified' }, { value: 'rejected', label: 'Rejected' }]
      : [
        { value: '', label: 'All users', count: counts.all_users },
        { value: 'tenant', label: 'Tenants', count: counts.tenant },
        { value: 'landlord', label: 'Landlords', count: counts.landlord },
        { value: 'admin', label: 'Administrators', count: counts.admin },
      ]),
    filters: [{ name: 'status', label: 'Status', options: STATUS_OPTIONS }],
    fixed: isLandlordPage ? { role: 'landlord' } : {},
    defaults: { sort: 'created_at', order: 'desc' },
    searchPlaceholder: isLandlordPage ? 'Search by name, email, phone or business…' : 'Search by name, email, username or phone…',
    fetch: (query) => api.users.list(query),
    columns,
    actions: (u) => html`
      ${actionButton('view', u.id, 'eye', 'View details')}
      ${actionButton('edit', u.id, 'edit', 'Edit')}
      ${u.status === 'disabled'
        ? actionButton('enable', u.id, 'unlock', 'Enable account', 'success')
        : actionButton('disable', u.id, 'ban', 'Disable account', 'danger')}
      ${actionButton('delete', u.id, 'trash', 'Delete', 'danger')}`,
    onAction: (action, id, row, listRef) => ({
      view: () => showDetails(id, listRef),
      edit: () => openForm(row, listRef),
      disable: () => setStatus(row, 'disabled', listRef),
      enable: () => setStatus(row, 'active', listRef),
      delete: () => remove(row, listRef),
    })[action]?.(),
    emptyState: (state) => html`<div class="py-14 text-center text-sm text-slate-500">${state.q ? html`No accounts match “${state.q}”.` : 'No accounts found for this filter.'}</div>`,
    onReady: (listRef) => {
      const viewId = Number(ctx.params.get('view'));
      if (viewId) showDetails(viewId, listRef).catch((e) => toast(e.message, 'error'));
    },
  });

  view.querySelector('[data-add]').addEventListener('click', () => openForm(null, list));
}

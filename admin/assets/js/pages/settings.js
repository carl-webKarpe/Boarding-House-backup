/* Settings: admin profile, password, system settings, notification preferences. */

import { api } from '../api.js';
import { html, setHtml, icon, $, $$, toast, pageHeader, formFields, readForm, showErrors, clearErrors, withLoading, errorState, fmtDateTime, avatar } from '../ui.js';

const TABS = [
  { value: 'profile', label: 'Admin profile', icon: 'users' },
  { value: 'security', label: 'Password', icon: 'key' },
  { value: 'system', label: 'System settings', icon: 'settings' },
  { value: 'notifications', label: 'Notification preferences', icon: 'bell' },
];

function toggleRow(name, label, description, checked) {
  return html`
    <label class="flex cursor-pointer items-start justify-between gap-4 py-4">
      <span><span class="block text-sm font-semibold text-ink">${label}</span><span class="mt-0.5 block text-sm text-slate-500">${description}</span></span>
      <span class="switch mt-1"><input type="checkbox" name="${name}" ${checked ? 'checked' : ''} /><span></span></span>
    </label>`;
}

function bindForm(form, submit) {
  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    clearErrors(form);
    const button = form.querySelector('[type=submit]');
    try {
      await withLoading(button, () => submit(readForm(form), form));
    } catch (error) {
      if (error.errors) showErrors(form, error.errors);
      toast(error.message, 'error');
    }
  });
}

export async function render(view, ctx) {
  const container = document.createElement('div');
  view.replaceChildren(container);
  let active = TABS.some((t) => t.value === ctx.params.get('tab')) ? ctx.params.get('tab') : 'profile';

  let profile;
  let settings;
  try {
    [profile, settings] = await Promise.all([api.profile.get(), api.settings.get()]);
  } catch (error) {
    setHtml(container, errorState(error.message));
    $('[data-retry]', container).addEventListener('click', () => render(view, ctx));
    return;
  }

  const panels = {
    profile: () => html`
      <div class="mb-6 flex items-center gap-4">
        ${avatar(profile.full_name, 'h-14 w-14 text-base')}
        <div>
          <p class="font-semibold text-ink">${profile.full_name}</p>
          <p class="text-sm text-slate-500">${profile.role_label} · @${profile.username}</p>
          <p class="text-xs text-slate-400">Last login ${fmtDateTime(profile.last_login_at)}</p>
        </div>
      </div>
      <form data-form="profile" novalidate>
        ${formFields([
          { name: 'first_name', label: 'First name', required: true, maxlength: 80 },
          { name: 'last_name', label: 'Last name', required: true, maxlength: 80 },
          { name: 'email', label: 'Email', type: 'email', required: true },
          { name: 'contact_number', label: 'Contact number', type: 'tel', placeholder: '09XXXXXXXXX' },
        ], profile)}
        <div class="mt-6 flex justify-end"><button type="submit" class="btn btn-primary">Save profile</button></div>
      </form>`,

    security: () => html`
      <form data-form="security" novalidate class="max-w-lg">
        <div class="grid gap-4">
          ${formFields([
            { name: 'current_password', label: 'Current password', type: 'password', required: true, span: 2, autocomplete: 'current-password' },
            { name: 'new_password', label: 'New password', type: 'password', required: true, span: 2, autocomplete: 'new-password', hint: 'At least 8 characters with upper & lower case letters, a number and a symbol.' },
            { name: 'confirm_password', label: 'Confirm new password', type: 'password', required: true, span: 2, autocomplete: 'new-password' },
          ])}
        </div>
        <div class="mt-6 flex justify-end"><button type="submit" class="btn btn-primary">${icon('key')} Change password</button></div>
      </form>`,

    system: () => html`
      <form data-form="system" novalidate>
        ${formFields([
          { name: 'site_name', label: 'System name', required: true, span: 2, maxlength: 100 },
          { name: 'support_email', label: 'Support email', type: 'email', required: true, hint: 'Shown to users who need help.' },
          { name: 'support_phone', label: 'Support phone', type: 'tel', required: true },
          { name: 'max_upload_mb', label: 'Maximum upload size (MB)', type: 'number', min: 1, max: 5, required: true },
        ], settings)}
        <div class="mt-6 divide-y divide-slate-100 rounded-2xl border border-slate-200 px-4">
          ${toggleRow('require_listing_approval', 'Require approval for new listings', 'New boarding houses stay hidden until an administrator approves them.', settings.require_listing_approval === '1')}
          ${toggleRow('allow_tenant_registration', 'Allow tenant registration', 'Students can create new accounts.', settings.allow_tenant_registration === '1')}
          ${toggleRow('allow_landlord_registration', 'Allow landlord registration', 'Boarding house owners can create new accounts.', settings.allow_landlord_registration === '1')}
        </div>
        <div class="mt-6 flex justify-end"><button type="submit" class="btn btn-primary">Save settings</button></div>
      </form>`,

    notifications: () => html`
      <form data-form="notifications">
        <p class="text-sm text-slate-500">Choose which events appear in your notification centre.</p>
        <div class="mt-4 divide-y divide-slate-100 rounded-2xl border border-slate-200 px-4">
          ${toggleRow('new_users', 'New user registrations', 'When a tenant creates an account.', profile.notification_prefs.new_users)}
          ${toggleRow('new_listings', 'New landlords & listings', 'When a landlord registers or a boarding house needs approval.', profile.notification_prefs.new_listings)}
          ${toggleRow('bookings', 'Booking requests', 'When tenants request or cancel bookings.', profile.notification_prefs.bookings)}
          ${toggleRow('system', 'System announcements', 'Reports and maintenance notices.', profile.notification_prefs.system)}
        </div>
        <div class="mt-6 flex justify-end"><button type="submit" class="btn btn-primary">Save preferences</button></div>
      </form>`,
  };

  const draw = () => {
    ctx.setParams(active === 'profile' ? {} : { tab: active });
    setHtml(container, html`
      ${pageHeader({ title: 'Settings', description: 'Manage your administrator account and how the system behaves.' })}
      <div class="grid gap-6 lg:grid-cols-[15rem_1fr]">
        <nav class="card h-fit p-2" aria-label="Settings sections">
          <div class="flex gap-1 overflow-x-auto lg:flex-col">${TABS.map((t) => html`
            <button type="button" data-settings-tab="${t.value}" class="nav-link shrink-0 ${t.value === active ? 'is-active' : ''}" aria-current="${t.value === active ? 'page' : 'false'}">
              <span class="nav-icon">${icon(t.icon)}</span>${t.label}
            </button>`)}</div>
        </nav>
        <section class="card p-5 sm:p-6">
          <h2 class="mb-5 font-display text-lg font-semibold text-ink">${TABS.find((t) => t.value === active).label}</h2>
          ${panels[active]()}
        </section>
      </div>`);

    $$('[data-settings-tab]', container).forEach((btn) => btn.addEventListener('click', () => { active = btn.dataset.settingsTab; draw(); }));

    const form = $('[data-form]', container);
    const handlers = {
      profile: async (data) => {
        const res = await api.profile.save(data);
        profile = res.data;
        $$('[data-admin-name]').forEach((el) => { el.textContent = profile.full_name; });
        toast(res.message);
        draw();
      },
      security: async (data, f) => {
        if (data.new_password !== data.confirm_password) {
          throw Object.assign(new Error('The new passwords do not match.'), { errors: { confirm_password: 'Does not match.' } });
        }
        const res = await api.profile.changePassword(data);
        f.reset();
        toast(res.message);
      },
      system: async (data, f) => {
        ['require_listing_approval', 'allow_tenant_registration', 'allow_landlord_registration'].forEach((k) => { data[k] = f.elements[k].checked; });
        const res = await api.settings.save(data);
        settings = res.data;
        toast(res.message);
      },
      notifications: async (data, f) => {
        const prefs = Object.fromEntries(['new_users', 'new_listings', 'bookings', 'system'].map((k) => [k, f.elements[k].checked]));
        const res = await api.profile.savePreferences(prefs);
        profile = res.data;
        toast(res.message);
      },
    };
    bindForm(form, handlers[active]);
  };

  draw();
}

/* Landlord profile: personal details, profile photo (shown on Contact Landlord) and password. */

import { api } from '../api.js';
import { html, setHtml, $, $$, icon, badge, toast, pageHeader, tabs, formFields, readForm, clearErrors, showErrors, withLoading, imageError, IMAGE_RULES, assetUrl, avatar, fmtDate } from '../ui.js';

export async function render(view, ctx) {
  let profile = await api.profile.get();
  let active = ctx.params.get('tab') === 'security' ? 'security' : 'profile';

  const container = document.createElement('div');
  view.replaceChildren(container);

  const photo = () => (profile.avatar_path
    ? html`<img src="${assetUrl(profile.avatar_path)}" alt="Your profile photo" class="h-24 w-24 rounded-3xl object-cover" />`
    : avatar(profile.full_name, 'h-24 w-24 text-2xl'));

  function draw() {
    setHtml(container, html`
      ${pageHeader({ title: 'Profile', description: 'Students see your name, photo and contact number when they contact you.' })}
      <div class="mb-5">${tabs([{ value: 'profile', label: 'My profile' }, { value: 'security', label: 'Password' }], active)}</div>
      ${active === 'profile' ? html`
        <div class="grid gap-6 lg:grid-cols-3">
          <section class="card p-5 text-center sm:p-6">
            <div class="mx-auto w-fit" data-photo>${photo()}</div>
            <p class="mt-3 font-semibold text-ink">${profile.full_name}</p>
            <p class="text-sm text-slate-500">@${profile.username} · since ${fmtDate(profile.created_at)}</p>
            <div class="mt-2">${badge(profile.verification_status, profile.verification_status === 'verified' ? 'Verified landlord' : '')}</div>
            <label class="btn btn-secondary mt-4 cursor-pointer">${icon('upload')} Change photo
              <input type="file" class="sr-only" accept="${IMAGE_RULES.accept}" data-photo-input /></label>
            <p class="form-hint">JPG, PNG or WebP · up to 5 MB</p>
          </section>
          <section class="card p-5 sm:p-6 lg:col-span-2">
            <form id="profileForm" novalidate>
              ${formFields([
                { name: 'first_name', label: 'First name', required: true, maxlength: 80 },
                { name: 'last_name', label: 'Last name', required: true, maxlength: 80 },
                { name: 'email', label: 'Email', type: 'email', required: true, maxlength: 150 },
                { name: 'contact_number', label: 'Contact number', type: 'tel', required: true, maxlength: 20, placeholder: '09XXXXXXXXX' },
                { name: 'business_name', label: 'Business name (optional)', maxlength: 150, span: 2 },
                { name: 'address', label: 'Address', maxlength: 255, span: 2 },
              ], profile)}
              <div class="mt-5 flex justify-end"><button type="submit" class="btn btn-primary">${icon('check')} Save profile</button></div>
            </form>
          </section>
        </div>` : html`
        <section class="card max-w-xl p-5 sm:p-6">
          <form id="passwordForm" novalidate>
            ${formFields([
              { name: 'current_password', label: 'Current password', type: 'password', required: true, span: 2, autocomplete: 'current-password' },
              { name: 'new_password', label: 'New password', type: 'password', required: true, autocomplete: 'new-password', hint: 'At least 8 characters with upper and lower case letters, a number and a symbol.' },
              { name: 'confirm_password', label: 'Confirm new password', type: 'password', required: true, autocomplete: 'new-password' },
            ])}
            <div class="mt-5 flex justify-end"><button type="submit" class="btn btn-primary">${icon('key')} Change password</button></div>
          </form>
        </section>`}`);

    $$('[data-tab]', container).forEach((btn) => btn.addEventListener('click', () => {
      active = btn.dataset.tab;
      ctx.setParams(active === 'security' ? { tab: 'security' } : {});
      draw();
    }));

    $('[data-photo-input]', container)?.addEventListener('change', async (event) => {
      const file = event.target.files[0];
      event.target.value = '';
      if (!file) return;
      const error = imageError(file);
      if (error) { toast(error, 'error'); return; }
      try {
        const res = await api.profile.uploadPhoto(file);
        profile = res.data;
        setHtml($('[data-photo]', container), photo());
        window.dispatchEvent(new CustomEvent('bh:avatar', { detail: assetUrl(profile.avatar_path) }));
        toast(res.message);
      } catch (e) {
        toast(e.message, 'error');
      }
    });

    $('#profileForm', container)?.addEventListener('submit', async (event) => {
      event.preventDefault();
      const form = event.currentTarget;
      clearErrors(form);
      try {
        await withLoading($('[type=submit]', form), async () => {
          const res = await api.profile.save(readForm(form));
          profile = res.data;
          $$('[data-landlord-name]').forEach((el) => { el.textContent = profile.full_name; });
          toast(res.message);
        });
      } catch (e) {
        showErrors(form, e.errors);
        toast(e.message, 'error');
      }
    });

    $('#passwordForm', container)?.addEventListener('submit', async (event) => {
      event.preventDefault();
      const form = event.currentTarget;
      clearErrors(form);
      const data = readForm(form);
      if (data.new_password !== data.confirm_password) {
        showErrors(form, { confirm_password: 'The new passwords do not match.' });
        return;
      }
      try {
        await withLoading($('[type=submit]', form), async () => {
          const res = await api.profile.changePassword(data);
          form.reset();
          toast(res.message);
        });
      } catch (e) {
        showErrors(form, e.errors);
        toast(e.message, 'error');
      }
    });
  }

  draw();
}

/* My Tenants: boarders in my boarding houses, their rent status, payment history, chat and move-out. */

import { api } from '../api.js';
import { html, $, icon, money, num, fmtDate, toast, openModal, details, avatar, withLoading } from '../ui.js';
import { createListPage, actionButton } from '../../../../admin/assets/js/pages/_list.js';
import { openPaymentModal, stateBadge } from './payments.js';

const personPhoto = (t, size = 'h-9 w-9') => (t.photo
  ? html`<img src="${t.photo}" alt="" class="${size} shrink-0 rounded-xl object-cover" />`
  : avatar(t.name, size));

async function openTenant(id, list, ctx) {
  const t = await api.tenants.get(id);
  if (!ctx.isCurrent()) return; // the user already went to another page
  const modal = openModal({
    title: t.name,
    subtitle: `${t.house_name} · ${t.room_number} · ${t.status === 'approved' ? 'Current tenant' : 'Moved out'}`,
    size: 'lg',
    body: html`
      <div class="mb-5 flex flex-wrap items-center gap-3">
        ${personPhoto(t, 'h-14 w-14')}
        <div class="min-w-0 flex-1">
          <p class="font-semibold text-ink">${t.name}</p>
          <p class="text-sm text-slate-500">${t.phone ? html`<a class="font-semibold text-primary hover:underline" href="tel:${t.phone}">${t.phone}</a> · ` : ''}<a class="text-primary hover:underline" href="mailto:${t.email}">${t.email}</a></p>
        </div>
        ${t.unpaid_months ? html`<span class="rounded-xl bg-rose-50 px-3 py-2 text-sm font-semibold text-rose-700">Balance ${money(t.balance)}</span>` : html`<span class="rounded-xl bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-700">No unpaid rent</span>`}
      </div>
      ${details([
        ['Room', `${t.room_number} · ${t.room_type_label}`],
        ['Persons', num(t.occupants_count)],
        ['Monthly rent', money(t.monthly_rent)],
        ['Move-in date', fmtDate(t.move_in_date)],
        t.moved_out_at ? ['Moved out', fmtDate(t.moved_out_at)] : null,
        ['Reservation', t.code],
      ])}
      <h3 class="mt-6 text-sm font-semibold">Monthly rent</h3>
      ${t.history.length ? html`
        <ul class="mt-2 divide-y divide-slate-100 rounded-2xl border border-slate-100">${t.history.map((h) => html`
          <li class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 text-sm">
            <div>
              <p class="font-medium text-ink">${h.label}</p>
              <p class="text-xs text-slate-500">${money(h.amount_paid)} of ${money(h.amount_due)}${h.payment?.paid_at ? ` · paid ${fmtDate(h.payment.paid_at)}` : ''}${h.payment?.method_label ? ` · ${h.payment.method_label}` : ''}</p>
            </div>
            <div class="flex items-center gap-2">${stateBadge(h.state)}
              <button type="button" class="btn btn-secondary btn-sm" data-pay-month="${h.month}">${h.state === 'paid' ? 'Edit' : 'Record'}</button></div>
          </li>`)}</ul>`
        : html`<p class="mt-1 text-sm text-slate-500">Rent starts in the move-in month (${fmtDate(t.move_in_date)}).</p>`}`,
    footer: html`
      <button type="button" class="btn btn-secondary" data-close>Close</button>
      <a href="#/chat?tenant=${t.tenant_id}" class="btn btn-secondary" data-close>${icon('chat')} Chat</a>
      ${t.status === 'approved' ? html`<button type="button" class="btn btn-ghost text-rose-600" data-move-out>${icon('logout')} Move out</button>` : ''}`,
  });

  modal.el.querySelectorAll('[data-pay-month]').forEach((btn) => btn.addEventListener('click', () => {
    const h = t.history.find((x) => x.month === btn.dataset.payMonth);
    modal.close();
    openPaymentModal({ booking: t, month: h.month, amountDue: h.amount_due, payment: h.payment, onSaved: () => { list.changed(); openTenant(id, list, ctx); } });
  }));
  $('[data-move-out]', modal.el)?.addEventListener('click', () => { modal.close(); moveOut(t, list); });
}

function moveOut(t, list) {
  const modal = openModal({
    title: 'Move out this tenant?',
    subtitle: `${t.name} · ${t.house_name} ${t.room_number}`,
    size: 'sm',
    body: html`
      <p class="text-sm text-slate-600">The reservation is marked <strong>Completed</strong>, the ${num(t.occupants_count)} place${t.occupants_count === 1 ? '' : 's'} in the room become free again and the tenant moves to "Former tenants". Payment records are kept.</p>
      ${t.unpaid_months ? html`<p class="mt-3 rounded-xl bg-rose-50 p-3 text-sm text-rose-700">This tenant still has ${money(t.balance)} unpaid rent.</p>` : ''}
      <label class="form-label mt-4" for="moveOutNote">Note to the tenant (optional)</label>
      <textarea id="moveOutNote" rows="2" maxlength="500" class="form-control" placeholder="e.g. Thank you for staying with us!"></textarea>`,
    footer: html`
      <button type="button" class="btn btn-secondary" data-close>Cancel</button>
      <button type="button" class="btn btn-danger" data-confirm>${icon('logout')} Move out</button>`,
  });
  $('[data-confirm]', modal.el).addEventListener('click', async (event) => {
    try {
      await withLoading(event.currentTarget, async () => {
        await api.reservations.update(t.id, { status: 'completed', note: $('#moveOutNote', modal.el).value.trim() });
      });
      toast(`${t.name} moved out. The room places are free again.`);
      modal.close();
      list.changed();
    } catch (error) {
      toast(error.message, 'error');
    }
  });
}

export async function render(view, ctx) {
  const houses = await api.options('houses');
  createListPage(view, ctx, {
    title: 'My Tenants',
    description: 'Students staying in your boarding houses, their rent this month and their payment history.',
    headerActions: html`<a href="#/payments" class="btn btn-secondary">${icon('wallet')} Payments</a><a href="#/chat" class="btn btn-secondary">${icon('chat')} Chat</a>`,
    tabKey: 'status',
    defaults: { status: 'current' },
    tabs: (c) => [
      { value: 'current', label: 'Current tenants', count: c.current },
      { value: 'past', label: 'Former tenants', count: c.past },
      { value: 'all', label: 'All', count: c.all },
    ],
    searchPlaceholder: 'Search tenant, room or boarding house…',
    filters: [{ name: 'house_id', label: 'Boarding house', options: houses.map((h) => ({ value: h.id, label: h.label })) }],
    fetch: (query) => api.tenants.list(query),
    columns: (state) => [
      {
        key: 'name', label: 'Tenant', primary: true,
        render: (t) => html`<div class="flex items-center gap-3">${personPhoto(t)}<div class="min-w-0">
          <p class="truncate font-semibold text-ink">${t.name}</p>
          <p class="truncate text-xs text-slate-500">${t.phone || t.email}</p></div></div>`,
      },
      { key: 'room', label: 'Room', render: (t) => html`<p class="font-medium">${t.house_name}</p><p class="text-xs text-slate-500">${t.room_number} · ${num(t.occupants_count)} person${t.occupants_count === 1 ? '' : 's'}</p>` },
      { key: 'move_in_date', label: state.status === 'past' ? 'Stayed' : 'Move-in', render: (t) => (t.moved_out_at ? `${fmtDate(t.move_in_date)} – ${fmtDate(t.moved_out_at)}` : fmtDate(t.move_in_date)) },
      { key: 'monthly_rent', label: 'Monthly rent', className: 'whitespace-nowrap', render: (t) => money(t.monthly_rent) },
      { key: 'this_month', label: 'This month', render: (t) => (t.this_month ? stateBadge(t.this_month) : html`<span class="text-xs text-slate-400">Moved out</span>`) },
      {
        key: 'balance', label: 'Unpaid', className: 'whitespace-nowrap',
        render: (t) => (t.unpaid_months ? html`<span class="font-semibold text-rose-600">${money(t.balance)}</span><span class="block text-[11px] text-slate-400">${t.unpaid_months} month${t.unpaid_months === 1 ? '' : 's'}</span>` : html`<span class="text-emerald-700">—</span>`),
      },
    ],
    actions: (t) => html`
      ${actionButton('view', t.id, 'eye', 'Details & payment history')}
      ${t.status === 'approved' ? actionButton('pay', t.id, 'wallet', 'Record payment', 'text-emerald-600 hover:bg-emerald-50') : ''}
      ${actionButton('chat', t.id, 'chat', 'Chat')}
      ${t.status === 'approved' ? actionButton('moveout', t.id, 'logout', 'Move out', 'text-rose-600 hover:bg-rose-50') : ''}`,
    emptyState: (state) => html`
      <div class="flex flex-col items-center px-6 py-14 text-center">
        <span class="grid h-14 w-14 place-items-center rounded-2xl bg-primary-50 text-primary">${icon('users', 'h-6 w-6')}</span>
        <p class="mt-4 font-semibold text-ink">${state.status === 'past' ? 'No former tenants' : 'No tenants yet'}</p>
        <p class="mt-1 max-w-sm text-sm text-slate-500">When you approve a student's reservation, they appear here as your tenant.</p>
        <a href="#/reservations?status=pending" class="btn btn-secondary mt-4">${icon('calendar')} Pending reservations</a>
      </div>`,
    onAction: async (action, id, row, list) => {
      if (action === 'view') await openTenant(id, list, ctx);
      if (action === 'chat') ctx.navigate('chat', { tenant: row.tenant_id });
      if (action === 'moveout') moveOut(row, list);
      if (action === 'pay') {
        const t = await api.tenants.get(id);
        const month = t.history[0] || { month: new Date().toISOString().slice(0, 7), amount_due: t.monthly_rent, payment: null };
        openPaymentModal({ booking: t, month: month.month, amountDue: month.amount_due, payment: month.payment, onSaved: () => list.changed() });
      }
    },
    onReady: (list) => {
      const viewId = Number(ctx.params.get('view'));
      if (viewId) openTenant(viewId, list, ctx).catch((e) => toast(e.message, 'error'));
    },
  });
}

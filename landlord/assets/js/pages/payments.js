/* Payments: who paid this month's rent, record payments, totals per month. */

import { api } from '../api.js';
import {
  html, setHtml, $, icon, badge, money, num, fmtDate, toast, confirmDialog, pageHeader, dataTable, tabs, avatar,
  formModal, errorState, skeletonRows, downloadCsv,
} from '../ui.js';

export const STATE_LABELS = { paid: 'Paid', partial: 'Partly paid', unpaid: 'Unpaid', overdue: 'Overdue', upcoming: 'Not yet due' };
export const stateBadge = (state) => (state ? badge(state, STATE_LABELS[state]) : '');
const METHODS = [
  { value: 'cash', label: 'Cash' },
  { value: 'gcash', label: 'GCash' },
  { value: 'bank', label: 'Bank transfer' },
  { value: 'other', label: 'Other' },
];
const today = () => { const d = new Date(); d.setMinutes(d.getMinutes() - d.getTimezoneOffset()); return d.toISOString().slice(0, 10); };
const thisMonth = () => today().slice(0, 7);
const shiftMonth = (month, delta) => {
  const [y, m] = month.split('-').map(Number);
  const d = new Date(y, m - 1 + delta, 1);
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
};
const monthName = (month) => { const [y, m] = month.split('-').map(Number); return new Date(y, m - 1, 1).toLocaleDateString('en-PH', { month: 'long', year: 'numeric' }); };

/**
 * Record (or correct) the rent of one month.
 * item: { id (booking), name, house_name, room_number, amount_due, amount_paid, payment }
 */
export function openPaymentModal({ booking, month, amountDue, payment, onSaved }) {
  const remaining = Math.max(0, amountDue - (payment?.amount_paid || 0));
  formModal({
    title: payment ? 'Edit payment' : 'Record payment',
    subtitle: `${booking.name} · ${booking.house_name} ${booking.room_number} · ${monthName(month)}`,
    fields: [
      { name: 'month', label: 'Rent for month', type: 'month', required: true },
      { name: 'amount_paid', label: `Amount paid (₱) · due ${money(amountDue)}`, type: 'number', required: true, min: 0, step: '0.01', hint: 'Enter the total paid for this month. Less than the amount due is saved as "Partly paid".' },
      { name: 'paid_at', label: 'Date paid', type: 'date', required: true, max: today() },
      { name: 'method', label: 'Payment method', type: 'select', options: METHODS, required: true },
      { name: 'reference', label: 'Reference no. (optional)', maxlength: 100, placeholder: 'e.g. GCash reference' },
      { name: 'note', label: 'Note (optional)', type: 'textarea', maxlength: 500 },
    ],
    values: {
      month,
      amount_paid: payment ? payment.amount_paid : remaining || amountDue,
      paid_at: payment?.paid_at || today(),
      method: payment?.method || 'cash',
      reference: payment?.reference || '',
      note: payment?.note || '',
    },
    submitText: 'Save payment',
    onSubmit: async (data) => {
      const res = await api.payments.save({ ...data, booking_id: booking.id });
      toast(res.message);
      window.dispatchEvent(new CustomEvent('bh:data-changed'));
      onSaved?.(res.data);
    },
  });
}

function summaryCard(label, value, sub, tone, iconName) {
  return html`
    <div class="card p-5">
      <div class="flex items-start justify-between gap-3">
        <p class="text-sm font-medium text-slate-500">${label}</p>
        <span class="grid h-10 w-10 place-items-center rounded-xl ${tone}">${icon(iconName, 'h-5 w-5')}</span>
      </div>
      <p class="mt-3 font-display text-2xl font-semibold text-ink">${value}</p>
      <p class="mt-1 text-xs text-slate-500">${sub}</p>
    </div>`;
}

export async function render(view, ctx) {
  const houses = await api.options('houses');
  const state = {
    month: /^\d{4}-\d{2}$/.test(ctx.params.get('month') || '') ? ctx.params.get('month') : thisMonth(),
    status: ctx.params.get('status') || '',
    house_id: ctx.params.get('house_id') || '',
  };
  let last = null;

  const container = document.createElement('div');
  view.replaceChildren(container);
  setHtml(container, html`
    ${pageHeader({
      title: 'Payments',
      description: 'Monthly rent of your tenants. Record a payment when a tenant pays you.',
      actions: html`<button type="button" class="btn btn-secondary" data-export>${icon('download')} Export CSV</button>`,
    })}
    <div class="card mb-6 flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:p-5">
      <div class="flex items-center gap-2">
        <button type="button" class="icon-btn" data-shift="-1" aria-label="Previous month">${icon('chevronLeft')}</button>
        <label class="sr-only" for="payMonth">Month</label>
        <input id="payMonth" type="month" class="form-control w-44" value="${state.month}" max="${shiftMonth(thisMonth(), 1)}" />
        <button type="button" class="icon-btn" data-shift="1" aria-label="Next month">${icon('chevronRight')}</button>
        <button type="button" class="btn btn-ghost btn-sm" data-this-month>This month</button>
      </div>
      <div class="sm:ml-auto sm:w-64">
        <label class="sr-only" for="payHouse">Boarding house</label>
        <select id="payHouse" class="form-control">
          <option value="">All boarding houses</option>
          ${houses.map((h) => html`<option value="${h.id}" ${String(h.id) === state.house_id ? 'selected' : ''}>${h.label}</option>`)}
        </select>
      </div>
    </div>
    <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" data-summary></section>
    <div class="card mt-6 overflow-hidden">
      <div class="border-b border-slate-100 p-4 sm:p-5" data-tabs></div>
      <div data-body>${skeletonRows()}</div>
    </div>`);

  const body = $('[data-body]', container);

  function sync() {
    ctx.setParams({ month: state.month === thisMonth() ? '' : state.month, status: state.status, house_id: state.house_id });
    $('#payMonth', container).value = state.month;
  }

  async function load() {
    sync();
    body.style.opacity = '0.55';
    try {
      const res = await api.payments.list({ month: state.month, house_id: state.house_id });
      if (!ctx.isCurrent()) return;
      last = res;
      const t = res.totals;
      const count = t.paid + t.unpaid;
      setHtml($('[data-summary]', container), html`
        ${summaryCard('Expected rent', money(t.expected), `${num(count)} tenant${count === 1 ? '' : 's'} · ${res.month_label}`, 'bg-sky-50 text-sky-600', 'wallet')}
        ${summaryCard('Collected', money(t.collected), t.expected ? `${Math.round((t.collected / t.expected) * 100)}% of expected` : 'Nothing due', 'bg-emerald-50 text-emerald-600', 'checkCircle')}
        ${summaryCard('Still unpaid', money(t.outstanding), `${num(t.unpaid)} tenant${t.unpaid === 1 ? '' : 's'} not fully paid`, 'bg-rose-50 text-rose-600', 'alert')}
        ${summaryCard('Fully paid', `${num(t.paid)} / ${num(count)}`, 'Tenants who paid the whole month', 'bg-violet-50 text-violet-600', 'users')}`);

      const unpaidStates = ['unpaid', 'partial', 'overdue'];
      const rows = res.data.filter((r) => !state.status || (state.status === 'paid' ? r.state === 'paid' : unpaidStates.includes(r.state)));
      setHtml($('[data-tabs]', container), tabs([
        { value: '', label: 'All', count: res.data.length },
        { value: 'unpaid', label: 'Unpaid', count: res.data.filter((r) => unpaidStates.includes(r.state)).length },
        { value: 'paid', label: 'Paid', count: res.data.filter((r) => r.state === 'paid').length },
      ], state.status));

      setHtml(body, dataTable({
        rows,
        columns: [
          {
            key: 'name', label: 'Tenant', primary: true,
            render: (r) => html`<div class="flex items-center gap-3">${avatar(r.name)}<div class="min-w-0">
              <p class="truncate font-semibold text-ink">${r.name}</p>
              <p class="truncate text-xs text-slate-500">${r.phone || r.code}${r.boarder_status === 'completed' ? ' · moved out' : ''}</p></div></div>`,
          },
          { key: 'room', label: 'Room', render: (r) => html`<p class="font-medium">${r.house_name}</p><p class="text-xs text-slate-500">${r.room_number} · ${num(r.occupants_count)} person${r.occupants_count === 1 ? '' : 's'}</p>` },
          { key: 'amount_due', label: 'Rent due', className: 'whitespace-nowrap', render: (r) => money(r.amount_due) },
          { key: 'amount_paid', label: 'Paid', className: 'whitespace-nowrap', render: (r) => html`<span class="${r.amount_paid ? 'font-semibold text-emerald-700' : 'text-slate-400'}">${money(r.amount_paid)}</span>` },
          { key: 'state', label: 'Status', render: (r) => stateBadge(r.state) },
          {
            key: 'paid_at', label: 'Paid on', hideOnMobile: true,
            render: (r) => (r.payment?.paid_at ? html`${fmtDate(r.payment.paid_at)}<span class="block text-xs text-slate-400">${r.payment.method_label || ''}${r.payment.reference ? ` · ${r.payment.reference}` : ''}</span>` : '—'),
          },
        ],
        actions: (r) => html`
          <button type="button" class="btn ${r.state === 'paid' ? 'btn-secondary' : 'btn-primary'} btn-sm" data-pay="${r.id}">${icon(r.state === 'paid' ? 'edit' : 'wallet')} ${r.state === 'paid' ? 'Edit' : 'Record payment'}</button>
          <a href="#/chat?tenant=${r.tenant_id}" class="icon-btn" title="Chat with ${r.name}" aria-label="Chat with ${r.name}">${icon('chat')}</a>
          ${r.payment ? html`<button type="button" class="icon-btn text-rose-600 hover:bg-rose-50" data-remove="${r.payment.id}" title="Remove payment record" aria-label="Remove payment record">${icon('trash')}</button>` : ''}`,
        empty: html`
          <div class="flex flex-col items-center px-6 py-14 text-center">
            <span class="grid h-14 w-14 place-items-center rounded-2xl bg-slate-100 text-slate-400">${icon('wallet', 'h-6 w-6')}</span>
            <p class="mt-4 font-semibold text-ink">${res.data.length ? 'Nothing in this list' : `No rent due for ${res.month_label}`}</p>
            <p class="mt-1 max-w-sm text-sm text-slate-500">${res.data.length ? 'Try another tab.' : 'Tenants appear here once you approve their reservation and their move-in month starts.'}</p>
          </div>`,
      }));
    } catch (error) {
      setHtml(body, errorState(error.message));
    } finally {
      body.style.opacity = '';
    }
  }

  container.addEventListener('click', async (event) => {
    const shift = event.target.closest('[data-shift]');
    if (shift) { state.month = shiftMonth(state.month, Number(shift.dataset.shift)); load(); return; }
    if (event.target.closest('[data-this-month]')) { state.month = thisMonth(); load(); return; }
    const tab = event.target.closest('[data-tab]');
    if (tab) { state.status = tab.dataset.tab; load(); return; }
    if (event.target.closest('[data-retry]')) { load(); return; }
    if (event.target.closest('[data-export]') && last) {
      downloadCsv(`rent-${last.month}.csv`, ['Tenant', 'Phone', 'Boarding house', 'Room', 'Rent due', 'Paid', 'Status', 'Paid on', 'Method', 'Reference'],
        last.data.map((r) => [r.name, r.phone, r.house_name, r.room_number, r.amount_due, r.amount_paid, STATE_LABELS[r.state], r.payment?.paid_at || '', r.payment?.method_label || '', r.payment?.reference || '']));
      return;
    }
    const pay = event.target.closest('[data-pay]');
    if (pay && last) {
      const item = last.data.find((r) => String(r.id) === pay.dataset.pay);
      openPaymentModal({ booking: item, month: last.month, amountDue: item.amount_due, payment: item.payment, onSaved: load });
      return;
    }
    const remove = event.target.closest('[data-remove]');
    if (remove) {
      const ok = await confirmDialog({ title: 'Remove this payment record?', message: 'The month goes back to Unpaid. Use this only to undo a mistake.', confirmText: 'Remove record', danger: true, iconName: 'trash' });
      if (!ok) return;
      try {
        const res = await api.payments.remove(Number(remove.dataset.remove));
        toast(res.message);
        window.dispatchEvent(new CustomEvent('bh:data-changed'));
        load();
      } catch (error) {
        toast(error.message, 'error');
      }
    }
  });
  $('#payMonth', container).addEventListener('change', (e) => { if (/^\d{4}-\d{2}$/.test(e.target.value)) { state.month = e.target.value; load(); } });
  $('#payHouse', container).addEventListener('change', (e) => { state.house_id = e.target.value; load(); });

  load();
}


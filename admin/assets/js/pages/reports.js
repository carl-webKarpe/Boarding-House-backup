/* Reports & analytics with CSV export and a print-friendly layout. */

import { api } from '../api.js';
import { html, setHtml, icon, $, $$, num, money, errorState, downloadCsv, pageHeader } from '../ui.js';
import { lineChart, groupedBarChart, horizontalBarChart, chartTable, SERIES } from '../charts.js';

function kpi(label, value, note = '') {
  return html`
    <div class="card p-4">
      <p class="text-xs font-medium text-slate-500">${label}</p>
      <p class="mt-2 font-display text-2xl font-semibold text-ink">${value}</p>
      ${note ? html`<p class="mt-1 text-xs text-slate-500">${note}</p>` : ''}
    </div>`;
}

function tableCard(title, subtitle, exportKey, headers, rows, numericFrom = 1) {
  return html`
    <div class="card overflow-hidden">
      <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
        <div><h2 class="font-semibold text-ink">${title}</h2><p class="text-xs text-slate-500">${subtitle}</p></div>
        <button type="button" class="btn btn-secondary btn-sm no-print" data-export="${exportKey}">${icon('download')} CSV</button>
      </div>
      ${rows.length ? html`<div class="overflow-x-auto"><table class="data-table">
        <thead><tr>${headers.map((h, i) => html`<th class="${i >= numericFrom ? 'num' : ''}">${h}</th>`)}</tr></thead>
        <tbody>${rows.map((r) => html`<tr>${r.map((cell, i) => html`<td class="${i >= numericFrom ? 'num' : ''} ${i === 0 ? 'font-medium' : ''}">${cell}</td>`)}</tr>`)}</tbody>
      </table></div>` : html`<p class="px-5 py-10 text-center text-sm text-slate-400">No data yet.</p>`}
    </div>`;
}

export async function render(container, ctx) {
  let months = Number(ctx.params.get('months')) === 12 ? 12 : 6;

  const load = async () => {
    ctx.setParams(months === 6 ? {} : { months });
    let r;
    try {
      r = await api.reports(months);
    } catch (error) {
      setHtml(container, errorState(error.message));
      $('[data-retry]', container).addEventListener('click', load);
      return;
    }
    if (!ctx.isCurrent()) return;

    const s = r.summary;
    const sum = (arr) => arr.reduce((a, b) => a + b, 0);
    const bookingTotal = sum(r.booking_activity.pending) + sum(r.booking_activity.approved) + sum(r.booking_activity.cancelled) + sum(r.booking_activity.completed);
    const newUsers = sum(r.registrations.tenants) + sum(r.registrations.landlords);

    const exports = {
      registrations: [['Month', 'New tenants', 'New landlords'], r.labels.map((l, i) => [l, r.registrations.tenants[i], r.registrations.landlords[i]])],
      bookings: [['Month', 'Pending', 'Approved', 'Cancelled', 'Completed'], r.labels.map((l, i) => [l, r.booking_activity.pending[i], r.booking_activity.approved[i], r.booking_activity.cancelled[i], r.booking_activity.completed[i]])],
      listings: [['Month', 'Submitted', 'Approved'], r.labels.map((l, i) => [l, r.listings.submitted[i], r.listings.approved[i]])],
      cities: [['City / municipality', 'Approved boarding houses', 'Rooms', 'Available rooms', 'Average rent'], r.by_city.map((c) => [c.city, c.houses, c.rooms, c.available_rooms, c.avg_price])],
      types: [['Room type', 'Rooms', 'Beds', 'Occupied beds', 'Average rent', 'Lowest', 'Highest'], r.by_room_type.map((t) => [t.room_type, t.rooms, t.beds, t.occupants, t.avg_price, t.min_price, t.max_price])],
      occupancy: [['Boarding house', 'City', 'Beds', 'Occupied', 'Occupancy %'], r.top_occupancy.map((h) => [h.name, h.city, h.beds, h.occupants, h.occupancy_rate])],
    };

    setHtml(container, html`
      ${pageHeader({
        title: 'Reports',
        description: `System summary for the last ${months} months · generated ${new Date().toLocaleString('en-PH')}`,
        actions: html`
          <div class="tabs no-print rounded-xl border border-slate-200 bg-white p-1" role="group" aria-label="Report period">
            ${[6, 12].map((m) => html`<button type="button" class="tab ${m === months ? 'is-active' : ''}" data-months="${m}" aria-pressed="${m === months}">${m} months</button>`)}
          </div>
          <button type="button" class="btn btn-secondary no-print" data-print>${icon('file')} Print / PDF</button>`,
      })}

      <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7">
        ${kpi('Registered users', num(s.users.total), `${num(newUsers)} new in ${months} months`)}
        ${kpi('Tenants', num(s.tenants.total))}
        ${kpi('Active landlords', num(s.landlords.active), `${num(s.landlords.total)} total`)}
        ${kpi('Boarding houses', num(s.boarding_houses.approved), `approved · ${num(s.boarding_houses.total)} total`)}
        ${kpi('Available rooms', num(s.available_rooms))}
        ${kpi('Occupied rooms', num(s.occupied_rooms), `${s.occupancy_rate}% bed occupancy`)}
        ${kpi('Booking activity', num(bookingTotal), `in ${months} months`)}
      </section>

      <section class="mt-6 grid gap-6 xl:grid-cols-2">
        <div class="card p-5">
          <div class="flex items-start justify-between gap-2"><div><h2 class="font-semibold text-ink">Registered users</h2><p class="text-xs text-slate-500">New tenant and landlord accounts per month</p></div>
          <button type="button" class="btn btn-ghost btn-sm no-print" data-export="registrations">${icon('download')} CSV</button></div>
          <div class="chart-box mt-4 h-72"><canvas id="rptRegistrations" role="img" aria-label="Line chart of registrations per month"></canvas></div>
          ${chartTable(exports.registrations[0], exports.registrations[1])}
        </div>
        <div class="card p-5">
          <div class="flex items-start justify-between gap-2"><div><h2 class="font-semibold text-ink">Booking activity</h2><p class="text-xs text-slate-500">Bookings made each month, by their current status</p></div>
          <button type="button" class="btn btn-ghost btn-sm no-print" data-export="bookings">${icon('download')} CSV</button></div>
          <div class="chart-box mt-4 h-72"><canvas id="rptBookings" role="img" aria-label="Stacked bar chart of bookings per month by status"></canvas></div>
          ${chartTable(exports.bookings[0], exports.bookings[1])}
        </div>
        <div class="card p-5">
          <div class="flex items-start justify-between gap-2"><div><h2 class="font-semibold text-ink">Boarding house listings</h2><p class="text-xs text-slate-500">Listings submitted each month and how many are approved</p></div>
          <button type="button" class="btn btn-ghost btn-sm no-print" data-export="listings">${icon('download')} CSV</button></div>
          <div class="chart-box mt-4 h-64"><canvas id="rptListings" role="img" aria-label="Bar chart of listings submitted and approved per month"></canvas></div>
          ${chartTable(exports.listings[0], exports.listings[1])}
        </div>
        <div class="card p-5">
          <h2 class="font-semibold text-ink">Available vs occupied rooms</h2>
          <p class="text-xs text-slate-500">Current room availability across all listings</p>
          <div class="chart-box mt-4 h-64"><canvas id="rptRooms" role="img" aria-label="Bar chart of rooms by availability"></canvas></div>
          ${chartTable(['Status', 'Rooms'], [['Available', r.rooms.available], ['Occupied', r.rooms.occupied], ['Maintenance', r.rooms.maintenance]])}
        </div>
      </section>

      <section class="mt-6 grid gap-6">
        ${tableCard('Listings by location', 'Approved boarding houses per city or municipality', 'cities', exports.cities[0],
          r.by_city.map((c) => [c.city, num(c.houses), num(c.rooms), num(c.available_rooms), money(c.avg_price)]))}
        ${tableCard('Rooms by type', 'Prices and occupancy for each room type', 'types', exports.types[0],
          r.by_room_type.map((t) => [t.room_type[0].toUpperCase() + t.room_type.slice(1), num(t.rooms), num(t.beds), num(t.occupants), money(t.avg_price), money(t.min_price), money(t.max_price)]))}
      </section>
      <section class="mt-6">
        ${tableCard('Highest occupancy', 'Top 10 approved boarding houses by occupied beds', 'occupancy', exports.occupancy[0],
          r.top_occupancy.map((h) => [h.name, h.city, num(h.beds), num(h.occupants), `${h.occupancy_rate ?? 0}%`]), 2)}
      </section>`);

    lineChart($('#rptRegistrations', container), r.labels, [
      { label: 'Tenants', data: r.registrations.tenants },
      { label: 'Landlords', data: r.registrations.landlords },
    ]);
    groupedBarChart($('#rptBookings', container), r.labels, [
      { label: 'Pending', data: r.booking_activity.pending },
      { label: 'Approved', data: r.booking_activity.approved },
      { label: 'Cancelled', data: r.booking_activity.cancelled },
      { label: 'Completed', data: r.booking_activity.completed },
    ], { stacked: true });
    groupedBarChart($('#rptListings', container), r.labels, [
      { label: 'Submitted', data: r.listings.submitted, color: SERIES[0] },
      { label: 'Approved', data: r.listings.approved, color: SERIES[1] },
    ]);
    horizontalBarChart($('#rptRooms', container), ['Available', 'Occupied', 'Maintenance'], [r.rooms.available, r.rooms.occupied, r.rooms.maintenance]);

    $$('[data-months]', container).forEach((btn) => btn.addEventListener('click', () => {
      months = Number(btn.dataset.months);
      load();
    }));
    $('[data-print]', container).addEventListener('click', () => window.print());
    $$('[data-export]', container).forEach((btn) => btn.addEventListener('click', () => {
      const [headers, rows] = exports[btn.dataset.export];
      downloadCsv(`report-${btn.dataset.export}-${new Date().toISOString().slice(0, 10)}.csv`, headers, rows);
    }));
  };

  await load();
}

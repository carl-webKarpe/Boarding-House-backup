/* ==========================================================================
   Chart helpers (Chart.js 4).

   Colour rules:
   - Multi-series charts use the categorical palette in fixed order
     (validated for colour-blind separation): blue, then orange.
   - Single-series bar charts use the brand green.
   - Text always uses ink colours, never the series colour.
   Every chart has hover tooltips and a "Show data table" fallback.
   ========================================================================== */

import { html, num } from './ui.js';

export const SERIES = ['#2a78d6', '#eb6834', '#1baf7a', '#eda100'];
export const BRAND = '#16a34a';
const INK = '#1e293b';
const MUTED = '#64748b';
const GRID = '#eef2f6';

const registry = new Map();

function applyDefaults() {
  const { Chart } = window;
  if (!Chart || Chart.__bhDefaults) return;
  Chart.defaults.font.family = 'Inter, system-ui, sans-serif';
  Chart.defaults.font.size = 12;
  Chart.defaults.color = MUTED;
  Chart.defaults.maintainAspectRatio = false;
  Chart.defaults.plugins.legend.labels.usePointStyle = true;
  Chart.defaults.plugins.legend.labels.boxWidth = 8;
  Chart.defaults.plugins.legend.labels.boxHeight = 8;
  Chart.defaults.plugins.legend.labels.color = INK;
  Chart.defaults.plugins.tooltip.backgroundColor = '#0f172a';
  Chart.defaults.plugins.tooltip.padding = 10;
  Chart.defaults.plugins.tooltip.cornerRadius = 10;
  Chart.defaults.plugins.tooltip.titleFont = { weight: '600' };
  Chart.defaults.plugins.tooltip.boxPadding = 4;
  Chart.__bhDefaults = true;
}

function mount(canvas, config) {
  applyDefaults();
  if (!window.Chart) {
    canvas.replaceWith(Object.assign(document.createElement('p'), {
      className: 'py-10 text-center text-sm text-slate-400',
      textContent: 'Charts could not load (no internet connection to the chart library). Use the data table below.',
    }));
    return null;
  }
  registry.get(canvas.id)?.destroy();
  const chart = new window.Chart(canvas, config);
  registry.set(canvas.id, chart);
  return chart;
}

export function destroyCharts() {
  registry.forEach((chart) => chart.destroy());
  registry.clear();
}

const axis = (extra = {}) => ({
  grid: { color: GRID, drawTicks: false },
  border: { display: false },
  ticks: { padding: 8, precision: 0 },
  beginAtZero: true,
  ...extra,
});

/** Change over time: one line per series. */
export function lineChart(canvas, labels, series) {
  return mount(canvas, {
    type: 'line',
    data: {
      labels,
      datasets: series.map((s, i) => ({
        label: s.label,
        data: s.data,
        borderColor: SERIES[i],
        backgroundColor: SERIES[i],
        borderWidth: 2,
        pointRadius: 4,
        pointHoverRadius: 6,
        pointBorderColor: '#fff',
        pointBorderWidth: 2,
        cubicInterpolationMode: 'monotone',
      })),
    },
    options: {
      interaction: { mode: 'index', intersect: false },
      plugins: { legend: { position: 'top', align: 'end' } },
      scales: { x: axis({ grid: { display: false }, beginAtZero: false }), y: axis() },
    },
  });
}

/** Grouped or stacked vertical bars, one colour per series. */
export function groupedBarChart(canvas, labels, series, { stacked = false } = {}) {
  return mount(canvas, {
    type: 'bar',
    data: {
      labels,
      datasets: series.map((s, i) => ({
        label: s.label,
        data: s.data,
        backgroundColor: s.color || SERIES[i],
        borderRadius: 4,
        borderSkipped: 'bottom',
        maxBarThickness: 22,
        borderColor: '#fff',
        borderWidth: stacked ? { top: 2 } : 0,
      })),
    },
    options: {
      interaction: { mode: 'index', intersect: false },
      plugins: { legend: { position: 'top', align: 'end' } },
      scales: { x: axis({ stacked, grid: { display: false } }), y: axis({ stacked }) },
    },
  });
}

/** Single-series horizontal bars with the value printed at the end of each bar. */
export function horizontalBarChart(canvas, labels, data, { color = BRAND, suffix = '' } = {}) {
  const valueLabels = {
    id: 'valueLabels',
    afterDatasetsDraw(chart) {
      const { ctx } = chart;
      ctx.save();
      ctx.font = '600 12px Inter, system-ui, sans-serif';
      ctx.fillStyle = INK;
      ctx.textBaseline = 'middle';
      chart.getDatasetMeta(0).data.forEach((bar, i) => {
        ctx.fillText(`${num(data[i])}${suffix}`, bar.x + 6, bar.y);
      });
      ctx.restore();
    },
  };

  const max = Math.max(1, ...data);
  return mount(canvas, {
    type: 'bar',
    data: { labels, datasets: [{ data, backgroundColor: color, borderRadius: 4, borderSkipped: 'start', barThickness: 18 }] },
    options: {
      indexAxis: 'y',
      layout: { padding: { right: 36 } },
      plugins: { legend: { display: false }, tooltip: { callbacks: { label: (item) => ` ${num(item.raw)}${suffix}` } } },
      scales: {
        x: axis({ suggestedMax: max * 1.1, ticks: { display: false }, grid: { display: false } }),
        y: { grid: { display: false }, border: { display: false }, ticks: { color: INK, font: { weight: '500' } } },
      },
    },
    plugins: [valueLabels],
  });
}

/** Accessible table view of chart data, shown on demand. */
export function chartTable(headers, rows) {
  return html`
    <details class="mt-3 text-sm">
      <summary class="cursor-pointer select-none text-xs font-medium text-slate-500 hover:text-ink">Show data table</summary>
      <div class="mt-2 overflow-x-auto rounded-xl border border-slate-100">
        <table class="data-table">
          <thead><tr>${headers.map((h, i) => html`<th class="${i ? 'num' : ''}">${h}</th>`)}</tr></thead>
          <tbody>${rows.map((row) => html`<tr>${row.map((cell, i) => html`<td class="${i ? 'num' : ''}">${i ? num(cell) : cell}</td>`)}</tr>`)}</tbody>
        </table>
      </div>
    </details>`;
}

/* ==========================================================================
   Reusable list page: header, status tabs, search, filters, responsive
   table, sorting and pagination. The users, landlords, boarding houses,
   rooms and bookings pages are all built on this.

   State lives in the URL (e.g. #/rooms?status=available&page=2), so
   filtered views can be bookmarked and survive a refresh.
   ========================================================================== */

import { html, setHtml, $, $$, debounce, pageHeader, tabs, dataTable, pagination, skeletonRows, errorState, icon, toast } from '../ui.js';

/**
 * @param {object} cfg
 *  - title, description, headerActions (markup)
 *  - tabKey: state key the tabs control (e.g. "status"); tabs(counts) => [{value,label,count}]
 *  - filters: [{ name, label, options: [{value,label}] }]  (select filters)
 *  - fixed: params always sent to the API (e.g. { role: 'landlord' })
 *  - keepInUrl: params that must stay in the URL (e.g. { boarding_house_id: 3 })
 *  - defaults: initial state values ({ sort: 'created_at', order: 'desc' })
 *  - fetch(query) => Promise<{data, meta, counts}>
 *  - columns(state) => column specs for dataTable
 *  - actions(row) => markup of row action buttons (use data-action="x" data-id)
 *  - onAction(action, id, row, list) => handler
 *  - searchPlaceholder, emptyState(state) => markup
 *  - onReady(list) => called after first render (e.g. to open ?view=ID)
 */
export function createListPage(view, ctx, cfg) {
  // A fresh element per page visit, so event listeners never pile up on #view.
  const container = document.createElement('div');
  view.replaceChildren(container);

  const stateKeys = ['q', 'page', 'sort', 'order', cfg.tabKey, ...(cfg.filters || []).map((f) => f.name)].filter(Boolean);
  const state = { page: 1, ...cfg.defaults };
  stateKeys.forEach((key) => {
    const value = ctx.params.get(key);
    if (value !== null) state[key] = key === 'page' ? Math.max(1, Number(value) || 1) : value;
  });

  let rowsById = new Map();
  let lastCounts = {};
  let requestId = 0;

  setHtml(container, html`
    ${pageHeader({ title: cfg.title, description: cfg.description, actions: cfg.headerActions || '' })}
    <div class="card overflow-hidden">
      <div class="flex flex-col gap-3 border-b border-slate-100 p-4 sm:p-5">
        <div data-tabs></div>
        <div class="flex flex-col gap-2 md:flex-row md:items-center">
          <div class="relative min-w-0 flex-1">
            <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-slate-400">${icon('search', 'h-4 w-4')}</span>
            <input type="search" data-search class="form-control pl-9" placeholder="${cfg.searchPlaceholder || 'Search…'}" aria-label="Search" value="${state.q || ''}" />
          </div>
          ${(cfg.filters || []).map((f) => html`
            <div class="md:w-48 md:shrink-0">
            <label class="sr-only" for="filter-${f.name}">${f.label}</label>
            <select id="filter-${f.name}" data-filter="${f.name}" class="form-control">
              <option value="">${f.label}: All</option>
              ${f.options.map((o) => html`<option value="${o.value}" ${String(state[f.name] ?? '') === String(o.value) ? 'selected' : ''}>${o.label}</option>`)}
            </select>
            </div>`)}
          <button type="button" class="btn btn-secondary md:shrink-0" data-refresh title="Refresh">${icon('refresh')}<span class="md:hidden">Refresh</span></button>
        </div>
      </div>
      <div data-body aria-live="polite">${skeletonRows()}</div>
      <div data-pagination></div>
    </div>`);

  const body = $('[data-body]', container);
  const pager = $('[data-pagination]', container);
  const tabsHolder = $('[data-tabs]', container);

  function syncUrl() {
    const params = { ...(cfg.keepInUrl || {}) };
    stateKeys.forEach((key) => {
      const value = state[key];
      if (value === undefined || value === null || value === '') return;
      if (key === 'page' && Number(value) === 1) return;
      if (cfg.defaults && cfg.defaults[key] === value) return;
      params[key] = value;
    });
    ctx.setParams(params);
  }

  function renderTabs() {
    if (!cfg.tabs) return;
    setHtml(tabsHolder, tabs(cfg.tabs(lastCounts), state[cfg.tabKey] || ''));
    $$('[data-tab]', tabsHolder).forEach((btn) => btn.addEventListener('click', () => {
      state[cfg.tabKey] = btn.dataset.tab;
      state.page = 1;
      load();
    }));
  }

  async function load() {
    const id = ++requestId;
    syncUrl();
    renderTabs();
    body.style.opacity = '0.55';
    try {
      const query = { per_page: 10, ...cfg.fixed };
      stateKeys.forEach((key) => { if (state[key] !== undefined && state[key] !== '') query[key] = state[key]; });
      const res = await cfg.fetch(query);
      if (id !== requestId || !ctx.isCurrent()) return;

      // Jump back when the current page became empty (e.g. after deleting its last row).
      if (!res.data.length && state.page > 1 && res.meta && state.page > res.meta.total_pages) {
        state.page = res.meta.total_pages;
        load();
        return;
      }

      rowsById = new Map(res.data.map((row) => [String(row.id), row]));
      lastCounts = res.counts || {};
      renderTabs();
      setHtml(body, dataTable({
        columns: cfg.columns(state),
        rows: res.data,
        actions: cfg.actions,
        sort: { sort: state.sort, order: state.order },
        empty: cfg.emptyState?.(state),
      }));
      setHtml(pager, pagination(res.meta));
    } catch (error) {
      if (id !== requestId) return;
      setHtml(body, errorState(error.message));
      setHtml(pager, '');
    } finally {
      if (id === requestId) body.style.opacity = '';
    }
  }

  const onSearch = debounce((value) => {
    state.q = value.trim();
    state.page = 1;
    load();
  }, 300);

  $('[data-search]', container).addEventListener('input', (e) => onSearch(e.target.value));
  $('[data-refresh]', container).addEventListener('click', () => load());
  $$('[data-filter]', container).forEach((select) => select.addEventListener('change', () => {
    state[select.dataset.filter] = select.value;
    state.page = 1;
    load();
  }));

  // Delegated events: sorting, paging, row actions, retry.
  container.addEventListener('click', async (event) => {
    const sortBtn = event.target.closest('[data-sort]');
    if (sortBtn) {
      const key = sortBtn.dataset.sort;
      state.order = state.sort === key && state.order === 'asc' ? 'desc' : 'asc';
      state.sort = key;
      load();
      return;
    }
    const pageBtn = event.target.closest('[data-page]');
    if (pageBtn && !pageBtn.disabled) {
      state.page = Number(pageBtn.dataset.page);
      load();
      container.scrollIntoView({ behavior: 'smooth', block: 'start' });
      return;
    }
    if (event.target.closest('[data-retry]')) {
      load();
      return;
    }
    const actionBtn = event.target.closest('[data-action][data-id]');
    if (actionBtn && cfg.onAction) {
      const row = rowsById.get(actionBtn.dataset.id);
      try {
        await cfg.onAction(actionBtn.dataset.action, Number(actionBtn.dataset.id), row, list);
      } catch (error) {
        toast(error.message || 'Action failed.', 'error');
      }
    }
  });

  const list = {
    state,
    reload: () => load(),
    changed: () => {
      window.dispatchEvent(new CustomEvent('bh:data-changed'));
      return load();
    },
  };

  load().then(() => cfg.onReady?.(list));
  return list;
}

/** Icon button used in table action columns. */
export function actionButton(action, id, iconName, label, tone = '') {
  return html`<button type="button" class="icon-btn ${tone}" data-action="${action}" data-id="${id}" title="${label}" aria-label="${label}">${icon(iconName)}</button>`;
}

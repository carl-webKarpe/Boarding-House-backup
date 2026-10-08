/* ==========================================================================
   Browse Rooms (php/browse-rooms.php) - the student room marketplace.

   Everything comes from the database:
     api/rooms.php         rooms (newest first), details, change fingerprint
     api/reservations.php  reserve a room, my reservations, cancel
     api/inquiries.php     contact the landlord, my messages and replies

   Vanilla JS, no build step. All text from the database is escaped with
   esc() before it is put into the page.
   ========================================================================== */
(function () {
  "use strict";

  const API = "../api/";
  const PER_PAGE = 6;
  const POLL_MS = 30000;
  const body = document.body;
  const me = {
    loggedIn: body.dataset.loggedIn === "1",
    role: body.dataset.role || "",
    name: body.dataset.name || "",
    email: body.dataset.email || "",
    phone: body.dataset.phone || "",
  };
  const isTenant = me.loggedIn && me.role === "tenant";

  const $ = (sel, root = document) => root.querySelector(sel);
  const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));
  const ESC = { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;", "`": "&#96;" };
  const esc = (v) => String(v ?? "").replace(/[&<>"'`]/g, (c) => ESC[c]);
  const peso = (v) => "₱" + Number(v || 0).toLocaleString("en-PH", { maximumFractionDigits: 0 });
  const plural = (n, word) => `${n} ${word}${n === 1 ? "" : "s"}`;
  const csrf = () => $('meta[name="csrf-token"]')?.content || "";

  /* ---------------------------------------------------------------- *
   * Icons
   * ---------------------------------------------------------------- */
  const ICONS = {
    pin: '<path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/>',
    school: '<path d="M22 10 12 5 2 10l10 5 10-5Z"/><path d="M6 12v5c3 2 9 2 12 0v-5"/>',
    users: '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
    bed: '<path d="M2 4v16"/><path d="M2 8h18a2 2 0 0 1 2 2v10"/><path d="M2 17h20"/><path d="M6 8v9"/>',
    check: '<path d="M20 6 9 17l-5-5"/>',
    shield: '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/><path d="m9 12 2 2 4-4"/>',
    phone: '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.79 19.79 0 0 1 2.12 4.18 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92Z"/>',
    mail: '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 6L2 7"/>',
    message: '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2Z"/>',
    calendar: '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
    info: '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/>',
    x: '<path d="M18 6 6 18M6 6l12 12"/>',
    left: '<path d="m15 18-6-6 6-6"/>',
    right: '<path d="m9 18 6-6-6-6"/>',
    image: '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.09-3.09a2 2 0 0 0-2.82 0L6 21"/>',
    clock: '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
    ruler: '<path d="M21.3 15.3a2.4 2.4 0 0 1 0 3.4l-2.6 2.6a2.4 2.4 0 0 1-3.4 0L2.7 8.7a2.4 2.4 0 0 1 0-3.4l2.6-2.6a2.4 2.4 0 0 1 3.4 0Z"/><path d="m7.5 10.5 2-2M10.5 13.5l2-2M13.5 16.5l2-2"/>',
    alert: '<path d="m21.73 18-8-14a2 2 0 0 0-3.46 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4M12 17h.01"/>',
    map: '<path d="m3 6 6-3 6 3 6-3v15l-6 3-6-3-6 3Z"/><path d="M9 3v15M15 6v15"/>',
  };
  const icon = (name, cls = "h-4 w-4") =>
    `<svg class="${cls}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${ICONS[name] || ICONS.info}</svg>`;

  const BADGES = { available: "Available", reserved: "Reserved", full: "Full" };
  const STATUS_LABELS = { pending: "Pending", approved: "Approved", rejected: "Rejected", cancelled: "Cancelled", completed: "Completed", new: "Sent", read: "Seen", replied: "Replied", closed: "Closed" };
  const badge = (key, label) => `<span class="badge badge-${esc(key)}">${esc(label || BADGES[key] || STATUS_LABELS[key] || key)}</span>`;

  /* ---------------------------------------------------------------- *
   * API
   * ---------------------------------------------------------------- */
  class ApiError extends Error {
    constructor(message, status, errors) { super(message); this.status = status; this.errors = errors || {}; }
  }

  async function api(method, endpoint, { query, data } = {}) {
    const url = new URL(API + endpoint, window.location.href);
    Object.entries(query || {}).forEach(([k, v]) => { if (v !== "" && v !== null && v !== undefined) url.searchParams.set(k, v); });
    const headers = { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" };
    if (method !== "GET") { headers["Content-Type"] = "application/json"; headers["X-CSRF-Token"] = csrf(); }
    let res;
    try {
      res = await fetch(url, { method, headers, credentials: "same-origin", body: data ? JSON.stringify(data) : undefined });
    } catch {
      throw new ApiError("Cannot reach the server. Check your connection and try again.", 0);
    }
    let json = null;
    try { json = await res.json(); } catch { /* handled below */ }
    if (!res.ok || !json || !json.success) throw new ApiError(json?.message || `Request failed (HTTP ${res.status}).`, res.status, json?.errors);
    return json;
  }

  /* ---------------------------------------------------------------- *
   * Toasts
   * ---------------------------------------------------------------- */
  function toast(message, type = "success") {
    const root = $("#toastRoot");
    const el = document.createElement("div");
    el.className = `toast ${type === "error" ? "bg-rose-600" : type === "info" ? "bg-brand" : "bg-emerald-600"}`;
    el.setAttribute("role", type === "error" ? "alert" : "status");
    el.innerHTML = `${icon(type === "error" ? "alert" : type === "info" ? "info" : "check", "mt-0.5 h-4 w-4 shrink-0")}<span>${esc(message)}</span>`;
    root.appendChild(el);
    setTimeout(() => { el.classList.add("leaving"); setTimeout(() => el.remove(), 220); }, type === "error" ? 6000 : 3800);
  }

  /* ---------------------------------------------------------------- *
   * Dialogs (modal on desktop, bottom sheet on phones) and drawer
   * ---------------------------------------------------------------- */
  function openDialog({ title, subtitle = "", content, size = "max-w-2xl", drawer = false, onClose }) {
    const previous = document.activeElement;
    const wrap = document.createElement("div");
    wrap.className = drawer ? "drawer-backdrop" : "dialog-backdrop";
    const id = "dlg" + Date.now();
    wrap.innerHTML = `
      <div class="${drawer ? "drawer" : `dialog ${size}`}" role="dialog" aria-modal="true" aria-labelledby="${id}">
        <div class="flex items-start gap-3 border-b border-brand/10 bg-white px-5 py-4 sm:px-6">
          <div class="min-w-0 flex-1">
            <h2 id="${id}" class="font-display text-lg font-semibold text-brand">${esc(title)}</h2>
            ${subtitle ? `<p class="mt-0.5 truncate text-sm text-muted">${esc(subtitle)}</p>` : ""}
          </div>
          <button type="button" class="grid h-9 w-9 shrink-0 place-items-center rounded-full text-muted transition hover:bg-brand/5 hover:text-brand" data-close aria-label="Close">${icon("x", "h-5 w-5")}</button>
        </div>
        <div class="flex-1 overflow-y-auto" data-dialog-body></div>
      </div>`;
    const bodyEl = $("[data-dialog-body]", wrap);
    if (typeof content === "string") bodyEl.innerHTML = content;
    let closed = false;
    const close = () => {
      if (closed) return;
      closed = true;
      document.removeEventListener("keydown", onKey);
      wrap.remove();
      if (!$(".dialog-backdrop, .drawer-backdrop")) document.body.style.overflow = "";
      previous?.focus?.();
      onClose?.();
    };
    const onKey = (e) => {
      const top = $$(".dialog-backdrop, .drawer-backdrop").pop();
      if (top !== wrap) return;
      if (e.key === "Escape") close();
      if (e.key === "Tab") {
        const f = $$('a[href], button:not([disabled]), input:not([disabled]):not([type=hidden]), select, textarea, iframe, [tabindex]:not([tabindex="-1"])', wrap);
        if (!f.length) return;
        if (e.shiftKey && document.activeElement === f[0]) { f[f.length - 1].focus(); e.preventDefault(); }
        else if (!e.shiftKey && document.activeElement === f[f.length - 1]) { f[0].focus(); e.preventDefault(); }
      }
    };
    wrap.addEventListener("mousedown", (e) => { if (e.target === wrap) close(); });
    wrap.addEventListener("click", (e) => { if (e.target.closest("[data-close]")) close(); });
    document.addEventListener("keydown", onKey);
    $("#dialogRoot").appendChild(wrap);
    document.body.style.overflow = "hidden";
    $("[data-close]", wrap).focus();
    return { el: wrap, body: bodyEl, close, setContent: (html) => { bodyEl.innerHTML = html; bodyEl.scrollTop = 0; } };
  }

  function confirmBox({ title, message, confirmText = "Confirm", danger = false }) {
    return new Promise((resolve) => {
      let ok = false;
      const d = openDialog({
        title,
        size: "max-w-md",
        content: `<div class="p-5 sm:p-6"><p class="text-sm leading-relaxed text-muted">${esc(message)}</p>
          <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <button type="button" class="btn-outline" data-close>Go back</button>
            <button type="button" class="${danger ? "btn-primary !bg-rose-600 hover:!bg-rose-700" : "btn-primary"}" data-yes>${esc(confirmText)}</button></div></div>`,
        onClose: () => resolve(ok),
      });
      $("[data-yes]", d.el).addEventListener("click", () => { ok = true; d.close(); });
    });
  }

  async function withBusy(button, task, label = "Sending…") {
    const original = button.innerHTML;
    button.disabled = true;
    button.innerHTML = `<span class="h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent"></span> ${esc(label)}`;
    try { return await task(); } finally { button.disabled = false; button.innerHTML = original; }
  }

  function showFieldErrors(form, errors) {
    $$(".field-error", form).forEach((el) => el.remove());
    $$(".is-invalid", form).forEach((el) => el.classList.remove("is-invalid"));
    let first = null;
    Object.entries(errors || {}).forEach(([name, msg]) => {
      const input = form.elements[name];
      if (!input) return;
      input.classList.add("is-invalid");
      input.insertAdjacentHTML("afterend", `<p class="field-error">${esc(msg)}</p>`);
      first = first || input;
    });
    first?.focus();
    return Boolean(first);
  }

  /* ---------------------------------------------------------------- *
   * Gallery (main photo + clickable thumbnails)
   * ---------------------------------------------------------------- */
  function galleryHtml(images, { height = "h-64 sm:h-72 md:h-80", alt = "" } = {}) {
    if (!images.length) {
      return `<div class="gallery-main grid ${height} place-items-center text-brand/40">${icon("image", "h-10 w-10")}<span class="sr-only">No photos yet</span></div>`;
    }
    return `
      <div class="flex h-full flex-col" data-gallery>
        <div class="gallery-main ${height}">
          <img src="${esc(images[0])}" alt="${esc(alt)}" class="h-full w-full object-cover" data-gallery-main loading="lazy" />
          ${images.length > 1 ? `
            <button type="button" class="gallery-nav left-3" data-gallery-step="-1" aria-label="Previous photo">${icon("left")}</button>
            <button type="button" class="gallery-nav right-3" data-gallery-step="1" aria-label="Next photo">${icon("right")}</button>
            <span class="absolute bottom-3 right-3 rounded-full bg-brand/80 px-2.5 py-1 text-[11px] font-semibold text-white" data-gallery-count>1 / ${images.length}</span>` : ""}
        </div>
        ${images.length > 1 ? `<div class="flex gap-2 overflow-x-auto bg-white p-3">${images.map((src, i) => `
          <button type="button" class="gallery-thumb ${i === 0 ? "is-active" : ""}" data-gallery-thumb="${i}" aria-label="Show photo ${i + 1} of ${images.length}">
            <img src="${esc(src)}" alt="" class="h-full w-full object-cover" loading="lazy" />
          </button>`).join("")}</div>` : ""}
      </div>`;
  }

  // One delegated handler drives every gallery on the page and in dialogs.
  document.addEventListener("click", (e) => {
    const thumb = e.target.closest("[data-gallery-thumb]");
    const step = e.target.closest("[data-gallery-step]");
    if (!thumb && !step) return;
    const gallery = (thumb || step).closest("[data-gallery]");
    const thumbs = $$("[data-gallery-thumb]", gallery);
    const current = thumbs.findIndex((t) => t.classList.contains("is-active"));
    const index = thumb ? Number(thumb.dataset.galleryThumb) : (current + Number(step.dataset.galleryStep) + thumbs.length) % thumbs.length;
    const main = $("[data-gallery-main]", gallery);
    const src = $("img", thumbs[index]).getAttribute("src");
    main.style.opacity = "0";
    setTimeout(() => { main.src = src; main.style.opacity = "1"; }, 160);
    thumbs.forEach((t, i) => t.classList.toggle("is-active", i === index));
    thumbs[index].scrollIntoView({ block: "nearest", inline: "nearest", behavior: "smooth" });
    const counter = $("[data-gallery-count]", gallery);
    if (counter) counter.textContent = `${index + 1} / ${thumbs.length}`;
  });

  /* ---------------------------------------------------------------- *
   * Room cards
   * ---------------------------------------------------------------- */
  const distanceText = (room) => {
    if (room.house.distance_note) return room.house.distance_note;
    if (room.distance_m === null || room.distance_m === undefined) return "";
    return room.distance_m < 1000 ? `${room.distance_m} m from ${room.school}` : `${(room.distance_m / 1000).toFixed(1)} km from ${room.school}`;
  };
  const isShared = (room) => room.room_type !== "solo" && room.room_type !== "studio";
  const priceLine = (room) => `${peso(room.price)}<span class="text-sm font-medium text-muted"> / ${isShared(room) ? "person / month" : "month"}</span>`;
  const slotsLine = (room) => {
    if (room.availability === "full") return "No free slot right now";
    const free = `${plural(room.open_slots, "slot")} free of ${room.capacity}`;
    return room.availability === "reserved" ? `${free} · reservations pending` : free;
  };
  const timeAgo = (value) => {
    const d = new Date(String(value).replace(" ", "T"));
    const s = Math.round((Date.now() - d.getTime()) / 1000);
    if (!Number.isFinite(s)) return "";
    if (s < 60) return "just now";
    for (const [u, n] of [["year", 31536000], ["month", 2592000], ["week", 604800], ["day", 86400], ["hour", 3600], ["minute", 60]]) {
      const a = Math.floor(s / n);
      if (a >= 1) return `${a} ${u}${a > 1 ? "s" : ""} ago`;
    }
    return "just now";
  };
  const fmtDate = (value) => (value ? new Date(String(value).replace(" ", "T")).toLocaleDateString("en-PH", { year: "numeric", month: "short", day: "numeric" }) : "—");

  function roomCard(room) {
    const distance = distanceText(room);
    const amenities = room.amenities.slice(0, 6);
    const more = room.amenities.length - amenities.length;
    const canReserve = room.availability !== "full";
    return `
      <article class="room-card" data-room="${room.id}" aria-labelledby="room-${room.id}-title">
        <div class="order-2 flex flex-col p-5 sm:p-6 md:order-1">
          <div class="flex flex-wrap items-center gap-2">
            ${badge(room.availability)}
            <span class="text-xs text-muted">${esc(room.room_type_label)} · updated ${esc(timeAgo(room.updated_at))}</span>
          </div>
          <h3 id="room-${room.id}-title" class="mt-3 font-display text-xl font-semibold leading-snug text-brand">${esc(room.house.name)}</h3>
          <p class="text-sm font-medium text-ink">${esc(room.room_number)}</p>
          <ul class="mt-3 space-y-1.5 text-sm text-muted">
            <li class="flex items-start gap-2">${icon("pin", "mt-0.5 h-4 w-4 shrink-0 text-brand")}<span>${esc(room.house.location || room.house.address)}</span></li>
            ${distance ? `<li class="flex items-start gap-2">${icon("school", "mt-0.5 h-4 w-4 shrink-0 text-brand")}<span>${esc(distance)}</span></li>` : ""}
            <li class="flex items-start gap-2">${icon("users", "mt-0.5 h-4 w-4 shrink-0 text-brand")}<span>${esc(slotsLine(room))}</span></li>
          </ul>
          <p class="mt-4 font-display text-2xl font-semibold text-ink">${priceLine(room)}</p>
          ${amenities.length ? `<ul class="mt-4 flex flex-wrap gap-1.5" aria-label="Amenities">${amenities.map((a) => `<li class="chip">${icon("check", "h-3 w-3")}${esc(a)}</li>`).join("")}${more > 0 ? `<li class="chip bg-brand/5">+${more} more</li>` : ""}</ul>` : ""}
          <p class="mt-4 flex items-center gap-1.5 text-xs text-muted">${room.landlord.verified ? icon("shield", "h-4 w-4 text-emerald-600") : ""}Landlord: <strong class="font-semibold text-ink">${esc(room.landlord.name)}</strong>${room.landlord.verified ? " · Verified" : ""}</p>
          <div class="mt-auto flex flex-wrap gap-2 pt-5">
            <button type="button" class="btn-primary" data-details="${room.id}">More Details</button>
            <button type="button" class="btn-lime" data-reserve="${room.id}" ${canReserve ? "" : 'disabled title="This room is full"'}>${icon("calendar")} Reserve</button>
            <button type="button" class="btn-outline" data-contact="${room.id}">${icon("message")} Contact Landlord</button>
          </div>
        </div>
        <div class="order-1 md:order-2">${galleryHtml(room.images, { alt: `${room.house.name} ${room.room_number}` })}</div>
      </article>`;
  }

  function skeletonCards(n = 2) {
    return Array.from({ length: n }, () => `
      <div class="grid overflow-hidden rounded-3xl border border-brand/10 bg-white md:grid-cols-[1.05fr_1fr]" aria-hidden="true">
        <div class="space-y-3 p-6"><div class="skeleton h-5 w-24"></div><div class="skeleton h-7 w-3/4"></div><div class="skeleton h-4 w-1/2"></div>
          <div class="skeleton h-4 w-2/3"></div><div class="skeleton h-8 w-40"></div><div class="flex gap-2"><div class="skeleton h-10 w-28"></div><div class="skeleton h-10 w-24"></div></div></div>
        <div class="skeleton h-64 rounded-none md:h-full"></div>
      </div>`).join("");
  }

  /* ---------------------------------------------------------------- *
   * List state, filters, Load More, auto-update
   * ---------------------------------------------------------------- */
  const listEl = $("#roomList");
  const loadMoreBtn = $("#loadMore");
  const statusEl = $("#listStatus");
  const filtersForm = $("#filters");
  const rooms = new Map();
  let page = 1;
  let totalPages = 1;
  let version = null;
  let requestId = 0;

  function filterQuery() {
    const data = new FormData(filtersForm);
    return {
      q: String(data.get("q") || "").trim(),
      type: data.get("type") || "",
      max_price: data.get("max_price") || "",
      sort: data.get("sort") || "newest",
      available: data.get("available") ? "1" : "",
    };
  }

  function syncUrl(query) {
    const params = new URLSearchParams(Object.entries(query).filter(([k, v]) => v && !(k === "sort" && v === "newest")));
    history.replaceState(null, "", params.toString() ? `?${params}` : window.location.pathname);
  }

  function restoreFilters() {
    const params = new URLSearchParams(window.location.search);
    ["q", "type", "max_price", "sort"].forEach((name) => { if (params.has(name) && filtersForm.elements[name]) filtersForm.elements[name].value = params.get(name); });
    if (params.get("available") === "1") filtersForm.elements.available.checked = true;
  }

  function renderSummary(summary, total) {
    const chips = [
      `${plural(summary.rooms, "room")} listed`,
      `${summary.available} with free slots`,
      `${plural(summary.houses, "boarding house")}`,
    ];
    if (summary.min_price !== null) chips.push(`from ${peso(summary.min_price)}`);
    $("#summaryChips").innerHTML = chips.map((c) => `<li class="rounded-full bg-white/10 px-3 py-1.5">${esc(c)}</li>`).join("");
    $("#resultsTitle").textContent = total === summary.rooms ? `All rooms (${total})` : `${plural(total, "room")} found`;
  }

  async function loadRooms({ reset = false } = {}) {
    const id = ++requestId;
    const query = filterQuery();
    if (reset) {
      page = 1;
      rooms.clear();
      listEl.innerHTML = skeletonCards();
      listEl.setAttribute("aria-busy", "true");
      syncUrl(query);
    }
    loadMoreBtn.disabled = true;
    statusEl.textContent = reset ? "" : "Loading more rooms…";
    try {
      const res = await api("GET", "rooms.php", { query: { ...query, page, per_page: PER_PAGE } });
      if (id !== requestId) return;
      totalPages = res.meta.total_pages;
      if (reset) listEl.innerHTML = "";
      res.data.forEach((room) => rooms.set(room.id, room));
      listEl.insertAdjacentHTML("beforeend", res.data.map(roomCard).join(""));
      renderSummary(res.summary, res.meta.total);
      if (!res.meta.total) {
        const filtered = query.q || query.type || query.max_price || query.available;
        listEl.innerHTML = `
          <div class="rounded-3xl border border-dashed border-brand/20 bg-white px-6 py-16 text-center">
            <span class="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-mint text-brand">${icon("bed", "h-6 w-6")}</span>
            <p class="mt-4 font-display text-lg font-semibold text-brand">${filtered ? "No rooms match your search" : "No rooms are listed yet"}</p>
            <p class="mx-auto mt-1 max-w-md text-sm text-muted">${filtered ? "Try another barangay, a higher budget or turn off “Available only”." : "Landlords are adding their boarding houses. This page updates automatically when new rooms are published."}</p>
            ${filtered ? '<button type="button" class="btn-outline mt-5" data-clear-filters>Clear filters</button>' : ""}
          </div>`;
      }
      const shown = rooms.size;
      loadMoreBtn.classList.toggle("hidden", page >= totalPages);
      statusEl.textContent = res.meta.total ? (page >= totalPages ? `Showing all ${plural(res.meta.total, "room")}.` : `Showing ${shown} of ${res.meta.total} rooms.`) : "";
    } catch (error) {
      if (id !== requestId) return;
      if (reset) {
        listEl.innerHTML = `<div class="rounded-3xl border border-rose-200 bg-rose-50 px-6 py-12 text-center text-rose-700">
          <p class="font-semibold">Could not load rooms</p><p class="mt-1 text-sm">${esc(error.message)}</p>
          <button type="button" class="btn-outline mt-4" data-retry>Try again</button></div>`;
      } else {
        page -= 1;
        toast(error.message, "error");
      }
      statusEl.textContent = "";
    } finally {
      if (id === requestId) { loadMoreBtn.disabled = false; listEl.setAttribute("aria-busy", "false"); }
    }
  }

  let filterTimer;
  filtersForm.addEventListener("input", (e) => {
    clearTimeout(filterTimer);
    filterTimer = setTimeout(() => loadRooms({ reset: true }), e.target.name === "q" ? 350 : 0);
  });
  filtersForm.addEventListener("submit", (e) => { e.preventDefault(); loadRooms({ reset: true }); });
  loadMoreBtn.addEventListener("click", () => { page += 1; loadRooms(); });

  // Auto-update: the server sends a fingerprint of all listings; when a landlord
  // adds, edits or removes a room it changes and we offer to refresh.
  async function checkForUpdates() {
    if (document.visibilityState !== "visible") return;
    try {
      const res = await api("GET", "rooms.php", { query: { version: 1 } });
      if (version && res.data.version !== version) $("#updateBanner").classList.replace("hidden", "flex");
      version = res.data.version;
    } catch { /* try again later */ }
  }
  $("[data-refresh]").addEventListener("click", async () => {
    $("#updateBanner").classList.replace("flex", "hidden");
    await loadRooms({ reset: true });
    version = null;
    checkForUpdates();
  });
  document.addEventListener("visibilitychange", checkForUpdates);

  /* ---------------------------------------------------------------- *
   * More Details
   * ---------------------------------------------------------------- */
  async function getRoom(id) {
    const res = await api("GET", "rooms.php", { query: { id } });
    rooms.set(res.data.id, { ...rooms.get(res.data.id), ...res.data });
    return res.data;
  }

  function mapHtml(house) {
    if (house.latitude === null || house.longitude === null) {
      return house.map_url ? `<a href="${esc(house.map_url)}" target="_blank" rel="noopener" class="btn-outline btn-sm">${icon("map")} Open in Google Maps</a>` : "";
    }
    const lat = Number(house.latitude);
    const lng = Number(house.longitude);
    const bbox = [lng - 0.006, lat - 0.004, lng + 0.006, lat + 0.004].map((n) => n.toFixed(6)).join(",");
    const directions = house.map_url || `https://www.google.com/maps/search/?api=1&query=${lat},${lng}`;
    return `
      <iframe title="Map of ${esc(house.name)}" class="h-56 w-full rounded-2xl border-0 bg-brand/5" loading="lazy"
        src="https://www.openstreetmap.org/export/embed.html?bbox=${bbox}&layer=mapnik&marker=${lat.toFixed(6)},${lng.toFixed(6)}"></iframe>
      <a href="${esc(directions)}" target="_blank" rel="noopener" class="mt-2 inline-flex items-center gap-1.5 text-sm font-semibold text-brand hover:underline">${icon("map")} Open in Google Maps</a>`;
  }

  const fact = (label, value) => (value ? `<div class="rounded-2xl bg-cream p-3"><dt class="text-[11px] font-semibold uppercase tracking-wide text-muted">${esc(label)}</dt><dd class="mt-0.5 text-sm font-semibold text-ink">${value}</dd></div>` : "");

  async function openDetails(id) {
    const dlg = openDialog({ title: "Room details", content: `<div class="space-y-3 p-6">${skeletonCards(1)}</div>`, size: "max-w-4xl" });
    try {
      const room = await getRoom(id);
      const h = room.house;
      const l = room.landlord;
      dlg.el.querySelector("h2").textContent = h.name;
      dlg.setContent(`
        ${galleryHtml(room.images, { height: "h-64 sm:h-96", alt: `${h.name} ${room.room_number}` })}
        <div class="grid gap-6 p-5 sm:p-6 lg:grid-cols-[1.5fr_1fr]">
          <div>
            <div class="flex flex-wrap items-center gap-2">${badge(room.availability)}<span class="text-sm text-muted">${esc(room.room_number)} · ${esc(room.room_type_label)}</span></div>
            <p class="mt-3 font-display text-3xl font-semibold text-ink">${priceLine(room)}</p>
            ${room.deposit ? `<p class="text-sm text-muted">Deposit ${peso(room.deposit)}</p>` : ""}
            <dl class="mt-5 grid gap-2 sm:grid-cols-2">
              ${fact("Availability", esc(slotsLine(room)))}
              ${fact("Room type", esc(room.room_type_label))}
              ${fact("Barangay", esc(h.barangay))}
              ${fact("Nearby school", esc(h.nearby_school))}
              ${fact("Distance from school", esc(distanceText(room)))}
              ${room.size_sqm ? fact("Room size", `${esc(room.size_sqm)} m²`) : ""}
            </dl>
            <h3 class="mt-6 font-display text-base font-semibold text-brand">Amenities</h3>
            ${room.amenities.length ? `<ul class="mt-2 grid gap-2 sm:grid-cols-2">${room.amenities.map((a) => `<li class="flex items-center gap-2 text-sm text-ink"><span class="grid h-6 w-6 place-items-center rounded-full bg-mint text-brand">${icon("check", "h-3.5 w-3.5")}</span>${esc(a)}</li>`).join("")}</ul>` : '<p class="mt-1 text-sm text-muted">The landlord has not listed amenities yet.</p>'}
            ${room.description ? `<h3 class="mt-6 font-display text-base font-semibold text-brand">Description</h3><p class="mt-1 whitespace-pre-line text-sm leading-relaxed text-ink/80">${esc(room.description)}</p>` : ""}
            ${h.description && h.description !== room.description ? `<h3 class="mt-6 font-display text-base font-semibold text-brand">About the boarding house</h3><p class="mt-1 whitespace-pre-line text-sm leading-relaxed text-ink/80">${esc(h.description)}</p>` : ""}
            ${h.rules ? `<h3 class="mt-6 font-display text-base font-semibold text-brand">House rules</h3><p class="mt-1 whitespace-pre-line text-sm leading-relaxed text-ink/80">${esc(h.rules)}</p>` : ""}
          </div>
          <aside class="space-y-5">
            <div class="rounded-3xl border border-brand/10 p-4">
              <h3 class="font-display text-base font-semibold text-brand">Location</h3>
              <p class="mt-1 flex items-start gap-2 text-sm text-ink">${icon("pin", "mt-0.5 h-4 w-4 shrink-0 text-brand")}${esc(h.address)}</p>
              ${h.location_note ? `<p class="mt-1 pl-6 text-xs text-muted">${esc(h.location_note)}</p>` : ""}
              <div class="mt-3">${mapHtml(h)}</div>
            </div>
            <div class="rounded-3xl border border-brand/10 p-4">
              <h3 class="font-display text-base font-semibold text-brand">Landlord</h3>
              <div class="mt-3 flex items-center gap-3">
                ${l.photo ? `<img src="${esc(l.photo)}" alt="" class="h-12 w-12 rounded-2xl object-cover" />` : `<span class="grid h-12 w-12 place-items-center rounded-2xl bg-mint font-display text-lg font-semibold text-brand">${esc((l.name || "?").charAt(0))}</span>`}
                <div class="min-w-0"><p class="truncate font-semibold text-ink">${esc(l.name)}</p>
                  <p class="flex items-center gap-1 text-xs ${l.verified ? "text-emerald-700" : "text-muted"}">${l.verified ? `${icon("shield", "h-3.5 w-3.5")} Verified landlord` : "Landlord"}</p></div>
              </div>
              <ul class="mt-3 space-y-1 text-sm">
                ${l.contact_number ? `<li><a class="inline-flex items-center gap-2 font-medium text-brand hover:underline" href="tel:${esc(l.contact_number)}">${icon("phone")} ${esc(l.contact_number)}</a></li>` : ""}
                ${l.email ? `<li><a class="inline-flex items-center gap-2 font-medium text-brand hover:underline" href="mailto:${esc(l.email)}">${icon("mail")} ${esc(l.email)}</a></li>` : ""}
              </ul>
            </div>
            ${h.other_available_rooms ? `<p class="text-xs text-muted">${plural(h.other_available_rooms, "other room")} available in this boarding house.</p>` : ""}
          </aside>
        </div>
        <div class="sticky bottom-0 flex flex-col gap-2 border-t border-brand/10 bg-white px-5 py-4 sm:flex-row sm:justify-end sm:px-6">
          <button type="button" class="btn-outline" data-contact="${room.id}">${icon("message")} Contact Landlord</button>
          <button type="button" class="btn-lime" data-reserve="${room.id}" ${room.availability === "full" ? "disabled" : ""}>${icon("calendar")} ${room.availability === "full" ? "Room is full" : "Reserve / Book this room"}</button>
        </div>`);
    } catch (error) {
      dlg.setContent(`<div class="p-8 text-center"><p class="font-semibold text-rose-700">${esc(error.message)}</p><button type="button" class="btn-outline mt-4" data-close>Close</button></div>`);
      if (error.status === 404) loadRooms({ reset: true });
    }
  }

  /* ---------------------------------------------------------------- *
   * Reserve / Book
   * ---------------------------------------------------------------- */
  function loginPrompt(action) {
    openDialog({
      title: "Log in to continue",
      size: "max-w-md",
      content: `<div class="p-6 text-center">
        <span class="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-mint text-brand">${icon("calendar", "h-6 w-6")}</span>
        <p class="mt-4 text-sm text-muted">${esc(action)} needs a student account, so the landlord knows who is asking and you can follow the status in “My Reservations”.</p>
        <div class="mt-6 flex flex-col gap-2 sm:flex-row sm:justify-center">
          <a href="../html/loginform.html" class="btn-primary">Log in</a>
          <a href="../html/register-tenant.html" class="btn-outline">Create a student account</a>
        </div></div>`,
    });
  }

  const todayIso = () => { const d = new Date(); d.setMinutes(d.getMinutes() - d.getTimezoneOffset()); return d.toISOString().slice(0, 10); };

  // Reserve / Contact replace the details window instead of stacking on top of it.
  const closeDialogs = () => $$(".dialog-backdrop [data-close]").reverse().forEach((b) => b.click());

  async function openReserve(id) {
    closeDialogs();
    if (!me.loggedIn) { loginPrompt("Reserving a room"); return; }
    if (!isTenant) { toast("Only student (tenant) accounts can reserve rooms.", "error"); return; }
    let room;
    try { room = await getRoom(id); } catch (error) { toast(error.message, "error"); return; }
    if (room.availability === "full") { toast("Sorry, this room is already full.", "error"); return; }
    const maxOccupants = Math.max(1, room.open_slots);

    const dlg = openDialog({
      title: "Reserve / Book a room",
      subtitle: `${room.house.name} · ${room.room_number}`,
      content: `
        <form class="grid gap-4 p-5 sm:grid-cols-2 sm:p-6" novalidate data-reserve-form>
          <div><label class="field-label" for="rs-house">Boarding house</label><input id="rs-house" class="field bg-cream" value="${esc(room.house.name)}" readonly /></div>
          <div><label class="field-label" for="rs-room">Room</label><input id="rs-room" class="field bg-cream" value="${esc(`${room.room_number} · ${room.room_type_label} · ${peso(room.price)}`)}" readonly /></div>
          <div class="sm:col-span-2"><label class="field-label" for="rs-name">Full name</label><input id="rs-name" name="contact_name" class="field" maxlength="160" required value="${esc(me.name)}" autocomplete="name" /></div>
          <div><label class="field-label" for="rs-phone">Contact number</label><input id="rs-phone" name="contact_number" type="tel" class="field" maxlength="20" required placeholder="09XXXXXXXXX" value="${esc(me.phone)}" autocomplete="tel" /></div>
          <div><label class="field-label" for="rs-email">Email address</label><input id="rs-email" name="contact_email" type="email" class="field" maxlength="150" required value="${esc(me.email)}" autocomplete="email" /></div>
          <div><label class="field-label" for="rs-date">Preferred move-in date</label><input id="rs-date" name="move_in_date" type="date" class="field" required min="${todayIso()}" /></div>
          <div><label class="field-label" for="rs-occ">Number of occupants</label>
            <input id="rs-occ" name="occupants_count" type="number" class="field" min="1" max="${maxOccupants}" value="1" required ${maxOccupants === 1 ? "readonly" : ""} />
            <p class="mt-1 text-xs text-muted">${plural(room.open_slots, "slot")} free in this room.</p></div>
          <div class="sm:col-span-2"><label class="field-label" for="rs-msg">Message to the landlord <span class="font-normal text-muted">(optional)</span></label>
            <textarea id="rs-msg" name="message" rows="3" maxlength="1000" class="field" placeholder="e.g. I'm a first-year SIIT student. Is the deposit refundable?"></textarea></div>
          <p class="flex items-start gap-2 rounded-2xl bg-mint/60 p-3 text-xs text-brand sm:col-span-2">${icon("info", "mt-0.5 h-4 w-4 shrink-0")}Your reservation is sent as <strong>Pending</strong>. The landlord approves or rejects it, and you can follow the status in My Reservations.</p>
          <div class="flex flex-col-reverse gap-2 sm:col-span-2 sm:flex-row sm:justify-end">
            <button type="button" class="btn-outline" data-close>Cancel</button>
            <button type="submit" class="btn-primary">Review reservation</button>
          </div>
        </form>`,
    });
    const form = $("[data-reserve-form]", dlg.el);
    form.addEventListener("submit", async (e) => {
      e.preventDefault();
      const data = Object.fromEntries(new FormData(form).entries());
      Object.keys(data).forEach((k) => { data[k] = String(data[k]).trim(); });
      const errors = {};
      if (!data.contact_name) errors.contact_name = "Enter your full name.";
      if (!/^(09\d{9}|\+639\d{9})$/.test(data.contact_number.replace(/[\s-]/g, ""))) errors.contact_number = "Enter a mobile number like 09171234567.";
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(data.contact_email)) errors.contact_email = "Enter a valid email address.";
      if (!data.move_in_date) errors.move_in_date = "Choose your move-in date.";
      else if (data.move_in_date < todayIso()) errors.move_in_date = "The date cannot be in the past.";
      const occ = Number(data.occupants_count);
      if (!(occ >= 1 && occ <= maxOccupants)) errors.occupants_count = `Between 1 and ${maxOccupants}.`;
      if (showFieldErrors(form, errors)) return;

      const ok = await confirmBox({
        title: "Send this reservation?",
        message: `${room.house.name}, ${room.room_number} for ${plural(occ, "person")}, moving in ${fmtDate(data.move_in_date)}. Rent ${peso(room.price)}${isShared(room) ? " per person" : ""} a month. The landlord will review your request.`,
        confirmText: "Yes, send reservation",
      });
      if (!ok) return;
      try {
        const res = await withBusy($('[type="submit"]', form), () => api("POST", "reservations.php", { data: { ...data, room_id: room.id } }));
        const r = res.data;
        dlg.setContent(`
          <div class="p-6 text-center sm:p-8">
            <span class="mx-auto grid h-16 w-16 place-items-center rounded-full bg-mint text-brand">${icon("check", "h-8 w-8")}</span>
            <h3 class="mt-4 font-display text-xl font-semibold text-brand">Reservation sent</h3>
            <p class="mt-1 text-sm text-muted">${esc(res.message)}</p>
            <dl class="mx-auto mt-5 grid max-w-sm gap-2 text-left">
              ${fact("Reference", esc(r.code))}
              ${fact("Status", badge(r.status))}
              ${fact("Room", esc(`${r.house.name} · ${r.room.room_number}`))}
            </dl>
            <div class="mt-6 flex flex-col gap-2 sm:flex-row sm:justify-center">
              <button type="button" class="btn-outline" data-close>Keep browsing</button>
              <button type="button" class="btn-primary" data-open-reservations>View My Reservations</button>
            </div>
          </div>`);
        loadRooms({ reset: true });
      } catch (error) {
        showFieldErrors(form, error.errors);
        toast(error.message, "error");
      }
    });
  }

  /* ---------------------------------------------------------------- *
   * Contact Landlord
   * ---------------------------------------------------------------- */
  async function openContact(id) {
    closeDialogs();
    let room;
    try { room = await getRoom(id); } catch (error) { toast(error.message, "error"); return; }
    const l = room.landlord;
    const dlg = openDialog({
      title: "Contact Landlord",
      subtitle: `${room.house.name} · ${room.room_number}`,
      content: `
        <div class="p-5 sm:p-6">
          <div class="flex items-center gap-3 rounded-3xl bg-cream p-4">
            ${l.photo ? `<img src="${esc(l.photo)}" alt="" class="h-14 w-14 rounded-2xl object-cover" />` : `<span class="grid h-14 w-14 place-items-center rounded-2xl bg-mint font-display text-xl font-semibold text-brand">${esc((l.name || "?").charAt(0))}</span>`}
            <div class="min-w-0 flex-1">
              <p class="truncate font-semibold text-ink">${esc(l.name)} ${l.verified ? `<span class="ml-1 inline-flex items-center gap-1 text-xs font-medium text-emerald-700">${icon("shield", "h-3.5 w-3.5")}Verified</span>` : ""}</p>
              <p class="truncate text-sm text-muted">${esc(room.house.name)} · ${esc(room.house.location)}</p>
              <p class="mt-1 flex flex-wrap gap-x-3 text-sm">
                ${l.contact_number ? `<a class="inline-flex items-center gap-1 font-semibold text-brand hover:underline" href="tel:${esc(l.contact_number)}">${icon("phone")} ${esc(l.contact_number)}</a>` : ""}
                ${l.email ? `<a class="inline-flex items-center gap-1 font-semibold text-brand hover:underline" href="mailto:${esc(l.email)}">${icon("mail")} Email</a>` : ""}
              </p>
            </div>
          </div>
          <form class="mt-5 grid gap-4 sm:grid-cols-2" novalidate data-contact-form>
            <div class="sm:col-span-2"><label class="field-label" for="ct-name">Your name</label><input id="ct-name" name="sender_name" class="field" maxlength="160" required value="${esc(me.name)}" autocomplete="name" /></div>
            <div><label class="field-label" for="ct-email">Email address</label><input id="ct-email" name="sender_email" type="email" class="field" maxlength="150" required value="${esc(me.email)}" autocomplete="email" /></div>
            <div><label class="field-label" for="ct-phone">Contact number <span class="font-normal text-muted">(optional)</span></label><input id="ct-phone" name="sender_phone" type="tel" class="field" maxlength="20" value="${esc(me.phone)}" autocomplete="tel" /></div>
            <div class="sm:col-span-2"><label class="field-label" for="ct-msg">Message</label>
              <textarea id="ct-msg" name="message" rows="4" maxlength="2000" class="field" required>Hello, I'm interested in ${esc(room.room_number)} at ${esc(room.house.name)}. Is it still available?</textarea></div>
            <div class="flex flex-col-reverse gap-2 sm:col-span-2 sm:flex-row sm:justify-end">
              <button type="button" class="btn-outline" data-close>Cancel</button>
              <button type="submit" class="btn-primary">${icon("message")} Send message</button>
            </div>
          </form>
        </div>`,
    });
    const form = $("[data-contact-form]", dlg.el);
    form.addEventListener("submit", async (e) => {
      e.preventDefault();
      const data = Object.fromEntries(new FormData(form).entries());
      Object.keys(data).forEach((k) => { data[k] = String(data[k]).trim(); });
      const errors = {};
      if (!data.sender_name) errors.sender_name = "Enter your name.";
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(data.sender_email)) errors.sender_email = "Enter a valid email address.";
      if (data.sender_phone && !/^(09\d{9}|\+639\d{9})$/.test(data.sender_phone.replace(/[\s-]/g, ""))) errors.sender_phone = "Enter a mobile number like 09171234567, or leave it empty.";
      if (data.message.length < 5) errors.message = "Please write your question.";
      if (showFieldErrors(form, errors)) return;
      try {
        const res = await withBusy($('[type="submit"]', form), () => api("POST", "inquiries.php", { data: { ...data, room_id: room.id } }));
        dlg.close();
        toast(res.message);
      } catch (error) {
        showFieldErrors(form, error.errors);
        toast(error.message, "error");
      }
    });
  }

  /* ---------------------------------------------------------------- *
   * My Reservations & messages (students)
   * ---------------------------------------------------------------- */
  async function openMyReservations(tab = "reservations") {
    if (!isTenant) { loginPrompt("Seeing your reservations"); return; }
    closeDialogs();
    const drawer = openDialog({ title: "My Reservations", subtitle: "Status updates from landlords", drawer: true, content: `<div class="space-y-3 p-5">${'<div class="skeleton h-28"></div>'.repeat(3)}</div>` });

    async function draw() {
      try {
        const [reservations, messages] = await Promise.all([api("GET", "reservations.php"), api("GET", "inquiries.php")]);
        const tabBtn = (key, label, count) => `<button type="button" role="tab" aria-selected="${tab === key}" data-tab="${key}" class="flex-1 rounded-full px-4 py-2 text-sm font-semibold transition ${tab === key ? "bg-brand text-white" : "text-brand hover:bg-brand/5"}">${label} <span class="opacity-70">(${count})</span></button>`;
        const reservationList = reservations.data.length ? reservations.data.map((r) => `
          <article class="overflow-hidden rounded-3xl border border-brand/10 bg-white">
            <div class="flex gap-3 p-4">
              ${r.room.photo ? `<img src="${esc(r.room.photo)}" alt="" class="h-20 w-20 shrink-0 rounded-2xl object-cover" />` : `<span class="grid h-20 w-20 shrink-0 place-items-center rounded-2xl bg-mint text-brand">${icon("bed", "h-6 w-6")}</span>`}
              <div class="min-w-0 flex-1">
                <div class="flex items-start justify-between gap-2"><p class="truncate font-semibold text-ink">${esc(r.house.name)}</p>${badge(r.status)}</div>
                <p class="text-sm text-muted">${esc(r.room.room_number)} · ${peso(r.room.price)}/mo</p>
                <p class="mt-1 text-xs text-muted">${esc(r.code)} · Move-in ${esc(fmtDate(r.move_in_date))} · ${plural(r.occupants_count, "person")}</p>
                <p class="text-xs text-muted">Sent ${esc(timeAgo(r.requested_at))}</p>
              </div>
            </div>
            ${r.landlord_note ? `<p class="mx-4 mb-3 rounded-2xl bg-mint/60 p-3 text-sm text-brand"><strong>Note:</strong> ${esc(r.landlord_note)}</p>` : ""}
            ${r.status === "pending" ? `<div class="border-t border-brand/10 px-4 py-2 text-right"><button type="button" class="btn-ghost btn-sm text-rose-600 hover:bg-rose-50 hover:text-rose-700" data-cancel-reservation="${r.id}" data-code="${esc(r.code)}">Cancel reservation</button></div>` : ""}
          </article>`).join("") : `<div class="rounded-3xl border border-dashed border-brand/20 bg-white p-8 text-center text-sm text-muted">You have no reservations yet. Find a room and press <strong>Reserve</strong>.</div>`;
        const messageList = messages.data.length ? messages.data.map((m) => `
          <article class="rounded-3xl border border-brand/10 bg-white p-4">
            <div class="flex items-start justify-between gap-2"><p class="font-semibold text-ink">${esc(m.house_name)}${m.room_number ? ` · ${esc(m.room_number)}` : ""}</p>${badge(m.status)}</div>
            <p class="mt-2 whitespace-pre-line rounded-2xl bg-cream p-3 text-sm text-ink/80">${esc(m.message)}</p>
            <p class="mt-1 text-[11px] text-muted">You · ${esc(timeAgo(m.created_at))}</p>
            ${m.reply ? `<p class="mt-3 whitespace-pre-line rounded-2xl bg-mint/70 p-3 text-sm text-brand">${esc(m.reply)}</p><p class="mt-1 text-[11px] text-muted">${esc(m.landlord_name)} · ${esc(timeAgo(m.replied_at))}</p>` : '<p class="mt-2 text-xs text-muted">Waiting for the landlord’s reply.</p>'}
          </article>`).join("") : `<div class="rounded-3xl border border-dashed border-brand/20 bg-white p-8 text-center text-sm text-muted">No messages yet. Use <strong>Contact Landlord</strong> on a room to ask a question.</div>`;

        drawer.setContent(`
          <div class="sticky top-0 z-10 bg-cream px-5 pb-2 pt-4"><div class="flex gap-1 rounded-full bg-white p-1 shadow-card" role="tablist">
            ${tabBtn("reservations", "Reservations", reservations.data.length)}${tabBtn("messages", "Messages", messages.data.length)}</div></div>
          <div class="space-y-3 p-5 pt-3">${tab === "reservations" ? reservationList : messageList}</div>`);
      } catch (error) {
        drawer.setContent(`<div class="p-8 text-center text-sm text-rose-700">${esc(error.message)}</div>`);
      }
    }

    drawer.body.addEventListener("click", async (e) => {
      const tabBtn = e.target.closest("[data-tab]");
      if (tabBtn) { tab = tabBtn.dataset.tab; draw(); return; }
      const cancel = e.target.closest("[data-cancel-reservation]");
      if (cancel) {
        const ok = await confirmBox({ title: "Cancel this reservation?", message: `Reservation ${cancel.dataset.code} will be cancelled and the landlord will be told.`, confirmText: "Cancel reservation", danger: true });
        if (!ok) return;
        try {
          const res = await api("PUT", "reservations.php", { query: { id: cancel.dataset.cancelReservation, action: "cancel" }, data: {} });
          toast(res.message || "Reservation cancelled.");
          draw();
          loadRooms({ reset: true });
        } catch (error) { toast(error.message, "error"); }
      }
    });
    draw();
  }

  /* ---------------------------------------------------------------- *
   * Global click handling
   * ---------------------------------------------------------------- */
  document.addEventListener("click", (e) => {
    const t = e.target.closest("[data-details], [data-reserve], [data-contact], [data-open-reservations], [data-clear-filters], [data-retry]");
    if (!t) return;
    if (t.dataset.details) openDetails(Number(t.dataset.details));
    else if (t.dataset.reserve) openReserve(Number(t.dataset.reserve));
    else if (t.dataset.contact) openContact(Number(t.dataset.contact));
    else if (t.hasAttribute("data-open-reservations")) { closeMenu(); openMyReservations(); }
    else if (t.hasAttribute("data-clear-filters")) { filtersForm.reset(); loadRooms({ reset: true }); }
    else if (t.hasAttribute("data-retry")) loadRooms({ reset: true });
  });

  // Account menu
  const menuBtn = $("[data-menu-button]");
  const menuPanel = $("[data-menu-panel]");
  function closeMenu() { if (menuPanel) { menuPanel.classList.add("hidden"); menuBtn.setAttribute("aria-expanded", "false"); } }
  menuBtn?.addEventListener("click", (e) => {
    e.stopPropagation();
    const open = menuPanel.classList.toggle("hidden") === false;
    menuBtn.setAttribute("aria-expanded", String(open));
  });
  document.addEventListener("click", (e) => { if (menuPanel && !e.target.closest("[data-menu]")) closeMenu(); });
  document.addEventListener("keydown", (e) => { if (e.key === "Escape") closeMenu(); });

  $$("[data-year]").forEach((el) => { el.textContent = new Date().getFullYear(); });

  /* ---------------------------------------------------------------- *
   * Start
   * ---------------------------------------------------------------- */
  restoreFilters();
  loadRooms({ reset: true }).then(() => {
    const params = new URLSearchParams(window.location.search);
    if (params.get("room")) openDetails(Number(params.get("room")));
    if (params.get("reservations") === "1") openMyReservations();
  });
  checkForUpdates();
  setInterval(checkForUpdates, POLL_MS);
})();

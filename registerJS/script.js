/* ==========================================================================
   Boarding House Rental System — home.js
   Vanilla JS only. Drives the public Home Page; boarding house listings
   and statistics are loaded from the database through api/listings.php.
   ========================================================================== */

"use strict";

/* ---------------------------------------------------------------------- *
 * SIIT reference location — the anchor point for the whole location system.
 * Coordinates verified against Siargao Island Institute of Technology's
 * actual campus location in Dapa, Surigao del Norte (not a generic
 * Dapa/Siargao-wide point).
 * ---------------------------------------------------------------------- */
const SIIT = {
  name: "Siargao Island Institute of Technology (SIIT)",
  shortName: "SIIT",
  address: "National Highway, Dapa, Surigao del Norte, Philippines",
  coordinates: [9.760829, 126.047129],
};

/* ---------------------------------------------------------------------- *
 * Listings come from the database (api/listings.php). Only boarding houses
 * that an administrator approved in the Admin Dashboard are shown, so
 * adding, editing or approving a listing there updates this page.
 * ---------------------------------------------------------------------- */
let LISTINGS = [];

const FALLBACK_IMAGES = ["../Image/image2.jpg", "../Image/haven.jpg", "../Image/image4.jpg", "../Image/images.jpg", "../Image/image3.jpg", "../Image/BGINFO.jpg"];

/** Escapes text typed by landlords/admins before it goes into HTML. */
function esc(value) {
  return String(value ?? "").replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[c]);
}

async function loadListings() {
  const response = await fetch("../api/listings.php", { headers: { Accept: "application/json" } });
  const json = await response.json();
  if (!response.ok || !json.success) throw new Error(json.message || "Could not load listings.");

  LISTINGS = json.data.map((item, i) => ({
    ...item,
    id: String(item.id),
    img: item.img || FALLBACK_IMAGES[i % FALLBACK_IMAGES.length],
    distanceToSIIT: item.coordinates ? distanceMeters(SIIT.coordinates, item.coordinates) : Infinity,
  }));
  return json.stats || {};
}

/* ---------------------------------------------------------------------- *
 * Distance helpers — every "X m / X km from SIIT" figure on the site is
 * calculated from real coordinates via the Haversine formula, not typed
 * in by hand.
 * ---------------------------------------------------------------------- */
function distanceMeters(a, b) {
  const R = 6371000; // Earth's mean radius, meters
  const toRad = (deg) => (deg * Math.PI) / 180;
  const dLat = toRad(b[0] - a[0]);
  const dLon = toRad(b[1] - a[1]);
  const lat1 = toRad(a[0]);
  const lat2 = toRad(b[0]);
  const h = Math.sin(dLat / 2) ** 2 + Math.cos(lat1) * Math.cos(lat2) * Math.sin(dLon / 2) ** 2;
  return 2 * R * Math.asin(Math.sqrt(h));
}

function formatDistance(meters) {
  if (!Number.isFinite(meters)) return "Location not set";
  if (meters < 1000) return `${Math.round(meters / 10) * 10} m`;
  return `${(meters / 1000).toFixed(1)} km`;
}

function directionsUrl(item) {
  if (!item.coordinates) return `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(item.address || item.location)}`;
  // No origin specified on purpose: Google Maps automatically routes from
  // the visitor's current location (after a permission prompt) whether
  // opened in the browser or the Google Maps app.
  return `https://www.google.com/maps/dir/?api=1&destination=${item.coordinates[0]},${item.coordinates[1]}&travelmode=driving`;
}

function byDistance(a, b) {
  return a.distanceToSIIT - b.distanceToSIIT;
}

/* ---------------------------------------------------------------------- *
 * Listings rendering + filtering (Featured Listings preview grid)
 * ---------------------------------------------------------------------- */
function currency(amount) {
  return "\u20B1" + Number(amount).toLocaleString("en-PH", { maximumFractionDigits: 0 });
}

function distanceLabel(item) {
  return Number.isFinite(item.distanceToSIIT) ? `${formatDistance(item.distanceToSIIT)} from SIIT` : "Location not set";
}

function listingCardHTML(item) {
  const badgeClass = item.availability === "Available" ? "" : " listing-card__badge--soon";
  const isBedspace = item.roomType === "Bedspace";
  const roomsText = item.availability === "Coming Soon"
    ? "Opening soon"
    : isBedspace
      ? (item.openBeds ? `${item.openBeds} bed${item.openBeds === 1 ? "" : "s"} available` : "No beds open right now")
      : (item.rooms ? `${item.rooms} room${item.rooms === 1 ? "" : "s"} available` : "No rooms open right now");
  return `
    <article class="listing-card reveal is-visible" data-id="${esc(item.id)}">
      <div class="listing-card__img-wrap">
        <img class="listing-card__img" src="${esc(item.img)}" alt="${esc(item.name)}" loading="lazy" />
        <span class="listing-card__badge${badgeClass}">${esc(item.availability)}</span>
      </div>
      <div class="listing-card__body">
        <h3>${esc(item.name)}</h3>
        <span class="listing-card__loc">${esc(item.location)}</span>
        <span class="listing-card__price">${item.price === item.max_price ? "" : "from "}${currency(item.price)} <span>/ month</span></span>
        <span class="listing-card__meta">${esc(item.roomType)} &middot; ${roomsText}</span>
        <span class="listing-card__distance">\u{1F4CD} ${esc(distanceLabel(item))}</span>
        <span class="listing-card__amenities">${item.amenities.map(esc).join(" \u2022 ")}</span>
        <button type="button" class="listing-card__cta" data-view="${esc(item.id)}">View Details</button>
      </div>
    </article>
  `;
}

function renderListings(items) {
  const grid = document.getElementById("listingsGrid");
  const empty = document.getElementById("listingsEmpty");
  if (!grid) return;

  grid.innerHTML = items.map(listingCardHTML).join("");
  empty.classList.toggle("hidden", items.length > 0);

  // Wire up "View Details" buttons for the cards just rendered.
  grid.querySelectorAll("[data-view]").forEach((btn) => {
    btn.addEventListener("click", () => openListingModal(btn.getAttribute("data-view")));
  });
}

function applyFilters() {
  const location = document.getElementById("fLocation").value.trim().toLowerCase();
  const maxPrice = parseFloat(document.getElementById("fPrice").value);
  const roomType = document.getElementById("fType").value;
  const availability = document.getElementById("fAvailability").value;

  const filtered = LISTINGS.filter((item) => {
    if (location && !`${item.name} ${item.address}`.toLowerCase().includes(location)) return false;
    if (!isNaN(maxPrice) && maxPrice > 0 && item.price > maxPrice) return false;
    if (roomType && !item.roomTypes.includes(roomType)) return false;
    if (availability && item.availability !== availability) return false;
    return true;
  }).sort(byDistance);

  renderListings(filtered);

  const hint = document.getElementById("searchHint");
  hint.textContent = filtered.length
    ? `Showing ${filtered.length} boarding house${filtered.length === 1 ? "" : "s"} that match your search.`
    : "";

  document.getElementById("listings").scrollIntoView({ behavior: "smooth", block: "start" });
}

/* ---------------------------------------------------------------------- *
 * Photo slideshow inside "View Details". Photos come from the boarding
 * house's images in the Admin Dashboard (the cover photo is shown first).
 * ---------------------------------------------------------------------- */
function slideshowHTML(item, badgeClass) {
  const photos = item.images && item.images.length ? item.images : [item.img];
  const many = photos.length > 1;
  return `
    <div class="modal__media slideshow" tabindex="-1" aria-roledescription="carousel" aria-label="Photos of ${esc(item.name)}">
      <div class="slideshow__track">
        ${photos.map((src, i) => `
          <img class="slideshow__slide${i === 0 ? " is-active" : ""}" src="${esc(src)}"
               alt="${esc(item.name)} photo ${i + 1} of ${photos.length}" ${i === 0 ? "" : 'loading="lazy"'} />`).join("")}
      </div>
      <span class="listing-card__badge${badgeClass}">${esc(item.availability)}</span>
      ${many ? `
        <button type="button" class="slideshow__nav slideshow__nav--prev" data-slide="prev" aria-label="Previous photo">&#8249;</button>
        <button type="button" class="slideshow__nav slideshow__nav--next" data-slide="next" aria-label="Next photo">&#8250;</button>
        <span class="slideshow__count" aria-live="polite"><span data-slide-current>1</span> / ${photos.length}</span>
        <div class="slideshow__dots">
          ${photos.map((_, i) => `<button type="button" class="slideshow__dot${i === 0 ? " is-active" : ""}" data-slide-to="${i}" aria-label="Show photo ${i + 1}"></button>`).join("")}
        </div>` : ""}
    </div>
  `;
}

function initSlideshow(root) {
  if (!root) return null;
  const slides = Array.from(root.querySelectorAll(".slideshow__slide"));
  const dots = Array.from(root.querySelectorAll(".slideshow__dot"));
  const counter = root.querySelector("[data-slide-current]");
  let index = 0;
  let timer = null;

  // Drop photos that fail to load (e.g. a deleted file or an offline web link).
  slides.forEach((img) => img.addEventListener("error", () => {
    if (slides.filter((s) => !s.hidden).length > 1) {
      img.hidden = true;
      const dot = dots[slides.indexOf(img)];
      if (dot) dot.hidden = true;
      if (img.classList.contains("is-active")) step(1);
    } else {
      img.src = FALLBACK_IMAGES[0];
    }
  }, { once: true }));

  function visible() {
    return slides.map((s, i) => (s.hidden ? -1 : i)).filter((i) => i >= 0);
  }

  function go(target) {
    const list = visible();
    if (!list.length) return;
    index = list.includes(target) ? target : list[0];
    slides.forEach((s, i) => s.classList.toggle("is-active", i === index));
    dots.forEach((d, i) => d.classList.toggle("is-active", i === index));
    if (counter) counter.textContent = String(list.indexOf(index) + 1);
  }
  function step(offset) {
    const list = visible();
    if (list.length < 2) return;
    go(list[(list.indexOf(index) + offset + list.length) % list.length]);
  }
  const next = () => step(1);
  const prev = () => step(-1);

  const reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  function play() {
    stopTimer();
    if (slides.length > 1 && !reduceMotion) timer = window.setInterval(next, 4500);
  }
  function stopTimer() {
    if (timer) window.clearInterval(timer);
    timer = null;
  }

  root.addEventListener("click", (e) => {
    const nav = e.target.closest("[data-slide]");
    const dot = e.target.closest("[data-slide-to]");
    if (nav) nav.getAttribute("data-slide") === "next" ? next() : prev();
    if (dot) go(Number(dot.getAttribute("data-slide-to")));
    if (nav || dot) play();
  });
  root.addEventListener("mouseenter", stopTimer);
  root.addEventListener("mouseleave", play);

  // Swipe on phones and tablets.
  let startX = null;
  root.addEventListener("pointerdown", (e) => { startX = e.clientX; });
  root.addEventListener("pointerup", (e) => {
    if (startX === null) return;
    const dx = e.clientX - startX;
    startX = null;
    if (Math.abs(dx) > 40) { dx < 0 ? next() : prev(); play(); }
  });

  // Left / right arrow keys while the details window is open.
  const onKey = (e) => {
    if (e.key === "ArrowRight") { next(); play(); }
    if (e.key === "ArrowLeft") { prev(); play(); }
  };
  document.addEventListener("keydown", onKey);

  play();
  return {
    stop() {
      stopTimer();
      document.removeEventListener("keydown", onKey);
    },
  };
}

/* ---------------------------------------------------------------------- *
 * "View Details" modal
 * ---------------------------------------------------------------------- */
function openListingModal(id) {
  const item = LISTINGS.find((l) => l.id === id);
  if (!item) return;

  const overlay = document.getElementById("modalOverlay");
  const body = document.getElementById("modalBody");

  const badgeClass = item.availability === "Available" ? "" : " listing-card__badge--soon";
  const amenitiesHTML = item.amenities.map((a) => `<li>${esc(a)}</li>`).join("");
  const roomsHTML = item.roomList
    .map((r) => `<li><span>Room ${esc(r.room_number)} &middot; ${esc(r.type)}</span><span>${currency(r.price)}</span><span class="modal__room-status${r.status === "available" ? "" : " is-off"}">${
      r.status === "maintenance" ? "Coming soon" : r.open > 0 ? `${r.open} open` : "Full"}</span></li>`)
    .join("");
  const isBedspace = item.roomType === "Bedspace";

  body.innerHTML = `
    ${slideshowHTML(item, badgeClass)}
    <div class="modal__content">
      <h3 id="modalTitle">${esc(item.name)}</h3>
      <span class="listing-card__loc">${esc(item.address)}</span>
      ${item.description ? `<p class="modal__description">${esc(item.description)}</p>` : ""}

      <div class="modal__location">
        <div class="modal__location-heading">
          <div>
            <h4>Distance from SIIT</h4>
            <p>${Number.isFinite(item.distanceToSIIT) ? `${formatDistance(item.distanceToSIIT)} from Siargao Island Institute of Technology` : "The landlord has not pinned this location yet."}</p>
          </div>
          <a href="${esc(directionsUrl(item))}" target="_blank" rel="noopener" class="modal__map-link">Get Directions</a>
        </div>
        ${item.coordinates ? `<div id="roomMap" class="room-map" aria-label="Map showing ${esc(item.name)} near SIIT"></div>` : ""}
      </div>

      <div class="modal__price-row">
        <span class="listing-card__price">${item.price === item.max_price ? "" : "from "}${currency(item.price)} <span>/ month</span></span>
        <span class="modal__roomtype">${item.roomTypes.map(esc).join(", ")}</span>
      </div>

      <div class="modal__details-grid">
        <div class="modal__detail">
          <span class="modal__detail-label">${isBedspace ? "Beds Open" : "Rooms Open"}</span>
          <span class="modal__detail-value">${isBedspace ? `${item.openBeds} of ${item.totalBeds}` : `${item.rooms} of ${item.totalRooms}`}</span>
        </div>
        <div class="modal__detail">
          <span class="modal__detail-label">Availability</span>
          <span class="modal__detail-value">${esc(item.availability)}</span>
        </div>
        <div class="modal__detail">
          <span class="modal__detail-label">Landlord</span>
          <span class="modal__detail-value">${esc(item.landlord)}</span>
        </div>
        <div class="modal__detail">
          <span class="modal__detail-label">Contact</span>
          <span class="modal__detail-value">${esc(item.contact || "—")}</span>
        </div>
      </div>

      <div class="modal__amenities">
        <h4>Amenities</h4>
        <ul class="modal__amenity-list">${amenitiesHTML || "<li>Not listed</li>"}</ul>
      </div>

      <div class="modal__amenities">
        <h4>Rooms</h4>
        <ul class="modal__rooms">${roomsHTML}</ul>
      </div>

      ${item.rules ? `<div class="modal__amenities"><h4>House Rules</h4><p>${esc(item.rules)}</p></div>` : ""}

      <a href="../html/loginform.html" class="btn btn--dark modal__cta">Reserve This Room</a>
    </div>
  `;

  overlay.classList.add("is-open");
  document.body.style.overflow = "hidden";
  overlay._slideshow = initSlideshow(body.querySelector(".slideshow"));

  if (typeof L !== "undefined" && item.coordinates) {
    const map = L.map("roomMap", { scrollWheelZoom: false });
    L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
      maxZoom: 19,
      attribution: "&copy; OpenStreetMap contributors",
    }).addTo(map);
    L.marker(item.coordinates, { icon: propertyIcon() })
      .addTo(map)
      .bindPopup(`<strong>${esc(item.name)}</strong><br>${esc(item.location)}`)
      .openPopup();
    L.marker(SIIT.coordinates, { icon: siitIcon(), zIndexOffset: 1000 })
      .addTo(map)
      .bindPopup(`<strong>${SIIT.shortName}</strong><br>Main reference point`);
    L.circle(SIIT.coordinates, { radius: 500, color: "#0b5c55", fillColor: "#E8B830", fillOpacity: 0.14 }).addTo(map);
    map.fitBounds([item.coordinates, SIIT.coordinates], { padding: [24, 24], maxZoom: 16 });
    overlay._roomMap = map;
    window.setTimeout(() => map.invalidateSize(), 0);
  }
}

function closeListingModal() {
  const overlay = document.getElementById("modalOverlay");
  if (overlay._slideshow) {
    overlay._slideshow.stop();
    overlay._slideshow = null;
  }
  if (overlay._roomMap) {
    overlay._roomMap.remove();
    overlay._roomMap = null;
  }
  overlay.classList.remove("is-open");
  document.body.style.overflow = "";
}

/* ---------------------------------------------------------------------- *
 * SIIT Location Finder — dedicated interactive map section.
 * Center: SIIT. Markers: SIIT (prominent) + every property within range.
 * Sorted nearest-to-farthest, filterable by radius from SIIT.
 * ---------------------------------------------------------------------- */
const SIIT_FILTERS = {
  near: 2000,
  500: 500,
  200: 200,
  210: 210,
  250: 250,
  350: 350,
  all: Infinity,
};

let siitMap = null;
let siitMarkers = {};
let siitActiveFilter = "all";

function siitIcon() {
  return L.divIcon({
    className: "siit-marker siit-marker--school",
    html: '<span class="siit-marker__pin">\u{1F3EB}</span>',
    iconSize: [36, 36],
    iconAnchor: [18, 36],
    popupAnchor: [0, -32],
  });
}

function propertyIcon() {
  return L.divIcon({
    className: "siit-marker siit-marker--property",
    html: '<span class="siit-marker__pin">\u{1F3E0}</span>',
    iconSize: [30, 30],
    iconAnchor: [15, 30],
    popupAnchor: [0, -26],
  });
}

function visibleSiitListings() {
  const maxDist = SIIT_FILTERS[siitActiveFilter];
  return LISTINGS.filter((item) => item.coordinates && item.distanceToSIIT <= maxDist).sort(byDistance);
}

function siitCardHTML(item) {
  return `
    <article class="siit-card" data-id="${esc(item.id)}">
      <img class="siit-card__img" src="${esc(item.img)}" alt="${esc(item.name)}" loading="lazy" />
      <div class="siit-card__body">
        <div class="siit-card__top">
          <h3>${esc(item.name)}</h3>
          <span class="siit-card__price">${currency(item.price)}<span>/mo</span></span>
        </div>
        <span class="siit-card__loc">${esc(item.location)}</span>
        <span class="siit-card__distance">\u{1F4CD} ${esc(distanceLabel(item))} &middot; ${esc(item.roomType)}</span>
        <div class="siit-card__actions">
          <button type="button" class="btn btn--outline-dark btn--sm" data-map-focus="${esc(item.id)}">View on Map</button>
          <a href="${esc(directionsUrl(item))}" target="_blank" rel="noopener" class="btn btn--dark btn--sm">Get Directions</a>
        </div>
      </div>
    </article>
  `;
}

function renderSiitList() {
  const list = document.getElementById("siitMapList");
  if (!list) return;

  const items = visibleSiitListings();
  list.innerHTML = items.length
    ? items.map(siitCardHTML).join("")
    : `<p class="siit-map__empty">No boarding houses found in this range. Try a wider distance filter.</p>`;

  list.querySelectorAll("[data-map-focus]").forEach((btn) => {
    btn.addEventListener("click", () => focusSiitMarker(btn.getAttribute("data-map-focus")));
  });
}

function markActiveSiitCard(id) {
  document.querySelectorAll(".siit-card").forEach((card) => {
    card.classList.toggle("is-active", card.getAttribute("data-id") === id);
  });
}

function focusSiitMarker(id) {
  const item = LISTINGS.find((l) => l.id === id);
  const marker = siitMarkers[id];
  if (!item || !marker || !siitMap) return;

  siitMap.flyTo(item.coordinates, 17, { duration: 1.1 });
  window.setTimeout(() => marker.openPopup(), 480);
  markActiveSiitCard(id);
}

function popupHTML(item) {
  return `
    <div class="siit-popup">
      <strong>${esc(item.name)}</strong>
      <span>${esc(item.location)}</span>
      <span>${esc(distanceLabel(item))}</span>
      <span>${currency(item.price)} / month &middot; ${esc(item.roomType)}</span>
      <span>${item.rooms} rooms available</span>
      <div class="siit-popup__actions">
        <button type="button" class="btn btn--dark btn--sm" data-popup-view="${esc(item.id)}">View Details</button>
        <a href="${esc(directionsUrl(item))}" target="_blank" rel="noopener" class="btn btn--outline-dark btn--sm">Directions</a>
      </div>
    </div>
  `;
}

function refreshSiitMarkers() {
  if (!siitMap) return;
  const visible = visibleSiitListings();
  const visibleIds = new Set(visible.map((i) => i.id));

  Object.entries(siitMarkers).forEach(([id, marker]) => {
    const shouldShow = visibleIds.has(id);
    const hasLayer = siitMap.hasLayer(marker);
    if (shouldShow && !hasLayer) marker.addTo(siitMap);
    if (!shouldShow && hasLayer) siitMap.removeLayer(marker);
  });

  const boundsPoints = [SIIT.coordinates, ...visible.map((i) => i.coordinates)];
  if (boundsPoints.length > 1) {
    siitMap.flyToBounds(boundsPoints, { padding: [46, 46], maxZoom: 16, duration: 1 });
  } else {
    siitMap.flyTo(SIIT.coordinates, 15, { duration: 1 });
  }
}

function setSiitFilter(filterKey) {
  siitActiveFilter = filterKey;
  document.querySelectorAll(".chip[data-radius]").forEach((chip) => {
    chip.classList.toggle("chip--active", chip.getAttribute("data-radius") === filterKey);
  });
  renderSiitList();
  refreshSiitMarkers();
}

function initSiitMap() {
  const mapEl = document.getElementById("siitMap");
  if (!mapEl || typeof L === "undefined") return;

  siitMap = L.map("siitMap", { scrollWheelZoom: false }).setView(SIIT.coordinates, 15);

  L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
    maxZoom: 19,
    attribution: "&copy; OpenStreetMap contributors",
  }).addTo(siitMap);

  L.marker(SIIT.coordinates, { icon: siitIcon(), zIndexOffset: 1000 })
    .addTo(siitMap)
    .bindPopup(
      `<div class="siit-popup"><strong>${SIIT.name}</strong><span>${SIIT.address}</span><span>Main reference point for every listing on this page</span></div>`
    )
    .openPopup();

  LISTINGS.filter((item) => item.coordinates).forEach((item) => {
    const marker = L.marker(item.coordinates, { icon: propertyIcon() }).bindPopup(popupHTML(item));
    marker.on("click", () => markActiveSiitCard(item.id));
    siitMarkers[item.id] = marker;
  });

  document.querySelectorAll(".chip[data-radius]").forEach((chip) => {
    chip.addEventListener("click", () => setSiitFilter(chip.getAttribute("data-radius")));
  });

  // "View Details" buttons inside map popups.
  mapEl.addEventListener("click", (e) => {
    const btn = e.target.closest("[data-popup-view]");
    if (btn) openListingModal(btn.getAttribute("data-popup-view"));
  });

  renderSiitList();
  refreshSiitMarkers();

  window.setTimeout(() => siitMap.invalidateSize(), 150);
  window.addEventListener("resize", () => siitMap && siitMap.invalidateSize());
}

/* ---------------------------------------------------------------------- *
 * Navbar: sticky shrink-on-scroll + mobile hamburger menu
 * ---------------------------------------------------------------------- */
function initNavbar() {
  const navbar = document.getElementById("navbar");
  const burger = document.getElementById("burgerBtn");
  const links = document.getElementById("navLinks");

  function onScroll() {
    navbar.classList.toggle("is-scrolled", window.scrollY > 30);
  }
  window.addEventListener("scroll", onScroll, { passive: true });
  onScroll();

  burger.addEventListener("click", () => {
    const isOpen = links.classList.toggle("is-open");
    burger.classList.toggle("is-open", isOpen);
    burger.setAttribute("aria-expanded", String(isOpen));
  });

  // Close the mobile menu whenever a nav link is tapped.
  links.querySelectorAll("a").forEach((link) => {
    link.addEventListener("click", () => {
      links.classList.remove("is-open");
      burger.classList.remove("is-open");
      burger.setAttribute("aria-expanded", "false");
    });
  });

  // Highlight the current section's nav link on scroll.
  const sections = ["top", "listings", "siit-map", "how-it-works", "about", "footer"]
    .map((id) => document.getElementById(id))
    .filter(Boolean);

  const navlinkFor = (id) => document.querySelector(`.navlink[href="#${id}"]`);

  const sectionObserver = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          document.querySelectorAll(".navlink").forEach((a) => a.classList.remove("is-active"));
          const active = navlinkFor(entry.target.id);
          if (active) active.classList.add("is-active");
        }
      });
    },
    { rootMargin: "-45% 0px -50% 0px" }
  );
  sections.forEach((section) => sectionObserver.observe(section));
}

/* ---------------------------------------------------------------------- *
 * Scroll-reveal animation for elements marked .reveal
 * ---------------------------------------------------------------------- */
function initScrollReveal() {
  const targets = document.querySelectorAll(".reveal");
  const revealObserver = new IntersectionObserver(
    (entries, obs) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          entry.target.classList.add("is-visible");
          obs.unobserve(entry.target);
        }
      });
    },
    { threshold: 0.15 }
  );
  targets.forEach((el) => revealObserver.observe(el));
}

/* ---------------------------------------------------------------------- *
 * Animated stat counters (10+, 50+, 100+)
 * ---------------------------------------------------------------------- */
function initStatCounters() {
  const stats = document.querySelectorAll(".stat__num");
  if (!stats.length) return;

  function animateCount(el) {
    const target = parseInt(el.getAttribute("data-count"), 10) || 0;
    const duration = 1200;
    const start = performance.now();

    function tick(now) {
      const progress = Math.min((now - start) / duration, 1);
      el.textContent = Math.round(progress * target);
      if (progress < 1) requestAnimationFrame(tick);
    }
    requestAnimationFrame(tick);
  }

  const statsObserver = new IntersectionObserver(
    (entries, obs) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          animateCount(entry.target);
          obs.unobserve(entry.target);
        }
      });
    },
    { threshold: 0.5 }
  );
  stats.forEach((el) => statsObserver.observe(el));
}

function setStat(id, value) {
  const el = document.getElementById(id);
  if (el && value !== undefined) el.setAttribute("data-count", String(value));
}

/* ---------------------------------------------------------------------- *
 * INIT
 * ---------------------------------------------------------------------- */
document.addEventListener("DOMContentLoaded", () => {
  initNavbar();
  initScrollReveal();

  loadListings()
    .then((stats) => {
      setStat("statHouses", stats.houses);
      setStat("statRooms", stats.available_rooms);
      setStat("statTenants", stats.tenants);
      const heroRooms = document.getElementById("heroRooms");
      if (heroRooms && stats.available_rooms !== undefined) heroRooms.textContent = stats.available_rooms;
      renderListings([...LISTINGS].sort(byDistance));
      initSiitMap();
      initStatCounters();
    })
    .catch(() => {
      const empty = document.getElementById("listingsEmpty");
      empty.textContent = "Listings could not be loaded right now. Please make sure the server and database are running, then refresh.";
      empty.classList.remove("hidden");
      initStatCounters();
    });

  document.getElementById("searchForm").addEventListener("submit", (e) => {
    e.preventDefault();
    applyFilters();
  });

  document.getElementById("modalClose").addEventListener("click", closeListingModal);
  document.getElementById("modalOverlay").addEventListener("click", (e) => {
    if (e.target.id === "modalOverlay") closeListingModal();
  });
  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape") closeListingModal();
  });

  document.getElementById("year").textContent = new Date().getFullYear();
});

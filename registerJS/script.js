/* ==========================================================================
   Boarding House Rental System — home.js
   Vanilla JS only. No framework, no backend calls — this file drives the
   front-end prototype behavior for the public Home Page.
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
 * Sample listings data — replace with real data from the database later.
 * NOTE ON COORDINATES: these are placeholder sample coordinates chosen to
 * sit a realistic distance from SIIT for this prototype. In production,
 * `coordinates` should come from the property's saved location record —
 * set once by the landlord/admin via a map location-picker when the
 * listing is created — not hard-coded here.
 * ---------------------------------------------------------------------- */
const LISTINGS = [
  {
    id: "greenview",
    name: "Green View Boarding House",
    location: "Purok 2, Dapa, Siargao",
    price: 2500,
    roomType: "Single Room",
    availability: "Available",
    rooms: 6,
    amenities: ["Wi-Fi", "Electricity", "Water", "Study Area"],
    coordinates: [9.7608, 126.0490],
    img: "https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQP2gJlXIUqunx_oRPYPnk3T67yT5JCJlJDHbZc9K22Jg&s=10",
  },
  {
    id: "islandhome",
    name: "Island Home Boarding House",
    location: "Brgy. 5, Dapa, Siargao",
    price: 2500,
    roomType: "Shared Room",
    availability: "Available",
    rooms: 5,
    amenities: ["Wi-Fi", "Kitchen", "Water", "Laundry"],
    coordinates: [9.7602, 126.0495],
    img: "../Image/image2.jpg",
  },
  {
    id: "studenthaven",
    name: "Student Haven",
    location: "Brgy. 9, Dapa, Siargao",
    price: 5000,
    roomType: "Single Room",
    availability: "Coming Soon",
    rooms: 4,
    amenities: ["Wi-Fi", "Private Bathroom", "Study Area"],
    coordinates: [9.7612, 126.0493],
    img: "../Image/haven.jpg",
  },
  {
    id: "northview",
    name: "Northview Student Residences",
    location: "Brgy. Osme\u00f1a, Dapa, Siargao",
    price: 1800,
    roomType: "Shared Room",
    availability: "Available",
    rooms: 8,
    amenities: ["Wi-Fi", "Kitchen", "Study Area", "Security Guard"],
    coordinates: [9.7618, 126.0500],
    img: "../Image/image4.jpg",
  },
  {
    id: "sunrise",
    name: "Boarding House Sunrise",
    location: "Brgy. 3, Dapa, Siargao",
    price: 1300,
    roomType: "Bedspace",
    availability: "Available",
    rooms: 12,
    amenities: ["Wi-Fi", "Shared Kitchen", "Electric Fan"],
    coordinates: [9.7598, 126.0492],
    img: "../Image/images.jpg",
  },
  {
    id: "seaside",
    name: "Seaside Boarders",
    location: "Brgy. Union, Dapa, Siargao",
    price: 1200,
    roomType: "Bedspace",
    availability: "Available",
    rooms: 10,
    amenities: ["Wi-Fi", "Shared Kitchen", "Water"],
    coordinates: [9.7605, 126.0500],
    img: "../Image/image3.jpg",
  },
];

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
  if (meters < 1000) return `${Math.round(meters / 10) * 10} m`;
  return `${(meters / 1000).toFixed(1)} km`;
}

function directionsUrl(item) {
  // No origin specified on purpose: Google Maps automatically routes from
  // the visitor's current location (after a permission prompt) whether
  // opened in the browser or the Google Maps app.
  return `https://www.google.com/maps/dir/?api=1&destination=${item.coordinates[0]},${item.coordinates[1]}&travelmode=driving`;
}

// Pre-compute each listing's distance from SIIT once, up front.
LISTINGS.forEach((item) => {
  item.distanceToSIIT = distanceMeters(SIIT.coordinates, item.coordinates);
});

function byDistance(a, b) {
  return a.distanceToSIIT - b.distanceToSIIT;
}

/* ---------------------------------------------------------------------- *
 * Listings rendering + filtering (Featured Listings preview grid)
 * ---------------------------------------------------------------------- */
function currency(amount) {
  return "\u20B1" + amount.toLocaleString("en-PH");
}

function listingCardHTML(item) {
  const badgeClass = item.availability === "Available" ? "" : " listing-card__badge--soon";
  return `
    <article class="listing-card reveal is-visible" data-id="${item.id}">
      <div class="listing-card__img-wrap">
        <img class="listing-card__img" src="${item.img}" alt="${item.name}" loading="lazy" />
        <span class="listing-card__badge${badgeClass}">${item.availability}</span>
      </div>
      <div class="listing-card__body">
        <h3>${item.name}</h3>
        <span class="listing-card__loc">${item.location}</span>
        <span class="listing-card__price">${currency(item.price)} <span>/ month</span></span>
        <span class="listing-card__meta">${item.roomType} &middot; ${item.rooms} rooms available</span>
        <span class="listing-card__distance">\u{1F4CD} ${formatDistance(item.distanceToSIIT)} from SIIT</span>
        <span class="listing-card__amenities">${item.amenities.join(" \u2022 ")}</span>
        <button type="button" class="listing-card__cta" data-view="${item.id}">View Details</button>
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
    if (location && !item.location.toLowerCase().includes(location)) return false;
    if (!isNaN(maxPrice) && maxPrice > 0 && item.price > maxPrice) return false;
    if (roomType && item.roomType !== roomType) return false;
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
 * "View Details" modal
 * ---------------------------------------------------------------------- */
function openListingModal(id) {
  const item = LISTINGS.find((l) => l.id === id);
  if (!item) return;

  const overlay = document.getElementById("modalOverlay");
  const body = document.getElementById("modalBody");

  const badgeClass = item.availability === "Available" ? "" : " listing-card__badge--soon";
  const amenitiesHTML = item.amenities.map((a) => `<li>${a}</li>`).join("");

  body.innerHTML = `
    <div class="modal__media">
      <img src="${item.img}" alt="${item.name}" />
      <span class="listing-card__badge${badgeClass}">${item.availability}</span>
    </div>
    <div class="modal__content">
      <h3 id="modalTitle">${item.name}</h3>
      <span class="listing-card__loc">${item.location}</span>

      <div class="modal__location">
        <div class="modal__location-heading">
          <div>
            <h4>Distance from SIIT</h4>
            <p>${formatDistance(item.distanceToSIIT)} from Siargao Island Institute of Technology</p>
          </div>
          <a href="${directionsUrl(item)}" target="_blank" rel="noopener" class="modal__map-link">Get Directions</a>
        </div>
        <div id="roomMap" class="room-map" aria-label="Map showing ${item.name} near SIIT"></div>
      </div>

      <div class="modal__price-row">
        <span class="listing-card__price">${currency(item.price)} <span>/ month</span></span>
        <span class="modal__roomtype">${item.roomType}</span>
      </div>

      <div class="modal__details-grid">
        <div class="modal__detail">
          <span class="modal__detail-label">Rooms Open</span>
          <span class="modal__detail-value">${item.rooms}</span>
        </div>
        <div class="modal__detail">
          <span class="modal__detail-label">Availability</span>
          <span class="modal__detail-value">${item.availability}</span>
        </div>
      </div>

      <div class="modal__amenities">
        <h4>Amenities</h4>
        <ul class="modal__amenity-list">${amenitiesHTML}</ul>
      </div>

      <a href="../html/loginform.html" class="btn btn--dark modal__cta">Reserve This Room</a>
    </div>
  `;

  overlay.classList.add("is-open");
  document.body.style.overflow = "hidden";

  if (typeof L !== "undefined") {
    const map = L.map("roomMap", { scrollWheelZoom: false });
    L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
      maxZoom: 19,
      attribution: "&copy; OpenStreetMap contributors",
    }).addTo(map);
    L.marker(item.coordinates, { icon: propertyIcon() })
      .addTo(map)
      .bindPopup(`<strong>${item.name}</strong><br>${item.location}`)
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
  return LISTINGS.filter((item) => item.distanceToSIIT <= maxDist).sort(byDistance);
}

function siitCardHTML(item) {
  return `
    <article class="siit-card" data-id="${item.id}">
      <img class="siit-card__img" src="${item.img}" alt="${item.name}" loading="lazy" />
      <div class="siit-card__body">
        <div class="siit-card__top">
          <h3>${item.name}</h3>
          <span class="siit-card__price">${currency(item.price)}<span>/mo</span></span>
        </div>
        <span class="siit-card__loc">${item.location}</span>
        <span class="siit-card__distance">\u{1F4CD} ${formatDistance(item.distanceToSIIT)} from SIIT &middot; ${item.roomType}</span>
        <div class="siit-card__actions">
          <button type="button" class="btn btn--outline-dark btn--sm" data-map-focus="${item.id}">View on Map</button>
          <a href="${directionsUrl(item)}" target="_blank" rel="noopener" class="btn btn--dark btn--sm">Get Directions</a>
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
      <strong>${item.name}</strong>
      <span>${item.location}</span>
      <span>${formatDistance(item.distanceToSIIT)} from SIIT</span>
      <span>${currency(item.price)} / month &middot; ${item.roomType}</span>
      <span>${item.rooms} rooms available</span>
      <div class="siit-popup__actions">
        <button type="button" class="btn btn--dark btn--sm" onclick="openListingModal('${item.id}')">View Details</button>
        <a href="${directionsUrl(item)}" target="_blank" rel="noopener" class="btn btn--outline-dark btn--sm">Directions</a>
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

  LISTINGS.forEach((item) => {
    const marker = L.marker(item.coordinates, { icon: propertyIcon() }).bindPopup(popupHTML(item));
    marker.on("click", () => markActiveSiitCard(item.id));
    siitMarkers[item.id] = marker;
  });

  document.querySelectorAll(".chip[data-radius]").forEach((chip) => {
    chip.addEventListener("click", () => setSiitFilter(chip.getAttribute("data-radius")));
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

/* ---------------------------------------------------------------------- *
 * INIT
 * ---------------------------------------------------------------------- */
document.addEventListener("DOMContentLoaded", () => {
  initNavbar();
  initScrollReveal();
  initStatCounters();

  renderListings([...LISTINGS].sort(byDistance));
  initSiitMap();

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

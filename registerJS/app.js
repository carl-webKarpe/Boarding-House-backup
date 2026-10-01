/* ==========================================================================
   Boarding House Rental System — app.js
   Shared UI behavior for the login flow and the modern homepage experience.
   ========================================================================== */

"use strict";

function initDarkMode() {
  const root = document.documentElement;
  const toggleBtn = document.getElementById("darkModeToggle");
  const sunIcon = document.getElementById("iconSun");
  const moonIcon = document.getElementById("iconMoon");
  if (!toggleBtn || !sunIcon || !moonIcon) return;

  const applyMode = (isDark) => {
    root.classList.toggle("dark", isDark);
    root.setAttribute("data-theme", isDark ? "dark" : "light");
    document.body.classList.toggle("dark", isDark);
    document.documentElement.style.colorScheme = isDark ? "dark" : "light";
    sunIcon.classList.toggle("hidden", !isDark);
    moonIcon.classList.toggle("hidden", isDark);
    toggleBtn.setAttribute("aria-pressed", String(isDark));
  };

  const saved = localStorage.getItem("bhrs-theme");
  const prefersDark = window.matchMedia("(prefers-color-scheme: dark)").matches;
  applyMode(saved ? saved === "dark" : prefersDark);

  toggleBtn.addEventListener("click", () => {
    const isDark = !root.classList.contains("dark");
    applyMode(isDark);
    localStorage.setItem("bhrs-theme", isDark ? "dark" : "light");
  });
}

function showToast(message, type = "success") {
  const container = document.getElementById("toastContainer");
  if (!container) return;

  const palette = {
    success: { bg: "bg-emerald-600", icon: "M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" },
    error: { bg: "bg-red-500", icon: "M12 9v3.75m0 3.75h.008v.008H12v-.008ZM21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" },
    info: { bg: "bg-slate-700", icon: "M11.25 11.25h.008v.008h-.008v-.008ZM12 21a9 9 0 1 1 0-18 9 9 0 0 1 0 18Z" },
  };
  const style = palette[type] || palette.info;

  const toast = document.createElement("div");
  toast.className = `toast-enter ${style.bg} text-white text-sm font-medium rounded-2xl shadow-soft px-4 py-3 flex items-start gap-2`;
  toast.setAttribute("role", "status");
  toast.innerHTML = `
    <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
      <path stroke-linecap="round" stroke-linejoin="round" d="${style.icon}" />
    </svg>
    <span></span>
  `;
  toast.querySelector("span").textContent = message;

  container.appendChild(toast);
  setTimeout(() => {
    toast.classList.remove("toast-enter");
    toast.classList.add("toast-exit");
    toast.addEventListener("animationend", () => toast.remove(), { once: true });
  }, 3500);
}

function initPasswordToggle(toggleId, inputId, eyeOpenId, eyeClosedId) {
  const toggleBtn = document.getElementById(toggleId);
  const input = document.getElementById(inputId);
  const eyeOpen = document.getElementById(eyeOpenId);
  const eyeClosed = document.getElementById(eyeClosedId);
  if (!toggleBtn || !input || !eyeOpen || !eyeClosed) return;

  toggleBtn.addEventListener("click", () => {
    const isVisible = input.type === "text";
    input.type = isVisible ? "password" : "text";
    eyeOpen.classList.toggle("hidden", !isVisible);
    eyeClosed.classList.toggle("hidden", isVisible);
    toggleBtn.setAttribute("aria-label", isVisible ? "Show password" : "Hide password");
    toggleBtn.setAttribute("aria-pressed", String(!isVisible));
  });
}

/** Escapes text before it is placed inside an HTML template string. */
function escapeHtml(value) {
  return String(value ?? "").replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[c]);
}

const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

function setFieldError(inputEl, errorEl, message) {
  if (!inputEl || !errorEl) return;

  if (message) {
    inputEl.classList.add("border-red-400", "focus:ring-red-300", "focus:border-red-400");
    inputEl.classList.remove("border-slate-200", "focus:ring-primary/60", "focus:border-primary", "border-emerald-400");
    inputEl.setAttribute("aria-invalid", "true");
    errorEl.textContent = `⚠ ${message}`;
    errorEl.className = "field-error text-xs text-red-500 mt-1.5";
  } else {
    inputEl.classList.remove("border-red-400", "focus:ring-red-300", "focus:border-red-400");
    inputEl.classList.add("border-slate-200", "focus:ring-primary/60", "focus:border-primary");
    inputEl.setAttribute("aria-invalid", "false");
    errorEl.textContent = "";
    errorEl.className = "hidden";
  }
}

function setFieldSuccess(inputEl, messageEl, message) {
  if (!inputEl || !messageEl) return;

  inputEl.classList.remove("border-red-400", "focus:ring-red-300", "focus:border-red-400", "border-slate-200");
  inputEl.classList.add("border-emerald-400", "focus:ring-primary/60", "focus:border-primary");
  inputEl.setAttribute("aria-invalid", "false");
  messageEl.textContent = message ? `✓ ${message}` : "";
  messageEl.className = message ? "text-xs text-emerald-600 mt-1.5" : "hidden";
}

function validateRequired(value, fieldName) {
  return String(value || "").trim() ? "" : `${fieldName} is required.`;
}

function validateDigitsOnly(value, fieldName) {
  const trimmed = String(value || "").trim();
  if (!trimmed) return `${fieldName} is required.`;
  if (!/^(09\d{9}|\+639\d{9})$/.test(trimmed)) {
    return `${fieldName} must be a valid PH mobile number (e.g. 09123456789).`;
  }
  return "";
}

function validateGender(value) {
  return value ? "" : "Please select your gender.";
}

function validateDateOfBirth(value, minAge = 16) {
  if (!value) return "Date of birth is required.";
  const dob = new Date(`${value}T00:00:00`);
  if (Number.isNaN(dob.getTime())) return "Please enter a valid date.";
  const today = new Date();
  if (dob > today) return "Date of birth cannot be in the future.";
  let age = today.getFullYear() - dob.getFullYear();
  const beforeBirthday = today.getMonth() < dob.getMonth()
    || (today.getMonth() === dob.getMonth() && today.getDate() < dob.getDate());
  if (beforeBirthday) age -= 1;
  if (age < minAge) return `You must be at least ${minAge} years old.`;
  if (age > 120) return "Please enter a valid date of birth.";
  return "";
}

function validateEmail(value) {
  if (!value.trim()) return "Email is required.";
  if (!EMAIL_PATTERN.test(value.trim())) return "Please enter a valid email address.";
  return "";
}

function validatePassword(value) {
  if (!value) return "Password is required.";
  if (value.length < 8) return "Password must be at least 8 characters.";
  return "";
}

const ACCEPTED_DOC_TYPES = ["image/jpeg", "image/png", "application/pdf"];

function initFileUpload({ dropzoneId, inputId, previewId, errorId, maxSizeMB = 5, label = "file" }) {
  const dropzone = document.getElementById(dropzoneId);
  const input = document.getElementById(inputId);
  const preview = document.getElementById(previewId);
  const errorEl = document.getElementById(errorId);
  if (!dropzone || !input || !preview) return null;

  let currentFile = null;

  function humanSize(bytes) {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(2)} MB`;
  }

  function showError(msg) {
    if (!errorEl) return;
    errorEl.textContent = msg ? `⚠ ${msg}` : "";
    errorEl.className = msg ? "field-error text-xs text-red-500 mt-1.5" : "hidden";
  }

  function renderPreview() {
    if (!currentFile) {
      preview.innerHTML = "";
      preview.classList.add("hidden");
      dropzone.classList.remove("hidden");
      return;
    }
    dropzone.classList.add("hidden");
    preview.classList.remove("hidden");

    const isImage = currentFile.type.startsWith("image/");
    const thumbHtml = isImage
      ? `<img src="${URL.createObjectURL(currentFile)}" alt="" class="w-14 h-14 rounded-xl object-cover flex-shrink-0" />`
      : `<div class="w-14 h-14 rounded-xl bg-primary/10 grid place-items-center flex-shrink-0"><svg class="w-7 h-7 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" /></svg></div>`;

    preview.innerHTML = `
      <div class="flex items-center gap-3 rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-4 py-3">
        ${thumbHtml}
        <div class="min-w-0 flex-1">
          <p class="text-sm font-semibold text-textmain dark:text-white truncate">${escapeHtml(currentFile.name)}</p>
          <p class="text-xs text-slate-400">${humanSize(currentFile.size)}</p>
        </div>
        <button type="button" data-remove-file aria-label="Remove file" class="flex-shrink-0 w-8 h-8 grid place-items-center rounded-full text-slate-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-500/10 transition-colors">
          <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
        </button>
      </div>
    `;
    preview.querySelector("[data-remove-file]").addEventListener("click", () => setFile(null));
  }

  function validateFile(file) {
    if (!file) return "";
    if (!ACCEPTED_DOC_TYPES.includes(file.type)) return `Please upload a ${label} as JPG, JPEG, PNG, or PDF.`;
    if (file.size > maxSizeMB * 1024 * 1024) return `File is too large. Maximum size is ${maxSizeMB}MB.`;
    return "";
  }

  function setFile(file) {
    const msg = validateFile(file);
    if (msg) {
      showError(msg);
      input.value = "";
      currentFile = null;
      renderPreview();
      return;
    }
    showError("");
    currentFile = file;
    renderPreview();
  }

  input.addEventListener("change", () => setFile(input.files && input.files[0] ? input.files[0] : null));

  ["dragenter", "dragover"].forEach((evt) => dropzone.addEventListener(evt, (e) => {
    e.preventDefault();
    dropzone.classList.add("border-primary", "bg-primary/5");
  }));
  ["dragleave", "drop"].forEach((evt) => dropzone.addEventListener(evt, (e) => {
    e.preventDefault();
    dropzone.classList.remove("border-primary", "bg-primary/5");
  }));
  dropzone.addEventListener("drop", (e) => {
    const file = e.dataTransfer.files && e.dataTransfer.files[0];
    if (file) setFile(file);
  });
  dropzone.addEventListener("click", () => input.click());
  dropzone.addEventListener("keydown", (e) => {
    if (e.key === "Enter" || e.key === " ") {
      e.preventDefault();
      input.click();
    }
  });

  return {
    getFile: () => currentFile,
    isValid: () => !!currentFile,
    reset: () => setFile(null),
  };
}

async function getCsrfToken() {
  try {
    const response = await fetch("../api/csrf.php", { method: "GET", headers: { "X-Requested-With": "XMLHttpRequest" } });
    const data = await response.json();
    return data.csrf_token || "";
  } catch (error) {
    return "";
  }
}

function initLoginForm() {
  const form = document.getElementById("loginForm");
  if (!form) return;

  const emailInput = document.getElementById("email");
  const emailError = document.getElementById("emailError");
  const passwordInput = document.getElementById("password");
  const passwordError = document.getElementById("passwordError");
  const rememberMe = document.getElementById("rememberMe");
  const loginBtn = document.getElementById("loginBtn");
  const loginBtnText = document.getElementById("loginBtnText");
  const loginSpinner = document.getElementById("loginSpinner");

  const rememberedEmail = localStorage.getItem("bhrs-remember-email");
  if (rememberedEmail) {
    emailInput.value = rememberedEmail;
    rememberMe.checked = true;
  }

  const runValidation = () => {
    const emailMsg = validateEmail(emailInput.value);
    const passwordMsg = validatePassword(passwordInput.value);
    setFieldError(emailInput, emailError, emailInput.value ? emailMsg : "");
    setFieldError(passwordInput, passwordError, passwordInput.value ? passwordMsg : "");
    const isValid = !emailMsg && !passwordMsg;
    loginBtn.disabled = !isValid;
    return isValid;
  };

  emailInput.addEventListener("input", runValidation);
  passwordInput.addEventListener("input", runValidation);
  emailInput.addEventListener("blur", () => setFieldError(emailInput, emailError, validateEmail(emailInput.value)));
  passwordInput.addEventListener("blur", () => setFieldError(passwordInput, passwordError, validatePassword(passwordInput.value)));

  form.addEventListener("submit", async (e) => {
    e.preventDefault();

    const emailMsg = validateEmail(emailInput.value);
    const passwordMsg = validatePassword(passwordInput.value);
    setFieldError(emailInput, emailError, emailMsg);
    setFieldError(passwordInput, passwordError, passwordMsg);

    if (emailMsg || passwordMsg) {
      showToast("Please complete all required fields.", "error");
      return;
    }

    if (rememberMe.checked) {
      localStorage.setItem("bhrs-remember-email", emailInput.value.trim());
    } else {
      localStorage.removeItem("bhrs-remember-email");
    }

    loginBtn.disabled = true;
    loginBtnText.classList.add("hidden");
    loginSpinner.classList.remove("hidden");

    try {
      const csrfToken = await getCsrfToken();
      if (!csrfToken) {
        showToast("Security token could not be prepared. Please refresh the page.", "error");
        loginSpinner.classList.add("hidden");
        loginBtnText.classList.remove("hidden");
        loginBtn.disabled = false;
        return;
      }

      const response = await fetch("../api/login.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          email: emailInput.value.trim(),
          password: passwordInput.value,
          rememberMe: rememberMe.checked,
          csrf_token: csrfToken,
        }),
      });

      const data = await response.json();
      loginSpinner.classList.add("hidden");
      loginBtnText.classList.remove("hidden");
      loginBtn.disabled = false;
      if (!response.ok || !data.success) {
        showToast(data.message || "Login failed.", "error");
        return;
      }

      const account = data.data || {};
      showToast(`Welcome back, ${account.name || account.username || ""}!`, "success");
      form.reset();
      if (rememberMe.checked) {
        emailInput.value = localStorage.getItem("bhrs-remember-email") || "";
      }
      runValidation();
      // Each role has its own home: admins -> dashboard, landlords -> dashboard, tenants -> rooms.
      const target = typeof account.redirect === "string" && /^[a-z/.-]+(\.php)?\/?$/i.test(account.redirect)
        ? `../${account.redirect}`
        : "../php/browse-rooms.php";
      setTimeout(() => {
        window.location.href = target;
      }, 800);
    } catch (error) {
      loginSpinner.classList.add("hidden");
      loginBtnText.classList.remove("hidden");
      loginBtn.disabled = false;
      showToast("Could not reach the server. Make sure XAMPP Apache and MySQL are running.", "error");
    }
  });

  runValidation();
}

function initHomepageExperience() {
  const profileMenuButton = document.getElementById("profileMenuButton");
  const profileDropdown = document.getElementById("profileDropdown");
  if (profileMenuButton && profileDropdown) {
    profileMenuButton.addEventListener("click", (event) => {
      event.stopPropagation();
      profileDropdown.classList.toggle("hidden");
    });
    document.addEventListener("click", () => profileDropdown.classList.add("hidden"));
  }

  const heroSlides = Array.from(document.querySelectorAll("[data-hero-slide]"));
  if (heroSlides.length) {
    let slideIndex = 0;
    setInterval(() => {
      heroSlides.forEach((slide, index) => slide.classList.toggle("hidden", index !== slideIndex));
      slideIndex = (slideIndex + 1) % heroSlides.length;
    }, 5000);
  }

  const searchForm = document.getElementById("searchForm");
  if (searchForm) {
    searchForm.addEventListener("submit", (event) => {
      event.preventDefault();
      const formData = new FormData(searchForm);
      const values = Array.from(formData.values()).filter(Boolean).join(" ").trim();
      if (!values) {
        showToast("Enter a location or school to start searching.", "info");
        return;
      }
      showToast("Filtering homes to match your search.", "success");
    });
  }

  const globalSearch = document.getElementById("globalSearch");
  const cards = Array.from(document.querySelectorAll("[data-room-card]"));
  const filterChips = Array.from(document.querySelectorAll("[data-filter-chip]"));
  let activeFilter = "all";

  const updateCards = (query = "", filter = activeFilter) => {
    const normalizedQuery = query.trim().toLowerCase();
    cards.forEach((card) => {
      const title = card.getAttribute("data-title") || "";
      const location = card.getAttribute("data-location") || "";
      const amenities = card.getAttribute("data-amenities") || "";
      const type = card.getAttribute("data-type") || "";
      const price = Number(card.getAttribute("data-price") || 0);
      const matchesQuery = !normalizedQuery || `${title} ${location} ${amenities}`.toLowerCase().includes(normalizedQuery);
      const normalizedType = type.toLowerCase();
      const matchesFilter = filter === "all"
        || (filter === "shared" && normalizedType.includes("shared"))
        || (filter === "solo" && normalizedType.includes("solo"))
        || (filter === "dormitory" && normalizedType.includes("dorm"))
        || (filter === "budget" && price <= 3500)
        || (filter === "verified");
      card.style.display = matchesQuery && matchesFilter ? "block" : "none";
    });
  };

  if (globalSearch) {
    globalSearch.addEventListener("input", (event) => updateCards(event.target.value));
  }

  filterChips.forEach((chip) => {
    chip.addEventListener("click", () => {
      activeFilter = chip.getAttribute("data-filter-chip") || "all";
      filterChips.forEach((item) => item.classList.toggle("active", item === chip));
      updateCards(globalSearch ? globalSearch.value : "", activeFilter);
    });
  });

  const saveButtons = document.querySelectorAll("[data-save-room]");
  saveButtons.forEach((button) => {
    button.addEventListener("click", () => {
      button.classList.toggle("active");
      const isSaved = button.classList.contains("active");
      showToast(isSaved ? "Property saved to favorites." : "Removed from favorites.", "info");
    });
  });

  const bookingModal = document.getElementById("bookingModal");
  const modalTitle = document.getElementById("modalTitle");
  const modalBody = document.getElementById("modalBody");
  const closeModalBtn = document.getElementById("closeModalBtn");
  const cancelModalBtn = document.getElementById("cancelModalBtn");
  const bookButtons = document.querySelectorAll("[data-book-room]");

  const openModal = (title) => {
    if (!bookingModal || !modalTitle || !modalBody) {
      showToast(`Online booking for "${title}" is coming soon. Please contact the landlord directly for now.`, "info");
      return;
    }
    modalTitle.textContent = title;
    modalBody.innerHTML = '<p class="mb-2">A landlord will review your request and contact you shortly.</p><p class="text-sm text-slate-500">You can also message them directly from the dashboard after confirmation.</p>';
    bookingModal.classList.remove("hidden");
    bookingModal.classList.add("flex");
  };

  const closeModal = () => {
    if (!bookingModal) return;
    bookingModal.classList.add("hidden");
    bookingModal.classList.remove("flex");
  };

  bookButtons.forEach((button) => {
    button.addEventListener("click", () => {
      const title = button.closest("article")?.querySelector("h3")?.textContent || "This property";
      openModal(title);
    });
  });

  [closeModalBtn, cancelModalBtn].forEach((button) => {
    if (button) button.addEventListener("click", closeModal);
  });
  if (bookingModal) {
    bookingModal.addEventListener("click", (event) => {
      if (event.target === bookingModal) closeModal();
    });
  }

  const loadMoreBtn = document.getElementById("loadMoreBtn");
  const loadMoreStatus = document.getElementById("loadMoreStatus");
  if (loadMoreBtn && loadMoreStatus) {
    loadMoreBtn.addEventListener("click", async () => {
      loadMoreBtn.disabled = true;
      loadMoreBtn.textContent = "Loading...";
      try {
        const grid = document.querySelector("#listings .grid");
        const offset = grid ? grid.querySelectorAll("article").length : 0;
        const response = await fetch(`../api/rooms.php?offset=${offset}&limit=2`, { headers: { "X-Requested-With": "XMLHttpRequest" } });
        const data = await response.json();
        if (data.rooms && data.rooms.length) {
          const fragment = document.createDocumentFragment();
          data.rooms.forEach((room) => {
            const article = document.createElement("article");
            article.className = "group overflow-hidden rounded-[28px] border border-slate-200 bg-white shadow-sm transition duration-300 hover:-translate-y-1 hover:shadow-soft dark:border-slate-800 dark:bg-slate-900/80";
            article.innerHTML = `
              <div class="relative">
                <img src="${escapeHtml(room.image)}" alt="${escapeHtml(room.title)}" class="h-56 w-full object-cover" loading="lazy" />
                <span class="absolute left-4 top-4 rounded-full bg-emerald-600 px-3 py-1 text-xs font-semibold text-white">${escapeHtml(room.badge)}</span>
              </div>
              <div class="p-5">
                <div class="flex items-start justify-between gap-3">
                  <div>
                    <h3 class="font-display text-lg font-semibold text-slate-900 dark:text-white">${escapeHtml(room.title)}</h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">${escapeHtml(room.barangay)}, ${escapeHtml(room.city)}</p>
                  </div>
                  <div class="rounded-full bg-amber-50 px-2.5 py-1 text-sm font-semibold text-amber-600 dark:bg-amber-950/40">★ ${Number(room.rating).toFixed(1)}</div>
                </div>
                <div class="mt-4 flex items-center justify-between text-sm text-slate-500 dark:text-slate-400">
                  <span>${escapeHtml(room.type)}</span>
                  <span>${escapeHtml(room.distance)} from school</span>
                </div>
                <div class="mt-5 flex items-center justify-between">
                  <div>
                    <div class="text-lg font-semibold text-slate-900 dark:text-white">₱${Number(room.rent).toLocaleString()}</div>
                    <div class="text-sm text-slate-500 dark:text-slate-400">/ month</div>
                  </div>
                  <button type="button" class="rounded-full bg-emerald-600 px-3 py-2 text-sm font-semibold text-white" data-book-room="${Number(room.id)}">Book</button>
                </div>
              </div>`;
            fragment.appendChild(article);
          });
          if (grid) grid.appendChild(fragment);
        }
        if (!data.hasMore) {
          loadMoreBtn.classList.add("hidden");
          loadMoreStatus.textContent = "You’ve reached the latest listings.";
        } else {
          loadMoreStatus.textContent = "More homes are available for you to explore.";
        }
      } catch (error) {
        showToast("We could not load more listings right now.", "error");
      } finally {
        loadMoreBtn.disabled = false;
        loadMoreBtn.textContent = "Load more";
      }
    });
  }
}

document.addEventListener("DOMContentLoaded", () => {
  initDarkMode();
  initPasswordToggle("togglePassword", "password", "eyeOpen", "eyeClosed");
  initLoginForm();
  initHomepageExperience();
});

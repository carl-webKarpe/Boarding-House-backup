/* ==========================================================================
   Boarding House Rental System — signup.js
   Registration form logic. Relies on shared helpers from js/app.js
   (initDarkMode, showToast, initPasswordToggle, setFieldError,
   setFieldSuccess, validateEmail) which is loaded first in signup.html.
   ========================================================================== */

"use strict";

/* ---------------------------------------------------------------------- *
 * Simulated "already taken" usernames — stands in for a real API check.
 * ---------------------------------------------------------------------- */
const TAKEN_USERNAMES = ["admin", "student1", "johndoe", "boarder", "testuser"];

/* ---------------------------------------------------------------------- *
 * Field-level validators
 * ---------------------------------------------------------------------- */
function validateGmail(value) {
  const trimmed = value.trim();
  if (!trimmed) return "Gmail address is required.";
  const genericMsg = validateEmail(trimmed);
  if (genericMsg) return "Please enter a valid Gmail address (e.g. yourname@gmail.com).";
  if (!/@gmail\.com$/i.test(trimmed)) return "Please use a Gmail address (must end in @gmail.com).";
  return "";
}

function validateUsername(value) {
  if (!value) return "Username is required.";
  if (value.length < 5 || value.length > 20) return "Username must be 5–20 characters long.";
  if (!/^[A-Za-z0-9_]+$/.test(value)) return "Only letters, numbers, and underscores are allowed.";
  return "";
}

function getPasswordCriteria(value) {
  return {
    length: value.length >= 8,
    upper: /[A-Z]/.test(value),
    lower: /[a-z]/.test(value),
    number: /[0-9]/.test(value),
    special: /[^A-Za-z0-9]/.test(value),
  };
}

function validateSignupPassword(value) {
  if (!value) return "Password is required.";
  const c = getPasswordCriteria(value);
  if (!c.length) return "Password must be at least 8 characters.";
  if (!c.upper || !c.lower) return "Password needs both uppercase and lowercase letters.";
  if (!c.number) return "Password needs at least one number.";
  if (!c.special) return "Password needs at least one special character.";
  return "";
}

function getPasswordStrength(value) {
  const c = getPasswordCriteria(value);
  const score = Object.values(c).filter(Boolean).length; // 0–5

  if (!value) return { label: "", widthPct: 0, colorClass: "" };
  if (score <= 2) return { label: "Weak", widthPct: 33, colorClass: "bg-red-500" };
  if (score <= 4) return { label: "Medium", widthPct: 66, colorClass: "bg-amber-500" };
  return { label: "Strong", widthPct: 100, colorClass: "bg-primary" };
}

function validateConfirmPassword(password, confirm) {
  if (!confirm) return "Please confirm your password.";
  if (password !== confirm) return "Passwords do not match.";
  return "";
}

function validateAddress(selectValue, otherValue) {
  if (!selectValue) return "Please select your current address / location.";
  if (selectValue === "other") {
    const trimmed = (otherValue || "").trim();
    if (!trimmed) return "Please type your address since \"Other\" is selected.";
    if (trimmed.length < 10) return "Please enter a more complete address (at least 10 characters).";
  }
  return "";
}

/** Resolves the actual address string to submit: the picked option, or the typed "Other" value. */
function resolveAddressValue(selectValue, otherValue) {
  return selectValue === "other" ? (otherValue || "").trim() : selectValue;
}

const PH_MOBILE_PATTERN = /^(09\d{9}|\+639\d{9})$/;

function validateContactNumber(value) {
  const trimmed = value.trim();
  if (!trimmed) return "Contact number is required.";
  if (!PH_MOBILE_PATTERN.test(trimmed)) {
    return "Enter a valid PH mobile number (e.g. 09123456789 or +639123456789).";
  }
  return "";
}

function validateTerms(checked) {
  return checked ? "" : "You must agree to the Terms and Conditions.";
}

/* ---------------------------------------------------------------------- *
 * Form wiring
 * ---------------------------------------------------------------------- */
async function getCsrfToken() {
  try {
    const response = await fetch("../api/csrf.php", { method: "GET", headers: { "X-Requested-With": "XMLHttpRequest" } });
    const data = await response.json();
    return data.csrf_token || "";
  } catch (error) {
    return "";
  }
}

function initSignupForm() {
  const form = document.getElementById("signupForm");
  if (!form) return;

  const gmail = document.getElementById("gmail");
  const gmailError = document.getElementById("gmailError");

  const username = document.getElementById("username");
  const usernameError = document.getElementById("usernameError");
  const usernameStatus = document.getElementById("usernameStatus");

  const password = document.getElementById("password");
  const passwordError = document.getElementById("passwordError");
  const strengthTrack = document.getElementById("strengthTrack");
  const strengthFill = document.getElementById("strengthFill");
  const strengthLabel = document.getElementById("strengthLabel");

  const confirmPassword = document.getElementById("confirmPassword");
  const confirmPasswordError = document.getElementById("confirmPasswordError");

  const address = document.getElementById("address");
  const addressError = document.getElementById("addressError");
  const addressOtherWrap = document.getElementById("addressOtherWrap");
  const addressOther = document.getElementById("addressOther");

  const contactNumber = document.getElementById("contactNumber");
  const contactNumberError = document.getElementById("contactNumberError");

  const terms = document.getElementById("terms");
  const termsError = document.getElementById("termsError");

  const registerBtn = document.getElementById("registerBtn");
  const registerBtnText = document.getElementById("registerBtnText");
  const registerSpinner = document.getElementById("registerSpinner");

  let usernameTakenCheck = ""; // holds an error string if the async check flags it taken
  let usernameCheckTimer = null;

  /* ---- Username availability simulation (debounced) ---- */
  function checkUsernameAvailability() {
    const formatMsg = validateUsername(username.value);
    if (formatMsg) {
      usernameStatus.textContent = "";
      usernameTakenCheck = "";
      return;
    }

    usernameStatus.textContent = "Checking availability...";
    usernameStatus.className = "text-xs text-slate-400 mt-1.5";

    clearTimeout(usernameCheckTimer);
    usernameCheckTimer = setTimeout(() => {
      const taken = TAKEN_USERNAMES.includes(username.value.toLowerCase());
      usernameTakenCheck = taken ? "Username already exists." : "";

      if (taken) {
        usernameStatus.textContent = "";
        setFieldError(username, usernameError, usernameTakenCheck);
      } else {
        usernameStatus.textContent = "✓ Username is available";
        usernameStatus.className = "text-xs text-emerald-600 mt-1.5";
      }
      updateFormValidity();
    }, 500);
  }

  /* ---- Address selection: reveal the free-text field only when "Other" is chosen ---- */
  function syncAddressOtherVisibility() {
    const isOther = address.value === "other";
    addressOtherWrap.classList.toggle("hidden", !isOther);
    if (!isOther) addressOther.value = "";
  }

  /* ---- Password strength meter ---- */
  function renderStrengthMeter() {
    const { label, widthPct, colorClass } = getPasswordStrength(password.value);
    strengthFill.style.width = `${widthPct}%`;
    strengthFill.className = `strength-fill ${colorClass}`;
    strengthLabel.textContent = label ? `Strength: ${label}` : "";
    strengthTrack.classList.toggle("hidden", !password.value);
  }

  /* ---- Master validation: validates everything, toggles errors, enables/disables submit ---- */
  function updateFormValidity({ touchAll = false } = {}) {
    const gmailMsg = validateGmail(gmail.value);
    const usernameFormatMsg = validateUsername(username.value);
    const usernameMsg = usernameFormatMsg || usernameTakenCheck;
    const passwordMsg = validateSignupPassword(password.value);
    const confirmMsg = validateConfirmPassword(password.value, confirmPassword.value);
    const addressMsg = validateAddress(address.value, addressOther.value);
    const contactMsg = validateContactNumber(contactNumber.value);
    const termsMsg = validateTerms(terms.checked);

    if (touchAll || gmail.value) setFieldError(gmail, gmailError, gmailMsg);
    if (touchAll || username.value) setFieldError(username, usernameError, usernameMsg);
    if (touchAll || password.value) setFieldError(password, passwordError, passwordMsg);

    if ((touchAll || confirmPassword.value) && !confirmMsg && confirmPassword.value) {
      setFieldSuccess(confirmPassword, confirmPasswordError, "Passwords matched");
    } else if (touchAll || confirmPassword.value) {
      setFieldError(confirmPassword, confirmPasswordError, confirmMsg);
    }

    if (touchAll || address.value) setFieldError(address, addressError, addressMsg);
    if (touchAll || contactNumber.value) setFieldError(contactNumber, contactNumberError, contactMsg);

    if (touchAll) {
      termsError.textContent = termsMsg ? `⚠ ${termsMsg}` : "";
      termsError.className = termsMsg ? "field-error text-xs text-red-500 mt-1.5" : "hidden";
    }

    const isValid =
      !gmailMsg && !usernameMsg && !passwordMsg && !confirmMsg && !addressMsg && !contactMsg && !termsMsg;

    registerBtn.disabled = !isValid;
    return isValid;
  }

  /* ---- Event listeners ---- */
  gmail.addEventListener("input", () => updateFormValidity());
  gmail.addEventListener("blur", () => setFieldError(gmail, gmailError, validateGmail(gmail.value)));

  username.addEventListener("input", () => {
    checkUsernameAvailability();
    updateFormValidity();
  });

  password.addEventListener("input", () => {
    renderStrengthMeter();
    updateFormValidity();
  });

  confirmPassword.addEventListener("input", () => updateFormValidity());

  address.addEventListener("change", () => {
    syncAddressOtherVisibility();
    updateFormValidity();
    if (address.value === "other") addressOther.focus();
  });
  addressOther.addEventListener("input", () => updateFormValidity());
  contactNumber.addEventListener("input", () => updateFormValidity());
  terms.addEventListener("change", () => {
    if (terms.checked) {
      termsError.textContent = "";
      termsError.className = "hidden";
    }
    updateFormValidity();
  });

  form.addEventListener("submit", async (e) => {
    e.preventDefault();
    const isValid = updateFormValidity({ touchAll: true });

    if (!isValid) {
      showToast("Please complete all required fields.", "error");
      return;
    }

    registerBtn.disabled = true;
    registerBtnText.classList.add("hidden");
    registerSpinner.classList.remove("hidden");

    try {
      const csrfToken = await getCsrfToken();
      if (!csrfToken) {
        showToast("Security token could not be prepared. Please refresh the page.", "error");
        registerSpinner.classList.add("hidden");
        registerBtnText.classList.remove("hidden");
        registerBtn.disabled = false;
        return;
      }

      const response = await fetch("../api/register.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          gmail: gmail.value.trim(),
          username: username.value.trim(),
          password: password.value,
          confirmPassword: confirmPassword.value,
          address: resolveAddressValue(address.value, addressOther.value),
          contactNumber: contactNumber.value.trim(),
          terms: terms.checked,
          csrf_token: csrfToken,
        }),
      });

      const data = await response.json();

      registerSpinner.classList.add("hidden");
      registerBtnText.classList.remove("hidden");
      registerBtn.disabled = false;

      if (!response.ok || !data.success) {
        showToast(data.message || "Registration failed.", "error");
        return;
      }

      showToast("Account created successfully! You may now login.", "success");
      form.reset();
      syncAddressOtherVisibility();
      strengthTrack.classList.add("hidden");
      strengthLabel.textContent = "";
      usernameStatus.textContent = "";
      usernameTakenCheck = "";

      setTimeout(() => {
        window.location.href = "../html/loginform.html";
      }, 1600);
    } catch (error) {
      registerSpinner.classList.add("hidden");
      registerBtnText.classList.remove("hidden");
      registerBtn.disabled = false;
      showToast("Could not reach the server. Make sure XAMPP Apache and MySQL are running.", "error");
    }
  });

  // Initial state (button stays disabled until the form is valid)
  syncAddressOtherVisibility();
  registerBtn.disabled = true;
}

/* ---------------------------------------------------------------------- *
 * INIT
 * ---------------------------------------------------------------------- */
document.addEventListener("DOMContentLoaded", () => {
  // NOTE: initDarkMode() and the main "password" field's initPasswordToggle()
  // are already called by app.js's own DOMContentLoaded listener (app.js is
  // loaded before this file on signup.html). Calling them again here was
  // binding two click handlers to the same buttons, which made dark mode
  // (and the password eye-icon) toggle on and immediately back off on every
  // click. Only initialize what's unique to the signup page here.
  initPasswordToggle("toggleConfirmPassword", "confirmPassword", "eyeOpenConfirm", "eyeClosedConfirm");
  initSignupForm();
});
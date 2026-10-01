/* ==========================================================================
   Boarding House Rental System — register-tenant.js
   Tenant registration form logic. Relies on shared helpers from app.js
   (initDarkMode, showToast, initPasswordToggle, setFieldError,
   setFieldSuccess, validateEmail, validateRequired, validateDigitsOnly,
   validateDateOfBirth, validateGender, initFileUpload) which is loaded
   first in register-tenant.html.
   ========================================================================== */

"use strict";

/* ---------------------------------------------------------------------- *
 * Field-level validators specific to this form
 * ---------------------------------------------------------------------- */
function validateGmail(value) {
  const trimmed = value.trim();
  if (!trimmed) return "Email address is required.";
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

function validateIdType(value) {
  return value ? "" : "Please select which ID you're uploading.";
}

/* ---------------------------------------------------------------------- *
 * Form wiring
 * ---------------------------------------------------------------------- */
function initTenantForm() {
  const form = document.getElementById("tenantForm");
  if (!form) return;

  const firstName = document.getElementById("firstName");
  const firstNameError = document.getElementById("firstNameError");
  const lastName = document.getElementById("lastName");
  const lastNameError = document.getElementById("lastNameError");

  const genderInputs = form.querySelectorAll('input[name="gender"]');
  const genderError = document.getElementById("genderError");

  const dob = document.getElementById("dob");
  const dobError = document.getElementById("dobError");

  const gmail = document.getElementById("gmail");
  const gmailError = document.getElementById("gmailError");

  const mobileNumber = document.getElementById("mobileNumber");
  const mobileNumberError = document.getElementById("mobileNumberError");

  const currentAddress = document.getElementById("currentAddress");
  const currentAddressError = document.getElementById("currentAddressError");

  const username = document.getElementById("username");
  const usernameError = document.getElementById("usernameError");

  const password = document.getElementById("password");
  const passwordError = document.getElementById("passwordError");
  const strengthTrack = document.getElementById("strengthTrack");
  const strengthFill = document.getElementById("strengthFill");
  const strengthLabel = document.getElementById("strengthLabel");

  const confirmPassword = document.getElementById("confirmPassword");
  const confirmPasswordError = document.getElementById("confirmPasswordError");

  const idTypeInputs = form.querySelectorAll('input[name="idType"]');
  const idTypeError = document.getElementById("idTypeError");
  const idFileError = document.getElementById("idFileError");

  const terms = document.getElementById("terms");
  const privacy = document.getElementById("privacy");
  const termsError = document.getElementById("termsError");

  const registerBtn = document.getElementById("registerBtn");
  const registerBtnText = document.getElementById("registerBtnText");
  const registerSpinner = document.getElementById("registerSpinner");

  const idUpload = initFileUpload({
    dropzoneId: "idDropzone",
    inputId: "idInput",
    previewId: "idPreview",
    errorId: "idFileError",
    maxSizeMB: 5,
    label: "ID",
  });

  function getSelectedRadio(inputs) {
    const checked = Array.from(inputs).find((i) => i.checked);
    return checked ? checked.value : "";
  }

  function renderStrengthMeter() {
    const { label, widthPct, colorClass } = getPasswordStrength(password.value);
    strengthFill.style.width = `${widthPct}%`;
    strengthFill.className = `strength-fill ${colorClass}`;
    strengthLabel.textContent = label ? `Strength: ${label}` : "";
    strengthTrack.classList.toggle("hidden", !password.value);
  }

  function updateFormValidity({ touchAll = false } = {}) {
    const firstNameMsg = validateRequired(firstName.value, "First name");
    const lastNameMsg = validateRequired(lastName.value, "Last name");
    const genderValue = getSelectedRadio(genderInputs);
    const genderMsg = validateGender(genderValue);
    const dobMsg = validateDateOfBirth(dob.value);
    const gmailMsg = validateGmail(gmail.value);
    const mobileMsg = validateDigitsOnly(mobileNumber.value, "Mobile number");
    const addressMsg = validateRequired(currentAddress.value, "Current address");
    const usernameMsg = validateUsername(username.value);
    const passwordMsg = validateSignupPassword(password.value);
    const confirmMsg = validateConfirmPassword(password.value, confirmPassword.value);
    const idTypeValue = getSelectedRadio(idTypeInputs);
    const idTypeMsg = validateIdType(idTypeValue);
    const idFileMsg = idUpload && idUpload.isValid() ? "" : "Please upload your ID.";
    const termsMsg = terms.checked && privacy.checked ? "" : "You must agree to the Terms and the Privacy Policy.";

    if (touchAll || firstName.value) setFieldError(firstName, firstNameError, firstNameMsg);
    if (touchAll || lastName.value) setFieldError(lastName, lastNameError, lastNameMsg);

    if (touchAll || genderValue) {
      genderError.textContent = genderMsg ? `⚠ ${genderMsg}` : "";
      genderError.className = genderMsg ? "field-error text-xs text-red-500 mt-1.5" : "hidden";
    }

    if (touchAll || dob.value) setFieldError(dob, dobError, dobMsg);
    if (touchAll || gmail.value) setFieldError(gmail, gmailError, gmailMsg);
    if (touchAll || mobileNumber.value) setFieldError(mobileNumber, mobileNumberError, mobileMsg);
    if (touchAll || currentAddress.value) setFieldError(currentAddress, currentAddressError, addressMsg);
    if (touchAll || username.value) setFieldError(username, usernameError, usernameMsg);
    if (touchAll || password.value) setFieldError(password, passwordError, passwordMsg);

    if ((touchAll || confirmPassword.value) && !confirmMsg && confirmPassword.value) {
      setFieldSuccess(confirmPassword, confirmPasswordError, "Passwords matched");
    } else if (touchAll || confirmPassword.value) {
      setFieldError(confirmPassword, confirmPasswordError, confirmMsg);
    }

    if (touchAll || idTypeValue) {
      idTypeError.textContent = idTypeMsg ? `⚠ ${idTypeMsg}` : "";
      idTypeError.className = idTypeMsg ? "field-error text-xs text-red-500 mt-1.5" : "hidden";
    }

    if (touchAll) {
      termsError.textContent = termsMsg ? `⚠ ${termsMsg}` : "";
      termsError.className = termsMsg ? "field-error text-xs text-red-500 mt-1.5" : "hidden";
    }

    const isValid =
      !firstNameMsg && !lastNameMsg && !genderMsg && !dobMsg && !gmailMsg && !mobileMsg && !addressMsg &&
      !usernameMsg && !passwordMsg && !confirmMsg && !idTypeMsg && !idFileMsg && !termsMsg;

    registerBtn.disabled = !isValid;
    return isValid;
  }

  /* ---- Event listeners ---- */
  firstName.addEventListener("input", () => updateFormValidity());
  lastName.addEventListener("input", () => updateFormValidity());
  genderInputs.forEach((el) => el.addEventListener("change", () => updateFormValidity()));
  dob.addEventListener("change", () => updateFormValidity());
  gmail.addEventListener("input", () => updateFormValidity());
  gmail.addEventListener("blur", () => setFieldError(gmail, gmailError, validateGmail(gmail.value)));
  mobileNumber.addEventListener("input", () => updateFormValidity());
  currentAddress.addEventListener("input", () => updateFormValidity());
  username.addEventListener("input", () => updateFormValidity());
  password.addEventListener("input", () => {
    renderStrengthMeter();
    updateFormValidity();
  });
  confirmPassword.addEventListener("input", () => updateFormValidity());
  idTypeInputs.forEach((el) => el.addEventListener("change", () => updateFormValidity()));
  document.getElementById("idInput").addEventListener("change", () => updateFormValidity());
  terms.addEventListener("change", () => updateFormValidity());
  privacy.addEventListener("change", () => updateFormValidity());

  form.addEventListener("reset", () => {
    setTimeout(() => {
      idUpload.reset();
      strengthTrack.classList.add("hidden");
      strengthLabel.textContent = "";
      updateFormValidity();
    }, 0);
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
      const formData = new FormData();
      formData.append("role", "tenant");
      formData.append("firstName", firstName.value.trim());
      formData.append("middleName", document.getElementById("middleName").value.trim());
      formData.append("lastName", lastName.value.trim());
      formData.append("gender", getSelectedRadio(genderInputs));
      formData.append("dob", dob.value);
      formData.append("gmail", gmail.value.trim());
      formData.append("mobileNumber", mobileNumber.value.trim());
      formData.append("currentAddress", currentAddress.value.trim());
      formData.append("username", username.value.trim());
      formData.append("password", password.value);
      formData.append("confirmPassword", confirmPassword.value);
      formData.append("idType", getSelectedRadio(idTypeInputs));
      formData.append("idFile", idUpload.getFile());
      formData.append("terms", terms.checked);
      formData.append("privacy", privacy.checked);

      const csrfToken = await getCsrfToken();
      if (!csrfToken) {
        showToast("Security token could not be prepared. Please refresh the page.", "error");
        registerSpinner.classList.add("hidden");
        registerBtnText.classList.remove("hidden");
        registerBtn.disabled = false;
        return;
      }
      formData.append('csrf_token', csrfToken);

      const response = await fetch("../api/register-tenant.php", { method: "POST", body: formData });
      const data = await response.json();

      registerSpinner.classList.add("hidden");
      registerBtnText.classList.remove("hidden");
      registerBtn.disabled = false;

      if (!response.ok || !data.success) {
        showToast(data.message || "Registration failed.", "error");
        return;
      }

      showToast("Your tenant account has been created successfully. You may now log in and start searching for boarding houses.", "success");
      form.reset();
      idUpload.reset();
      strengthTrack.classList.add("hidden");
      strengthLabel.textContent = "";

      setTimeout(() => {
        window.location.href = "../html/loginform.html";
      }, 1800);
    } catch (error) {
      registerSpinner.classList.add("hidden");
      registerBtnText.classList.remove("hidden");
      registerBtn.disabled = false;
      showToast("Could not reach the server. Make sure XAMPP Apache and MySQL are running.", "error");
    }
  });

  registerBtn.disabled = true;
}

/* ---------------------------------------------------------------------- *
 * INIT
 * ---------------------------------------------------------------------- */
document.addEventListener("DOMContentLoaded", () => {
  initDarkMode();
  initPasswordToggle("togglePassword", "password", "eyeOpen", "eyeClosed");
  initPasswordToggle("toggleConfirmPassword", "confirmPassword", "eyeOpenConfirm", "eyeClosedConfirm");
  initTenantForm();
});

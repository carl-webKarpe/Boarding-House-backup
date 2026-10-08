<?php

declare(strict_types=1);

/**
 * Step 2 of the Super Admin login: enter the 6-digit code sent by email.
 * Without a pending login (password not entered, or expired) it goes back
 * to the login form. Talks to api/verify-login.php.
 */

require_once __DIR__ . '/../security/security_headers.php';
require_once __DIR__ . '/../security/session.php';
require_once __DIR__ . '/../security/sanitize.php';
require_once __DIR__ . '/../security/two_factor.php';

applySecurityHeaders();
startSecureSession();
header('Cache-Control: no-store');

if (isLoggedIn()) {
    redirectTo(homePathForRole(currentUserRole()));
}
$pending = pendingTwoFactor();
if (!$pending) {
    redirectTo('html/loginform.html');
}

$email = sanitizeForOutput((string) $pending['email']);
$csrf = sanitizeForOutput(generateCsrfToken());
$resendIn = max(0, BH_2FA_RESEND_SECONDS - (time() - (int) $pending['last_sent']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="<?php echo $csrf; ?>" />
  <meta name="robots" content="noindex" />
  <title>Verify it's you | Boarding House Rental System</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700&display=swap" rel="stylesheet" />
  <style>
    :root { --brand: #073F3B; --lime: #A8F15A; --mint: #DFF8C5; --ink: #10201E; --muted: #5B6B68; --cream: #F8F8F1; }
    * { box-sizing: border-box; }
    body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 16px; background: var(--brand); font-family: Inter, system-ui, sans-serif; color: var(--ink); }
    .card { width: 100%; max-width: 420px; background: #fff; border-radius: 28px; padding: 32px 28px; box-shadow: 0 24px 60px -24px rgba(0, 0, 0, .45); }
    .icon { width: 64px; height: 64px; margin: 0 auto; display: grid; place-items: center; border-radius: 20px; background: var(--mint); color: var(--brand); }
    h1 { margin: 18px 0 6px; text-align: center; font: 700 1.5rem Poppins, sans-serif; color: var(--brand); }
    p.lead { margin: 0; text-align: center; font-size: .92rem; line-height: 1.5; color: var(--muted); }
    p.lead strong { color: var(--ink); }
    label { display: block; margin: 26px 0 8px; font-size: .85rem; font-weight: 600; }
    #code { width: 100%; padding: 14px; border: 2px solid #d7dfdd; border-radius: 16px; font: 700 2rem/1 ui-monospace, Menlo, Consolas, monospace; letter-spacing: .5em; text-align: center; text-indent: .5em; color: var(--brand); outline: none; transition: border-color .2s, box-shadow .2s; }
    #code:focus { border-color: var(--brand); box-shadow: 0 0 0 4px rgba(168, 241, 90, .45); }
    #code.invalid { border-color: #e11d48; box-shadow: 0 0 0 4px rgba(225, 29, 72, .15); }
    .btn { width: 100%; margin-top: 16px; padding: 13px 18px; border: 0; border-radius: 999px; background: var(--brand); color: #fff; font: 600 .95rem Inter, sans-serif; cursor: pointer; display: inline-flex; gap: 8px; align-items: center; justify-content: center; transition: background .2s, opacity .2s; }
    .btn:hover { background: #0B5C55; }
    .btn:disabled { opacity: .55; cursor: not-allowed; }
    .row { margin-top: 18px; display: flex; justify-content: space-between; gap: 12px; font-size: .85rem; }
    .link { background: none; border: 0; padding: 0; color: var(--brand); font: 600 .85rem Inter, sans-serif; cursor: pointer; text-decoration: underline; }
    .link:disabled { color: var(--muted); text-decoration: none; cursor: default; }
    a.link { text-decoration: none; }
    .msg { margin-top: 16px; padding: 10px 14px; border-radius: 14px; font-size: .85rem; display: none; }
    .msg.show { display: block; }
    .msg.error { background: #fff1f2; color: #be123c; }
    .msg.ok { background: var(--mint); color: var(--brand); }
    .note { margin-top: 22px; padding-top: 16px; border-top: 1px solid #eef2f1; font-size: .78rem; color: var(--muted); text-align: center; line-height: 1.5; }
    .spin { width: 16px; height: 16px; border: 2px solid rgba(255,255,255,.4); border-top-color: #fff; border-radius: 50%; animation: spin .8s linear infinite; }
    @keyframes spin { to { transform: rotate(360deg); } }
  </style>
</head>
<body>
  <main class="card">
    <div class="icon" aria-hidden="true">
      <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 6L2 7"/></svg>
    </div>
    <h1>Check your email</h1>
    <p class="lead">For your security, we sent a 6-digit code to <strong><?php echo $email; ?></strong>. Enter it below to open the admin dashboard.</p>

    <form id="verifyForm" novalidate>
      <label for="code">Verification code</label>
      <input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9]{6}" placeholder="••••••" required autofocus />
      <button type="submit" class="btn" id="verifyBtn">Verify and log in</button>
    </form>

    <div class="msg" id="msg" role="alert"></div>

    <div class="row">
      <button type="button" class="link" id="resendBtn" <?php echo $resendIn ? 'disabled' : ''; ?>>Send a new code</button>
      <a class="link" href="../html/loginform.html">Back to login</a>
    </div>

    <p class="note">The code expires in <?php echo BH_2FA_CODE_MINUTES; ?> minutes. Didn't get it? Check your Spam folder, or send a new code.</p>
  </main>

  <script>
    (function () {
      "use strict";
      const csrf = document.querySelector('meta[name="csrf-token"]').content;
      const form = document.getElementById("verifyForm");
      const input = document.getElementById("code");
      const verifyBtn = document.getElementById("verifyBtn");
      const resendBtn = document.getElementById("resendBtn");
      const msg = document.getElementById("msg");
      let resendIn = <?php echo (int) $resendIn; ?>;

      function show(text, ok) {
        msg.textContent = text;
        msg.className = "msg show " + (ok ? "ok" : "error");
      }

      function tick() {
        if (resendIn > 0) {
          resendBtn.disabled = true;
          resendBtn.textContent = "Send a new code (" + resendIn + "s)";
          resendIn -= 1;
          setTimeout(tick, 1000);
        } else {
          resendBtn.disabled = false;
          resendBtn.textContent = "Send a new code";
        }
      }

      async function post(body) {
        const res = await fetch("../api/verify-login.php", {
          method: "POST",
          headers: { "Content-Type": "application/json", "X-CSRF-Token": csrf },
          credentials: "same-origin",
          body: JSON.stringify(body),
        });
        let data = {};
        try { data = await res.json(); } catch (e) { /* handled below */ }
        return { ok: res.ok && data.success, status: res.status, data };
      }

      input.addEventListener("input", () => {
        input.value = input.value.replace(/\D/g, "").slice(0, 6);
        input.classList.remove("invalid");
        if (input.value.length === 6) form.requestSubmit();
      });

      form.addEventListener("submit", async (e) => {
        e.preventDefault();
        if (!/^\d{6}$/.test(input.value)) {
          input.classList.add("invalid");
          show("Enter the 6-digit code from the email.");
          return;
        }
        verifyBtn.disabled = true;
        verifyBtn.innerHTML = '<span class="spin"></span> Checking…';
        try {
          const r = await post({ code: input.value });
          if (r.ok) {
            show("Verified! Opening the dashboard…", true);
            const target = /^[a-z/.-]+(\.php)?\/?$/i.test(r.data.data.redirect || "") ? "../" + r.data.data.redirect : "../admin/";
            setTimeout(() => { window.location.href = target; }, 500);
            return;
          }
          if (r.status === 440) {
            show(r.data.message || "Your login expired.");
            setTimeout(() => { window.location.href = "../html/loginform.html"; }, 1500);
            return;
          }
          input.classList.add("invalid");
          input.select();
          show(r.data.message || "The code could not be checked.");
        } catch (err) {
          show("Cannot reach the server. Check your connection.");
        }
        verifyBtn.disabled = false;
        verifyBtn.textContent = "Verify and log in";
      });

      resendBtn.addEventListener("click", async () => {
        resendBtn.disabled = true;
        resendBtn.textContent = "Sending…";
        try {
          const r = await post({ action: "resend" });
          if (r.ok) {
            show(r.data.message || "A new code was sent.", true);
            input.value = "";
            input.focus();
            resendIn = 60;
          } else if (r.status === 440) {
            show(r.data.message);
            setTimeout(() => { window.location.href = "../html/loginform.html"; }, 1500);
            return;
          } else {
            show(r.data.message || "Could not send a new code.");
            const wait = /(\d+) seconds/.exec(r.data.message || "");
            resendIn = wait ? Number(wait[1]) : 0;
          }
        } catch (err) {
          show("Cannot reach the server. Check your connection.");
        }
        tick();
      });

      tick();
    })();
  </script>
</body>
</html>

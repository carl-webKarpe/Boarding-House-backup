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
$sentAgo = time() - (int) $pending['last_sent'];
$resendIn = max(0, BH_2FA_RESEND_SECONDS - $sentAgo);
$expiresIn = max(0, BH_2FA_CODE_MINUTES * 60 - $sentAgo);
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
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@500;600;700&display=swap" rel="stylesheet" />
  <style>
    :root {
      --brand: #073F3B; --brand-2: #0B5C55; --brand-3: #0E7A6F;
      --lime: #A8F15A; --mint: #DFF8C5; --ink: #10201E; --muted: #5B6B68; --line: #E3EAE8;
    }
    * { box-sizing: border-box; }
    html, body { height: 100%; }
    body {
      margin: 0; font-family: Inter, system-ui, sans-serif; color: var(--ink);
      background: var(--brand); overflow-x: hidden;
    }

    /* ---------- Background ---------- */
    .bg { position: fixed; inset: 0; z-index: 0; overflow: hidden;
      background:
        radial-gradient(1200px 600px at 10% -10%, rgba(168, 241, 90, .18), transparent 60%),
        radial-gradient(900px 600px at 110% 110%, rgba(14, 122, 111, .55), transparent 60%),
        linear-gradient(135deg, #052E2B 0%, var(--brand) 45%, #0A4E48 100%);
    }
    .bg::before { /* soft dot grid */
      content: ""; position: absolute; inset: 0;
      background-image: radial-gradient(rgba(255,255,255,.09) 1px, transparent 1px);
      background-size: 26px 26px;
      mask-image: radial-gradient(ellipse at center, #000 30%, transparent 75%);
      -webkit-mask-image: radial-gradient(ellipse at center, #000 30%, transparent 75%);
    }
    .blob { position: absolute; border-radius: 50%; filter: blur(70px); opacity: .55; animation: float 16s ease-in-out infinite; }
    .blob.one { width: 380px; height: 380px; background: #A8F15A; top: -120px; left: -80px; opacity: .22; }
    .blob.two { width: 460px; height: 460px; background: #12A594; bottom: -160px; right: -120px; opacity: .35; animation-delay: -6s; }
    .blob.three { width: 260px; height: 260px; background: #DFF8C5; top: 45%; left: 55%; opacity: .12; animation-delay: -11s; }
    @keyframes float { 0%, 100% { transform: translate(0, 0) scale(1); } 50% { transform: translate(30px, -25px) scale(1.06); } }
    .houses { position: absolute; bottom: 0; left: 0; right: 0; height: 180px; opacity: .10; color: #fff; }

    /* ---------- Layout ---------- */
    .wrap { position: relative; z-index: 1; min-height: 100%; display: grid; place-items: center; padding: 32px 16px; }
    .shell { width: 100%; max-width: 980px; display: grid; grid-template-columns: 1fr; border-radius: 32px; overflow: hidden;
      box-shadow: 0 40px 80px -30px rgba(0,0,0,.55), 0 0 0 1px rgba(255,255,255,.06); animation: rise .5s ease-out both; }
    @media (min-width: 880px) { .shell { grid-template-columns: 1.05fr 1fr; } }
    @keyframes rise { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: none; } }

    /* Brand panel */
    .side { display: none; position: relative; padding: 44px 40px; color: #fff;
      background: linear-gradient(160deg, rgba(255,255,255,.10), rgba(255,255,255,.03)); backdrop-filter: blur(14px); -webkit-backdrop-filter: blur(14px);
      border-right: 1px solid rgba(255,255,255,.08); }
    @media (min-width: 880px) { .side { display: flex; flex-direction: column; } }
    .logo { display: flex; align-items: center; gap: 12px; font: 600 1rem Poppins, sans-serif; }
    .logo span.mark { width: 42px; height: 42px; display: grid; place-items: center; border-radius: 14px; background: var(--lime); color: var(--brand); }
    .side h2 { margin: 40px 0 10px; font: 700 2rem/1.2 Poppins, sans-serif; }
    .side h2 em { font-style: normal; color: var(--lime); }
    .side p { margin: 0; color: rgba(255,255,255,.75); line-height: 1.6; font-size: .95rem; }
    .steps { list-style: none; margin: 34px 0 0; padding: 0; display: grid; gap: 14px; }
    .steps li { display: flex; align-items: center; gap: 12px; font-size: .92rem; color: rgba(255,255,255,.85); }
    .steps .dot { width: 30px; height: 30px; flex-shrink: 0; display: grid; place-items: center; border-radius: 50%; font-size: .8rem; font-weight: 700;
      border: 1.5px solid rgba(255,255,255,.35); }
    .steps .done .dot { background: var(--lime); color: var(--brand); border-color: var(--lime); }
    .steps .now .dot { border-color: var(--lime); color: var(--lime); box-shadow: 0 0 0 5px rgba(168,241,90,.18); }
    .steps .now { color: #fff; font-weight: 600; }
    .side .foot { margin-top: auto; padding-top: 40px; display: flex; align-items: center; gap: 10px; font-size: .8rem; color: rgba(255,255,255,.6); }

    /* Card */
    .card { background: #fff; padding: 40px 32px 32px; }
    @media (min-width: 480px) { .card { padding: 44px 44px 36px; } }
    .badge { display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; border-radius: 999px; background: var(--mint);
      color: var(--brand); font-size: .75rem; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; }
    .icon { position: relative; width: 74px; height: 74px; margin: 22px 0 0; display: grid; place-items: center; border-radius: 24px;
      background: linear-gradient(145deg, var(--mint), #F2FDE6); color: var(--brand); box-shadow: inset 0 0 0 1px rgba(7,63,59,.06); }
    .icon::after { content: ""; position: absolute; top: -4px; right: -4px; width: 18px; height: 18px; border-radius: 50%;
      background: var(--lime); border: 3px solid #fff; animation: ping 2s ease-out infinite; }
    @keyframes ping { 0% { box-shadow: 0 0 0 0 rgba(168,241,90,.7); } 100% { box-shadow: 0 0 0 12px rgba(168,241,90,0); } }
    h1 { margin: 20px 0 8px; font: 700 1.75rem/1.25 Poppins, sans-serif; color: var(--brand); }
    .lead { margin: 0; font-size: .95rem; line-height: 1.6; color: var(--muted); }
    .lead strong { color: var(--ink); overflow-wrap: anywhere; }

    /* Code boxes */
    .label { display: flex; justify-content: space-between; align-items: center; margin: 28px 0 10px; font-size: .85rem; font-weight: 600; }
    .timer { display: inline-flex; align-items: center; gap: 6px; font-weight: 600; color: var(--brand-3); font-variant-numeric: tabular-nums; }
    .timer.low { color: #be123c; }
    .boxes { display: grid; grid-template-columns: repeat(6, 1fr); gap: 10px; }
    .boxes input { width: 100%; aspect-ratio: 1 / 1.15; min-width: 0; border: 2px solid var(--line); border-radius: 16px; background: #F8FBFA;
      font: 700 1.6rem ui-monospace, Menlo, Consolas, monospace; color: var(--brand); text-align: center; outline: none; caret-color: var(--brand-3);
      transition: border-color .15s, box-shadow .15s, background .15s, transform .15s; }
    .boxes input:focus { border-color: var(--brand); background: #fff; box-shadow: 0 0 0 4px rgba(168,241,90,.45); transform: translateY(-1px); }
    .boxes input.filled { border-color: var(--brand-3); background: #fff; }
    .boxes.invalid input { border-color: #e11d48; background: #FFF5F6; }
    .boxes.invalid { animation: shake .35s; }
    .boxes.ok input { border-color: #16a34a; background: #F0FDF4; }
    @keyframes shake { 20%, 60% { transform: translateX(-6px); } 40%, 80% { transform: translateX(6px); } }

    .btn { width: 100%; margin-top: 22px; padding: 15px 18px; border: 0; border-radius: 999px; cursor: pointer;
      background: linear-gradient(135deg, var(--brand), var(--brand-3)); color: #fff; font: 600 1rem Inter, sans-serif;
      display: inline-flex; gap: 10px; align-items: center; justify-content: center; box-shadow: 0 12px 24px -12px rgba(7,63,59,.7);
      transition: transform .15s, box-shadow .15s, opacity .2s; }
    .btn:hover { transform: translateY(-1px); box-shadow: 0 16px 28px -12px rgba(7,63,59,.75); }
    .btn:disabled { opacity: .6; cursor: not-allowed; transform: none; }
    .row { margin-top: 18px; display: flex; justify-content: space-between; align-items: center; gap: 12px; font-size: .88rem; }
    .link { background: none; border: 0; padding: 0; color: var(--brand); font: 600 .88rem Inter, sans-serif; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; text-decoration: none; }
    .link:hover { text-decoration: underline; }
    .link:disabled { color: var(--muted); cursor: default; text-decoration: none; }
    .msg { margin-top: 16px; padding: 11px 14px; border-radius: 14px; font-size: .88rem; display: none; align-items: flex-start; gap: 8px; }
    .msg.show { display: flex; }
    .msg.error { background: #FFF1F2; color: #BE123C; }
    .msg.ok { background: var(--mint); color: var(--brand); }
    .note { margin-top: 26px; padding: 14px 16px; border-radius: 16px; background: #F6F9F8; display: flex; gap: 10px; font-size: .8rem; line-height: 1.5; color: var(--muted); }
    .note svg { flex-shrink: 0; color: var(--brand-3); margin-top: 1px; }
    .spin { width: 18px; height: 18px; border: 2px solid rgba(255,255,255,.4); border-top-color: #fff; border-radius: 50%; animation: spin .8s linear infinite; }
    @keyframes spin { to { transform: rotate(360deg); } }
    .sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0,0,0,0); border: 0; }
    @media (prefers-reduced-motion: reduce) { *, *::before, *::after { animation: none !important; transition: none !important; } }
  </style>
</head>
<body>
  <div class="bg" aria-hidden="true">
    <span class="blob one"></span><span class="blob two"></span><span class="blob three"></span>
    <svg class="houses" viewBox="0 0 1200 180" preserveAspectRatio="xMidYMax slice" fill="currentColor">
      <path d="M0 180V120l60-45 60 45v60zM140 180V95l85-65 85 65v85zM330 180v-50l45-35 45 35v50zM440 180V110l70-55 70 55v70zM600 180V80l100-75 100 75v100zM820 180v-55l50-40 50 40v55zM940 180V100l80-60 80 60v80zM1120 180v-45l40-30 40 30v45z"/>
    </svg>
  </div>

  <div class="wrap">
    <div class="shell">
      <!-- Brand panel (desktop) -->
      <aside class="side">
        <div class="logo">
          <span class="mark"><svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 3 2 11h3v9h5v-6h4v6h5v-9h3z"/></svg></span>
          Boarding House Rental System
        </div>
        <h2>Two-step <em>verification</em></h2>
        <p>The Super Admin account controls every listing and user, so we double-check it's really you before opening the dashboard.</p>
        <ol class="steps">
          <li class="done"><span class="dot">✓</span>Password confirmed</li>
          <li class="now"><span class="dot">2</span>Enter the code from your email</li>
          <li><span class="dot">3</span>Open the admin dashboard</li>
        </ol>
        <div class="foot">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/><path d="m9 12 2 2 4-4"/></svg>
          Codes are single-use and expire after <?php echo BH_2FA_CODE_MINUTES; ?> minutes.
        </div>
      </aside>

      <!-- Code card -->
      <main class="card">
        <span class="badge">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
          Secure login
        </span>
        <div class="icon" aria-hidden="true">
          <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="3"/><path d="m22 7-10 6L2 7"/></svg>
        </div>
        <h1>Check your email</h1>
        <p class="lead">We sent a 6-digit code to <strong><?php echo $email; ?></strong>. Enter it below to open the admin dashboard.</p>

        <form id="verifyForm" novalidate>
          <div class="label">
            <span id="codeLabel">Verification code</span>
            <span class="timer" id="timer" aria-live="polite">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
              <span id="timerText"></span>
            </span>
          </div>
          <div class="boxes" id="boxes" role="group" aria-labelledby="codeLabel">
            <?php for ($i = 1; $i <= 6; $i++): ?>
              <input type="text" inputmode="numeric" maxlength="1" pattern="[0-9]" aria-label="Digit <?php echo $i; ?> of 6" <?php echo $i === 1 ? 'autocomplete="one-time-code" autofocus' : 'autocomplete="off"'; ?> />
            <?php endfor; ?>
          </div>
          <button type="submit" class="btn" id="verifyBtn">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/><path d="m9 12 2 2 4-4"/></svg>
            Verify and log in
          </button>
        </form>

        <div class="msg" id="msg" role="alert"></div>

        <div class="row">
          <button type="button" class="link" id="resendBtn" <?php echo $resendIn ? 'disabled' : ''; ?>>
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12a9 9 0 1 1-3-6.7L21 8"/><path d="M21 3v5h-5"/></svg>
            <span id="resendText">Send a new code</span>
          </button>
          <a class="link" href="../html/loginform.html">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
            Back to login
          </a>
        </div>

        <div class="note">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
          <span>Didn't get it? Check your Spam or Promotions folder, then press "Send a new code". Never share this code with anyone.</span>
        </div>
      </main>
    </div>
  </div>

  <script>
    (function () {
      "use strict";
      const csrf = document.querySelector('meta[name="csrf-token"]').content;
      const form = document.getElementById("verifyForm");
      const boxesWrap = document.getElementById("boxes");
      const boxes = Array.from(boxesWrap.querySelectorAll("input"));
      const verifyBtn = document.getElementById("verifyBtn");
      const verifyHtml = verifyBtn.innerHTML;
      const resendBtn = document.getElementById("resendBtn");
      const resendText = document.getElementById("resendText");
      const timer = document.getElementById("timer");
      const timerText = document.getElementById("timerText");
      const msg = document.getElementById("msg");
      let resendIn = <?php echo (int) $resendIn; ?>;
      let expiresIn = <?php echo (int) $expiresIn; ?>;
      let busy = false;

      const code = () => boxes.map((b) => b.value).join("");
      const show = (text, ok) => { msg.textContent = text; msg.className = "msg show " + (ok ? "ok" : "error"); };
      const clearState = () => { boxesWrap.classList.remove("invalid", "ok"); };
      const paint = () => boxes.forEach((b) => b.classList.toggle("filled", b.value !== ""));

      function fill(digits, start) {
        digits.split("").forEach((d, i) => { if (boxes[start + i]) boxes[start + i].value = d; });
        paint();
        const next = boxes.find((b) => b.value === "");
        (next || boxes[boxes.length - 1]).focus();
        if (code().length === 6) form.requestSubmit();
      }

      boxes.forEach((box, i) => {
        box.addEventListener("input", () => {
          clearState();
          const digits = box.value.replace(/\D/g, "");
          box.value = "";
          if (digits) fill(digits, i); else paint();
        });
        box.addEventListener("keydown", (e) => {
          if (e.key === "Backspace" && !box.value && i > 0) { boxes[i - 1].value = ""; boxes[i - 1].focus(); paint(); e.preventDefault(); }
          if (e.key === "ArrowLeft" && i > 0) { boxes[i - 1].focus(); e.preventDefault(); }
          if (e.key === "ArrowRight" && i < 5) { boxes[i + 1].focus(); e.preventDefault(); }
        });
        box.addEventListener("focus", () => box.select());
        box.addEventListener("paste", (e) => {
          const digits = (e.clipboardData.getData("text") || "").replace(/\D/g, "").slice(0, 6);
          if (!digits) return;
          e.preventDefault();
          clearState();
          boxes.forEach((b) => { b.value = ""; });
          fill(digits, 0);
        });
      });

      function resetBoxes() {
        boxes.forEach((b) => { b.value = ""; });
        paint();
        boxes[0].focus();
      }

      function tickResend() {
        if (resendIn > 0) {
          resendBtn.disabled = true;
          resendText.textContent = "Send a new code (" + resendIn + "s)";
          resendIn -= 1;
          setTimeout(tickResend, 1000);
        } else {
          resendBtn.disabled = false;
          resendText.textContent = "Send a new code";
        }
      }

      let expiryTimer = null;
      function tickExpiry() {
        clearTimeout(expiryTimer);
        if (expiresIn <= 0) {
          timerText.textContent = "Code expired";
          timer.classList.add("low");
          return;
        }
        const m = Math.floor(expiresIn / 60);
        const s = String(expiresIn % 60).padStart(2, "0");
        timerText.textContent = "Expires in " + m + ":" + s;
        timer.classList.toggle("low", expiresIn <= 60);
        expiresIn -= 1;
        expiryTimer = setTimeout(tickExpiry, 1000);
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

      form.addEventListener("submit", async (e) => {
        e.preventDefault();
        if (busy) return;
        if (!/^\d{6}$/.test(code())) {
          boxesWrap.classList.add("invalid");
          show("Enter all 6 digits from the email.");
          (boxes.find((b) => b.value === "") || boxes[0]).focus();
          return;
        }
        busy = true;
        verifyBtn.disabled = true;
        verifyBtn.innerHTML = '<span class="spin"></span> Checking…';
        try {
          const r = await post({ code: code() });
          if (r.ok) {
            boxesWrap.classList.add("ok");
            show("Verified! Opening the dashboard…", true);
            verifyBtn.innerHTML = "Welcome back!";
            const target = /^[a-z/.-]+(\.php)?\/?$/i.test(r.data.data.redirect || "") ? "../" + r.data.data.redirect : "../admin/";
            setTimeout(() => { window.location.href = target; }, 600);
            return;
          }
          if (r.status === 440) {
            show(r.data.message || "Your login expired.");
            setTimeout(() => { window.location.href = "../html/loginform.html"; }, 1500);
            return;
          }
          boxesWrap.classList.add("invalid");
          show(r.data.message || "The code could not be checked.");
          resetBoxes();
        } catch (err) {
          show("Cannot reach the server. Check your connection.");
        }
        busy = false;
        verifyBtn.disabled = false;
        verifyBtn.innerHTML = verifyHtml;
      });

      resendBtn.addEventListener("click", async () => {
        resendBtn.disabled = true;
        resendText.textContent = "Sending…";
        try {
          const r = await post({ action: "resend" });
          if (r.ok) {
            show(r.data.message || "A new code was sent.", true);
            clearState();
            resetBoxes();
            resendIn = 60;
            expiresIn = <?php echo BH_2FA_CODE_MINUTES * 60; ?>;
            tickExpiry();
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
        tickResend();
      });

      tickResend();
      tickExpiry();
    })();
  </script>
</body>
</html>

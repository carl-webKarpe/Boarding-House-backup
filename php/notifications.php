<?php
require_once __DIR__ . '/../security/security_headers.php';
require_once __DIR__ . '/../security/session.php';
require_once __DIR__ . '/../security/sanitize.php';

applySecurityHeaders();
requireLogin();

$userName = sanitizeForOutput($_SESSION['username'] ?? 'Student');
$role = sanitizeForOutput($_SESSION['role'] ?? 'tenant');
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Notifications — Boarding House Rental System</title>
  <meta name="description" content="View your notifications, announcements, and trending locations for boarding house rentals." />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Poppins:wght@500;600;700;800&display=swap" rel="stylesheet" />
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      darkMode: 'class',
      theme: {
        extend: {
          colors: {
            primary: '#22C55E',
            accent: '#16A34A',
            ink: '#0F172A',
            muted: '#64748B'
          },
          fontFamily: {
            display: ['Poppins', 'sans-serif'],
            body: ['Inter', 'sans-serif']
          }
        }
      }
    };
  </script>
  <link rel="stylesheet" href="../style.css" />
</head>
<body class="min-h-screen bg-slate-50 font-body text-slate-800 transition-colors duration-300 dark:bg-slate-950 dark:text-slate-100">
  <button id="darkModeToggle" type="button" class="fixed right-4 top-4 z-50 grid h-11 w-11 place-items-center rounded-full border border-slate-200 bg-white/90 text-slate-700 shadow-soft backdrop-blur transition hover:scale-105 dark:border-slate-700 dark:bg-slate-900/90 dark:text-slate-100" aria-label="Toggle dark mode">
    <svg id="iconSun" class="hidden h-5 w-5 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2m0 14v2m9-9h-2M5 12H3m15.36 6.36-1.42-1.42M7.05 7.05 5.64 5.64m12.73 0-1.42 1.42M7.05 16.95l-1.42 1.42M12 8a4 4 0 100 8 4 4 0 000-8z" /></svg>
    <svg id="iconMoon" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M20.354 15.354A9 9 0 118.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" /></svg>
  </button>

  <div id="toastContainer" class="fixed left-1/2 top-4 z-[60] flex w-[90%] max-w-sm -translate-x-1/2 flex-col gap-3 sm:left-auto sm:right-6 sm:top-6 sm:translate-x-0" aria-live="polite"></div>

  <nav class="sticky top-0 z-40 border-b border-slate-200/80 bg-white/85 backdrop-blur-xl dark:border-slate-800 dark:bg-slate-900/85">
    <div class="mx-auto flex max-w-7xl items-center gap-3 px-4 py-3 sm:px-6 lg:px-8">
      <a href="browse-rooms.php" class="flex items-center gap-3">
        <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-emerald-600 text-lg font-semibold text-white shadow-lg shadow-emerald-600/20">BH</div>
        <div>
          <div class="font-display text-sm font-semibold text-slate-900 dark:text-white">Boarding House</div>
          <div class="text-xs text-slate-500 dark:text-slate-400">Rental System</div>
        </div>
      </a>

      <div class="hidden items-center gap-2 lg:flex">
        <a href="browse-rooms.php" class="rounded-full px-3 py-2 text-sm font-semibold text-slate-600 transition hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white">Home</a>
        <a href="notifications.php" class="rounded-full bg-emerald-600 px-3 py-2 text-sm font-semibold text-white shadow-sm">Notifications</a>
        <a href="dashboard.php" class="rounded-full px-3 py-2 text-sm font-semibold text-slate-600 transition hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white">Dashboard</a>
      </div>

      <div class="relative ml-auto flex items-center gap-2">
        <button id="profileMenuButton" type="button" class="flex items-center gap-2 rounded-full border border-slate-200 bg-slate-50 px-2 py-1.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-emerald-300 hover:text-emerald-600 dark:border-slate-700 dark:bg-slate-800/80 dark:text-slate-100">
          <span class="flex h-8 w-8 items-center justify-center rounded-full bg-emerald-100 text-sm font-bold text-emerald-700"><?php echo strtoupper(substr($userName, 0, 1)); ?></span>
          <span class="hidden sm:inline"><?php echo $userName; ?></span>
        </button>
        <div id="profileDropdown" class="absolute right-0 top-full mt-2 hidden w-44 rounded-2xl border border-slate-200 bg-white p-2 shadow-soft dark:border-slate-700 dark:bg-slate-900">
          <a href="dashboard.php" class="block rounded-xl px-3 py-2 text-sm hover:bg-slate-100 dark:hover:bg-slate-800">Profile</a>
          <a href="dashboard.php" class="block rounded-xl px-3 py-2 text-sm hover:bg-slate-100 dark:hover:bg-slate-800">Settings</a>
          <form method="post" action="logout.php"><?php echo csrfInput(); ?><button type="submit" class="block w-full rounded-xl px-3 py-2 text-left text-sm text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-500/10">Logout</button></form>
        </div>
      </div>
    </div>
  </nav>

  <main class="mx-auto max-w-5xl px-4 pb-24 pt-6 sm:px-6 lg:px-8">
    <section class="rounded-[32px] border border-slate-200 bg-white/90 p-6 shadow-soft dark:border-slate-800 dark:bg-slate-900/80">
      <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
          <p class="text-sm font-semibold uppercase tracking-[0.25em] text-emerald-600">Notifications center</p>
          <h1 class="mt-2 font-display text-3xl font-semibold text-slate-900 dark:text-white">Everything you need to know in one place</h1>
          <p class="mt-3 text-sm text-slate-500 dark:text-slate-400">Check your latest alerts, announcements, and market trends without the clutter of the home page.</p>
        </div>
        <a href="browse-rooms.php" class="inline-flex items-center rounded-full border border-slate-200 bg-slate-50 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:border-emerald-300 hover:text-emerald-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200">Back to home</a>
      </div>
    </section>

    <div class="mt-6 grid gap-6 lg:grid-cols-[1.2fr_0.8fr]">
      <div class="space-y-6">
        <section class="rounded-[28px] border border-slate-200 bg-white/90 p-5 shadow-soft dark:border-slate-800 dark:bg-slate-900/80">
          <div class="flex items-center justify-between">
            <div>
              <h2 class="font-display text-xl font-semibold text-slate-900 dark:text-white">Notifications</h2>
              <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">New updates about your saved rooms and landlord replies.</p>
            </div>
            <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-950/35 dark:text-emerald-400">3 new</span>
          </div>
          <div class="mt-5 space-y-3 text-sm text-slate-600 dark:text-slate-300">
            <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-800/70">
              <p class="font-semibold text-slate-900 dark:text-white">A new room match was added near your preferred school.</p>
              <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Explore the newest listing with the quiet study lounge and fast Wi-Fi.</p>
            </div>
            <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-800/70">
              <p class="font-semibold text-slate-900 dark:text-white">Your saved property has a 25% discount before Friday.</p>
              <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Book early to claim the reduced monthly rate and free security deposit.</p>
            </div>
            <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-800/70">
              <p class="font-semibold text-slate-900 dark:text-white">The landlord replied to your message in under 10 minutes.</p>
              <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Check your dashboard for the booking confirmation request.</p>
            </div>
          </div>
        </section>

        <section class="rounded-[28px] border border-slate-200 bg-white/90 p-5 shadow-soft dark:border-slate-800 dark:bg-slate-900/80">
          <h2 class="font-display text-xl font-semibold text-slate-900 dark:text-white">Announcements</h2>
          <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Platform updates and student safety alerts.</p>
          <div class="mt-5 space-y-3 text-sm text-slate-600 dark:text-slate-300">
            <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-800/70">New verification checks are now faster for landlords.</div>
            <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-800/70">Student safety badges are now displayed on all premium listings.</div>
          </div>
        </section>
      </div>

      <aside class="space-y-6">
        <section class="rounded-[28px] border border-slate-200 bg-white/90 p-5 shadow-soft dark:border-slate-800 dark:bg-slate-900/80">
          <h2 class="font-display text-xl font-semibold text-slate-900 dark:text-white">Trending locations</h2>
          <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">See where demand is rising near schools.</p>
          <div class="mt-5 space-y-3 text-sm text-slate-700 dark:text-slate-300">
            <div class="flex items-center justify-between rounded-3xl bg-slate-50 px-4 py-3 dark:bg-slate-800/70"><span>Surigao City</span><span class="font-semibold text-emerald-600">+18%</span></div>
            <div class="flex items-center justify-between rounded-3xl bg-slate-50 px-4 py-3 dark:bg-slate-800/70"><span>Canlanipa</span><span class="font-semibold text-emerald-600">+12%</span></div>
            <div class="flex items-center justify-between rounded-3xl bg-slate-50 px-4 py-3 dark:bg-slate-800/70"><span>Washington</span><span class="font-semibold text-emerald-600">+9%</span></div>
          </div>
        </section>

        <section class="rounded-[28px] border border-slate-200 bg-white/90 p-5 shadow-soft dark:border-slate-800 dark:bg-slate-900/80">
          <h2 class="font-display text-lg font-semibold text-slate-900 dark:text-white">Need help?</h2>
          <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Contact support if you want personalized alerts or booking assistance.</p>
          <a href="../html/index.html#footer" class="mt-4 inline-flex w-full items-center justify-center rounded-full bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-700">Contact support</a>
        </section>
      </aside>
    </div>
  </main>

  <script src="../registerJS/app.js"></script>
</body>
</html>

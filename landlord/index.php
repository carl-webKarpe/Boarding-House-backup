<?php

declare(strict_types=1);

/**
 * Landlord Dashboard (single-page app shell).
 *
 * Access control: only landlord accounts get this page.
 * Not logged in -> login form. Tenants/admins -> their own home page.
 * Every request the page makes goes to /api/landlord/, which only ever
 * returns or changes the logged-in landlord's own listings.
 */

require_once __DIR__ . '/../security/security_headers.php';
require_once __DIR__ . '/../security/session.php';
require_once __DIR__ . '/../security/sanitize.php';
require_once __DIR__ . '/../security/database.php';

applySecurityHeaders(['https://www.openstreetmap.org'], true);
requireRole([ROLE_LANDLORD]);
header('Cache-Control: no-store');

$verification = 'pending';
try {
    $stmt = getDb()->prepare('SELECT verification_status FROM landlords WHERE user_id = :id');
    $stmt->execute([':id' => currentUserId()]);
    $verification = (string) ($stmt->fetchColumn() ?: 'pending');
} catch (Throwable) {
    // The dashboard still loads; the API reports database problems itself.
}

$landlordName = sanitizeForOutput($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Landlord');
$landlordEmail = sanitizeForOutput($_SESSION['email'] ?? '');
$initial = sanitizeForOutput(strtoupper(substr((string) ($_SESSION['full_name'] ?? 'L'), 0, 1)));
$csrfToken = sanitizeForOutput(generateCsrfToken());
$assetVersion = '20261008';
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="<?php echo $csrfToken; ?>" />
  <meta name="robots" content="noindex" />
  <title>Landlord Dashboard | Boarding House Rental System</title>
  <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%2316A34A'%3E%3Cpath d='M12 3 2 11h3v9h5v-6h4v6h5v-9h3z'/%3E%3C/svg%3E" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@500;600;700&display=swap" rel="stylesheet" />
  <!-- Same look as the admin dashboard (built locally with npm run build:admin). -->
  <link rel="stylesheet" href="../admin/assets/css/tailwind.css?v=<?php echo $assetVersion; ?>" />
  <link rel="stylesheet" href="../admin/assets/css/admin.css?v=<?php echo $assetVersion; ?>" />
  <link rel="stylesheet" href="assets/css/landlord.css?v=<?php echo $assetVersion; ?>" />
</head>
<body class="h-full bg-canvas font-sans text-ink antialiased" data-verification="<?php echo sanitizeForOutput($verification); ?>">
  <a href="#view" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[100] focus:rounded-lg focus:bg-white focus:px-4 focus:py-2 focus:shadow-card">Skip to content</a>

  <div id="sidebarOverlay" class="fixed inset-0 z-30 hidden bg-slate-900/40 backdrop-blur-sm lg:hidden" aria-hidden="true"></div>

  <!-- Sidebar -->
  <aside id="sidebar" class="sidebar fixed inset-y-0 left-0 z-40 flex w-72 -translate-x-full flex-col border-r border-slate-200 bg-white transition-[transform,width] duration-300 lg:translate-x-0" aria-label="Landlord navigation">
    <div class="flex h-16 shrink-0 items-center gap-3 border-b border-slate-100 px-5">
      <div class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-primary text-white shadow-lg shadow-primary/30">
        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 3 2 11h3v9h5v-6h4v6h5v-9h3z"/></svg>
      </div>
      <div class="sidebar-label min-w-0 leading-tight">
        <p class="truncate font-display text-sm font-semibold text-ink">Boarding House</p>
        <p class="truncate text-xs text-slate-500">Rental System · Landlord</p>
      </div>
      <button id="sidebarClose" type="button" class="ml-auto grid h-9 w-9 place-items-center rounded-lg text-slate-500 hover:bg-slate-100 lg:hidden" aria-label="Close menu">
        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
      </button>
    </div>

    <nav id="sidebarNav" class="flex-1 space-y-6 overflow-y-auto px-3 py-5"></nav>

    <div class="border-t border-slate-100 p-3">
      <button type="button" data-action="logout" class="nav-link w-full text-rose-600 hover:bg-rose-50 hover:text-rose-700">
        <span class="nav-icon" data-icon="logout"></span>
        <span class="sidebar-label">Logout</span>
      </button>
    </div>
  </aside>

  <!-- Main column -->
  <div id="mainColumn" class="main-column flex min-h-full flex-col transition-[padding] duration-300 lg:pl-72">
    <header class="sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-slate-200 bg-white/90 px-4 backdrop-blur sm:px-6">
      <button id="sidebarToggle" type="button" class="grid h-10 w-10 shrink-0 place-items-center rounded-xl text-slate-600 hover:bg-slate-100" aria-label="Toggle menu" aria-controls="sidebar" aria-expanded="false">
        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
      </button>

      <p class="hidden truncate font-display text-base font-semibold text-ink sm:block" id="pageTitle">Dashboard</p>

      <div class="ml-auto flex items-center gap-1 sm:gap-2">
        <a href="#/add-house" class="btn btn-primary btn-sm hidden sm:inline-flex">
          <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
          Add Boarding House
        </a>
        <div class="relative">
          <button id="notifButton" type="button" class="relative grid h-10 w-10 place-items-center rounded-xl text-slate-600 hover:bg-slate-100" aria-label="Notifications" aria-haspopup="true" aria-expanded="false">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.4-1.4A2 2 0 0 1 18 14.2V11a6 6 0 1 0-12 0v3.2a2 2 0 0 1-.6 1.4L4 17h5m6 0v1a3 3 0 1 1-6 0v-1m6 0H9"/></svg>
            <span id="notifBadge" class="absolute right-1.5 top-1.5 hidden min-w-[18px] rounded-full bg-rose-500 px-1 text-center text-[10px] font-bold leading-[18px] text-white"></span>
          </button>
          <div id="notifPanel" class="dropdown-panel absolute right-0 top-12 hidden w-[min(22rem,calc(100vw-2rem))]"></div>
        </div>

        <div class="relative">
          <button id="profileButton" type="button" class="flex items-center gap-2 rounded-xl p-1 pr-2 hover:bg-slate-100" aria-haspopup="true" aria-expanded="false">
            <span class="grid h-9 w-9 place-items-center overflow-hidden rounded-xl bg-primary-100 font-semibold text-primary-dark" data-landlord-avatar><?php echo $initial; ?></span>
            <span class="hidden text-left leading-tight md:block">
              <span class="block text-sm font-semibold" data-landlord-name><?php echo $landlordName; ?></span>
              <span class="block text-xs text-slate-500">Landlord</span>
            </span>
            <svg class="hidden h-4 w-4 text-slate-400 md:block" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>
          </button>
          <div id="profilePanel" class="dropdown-panel absolute right-0 top-12 hidden w-60 p-2">
            <div class="border-b border-slate-100 px-3 pb-3 pt-1">
              <p class="truncate text-sm font-semibold" data-landlord-name><?php echo $landlordName; ?></p>
              <p class="truncate text-xs text-slate-500"><?php echo $landlordEmail; ?></p>
            </div>
            <a href="#/profile" class="menu-item mt-1">My profile</a>
            <a href="#/profile?tab=security" class="menu-item">Change password</a>
            <a href="../php/browse-rooms.php" class="menu-item">View student listing page</a>
            <button type="button" data-action="logout" class="menu-item w-full text-left text-rose-600 hover:bg-rose-50">Logout</button>
          </div>
        </div>
      </div>
    </header>

    <main id="view" class="flex-1 px-4 py-6 sm:px-6 lg:px-8" tabindex="-1" aria-live="polite"></main>

    <footer class="px-4 pb-6 text-center text-xs text-slate-400 sm:px-6 lg:px-8">
      Boarding House Rental System · Landlord Dashboard
    </footer>
  </div>

  <div id="modalRoot"></div>
  <div id="toastRoot" class="pointer-events-none fixed inset-x-4 bottom-4 z-[70] flex flex-col items-center gap-2 sm:inset-x-auto sm:right-6 sm:items-end" aria-live="polite"></div>

  <form id="logoutForm" method="post" action="../php/logout.php" class="hidden">
    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>" />
  </form>

  <script type="module" src="assets/js/app.js?v=<?php echo $assetVersion; ?>"></script>
</body>
</html>

<?php

declare(strict_types=1);

/**
 * Student room marketplace: browse rooms (newest first), view details,
 * reserve a room and contact the landlord.
 *
 * Everything on this page is loaded from the database through
 * api/rooms.php, api/reservations.php and api/inquiries.php by
 * registerJS/rooms.js. Guests can browse and send questions; reserving
 * needs a student (tenant) account.
 */

require_once __DIR__ . '/../security/security_headers.php';
require_once __DIR__ . '/../security/session.php';
require_once __DIR__ . '/../security/sanitize.php';

// The room details window embeds an OpenStreetMap map.
applySecurityHeaders(['https://www.openstreetmap.org']);
startSecureSession();

$isLoggedIn = isLoggedIn();
$role = currentUserRole();
$fullName = (string) ($_SESSION['full_name'] ?? $_SESSION['username'] ?? '');
$account = ['name' => '', 'email' => '', 'phone' => ''];

if ($isLoggedIn) {
    try {
        require_once __DIR__ . '/../security/database.php';
        $stmt = getDb()->prepare('SELECT first_name, last_name, email, contact_number FROM users WHERE id = :id');
        $stmt->execute([':id' => currentUserId()]);
        if ($row = $stmt->fetch()) {
            $account = [
                'name' => trim($row['first_name'] . ' ' . $row['last_name']),
                'email' => (string) $row['email'],
                'phone' => (string) $row['contact_number'],
            ];
        }
    } catch (Throwable $e) {
        // The page still works; the forms just won't be pre-filled.
    }
}

$e = static fn (string $value): string => sanitizeForOutput($value);
$initial = $e(strtoupper(substr($fullName ?: 'G', 0, 1)));
$homeForRole = $isLoggedIn ? appUrl(homePathForRole($role)) : '';
$assetVersion = '20261008';
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="<?php echo $e(generateCsrfToken()); ?>" />
  <title>Browse Rooms | Boarding House Rental System</title>
  <meta name="description" content="Find, compare and reserve boarding house rooms near Siargao Island Institute of Technology (SIIT)." />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@500;600;700&display=swap" rel="stylesheet" />
  <!-- Built locally (npm run build:tenant) so the page also works offline. -->
  <link rel="stylesheet" href="../assets/css/tenant.css?v=<?php echo $assetVersion; ?>" />
</head>
<body class="min-h-screen bg-cream font-sans text-ink antialiased"
      data-logged-in="<?php echo $isLoggedIn ? '1' : '0'; ?>"
      data-role="<?php echo $e((string) $role); ?>"
      data-name="<?php echo $e($account['name']); ?>"
      data-email="<?php echo $e($account['email']); ?>"
      data-phone="<?php echo $e($account['phone']); ?>">

  <a href="#roomList" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[100] focus:rounded-xl focus:bg-white focus:px-4 focus:py-2 focus:shadow-card">Skip to rooms</a>

  <!-- ===================== Header ===================== -->
  <header class="sticky top-0 z-40 border-b border-brand/10 bg-brand text-white">
    <div class="mx-auto flex h-16 max-w-7xl items-center gap-4 px-4 sm:px-6 lg:px-8">
      <a href="../html/index.html" class="flex min-w-0 items-center gap-3">
        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-lime text-brand" aria-hidden="true">
          <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor"><path d="M12 3 2 11h3v9h5v-6h4v6h5v-9h3z"/></svg>
        </span>
        <span class="truncate font-display text-sm font-semibold leading-tight sm:text-base">
          Boarding House<span class="hidden sm:inline"> Rental System</span>
        </span>
      </a>

      <nav class="ml-auto flex items-center gap-1 text-sm font-semibold" aria-label="Main">
        <a href="../html/index.html" class="hidden rounded-full px-3 py-2 text-white/80 transition hover:bg-white/10 hover:text-white md:inline-flex">Home</a>
        <a href="browse-rooms.php" class="hidden rounded-full bg-white/10 px-3 py-2 text-lime md:inline-flex" aria-current="page">Browse Rooms</a>
        <?php if ($isLoggedIn && $role === ROLE_TENANT): ?>
          <button type="button" data-open-reservations class="inline-flex items-center gap-2 rounded-full px-3 py-2 text-white/90 transition hover:bg-white/10 hover:text-white">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
            <span class="hidden sm:inline">My Reservations</span>
          </button>
        <?php endif; ?>

        <?php if ($isLoggedIn): ?>
          <div class="relative" data-menu>
            <button type="button" class="ml-1 flex items-center gap-2 rounded-full bg-white/10 py-1 pl-1 pr-3 transition hover:bg-white/20" aria-haspopup="true" aria-expanded="false" data-menu-button>
              <span class="grid h-8 w-8 place-items-center rounded-full bg-lime font-bold text-brand"><?php echo $initial; ?></span>
              <span class="hidden max-w-[10rem] truncate sm:inline"><?php echo $e($fullName); ?></span>
            </button>
            <div class="absolute right-0 top-12 hidden w-56 rounded-2xl border border-brand/10 bg-white p-2 text-ink shadow-card" data-menu-panel>
              <p class="truncate px-3 pb-2 pt-1 text-xs text-muted"><?php echo $e(roleLabel($role)); ?></p>
              <?php if ($role !== ROLE_TENANT): ?>
                <a href="<?php echo $e($homeForRole); ?>" class="block rounded-xl px-3 py-2 text-sm hover:bg-mint">My dashboard</a>
              <?php else: ?>
                <button type="button" data-open-reservations class="block w-full rounded-xl px-3 py-2 text-left text-sm hover:bg-mint">My reservations &amp; messages</button>
              <?php endif; ?>
              <form method="post" action="logout.php">
                <?php echo csrfInput(); ?>
                <button type="submit" class="block w-full rounded-xl px-3 py-2 text-left text-sm text-rose-600 hover:bg-rose-50">Log out</button>
              </form>
            </div>
          </div>
        <?php else: ?>
          <a href="../html/loginform.html" class="rounded-full px-3 py-2 text-white/90 transition hover:bg-white/10 hover:text-white">Log in</a>
          <a href="../html/account-type.html" class="rounded-full bg-lime px-4 py-2 text-brand transition hover:brightness-95">Sign up</a>
        <?php endif; ?>
      </nav>
    </div>
  </header>

  <main>
    <!-- ===================== Intro + filters ===================== -->
    <section class="bg-brand pb-10 pt-8 text-white sm:pt-10">
      <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-lime">Rooms near SIIT · Dapa, Surigao del Norte</p>
        <h1 class="mt-2 font-display text-3xl font-semibold leading-tight sm:text-4xl">Find your room for the semester</h1>
        <p class="mt-2 max-w-2xl text-sm text-white/75 sm:text-base">Compare prices, photos and amenities, then reserve or message the landlord — all in one place.</p>
        <ul class="mt-5 flex flex-wrap gap-2 text-xs font-semibold sm:text-sm" id="summaryChips" aria-live="polite">
          <li class="rounded-full bg-white/10 px-3 py-1.5"><span class="skeleton-text">Loading rooms…</span></li>
        </ul>
      </div>
    </section>

    <div class="relative z-30 -mt-6 px-4 sm:px-6 lg:sticky lg:top-16 lg:px-8">
      <form id="filters" class="mx-auto grid max-w-7xl gap-2 rounded-3xl border border-brand/10 bg-white p-3 shadow-card sm:grid-cols-2 lg:grid-cols-[1.6fr_1fr_1fr_1fr_auto]" role="search" aria-label="Filter rooms">
        <label class="relative block">
          <span class="sr-only">Search</span>
          <svg class="pointer-events-none absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
          <input type="search" name="q" placeholder="Boarding house, barangay or school" class="field pl-10" autocomplete="off" />
        </label>
        <label class="block">
          <span class="sr-only">Room type</span>
          <select name="type" class="field">
            <option value="">All room types</option>
            <option value="solo">Single Room</option>
            <option value="shared">Shared Room</option>
          </select>
        </label>
        <label class="block">
          <span class="sr-only">Budget</span>
          <select name="max_price" class="field">
            <option value="">Any budget</option>
            <option value="1500">Up to ₱1,500</option>
            <option value="2500">Up to ₱2,500</option>
            <option value="3500">Up to ₱3,500</option>
            <option value="5000">Up to ₱5,000</option>
          </select>
        </label>
        <label class="block">
          <span class="sr-only">Sort by</span>
          <select name="sort" class="field">
            <option value="newest">Newest first</option>
            <option value="nearest">Nearest to SIIT</option>
            <option value="price_asc">Price: low to high</option>
            <option value="price_desc">Price: high to low</option>
          </select>
        </label>
        <label class="flex cursor-pointer items-center justify-between gap-3 rounded-2xl border border-brand/10 px-4 py-2.5 text-sm font-semibold sm:col-span-2 lg:col-span-1">
          Available only
          <span class="toggle"><input type="checkbox" name="available" value="1" /><span></span></span>
        </label>
      </form>
    </div>

    <!-- ===================== Results ===================== -->
    <section class="mx-auto max-w-7xl px-4 pb-16 pt-6 sm:px-6 lg:px-8" aria-labelledby="resultsTitle">
      <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h2 id="resultsTitle" class="font-display text-lg font-semibold text-brand" aria-live="polite">Rooms</h2>
        <p class="flex items-center gap-2 text-xs text-muted"><span class="h-2 w-2 animate-pulse rounded-full bg-emerald-500" aria-hidden="true"></span>Live from the database</p>
      </div>

      <div id="updateBanner" class="mb-4 hidden items-center justify-between gap-3 rounded-2xl border border-lime bg-mint px-4 py-3 text-sm font-medium text-brand" role="status">
        <span>New or updated rooms are available.</span>
        <button type="button" class="btn-primary btn-sm" data-refresh>Show latest</button>
      </div>

      <div id="roomList" class="space-y-6" aria-busy="true"></div>

      <div class="mt-8 flex flex-col items-center gap-2" id="listFooter">
        <button type="button" id="loadMore" class="btn-outline hidden">Load more rooms</button>
        <p id="listStatus" class="text-sm text-muted" aria-live="polite"></p>
      </div>
    </section>
  </main>

  <footer class="border-t border-brand/10 bg-white">
    <div class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-2 px-4 py-6 text-xs text-muted sm:flex-row sm:px-6 lg:px-8">
      <p>© <span data-year></span> Boarding House Rental System · Dapa, Surigao del Norte</p>
      <p>Prices and availability come straight from the landlords' listings.</p>
    </div>
  </footer>

  <!-- Dialogs and notifications are rendered here by registerJS/rooms.js -->
  <div id="dialogRoot"></div>
  <div id="toastRoot" class="pointer-events-none fixed inset-x-4 bottom-4 z-[80] flex flex-col items-center gap-2 sm:inset-x-auto sm:right-6 sm:items-end" aria-live="polite"></div>

  <script src="../registerJS/rooms.js?v=<?php echo $assetVersion; ?>" defer></script>
</body>
</html>

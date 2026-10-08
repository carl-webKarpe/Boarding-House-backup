# Boarding House Rental System

A web-based system that helps students — especially new students and those from other towns — find safe, affordable boarding houses near their school. Landlords manage their listings, and administrators oversee the whole platform from an admin dashboard.

- **Frontend:** HTML5, Tailwind CSS, JavaScript (ES modules)
- **Backend / API:** PHP 8 (PDO) — JSON REST endpoints
- **Database:** MySQL (created and managed with MySQL Workbench) — MariaDB/XAMPP also works

---

## System architecture

```text
                 USER (student / landlord / administrator)
                                  │
                                  ▼
        ┌─────────────────────────────────────────────────┐
        │  FRONTEND UI  (html/, php/ pages, admin/)        │
        │  HTML + Tailwind CSS + JavaScript                │
        │  admin/assets/js/api.js = the only file that     │
        │  calls the backend                               │
        └───────────────────────┬─────────────────────────┘
                                │  fetch() — JSON, session cookie, CSRF token
                                ▼
        ┌─────────────────────────────────────────────────┐
        │  BACKEND / API  (api/, api/admin/)               │
        │  Authentication · Authorization (roles)          │
        │  Validation · Business rules · Activity logging  │
        │  security/ = shared security layer               │
        └───────────────────────┬─────────────────────────┘
                                │  PDO prepared statements
                                ▼
        ┌─────────────────────────────────────────────────┐
        │  MySQL DATABASE  (bhsystem)                      │
        └─────────────────────────────────────────────────┘
                                ▲
                                │  used by the developer / DBA only
        ┌─────────────────────────────────────────────────┐
        │  MySQL Workbench — create, inspect, back up the  │
        │  database. It is NOT part of the running app.    │
        └─────────────────────────────────────────────────┘
```

## Main users and roles

| Role | Can do |
| --- | --- |
| **Tenant / Student** (`tenant`) | Register, log in, browse rooms (newest first), view details, reserve a room, contact the landlord, follow reservation status and replies |
| **Landlord** (`landlord`) | Landlord Dashboard: add and manage **their own** boarding houses, rooms, photos, amenities and listing status; approve or reject reservations; answer messages |
| **Administrator** (`admin`) | Everything in the Admin Dashboard: users, landlords, listings, rooms, bookings, reports |
| **Super Admin** (`super_admin`) | Everything an admin can do, plus create and manage other administrators |

Role-based access control is enforced on the server for every page and API call. A tenant or landlord who opens `/admin` is redirected to their own home page; the admin API answers `401` (not logged in) or `403` (not an administrator). The landlord API (`api/landlord/`) only ever reads or changes the logged-in landlord's own records: another landlord's boarding house, room, photo, reservation or message answers **404 Not found**, so one landlord can never view, edit or delete another landlord's listing.

---

## Admin Dashboard (`admin/`)

| Section | Features |
| --- | --- |
| **Dashboard** | 6 statistic cards (tenants, landlords, boarding houses, available rooms, occupied rooms, pending approvals) with "this month" growth; registration chart; booking, room and listing statistics; approval queue; recent-activity timeline |
| **Users** | All accounts with tabs (tenants / landlords / administrators), search, status filter, sorting, pagination. View (profile, uploaded IDs, listings, bookings), add, edit, reset password, disable/enable, delete |
| **Landlords** | Landlord list with business name, number of boarding houses and verification status; verify or reject landlords after reviewing their documents |
| **Boarding Houses** | Name, owner, location, rooms available, price range, status, date added. View, add, edit, approve, reject (with a reason sent to the landlord), deactivate, delete, upload/remove photos |
| **Rooms** | Room number, boarding house, type, price, capacity/occupancy, amenities, status. Add, edit, mark under maintenance, delete. Amenity catalogue manager |
| **Bookings** | Booking ID, tenant, boarding house, room, date, status (Pending / Approved / Cancelled / Completed). Approving fills a room slot automatically; cancelling or completing frees it. CSV export |
| **Reports** | Registered users, active landlords, boarding houses, available/occupied rooms, booking activity — charts for 6 or 12 months, tables by city, room type and occupancy, CSV export and print/PDF |
| **Notifications** | New users, new landlords, listings that need approval; mark read/unread, delete, notification preferences |
| **Activity Log** | Searchable audit trail of logins, registrations, approvals and every admin change, with CSV export |
| **Settings** | Admin profile, password change, system settings (name, support contacts, registration on/off, listing approval), notification preferences |
| **Logout** | Confirmation modal, then a CSRF-protected POST logout |

The layout is responsive: a collapsible sidebar on desktop, a slide-in drawer on phones and tablets, and tables that turn into cards on small screens. Every chart has hover tooltips and a "Show data table" view for accessibility.

---

## Database design

Run `database/schema.sql` to create every table, then optionally `database/seed.sql` for realistic demo data.

```text
users ──1:1── landlords ──1:N── boarding_houses ──1:N── rooms ──N:M── amenities
  │                                    │                   │       (room_amenities)
  │                                    └──1:N── boarding_house_images
  ├──1:N── bookings ──N:1── rooms
  ├──1:N── verification_documents
  ├──1:N── notifications
  ├──1:N── activity_logs
  └──1:N── password_resets
settings (key/value system configuration)
```

| Table | Purpose |
| --- | --- |
| `users` | Every account: username, email, password hash, role, name, contact number, address, gender, birth date, status (active/pending/disabled), lockout fields |
| `landlords` | Landlord business name/address and verification status (one row per landlord user) |
| `verification_documents` | IDs, selfies and permits uploaded at registration (files kept privately in `storage/documents`) |
| `boarding_houses` | Listing name, description, address, city, coordinates, nearest school, contacts, house rules, status (pending/approved/rejected/inactive) |
| `boarding_house_images` | Listing photos (`uploads/listings`) |
| `rooms` | Room number, type, price, deposit, capacity, current occupants, status (available/occupied/maintenance) |
| `amenities`, `room_amenities` | Amenity catalogue and which rooms have which amenities |
| `bookings` | Tenant, room, booking date, move-in date, status (pending/approved/cancelled/completed) |
| `notifications` | Messages for administrators (`audience = admin`) or a single user |
| `activity_logs` | Who did what and when — feeds Recent Activities and the Activity Log |
| `password_resets` | Hashed, expiring password-reset tokens |
| `settings` | System settings editable in Admin › Settings |

All relationships use foreign keys. Bookings use `ON DELETE RESTRICT`, so rental history cannot be lost by accident — the admin is asked to disable the account or listing instead.

---

## Landlord Dashboard (`landlord/`)

Landlords log in on the normal login page and land on `landlord/`.

| Section | Features |
| --- | --- |
| **Dashboard** | Total boarding houses, total available rooms, total reserved rooms, pending reservations, recent listings, recent reservations |
| **My Boarding Houses** | Photo, name, barangay, price range, room types, available rooms, status, date added. View (gallery, details, rooms), Edit, Manage Rooms, Update Status, Delete (with confirmation) |
| **Add Boarding House** | Main + additional photos with previews (JPG/JPEG/PNG/WebP, max 5 MB each); name, landlord/contact name, contact number, email; **barangay dropdown from the `barangays` table** (municipality and province filled in automatically), complete address, landmark, nearby school, distance from school (e.g. "5 minutes walk from SIIT"), Google Maps link, map pin (latitude/longitude, "use my current location", map preview); description, house rules; listing status; rooms |
| **Rooms** | Room name/number, Single Room (monthly price, vacant/occupied) or Shared Room (price per person, number of occupants, available slots), "how many rooms like this", availability, amenities checklist (Wi-Fi, Bed, Cabinet, Table, Chair, Electric Fan, Air Conditioning, Private/Shared Bathroom, Kitchen, Laundry Area, Parking, Study Area, CCTV, Water Supply, Electricity Included) + custom amenities, description, room photos |
| **Room Management** | Table of all rooms with filters; add, edit, delete, make available/unavailable, upload or delete room photos |
| **Reservations** | Pending / Approved / Rejected / Cancelled / Completed; approve or reject with a note to the student. Approving takes the room slots automatically |
| **My Tenants** | Current and former boarders (approved reservations): room, persons, move-in date, monthly rent, this month's status (Paid / Partly paid / Unpaid / Overdue), unpaid balance, month-by-month payment history, record payment, chat, move out |
| **Payments** | Pick a month: expected rent, collected, still unpaid, fully paid count; record or edit each tenant's payment (amount, date, Cash / GCash / Bank, reference, note), remove a wrong record, CSV export. Shared room rent = price per person × persons |
| **Chat** | Two-way chat with tenants (new messages appear automatically). Students read and reply in Browse Rooms › My Reservations › Chat |
| **Inquiries** | "Contact Landlord" questions from students and visitors; reply (the student sees it in My Reservations) or close |
| **Profile** | Name, email, contact number, business name, profile photo (shown to students), password |

**Listing status:** *Available* (open for reservations) · *Fully Occupied* (still shown, marked FULL) · *Temporarily Unavailable* (hidden from students) · *Pending Approval* (new listings wait for an administrator while **Settings › Require listing approval** is on; rejected listings go back to pending after the landlord edits them).

A boarding house that already has reservations cannot be deleted (the history is kept); set it to *Temporarily Unavailable* instead.

### Student room listing (`php/browse-rooms.php`)

Loaded from `api/rooms.php`: only rooms of **approved** boarding houses whose status is not *Temporarily Unavailable*. Each card shows the details on the left and a photo gallery (main photo + clickable thumbnails) on the right, stacked on phones, newest first with **Load more**, badges AVAILABLE / RESERVED / FULL. **More Details** shows the gallery, location and map, barangay, rent, room type, availability, amenities, description, house rules, nearby school, distance and the landlord. **Reserve** (student account) asks for confirmation and saves a *Pending* reservation; **Contact Landlord** saves a message for the landlord. The page checks every 30 seconds and offers to refresh when landlords change their listings.

### Adding or changing barangays

The barangay dropdown reads the `barangays` table (Dapa and General Luna are included). Add more in MySQL Workbench:

```sql
INSERT INTO barangays (name, municipality, province) VALUES ('Barangay Name', 'Del Carmen', 'Surigao del Norte');
UPDATE barangays SET is_active = 0 WHERE name = 'Old Name';   -- hide one from the list
```

---

## Installation (XAMPP + MySQL Workbench)

1. Copy the project to `C:\xampp\htdocs\BHsystem`.
2. Start **Apache** and **MySQL** in the XAMPP Control Panel.
3. In **MySQL Workbench**, connect to `127.0.0.1:3306` (user `root`), then **File › Run SQL Script…**:
   1. `database/schema.sql` — creates the `bhsystem` database and tables.
   2. `database/seed.sql` — *optional* demo **accounts** (2 admins, 10 landlords, 1 demo tenant). It contains **no** boarding houses: landlords add their listings in the Landlord Dashboard.
   (phpMyAdmin › Import works too.)

   **Already have the database and want to keep your accounts?** Run only `database/migrate-landlord-dashboard.sql` instead (and then `database/migrate-tenants-payments-chat.sql`). It keeps every user, landlord, admin and setting, **removes all boarding houses, rooms, photos records and bookings**, and adds the new tables and columns (barangays, room photos, messages, listing status).
4. If your MySQL user/password is not `root` with an empty password, copy `security/config.local.example.php` to `security/config.local.php` and edit it. This file is ignored by Git, so passwords never get committed. Environment variables (`BH_DB_HOST`, `BH_DB_USER`, `BH_DB_PASS`, …) also work.
5. Create your administrator:
   - **With demo data:** log in as `admin@bhrental.local` / `Admin@12345`.
   - **Without demo data:** open `http://localhost/BHsystem/setup/create-admin.php` (works only on localhost and only while no admin exists), or run
     `php setup/create-admin.php you@example.com "YourPass@123" First Last`.
6. Open `http://localhost/BHsystem/html/loginform.html`. Administrators land on the dashboard at `http://localhost/BHsystem/admin/`.

### Run without XAMPP (MySQL Server + MySQL Workbench only)

You still need PHP to run the website, but not the XAMPP Control Panel.

1. Make sure your MySQL Server is running and you imported `database/schema.sql` and `database/seed.sql` in MySQL Workbench.
2. Copy `security/config.local.example.php` to `security/config.local.php` and set `BH_DB_PASS` to your MySQL root password.
3. Double-click **`start-server.bat`**. It finds PHP (on your PATH, or `C:\xampp\php\php.exe`), starts PHP's built-in web server and opens the login page.
4. Use `http://localhost:8000/html/loginform.html` (admin dashboard: `http://localhost:8000/admin/`, landlord dashboard: `http://localhost:8000/landlord/`). Keep the black window open; close it to stop.

`router.php` blocks the private folders (`storage`, `security`, `database`) because PHP's built-in server ignores `.htaccess` files.

### Landing page = live database records

`http://localhost:8000/` opens the public landing page (`html/index.html`). Its **Featured Boarding Houses**, **Boarding Houses Near SIIT** map and statistics are loaded from `api/listings.php`, which returns only boarding houses an administrator has **approved** (not *Temporarily Unavailable*, and with at least one room). Listings are added by landlords in the Landlord Dashboard; a pin on the map comes from the latitude/longitude they set.

### Super Admin login with a Gmail code (two-step verification)

After the correct password, the **Super Admin** gets a 6-digit code by email (Gmail + PHPMailer) and must enter it on `php/verify-login.php`. The code expires after 10 minutes, 5 wrong tries cancel it, and "Send a new code" waits 60 seconds. Other roles log in as before.

1. **Install PHPMailer with Composer** (once): install Composer from https://getcomposer.org, then in the project folder run `composer install`. The library goes to `security/vendor/` (protected, not uploaded to Git).
2. **Gmail App Password:** turn on 2-Step Verification in the Gmail account, then create an App Password at https://myaccount.google.com/apppasswords.
3. Run `php setup/setup-mail.php` (asks for your Gmail and App Password, creates the file and sends a test email) — or copy `security/mail.local.example.php` to `security/mail.local.php` and fill in `username`, `password` (the App Password) and `from_email` (same Gmail address). This file is ignored by Git.
4. Make sure the Super Admin account's email is a **real inbox you can open** (Admin › Settings › My profile) — the code is sent there.

Check the setup any time with `php setup/check-mail.php` (add your Gmail address to also send a test email). While `security/mail.local.php` is missing, the code step is skipped (a warning is written to `storage/app.log`), so you can never be locked out: if email stops working, rename that file to log in, then fix it.

### Continue with Google / Facebook

The Google and Facebook buttons on the login page use OAuth 2.0 (`php/oauth.php` → provider → `php/oauth-callback.php`):

- a Google/Facebook account seen before → logs in;
- an existing account with the same (verified) email → the social account is linked and it logs in;
- a new person → a **Student (tenant)** account is created (landlords still register with the landlord form because they upload IDs);
- the Super Admin still has to enter the emailed code.

Setup:
1. `composer install` (adds `composer/ca-bundle`, so HTTPS to Google/Facebook works on XAMPP too).
2. Copy `security/oauth.local.example.php` to `security/oauth.local.php` (ignored by Git) and paste the keys:
   - **Google:** https://console.cloud.google.com/apis/credentials → Create credentials → OAuth client ID → Web application.
   - **Facebook:** https://developers.facebook.com/apps → Create app → Facebook Login.
3. In both consoles add the redirect URL `http://localhost:8000/php/oauth-callback.php` (or `http://localhost/BHsystem/php/oauth-callback.php` on XAMPP Apache).

A provider without keys shows "not set up yet" when its button is clicked.

### Demo accounts (`database/seed.sql` only — never import it on a real server)

| Role | Email | Password |
| --- | --- | --- |
| Super Admin | `admin@bhrental.local` | `Admin@12345` |
| Landlord | `landlord1@bhrental.local` … `landlord10@bhrental.local` | `Demo@12345` |
| Tenant | `tenant1@bhrental.local` | `Demo@12345` |

Have an older database with the 72 sample tenants? Run `database/cleanup-demo-tenants.sql` to delete them all except `tenant1@bhrental.local`.

Seed dates are relative to the day you import it, so the charts always show recent activity. Re-import it any time to reset the demo.

### Rebuilding the CSS (only when you change Tailwind classes)

The compiled files are committed, so XAMPP needs no Node.js. If you add new Tailwind classes:

```bash
npm install
npm run build:admin      # admin + landlord dashboards (or: npm run watch:admin-css)
npm run build:tenant     # Browse Rooms page -> assets/css/tenant.css
```

Tailwind and Chart.js are served locally from `admin/assets/`, so the admin dashboard also works without an internet connection.

---

## Project structure

```text
BHsystem/
├── admin/
│   ├── index.php                 Admin dashboard shell (role-protected)
│   ├── assets/css/               admin.css + compiled tailwind.css
│   ├── assets/vendor/            Chart.js (local copy)
│   └── assets/js/
│       ├── api.js                API client — the only file that calls the backend
│       ├── ui.js                 Safe HTML templates, modals, toasts, tables, forms
│       ├── charts.js             Chart helpers (colour-blind-safe palette)
│       ├── app.js                Router, sidebar, search, notifications, logout
│       └── pages/                dashboard, users, boarding-houses, rooms, bookings,
│                                 reports, notifications, activity, settings
├── landlord/
│   ├── index.php                 Landlord dashboard shell (landlord role only)
│   ├── assets/css/landlord.css   Photo pickers, form sections (+ the admin CSS)
│   └── assets/js/                api.js, ui.js, app.js and pages/ (dashboard, houses,
│                                 house-form, room-form, rooms, reservations, messages, profile)
├── api/
│   ├── _common.php               JSON responses, validation helpers, error handling
│   ├── _queries.php              Shared query helpers (paging, occupancy)
│   ├── login.php, csrf.php, register-tenant.php, register-landlord.php
│   ├── rooms.php, listings.php   Public listings (Browse Rooms, landing page)
│   ├── reservations.php          Student reservations
│   ├── inquiries.php             Contact Landlord messages
│   ├── chat.php, _chat.php       Landlord <-> tenant chat (student side + shared logic)
│   └── verify-login.php          Super Admin email code (step 2 of the login)
│   ├── landlord/                 Landlord REST API (own data only)
│   └── admin/                    Admin REST API (see below)
├── assets/css/                   tenant.css (Browse Rooms, built with npm run build:tenant)
├── database/schema.sql, seed.sql, migrate-landlord-dashboard.sql,
│   migrate-tenants-payments-chat.sql, cleanup-demo-tenants.sql
├── security/                     config, database, session, CSRF, roles, validation,
│                                 rate limiting, uploads, activity log, settings
├── setup/create-admin.php        First administrator setup
├── html/, php/, registerJS/      Public pages, registration and tenant pages
├── storage/                      Logs, rate-limit data, private documents (not web-accessible)
└── uploads/                      Listing photos (scripts can never run here)
```

## Admin REST API (`api/admin/`)

Every endpoint requires an administrator session. `POST`, `PUT` and `DELETE` also require the `X-CSRF-Token` header. Responses look like `{ "success": true, "data": …, "meta": {pagination} }` or `{ "success": false, "message": "…", "errors": { field: message } }` with the matching HTTP status.

| Endpoint | Methods | Notes |
| --- | --- | --- |
| `stats.php` | GET | Dashboard cards, chart data, recent activity, approval queue |
| `users.php` | GET, POST, PUT, DELETE | `?role=tenant\|landlord\|admin&status=&verification=&q=&page=&sort=&order=`; `?id=` for details |
| `boarding-houses.php` | GET, POST, PUT, DELETE | `PUT {status: approved\|rejected\|inactive, rejection_reason}`; `POST ?id=&action=images` (multipart); `DELETE ?image_id=` |
| `rooms.php` | GET, POST, PUT, DELETE | Filters: `boarding_house_id, status, room_type, amenity_id, min_price, max_price`; body `amenity_ids: []` |
| `amenities.php` | GET, POST, PUT, DELETE | Amenity catalogue |
| `bookings.php` | GET, POST, PUT, DELETE | Status changes keep room occupancy correct (row-locked transaction) |
| `reports.php` | GET | `?months=6\|12` |
| `notifications.php` | GET, PUT, DELETE | `?filter=unread`, `PUT ?action=read_all`, `DELETE ?action=clear_read` |
| `activity.php` | GET | `?q=&action=&date_from=&date_to=` |
| `settings.php` | GET, PUT | System settings |
| `profile.php` | GET, PUT | `?action=password`, `?action=preferences` |
| `search.php` | GET | Global search (users, boarding houses, rooms, booking IDs) |
| `options.php` | GET | Dropdown lists: `?type=landlords\|boarding_houses\|rooms\|tenants\|amenities` |
| `documents.php` | GET | Streams a private verification document to an admin |

---

## Security

- Passwords hashed with `password_hash` (bcrypt) and upgraded automatically on login.
- Sessions: HttpOnly + SameSite cookies, ID regenerated on login, 30-minute idle timeout.
- CSRF tokens on every state-changing request, including logout.
- Role checks on every admin page **and** API call, re-read from the database each request (disabling an admin takes effect immediately). Admins cannot change their own role/status; only a super admin can manage administrators.
- Login protection: counts failed attempts only, per email + IP (students on the same campus Wi-Fi are not locked out together), plus a per-account temporary lockout.
- All SQL uses prepared statements; all output is escaped (`html` template in `ui.js`, `sanitizeForOutput` in PHP).
- Uploads: type detected from file content, random file names, 5MB limit. ID documents are kept in `storage/documents` (the web server blocks direct access), viewable only by administrators through the API, and every view is logged. Listing photos are in `uploads/`, where scripts cannot run.
- Database credentials come from `security/config.local.php` or environment variables — not hard-coded for production.
- Activity log of logins, registrations, approvals and administrative changes.

Before going live: enable HTTPS, use a dedicated MySQL user (not `root`), do **not** import `seed.sql`, and delete the `setup/` folder after creating your admin.

---

## Roadmap (next phases)

1. Admin page to manage the barangay list (today: SQL, see above).
2. Favorites, reviews and email notifications.
3. Payments and rental contracts.

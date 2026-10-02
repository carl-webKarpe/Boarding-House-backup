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
| **Tenant / Student** (`tenant`) | Register with student/government ID, log in, browse and search rooms |
| **Landlord** (`landlord`) | Register with government ID, selfie and property details; first listing waits for approval |
| **Administrator** (`admin`) | Everything in the Admin Dashboard: users, landlords, listings, rooms, bookings, reports |
| **Super Admin** (`super_admin`) | Everything an admin can do, plus create and manage other administrators |

Role-based access control is enforced on the server for every page and API call. A tenant or landlord who opens `/admin` is redirected to their own home page; the admin API answers `401` (not logged in) or `403` (not an administrator).

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

Run `database/schema.sql` to create every table, then `database/seed.sql` to load your own data (the six boarding houses from the landing page). `database/demo-data.sql` is an optional, larger demo (≈90 users, bookings) for presenting full charts — load it **instead of** `seed.sql`.

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

## Installation (XAMPP + MySQL Workbench)

1. Copy the project to `C:\xampp\htdocs\BHsystem`.
2. Start **Apache** and **MySQL** in the XAMPP Control Panel.
3. In **MySQL Workbench**, connect to `127.0.0.1:3306` (user `root`), then **File › Run SQL Script…**:
   1. `database/schema.sql` — creates the `bhsystem` database and tables.
   2. `database/seed.sql` — your data: 6 boarding houses (Green View, Island Home, Student Haven, Northview, Sunrise, Seaside) with rooms, amenities, photos and map locations, plus the admin and one landlord account per house.
      *(Or `database/demo-data.sql` instead, for a large demo with tenants and bookings.)*
   Running `schema.sql` again **resets** the database (all tables are dropped and recreated).
   (phpMyAdmin › Import works too.)
4. If your MySQL user/password is not `root` with an empty password, copy `security/config.local.example.php` to `security/config.local.php` and edit it. This file is ignored by Git, so passwords never get committed. Environment variables (`BH_DB_HOST`, `BH_DB_USER`, `BH_DB_PASS`, …) also work.
5. Create your administrator:
   - **With `seed.sql` or `demo-data.sql`:** log in as `admin@bhrental.local` / `Admin@12345`.
   - **Without either file:** open `http://localhost/BHsystem/setup/create-admin.php` (works only on localhost and only while no admin exists), or run
     `php setup/create-admin.php you@example.com "YourPass@123" First Last`.
6. Open `http://localhost/BHsystem/html/loginform.html`. Administrators land on the dashboard at `http://localhost/BHsystem/admin/`.

### Run without XAMPP (MySQL Server + MySQL Workbench only)

You still need PHP to run the website, but not the XAMPP Control Panel.

1. Make sure your MySQL Server is running and you imported `database/schema.sql` and `database/seed.sql` in MySQL Workbench.
2. Copy `security/config.local.example.php` to `security/config.local.php` and set `BH_DB_PASS` to your MySQL root password.
3. Double-click **`start-server.bat`**. It finds PHP (on your PATH, or `C:\xampp\php\php.exe`), starts PHP's built-in web server and opens the login page.
4. Use `http://localhost:8000/html/loginform.html` (admin dashboard: `http://localhost:8000/admin/`). Keep the black window open; close it to stop.

`router.php` blocks the private folders (`storage`, `security`, `database`) because PHP's built-in server ignores `.htaccess` files.

### Landing page = live database records

`http://localhost:8000/` opens the public landing page (`html/index.html`). Its **Featured Boarding Houses** (with a photo slideshow in **View Details**), **Boarding Houses Near SIIT** map and statistics are loaded from `api/listings.php`, which returns only boarding houses an administrator has **approved** in the Admin Dashboard (and that have at least one room). Add, edit, approve, reject or deactivate a listing in the admin, refresh the landing page, and the change is there. Set a listing's latitude/longitude in the admin so it appears on the SIIT map.

### Accounts

`database/seed.sql` (your data): `admin@bhrental.local` / `Admin@12345`, and one landlord per house, e.g. `greenview.owner@bhrental.local` / `Demo@12345` (names and phone numbers are placeholders — edit them in Admin › Landlords).

### Demo accounts (`database/demo-data.sql` only — never import it on a real server)

| Role | Email | Password |
| --- | --- | --- |
| Super Admin | `admin@bhrental.local` | `Admin@12345` |
| Landlord | `landlord1@bhrental.local` | `Demo@12345` |
| Tenant | `tenant1@bhrental.local` | `Demo@12345` |

Seed dates are relative to the day you import it, so the charts always show recent activity. Re-import it any time to reset the demo.

### Rebuilding the admin CSS (only when you change Tailwind classes)

The compiled files are committed, so XAMPP needs no Node.js. If you add new Tailwind classes to `admin/`:

```bash
npm install
npm run build:admin      # or: npm run watch:admin-css while editing
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
├── api/
│   ├── _common.php               JSON responses, validation helpers, error handling
│   ├── login.php, csrf.php, register-tenant.php, register-landlord.php, rooms.php
│   └── admin/                    Admin REST API (see below)
├── database/schema.sql, seed.sql (your data), demo-data.sql (optional large demo)
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

Before going live: enable HTTPS, use a dedicated MySQL user (not `root`), do **not** import `demo-data.sql`, change the seeded passwords, and delete the `setup/` folder after creating your admin.

---

## Roadmap (next phases)

1. **Tenant side on the database** — replace the sample data in `php/room-data.php` with approved listings from MySQL; real search by location, school, price, room type and amenities; room details page.
2. **Landlord dashboard** — landlords add and edit their own boarding houses, rooms, prices, availability, amenities and photos (the database and validation rules are ready).
3. **Online booking requests** from tenants (the `bookings` table and admin workflow already exist).
4. Favorites, messaging, reviews and email notifications.
5. Payments and rental contracts.

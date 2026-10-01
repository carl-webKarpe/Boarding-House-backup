# Boarding House Rental System

A PHP and MySQL web application for helping students and other tenants discover boarding houses, review room information, and access a secure account dashboard. The project also provides landlord registration endpoints and a role-protected administration area for managing users.

## Project Overview

The system is designed around three groups of users:

- **Tenants** browse available rooms, search by location, school, price, and room type, view amenities and landlord information, and access their account dashboard.
- **Landlords** can use the landlord registration flow as the foundation for publishing and managing rental listings.
- **Administrators** use the protected admin area to manage users and oversee system operations.

The current project is a working foundation and user-interface prototype. Authentication and user accounts use MySQL, while the room catalog currently comes from the sample records in `php/room-data.php`. Booking, messaging, and listing-management persistence can be connected to database tables as the next development phase.

## Main Features

### Tenant experience

- Responsive room-discovery interface in `php/browse-rooms.php`.
- Search and filtering by location, school, budget, and room type.
- Room details including rent, deposit, advance payment, capacity, room size, availability, amenities, house rules, nearby places, reviews, and landlord information.
- Featured listings, recommended rooms, saved/favorite UI, messages UI, notifications, and booking navigation.
- Light and dark display modes.
- Authenticated user dashboard and logout flow.

### Accounts and administration

- Tenant and landlord registration pages.
- Login and password reset flow.
- Secure session handling with role information.
- Roles for `super_admin`, `admin`, `staff`, and regular `user` accounts.
- Admin-only access to `admin/adminpanel.php` and user management in `admin/users.php`.
- Audit-log and failed-login tracking support.

### Security foundation

- Password hashing through PHP password APIs.
- CSRF token validation for authentication and registration requests.
- Prepared database queries through the security/database layer.
- Output sanitization helpers to reduce XSS risk.
- Secure session configuration and session-based authentication.
- Security headers.
- Login rate limiting and temporary account lockout after repeated failures.
- Password reset tokens with an expiration period.
- Upload-security helpers for future listing media uploads.

## Design and User Interface

The interface uses a clean, student-focused rental marketplace style:

- **Visual direction:** emerald green is used for trust, availability, and primary actions; slate neutrals provide readable content surfaces.
- **Typography:** Poppins is used for display headings and Inter for interface text on the room-browsing experience.
- **Layout:** responsive navigation, filter/search controls, listing cards, room detail sections, dashboards, and admin panels.
- **Responsive behavior:** layouts adapt for phones, tablets, and desktop screens using Tailwind utility classes, Bootstrap components, and local CSS.
- **Accessibility basics:** semantic headings, form labels, responsive controls, focus-friendly buttons, and live-region support for notifications.
- **Interaction design:** search filters, profile dropdowns, favorite controls, room detail views, notifications, toast messages, and dark-mode switching.

The public browsing experience is primarily styled in `style.css` and uses Tailwind CSS from its CDN. The dashboard and admin pages use Bootstrap 5 from its CDN with page-specific styles.

## Technology Stack

- **Backend:** PHP 8+ recommended
- **Database:** MySQL or MariaDB
- **Frontend:** HTML5, CSS3, JavaScript
- **UI:** Tailwind CSS CDN and Bootstrap 5 CDN
- **Fonts:** Google Fonts, Poppins and Inter
- **Local server:** XAMPP Apache and MySQL
- **Data format:** JSON requests and responses for authentication APIs

## Directory Structure

```text
BHsystem/
├── admin/                  Protected administrator pages
├── api/                    JSON endpoints for login, registration, rooms, and CSRF
├── html/                   Public HTML pages and registration forms
├── Image/                  Local room and background images
├── php/                    Dashboard, browsing, notifications, and account pages
├── registerJS/             Registration and signup JavaScript
├── security/               Authentication, sessions, validation, roles, and headers
├── storage/                Runtime logs and rate-limit data
├── home.css                Home-page styles
├── style.css               Room-browsing styles
└── sql.setup.sql           Database schema and initial administrator account
```

## Database Design

The SQL setup creates the `bhsystem` database with these current tables:

| Table | Purpose |
| --- | --- |
| `users` | Accounts, roles, password hashes, account status, and login lockout values |
| `audit_logs` | Security and administrative activity records |
| `password_resets` | Expiring password-reset tokens |

Room listings are currently represented by the PHP array in `php/room-data.php`. A production release should add tables for properties, rooms, amenities, bookings, favorites, messages, reviews, and payments.

## Local Installation with XAMPP

### Requirements

- XAMPP with Apache and MySQL enabled
- PHP 8.0 or newer recommended
- A modern web browser

### Setup

1. Copy the project folder into the XAMPP web root:

   ```text
   C:\xampp\htdocs\BHsystem
   ```

2. Start **Apache** and **MySQL** in the XAMPP Control Panel.

3. Open phpMyAdmin at `http://localhost/phpmyadmin`.

4. Import `sql.setup.sql`, or run it from the MySQL console. This creates the database and required account tables.

5. Check the local database settings in `security/config.php`:

   ```php
   BH_DB_HOST = 127.0.0.1
   BH_DB_NAME = bhsystem
   BH_DB_USER = root
   BH_DB_PASS = ''
   ```

   Change these values when using a non-default MySQL installation.

6. Open the application:

   ```text
   http://localhost/BHsystem/html/index.html
   ```

   The main room browser is available at:

   ```text
   http://localhost/BHsystem/php/browse-rooms.php
   ```

## Initial Administrator Account

The SQL file inserts the initial super administrator record:

```text
Username: superadmin
Email:    superadmin@example.com
Role:     super_admin
```

The SQL file contains a password hash rather than a plain-text password. Set or replace the administrator password through a controlled local setup process before using the application in a real environment. Never publish default credentials or production secrets in the repository.

## Important Routes

| Route | Description |
| --- | --- |
| `html/index.html` | Public entry page |
| `html/loginform.html` | Login form |
| `html/signup.html` | Account type selection and signup entry |
| `html/register-tenant.html` | Tenant registration form |
| `html/register-landlord.html` | Landlord registration form |
| `php/browse-rooms.php` | Room discovery and details |
| `php/dashboard.php` | Authenticated dashboard |
| `php/notifications.php` | User notifications page |
| `admin/adminpanel.php` | Admin dashboard |
| `admin/users.php` | Admin user management |
| `api/login.php` | JSON login endpoint |
| `api/register.php` | JSON registration endpoint |
| `api/rooms.php` | Room data endpoint |
| `php/logout.php` | Session logout |

## API Request Notes

Authentication and registration endpoints expect `POST` requests with JSON data and a valid CSRF token. Example login payload:

```json
{
  "email": "tenant@example.com",
  "password": "your-password",
  "rememberMe": false,
  "csrf_token": "token-from-the-session"
}
```

The API returns JSON containing a success value and a user-facing message. Client-side form scripts in `registerJS/` handle registration interactions and display responses.

## Security and Production Checklist

- Enable HTTPS before deploying outside localhost.
- Move database credentials and application secrets outside the public web root or into environment variables.
- Replace the development database password and remove sample accounts.
- Keep `storage/` and uploaded files protected from direct script execution.
- Review PHP error display settings and disable verbose errors in production.
- Add database-backed room, booking, and payment authorization checks before launch.
- Validate ownership before allowing landlords to edit or remove listings.
- Add automated tests for authentication, authorization, CSRF validation, and booking state changes.
- Do not use Vercel as a direct PHP host without converting the backend to serverless functions. Use Apache/PHP hosting, a PHP-capable cloud service, or keep Vercel only for a separate frontend.

## Current Limitations and Next Steps

The following items are planned extensions rather than complete production features:

1. Persist room and property listings in MySQL instead of a PHP sample array.
2. Add landlord listing creation, editing, availability, and image-upload screens.
3. Add a complete tenant booking workflow with booking status history.
4. Store favorites, messages, reviews, and notifications in the database.
5. Add payment records and rental contract support.
6. Add automated PHP and browser tests.
7. Replace CDN-only frontend assets with a versioned build pipeline for production.

## License

No license has been declared for this project yet. Add a license file before distributing the system publicly.

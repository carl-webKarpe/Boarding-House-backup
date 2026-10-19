# Database for XAMPP

XAMPP uses **MariaDB** (a MySQL-compatible database) and **phpMyAdmin**, while
MySQL Workbench usually runs **MySQL 8**. The files in this folder move the
Boarding House Rental System database to XAMPP.

| File | Use it when |
|---|---|
| `bhsystem-xampp.sql` | You want a **fresh** database in XAMPP: all 21 tables plus the starting accounts. |
| `convert-workbench-dump.php` | You want to **move your own data** (users, boarding houses, rooms, bookings …) from MySQL Workbench to XAMPP. |

---

## Before you start: only one MySQL at a time

MySQL Workbench is often installed together with the **MySQL80** Windows
service. It uses port **3306**, the same port as XAMPP's MySQL, so XAMPP's MySQL
will not start ("Port 3306 in use") while it runs.

1. Press **Windows + R**, type `services.msc`, press Enter.
2. Find **MySQL80** → right-click → **Stop**.
   Double-click it and set **Startup type: Manual**, so it does not start again after a restart.
3. Open the **XAMPP Control Panel** → **Start** Apache and MySQL.

Do the export from MySQL Workbench (option B, step 1) **before** stopping MySQL80.

---

## A. Fresh database (no old data needed)

1. XAMPP Control Panel → Apache and MySQL **running**.
2. Open **http://localhost/phpmyadmin**
3. Click **Import** (top menu). You do not need to select a database first.
4. **Choose File** → `database\xampp\bhsystem-xampp.sql` → **Import** (bottom).
5. You should see "Import has been successfully finished". A database
   **bhsystem** with 21 tables appears on the left.

Starting logins (change them after the first login):

| Role | Email | Password |
|---|---|---|
| Super Admin | admin@bhrental.local | Admin@12345 |
| Landlord | landlord1@bhrental.local | Demo@12345 |
| Tenant | tenant1@bhrental.local | Demo@12345 |

> Importing this file again **deletes all data** and starts over.

---

## B. Move your data from MySQL Workbench to XAMPP

### 1. Export from MySQL Workbench
1. Open MySQL Workbench and connect to your local server.
2. Menu **Server** → **Data Export**.
3. Tick the schema **bhsystem** (all tables).
4. Choose **Dump Structure and Data**.
5. Choose **Export to Self-Contained File** and pick a place, e.g.
   `C:\xampp\htdocs\BHsystem\database\xampp\workbench-export.sql`
6. Tick **Include Create Schema**.
7. Click **Start Export**.

### 2. Convert it for XAMPP
Open Command Prompt or the VS Code terminal and run:

```
cd C:\xampp\htdocs\BHsystem
C:\xampp\php\php.exe database\xampp\convert-workbench-dump.php database\xampp\workbench-export.sql
```

It creates **`workbench-export-xampp.sql`** next to the export and lists what it
changed. The original file is not touched. What it fixes:

| MySQL 8 (Workbench) | Problem in XAMPP | Converted to |
|---|---|---|
| `utf8mb4_0900_ai_ci` collation | "Unknown collation" error | `utf8mb4_unicode_ci` |
| `DEFAULT ENCRYPTION='N'` | Syntax error | removed |
| `SET @@GLOBAL.GTID_PURGED …` | "Access denied / unknown variable" | removed |
| `DEFINER=` on views / triggers | User does not exist | removed |
| Export without `CREATE DATABASE` | "No database selected" | adds `bhsystem` |

### 3. Import into XAMPP
1. Stop **MySQL80** and start XAMPP's MySQL (see "Before you start").
2. **http://localhost/phpmyadmin** → **Import** → choose
   `workbench-export-xampp.sql` → **Import**.

If phpMyAdmin says the file is too big, import from the command line instead:

```
C:\xampp\mysql\bin\mysql.exe -u root < database\xampp\workbench-export-xampp.sql
```

### 4. Copy the photos too
Photos are files, not database rows. Keep the project's **`uploads`** folder
(listing photos) and **`storage\documents`** (landlord ID documents) from the
old copy of the project.

---

## C. Connect the system to XAMPP

XAMPP's default login is user **root** with **no password**, which is also the
system's default. Either delete `security\config.local.php`, or make sure it
contains:

```php
<?php
return [
    'BH_DB_HOST' => '127.0.0.1',
    'BH_DB_PORT' => '3306',
    'BH_DB_NAME' => 'bhsystem',
    'BH_DB_USER' => 'root',
    'BH_DB_PASS' => '',
];
```

Then open **http://localhost/BHsystem/** and log in.

## Troubleshooting

| Message | Fix |
|---|---|
| XAMPP MySQL: "Port 3306 in use" | Stop the **MySQL80** service (see above). |
| `#1273 Unknown collation: 'utf8mb4_0900_ai_ci'` | You imported the Workbench export directly. Run the converter (B2) and import the `-xampp.sql` file. |
| `#1046 No database selected` | Use the converted file, or click **bhsystem** on the left before importing. |
| Site says "Database unavailable" | XAMPP MySQL is not running, or `config.local.php` has a wrong user / password. |

To rebuild `bhsystem-xampp.sql` after changing `schema.sql` or `seed.sql`:
`C:\xampp\php\php.exe setup\make-xampp-sql.php`

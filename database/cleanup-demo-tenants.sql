-- ============================================================================
-- Boarding House Rental System - REMOVE THE EXTRA DEMO TENANTS
--
-- Deletes every tenant (student) account EXCEPT the demo tenant
-- tenant1@bhrental.local (Maria Mendoza). Landlords, administrators,
-- boarding houses and rooms are not touched.
--
-- Their reservations are removed too; places they held in a room are freed.
-- Messages they sent stay with the landlord (shown as from a visitor).
--
-- Run it once in MySQL Workbench (open the file, then Ctrl+Shift+Enter).
-- ============================================================================
SET NAMES utf8mb4;
USE bhsystem;
-- MySQL Workbench "safe update mode" blocks these deletes; off for this script only.
SET @OLD_SQL_SAFE_UPDATES = @@SQL_SAFE_UPDATES;
SET SQL_SAFE_UPDATES = 0;

-- Tenants to remove
DROP TEMPORARY TABLE IF EXISTS cleanup_tenants;
CREATE TEMPORARY TABLE cleanup_tenants (id INT UNSIGNED PRIMARY KEY);
INSERT INTO cleanup_tenants (id)
  SELECT id FROM users WHERE role = 'tenant' AND email <> 'tenant1@bhrental.local';

-- Free the room places taken by their approved reservations
UPDATE rooms r
  JOIN (SELECT b.room_id, SUM(b.occupants_count) AS taken
          FROM bookings b JOIN cleanup_tenants c ON c.id = b.tenant_id
         WHERE b.status = 'approved'
         GROUP BY b.room_id) t ON t.room_id = r.id
   -- (status first: MySQL applies the assignments left to right)
   SET r.status = IF(r.status = 'occupied' AND r.occupants - t.taken < r.capacity, 'available', r.status),
       r.occupants = GREATEST(r.occupants - t.taken, 0);

-- Their reservations, then the accounts (ID documents, notifications and
-- password resets are removed automatically; activity log entries are kept).
DELETE b FROM bookings b JOIN cleanup_tenants c ON c.id = b.tenant_id;
DELETE u FROM users u JOIN cleanup_tenants c ON c.id = u.id;

DROP TEMPORARY TABLE cleanup_tenants;
SET SQL_SAFE_UPDATES = @OLD_SQL_SAFE_UPDATES;

-- Result: should show 1 tenant
SELECT role, COUNT(*) AS accounts FROM users GROUP BY role;

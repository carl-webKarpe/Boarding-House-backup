-- ============================================================================
-- Boarding House Rental System - UPGRADE FOR THE LANDLORD DASHBOARD
--
-- Use this INSTEAD of schema.sql + seed.sql when you want to KEEP the user
-- accounts (admin, landlords, tenants) that are already in your database.
-- Run it ONCE in MySQL Workbench (File > Run SQL Script).
--
-- What it does:
--   * removes the sample boarding houses, rooms, photos and bookings
--   * adds the new tables (barangays, room_images, inquiries)
--   * adds the new columns (listing availability, location, room photos ...)
--   * replaces the amenity list with the landlord checklist
-- User accounts, roles, passwords and settings are NOT touched.
-- ============================================================================
SET NAMES utf8mb4;
USE bhsystem;
SET FOREIGN_KEY_CHECKS = 0;

-- 1. Remove sample listing data -------------------------------------------
DELETE FROM bookings;
DELETE FROM room_amenities;
DELETE FROM rooms;
DELETE FROM boarding_house_images;
DELETE FROM boarding_houses;
DELETE FROM notifications WHERE type IN ('listing_submitted', 'listing_pending', 'booking_created', 'listing_status', 'booking_status');
ALTER TABLE bookings AUTO_INCREMENT = 1;
ALTER TABLE rooms AUTO_INCREMENT = 1;
ALTER TABLE boarding_houses AUTO_INCREMENT = 1;
ALTER TABLE boarding_house_images AUTO_INCREMENT = 1;

-- 2. Accounts: optional profile photo --------------------------------------
ALTER TABLE users ADD COLUMN avatar_path VARCHAR(255) NULL AFTER password_hash;

-- 3. Barangay dropdown -----------------------------------------------------
CREATE TABLE IF NOT EXISTS barangays (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  municipality VARCHAR(100) NOT NULL,
  province VARCHAR(100) NOT NULL DEFAULT 'Surigao del Norte',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_barangay (name, municipality, province),
  KEY idx_barangay_municipality (municipality)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Barangays of Dapa (29) and General Luna (19), Siargao Island, Surigao del Norte.
-- To add more:  INSERT INTO barangays (name, municipality, province) VALUES ('Name', 'Municipality', 'Surigao del Norte');
INSERT IGNORE INTO barangays (name, municipality, province) VALUES
  ('Bagakay', 'Dapa', 'Surigao del Norte'),
  ('Barangay 1 (Poblacion)', 'Dapa', 'Surigao del Norte'),
  ('Barangay 2 (Poblacion)', 'Dapa', 'Surigao del Norte'),
  ('Barangay 3 (Poblacion)', 'Dapa', 'Surigao del Norte'),
  ('Barangay 4 (Poblacion)', 'Dapa', 'Surigao del Norte'),
  ('Barangay 5 (Poblacion)', 'Dapa', 'Surigao del Norte'),
  ('Barangay 6 (Poblacion)', 'Dapa', 'Surigao del Norte'),
  ('Barangay 7 (Poblacion)', 'Dapa', 'Surigao del Norte'),
  ('Barangay 8 (Poblacion)', 'Dapa', 'Surigao del Norte'),
  ('Barangay 9 (Poblacion)', 'Dapa', 'Surigao del Norte'),
  ('Barangay 10 (Poblacion)', 'Dapa', 'Surigao del Norte'),
  ('Barangay 11 (Poblacion)', 'Dapa', 'Surigao del Norte'),
  ('Barangay 12 (Poblacion)', 'Dapa', 'Surigao del Norte'),
  ('Barangay 13 (Poblacion)', 'Dapa', 'Surigao del Norte'),
  ('Buenavista', 'Dapa', 'Surigao del Norte'),
  ('Cabawa', 'Dapa', 'Surigao del Norte'),
  ('Cambas-ac', 'Dapa', 'Surigao del Norte'),
  ('Consolacion', 'Dapa', 'Surigao del Norte'),
  ('Corregidor', 'Dapa', 'Surigao del Norte'),
  ('Dagohoy', 'Dapa', 'Surigao del Norte'),
  ('Don Paulino', 'Dapa', 'Surigao del Norte'),
  ('Jubang', 'Dapa', 'Surigao del Norte'),
  ('Montserrat', 'Dapa', 'Surigao del Norte'),
  ('Osmeña', 'Dapa', 'Surigao del Norte'),
  ('San Carlos', 'Dapa', 'Surigao del Norte'),
  ('San Miguel', 'Dapa', 'Surigao del Norte'),
  ('Santa Fe', 'Dapa', 'Surigao del Norte'),
  ('Santa Felomina', 'Dapa', 'Surigao del Norte'),
  ('Union', 'Dapa', 'Surigao del Norte'),
  ('Anajawan', 'General Luna', 'Surigao del Norte'),
  ('Cabitoonan', 'General Luna', 'Surigao del Norte'),
  ('Catangnan', 'General Luna', 'Surigao del Norte'),
  ('Consuelo', 'General Luna', 'Surigao del Norte'),
  ('Corazon', 'General Luna', 'Surigao del Norte'),
  ('Daku', 'General Luna', 'Surigao del Norte'),
  ('La Januza', 'General Luna', 'Surigao del Norte'),
  ('Libertad', 'General Luna', 'Surigao del Norte'),
  ('Magsaysay', 'General Luna', 'Surigao del Norte'),
  ('Malinao', 'General Luna', 'Surigao del Norte'),
  ('Poblacion I', 'General Luna', 'Surigao del Norte'),
  ('Poblacion II', 'General Luna', 'Surigao del Norte'),
  ('Poblacion III', 'General Luna', 'Surigao del Norte'),
  ('Poblacion IV', 'General Luna', 'Surigao del Norte'),
  ('Poblacion V', 'General Luna', 'Surigao del Norte'),
  ('Santa Cruz', 'General Luna', 'Surigao del Norte'),
  ('Santa Fe', 'General Luna', 'Surigao del Norte'),
  ('Suyangan', 'General Luna', 'Surigao del Norte'),
  ('Tawin-tawin', 'General Luna', 'Surigao del Norte');

-- 4. Boarding houses: barangay, location and landlord-set availability -----
ALTER TABLE boarding_houses
  ADD COLUMN barangay_id INT UNSIGNED NULL AFTER address,
  ADD COLUMN location_note VARCHAR(255) NULL AFTER province,
  ADD COLUMN map_url VARCHAR(500) NULL AFTER longitude,
  ADD COLUMN distance_note VARCHAR(150) NULL AFTER nearby_school,
  ADD COLUMN contact_name VARCHAR(160) NULL AFTER distance_note,
  ADD COLUMN availability_status ENUM('available', 'fully_occupied', 'temporarily_unavailable') NOT NULL DEFAULT 'available' AFTER status,
  ADD KEY idx_bh_barangay (barangay_id),
  ADD CONSTRAINT fk_bh_barangay FOREIGN KEY (barangay_id) REFERENCES barangays (id) ON DELETE SET NULL;

-- 5. Rooms: longer room names and room photos -------------------------------
ALTER TABLE rooms
  MODIFY room_number VARCHAR(60) NOT NULL,
  MODIFY description TEXT NULL;

CREATE TABLE IF NOT EXISTS room_images (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  room_id INT UNSIGNED NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_room_images_room (room_id, sort_order),
  CONSTRAINT fk_room_images_room FOREIGN KEY (room_id) REFERENCES rooms (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Amenities: landlord checklist + custom amenities ----------------------
ALTER TABLE amenities
  ADD COLUMN created_by INT UNSIGNED NULL AFTER name,
  ADD CONSTRAINT fk_amenities_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL;
DELETE FROM amenities;
ALTER TABLE amenities AUTO_INCREMENT = 1;
INSERT INTO amenities (name) VALUES
  ('Wi-Fi'), ('Bed'), ('Cabinet'), ('Table'), ('Chair'), ('Electric Fan'), ('Air Conditioning'),
  ('Private Bathroom'), ('Shared Bathroom'), ('Kitchen'), ('Laundry Area'), ('Parking'),
  ('Study Area'), ('CCTV'), ('Water Supply'), ('Electricity Included');

-- 7. Reservations: details from the student and a "rejected" status --------
ALTER TABLE bookings
  ADD COLUMN occupants_count TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER move_in_date,
  ADD COLUMN contact_name VARCHAR(160) NULL AFTER occupants_count,
  ADD COLUMN contact_number VARCHAR(20) NULL AFTER contact_name,
  ADD COLUMN contact_email VARCHAR(255) NULL AFTER contact_number,
  ADD COLUMN message VARCHAR(1000) NULL AFTER contact_email,
  MODIFY status ENUM('pending', 'approved', 'rejected', 'cancelled', 'completed') NOT NULL DEFAULT 'pending';

-- 8. "Contact Landlord" messages --------------------------------------------
CREATE TABLE IF NOT EXISTS inquiries (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  boarding_house_id INT UNSIGNED NOT NULL,
  room_id INT UNSIGNED NULL,
  landlord_user_id INT UNSIGNED NOT NULL,
  tenant_id INT UNSIGNED NULL,
  sender_name VARCHAR(160) NOT NULL,
  sender_email VARCHAR(255) NOT NULL,
  sender_phone VARCHAR(20) NULL,
  message VARCHAR(2000) NOT NULL,
  status ENUM('new', 'read', 'replied', 'closed') NOT NULL DEFAULT 'new',
  reply VARCHAR(2000) NULL,
  replied_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_inquiries_landlord (landlord_user_id, status),
  KEY idx_inquiries_tenant (tenant_id),
  CONSTRAINT fk_inquiries_house FOREIGN KEY (boarding_house_id) REFERENCES boarding_houses (id) ON DELETE CASCADE,
  CONSTRAINT fk_inquiries_room FOREIGN KEY (room_id) REFERENCES rooms (id) ON DELETE SET NULL,
  CONSTRAINT fk_inquiries_landlord FOREIGN KEY (landlord_user_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_inquiries_tenant FOREIGN KEY (tenant_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

INSERT INTO notifications (user_id, audience, type, title, message, link)
VALUES (NULL, 'admin', 'system', 'Listings were reset', 'Sample boarding houses were removed. Landlords can now add their real listings from the Landlord Dashboard.', '#/boarding-houses');

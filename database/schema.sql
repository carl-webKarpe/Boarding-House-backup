-- ============================================================================
-- Boarding House Rental System - MySQL schema
--
-- Import this file in MySQL Workbench (File > Run SQL Script) or phpMyAdmin.
-- It creates the `bhsystem` database and every table the application uses.
-- Then import `seed.sql` if you want realistic demo data.
--
-- Compatible with MySQL 8.0+ and MariaDB 10.4+ (XAMPP).
-- WARNING: this script drops and recreates all tables.
-- ============================================================================

CREATE DATABASE IF NOT EXISTS bhsystem CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
SET NAMES utf8mb4;
USE bhsystem;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS settings;
DROP TABLE IF EXISTS login_codes;
DROP TABLE IF EXISTS chat_messages;
DROP TABLE IF EXISTS conversations;
DROP TABLE IF EXISTS rent_payments;
DROP TABLE IF EXISTS inquiries;
DROP TABLE IF EXISTS room_images;
DROP TABLE IF EXISTS activity_logs;
DROP TABLE IF EXISTS audit_logs;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS bookings;
DROP TABLE IF EXISTS room_amenities;
DROP TABLE IF EXISTS amenities;
DROP TABLE IF EXISTS rooms;
DROP TABLE IF EXISTS boarding_house_images;
DROP TABLE IF EXISTS boarding_houses;
DROP TABLE IF EXISTS barangays;
DROP TABLE IF EXISTS verification_documents;
DROP TABLE IF EXISTS landlords;
DROP TABLE IF EXISTS password_resets;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

-- ----------------------------------------------------------------------------
-- users: every account (tenant/student, landlord, admin, super_admin)
-- ----------------------------------------------------------------------------
CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL,
  email VARCHAR(255) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  avatar_path VARCHAR(255) NULL,
  role ENUM('tenant', 'landlord', 'admin', 'super_admin') NOT NULL DEFAULT 'tenant',
  first_name VARCHAR(80) NOT NULL DEFAULT '',
  middle_name VARCHAR(80) NULL,
  last_name VARCHAR(80) NOT NULL DEFAULT '',
  contact_number VARCHAR(20) NULL,
  address VARCHAR(255) NULL,
  gender ENUM('male', 'female', 'prefer_not_to_say') NULL,
  birth_date DATE NULL,
  status ENUM('active', 'pending', 'disabled') NOT NULL DEFAULT 'active',
  failed_login_attempts INT NOT NULL DEFAULT 0,
  locked_until DATETIME NULL,
  last_login_at DATETIME NULL,
  notification_prefs TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_users_username (username),
  UNIQUE KEY uq_users_email (email),
  KEY idx_users_role_status (role, status),
  KEY idx_users_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- landlords: landlord-specific business and verification details (1:1 users)
-- ----------------------------------------------------------------------------
CREATE TABLE landlords (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  business_name VARCHAR(150) NULL,
  business_address VARCHAR(255) NULL,
  verification_status ENUM('unverified', 'pending', 'verified', 'rejected') NOT NULL DEFAULT 'pending',
  verified_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_landlords_user (user_id),
  CONSTRAINT fk_landlords_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- verification_documents: IDs, selfies, permits uploaded at registration.
-- Files are stored in storage/documents (never publicly reachable).
-- ----------------------------------------------------------------------------
CREATE TABLE verification_documents (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  doc_type ENUM('student_id', 'government_id', 'selfie', 'business_permit', 'proof_of_ownership') NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  stored_name VARCHAR(100) NOT NULL,
  mime_type VARCHAR(100) NOT NULL,
  file_size INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_docs_user (user_id),
  CONSTRAINT fk_docs_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- barangays: the dropdown list landlords pick from. Add more rows here (or in
-- MySQL Workbench) to support more barangays/municipalities.
-- ----------------------------------------------------------------------------
CREATE TABLE barangays (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  municipality VARCHAR(100) NOT NULL,
  province VARCHAR(100) NOT NULL DEFAULT 'Surigao del Norte',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_barangay (name, municipality, province),
  KEY idx_barangay_municipality (municipality)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- boarding_houses: LANDLORD 1 ── * BOARDING HOUSE
-- ----------------------------------------------------------------------------
CREATE TABLE boarding_houses (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  landlord_id INT UNSIGNED NOT NULL,
  name VARCHAR(150) NOT NULL,
  description TEXT NULL,
  address VARCHAR(255) NOT NULL,
  barangay_id INT UNSIGNED NULL,
  barangay VARCHAR(100) NULL,
  city VARCHAR(100) NOT NULL,
  province VARCHAR(100) NULL,
  location_note VARCHAR(255) NULL,
  latitude DECIMAL(10, 7) NULL,
  longitude DECIMAL(10, 7) NULL,
  map_url VARCHAR(500) NULL,
  nearby_school VARCHAR(150) NULL,
  distance_note VARCHAR(150) NULL,
  contact_name VARCHAR(160) NULL,
  contact_number VARCHAR(20) NULL,
  contact_email VARCHAR(255) NULL,
  house_rules TEXT NULL,
  -- status = administrator approval; availability_status = set by the landlord.
  status ENUM('pending', 'approved', 'rejected', 'inactive') NOT NULL DEFAULT 'pending',
  availability_status ENUM('available', 'fully_occupied', 'temporarily_unavailable') NOT NULL DEFAULT 'available',
  rejection_reason VARCHAR(255) NULL,
  approved_at DATETIME NULL,
  approved_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_bh_landlord (landlord_id),
  KEY idx_bh_status (status),
  KEY idx_bh_city (city),
  KEY idx_bh_barangay (barangay_id),
  CONSTRAINT fk_bh_landlord FOREIGN KEY (landlord_id) REFERENCES landlords (id) ON DELETE CASCADE,
  CONSTRAINT fk_bh_barangay FOREIGN KEY (barangay_id) REFERENCES barangays (id) ON DELETE SET NULL,
  CONSTRAINT fk_bh_approved_by FOREIGN KEY (approved_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE boarding_house_images (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  boarding_house_id INT UNSIGNED NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  is_cover TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_bhi_house (boarding_house_id),
  CONSTRAINT fk_bhi_house FOREIGN KEY (boarding_house_id) REFERENCES boarding_houses (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- rooms: BOARDING HOUSE 1 ── * ROOM
-- status: available = has free slots, occupied = full, maintenance = not rentable
-- ----------------------------------------------------------------------------
CREATE TABLE rooms (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  boarding_house_id INT UNSIGNED NOT NULL,
  room_number VARCHAR(60) NOT NULL,
  room_type ENUM('solo', 'shared', 'dormitory', 'studio') NOT NULL DEFAULT 'shared',
  price DECIMAL(10, 2) NOT NULL,
  deposit DECIMAL(10, 2) NOT NULL DEFAULT 0,
  capacity TINYINT UNSIGNED NOT NULL DEFAULT 1,
  occupants TINYINT UNSIGNED NOT NULL DEFAULT 0,
  size_sqm DECIMAL(6, 2) NULL,
  description TEXT NULL,
  status ENUM('available', 'occupied', 'maintenance') NOT NULL DEFAULT 'available',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_rooms_house_number (boarding_house_id, room_number),
  KEY idx_rooms_status (status),
  KEY idx_rooms_price (price),
  CONSTRAINT fk_rooms_house FOREIGN KEY (boarding_house_id) REFERENCES boarding_houses (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Photos of one specific room (shown before the boarding house photos).
CREATE TABLE room_images (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  room_id INT UNSIGNED NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_room_images_room (room_id, sort_order),
  CONSTRAINT fk_room_images_room FOREIGN KEY (room_id) REFERENCES rooms (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE amenities (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(60) NOT NULL,
  -- NULL = standard amenity; otherwise the landlord who added it as a custom amenity.
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_amenities_name (name),
  CONSTRAINT fk_amenities_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE room_amenities (
  room_id INT UNSIGNED NOT NULL,
  amenity_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (room_id, amenity_id),
  KEY idx_ra_amenity (amenity_id),
  CONSTRAINT fk_ra_room FOREIGN KEY (room_id) REFERENCES rooms (id) ON DELETE CASCADE,
  CONSTRAINT fk_ra_amenity FOREIGN KEY (amenity_id) REFERENCES amenities (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- bookings: TENANT * ── * ROOM.  RESTRICT keeps rental history from being
-- deleted by accident; disable an account instead of deleting it.
-- ----------------------------------------------------------------------------
CREATE TABLE bookings (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id INT UNSIGNED NOT NULL,
  room_id INT UNSIGNED NOT NULL,
  booking_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  move_in_date DATE NULL,
  occupants_count TINYINT UNSIGNED NOT NULL DEFAULT 1,
  contact_name VARCHAR(160) NULL,
  contact_number VARCHAR(20) NULL,
  contact_email VARCHAR(255) NULL,
  message VARCHAR(1000) NULL,
  status ENUM('pending', 'approved', 'rejected', 'cancelled', 'completed') NOT NULL DEFAULT 'pending',
  notes VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_bookings_tenant (tenant_id),
  KEY idx_bookings_room (room_id),
  KEY idx_bookings_status (status),
  KEY idx_bookings_date (booking_date),
  CONSTRAINT fk_bookings_tenant FOREIGN KEY (tenant_id) REFERENCES users (id) ON DELETE RESTRICT,
  CONSTRAINT fk_bookings_room FOREIGN KEY (room_id) REFERENCES rooms (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- inquiries: "Contact Landlord" messages from students (logged in or guests).
-- The landlord reads them and replies; tenant_id is NULL for guests.
-- ----------------------------------------------------------------------------
CREATE TABLE inquiries (
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

-- ----------------------------------------------------------------------------
-- rent_payments: monthly rent of a boarder (an approved reservation).
-- One row per reservation and month; the landlord records what was paid.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS rent_payments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  booking_id INT UNSIGNED NOT NULL,
  period_month DATE NOT NULL COMMENT 'First day of the month the rent is for',
  amount_due DECIMAL(10,2) NOT NULL DEFAULT 0,
  amount_paid DECIMAL(10,2) NOT NULL DEFAULT 0,
  status ENUM('unpaid', 'partial', 'paid') NOT NULL DEFAULT 'unpaid',
  paid_at DATE NULL,
  method ENUM('cash', 'gcash', 'bank', 'other') NULL,
  reference VARCHAR(100) NULL,
  note VARCHAR(500) NULL,
  recorded_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_payment_month (booking_id, period_month),
  KEY idx_payment_month (period_month, status),
  CONSTRAINT fk_payment_booking FOREIGN KEY (booking_id) REFERENCES bookings (id) ON DELETE CASCADE,
  CONSTRAINT fk_payment_recorder FOREIGN KEY (recorded_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- conversations / chat_messages: chat between a landlord and a tenant.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS conversations (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  landlord_user_id INT UNSIGNED NOT NULL,
  tenant_id INT UNSIGNED NOT NULL,
  last_message_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_conversation (landlord_user_id, tenant_id),
  KEY idx_conversation_tenant (tenant_id),
  CONSTRAINT fk_conv_landlord FOREIGN KEY (landlord_user_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_conv_tenant FOREIGN KEY (tenant_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS chat_messages (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  conversation_id INT UNSIGNED NOT NULL,
  sender_id INT UNSIGNED NOT NULL,
  body VARCHAR(2000) NOT NULL,
  read_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_chat_conversation (conversation_id, id),
  CONSTRAINT fk_chat_conversation FOREIGN KEY (conversation_id) REFERENCES conversations (id) ON DELETE CASCADE,
  CONSTRAINT fk_chat_sender FOREIGN KEY (sender_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- login_codes: 6-digit codes emailed to the Super Admin at login (hash only).
-- (security/two_factor.php also creates this table automatically.)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS login_codes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  code_hash VARCHAR(255) NOT NULL,
  expires_at DATETIME NOT NULL,
  attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  used_at DATETIME NULL,
  ip_address VARCHAR(45) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_login_codes_user (user_id, created_at),
  CONSTRAINT fk_login_codes_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- notifications: audience 'admin' = shown to all administrators,
-- audience 'user' = shown only to user_id.
-- ----------------------------------------------------------------------------
CREATE TABLE notifications (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  audience ENUM('admin', 'user') NOT NULL DEFAULT 'user',
  type VARCHAR(40) NOT NULL DEFAULT 'info',
  title VARCHAR(150) NOT NULL,
  message VARCHAR(500) NOT NULL,
  link VARCHAR(255) NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_notif_user (user_id, is_read),
  KEY idx_notif_audience (audience, is_read),
  CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- activity_logs: who did what, and when (shown in "Recent Activities")
-- ----------------------------------------------------------------------------
CREATE TABLE activity_logs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  action VARCHAR(50) NOT NULL,
  description VARCHAR(500) NOT NULL,
  entity_type VARCHAR(40) NULL,
  entity_id INT UNSIGNED NULL,
  ip_address VARCHAR(45) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_activity_user (user_id),
  KEY idx_activity_action (action),
  KEY idx_activity_created (created_at),
  CONSTRAINT fk_activity_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE password_resets (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  token CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_reset_token (token),
  CONSTRAINT fk_reset_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- settings: system-wide key/value configuration editable in Admin > Settings
-- ----------------------------------------------------------------------------
CREATE TABLE settings (
  setting_key VARCHAR(60) NOT NULL PRIMARY KEY,
  setting_value VARCHAR(500) NOT NULL,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO settings (setting_key, setting_value) VALUES
  ('site_name', 'Boarding House Rental System'),
  ('support_email', 'support@bhrental.local'),
  ('support_phone', '09171234567'),
  ('require_listing_approval', '1'),
  ('allow_tenant_registration', '1'),
  ('allow_landlord_registration', '1'),
  ('max_upload_mb', '5');

INSERT INTO amenities (name) VALUES
  ('Wi-Fi'), ('Bed'), ('Cabinet'), ('Table'), ('Chair'), ('Electric Fan'), ('Air Conditioning'),
  ('Private Bathroom'), ('Shared Bathroom'), ('Kitchen'), ('Laundry Area'), ('Parking'),
  ('Study Area'), ('CCTV'), ('Water Supply'), ('Electricity Included');

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

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
USE bhsystem;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS settings;
DROP TABLE IF EXISTS activity_logs;
DROP TABLE IF EXISTS audit_logs;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS bookings;
DROP TABLE IF EXISTS room_amenities;
DROP TABLE IF EXISTS amenities;
DROP TABLE IF EXISTS rooms;
DROP TABLE IF EXISTS boarding_house_images;
DROP TABLE IF EXISTS boarding_houses;
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
-- boarding_houses: LANDLORD 1 ── * BOARDING HOUSE
-- ----------------------------------------------------------------------------
CREATE TABLE boarding_houses (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  landlord_id INT UNSIGNED NOT NULL,
  name VARCHAR(150) NOT NULL,
  description TEXT NULL,
  address VARCHAR(255) NOT NULL,
  barangay VARCHAR(100) NULL,
  city VARCHAR(100) NOT NULL,
  province VARCHAR(100) NULL,
  latitude DECIMAL(10, 7) NULL,
  longitude DECIMAL(10, 7) NULL,
  nearby_school VARCHAR(150) NULL,
  contact_number VARCHAR(20) NULL,
  contact_email VARCHAR(255) NULL,
  house_rules TEXT NULL,
  status ENUM('pending', 'approved', 'rejected', 'inactive') NOT NULL DEFAULT 'pending',
  rejection_reason VARCHAR(255) NULL,
  approved_at DATETIME NULL,
  approved_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_bh_landlord (landlord_id),
  KEY idx_bh_status (status),
  KEY idx_bh_city (city),
  CONSTRAINT fk_bh_landlord FOREIGN KEY (landlord_id) REFERENCES landlords (id) ON DELETE CASCADE,
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
  room_number VARCHAR(20) NOT NULL,
  room_type ENUM('solo', 'shared', 'dormitory', 'studio') NOT NULL DEFAULT 'shared',
  price DECIMAL(10, 2) NOT NULL,
  deposit DECIMAL(10, 2) NOT NULL DEFAULT 0,
  capacity TINYINT UNSIGNED NOT NULL DEFAULT 1,
  occupants TINYINT UNSIGNED NOT NULL DEFAULT 0,
  size_sqm DECIMAL(6, 2) NULL,
  description VARCHAR(500) NULL,
  status ENUM('available', 'occupied', 'maintenance') NOT NULL DEFAULT 'available',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_rooms_house_number (boarding_house_id, room_number),
  KEY idx_rooms_status (status),
  KEY idx_rooms_price (price),
  CONSTRAINT fk_rooms_house FOREIGN KEY (boarding_house_id) REFERENCES boarding_houses (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE amenities (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(60) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_amenities_name (name)
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
  status ENUM('pending', 'approved', 'cancelled', 'completed') NOT NULL DEFAULT 'pending',
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
  ('WiFi'), ('Air Conditioning'), ('Electric Fan'), ('Study Table'), ('Bed & Mattress'),
  ('Cabinet'), ('Private Bathroom'), ('Shared Bathroom'), ('Kitchen Access'), ('Laundry Area'),
  ('CCTV'), ('Water Included'), ('Electricity Included'), ('Parking'), ('Curfew-Free'),
  ('Balcony'), ('Refrigerator'), ('Security Guard');

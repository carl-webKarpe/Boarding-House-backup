-- ============================================================================
-- Boarding House Rental System - UPGRADE: APARTMENTS
--
-- Adds apartments next to boarding houses:
--   boarding_houses.property_type   'boarding_house' (default) or 'apartment'
--   rooms.room_type                 new value 'apartment' = a whole apartment unit
--
-- Run it ONCE on a database created before this feature:
--   InfinityFree: phpMyAdmin -> click your database on the left -> SQL tab ->
--                 paste this file -> Go   (or Import -> choose this file -> Go)
--   XAMPP / MySQL Workbench: select the bhsystem database first, then run it.
-- Nothing is deleted, existing listings stay boarding houses, and running it
-- twice is harmless.
-- ============================================================================
SET NAMES utf8mb4;

-- Add the property type column only if it is not there yet.
SET @has_column := (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'boarding_houses' AND COLUMN_NAME = 'property_type');
SET @add_column := IF(@has_column = 0,
  'ALTER TABLE boarding_houses ADD COLUMN property_type ENUM(''boarding_house'', ''apartment'') NOT NULL DEFAULT ''boarding_house'' AFTER landlord_id',
  'SELECT ''property_type already exists'' AS note');
PREPARE add_column_stmt FROM @add_column;
EXECUTE add_column_stmt;
DEALLOCATE PREPARE add_column_stmt;

-- Allow apartment units (keeps every existing room type).
ALTER TABLE rooms
  MODIFY room_type ENUM('solo', 'shared', 'dormitory', 'studio', 'apartment') NOT NULL DEFAULT 'shared';

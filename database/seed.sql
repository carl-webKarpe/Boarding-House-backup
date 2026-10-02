-- ============================================================================
-- Boarding House Rental System - YOUR DATA (from the landing page)
--
-- Run AFTER schema.sql. This resets every table and loads the six boarding
-- houses that were on the landing page (html/index.html), so the landing
-- page and the Admin Dashboard show the same records.
--
-- Logins:
--   Administrator : admin@bhrental.local          / Admin@12345
--   Landlords     : <house>.owner@bhrental.local   / Demo@12345
--                   (greenview, islandhome, studenthaven, northview,
--                    sunrise, seaside)
--
-- Landlord names and phone numbers below are placeholders - update them in
-- Admin > Landlords. Add descriptions, rules and photos in
-- Admin > Boarding Houses.
-- ============================================================================
SET NAMES utf8mb4;
USE bhsystem;

SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE activity_logs;
TRUNCATE TABLE notifications;
TRUNCATE TABLE bookings;
TRUNCATE TABLE room_amenities;
TRUNCATE TABLE rooms;
TRUNCATE TABLE amenities;
TRUNCATE TABLE boarding_house_images;
TRUNCATE TABLE boarding_houses;
TRUNCATE TABLE verification_documents;
TRUNCATE TABLE landlords;
TRUNCATE TABLE password_resets;
TRUNCATE TABLE users;
SET FOREIGN_KEY_CHECKS = 1;

-- ----------------------------------------------------------------------------
-- Amenities (the ones used on the landing page, plus common extras)
-- ----------------------------------------------------------------------------
INSERT INTO amenities (name) VALUES
  ('Wi-Fi'), ('Electricity'), ('Water'), ('Study Area'), ('Kitchen'), ('Shared Kitchen'),
  ('Laundry'), ('Private Bathroom'), ('Security Guard'), ('Electric Fan'),
  ('Air Conditioning'), ('CCTV'), ('Parking'), ('Bed & Mattress'), ('Cabinet');

-- ----------------------------------------------------------------------------
-- Accounts
-- ----------------------------------------------------------------------------
INSERT INTO users (id, username, email, password_hash, role, first_name, last_name, contact_number, address, status) VALUES
  (1, 'sysadmin', 'admin@bhrental.local', '$2y$10$lFIq0AKp3uDNyVaJiIOFuugdAmEuyx4NYZA5y/knjEU2sYCWL6bXG', 'super_admin', 'System', 'Administrator', NULL, 'Siargao Island Institute of Technology, Dapa', 'active'),
  (2, 'greenview_owner', 'greenview.owner@bhrental.local', '$2y$10$ifFDpLEemCtlqMim9g4ftOX0TvoGUvSrWAWwJ2xet2hajAHo27AxW', 'landlord', 'Green View', 'Owner', '09000000001', 'Purok 2, Dapa, Siargao', 'active'),
  (3, 'islandhome_owner', 'islandhome.owner@bhrental.local', '$2y$10$ifFDpLEemCtlqMim9g4ftOX0TvoGUvSrWAWwJ2xet2hajAHo27AxW', 'landlord', 'Island Home', 'Owner', '09000000002', 'Brgy. 5, Dapa, Siargao', 'active'),
  (4, 'haven_owner', 'studenthaven.owner@bhrental.local', '$2y$10$ifFDpLEemCtlqMim9g4ftOX0TvoGUvSrWAWwJ2xet2hajAHo27AxW', 'landlord', 'Student Haven', 'Owner', '09000000003', 'Brgy. 9, Dapa, Siargao', 'active'),
  (5, 'northview_owner', 'northview.owner@bhrental.local', '$2y$10$ifFDpLEemCtlqMim9g4ftOX0TvoGUvSrWAWwJ2xet2hajAHo27AxW', 'landlord', 'Northview', 'Owner', '09000000004', 'Brgy. Osmeña, Dapa, Siargao', 'active'),
  (6, 'sunrise_owner', 'sunrise.owner@bhrental.local', '$2y$10$ifFDpLEemCtlqMim9g4ftOX0TvoGUvSrWAWwJ2xet2hajAHo27AxW', 'landlord', 'Sunrise', 'Owner', '09000000005', 'Brgy. 3, Dapa, Siargao', 'active'),
  (7, 'seaside_owner', 'seaside.owner@bhrental.local', '$2y$10$ifFDpLEemCtlqMim9g4ftOX0TvoGUvSrWAWwJ2xet2hajAHo27AxW', 'landlord', 'Seaside', 'Owner', '09000000006', 'Brgy. Union, Dapa, Siargao', 'active');

INSERT INTO landlords (id, user_id, business_name, business_address, verification_status, verified_at) VALUES
  (1, 2, 'Green View Boarding House', 'Purok 2, Dapa, Siargao', 'verified', NOW()),
  (2, 3, 'Island Home Boarding House', 'Brgy. 5, Dapa, Siargao', 'verified', NOW()),
  (3, 4, 'Student Haven', 'Brgy. 9, Dapa, Siargao', 'verified', NOW()),
  (4, 5, 'Northview Student Residences', 'Brgy. Osmeña, Dapa, Siargao', 'verified', NOW()),
  (5, 6, 'Boarding House Sunrise', 'Brgy. 3, Dapa, Siargao', 'verified', NOW()),
  (6, 7, 'Seaside Boarders', 'Brgy. Union, Dapa, Siargao', 'verified', NOW());

-- ----------------------------------------------------------------------------
-- Boarding houses (coordinates from the landing page, around SIIT)
-- ----------------------------------------------------------------------------
INSERT INTO boarding_houses (id, landlord_id, name, address, barangay, city, province, latitude, longitude, nearby_school, contact_number, status, approved_at, approved_by) VALUES
  (1, 1, 'Green View Boarding House', 'Purok 2', NULL, 'Dapa', 'Surigao del Norte', 9.7608000, 126.0490000, 'SIIT - Siargao Island Institute of Technology', '09000000001', 'approved', NOW(), 1),
  (2, 2, 'Island Home Boarding House', 'Brgy. 5', 'Brgy. 5', 'Dapa', 'Surigao del Norte', 9.7602000, 126.0495000, 'SIIT - Siargao Island Institute of Technology', '09000000002', 'approved', NOW(), 1),
  (3, 3, 'Student Haven', 'Brgy. 9', 'Brgy. 9', 'Dapa', 'Surigao del Norte', 9.7612000, 126.0493000, 'SIIT - Siargao Island Institute of Technology', '09000000003', 'approved', NOW(), 1),
  (4, 4, 'Northview Student Residences', 'Brgy. Osmeña', 'Brgy. Osmeña', 'Dapa', 'Surigao del Norte', 9.7618000, 126.0500000, 'SIIT - Siargao Island Institute of Technology', '09000000004', 'approved', NOW(), 1),
  (5, 5, 'Boarding House Sunrise', 'Brgy. 3', 'Brgy. 3', 'Dapa', 'Surigao del Norte', 9.7598000, 126.0492000, 'SIIT - Siargao Island Institute of Technology', '09000000005', 'approved', NOW(), 1),
  (6, 6, 'Seaside Boarders', 'Brgy. Union', 'Brgy. Union', 'Dapa', 'Surigao del Norte', 9.7605000, 126.0500000, 'SIIT - Siargao Island Institute of Technology', '09000000006', 'approved', NOW(), 1);

-- ----------------------------------------------------------------------------
-- Photos (the first one of each house is the cover; the rest are shown in
-- the "View Details" slideshow). Paths are relative to the project folder.
-- ----------------------------------------------------------------------------
INSERT INTO boarding_house_images (boarding_house_id, file_path, is_cover) VALUES
  (1, 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQP2gJlXIUqunx_oRPYPnk3T67yT5JCJlJDHbZc9K22Jg&s=10', 1),
  (1, 'Image/ROOM 1.jfif', 0),
  (1, 'Image/ROOM 3.jfif', 0),
  (2, 'Image/image2.jpg', 1),
  (2, 'Image/ROOM 2.jfif', 0),
  (2, 'Image/ROOM 3.jfif', 0),
  (3, 'Image/haven.jpg', 1),
  (3, 'Image/ROOM 1.jfif', 0),
  (3, 'Image/ROOM 3.jfif', 0),
  (4, 'Image/image4.jpg', 1),
  (4, 'Image/ROOM 2.jfif', 0),
  (4, 'Image/ROOM 1.jfif', 0),
  (5, 'Image/images.jpg', 1),
  (5, 'Image/ROOM 4.jfif', 0),
  (5, 'Image/ROOM 2.jfif', 0),
  (6, 'Image/image3.jpg', 1),
  (6, 'Image/ROOM 4.jfif', 0),
  (6, 'Image/background1.jfif', 0);

-- ----------------------------------------------------------------------------
-- Rooms
--   Single Room -> solo (1 bed)      Shared Room -> shared (2 beds)
--   Bedspace    -> dormitory, price is per bed
--   "Coming Soon" (Student Haven) -> maintenance until the rooms open
-- ----------------------------------------------------------------------------
INSERT INTO rooms (boarding_house_id, room_number, room_type, price, deposit, capacity, occupants, status) VALUES
  -- Green View: 6 single rooms, P2,500
  (1, '101', 'solo', 2500, 2500, 1, 0, 'available'),
  (1, '102', 'solo', 2500, 2500, 1, 0, 'available'),
  (1, '103', 'solo', 2500, 2500, 1, 0, 'available'),
  (1, '104', 'solo', 2500, 2500, 1, 0, 'available'),
  (1, '105', 'solo', 2500, 2500, 1, 0, 'available'),
  (1, '106', 'solo', 2500, 2500, 1, 0, 'available'),
  -- Island Home: 5 shared rooms, P2,500
  (2, '101', 'shared', 2500, 2500, 2, 0, 'available'),
  (2, '102', 'shared', 2500, 2500, 2, 0, 'available'),
  (2, '103', 'shared', 2500, 2500, 2, 0, 'available'),
  (2, '104', 'shared', 2500, 2500, 2, 0, 'available'),
  (2, '105', 'shared', 2500, 2500, 2, 0, 'available'),
  -- Student Haven: 4 single rooms, P5,000, coming soon
  (3, '101', 'solo', 5000, 5000, 1, 0, 'maintenance'),
  (3, '102', 'solo', 5000, 5000, 1, 0, 'maintenance'),
  (3, '103', 'solo', 5000, 5000, 1, 0, 'maintenance'),
  (3, '104', 'solo', 5000, 5000, 1, 0, 'maintenance'),
  -- Northview: 8 shared rooms, P1,800
  (4, '101', 'shared', 1800, 1800, 2, 0, 'available'),
  (4, '102', 'shared', 1800, 1800, 2, 0, 'available'),
  (4, '103', 'shared', 1800, 1800, 2, 0, 'available'),
  (4, '104', 'shared', 1800, 1800, 2, 0, 'available'),
  (4, '201', 'shared', 1800, 1800, 2, 0, 'available'),
  (4, '202', 'shared', 1800, 1800, 2, 0, 'available'),
  (4, '203', 'shared', 1800, 1800, 2, 0, 'available'),
  (4, '204', 'shared', 1800, 1800, 2, 0, 'available'),
  -- Sunrise: 12 bedspaces (3 rooms x 4 beds), P1,300 per bed
  (5, 'A', 'dormitory', 1300, 1300, 4, 0, 'available'),
  (5, 'B', 'dormitory', 1300, 1300, 4, 0, 'available'),
  (5, 'C', 'dormitory', 1300, 1300, 4, 0, 'available'),
  -- Seaside: 10 bedspaces (2 rooms x 5 beds), P1,200 per bed
  (6, 'A', 'dormitory', 1200, 1200, 5, 0, 'available'),
  (6, 'B', 'dormitory', 1200, 1200, 5, 0, 'available');

-- Room amenities, by boarding house
INSERT INTO room_amenities (room_id, amenity_id)
  SELECT r.id, a.id FROM rooms r JOIN amenities a ON a.name IN ('Wi-Fi', 'Electricity', 'Water', 'Study Area') WHERE r.boarding_house_id = 1;
INSERT INTO room_amenities (room_id, amenity_id)
  SELECT r.id, a.id FROM rooms r JOIN amenities a ON a.name IN ('Wi-Fi', 'Kitchen', 'Water', 'Laundry') WHERE r.boarding_house_id = 2;
INSERT INTO room_amenities (room_id, amenity_id)
  SELECT r.id, a.id FROM rooms r JOIN amenities a ON a.name IN ('Wi-Fi', 'Private Bathroom', 'Study Area') WHERE r.boarding_house_id = 3;
INSERT INTO room_amenities (room_id, amenity_id)
  SELECT r.id, a.id FROM rooms r JOIN amenities a ON a.name IN ('Wi-Fi', 'Kitchen', 'Study Area', 'Security Guard') WHERE r.boarding_house_id = 4;
INSERT INTO room_amenities (room_id, amenity_id)
  SELECT r.id, a.id FROM rooms r JOIN amenities a ON a.name IN ('Wi-Fi', 'Shared Kitchen', 'Electric Fan') WHERE r.boarding_house_id = 5;
INSERT INTO room_amenities (room_id, amenity_id)
  SELECT r.id, a.id FROM rooms r JOIN amenities a ON a.name IN ('Wi-Fi', 'Shared Kitchen', 'Water') WHERE r.boarding_house_id = 6;

-- ----------------------------------------------------------------------------
-- Activity log entries for the Admin Dashboard timeline
-- ----------------------------------------------------------------------------
INSERT INTO activity_logs (user_id, action, description, entity_type, entity_id, ip_address) VALUES
  (1, 'listing_approve', 'Boarding house approved: Green View Boarding House', 'boarding_house', 1, '127.0.0.1'),
  (1, 'listing_approve', 'Boarding house approved: Island Home Boarding House', 'boarding_house', 2, '127.0.0.1'),
  (1, 'listing_approve', 'Boarding house approved: Student Haven', 'boarding_house', 3, '127.0.0.1'),
  (1, 'listing_approve', 'Boarding house approved: Northview Student Residences', 'boarding_house', 4, '127.0.0.1'),
  (1, 'listing_approve', 'Boarding house approved: Boarding House Sunrise', 'boarding_house', 5, '127.0.0.1'),
  (1, 'listing_approve', 'Boarding house approved: Seaside Boarders', 'boarding_house', 6, '127.0.0.1');

INSERT INTO notifications (user_id, audience, type, title, message, link) VALUES
  (NULL, 'admin', 'system', 'Your data is loaded', 'Six boarding houses from the landing page were added. Update the landlord names and phone numbers in Landlords.', '#/landlords');

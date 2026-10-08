-- ============================================================================
-- Boarding House Rental System - ACCOUNTS (generated)
-- Import AFTER schema.sql. Creates the admin, landlord and tenant accounts only.
-- No boarding houses: landlords add real listings from the Landlord Dashboard.
--
-- Demo logins (LOCAL DEVELOPMENT ONLY - never import this file in production):
--   Admin    : admin@bhrental.local      / Admin@12345
--   Landlord : landlord1@bhrental.local  / Demo@12345
--   Tenant   : tenant1@bhrental.local    / Demo@12345
-- ============================================================================
SET NAMES utf8mb4;
USE bhsystem;
SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE inquiries;
TRUNCATE TABLE room_images;
TRUNCATE TABLE activity_logs;
TRUNCATE TABLE notifications;
TRUNCATE TABLE bookings;
TRUNCATE TABLE room_amenities;
TRUNCATE TABLE rooms;
TRUNCATE TABLE boarding_house_images;
TRUNCATE TABLE boarding_houses;
TRUNCATE TABLE verification_documents;
TRUNCATE TABLE landlords;
TRUNCATE TABLE password_resets;
TRUNCATE TABLE users;
SET FOREIGN_KEY_CHECKS = 1;

-- Administrators
INSERT INTO users (id, username, email, password_hash, role, first_name, middle_name, last_name, contact_number, address, gender, birth_date, status, last_login_at, created_at) VALUES (1, 'sysadmin', 'admin@bhrental.local', '$2y$10$lFIq0AKp3uDNyVaJiIOFuugdAmEuyx4NYZA5y/knjEU2sYCWL6bXG', 'super_admin', 'System', NULL, 'Administrator', '09588139986', 'Brgy. Poblacion 5, Dapa, Surigao del Norte', 'female', DATE(NOW() - INTERVAL 20740 DAY), 'active', NOW() - INTERVAL 324420 MINUTE, NOW() - INTERVAL 432039 MINUTE);
INSERT INTO users (id, username, email, password_hash, role, first_name, middle_name, last_name, contact_number, address, gender, birth_date, status, last_login_at, created_at) VALUES (2, 'staff_admin', 'staff.admin@bhrental.local', '$2y$10$ifFDpLEemCtlqMim9g4ftOX0TvoGUvSrWAWwJ2xet2hajAHo27AxW', 'admin', 'Rhea', NULL, 'Montenegro', '09141707536', 'Brgy. Poblacion 5, Dapa, Surigao del Norte', 'female', DATE(NOW() - INTERVAL 15877 DAY), 'active', NOW() - INTERVAL 297360 MINUTE, NOW() - INTERVAL 347012 MINUTE);

-- Landlords
INSERT INTO users (id, username, email, password_hash, role, first_name, middle_name, last_name, contact_number, address, gender, birth_date, status, last_login_at, created_at) VALUES (3, 'landlord1', 'landlord1@bhrental.local', '$2y$10$ifFDpLEemCtlqMim9g4ftOX0TvoGUvSrWAWwJ2xet2hajAHo27AxW', 'landlord', 'Mark', NULL, 'Cabrera', '09188447294', 'Brgy. Poblacion, Del Carmen, Surigao del Norte', 'male', DATE(NOW() - INTERVAL 21723 DAY), 'active', NOW() - INTERVAL 268680 MINUTE, NOW() - INTERVAL 402545 MINUTE);
INSERT INTO landlords (id, user_id, business_name, business_address, verification_status, verified_at, created_at) VALUES (1, 3, 'Cabrera Rentals', 'Brgy. Poblacion 1, Dapa, Surigao del Norte', 'verified', NOW() - INTERVAL 398880 MINUTE, NOW() - INTERVAL 401760 MINUTE);
INSERT INTO users (id, username, email, password_hash, role, first_name, middle_name, last_name, contact_number, address, gender, birth_date, status, last_login_at, created_at) VALUES (4, 'landlord2', 'landlord2@bhrental.local', '$2y$10$ifFDpLEemCtlqMim9g4ftOX0TvoGUvSrWAWwJ2xet2hajAHo27AxW', 'landlord', 'Faith', NULL, 'Pascual', '09716897684', 'Brgy. Poblacion, Santa Monica, Surigao del Norte', 'female', DATE(NOW() - INTERVAL 18495 DAY), 'active', NOW() - INTERVAL 378240 MINUTE, NOW() - INTERVAL 400356 MINUTE);
INSERT INTO landlords (id, user_id, business_name, business_address, verification_status, verified_at, created_at) VALUES (2, 4, 'Pascual Rentals', 'Brgy. Poblacion 5, Dapa, Surigao del Norte', 'verified', NOW() - INTERVAL 397440 MINUTE, NOW() - INTERVAL 400320 MINUTE);
INSERT INTO users (id, username, email, password_hash, role, first_name, middle_name, last_name, contact_number, address, gender, birth_date, status, last_login_at, created_at) VALUES (5, 'rafael_espinosa3', 'rafael.espinosa3@gmail.com', '$2y$10$ifFDpLEemCtlqMim9g4ftOX0TvoGUvSrWAWwJ2xet2hajAHo27AxW', 'landlord', 'Rafael', NULL, 'Espinosa', '09819672731', 'Brgy. Poblacion 1, Dapa, Surigao del Norte', 'female', DATE(NOW() - INTERVAL 19585 DAY), 'active', NOW() - INTERVAL 93180 MINUTE, NOW() - INTERVAL 196602 MINUTE);
INSERT INTO landlords (id, user_id, business_name, business_address, verification_status, verified_at, created_at) VALUES (3, 5, 'Espinosa Rentals', 'Brgy. Poblacion 9, Dapa, Surigao del Norte', 'verified', NOW() - INTERVAL 192960 MINUTE, NOW() - INTERVAL 195840 MINUTE);
INSERT INTO users (id, username, email, password_hash, role, first_name, middle_name, last_name, contact_number, address, gender, birth_date, status, last_login_at, created_at) VALUES (6, 'bea_domingo4', 'bea.domingo4@gmail.com', '$2y$10$ifFDpLEemCtlqMim9g4ftOX0TvoGUvSrWAWwJ2xet2hajAHo27AxW', 'landlord', 'Bea', NULL, 'Domingo', '09876634400', 'Brgy. Poblacion, San Isidro, Surigao del Norte', 'male', DATE(NOW() - INTERVAL 19214 DAY), 'active', NOW() - INTERVAL 296160 MINUTE, NOW() - INTERVAL 320603 MINUTE);
INSERT INTO landlords (id, user_id, business_name, business_address, verification_status, verified_at, created_at) VALUES (4, 6, 'Domingo Rentals', 'Brgy. Osmeña, Dapa, Surigao del Norte', 'verified', NOW() - INTERVAL 316800 MINUTE, NOW() - INTERVAL 319680 MINUTE);
INSERT INTO users (id, username, email, password_hash, role, first_name, middle_name, last_name, contact_number, address, gender, birth_date, status, last_login_at, created_at) VALUES (7, 'miguel_bautista5', 'miguel.bautista5@gmail.com', '$2y$10$ifFDpLEemCtlqMim9g4ftOX0TvoGUvSrWAWwJ2xet2hajAHo27AxW', 'landlord', 'Miguel', NULL, 'Bautista', '09787680457', 'Brgy. Poblacion, San Benito, Surigao del Norte', 'male', DATE(NOW() - INTERVAL 17053 DAY), 'active', NOW() - INTERVAL 95880 MINUTE, NOW() - INTERVAL 299847 MINUTE);
INSERT INTO landlords (id, user_id, business_name, business_address, verification_status, verified_at, created_at) VALUES (5, 7, 'Bautista Rentals', 'Brgy. Poblacion 3, Dapa, Surigao del Norte', 'verified', NOW() - INTERVAL 296640 MINUTE, NOW() - INTERVAL 299520 MINUTE);
INSERT INTO users (id, username, email, password_hash, role, first_name, middle_name, last_name, contact_number, address, gender, birth_date, status, last_login_at, created_at) VALUES (8, 'jhon_reyes6', 'jhon.reyes6@gmail.com', '$2y$10$ifFDpLEemCtlqMim9g4ftOX0TvoGUvSrWAWwJ2xet2hajAHo27AxW', 'landlord', 'Jhon', NULL, 'Reyes', '09751362701', 'Brgy. Catangnan, General Luna, Surigao del Norte', 'male', DATE(NOW() - INTERVAL 17087 DAY), 'active', NOW() - INTERVAL 200280 MINUTE, NOW() - INTERVAL 293510 MINUTE);
INSERT INTO landlords (id, user_id, business_name, business_address, verification_status, verified_at, created_at) VALUES (6, 8, 'Reyes Rentals', 'Brgy. Union, Dapa, Surigao del Norte', 'verified', NOW() - INTERVAL 289440 MINUTE, NOW() - INTERVAL 292320 MINUTE);
INSERT INTO users (id, username, email, password_hash, role, first_name, middle_name, last_name, contact_number, address, gender, birth_date, status, last_login_at, created_at) VALUES (9, 'miguel_gonzales7', 'miguel.gonzales7@gmail.com', '$2y$10$ifFDpLEemCtlqMim9g4ftOX0TvoGUvSrWAWwJ2xet2hajAHo27AxW', 'landlord', 'Miguel', NULL, 'Gonzales', '09084520599', 'Brgy. Poblacion, Burgos, Surigao del Norte', 'female', DATE(NOW() - INTERVAL 19779 DAY), 'active', NOW() - INTERVAL 82980 MINUTE, NOW() - INTERVAL 195488 MINUTE);
INSERT INTO landlords (id, user_id, business_name, business_address, verification_status, verified_at, created_at) VALUES (7, 9, 'Gonzales Rentals', 'Brgy. Jubang, Dapa, Surigao del Norte', 'verified', NOW() - INTERVAL 191520 MINUTE, NOW() - INTERVAL 194400 MINUTE);
INSERT INTO users (id, username, email, password_hash, role, first_name, middle_name, last_name, contact_number, address, gender, birth_date, status, last_login_at, created_at) VALUES (10, 'ralph_villanueva8', 'ralph.villanueva8@gmail.com', '$2y$10$ifFDpLEemCtlqMim9g4ftOX0TvoGUvSrWAWwJ2xet2hajAHo27AxW', 'landlord', 'Ralph', NULL, 'Villanueva', '09229486628', 'Brgy. Poblacion, Pilar, Surigao del Norte', 'male', DATE(NOW() - INTERVAL 16067 DAY), 'active', NOW() - INTERVAL 39600 MINUTE, NOW() - INTERVAL 187323 MINUTE);
INSERT INTO landlords (id, user_id, business_name, business_address, verification_status, verified_at, created_at) VALUES (8, 10, 'Villanueva Rentals', 'Brgy. Cambas-ac, Dapa, Surigao del Norte', 'verified', NOW() - INTERVAL 184320 MINUTE, NOW() - INTERVAL 187200 MINUTE);
INSERT INTO users (id, username, email, password_hash, role, first_name, middle_name, last_name, contact_number, address, gender, birth_date, status, last_login_at, created_at) VALUES (11, 'renz_plaza9', 'renz.plaza9@gmail.com', '$2y$10$ifFDpLEemCtlqMim9g4ftOX0TvoGUvSrWAWwJ2xet2hajAHo27AxW', 'landlord', 'Renz', NULL, 'Plaza', '09875475177', 'Brgy. Poblacion 1, Dapa, Surigao del Norte', 'male', DATE(NOW() - INTERVAL 13406 DAY), 'pending', NULL, NOW() - INTERVAL 1827 MINUTE);
INSERT INTO landlords (id, user_id, business_name, business_address, verification_status, verified_at, created_at) VALUES (9, 11, 'Plaza Rentals', 'Brgy. Poblacion, Dapa, Surigao del Norte', 'pending', NULL, NOW() - INTERVAL 1440 MINUTE);
INSERT INTO users (id, username, email, password_hash, role, first_name, middle_name, last_name, contact_number, address, gender, birth_date, status, last_login_at, created_at) VALUES (12, 'shiela_salazar10', 'shiela.salazar10@gmail.com', '$2y$10$ifFDpLEemCtlqMim9g4ftOX0TvoGUvSrWAWwJ2xet2hajAHo27AxW', 'landlord', 'Shiela', NULL, 'Salazar', '09186032080', 'Brgy. Poblacion, San Isidro, Surigao del Norte', 'female', DATE(NOW() - INTERVAL 21631 DAY), 'pending', NULL, NOW() - INTERVAL 6705 MINUTE);
INSERT INTO landlords (id, user_id, business_name, business_address, verification_status, verified_at, created_at) VALUES (10, 12, 'Salazar Rentals', 'Brgy. Poblacion, Dapa, Surigao del Norte', 'pending', NULL, NOW() - INTERVAL 5760 MINUTE);

-- Tenants / students
INSERT INTO users (id, username, email, password_hash, role, first_name, middle_name, last_name, contact_number, address, gender, birth_date, status, last_login_at, created_at) VALUES (13, 'tenant1', 'tenant1@bhrental.local', '$2y$10$ifFDpLEemCtlqMim9g4ftOX0TvoGUvSrWAWwJ2xet2hajAHo27AxW', 'tenant', 'Maria', NULL, 'Mendoza', '09838222646', 'Brgy. Poblacion, San Isidro, Surigao del Norte', 'female', DATE(NOW() - INTERVAL 8800 DAY), 'active', NOW() - INTERVAL 141660 MINUTE, NOW() - INTERVAL 288322 MINUTE);

-- Notifications for administrators
INSERT INTO notifications (user_id, audience, type, title, message, link, is_read, created_at) VALUES (NULL, 'admin', 'landlord_registered', 'New landlord registered', 'Renz Plaza registered as a landlord and is waiting for verification.', '#/landlords', 0, NOW() - INTERVAL 35 MINUTE);
INSERT INTO notifications (user_id, audience, type, title, message, link, is_read, created_at) VALUES (NULL, 'admin', 'landlord_registered', 'New landlord registered', 'Shiela Salazar registered as a landlord.', '#/landlords', 0, NOW() - INTERVAL 3060 MINUTE);
INSERT INTO notifications (user_id, audience, type, title, message, link, is_read, created_at) VALUES (NULL, 'admin', 'user_registered', 'New user registered', 'Maria Mendoza created a tenant account.', '#/users?role=tenant', 0, NOW() - INTERVAL 120 MINUTE);
INSERT INTO notifications (user_id, audience, type, title, message, link, is_read, created_at) VALUES (NULL, 'admin', 'system', 'Listings were reset', 'Sample boarding houses were removed. Landlords can now add their real listings from the Landlord Dashboard.', '#/boarding-houses', 0, NOW() - INTERVAL 1 MINUTE);

-- Activity log
INSERT INTO activity_logs (user_id, action, description, entity_type, entity_id, ip_address, created_at) VALUES
  (1, 'login', 'Administrator logged in', 'user', 1, '127.0.0.1', NOW() - INTERVAL 90 MINUTE),
  (11, 'register', 'New landlord registered: Renz Plaza', 'user', 11, '127.0.0.1', NOW() - INTERVAL 1971 MINUTE),
  (12, 'register', 'New landlord registered: Shiela Salazar', 'user', 12, '127.0.0.1', NOW() - INTERVAL 6253 MINUTE),
  (10, 'register', 'New landlord registered: Ralph Villanueva', 'user', 10, '127.0.0.1', NOW() - INTERVAL 187533 MINUTE),
  (9, 'register', 'New landlord registered: Miguel Gonzales', 'user', 9, '127.0.0.1', NOW() - INTERVAL 194581 MINUTE),
  (5, 'register', 'New landlord registered: Rafael Espinosa', 'user', 5, '127.0.0.1', NOW() - INTERVAL 196349 MINUTE),
  (13, 'register', 'New tenant registered: Maria Mendoza', 'user', 13, '127.0.0.1', NOW() - INTERVAL 288178 MINUTE),
  (8, 'register', 'New landlord registered: Jhon Reyes', 'user', 8, '127.0.0.1', NOW() - INTERVAL 292798 MINUTE),
  (7, 'register', 'New landlord registered: Miguel Bautista', 'user', 7, '127.0.0.1', NOW() - INTERVAL 299814 MINUTE),
  (6, 'register', 'New landlord registered: Bea Domingo', 'user', 6, '127.0.0.1', NOW() - INTERVAL 320105 MINUTE),
  (4, 'register', 'New landlord registered: Faith Pascual', 'user', 4, '127.0.0.1', NOW() - INTERVAL 400795 MINUTE),
  (3, 'register', 'New landlord registered: Mark Cabrera', 'user', 3, '127.0.0.1', NOW() - INTERVAL 402029 MINUTE);

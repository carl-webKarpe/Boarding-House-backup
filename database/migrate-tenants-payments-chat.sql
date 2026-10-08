-- ============================================================================
-- Boarding House Rental System - UPGRADE: MY TENANTS, PAYMENTS AND CHAT
--
-- Run it ONCE in MySQL Workbench (open the file, then Ctrl+Shift+Enter)
-- if your database was created before these features. It only ADDS tables;
-- nothing is deleted. Running it twice is harmless.
-- ============================================================================
SET NAMES utf8mb4;
USE bhsystem;

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

SELECT 'Tenants, payments and chat are ready.' AS result;

CREATE DATABASE IF NOT EXISTS eventspot CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE eventspot;

CREATE TABLE users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL,
  email VARCHAR(254) NOT NULL,
  password_hash VARCHAR(255) NULL,
  full_name VARCHAR(120) NOT NULL DEFAULT '',
  auth_provider VARCHAR(20) NOT NULL DEFAULT 'email',
  provider_user_id VARCHAR(255) NULL,
  email_verified BOOLEAN NOT NULL DEFAULT FALSE,
  terms_accepted_at DATETIME NULL,
  role ENUM('user','admin','staff') NOT NULL DEFAULT 'user',
  status ENUM('active','suspended','deleted') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_users_username (username),
  UNIQUE KEY uq_users_email (email),
  UNIQUE KEY uq_users_provider_identity (auth_provider, provider_user_id),
  KEY idx_users_role_status (role, status)
) ENGINE=InnoDB;

CREATE TABLE user_auth_identities (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  provider ENUM('google','microsoft','x') NOT NULL,
  provider_user_id VARCHAR(255) NOT NULL,
  email VARCHAR(254) NOT NULL,
  email_verified BOOLEAN NOT NULL DEFAULT FALSE,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_auth_identity_provider_user (provider, provider_user_id),
  UNIQUE KEY uq_auth_identity_user_provider (user_id, provider),
  CONSTRAINT fk_auth_identity_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE payment_settings (
  id TINYINT UNSIGNED PRIMARY KEY,
  qr_image_path VARCHAR(255) NULL,
  upi_id VARCHAR(100) NULL,
  payee_name VARCHAR(100) NULL,
  updated_by BIGINT UNSIGNED NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT chk_payment_settings_singleton CHECK (id = 1),
  CONSTRAINT fk_payment_settings_admin FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT INTO payment_settings (id, qr_image_path, upi_id, payee_name) VALUES (1, NULL, 'pprajwaldeshpande-1@okicici', 'Prajwal Deshpande')
ON DUPLICATE KEY UPDATE upi_id = VALUES(upi_id), payee_name = VALUES(payee_name);

CREATE TABLE events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  created_by BIGINT UNSIGNED NOT NULL,
  title VARCHAR(180) NOT NULL,
  slug VARCHAR(200) NOT NULL,
  description TEXT NOT NULL,
  category VARCHAR(50) NOT NULL,
  venue VARCHAR(180) NOT NULL,
  venue_details VARCHAR(2000) NULL,
  latitude DECIMAL(10,7) NULL,
  longitude DECIMAL(10,7) NULL,
  starts_at DATETIME NOT NULL,
  ends_at DATETIME NULL,
  ticket_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  capacity INT UNSIGNED NOT NULL,
  reserved_quantity INT UNSIGNED NOT NULL DEFAULT 0,
  status ENUM('draft','published','cancelled','archived') NOT NULL DEFAULT 'draft',
  cancellation_cutoff_hours SMALLINT UNSIGNED NOT NULL DEFAULT 24,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_events_slug (slug),
  KEY idx_events_browse (status, starts_at, category),
  CONSTRAINT fk_events_creator FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE event_images (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  event_id BIGINT UNSIGNED NOT NULL,
  path VARCHAR(255) NOT NULL,
  mime_type VARCHAR(100) NOT NULL,
  size_bytes INT UNSIGNED NOT NULL,
  is_primary BOOLEAN NOT NULL DEFAULT FALSE,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_event_images_event (event_id, is_primary),
  CONSTRAINT fk_event_images_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE bookings (
  id CHAR(26) PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  event_id BIGINT UNSIGNED NOT NULL,
  quantity SMALLINT UNSIGNED NOT NULL,
  unit_price DECIMAL(10,2) NOT NULL,
  total_amount DECIMAL(10,2) NOT NULL,
  status ENUM('pending_payment','confirmed','failed','cancelled','refund_pending','refunded') NOT NULL DEFAULT 'pending_payment',
  idempotency_key CHAR(36) NOT NULL,
  expires_at DATETIME NULL,
  payment_reference VARCHAR(100) NULL,
  payment_submitted_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_bookings_idempotency (user_id, idempotency_key),
  UNIQUE KEY uq_bookings_payment_reference (payment_reference),
  KEY idx_bookings_user_created (user_id, created_at),
  KEY idx_bookings_event_status (event_id, status),
  CONSTRAINT chk_booking_quantity CHECK (quantity > 0),
  CONSTRAINT fk_bookings_user FOREIGN KEY (user_id) REFERENCES users(id),
  CONSTRAINT fk_bookings_event FOREIGN KEY (event_id) REFERENCES events(id)
) ENGINE=InnoDB;

CREATE TABLE payments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  booking_id CHAR(26) NOT NULL,
  provider ENUM('razorpay','free','upi') NOT NULL,
  provider_order_id VARCHAR(100) NULL,
  provider_payment_id VARCHAR(100) NULL,
  amount DECIMAL(10,2) NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'INR',
  status ENUM('created','authorized','captured','failed','refunded') NOT NULL DEFAULT 'created',
  provider_payload JSON NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_payments_order (provider_order_id),
  UNIQUE KEY uq_payments_payment (provider_payment_id),
  KEY idx_payments_booking (booking_id),
  CONSTRAINT fk_payments_booking FOREIGN KEY (booking_id) REFERENCES bookings(id)
) ENGINE=InnoDB;

CREATE TABLE tickets (
  id CHAR(26) PRIMARY KEY,
  booking_id CHAR(26) NOT NULL,
  validation_token CHAR(64) NOT NULL,
  status ENUM('active','checked_in','cancelled','refunded') NOT NULL DEFAULT 'active',
  checked_in_at DATETIME NULL,
  checked_in_by BIGINT UNSIGNED NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_tickets_token (validation_token),
  KEY idx_tickets_booking (booking_id),
  CONSTRAINT fk_tickets_booking FOREIGN KEY (booking_id) REFERENCES bookings(id),
  CONSTRAINT fk_tickets_checker FOREIGN KEY (checked_in_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE refunds (
  id CHAR(26) PRIMARY KEY,
  booking_id CHAR(26) NOT NULL,
  payment_id BIGINT UNSIGNED NOT NULL,
  requested_by BIGINT UNSIGNED NOT NULL,
  approved_by BIGINT UNSIGNED NULL,
  amount DECIMAL(10,2) NOT NULL,
  reason VARCHAR(500) NOT NULL,
  provider_refund_id VARCHAR(100) NULL,
  status ENUM('requested','approved','processing','processed','failed','rejected') NOT NULL DEFAULT 'requested',
  provider_payload JSON NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_refunds_provider (provider_refund_id),
  KEY idx_refunds_booking_status (booking_id, status),
  CONSTRAINT fk_refunds_booking FOREIGN KEY (booking_id) REFERENCES bookings(id),
  CONSTRAINT fk_refunds_payment FOREIGN KEY (payment_id) REFERENCES payments(id),
  CONSTRAINT fk_refunds_requester FOREIGN KEY (requested_by) REFERENCES users(id),
  CONSTRAINT fk_refunds_approver FOREIGN KEY (approved_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE reviews (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  event_id BIGINT UNSIGNED NOT NULL,
  rating TINYINT UNSIGNED NOT NULL,
  body VARCHAR(2000) NOT NULL,
  status ENUM('published','hidden','reported') NOT NULL DEFAULT 'published',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_reviews_user_event (user_id, event_id),
  KEY idx_reviews_event_status (event_id, status),
  CONSTRAINT chk_reviews_rating CHECK (rating BETWEEN 1 AND 5),
  CONSTRAINT fk_reviews_user FOREIGN KEY (user_id) REFERENCES users(id),
  CONSTRAINT fk_reviews_event FOREIGN KEY (event_id) REFERENCES events(id)
) ENGINE=InnoDB;

CREATE TABLE wishlists (
  user_id BIGINT UNSIGNED NOT NULL,
  event_id BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, event_id),
  KEY idx_wishlists_event (event_id),
  CONSTRAINT fk_wishlists_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_wishlists_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE notifications (
  id CHAR(26) PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  type VARCHAR(50) NOT NULL,
  message VARCHAR(1000) NOT NULL,
  payload JSON NULL,
  read_at DATETIME NULL,
  email_status ENUM('pending','sent','failed','skipped') NOT NULL DEFAULT 'pending',
  email_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  email_last_attempt_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_notifications_user_read (user_id, read_at, created_at),
  CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE audit_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  actor_id BIGINT UNSIGNED NULL,
  action VARCHAR(100) NOT NULL,
  target_type VARCHAR(80) NOT NULL,
  target_id VARCHAR(100) NULL,
  metadata JSON NULL,
  ip_address VARBINARY(16) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_audit_actor_created (actor_id, created_at),
  KEY idx_audit_target (target_type, target_id),
  CONSTRAINT fk_audit_actor FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE login_attempts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ip_address VARCHAR(45) NOT NULL,
  identifier VARCHAR(254) NOT NULL,
  successful BOOLEAN NOT NULL DEFAULT FALSE,
  attempted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_login_attempts_ip_time (ip_address, attempted_at)
) ENGINE=InnoDB;

CREATE TABLE password_reset_tokens (
  token_hash CHAR(64) PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  expires_at DATETIME NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_password_reset_user_expiry (user_id, expires_at),
  CONSTRAINT fk_password_reset_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE auth_action_attempts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  action VARCHAR(32) NOT NULL,
  ip_address VARCHAR(45) NOT NULL,
  identifier_hash CHAR(64) NOT NULL,
  attempted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_auth_action_limit (action, ip_address, attempted_at)
) ENGINE=InnoDB;
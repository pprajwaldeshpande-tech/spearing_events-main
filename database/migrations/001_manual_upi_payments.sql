CREATE TABLE payment_settings (
  id TINYINT UNSIGNED PRIMARY KEY,
  qr_image_path VARCHAR(255) NULL,
  updated_by BIGINT UNSIGNED NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT chk_payment_settings_singleton CHECK (id = 1),
  CONSTRAINT fk_payment_settings_admin FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT INTO payment_settings (id, qr_image_path) VALUES (1, NULL);

ALTER TABLE bookings
  ADD COLUMN payment_reference VARCHAR(100) NULL,
  ADD COLUMN payment_submitted_at DATETIME NULL,
  ADD UNIQUE KEY uq_bookings_payment_reference (payment_reference);

ALTER TABLE payments
  MODIFY provider ENUM('razorpay','free','upi') NOT NULL;

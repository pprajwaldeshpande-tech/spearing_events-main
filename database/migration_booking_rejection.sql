ALTER TABLE bookings
  ADD COLUMN rejection_reason VARCHAR(1000) NULL AFTER payment_submitted_at,
  ADD COLUMN rejected_at DATETIME NULL AFTER rejection_reason;

ALTER TABLE events
  ADD COLUMN cancellation_reason VARCHAR(1000) NULL AFTER status,
  ADD COLUMN cancelled_at DATETIME NULL AFTER cancellation_reason;

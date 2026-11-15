ALTER TABLE payments
  MODIFY COLUMN provider ENUM('razorpay','free','upi','demo_card') NOT NULL;

UPDATE payments
SET provider='demo_card'
WHERE provider='' AND status='authorized';

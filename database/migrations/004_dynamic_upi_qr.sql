ALTER TABLE payment_settings
  ADD COLUMN upi_id VARCHAR(100) NULL AFTER qr_image_path,
  ADD COLUMN payee_name VARCHAR(100) NULL AFTER upi_id;

UPDATE payment_settings
SET upi_id = 'pprajwaldeshpande-1@okicici', payee_name = 'Prajwal Deshpande'
WHERE id = 1 AND (upi_id IS NULL OR upi_id = '');

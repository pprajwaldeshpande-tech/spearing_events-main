-- Run this once after installing the existing project schema.
ALTER TABLE tickets
  MODIFY validation_token CHAR(64) NOT NULL,
  MODIFY status ENUM('active','checked_in','cancelled','refunded') NOT NULL DEFAULT 'active';

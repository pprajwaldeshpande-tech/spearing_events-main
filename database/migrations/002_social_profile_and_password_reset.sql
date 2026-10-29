ALTER TABLE users
  MODIFY password_hash VARCHAR(255) NULL,
  ADD COLUMN full_name VARCHAR(120) NOT NULL DEFAULT '' AFTER password_hash,
  ADD COLUMN auth_provider VARCHAR(20) NOT NULL DEFAULT 'email' AFTER full_name,
  ADD COLUMN provider_user_id VARCHAR(255) NULL AFTER auth_provider,
  ADD COLUMN email_verified BOOLEAN NOT NULL DEFAULT FALSE AFTER provider_user_id,
  ADD COLUMN terms_accepted_at DATETIME NULL AFTER email_verified,
  ADD UNIQUE KEY uq_users_provider_identity (auth_provider, provider_user_id);

UPDATE users SET full_name = username WHERE full_name = '';

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

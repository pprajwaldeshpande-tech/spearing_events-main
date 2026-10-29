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

INSERT INTO user_auth_identities (user_id,provider,provider_user_id,email,email_verified)
SELECT id,auth_provider,provider_user_id,email,email_verified
FROM users
WHERE auth_provider IN ('google','microsoft','x') AND provider_user_id IS NOT NULL;

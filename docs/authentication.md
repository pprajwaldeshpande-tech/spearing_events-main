# Spearing Events authentication setup

## Email accounts

Spearing Events account creation and login use an email address or username with a password. Google, Microsoft, and X sign-in and account linking are disabled. Passwords are stored only as hashes generated with PHP `password_hash(..., PASSWORD_DEFAULT)` and checked with `password_verify()`; plaintext passwords are never stored.

Registration requires a full name, unique username and email, a password of 10-72 characters containing at least one letter and number, and acceptance of the terms. Password reset uses a random, one-time token stored only as a SHA-256 hash. The token expires after one hour, and reset requests return a generic response whether or not the email is registered.

## Password-reset email

Configure these `.env` settings with working SMTP values to enable password-reset emails:

```dotenv
APP_URL=http://localhost/eventspot/public
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=your-smtp-username
MAIL_PASSWORD=your-smtp-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=no-reply@example.com
MAIL_FROM_NAME=Spearing Events
```

Use the externally reachable `public` URL for `APP_URL`. In production, use HTTPS and set `SESSION_SECURE=true`. Keep `.env` private and never commit mail credentials. If SMTP delivery fails, the reset token is removed and the failure is logged server-side.

## Existing installations

Apply unapplied database migrations in order; do not rerun migrations already applied. Migration `002_social_profile_and_password_reset.sql` contains columns and identity tables retained for compatibility with existing databases. These legacy fields do not enable social sign-in: the OAuth routes and account-linking UI are disabled.

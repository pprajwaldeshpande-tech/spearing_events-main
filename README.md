# Spearing Events PHP + MySQL

Spearing Events is a session-authenticated PHP 8.1+ and MySQL 8 event discovery and ticket system. The original frontend is retained under `public/`; its data calls now target the PHP API. This folder is intentionally independent of `C:\event` and contains no Git repository setup.

## Requirements

- XAMPP with Apache, MySQL, and PHP 8.1+
- Composer 2
- Admin-provided UPI payment QR for paid events
- SMTP account for email delivery configuration (optional)

## Windows/XAMPP installation

1. Copy this directory to a location Apache can serve, for example `C:\xampp\htdocs\eventspot`.
2. In a terminal at the project root, run `composer install --no-dev --optimize-autoloader`.
3. Copy `.env.example` to `.env`, then set database, SMTP, and `APP_URL` values. Do not commit `.env`.
4. Start Apache and MySQL from the XAMPP Control Panel. Create the database schema with `mysql -u root -p < database/schema.sql`.
   For an existing Spearing Events database, apply the unapplied migrations in order, including `database/migrations/001_manual_upi_payments.sql`, `database/migrations/002_social_profile_and_password_reset.sql`, `database/migrations/003_linkable_auth_identities.sql`, `database/migrations/004_dynamic_upi_qr.sql`, and `database/migrations/005_wishlist_venue_details.sql`. Do not rerun a migration that has already been applied.
5. Create the first administrator without placing a password in source: in PowerShell, set `$env:EVENTSPOT_ADMIN_PASSWORD = 'a-long-unique-password'`, then run `php private/create-admin.php eventadmin admin@example.test`.
6. Load sample events with `mysql -u root -p eventspot < database/seed.sql`.
7. Sign in as the administrator, open **Payment QR**, and upload the real UPI QR to use for all paid events.
8. Browse to `http://localhost/eventspot/public/`. Use the theme toggle to switch between saved light and dark appearances.

To update the password for an existing active administrator, set `$env:EVENTSPOT_ADMIN_PASSWORD` to a new 10-72 character password containing a letter and a number, then run `php private/reset-admin-password.php eventadmin`. This CLI-only helper stores a PHP password hash, never the plaintext password. Remove the environment variable afterward with `Remove-Item Env:EVENTSPOT_ADMIN_PASSWORD`.

Paid bookings remain pending after the customer submits a UPI transaction reference. An administrator must verify the payment in their payment provider before confirming the booking; tickets are issued only after confirmation. Never use a dummy QR or treat a customer-provided reference as proof of payment.

The separate Admin Console at `public/admin.html` supports creating and updating events, adding up to five event photos per upload, reviewing registrations, and archiving events. Configure the real merchant UPI ID under **Payment QR**; paid bookings receive a server-generated QR with the exact booking amount encoded. Event descriptions and photo galleries are shown on the public event detail page. User passwords are stored only as PHP password hashes and verified with `password_verify`; the application must not store plaintext passwords.

To add the 300 fictional Spearing Events demo listings to an existing database, first make sure an active administrator exists, then run `php private/seed-demo-events.php` from the project root. The command is CLI-only, transactional, and safe to re-run after a successful import. It creates draft events with Unsplash image URLs; they appear in the paginated Admin Console but remain hidden from customers until an administrator publishes them. The demo listings start in 2027 and must be reviewed and updated before publication.

The home page includes a Leaflet map using OpenStreetMap tiles and Nominatim place search. Location permission is requested only when the visitor chooses **Use my location**, unless permission was already granted in the browser. GPS coordinates are used in the current browser session and sent to OpenStreetMap for area labeling; Spearing Events does not save them in the database or share them with other users. No profile-location field is currently stored.

Email account creation requires a full name, unique username and email, a password of 10-72 characters containing a letter and a number, and acceptance of the terms checkbox; acceptance time is recorded. Password reset uses a random one-time token stored only as a SHA-256 hash and expires after one hour. Reset and password-change flows use the same password length and composition requirements. Configure working SMTP credentials and a correct public `APP_URL` in `.env` before relying on password-reset email. The reset-request screen intentionally uses a generic response to avoid revealing whether an address is registered.

Spearing Events uses email-and-password authentication. Google, Microsoft, and X sign-in and account linking are disabled; users create accounts and log in with their Spearing Events email or username and password. See [authentication setup](docs/authentication.md) for password and reset guidance.

Run `php private/deliver-notifications.php` from Windows Task Scheduler or a cron-equivalent every few minutes. It sends queued notification emails and retries transient failures up to five times without affecting payment or booking records.

Set Apache's document root to `.../eventspot/public` in production. Do not expose `app`, `database`, `private`, `storage/logs`, `.env`, or `vendor` over HTTP. Validated event and payment QR images are stored beneath `public/uploads/`; the uploads directory denies executable PHP-like files.

## Manual UPI payment flow

The administrator uploads the shared payment QR in the Admin Console. Customers scan it, pay the displayed event amount, and submit the transaction reference from their UPI receipt. Admins verify the transaction outside Spearing Events and then confirm or cancel the pending booking in **Registrations**. This manual flow does not automatically validate UPI transfers.

## Validation checklist

1. `php -l app/Api.php`, `php -l app/Auth.php`, `php -l app/Mailer.php`, `php -l app/bootstrap.php`, and `composer validate`.
2. Register, logout, login, and verify session rotation and failed-login throttling. Test password-reset email delivery with configured SMTP; confirm tokens expire and cannot be reused.
3. Confirm public event search/filter results; verify an unauthenticated admin request returns `401`.
4. Attempt simultaneous bookings on the last available seat and verify only permitted quantities succeed.
5. Complete a Razorpay test payment and verify that the signature/webhook creates tickets exactly once.
6. Scan the QR code, check in as staff/admin, then verify a second check-in fails.
7. Request, approve, reject, and reconcile refunds in Razorpay test mode.
8. Attempt executable/oversized uploads, forged CSRF requests, altered amounts, and repeated idempotency keys.

See [API documentation](docs/api.md) for endpoint contracts.
See [authentication setup](docs/authentication.md) for SMTP setup, provider callback URLs, credentials, scopes, and account-linking behavior.


## Reset an administrator password
## run this in terminal PS PS C:\xampp\htdocs\spearing_events> in this path
``` $secure = Read-Host "Enter a new admin password" -AsSecureString
$ptr = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secure)
try {
  $env:EVENTSPOT_ADMIN_PASSWORD = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($ptr)
  php private/reset-admin-password.php eventadmin
} finally {
  Remove-Item Env:EVENTSPOT_ADMIN_PASSWORD -ErrorAction SilentlyContinue
  [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($ptr)
  $secure.Dispose()
} ```

``` http://localhost/spearing_events/public ```
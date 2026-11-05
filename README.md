# Spearing Events

Spearing Events is a session-authenticated event discovery, booking, payment, ticketing, notification, review, wishlist, support, and administration platform. The customer-facing pages are implemented in HTML/CSS/JavaScript and communicate with a PHP REST-style API. PHP and MySQL persist users, events, bookings, payments, refunds, tickets, reviews, wishlists, notifications, support requests, and audit/security records.

## Table of contents

- [Project overview](#project-overview)
- [Technology stack](#technology-stack)
- [Key features](#key-features)
- [Application architecture](#application-architecture)
- [Request and business flows](#request-and-business-flows)
- [Repository structure](#repository-structure)
- [Local setup](#local-setup)
- [Configuration](#configuration)
- [Database setup and migrations](#database-setup-and-migrations)
- [Run and validation](#run-and-validation)
- [AWS deployment overview](#aws-deployment-overview)
- [Deploy to AWS using EC2, RDS, and S3](#deploy-to-aws-using-ec2-rds-and-s3)
- [Post-deployment configuration and operations](#post-deployment-configuration-and-operations)
- [AWS free-tier and cost notes](#aws-free-tier-and-cost-notes)
- [Troubleshooting](#troubleshooting)
- [Security notes](#security-notes)

## Project overview

### User roles

| Role | Primary capabilities |
|---|---|
| Guest | Browse published events and view event details |
| User | Register, sign in, manage profile and password, book events, pay, view tickets, wishlist events, submit reviews, request refunds, and contact support |
| Staff | Check in valid tickets and perform entry verification |
| Admin | Manage events, images, users, bookings, payment settings, refunds, support requests, reports, and entry passes |

### Payment modes

- **UPI/manual verification:** the administrator uploads a payment QR, the customer submits a transaction reference, and an administrator verifies it outside the application.
- **Razorpay:** the application creates an order, verifies signatures, processes payment webhooks, creates tickets, and supports refunds.

Manual UPI submission must not be treated as proof of payment. Free tickets and free bookings can also be processed through the application's booking flow.

## Technology stack

| Layer | Technology |
|---|---|
| Front end | HTML5, CSS3, modern JavaScript |
| Maps | Leaflet and OpenStreetMap/Nominatim |
| Backend | PHP 8.1+ |
| API style | REST-style JSON endpoints routed by `public/api/index.php` |
| Database | MySQL 8 or Amazon RDS for MySQL |
| PHP packages | Composer, vlucas/phpdotenv, PHPMailer, Endroid QR Code, Razorpay SDK |
| Local web/database server | XAMPP, Apache, MySQL |
| AWS application server | Amazon Linux 2023, EC2, Apache HTTP Server, PHP-FPM or Apache PHP module |
| AWS database | Amazon RDS for MySQL |
| AWS object storage | Amazon S3 |
| Deployment access | SSH and AWS Systems Manager Session Manager |
| Mail delivery | SMTP provider configured through environment variables |

## Key features

- Email-and-password authentication with CSRF protection, session rotation, login throttling, password reset tokens, and role/status enforcement.
- Event publishing, cancellation, archiving, categories, venue details, coordinates, capacity, ticket prices, and gallery images.
- Booking quantity validation, idempotency keys, seat checks, ticket generation, QR codes, check-in, and entry-pass management.
- Manual UPI and Razorpay payment flows.
- Refund requests, administrator approval/rejection, Razorpay refund processing, and audit records.
- Reviews, wishlists, notifications, support tickets, administrator reports, account suspension, and profile management.
- Payment configuration and QR image management.
- Responsive customer, dashboard, event, ticket, administrator, and authentication pages.

## Application architecture

```mermaid
flowchart LR
    Browser["Web browser"]

    subgraph Client["Browser client"]
        HTML["HTML pages"]
        CSS["CSS and responsive UI"]
        JS["JavaScript / api-client.js"]
    end

    subgraph EC2["EC2 Amazon Linux 2023"]
        Apache["Apache HTTP Server"]
        PHP["PHP application"]
        Bootstrap["bootstrap.php / Dotenv"]
        Api["Api.php dispatcher"]
        Auth["Auth.php"]
        Mailer["Mailer.php"]
        Files["Validated uploads and application logs"]
    end

    subgraph AWS["AWS managed services"]
        RDS[("RDS for MySQL")]
        S3[("Amazon S3")]
        SES["Optional SMTP/SES"]
    end

    Payment["Razorpay / UPI"]
    SMTP["SMTP provider"]

    Browser --> HTML
    HTML --> CSS
    HTML --> JS
    JS -->|"HTTP / HTTPS and JSON"| Apache
    Apache --> PHP
    PHP --> Bootstrap
    Bootstrap --> Api
    Api --> Auth
    Api --> RDS
    Mailer --> SMTP
    Mailer --> SES
    PHP --> Files
    PHP --> Payment
    Payment -->|"Webhook"| Apache
    PHP -. "Object storage integration" .-> S3
```

### Component responsibilities

- `public/`: HTML pages, CSS, browser JavaScript, front controller, and web-facing `.htaccess` rules.
- `public/api/index.php`: Apache entry point for API requests.
- `app/bootstrap.php`: Composer autoloading, environment loading, timezone, error logging, and PHP session initialization.
- `app/Api.php`: route dispatch, validation, payment flows, booking logic, administration operations, tickets, reviews, wishes, notifications, and support requests.
- `app/Auth.php`: sessions, password verification, roles, CSRF, login throttling, account status, and password reset.
- `app/Database.php`: PDO/MySQL connection management.
- `app/Mailer.php` and `app/Support.php`: email delivery and notifications.
- `database/schema.sql`: baseline MySQL schema.
- `database/migrations/`: incremental schema changes.
- `private/`: administrative CLI scripts and background notification delivery.

The current upload implementation writes validated image files beneath `public/uploads/` and records image paths in RDS. This is a single-EC2 deployment model. S3 integration requires updating upload and URL-resolution code; storing files only on EC2 is not durable across instance replacement or suitable for auto scaling without shared storage.

## Request and business flows

### Authentication flow

1. The browser loads a page and requests `GET /api/auth/me`.
2. The API retrieves or initializes the PHP session.
3. Registration validates account details, password rules, and terms acceptance.
4. Login verifies the password, updates security state, and rotates the session.
5. The API returns the current user and CSRF token.
6. State-changing requests include the CSRF token in the `X-CSRF-Token` header.
7. Logout invalidates the session.

### Booking and ticket flow

1. A user opens a published event.
2. The browser sends a booking request with an idempotency key.
3. The server checks authentication, quantity, availability, event state, and transaction rules.
4. The booking and payment record are created in MySQL.
5. For Razorpay, the server creates an order; for UPI, the user submits the provider transaction reference.
6. Payment verification updates payment/booking state.
7. Confirmation creates one tokenized ticket per booked quantity.
8. The user opens the ticket page and staff scan/check in the QR or token.
9. A second check-in is rejected.

### Administrator flow

1. An administrator signs in.
2. The admin page lists or edits events and users.
3. Event changes and booking decisions are authorized through the server-side admin role check.
4. Payment verification, cancellations, publishing, and refunds create audit records and notifications.

## Repository structure

```text
app/                 PHP application classes and API
database/            MySQL schema, seed data, and migrations
docs/                API and authentication documentation
private/             Protected CLI and notification scripts
public/              Static frontend and public API front controller
public/uploads/      Current local validated image uploads
storage/logs/        PHP application logs
storage/uploads/     Reserved private runtime uploads
.env.example         Documented non-secret configuration template
composer.json        PHP dependency and autoload configuration
```

## Local setup

### Prerequisites

- Windows with XAMPP, or Linux/macOS with Apache, PHP, and MySQL
- PHP 8.1 or newer
- PHP extensions: `pdo_mysql`, `mbstring`, and commonly required JSON/openssl support
- Composer 2
- MySQL 8
- Git

### Windows and XAMPP

1. Clone or copy the project under XAMPP:

   ```powershell
   git clone https://github.com/pprajwaldeshpande-tech/spearing_events-main.git C:\xampp\htdocs\spearing_events
   cd C:\xampp\htdocs\spearing_events
   ```

2. Start **Apache** and **MySQL** from the XAMPP Control Panel.

3. Install PHP dependencies:

   ```powershell
   composer install
   ```

4. Create an application database and user in phpMyAdmin, or from the MySQL command line:

   ```sql
   CREATE DATABASE eventspot CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'eventspot_app'@'localhost' IDENTIFIED BY 'replace_with_a_long_password';
   GRANT SELECT, INSERT, UPDATE, DELETE ON eventspot.* TO 'eventspot_app'@'localhost';
   FLUSH PRIVILEGES;
   ```

5. Copy `.env.example` to `.env` and update the database and application values.

6. Import the schema:

   ```powershell
   mysql -u root -p eventspot < database/schema.sql
   ```

7. Apply numbered migrations if upgrading an existing database, in numeric order. Do not rerun migrations already applied.

8. Create the first administrator:

   ```powershell
   $env:EVENTSPOT_ADMIN_PASSWORD = Read-Host "Enter admin password"
   php private/create-admin.php admin admin@example.com
   Remove-Item Env:EVENTSPOT_ADMIN_PASSWORD
   ```

9. Enable Apache rewrite support:

   - Allow `.htaccess` overrides in Apache configuration, normally with `AllowOverride All`.
   - Ensure `mod_rewrite` is enabled.

10. Open:

    ```text
    http://localhost/spearing_events/public/
    ```

### Linux/macOS

1. Install Apache, PHP, MySQL, and Composer.
2. Clone the repository and run `composer install`.
3. Copy `.env.example` to `.env`.
4. Create and import the MySQL database:

   ```bash
   mysql -u root -p -e "CREATE DATABASE eventspot CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   mysql -u root -p eventspot < database/schema.sql
   ```

5. Set the Apache document root to the repository's `public/` directory.
6. Confirm the web server user can write to `storage/logs` and `public/uploads`.

## Configuration

Copy `.env.example`; never commit `.env`.

| Variable | Purpose |
|---|---|
| `APP_ENV` | Application environment, usually `development` or `production` |
| `APP_DEBUG` | Debug display flag; keep `false` in production |
| `APP_URL` | Canonical local/application URL |
| `PUBLIC_APP_URL` | Public HTTPS URL for generated links |
| `APP_TIMEZONE` | PHP timezone, default `Asia/Kolkata` |
| `SESSION_NAME` | PHP session cookie name |
| `SESSION_SECURE` | Set `true` when the complete site uses HTTPS |
| `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | Database connection |
| `RAZORPAY_*` | Razorpay keys and webhook secret |
| `MAIL_*` | SMTP host, credentials, encryption, and sender identity |

Use a strong database password and a different password for every environment. Store production secrets outside source control. A production EC2 `.env` file should have permissions `600` or stricter.

## Database setup and migrations

Import this baseline schema:

```bash
mysql -h <rds-endpoint> -u <admin-user> -p eventspot < database/schema.sql
```

Apply migrations in ascending filename order. Current migrations cover:

- Manual UPI payments
- Social profile fields and password reset
- Linkable authentication identities
- Dynamic UPI QR configuration
- Wishlist and venue details

Additional standalone migration scripts are also provided in `database/`. Review them and use only for existing databases requiring the corresponding feature.

## Run and validation

### Local validation commands

```powershell
composer validate
php -l app/Api.php
php -l app/Auth.php
php -l app/Mailer.php
php -l app/bootstrap.php
```

Functional checks:

1. Register a user and verify password and email validation.
2. Log in, log out, and verify that session rotation works.
3. Request and complete a password reset.
4. Verify unauthenticated and unauthorized administrator API requests are rejected.
5. Publish an event and search/filter it.
6. Attempt booking with the last available seat.
7. Submit a manual UPI reference and verify that only an administrator can confirm it.
8. Test Razorpay with test credentials and test webhook signing.
9. Generate and scan a ticket; confirm a second check-in fails.
10. Test image size/type restrictions and executable-upload rejection.

## AWS deployment overview

```mermaid
flowchart LR
    User["Public users"]
    Internet["Internet"]
    EC2["EC2\nAmazon Linux 2023\nApache + PHP\nSpearing Events"]
    RDS[("RDS MySQL\nPrivate subnet")]
    S3[("S3\nBackups / future media")]
    SMTP["Email provider"]

    User -->|"HTTP/HTTPS port 80/443"| Internet
    Internet --> EC2
    EC2 -->|"MySQL port 3306"| RDS
    EC2 -. "AWS CLI / application integration" .-> S3
    EC2 --> SMTP
```

### AWS resources

| Resource | Purpose |
|---|---|
| EC2 | Hosts Apache/PHP and the application frontend/API |
| RDS for MySQL | Stores transactional application data |
| S3 | Stores database backups and is the object-storage target for uploaded media |
| Security groups | Restrict EC2 public traffic and database access |
| IAM role | Recommended identity for application or backup access to S3 |
| Elastic IP | Optional stable public address for DNS while an EC2 instance remains associated |

## Deploy to AWS using EC2, RDS, and S3

AWS Console labels and free-tier eligibility can change. Confirm the current offer under **AWS Billing > Free Tier** and avoid production data until eligibility, region, instance class, storage, data transfer, and RDS backup settings are understood.

### Step 1: Create an S3 bucket

1. Open **S3 > Buckets > Create bucket**.
2. Choose a unique globally unique name such as `spearing-events-prod-<account-id>`.
3. Keep **Block all public access** enabled.
4. Enable default encryption.
5. Enable bucket versioning if database backups are stored there.
6. Create an optional lifecycle rule to delete old backups after 30-90 days, subject to retention needs.
7. Do not make uploaded event images publicly accessible unless the application and bucket policy are designed for public delivery through CloudFront or pre-signed URLs.

### Step 2: Create an RDS security group

1. Open **EC2 > Security Groups > Create security group**.
2. Name it `spearing-events-rds-sg`.
3. Add inbound rule:

   | Type | Protocol | Port | Source |
   |---|---|---:|---|
   | MySQL/Aurora | TCP | 3306 | EC2 security group selected in the next steps |

4. Leave 3306 open to no public CIDR block.
5. If the RDS console requires a database management step from outside AWS, use a temporary EC2-connected SSH tunnel or a temporary, tightly time-limited rule rather than `0.0.0.0/0`.

### Step 3: Create RDS for MySQL

1. Open **RDS > Create database**.
2. Choose **MySQL**, not Aurora.
3. For free-tier-compatible evaluation, select **Free tier** if the current console shows it.
4. Choose a supported MySQL engine version appropriate for the AWS account.
5. Create a database identifier such as `spearing-events-db`.
6. Create a strong master username and password; save them in AWS Secrets Manager or a password manager.
7. Use a small burstable/free-tier-compatible instance class if offered, such as `db.t3.micro` or the current free-tier eligible equivalent.
8. Allocate at least 20 GiB and enable storage autoscaling only after confirming its cost limit.
9. Place the DB in a VPC and use the private database security group created earlier.
10. Disable public access.
11. For an evaluation deployment, disable deletion protection and Multi-AZ; do not use those settings as a production availability shortcut without understanding cost and backup requirements.
12. Set backup retention to a safe period within free-tier eligibility, often 1-7 days for a test environment.
13. Create the database and record its **Connectivity & security > Endpoint**.

### Step 4: Create the EC2 security group

1. Create `spearing-events-web-sg`.
2. Add inbound rules:

   | Type | Protocol | Port | Source |
   |---|---|---:|---|
   | SSH | TCP | 22 | Your public IP only, or Session Manager |
   | HTTP | TCP | 80 | 0.0.0.0/0 |
   | HTTPS | TCP | 443 | 0.0.0.0/0 |

3. Do not expose SSH to `0.0.0.0/0`.

### Step 5: Create an IAM role for S3 access

1. Open **IAM > Roles > Create role > AWS service > EC2**.
2. Create a least-privilege policy scoped to the project bucket and required operations:

   ```json
   {
     "Version": "2012-10-17",
     "Statement": [
       {
         "Sid": "SpearingEventsObjectAccess",
         "Effect": "Allow",
         "Action": [
           "s3:PutObject",
           "s3:GetObject",
           "s3:DeleteObject",
           "s3:ListBucket"
         ],
         "Resource": [
           "arn:aws:s3:::YOUR-BUCKET-NAME",
           "arn:aws:s3:::YOUR-BUCKET-NAME/*"
         ]
       }
     ]
   }
   ```

3. Name the role `spearing-events-ec2-role`.
4. Do not store AWS access keys in `.env` when an EC2 instance role can provide credentials.

### Step 6: Launch an EC2 instance using Amazon Linux

1. Open **EC2 > Instances > Launch instances**.
2. Name the instance `spearing-events`.
3. Choose **Amazon Linux 2023 AMI**, published by Amazon.
4. Select a free-tier-compatible architecture and instance type offered to the account, normally `t2.micro`/`t3.micro` or the current equivalent.
5. Create or select a key pair only if SSH will be used.
6. Under network settings, select the project VPC and subnet.
7. Enable auto-assign public IPv4.
8. Attach `spearing-events-web-sg`.
9. Attach `spearing-events-ec2-role` under IAM instance profile/advanced details.
10. Allocate at least 20-30 GiB GP3 root storage, staying within the free tier if applicable.
11. Optionally add a user-data script to install Apache/PHP after launch.
12. Launch the instance.

### Step 7: Open SSH access safely

Connect with the key pair:

```bash
chmod 400 C:/path/to/spearing-events.pem
ssh -i C:/path/to/spearing-events.pem ec2-user@EC2-PUBLIC-IP
```

Session Manager can be used instead:

1. Attach the AWS managed `AmazonSSMManagedInstanceCore` policy to the EC2 role if Session Manager is desired.
2. Open **EC2 > Instances > Connect > Session Manager**.

### Step 8: Update Amazon Linux and install the application stack

On Amazon Linux 2023:

```bash
sudo dnf update -y
sudo dnf install -y httpd php php-cli php-common php-mysqlnd php-mbstring php-pdo unzip git mariadb105
php -v
httpd -v
```

If PHP packages map to PHP 8.3 or a newer supported Amazon Linux release, confirm `php -v` is PHP 8.1 or newer and enable required extensions:

```bash
php -m | grep -E "PDO|pdo_mysql|mbstring"
```

Enable Apache:

```bash
sudo systemctl enable --now httpd
sudo systemctl status httpd --no-pager
```

Install Composer:

```bash
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php composer-setup.php --install-dir=/usr/local/bin --filename=composer
php -r "unlink('composer-setup.php');"
composer --version
```

### Step 9: Deploy application files

1. Remove any placeholder Apache content if applicable.
2. Clone with a deploy key or deploy from a release archive.
3. Keep application code outside Apache's public root:

```bash
sudo mkdir -p /var/www/spearing-events
sudo chown -R ec2-user:ec2-user /var/www/spearing-events
cd /var/www/spearing-events
git clone https://github.com/pprajwaldeshpande-tech/spearing_events-main.git .
composer install --no-dev --optimize-autoloader
mkdir -p storage/logs storage/uploads public/uploads/payment-qr public/uploads/events
touch storage/logs/.gitkeep storage/uploads/.gitkeep
sudo chown -R ec2-user:apache storage public/uploads
sudo find storage public/uploads -type d -exec chmod 2775 {} \;
sudo find storage public/uploads -type f -exec chmod 664 {} \;
```

If the repository has not been published or the instance cannot access GitHub, upload a release ZIP through S3 and download it using the instance role.

### Step 10: Configure Apache on Amazon Linux

Create `/etc/httpd/conf.d/spearing-events.conf` using `sudo vi`:

```apache
<VirtualHost *:80>
    ServerName YOUR-DOMAIN-OR-EC2-PUBLIC-IP

    DocumentRoot "/var/www/spearing-events/public"

    <Directory "/var/www/spearing-events/public">
        AllowOverride All
        Require all granted
    </Directory>

    <Directory "/var/www/spearing-events">
        Require all denied
    </Directory>

    <Directory "/var/www/spearing-events/public">
        Options -Indexes +FollowSymLinks
    </Directory>

    ErrorLog /var/log/httpd/spearing-events-error.log
    CustomLog /var/log/httpd/spearing-events-access.log combined
</VirtualHost>
```

Allow Apache URL rewriting:

```bash
sudo systemctl restart httpd
sudo httpd -t
sudo systemctl status httpd --no-pager
```

Check the public directory through a browser at:

```text
http://EC2-PUBLIC-IP/
```

### Step 11: Link RDS and EC2

Return to the RDS console:

1. Select the RDS database and note the endpoint and port.
2. Edit its database security group.
3. Ensure MySQL port 3306 has `spearing-events-web-sg` as its source.
4. Edit the EC2 security group's outbound rules only if they were deliberately restricted; default allow-all TCP is required for ordinary HTTPS, SMTP, and database connectivity.

Create RDS application credentials from a MySQL client on EC2:

```bash
mysql -h YOUR-RDS-ENDPOINT -P 3306 -u admin -p
```

```sql
CREATE DATABASE IF NOT EXISTS eventspot
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'eventspot_app'@'%'
  IDENTIFIED BY 'replace_with_a_long_unique_password';
GRANT SELECT, INSERT, UPDATE, DELETE ON eventspot.*
  TO 'eventspot_app'@'%';
FLUSH PRIVILEGES;
EXIT;
```

Import the schema from EC2. If the application files are in `/var/www/spearing-events`:

```bash
cd /var/www/spearing-events
mysql -h YOUR-RDS-ENDPOINT -P 3306 -u admin -p eventspot < database/schema.sql
```

### Step 12: Configure production environment variables

```bash
cd /var/www/spearing-events
cp .env.example .env
sudo chmod 600 .env
sudo chown ec2-user:apache .env
sudo vi .env
```

Use production values:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://events.example.com
PUBLIC_APP_URL=https://events.example.com
APP_TIMEZONE=Asia/Kolkata
SESSION_NAME=spearing_events_session
SESSION_SECURE=true

DB_HOST=YOUR-RDS-ENDPOINT
DB_PORT=3306
DB_DATABASE=eventspot
DB_USERNAME=eventspot_app
DB_PASSWORD=YOUR-APPLICATION-DATABASE-PASSWORD

RAZORPAY_KEY_ID=rzp_live_replace_me
RAZORPAY_KEY_SECRET=replace_me
RAZORPAY_WEBHOOK_SECRET=replace_me

MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=replace_me
MAIL_PASSWORD=replace_me
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=no-reply@events.example.com
MAIL_FROM_NAME="Spearing Events"
```

Do not enable live Razorpay until the HTTPS domain, webhook URL, and signature verification have been tested.

### Step 13: Create the first production administrator

```bash
cd /var/www/spearing-events
export EVENTSPOT_ADMIN_PASSWORD='replace_with_a_strong_unique_password'
php private/create-admin.php admin admin@example.com
unset EVENTSPOT_ADMIN_PASSWORD
```

### Step 14: Configure S3 backups

Install the AWS CLI:

```bash
sudo dnf install -y awscli2
aws sts get-caller-identity
aws s3 ls s3://YOUR-BUCKET-NAME
```

Create a compressed database backup on EC2:

```bash
mysqldump -h YOUR-RDS-ENDPOINT -u eventspot_app -p \
  --single-transaction --routines --triggers eventspot \
  | gzip \
  | aws s3 cp - s3://YOUR-BUCKET-NAME/backups/eventspot-$(date +%F-%H%M%S).sql.gz
```

Schedule this with `crontab -e` for an appropriate low-cost schedule. Avoid putting the database password on the command line; use `MYSQL_PWD` only in a root-readable script or `~/.my.cnf`.

### Step 15: S3 media integration status

The current application writes uploaded images to `public/uploads/` and therefore does **not** yet use S3 for media. Before production auto scaling or instance replacement:

- Add AWS SDK for PHP with Composer.
- Use the EC2 IAM role for S3 credentials.
- Upload payment QR and event images to private or CloudFront-backed bucket prefixes.
- Store the resulting object key or signed/CDN URL in RDS.
- Update image-serving and QR endpoints to return accessible URLs.
- Add content validation, size limits, malware scanning where appropriate, and lifecycle rules.
- Preserve `.htaccess` executable-file protection locally until S3 URLs replace local files.

For a single EC2 server, the current local upload behavior can be used temporarily, but its files and instance-root disks must be included in backup and replacement procedures.

### Step 16: Domain and HTTPS

1. Register or use a domain hosted in Route 53, another DNS provider, or an AWS-free account if available.
2. Add an A record pointing `events.example.com` to the Elastic IP associated with the EC2 instance.
3. Associate an Elastic IP with EC2.
4. For AWS-managed certificates and automatic renewal, place an Application Load Balancer and ACM certificate in front of EC2 and configure HTTP to HTTPS redirection.
5. A free-tier-friendly alternative is install Let's Encrypt/Certbot on Amazon Linux 2023 and use HTTP-01 validation with port 80 temporarily reachable.

Set Apache virtual-host HTTPS correctly before setting `SESSION_SECURE=true`.

If an Elastic IP is not used, DNS must be updated when the EC2 public address changes.

### Step 17: Configure email and webhook delivery

1. Configure SMTP values in `.env`.
2. Test account creation and password reset.
3. If using SES, verify the sender/domain, request production access if required, and give EC2 SMTP credentials only through the selected secret mechanism.
4. Configure Razorpay webhooks to:

   ```text
   https://events.example.com/api/payments/webhook
   ```

5. Save the exact webhook secret in `RAZORPAY_WEBHOOK_SECRET`.

### Step 18: Schedule notification delivery

Run notification delivery every few minutes:

```bash
crontab -e
```

```cron
*/5 * * * * cd /var/www/spearing-events && /usr/bin/php private/deliver-notifications.php >> /var/www/spearing-events/storage/logs/notifications.log 2>&1
```

Ensure the cron user can access the application `.env` and write to `storage/logs`.

## Post-deployment configuration and operations

### Health checks

```bash
curl -I http://localhost/
curl https://events.example.com/api/auth/me
sudo apachectl -t
sudo systemctl status httpd --no-pager
php -l /var/www/spearing-events/app/Api.php
```

### Log locations

- Apache errors: `/var/log/httpd/spearing-events-error.log`
- Apache access: `/var/log/httpd/spearing-events-access.log`
- PHP application log: `/var/www/spearing-events/storage/logs/php-error.log`
- Notification output: configured in cron

### Application update

```bash
cd /var/www/spearing-events
git pull --ff-only
composer install --no-dev --optimize-autoloader --no-interaction
sudo systemctl reload httpd
```

Back up the database and production media before pulling or applying migrations. Run migrations one at a time and verify output.

### RDS backup restore test

Test restore regularly in a separate database:

```bash
mysql -h YOUR-RDS-ENDPOINT -u admin -p eventspot_test < backup.sql
```

AWS-managed automated snapshots should be used with explicit restore and recovery-point testing.

## AWS free-tier and cost notes

- Amazon EC2 free-tier eligibility depends on region, account age, instance family, operating system, and current AWS offer. Confirm from the AWS Billing dashboard before launch.
- RDS free-tier storage, instance hours, backup storage, and internet transfer are commonly limited separately. RDS public endpoints, Multi-AZ, and extra storage can incur charges.
- S3 has separate storage, request, retrieval, and data-transfer allowances. A private backup bucket can still incur request and storage charges outside its allowance.
- Elastic IPs are normally charged when not attached to a running instance or when additional addresses are allocated.
- Data transfer out, NAT gateways, load balancers, CloudFront, Route 53 hosted zones, and backup exports may not be free.
- Use AWS Budgets with an email threshold and enable billing alerts.
- Stop or terminate unused evaluation resources rather than leaving them running.
- Free-tier eligibility does not replace encryption, least privilege, backups, patching, or HTTPS.

## Troubleshooting

### `500 SERVER_ERROR`

Inspect `/var/log/httpd/spearing-events-error.log` and `storage/logs/php-error.log`. Confirm the PHP extension list and `.env` permissions.

### Database connection failure

- Confirm the RDS endpoint and port.
- Confirm port 3306 is allowed from the EC2 security group.
- Confirm public RDS access is disabled.
- Confirm `DB_USERNAME` and `DB_PASSWORD`.
- Run MySQL connectivity from the EC2 instance.

### `403` or `404` on API requests

- Confirm Apache has `AllowOverride All`.
- Confirm `mod_rewrite` or the Amazon Linux Apache rewrite module is loaded.
- Confirm the VirtualHost points to `public/`, not the repository root.
- Inspect `/public/api/.htaccess`.

### Upload failure

- Confirm directory ownership by Apache.
- Confirm the EC2 disk has space.
- Check `public/uploads/payment-qr`, `public/uploads/events`, and `storage/uploads`.
- Confirm `upload_max_filesize` and `post_max_size` in PHP configuration.

### Sessions fail or users are logged out

- Set a domain-specific canonical `APP_URL` and `PUBLIC_APP_URL`.
- Set `SESSION_SECURE=true` only with HTTPS fully functioning.
- Avoid mixing `www` and non-`www` hostnames.
- Confirm PHP session storage permissions.

## Security notes

- Keep `.env`, `app/`, `database/`, `private/`, `vendor/`, and `storage/` outside the public Apache document root.
- Use HTTPS for production sessions and payment flows.
- Never commit credentials, AWS keys, SMTP passwords, Razorpay secrets, or private certificates.
- Prefer IAM roles and AWS Secrets Manager for production credentials.
- Keep RDS private and reachable only through application security-group membership.
- Keep S3 public access blocked except where an explicitly reviewed public-media architecture is implemented.
- Keep Apache, PHP, Composer dependencies, RDS, and OS packages patched.
- Treat email, payment, administrator, and support actions as audited operations.
- Validate all client input and uploaded images server-side.

## Documentation

- API reference: [docs/api.md](docs/api.md)
- Authentication and SMTP configuration: [docs/authentication.md](docs/authentication.md)

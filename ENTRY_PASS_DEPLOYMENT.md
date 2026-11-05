# Entry Pass Deployment Guide

## XAMPP setup

1. Place the project in `C:\xaampp\htdocs\spearing_events-main`.
2. Start Apache and MySQL from the XAMPP Control Panel.
3. Create or select the project database in phpMyAdmin.
4. Import `database/schema.sql`, then import `database/migration_entry_passes.sql`.
5. Copy `.env.example` to `.env` if needed and configure database credentials.
6. For local testing, set `APP_URL=http://localhost/spearing_events-main/public`. QR codes automatically use the laptop LAN address only when the phone can reach the laptop network.
   For internet/mobile testing from XAMPP, start HTTPS tunnel such as Cloudflare Tunnel or ngrok, then set `PUBLIC_APP_URL=https://your-tunnel-host/spearing_events-main/public`.
7. Open `http://localhost/spearing_events-main/public/login.html`.

Do not share a QR code while it contains a private address if the phone will not be on the same network.

## Android testing without cloud hosting

1. Connect the Windows laptop and Android phone to the same router network.
2. Allow Apache through Windows Firewall.
3. Confirm `http://<laptop-LAN-IP>/spearing_events-main/public/user.html` loads on the phone.
4. Create a confirmed booking and open its pass in My Bookings.
5. Scan the QR code with the phone camera.
6. The address must be an `http://<laptop-IP>/.../ticket.php?token=...` address, not `localhost`.

LAN testing cannot work independently of the Wi-Fi network. For internet access, deploy to public PHP hosting.

## Public hosting

1. Buy a domain and PHP/MySQL hosting with HTTPS enabled.
2. Upload all project folders, keeping `app/`, `config/`, and `database/` above the public web root where possible. Otherwise ensure `.env` is protected.
3. Create a MySQL database and import `database/schema.sql` and `database/migration_entry_passes.sql`.
4. Set `.env`:

   APP_URL=https://your-domain.example/spearing_events-main/public
   PUBLIC_APP_URL=https://your-domain.example/spearing_events-main/public

   Replace the path if the project public folder is the hosting document root.

5. Configure `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, and `DB_PASSWORD`.
6. Enable HTTPS and test `https://your-domain.example/spearing_events-main/public/login.html`.
7. Confirm a newly generated booking QR contains the HTTPS domain. QR codes generated before changing `APP_URL` must be regenerated.

## Security and verification

- Tokens are 64-character cryptographically random values.
- Invalid, cancelled, and already checked-in passes have distinct public states.
- Check-in uses an atomic database update, so concurrent duplicate scans result in only one successful check-in.

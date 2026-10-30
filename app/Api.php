<?php
declare(strict_types=1);

namespace EventSpot;

use Razorpay\Api\Api as Razorpay;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\SvgWriter;
use PDO;

final class Api
{
    public static function dispatch(): never
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ($method === 'OPTIONS') { header('Access-Control-Allow-Origin: ' . Config::get('APP_URL', '')); header('Access-Control-Allow-Credentials: true'); header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token, Idempotency-Key, X-Razorpay-Signature'); http_response_code(204); exit; }
        $path = '/' . trim((string) ($_GET['path'] ?? self::path()), '/');
        if ($path === '/auth/me' && $method === 'GET') self::me();
        if ($path === '/auth/register' && $method === 'POST') self::register();
        if ($path === '/auth/login' && $method === 'POST') self::login();
        if ($path === '/auth/forgot-password' && $method === 'POST') self::forgotPassword();
        if ($path === '/auth/reset-password' && $method === 'POST') self::resetPassword();
        if ($path === '/auth/logout' && $method === 'POST') { Auth::verifyCsrf(); Auth::logout(); Http::json(); }
        if ($path === '/auth/profile' && $method === 'PATCH') self::profile();
        if ($path === '/auth/password' && $method === 'PATCH') self::password();
        if ($path === '/payment-qr' && $method === 'GET') self::paymentQr();
        if ($path === '/payments/upi-qr' && $method === 'GET') self::dynamicUpiQr();
        if ($path === '/events' && $method === 'GET') self::events();
        if (preg_match('#^/events/(\d+)$#', $path, $m) && $method === 'GET') self::event((int) $m[1]);
        if ($path === '/bookings' && $method === 'POST') self::createBooking();
        if ($path === '/me/bookings' && $method === 'GET') self::myBookings();
        if (preg_match('#^/bookings/([A-F0-9]{26})$#', $path, $m) && $method === 'GET') self::booking($m[1]);
        if ($path === '/payments/orders' && $method === 'POST') self::paymentOrder();
        if ($path === '/payments/upi-submissions' && $method === 'POST') self::submitUpiPayment();
        if ($path === '/payments/verify' && $method === 'POST') self::verifyPayment();
        if ($path === '/payments/webhook' && $method === 'POST') self::webhook();
        if (preg_match('#^/tickets/([a-f0-9]{64})/qr$#i', $path, $m) && $method === 'GET') self::ticketQr($m[1]);
        if (preg_match('#^/tickets/([a-f0-9]{64})$#i', $path, $m) && $method === 'GET') self::ticket($m[1]);
        if (preg_match('#^/tickets/([a-f0-9]{64})/check-in$#i', $path, $m) && $method === 'POST') self::checkIn($m[1]);
        if ($path === '/refunds' && $method === 'POST') self::refundRequest();
        if ($path === '/reviews' && $method === 'POST') self::review();
        if ($path === '/reviews' && $method === 'GET') self::reviews();
        if ($path === '/wishlists' && $method === 'GET') self::wishlists();
        if ($path === '/wishlists' && $method === 'POST') self::addWishlist();
        if (preg_match('#^/wishlists/(\\d+)$#', $path, $m) && $method === 'DELETE') self::removeWishlist((int) $m[1]);
        if ($path === '/notifications' && $method === 'GET') self::notifications();
        if ($path === '/notifications/read-all' && $method === 'POST') self::readNotifications();
        if ($path === '/admin/events' && $method === 'GET') self::adminEvents();
        if ($path === '/admin/payment-qr' && $method === 'POST') self::uploadPaymentQr();
        if ($path === '/admin/payment-settings' && $method === 'POST') self::savePaymentSettings();
        if ($path === '/admin/events' && $method === 'POST') self::saveEvent();
        if (preg_match('#^/admin/events/(\d+)$#', $path, $m) && $method === 'PUT') self::saveEvent((int) $m[1]);
        if (preg_match('#^/admin/events/(\d+)/(publish|cancel|archive)$#', $path, $m) && $method === 'POST') self::eventStatus((int) $m[1], $m[2]);
        if (preg_match('#^/admin/events/(\d+)/image$#', $path, $m) && $method === 'POST') self::uploadImage((int) $m[1]);
        if ($path === '/admin/users' && $method === 'GET') self::adminUsers();
        if (preg_match('#^/admin/users/(\\d+)/(suspend|activate|delete)$#', $path, $m) && $method === 'POST') self::adminUserStatus((int) $m[1], $m[2]);
        if ($path === '/admin/bookings' && $method === 'GET') self::adminBookings();
        if (preg_match('#^/admin/bookings/([A-F0-9]{26})/(confirm|cancel)$#', $path, $m) && $method === 'POST') self::adminBooking($m[1], $m[2]);
        if (preg_match('#^/admin/refunds/([A-F0-9]{26})/(approve|reject)$#', $path, $m) && $method === 'POST') self::adminRefund($m[1], $m[2]);
        if ($path === '/admin/reports/summary' && $method === 'GET') self::summary();
        Http::error('NOT_FOUND', 'Endpoint not found.', 404);
    }

    private static function path(): string { $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'; return preg_replace('#^.*?/api#', '', $uri) ?: '/'; }
    private static function razorpayConfigured(): bool
    {
        $keyId = trim((string) Config::get('RAZORPAY_KEY_ID', ''));
        $keySecret = trim((string) Config::get('RAZORPAY_KEY_SECRET', ''));
        return (str_starts_with($keyId, 'rzp_test_') || str_starts_with($keyId, 'rzp_live_'))
            && $keySecret !== ''
            && !str_contains(strtolower($keySecret), 'replace_me');
    }
    private static function paymentQrPath(): ?string
    {
        $stmt = Database::connection()->query('SELECT qr_image_path FROM payment_settings WHERE id=1');
        $path = $stmt->fetchColumn();
        return is_string($path) && $path !== '' ? $path : null;
    }
    private static function paymentQr(): never
    {
        $pdo = Database::connection();
        $stmt = $pdo->query('SELECT qr_image_path,upi_id,payee_name FROM payment_settings WHERE id=1');
        $settings = $stmt->fetch() ?: [];
        $upiId = trim((string) ($settings['upi_id'] ?? ''));
        if ($upiId === '') {
            $upiId = 'pprajwaldeshpande-1@okicici';
            try {
                $pdo->exec("UPDATE payment_settings SET upi_id='pprajwaldeshpande-1@okicici', payee_name=COALESCE(NULLIF(payee_name,''),'Prajwal Deshpande') WHERE id=1 AND (upi_id IS NULL OR upi_id='')");
            } catch (\Throwable) {}
        }
        $payeeName = trim((string) ($settings['payee_name'] ?? ''));
        if ($payeeName === '') {
            $payeeName = 'Prajwal Deshpande';
        }
        Http::json([
            'imagePath' => is_string($settings['qr_image_path'] ?? null) && $settings['qr_image_path'] !== '' ? $settings['qr_image_path'] : null,
            'upiId' => $upiId,
            'payeeName' => $payeeName,
        ]);
    }
    private static function savePaymentSettings(): never
    {
        Auth::verifyCsrf();
        $admin = Auth::requireRole('admin');
        $input = Http::input();
        $upiId = strtolower(trim((string) ($input['upiId'] ?? '')));
        $payeeName = trim((string) ($input['payeeName'] ?? ''));
        if (!preg_match('/^[a-z0-9._-]+@[a-z0-9.-]+$/i', $upiId)) {
            Http::error('VALIDATION_FAILED', 'Enter a valid UPI ID (for example, name@okaxis).', 422);
        }
        if ($payeeName === '') $payeeName = $admin['username'];
        Database::connection()->prepare(
            'INSERT INTO payment_settings (id,upi_id,payee_name,updated_by) VALUES (1,:upi,:name,:admin)
             ON DUPLICATE KEY UPDATE upi_id=VALUES(upi_id),payee_name=VALUES(payee_name),updated_by=VALUES(updated_by)'
        )->execute(['upi' => $upiId, 'name' => substr($payeeName, 0, 100), 'admin' => $admin['id']]);
        Support::audit($admin['id'], 'payment_settings.updated', 'payment_settings', '1');
        Http::json(['upiId' => $upiId, 'payeeName' => $payeeName]);
    }
    private static function dynamicUpiQr(): never
    {
        $bookingId = strtoupper(trim((string) ($_GET['bookingId'] ?? '')));
        $eventId = (int) ($_GET['eventId'] ?? 0);
        $customAmount = isset($_GET['amount']) ? (float) $_GET['amount'] : 0.0;
        $wantsJson = (($_GET['format'] ?? '') === 'json') || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');

        $pdo = Database::connection();
        $settings = $pdo->query('SELECT qr_image_path,upi_id,payee_name FROM payment_settings WHERE id=1')->fetch() ?: [];
        $upiId = trim((string) ($settings['upi_id'] ?? '')) ?: 'pprajwaldeshpande-1@okicici';
        $payeeName = trim((string) ($settings['payee_name'] ?? '')) ?: 'Prajwal Deshpande';
        $staticQrPath = trim((string) ($settings['qr_image_path'] ?? ''));
        $staticQrDirectory = dirname(__DIR__) . '/public/uploads/payment-qr/';
        $publicStaticQrPath = $staticQrPath !== ''
            ? $staticQrDirectory . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, basename($staticQrPath))
            : '';

        if (
            $staticQrPath !== ''
            && str_starts_with($staticQrPath, 'uploads/payment-qr/')
            && basename($staticQrPath) === substr($staticQrPath, strlen('uploads/payment-qr/'))
            && is_file($publicStaticQrPath)
        ) {
            $staticQrUrl = './' . ltrim(str_replace(DIRECTORY_SEPARATOR, '/', $staticQrPath), './');
            if ($wantsJson) {
                Http::json([
                    'upiId' => $upiId,
                    'payeeName' => $payeeName,
                    'amount' => $customAmount > 0 ? $customAmount : null,
                    'amountFormatted' => $customAmount > 0 ? number_format($customAmount, 2, '.', '') : null,
                    'note' => 'Spearing Events Payment',
                    'upiUri' => null,
                    'qrUrl' => $staticQrUrl,
                    'staticQr' => true,
                ]);
            }
            $mime = match (strtolower(pathinfo($publicStaticQrPath, PATHINFO_EXTENSION))) {
                'jpg', 'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'webp' => 'image/webp',
                default => '',
            };
            if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
                Http::error('UPLOAD_INVALID', 'The configured payment QR image is invalid.', 500);
            }
            header('Content-Type: ' . $mime);
            header('Content-Length: ' . (string) filesize($publicStaticQrPath));
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            readfile($publicStaticQrPath);
            exit;
        }

        $amount = 0.0;
        $note = 'Spearing Events Payment';

        if ($bookingId !== '') {
            $user = Auth::requireUser();
            $stmt = $pdo->prepare('SELECT b.total_amount, e.title FROM bookings b JOIN events e ON e.id=b.event_id WHERE b.id=:id AND (b.user_id=:user OR :role IN (\'admin\',\'staff\'))');
            $stmt->execute(['id' => $bookingId, 'user' => $user['id'], 'role' => $user['role']]);
            $row = $stmt->fetch();
            if (!$row || (float) $row['total_amount'] <= 0) {
                Http::error('BOOKING_UNAVAILABLE', 'Paid booking not found.', 404);
            }
            $amount = (float) $row['total_amount'];
            $cleanTitle = trim(preg_replace('/[^A-Za-z0-9 ]/', '', (string) $row['title']));
            $note = 'Booking ' . $bookingId . ($cleanTitle !== '' ? ' - ' . substr($cleanTitle, 0, 20) : '');
        } elseif ($eventId > 0) {
            $stmt = $pdo->prepare('SELECT title, ticket_price FROM events WHERE id=:id');
            $stmt->execute(['id' => $eventId]);
            $row = $stmt->fetch();
            if (!$row || (float) $row['ticket_price'] <= 0) {
                Http::error('EVENT_UNAVAILABLE', 'Paid event not found or is free.', 404);
            }
            $amount = (float) $row['ticket_price'];
            $cleanTitle = trim(preg_replace('/[^A-Za-z0-9 ]/', '', (string) $row['title']));
            $note = 'Event ' . ($cleanTitle !== '' ? substr($cleanTitle, 0, 25) : (string) $eventId);
        } elseif ($customAmount > 0) {
            $amount = $customAmount;
            $rawNote = trim((string) ($_GET['note'] ?? 'Event Payment'));
            $cleanNote = trim(preg_replace('/[^A-Za-z0-9 ]/', '', $rawNote));
            $note = $cleanNote !== '' ? substr($cleanNote, 0, 30) : 'Event Payment';
        } else {
            Http::error('BAD_REQUEST', 'Missing bookingId, eventId, or amount parameter.', 400);
        }

        $params = [
            'pa' => $upiId,
            'pn' => $payeeName,
            'am' => number_format($amount, 2, '.', ''),
            'cu' => 'INR',
            'tn' => $note,
        ];
        $payload = 'upi://pay?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);

        if ($wantsJson) {
            Http::json([
                'upiId' => $upiId,
                'payeeName' => $payeeName,
                'amount' => $amount,
                'amountFormatted' => number_format($amount, 2, '.', ''),
                'note' => $note,
                'upiUri' => $payload,
                'qrUrl' => './api/payments/upi-qr?' . ($bookingId !== '' ? 'bookingId=' . urlencode($bookingId) : ($eventId > 0 ? 'eventId=' . $eventId : 'amount=' . $amount))
            ]);
        }

        $result = (new PngWriter())->write(new QrCode(data: $payload, size: 300, margin: 10));
        header('Content-Type: image/png');
        header('Cache-Control: private, no-store');
        echo $result->getString();
        exit;
    }
    private static function uploadPaymentQr(): never
    {
        Auth::verifyCsrf(); $admin = Auth::requireRole('admin'); $file = $_FILES['qr'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK || $file['size'] > 3 * 1024 * 1024) Http::error('UPLOAD_INVALID', 'Upload a QR image smaller than 3 MB.', 422);
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']); $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!isset($extensions[$mime]) || !getimagesize($file['tmp_name'])) Http::error('UPLOAD_INVALID', 'Only valid JPEG, PNG, and WebP QR images are allowed.', 422);
        $directory = dirname(__DIR__) . '/public/uploads/payment-qr';
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) Http::error('UPLOAD_FAILED', 'Could not create QR image storage.', 500);
        $name = bin2hex(random_bytes(16)) . '.' . $extensions[$mime]; $path = 'uploads/payment-qr/' . $name;
        if (!move_uploaded_file($file['tmp_name'], $directory . '/' . $name)) Http::error('UPLOAD_FAILED', 'Could not store QR image.', 500);
        $pdo = Database::connection(); $oldPath = self::paymentQrPath();
        try {
            $pdo->prepare('INSERT INTO payment_settings (id, qr_image_path, updated_by) VALUES (1, :path, :admin) ON DUPLICATE KEY UPDATE qr_image_path=VALUES(qr_image_path), updated_by=VALUES(updated_by)')->execute(['path' => $path, 'admin' => $admin['id']]);
        } catch (\Throwable $error) {
            if (is_file($directory . '/' . $name) && !unlink($directory . '/' . $name)) error_log('Could not remove an unreferenced payment QR upload: ' . $name);
            throw $error;
        }
        if ($oldPath && str_starts_with($oldPath, 'uploads/payment-qr/')) {
            $oldName = basename($oldPath);
            if ($oldName !== $name && is_file($directory . '/' . $oldName) && !unlink($directory . '/' . $oldName)) error_log('Could not remove the replaced payment QR image: ' . $oldName);
        }
        Support::audit($admin['id'], 'payment_qr.updated', 'payment_settings', '1');
        Http::json(['imagePath' => $path], 201);
    }
    private static function submitUpiPayment(): never
    {
        Auth::verifyCsrf(); $user = Auth::requireUser(); $input = Http::input(); $bookingId = strtoupper(trim((string) ($input['bookingId'] ?? ''))); $reference = trim((string) ($input['transactionReference'] ?? ''));
        if (!preg_match('/^[A-Za-z0-9\/-]{6,100}$/', $reference)) Http::error('VALIDATION_FAILED', 'Enter a valid UPI transaction reference (6–100 letters or numbers).', 422, ['transactionReference' => 'Invalid transaction reference.']);
        $pdo = Database::connection(); $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("SELECT id,total_amount,status,expires_at,payment_reference FROM bookings WHERE id=:id AND user_id=:user FOR UPDATE"); $stmt->execute(['id' => $bookingId, 'user' => $user['id']]); $booking = $stmt->fetch();
            if (!$booking || $booking['status'] !== 'pending_payment' || (float) $booking['total_amount'] <= 0 || ($booking['expires_at'] && strtotime($booking['expires_at']) < time())) { $pdo->rollBack(); Http::error('BOOKING_UNAVAILABLE', 'This booking is no longer available for payment verification.', 409); }
            $paymentSettings = Database::connection()->query('SELECT qr_image_path,upi_id FROM payment_settings WHERE id=1')->fetch() ?: [];
            $configuredUpi = trim((string) ($paymentSettings['upi_id'] ?? '')) ?: 'pprajwaldeshpande-1@okicici';
            if (!$configuredUpi && !($paymentSettings['qr_image_path'] ?? null)) { $pdo->rollBack(); Http::error('PAYMENTS_NOT_CONFIGURED', 'The admin has not configured payment details yet.', 503); }
            if ($booking['payment_reference'] !== null) { $pdo->rollBack(); Http::error('PAYMENT_ALREADY_SUBMITTED', 'A payment reference has already been submitted for this booking.', 409); }
            $pdo->prepare('UPDATE bookings SET payment_reference=:reference,payment_submitted_at=NOW(),expires_at=NULL WHERE id=:id')->execute(['reference' => $reference, 'id' => $bookingId]);
            $pdo->commit();
        } catch (\Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if ($error instanceof \PDOException && (string) $error->getCode() === '23000') Http::error('PAYMENT_REFERENCE_USED', 'That transaction reference has already been submitted.', 409);
            throw $error;
        }
        Support::notify((int) $user['id'], 'payment_submitted', 'Your payment reference was submitted and is awaiting admin verification.', ['bookingId' => $bookingId]);
        Http::json(['bookingId' => $bookingId, 'status' => 'pending_verification']);
    }
    private static function me(): never { $user = Auth::user(); Http::json(['user' => $user, 'csrfToken' => Auth::csrf()]); }
    private static function register(): never
    {
        $input = Http::input();
        $fullName = trim((string) ($input['fullName'] ?? ''));
        $username = trim((string) ($input['username'] ?? ''));
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        $password = (string) ($input['password'] ?? '');
        $fields = [];
        $nameLength = preg_match_all('/./us', $fullName);
        if ($fullName === '' || $nameLength === false || $nameLength > 120) $fields['fullName'] = 'Enter your name (up to 120 characters).';
        if (!preg_match('/^[A-Za-z0-9_]{3,50}$/', $username)) $fields['username'] = 'Use 3-50 letters, numbers, or underscores.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $fields['email'] = 'Enter a valid email address.';
        if (strlen($password) < 10 || strlen($password) > 72 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) $fields['password'] = 'Use 10-72 characters and include at least one letter and one number.';
        if (($input['termsAccepted'] ?? false) !== true) $fields['termsAccepted'] = 'Accept the terms to create an account.';
        if ($fields) Http::error('VALIDATION_FAILED', 'Please correct the highlighted fields.', 422, $fields);
        $pdo = Database::connection();
        try {
            $pdo->prepare("INSERT INTO users (username,email,password_hash,full_name,auth_provider,terms_accepted_at) VALUES (:username,:email,:hash,:full_name,'email',NOW())")
                ->execute(['username' => $username, 'email' => $email, 'hash' => password_hash($password, PASSWORD_DEFAULT), 'full_name' => $fullName]);
        } catch (\PDOException $error) {
            if ((string) $error->getCode() === '23000') Http::error('ALREADY_EXISTS', 'That username or email is already registered.', 409);
            throw $error;
        }
        $user = ['id' => (int) $pdo->lastInsertId(), 'username' => $username, 'email' => $email, 'full_name' => $fullName, 'role' => 'user'];
        Auth::login($user);
        Support::audit($user['id'], 'user.registered', 'user', (string) $user['id']);
        Http::json(['user' => Auth::user(), 'csrfToken' => Auth::csrf()], 201);
    }
    private static function login(): never
    {
        $input = Http::input(); $identifier = trim((string) ($input['identifier'] ?? $input['username'] ?? '')); $password = (string) ($input['password'] ?? ''); $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'; Auth::rateLimit($ip);
        $pdo = Database::connection(); $stmt = $pdo->prepare('SELECT * FROM users WHERE username = :username OR email = :email LIMIT 1'); $stmt->execute(['username' => $identifier, 'email' => $identifier]); $user = $stmt->fetch(); $valid = $user && $user['status'] === 'active' && is_string($user['password_hash']) && password_verify($password, $user['password_hash']); Auth::recordLoginAttempt($ip, $identifier, (bool) $valid);
        if (!$valid) Http::error('INVALID_CREDENTIALS', 'Invalid username or password.', 401); Auth::login($user); Support::audit((int) $user['id'], 'user.login', 'user', (string) $user['id']); Http::json(['user' => Auth::user(), 'csrfToken' => Auth::csrf()]);
    }
    private static function forgotPassword(): never
    {
        $input = Http::input();
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        Auth::rateLimitAction('password-reset-request', $email);
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $pdo = Database::connection();
            $pdo->exec('DELETE FROM password_reset_tokens WHERE expires_at<=NOW()');
            $stmt = $pdo->prepare("SELECT id,username,email FROM users WHERE email=:email AND status='active' LIMIT 1");
            $stmt->execute(['email' => $email]);
            $user = $stmt->fetch();
            if ($user) {
                $token = bin2hex(random_bytes(32));
                $tokenHash = hash('sha256', $token);
                $pdo->prepare('DELETE FROM password_reset_tokens WHERE user_id=:user')->execute(['user' => $user['id']]);
                $pdo->prepare('INSERT INTO password_reset_tokens (token_hash,user_id,expires_at) VALUES (:token,:user,DATE_ADD(NOW(),INTERVAL 1 HOUR))')
                    ->execute(['token' => $tokenHash, 'user' => $user['id']]);
                try {
                    Mailer::sendPasswordReset($user, $token);
                } catch (\Throwable $error) {
                    error_log('Password reset email delivery failed: ' . $error->getMessage());
                    $pdo->prepare('DELETE FROM password_reset_tokens WHERE token_hash=:token')->execute(['token' => $tokenHash]);
                }
            }
        }
        Http::json(['message' => 'If an active account matches that email address, a password reset link will be sent.']);
    }
    private static function resetPassword(): never
    {
        $input = Http::input();
        $token = trim((string) ($input['token'] ?? ''));
        $password = (string) ($input['password'] ?? '');
        Auth::rateLimitAction('password-reset-submit', $token);
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) Http::error('RESET_LINK_INVALID', 'This password reset link is invalid or expired.', 400);
        if (strlen($password) < 10 || strlen($password) > 72 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
            Http::error('VALIDATION_FAILED', 'Use 10-72 characters and include at least one letter and one number.', 422, ['password' => 'Password does not meet the requirements.']);
        }
        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT user_id FROM password_reset_tokens WHERE token_hash=:token AND expires_at>NOW() FOR UPDATE');
            $stmt->execute(['token' => hash('sha256', $token)]);
            $userId = $stmt->fetchColumn();
            if (!$userId) {
                $pdo->rollBack();
                Http::error('RESET_LINK_INVALID', 'This password reset link is invalid or expired.', 400);
            }
            $pdo->prepare('UPDATE users SET password_hash=:hash WHERE id=:user')
                ->execute(['hash' => password_hash($password, PASSWORD_DEFAULT), 'user' => $userId]);
            $pdo->prepare('DELETE FROM password_reset_tokens WHERE user_id=:user')->execute(['user' => $userId]);
            $pdo->commit();
        } catch (\Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        }
        Auth::logout();
        Http::json(['message' => 'Your password has been reset. Sign in with your new password.']);
    }
    private static function profile(): never
    {
        Auth::verifyCsrf(); $user = Auth::requireUser(); $input = Http::input(); $email = strtolower(trim((string) ($input['email'] ?? ''))); if (!filter_var($email, FILTER_VALIDATE_EMAIL)) Http::error('VALIDATION_FAILED', 'Enter a valid email address.', 422, ['email' => 'Invalid email.']);
        try { Database::connection()->prepare('UPDATE users SET email = :email WHERE id = :id')->execute(['email' => $email, 'id' => $user['id']]); } catch (\PDOException) { Http::error('ALREADY_EXISTS', 'This email address is already registered.', 409); } $_SESSION['user']['email'] = $email; Http::json(['user' => Auth::user()]);
    }
    private static function password(): never
    {
        Auth::verifyCsrf(); $user = Auth::requireUser(); $input = Http::input(); $current = (string) ($input['currentPassword'] ?? ''); $next = (string) ($input['newPassword'] ?? ''); if (strlen($next) < 10 || strlen($next) > 72 || !preg_match('/[A-Za-z]/', $next) || !preg_match('/[0-9]/', $next)) Http::error('VALIDATION_FAILED', 'Use 10-72 characters and include at least one letter and one number.', 422, ['newPassword' => 'Password does not meet the requirements.']); $pdo = Database::connection(); $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = :id'); $stmt->execute(['id' => $user['id']]); if (!password_verify($current, (string) $stmt->fetchColumn())) Http::error('INVALID_CREDENTIALS', 'Current password is incorrect.', 401); $pdo->prepare('UPDATE users SET password_hash = :hash WHERE id = :id')->execute(['hash' => password_hash($next, PASSWORD_DEFAULT), 'id' => $user['id']]); session_regenerate_id(true); Http::json();
    }
    private static function events(): never
    {
        $q = trim((string) ($_GET['search'] ?? '')); $category = trim((string) ($_GET['category'] ?? '')); $date = trim((string) ($_GET['date'] ?? '')); $page = max(1, (int) ($_GET['page'] ?? 1)); $limit = min(50, max(1, (int) ($_GET['limit'] ?? 24))); $where = ["e.status = 'published'", 'e.starts_at >= NOW()']; $params = [];
        if ($q !== '') { $where[] = '(e.title LIKE :q OR e.venue LIKE :q)'; $params['q'] = "%$q%"; } if ($category !== '') { $where[] = 'e.category = :category'; $params['category'] = $category; } if ($date !== '') { $where[] = 'DATE(e.starts_at) = :date'; $params['date'] = $date; }
        $base = ' FROM events e LEFT JOIN event_images i ON i.event_id = e.id AND i.is_primary = 1 WHERE ' . implode(' AND ', $where); $pdo = Database::connection(); $count = $pdo->prepare('SELECT COUNT(*)' . $base); $count->execute($params); $total = (int) $count->fetchColumn(); $stmt = $pdo->prepare('SELECT e.*, i.path AS image' . $base . ' ORDER BY e.starts_at LIMIT :limit OFFSET :offset'); foreach ($params as $k => $v) $stmt->bindValue(":$k", $v); $stmt->bindValue(':limit', $limit, PDO::PARAM_INT); $stmt->bindValue(':offset', ($page - 1) * $limit, PDO::PARAM_INT); $stmt->execute(); Http::json(['events' => array_map([Support::class, 'eventRow'], $stmt->fetchAll()), 'pagination' => ['page' => $page, 'limit' => $limit, 'total' => $total]]);
    }
    private static function event(int $id): never { $pdo = Database::connection(); $stmt = $pdo->prepare("SELECT e.*, i.path AS image FROM events e LEFT JOIN event_images i ON i.event_id=e.id AND i.is_primary=1 WHERE e.id=:id AND e.status='published'"); $stmt->execute(['id' => $id]); $event = $stmt->fetch(); if (!$event) Http::error('NOT_FOUND', 'Event not found.', 404); $images = $pdo->prepare('SELECT path FROM event_images WHERE event_id=:id ORDER BY is_primary DESC,id'); $images->execute(['id' => $id]); $row = Support::eventRow($event); $row['images'] = array_column($images->fetchAll(), 'path'); Http::json(['event' => $row]); }
    private static function createBooking(): never
    {
        Auth::verifyCsrf(); $user = Auth::requireUser(); $input = Http::input(); $eventId = (int) ($input['eventId'] ?? 0); $quantity = max(1, min(10, (int) ($input['quantity'] ?? 1))); $key = $_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? ''; if (!preg_match('/^[a-f0-9-]{36}$/i', $key)) Http::error('IDEMPOTENCY_REQUIRED', 'Provide a valid idempotency key.', 400); $pdo = Database::connection();
        $existing = $pdo->prepare('SELECT id, status FROM bookings WHERE user_id=:user AND idempotency_key=:key'); $existing->execute(['user' => $user['id'], 'key' => $key]); if ($row = $existing->fetch()) Http::json(['bookingId' => $row['id'], 'status' => $row['status'], 'reused' => true]);
        $pdo->beginTransaction(); try { $stmt = $pdo->prepare("SELECT * FROM events WHERE id=:id AND status='published' AND starts_at > NOW() FOR UPDATE"); $stmt->execute(['id' => $eventId]); $event = $stmt->fetch(); if (!$event) { $pdo->rollBack(); Http::error('EVENT_UNAVAILABLE', 'This event is no longer available.', 409); }         $paymentSettings = Database::connection()->query('SELECT qr_image_path,upi_id FROM payment_settings WHERE id=1')->fetch() ?: [];
        $configuredUpi = trim((string) ($paymentSettings['upi_id'] ?? '')) ?: 'pprajwaldeshpande-1@okicici';
        if ((float) $event['ticket_price'] > 0 && !$configuredUpi && !($paymentSettings['qr_image_path'] ?? null)) { $pdo->rollBack(); Http::error('PAYMENTS_NOT_CONFIGURED', 'The admin has not configured payment details yet.', 503); } if ((int) $event['capacity'] - (int) $event['reserved_quantity'] < $quantity) { $pdo->rollBack(); Http::error('SOLD_OUT', 'Not enough tickets are available.', 409); } $bookingId = Support::id(); $total = number_format((float) $event['ticket_price'] * $quantity, 2, '.', ''); $status = (float) $event['ticket_price'] == 0.0 ? 'confirmed' : 'pending_payment'; $pdo->prepare('UPDATE events SET reserved_quantity=reserved_quantity+:quantity WHERE id=:id')->execute(['quantity' => $quantity, 'id' => $eventId]); $pdo->prepare('INSERT INTO bookings (id,user_id,event_id,quantity,unit_price,total_amount,status,idempotency_key,expires_at) VALUES (:id,:user,:event,:quantity,:price,:total,:status,:key,DATE_ADD(NOW(), INTERVAL 20 MINUTE))')->execute(['id' => $bookingId, 'user' => $user['id'], 'event' => $eventId, 'quantity' => $quantity, 'price' => $event['ticket_price'], 'total' => $total, 'status' => $status, 'key' => $key]); if ($status === 'confirmed') self::confirmBooking($pdo, $bookingId);         $pdo->commit();
        $message = $status === 'confirmed'
            ? "Your booking for {$event['title']} is confirmed."
            : "Booking created for {$event['title']}. Payment verification is pending.";
        Support::notify($user['id'], $status === 'confirmed' ? 'booking_confirmed' : 'booking_created', $message, ['bookingId' => $bookingId, 'status' => $status]);
        Http::json(['bookingId' => $bookingId, 'status' => $status, 'amount' => (float) $total], 201);
    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
    }
    private static function paymentOrder(): never
    {
        Auth::verifyCsrf(); $user = Auth::requireUser(); if (!self::razorpayConfigured()) Http::error('PAYMENTS_NOT_CONFIGURED', 'Online payments are not configured. Add valid Razorpay test keys to the project .env file.', 503); $input = Http::input(); $bookingId = strtoupper((string) ($input['bookingId'] ?? '')); $pdo = Database::connection(); $stmt = $pdo->prepare('SELECT b.*, e.title FROM bookings b JOIN events e ON e.id=b.event_id WHERE b.id=:id AND b.user_id=:user FOR UPDATE'); $pdo->beginTransaction(); $stmt->execute(['id' => $bookingId, 'user' => $user['id']]); $booking = $stmt->fetch(); if (!$booking || $booking['status'] !== 'pending_payment' || strtotime($booking['expires_at']) < time()) { $pdo->rollBack(); Http::error('BOOKING_UNAVAILABLE', 'This booking cannot be paid.', 409); } $api = new Razorpay((string) Config::get('RAZORPAY_KEY_ID'), (string) Config::get('RAZORPAY_KEY_SECRET')); $order = $api->order->create(['receipt' => $bookingId, 'amount' => (int) round((float) $booking['total_amount'] * 100), 'currency' => 'INR', 'notes' => ['booking_id' => $bookingId]]); $pdo->prepare('INSERT INTO payments (booking_id,provider,provider_order_id,amount,status) VALUES (:booking,:provider,:order,:amount,:status)')->execute(['booking' => $bookingId, 'provider' => 'razorpay', 'order' => $order['id'], 'amount' => $booking['total_amount'], 'status' => 'created']); $pdo->commit(); Http::json(['bookingId' => $bookingId, 'orderId' => $order['id'], 'amount' => (int) round((float) $booking['total_amount'] * 100), 'currency' => 'INR', 'keyId' => Config::get('RAZORPAY_KEY_ID')]);
    }
    private static function verifyPayment(): never
    {
        Auth::verifyCsrf(); $user = Auth::requireUser(); $input = Http::input(); $bookingId = strtoupper((string) ($input['bookingId'] ?? '')); $orderId = (string) ($input['razorpay_order_id'] ?? ''); $paymentId = (string) ($input['razorpay_payment_id'] ?? ''); $signature = (string) ($input['razorpay_signature'] ?? ''); if (!$bookingId || !$orderId || !$paymentId || !$signature) Http::error('VALIDATION_FAILED', 'Incomplete payment confirmation.', 422); $expected = hash_hmac('sha256', $orderId . '|' . $paymentId, (string) Config::get('RAZORPAY_KEY_SECRET')); if (!hash_equals($expected, $signature)) Http::error('PAYMENT_SIGNATURE_INVALID', 'Payment signature verification failed.', 400); self::capturePayment($bookingId, $orderId, $paymentId, ['source' => 'client_verify']); self::booking($bookingId);
    }
    private static function webhook(): never
    {
        $body = file_get_contents('php://input') ?: ''; $signature = $_SERVER['HTTP_X_RAZORPAY_SIGNATURE'] ?? ''; $expected = hash_hmac('sha256', $body, (string) Config::get('RAZORPAY_WEBHOOK_SECRET')); if (!hash_equals($expected, $signature)) Http::error('WEBHOOK_SIGNATURE_INVALID', 'Invalid webhook signature.', 400); $payload = json_decode($body, true); $payment = $payload['payload']['payment']['entity'] ?? []; $orderId = (string) ($payment['order_id'] ?? ''); $paymentId = (string) ($payment['id'] ?? ''); if (($payload['event'] ?? '') === 'payment.captured' && $orderId && $paymentId) { $stmt = Database::connection()->prepare('SELECT booking_id FROM payments WHERE provider_order_id=:id'); $stmt->execute(['id' => $orderId]); if ($bookingId = $stmt->fetchColumn()) self::capturePayment((string) $bookingId, $orderId, $paymentId, $payload); } Http::json();
    }
    private static function capturePayment(string $bookingId, string $orderId, string $paymentId, array $payload): void
    {
        $pdo = Database::connection(); $pdo->beginTransaction(); try { $stmt = $pdo->prepare('SELECT b.*, p.id AS payment_row FROM bookings b JOIN payments p ON p.booking_id=b.id WHERE b.id=:booking AND p.provider_order_id=:order FOR UPDATE'); $stmt->execute(['booking' => $bookingId, 'order' => $orderId]); $row = $stmt->fetch(); if (!$row) { $pdo->rollBack(); return; } if ($row['status'] === 'confirmed') { $pdo->rollBack(); return; } $pdo->prepare("UPDATE payments SET provider_payment_id=:payment,status='captured',provider_payload=:payload WHERE id=:id")->execute(['payment' => $paymentId, 'payload' => json_encode($payload), 'id' => $row['payment_row']]); self::confirmBooking($pdo, $bookingId); $pdo->commit(); Support::notify((int) $row['user_id'], 'payment_success', 'Your payment was verified and tickets are available.', ['bookingId' => $bookingId]); } catch (\Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
    }
    private static function confirmBooking(PDO $pdo, string $bookingId): void { $pdo->prepare("UPDATE bookings SET status='confirmed', expires_at=NULL WHERE id=:id")->execute(['id' => $bookingId]); $stmt = $pdo->prepare('SELECT quantity FROM bookings WHERE id=:id'); $stmt->execute(['id' => $bookingId]); for ($i = 0; $i < (int) $stmt->fetchColumn(); $i++) $pdo->prepare('INSERT INTO tickets (id,booking_id,validation_token) VALUES (:id,:booking,:token)')->execute(['id' => Support::id(), 'booking' => $bookingId, 'token' => Support::token()]); }
    private static function myBookings(): never { $user = Auth::requireUser(); self::bookingsFor('b.user_id=:id', ['id' => $user['id']]); }
    private static function booking(string $id): never { $user = Auth::requireUser(); self::bookingsFor('b.id=:id AND (b.user_id=:user OR :role IN (\'admin\',\'staff\'))', ['id' => $id, 'user' => $user['id'], 'role' => $user['role']], true); }
    private static function bookingsFor(string $where, array $params, bool $one = false): never
    {
        $sql = 'SELECT b.*, u.username, e.title, e.starts_at, e.venue, p.provider_payment_id FROM bookings b JOIN users u ON u.id=b.user_id JOIN events e ON e.id=b.event_id LEFT JOIN payments p ON p.booking_id=b.id WHERE ' . $where . ' ORDER BY b.created_at DESC';
        $pdo = Database::connection();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            if ($row['status'] === 'confirmed') {
                $ticketCount = $pdo->prepare('SELECT COUNT(*) FROM tickets WHERE booking_id=:id');
                $ticketCount->execute(['id' => $row['id']]);
                $missingTickets = max(0, (int) $row['quantity'] - (int) $ticketCount->fetchColumn());
                if ($missingTickets > 0) {
                    $insertTicket = $pdo->prepare('INSERT INTO tickets (id,booking_id,validation_token,status) VALUES (:id,:booking,:token,\'active\')');
                    for ($i = 0; $i < $missingTickets; $i++) {
                        $insertTicket->execute([
                            'id' => Support::id(),
                            'booking' => $row['id'],
                            'token' => Support::token(),
                        ]);
                    }
                }
            }
            $tickets = $pdo->prepare('SELECT id, validation_token, status FROM tickets WHERE booking_id=:id ORDER BY id');
            $tickets->execute(['id' => $row['id']]);
            $row['tickets'] = $tickets->fetchAll();
        }
        if ($one) {
            if (!$rows) Http::error('NOT_FOUND', 'Booking not found.', 404);
            Http::json(['booking' => $rows[0]]);
        }
        Http::json(['bookings' => $rows]);
    }
    private static function ticket(string $token): never { $ticket = self::authorisedTicket($token); Http::json(['ticket' => $ticket, 'qrUrl' => './api/tickets/' . $token . '/qr']); }
    private static function ticketQr(string $token): never
    {
        self::authorisedTicket($token);
        $result = (new SvgWriter())->write(new QrCode(data: $token, size: 300, margin: 10));
        header('Content-Type: image/svg+xml');
        header('Cache-Control: private, no-store');
        header('Content-Length: ' . strlen($result->getString()));
        echo $result->getString();
        exit;
    }
    private static function authorisedTicket(string $token): array { $user = Auth::requireUser(); $stmt = Database::connection()->prepare('SELECT t.*, b.user_id, b.status AS booking_status, e.title, e.starts_at, e.venue FROM tickets t JOIN bookings b ON b.id=t.booking_id JOIN events e ON e.id=b.event_id WHERE t.validation_token=:token'); $stmt->execute(['token' => $token]); $ticket = $stmt->fetch(); if (!$ticket || $ticket['booking_status'] !== 'confirmed' || $ticket['status'] === 'cancelled' || $ticket['status'] === 'refunded' || ((int) $ticket['user_id'] !== $user['id'] && !in_array($user['role'], ['admin','staff'], true))) Http::error('NOT_FOUND', 'Ticket not found or no longer valid.', 404); return $ticket; }
    private static function checkIn(string $token): never { Auth::verifyCsrf(); $staff = Auth::requireRole('admin', 'staff'); $pdo = Database::connection(); $stmt = $pdo->prepare("UPDATE tickets t JOIN bookings b ON b.id=t.booking_id JOIN events e ON e.id=b.event_id SET t.status='checked_in',t.checked_in_at=NOW(),t.checked_in_by=:staff WHERE t.validation_token=:token AND t.status='active' AND b.status='confirmed' AND e.status='published' AND DATE(e.starts_at)=CURDATE()"); $stmt->execute(['staff' => $staff['id'], 'token' => $token]); if ($stmt->rowCount() !== 1) Http::error('TICKET_NOT_ELIGIBLE', 'Ticket is invalid, already checked in, or not eligible today.', 409); Support::audit($staff['id'], 'ticket.checked_in', 'ticket', $token); Http::json(['checkedIn' => true]); }
    private static function refundRequest(): never { Auth::verifyCsrf(); $user = Auth::requireUser(); $input = Http::input(); $bookingId = strtoupper((string) ($input['bookingId'] ?? '')); $reason = trim((string) ($input['reason'] ?? '')); if (strlen($reason) < 5) Http::error('VALIDATION_FAILED', 'Provide a refund reason.', 422, ['reason' => 'At least 5 characters.']); $pdo = Database::connection(); $stmt = $pdo->prepare("SELECT b.*,p.id AS payment_id,e.starts_at,e.cancellation_cutoff_hours FROM bookings b JOIN payments p ON p.booking_id=b.id JOIN events e ON e.id=b.event_id WHERE b.id=:id AND b.user_id=:user AND b.status='confirmed'"); $stmt->execute(['id' => $bookingId, 'user' => $user['id']]); $booking = $stmt->fetch(); if (!$booking || strtotime($booking['starts_at']) <= time() + ((int) $booking['cancellation_cutoff_hours'] * 3600)) Http::error('REFUND_INELIGIBLE', 'This booking is not eligible for a refund.', 409); $id = Support::id(); $pdo->prepare("INSERT INTO refunds (id,booking_id,payment_id,requested_by,amount,reason) VALUES (:id,:booking,:payment,:user,:amount,:reason)")->execute(['id' => $id, 'booking' => $bookingId, 'payment' => $booking['payment_id'], 'user' => $user['id'], 'amount' => $booking['total_amount'], 'reason' => $reason]); $pdo->prepare("UPDATE bookings SET status='refund_pending' WHERE id=:id")->execute(['id' => $bookingId]); Http::json(['refundId' => $id], 201); }
    private static function reviews(): never
    {
        $eventId = (int) ($_GET['eventId'] ?? 0);
        if ($eventId <= 0) Http::error('VALIDATION_FAILED', 'Event ID is required.', 422);
        $stmt = Database::connection()->prepare("SELECT r.rating,r.body,r.created_at,u.username FROM reviews r JOIN users u ON u.id=r.user_id WHERE r.event_id=:event AND r.status='published' ORDER BY r.created_at DESC LIMIT 50");
        $stmt->execute(['event' => $eventId]);
        Http::json(['reviews' => $stmt->fetchAll()]);
    }
    private static function wishlists(): never
    {
        $user = Auth::requireUser();
        $stmt = Database::connection()->prepare("SELECT e.id FROM wishlists w JOIN events e ON e.id=w.event_id WHERE w.user_id=:user AND e.status='published' ORDER BY w.created_at DESC");
        $stmt->execute(['user' => $user['id']]);
        Http::json(['eventIds' => array_map(fn($row) => (string) $row['id'], $stmt->fetchAll())]);
    }
    private static function addWishlist(): never
    {
        Auth::verifyCsrf();
        $user = Auth::requireUser();
        $input = Http::input();
        $eventId = (int) ($input['eventId'] ?? 0);
        if ($eventId <= 0) Http::error('VALIDATION_FAILED', 'Event ID is required.', 422);
        Database::connection()->prepare('INSERT IGNORE INTO wishlists (user_id,event_id) VALUES (:user,:event)')->execute(['user' => $user['id'], 'event' => $eventId]);
        Http::json(['saved' => true]);
    }
    private static function removeWishlist(int $eventId): never
    {
        Auth::verifyCsrf();
        $user = Auth::requireUser();
        Database::connection()->prepare('DELETE FROM wishlists WHERE user_id=:user AND event_id=:event')->execute(['user' => $user['id'], 'event' => $eventId]);
        Http::json(['saved' => false]);
    }
    private static function review(): never { Auth::verifyCsrf(); $user = Auth::requireUser(); $input = Http::input(); $eventId = (int) ($input['eventId'] ?? 0); $rating = (int) ($input['rating'] ?? 0); $body = trim(strip_tags((string) ($input['body'] ?? ''))); if ($rating < 1 || $rating > 5 || strlen($body) < 3) Http::error('VALIDATION_FAILED', 'Provide a rating and review.', 422); $eligible = Database::connection()->prepare("SELECT 1 FROM bookings WHERE user_id=:user AND event_id=:event AND status='confirmed' LIMIT 1"); $eligible->execute(['user' => $user['id'], 'event' => $eventId]); if (!$eligible->fetchColumn()) Http::error('REVIEW_INELIGIBLE', 'Only confirmed attendees can review an event.', 403); Database::connection()->prepare('INSERT INTO reviews (user_id,event_id,rating,body) VALUES (:user,:event,:rating,:body) ON DUPLICATE KEY UPDATE rating=VALUES(rating),body=VALUES(body),status=\'published\'')->execute(['user' => $user['id'], 'event' => $eventId, 'rating' => $rating, 'body' => $body]); Http::json(); }
    private static function notifications(): never { $user = Auth::requireUser(); $stmt = Database::connection()->prepare('SELECT * FROM notifications WHERE user_id=:id ORDER BY created_at DESC LIMIT 100'); $stmt->execute(['id' => $user['id']]); Http::json(['notifications' => $stmt->fetchAll()]); }
    private static function readNotifications(): never { Auth::verifyCsrf(); $user = Auth::requireUser(); Database::connection()->prepare('UPDATE notifications SET read_at=COALESCE(read_at,NOW()) WHERE user_id=:id')->execute(['id' => $user['id']]); Http::json(); }
    private static function saveEvent(?int $id = null): never
    {
        Auth::verifyCsrf();
        $admin = Auth::requireRole('admin');
        $input = Http::input();
        foreach (['title', 'description', 'category', 'venue', 'startsAt', 'capacity'] as $key) {
            if (trim((string) ($input[$key] ?? '')) === '') Http::error('VALIDATION_FAILED', 'Missing required event fields.', 422, [$key => 'Required.']);
        }
        $startsAt = strtotime((string) $input['startsAt']);
        $endsAt = empty($input['endsAt']) ? null : strtotime((string) $input['endsAt']);
        if ($startsAt === false || (!empty($input['endsAt']) && $endsAt === false)) Http::error('VALIDATION_FAILED', 'Enter a valid event date.', 422, ['startsAt' => 'Invalid date.']);
        $data = [
            'title' => trim($input['title']),
            'slug' => Support::slug($input['title']),
            'description' => trim($input['description']),
            'category' => trim($input['category']),
            'venue' => trim($input['venue']),
            'venue_details' => trim(strip_tags((string) ($input['venueDetails'] ?? ''))),
            'latitude' => ($input['latitude'] ?? null) !== '' ? ($input['latitude'] ?? null) : null,
            'longitude' => ($input['longitude'] ?? null) !== '' ? ($input['longitude'] ?? null) : null,
            'starts_at' => date('Y-m-d H:i:s', $startsAt),
            'ends_at' => $endsAt === null ? null : date('Y-m-d H:i:s', $endsAt),
            'ticket_price' => number_format(max(0, (float) ($input['price'] ?? 0)), 2, '.', ''),
            'capacity' => max(1, (int) $input['capacity']),
            'status' => in_array($input['status'] ?? 'published', ['draft', 'published'], true) ? $input['status'] ?? 'published' : 'draft',
        ];
        $pdo = Database::connection();
        $isUpdate = $id !== null;
        if ($isUpdate) {
            $updateData = $data;
            unset($updateData['slug']);
            $stmt = $pdo->prepare('UPDATE events SET title=:title,description=:description,category=:category,venue=:venue,venue_details=:venue_details,latitude=:latitude,longitude=:longitude,starts_at=:starts_at,ends_at=:ends_at,ticket_price=:ticket_price,capacity=:capacity,status=:status WHERE id=:id');
            $stmt->execute($updateData + ['id' => $id]);
            if ($stmt->rowCount() === 0) {
                $exists = $pdo->prepare('SELECT 1 FROM events WHERE id=:id');
                $exists->execute(['id' => $id]);
                if (!$exists->fetchColumn()) Http::error('NOT_FOUND', 'Event not found.', 404);
            }
        } else {
            $pdo->prepare('INSERT INTO events (created_by,title,slug,description,category,venue,venue_details,latitude,longitude,starts_at,ends_at,ticket_price,capacity,status) VALUES (:created_by,:title,:slug,:description,:category,:venue,:venue_details,:latitude,:longitude,:starts_at,:ends_at,:ticket_price,:capacity,:status)')->execute($data + ['created_by' => $admin['id']]);
            $id = (int) $pdo->lastInsertId();
        }
        Support::audit($admin['id'], $isUpdate ? 'event.updated' : 'event.created', 'event', (string) $id);
        Http::json(['eventId' => $id]);
    }
    private static function eventStatus(int $id, string $action): never { Auth::verifyCsrf(); $admin = Auth::requireRole('admin'); $status = $action === 'publish' ? 'published' : ($action === 'cancel' ? 'cancelled' : 'archived'); Database::connection()->prepare('UPDATE events SET status=:status WHERE id=:id')->execute(['status' => $status, 'id' => $id]); Support::audit($admin['id'], "event.$action", 'event', (string) $id); Http::json(); }
    private static function uploadImage(int $eventId): never
    {
        Auth::verifyCsrf(); Auth::requireRole('admin');
        $files = $_FILES['images'] ?? $_FILES['image'] ?? null;
        if (!$files) Http::error('UPLOAD_INVALID', 'Choose at least one image to upload.', 422);
        if (isset($files['name']) && is_array($files['name'])) {
            $uploads = [];
            foreach ($files['name'] as $index => $name) {
                $uploads[] = ['name' => $name, 'type' => $files['type'][$index] ?? '', 'tmp_name' => $files['tmp_name'][$index] ?? '', 'error' => $files['error'][$index] ?? UPLOAD_ERR_NO_FILE, 'size' => $files['size'][$index] ?? 0];
            }
        } else {
            $uploads = [$files];
        }
        if (count($uploads) > 5) Http::error('UPLOAD_INVALID', 'Upload no more than 5 images at a time.', 422);
        $pdo = Database::connection();
        $event = $pdo->prepare('SELECT id FROM events WHERE id=:id');
        $event->execute(['id' => $eventId]);
        if (!$event->fetchColumn()) Http::error('NOT_FOUND', 'Event not found.', 404);
        $directory = dirname(__DIR__) . '/public/uploads/events';
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) Http::error('UPLOAD_FAILED', 'Could not create event image storage.', 500);
        $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $validated = [];
        foreach ($uploads as $file) {
            if ($file['error'] !== UPLOAD_ERR_OK || $file['size'] > 5 * 1024 * 1024) Http::error('UPLOAD_INVALID', 'Each image must be smaller than 5 MB.', 422);
            $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
            if (!isset($extensions[$mime]) || !getimagesize($file['tmp_name'])) Http::error('UPLOAD_INVALID', 'Only valid JPEG, PNG, and WebP images are allowed.', 422);
            $validated[] = ['tmp_name' => $file['tmp_name'], 'mime' => $mime, 'size' => $file['size'], 'extension' => $extensions[$mime]];
        }
        $stored = [];
        foreach ($validated as $file) {
            $name = bin2hex(random_bytes(16)) . '.' . $file['extension'];
            if (!move_uploaded_file($file['tmp_name'], $directory . '/' . $name)) {
                foreach ($stored as $item) if (is_file($directory . '/' . $item['name'])) unlink($directory . '/' . $item['name']);
                Http::error('UPLOAD_FAILED', 'Could not store event image.', 500);
            }
            $stored[] = ['name' => $name, 'path' => 'uploads/events/' . $name, 'mime' => $file['mime'], 'size' => $file['size']];
        }
        try {
            $pdo->beginTransaction();
            $primary = $pdo->prepare('SELECT 1 FROM event_images WHERE event_id=:event AND is_primary=1 LIMIT 1');
            $primary->execute(['event' => $eventId]);
            $hasPrimary = (bool) $primary->fetchColumn();
            foreach ($stored as $index => $item) {
                $pdo->prepare('INSERT INTO event_images (event_id,path,mime_type,size_bytes,is_primary) VALUES (:event,:path,:mime,:size,:primary)')->execute(['event' => $eventId, 'path' => $item['path'], 'mime' => $item['mime'], 'size' => $item['size'], 'primary' => !$hasPrimary && $index === 0 ? 1 : 0]);
            }
            $pdo->commit();
        } catch (\Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            foreach ($stored as $item) if (is_file($directory . '/' . $item['name']) && !unlink($directory . '/' . $item['name'])) error_log('Could not remove an unreferenced event image: ' . $item['name']);
            throw $error;
        }
        Http::json(['paths' => array_column($stored, 'path')], 201);
    }
    private static function adminEvents(): never { Auth::requireRole('admin'); $stmt = Database::connection()->query('SELECT e.*,i.path AS image FROM events e LEFT JOIN event_images i ON i.event_id=e.id AND i.is_primary=1 ORDER BY e.starts_at DESC'); Http::json(['events' => array_map([Support::class, 'eventRow'], $stmt->fetchAll())]); }
    private static function adminUsers(): never
    {
        Auth::requireRole('admin');
        $stmt = Database::connection()->query("SELECT u.id,u.username,u.full_name,u.email,u.role,u.status,u.created_at,COUNT(b.id) AS booking_count FROM users u LEFT JOIN bookings b ON b.user_id=u.id WHERE u.role='user' GROUP BY u.id ORDER BY u.created_at DESC");
        Http::json(['users' => $stmt->fetchAll()]);
    }
    private static function adminUserStatus(int $id, string $action): never
    {
        Auth::verifyCsrf();
        $admin = Auth::requireRole('admin');
        $pdo = Database::connection();
        $stmt = $pdo->prepare("SELECT id,username,status FROM users WHERE id=:id AND role='user'");
        $stmt->execute(['id' => $id]);
        $user = $stmt->fetch();
        if (!$user) Http::error('NOT_FOUND', 'User not found.', 404);

        $status = match ($action) {
            'suspend' => 'suspended',
            'activate' => 'active',
            'delete' => 'deleted',
        };
        $pdo->prepare('UPDATE users SET status=:status WHERE id=:id')->execute(['status' => $status, 'id' => $id]);

        $message = match ($action) {
            'suspend' => 'Your account has been suspended by the administrator.',
            'activate' => 'Your account has been activated by the administrator.',
            'delete' => 'Your account has been deleted by the administrator.',
        };
        Support::notify($id, 'account_' . $action, $message, ['status' => $status]);
        Support::audit($admin['id'], "user.$action", 'user', (string) $id);
        Http::json(['status' => $status]);
    }
    private static function adminBookings(): never { Auth::requireRole('admin'); self::bookingsFor('1=1', []); }
    private static function adminBooking(string $id, string $action): never
    {
        Auth::verifyCsrf(); $admin = Auth::requireRole('admin'); $pdo = Database::connection(); $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT b.*,e.id AS event_id FROM bookings b JOIN events e ON e.id=b.event_id WHERE b.id=:id FOR UPDATE'); $stmt->execute(['id' => $id]); $booking = $stmt->fetch();
            if (!$booking) { $pdo->rollBack(); Http::error('NOT_FOUND', 'Booking not found.', 404); }
            if ($action === 'confirm') {
                if ($booking['status'] !== 'pending_payment') { $pdo->rollBack(); Http::error('BOOKING_UNAVAILABLE', 'This booking is no longer awaiting verification.', 409); }
                if ((float) $booking['total_amount'] > 0 && !$booking['payment_reference']) { $pdo->rollBack(); Http::error('PAYMENT_REFERENCE_REQUIRED', 'Verify the submitted transaction reference before confirming this paid booking.', 409); }
                $pdo->prepare("UPDATE bookings SET status='confirmed',expires_at=NULL WHERE id=:id")->execute(['id' => $id]);
                if ((float) $booking['total_amount'] > 0) {
                    $pdo->prepare("INSERT INTO payments (booking_id,provider,provider_payment_id,amount,status) VALUES (:booking,'upi',:reference,:amount,'captured')")->execute(['booking' => $id, 'reference' => $booking['payment_reference'], 'amount' => $booking['total_amount']]);
                }
                self::confirmBooking($pdo, $id);
            } else {
                if ($booking['status'] !== 'pending_payment') { $pdo->rollBack(); Http::error('BOOKING_UNAVAILABLE', 'Only a pending booking can be cancelled here. Confirmed bookings must use the refund process.', 409); }
                $pdo->prepare("UPDATE bookings SET status='cancelled' WHERE id=:id")->execute(['id' => $id]);
                $pdo->prepare("UPDATE events SET reserved_quantity=GREATEST(0,reserved_quantity-:quantity) WHERE id=:id")->execute(['quantity' => $booking['quantity'], 'id' => $booking['event_id']]);
            }
            $pdo->commit();
        } catch (\Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        }
        Support::audit($admin['id'], "booking.$action", 'booking', $id);
        Support::notify((int) $booking['user_id'], $action === 'confirm' ? 'payment_verified' : 'booking_cancelled', $action === 'confirm' ? 'Your payment was verified and your booking is confirmed.' : 'Your booking was cancelled by an admin.', ['bookingId' => $id]);
        Http::json();
    }
    private static function adminRefund(string $id, string $action): never { Auth::verifyCsrf(); Auth::requireRole('admin'); $pdo=Database::connection(); $stmt=$pdo->prepare('SELECT r.*,p.provider_payment_id FROM refunds r JOIN payments p ON p.id=r.payment_id WHERE r.id=:id FOR UPDATE'); $pdo->beginTransaction(); $stmt->execute(['id'=>$id]); $refund=$stmt->fetch(); if(!$refund || $refund['status']!=='requested'){ $pdo->rollBack(); Http::error('REFUND_UNAVAILABLE','Refund cannot be processed.',409); } if($action==='reject'){ $pdo->prepare("UPDATE refunds SET status='rejected' WHERE id=:id")->execute(['id'=>$id]); $pdo->prepare("UPDATE bookings SET status='confirmed' WHERE id=:id")->execute(['id'=>$refund['booking_id']]); $pdo->commit(); Http::json(); } $api=new Razorpay((string)Config::get('RAZORPAY_KEY_ID'),(string)Config::get('RAZORPAY_KEY_SECRET')); $provider=$api->payment->fetch($refund['provider_payment_id'])->refund(['amount'=>(int)round((float)$refund['amount']*100)]); $pdo->prepare("UPDATE refunds SET status='processing',provider_refund_id=:provider WHERE id=:id")->execute(['provider'=>$provider['id'],'id'=>$id]); $pdo->commit(); Http::json(['providerRefundId'=>$provider['id']]); }
    private static function summary(): never
    {
        Auth::requireRole('admin');
        $pdo = Database::connection();
        $stats = [
            'events' => (int) $pdo->query("SELECT COUNT(*) FROM events WHERE status='published'")->fetchColumn(),
            'users' => (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role='user'")->fetchColumn(),
            'bookings' => (int) $pdo->query('SELECT COUNT(*) FROM bookings')->fetchColumn(),
            'revenue' => (float) $pdo->query("SELECT COALESCE(SUM(total_amount),0) FROM bookings WHERE status='confirmed'")->fetchColumn(),
            'paidUsers' => (int) $pdo->query("SELECT COUNT(DISTINCT user_id) FROM bookings WHERE status='confirmed' AND total_amount>0")->fetchColumn(),
            'pendingPayments' => (int) $pdo->query("SELECT COUNT(*) FROM bookings WHERE status='pending_payment'")->fetchColumn(),
        ];
        $periodRevenue = (float) $pdo->query("SELECT COALESCE(SUM(total_amount),0) FROM bookings WHERE status='confirmed' AND created_at>=DATE_SUB(NOW(),INTERVAL 30 DAY)")->fetchColumn();
        $previousRevenue = (float) $pdo->query("SELECT COALESCE(SUM(total_amount),0) FROM bookings WHERE status='confirmed' AND created_at>=DATE_SUB(NOW(),INTERVAL 60 DAY) AND created_at<DATE_SUB(NOW(),INTERVAL 30 DAY)")->fetchColumn();
        $stats['revenueTrend'] = $previousRevenue > 0 ? round((($periodRevenue - $previousRevenue) / $previousRevenue) * 100, 1) : ($periodRevenue > 0 ? 100.0 : 0.0);
        $stats['periodRevenue'] = $periodRevenue;
        $stats['newEvents30Days'] = (int) $pdo->query("SELECT COUNT(*) FROM events WHERE created_at>=DATE_SUB(NOW(),INTERVAL 30 DAY)")->fetchColumn();

        $trendRows = $pdo->query("SELECT DATE(created_at) AS day, COALESCE(SUM(CASE WHEN status='confirmed' THEN total_amount ELSE 0 END),0) AS revenue, COUNT(*) AS bookings FROM bookings WHERE created_at>=DATE_SUB(CURDATE(),INTERVAL 6 DAY) GROUP BY DATE(created_at) ORDER BY day")->fetchAll();
        $stats['dailyTrend'] = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = (new \DateTimeImmutable())->modify("-{$i} days")->format('Y-m-d');
            $row = array_values(array_filter($trendRows, fn($item) => $item['day'] === $date))[0] ?? null;
            $stats['dailyTrend'][] = [
                'day' => $date,
                'revenue' => $row ? (float) $row['revenue'] : 0.0,
                'bookings' => $row ? (int) $row['bookings'] : 0,
            ];
        }
        $stats['topEvents'] = $pdo->query("SELECT e.title, COUNT(b.id) AS bookings, COALESCE(SUM(CASE WHEN b.status='confirmed' THEN b.total_amount ELSE 0 END),0) AS revenue FROM events e LEFT JOIN bookings b ON b.event_id=e.id GROUP BY e.id,e.title HAVING bookings>0 ORDER BY revenue DESC, bookings DESC LIMIT 5")->fetchAll();
        Http::json(['summary' => $stats]);
    }
}
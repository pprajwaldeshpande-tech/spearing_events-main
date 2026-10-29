<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/app/bootstrap.php';

$username = $argv[1] ?? '';
$password = getenv('EVENTSPOT_ADMIN_PASSWORD') ?: '';

if (!preg_match('/^[A-Za-z0-9_]{3,50}$/', $username)) {
    fwrite(STDERR, "Usage: php private/reset-admin-password.php <admin-username>\n");
    exit(1);
}

if (strlen($password) < 10 || strlen($password) > 72
    || !preg_match('/[A-Za-z]/', $password)
    || !preg_match('/[0-9]/', $password)) {
    fwrite(STDERR, "EVENTSPOT_ADMIN_PASSWORD must be 10-72 characters and contain a letter and a number.\n");
    exit(1);
}

$pdo = EventSpot\Database::connection();
$stmt = $pdo->prepare("SELECT id FROM users WHERE username=:username AND role='admin' AND status='active' LIMIT 1");
$stmt->execute(['username' => $username]);
$adminId = $stmt->fetchColumn();

if ($adminId === false) {
    fwrite(STDERR, "No active administrator was found with that username.\n");
    exit(1);
}

$hash = password_hash($password, PASSWORD_DEFAULT);
$update = $pdo->prepare("UPDATE users SET password_hash=:hash WHERE id=:id AND role='admin' AND status='active'");
$update->execute(['hash' => $hash, 'id' => $adminId]);

if ($update->rowCount() !== 1) {
    fwrite(STDERR, "The administrator password was not changed.\n");
    exit(1);
}

fwrite(STDOUT, "Administrator password updated. Sign in using the new password.\n");

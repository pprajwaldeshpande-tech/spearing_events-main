<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/app/bootstrap.php';

[$script, $username, $email] = array_pad($argv, 3, null);
if (!$username || !$email || !filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/^[A-Za-z0-9_]{3,50}$/', $username)) {
    fwrite(STDERR, "Usage: php private/create-admin.php <username> <email>\nSet EVENTSPOT_ADMIN_PASSWORD to a password of at least 10 characters.\n");
    exit(1);
}
$password = getenv('EVENTSPOT_ADMIN_PASSWORD') ?: '';
if (strlen($password) < 10) {
    fwrite(STDERR, "EVENTSPOT_ADMIN_PASSWORD must contain at least 10 characters.\n");
    exit(1);
}
try {
    EventSpot\Database::connection()->prepare("INSERT INTO users (username,email,password_hash,full_name,role) VALUES (:username,:email,:hash,:full_name,'admin')")->execute([
        'username' => $username,
        'email' => strtolower($email),
        'hash' => password_hash($password, PASSWORD_DEFAULT),
        'full_name' => $username,
    ]);
    fwrite(STDOUT, "Administrator created.\n");
} catch (PDOException) {
    fwrite(STDERR, "Username or email is already registered.\n");
    exit(1);
}
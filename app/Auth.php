<?php
declare(strict_types=1);

namespace EventSpot;

use PDO;

final class Auth
{
    public static function user(): ?array { return $_SESSION['user'] ?? null; }

    public static function requireUser(): array
    {
        $user = self::user();
        if (!$user) Http::error('AUTH_REQUIRED', 'Please sign in to continue.', 401);
        return $user;
    }

    public static function requireRole(string ...$roles): array
    {
        $user = self::requireUser();
        if (!in_array($user['role'], $roles, true)) Http::error('FORBIDDEN', 'You do not have permission for this action.', 403);
        return $user;
    }

    public static function login(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION['user'] = ['id' => (int) $user['id'], 'username' => $user['username'], 'email' => $user['email'], 'role' => $user['role'], 'full_name' => $user['full_name'] ?? $user['username']];
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
        $_SESSION['authenticated_at'] = time();
    }

    public static function csrf(): string
    {
        return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
    }

    public static function verifyCsrf(): void
    {
        if (in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD', 'OPTIONS'], true)) return;
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['_csrf'] ?? '';
        $sessionToken = $_SESSION['csrf'] ?? '';
        if (!is_string($token) || !is_string($sessionToken) || $token === '' || $sessionToken === '' || !hash_equals($sessionToken, $token)) {
            Http::error('CSRF_FAILED', 'Your session token is missing or invalid.', 403);
        }
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) setcookie(session_name(), '', time() - 3600, '/');
        session_destroy();
    }

    public static function rateLimit(string $ip): void
    {
        $pdo = Database::connection();
        $cutoff = date('Y-m-d H:i:s', time() - 900);
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip_address = :ip AND attempted_at >= :cutoff');
        $stmt->execute(['ip' => $ip, 'cutoff' => $cutoff]);
        if ((int) $stmt->fetchColumn() >= 8) Http::error('LOGIN_THROTTLED', 'Too many login attempts. Try again later.', 429);
    }

    public static function recordLoginAttempt(string $ip, string $identifier, bool $successful): void
    {
        Database::connection()->prepare('INSERT INTO login_attempts (ip_address, identifier, successful) VALUES (:ip, :identifier, :successful)')->execute(['ip' => $ip, 'identifier' => $identifier, 'successful' => $successful]);
    }

    public static function rateLimitAction(string $action, string $identifier): void
    {
        $pdo = Database::connection();
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $hash = hash('sha256', strtolower($identifier));
        $cutoff = date('Y-m-d H:i:s', time() - 900);
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM auth_action_attempts WHERE action=:action AND ip_address=:ip AND identifier_hash=:identifier AND attempted_at>=:cutoff');
        $stmt->execute(['action' => $action, 'ip' => $ip, 'identifier' => $hash, 'cutoff' => $cutoff]);
        if ((int) $stmt->fetchColumn() >= 5) Http::error('AUTH_ACTION_THROTTLED', 'Too many requests. Wait a few minutes and try again.', 429);
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM auth_action_attempts WHERE action=:action AND ip_address=:ip AND attempted_at>=:cutoff');
        $stmt->execute(['action' => $action, 'ip' => $ip, 'cutoff' => $cutoff]);
        if ((int) $stmt->fetchColumn() >= 20) Http::error('AUTH_ACTION_THROTTLED', 'Too many requests. Wait a few minutes and try again.', 429);
        $pdo->prepare('INSERT INTO auth_action_attempts (action,ip_address,identifier_hash) VALUES (:action,:ip,:identifier)')
            ->execute(['action' => $action, 'ip' => $ip, 'identifier' => $hash]);
    }
}
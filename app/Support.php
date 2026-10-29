<?php
declare(strict_types=1);

namespace EventSpot;

use PDO;

final class Support
{
    public static function id(): string { return strtoupper(bin2hex(random_bytes(13))); }
    public static function token(): string { return bin2hex(random_bytes(32)); }
    public static function slug(string $value): string { $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $value), '-')); return $slug . '-' . substr(bin2hex(random_bytes(4)), 0, 8); }
    public static function audit(?int $actorId, string $action, string $targetType, ?string $targetId = null, array $metadata = []): void
    {
        Database::connection()->prepare('INSERT INTO audit_logs (actor_id, action, target_type, target_id, metadata, ip_address) VALUES (:actor, :action, :type, :target, :metadata, INET6_ATON(:ip))')->execute(['actor' => $actorId, 'action' => $action, 'type' => $targetType, 'target' => $targetId, 'metadata' => json_encode($metadata), 'ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1']);
    }
    public static function notify(int $userId, string $type, string $message, array $payload = []): void
    {
        Database::connection()->prepare('INSERT INTO notifications (id, user_id, type, message, payload, email_status) VALUES (:id, :user, :type, :message, :payload, :status)')->execute(['id' => self::id(), 'user' => $userId, 'type' => $type, 'message' => $message, 'payload' => json_encode($payload), 'status' => 'pending']);
    }
    public static function eventRow(array $event): array
    {
        $event['id'] = (string) $event['id'];
        $event['price'] = (float) $event['ticket_price'];
        $event['date'] = substr($event['starts_at'], 0, 10);
        $event['location'] = $event['venue'];
        $event['available'] = max(0, (int) $event['capacity'] - (int) $event['reserved_quantity']);
        return $event;
    }
}
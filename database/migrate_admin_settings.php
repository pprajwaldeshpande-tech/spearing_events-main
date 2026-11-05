<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

$pdo = \EventSpot\Database::connection();
$columns = [
    'phone' => 'VARCHAR(30) NULL',
    'profile_picture' => 'VARCHAR(255) NULL',
];
foreach ($columns as $column => $definition) {
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = :column"
    );
    $stmt->execute(['column' => $column]);
    if ((int) $stmt->fetchColumn() === 0) {
        $pdo->exec("ALTER TABLE users ADD COLUMN `{$column}` {$definition}");
    }
}
$pdo->exec(
    "CREATE TABLE IF NOT EXISTS admin_settings (
      user_id BIGINT UNSIGNED NOT NULL,
      setting_key VARCHAR(100) NOT NULL,
      setting_value JSON NOT NULL,
      updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (user_id, setting_key),
      CONSTRAINT fk_admin_settings_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB"
);
echo "Admin settings migration applied successfully.\n";

<?php
declare(strict_types=1);

namespace EventSpot;

use PHPMailer\PHPMailer\PHPMailer;

final class Mailer
{
    public static function sendPasswordReset(array $user, string $token): void
    {
        $host = trim((string) Config::get('MAIL_HOST', ''));
        $username = trim((string) Config::get('MAIL_USERNAME', ''));
        $password = trim((string) Config::get('MAIL_PASSWORD', ''));
        $from = trim((string) Config::get('MAIL_FROM_ADDRESS', ''));
        if ($host === '' || $username === '' || $password === '' || $from === ''
            || str_contains($host, 'example.com') || str_contains(strtolower($username), 'replace_me')
            || str_contains(strtolower($password), 'replace_me')) {
            throw new \RuntimeException('SMTP is not configured for password reset email delivery.');
        }
        $baseUrl = rtrim((string) Config::get('APP_URL', ''), '/');
        if (!filter_var($baseUrl, FILTER_VALIDATE_URL)) {
            throw new \RuntimeException('APP_URL must be a valid absolute URL to send password reset links.');
        }
        $resetUrl = $baseUrl . '/reset-password.html?token=' . rawurlencode($token);
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $host;
        $mail->Port = (int) Config::get('MAIL_PORT', '587');
        $mail->SMTPAuth = true;
        $mail->Username = $username;
        $mail->Password = $password;
        $mail->SMTPSecure = (string) Config::get('MAIL_ENCRYPTION', 'tls');
        $mail->setFrom($from, (string) Config::get('MAIL_FROM_NAME', 'Spearing Events'));
        $mail->addAddress($user['email'], $user['username']);
        $mail->isHTML(false);
        $mail->Subject = 'Reset your Spearing Events password';
        $mail->Body = "Hello {$user['username']},\n\nUse this link to reset your Spearing Events password. The link expires in one hour and can only be used once:\n\n{$resetUrl}\n\nIf you did not request a reset, you can ignore this email.";
        $mail->send();
    }

    public static function deliverPending(int $limit = 50): int
    {
        $pdo = Database::connection();
        $items = $pdo->query("SELECT n.*,u.email,u.username FROM notifications n JOIN users u ON u.id=n.user_id WHERE n.email_status IN ('pending','failed') AND n.email_attempts < 5 ORDER BY n.created_at LIMIT " . (int) $limit)->fetchAll();
        $sent = 0;
        foreach ($items as $item) {
            try {
                $mail = new PHPMailer(true);
                $mail->isSMTP();
                $mail->Host = (string) Config::get('MAIL_HOST');
                $mail->Port = (int) Config::get('MAIL_PORT', '587');
                $mail->SMTPAuth = true;
                $mail->Username = (string) Config::get('MAIL_USERNAME');
                $mail->Password = (string) Config::get('MAIL_PASSWORD');
                $mail->SMTPSecure = (string) Config::get('MAIL_ENCRYPTION', 'tls');
                $mail->setFrom((string) Config::get('MAIL_FROM_ADDRESS'), (string) Config::get('MAIL_FROM_NAME', 'Spearing Events'));
                $mail->addAddress($item['email'], $item['username']);
                $mail->isHTML(false);
                $mail->Subject = 'Spearing Events notification';
                $mail->Body = $item['message'];
                $mail->send();
                $pdo->prepare("UPDATE notifications SET email_status='sent',email_attempts=email_attempts+1,email_last_attempt_at=NOW() WHERE id=:id")->execute(['id' => $item['id']]);
                $sent++;
            } catch (\Throwable $exception) {
                error_log('Notification email failed: ' . $exception->getMessage());
                $pdo->prepare("UPDATE notifications SET email_status='failed',email_attempts=email_attempts+1,email_last_attempt_at=NOW() WHERE id=:id")->execute(['id' => $item['id']]);
            }
        }
        return $sent;
    }
}
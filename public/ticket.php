<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

$token = trim((string) ($_GET['token'] ?? ''));
$ticket = null;
$ticketMessage = 'Invalid Entry Pass';
$event = null;

if (preg_match('/^[a-f0-9]{64}$/i', $token)) {
    $stmt = EventSpot\Database::connection()->prepare(
        'SELECT t.status AS ticket_status,t.checked_in_at,b.id AS booking_id,b.status AS booking_status,b.quantity,u.full_name,u.username,
                e.id AS event_id,e.title,e.description,e.category,e.venue,e.venue_details,e.starts_at,e.ends_at,e.ticket_price,i.path AS image
         FROM tickets t
         JOIN bookings b ON b.id=t.booking_id
         JOIN users u ON u.id=b.user_id
         JOIN events e ON e.id=b.event_id
         LEFT JOIN event_images i ON i.event_id=e.id AND i.is_primary=1
         WHERE t.validation_token=:token'
    );
    $stmt->execute(['token' => $token]);
    $ticket = $stmt->fetch();

    if ($ticket && in_array($ticket['booking_status'], ['cancelled', 'cancelled_by_admin'], true)) {
        $ticketMessage = 'Booking Cancelled';
        $ticket = null;
    } elseif ($ticket && in_array($ticket['ticket_status'], ['cancelled', 'refunded'], true)) {
        $ticketMessage = 'Invalid Entry Pass';
        $ticket = null;
    } elseif ($ticket && $ticket['booking_status'] !== 'confirmed') {
        $ticketMessage = 'Booking not confirmed';
        $ticket = null;
    } elseif ($ticket) {
        if ($ticket['ticket_status'] === 'active') {
            $update = EventSpot\Database::connection()->prepare(
                "UPDATE tickets SET status='checked_in', checked_in_at=NOW() WHERE validation_token=:token AND status='active'"
            );
            $update->execute(['token' => $token]);
            if ($update->rowCount() === 1) {
                $ticketMessage = 'Entry Successful';
                $ticket['ticket_status'] = 'checked_in';
                $ticket['checked_in_at'] = date('Y-m-d H:i:s');
            } else {
                $ticketMessage = 'Already Scanned - Entry Completed';
                $checked = EventSpot\Database::connection()->prepare('SELECT status, checked_in_at FROM tickets WHERE validation_token=:token');
                $checked->execute(['token' => $token]);
                $current = $checked->fetch();
                $ticket['ticket_status'] = (string) ($current['status'] ?? 'checked_in');
                $ticket['checked_in_at'] = (string) ($current['checked_in_at'] ?? '');
            }
        } else {
            $ticketMessage = 'Already Scanned - Entry Completed';
        }
        $event = [
            'id' => (int) $ticket['event_id'],
            'title' => (string) $ticket['title'],
            'description' => (string) $ticket['description'],
            'category' => (string) $ticket['category'],
            'venue' => (string) $ticket['venue'],
            'venueDetails' => (string) ($ticket['venue_details'] ?? ''),
            'startsAt' => (string) $ticket['starts_at'],
            'endsAt' => (string) ($ticket['ends_at'] ?? ''),
            'price' => (float) $ticket['ticket_price'],
            'image' => (string) ($ticket['image'] ?? ''),
        ];
    }
}

$escape = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$formatDate = static function (string $value): string {
    if ($value === '') return 'To be confirmed';
    $date = DateTime::createFromFormat('Y-m-d H:i:s', $value);
    return $date ? $date->format('D, j F Y \a\t g:i a') : $value;
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $event ? $escape($event['title']) . ' - Entry Pass' : 'Entry Pass' ?> | Spearing Events</title>
  <link rel="stylesheet" href="style.css?v=15">
  <style>
    .ticket-page{max-width:760px;margin:2rem auto;padding:0 1rem}
    .ticket-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);box-shadow:var(--shadow);overflow:hidden}
    .ticket-card>img{width:100%;max-height:300px;object-fit:cover;display:block}
    .ticket-content{padding:1.5rem}.ticket-status{display:inline-block;margin-bottom:1rem;padding:.35rem .8rem;border-radius:999px;font-weight:700;color:var(--success);background:rgba(34,211,160,.12)}
    .ticket-details{display:grid;gap:.85rem;margin-top:1rem;color:var(--muted)}.ticket-details strong{display:block;color:var(--text)}
  </style>
</head>
<body>
<main class="ticket-page">
  <div class="ticket-card">
    <?php if (!$event): ?>
      <div class="ticket-content" style="text-align:center">
        <h1><?= $escape($ticketMessage) ?></h1>
        <p>The entry pass could not be validated.</p>
      </div>
    <?php else: ?>
      <?php if ($event['image'] !== ''): ?>
        <img src="<?= $escape($event['image']) ?>" alt="<?= $escape($event['title']) ?>">
      <?php endif; ?>
      <div class="ticket-content">
        <?php if ($ticket['ticket_status'] === 'checked_in'): ?>
          <?php $isFirstScan = $ticketMessage === 'Entry Successful'; ?>
          <span class="ticket-status" style="color:<?= $isFirstScan ? 'var(--success)' : 'var(--danger)' ?>;background:<?= $isFirstScan ? 'rgba(34,211,160,.12)' : 'rgba(244,63,94,.12)' ?>"><?= $escape($ticketMessage) ?></span>
          <div style="font-weight:700;margin-bottom:1rem"><?= $isFirstScan ? 'Entry recorded successfully.' : 'Already Scanned - Entry Completed' ?></div>
          <div>Scanned date and time<strong><?= $escape($formatDate((string) $ticket['checked_in_at'])) ?></strong></div>
        <?php else: ?>
          <span class="ticket-status">PENDING ENTRY</span>
        <?php endif; ?>
        <h1 style="margin-top:0"><?= $escape($event['title']) ?></h1>
        <p><?= nl2br($escape($event['description'])) ?></p>
        <div class="ticket-details">
          <div>Date and time<strong><?= $escape($formatDate($event['startsAt'])) ?></strong></div>
          <div>Venue<strong><?= $escape($event['venue']) ?></strong><?= $event['venueDetails'] !== '' ? '<span>' . $escape($event['venueDetails']) . '</span>' : '' ?></div>
          <div>Category<strong><?= $escape($event['category']) ?></strong></div>
          <div>User name<strong><?= $escape($ticket['full_name'] ?: $ticket['username']) ?></strong></div>
          <div>Booking status<strong><?= $escape(ucwords(str_replace('_', ' ', $ticket['booking_status']))) ?></strong></div>
          <div>Ticket price<strong><?= $event['price'] == 0 ? 'Free' : '₹' . number_format($event['price'], 2) ?></strong></div>
          <div>Booking<strong><?= $escape($ticket['booking_id']) ?> · <?= (int) $ticket['quantity'] ?> ticket<?= (int) $ticket['quantity'] === 1 ? '' : 's' ?></strong></div>
        </div>
      </div>
    <?php endif; ?>
  </div>
</main>
</body>
</html>

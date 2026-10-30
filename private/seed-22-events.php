<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/app/bootstrap.php';

use EventSpot\Database;
use EventSpot\Support;

$pdo = Database::connection();
$adminId = $pdo->query("SELECT id FROM users WHERE role='admin' AND status='active' ORDER BY id LIMIT 1")->fetchColumn();
if ($adminId === false) {
    fwrite(STDERR, "Create an active administrator before seeding events.\n");
    exit(1);
}

$marker = 'ADDED EVENT CATALOG 22';
$existing = $pdo->prepare("SELECT COUNT(*) FROM events WHERE description LIKE :marker");
$existing->execute(['marker' => '%' . $marker . '%']);
if ((int) $existing->fetchColumn() >= 22) {
    fwrite(STDOUT, "The 22-event catalog is already present.\n");
    exit(0);
}

$events = [
    ['Bengaluru Tech Summit 2026', 'Technology', 'KTPO Convention Centre, Whitefield', 999, 'Technology leaders, developers, and startups for talks, demos, and networking.'],
    ['Indie Music Night', 'Music', 'The Humming Tree, Indiranagar', 499, 'An evening of live indie bands, acoustic performances, and local food stalls.'],
    ['Bengaluru Food Carnival', 'Food', 'Palace Grounds, Bengaluru', 399, 'Explore regional cuisine, chef demonstrations, desserts, and street food favourites.'],
    ['Startup Founders Meetup', 'Business', 'Bangalore International Centre, Domlur', 299, 'Meet founders and investors for pitches, mentoring, and practical networking.'],
    ['Weekend Yoga Retreat', 'Health', 'Lalbagh Botanical Garden, Bengaluru', 249, 'Guided yoga, breathing sessions, wellness talks, and mindfulness activities.'],
    ['Cubbon Park Photography Walk', 'Arts', 'Cubbon Park, Bengaluru', 349, 'A guided street and nature photography walk with composition tips and group reviews.'],
    ['Bengaluru Marathon Warmup', 'Sports', 'Freedom Park, Bengaluru', 599, 'A community run with warm-up coaching, pacing groups, and hydration support.'],
    ['Python and AI Workshop', 'Education', 'IISc Campus, Malleshwaram', 1499, 'A hands-on workshop covering Python, machine learning basics, and AI projects.'],
    ['Cybersecurity Bootcamp', 'Technology', 'NIMHANS Convention Centre, Bengaluru', 1299, 'Learn practical security testing, incident response, and safe digital habits.'],
    ['Jazz Under the Stars', 'Music', 'Jayamahal Palace Grounds, Bengaluru', 799, 'A relaxed open-air evening featuring jazz ensembles and contemporary musicians.'],
    ['Traditional Food Festival', 'Food', 'Freedom Park, Bengaluru', 299, 'Taste traditional Karnataka dishes prepared by local family-run kitchens.'],
    ['Women in Business Forum', 'Business', 'The Leela Palace, Bengaluru', 699, 'Leadership stories, investor conversations, workshops, and peer networking.'],
    ['Healthy Living Expo', 'Health', 'Bangalore International Exhibition Centre, Madavara', 199, 'Nutrition, fitness, preventive healthcare, and wellness product demonstrations.'],
    ['Watercolour Art Workshop', 'Arts', 'Bangalore Creative Circus, Yeshwanthpur', 899, 'Learn colour mixing, composition, and outdoor sketching with a professional artist.'],
    ['Badminton Community Cup', 'Sports', 'St. Josephs University, Bengaluru', 449, 'Friendly doubles and singles matches with junior and adult skill divisions.'],
    ['Career Skills Bootcamp', 'Education', 'Bangalore International Centre, Domlur', 999, 'Career planning, interview practice, resume reviews, and communication workshops.'],
    ['Robotics for Beginners', 'Technology', 'KTPO Convention Centre, Whitefield', 1199, 'Build and program a simple robot through guided beginner-friendly challenges.'],
    ['Classical Music Evening', 'Music', 'Ranga Shankara, Bengaluru', 599, 'An intimate classical music concert featuring leading regional performers.'],
    ['Plant Based Cooking Masterclass', 'Food', 'Indiranagar, Bengaluru', 749, 'Learn practical plant-based recipes, meal planning, and everyday cooking techniques.'],
    ['Leadership and Strategy Forum', 'Business', 'ITC Gardenia, Bengaluru', 1599, 'Executive sessions on team leadership, growth strategy, and future-ready organisations.'],
    ['Family Cycling Sunday', 'Sports', 'Cubbon Park, Bengaluru', 249, 'A safe guided family cycling route with beginner coaching and rest stops.'],
    ['Community Volunteer Day', 'Other', 'Lalbagh Botanical Garden, Bengaluru', 0, 'Join residents for park clean-up, tree care, and community gardening activities.'],
];

$images = [
    'Technology' => 'https://images.unsplash.com/photo-1540575467063-178a50c2df87?w=900&q=80',
    'Music' => 'https://images.unsplash.com/photo-1501386761578-eaa54b3538c8?w=900&q=80',
    'Food' => 'https://images.unsplash.com/photo-1555939594-58d7cb561ad1?w=900&q=80',
    'Business' => 'https://images.unsplash.com/photo-1556761175-4b46a572b786?w=900&q=80',
    'Health' => 'https://images.unsplash.com/photo-1544367567-0f2fcb009e0b?w=900&q=80',
    'Arts' => 'https://images.unsplash.com/photo-1460661419201-fd4cecdf8a8b?w=900&q=80',
    'Sports' => 'https://images.unsplash.com/photo-1461896836934-ffe607ba8211?w=900&q=80',
    'Education' => 'https://images.unsplash.com/photo-1524178232363-1fb2b075b655?w=900&q=80',
    'Other' => 'https://images.unsplash.com/photo-1492684223066-81342ee5ff30?w=900&q=80',
];

$insert = $pdo->prepare(
    "INSERT INTO events
      (created_by,title,slug,description,category,venue,starts_at,ticket_price,capacity,status)
     VALUES
      (:created_by,:title,:slug,:description,:category,:venue,:starts_at,:ticket_price,:capacity,'published')"
);
$insertImage = $pdo->prepare(
    "INSERT INTO event_images (event_id,path,mime_type,size_bytes,is_primary)
     VALUES (:event_id,:path,'image/jpeg',0,1)"
);
$checkSlug = $pdo->prepare('SELECT id FROM events WHERE slug=:slug');

$pdo->beginTransaction();
try {
    foreach ($events as $index => [$title, $category, $venue, $price, $description]) {
        $slug = Support::slug($title);
        $checkSlug->execute(['slug' => $slug]);
        if ($existingId = $checkSlug->fetchColumn()) {
            continue;
        }
        $date = (new DateTimeImmutable('2026-11-01 09:00:00'))->modify("+{$index} days");
        $insert->execute([
            'created_by' => $adminId,
            'title' => $title,
            'slug' => $slug,
            'description' => $description . ' ' . $marker,
            'category' => $category,
            'venue' => $venue,
            'starts_at' => $date->format('Y-m-d H:i:s'),
            'ticket_price' => $price,
            'capacity' => 100 + (($index % 5) * 50),
        ]);
        $eventId = (int) $pdo->lastInsertId();
        $insertImage->execute([
            'event_id' => $eventId,
            'path' => $images[$category] ?? $images['Other'],
        ]);
    }
    $pdo->commit();
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $error;
}

fwrite(STDOUT, "Added 22 published events to the catalog.\n");

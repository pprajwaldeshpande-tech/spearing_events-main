<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/app/bootstrap.php';

$pdo = EventSpot\Database::connection();
$adminId = $pdo->query("SELECT id FROM users WHERE role='admin' AND status='active' ORDER BY id LIMIT 1")->fetchColumn();
if ($adminId === false) {
    fwrite(STDERR, "Create an active administrator before seeding demo events.\n");
    exit(1);
}

$marker = 'DEMO DATA: Event Spear 300-event sample catalog';
$existing = $pdo->prepare('SELECT COUNT(*) FROM events WHERE description LIKE :marker');
$existing->execute(['marker' => $marker . '%']);
$existingCount = (int) $existing->fetchColumn();
if ($existingCount === 300) {
    fwrite(STDOUT, "The 300 demo events are already present; no changes made.\n");
    exit(0);
}
if ($existingCount !== 0) {
    fwrite(STDERR, "Found {$existingCount} demo events instead of 0 or 300. No changes made.\n");
    exit(1);
}

$categories = [
    'Technology' => [
        'Applied AI', 'Cloud Computing', 'Cybersecurity', 'Data Science', 'Developer Tools',
        'Digital Design', 'Open Source', 'Robotics', 'Smart Cities', 'Startup Technology',
    ],
    'Music' => [
        'Acoustic Music', 'Alternative Music', 'Classical Music', 'Electronic Music', 'Indie Music',
        'Jazz Music', 'Local Music', 'New Music', 'Orchestral Music', 'World Music',
    ],
    'Food' => [
        'Artisan Food', 'Bengaluru Food', 'Coastal Food', 'Farm Fresh Food', 'Global Food',
        'Healthy Food', 'Home Cooking', 'Plant Based Food', 'Street Food', 'Traditional Food',
    ],
    'Business' => [
        'Business Growth', 'Creative Business', 'Early Stage Business', 'Founder Stories', 'Future Work',
        'Leadership', 'Local Business', 'Product Strategy', 'Social Enterprise', 'Women in Business',
    ],
    'Health' => [
        'Active Living', 'Community Health', 'Everyday Fitness', 'Healthy Habits', 'Mindful Living',
        'Nutrition', 'Outdoor Wellness', 'Preventive Health', 'Stress Management', 'Yoga Practice',
    ],
    'Arts' => [
        'Ceramic Arts', 'Creative Writing', 'Digital Arts', 'Drawing Arts', 'Film Arts',
        'Local Arts', 'Modern Arts', 'Photography Arts', 'Public Arts', 'Textile Arts',
    ],
    'Sports' => [
        'Badminton Sports', 'Basketball Sports', 'Cycling Sports', 'Football Sports', 'Running Sports',
        'Swimming Sports', 'Table Tennis Sports', 'Tennis Sports', 'Volleyball Sports', 'Walking Sports',
    ],
    'Education' => [
        'Career Learning', 'Coding Education', 'Design Education', 'Financial Education', 'Language Learning',
        'Lifelong Learning', 'Math Education', 'Science Education', 'Teaching Education', 'Writing Education',
    ],
    'Other' => [
        'Animal Care', 'Book Community', 'Climate Action', 'Community Gardening', 'Culture Exchange',
        'Family Activities', 'Local Heritage', 'Neighborhood Meetup', 'Public Service', 'Volunteer Community',
    ],
];

$formats = ['Festival', 'Forum', 'Showcase', 'Workshop'];
$venues = [
    ['Cubbon Park', 12.9763, 77.5929],
    ['Palace Grounds', 13.0067, 77.5928],
    ['KTPO Convention Centre, Whitefield', 12.9698, 77.7500],
    ['IISc Campus, Malleshwaram', 13.0219, 77.5671],
    ['Bangalore International Centre, Domlur', 12.9608, 77.6412],
    ['Jayamahal Palace Hotel, Jayamahal', 13.0036, 77.5944],
    ['St. Josephs University, Lalbagh Road', 12.9622, 77.6010],
    ['Indiranagar Social, Indiranagar', 12.9784, 77.6408],
    ['NIMHANS Convention Centre, Wilson Garden', 12.9421, 77.5962],
    ['Bengaluru International Exhibition Centre, Madavara', 13.0590, 77.4730],
    ['Ranga Shankara, JP Nagar', 12.9077, 77.5850],
    ['The Humming Tree, Indiranagar', 12.9787, 77.6402],
    ['Freedom Park, Gandhi Nagar', 12.9786, 77.5780],
    ['Lalbagh Botanical Garden, Mavalli', 12.9507, 77.5848],
    ['Bangalore Creative Circus, Yeshwanthpur', 13.0184, 77.5550],
];

$images = [
    'Technology' => [
        'https://images.unsplash.com/photo-1540575467063-178a50c2df87?w=900&q=80',
        'https://images.unsplash.com/photo-1591115765373-5207764f72e7?w=900&q=80',
        'https://images.unsplash.com/photo-1519389950473-47ba0277781c?w=900&q=80',
    ],
    'Music' => [
        'https://images.unsplash.com/photo-1501386761578-eaa54b3538c8?w=900&q=80',
        'https://images.unsplash.com/photo-1468359601543-843bfaef291a?w=900&q=80',
        'https://images.unsplash.com/photo-1514525253161-7a46d19cd819?w=900&q=80',
    ],
    'Food' => [
        'https://images.unsplash.com/photo-1555939594-58d7cb561ad1?w=900&q=80',
        'https://images.unsplash.com/photo-1567620905732-2d1ec7ab7445?w=900&q=80',
        'https://images.unsplash.com/photo-1504674900247-0877df9cc836?w=900&q=80',
    ],
    'Business' => [
        'https://images.unsplash.com/photo-1552581234-26160f608093?w=900&q=80',
        'https://images.unsplash.com/photo-1521737604893-d14cc237f11d?w=900&q=80',
        'https://images.unsplash.com/photo-1556761175-4b46a572b786?w=900&q=80',
    ],
    'Health' => [
        'https://images.unsplash.com/photo-1571019614242-c5c5dee9f50b?w=900&q=80',
        'https://images.unsplash.com/photo-1544367567-0f2fcb009e0b?w=900&q=80',
        'https://images.unsplash.com/photo-1506126613408-eca07ce68773?w=900&q=80',
    ],
    'Arts' => [
        'https://images.unsplash.com/photo-1460661419201-fd4cecdf8a8b?w=900&q=80',
        'https://images.unsplash.com/photo-1513364776144-60967b0f800f?w=900&q=80',
        'https://images.unsplash.com/photo-1547826039-bfc35e0f1ea8?w=900&q=80',
    ],
    'Sports' => [
        'https://images.unsplash.com/photo-1461896836934-ffe607ba8211?w=900&q=80',
        'https://images.unsplash.com/photo-1530549387789-4c1017266635?w=900&q=80',
        'https://images.unsplash.com/photo-1504280390367-361c6d9f38f4?w=900&q=80',
    ],
    'Education' => [
        'https://images.unsplash.com/photo-1427504494785-3a9ca7044f45?w=900&q=80',
        'https://images.unsplash.com/photo-1503676260728-1c00da094a0b?w=900&q=80',
        'https://images.unsplash.com/photo-1524178232363-1fb2b075b655?w=900&q=80',
    ],
    'Other' => [
        'https://images.unsplash.com/photo-1492684223066-81342ee5ff30?w=900&q=80',
        'https://images.unsplash.com/photo-1529543544282-ea669407fca3?w=900&q=80',
    ],
];

$insertEvent = $pdo->prepare(
    'INSERT INTO events (created_by,title,slug,description,category,venue,latitude,longitude,starts_at,ticket_price,capacity,status)
     VALUES (:created_by,:title,:slug,:description,:category,:venue,:latitude,:longitude,:starts_at,:ticket_price,:capacity,\'draft\')'
);
$insertImage = $pdo->prepare(
    'INSERT INTO event_images (event_id,path,mime_type,size_bytes,is_primary)
     VALUES (:event_id,:path,\'image/jpeg\',0,1)'
);
$startDate = new DateTimeImmutable('2027-01-01 09:00:00');

$pdo->beginTransaction();
try {
    for ($number = 1; $number <= 300; $number++) {
        $categoryIndex = ($number - 1) % count($categories);
        $category = array_keys($categories)[$categoryIndex];
        $topics = $categories[$category];
        $topic = $topics[intdiv($number - 1, count($categories)) % count($topics)];
        $format = $formats[intdiv($number - 1, count($categories) * count($topics)) % count($formats)];
        $venue = $venues[($number - 1) % count($venues)];
        $title = sprintf('Bengaluru %s %s 2027 %03d', $topic, $format, $number);
        $description = sprintf(
            '%s: Spearing Events demo listing %03d. This fictional sample event explores %s through a %s programme in Bengaluru. The sample schedule includes an opening session, guided activities, community networking, and practical information for attendees. This draft is demonstration data and is not an actual event.',
            $marker,
            $number,
            strtolower($topic),
            strtolower($format)
        );
        $startsAt = $startDate
            ->modify('+' . (($number - 1) * 3) . ' days')
            ->modify('+' . (($number % 8) * 60) . ' minutes')
            ->format('Y-m-d H:i:s');
        $price = $number % 6 === 0 ? 0 : (($number * 137) % 1800) + 199;
        $capacity = 40 + (($number * 37) % 460);
        $imageList = $images[$category];
        $image = $imageList[($number - 1) % count($imageList)];

        $insertEvent->execute([
            'created_by' => $adminId,
            'title' => $title,
            'slug' => sprintf('event-spear-demo-%03d', $number),
            'description' => $description,
            'category' => $category,
            'venue' => $venue[0],
            'latitude' => $venue[1] + (($number % 13) - 6) * 0.001,
            'longitude' => $venue[2] + (($number % 11) - 5) * 0.001,
            'starts_at' => $startsAt,
            'ticket_price' => $price,
            'capacity' => $capacity,
        ]);
        $insertImage->execute(['event_id' => $pdo->lastInsertId(), 'path' => $image]);
    }
    $pdo->commit();
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $error;
}

fwrite(STDOUT, "Created 300 fictional draft events with category images. They are not visible to customers until published.\n");

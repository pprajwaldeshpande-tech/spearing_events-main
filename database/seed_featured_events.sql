USE eventspot;

SET @admin_id := (SELECT id FROM users WHERE role = 'admin' AND status = 'active' ORDER BY id LIMIT 1);

INSERT INTO events (created_by, title, slug, description, category, venue, latitude, longitude, starts_at, ticket_price, capacity, status)
SELECT @admin_id, 'Bengaluru Food Festival 2026', 'bengaluru-food-festival-2026-featured', 'Taste regional favourites, chef-led workshops, live cooking demos, and desserts from more than 40 Bengaluru food makers.', 'Food', 'Palace Grounds, Bengaluru', 13.0067000, 77.5928000, '2026-11-08 11:00:00', 499.00, 350, 'published'
WHERE @admin_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM events WHERE slug = 'bengaluru-food-festival-2026-featured');

SET @event_id := (SELECT id FROM events WHERE slug = 'bengaluru-food-festival-2026-featured');
INSERT INTO event_images (event_id, path, mime_type, size_bytes, is_primary)
SELECT @event_id, 'https://images.unsplash.com/photo-1555939594-58d7cb561ad1?w=900&q=80', 'image/jpeg', 0, 1
WHERE @event_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM event_images WHERE event_id = @event_id);

INSERT INTO events (created_by, title, slug, description, category, venue, latitude, longitude, starts_at, ticket_price, capacity, status)
SELECT @admin_id, 'Sunset Music Carnival 2026', 'sunset-music-carnival-2026-featured', 'A lively evening of indie bands, acoustic sets, food stalls, and sunset performances by the lake.', 'Music', 'Jayamahal Palace Grounds, Bengaluru', 13.0036000, 77.5944000, '2026-11-14 16:00:00', 799.00, 600, 'published'
WHERE @admin_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM events WHERE slug = 'sunset-music-carnival-2026-featured');

SET @event_id := (SELECT id FROM events WHERE slug = 'sunset-music-carnival-2026-featured');
INSERT INTO event_images (event_id, path, mime_type, size_bytes, is_primary)
SELECT @event_id, 'https://images.unsplash.com/photo-1501386761578-eaa54b3538c8?w=900&q=80', 'image/jpeg', 0, 1
WHERE @event_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM event_images WHERE event_id = @event_id);

INSERT INTO events (created_by, title, slug, description, category, venue, latitude, longitude, starts_at, ticket_price, capacity, status)
SELECT @admin_id, 'Startup Founders Meetup 2026', 'startup-founders-meetup-2026-featured', 'Meet investors and Bengaluru startup founders for pitch feedback, expert panels, and focused networking sessions.', 'Business', 'Bangalore International Centre, Domlur', 12.9608000, 77.6412000, '2026-11-21 10:00:00', 299.00, 180, 'published'
WHERE @admin_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM events WHERE slug = 'startup-founders-meetup-2026-featured');

SET @event_id := (SELECT id FROM events WHERE slug = 'startup-founders-meetup-2026-featured');
INSERT INTO event_images (event_id, path, mime_type, size_bytes, is_primary)
SELECT @event_id, 'https://images.unsplash.com/photo-1556761175-4b46a572b786?w=900&q=80', 'image/jpeg', 0, 1
WHERE @event_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM event_images WHERE event_id = @event_id);

INSERT INTO events (created_by, title, slug, description, category, venue, latitude, longitude, starts_at, ticket_price, capacity, status)
SELECT @admin_id, 'Bengaluru Photography Walk 2026', 'bengaluru-photography-walk-2026-featured', 'Explore street photography in Bengaluru with a professional photographer, practical composition tips, and a group photo review.', 'Arts', 'Cubbon Park, Bengaluru', 12.9763000, 77.5929000, '2026-11-29 07:00:00', 399.00, 75, 'published'
WHERE @admin_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM events WHERE slug = 'bengaluru-photography-walk-2026-featured');

SET @event_id := (SELECT id FROM events WHERE slug = 'bengaluru-photography-walk-2026-featured');
INSERT INTO event_images (event_id, path, mime_type, size_bytes, is_primary)
SELECT @event_id, 'https://images.unsplash.com/photo-1516035069371-29a1b244cc32?w=900&q=80', 'image/jpeg', 0, 1
WHERE @event_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM event_images WHERE event_id = @event_id);

INSERT INTO events (created_by, title, slug, description, category, venue, latitude, longitude, starts_at, ticket_price, capacity, status)
SELECT @admin_id, 'Weekend Yoga and Wellness 2026', 'weekend-yoga-wellness-2026-featured', 'Start your weekend with guided yoga, breathing exercises, nutrition guidance, and outdoor mindfulness sessions.', 'Health', 'Lalbagh Botanical Garden, Bengaluru', 12.9507000, 77.5848000, '2026-12-06 06:30:00', 249.00, 120, 'published'
WHERE @admin_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM events WHERE slug = 'weekend-yoga-wellness-2026-featured');

SET @event_id := (SELECT id FROM events WHERE slug = 'weekend-yoga-wellness-2026-featured');
INSERT INTO event_images (event_id, path, mime_type, size_bytes, is_primary)
SELECT @event_id, 'https://images.unsplash.com/photo-1544367567-0f2fcb009e0b?w=900&q=80', 'image/jpeg', 0, 1
WHERE @event_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM event_images WHERE event_id = @event_id);

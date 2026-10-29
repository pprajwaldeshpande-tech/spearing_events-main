USE eventspot;

UPDATE payment_settings SET upi_id = 'pprajwaldeshpande-1@okicici', payee_name = 'Prajwal Deshpande' WHERE id = 1;

-- Run private/create-admin.php first. This seed adds no credentials.
SET @admin_id := (SELECT id FROM users WHERE role = 'admin' AND status = 'active' ORDER BY id LIMIT 1);

INSERT INTO events (created_by, title, slug, description, category, venue, latitude, longitude, starts_at, ticket_price, capacity, status)
SELECT @admin_id, 'Bengaluru Tech Summit 2027', 'bengaluru-tech-summit-2027', 'Developers, designers, and product leaders meet for talks, workshops, and networking.', 'Technology', 'KTPO Convention Centre, Whitefield', 12.9698000, 77.7500000, '2027-02-15 09:00:00', 999.00, 200, 'published' WHERE @admin_id IS NOT NULL
UNION ALL SELECT @admin_id, 'Cubbon Park Music Fest 2027', 'cubbon-park-music-fest-2027', 'Live independent music with local food and community activities.', 'Music', 'Cubbon Park, MG Road', 12.9763000, 77.5929000, '2027-03-20 17:00:00', 499.00, 500, 'published' WHERE @admin_id IS NOT NULL
UNION ALL SELECT @admin_id, 'Python and AI Workshop 2027', 'python-ai-workshop-2027', 'A practical workshop covering Python, data science, and introductory machine learning.', 'Education', 'IISc Campus, Malleshwaram', 13.0219000, 77.5671000, '2027-04-22 10:00:00', 1499.00, 60, 'published' WHERE @admin_id IS NOT NULL;
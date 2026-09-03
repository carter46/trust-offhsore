<?php
/**
 * Seed the CMS pages (our-services, faq) if the pages table is empty.
 * This ensures a fresh deploy has at least the default content.
 */

return [
    'id' => '2026_09_03_default_pages',
    'description' => 'Seed default CMS pages if pages table is empty',
    'up' => function (mysqli $conn) {
        $result = $conn->query("SELECT COUNT(*) AS cnt FROM pages");
        $row = $result ? $result->fetch_assoc() : null;
        if ($row && (int) $row['cnt'] > 0) {
            return; // pages already seeded
        }

        $pages = [
            [
                'slug' => 'our-services',
                'title' => 'Our Services',
                'content' => json_encode([
                    'hero' => [
                        'heading' => 'Our Services',
                        'subtitle' => 'We Provide you with the best services out there!',
                        'description' => 'We offer dependable shipping solutions for clients who need to move goods locally and internationally.',
                        'track_shipment_text' => 'Track Shipment',
                        'track_shipment_link' => '/track.php',
                    ],
                    'section_title' => 'What We Offer!',
                    'services' => [],
                ], JSON_UNESCAPED_SLASHES),
            ],
            [
                'slug' => 'faq',
                'title' => 'FAQ',
                'content' => json_encode([
                    'hero' => [
                        'heading' => 'Frequently Asked Questions',
                        'subtitle' => 'Common questions about {company}',
                    ],
                    'items' => [
                        ['question' => 'How can I track my shipment?', 'answer' => 'Enter your tracking number on our tracking page for real-time updates.'],
                        ['question' => 'Does {company} offer international shipping?', 'answer' => 'Yes, we ship worldwide with customs handling included.'],
                        ['question' => 'How do I contact support?', 'answer' => 'Use the contact form on our website or email us.'],
                    ],
                ], JSON_UNESCAPED_SLASHES),
            ],
        ];

        $stmt = $conn->prepare("INSERT INTO pages (page_slug, page_title, content) VALUES (?, ?, ?)");
        foreach ($pages as $page) {
            $stmt->bind_param('sss', $page['slug'], $page['title'], $page['content']);
            $stmt->execute();
        }
        $stmt->close();
    },
];

<?php
include __DIR__ . '/includes/admin-header.php';

// Define all public-facing pages (excluding admin, api, includes, etc.)
// Note: Dynamic/functional pages are excluded:
// - track-result.php, track-result-map.php, track.php (dynamic, show data based on URL params)
// - shipping.php, contact.php (functional forms, use getSetting() not pages table)
$allPageFiles = [
    'homepage' => ['file' => 'index.php', 'title' => 'Homepage'],
    'our-services' => ['file' => 'our-services.php', 'title' => 'Our Services'],
    'faq' => ['file' => 'faq.php', 'title' => 'FAQ'],
];

// Get all pages from database
$dbPages = [];
$result = $conn->query("SELECT id, page_slug, page_title, updated_at FROM pages ORDER BY page_title ASC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $dbPages[$row['page_slug']] = $row;
    }
    $result->free();
}

// Merge database pages with file-based pages
$allPages = [];
foreach ($allPageFiles as $slug => $pageInfo) {
    if (isset($dbPages[$slug])) {
        // Page exists in database
        $allPages[] = array_merge($dbPages[$slug], ['in_db' => true, 'file_exists' => file_exists(__DIR__ . '/../' . $pageInfo['file'])]);
    } else {
        // Page exists as file but not in database
        $allPages[] = [
            'id' => null,
            'page_slug' => $slug,
            'page_title' => $pageInfo['title'],
            'updated_at' => null,
            'in_db' => false,
            'file_exists' => file_exists(__DIR__ . '/../' . $pageInfo['file'])
        ];
    }
}

// Sort by title
usort($allPages, function($a, $b) {
    return strcmp($a['page_title'], $b['page_title']);
});
?>
<div class="mb-8">
    <h1 class="text-3xl font-light text-gray-800 dark:text-white mb-2">Pages</h1>
    <p class="text-gray-600 dark:text-gray-400">Manage website pages and content</p>
</div>

<div class="bg-white dark:bg-surface-dark rounded-lg shadow overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
        <h2 class="text-xl font-bold text-gray-800 dark:text-white">All Pages</h2>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50 dark:bg-gray-800">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Page Title</th>
                    <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Slug</th>
                    <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Last Updated</th>
                    <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                <?php if (empty($allPages)): ?>
                    <tr>
                        <td colspan="4" class="px-6 py-8 text-center text-gray-500">No pages found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($allPages as $page): ?>
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <div class="text-sm font-medium text-gray-800 dark:text-gray-200"><?php echo htmlspecialchars($page['page_title']); ?></div>
                                    <?php if (!$page['in_db']): ?>
                                        <span class="px-2 py-0.5 text-xs bg-yellow-100 dark:bg-yellow-900/30 text-yellow-800 dark:text-yellow-300 rounded">Not in DB</span>
                                    <?php endif; ?>
                                    <?php if (!$page['file_exists']): ?>
                                        <span class="px-2 py-0.5 text-xs bg-red-100 dark:bg-red-900/30 text-red-800 dark:text-red-300 rounded">File Missing</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-gray-600 dark:text-gray-400 font-mono"><?php echo htmlspecialchars($page['page_slug']); ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                <?php if ($page['updated_at']): ?>
                                    <?php echo formatDate($page['updated_at']); ?>
                                <?php else: ?>
                                    <span class="text-gray-400">Never</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <?php if ($page['in_db']): ?>
                                    <a href="/admin/edit-page.php?slug=<?php echo urlencode($page['page_slug']); ?>"
                                       class="inline-flex items-center px-3 py-1.5 rounded border border-primary text-primary hover:bg-primary hover:text-white transition-colors font-bold">
                                        Edit
                                    </a>
                                <?php else: ?>
                                    <button onclick="createPageEntry('<?php echo htmlspecialchars($page['page_slug'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($page['page_title'], ENT_QUOTES); ?>')"
                                            class="inline-flex items-center px-3 py-1.5 rounded border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors font-bold">
                                        Add to DB
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
async function createPageEntry(slug, title) {
    if (!confirm(`Create database entry for "${title}"? This will allow you to edit its content.`)) {
        return;
    }
    
    try {
        const response = await fetch('/api/pages.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'create',
                slug: slug,
                title: title,
                content: {}
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('Page entry created successfully!');
            location.reload();
        } else {
            alert('Error: ' + (data.error || 'Failed to create page entry'));
        }
    } catch (error) {
        alert('Error: ' + error.message);
    }
}
</script>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>


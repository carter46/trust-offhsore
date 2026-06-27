<?php
include __DIR__ . '/includes/admin-header.php';

$success = '';
$error = '';
$slug = isset($_GET['slug']) ? sanitizeInput($_GET['slug']) : 'homepage';

// Get page data
$page = getPageContent($slug);
if (!$page) {
    $error = 'Page not found.';
    include __DIR__ . '/includes/admin-footer.php';
    exit;
}

$content = $page['content'] ?? [];
$assetDir = __DIR__ . '/../asset/';

// Determine page type
$pageType = 'homepage'; // default
if ($slug === 'our-services') {
    $pageType = 'our-services';
} elseif ($slug === 'faq') {
    $pageType = 'faq';
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newContent = [];
    
    // Helper function to handle image upload
    function handleImageUpload($fileKey, $currentPath, $assetDir, $prefix = 'page') {
        if (isset($_FILES[$fileKey]) && $_FILES[$fileKey]['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES[$fileKey];
            $allowedTypes = ['image/png', 'image/jpeg', 'image/jpg', 'image/gif', 'image/webp', 'image/svg+xml'];
            $maxSize = 5 * 1024 * 1024; // 5MB
            
            if (in_array($file['type'], $allowedTypes) && $file['size'] <= $maxSize) {
                $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $newFileName = $prefix . '_' . time() . '_' . rand(1000, 9999) . '.' . $extension;
                $targetPath = $assetDir . $newFileName;
                
                if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                    // Delete old file if it was uploaded (starts with prefix)
                    if ($currentPath && strpos(basename($currentPath), $prefix . '_') === 0) {
                        $oldPath = __DIR__ . '/..' . $currentPath;
                        if (file_exists($oldPath)) {
                            @unlink($oldPath);
                        }
                    }
                    return '/asset/' . $newFileName;
                }
            }
        }
        return $currentPath; // Return current path if upload failed
    }
    
    // Handle form submission based on page type
    if ($pageType === 'homepage') {
        // Homepage Section
    $newContent['hero'] = [
        'bg_image' => handleImageUpload('hero_bg_image', $content['hero']['bg_image'] ?? '/asset/hero-sectionbg.jpg', $assetDir, 'hero_bg'),
        'heading' => sanitizeInput($_POST['hero_heading'] ?? ''),
        'tabs' => [
            [
                'text' => sanitizeInput($_POST['hero_tab1_text'] ?? ''),
                'link' => sanitizeInput($_POST['hero_tab1_link'] ?? '#'),
                'icon' => sanitizeInput($_POST['hero_tab1_icon'] ?? 'calculate')
            ],
            [
                'text' => sanitizeInput($_POST['hero_tab2_text'] ?? ''),
                'link' => sanitizeInput($_POST['hero_tab2_link'] ?? '#'),
                'icon' => sanitizeInput($_POST['hero_tab2_icon'] ?? 'inventory_2')
            ],
            [
                'text' => sanitizeInput($_POST['hero_tab3_text'] ?? ''),
                'link' => sanitizeInput($_POST['hero_tab3_link'] ?? '#'),
                'icon' => sanitizeInput($_POST['hero_tab3_icon'] ?? 'place')
            ]
        ],
        'track_button_text' => sanitizeInput($_POST['hero_track_button_text'] ?? 'Track')
    ];
    
    // Service Icons
    $newContent['service_icons'] = [];
    for ($i = 1; $i <= 5; $i++) {
        $currentImage = $content['service_icons'][$i-1]['image'] ?? '/asset/icon' . $i . '.png';
        $newContent['service_icons'][] = [
            'image' => handleImageUpload('service_icon' . $i, $currentImage, $assetDir, 'service_icon' . $i),
            'text' => sanitizeInput($_POST['service_icon' . $i . '_text'] ?? ''),
            'link' => sanitizeInput($_POST['service_icon' . $i . '_link'] ?? '#')
        ];
    }
    
    // Why Ship Section
    $newContent['why_ship'] = [
        'image' => handleImageUpload('why_ship_image', $content['why_ship']['image'] ?? '/asset/snowman.jpg', $assetDir, 'why_ship'),
        'heading' => sanitizeInput($_POST['why_ship_heading'] ?? ''),
        'features' => [
            [
                'heading' => sanitizeInput($_POST['why_ship_feature1_heading'] ?? ''),
                'text' => sanitizeInput($_POST['why_ship_feature1_text'] ?? '')
            ],
            [
                'heading' => sanitizeInput($_POST['why_ship_feature2_heading'] ?? ''),
                'text' => sanitizeInput($_POST['why_ship_feature2_text'] ?? '')
            ],
            [
                'heading' => sanitizeInput($_POST['why_ship_feature3_heading'] ?? ''),
                'text' => sanitizeInput($_POST['why_ship_feature3_text'] ?? '')
            ],
            [
                'heading' => sanitizeInput($_POST['why_ship_feature4_heading'] ?? ''),
                'text' => sanitizeInput($_POST['why_ship_feature4_text'] ?? '')
            ]
        ],
        'button_text' => sanitizeInput($_POST['why_ship_button_text'] ?? ''),
        'button_link' => sanitizeInput($_POST['why_ship_button_link'] ?? '#'),
        'footer_text' => sanitizeInput($_POST['why_ship_footer_text'] ?? '')
    ];
    
    // Business Gear Section
    $newContent['business_gear'] = [
        'heading' => sanitizeInput($_POST['business_gear_heading'] ?? ''),
        'cards' => []
    ];
    for ($i = 1; $i <= 3; $i++) {
        $currentImage = $content['business_gear']['cards'][$i-1]['image'] ?? '';
        $newContent['business_gear']['cards'][] = [
            'image' => handleImageUpload('business_gear_card' . $i . '_image', $currentImage, $assetDir, 'business_gear' . $i),
            'heading' => sanitizeInput($_POST['business_gear_card' . $i . '_heading'] ?? ''),
            'text' => sanitizeInput($_POST['business_gear_card' . $i . '_text'] ?? ''),
            'link_text' => sanitizeInput($_POST['business_gear_card' . $i . '_link_text'] ?? ''),
            'link' => sanitizeInput($_POST['business_gear_card' . $i . '_link'] ?? '#')
        ];
    }
    
    // Shipping Supplies Section
    $currentSuppliesImage = $content['shipping_supplies']['image'] ?? '/asset/POD_Feature_ShippingSupplies_727x463.jpg';
    $newContent['shipping_supplies'] = [
        'image' => handleImageUpload('shipping_supplies_image', $currentSuppliesImage, $assetDir, 'shipping_supplies'),
        'heading' => sanitizeInput($_POST['shipping_supplies_heading'] ?? ''),
        'text' => sanitizeInput($_POST['shipping_supplies_text'] ?? ''),
        'link_text' => sanitizeInput($_POST['shipping_supplies_link_text'] ?? ''),
        'link' => sanitizeInput($_POST['shipping_supplies_link'] ?? '#')
    ];
    
    // Ship Track Return Section
    $newContent['ship_track_return'] = [
        'heading' => sanitizeInput($_POST['ship_track_return_heading'] ?? ''),
        'items' => [],
        'footer_items' => [],
        'footer_disclaimer' => sanitizeInput($_POST['ship_track_return_footer_disclaimer'] ?? '')
    ];
    
    // Items (3)
    for ($i = 1; $i <= 3; $i++) {
        $currentImage = $content['ship_track_return']['items'][$i-1]['image'] ?? '';
        $newContent['ship_track_return']['items'][] = [
            'image' => handleImageUpload('ship_track_return_item' . $i . '_image', $currentImage, $assetDir, 'ship_track_return' . $i),
            'heading' => sanitizeInput($_POST['ship_track_return_item' . $i . '_heading'] ?? ''),
            'text' => sanitizeInput($_POST['ship_track_return_item' . $i . '_text'] ?? ''),
            'link_text' => sanitizeInput($_POST['ship_track_return_item' . $i . '_link_text'] ?? ''),
            'link' => sanitizeInput($_POST['ship_track_return_item' . $i . '_link'] ?? '#')
        ];
    }
    
    // Footer Items (2)
    for ($i = 1; $i <= 2; $i++) {
        $newContent['ship_track_return']['footer_items'][] = [
            'heading' => sanitizeInput($_POST['ship_track_return_footer' . $i . '_heading'] ?? ''),
            'text' => sanitizeInput($_POST['ship_track_return_footer' . $i . '_text'] ?? ''),
            'link' => sanitizeInput($_POST['ship_track_return_footer' . $i . '_link'] ?? '#')
        ];
    }
    
    } elseif ($pageType === 'our-services') {
        // Our Services Page
        $newContent['hero'] = [
            'heading' => sanitizeInput($_POST['hero_heading'] ?? ''),
            'subtitle' => sanitizeInput($_POST['hero_subtitle'] ?? ''),
            'description' => sanitizeInput($_POST['hero_description'] ?? ''),
            'track_shipment_text' => sanitizeInput($_POST['hero_track_shipment_text'] ?? 'Track Shipment'),
            'track_shipment_link' => sanitizeInput($_POST['hero_track_shipment_link'] ?? '/track.php')
        ];
        $newContent['section_title'] = sanitizeInput($_POST['section_title'] ?? '');
        $newContent['services'] = [];
        
        // Process 8 services
        for ($i = 1; $i <= 8; $i++) {
            $currentImage = $content['services'][$i-1]['image'] ?? '';
            $keyFeatures = [];
            // Handle features as newline-separated text
            if (isset($_POST['service' . $i . '_features_text'])) {
                $featuresText = $_POST['service' . $i . '_features_text'];
                $featuresLines = explode("\n", $featuresText);
                foreach ($featuresLines as $feature) {
                    $feature = trim($feature);
                    if (!empty($feature)) {
                        $keyFeatures[] = sanitizeInput($feature);
                    }
                }
            }
            $newContent['services'][] = [
                'image' => handleImageUpload('service' . $i . '_image', $currentImage, $assetDir, 'service' . $i),
                'title' => sanitizeInput($_POST['service' . $i . '_title'] ?? ''),
                'description' => sanitizeInput($_POST['service' . $i . '_description'] ?? ''),
                'key_features' => $keyFeatures,
                'cta_text' => sanitizeInput($_POST['service' . $i . '_cta_text'] ?? ''),
                'cta_link' => sanitizeInput($_POST['service' . $i . '_cta_link'] ?? '#')
            ];
        }
        
        $newContent['download_section'] = [
            'heading' => sanitizeInput($_POST['download_heading'] ?? ''),
            'text' => sanitizeInput($_POST['download_text'] ?? ''),
            'button_text' => sanitizeInput($_POST['download_button_text'] ?? ''),
            'button_link' => sanitizeInput($_POST['download_button_link'] ?? '#')
        ];
        
    } elseif ($pageType === 'faq') {
        // FAQ Page
        $newContent['hero'] = [
            'heading' => sanitizeInput($_POST['hero_heading'] ?? ''),
            'subtitle' => sanitizeInput($_POST['hero_subtitle'] ?? '')
        ];
        $newContent['items'] = [];
        
        // Process FAQ items
        if (isset($_POST['faq_question']) && is_array($_POST['faq_question'])) {
            $questions = $_POST['faq_question'];
            $answers = $_POST['faq_answer'] ?? [];
            foreach ($questions as $index => $question) {
                $question = trim($question);
                $answer = isset($answers[$index]) ? trim($answers[$index]) : '';
                if (!empty($question) || !empty($answer)) {
                    $newContent['items'][] = [
                        'question' => sanitizeInput($question),
                        'answer' => sanitizeInput($answer)
                    ];
                }
            }
        }
    }
    
    // Save to database
    if (updatePageContent($slug, $page['page_title'], $newContent)) {
        $success = 'Page content saved successfully!';
        // Reload page data
        $page = getPageContent($slug);
        $content = $page['content'] ?? [];
    } else {
        $error = 'Failed to save page content.';
    }
}
?>
<div class="mb-8">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-light text-gray-800 dark:text-white mb-2">Edit Page: <?php echo htmlspecialchars($page['page_title']); ?></h1>
            <p class="text-gray-600 dark:text-gray-400">Edit homepage content, images, and links</p>
        </div>
        <a href="/admin/pages.php" class="bg-gray-300 dark:bg-gray-600 hover:bg-gray-400 dark:hover:bg-gray-700 text-gray-800 dark:text-white font-bold py-2 px-4 rounded transition-colors">
            Back to Pages
        </a>
    </div>
</div>

<?php if ($success): ?>
    <div class="bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-200 px-4 py-3 rounded mb-6">
        <?php echo htmlspecialchars($success); ?>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-200 px-4 py-3 rounded mb-6">
        <?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<form method="POST" action="" enctype="multipart/form-data" class="space-y-8">
    <?php if ($pageType === 'homepage'): ?>
    <!-- Homepage Editor -->
    <!-- Hero Section -->
    <div class="bg-white dark:bg-surface-dark rounded-lg shadow p-6">
        <h2 class="text-xl font-bold text-gray-800 dark:text-white mb-6">Hero Section</h2>
        
        <div class="space-y-6">
            <!-- Hero BG Image -->
            <div>
                <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Hero Background Image</label>
                <div class="mb-2">
                    <?php if (!empty($content['hero']['bg_image'])): ?>
                        <img src="<?php echo htmlspecialchars($content['hero']['bg_image']); ?>" alt="Current BG" class="h-32 w-auto border border-gray-300 dark:border-gray-600 rounded p-1">
                    <?php endif; ?>
                </div>
                <input type="file" name="hero_bg_image" accept="image/png,image/jpeg,image/jpg,image/gif,image/webp,image/svg+xml"
                       class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary">
                <p class="mt-1 text-xs text-gray-500">Current: <?php echo htmlspecialchars($content['hero']['bg_image'] ?? 'Not set'); ?></p>
            </div>
            
            <!-- Hero Heading -->
            <div>
                <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="hero_heading">Hero Heading</label>
                <input type="text" id="hero_heading" name="hero_heading" 
                       value="<?php echo htmlspecialchars($content['hero']['heading'] ?? ''); ?>"
                       class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary">
            </div>
            
            <!-- Hero Tabs -->
            <div>
                <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-4">Hero Tabs (3 tabs)</label>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <?php for ($i = 1; $i <= 3; $i++): 
                        $tab = $content['hero']['tabs'][$i-1] ?? ['text' => '', 'link' => '#', 'icon' => ''];
                    ?>
                        <div class="border border-gray-300 dark:border-gray-600 rounded p-4">
                            <h4 class="text-sm font-bold mb-3">Tab <?php echo $i; ?></h4>
                            <div class="space-y-3">
                                <div>
                                    <label class="block text-xs font-bold text-gray-600 dark:text-gray-400 mb-1">Text</label>
                                    <input type="text" name="hero_tab<?php echo $i; ?>_text" 
                                           value="<?php echo htmlspecialchars($tab['text']); ?>"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white text-sm focus:ring-2 focus:ring-primary">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-600 dark:text-gray-400 mb-1">Link</label>
                                    <input type="text" name="hero_tab<?php echo $i; ?>_link" 
                                           value="<?php echo htmlspecialchars($tab['link']); ?>"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white text-sm focus:ring-2 focus:ring-primary">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-600 dark:text-gray-400 mb-1">Icon (Material Icons name)</label>
                                    <input type="text" name="hero_tab<?php echo $i; ?>_icon" 
                                           value="<?php echo htmlspecialchars($tab['icon']); ?>"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white text-sm focus:ring-2 focus:ring-primary">
                                </div>
                            </div>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>
            
            <!-- Track Button Text -->
            <div>
                <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="hero_track_button_text">Track Button Text</label>
                <input type="text" id="hero_track_button_text" name="hero_track_button_text" 
                       value="<?php echo htmlspecialchars($content['hero']['track_button_text'] ?? 'Track'); ?>"
                       class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary">
            </div>
        </div>
    </div>
    
    <!-- Service Icons Section -->
    <div class="bg-white dark:bg-surface-dark rounded-lg shadow p-6">
        <h2 class="text-xl font-bold text-gray-800 dark:text-white mb-6">Service Icons Section</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php for ($i = 1; $i <= 5; $i++): 
                $icon = $content['service_icons'][$i-1] ?? ['image' => '/asset/icon' . $i . '.png', 'text' => '', 'link' => '#'];
            ?>
                <div class="border border-gray-300 dark:border-gray-600 rounded p-4">
                    <h4 class="text-sm font-bold mb-3">Icon <?php echo $i; ?></h4>
                    <div class="space-y-3">
                        <div>
                            <label class="block text-xs font-bold text-gray-600 dark:text-gray-400 mb-1">Image</label>
                            <?php if (!empty($icon['image'])): ?>
                                <img src="<?php echo htmlspecialchars($icon['image']); ?>" alt="Icon <?php echo $i; ?>" class="h-12 w-12 mb-2 border border-gray-300 dark:border-gray-600 rounded p-1">
                            <?php endif; ?>
                            <input type="file" name="service_icon<?php echo $i; ?>" accept="image/png,image/jpeg,image/jpg,image/gif,image/webp,image/svg+xml"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white text-sm focus:ring-2 focus:ring-primary">
                            <p class="mt-1 text-xs text-gray-500">Current: <?php echo htmlspecialchars($icon['image'] ?? 'Not set'); ?></p>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-600 dark:text-gray-400 mb-1">Text (use \n for line breaks)</label>
                            <textarea name="service_icon<?php echo $i; ?>_text" rows="2"
                                      class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white text-sm focus:ring-2 focus:ring-primary"><?php echo htmlspecialchars($icon['text']); ?></textarea>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-600 dark:text-gray-400 mb-1">Link</label>
                            <input type="text" name="service_icon<?php echo $i; ?>_link" 
                                   value="<?php echo htmlspecialchars($icon['link']); ?>"
                                   class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white text-sm focus:ring-2 focus:ring-primary">
                        </div>
                    </div>
                </div>
            <?php endfor; ?>
        </div>
    </div>
    
    <!-- Why Ship Section -->
    <div class="bg-white dark:bg-surface-dark rounded-lg shadow p-6">
        <h2 class="text-xl font-bold text-gray-800 dark:text-white mb-6">Why Ship Section</h2>
        <div class="space-y-6">
            <div>
                <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Section Image</label>
                <div class="mb-2">
                    <?php if (!empty($content['why_ship']['image'])): ?>
                        <img src="<?php echo htmlspecialchars($content['why_ship']['image']); ?>" alt="Why Ship" class="h-48 w-auto border border-gray-300 dark:border-gray-600 rounded p-1">
                    <?php endif; ?>
                </div>
                <input type="file" name="why_ship_image" accept="image/png,image/jpeg,image/jpg,image/gif,image/webp,image/svg+xml"
                       class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary">
                <p class="mt-1 text-xs text-gray-500">Current: <?php echo htmlspecialchars($content['why_ship']['image'] ?? 'Not set'); ?></p>
            </div>
            
            <div>
                <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="why_ship_heading">Heading (use {company} for company name)</label>
                <input type="text" id="why_ship_heading" name="why_ship_heading" 
                       value="<?php echo htmlspecialchars($content['why_ship']['heading'] ?? ''); ?>"
                       class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary">
            </div>
            
            <div>
                <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-4">Features (4 features)</label>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <?php for ($i = 1; $i <= 4; $i++): 
                        $feature = $content['why_ship']['features'][$i-1] ?? ['heading' => '', 'text' => ''];
                    ?>
                        <div class="border border-gray-300 dark:border-gray-600 rounded p-4">
                            <h4 class="text-sm font-bold mb-3">Feature <?php echo $i; ?></h4>
                            <div class="space-y-3">
                                <div>
                                    <label class="block text-xs font-bold text-gray-600 dark:text-gray-400 mb-1">Heading</label>
                                    <input type="text" name="why_ship_feature<?php echo $i; ?>_heading" 
                                           value="<?php echo htmlspecialchars($feature['heading']); ?>"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white text-sm focus:ring-2 focus:ring-primary">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-600 dark:text-gray-400 mb-1">Text</label>
                                    <textarea name="why_ship_feature<?php echo $i; ?>_text" rows="3"
                                              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white text-sm focus:ring-2 focus:ring-primary"><?php echo htmlspecialchars($feature['text']); ?></textarea>
                                </div>
                            </div>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="why_ship_button_text">Button Text</label>
                    <input type="text" id="why_ship_button_text" name="why_ship_button_text" 
                           value="<?php echo htmlspecialchars($content['why_ship']['button_text'] ?? ''); ?>"
                           class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary">
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="why_ship_button_link">Button Link</label>
                    <input type="text" id="why_ship_button_link" name="why_ship_button_link" 
                           value="<?php echo htmlspecialchars($content['why_ship']['button_link'] ?? '#'); ?>"
                           class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary">
                </div>
            </div>
            
            <div>
                <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="why_ship_footer_text">Footer Text (use \n for line breaks, {company} for company name)</label>
                <textarea id="why_ship_footer_text" name="why_ship_footer_text" rows="4"
                          class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary"><?php echo htmlspecialchars($content['why_ship']['footer_text'] ?? ''); ?></textarea>
            </div>
        </div>
    </div>
    
    <!-- Business Gear Section -->
    <div class="bg-white dark:bg-surface-dark rounded-lg shadow p-6">
        <h2 class="text-xl font-bold text-gray-800 dark:text-white mb-6">Business Gear Section</h2>
        <div class="space-y-6">
            <div>
                <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="business_gear_heading">Heading</label>
                <input type="text" id="business_gear_heading" name="business_gear_heading" 
                       value="<?php echo htmlspecialchars($content['business_gear']['heading'] ?? ''); ?>"
                       class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary">
            </div>
            
            <div>
                <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-4">Cards (3 cards)</label>
                <div class="space-y-6">
                    <?php for ($i = 1; $i <= 3; $i++): 
                        $card = $content['business_gear']['cards'][$i-1] ?? ['image' => '', 'heading' => '', 'text' => '', 'link_text' => '', 'link' => '#'];
                    ?>
                        <div class="border border-gray-300 dark:border-gray-600 rounded p-4">
                            <h4 class="text-sm font-bold mb-4">Card <?php echo $i; ?></h4>
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-xs font-bold text-gray-600 dark:text-gray-400 mb-1">Image</label>
                                    <?php if (!empty($card['image'])): ?>
                                        <img src="<?php echo htmlspecialchars($card['image']); ?>" alt="Card <?php echo $i; ?>" class="h-32 w-auto mb-2 border border-gray-300 dark:border-gray-600 rounded p-1">
                                    <?php endif; ?>
                                    <input type="file" name="business_gear_card<?php echo $i; ?>_image" accept="image/png,image/jpeg,image/jpg,image/gif,image/webp,image/svg+xml"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white text-sm focus:ring-2 focus:ring-primary">
                                    <p class="mt-1 text-xs text-gray-500">Current: <?php echo htmlspecialchars($card['image'] ?? 'Not set'); ?></p>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-600 dark:text-gray-400 mb-1">Heading</label>
                                    <input type="text" name="business_gear_card<?php echo $i; ?>_heading" 
                                           value="<?php echo htmlspecialchars($card['heading']); ?>"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white text-sm focus:ring-2 focus:ring-primary">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-600 dark:text-gray-400 mb-1">Text</label>
                                    <textarea name="business_gear_card<?php echo $i; ?>_text" rows="4"
                                              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white text-sm focus:ring-2 focus:ring-primary"><?php echo htmlspecialchars($card['text']); ?></textarea>
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-xs font-bold text-gray-600 dark:text-gray-400 mb-1">Link Text</label>
                                        <input type="text" name="business_gear_card<?php echo $i; ?>_link_text" 
                                               value="<?php echo htmlspecialchars($card['link_text']); ?>"
                                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white text-sm focus:ring-2 focus:ring-primary">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-gray-600 dark:text-gray-400 mb-1">Link</label>
                                        <input type="text" name="business_gear_card<?php echo $i; ?>_link" 
                                               value="<?php echo htmlspecialchars($card['link']); ?>"
                                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white text-sm focus:ring-2 focus:ring-primary">
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Shipping Supplies Section -->
    <div class="bg-white dark:bg-surface-dark rounded-lg shadow p-6">
        <h2 class="text-xl font-bold text-gray-800 dark:text-white mb-6">Shipping Supplies Section</h2>
        <div class="space-y-6">
            <div>
                <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Section Image</label>
                <div class="mb-2">
                    <?php if (!empty($content['shipping_supplies']['image'])): ?>
                        <img src="<?php echo htmlspecialchars($content['shipping_supplies']['image']); ?>" alt="Shipping Supplies" class="h-48 w-auto border border-gray-300 dark:border-gray-600 rounded p-1">
                    <?php endif; ?>
                </div>
                <input type="file" name="shipping_supplies_image" accept="image/png,image/jpeg,image/jpg,image/gif,image/webp,image/svg+xml"
                       class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary">
                <p class="mt-1 text-xs text-gray-500">Current: <?php echo htmlspecialchars($content['shipping_supplies']['image'] ?? 'Not set'); ?></p>
            </div>
            
            <div>
                <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="shipping_supplies_heading">Heading</label>
                <input type="text" id="shipping_supplies_heading" name="shipping_supplies_heading" 
                       value="<?php echo htmlspecialchars($content['shipping_supplies']['heading'] ?? ''); ?>"
                       class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary">
            </div>
            
            <div>
                <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="shipping_supplies_text">Text</label>
                <textarea id="shipping_supplies_text" name="shipping_supplies_text" rows="4"
                          class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary"><?php echo htmlspecialchars($content['shipping_supplies']['text'] ?? ''); ?></textarea>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="shipping_supplies_link_text">Link Text</label>
                    <input type="text" id="shipping_supplies_link_text" name="shipping_supplies_link_text" 
                           value="<?php echo htmlspecialchars($content['shipping_supplies']['link_text'] ?? ''); ?>"
                           class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary">
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="shipping_supplies_link">Link</label>
                    <input type="text" id="shipping_supplies_link" name="shipping_supplies_link" 
                           value="<?php echo htmlspecialchars($content['shipping_supplies']['link'] ?? '#'); ?>"
                           class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary">
                </div>
            </div>
        </div>
    </div>
    
    <!-- Ship Track Return Section -->
    <div class="bg-white dark:bg-surface-dark rounded-lg shadow p-6">
        <h2 class="text-xl font-bold text-gray-800 dark:text-white mb-6">Ship Track Return Section</h2>
        <div class="space-y-6">
            <div>
                <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="ship_track_return_heading">Heading</label>
                <input type="text" id="ship_track_return_heading" name="ship_track_return_heading" 
                       value="<?php echo htmlspecialchars($content['ship_track_return']['heading'] ?? ''); ?>"
                       class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary">
            </div>
            
            <div>
                <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-4">Items (3 items)</label>
                <div class="space-y-6">
                    <?php for ($i = 1; $i <= 3; $i++): 
                        $item = $content['ship_track_return']['items'][$i-1] ?? ['image' => '', 'heading' => '', 'text' => '', 'link_text' => '', 'link' => '#'];
                    ?>
                        <div class="border border-gray-300 dark:border-gray-600 rounded p-4">
                            <h4 class="text-sm font-bold mb-4">Item <?php echo $i; ?></h4>
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-xs font-bold text-gray-600 dark:text-gray-400 mb-1">Image</label>
                                    <?php if (!empty($item['image'])): ?>
                                        <img src="<?php echo htmlspecialchars($item['image']); ?>" alt="Item <?php echo $i; ?>" class="h-32 w-auto mb-2 border border-gray-300 dark:border-gray-600 rounded p-1">
                                    <?php endif; ?>
                                    <input type="file" name="ship_track_return_item<?php echo $i; ?>_image" accept="image/png,image/jpeg,image/jpg,image/gif,image/webp,image/svg+xml"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white text-sm focus:ring-2 focus:ring-primary">
                                    <p class="mt-1 text-xs text-gray-500">Current: <?php echo htmlspecialchars($item['image'] ?? 'Not set'); ?></p>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-600 dark:text-gray-400 mb-1">Heading</label>
                                    <input type="text" name="ship_track_return_item<?php echo $i; ?>_heading" 
                                           value="<?php echo htmlspecialchars($item['heading']); ?>"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white text-sm focus:ring-2 focus:ring-primary">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-600 dark:text-gray-400 mb-1">Text</label>
                                    <textarea name="ship_track_return_item<?php echo $i; ?>_text" rows="4"
                                              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white text-sm focus:ring-2 focus:ring-primary"><?php echo htmlspecialchars($item['text']); ?></textarea>
                                </div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-xs font-bold text-gray-600 dark:text-gray-400 mb-1">Link Text</label>
                                        <input type="text" name="ship_track_return_item<?php echo $i; ?>_link_text" 
                                               value="<?php echo htmlspecialchars($item['link_text']); ?>"
                                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white text-sm focus:ring-2 focus:ring-primary">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold text-gray-600 dark:text-gray-400 mb-1">Link</label>
                                        <input type="text" name="ship_track_return_item<?php echo $i; ?>_link" 
                                               value="<?php echo htmlspecialchars($item['link']); ?>"
                                               class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white text-sm focus:ring-2 focus:ring-primary">
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>
            
            <div>
                <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-4">Footer Items (2 items)</label>
                <div class="space-y-4">
                    <?php for ($i = 1; $i <= 2; $i++): 
                        $footerItem = $content['ship_track_return']['footer_items'][$i-1] ?? ['heading' => '', 'text' => '', 'link' => '#'];
                    ?>
                        <div class="border border-gray-300 dark:border-gray-600 rounded p-4">
                            <h4 class="text-sm font-bold mb-3">Footer Item <?php echo $i; ?></h4>
                            <div class="space-y-3">
                                <div>
                                    <label class="block text-xs font-bold text-gray-600 dark:text-gray-400 mb-1">Heading</label>
                                    <input type="text" name="ship_track_return_footer<?php echo $i; ?>_heading" 
                                           value="<?php echo htmlspecialchars($footerItem['heading']); ?>"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white text-sm focus:ring-2 focus:ring-primary">
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-600 dark:text-gray-400 mb-1">Text</label>
                                    <textarea name="ship_track_return_footer<?php echo $i; ?>_text" rows="3"
                                              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white text-sm focus:ring-2 focus:ring-primary"><?php echo htmlspecialchars($footerItem['text']); ?></textarea>
                                </div>
                                <div>
                                    <label class="block text-xs font-bold text-gray-600 dark:text-gray-400 mb-1">Link</label>
                                    <input type="text" name="ship_track_return_footer<?php echo $i; ?>_link" 
                                           value="<?php echo htmlspecialchars($footerItem['link']); ?>"
                                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white text-sm focus:ring-2 focus:ring-primary">
                                </div>
                            </div>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>
            
            <div>
                <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="ship_track_return_footer_disclaimer">Footer Disclaimer (use {company} for company name)</label>
                <textarea id="ship_track_return_footer_disclaimer" name="ship_track_return_footer_disclaimer" rows="3"
                          class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary"><?php echo htmlspecialchars($content['ship_track_return']['footer_disclaimer'] ?? ''); ?></textarea>
            </div>
        </div>
    </div>
    <?php elseif ($pageType === 'our-services'): ?>
    <!-- Our Services Editor -->
    <?php
    $hero = $content['hero'] ?? ['heading' => '', 'subtitle' => '', 'description' => '', 'track_shipment_text' => 'Track Shipment', 'track_shipment_link' => '/track.php'];
    $sectionTitle = $content['section_title'] ?? '';
    $services = $content['services'] ?? array_fill(0, 8, ['image' => '', 'title' => '', 'description' => '', 'key_features' => [], 'cta_text' => '', 'cta_link' => '']);
    $downloadSection = $content['download_section'] ?? ['heading' => '', 'text' => '', 'button_text' => '', 'button_link' => ''];
    ?>
    
    <!-- Hero Section -->
    <div class="bg-white dark:bg-surface-dark rounded-lg shadow p-6">
        <h2 class="text-xl font-bold text-gray-800 dark:text-white mb-6">Hero Section</h2>
        <div class="space-y-4">
            <div>
                <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Heading</label>
                <input type="text" name="hero_heading" value="<?php echo htmlspecialchars($hero['heading']); ?>" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary">
            </div>
            <div>
                <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Subtitle</label>
                <input type="text" name="hero_subtitle" value="<?php echo htmlspecialchars($hero['subtitle']); ?>" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary">
            </div>
            <div>
                <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Description</label>
                <textarea name="hero_description" rows="3" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary"><?php echo htmlspecialchars($hero['description']); ?></textarea>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Track Shipment Button Text</label>
                    <input type="text" name="hero_track_shipment_text" value="<?php echo htmlspecialchars($hero['track_shipment_text'] ?? 'Track Shipment'); ?>" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary">
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Track Shipment Link</label>
                    <input type="text" name="hero_track_shipment_link" value="<?php echo htmlspecialchars($hero['track_shipment_link'] ?? '/track.php'); ?>" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary">
                </div>
            </div>
        </div>
    </div>
    
    <!-- Section Title -->
    <div class="bg-white dark:bg-surface-dark rounded-lg shadow p-6">
        <h2 class="text-xl font-bold text-gray-800 dark:text-white mb-6">Section Title</h2>
        <div>
            <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Title (e.g., "What We Offer!")</label>
            <input type="text" name="section_title" value="<?php echo htmlspecialchars($sectionTitle); ?>" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary">
        </div>
    </div>
    
    <!-- Services -->
    <div class="bg-white dark:bg-surface-dark rounded-lg shadow p-6">
        <h2 class="text-xl font-bold text-gray-800 dark:text-white mb-6">Services (8 services)</h2>
        <div class="space-y-8">
            <?php for ($i = 1; $i <= 8; $i++): 
                $service = $services[$i-1] ?? ['image' => '', 'title' => '', 'description' => '', 'key_features' => [], 'cta_text' => '', 'cta_link' => ''];
                $features = $service['key_features'] ?? [];
            ?>
            <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-4">Service <?php echo $i; ?></h3>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Image</label>
                        <?php if (!empty($service['image'])): ?>
                            <img src="<?php echo htmlspecialchars($service['image']); ?>" alt="Service <?php echo $i; ?>" class="h-32 w-auto mb-2 border border-gray-300 dark:border-gray-600 rounded p-1">
                        <?php endif; ?>
                        <input type="file" name="service<?php echo $i; ?>_image" accept="image/png,image/jpeg,image/jpg,image/gif,image/webp,image/svg+xml" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white text-sm focus:ring-2 focus:ring-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Title</label>
                        <input type="text" name="service<?php echo $i; ?>_title" value="<?php echo htmlspecialchars($service['title']); ?>" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Description</label>
                        <textarea name="service<?php echo $i; ?>_description" rows="4" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary"><?php echo htmlspecialchars($service['description']); ?></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Key Features (one per line)</label>
                        <textarea name="service<?php echo $i; ?>_features_text" rows="5" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary" placeholder="Feature 1&#10;Feature 2&#10;Feature 3"><?php echo htmlspecialchars(implode("\n", $features)); ?></textarea>
                        <p class="text-xs text-gray-500 mt-1">Enter one feature per line</p>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">CTA Text</label>
                            <input type="text" name="service<?php echo $i; ?>_cta_text" value="<?php echo htmlspecialchars($service['cta_text']); ?>" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">CTA Link</label>
                            <input type="text" name="service<?php echo $i; ?>_cta_link" value="<?php echo htmlspecialchars($service['cta_link']); ?>" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary">
                        </div>
                    </div>
                </div>
            </div>
            <?php endfor; ?>
        </div>
    </div>
    
    <!-- Download Section -->
    <div class="bg-white dark:bg-surface-dark rounded-lg shadow p-6">
        <h2 class="text-xl font-bold text-gray-800 dark:text-white mb-6">Download Section</h2>
        <div class="space-y-4">
            <div>
                <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Heading</label>
                <input type="text" name="download_heading" value="<?php echo htmlspecialchars($downloadSection['heading']); ?>" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary">
            </div>
            <div>
                <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Text</label>
                <input type="text" name="download_text" value="<?php echo htmlspecialchars($downloadSection['text']); ?>" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary">
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Button Text</label>
                    <input type="text" name="download_button_text" value="<?php echo htmlspecialchars($downloadSection['button_text']); ?>" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary">
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Button Link</label>
                    <input type="text" name="download_button_link" value="<?php echo htmlspecialchars($downloadSection['button_link']); ?>" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary">
                </div>
            </div>
        </div>
    </div>
    <?php elseif ($pageType === 'faq'): ?>
    <!-- FAQ Editor -->
    <?php
    $hero = $content['hero'] ?? ['heading' => '', 'subtitle' => ''];
    $faqItems = $content['items'] ?? [];
    // Ensure at least one empty item for editing
    if (empty($faqItems)) {
        $faqItems = [['question' => '', 'answer' => '']];
    }
    ?>
    
    <!-- Hero Section -->
    <div class="bg-white dark:bg-surface-dark rounded-lg shadow p-6">
        <h2 class="text-xl font-bold text-gray-800 dark:text-white mb-6">Hero Section</h2>
        <div class="space-y-4">
            <div>
                <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Heading</label>
                <input type="text" name="hero_heading" value="<?php echo htmlspecialchars($hero['heading']); ?>" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary">
            </div>
            <div>
                <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Subtitle (use {company} for company name)</label>
                <input type="text" name="hero_subtitle" value="<?php echo htmlspecialchars($hero['subtitle']); ?>" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary">
            </div>
        </div>
    </div>
    
    <!-- FAQ Items -->
    <div class="bg-white dark:bg-surface-dark rounded-lg shadow p-6">
        <h2 class="text-xl font-bold text-gray-800 dark:text-white mb-6">FAQ Items</h2>
        <div id="faq-items" class="space-y-4">
            <?php foreach ($faqItems as $index => $item): ?>
            <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 faq-item">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="text-lg font-bold text-gray-800 dark:text-white">FAQ Item <?php echo $index + 1; ?></h3>
                    <?php if ($index > 0): ?>
                    <button type="button" onclick="removeFaqItem(this)" class="text-red-600 hover:text-red-800 text-sm font-bold">Remove</button>
                    <?php endif; ?>
                </div>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Question</label>
                        <input type="text" name="faq_question[]" value="<?php echo htmlspecialchars($item['question']); ?>" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Answer (use {company} for company name)</label>
                        <textarea name="faq_answer[]" rows="4" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary"><?php echo htmlspecialchars($item['answer']); ?></textarea>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <button type="button" onclick="addFaqItem()" class="mt-4 bg-gray-300 dark:bg-gray-600 hover:bg-gray-400 dark:hover:bg-gray-700 text-gray-800 dark:text-white font-bold py-2 px-4 rounded transition-colors">
            + Add FAQ Item
        </button>
    </div>
    
    <script>
    let faqItemCount = <?php echo count($faqItems); ?>;
    function addFaqItem() {
        faqItemCount++;
        const container = document.getElementById('faq-items');
        const newItem = document.createElement('div');
        newItem.className = 'border border-gray-200 dark:border-gray-700 rounded-lg p-4 faq-item';
        newItem.innerHTML = `
            <div class="flex items-center justify-between mb-2">
                <h3 class="text-lg font-bold text-gray-800 dark:text-white">FAQ Item ${faqItemCount}</h3>
                <button type="button" onclick="removeFaqItem(this)" class="text-red-600 hover:text-red-800 text-sm font-bold">Remove</button>
            </div>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Question</label>
                    <input type="text" name="faq_question[]" value="" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary">
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">Answer (use {company} for company name)</label>
                    <textarea name="faq_answer[]" rows="4" class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary"></textarea>
                </div>
            </div>
        `;
        container.appendChild(newItem);
    }
    function removeFaqItem(button) {
        button.closest('.faq-item').remove();
    }
    </script>
    <?php endif; ?>
    
    <div class="flex items-center justify-between pt-6 border-t border-gray-300 dark:border-gray-600">
        <a href="/admin/pages.php" class="bg-gray-300 dark:bg-gray-600 hover:bg-gray-400 dark:hover:bg-gray-700 text-gray-800 dark:text-white font-bold py-2 px-4 rounded transition-colors">
            Cancel
        </a>
        <button type="submit" class="bg-primary hover:bg-primary-dark text-white font-bold py-3 px-8 rounded uppercase tracking-wide transition-colors">
            Save Page Content
        </button>
    </div>
</form>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>


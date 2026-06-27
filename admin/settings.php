<?php
include __DIR__ . '/includes/admin-header.php';

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle file uploads
    $assetDir = __DIR__ . '/../asset/';
    
    // Handle logo upload
    if (isset($_FILES['logo_upload']) && $_FILES['logo_upload']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['logo_upload'];
        $allowedTypes = ['image/png', 'image/jpeg', 'image/jpg', 'image/gif', 'image/webp', 'image/svg+xml'];
        $maxSize = 5 * 1024 * 1024; // 5MB
        
        if (in_array($file['type'], $allowedTypes) && $file['size'] <= $maxSize) {
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $newFileName = 'logo_uploaded_' . time() . '.' . $extension;
            $targetPath = $assetDir . $newFileName;
            
            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                // Delete old logo if it exists and is not the default
                $oldLogo = getSetting('company_logo', '');
                if (!empty($oldLogo) && strpos($oldLogo, 'logo_uploaded_') !== false) {
                    $oldPath = __DIR__ . '/..' . $oldLogo;
                    if (file_exists($oldPath)) {
                        @unlink($oldPath);
                    }
                }
                updateSetting('company_logo', '/asset/' . $newFileName);
                $success = 'Logo uploaded successfully!';
            } else {
                $error = 'Failed to upload logo.';
            }
        } else {
            $error = 'Invalid logo file. Please upload PNG, JPG, GIF, WEBP, or SVG (max 5MB).';
        }
    }
    
    // Handle favicon upload
    if (isset($_FILES['favicon_upload']) && $_FILES['favicon_upload']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['favicon_upload'];
        $allowedTypes = ['image/png', 'image/jpeg', 'image/jpg', 'image/x-icon', 'image/vnd.microsoft.icon', 'image/svg+xml'];
        $maxSize = 2 * 1024 * 1024; // 2MB
        
        if (in_array($file['type'], $allowedTypes) && $file['size'] <= $maxSize) {
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $newFileName = 'favicon_uploaded_' . time() . '.' . $extension;
            $targetPath = $assetDir . $newFileName;
            
            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                // Delete old favicon if it exists and is not the default
                $oldFavicon = getSetting('site_favicon', '');
                if (!empty($oldFavicon) && strpos($oldFavicon, 'favicon_uploaded_') !== false) {
                    $oldPath = __DIR__ . '/..' . $oldFavicon;
                    if (file_exists($oldPath)) {
                        @unlink($oldPath);
                    }
                }
                updateSetting('site_favicon', '/asset/' . $newFileName);
                $success = $success ? $success . ' Favicon uploaded successfully!' : 'Favicon uploaded successfully!';
            } else {
                $error = $error ? $error . ' Failed to upload favicon.' : 'Failed to upload favicon.';
            }
        } else {
            $error = $error ? $error . ' Invalid favicon file. Please upload PNG, JPG, ICO, or SVG (max 2MB).' : 'Invalid favicon file. Please upload PNG, JPG, ICO, or SVG (max 2MB).';
        }
    }
    
    // Handle color settings (validate hex format)
    if (isset($_POST['setting_primary_color'])) {
        $primaryColor = sanitizeInput($_POST['setting_primary_color']);
        if (preg_match('/^#[0-9A-Fa-f]{6}$/', $primaryColor)) {
            updateSetting('primary_color', strtoupper($primaryColor));
        }
    }
    
    if (isset($_POST['setting_secondary_color'])) {
        $secondaryColor = sanitizeInput($_POST['setting_secondary_color']);
        if (preg_match('/^#[0-9A-Fa-f]{6}$/', $secondaryColor)) {
            updateSetting('secondary_color', strtoupper($secondaryColor));
        }
    }
    
    // Handle regular text settings
    foreach ($_POST as $key => $value) {
        if (strpos($key, 'setting_') === 0 && $key !== 'setting_primary_color' && $key !== 'setting_secondary_color') {
            $settingKey = str_replace('setting_', '', $key);
            $result = updateSetting($settingKey, sanitizeInput($value));
        }
    }
    
    if (empty($success) && empty($error)) {
        $success = 'Settings updated successfully!';
    }
}

// Get all settings (safe - no user input)
$settings = [];
$result = $conn->query("SELECT setting_key, setting_value FROM settings");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    $result->free();
}
?>
<div class="mb-8">
    <h1 class="text-3xl font-light text-gray-800 dark:text-white mb-2">Settings</h1>
    <p class="text-gray-600 dark:text-gray-400">Manage site settings and configuration</p>
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

<div class="bg-white dark:bg-surface-dark rounded-lg shadow p-6">
    <form method="POST" action="" enctype="multipart/form-data">
        <div class="space-y-6">
            <!-- Company Logo Upload -->
            <div>
                <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">
                    Company Logo
                </label>
                <div class="mb-2">
                    <?php 
                    $currentLogo = getSetting('company_logo', '/asset/logo1.png');
                    if ($currentLogo): ?>
                        <img src="<?php echo htmlspecialchars($currentLogo); ?>" alt="Current Logo" class="h-12 w-auto mb-2 border border-gray-300 dark:border-gray-600 rounded p-1">
                    <?php endif; ?>
                </div>
                <input type="file" id="logo_upload" name="logo_upload" accept="image/png,image/jpeg,image/jpg,image/gif,image/webp,image/svg+xml"
                       class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary">
                <p class="mt-1 text-xs text-gray-500">Upload PNG, JPG, GIF, WEBP, or SVG (max 5MB)</p>
            </div>
            
            <!-- Site Favicon Upload -->
            <div>
                <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">
                    Site Favicon
                </label>
                <div class="mb-2">
                    <?php 
                    $currentFavicon = getSetting('site_favicon', '/asset/logo1.png');
                    if ($currentFavicon): ?>
                        <img src="<?php echo htmlspecialchars($currentFavicon); ?>" alt="Current Favicon" class="h-8 w-8 mb-2 border border-gray-300 dark:border-gray-600 rounded p-1">
                    <?php endif; ?>
                </div>
                <input type="file" id="favicon_upload" name="favicon_upload" accept="image/png,image/jpeg,image/jpg,image/x-icon,image/vnd.microsoft.icon,image/svg+xml"
                       class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary">
                <p class="mt-1 text-xs text-gray-500">Upload PNG, JPG, ICO, or SVG (max 2MB)</p>
            </div>
            
            <div class="border-t border-gray-300 dark:border-gray-600 pt-6">
            <!-- Google Maps API Key -->
            <div>
                <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="setting_google_maps_api_key">
                    Google Maps API Key
                </label>
                <input type="text" id="setting_google_maps_api_key" name="setting_google_maps_api_key" 
                       value="<?php echo htmlspecialchars($settings['google_maps_api_key'] ?? ''); ?>"
                       class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary"
                       placeholder="Enter your Google Maps API key">
                <p class="mt-1 text-xs text-gray-500">Get your API key from <a href="https://console.cloud.google.com/" target="_blank" class="text-primary hover:underline">Google Cloud Console</a></p>
                <div class="mt-2 p-3 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded">
                    <p class="text-xs font-semibold text-yellow-800 dark:text-yellow-200 mb-1">Required APIs:</p>
                    <ul class="text-xs text-yellow-700 dark:text-yellow-300 list-disc list-inside space-y-1">
                        <li>Places API</li>
                        <li>Maps JavaScript API</li>
                        <li>Geolocation API</li>
                        <li>Geocoding API</li>
                    </ul>
                </div>
                <!-- Map Preview -->
                <div class="mt-4">
                    <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2">
                        Map Preview (Test API Key)
                    </label>
                    <div id="map-preview" class="w-full h-64 border border-gray-300 dark:border-gray-600 rounded" style="display: none;"></div>
                    <button type="button" id="test-map-btn" class="mt-2 px-4 py-2 bg-secondary hover:bg-secondary-dark text-white text-sm rounded transition-colors">
                        Test Map Display
                    </button>
                    <p id="map-status" class="mt-2 text-xs"></p>
                </div>
            </div>
            
            <!-- Company Name -->
            <div>
                <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="setting_company_name">
                    Company Name
                </label>
                <input type="text" id="setting_company_name" name="setting_company_name" 
                       value="<?php echo htmlspecialchars($settings['company_name'] ?? 'FedEx'); ?>"
                       class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary">
            </div>
            
            <!-- Company Tagline -->
            <div>
                <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="setting_company_tagline">
                    Company Tagline
                </label>
                <input type="text" id="setting_company_tagline" name="setting_company_tagline" 
                       value="<?php echo htmlspecialchars($settings['company_tagline'] ?? 'Global Shipping Solutions'); ?>"
                       class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary"
                       placeholder="e.g., Global Shipping Solutions">
                <p class="mt-1 text-xs text-gray-500">Tagline or subtitle displayed under company name (e.g., in PDF documents)</p>
            </div>
            
            <!-- Site Title -->
            <div>
                <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="setting_site_title">
                    Site Title
                </label>
                <input type="text" id="setting_site_title" name="setting_site_title" 
                       value="<?php echo htmlspecialchars($settings['site_title'] ?? ''); ?>"
                       class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary">
            </div>
            
            <!-- Contact Information Section -->
            <div class="border-t border-gray-300 dark:border-gray-600 pt-6">
                <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-4">Contact Information</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-6">Manage contact details displayed on the Contact Us page.</p>
                
                <!-- Contact Phone Number -->
                <div class="mb-6">
                    <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="setting_contact_phone_number">
                        Contact Phone Number
                    </label>
                    <input type="text" id="setting_contact_phone_number" name="setting_contact_phone_number" 
                           value="<?php echo htmlspecialchars($settings['contact_phone_number'] ?? '+1 (800) 555-0199'); ?>"
                           class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary"
                           placeholder="+1 (800) 555-0199">
                    <p class="mt-1 text-xs text-gray-500">Phone number displayed on the Contact Us page. Formatting will be preserved for display, but cleaned for the tel: link.</p>
                </div>
                
                <!-- Contact HQ Address -->
                <div>
                    <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="setting_contact_hq_address">
                        Headquarters Address
                    </label>
                    <textarea id="setting_contact_hq_address" name="setting_contact_hq_address" rows="4"
                              class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary"
                              placeholder="1200 Logistics Blvd, Suite 500&#10;San Francisco, CA 94107&#10;United States"><?php echo htmlspecialchars($settings['contact_hq_address'] ?? "1200 Logistics Blvd, Suite 500\nSan Francisco, CA 94107\nUnited States"); ?></textarea>
                    <p class="mt-1 text-xs text-gray-500">Headquarters address displayed on the Contact Us page. Use line breaks (Enter) to separate address lines.</p>
                </div>
            </div>
            
            <!-- Cookie Message -->
            <div>
                <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="setting_cookie_message">
                    Cookie Consent Message
                </label>
                <textarea id="setting_cookie_message" name="setting_cookie_message" rows="4"
                          class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary"><?php echo htmlspecialchars($settings['cookie_message'] ?? ''); ?></textarea>
            </div>
            
            <!-- Email Configuration Section -->
            <div class="border-t border-gray-300 dark:border-gray-600 pt-6">
                <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-4">Email Configuration</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-6">Configure SMTP settings for sending emails. These settings can also be configured in config.php.</p>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- SMTP Host -->
                    <div>
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="setting_smtp_host">
                            SMTP Host
                        </label>
                        <input type="text" id="setting_smtp_host" name="setting_smtp_host" 
                               value="<?php echo htmlspecialchars($settings['smtp_host'] ?? SMTP_HOST); ?>"
                               class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary"
                               placeholder="smtp.gmail.com">
                        <p class="mt-1 text-xs text-gray-500">SMTP server hostname</p>
                    </div>
                    
                    <!-- SMTP Port -->
                    <div>
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="setting_smtp_port">
                            SMTP Port
                        </label>
                        <input type="number" id="setting_smtp_port" name="setting_smtp_port" 
                               value="<?php echo htmlspecialchars($settings['smtp_port'] ?? SMTP_PORT); ?>"
                               class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary"
                               placeholder="587">
                        <p class="mt-1 text-xs text-gray-500">Common: 587 (TLS), 465 (SSL), 25 (unencrypted)</p>
                    </div>
                    
                    <!-- SMTP Username -->
                    <div>
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="setting_smtp_username">
                            SMTP Username
                        </label>
                        <input type="text" id="setting_smtp_username" name="setting_smtp_username" 
                               value="<?php echo htmlspecialchars($settings['smtp_username'] ?? SMTP_USERNAME); ?>"
                               class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary"
                               placeholder="your-email@gmail.com">
                        <p class="mt-1 text-xs text-gray-500">Email address for SMTP authentication</p>
                    </div>
                    
                    <!-- SMTP Password -->
                    <div>
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="setting_smtp_password">
                            SMTP Password
                        </label>
                        <input type="password" id="setting_smtp_password" name="setting_smtp_password" 
                               value="<?php echo htmlspecialchars($settings['smtp_password'] ?? ''); ?>"
                               class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary"
                               placeholder="Leave blank to keep current password">
                        <p class="mt-1 text-xs text-gray-500">SMTP password or app password (leave blank to keep current)</p>
                    </div>
                    
                    <!-- SMTP Encryption -->
                    <div>
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="setting_smtp_encryption">
                            SMTP Encryption
                        </label>
                        <select id="setting_smtp_encryption" name="setting_smtp_encryption" 
                                class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary">
                            <option value="tls" <?php echo ($settings['smtp_encryption'] ?? SMTP_ENCRYPTION) === 'tls' ? 'selected' : ''; ?>>TLS (Recommended)</option>
                            <option value="ssl" <?php echo ($settings['smtp_encryption'] ?? SMTP_ENCRYPTION) === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                            <option value="none" <?php echo ($settings['smtp_encryption'] ?? SMTP_ENCRYPTION) === 'none' ? 'selected' : ''; ?>>None</option>
                        </select>
                        <p class="mt-1 text-xs text-gray-500">Encryption method (TLS recommended for port 587)</p>
                    </div>
                    
                    <!-- From Email -->
                    <div>
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="setting_smtp_from_email">
                            From Email Address
                        </label>
                        <input type="email" id="setting_smtp_from_email" name="setting_smtp_from_email" 
                               value="<?php echo htmlspecialchars($settings['smtp_from_email'] ?? SMTP_FROM_EMAIL); ?>"
                               class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary"
                               placeholder="noreply@example.com">
                        <p class="mt-1 text-xs text-gray-500">Default sender email address</p>
                    </div>
                    
                    <!-- From Name -->
                    <div class="md:col-span-2">
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="setting_smtp_from_name">
                            From Name
                        </label>
                        <input type="text" id="setting_smtp_from_name" name="setting_smtp_from_name" 
                               value="<?php echo htmlspecialchars($settings['smtp_from_name'] ?? SMTP_FROM_NAME); ?>"
                               class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary"
                               placeholder="Shipping Company">
                        <p class="mt-1 text-xs text-gray-500">Display name for sender</p>
                    </div>
                </div>
                
                <!-- Test Email Section -->
                <div class="mt-6 p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded">
                    <h4 class="text-sm font-bold text-blue-800 dark:text-blue-200 mb-3">Test Email Configuration</h4>
                    <p class="text-xs text-blue-700 dark:text-blue-300 mb-4">Send a test email to verify your SMTP configuration is working correctly.</p>
                    <div class="flex flex-col sm:flex-row gap-3">
                        <input type="email" id="test_email_address" 
                               class="flex-1 px-4 py-2 border border-blue-300 dark:border-blue-700 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-blue-500"
                               placeholder="Enter email address to test">
                        <button type="button" id="send_test_email_btn" 
                                class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded transition-colors">
                            Send Test Email
                        </button>
                    </div>
                    <div id="test_email_result" class="mt-3 text-sm"></div>
                </div>
            </div>
            
            <!-- Smartsupp Live Chat Configuration -->
            <div class="border-t border-gray-300 dark:border-gray-600 pt-6">
                <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-4">Live Chat Configuration</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-6">Configure Smartsupp live chat widget. The chat will appear on Homepage, Contact, Track, and Track Result pages.</p>
                
                <div>
                    <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="setting_smartsupp_key">
                        Smartsupp API Key
                    </label>
                    <input type="text" id="setting_smartsupp_key" name="setting_smartsupp_key" 
                           value="<?php echo htmlspecialchars($settings['smartsupp_key'] ?? ''); ?>"
                           class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary"
                           placeholder="Enter your Smartsupp API key">
                    <p class="mt-1 text-xs text-gray-500">Get your API key from <a href="https://www.smartsupp.com" target="_blank" class="text-primary underline">Smartsupp.com</a>. The chat widget will automatically appear on: Homepage, Contact, Track, and Track Result pages.</p>
                </div>
            </div>
            
            <!-- Color Settings -->
            <div class="border-t border-gray-300 dark:border-gray-600 pt-6">
                <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-4">Brand Colors</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-6">Customize your brand colors. Dark variants will be automatically generated for hover states.</p>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Primary Color -->
                    <div>
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="setting_primary_color">
                            Primary Color
                        </label>
                        <div class="flex items-center gap-3">
                            <input type="color" id="setting_primary_color" name="setting_primary_color" 
                                   value="<?php echo htmlspecialchars($settings['primary_color'] ?? '#4D148C'); ?>"
                                   class="h-12 w-20 border border-gray-300 dark:border-gray-600 rounded cursor-pointer">
                            <input type="text" id="setting_primary_color_text" 
                                   value="<?php echo htmlspecialchars($settings['primary_color'] ?? '#4D148C'); ?>"
                                   class="flex-1 px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary uppercase"
                                   pattern="^#[0-9A-Fa-f]{6}$"
                                   placeholder="#4D148C">
                        </div>
                        <p class="mt-1 text-xs text-gray-500">Used for headers, primary buttons, and links</p>
                    </div>
                    
                    <!-- Secondary Color -->
                    <div>
                        <label class="block text-sm font-bold text-gray-700 dark:text-gray-300 mb-2" for="setting_secondary_color">
                            Secondary Color
                        </label>
                        <div class="flex items-center gap-3">
                            <input type="color" id="setting_secondary_color" name="setting_secondary_color" 
                                   value="<?php echo htmlspecialchars($settings['secondary_color'] ?? '#FF6200'); ?>"
                                   class="h-12 w-20 border border-gray-300 dark:border-gray-600 rounded cursor-pointer">
                            <input type="text" id="setting_secondary_color_text" 
                                   value="<?php echo htmlspecialchars($settings['secondary_color'] ?? '#FF6200'); ?>"
                                   class="flex-1 px-4 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-background-dark text-gray-800 dark:text-white focus:ring-2 focus:ring-primary uppercase"
                                   pattern="^#[0-9A-Fa-f]{6}$"
                                   placeholder="#FF6200">
                        </div>
                        <p class="mt-1 text-xs text-gray-500">Used for secondary buttons and accent elements</p>
                    </div>
                </div>
                
                <div class="mt-4 p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded">
                    <p class="text-xs font-semibold text-blue-800 dark:text-blue-200 mb-1">💡 Tip:</p>
                    <p class="text-xs text-blue-700 dark:text-blue-300">Dark variants for hover states are automatically generated. After saving, refresh the page to see changes.</p>
                </div>
            </div>
            </div>
        </div>
        
        <div class="mt-8">
            <button type="submit" class="bg-primary hover:bg-primary-dark text-white font-bold py-3 px-8 rounded uppercase tracking-wide transition-colors">
                Save Settings
            </button>
        </div>
    </form>
</div>

<script>
// Sync color picker and text input
(function() {
    const primaryColor = document.getElementById('setting_primary_color');
    const primaryColorText = document.getElementById('setting_primary_color_text');
    const secondaryColor = document.getElementById('setting_secondary_color');
    const secondaryColorText = document.getElementById('setting_secondary_color_text');
    
    if (primaryColor && primaryColorText) {
        primaryColor.addEventListener('input', function() {
            primaryColorText.value = this.value.toUpperCase();
        });
        primaryColorText.addEventListener('input', function() {
            if (/^#[0-9A-Fa-f]{6}$/.test(this.value)) {
                primaryColor.value = this.value.toUpperCase();
            }
        });
        primaryColorText.addEventListener('blur', function() {
            if (!/^#[0-9A-Fa-f]{6}$/.test(this.value)) {
                this.value = primaryColor.value.toUpperCase();
            }
        });
    }
    
    if (secondaryColor && secondaryColorText) {
        secondaryColor.addEventListener('input', function() {
            secondaryColorText.value = this.value.toUpperCase();
        });
        secondaryColorText.addEventListener('input', function() {
            if (/^#[0-9A-Fa-f]{6}$/.test(this.value)) {
                secondaryColor.value = this.value.toUpperCase();
            }
        });
        secondaryColorText.addEventListener('blur', function() {
            if (!/^#[0-9A-Fa-f]{6}$/.test(this.value)) {
                this.value = secondaryColor.value.toUpperCase();
            }
        });
    }
    
    // Sync text inputs with hidden color inputs on form submit
    const form = document.querySelector('form');
    if (form) {
        form.addEventListener('submit', function() {
            if (primaryColorText) {
                primaryColor.value = primaryColorText.value.toUpperCase();
            }
            if (secondaryColorText) {
                secondaryColor.value = secondaryColorText.value.toUpperCase();
            }
        });
    }
})();

// Test Google Maps API Key
(function() {
    const apiKeyInput = document.getElementById('setting_google_maps_api_key');
    const testBtn = document.getElementById('test-map-btn');
    const mapPreview = document.getElementById('map-preview');
    const mapStatus = document.getElementById('map-status');
    
    if (testBtn && apiKeyInput) {
        testBtn.addEventListener('click', function() {
            const apiKey = apiKeyInput.value.trim();
            
            if (!apiKey) {
                mapStatus.textContent = 'Please enter an API key first.';
                mapStatus.className = 'mt-2 text-xs text-red-600 dark:text-red-400';
                return;
            }
            
            mapStatus.textContent = 'Loading map...';
            mapStatus.className = 'mt-2 text-xs text-blue-600 dark:text-blue-400';
            mapPreview.style.display = 'block';
            
            // Load Google Maps script
            const script = document.createElement('script');
            script.src = `https://maps.googleapis.com/maps/api/js?key=${apiKey}&libraries=places`;
            script.async = true;
            script.defer = true;
            
            script.onload = function() {
                try {
                    const map = new google.maps.Map(mapPreview, {
                        zoom: 10,
                        center: { lat: 40.7128, lng: -74.0060 }, // New York City
                        disableDefaultUI: false
                    });
                    
                    new google.maps.Marker({
                        position: { lat: 40.7128, lng: -74.0060 },
                        map: map,
                        title: 'Test Location'
                    });
                    
                    mapStatus.textContent = '✓ Map loaded successfully! API key is working.';
                    mapStatus.className = 'mt-2 text-xs text-green-600 dark:text-green-400';
                } catch (error) {
                    mapStatus.textContent = '✗ Error initializing map: ' + error.message;
                    mapStatus.className = 'mt-2 text-xs text-red-600 dark:text-red-400';
                }
            };
            
            script.onerror = function() {
                mapStatus.textContent = '✗ Failed to load Google Maps. Check your API key and ensure required APIs are enabled.';
                mapStatus.className = 'mt-2 text-xs text-red-600 dark:text-red-400';
            };
            
            // Remove old script if exists
            const oldScript = document.querySelector('script[src*="maps.googleapis.com"]');
            if (oldScript) {
                oldScript.remove();
            }
            
            document.head.appendChild(script);
        });
        
        // Auto-test when API key is entered and form is saved
        const form = document.querySelector('form');
        if (form) {
            form.addEventListener('submit', function() {
            });
        }
    }
})();

// Test Email Functionality
(function() {
    const testEmailBtn = document.getElementById('send_test_email_btn');
    const testEmailInput = document.getElementById('test_email_address');
    const testEmailResult = document.getElementById('test_email_result');
    
    if (testEmailBtn && testEmailInput) {
        testEmailBtn.addEventListener('click', async function() {
            const email = testEmailInput.value.trim();
            
            if (!email) {
                testEmailResult.innerHTML = '<p class="text-red-600 dark:text-red-400">Please enter an email address.</p>';
                return;
            }
            
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                testEmailResult.innerHTML = '<p class="text-red-600 dark:text-red-400">Please enter a valid email address.</p>';
                return;
            }
            
            // Disable button and show loading
            testEmailBtn.disabled = true;
            testEmailBtn.textContent = 'Sending...';
            testEmailResult.innerHTML = '<p class="text-blue-600 dark:text-blue-400">Sending test email...</p>';
            
            // Create AbortController for timeout
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 30000); // 30 second timeout
            
            try {
                const response = await fetch('/api/test-email.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        email: email
                    }),
                    signal: controller.signal
                });
                
                clearTimeout(timeoutId);
                
                if (!response.ok) {
                    throw new Error('Server responded with status: ' + response.status);
                }
                
                const data = await response.json();
                
                if (data.success) {
                    testEmailResult.innerHTML = '<p class="text-green-600 dark:text-green-400">✓ Test email sent successfully! Check your inbox.</p>';
                } else {
                    testEmailResult.innerHTML = '<p class="text-red-600 dark:text-red-400">✗ Failed to send test email: ' + (data.message || 'Unknown error') + '</p>';
                }
            } catch (error) {
                clearTimeout(timeoutId);
                if (error.name === 'AbortError') {
                    testEmailResult.innerHTML = '<p class="text-red-600 dark:text-red-400">✗ Request timed out. Check your SMTP settings and try again.</p>';
                } else {
                    testEmailResult.innerHTML = '<p class="text-red-600 dark:text-red-400">✗ Error: ' + error.message + '</p>';
                }
            } finally {
                testEmailBtn.disabled = false;
                testEmailBtn.textContent = 'Send Test Email';
            }
        });
    }
})();
</script>

<?php include __DIR__ . '/includes/admin-footer.php'; ?>


<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/functions.php';

$cookieMessage = getSetting('cookie_message', 'This website uses cookies and similar technologies (collectively cookies). We use functional, analytical and tracking cookies. For functional cookies we do not require your consent. However, we need your consent for all optional analytical and tracking cookies. You can further customize your cookie preferences by clicking the "Cookie Preferences" link in this banner or in the footer of this website. For more information about the cookies we use, click here to read our cookie notice.');
$companyName = getSetting('company_name', 'FedEx');
?>
<div class="fixed bottom-0 left-0 right-0 bg-gray-100 dark:bg-surface-dark border-t border-gray-300 dark:border-gray-700 p-6 z-50 shadow-2xl" id="cookie-banner" style="display: none;">
    <div class="max-w-container mx-auto">
        <div class="flex items-center mb-4">
            <div class="mr-2">
                <img src="<?php echo htmlspecialchars(getLogo('light')); ?>" alt="<?php echo htmlspecialchars($companyName); ?>" class="h-6 w-auto">
            </div>
        </div>
        <h3 class="text-lg font-bold mb-2">Privacy Settings</h3>
        <p class="text-sm text-gray-700 dark:text-gray-300 mb-6 leading-relaxed">
            <?php echo htmlspecialchars($cookieMessage); ?>
        </p>
        <div class="flex flex-col md:flex-row justify-between items-center gap-4">
            <button class="text-primary dark:text-purple-400 font-bold text-sm hover:underline uppercase tracking-wide" data-cookie-action="preferences">Cookie Preferences</button>
            <div class="flex gap-4 w-full md:w-auto">
                <button class="flex-1 md:flex-none text-secondary hover:text-secondary-dark font-bold text-sm uppercase tracking-wide py-2 px-4" data-cookie-action="reject">Reject Optional Cookies</button>
                <button class="flex-1 md:flex-none text-secondary hover:text-secondary-dark font-bold text-sm uppercase tracking-wide py-2 px-4" data-cookie-action="accept-all">Accept All Cookies</button>
            </div>
        </div>
    </div>
</div>


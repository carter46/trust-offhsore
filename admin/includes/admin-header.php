<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/admin-auth.php';

$currentPage = basename($_SERVER['PHP_SELF']);

// Get brand colors from settings
$primaryColor = getSetting('primary_color', '#4D148C');
$secondaryColor = getSetting('secondary_color', '#FF6200');

// Auto-generate dark variants (30% darker)
$primaryDark = darkenColor($primaryColor, 30);
$secondaryDark = darkenColor($secondaryColor, 30);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Admin Panel - <?php echo htmlspecialchars(getSetting('company_name', 'FedEx')); ?></title>
    <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet"/>
    <link rel="stylesheet" href="/css/admin.css">
    <script>
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        primary: "<?php echo htmlspecialchars($primaryColor); ?>",
                        secondary: "<?php echo htmlspecialchars($secondaryColor); ?>",
                        "primary-dark": "<?php echo htmlspecialchars($primaryDark); ?>",
                        "secondary-dark": "<?php echo htmlspecialchars($secondaryDark); ?>",
                    },
                    fontFamily: {
                        sans: ["Roboto", "sans-serif"],
                    },
                },
            },
        };
    </script>
</head>
<body class="bg-gray-100 dark:bg-background-dark font-sans min-h-screen">
    <nav class="bg-primary text-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex items-center justify-between h-16">
                <div class="flex items-center space-x-8">
                    <a href="/admin/dashboard.php" class="flex items-center">
                        <img src="<?php echo htmlspecialchars(getLogo('dark')); ?>" alt="<?php echo htmlspecialchars(getSetting('company_name', 'FedEx')); ?>" class="h-8 w-auto">
                        <span class="text-sm font-normal ml-2 text-white">Admin</span>
                    </a>
                    <div class="hidden md:flex space-x-4">
                        <a href="/admin/dashboard.php" class="px-3 py-2 rounded text-sm font-medium hover:bg-primary-dark <?php echo $currentPage === 'dashboard.php' ? 'bg-primary-dark' : ''; ?>">
                            Dashboard
                        </a>
                        <a href="/admin/create-shipment.php" class="px-3 py-2 rounded text-sm font-medium hover:bg-primary-dark <?php echo $currentPage === 'create-shipment.php' ? 'bg-primary-dark' : ''; ?>">
                            Create Shipment
                        </a>
                        <a href="/admin/manage-shipments.php" class="px-3 py-2 rounded text-sm font-medium hover:bg-primary-dark <?php echo $currentPage === 'manage-shipments.php' ? 'bg-primary-dark' : ''; ?>">
                            Manage Shipments
                        </a>
                        <a href="/admin/pages.php" class="px-3 py-2 rounded text-sm font-medium hover:bg-primary-dark <?php echo in_array($currentPage, ['pages.php', 'edit-page.php']) ? 'bg-primary-dark' : ''; ?>">
                            Pages
                        </a>
                        <a href="/admin/settings.php" class="px-3 py-2 rounded text-sm font-medium hover:bg-primary-dark <?php echo $currentPage === 'settings.php' ? 'bg-primary-dark' : ''; ?>">
                            Settings
                        </a>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="/admin/profile.php" class="hidden md:flex text-sm hover:text-gray-200 items-center <?php echo $currentPage === 'profile.php' ? 'text-gray-200' : ''; ?>">
                        <span class="material-icons-outlined mr-1">account_circle</span>
                        <span><?php echo htmlspecialchars($_SESSION['admin_username'] ?? 'Admin'); ?></span>
                    </a>
                    <a href="/admin/logout.php" class="hidden md:flex text-sm hover:text-gray-200 items-center">
                        <span class="material-icons-outlined mr-1">logout</span> <span>Logout</span>
                    </a>
                    <button class="md:hidden hover:text-gray-200" onclick="toggleMobileMenu()">
                        <span class="material-icons-outlined">menu</span>
                    </button>
                </div>
            </div>
        </div>
    </nav>

    <!-- Mobile Slide-Down Menu -->
    <div id="mobileMenu" class="fixed left-0 top-0 w-full h-full bg-white dark:bg-background-dark z-[999] hidden -translate-y-full transition-transform duration-300 md:hidden">
        <div class="bg-primary text-white h-14 flex items-center justify-between px-4">
            <a class="flex items-center" href="/admin/dashboard.php">
                <img src="<?php echo htmlspecialchars(getLogo('dark')); ?>" alt="<?php echo htmlspecialchars(getSetting('company_name', 'FedEx')); ?>" class="h-8 w-auto">
                <span class="text-sm font-normal ml-2 text-white">Admin</span>
            </a>
            <div class="flex items-center gap-4">
                <a class="flex items-center hover:text-gray-200" href="/admin/profile.php">
                    <span class="material-icons-outlined">account_circle</span>
                </a>
                <button class="hover:text-gray-200" onclick="toggleMobileMenu()">
                    <span class="material-icons-outlined">close</span>
                </button>
            </div>
        </div>
        
        <ul class="list-none m-0 p-0 bg-white dark:bg-background-dark">
            <li class="border-b border-gray-200 dark:border-gray-700">
                <a href="/admin/dashboard.php" class="w-full px-4 py-4 flex items-center text-left text-[15px] text-gray-700 dark:text-gray-200 <?php echo $currentPage === 'dashboard.php' ? 'bg-primary/10 text-primary dark:text-purple-300' : ''; ?>">
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="border-b border-gray-200 dark:border-gray-700">
                <a href="/admin/create-shipment.php" class="w-full px-4 py-4 flex items-center text-left text-[15px] text-gray-700 dark:text-gray-200 <?php echo $currentPage === 'create-shipment.php' ? 'bg-primary/10 text-primary dark:text-purple-300' : ''; ?>">
                    <span>Create Shipment</span>
                </a>
            </li>
            <li class="border-b border-gray-200 dark:border-gray-700">
                <a href="/admin/manage-shipments.php" class="w-full px-4 py-4 flex items-center text-left text-[15px] text-gray-700 dark:text-gray-200 <?php echo $currentPage === 'manage-shipments.php' ? 'bg-primary/10 text-primary dark:text-purple-300' : ''; ?>">
                    <span>Manage Shipments</span>
                </a>
            </li>
            <li class="border-b border-gray-200 dark:border-gray-700">
                <a href="/admin/pages.php" class="w-full px-4 py-4 flex items-center text-left text-[15px] text-gray-700 dark:text-gray-200 <?php echo in_array($currentPage, ['pages.php', 'edit-page.php']) ? 'bg-primary/10 text-primary dark:text-purple-300' : ''; ?>">
                    <span>Pages</span>
                </a>
            </li>
            <li class="border-b border-gray-200 dark:border-gray-700">
                <a href="/admin/settings.php" class="w-full px-4 py-4 flex items-center text-left text-[15px] text-gray-700 dark:text-gray-200 <?php echo $currentPage === 'settings.php' ? 'bg-primary/10 text-primary dark:text-purple-300' : ''; ?>">
                    <span>Settings</span>
                </a>
            </li>
            <li class="border-b border-gray-200 dark:border-gray-700">
                <a href="/admin/profile.php" class="w-full px-4 py-4 flex items-center text-left text-[15px] text-gray-700 dark:text-gray-200 <?php echo $currentPage === 'profile.php' ? 'bg-primary/10 text-primary dark:text-purple-300' : ''; ?>">
                    <span>Profile</span>
                </a>
            </li>
            <li class="border-b border-gray-200 dark:border-gray-700">
                <a href="/admin/logout.php" class="w-full px-4 py-4 flex items-center text-left text-[15px] text-gray-700 dark:text-gray-200">
                    <span>Logout</span>
                </a>
            </li>
        </ul>
    </div>

    <script src="/js/mobile-menu.js"></script>
    <main class="max-w-7xl mx-auto px-4 py-8">


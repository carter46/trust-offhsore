<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/functions.php';

$siteTitle = getSetting('site_title', 'Shipping, Logistics Management and Supply Chain Management');
$companyName = getSetting('company_name', 'FedEx');
$contactPhone = getSetting('contact_phone_number', '+1 (800) 555-0199');
$headerGreeting = replacePlaceholders(getSetting('header_company_name', $companyName));

$primaryColor = getSetting('primary_color', '#fbbf24');
$secondaryColor = getSetting('secondary_color', '#f59e0b');
$primaryDark = darkenColor($primaryColor, 30);
$secondaryDark = darkenColor($secondaryColor, 30);

$currentPage = '';
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$cleanUri = rtrim(explode('?', $requestUri)[0], '/');
$cleanScript = basename($scriptName, '.php');

if ($cleanUri === '' || $cleanUri === '/' || $cleanScript === 'index') {
    $currentPage = 'homepage';
} elseif (strpos($cleanUri, '/contact') !== false || $cleanScript === 'contact') {
    $currentPage = 'contact';
} elseif (strpos($cleanUri, '/track-result') !== false || $cleanScript === 'track-result') {
    $currentPage = 'track-result';
} elseif (strpos($cleanUri, '/track') !== false || $cleanScript === 'track') {
    $currentPage = 'track';
}

$smartsuppKey = getSetting('smartsupp_key', '');
$showSmartsupp = in_array($currentPage, ['homepage', 'contact', 'track', 'track-result']);

$transparentNav = ($currentPage === 'homepage');
$logoDark = getLogo('dark');
$logoLight = getLogo('light');
$logoPath = $transparentNav ? $logoDark : $logoLight;
$mobileNavLogoPath = $transparentNav ? $logoLight : $logoPath;
$navLinks = [
    ['label' => 'Home', 'href' => '/'],
    ['label' => 'Tracking', 'href' => '/track'],
    ['label' => 'Shipping', 'href' => '/shipping'],
    ['label' => 'Our Services', 'href' => '/our-services'],
    ['label' => 'FAQ', 'href' => '/faq'],
    ['label' => 'Contact', 'href' => '/contact'],
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title><?php echo htmlspecialchars($siteTitle); ?></title>
    <link rel="icon" type="image/x-icon" href="<?php echo htmlspecialchars(getSetting('site_favicon', '/asset/logo1.png')); ?>">
    <link href="https://fonts.googleapis.com" rel="preconnect"/>
    <link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect"/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        primary: "<?php echo htmlspecialchars($primaryColor); ?>",
                        secondary: "<?php echo htmlspecialchars($secondaryColor); ?>",
                        "primary-dark": "<?php echo htmlspecialchars($primaryDark); ?>",
                        "secondary-dark": "<?php echo htmlspecialchars($secondaryDark); ?>",
                    },
                    fontFamily: {
                        sans: ["Inter", "sans-serif"],
                    },
                },
            },
        };
    </script>
    <link rel="stylesheet" href="/css/styles.css">
    <link rel="stylesheet" href="/css/animations.css">
    <?php if ($transparentNav): ?>
    <style>
        @media (min-width: 1024px) {
            .page-homepage #siteNav.site-nav-transparent:not(.nav-scrolled) .nav-link {
                color: #ffffff !important;
            }
            .page-homepage #siteNav.site-nav-transparent:not(.nav-scrolled) .nav-link:hover {
                color: #fde047 !important;
            }
            .page-homepage #siteNav.site-nav-transparent.nav-scrolled .nav-link {
                color: #374151 !important;
            }
            .page-homepage #siteNav.site-nav-transparent.nav-scrolled .nav-link:hover {
                color: #fbbf24 !important;
            }
        }
    </style>
    <?php endif; ?>
</head>
<body class="bg-white text-gray-800 font-sans antialiased overflow-x-hidden<?php echo $transparentNav ? ' page-homepage' : ''; ?>">
<div class="relative flex flex-col w-full min-h-screen">

<!-- Top Bar -->
<div class="header-top-bar py-2 px-4 md:px-12 text-sm font-semibold flex justify-between items-center">
    <div>Call : <?php echo htmlspecialchars($contactPhone); ?></div>
    <div class="hidden md:block">Howdy, <?php echo htmlspecialchars($headerGreeting); ?></div>
</div>

<!-- Navigation -->
<header id="siteNav" class="site-nav sticky top-0 z-50<?php echo $transparentNav ? ' site-nav-transparent' : ' bg-white shadow-sm'; ?>">
<nav class="container mx-auto px-4 md:px-12 py-4 flex justify-between items-center">
    <div class="flex items-center">
        <a href="/">
            <img alt="<?php echo htmlspecialchars($companyName); ?>" class="h-12 site-nav-logo" src="<?php echo htmlspecialchars($logoPath); ?>"<?php if ($transparentNav): ?> data-logo-dark="<?php echo htmlspecialchars($logoDark); ?>" data-logo-light="<?php echo htmlspecialchars($logoLight); ?>"<?php endif; ?>/>
        </a>
    </div>
    <ul class="hidden lg:flex space-x-8">
        <?php foreach ($navLinks as $link): ?>
        <li>
            <a class="<?php echo navLinkClass($link['href'], $currentPage, $cleanUri, $cleanScript, $transparentNav); ?>" href="<?php echo htmlspecialchars($link['href']); ?>">
                <?php echo htmlspecialchars($link['label']); ?>
            </a>
        </li>
        <?php endforeach; ?>
    </ul>
    <button class="lg:hidden text-2xl cursor-pointer site-nav-menu-btn" onclick="toggleMobileMenu()" aria-label="Open menu">
        <span class="material-symbols-outlined">menu</span>
    </button>
</nav>
</header>

<!-- Mobile Menu -->
<div id="mobileMenu" class="fixed left-0 top-0 w-full h-full bg-white z-[999] hidden -translate-y-full transition-transform duration-300 lg:hidden">
    <div class="header-top-bar h-14 flex items-center justify-between px-4">
        <a href="/" class="flex items-center">
            <img alt="<?php echo htmlspecialchars($companyName); ?>" class="h-10" src="<?php echo htmlspecialchars($mobileNavLogoPath); ?>"/>
        </a>
        <button class="text-gray-800" onclick="toggleMobileMenu()" aria-label="Close menu">
            <span class="material-symbols-outlined">close</span>
        </button>
    </div>
    <div class="px-4 py-3 border-b border-gray-200 bg-gray-50">
        <form action="/track-result" method="GET" class="flex items-center gap-2 w-full">
            <input type="text" name="id" placeholder="Tracking Number" class="flex-1 bg-white border border-gray-300 text-gray-800 text-sm px-3 py-2 rounded focus:ring-yellow-400 focus:border-yellow-400 outline-none" required/>
            <button type="submit" class="bg-yellow-400 hover:bg-yellow-500 text-black p-2 rounded flex items-center">
                <span class="material-symbols-outlined text-sm">search</span>
            </button>
        </form>
    </div>
    <ul class="list-none m-0 p-0">
        <?php foreach ($navLinks as $link): ?>
        <li class="border-b border-gray-200">
            <a href="<?php echo htmlspecialchars($link['href']); ?>" class="w-full px-4 py-4 flex items-center text-left text-sm font-bold uppercase tracking-wider text-gray-700 hover:bg-gray-50 hover:text-yellow-500">
                <?php echo htmlspecialchars($link['label']); ?>
            </a>
        </li>
        <?php endforeach; ?>
    </ul>
    <div class="px-4 py-4 border-t border-gray-200">
        <a href="/track" class="w-full bg-yellow-400 hover:bg-yellow-500 text-black text-sm font-bold py-3 px-6 rounded transition-colors flex items-center justify-center uppercase tracking-widest">
            Track
        </a>
    </div>
</div>

<script src="/js/mobile-menu.js"></script>
<script src="/js/header-scroll.js"></script>

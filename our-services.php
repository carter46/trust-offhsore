<?php
include __DIR__ . '/includes/header.php';

// Load our-services page content from database
$page = getPageContent('our-services');
$content = $page['content'] ?? null;

// Default values (fallback if database content is missing)
$hero = $content['hero'] ?? [
    'heading' => 'Our Services',
    'subtitle' => 'We Provide you with the best services out there!',
    'description' => 'We offer dependable sea freight solutions for clients who need to move goods across international waters. Whether you\'re handling full container loads or smaller shipments'
];

$sectionTitle = $content['section_title'] ?? 'What We Offer!';
$services = $content['services'] ?? [];
$downloadSection = $content['download_section'] ?? [
    'heading' => 'FIND ALL IN ONE DOCUMENT',
    'text' => 'Download our service Brochures',
    'button_text' => 'Download PDF',
    'button_link' => '#'
];

$companyName = getSetting('company_name', 'FedEx');
?>

<!-- Hero Section -->
<section class="page-hero-bg text-white py-16 lg:py-20">
    <div class="container mx-auto px-4 md:px-12 text-center">
        <div class="w-16 h-1 bg-yellow-400 mx-auto mb-4"></div>
        <h1 class="text-white text-4xl lg:text-5xl font-extrabold mb-4 tracking-tight">
            <?php echo htmlspecialchars(replacePlaceholders($hero['heading'])); ?>
        </h1>
        <p class="text-gray-200 text-xl lg:text-2xl font-normal mb-6">
            <?php echo htmlspecialchars(replacePlaceholders($hero['subtitle'])); ?>
        </p>
        <p class="text-gray-300 text-lg max-w-3xl mx-auto mb-6">
            <?php echo htmlspecialchars(replacePlaceholders($hero['description'] ?? '')); ?>
        </p>
        <?php if (!empty($hero['track_shipment_text'])): ?>
            <a href="<?php echo htmlspecialchars($hero['track_shipment_link'] ?? '/track'); ?>" class="inline-block bg-yellow-400 hover:bg-yellow-500 text-black font-bold py-3 px-8 rounded transition-all">
                <?php echo htmlspecialchars($hero['track_shipment_text']); ?>
                <span class="material-symbols-outlined text-sm ml-2 align-middle">arrow_forward</span>
            </a>
        <?php endif; ?>
    </div>
</section>

<!-- Services Section -->
<section class="py-24 px-4 lg:px-10 dot-pattern dot-pattern-gray">
    <div class="max-w-7xl mx-auto">
        <div class="text-center mb-16">
            <span class="text-yellow-500 font-bold uppercase tracking-widest text-sm">Services</span>
            <h2 class="text-slate-800 text-3xl lg:text-4xl font-extrabold mb-4 tracking-tight">
                <?php echo htmlspecialchars(replacePlaceholders($sectionTitle)); ?>
            </h2>
        </div>
        
        <div class="space-y-20">
            <?php foreach ($services as $index => $service): ?>
                <div class="flex flex-col <?php echo $index % 2 === 0 ? 'lg:flex-row' : 'lg:flex-row-reverse'; ?> gap-12 items-center">
                    <div class="w-full lg:w-1/2">
                        <?php if (!empty($service['image'])): ?>
                            <div class="rounded-xl overflow-hidden shadow-xl">
                                <img src="<?php echo htmlspecialchars($service['image']); ?>" 
                                     alt="<?php echo htmlspecialchars($service['title']); ?>" 
                                     class="w-full h-64 lg:h-96 object-cover">
                            </div>
                        <?php else: ?>
                            <div class="w-full h-64 lg:h-96 bg-gray-200 rounded-xl flex items-center justify-center">
                                <span class="text-gray-400">Service Image</span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="w-full lg:w-1/2">
                        <h3 class="text-slate-800 text-2xl lg:text-3xl font-bold mb-4">
                            <?php echo htmlspecialchars(replacePlaceholders($service['title'] ?? '')); ?>
                        </h3>
                        <p class="text-gray-600 mb-6 leading-relaxed text-lg">
                            <?php echo nl2br(htmlspecialchars(replacePlaceholders($service['description'] ?? ''))); ?>
                        </p>
                        
                        <?php if (!empty($service['key_features'])): ?>
                            <div class="mb-6">
                                <h4 class="font-bold text-lg text-slate-800 mb-3">Key Features</h4>
                                <ul class="space-y-3">
                                    <?php foreach ($service['key_features'] as $feature): ?>
                                        <li class="flex items-start text-gray-600">
                                            <span class="material-symbols-outlined text-yellow-500 mr-3 mt-0.5">check_circle</span>
                                            <span><?php echo htmlspecialchars(replacePlaceholders($feature)); ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($service['cta_text'])): ?>
                            <a href="<?php echo htmlspecialchars($service['cta_link'] ?? '#'); ?>" 
                               class="inline-block bg-yellow-400 hover:bg-yellow-500 text-black font-bold py-3 px-8 rounded transition-all">
                                <?php echo htmlspecialchars($service['cta_text']); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Download Section -->
<section class="py-24 px-4 lg:px-10 bg-white">
    <div class="max-w-4xl mx-auto text-center">
        <h2 class="text-slate-800 text-3xl lg:text-4xl font-extrabold mb-4 tracking-tight">
            <?php echo htmlspecialchars(replacePlaceholders($downloadSection['heading'] ?? '')); ?>
        </h2>
        <p class="text-gray-600 text-lg mb-8">
            <?php echo htmlspecialchars(replacePlaceholders($downloadSection['text'] ?? '')); ?>
        </p>
        <a href="<?php echo htmlspecialchars($downloadSection['button_link'] ?? '#'); ?>" 
           class="inline-block bg-slate-900 hover:bg-yellow-500 hover:text-black text-white font-bold py-3 px-8 rounded transition-all">
            <?php echo htmlspecialchars($downloadSection['button_text'] ?? 'Download PDF'); ?>
        </a>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>

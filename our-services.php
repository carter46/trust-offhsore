<?php
include __DIR__ . '/includes/header.php';

// Page content is defined here, not in the pages table; Admin > Pages edits do not apply.
$hero = [
    'heading' => 'Our Services',
    'subtitle' => 'We Provide you with the best services out there!',
    'description' => "We offer dependable sea freight solutions for clients who need to move goods across international waters. Whether you're handling full container loads or smaller shipments",
    'track_shipment_text' => 'Track Shipment',
    'track_shipment_link' => '/track.php',
];

$sectionTitle = 'What We Offer!';

$services = [
    [
        'title' => 'Sea Freight',
        'image' => '/asset/service1_1768432163_7326.jpg',
        'description' => "We offer dependable sea freight solutions for clients who need to move goods across international waters. Whether you're handling full container loads or smaller shipments, our team ensures timely, cost-effective, and compliant ocean shipping from port to port.",
        'key_features' => [
            'Full Container Load (FCL) & Less-than-Container Load (LCL)',
            'Bulk & Breakbulk Cargo Handling',
            'Import/Export Documentation & Customs Clearance',
            'Door-to-Port & Door-to-Door Delivery Options',
        ],
    ],
    [
        'title' => 'Air Freight',
        'image' => '/asset/service2_1768432163_1015.webp',
        'description' => 'For urgent shipments, {company} offers fast and reliable air freight services that ensure quick delivery across domestic and international routes.',
        'key_features' => [
            'Express & Standard Air Freight',
            'Cargo Pickup & Packaging',
            'Cargo Handling & Packaging',
            'Airport-to-Airport & Door-to-Door Services',
            'Real-Time Shipment Tracking',
        ],
    ],
    [
        'title' => 'Haulage & Local Transport',
        'image' => '/asset/service3_1768432163_9888.jpg',
        'description' => 'From cross-state hauls to last-mile delivery within the US and beyond, our modern fleet and experienced drivers provide safe, reliable, and timely ground transport for all types of goods.',
        'key_features' => [
            'Nationwide & Regional Trucking',
            'Full Truckload (FTL) & Less-than-Truckload (LTL)',
            'Specialized & Oversized Cargo Handling',
            'Last-Mile Distribution',
        ],
    ],
    [
        'title' => 'Bulk Cargo Handling',
        'image' => '/asset/service4_1768432163_9240.jpg',
        'description' => 'For clients with large-scale or heavy-volume shipments, we offer tailored bulk cargo logistics, including vessel chartering and inland transportation. Our bulk services are designed to reduce cost while maintaining speed and safety.',
        'key_features' => [
            'Vessel & Equipment Chartering',
            'Efficient Loading & Unloading',
            'Heavy Cargo Management',
            'Port & Inland Clearance Assistance',
        ],
    ],
    [
        'title' => 'Clearing & Customs Brokerage',
        'image' => '/asset/service5_1768432163_1963.jpg',
        'description' => 'We simplify the customs clearance process for imports and exports, handling documentation, duties, and compliance with U.S. and international trade laws. We ensure your shipments are processed swiftly and legally.',
        'key_features' => [
            'U.S. Customs Brokerage Services',
            'Regulatory Documentation Handling',
            'Duty Classification & Processing',
            'Port Coordination & Cargo Release',
        ],
    ],
    [
        'title' => 'Import & Duty Finance',
        'image' => '/asset/service6_1768432163_7551.jpg',
        'description' => "Importing can be expensive — that's why we offer flexible financial solutions to help you manage the upfront costs of duties and taxes. Our import duty financing helps reduce cash flow strain while keeping your shipments moving.",
        'key_features' => [
            'Duty & Tax Payment Support',
            'Structured Repayment Plans',
            'Full Logistics Coordination',
            'Support for High-Volume Importers',
        ],
    ],
    [
        'title' => 'Courier Services',
        'image' => '/asset/service7_1768432163_2040.jpg',
        'description' => "For smaller shipments, documents, and packages that need to move quickly, our courier services offer secure and efficient delivery across the U.S. and globally. From next-day delivery to international express, we've got you covered.",
        'key_features' => [
            'Same-Day & Next-Day Delivery',
            'Secure Packaging & Handling',
            'Door-to-Door Delivery',
            'Tracking & Notifications',
        ],
    ],
    [
        'title' => 'Repackaging Services',
        'image' => '/asset/service8_1768432163_9339.jpg',
        'description' => 'We help you save space, reduce shipping costs, and comply with customs requirements through our professional repackaging solutions. Whether for retail, export, or warehousing, we ensure your goods are secure and presentation-ready.',
        'key_features' => [
            'Custom Packaging Solutions',
            'Cost Optimization',
            'Labeling & Compliance',
            'Product Repacking for Retail',
        ],
    ],
];

foreach ($services as &$service) {
    $service['cta_text'] = 'Track Shipment';
    $service['cta_link'] = '/track.php';
}
unset($service);

$downloadSection = [
    'heading' => 'FIND ALL IN ONE DOCUMENT',
    'text' => 'Download our service Brochures',
    'button_text' => 'Download PDF',
    'button_link' => '#',
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

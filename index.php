<?php
include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/location-modal.php';

$companyName = getSetting('company_name', 'FedEx');
$contactPhone = getSetting('contact_phone_number', '+1 (800) 555-0199');
$hqAddress = replacePlaceholders(getSetting('contact_hq_address', "6964 E Century Park Dr, Tucson, United States"));
$hqAddressLine = str_replace(["\r\n", "\n", "\r"], ', ', $hqAddress);
?>

<!-- Hero Section -->
<section class="hero-bg text-white relative py-16 lg:py-32">
    <div class="container mx-auto px-4 md:px-12 grid lg:grid-cols-2 gap-12 items-center text-center lg:text-left">
        <div class="flex flex-col items-center lg:items-start">
            <h1 class="text-4xl md:text-6xl font-extrabold mb-2"><?php echo htmlspecialchars(replacePlaceholders($companyName)); ?></h1>
            <h2 class="text-3xl md:text-5xl font-bold mb-6">Delivery service</h2>
            <div class="w-20 h-1 bg-yellow-400 mb-6 mx-auto lg:mx-0"></div>
            <p class="text-lg mb-8 max-w-lg leading-relaxed mx-auto lg:mx-0">
                We handles local handling and haulage of consignments from the pickup point to the customer's desired destination.
            </p>
            <a class="inline-flex items-center bg-yellow-400 text-black px-8 py-3 rounded-md font-bold hover:bg-yellow-500 transition-colors" href="/contact">
                <span class="material-symbols-outlined mr-2 text-base">mail</span> Get In Touch
            </a>
        </div>
        <div class="hero-glass-card p-5 lg:p-6 rounded-xl shadow-2xl mx-auto lg:ml-auto lg:mr-0 w-full">
            <form action="/track-result" method="GET">
                <div class="flex items-center mb-4">
                    <span class="material-symbols-outlined text-yellow-400 hero-track-icon mr-3">track_changes</span>
                    <h2 class="text-white text-lg font-bold uppercase tracking-tight">Track Shipment</h2>
                </div>
                <p class="text-white/60 text-xs mb-4">Enter your Waybill or Reference number to see real-time updates.</p>
                <div class="space-y-4">
                    <div class="relative">
                        <input
                            class="hero-track-input w-full px-3 py-2.5 text-sm font-mono tracking-widest placeholder:text-xs uppercase"
                            name="id"
                            placeholder="WAYBILL NUMBER"
                            type="text"
                            required
                        />
                    </div>
                    <button type="submit" class="w-full bg-white text-slate-900 py-3 text-xs uppercase font-bold tracking-widest hover:bg-yellow-400 hover:text-slate-900 transition-all">
                        Track Now
                    </button>
                    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-1 text-[10px] text-white/40 pt-2">
                        <span>Available 24/7 Global Support</span>
                        <a class="text-yellow-400 hover:underline" href="/track">Advanced Search</a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</section>

<!-- Info Strip -->
<section class="bg-black text-white py-6 md:py-12">
    <div class="container mx-auto px-3 md:px-12 grid grid-cols-3 gap-2 md:gap-8">
        <div class="info-strip-card">
            <div class="info-strip-icon">
                <span class="material-symbols-outlined">call</span>
            </div>
            <div class="min-w-0">
                <h4 class="font-bold text-[10px] md:text-lg uppercase leading-tight">Call Center</h4>
                <p class="text-gray-400 text-[9px] md:text-sm hidden md:block">Give us a direct call</p>
                <p class="font-semibold text-yellow-400 text-[9px] md:text-sm leading-snug mt-0.5 break-words"><?php echo htmlspecialchars($contactPhone); ?></p>
            </div>
        </div>
        <div class="info-strip-card">
            <div class="info-strip-icon">
                <span class="material-symbols-outlined">schedule</span>
            </div>
            <div class="min-w-0">
                <h4 class="font-bold text-[10px] md:text-lg uppercase leading-tight">Working Hours</h4>
                <p class="text-gray-400 text-[9px] md:text-sm leading-snug mt-0.5">Mon-Sat 7AM-5PM</p>
                <p class="text-gray-400 text-[9px] md:text-sm leading-snug hidden md:block">Sat 9AM-3PM</p>
            </div>
        </div>
        <div class="info-strip-card">
            <div class="info-strip-icon">
                <span class="material-symbols-outlined">location_on</span>
            </div>
            <div class="min-w-0">
                <h4 class="font-bold text-[10px] md:text-lg uppercase leading-tight">Our Location</h4>
                <p class="text-gray-400 text-[9px] md:text-sm leading-snug mt-0.5 break-words"><?php echo htmlspecialchars($hqAddressLine); ?></p>
            </div>
        </div>
    </div>
</section>

<!-- Services Section -->
<section class="py-20 dot-pattern dot-pattern-gray">
    <div class="container mx-auto px-4 md:px-12">
        <div class="text-center mb-16">
            <span class="text-yellow-500 font-bold uppercase tracking-widest text-sm">Our Services</span>
            <h2 class="text-4xl font-extrabold mt-2 text-slate-800">WHAT WE CAN DO FOR YOU</h2>
        </div>
        <div class="grid lg:grid-cols-12 gap-6 lg:gap-8 items-stretch">
            <div class="lg:col-span-4 order-1">
                <img alt="Service Vehicle" class="services-grid-image rounded-lg shadow-xl w-full h-full object-cover" src="/asset/home/service-vehicle.jpg"/>
            </div>
            <div class="lg:col-span-8 grid sm:grid-cols-2 gap-4 order-2">
                <div class="bg-white p-5 rounded-lg shadow-sm border-t-4 border-yellow-400 hover:shadow-md transition-shadow">
                    <div class="text-yellow-500 text-2xl mb-3"><span class="material-symbols-outlined">description</span></div>
                    <h3 class="text-base font-bold mb-2">Clearing &amp; Documentation</h3>
                    <p class="text-gray-600 text-xs leading-relaxed">We handle the entire customs clearing process for shipments and consignments at all major ports and terminals.</p>
                </div>
                <div class="bg-white p-5 rounded-lg shadow-sm border-t-4 border-yellow-400 hover:shadow-md transition-shadow">
                    <div class="text-yellow-500 text-2xl mb-3"><span class="material-symbols-outlined">package_2</span></div>
                    <h3 class="text-base font-bold mb-2">Courier Services</h3>
                    <p class="text-gray-600 text-xs leading-relaxed">Our dependable courier service covers documents, parcels, and lightweight cargo. Whether local or interstate.</p>
                </div>
                <div class="bg-white p-5 rounded-lg shadow-sm border-t-4 border-yellow-400 hover:shadow-md transition-shadow">
                    <div class="text-yellow-500 text-2xl mb-3"><span class="material-symbols-outlined">local_shipping</span></div>
                    <h3 class="text-base font-bold mb-2">Haulage and Local Transport</h3>
                    <p class="text-gray-600 text-xs leading-relaxed"><?php echo htmlspecialchars(replacePlaceholders($companyName)); ?> provides professional haulage and transport services for goods of all sizes.</p>
                </div>
                <div class="bg-white p-5 rounded-lg shadow-sm border-t-4 border-yellow-400 hover:shadow-md transition-shadow">
                    <div class="text-yellow-500 text-2xl mb-3"><span class="material-symbols-outlined">payments</span></div>
                    <h3 class="text-base font-bold mb-2">Import and Duty Finance</h3>
                    <p class="text-gray-600 text-xs leading-relaxed">Managing import duties can be complex and costly. We offer flexible financial assistance to help businesses cover customs-related expenses.</p>
                </div>
                <div class="bg-white p-5 rounded-lg shadow-sm border-t-4 border-yellow-400 hover:shadow-md transition-shadow">
                    <div class="text-yellow-500 text-2xl mb-3"><span class="material-symbols-outlined">inventory_2</span></div>
                    <h3 class="text-base font-bold mb-2">Repackaging</h3>
                    <p class="text-gray-600 text-xs leading-relaxed">Whether for export, retail distribution, or warehouse storage, our packaging and repackaging services are designed to protect your items.</p>
                </div>
                <div class="bg-white p-5 rounded-lg shadow-sm border-t-4 border-yellow-400 hover:shadow-md transition-shadow">
                    <div class="text-yellow-500 text-2xl mb-3"><span class="material-symbols-outlined">directions_boat</span></div>
                    <h3 class="text-base font-bold mb-2">Sea/Air Freight &amp; Bulk Cargo</h3>
                    <p class="text-gray-600 text-xs leading-relaxed">We provide efficient international shipping solutions via both air and sea. Our global network allows us to move your goods safely.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Why Choose Us -->
<section class="why-choose-bg py-24 text-white">
    <div class="container mx-auto px-4 md:px-12 grid lg:grid-cols-2 gap-12 items-center">
        <div>
            <span class="text-yellow-400 font-bold uppercase tracking-widest text-sm">Importance</span>
            <h2 class="text-5xl font-extrabold mt-2 mb-8">why Choose Us</h2>
        </div>
        <div class="space-y-6">
            <div class="bg-white p-6 rounded-lg shadow-md border-l-4 border-yellow-400">
                <h3 class="text-xl font-bold mb-2 text-slate-800">Efficiency...</h3>
                <p class="text-gray-600 text-sm">At <?php echo htmlspecialchars(replacePlaceholders($companyName)); ?>, we move more than just packages — we move businesses forward. With a strong track record, global network, and client-focused mindset.</p>
            </div>
            <div class="grid md:grid-cols-2 gap-6">
                <div class="bg-white p-6 rounded-lg shadow-md border-l-4 border-yellow-400">
                    <div class="text-2xl mb-2 text-yellow-500"><span class="material-symbols-outlined">support_agent</span></div>
                    <h4 class="font-bold mb-2 text-slate-800">Customer-Centric Approach</h4>
                    <p class="text-xs text-gray-600">We put you first. Our team is always ready to provide real-time updates and tailored solutions.</p>
                </div>
                <div class="bg-white p-6 rounded-lg shadow-md border-l-4 border-yellow-400">
                    <div class="text-2xl mb-2 text-yellow-500"><span class="material-symbols-outlined">public</span></div>
                    <h4 class="font-bold mb-2 text-slate-800">Nationwide &amp; Global Reach</h4>
                    <p class="text-xs text-gray-600">Whether shipping within the US or importing worldwide, we have the network to make it happen.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Partners -->
<section class="py-12 bg-gray-100">
    <div class="container mx-auto px-4 md:px-12 text-center">
        <h3 class="text-sm font-bold text-gray-500 uppercase mb-6">Our Partners</h3>
        <div class="partners-slider">
            <div class="partners-track">
                <div class="partners-slide"><img alt="FedEx" src="/asset/home/partner-fedex.webp" width="160" height="64"/></div>
                <div class="partners-slide"><img alt="USPS" src="/asset/home/partner-usps.webp" width="160" height="64"/></div>
                <div class="partners-slide"><img alt="UPS" src="/asset/home/partner-ups.webp" width="160" height="64"/></div>
                <div class="partners-slide"><img alt="DHL" src="/asset/home/partner-dhl.png" width="160" height="64"/></div>
                <div class="partners-slide"><img alt="MSC" src="/asset/home/partner-msc.webp" width="160" height="64"/></div>
            </div>
        </div>
    </div>
</section>

<!-- Testimonials -->
<section class="py-24 dot-pattern">
    <div class="container mx-auto px-4 md:px-12">
        <div class="text-center mb-16">
            <span class="text-yellow-500 font-bold uppercase tracking-widest text-sm">OUR CLIENTS</span>
            <h2 class="text-4xl font-extrabold mt-2 text-slate-800">CLIENTS THAT TRUST OUR SERVICES</h2>
        </div>
        <div class="grid lg:grid-cols-3 gap-8">
            <div class="bg-white p-8 rounded-lg shadow-lg relative border-b-4 border-yellow-400">
                <div class="flex text-yellow-400 mb-4">★★★★★</div>
                <p class="text-gray-600 italic mb-6">"We've worked with several logistics providers in the past, but <?php echo htmlspecialchars(replacePlaceholders($companyName)); ?> stands out. Their attention to detail, especially in handling documentation and customs, is unmatched."</p>
                <div class="flex items-center flex-wrap">
                    <div class="font-bold text-slate-800">Sarah Martinez</div>
                    <div class="ml-2 text-xs text-gray-500">Import Manager at EcoHomes</div>
                </div>
            </div>
            <div class="bg-white p-8 rounded-lg shadow-lg relative border-b-4 border-yellow-400">
                <div class="flex text-yellow-400 mb-4">★★★★★</div>
                <p class="text-gray-600 italic mb-6">"<?php echo htmlspecialchars(replacePlaceholders($companyName)); ?> made international shipping way less stressful for me. As someone running a growing business, I needed a partner I could trust—and they've delivered every time."</p>
                <div class="flex items-center flex-wrap">
                    <div class="font-bold text-slate-800">Lena Chen</div>
                    <div class="ml-2 text-xs text-gray-500">CEO of Modern Décor Co.</div>
                </div>
            </div>
            <div class="bg-white p-8 rounded-lg shadow-lg relative border-b-4 border-yellow-400">
                <div class="flex text-yellow-400 mb-4">★★★★★</div>
                <p class="text-gray-600 italic mb-6">"Reliable. Fast. Hassle-free. <?php echo htmlspecialchars(replacePlaceholders($companyName)); ?> has simplified our supply chain and helped us scale without shipping worries. Highly recommend!"</p>
                <div class="flex items-center flex-wrap">
                    <div class="font-bold text-slate-800">Ray Charles</div>
                    <div class="ml-2 text-xs text-gray-500">Small Business Owner</div>
                </div>
                <div class="absolute bottom-4 right-8 text-6xl text-gray-100 opacity-50">"</div>
            </div>
        </div>
    </div>
</section>

<!-- Global Reach -->
<section class="py-24 global-reach-bg relative overflow-hidden">
    <div class="container mx-auto px-4 md:px-12 relative z-10">
        <div class="text-center mb-16">
            <span class="text-yellow-400 text-xs font-bold uppercase tracking-[0.3em] block mb-4">Seamless Connectivity</span>
            <h2 class="text-4xl lg:text-5xl font-extrabold text-white mb-4">Our Global Reach</h2>
            <p class="text-white/60 max-w-2xl mx-auto text-lg">A strategic network of high-tech logistics hubs connecting the world's most vital economic centers.</p>
        </div>
        <div class="relative h-[500px] w-full rounded-3xl overflow-hidden border border-white/10">
            <div class="absolute inset-0 global-reach-map opacity-40 grayscale contrast-125"></div>
            <svg class="absolute inset-0 w-full h-full pointer-events-none" viewBox="0 0 1000 500" preserveAspectRatio="none" aria-hidden="true">
                <path d="M200,150 Q450,50 800,200" fill="none" opacity="0.6" stroke="#fbbf24" stroke-dasharray="5 5" stroke-width="1.5">
                    <animate attributeName="stroke-dashoffset" dur="5s" from="100" repeatCount="indefinite" to="0"></animate>
                </path>
                <path d="M150,200 Q400,300 850,250" fill="none" opacity="0.4" stroke="#fbbf24" stroke-dasharray="5 5" stroke-width="1">
                    <animate attributeName="stroke-dashoffset" dur="7s" from="100" repeatCount="indefinite" to="0"></animate>
                </path>
                <circle class="animate-ping" cx="200" cy="150" fill="#fbbf24" r="4"></circle>
                <circle class="animate-ping" cx="800" cy="200" fill="#fbbf24" r="4"></circle>
                <circle class="animate-ping" cx="150" cy="200" fill="#fbbf24" r="4"></circle>
                <circle class="animate-ping" cx="850" cy="250" fill="#fbbf24" r="4"></circle>
            </svg>
            <div class="absolute bottom-10 left-10 global-reach-glass p-6 rounded-xl hidden md:block">
                <div class="flex items-center space-x-6">
                    <div>
                        <p class="text-white/40 text-[10px] uppercase tracking-widest font-bold">Active Vessels</p>
                        <p class="text-white text-xl font-bold">4,281</p>
                    </div>
                    <div class="w-px h-10 bg-white/10"></div>
                    <div>
                        <p class="text-white/40 text-[10px] uppercase tracking-widest font-bold">Air Fleet</p>
                        <p class="text-white text-xl font-bold">824</p>
                    </div>
                    <div class="w-px h-10 bg-white/10"></div>
                    <div>
                        <p class="text-white/40 text-[10px] uppercase tracking-widest font-bold">Hub Efficiency</p>
                        <p class="text-white text-xl font-bold">98.4%</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>

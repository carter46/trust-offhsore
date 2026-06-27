<?php
include __DIR__ . '/includes/header.php';

$companyName = getSetting('company_name', 'FedEx');
$siteTitle = getSetting('site_title', 'Shipping, Logistics Management and Supply Chain Management');
?>
<style>
    .form-input:focus {
        box-shadow: 0 0 0 2px rgba(251, 191, 36, 0.25);
        border-color: #fbbf24;
    }
    @keyframes fade-in-up {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    .animate-fade-in-up {
        animation: fade-in-up 0.3s ease-out;
    }
</style>

<!-- Page Content -->
<main class="flex-1 w-full">
    <!-- Header Section -->
    <section class="page-hero-bg text-white py-12 lg:py-16">
        <div class="container mx-auto px-4 md:px-12">
            <div class="max-w-3xl">
                <div class="w-16 h-1 bg-yellow-400 mb-4"></div>
                <h1 class="text-white text-4xl lg:text-5xl font-extrabold leading-tight tracking-tight mb-4">
                    <?php echo htmlspecialchars(replacePlaceholders(getSetting('contact_page_title', 'Global Support & Inquiries'))); ?>
                </h1>
                <p class="text-gray-200 text-lg font-normal leading-relaxed">
                    <?php echo htmlspecialchars(replacePlaceholders(getSetting('contact_page_subtitle', 'Connect with our enterprise logistics team for tailored supply chain solutions. Our dedicated specialists are ready to optimize your global operations.'))); ?>
                </p>
            </div>
        </div>
    </section>

    <!-- Main Content Grid -->
    <section class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 lg:gap-16 items-start">
            <!-- Left: Contact Form (7 Columns) -->
            <div class="lg:col-span-7 flex flex-col gap-8">
                <div>
                    <h3 class="text-2xl font-bold text-slate-800 mb-6">
                        <?php echo htmlspecialchars(replacePlaceholders(getSetting('contact_form_title', 'Send us a message'))); ?>
                    </h3>
                    <form class="flex flex-col gap-6" id="contact-form" method="POST" action="#">
                        <!-- Row 1 -->
                        <div class="flex flex-col md:flex-row gap-6">
                            <label class="flex flex-col flex-1">
                                <span class="text-slate-800 text-sm font-semibold leading-normal pb-2">
                                    <?php echo htmlspecialchars(replacePlaceholders(getSetting('contact_company_label', 'Company Name'))); ?>
                                </span>
                                <input class="form-input flex w-full resize-none rounded-lg border border-gray-300 bg-white h-14 placeholder:text-gray-400 px-4 text-base text-slate-800 transition-colors focus:ring-yellow-400 focus:border-yellow-400" 
                                       placeholder="<?php echo htmlspecialchars(replacePlaceholders(getSetting('contact_company_placeholder', 'Enter company name'))); ?>" 
                                       type="text" name="company" required/>
                            </label>
                            <label class="flex flex-col flex-1">
                                <span class="text-slate-800 text-sm font-semibold leading-normal pb-2">
                                    <?php echo htmlspecialchars(replacePlaceholders(getSetting('contact_email_label', 'Professional Email'))); ?>
                                </span>
                                <input class="form-input flex w-full resize-none rounded-lg border border-gray-300 bg-white h-14 placeholder:text-gray-400 px-4 text-base text-slate-800 transition-colors focus:ring-yellow-400 focus:border-yellow-400" 
                                       placeholder="<?php echo htmlspecialchars(replacePlaceholders(getSetting('contact_email_placeholder', 'name@company.com'))); ?>" 
                                       type="email" name="email" required/>
                            </label>
                        </div>
                        <!-- Row 2 -->
                        <div class="flex flex-col md:flex-row gap-6">
                            <label class="flex flex-col flex-1">
                                <span class="text-slate-800 text-sm font-semibold leading-normal pb-2">
                                    <?php echo htmlspecialchars(replacePlaceholders(getSetting('contact_phone_label', 'Direct Phone'))); ?>
                                </span>
                                <input class="form-input flex w-full resize-none rounded-lg border border-gray-300 bg-white h-14 placeholder:text-gray-400 px-4 text-base text-slate-800 transition-colors focus:ring-yellow-400 focus:border-yellow-400" 
                                       placeholder="<?php echo htmlspecialchars(replacePlaceholders(getSetting('contact_phone_placeholder', '+1 (555) 000-0000'))); ?>" 
                                       type="tel" name="phone"/>
                            </label>
                            <label class="flex flex-col flex-1">
                                <span class="text-slate-800 text-sm font-semibold leading-normal pb-2">
                                    <?php echo htmlspecialchars(replacePlaceholders(getSetting('contact_inquiry_label', 'Inquiry Type'))); ?>
                                </span>
                                <div class="relative">
                                    <select class="form-input appearance-none flex w-full resize-none rounded-lg border border-gray-300 bg-white h-14 text-slate-800 px-4 text-base transition-colors cursor-pointer" name="inquiry_type" required>
                                        <option disabled="" selected="" value=""><?php echo htmlspecialchars(replacePlaceholders(getSetting('contact_inquiry_placeholder', 'Select inquiry type'))); ?></option>
                                        <option value="freight"><?php echo htmlspecialchars(replacePlaceholders(getSetting('contact_inquiry_freight', 'Freight Forwarding'))); ?></option>
                                        <option value="warehouse"><?php echo htmlspecialchars(replacePlaceholders(getSetting('contact_inquiry_warehouse', 'Warehousing & Storage'))); ?></option>
                                        <option value="customs"><?php echo htmlspecialchars(replacePlaceholders(getSetting('contact_inquiry_customs', 'Customs Brokerage'))); ?></option>
                                        <option value="tech"><?php echo htmlspecialchars(replacePlaceholders(getSetting('contact_inquiry_tech', 'Supply Chain Tech'))); ?></option>
                                    </select>
                                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-4 text-gray-500">
                                        <span class="material-symbols-outlined">expand_more</span>
                                    </div>
                                </div>
                            </label>
                        </div>
                        <!-- Message Area -->
                        <label class="flex flex-col w-full">
                            <span class="text-slate-800 text-sm font-semibold leading-normal pb-2">
                                <?php echo htmlspecialchars(replacePlaceholders(getSetting('contact_message_label', 'Message Details'))); ?>
                            </span>
                            <textarea class="form-input flex w-full resize-none rounded-lg border border-gray-300 bg-white min-h-[160px] placeholder:text-gray-400 p-4 text-base text-slate-800 transition-colors focus:ring-yellow-400 focus:border-yellow-400" 
                                      placeholder="<?php echo htmlspecialchars(replacePlaceholders(getSetting('contact_message_placeholder', 'Please describe your logistics requirements...'))); ?>" 
                                      name="message" required></textarea>
                        </label>
                        <div class="pt-4">
                            <button class="w-full md:w-auto px-8 h-12 bg-yellow-400 hover:bg-yellow-500 text-black font-bold rounded-lg shadow-sm hover:shadow-md transition-all flex items-center justify-center gap-2" type="submit">
                                <span><?php echo htmlspecialchars(replacePlaceholders(getSetting('contact_submit_text', 'Submit Inquiry'))); ?></span>
                                <span class="material-symbols-outlined text-sm">arrow_forward</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Right: Quick Contact Card (5 Columns) -->
            <div class="lg:col-span-5 sticky top-24">
                <div class="bg-slate-900 rounded-2xl p-8 lg:p-10 text-white shadow-xl relative overflow-hidden group border border-slate-700">
                    <div class="absolute top-0 right-0 w-64 h-64 bg-yellow-400/10 rounded-full blur-3xl -mr-16 -mt-16 pointer-events-none"></div>
                    <div class="relative z-10 flex flex-col gap-10">
                        <div>
                            <div class="flex items-center gap-3 mb-6">
                                <div class="w-10 h-10 rounded-full bg-white/10 flex items-center justify-center">
                                    <span class="material-symbols-outlined text-yellow-400">apartment</span>
                                </div>
                                <h4 class="text-lg font-bold tracking-tight">
                                    <?php echo htmlspecialchars(replacePlaceholders(getSetting('contact_hq_title', 'Global Headquarters'))); ?>
                                </h4>
                            </div>
                            <address class="not-italic text-gray-300 leading-relaxed text-lg border-l-2 border-yellow-400/50 pl-4">
                                <?php echo nl2br(htmlspecialchars(replacePlaceholders(getSetting('contact_hq_address', "1200 Logistics Blvd, Suite 500\nSan Francisco, CA 94107\nUnited States")))); ?>
                            </address>
                        </div>
                        <div>
                            <div class="flex items-center gap-3 mb-4">
                                <div class="w-10 h-10 rounded-full bg-white/10 flex items-center justify-center">
                                    <span class="material-symbols-outlined text-yellow-400">support_agent</span>
                                </div>
                                <h4 class="text-lg font-bold tracking-tight">
                                    <?php echo htmlspecialchars(replacePlaceholders(getSetting('contact_support_title', '24/7 Priority Support'))); ?>
                                </h4>
                            </div>
                            <?php 
                            $phoneDisplay = getSetting('contact_phone_number', '+1 (800) 555-0199');
                            $phoneRaw = preg_replace('/[^0-9+]/', '', $phoneDisplay); // Strip formatting for tel: link
                            ?>
                            <a class="text-3xl lg:text-4xl font-black text-white hover:text-yellow-400 transition-colors block tracking-tight" href="tel:<?php echo htmlspecialchars($phoneRaw); ?>">
                                <?php echo htmlspecialchars($phoneDisplay); ?>
                            </a>
                            <p class="text-gray-400 mt-2 text-sm">
                                <?php echo htmlspecialchars(replacePlaceholders(getSetting('contact_support_desc', 'Dedicated line for enterprise partners.'))); ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Full Width Map Section -->
    <section class="relative w-full h-[500px] bg-slate-100 overflow-hidden group">
        <div class="absolute inset-0 bg-gray-900/5 z-10 pointer-events-none"></div>
        <!-- Map Image -->
        <img alt="<?php echo htmlspecialchars(replacePlaceholders(getSetting('contact_map_alt', 'High contrast grayscale world map showing global logistics network'))); ?>" 
             class="w-full h-full object-cover grayscale opacity-60 mix-blend-multiply" 
             src="/asset/home/hero-bg.jpg"/>
        <!-- Map Content Overlay -->
        <div class="absolute inset-0 z-20 flex flex-col items-center justify-center pointer-events-none">
            <div class="text-center mb-8 bg-white/90 backdrop-blur-sm px-6 py-3 rounded-full shadow-lg border border-gray-200 pointer-events-auto">
                <h2 class="text-slate-800 font-bold text-lg flex items-center gap-2">
                    <span class="material-symbols-outlined text-yellow-500">public</span>
                    <?php echo htmlspecialchars(replacePlaceholders(getSetting('contact_map_title', 'Global Operational Hubs'))); ?>
                </h2>
            </div>
            <!-- Interactive Markers (Simulated) -->
            <div class="absolute top-1/3 left-1/4 pointer-events-auto group/marker cursor-pointer">
                <div class="w-4 h-4 bg-yellow-400 rounded-full shadow-[0_0_0_4px_rgba(251,191,36,0.3)] animate-pulse"></div>
                <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 hidden group-hover/marker:block bg-slate-900 text-white text-xs py-1 px-3 rounded whitespace-nowrap border border-slate-700">
                    <?php echo htmlspecialchars(replacePlaceholders(getSetting('contact_marker1', 'NA Distribution Center'))); ?>
                </div>
            </div>
            <div class="absolute top-1/4 left-1/2 pointer-events-auto group/marker cursor-pointer">
                <div class="w-4 h-4 bg-yellow-400 rounded-full shadow-[0_0_0_4px_rgba(251,191,36,0.3)]"></div>
                <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 hidden group-hover/marker:block bg-slate-900 text-white text-xs py-1 px-3 rounded whitespace-nowrap border border-slate-700">
                    <?php echo htmlspecialchars(replacePlaceholders(getSetting('contact_marker2', 'Euro HQ - London'))); ?>
                </div>
            </div>
            <div class="absolute top-1/2 right-1/4 pointer-events-auto group/marker cursor-pointer">
                <div class="w-4 h-4 bg-yellow-400 rounded-full shadow-[0_0_0_4px_rgba(251,191,36,0.3)] animate-pulse delay-75"></div>
                <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 hidden group-hover/marker:block bg-slate-900 text-white text-xs py-1 px-3 rounded whitespace-nowrap border border-slate-700">
                    <?php echo htmlspecialchars(replacePlaceholders(getSetting('contact_marker3', 'APAC Hub - Singapore'))); ?>
                </div>
            </div>
            <div class="absolute bottom-1/3 left-1/3 pointer-events-auto group/marker cursor-pointer">
                <div class="w-4 h-4 bg-yellow-400 rounded-full shadow-[0_0_0_4px_rgba(251,191,36,0.3)]"></div>
                <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 hidden group-hover/marker:block bg-slate-900 text-white text-xs py-1 px-3 rounded whitespace-nowrap border border-slate-700">
                    <?php echo htmlspecialchars(replacePlaceholders(getSetting('contact_marker4', 'SA Hub - Sao Paulo'))); ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Routing Cards Section -->
    <section class="bg-gray-50 py-20 border-t border-gray-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <!-- Technical Support Card -->
                <div class="group border border-gray-200 rounded-2xl p-8 hover:border-yellow-400/50 hover:shadow-lg transition-all cursor-pointer bg-white">
                    <div class="flex items-start justify-between mb-6">
                        <div class="bg-gray-50 p-3 rounded-xl shadow-sm border border-gray-100 group-hover:bg-yellow-400 group-hover:text-black transition-colors">
                            <span class="material-symbols-outlined text-3xl">build_circle</span>
                        </div>
                        <span class="material-symbols-outlined text-gray-300 group-hover:text-yellow-500 transition-colors">arrow_outward</span>
                    </div>
                    <h3 class="text-xl font-bold text-slate-800 mb-2">
                        <?php echo htmlspecialchars(replacePlaceholders(getSetting('contact_tech_title', 'Technical Support'))); ?>
                    </h3>
                    <p class="text-gray-500 mb-6">
                        <?php echo htmlspecialchars(replacePlaceholders(getSetting('contact_tech_desc', 'For existing clients facing integration issues, API downtime, or account access problems.'))); ?>
                    </p>
                    <span class="text-yellow-600 font-bold text-sm uppercase tracking-wide">
                        <?php echo htmlspecialchars(replacePlaceholders(getSetting('contact_tech_link', 'Open Ticket'))); ?>
                    </span>
                </div>
                <!-- Sales Inquiry Card -->
                <div class="group border border-gray-200 rounded-2xl p-8 hover:border-yellow-400/50 hover:shadow-lg transition-all cursor-pointer bg-white">
                    <div class="flex items-start justify-between mb-6">
                        <div class="bg-gray-50 p-3 rounded-xl shadow-sm border border-gray-100 group-hover:bg-yellow-400 group-hover:text-black transition-colors">
                            <span class="material-symbols-outlined text-3xl">handshake</span>
                        </div>
                        <span class="material-symbols-outlined text-gray-300 group-hover:text-yellow-500 transition-colors">arrow_outward</span>
                    </div>
                    <h3 class="text-xl font-bold text-slate-800 mb-2">
                        <?php echo htmlspecialchars(replacePlaceholders(getSetting('contact_sales_title', 'Sales & Partnerships'))); ?>
                    </h3>
                    <p class="text-gray-500 mb-6">
                        <?php echo htmlspecialchars(replacePlaceholders(getSetting('contact_sales_desc', 'Request a demo, discuss enterprise pricing, or explore partnership opportunities.'))); ?>
                    </p>
                    <span class="text-yellow-600 font-bold text-sm uppercase tracking-wide">
                        <?php echo htmlspecialchars(replacePlaceholders(getSetting('contact_sales_link', 'Contact Sales'))); ?>
                    </span>
                </div>
            </div>
        </div>
    </section>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('contact-form');
    
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Here you can add form submission logic
        // For now, just show an alert
        alert('<?php echo htmlspecialchars(replacePlaceholders(getSetting('contact_form_success', 'Thank you for your inquiry. We will get back to you soon!'))); ?>');
        
        // Optionally reset the form
        // form.reset();
    });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>

<?php
include __DIR__ . '/includes/header.php';

$companyName = getSetting('company_name', 'FedEx');
$siteTitle = getSetting('site_title', 'Shipping, Logistics Management and Supply Chain Management');
?>
<style>
    /* Custom scrollbar for webkit */
    ::-webkit-scrollbar {
        width: 8px;
    }
    ::-webkit-scrollbar-track {
        background: transparent;
    }
    ::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 4px;
    }
    ::-webkit-scrollbar-thumb:hover {
        background: #94a3b8;
    }
    .step-connector {
        flex-grow: 1;
        height: 2px;
        background-color: #e2e8f0;
        margin: 0 1rem;
    }
    .step-connector.active {
        background-color: #fbbf24;
    }
</style>

<!-- Main Content Layout -->
<main class="flex-grow w-full max-w-[1440px] mx-auto p-6 md:p-10 lg:px-20">
    <div class="flex flex-col lg:flex-row gap-8 xl:gap-16">
        <!-- LEFT COLUMN: Form Steps -->
        <div class="flex-1 min-w-0">
            <!-- Header -->
            <div class="mb-8">
                <h1 class="text-slate-900 text-3xl md:text-4xl font-extrabold tracking-tight mb-2">
                    <?php echo htmlspecialchars(replacePlaceholders(getSetting('shipping_page_title', 'Request a Logistics Quote'))); ?>
                </h1>
                <p class="text-slate-500 text-lg">
                    <?php echo htmlspecialchars(replacePlaceholders(getSetting('shipping_page_subtitle', 'Step 1 of 4: Tell us about your shipment'))); ?>
                </p>
            </div>
            
            <!-- Progress Stepper -->
            <div class="mb-10 w-full overflow-x-auto pb-4 md:pb-0">
                <div class="flex items-center justify-between min-w-[500px]">
                    <!-- Step 1 -->
                    <div class="flex flex-col items-center gap-2 group cursor-pointer">
                        <div class="flex items-center justify-center size-8 rounded-full bg-yellow-400 text-black font-bold text-sm ring-4 ring-yellow-100">1</div>
                        <span class="text-sm font-bold text-yellow-600"><?php echo htmlspecialchars(replacePlaceholders(getSetting('shipping_step1_label', 'Cargo Details'))); ?></span>
                    </div>
                    <div class="step-connector"></div>
                    <!-- Step 2 -->
                    <div class="flex flex-col items-center gap-2 group cursor-not-allowed">
                        <div class="flex items-center justify-center size-8 rounded-full bg-slate-200 dark:bg-slate-700 text-slate-500 dark:text-slate-400 font-bold text-sm">2</div>
                        <span class="text-sm font-medium text-slate-500 dark:text-slate-400"><?php echo htmlspecialchars(replacePlaceholders(getSetting('shipping_step2_label', 'Route'))); ?></span>
                    </div>
                    <div class="step-connector"></div>
                    <!-- Step 3 -->
                    <div class="flex flex-col items-center gap-2 group cursor-not-allowed">
                        <div class="flex items-center justify-center size-8 rounded-full bg-slate-200 dark:bg-slate-700 text-slate-500 dark:text-slate-400 font-bold text-sm">3</div>
                        <span class="text-sm font-medium text-slate-500 dark:text-slate-400"><?php echo htmlspecialchars(replacePlaceholders(getSetting('shipping_step3_label', 'Service Level'))); ?></span>
                    </div>
                    <div class="step-connector"></div>
                    <!-- Step 4 -->
                    <div class="flex flex-col items-center gap-2 group cursor-not-allowed">
                        <div class="flex items-center justify-center size-8 rounded-full bg-slate-200 dark:bg-slate-700 text-slate-500 dark:text-slate-400 font-bold text-sm">4</div>
                        <span class="text-sm font-medium text-slate-500 dark:text-slate-400"><?php echo htmlspecialchars(replacePlaceholders(getSetting('shipping_step4_label', 'Review'))); ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Step Content Form -->
            <form class="flex flex-col gap-8" id="shipping-form">
                <!-- Transport Mode -->
                <section class="flex flex-col gap-4">
                    <h3 class="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                        <span class="material-symbols-outlined text-yellow-500">category</span>
                        <?php echo htmlspecialchars(replacePlaceholders(getSetting('shipping_transport_title', 'Select Transport Mode'))); ?>
                    </h3>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <!-- Air -->
                        <label class="relative flex flex-col items-center gap-3 p-6 rounded-xl border-2 border-yellow-400 bg-yellow-400/5 dark:bg-yellow-400/10 cursor-pointer transition-all hover:shadow-md">
                            <input checked="" class="sr-only" name="mode" type="radio" value="air"/>
                            <div class="size-12 rounded-full bg-white dark:bg-surface-dark flex items-center justify-center text-yellow-500 shadow-sm">
                                <span class="material-symbols-outlined text-3xl">flight</span>
                            </div>
                            <div class="text-center">
                                <span class="block font-bold text-slate-900 dark:text-white"><?php echo htmlspecialchars(replacePlaceholders(getSetting('shipping_mode_air_title', 'Air Freight'))); ?></span>
                                <span class="text-xs text-slate-500 dark:text-slate-400"><?php echo htmlspecialchars(replacePlaceholders(getSetting('shipping_mode_air_desc', 'Fastest delivery'))); ?></span>
                            </div>
                            <div class="absolute top-3 right-3 text-yellow-500">
                                <span class="material-symbols-outlined text-xl">check_circle</span>
                            </div>
                        </label>
                        <!-- Ocean -->
                        <label class="relative flex flex-col items-center gap-3 p-6 rounded-xl border border-slate-200 dark:border-slate-700 bg-surface-light dark:bg-surface-dark cursor-pointer transition-all hover:border-yellow-400/50 hover:bg-slate-50 dark:hover:bg-slate-800">
                            <input class="sr-only" name="mode" type="radio" value="ocean"/>
                            <div class="size-12 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-slate-600 dark:text-slate-300">
                                <span class="material-symbols-outlined text-3xl">directions_boat</span>
                            </div>
                            <div class="text-center">
                                <span class="block font-bold text-slate-900 dark:text-white"><?php echo htmlspecialchars(replacePlaceholders(getSetting('shipping_mode_ocean_title', 'Ocean Freight'))); ?></span>
                                <span class="text-xs text-slate-500 dark:text-slate-400"><?php echo htmlspecialchars(replacePlaceholders(getSetting('shipping_mode_ocean_desc', 'Cost effective'))); ?></span>
                            </div>
                        </label>
                        <!-- Land -->
                        <label class="relative flex flex-col items-center gap-3 p-6 rounded-xl border border-slate-200 dark:border-slate-700 bg-surface-light dark:bg-surface-dark cursor-pointer transition-all hover:border-yellow-400/50 hover:bg-slate-50 dark:hover:bg-slate-800">
                            <input class="sr-only" name="mode" type="radio" value="land"/>
                            <div class="size-12 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-slate-600 dark:text-slate-300">
                                <span class="material-symbols-outlined text-3xl">local_shipping</span>
                            </div>
                            <div class="text-center">
                                <span class="block font-bold text-slate-900 dark:text-white"><?php echo htmlspecialchars(replacePlaceholders(getSetting('shipping_mode_land_title', 'Land Transport'))); ?></span>
                                <span class="text-xs text-slate-500 dark:text-slate-400"><?php echo htmlspecialchars(replacePlaceholders(getSetting('shipping_mode_land_desc', 'Regional'))); ?></span>
                            </div>
                        </label>
                    </div>
                </section>
                
                <hr class="border-slate-200 dark:border-slate-800"/>
                
                <!-- Shipment Specifics -->
                <section class="flex flex-col gap-6">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                            <span class="material-symbols-outlined text-yellow-500">inventory_2</span>
                            <?php echo htmlspecialchars(replacePlaceholders(getSetting('shipping_specifics_title', 'Shipment Specifics'))); ?>
                        </h3>
                        <!-- Unit Toggle -->
                        <div class="flex items-center gap-3 bg-slate-100 dark:bg-slate-800 p-1 rounded-lg">
                            <button class="px-3 py-1 text-xs font-bold rounded bg-white dark:bg-surface-dark shadow text-slate-900 dark:text-white" type="button"><?php echo htmlspecialchars(replacePlaceholders(getSetting('shipping_unit_metric', 'Metric (kg/cm)'))); ?></button>
                            <button class="px-3 py-1 text-xs font-medium text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white" type="button"><?php echo htmlspecialchars(replacePlaceholders(getSetting('shipping_unit_imperial', 'Imperial (lb/in)'))); ?></button>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="col-span-1">
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5"><?php echo htmlspecialchars(replacePlaceholders(getSetting('shipping_weight_label', 'Total Weight'))); ?></label>
                            <div class="relative">
                                <input class="w-full bg-white dark:bg-surface-dark border border-slate-300 dark:border-slate-600 rounded-lg px-4 py-3 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-yellow-400/50 focus:border-yellow-400 transition-shadow placeholder-slate-400" placeholder="0" type="number" name="weight" id="weight"/>
                                <span class="absolute right-4 top-3 text-slate-400 dark:text-slate-500 font-medium">kg</span>
                            </div>
                        </div>
                        <div class="col-span-1">
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5"><?php echo htmlspecialchars(replacePlaceholders(getSetting('shipping_volume_label', 'Total Volume'))); ?></label>
                            <div class="relative">
                                <input class="w-full bg-white dark:bg-surface-dark border border-slate-300 dark:border-slate-600 rounded-lg px-4 py-3 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-yellow-400/50 focus:border-yellow-400 transition-shadow placeholder-slate-400" placeholder="0.00" type="number" name="volume" id="volume"/>
                                <span class="absolute right-4 top-3 text-slate-400 dark:text-slate-500 font-medium">m³</span>
                            </div>
                        </div>
                        <div class="col-span-1 md:col-span-2">
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5"><?php echo htmlspecialchars(replacePlaceholders(getSetting('shipping_commodity_label', 'Commodity Type'))); ?></label>
                            <select class="w-full bg-white dark:bg-surface-dark border border-slate-300 dark:border-slate-600 rounded-lg px-4 py-3 text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-yellow-400/50 focus:border-yellow-400 transition-shadow" name="commodity" id="commodity">
                                <option disabled="" selected="" value=""><?php echo htmlspecialchars(replacePlaceholders(getSetting('shipping_commodity_placeholder', 'Select commodity...'))); ?></option>
                                <option value="electronics"><?php echo htmlspecialchars(replacePlaceholders(getSetting('shipping_commodity_electronics', 'Electronics'))); ?></option>
                                <option value="textiles"><?php echo htmlspecialchars(replacePlaceholders(getSetting('shipping_commodity_textiles', 'Textiles & Clothing'))); ?></option>
                                <option value="machinery"><?php echo htmlspecialchars(replacePlaceholders(getSetting('shipping_commodity_machinery', 'Machinery parts'))); ?></option>
                                <option value="automotive"><?php echo htmlspecialchars(replacePlaceholders(getSetting('shipping_commodity_automotive', 'Automotive'))); ?></option>
                                <option value="pharma"><?php echo htmlspecialchars(replacePlaceholders(getSetting('shipping_commodity_pharma', 'Pharmaceuticals'))); ?></option>
                            </select>
                        </div>
                        <div class="col-span-1 md:col-span-2">
                            <div class="p-4 bg-slate-50 dark:bg-slate-800/50 rounded-xl border border-slate-200 dark:border-slate-700">
                                <h4 class="text-sm font-bold text-slate-900 dark:text-white mb-4"><?php echo htmlspecialchars(replacePlaceholders(getSetting('shipping_dimensions_title', 'Package Dimensions (Optional)'))); ?></h4>
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                    <div>
                                        <label class="text-xs text-slate-500 dark:text-slate-400 mb-1 block"><?php echo htmlspecialchars(replacePlaceholders(getSetting('shipping_dim_length', 'Length'))); ?></label>
                                        <div class="relative">
                                            <input class="w-full bg-white dark:bg-surface-dark border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm text-slate-900 dark:text-white focus:ring-yellow-400 focus:border-yellow-400" type="number" name="length" id="length"/>
                                            <span class="absolute right-3 top-2 text-xs text-slate-400">cm</span>
                                        </div>
                                    </div>
                                    <div>
                                        <label class="text-xs text-slate-500 dark:text-slate-400 mb-1 block"><?php echo htmlspecialchars(replacePlaceholders(getSetting('shipping_dim_width', 'Width'))); ?></label>
                                        <div class="relative">
                                            <input class="w-full bg-white dark:bg-surface-dark border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm text-slate-900 dark:text-white focus:ring-yellow-400 focus:border-yellow-400" type="number" name="width" id="width"/>
                                            <span class="absolute right-3 top-2 text-xs text-slate-400">cm</span>
                                        </div>
                                    </div>
                                    <div>
                                        <label class="text-xs text-slate-500 dark:text-slate-400 mb-1 block"><?php echo htmlspecialchars(replacePlaceholders(getSetting('shipping_dim_height', 'Height'))); ?></label>
                                        <div class="relative">
                                            <input class="w-full bg-white dark:bg-surface-dark border border-slate-300 dark:border-slate-600 rounded-lg px-3 py-2 text-sm text-slate-900 dark:text-white focus:ring-yellow-400 focus:border-yellow-400" type="number" name="height" id="height"/>
                                            <span class="absolute right-3 top-2 text-xs text-slate-400">cm</span>
                                        </div>
                                    </div>
                                </div>
                                <button class="mt-4 text-sm text-yellow-500 font-medium hover:underline flex items-center gap-1" type="button">
                                    <span class="material-symbols-outlined text-lg">add</span>
                                    <?php echo htmlspecialchars(replacePlaceholders(getSetting('shipping_add_package', 'Add another package type'))); ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </section>
                
                <hr class="border-slate-200 dark:border-slate-800"/>
                
                <!-- Dangerous Goods -->
                <section class="flex flex-col gap-4">
                    <div class="flex items-start gap-4 p-4 rounded-xl border border-orange-200 bg-orange-50 dark:bg-orange-900/10 dark:border-orange-900/30">
                        <span class="material-symbols-outlined text-orange-600 dark:text-orange-400 mt-1">warning</span>
                        <div class="flex-1">
                            <h4 class="text-base font-bold text-slate-900 dark:text-white"><?php echo htmlspecialchars(replacePlaceholders(getSetting('shipping_dangerous_title', 'Dangerous Goods Declaration'))); ?></h4>
                            <p class="text-sm text-slate-600 dark:text-slate-400 mt-1"><?php echo htmlspecialchars(replacePlaceholders(getSetting('shipping_dangerous_desc', 'Does this shipment contain hazardous materials, lithium batteries, or chemicals?'))); ?></p>
                        </div>
                        <div class="flex items-center">
                            <label class="inline-flex items-center cursor-pointer">
                                <input class="sr-only peer" type="checkbox" name="dangerous_goods" value="1"/>
                                <div class="relative w-11 h-6 bg-slate-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-primary/30 dark:peer-focus:ring-primary/20 rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-primary"></div>
                                <span class="ms-3 text-sm font-medium text-slate-900 dark:text-slate-300"><?php echo htmlspecialchars(replacePlaceholders(getSetting('shipping_dangerous_yes', 'Yes'))); ?></span>
                            </label>
                        </div>
                    </div>
                </section>
                
                <!-- Navigation Buttons -->
                <div class="flex items-center justify-between pt-6 mt-4">
                    <a href="/" class="px-8 py-3 rounded-lg text-slate-600 dark:text-slate-300 font-bold hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
                        <?php echo htmlspecialchars(replacePlaceholders(getSetting('shipping_cancel_text', 'Cancel'))); ?>
                    </a>
                    <button class="flex items-center gap-2 px-8 py-3 rounded-lg bg-primary hover:bg-yellow-500 text-white font-bold shadow-lg shadow-blue-500/30 transition-all transform hover:-translate-y-0.5" type="submit">
                        <?php echo htmlspecialchars(replacePlaceholders(getSetting('shipping_continue_text', 'Continue to Route'))); ?>
                        <span class="material-symbols-outlined text-sm">arrow_forward</span>
                    </button>
                </div>
            </form>
        </div>
        
        <!-- RIGHT COLUMN: Sticky Summary -->
        <div class="hidden lg:block w-[360px] flex-shrink-0">
            <div class="sticky top-28 flex flex-col gap-6">
                <!-- Summary Card -->
                <div class="bg-surface-light dark:bg-surface-dark rounded-2xl shadow-xl shadow-slate-200/50 dark:shadow-none border border-slate-100 dark:border-slate-800 overflow-hidden">
                    <div class="p-6 border-b border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/50">
                        <h2 class="text-xl font-bold text-slate-900 dark:text-white"><?php echo htmlspecialchars(replacePlaceholders(getSetting('shipping_summary_title', 'Quote Summary'))); ?></h2>
                        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1"><?php echo htmlspecialchars(replacePlaceholders(getSetting('shipping_summary_ref', 'Reference: #PENDING'))); ?></p>
                    </div>
                    <!-- Dynamic Content Area -->
                    <div class="p-6 flex flex-col gap-6">
                        <!-- Mode Summary -->
                        <div class="flex items-center gap-4">
                            <div class="size-10 rounded-lg bg-blue-50 dark:bg-blue-900/20 flex items-center justify-center text-yellow-500">
                                <span class="material-symbols-outlined">flight</span>
                            </div>
                            <div>
                                <p class="text-xs font-bold text-slate-400 uppercase tracking-wide"><?php echo htmlspecialchars(replacePlaceholders(getSetting('shipping_summary_mode_label', 'Transport Mode'))); ?></p>
                                <p class="text-slate-900 dark:text-white font-medium" id="summary-mode"><?php echo htmlspecialchars(replacePlaceholders(getSetting('shipping_summary_mode_default', 'Air Freight'))); ?></p>
                            </div>
                        </div>
                        <!-- Weight Placeholder -->
                        <div class="flex items-center gap-4 opacity-50">
                            <div class="size-10 rounded-lg bg-slate-50 dark:bg-slate-800 flex items-center justify-center text-slate-400">
                                <span class="material-symbols-outlined">scale</span>
                            </div>
                            <div>
                                <p class="text-xs font-bold text-slate-400 uppercase tracking-wide"><?php echo htmlspecialchars(replacePlaceholders(getSetting('shipping_summary_weight_label', 'Total Weight'))); ?></p>
                                <p class="text-slate-900 dark:text-white font-medium" id="summary-weight">-- kg</p>
                            </div>
                        </div>
                        <!-- Map Placeholder -->
                        <div class="relative w-full h-32 rounded-lg bg-slate-100 dark:bg-slate-800 overflow-hidden group">
                            <div class="absolute inset-0 bg-cover bg-center opacity-60 dark:opacity-40 grayscale group-hover:grayscale-0 transition-all duration-500" style="background-image: url('<?php echo htmlspecialchars(getSetting('shipping_map_placeholder', 'https://placeholder.pics/svg/300')); ?>');"></div>
                            <div class="absolute inset-0 flex items-center justify-center">
                                <span class="text-xs font-bold bg-white/80 dark:bg-black/60 px-3 py-1 rounded-full backdrop-blur-sm text-slate-600 dark:text-slate-300 border border-white/20"><?php echo htmlspecialchars(replacePlaceholders(getSetting('shipping_map_placeholder_text', 'Route not selected'))); ?></span>
                            </div>
                        </div>
                        <div class="border-t border-dashed border-slate-200 dark:border-slate-700 my-2"></div>
                        <div class="flex items-end justify-between">
                            <div>
                                <p class="text-sm text-slate-500 dark:text-slate-400"><?php echo htmlspecialchars(replacePlaceholders(getSetting('shipping_summary_total_label', 'Estimated Total'))); ?></p>
                            </div>
                            <div class="text-right">
                                <p class="text-2xl font-bold text-slate-300 dark:text-slate-600">---</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Support Box -->
                <div class="bg-yellow-400/5 dark:bg-yellow-400/10 rounded-xl p-4 border border-yellow-400/10 flex items-start gap-3">
                    <span class="material-symbols-outlined text-yellow-500 mt-0.5">support_agent</span>
                    <div>
                        <p class="text-sm font-bold text-slate-900 dark:text-white"><?php echo htmlspecialchars(replacePlaceholders(getSetting('shipping_support_title', 'Need help calculating?'))); ?></p>
                        <p class="text-xs text-slate-600 dark:text-slate-400 mt-1"><?php echo htmlspecialchars(replacePlaceholders(getSetting('shipping_support_desc', 'Our logistics experts are available 24/7 to assist with complex shipments.'))); ?></p>
                        <a class="text-xs font-bold text-yellow-500 hover:underline mt-2 inline-block" href="#"><?php echo htmlspecialchars(replacePlaceholders(getSetting('shipping_support_link', 'Contact Support'))); ?></a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Notification Modal -->
<div id="shipping-notification-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4 hidden">
    <div class="bg-white dark:bg-surface-dark w-full max-w-2xl rounded-2xl shadow-2xl relative animate-fade-in-up overflow-hidden">
        <button id="notification-modal-close" class="absolute top-4 right-4 text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200 transition z-10">
            <span class="material-symbols-outlined text-2xl">close</span>
        </button>
        <div class="p-8 md:p-12">
            <!-- Icon -->
            <div class="flex justify-center mb-6">
                <div class="size-20 rounded-full bg-orange-100 dark:bg-orange-900/30 flex items-center justify-center">
                    <span class="material-symbols-outlined text-4xl text-orange-600 dark:text-orange-400">info</span>
                </div>
            </div>
            
            <!-- Title -->
            <h2 class="text-2xl md:text-3xl font-bold text-slate-900 dark:text-white text-center mb-4">
                <?php echo htmlspecialchars(replacePlaceholders(getSetting('shipping_notification_title', 'Online Shipping Currently Unavailable'))); ?>
            </h2>
            
            <!-- Message -->
            <p class="text-slate-600 dark:text-slate-400 text-center text-lg mb-8 leading-relaxed">
                <?php echo htmlspecialchars(replacePlaceholders(getSetting('shipping_notification_message', 'We apologize, but online shipping is currently not available for designing packages. Please visit our nearest location or contact our support team for further information and assistance.'))); ?>
            </p>
            
            <!-- Action Buttons -->
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="/contact" id="find-location-btn" class="bg-primary hover:bg-yellow-500 text-white px-8 py-3 rounded-lg font-bold text-base transition-all shadow-lg shadow-primary/30 flex items-center justify-center gap-2">
                    <span class="material-symbols-outlined text-sm">location_on</span>
                    <?php echo htmlspecialchars(replacePlaceholders(getSetting('shipping_notification_find_location', 'Find Nearest Location'))); ?>
                </a>
                <a href="/contact" id="contact-support-btn" class="bg-transparent border-2 border-slate-300 dark:border-slate-600 hover:border-yellow-400 text-slate-900 dark:text-white px-8 py-3 rounded-lg font-bold text-base transition-all flex items-center justify-center gap-2">
                    <span class="material-symbols-outlined text-sm">support_agent</span>
                    <?php echo htmlspecialchars(replacePlaceholders(getSetting('shipping_notification_contact_support', 'Contact Support'))); ?>
                </a>
            </div>
            
            <!-- Additional Info -->
            <div class="mt-8 pt-8 border-t border-slate-200 dark:border-slate-700">
                <p class="text-sm text-slate-500 dark:text-slate-400 text-center">
                    <?php echo htmlspecialchars(replacePlaceholders(getSetting('shipping_notification_footer', 'Our team is available 24/7 to assist you with your shipping needs.'))); ?>
                </p>
            </div>
        </div>
    </div>
</div>

<script>
// Update summary when form changes
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('shipping-form');
    const modeInputs = form.querySelectorAll('input[name="mode"]');
    const weightInput = document.getElementById('weight');
    const summaryMode = document.getElementById('summary-mode');
    const summaryWeight = document.getElementById('summary-weight');
    const notificationModal = document.getElementById('shipping-notification-modal');
    const closeModalBtn = document.getElementById('notification-modal-close');
    const findLocationBtn = document.getElementById('find-location-btn');
    const contactSupportBtn = document.getElementById('contact-support-btn');
    
    // Update summary when form changes
    modeInputs.forEach(input => {
        input.addEventListener('change', function() {
            const labels = {
                'air': '<?php echo htmlspecialchars(replacePlaceholders(getSetting('shipping_mode_air_title', 'Air Freight'))); ?>',
                'ocean': '<?php echo htmlspecialchars(replacePlaceholders(getSetting('shipping_mode_ocean_title', 'Ocean Freight'))); ?>',
                'land': '<?php echo htmlspecialchars(replacePlaceholders(getSetting('shipping_mode_land_title', 'Land Transport'))); ?>'
            };
            summaryMode.textContent = labels[this.value] || 'Air Freight';
        });
    });
    
    weightInput.addEventListener('input', function() {
        if (this.value) {
            summaryWeight.textContent = this.value + ' kg';
            summaryWeight.parentElement.parentElement.classList.remove('opacity-50');
        } else {
            summaryWeight.textContent = '-- kg';
            summaryWeight.parentElement.parentElement.classList.add('opacity-50');
        }
    });
    
    // Handle form submission
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        notificationModal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    });
    
    // Close modal handlers
    function closeModal() {
        notificationModal.classList.add('hidden');
        document.body.style.overflow = '';
    }
    
    closeModalBtn.addEventListener('click', closeModal);
    
    // Close on backdrop click
    notificationModal.addEventListener('click', function(e) {
        if (e.target === notificationModal) {
            closeModal();
        }
    });
    
    // Close on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && !notificationModal.classList.contains('hidden')) {
            closeModal();
        }
    });
    
    // Set contact support link to contact page
    contactSupportBtn.href = '/contact';
    
    // Set find location link to contact page
    findLocationBtn.href = '/contact';
});
</script>

<style>
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

<?php include __DIR__ . '/includes/footer.php'; ?>

<?php
/**
 * Location Selection Modal
 * Shows on homepage to let users select their location
 */
$companyName = getSetting('company_name', 'FedEx');
?>
<div id="location-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4 hidden">
    <div class="bg-background-light dark:bg-surface-dark w-full max-w-3xl rounded shadow-modal relative animate-fade-in-up">
        <button id="location-modal-close" class="absolute top-4 right-4 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 transition">
            <span class="material-icons-outlined">close</span>
        </button>
        <div class="p-8 md:p-12 text-center">
            <h2 class="text-2xl md:text-3xl font-light text-text-light dark:text-white mb-2">Choose your location</h2>
            <p class="text-gray-500 dark:text-gray-400 mb-10 text-sm">Select the correct location</p>
            <div id="location-grid" class="grid grid-cols-1 md:grid-cols-2 gap-8 max-w-lg mx-auto relative">
                <div id="location-divider" class="hidden md:block absolute left-1/2 top-0 bottom-0 w-px bg-gray-200 dark:bg-gray-600 -translate-x-1/2"></div>
                
                <!-- Detected Location (will be populated dynamically) -->
                <div id="detected-location" class="flex flex-col items-center hidden">
                    <span id="detected-country" class="text-lg font-medium mb-4 text-text-light dark:text-white"></span>
                    <button class="location-option bg-secondary hover:bg-orange-600 text-white font-bold py-3 px-8 w-full uppercase tracking-wider mb-4 transition" id="detected-language-btn">
                        <span id="detected-language-text"></span>
                    </button>
                    <button class="location-option text-primary dark:text-blue-400 font-bold text-sm uppercase hover:underline" id="detected-english-btn">
                        English
                    </button>
                </div>
                
                <!-- United States (Default) -->
                <div class="flex flex-col items-center">
                    <span class="text-lg font-medium mb-4 text-text-light dark:text-white">United States</span>
                    <button class="location-option bg-transparent border-2 border-secondary text-secondary hover:bg-secondary hover:text-white font-bold py-3 px-8 w-full uppercase tracking-wider mb-4 transition" data-location="united-states" data-language="english">
                        English
                    </button>
                    <button class="location-option text-primary dark:text-blue-400 font-bold text-sm uppercase hover:underline" data-location="united-states" data-language="espanol">
                        Español
                    </button>
                </div>
            </div>
            <div class="mt-12">
                <button class="location-option text-gray-600 dark:text-gray-400 underline text-sm hover:text-gray-900 dark:hover:text-white" data-location="other">
                    Search for another country/territory
                </button>
            </div>
        </div>
    </div>
</div>


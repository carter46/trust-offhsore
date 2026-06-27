/**
 * Location Modal Handler
 * Shows location selection modal on homepage
 * Detects user location via IP and shows United States as default
 */

document.addEventListener('DOMContentLoaded', function() {
    const locationModal = document.getElementById('location-modal');
    const closeButton = document.getElementById('location-modal-close');
    
    // Check if user has already selected a location
    const locationSelected = localStorage.getItem('locationSelected');
    
    // Only show on homepage
    const isHomepage = window.location.pathname === '/' || window.location.pathname === '/index.php';
    
    if (!locationSelected && isHomepage && locationModal) {
        // Fetch user's location and populate modal
        fetchLocationAndShowModal();
    } else {
        // Still set up event handlers even if modal won't show
        setupEventHandlers();
    }
    
    /**
     * Fetch location from API and show modal
     */
    async function fetchLocationAndShowModal() {
        try {
            const response = await fetch('/api/detect-location.php');
            const data = await response.json();
            
            if (data.success) {
                // If a location was detected and it's not US, show it
                if (data.detected && data.detected.country_code !== 'US') {
                    populateDetectedLocation(data.detected);
                }
                
                // Show modal after populating location
                setTimeout(() => {
                    locationModal.classList.remove('hidden');
                    document.body.style.overflow = 'hidden';
                }, 500);
            } else {
                // Show modal with just US if detection fails
                setTimeout(() => {
                    locationModal.classList.remove('hidden');
                    document.body.style.overflow = 'hidden';
                }, 500);
            }
        } catch (error) {
            console.error('Error detecting location:', error);
            // Show modal with just US if API fails
            setTimeout(() => {
                locationModal.classList.remove('hidden');
                document.body.style.overflow = 'hidden';
            }, 500);
        }
        
        // Set up event handlers after modal is ready
        setupEventHandlers();
    }
    
    /**
     * Populate detected location in modal
     */
    function populateDetectedLocation(location) {
        const detectedLocationDiv = document.getElementById('detected-location');
        const detectedCountry = document.getElementById('detected-country');
        const detectedLanguageBtn = document.getElementById('detected-language-btn');
        const detectedLanguageText = document.getElementById('detected-language-text');
        const detectedEnglishBtn = document.getElementById('detected-english-btn');
        
        if (detectedLocationDiv && detectedCountry && detectedLanguageBtn && detectedLanguageText && detectedEnglishBtn) {
            detectedCountry.textContent = location.country;
            detectedLanguageText.textContent = location.language;
            
            // Set data attributes
            detectedLanguageBtn.setAttribute('data-location', location.country_code.toLowerCase());
            detectedLanguageBtn.setAttribute('data-language', location.language.toLowerCase());
            detectedEnglishBtn.setAttribute('data-location', location.country_code.toLowerCase());
            detectedEnglishBtn.setAttribute('data-language', 'english');
            
            // Show the detected location div
            detectedLocationDiv.classList.remove('hidden');
            
            // Show divider if both locations are visible
            const divider = document.getElementById('location-divider');
            if (divider) {
                divider.classList.remove('hidden');
            }
        } else {
            // If no detected location, adjust grid to single column
            const locationGrid = document.getElementById('location-grid');
            if (locationGrid) {
                locationGrid.classList.remove('md:grid-cols-2');
                locationGrid.classList.add('md:grid-cols-1');
            }
        }
    }
    
    /**
     * Set up all event handlers
     */
    function setupEventHandlers() {
        const locationOptions = document.querySelectorAll('.location-option');
        
        // Close button handler
        if (closeButton) {
            closeButton.addEventListener('click', function() {
                // Save preference so modal doesn't show again
                localStorage.setItem('locationSelected', 'true');
                closeModal();
            });
        }
        
        // Location option handlers
        locationOptions.forEach(option => {
            option.addEventListener('click', function() {
                const location = this.getAttribute('data-location') || 'unknown';
                const language = this.getAttribute('data-language') || 'unknown';
                
                // Save preference (we don't actually use the values, just mark as selected)
                localStorage.setItem('locationSelected', 'true');
                localStorage.setItem('selectedLocation', location);
                localStorage.setItem('selectedLanguage', language);
                
                closeModal();
            });
        });
        
        // Close on backdrop click
        if (locationModal) {
            locationModal.addEventListener('click', function(e) {
                if (e.target === locationModal) {
                    // Save preference so modal doesn't show again
                    localStorage.setItem('locationSelected', 'true');
                    closeModal();
                }
            });
        }
        
        // ESC key to close
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && locationModal && !locationModal.classList.contains('hidden')) {
                // Save preference so modal doesn't show again
                localStorage.setItem('locationSelected', 'true');
                closeModal();
            }
        });
    }
    
    /**
     * Close modal function
     */
    function closeModal() {
        if (locationModal) {
            locationModal.classList.add('hidden');
            document.body.style.overflow = ''; // Restore scrolling
        }
    }
});


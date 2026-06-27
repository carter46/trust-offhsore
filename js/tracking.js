/**
 * Tracking JavaScript
 * Handles tracking form submission and data display
 */

document.addEventListener('DOMContentLoaded', function() {
    // Handle main tracking form (track.php)
    const trackForm = document.getElementById('track-form');
    const trackButton = document.getElementById('track-button');
    const trackingInput = document.getElementById('tracking-number');
    const errorMessage = document.getElementById('error-message');
    
    // Handle homepage tracking form (index.php)
    const homeTrackForm = document.getElementById('home-track-form');
    const homeTrackingInput = document.getElementById('home-tracking-input');
    
    if (trackForm && trackButton && trackingInput) {
        // Enable button if input has value
        trackingInput.addEventListener('input', function() {
            const value = this.value.trim();
            if (value) {
                trackButton.classList.remove('opacity-50', 'cursor-not-allowed');
                trackButton.disabled = false;
            } else {
                trackButton.classList.add('opacity-50', 'cursor-not-allowed');
                trackButton.disabled = true;
            }
        });
        
        // Check initial value
        if (trackingInput.value.trim()) {
            trackButton.classList.remove('opacity-50', 'cursor-not-allowed');
            trackButton.disabled = false;
        }
        
        trackForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const trackingId = trackingInput.value.trim();
            if (!trackingId) {
                showError('Please enter a tracking number');
                return;
            }
            
            // Clean tracking number (remove spaces, newlines)
            const cleanTrackingId = trackingId.replace(/\s+/g, ' ').trim();
            
            // Disable button and show loading
            trackButton.disabled = true;
            trackButton.textContent = 'Tracking...';
            hideError();
            
            try {
                // Redirect to track result page
                window.location.href = `/track-result?id=${encodeURIComponent(cleanTrackingId)}`;
            } catch (error) {
                showError('An error occurred. Please try again.');
                trackButton.disabled = false;
                trackButton.textContent = 'Track';
            }
        });
    }
    
    // Handle homepage tracking form (works without JS, but adds validation)
    if (homeTrackForm && homeTrackingInput) {
        homeTrackForm.addEventListener('submit', function(e) {
            const trackingId = homeTrackingInput.value.trim();
            if (!trackingId) {
                e.preventDefault();
                return false;
            }
            // Clean tracking number before submission
            const cleanTrackingId = trackingId.replace(/\s+/g, ' ').trim();
            homeTrackingInput.value = cleanTrackingId;
        });
    }
});

function showError(message) {
    const errorMessage = document.getElementById('error-message');
    if (errorMessage) {
        errorMessage.textContent = message;
        errorMessage.classList.remove('hidden');
    }
}

function hideError() {
    const errorMessage = document.getElementById('error-message');
    if (errorMessage) {
        errorMessage.classList.add('hidden');
    }
}


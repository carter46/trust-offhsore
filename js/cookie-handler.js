/**
 * Cookie Handler
 * Handles cookie consent modal functionality
 */

document.addEventListener('DOMContentLoaded', function() {
    const cookieBanner = document.getElementById('cookie-banner');
    const acceptAllBtn = document.querySelector('[data-cookie-action="accept-all"]');
    const rejectBtn = document.querySelector('[data-cookie-action="reject"]');
    const preferencesBtn = document.querySelector('[data-cookie-action="preferences"]');
    
    // Check if user has already made a choice
    const cookieConsent = localStorage.getItem('cookieConsent');
    
    if (cookieConsent) {
        // User has already made a choice, hide the banner
        if (cookieBanner) {
            cookieBanner.style.display = 'none';
        }
        return;
    }
    
    // Show banner if it exists and no consent stored
    if (cookieBanner) {
        cookieBanner.style.display = 'block';
    }
    
    // Accept All Cookies
    if (acceptAllBtn) {
        acceptAllBtn.addEventListener('click', function(e) {
            e.preventDefault();
            handleCookieConsent('accept-all');
        });
    }
    
    // Reject Optional Cookies
    if (rejectBtn) {
        rejectBtn.addEventListener('click', function(e) {
            e.preventDefault();
            handleCookieConsent('reject');
        });
    }
    
    // Cookie Preferences (just accept for now, can be expanded)
    if (preferencesBtn) {
        preferencesBtn.addEventListener('click', function(e) {
            e.preventDefault();
            // For now, just accept all when preferences clicked
            // Can be expanded to show preferences modal
            handleCookieConsent('accept-all');
        });
    }
    
    // Close button if exists
    const closeBtn = cookieBanner?.querySelector('[data-cookie-close]');
    if (closeBtn) {
        closeBtn.addEventListener('click', function(e) {
            e.preventDefault();
            handleCookieConsent('reject'); // Default to reject if just closing
        });
    }
});

/**
 * Handle cookie consent
 */
function handleCookieConsent(action) {
    const cookieBanner = document.getElementById('cookie-banner');
    const preferences = {
        functional: true, // Always true
        analytical: action === 'accept-all',
        tracking: action === 'accept-all',
        timestamp: new Date().toISOString()
    };
    
    // Save to localStorage
    localStorage.setItem('cookieConsent', JSON.stringify(preferences));
    localStorage.setItem('cookieConsentAction', action);
    
    // Save to database via API (optional, for analytics)
    saveCookiePreferencesToDB(preferences);
    
    // Hide banner with animation
    if (cookieBanner) {
        cookieBanner.style.transition = 'opacity 0.3s ease-out, transform 0.3s ease-out';
        cookieBanner.style.opacity = '0';
        cookieBanner.style.transform = 'translateY(100%)';
        
        setTimeout(() => {
            cookieBanner.style.display = 'none';
        }, 300);
    }
}

/**
 * Save cookie preferences to database
 */
async function saveCookiePreferencesToDB(preferences) {
    try {
        const sessionId = getSessionId();
        const response = await fetch('/api/cookie-preferences.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                session_id: sessionId,
                preferences: JSON.stringify(preferences)
            })
        });
        
        // Don't show error to user, just log
        if (!response.ok) {
            console.log('Failed to save cookie preferences to database');
        }
    } catch (error) {
        console.log('Error saving cookie preferences:', error);
    }
}

/**
 * Get or create session ID
 */
function getSessionId() {
    let sessionId = sessionStorage.getItem('sessionId');
    if (!sessionId) {
        sessionId = 'sess_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
        sessionStorage.setItem('sessionId', sessionId);
    }
    return sessionId;
}


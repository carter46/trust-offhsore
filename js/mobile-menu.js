/**
 * Mobile Menu Toggle and Expand/Collapse Functionality
 */

function getMobileMenuEl() {
    return document.getElementById('mobileMenu');
}

function isMobileViewport() {
    // Menu button is visible up to Tailwind's lg breakpoint (1024px)
    return window.innerWidth < 1024;
}

function closeMobileMenu(menu) {
    if (!menu) return;
    menu.classList.remove('translate-y-0');
    menu.classList.add('-translate-y-full');
    document.body.style.overflow = '';

    // Hide after the transition completes (event-based, no timers)
    const onEnd = (e) => {
        if (e.propertyName !== 'transform') return;
        menu.classList.add('hidden');
        menu.removeEventListener('transitionend', onEnd);
        // #region agent log
        console.log('[mobile-menu] closed', { display: getComputedStyle(menu).display, transform: getComputedStyle(menu).transform, classes: menu.className });
        // #endregion
    };
    menu.addEventListener('transitionend', onEnd);
}

function openMobileMenu(menu) {
    if (!menu) return;
    menu.classList.remove('hidden');
    menu.classList.remove('-translate-y-full');
    menu.classList.add('translate-y-0');
    document.body.style.overflow = 'hidden';
    // #region agent log
    console.log('[mobile-menu] opened', { display: getComputedStyle(menu).display, transform: getComputedStyle(menu).transform, classes: menu.className, width: window.innerWidth });
    // #endregion
}

// Ensure menu starts closed on page load
document.addEventListener('DOMContentLoaded', function() {
    const menu = getMobileMenuEl();
    if (!menu) return;

    // Always initialize closed
    menu.classList.add('hidden');
    menu.classList.add('-translate-y-full');
    menu.classList.remove('translate-y-0');
    document.body.style.overflow = '';

    // #region agent log
    console.log('[mobile-menu] init', { isMobile: isMobileViewport(), display: getComputedStyle(menu).display, transform: getComputedStyle(menu).transform, classes: menu.className, width: window.innerWidth });
    // #endregion

    // If user resizes to desktop, force close
    window.addEventListener('resize', function() {
        const m = getMobileMenuEl();
        if (!m) return;
        if (!isMobileViewport()) {
            closeMobileMenu(m);
        }
        // #region agent log
        console.log('[mobile-menu] resize', { isMobile: isMobileViewport(), width: window.innerWidth, display: getComputedStyle(m).display, transform: getComputedStyle(m).transform, classes: m.className });
        // #endregion
    });
});

function toggleMobileMenu() {
    const menu = getMobileMenuEl();
    if (!menu) return;
    if (!isMobileViewport()) return;

    const isOpen = !menu.classList.contains('hidden') && menu.classList.contains('translate-y-0');
    // #region agent log
    console.log('[mobile-menu] toggle', { isOpen, width: window.innerWidth, display: getComputedStyle(menu).display, transform: getComputedStyle(menu).transform, classes: menu.className });
    // #endregion

    if (isOpen) {
        closeMobileMenu(menu);
    } else {
        openMobileMenu(menu);
    }
}

function toggleMenuItem(element) {
    // element is a <button> now
    const expandIcon = element.querySelector('.expand-icon');
    if (expandIcon) {
        if (expandIcon.textContent === 'expand_less') {
            expandIcon.textContent = 'expand_more';
        } else {
            expandIcon.textContent = 'expand_less';
        }
    }
}

// Close menu when clicking outside (on the menu overlay)
document.addEventListener('click', function(event) {
    const menu = getMobileMenuEl();
    if (!menu) return;
    if (menu.classList.contains('hidden')) return;
    if (!menu.contains(event.target) && !event.target.closest('button[onclick="toggleMobileMenu()"]')) {
        closeMobileMenu(menu);
    }
});

// Close menu on escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        const menu = getMobileMenuEl();
        if (menu && !menu.classList.contains('hidden')) {
            closeMobileMenu(menu);
        }
    }
});



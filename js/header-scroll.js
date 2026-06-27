(function () {
    var nav = document.getElementById('siteNav');
    if (!nav || !nav.classList.contains('site-nav-transparent')) {
        return;
    }

    var logo = nav.querySelector('.site-nav-logo');
    var links = nav.querySelectorAll('.nav-link');
    var darkLogoSrc = logo ? logo.getAttribute('data-logo-dark') : null;
    var lightLogoSrc = logo ? logo.getAttribute('data-logo-light') : null;
    var desktopQuery = window.matchMedia('(min-width: 1024px)');

    function isDesktop() {
        return desktopQuery.matches;
    }

    function setLinkColors(scrolled) {
        if (!isDesktop()) {
            return;
        }
        links.forEach(function (link) {
            if (scrolled) {
                link.classList.remove('text-white', 'hover:text-yellow-200');
                link.classList.add('text-gray-700', 'hover:text-yellow-500');
            } else {
                link.classList.remove('text-gray-700', 'hover:text-yellow-500', 'text-yellow-500');
                link.classList.add('text-white', 'hover:text-yellow-200');
            }
        });
    }

    function onScroll() {
        if (!isDesktop()) {
            nav.classList.remove('nav-scrolled');
            links.forEach(function (link) {
                link.classList.remove('text-gray-700', 'hover:text-yellow-500', 'text-yellow-500');
                link.classList.add('text-white', 'hover:text-yellow-200');
            });
            if (logo && lightLogoSrc) {
                logo.src = lightLogoSrc;
            }
            return;
        }

        var scrolled = window.scrollY > 20;

        if (scrolled) {
            nav.classList.add('nav-scrolled');
            setLinkColors(true);
            if (logo && lightLogoSrc) {
                logo.src = lightLogoSrc;
            }
        } else {
            nav.classList.remove('nav-scrolled');
            setLinkColors(false);
            if (logo && darkLogoSrc) {
                logo.src = darkLogoSrc;
            }
        }
    }

    window.addEventListener('scroll', onScroll, { passive: true });
    desktopQuery.addEventListener('change', onScroll);
    onScroll();
})();

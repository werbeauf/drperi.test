(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        var header    = document.getElementById('wa-header');
        var hamburger = document.querySelector('.wa-header-hamburger');
        var dropdown  = document.querySelector('.wa-mobile-dropdown');

        if (!header) return;

        // 1. STICKY HEADER
        // Toggelt nur die .sticky-Klasse (-> position:fixed via CSS) und
        // den Body-Spacer. Die CSS-Variable --wa-header-h wird zentral
        // von assets/js/sticky-offset.js gepflegt (funktioniert auch
        // mit Divi-TB-Header).
        var triggerPoint = header.offsetTop + header.offsetHeight;

        function activateSticky() {
            if (header.classList.contains('sticky')) return;
            header.classList.add('sticky');
            document.body.classList.add('wa-sticky-on');
        }
        function deactivateSticky() {
            if (!header.classList.contains('sticky')) return;
            header.classList.remove('sticky');
            document.body.classList.remove('wa-sticky-on');
        }

        function updateMetrics() {
            if (!header.classList.contains('sticky')) {
                triggerPoint = header.offsetTop + header.offsetHeight;
            }
        }

        window.addEventListener('scroll', function() {
            if (window.pageYOffset > triggerPoint) {
                activateSticky();
            } else {
                deactivateSticky();
            }
        }, { passive: true });

        window.addEventListener('resize', updateMetrics);
        window.addEventListener('load', updateMetrics);

        function updateBodyScrollLock() {
            var menuOpen = dropdown && dropdown.classList.contains('active');
            document.body.style.overflow = menuOpen ? 'hidden' : '';
        }

        // 2. MOBILE DROPDOWN TOGGLE
        if (hamburger && dropdown) {
            hamburger.addEventListener('click', function() {
                var isOpen = dropdown.classList.contains('active');

                if (isOpen) {
                    closeDropdown();
                } else {
                    openDropdown();
                }
            });

            // Close on link click inside dropdown
            dropdown.querySelectorAll('a').forEach(function(link) {
                link.addEventListener('click', function() {
                    closeDropdown();
                });
            });

            // Close when clicking outside
            document.addEventListener('click', function(e) {
                if (
                    dropdown &&
                    hamburger &&
                    dropdown.classList.contains('active') &&
                    !dropdown.contains(e.target) &&
                    !hamburger.contains(e.target)
                ) {
                    closeDropdown();
                }
            });
        }

        document.addEventListener('keydown', function(e) {
            if (e.key !== 'Escape') return;
            if (dropdown && dropdown.classList.contains('active')) {
                closeDropdown();
            }
        });

        function openDropdown() {
            if (!dropdown || !hamburger) return;
            updateDropdownPosition();
            dropdown.classList.add('active');
            dropdown.setAttribute('aria-hidden', 'false');
            hamburger.classList.add('active');
            hamburger.setAttribute('aria-expanded', 'true');
            updateBodyScrollLock();
        }

        function closeDropdown() {
            if (!dropdown || !hamburger) return;
            dropdown.classList.remove('active');
            dropdown.setAttribute('aria-hidden', 'true');
            hamburger.classList.remove('active');
            hamburger.setAttribute('aria-expanded', 'false');
            updateBodyScrollLock();
        }

        // 3. RECALCULATE DROPDOWN POSITION ON STICKY CHANGE
        function updateDropdownPosition() {
            if (!dropdown) return;
            var headerRect = header.getBoundingClientRect();
            var offset = headerRect.bottom;
            dropdown.style.paddingTop = offset + 10 + 'px';
        }

        if (dropdown) {
            window.addEventListener('scroll', updateDropdownPosition, { passive: true });
            window.addEventListener('resize', function() {
                updateDropdownPosition();
                if (window.innerWidth > 980 && dropdown && dropdown.classList.contains('active')) {
                    closeDropdown();
                }
            });
        }
    });
})();

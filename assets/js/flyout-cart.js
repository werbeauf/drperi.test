/* ============================================================
   Dr. Peri — Flyout-Warenkorb (Drawer) Verhalten
   - Klick auf Cart-Link/-Icon -> Drawer oeffnen statt Navigation
   - ESC, Overlay-Klick, Close-Button -> Schliessen
   - Auto-Open nach AJAX add_to_cart
   - Body-Scroll-Lock + Focus-Trap
============================================================ */
(function () {
    'use strict';

    var DRAWER_ID = 'waCartDrawer';
    var OPEN_CLASS = 'is-open';
    var LOCK_CLASS = 'wa-cart-drawer-locked';
    var lastFocus = null;

    function getDrawer() {
        return document.getElementById(DRAWER_ID);
    }

    function getCartUrl(drawer) {
        if (!drawer) return '';
        try {
            return new URL(drawer.getAttribute('data-cart-url') || '', window.location.origin).pathname;
        } catch (e) {
            return drawer.getAttribute('data-cart-url') || '';
        }
    }

    /**
     * Trifft auf alles, was eindeutig auf den Cart zeigt:
     *  - href = data-cart-url (Pathname-Vergleich)
     *  - href endet auf /cart/ oder /warenkorb/
     *  - Element traegt [data-wa-cart-trigger] oder eine bekannte Header-Cart-Klasse
     */
    function isCartTrigger(el, drawer) {
        if (!el || el.nodeType !== 1) return false;

        if (el.closest('[data-wa-cart-trigger]')) return true;

        var anchor = el.closest('a[href]');
        if (!anchor) return false;

        // bekannte Header-Cart-Anchors
        if (anchor.matches('.wa-header-cart, .wa-mobile-cart, .cart-contents, .wp-block-woocommerce-mini-cart')) {
            return true;
        }

        var cartPath = getCartUrl(drawer);
        if (!cartPath) return false;

        var hrefAttr = anchor.getAttribute('href') || '';
        if (!hrefAttr || hrefAttr.charAt(0) === '#') return false;

        var anchorPath;
        try {
            anchorPath = new URL(anchor.href, window.location.origin).pathname;
        } catch (e) {
            anchorPath = '';
        }
        if (!anchorPath) return false;

        // exakter Pfad-Match (Trailing-Slash-tolerant)
        var a = anchorPath.replace(/\/+$/, '');
        var b = cartPath.replace(/\/+$/, '');
        if (a === b) return true;

        // generische /cart/ oder /warenkorb/ Slugs
        return /\/(cart|warenkorb)\/?$/i.test(anchorPath);
    }

    function lockBody(lock) {
        var html = document.documentElement;
        var body = document.body;
        if (lock) {
            html.classList.add(LOCK_CLASS);
            body.classList.add(LOCK_CLASS);
        } else {
            html.classList.remove(LOCK_CLASS);
            body.classList.remove(LOCK_CLASS);
        }
    }

    function focusableInside(root) {
        if (!root) return [];
        var sel = 'a[href], area[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';
        return Array.prototype.filter.call(
            root.querySelectorAll(sel),
            function (n) { return n.offsetWidth > 0 || n.offsetHeight > 0 || n === document.activeElement; }
        );
    }

    function trapFocus(e) {
        var drawer = getDrawer();
        if (!drawer || !drawer.classList.contains(OPEN_CLASS)) return;
        if (e.key !== 'Tab') return;

        var nodes = focusableInside(drawer.querySelector('.wa-cart-drawer__panel'));
        if (!nodes.length) {
            e.preventDefault();
            return;
        }
        var first = nodes[0];
        var last = nodes[nodes.length - 1];

        if (e.shiftKey && document.activeElement === first) {
            e.preventDefault();
            last.focus();
        } else if (!e.shiftKey && document.activeElement === last) {
            e.preventDefault();
            first.focus();
        }
    }

    function openDrawer() {
        var drawer = getDrawer();
        if (!drawer || drawer.classList.contains(OPEN_CLASS)) return;

        lastFocus = document.activeElement;

        drawer.classList.add(OPEN_CLASS);
        drawer.setAttribute('aria-hidden', 'false');
        lockBody(true);

        // Initialer Fokus auf Close-Button (vermeidet sofortigen Outline auf erstem Link)
        window.requestAnimationFrame(function () {
            var closeBtn = drawer.querySelector('.wa-cart-drawer__close');
            if (closeBtn) closeBtn.focus({ preventScroll: true });
        });

        // WC bittet bei jedem Open ein frisches Mini-Cart-Fragment an
        if (window.jQuery && window.jQuery.event) {
            window.jQuery(document.body).trigger('wc_fragment_refresh');
        }
    }

    function closeDrawer() {
        var drawer = getDrawer();
        if (!drawer || !drawer.classList.contains(OPEN_CLASS)) return;

        drawer.classList.remove(OPEN_CLASS);
        drawer.setAttribute('aria-hidden', 'true');
        lockBody(false);

        if (lastFocus && typeof lastFocus.focus === 'function') {
            try { lastFocus.focus({ preventScroll: true }); } catch (e) { /* noop */ }
        }
        lastFocus = null;
    }

    // ---- Event-Bindings ----
    document.addEventListener('click', function (e) {
        var drawer = getDrawer();
        if (!drawer) return;

        // Close-Trigger im Drawer
        var closeEl = e.target.closest('[data-wa-cart-close]');
        if (closeEl && drawer.contains(closeEl)) {
            e.preventDefault();
            closeDrawer();
            return;
        }

        // Klicks innerhalb der Drawer normal navigieren lassen
        // (sonst wuerde "Warenkorb anzeigen" wieder die Drawer oeffnen).
        if (drawer.contains(e.target)) {
            return;
        }

        // Cart-Trigger im gesamten Dokument
        if (isCartTrigger(e.target, drawer)) {
            // Ctrl/Cmd-Click oder Mittelklick: native Navigation erlauben
            if (e.metaKey || e.ctrlKey || e.shiftKey || e.button === 1) return;
            e.preventDefault();
            openDrawer();
        }
    }, true);

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' || e.key === 'Esc') {
            closeDrawer();
            return;
        }
        trapFocus(e);
    });

    // jQuery-Hooks fuer WC-AJAX
    if (window.jQuery) {
        var $ = window.jQuery;

        // Auto-Open nach erfolgreichem add_to_cart (Single-Product-AJAX,
        // .ajax_add_to_cart Loop-Buttons, Block-basiertes WC).
        $(document.body).on('added_to_cart', function () {
            openDrawer();
        });

        // Wenn Fragmente refreshed werden waehrend der Drawer offen ist,
        // sicherstellen dass das Mini-Cart-DOM noch ein scrollbares Body hat.
        $(document.body).on('wc_fragments_refreshed wc_fragments_loaded', function () {
            // no-op: WC ersetzt .widget_shopping_cart_content automatisch.
        });

        // Mini-Cart Remove-Button (a.remove_from_cart_button) aktualisiert
        // die Fragmente von selbst — keine zusaetzliche Logik noetig.
    }
})();

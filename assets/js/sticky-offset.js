/* ============================================================
   DATEI: assets/js/sticky-offset.js
   ZWECK: Setzt --wa-header-h auf die tatsaechlich SICHTBARE
          Header-Hoehe -- egal ob Fallback-Header (#wa-header)
          oder Divi-Theme-Builder-Header (header.et-l--header)
          mit eigenem Sticky.

   Konsumenten:
     - .wa-detail-block__header  (sticky Pill-Bar, Single-Product)
     - .wa-product-side-cart     (sticky Sidebar-Cart)
     - body.wa-sticky-on padding-top (Fallback-Header-Spacer)

   Logik:
     getBoundingClientRect().bottom des aktiven Site-Headers gibt
     uns in Viewport-Koordinaten genau die Y-Linie, ueber der
     der Header liegt. Sub-Sticky-Elemente sollen exakt darunter
     pinnen -> das ist unser --wa-header-h.

     - Header in normalem Flow am Top  -> bottom = ~Header-Hoehe
     - Header sticky/fixed bei top:0   -> bottom = Sticky-Hoehe
     - Header out-of-view (gescrollt)  -> bottom < 0 -> clamped auf 0

   So funktioniert das robust mit Divi's Sticky-Mechanismus,
   unserem eigenen JS-Sticky, fehlendem Sticky, Resize-Breakpoints,
   admin-bar etc. -- alles wird durch den BoundingClientRect
   automatisch korrekt gemessen.
============================================================ */

(function () {
    'use strict';

    function getActiveHeader() {
        // 1. Divi Theme Builder Header (haeufigster Fall auf Dr. Peri).
        var diviHeader = document.querySelector('header.et-l--header');
        if (diviHeader) return diviHeader;

        // 2. Fallback: unser eigener wa-header (wird nur bei fehlendem
        //    TB-Header gerendert).
        return document.getElementById('wa-header');
    }

    var header  = null;
    var rafId   = null;
    var lastVal = -1;

    function update() {
        rafId = null;

        if (!header) {
            header = getActiveHeader();
            if (!header) return;
        }

        var bottom = Math.round(header.getBoundingClientRect().bottom);
        var px     = Math.max(0, bottom);

        if (px === lastVal) return;
        lastVal = px;

        document.documentElement.style.setProperty('--wa-header-h', px + 'px');
    }

    function schedule() {
        if (rafId !== null) return;
        rafId = requestAnimationFrame(update);
    }

    // Header kann via Divi-TB / Sticky-Modul auch beim Scrollen erst
    // umgehaengt / class-getoggled werden -- deshalb auf scroll listenen,
    // nicht nur auf load. requestAnimationFrame entkoppelt von scroll-Frequenz.
    window.addEventListener('scroll', schedule, { passive: true });
    window.addEventListener('resize', schedule);
    window.addEventListener('load', function () {
        // Header-Referenz nach Load nochmal frisch holen -- TB-Header kann
        // erst nach DOMContentLoaded eingehaengt sein.
        header = getActiveHeader();
        update();
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', update);
    } else {
        update();
    }
})();

/* ============================================================
   DATEI: assets/js/detail-block.js
   ZWECK: Tab-Switching + Smooth-Scroll fuer .wa-detail-block

   Verhalten:
     - Klick auf Pill -> Panel-Wechsel + smooth-scroll zur
       Block-Top (respektiert sticky offset des Headers, mit
       iterativer Korrektur fuer dynamische Header-Hoehen)
     - URL-Hash deep-link beim Page-Load (#wa-panel-additional)
     - In-Page-Links zu #wa-panel-* (z.B. der "Produktbeschreibung
       lesen"-Link unter der Short Description) aktivieren das
       Panel + smooth-scrollen zum Block. URL bleibt unveraendert,
       damit der Hash nicht in der Adressleiste auftaucht.
     - Pfeiltasten-Navigation zwischen Pills (WAI-ARIA tab pattern)
     - prefers-reduced-motion deaktiviert smooth scroll
============================================================ */

(function () {
    'use strict';

    // Visueller Puffer zwischen Sticky-Header-Unterkante und Scroll-Ziel.
    // Verhindert, dass das Heading direkt am Header klebt.
    var SCROLL_BUFFER = 24;

    /**
     * Misst die ECHTE Sichthoehe des Sticky-Site-Headers JETZT.
     *
     * Direktmessung am DOM ist robuster als die CSS-Variable
     * --wa-header-h: beim Klick aus dem Top-State ist die Variable
     * noch der Flow-Wert, nicht der finale Sticky-Wert (Divi-TB
     * kann beim Scrollen seine Hoehe aendern).
     *
     * Header-Suche identisch zu sticky-offset.js:
     *   1. Divi Theme Builder Header  (.et-l--header)
     *   2. Eigener Fallback-Header    (#wa-header)
     *   3. CSS-Variable --wa-header-h
     *   4. Hardcoded 76 (worst case)
     */
    function getStickyOffset() {
        var divi = document.querySelector('header.et-l--header');
        if (divi) {
            return Math.max(0, divi.getBoundingClientRect().bottom);
        }
        var own = document.getElementById('wa-header');
        if (own) {
            return Math.max(0, own.getBoundingClientRect().bottom);
        }
        var raw = getComputedStyle(document.documentElement)
            .getPropertyValue('--wa-header-h').trim();
        var px = parseFloat(raw);
        return (!isNaN(px) && px > 0) ? px : 76;
    }

    /**
     * Smooth-Scroll zu einem Element, das unter dem Sticky-Site-Header
     * ankern soll.
     *
     * Robustes Vorgehen gegen "Site-Header aendert seine Hoehe beim
     * Scrollen" (Divi-TB Sticky-Modul, shrink-on-scroll, etc.):
     *
     *  1. Erstes scrollTo mit aktuell gemessener Header-Hoehe.
     *  2. Nach jedem scrollend (max. 3x) wird neu gemessen --
     *     stimmt die Position nicht (Section.top sitzt zu nah am
     *     Header oder hat zu viel Abstand zum Wunschpunkt), wird
     *     erneut korrigiert. Konvergiert in der Regel nach 1-2
     *     Iterationen, da der Header nach dem ersten Scroll bereits
     *     im Sticky-Endzustand ist.
     *  3. Fallback fuer Browser ohne scrollend: Timeout.
     */
    function scrollToElement(el) {
        var prefersReduced = window.matchMedia
            && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        function desiredViewportTop() {
            return getStickyOffset() + SCROLL_BUFFER;
        }

        function targetScrollY() {
            var rect = el.getBoundingClientRect();
            return Math.max(0, rect.top + window.scrollY - desiredViewportTop());
        }

        window.scrollTo({
            top: targetScrollY(),
            behavior: prefersReduced ? 'auto' : 'smooth'
        });

        if (prefersReduced) {
            return;
        }

        var passes = 0;
        var MAX_PASSES = 3;
        var TOLERANCE  = 2;

        function tick() {
            if (passes >= MAX_PASSES) {
                return;
            }
            passes++;

            var rect = el.getBoundingClientRect();
            var diff = rect.top - desiredViewportTop();
            if (Math.abs(diff) <= TOLERANCE) {
                return;
            }

            window.scrollTo({ top: targetScrollY(), behavior: 'smooth' });
            schedule();
        }

        function schedule() {
            if ('onscrollend' in window) {
                var done = false;
                var onEnd = function () {
                    if (done) return;
                    done = true;
                    window.removeEventListener('scrollend', onEnd);
                    tick();
                };
                window.addEventListener('scrollend', onEnd);
                // Safety: scrollend feuert nicht, wenn wir bereits
                // am Ziel sind.
                setTimeout(onEnd, 900);
            } else {
                setTimeout(tick, 600);
            }
        }

        schedule();
    }

    function initBlock(block) {
        var nav    = block.querySelector('.wa-detail-block__nav');
        var pills  = nav ? nav.querySelectorAll('.wa-detail-block__pill') : [];
        var panels = block.querySelectorAll('.wa-detail-block__panel');
        if (!pills.length || !panels.length) {
            return;
        }

        function showPanel(targetId, opts) {
            opts = opts || {};

            pills.forEach(function (p) {
                var active = p.dataset.target === targetId;
                p.classList.toggle('is-active', active);
                p.setAttribute('aria-selected', active ? 'true' : 'false');
                p.setAttribute('tabindex', active ? '0' : '-1');
            });

            panels.forEach(function (panel) {
                if (panel.id === targetId) {
                    panel.removeAttribute('hidden');
                } else {
                    panel.setAttribute('hidden', '');
                }
            });

            if (opts.scroll) {
                scrollToElement(block);
            }
        }

        nav.addEventListener('click', function (e) {
            var pill = e.target.closest('.wa-detail-block__pill');
            if (!pill || !nav.contains(pill)) {
                return;
            }
            e.preventDefault();
            var target = pill.dataset.target;
            if (!target) {
                return;
            }
            showPanel(target, { scroll: true });

            // URL-Hash setzen ohne Page-Jump -- nuetzlich bei
            // 2-Tab-UI, weil der User zwischen "Produktdetails" und
            // "Zusaetzliche Informationen" wechseln kann und der
            // aktive Tab im URL-Hash hinterlegt sein soll (Deep-Link).
            if (history.replaceState) {
                try {
                    history.replaceState(null, '', '#' + target);
                } catch (err) {
                    // Manche Browser blocken replaceState in seltenen
                    // Edge-Cases (z.B. file:// origin); ignorieren.
                }
            }
        });

        // Pfeiltasten-Navigation (WAI-ARIA tab pattern).
        nav.addEventListener('keydown', function (e) {
            if (e.key !== 'ArrowLeft' && e.key !== 'ArrowRight'
                && e.key !== 'Home'    && e.key !== 'End') {
                return;
            }
            var arr = Array.prototype.slice.call(pills);
            var idx = arr.indexOf(document.activeElement);
            if (idx === -1) {
                return;
            }
            e.preventDefault();

            var next;
            if (e.key === 'ArrowRight') {
                next = (idx + 1) % arr.length;
            } else if (e.key === 'ArrowLeft') {
                next = (idx - 1 + arr.length) % arr.length;
            } else if (e.key === 'Home') {
                next = 0;
            } else {
                next = arr.length - 1;
            }
            arr[next].focus();
            showPanel(arr[next].dataset.target, { scroll: false });
        });

        // Deep-Link via URL-Hash beim Page-Load -- weiterhin
        // unterstuetzt, damit geteilte URLs (z.B. via Tab-Klick)
        // beim Reload den richtigen Tab oeffnen.
        if (location.hash) {
            var hashId = location.hash.replace(/^#/, '');
            if (hashId) {
                var found = block.querySelector('[id="' + hashId.replace(/"/g, '\\"') + '"]');
                if (found && found.classList.contains('wa-detail-block__panel')) {
                    // Erst Panel switchen, dann scroll im naechsten
                    // Frame -- damit das Layout schon stabil ist.
                    showPanel(hashId, { scroll: false });
                    requestAnimationFrame(function () {
                        showPanel(hashId, { scroll: true });
                    });
                }
            }
        }
    }

    /**
     * In-Page-Links zu Panels (z.B. <a href="#wa-panel-description">
     * unter der Short Description).
     *
     * MUSS in capture-Phase laufen: Divi (oder ein anderes Frontend-
     * Skript) ruft bei Anchor-Klicks stopPropagation() auf -- ohne
     * capture wuerde unser delegierter Handler nie erreicht.
     *
     * stopImmediatePropagation() verhindert, dass nach uns noch ein
     * weiterer Smooth-Scroll-Handler (z.B. Divi) den Klick mit
     * falschem Offset zusaetzlich verarbeitet.
     */
    function handlePanelLinkClick(e) {
        if (e.defaultPrevented || e.button !== 0
            || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) {
            return;
        }
        var link = e.target.closest('a[href*="#wa-panel-"]');
        if (!link) {
            return;
        }
        var href = link.getAttribute('href') || '';
        var hashIndex = href.indexOf('#');
        if (hashIndex === -1) {
            return;
        }
        var pathPart = href.slice(0, hashIndex);
        if (pathPart && pathPart !== location.pathname
            && pathPart !== (location.pathname + location.search)) {
            return;
        }
        var targetId = href.slice(hashIndex + 1);
        var targetEl = document.getElementById(targetId);
        if (!targetEl) {
            return;
        }

        // Fall A: Element ist ein Tab-Panel im Detail-Block.
        var block = targetEl.closest('.wa-detail-block');
        if (block && targetEl.classList.contains('wa-detail-block__panel')) {
            e.preventDefault();
            e.stopImmediatePropagation();
            // Pill-Klick simulieren -> nutzt vorhandene showPanel-Logik
            // (inkl. URL-Hash setzen, weil Tab-Wahl deep-linkbar bleibt).
            var pill = block.querySelector('.wa-detail-block__pill[data-target="'
                + targetId.replace(/"/g, '\\"') + '"]');
            if (pill) {
                pill.click();
            }
            return;
        }

        // Fall B: Single-Panel-Modus (.wa-single-panel mit derselben ID).
        // KEIN history.replaceState() -- wir verstecken den Hash, weil
        // im Single-Panel-Modus nur EIN Panel existiert und der Hash
        // optisch nur unnoetigen Adressleisten-Muell waere.
        if (targetEl.classList.contains('wa-single-panel')) {
            e.preventDefault();
            e.stopImmediatePropagation();
            scrollToElement(targetEl);
        }
    }

    function init() {
        document.querySelectorAll('.wa-detail-block').forEach(initBlock);
        document.addEventListener('click', handlePanelLinkClick, true);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();

/* ============================================================
   DATEI: assets/js/shop-filter.js
   ZWECK: Client-side Filter fuer ul.products via product_cat-{slug}
          Klassen, die WooCommerce automatisch an jedes <li> haengt.
============================================================ */

(function () {
    'use strict';

    function findList(filter) {
        // Explizit per data-target?
        var sel = filter.getAttribute('data-target');
        if (sel) {
            var node = document.querySelector(sel);
            if (node) {
                return node.matches && node.matches('ul.products')
                    ? node
                    : node.querySelector('ul.products');
            }
        }
        // Fallback: naechster Sibling, ansonsten in der Section
        var sibling = filter.nextElementSibling;
        while (sibling) {
            if (sibling.matches && sibling.matches('ul.products')) {
                return sibling;
            }
            var inside = sibling.querySelector && sibling.querySelector('ul.products');
            if (inside) {
                return inside;
            }
            sibling = sibling.nextElementSibling;
        }
        var section = filter.closest('section') || filter.parentElement;
        return section ? section.querySelector('ul.products') : null;
    }

    function ensureEmptyState(list) {
        var empty = list.querySelector(':scope > .wa-shop-filter__empty');
        if (empty) return empty;
        empty = document.createElement('li');
        empty.className = 'wa-shop-filter__empty';
        empty.setAttribute('hidden', '');
        empty.setAttribute('role', 'status');
        empty.textContent = 'Keine Produkte in dieser Kategorie.';
        list.appendChild(empty);
        return empty;
    }

    function applyFilter(list, items, empty, cat) {
        var visible = 0;
        items.forEach(function (item) {
            var match = !cat || item.classList.contains('product_cat-' + cat);
            item.classList.toggle('wa-is-hidden', !match);
            if (match) visible++;
        });
        if (empty) {
            empty.toggleAttribute('hidden', visible > 0);
        }
    }

    function bindFilter(filter) {
        var list = findList(filter);
        if (!list) return;

        var items = Array.prototype.slice.call(list.querySelectorAll('li.product'));
        var empty = ensureEmptyState(list);

        filter.addEventListener('click', function (e) {
            var pill = e.target.closest('.wa-shop-filter__pill');
            if (!pill || pill.classList.contains('is-active')) return;
            e.preventDefault();

            var cat = pill.getAttribute('data-cat') || '';

            filter.querySelectorAll('.wa-shop-filter__pill').forEach(function (p) {
                var active = p === pill;
                p.classList.toggle('is-active', active);
                p.setAttribute('aria-selected', active ? 'true' : 'false');
            });

            applyFilter(list, items, empty, cat);
        });

        // Tastatur-Navigation (Pfeiltasten zwischen Pills)
        filter.addEventListener('keydown', function (e) {
            if (e.key !== 'ArrowLeft' && e.key !== 'ArrowRight') return;
            var pills = Array.prototype.slice.call(filter.querySelectorAll('.wa-shop-filter__pill'));
            var idx = pills.indexOf(document.activeElement);
            if (idx === -1) return;
            e.preventDefault();
            var next = e.key === 'ArrowRight'
                ? (idx + 1) % pills.length
                : (idx - 1 + pills.length) % pills.length;
            pills[next].focus();
        });
    }

    function init() {
        document.querySelectorAll('.wa-shop-filter').forEach(bindFilter);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();

/* Single Product enhancements - Dr. Peri */
(function () {
    'use strict';

    function ready(fn) {
        if (document.readyState !== 'loading') fn();
        else document.addEventListener('DOMContentLoaded', fn);
    }

    ready(function () {
        // Cursor pointer auf Tab-Links sicherstellen (Divi/WC override)
        document.querySelectorAll('.woocommerce-tabs ul.tabs li a').forEach(function (a) {
            a.style.cursor = 'pointer';
        });

        // Quantity +/- Buttons rendern
        document.querySelectorAll('.summary form.cart .quantity').forEach(function (wrap) {
            if (wrap.dataset.waEnhanced) return;
            wrap.dataset.waEnhanced = '1';

            var input = wrap.querySelector('input.qty');
            if (!input) return;

            var minus = document.createElement('button');
            minus.type = 'button';
            minus.className = 'wa-qty-btn wa-qty-btn--minus';
            minus.setAttribute('aria-label', 'Menge verringern');
            minus.textContent = '−';

            var plus = document.createElement('button');
            plus.type = 'button';
            plus.className = 'wa-qty-btn wa-qty-btn--plus';
            plus.setAttribute('aria-label', 'Menge erhöhen');
            plus.textContent = '+';

            wrap.classList.add('wa-qty-wrap');
            wrap.insertBefore(minus, input);
            wrap.appendChild(plus);

            function step(delta) {
                var min = parseFloat(input.getAttribute('min')) || 1;
                var max = parseFloat(input.getAttribute('max')) || Infinity;
                var stepVal = parseFloat(input.getAttribute('step')) || 1;
                var current = parseFloat(input.value) || 0;
                var next = current + delta * stepVal;
                if (next < min) next = min;
                if (next > max) next = max;
                input.value = next;
                input.dispatchEvent(new Event('change', { bubbles: true }));
            }
            minus.addEventListener('click', function () { step(-1); });
            plus.addEventListener('click', function () { step(1); });
        });
    });
})();

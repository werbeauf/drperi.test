/* ============================================================
   DATEI: assets/js/newsletter.js
   ZWECK: Newsletter-Widget Submit-Handler + WC-Blocks-Checkout
          DOM-Injector mit MutationObserver.

   Globale Config: window.WA_NEWSLETTER = { restUrl, i18n }
============================================================ */
( function () {
    'use strict';

    if ( typeof window.WA_NEWSLETTER === 'undefined' ) return;
    var CFG = window.WA_NEWSLETTER;

    /* --------------------------------------------------------
       Block A -- Widget-Submit-Handler (event delegation).
    -------------------------------------------------------- */
    document.addEventListener( 'submit', function ( e ) {
        var form = e.target;
        if ( ! form || ! form.matches || ! form.matches( '[data-wa-newsletter]' ) ) return;
        e.preventDefault();
        handleSubmit( form );
    } );

    function handleSubmit( form ) {
        if ( form.dataset.waSubmitting === '1' ) return;
        form.dataset.waSubmitting = '1';
        form.dataset.waState = '';

        var button = form.querySelector( '.wa-newsletter__submit' );
        var msg    = form.querySelector( '.wa-newsletter__msg' );
        var origBtnText = button ? button.textContent : '';

        if ( button ) {
            button.disabled = true;
            button.textContent = ( CFG.i18n && CFG.i18n.submitting ) || 'Sending...';
        }
        if ( msg ) { msg.hidden = true; msg.textContent = ''; }

        var formData = new FormData( form );

        var headers = { 'Accept': 'application/json' };
        // X-WP-Nonce ist Pflicht fuer eingeloggte User -- sonst wirft die
        // WP-REST-Cookie-Auth "Die Cookie-Pruefung ist fehlgeschlagen",
        // bevor unsere eigene Permission greifen kann.
        if ( CFG.restNonce ) {
            headers[ 'X-WP-Nonce' ] = CFG.restNonce;
        }

        fetch( CFG.restUrl, {
            method:  'POST',
            headers: headers,
            body:    formData,
            credentials: 'same-origin'
        } )
        .then( function ( res ) {
            return res.json().then( function ( json ) {
                return { ok: res.ok, status: res.status, json: json };
            } ).catch( function () {
                return { ok: res.ok, status: res.status, json: {} };
            } );
        } )
        .then( function ( r ) {
            if ( r.ok ) {
                form.dataset.waState = 'success';
                if ( msg ) {
                    msg.hidden = false;
                    msg.textContent = ( r.json && r.json.message ) || 'Vielen Dank!';
                }
            } else {
                form.dataset.waState = 'error';
                var errMsg = ( r.json && r.json.message )
                    || ( CFG.i18n && CFG.i18n.generic_error )
                    || 'Error';
                if ( msg ) { msg.hidden = false; msg.textContent = errMsg; }
            }
        } )
        .catch( function () {
            form.dataset.waState = 'error';
            if ( msg ) {
                msg.hidden = false;
                msg.textContent = ( CFG.i18n && CFG.i18n.network_error ) || 'Network error.';
            }
        } )
        .then( function () {
            form.dataset.waSubmitting = '';
            if ( button ) {
                button.disabled = false;
                button.textContent = origBtnText;
            }
        } );
    }

    /* --------------------------------------------------------
       Block B -- WC-Blocks Checkout DOM-Injector.
       Findet `.wp-block-woocommerce-checkout-billing-address-block`
       sobald er existiert, fuegt eine eigenstaendige Optin-Card
       direkt davor in den Sidebar-Layout-Flow ein, BEVOR der
       Payment-Block. Persistiert den Status via Store API
       extension `wa-newsletter`.
    -------------------------------------------------------- */
    if ( ! document.body.classList.contains( 'woocommerce-checkout' ) ) return;

    var injected = false;
    var observer = new MutationObserver( function () {
        if ( injected ) { observer.disconnect(); return; }
        var billing = document.querySelector( '.wp-block-woocommerce-checkout-billing-address-block' );
        var payment = document.querySelector( '.wp-block-woocommerce-checkout-payment-block' );
        if ( ! billing || ! payment ) return;
        injectOptin( billing, payment );
        injected = true;
        observer.disconnect();
    } );
    observer.observe( document.body, { childList: true, subtree: true } );

    function injectOptin( billing, payment ) {
        if ( document.querySelector( '.wa-newsletter-optin' ) ) return;

        var box = document.createElement( 'div' );
        box.className = 'wa-newsletter-optin';

        var label = ( CFG.i18n && CFG.i18n.optin_label ) || 'Ich moechte den Newsletter erhalten.';
        box.innerHTML = ''
            + '<label class="wa-newsletter-optin__label">'
            +   '<input type="checkbox" class="wa-newsletter-optin__check">'
            +   '<span>' + escHtml( label ) + '</span>'
            + '</label>';

        // Insert between billing and payment in the same parent if possible.
        var parent = billing.parentNode;
        if ( parent && parent === payment.parentNode ) {
            parent.insertBefore( box, payment );
        } else {
            billing.insertAdjacentElement( 'afterend', box );
        }

        var input = box.querySelector( '.wa-newsletter-optin__check' );
        input.addEventListener( 'change', function () {
            applyConsent( !! input.checked );
        } );
    }

    function applyConsent( checked ) {
        if ( typeof window.wp === 'undefined' || ! window.wp.data || ! window.wp.data.dispatch ) return;
        try {
            var dispatch = window.wp.data.dispatch( 'wc/store/cart' );
            if ( dispatch && typeof dispatch.applyExtensionCartUpdate === 'function' ) {
                dispatch.applyExtensionCartUpdate( {
                    namespace: 'wa-newsletter',
                    data:      { consent: checked }
                } );
            }
        } catch ( e ) {
            // Silent: WC Blocks store may not be ready yet, the value gets
            // applied on next interaction.
        }
    }

    function escHtml( s ) {
        return String( s ).replace( /[&<>"']/g, function ( c ) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ c ];
        } );
    }
} )();

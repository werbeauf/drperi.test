<?php
/**
 * Flyout Warenkorb (Drawer) fuer Dr. Peri.
 *
 * Rendert ein right-side Slide-In-Panel mit der WooCommerce Mini-Cart.
 * - Trigger:        beliebiger Link auf wc_get_cart_url() ODER [data-wa-cart-trigger]
 * - Auto-Open:      nach erfolgreichem AJAX add_to_cart
 * - Live-Update:    via WooCommerce Fragment-System (.widget_shopping_cart_content)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Pruefen, ob die Drawer auf der aktuellen Anfrage aktiv sein soll.
 *
 * Auf Cart- und Checkout-Seiten lassen wir die Drawer aus, dort gibt es bereits
 * volle Cart-/Checkout-UI und ein zusaetzliches Overlay waere stoerend.
 *
 * @return bool
 */
function wa_flyout_cart_is_active() {
    if ( ! class_exists( 'WooCommerce' ) || ! function_exists( 'WC' ) ) {
        return false;
    }
    if ( is_admin() ) {
        return false;
    }
    if ( function_exists( 'is_cart' ) && is_cart() ) {
        return false;
    }
    if ( function_exists( 'is_checkout' ) && is_checkout() ) {
        return false;
    }
    return apply_filters( 'wa_flyout_cart_active', true );
}

/**
 * Drawer-Markup im Footer ausgeben.
 */
add_action( 'wp_footer', 'wa_render_flyout_cart', 25 );
function wa_render_flyout_cart() {
    if ( ! wa_flyout_cart_is_active() ) {
        return;
    }
    $cart_url     = function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : '#';
    $checkout_url = function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : '#';
    ?>
    <div class="wa-cart-drawer"
         id="waCartDrawer"
         data-wa-cart-root
         data-cart-url="<?php echo esc_attr( $cart_url ); ?>"
         data-checkout-url="<?php echo esc_attr( $checkout_url ); ?>"
         aria-hidden="true"
         role="dialog"
         aria-modal="true"
         aria-labelledby="waCartDrawerTitle">
        <div class="wa-cart-drawer__overlay" data-wa-cart-close tabindex="-1" aria-hidden="true"></div>
        <aside class="wa-cart-drawer__panel" role="document">
            <header class="wa-cart-drawer__head">
                <div class="wa-cart-drawer__heading">
                    <span class="wa-cart-drawer__eyebrow"><?php esc_html_e( 'Warenkorb', 'werbeauf-customs' ); ?></span>
                    <h2 class="wa-cart-drawer__title" id="waCartDrawerTitle"><?php esc_html_e( 'Ihre Auswahl', 'werbeauf-customs' ); ?></h2>
                </div>
                <button type="button"
                        class="wa-cart-drawer__close"
                        aria-label="<?php esc_attr_e( 'Warenkorb schließen', 'werbeauf-customs' ); ?>"
                        data-wa-cart-close>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                        <path d="M6 6l12 12M18 6L6 18"/>
                    </svg>
                </button>
            </header>

            <div class="wa-cart-drawer__body" data-wa-cart-body>
                <div class="widget_shopping_cart_content">
                    <?php woocommerce_mini_cart(); ?>
                </div>
            </div>
        </aside>
    </div>
    <?php
}

/**
 * Sicherstellen, dass die WC-Fragmente geladen sind, sobald der Drawer aktiv
 * ist — sonst gibt es kein automatisches Refresh nach add_to_cart.
 */
add_action( 'wp_enqueue_scripts', 'wa_flyout_cart_ensure_fragments', 30 );
function wa_flyout_cart_ensure_fragments() {
    if ( ! wa_flyout_cart_is_active() ) {
        return;
    }
    if ( wp_script_is( 'wc-cart-fragments', 'registered' ) ) {
        wp_enqueue_script( 'wc-cart-fragments' );
    }
    if ( wp_script_is( 'wc-add-to-cart', 'registered' ) ) {
        wp_enqueue_script( 'wc-add-to-cart' );
    }
}

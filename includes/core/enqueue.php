<?php
/* ============================================================
   DATEI: includes/core/enqueue.php
   ZWECK: Zentrales Asset-Enqueuing fuer das Frontend.

   Architektur:
     Layer-Konvention nach assets/css/-Subfolder:
       00-base       -> tokens.css, style.css       (immer geladen)
       10-layout     -> wc-shell.css, content-shell.css, header.css, footer.css
       20-components -> product-card, category-card, shop-filter,
                        breadcrumb, notices, page-title, flyout-cart
       30-pages      -> shop-archive, single-product, cart, checkout,
                        account, wc-blocks
       40-blocks     -> shop-layout, trust-badges, accordion
                        (werden VON Shortcodes self-enqueued, nicht hier)

   Bedingungen:
     - WC-Layer (10-layout/wc-shell + Komponenten + Tables) laedt auf
       allen WC-Pages und Content-Shell-Pages.
     - 30-pages laden konditional je nach is_*().
============================================================ */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Soll das Card-Grid (20-components/product-card + category-card) geladen werden?
 *
 * Trifft auf:
 *  - klassische WC-Archive (Shop, Produkt-Kategorie/-Tag/-Suche)
 *  - Single-Product (fuer "Aehnliche Produkte")
 *  - Singular-Seiten/Posts mit WC-Produkt-Shortcode
 *
 * @return bool
 */
function wa_should_load_products_grid() {
    if ( ! function_exists( 'is_woocommerce' ) ) {
        return false;
    }

    if ( is_shop() || is_product_taxonomy() || is_post_type_archive( 'product' ) ) {
        return true;
    }

    if ( function_exists( 'is_product' ) && is_product() ) {
        return true;
    }

    if ( ! is_singular() ) {
        return false;
    }

    $post = get_post();
    if ( ! $post || empty( $post->post_content ) ) {
        return false;
    }

    $shortcodes = array(
        'products',
        'product',
        'product_page',
        'product_category',
        'product_categories',
        'recent_products',
        'featured_products',
        'sale_products',
        'best_selling_products',
        'top_rated_products',
        'related_products',
    );
    foreach ( $shortcodes as $tag ) {
        if ( has_shortcode( $post->post_content, $tag ) ) {
            return true;
        }
    }
    return false;
}

add_action( 'wp_enqueue_scripts', 'wa_enqueue_frontend_assets' );

function wa_enqueue_frontend_assets() {

    /* ---------- 00-base: immer geladen ----------
       Hinweis: Montserrat wird vom Divi-Theme geladen — kein eigenes
       Font-Setup im Plugin (siehe docs/DESIGN.md). */

    wp_enqueue_style(
        'wa-tokens',
        WERBEAUF_PLUGIN_URL . 'assets/css/00-base/tokens.css',
        array(),
        '1.0.0'
    );

    wp_enqueue_style(
        'wa-style',
        WERBEAUF_PLUGIN_URL . 'assets/css/00-base/style.css',
        array( 'wa-tokens' ),
        '2.1.0'
    );

    /* ---------- Sticky-Offset: --wa-header-h immer korrekt ----------
       Setzt die CSS-Variable basierend auf dem tatsaechlichen
       Bottom des aktiven Site-Headers (Divi-TB oder Fallback).
       Wird IMMER geladen, weil Sub-Sticky-Elemente (Detail-Block-
       Pill-Bar, Sidebar-Cart) auf jedem Layout korrekt pinnen
       sollen -- unabhaengig davon, welcher Header aktiv ist. */
    wp_enqueue_script(
        'wa-sticky-offset',
        WERBEAUF_PLUGIN_URL . 'assets/js/sticky-offset.js',
        array(),
        '1.0.0',
        true
    );

    wp_enqueue_script(
        'wa-divi-toggles',
        WERBEAUF_PLUGIN_URL . 'assets/js/divi-toggles.js',
        array( 'jquery' ),
        '1.0.0',
        true
    );

    /* ---------- 10-layout: Custom Header / Footer (nur wenn kein TB) ---------- */

    global $wa_show_fallback_header;
    if ( ! empty( $wa_show_fallback_header ) ) {
        wp_enqueue_style(
            'wa-header',
            WERBEAUF_PLUGIN_URL . 'assets/css/10-layout/header.css',
            array( 'wa-tokens' ),
            '2.4.0'
        );
        wp_enqueue_script(
            'wa-header',
            WERBEAUF_PLUGIN_URL . 'assets/js/header.js',
            array(),
            '2.2.0',
            true
        );
    }

    global $wa_show_fallback_footer;
    if ( ! empty( $wa_show_fallback_footer ) ) {
        wp_enqueue_style(
            'wa-footer',
            WERBEAUF_PLUGIN_URL . 'assets/css/10-layout/footer.css',
            array( 'wa-tokens', 'wa-style' ),
            '1.12.0'
        );

        // Newsletter-Form ist Teil des Custom-Footers (do_shortcode in
        // templates/footer.php) -> CSS+JS vorab enqueuen, damit das
        // Stylesheet noch im <head> ausgegeben wird (wp_footer prio 5
        // ist zu spaet).
        if ( wp_style_is( 'wa-newsletter', 'registered' ) ) {
            wp_enqueue_style( 'wa-newsletter' );
        }
        if ( wp_script_is( 'wa-newsletter', 'registered' ) ) {
            wp_enqueue_script( 'wa-newsletter' );
        }
    }

    /* ---------- 10-layout + 20-components + 30-pages: WC + Content-Shell ----------
       Legacy-Shortcodes ([woocommerce_cart] / [woocommerce_checkout] /
       [woocommerce_my_account]) werden ueber wa_post_has_wc_shortcode()
       als Cart-/Checkout-/Account-Page behandelt, damit die selben
       Stylesheets wie auf den nativen Endpunkt-Pages greifen.            */

    $has_cart_sc     = function_exists( 'wa_post_has_wc_shortcode' ) && wa_post_has_wc_shortcode( 'woocommerce_cart' );
    $has_checkout_sc = function_exists( 'wa_post_has_wc_shortcode' ) && wa_post_has_wc_shortcode( 'woocommerce_checkout' );
    $has_account_sc  = function_exists( 'wa_post_has_wc_shortcode' ) && wa_post_has_wc_shortcode( 'woocommerce_my_account' );

    $is_cart_page     = ( function_exists( 'is_cart' )         && is_cart() )         || $has_cart_sc;
    $is_checkout_page = ( function_exists( 'is_checkout' )     && is_checkout() )     || $has_checkout_sc;
    $is_account_page  = ( function_exists( 'is_account_page' ) && is_account_page() ) || $has_account_sc;

    $is_wc_page = function_exists( 'is_woocommerce' ) && (
        is_woocommerce()
        || $is_cart_page
        || $is_checkout_page
        || $is_account_page
    );
    $is_shell_page = function_exists( 'wa_is_content_shell_page' ) && wa_is_content_shell_page();

    // WC Layout-Shell + Komponenten -> alle WC-Seiten
    if ( $is_wc_page ) {
        wp_enqueue_style(
            'wa-wc-shell',
            WERBEAUF_PLUGIN_URL . 'assets/css/10-layout/wc-shell.css',
            array( 'wa-tokens' ),
            '2.4.0'
        );
        wp_enqueue_style(
            'wa-breadcrumb',
            WERBEAUF_PLUGIN_URL . 'assets/css/20-components/breadcrumb.css',
            array( 'wa-wc-shell' ),
            '1.0.0'
        );
        wp_enqueue_style(
            'wa-page-title',
            WERBEAUF_PLUGIN_URL . 'assets/css/20-components/page-title.css',
            array( 'wa-wc-shell' ),
            '1.0.0'
        );
        wp_enqueue_style(
            'wa-notices',
            WERBEAUF_PLUGIN_URL . 'assets/css/20-components/notices.css',
            array( 'wa-wc-shell' ),
            '1.0.0'
        );
    }

    // Content-Shell -> AGB / Datenschutz / Impressum / ...
    if ( $is_shell_page ) {
        wp_enqueue_style(
            'wa-content-shell',
            WERBEAUF_PLUGIN_URL . 'assets/css/10-layout/content-shell.css',
            array( 'wa-tokens' ),
            '1.0.0'
        );
    }

    /* ---------- 20-components: Card-Grids ---------- */

    if ( wa_should_load_products_grid() ) {
        wp_enqueue_style(
            'wa-product-card',
            WERBEAUF_PLUGIN_URL . 'assets/css/20-components/product-card.css',
            array( 'wa-tokens' ),
            '3.3.0'
        );
        wp_enqueue_style(
            'wa-category-card',
            WERBEAUF_PLUGIN_URL . 'assets/css/20-components/category-card.css',
            array( 'wa-tokens', 'wa-product-card' ),
            '1.1.0'
        );
    }

    /* ---------- 20-components: Flyout-Cart ---------- */

    if ( function_exists( 'wa_flyout_cart_is_active' ) && wa_flyout_cart_is_active() ) {
        wp_enqueue_style(
            'wa-flyout-cart',
            WERBEAUF_PLUGIN_URL . 'assets/css/20-components/flyout-cart.css',
            array( 'wa-tokens' ),
            '1.3.5'
        );
        wp_enqueue_script(
            'wa-flyout-cart',
            WERBEAUF_PLUGIN_URL . 'assets/js/flyout-cart.js',
            array(),
            '1.0.1',
            true
        );
    }

    /* ---------- 30-pages: Page-Type-Stylesheets ---------- */

    // Shop-Archive (Result count, Pagination, Full-Width-Override)
    if ( function_exists( 'is_woocommerce' ) && (
        is_shop() || is_product_taxonomy() || is_post_type_archive( 'product' )
    ) ) {
        wp_enqueue_style(
            'wa-shop-archive',
            WERBEAUF_PLUGIN_URL . 'assets/css/30-pages/shop-archive.css',
            array( 'wa-wc-shell', 'wa-product-card' ),
            '1.0.0'
        );
    }

    // Cart (klassisch + [woocommerce_cart])
    if ( $is_cart_page ) {
        wp_enqueue_style(
            'wa-cart',
            WERBEAUF_PLUGIN_URL . 'assets/css/30-pages/cart.css',
            array( 'wa-wc-shell' ),
            '1.2.0'
        );
        wp_enqueue_style(
            'wa-wc-blocks',
            WERBEAUF_PLUGIN_URL . 'assets/css/30-pages/wc-blocks.css',
            array( 'wa-wc-shell' ),
            '1.4.0'
        );
    }

    // Checkout (klassisch + Block + [woocommerce_checkout])
    if ( $is_checkout_page ) {
        wp_enqueue_style(
            'wa-checkout',
            WERBEAUF_PLUGIN_URL . 'assets/css/30-pages/checkout.css',
            array( 'wa-wc-shell' ),
            '1.2.0'
        );
        wp_enqueue_style(
            'wa-wc-blocks',
            WERBEAUF_PLUGIN_URL . 'assets/css/30-pages/wc-blocks.css',
            array( 'wa-wc-shell' ),
            '1.4.0'
        );

        // Newsletter-Optin -> DOM-injected Checkbox zwischen Billing und Payment
        // im WC-Blocks-Checkout. CSS + JS sind in der Shortcode-Datei bereits
        // registriert -- hier nur enqueuen.
        if ( wp_style_is( 'wa-newsletter', 'registered' ) ) {
            wp_enqueue_style( 'wa-newsletter' );
        }
        if ( wp_script_is( 'wa-newsletter', 'registered' ) ) {
            wp_enqueue_script( 'wa-newsletter' );
        }
    }

    // My Account (klassisch + Block + [woocommerce_my_account])
    if ( $is_account_page ) {
        wp_enqueue_style(
            'wa-account',
            WERBEAUF_PLUGIN_URL . 'assets/css/30-pages/account.css',
            array( 'wa-wc-shell' ),
            '1.5.0'
        );
    }

    // Single-Product
    if ( function_exists( 'is_product' ) && is_product() ) {
        wp_enqueue_style(
            'wa-section-heading',
            WERBEAUF_PLUGIN_URL . 'assets/css/20-components/section-heading.css',
            array( 'wa-tokens' ),
            '2.0.0'
        );
        wp_enqueue_style(
            'wa-single-product',
            WERBEAUF_PLUGIN_URL . 'assets/css/30-pages/single-product.css',
            array( 'wa-tokens', 'wa-product-card', 'wa-section-heading' ),
            '2.9.1'
        );
        wp_enqueue_style(
            'wa-trust-badges',
            WERBEAUF_PLUGIN_URL . 'assets/css/40-blocks/trust-badges.css',
            array( 'wa-single-product' ),
            '1.2.0'
        );
        wp_enqueue_style(
            'wa-accordion',
            WERBEAUF_PLUGIN_URL . 'assets/css/40-blocks/accordion.css',
            array( 'wa-single-product', 'wa-section-heading' ),
            '2.1.0'
        );
        wp_enqueue_style(
            'wa-detail-block',
            WERBEAUF_PLUGIN_URL . 'assets/css/40-blocks/detail-block.css',
            array( 'wa-single-product', 'wa-section-heading' ),
            '1.1.0'
        );
        wp_enqueue_script(
            'wa-detail-block',
            WERBEAUF_PLUGIN_URL . 'assets/js/detail-block.js',
            array(),
            '1.2.0',
            true
        );
        wp_enqueue_script(
            'wa-single-product',
            WERBEAUF_PLUGIN_URL . 'assets/js/single-product.js',
            array(),
            '2.2.0',
            true
        );
    }

    // Single-Blog (CPT 'blog' -- Divi Theme Builder Layout nutzt
    // [global-blog1-header] und [global-blog3-authorbox]).
    if ( is_singular( 'blog' ) ) {
        wp_enqueue_style(
            'wa-blog',
            WERBEAUF_PLUGIN_URL . 'assets/css/30-pages/blog.css',
            array( 'wa-tokens' ),
            '1.1.0'
        );
    }

    // Legal-Pages (AGB, Datenschutz, Impressum, Widerruf, Versand, Zahlung)
    // Inhalt wird via Divi Code Module + .legal-styling Wrapper gerendert.
    // IDs sind drperi-spezifisch -- DE + EN (WPML).
    $wa_legal_page_ids = array(
        508, 512, 2146, 19380130, 19380143, 19380146, 19380210,
        674, 686, 19380169, 19380172, 19380179, 19380183, 19380222,
    );
    if ( is_page( $wa_legal_page_ids ) ) {
        wp_enqueue_style(
            'wa-legal',
            WERBEAUF_PLUGIN_URL . 'assets/css/30-pages/legal.css',
            array( 'wa-tokens' ),
            '1.0.0'
        );
    }

}

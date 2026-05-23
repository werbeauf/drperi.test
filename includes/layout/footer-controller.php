<?php
/* ============================================================
   DATEI: includes/layout/footer-controller.php
   ZWECK: Spiegelbild zu header-controller.php.

   Logik:
     - Wenn der Divi Theme Builder ein eigenes Footer-Layout
       definiert, lassen wir Divi den Footer rendern.
     - Andernfalls wird unser Custom-Footer (templates/footer.php) via
       wp_footer (Prio 5) injiziert und Divis Default-Footer
       per Inline-CSS ausgeblendet.
============================================================ */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function wa_divi_theme_builder_has_footer(): bool {
    if ( function_exists( 'et_theme_builder_overrides_layout' ) ) {
        return (bool) et_theme_builder_overrides_layout( 'et_footer_layout' );
    }
    return false;
}

function wa_compute_footer_mode(): void {
    global $wa_show_fallback_footer;

    $wa_show_fallback_footer = false;

    if ( is_admin() || wp_doing_ajax() || wp_is_json_request() ) {
        return;
    }

    if ( wa_divi_theme_builder_has_footer() ) {
        return;
    }

    $wa_show_fallback_footer = true;
}
add_action( 'wp', 'wa_compute_footer_mode', 1 );

add_filter( 'body_class', function ( $classes ) {
    global $wa_show_fallback_footer;

    if ( ! empty( $wa_show_fallback_footer ) ) {
        $classes[] = 'werbeauf-footer-active';
    }

    return $classes;
}, 20 );

add_action( 'wp_head', function () {
    global $wa_show_fallback_footer;

    if ( empty( $wa_show_fallback_footer ) ) {
        return;
    }

    echo '<style>
        body.werbeauf-footer-active #main-footer,
        body.werbeauf-footer-active footer.et-l--footer,
        body.werbeauf-footer-active #et-footer-nav,
        body.werbeauf-footer-active #footer-bottom,
        body.werbeauf-footer-active #footer-widgets { display:none !important; }
    </style>';
}, 20 );

add_action( 'wp_footer', function () {
    global $wa_show_fallback_footer;

    if ( ! empty( $wa_show_fallback_footer ) ) {
        include WERBEAUF_PLUGIN_PATH . 'templates/footer.php';
    }
}, 5 );

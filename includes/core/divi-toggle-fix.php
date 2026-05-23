<?php
/* ============================================================
   DATEI: includes/divi-toggle-fix.php
============================================================ */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action('template_redirect', function() {
    if ( is_admin() || ( isset($_GET['et_fb']) && $_GET['et_fb'] === '1' ) ) {
        return;
    }
    ob_start();
});

add_action('wp_footer', function() {

    if ( is_admin() || ( isset($_GET['et_fb']) && $_GET['et_fb'] === '1' ) ) {
        return;
    }

    $final_html = ob_get_contents();
    
    if ( ! $final_html ) {
        return;
    }

    if ( strpos( $final_html, 'et_pb_toggle_title' ) !== false ) {
        echo '<style>.et_pb_toggle:not(.et_pb_toggle_open) .et_pb_toggle_content{display:none;}</style>';
    }

}, 1);
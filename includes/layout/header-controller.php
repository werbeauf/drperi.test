<?php
if (!defined('ABSPATH')) exit;

function wa_divi_theme_builder_has_header(): bool {
    if (function_exists('et_theme_builder_overrides_layout')) {
        return (bool) et_theme_builder_overrides_layout('et_header_layout');
    }
    return false;
}

function wa_compute_header_mode(): void {
    global $wa_show_fallback_header;

    $wa_show_fallback_header = false;

    if (is_admin() || wp_doing_ajax() || wp_is_json_request()) {
        return;
    }

    if (wa_divi_theme_builder_has_header()) {
        return;
    }

    $wa_show_fallback_header = true;
}
add_action('wp', 'wa_compute_header_mode', 1);

add_filter('body_class', function ($classes) {
    global $wa_show_fallback_header;

    if (!empty($wa_show_fallback_header)) {
        $classes[] = 'werbeauf-header-active';
    }

    return $classes;
}, 20);

add_action('wp_head', function () {
    global $wa_show_fallback_header;

    if (empty($wa_show_fallback_header)) return;

    echo '<style>
        body.werbeauf-header-active #top-header,
        body.werbeauf-header-active #main-header,
        body.werbeauf-header-active header.et-l--header { display:none !important; }
        body.werbeauf-header-active #page-container { padding-top:0 !important; }
        body.werbeauf-header-active.et_fixed_nav #page-container { padding-top:0 !important; }
    </style>';
}, 20);

add_action('wp_body_open', function () {
    global $wa_show_fallback_header;

    if (!empty($wa_show_fallback_header)) {
        include WERBEAUF_PLUGIN_PATH . 'templates/header.php';
    }
}, 5);

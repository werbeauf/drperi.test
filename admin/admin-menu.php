<?php
/* ============================================================
   DATEI: admin/admin-menu.php
============================================================ */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ------------------------------------------------------------
   Project-spezifisch: das SSO-Login-Submenu unter "Dr. Peri"
   anhaengen statt unter "Einstellungen". Filter wird vom
   SSO-Modul (includes/sso/settings.php) konsultiert; das Modul
   selbst bleibt portabel (Default = Einstellungen).
------------------------------------------------------------ */
add_filter( 'wa_sso_settings_menu_parent', function () {
    return 'dr-peri';
} );

add_action('admin_menu', function () {
    // Direkt den bekannten ACF Options Page Slug verwenden
    $parent_slug = 'dr-peri';

    // 2) Definition der Submenüs: [Titel, Slug, Datei ODER Funktion, Funktionsname falls Datei]
    $sub_pages = [
        [ __( 'Admin Docu',       'werbeauf-customs' ), 'wa-admin-docu',         'admin-docu.php',              'wa_render_docs_content' ],
        [ __( 'Phorest API',      'werbeauf-customs' ), 'wa-phorest-api',        'phorest-api.php',             'wa_render_phorest_api_content' ],
        [ __( 'Phorest Produkte', 'werbeauf-customs' ), 'wa-phorest-data',       'phorest-data.php',            'wa_render_phorest_data_content' ],
        [ __( 'Phorest Lager',    'werbeauf-customs' ), 'wa-phorest-stocks',     'phorest-stocks.php',          'wa_render_phorest_stocks_content' ],
        [ __( 'Newsletter Log',   'werbeauf-customs' ), 'wa-phorest-newsletter', 'phorest-newsletter.php',      'wa_render_phorest_newsletter_content' ],
    ];

    // 3) Submenüs registrieren
    foreach ($sub_pages as $index => $page) {
        add_submenu_page(
            $parent_slug,
            $page[0],
            $page[0],
            'manage_options',
            $page[1],
            function() use ($page) {
                $target = $page[2];
                $func   = $page[3];

                // Falls es eine bereits geladene Funktion ist
                if (function_exists($target)) {
                    call_user_func($target);
                } 
                // Falls es eine Datei ist, die wir inkludieren müssen
                else {
                    $path = __DIR__ . '/' . $target;
                    if (file_exists($path)) {
                        require_once $path;
                        if (!empty($func) && function_exists($func)) {
                            call_user_func($func);
                        }
                    } else {
                        echo '<div class="wrap"><h1>' . esc_html__( 'Fehler', 'werbeauf-customs' ) . '</h1><p>'
                            . esc_html__( 'Datei nicht gefunden:', 'werbeauf-customs' ) . ' ' . esc_html( $target )
                            . '</p></div>';
                    }
                }
            },
            20 + $index
        );
    }
}, 999);
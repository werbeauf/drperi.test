<?php
/* ============================================================
   DATEI: includes/shortcodes/footer-menu.php
   ZWECK: [wa_footer_menu] -> rendert das Menue der Theme-Location
          'footer-menu' als <nav class="legal-menu"><ul>... </ul></nav>.
          Wird im Footer-Template fuer die rechtlichen Links verwendet.
============================================================ */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_shortcode( 'wa_footer_menu', 'wa_render_footer_menu_by_location' );

if ( ! function_exists( 'wa_render_footer_menu_by_location' ) ) {
    function wa_render_footer_menu_by_location() {
        $args = array(
            'theme_location'  => 'footer-menu',
            'container'       => 'nav',
            'container_class' => 'legal-menu',
            'menu_class'      => 'wa-footer-ul',
            'echo'            => false,
            'fallback_cb'     => false,
            'depth'           => 1,
        );

        return wp_nav_menu( $args );
    }
}

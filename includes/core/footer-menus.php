<?php
/* ============================================================
   DATEI: includes/core/footer-menus.php
   ZWECK: Registriert eine zweite Footer-Menue-Location.

   Divi registriert von Haus aus 'footer-menu' (siehe
   wp-content/themes/Divi/functions.php). Wir ergaenzen hier
   'footer-menu-2' im Plugin-Scope, damit das Divi-Child unangetastet
   bleibt (Divi-child/functions.php ist per Konvention tabu).

   Render: templates/footer.php gibt beide Locations side-by-side aus,
   wenn jeweils ein Menue zugewiesen ist.
============================================================ */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'after_setup_theme', function () {
    register_nav_menu( 'footer-menu-2', __( 'Footer Menu 2', 'werbeauf-customs' ) );
    register_nav_menu( 'footer-menu-3', __( 'Footer Menu 3', 'werbeauf-customs' ) );
}, 20 );

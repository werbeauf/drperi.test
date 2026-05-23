<?php
/* ============================================================
   DATEI: includes/woocommerce/icon-extensions.php
   ZWECK: Erweitert die Icon-Map (siehe Werbeauf_Single_Product_Renderer
          ::icon_paths()) um fuenf neue Skincare-Icons.

   Wird konsumiert von:
     - Facts-Repeater (Single Product)
     - [wa_set_table] / [wa_product_catalog] (siehe shortcodes/)

   Alle Pfade nutzen viewBox="0 0 24 24" und stroke="currentColor",
   konsistent mit den existierenden Icons.
============================================================ */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_filter( 'wa_icon_paths', 'wa_extend_icon_paths' );

function wa_extend_icon_paths( $paths ) {
    $extra = array(
        // Day · Sonne mit 8 Strahlen.
        'day' => '<circle cx="12" cy="12" r="4"/>'
               . '<line x1="12" y1="2" x2="12" y2="5"/>'
               . '<line x1="12" y1="19" x2="12" y2="22"/>'
               . '<line x1="2" y1="12" x2="5" y2="12"/>'
               . '<line x1="19" y1="12" x2="22" y2="12"/>'
               . '<line x1="4.9" y1="4.9" x2="7" y2="7"/>'
               . '<line x1="17" y1="17" x2="19.1" y2="19.1"/>'
               . '<line x1="4.9" y1="19.1" x2="7" y2="17"/>'
               . '<line x1="17" y1="7" x2="19.1" y2="4.9"/>',

        // Night · Mondsichel.
        'night' => '<path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/>',

        // Day & Night · Sonne + ueberlagerte Sichel.
        'day_night' => '<circle cx="9" cy="12" r="4"/>'
                     . '<line x1="9" y1="3" x2="9" y2="5"/>'
                     . '<line x1="9" y1="19" x2="9" y2="21"/>'
                     . '<line x1="2" y1="12" x2="4" y2="12"/>'
                     . '<line x1="3.5" y1="6.5" x2="5" y2="8"/>'
                     . '<line x1="3.5" y1="17.5" x2="5" y2="16"/>'
                     . '<path d="M22 14a5 5 0 1 1-5-5 4 4 0 0 0 5 5z"/>',

        // Frei von allergenen Duft · Spruehflasche mit Schraegstrich.
        'fragrance_free' => '<path d="M9 3h6v3a3 3 0 0 1 3 3v9a3 3 0 0 1-3 3H9a3 3 0 0 1-3-3V9a3 3 0 0 1 3-3z"/>'
                          . '<line x1="9" y1="3" x2="9" y2="6"/>'
                          . '<line x1="15" y1="3" x2="15" y2="6"/>'
                          . '<line x1="3" y1="21" x2="21" y2="3"/>',

        // pH-Hautneutral · Kreis mit "pH" Schriftzug + Linie.
        'ph_neutral' => '<circle cx="12" cy="12" r="9"/>'
                      . '<path d="M8 8.5v7"/>'
                      . '<path d="M8 8.5h2.2a1.7 1.7 0 0 1 0 3.4H8"/>'
                      . '<path d="M14 9.5v5"/>'
                      . '<path d="M16.5 9.5v5"/>'
                      . '<path d="M14 12h2.5"/>',
    );

    return array_merge( (array) $paths, $extra );
}

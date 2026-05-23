<?php
/* ============================================================
   DATEI: includes/woocommerce/non-base-location-prices.php
   ZWECK: WooCommerce soll Bruttopreise NICHT umrechnen, wenn der
          Kunde ausserhalb der Shop-Basis-Location ist (anderer
          MwSt-Satz). Eingetragener Bruttopreis bleibt konstant.
============================================================ */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_filter( 'woocommerce_adjust_non_base_location_prices', '__return_false' );

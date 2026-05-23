<?php
/**
 * Plugin Name:       Werbeauf Customs
 * Plugin URI:        https://www.werbeauf.com/
 * Description:       Site-specific WordPress + WooCommerce customizations for Dr. Peri Skincare (layout shells, single-product hero, shop layout, Phorest sync, flyout cart, admin tools). Not portable to other sites.
 * Version:           2.7.0
 * Requires at least: 6.5
 * Requires PHP:      8.2
 * Author:            Werbeauf
 * Author URI:        https://www.werbeauf.com/
 * License:           Proprietary
 * Update URI:        false
 * Text Domain:       werbeauf-customs
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'WERBEAUF_PLUGIN_VERSION', '2.7.0' );
define( 'WERBEAUF_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WERBEAUF_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );

/* ------------------------------------------------------------
   Textdomain laden -- Voraussetzung dafuer, dass __() / _e()
   gegen die .mo Files in /languages/ uebersetzt wird.
------------------------------------------------------------ */
add_action( 'plugins_loaded', 'wa_load_plugin_textdomain' );
function wa_load_plugin_textdomain() {
    load_plugin_textdomain(
        'werbeauf-customs',
        false,
        dirname( plugin_basename( __FILE__ ) ) . '/languages'
    );
}

/* ------------------------------------------------------------
   AUTO-LOADER
   Laedt alle PHP-Dateien aus den Subfoldern von includes/ und admin/
   in fester Reihenfolge:
     1. core       (enqueue, admin-tweaks, divi-fix)
     2. acf        (Local Field Group Registrierungen)
     3. layout     (header/footer-controller, wc-shell)
     4. woocommerce(flyout-cart, single-product-renderer, ...)
     5. phorest    (sync engines)
     6. shortcodes (shop-layout, shop-filter, footer-menu, ...)
     7. admin/     (Admin-Pages, Phorest-Admin)
   Reihenfolge ist wichtig: layout/ braucht core/ Hooks, shortcodes/
   nutzen Helpers aus woocommerce/, acf/ haengt sich auf 'acf/init'
   und ist daher unkritisch fuer die Reihenfolge.
------------------------------------------------------------ */

/* Reihenfolge:
     core       cross-cutting bootstrap + wpml-helpers
     acf        ACF Local Field Groups (laufen auf 'acf/init' -> Reihenfolge egal)
     layout     header/footer/wc-shell -> brauchen core-Hooks
     woocommerce flyout-cart, single-product-renderer, helpers fuer shortcodes
     phorest    Inbound/Outbound-Sync
     sso        SSO Login Modul (self-contained, kann spaeter extrahiert werden)
     shortcodes Pages-Bloecke -> brauchen woocommerce-Helpers
*/
$wa_include_dirs = array( 'core', 'acf', 'layout', 'woocommerce', 'phorest', 'sso', 'shortcodes' );

foreach ( $wa_include_dirs as $wa_dir ) {
    $wa_pattern = WERBEAUF_PLUGIN_PATH . 'includes/' . $wa_dir . '/*.php';
    foreach ( (array) glob( $wa_pattern ) as $wa_file ) {
        require_once $wa_file;
    }
}

foreach ( (array) glob( WERBEAUF_PLUGIN_PATH . 'admin/*.php' ) as $wa_file ) {
    require_once $wa_file;
}

unset( $wa_include_dirs, $wa_dir, $wa_pattern, $wa_file );

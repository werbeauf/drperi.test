<?php
/* ============================================================
   DATEI: includes/sso/wc-email-brand.php
   ZWECK: Brand-Wrapper fuer die WooCommerce-Standard-Customer-
          Emails. Sorgt dafuer, dass die Bestellbestaetigung,
          Versand-Mail, Refund-Mail etc. das gleiche Klinik-
          Branding zeigen wie unsere eigenen Workflow-Templates.

   Wir aendern KEINE WP-Optionen (nicht-invasiv). Stattdessen
   filtern wir die Werte zur Laufzeit:

     - option_woocommerce_email_base_color       -> primary
     - option_woocommerce_email_background_color -> page bg
     - option_woocommerce_email_body_background_color -> card bg
     - option_woocommerce_email_text_color       -> body text
     - option_woocommerce_email_footer_text_color -> footer text
     - option_woocommerce_email_header_image     -> drperi-Logo
     - woocommerce_email_styles                  -> Zusatz-CSS

   So bleibt die WC-Settings-UI editierbar — falls drperi mal
   etwas anpassen will, gehen unsere Filter weg per
   add_filter( 'wa_wc_email_brand_active', '__return_false' ).

   Aktiv nur wenn das SSO-Modul enabled ist (Master-Switch).
============================================================ */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Master-Toggle fuer das WC-Email-Brand-Wrapping. Per Default an,
 * wenn SSO-Modul aktiviert ist. Filterbar.
 *
 * @return bool
 */
function wa_wc_email_brand_active() {
    $default = function_exists( 'wa_sso_is_enabled' ) ? wa_sso_is_enabled() : true;
    return (bool) apply_filters( 'wa_wc_email_brand_active', $default );
}

/* ----------------------------------------------------------
   FARB-OVERRIDES ueber pre_option_* (kein DB-Write)
---------------------------------------------------------- */

add_filter( 'pre_option_woocommerce_email_base_color',            'wa_wc_email_brand_base_color', 10, 1 );
add_filter( 'pre_option_woocommerce_email_background_color',      'wa_wc_email_brand_bg_color', 10, 1 );
add_filter( 'pre_option_woocommerce_email_body_background_color', 'wa_wc_email_brand_body_bg_color', 10, 1 );
add_filter( 'pre_option_woocommerce_email_text_color',            'wa_wc_email_brand_text_color', 10, 1 );
add_filter( 'pre_option_woocommerce_email_footer_text_color',     'wa_wc_email_brand_footer_text_color', 10, 1 );
add_filter( 'pre_option_woocommerce_email_header_image',          'wa_wc_email_brand_header_image', 10, 1 );

/** pre_option_* erwartet null fuer "weiter zur DB", oder unseren Wert. */
function wa_wc_email_brand_base_color( $val ) {
    if ( ! wa_wc_email_brand_active() ) return $val;
    $b = wa_workflow_brand_assets();
    return ! empty( $b['primary'] ) ? $b['primary'] : $val;
}
function wa_wc_email_brand_bg_color( $val ) {
    if ( ! wa_wc_email_brand_active() ) return $val;
    return '#f7f8fa'; // Page-Hintergrund (cool grey, matched zu unseren CSS-Tokens)
}
function wa_wc_email_brand_body_bg_color( $val ) {
    if ( ! wa_wc_email_brand_active() ) return $val;
    return '#ffffff'; // Card-Hintergrund
}
function wa_wc_email_brand_text_color( $val ) {
    if ( ! wa_wc_email_brand_active() ) return $val;
    $b = wa_workflow_brand_assets();
    return ! empty( $b['primary'] ) ? $b['primary'] : $val;
}
function wa_wc_email_brand_footer_text_color( $val ) {
    if ( ! wa_wc_email_brand_active() ) return $val;
    return '#9aa9b6'; // Muted grey -- gleicher Wert wie unsere Templates
}
function wa_wc_email_brand_header_image( $val ) {
    if ( ! wa_wc_email_brand_active() ) return $val;
    $b = wa_workflow_brand_assets();
    return ! empty( $b['logo_url'] ) ? $b['logo_url'] : $val;
}

/* ----------------------------------------------------------
   CSS-INJECTION

   WC hat einen Filter `woocommerce_email_styles( $css, $email )`
   der die generierte CSS-Style-Section ausliefert. Wir
   appenden ein paar Klinik-Anpassungen damit das Look-and-Feel
   konsistent ist: groessere Border-Radius, runder CTA-Button,
   gefolgert von einem leichten Header-Border in unserer
   Akzent-Farbe.
---------------------------------------------------------- */

add_filter( 'woocommerce_email_styles', 'wa_wc_email_brand_styles', 99, 2 );

function wa_wc_email_brand_styles( $css, $email = null ) {
    if ( ! wa_wc_email_brand_active() ) {
        return $css;
    }
    $b       = wa_workflow_brand_assets();
    $primary = ! empty( $b['primary'] ) ? $b['primary'] : '#475e76';

    $extra = "
/* === drperi brand additions (werbeauf-customs/includes/sso/wc-email-brand.php) === */
#template_container {
    border-radius: 8px !important;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04) !important;
}
#template_header {
    border-bottom: 3px solid {$primary} !important;
    border-radius: 8px 8px 0 0 !important;
}
#template_header h1 {
    font-weight: 700 !important;
    letter-spacing: -0.3px !important;
}
.button {
    border-radius: 6px !important;
    padding: 14px 28px !important;
    font-weight: 600 !important;
}
a {
    color: {$primary} !important;
}
";

    return $css . $extra;
}

/* ----------------------------------------------------------
   ZUSATZ-TEXT IN »ADDITIONAL CONTENT« FUER PROCESSING + COMPLETED

   WC-Settings haben pro Email ein »Additional Content« Feld.
   Wir laden die Defaults pro Email-Klasse aus diesem Modul,
   damit drperi sofort einen sauberen Klinik-Tonfall hat
   ohne Admin-Arbeit. Manuell ueberschriebene Inhalte in WC
   ueberschreiben unsere Defaults (siehe Filter-Priority).
---------------------------------------------------------- */

add_filter( 'option_woocommerce_customer_processing_order_settings', 'wa_wc_email_brand_processing_defaults', 10, 1 );
add_filter( 'option_woocommerce_customer_completed_order_settings',  'wa_wc_email_brand_completed_defaults',  10, 1 );

function wa_wc_email_brand_processing_defaults( $val ) {
    if ( ! wa_wc_email_brand_active() || ! is_array( $val ) ) {
        return $val;
    }
    if ( empty( $val['additional_content'] ) ) {
        $val['additional_content'] = __( "Vielen Dank für Ihre Bestellung. Wir bereiten alles vor und melden uns sobald die Sendung unterwegs ist.\n\nBei Fragen erreichen Sie uns unter +43 1 470 41 97 oder office@drperi.at — wir helfen Ihnen gerne persönlich weiter.", 'werbeauf-customs' );
    }
    return $val;
}

function wa_wc_email_brand_completed_defaults( $val ) {
    if ( ! wa_wc_email_brand_active() || ! is_array( $val ) ) {
        return $val;
    }
    if ( empty( $val['additional_content'] ) ) {
        $val['additional_content'] = __( "Ihre Bestellung wurde an Sie versendet. Sie sollte in den nächsten Werktagen bei Ihnen eintreffen.\n\nSollte etwas nicht in Ordnung sein, schreiben Sie uns kurz an office@drperi.at — wir kümmern uns sofort darum.", 'werbeauf-customs' );
    }
    return $val;
}

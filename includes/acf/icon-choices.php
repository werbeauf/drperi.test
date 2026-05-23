<?php
/* ============================================================
   DATEI: includes/acf/icon-choices.php
   ZWECK: Haengt die fuenf neuen Icon-Keys in das ACF-Dropdown
          des Facts-Repeaters (Single Product).
   QUELLE: Field-Key field_wa_product_facts_icon
          (siehe includes/acf/single-product-fields.php).
============================================================ */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_filter( 'acf/load_field/key=field_wa_product_facts_icon', 'wa_extend_facts_icon_choices' );

function wa_extend_facts_icon_choices( $field ) {
    if ( ! isset( $field['choices'] ) || ! is_array( $field['choices'] ) ) {
        $field['choices'] = array();
    }
    $field['choices'] = array_merge( $field['choices'], wa_skincare_icon_choices() );
    return $field;
}

/**
 * Single-Source-of-Truth fuer die Skincare-Icon-Labels (DE).
 * Wird auch vom Keypoints-Subfield konsumiert.
 */
function wa_skincare_icon_choices() {
    return array(
        'day'             => 'Tag (Sonne)',
        'night'           => 'Nacht (Mond)',
        'day_night'       => 'Tag & Nacht (Sonne + Mond)',
        'fragrance_free'  => 'Frei von allergenen Duft',
        'ph_neutral'      => 'pH-Hautneutral',
    );
}

<?php
/* ============================================================
   DATEI: includes/acf/trust-items-icon-extend.php
   ZWECK: Erweitert das Icon-Dropdown im Trust-Items-Repeater
          (ACF Options-Page "Assets") um:
            - die 5 neuen Skincare-Icons (day/night/day_night/
              fragrance_free/ph_neutral)
            - alle 8 Default-Facts-Icons (falls dort noch nicht vorhanden)

   Field-Key: field_wasp_trust_icon
   (Repeater field_wasp_trust_items innerhalb von group field_wasp_group)
============================================================ */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_filter( 'acf/load_field/key=field_wasp_trust_icon', 'wa_extend_trust_icon_choices' );

function wa_extend_trust_icon_choices( $field ) {
    if ( ! isset( $field['choices'] ) || ! is_array( $field['choices'] ) ) {
        $field['choices'] = array();
    }

    // Vereinheitlichte Choice-Liste (8 Default + 5 Neu).
    $defaults = array(
        'check'    => 'Check (Allround)',
        'leaf'     => 'Leaf (Vegan / Naturkosmetik)',
        'shield'   => 'Shield (Hautvertraeglich / Schutz)',
        'sparkles' => 'Sparkles (Premium / Glow)',
        'droplet'  => 'Droplet (Feuchtigkeit / Hydration)',
        'flask'    => 'Flask (Wirkstoff / Lab)',
        'heart'    => 'Heart (Tierversuchsfrei / Care)',
        'truck'    => 'Truck (Versand)',
    );
    if ( function_exists( 'wa_skincare_icon_choices' ) ) {
        $defaults = array_merge( $defaults, wa_skincare_icon_choices() );
    }

    // Bestehende Choices (z.B. Custom-Icons aus dem ACF-UI) priorisieren,
    // dann unsere Defaults dazu, dedupliziert.
    $field['choices'] = array_merge( $defaults, $field['choices'] );

    // Select2-Modus aktivieren, damit Icon-Previews greifen.
    $field['ui'] = 1;

    return $field;
}

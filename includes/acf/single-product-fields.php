<?php
/* ============================================================
   DATEI: includes/acf/single-product-fields.php
   ZWECK: Registriert die ACF Field Groups fuer das custom Single-
          Product-Layout (siehe includes/woocommerce/single-product-
          renderer.php).

   Drei Gruppen, alle pro Produkt (post_type == product):
     A) wa_product_facts     -> 3 Icon/Text-Facts unter dem Produktbild
     B) wa_product_keypoints -> 5 Label/Value-Spec-Zeilen im Summary
     C) wa_product_faq       -> Headline + Beschreibung + bis zu 10
                                Frage/Antwort-Items am Seitenende
                                (auch fuer JSON-LD FAQPage Schema)

   Felder werden via PHP versioniert registriert -- kein manueller
   ACF-UI-Setup noetig. Bei Schema-Aenderungen reicht ein Edit dieser
   Datei + dev-flush.
============================================================ */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'acf/init', 'wa_register_single_product_field_groups' );

function wa_register_single_product_field_groups() {
    if ( ! function_exists( 'acf_add_local_field_group' ) ) {
        return;
    }

    $product_location = array(
        array(
            array(
                'param'    => 'post_type',
                'operator' => '==',
                'value'    => 'product',
            ),
        ),
    );

    // Icon-Optionen sind die Keys aus Werbeauf_Single_Product_Renderer::icon().
    // Bei Erweiterung dort -> hier ebenfalls ergaenzen.
    $icon_choices = array(
        'check'    => 'Check (Allround)',
        'leaf'     => 'Leaf (Vegan / Naturkosmetik)',
        'shield'   => 'Shield (Hautvertraeglich / Schutz)',
        'sparkles' => 'Sparkles (Premium / Glow)',
        'droplet'  => 'Droplet (Feuchtigkeit / Hydration)',
        'flask'    => 'Flask (Wirkstoff / Lab)',
        'heart'    => 'Heart (Tierversuchsfrei / Care)',
        'truck'    => 'Truck (Versand)',
    );

    /* ------------------------------------------------------------
       A) FACTS unter dem Produktbild (max 3, Icon + Text)
    ------------------------------------------------------------ */
    acf_add_local_field_group( array(
        'key'                   => 'group_wa_product_facts',
        'title'                 => 'Produkt-Facts (unter Bild)',
        'menu_order'            => 10,
        'position'              => 'normal',
        'style'                 => 'default',
        'label_placement'       => 'top',
        'instruction_placement' => 'label',
        'active'                => true,
        'description'           => '1-3 kurze Pluspunkte mit Icon, erscheinen direkt unter dem Produktbild.',
        'location'              => $product_location,
        'fields'                => array(
            array(
                'key'          => 'field_wa_product_facts',
                'label'        => 'Facts',
                'name'         => 'facts',
                'type'         => 'repeater',
                'instructions' => 'Maximal 3 Eintraege. Mehr werden ignoriert.',
                'min'          => 0,
                'max'          => 3,
                'layout'       => 'block',
                'button_label' => 'Fact hinzufuegen',
                'sub_fields'   => array(
                    array(
                        'key'           => 'field_wa_product_facts_icon',
                        'label'         => 'Icon',
                        'name'          => 'icon',
                        'type'          => 'select',
                        'choices'       => $icon_choices,
                        'default_value' => 'check',
                        'allow_null'    => 0,
                        'ui'            => 1,
                        'wrapper'       => array( 'width' => '30' ),
                    ),
                    array(
                        'key'         => 'field_wa_product_facts_text',
                        'label'       => 'Text',
                        'name'        => 'text',
                        'type'        => 'text',
                        'maxlength'   => 60,
                        'placeholder' => 'z.B. Vegan, pH-hautneutral, Parabenfrei',
                        'wrapper'     => array( 'width' => '70' ),
                    ),
                ),
            ),
        ),
    ) );

    /* ------------------------------------------------------------
       B) KEYPOINTS zwischen Short Description und Divider
    ------------------------------------------------------------ */
    acf_add_local_field_group( array(
        'key'                   => 'group_wa_product_keypoints',
        'title'                 => 'Produkt-Keypoints (Spec-Liste)',
        'menu_order'            => 11,
        'position'              => 'normal',
        'style'                 => 'default',
        'label_placement'       => 'top',
        'instruction_placement' => 'label',
        'active'                => true,
        'description'           => 'Bis zu 5 Label/Value-Zeilen (z.B. Verwendungszweck | Key Ingredients | Anwendung). Erscheinen direkt unter der Kurzbeschreibung.',
        'location'              => $product_location,
        'fields'                => array(
            array(
                'key'          => 'field_wa_product_keypoints',
                'label'        => 'Keypoints',
                'name'         => 'keypoints',
                'type'         => 'repeater',
                'min'          => 0,
                'max'          => 5,
                'layout'       => 'table',
                'button_label' => 'Keypoint hinzufuegen',
                'sub_fields'   => array(
                    array(
                        'key'         => 'field_wa_product_keypoints_label',
                        'label'       => 'Label',
                        'name'        => 'label',
                        'type'        => 'text',
                        'maxlength'   => 40,
                        'placeholder' => 'z.B. Verwendungszweck',
                        'wrapper'     => array( 'width' => '30' ),
                    ),
                    array(
                        'key'         => 'field_wa_product_keypoints_value',
                        'label'       => 'Value',
                        'name'        => 'value',
                        'type'        => 'textarea',
                        'rows'        => 2,
                        'new_lines'   => 'br',
                        'placeholder' => 'z.B. Milder alkoholfreier Reinigungsschaum fuer oelige & Mischhaut',
                        'wrapper'     => array( 'width' => '70' ),
                    ),
                ),
            ),
        ),
    ) );

    /* ------------------------------------------------------------
       C) FAQ am Seitenende + JSON-LD
    ------------------------------------------------------------ */
    acf_add_local_field_group( array(
        'key'                   => 'group_wa_product_faq',
        'title'                 => 'Produkt-FAQ (Seitenende + Schema.org)',
        'menu_order'            => 12,
        'position'              => 'normal',
        'style'                 => 'default',
        'label_placement'       => 'top',
        'instruction_placement' => 'label',
        'active'                => true,
        'description'           => 'Frage/Antwort-Block am Seitenende. Wird automatisch als FAQPage-JSON-LD im <head> ausgegeben (Google Rich Results).',
        'location'              => $product_location,
        'fields'                => array(
            array(
                'key'           => 'field_wa_product_faq_headline',
                'label'         => 'Headline',
                'name'          => 'faq_headline',
                'type'          => 'text',
                'default_value' => 'Haeufige Fragen',
                'placeholder'   => 'Haeufige Fragen',
            ),
            array(
                'key'         => 'field_wa_product_faq_description',
                'label'       => 'Beschreibung (optional)',
                'name'        => 'faq_description',
                'type'        => 'textarea',
                'rows'        => 3,
                'new_lines'   => 'br',
                'placeholder' => 'Optionaler Intro-Text ueber den FAQs.',
            ),
            array(
                'key'          => 'field_wa_product_faq_items',
                'label'        => 'FAQ-Items',
                'name'         => 'faq_items',
                'type'         => 'repeater',
                'min'          => 0,
                'max'          => 10,
                'layout'       => 'block',
                'button_label' => 'Frage hinzufuegen',
                'sub_fields'   => array(
                    array(
                        'key'         => 'field_wa_product_faq_question',
                        'label'       => 'Frage',
                        'name'        => 'question',
                        'type'        => 'text',
                        'placeholder' => 'z.B. Wie wende ich PeriClean Foam an?',
                    ),
                    array(
                        'key'         => 'field_wa_product_faq_answer',
                        'label'       => 'Antwort',
                        'name'        => 'answer',
                        'type'        => 'textarea',
                        'rows'        => 4,
                        'new_lines'   => 'br',
                        'instructions' => 'Plain Text -- HTML wird im JSON-LD Schema entfernt.',
                        'placeholder' => 'z.B. Morgens und abends eine erbsengrosse Menge auf der feuchten Haut sanft einmassieren und gruendlich abspuelen.',
                    ),
                ),
            ),
        ),
    ) );
}

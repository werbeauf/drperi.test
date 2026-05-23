<?php
/* ============================================================
   DATEI: includes/acf/footer-fields.php
   ZWECK: Registriert die ACF-Field-Group fuer den Custom-Footer
          (siehe templates/footer.php).

   Struktur:
     group_wa_footer (Field Group, Location: options_page == 'dr-peri')
       └─ footer (Group, key=field_wa_footer)
           ├─ address          (Textarea)
           ├─ email            (E-Mail)
           ├─ phone            (Text)
           ├─ opening_hours    (Repeater: day + hours, prefilled Mo–Sa)
           ├─ social_links     (Repeater: platform + url)
           └─ copyright_text   (Text, neu, ersetzt copyright_suffix)

   Hinweis: Die existierende UI-Group "Assets" enthaelt aktuell ein
   gleichnamiges 'footer'-Group-Field. Vor Aktivierung dieser PHP-
   Registrierung MUSS das Footer-Sub-Field aus "Assets" entfernt
   werden, sonst rendert ACF den Block doppelt auf der Optionsseite.
   Saved data (options_footer_address etc.) bleibt erhalten und
   re-bindet anhand der Feldnamen.

   Pattern angelehnt an includes/acf/single-product-fields.php.
============================================================ */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'acf/init', 'wa_register_footer_field_group' );

function wa_register_footer_field_group() {
    if ( ! function_exists( 'acf_add_local_field_group' ) ) {
        return;
    }

    $hours_default = array(
        array( 'day' => 'Montag',     'hours' => '08:30 – 19:00' ),
        array( 'day' => 'Dienstag',   'hours' => '08:30 – 19:00' ),
        array( 'day' => 'Mittwoch',   'hours' => '08:30 – 19:00' ),
        array( 'day' => 'Donnerstag', 'hours' => '08:30 – 19:00' ),
        array( 'day' => 'Freitag',    'hours' => '08:30 – 19:00' ),
        array( 'day' => 'Samstag',    'hours' => '09:00 – 16:00' ),
    );

    $social_choices = array(
        'instagram' => 'Instagram',
        'facebook'  => 'Facebook',
        'tiktok'    => 'TikTok',
        'youtube'   => 'YouTube',
        'linkedin'  => 'LinkedIn',
        'x'         => 'X (Twitter)',
    );

    acf_add_local_field_group( array(
        'key'                   => 'group_wa_footer',
        'title'                 => 'Footer',
        'menu_order'            => 20,
        'position'              => 'normal',
        'style'                 => 'default',
        'label_placement'       => 'top',
        'instruction_placement' => 'label',
        'active'                => true,
        'description'           => 'Inhalte fuer den Custom-Footer (templates/footer.php). Wird nur ausgegeben, wenn kein Divi-Theme-Builder-Footer aktiv ist.',
        'location'              => array(
            array(
                array(
                    'param'    => 'options_page',
                    'operator' => '==',
                    'value'    => 'dr-peri',
                ),
            ),
        ),
        'fields' => array(
            array(
                'key'          => 'field_wa_footer',
                'label'        => 'Footer',
                'name'         => 'footer',
                'type'         => 'group',
                'instructions' => '',
                'layout'       => 'block',
                'sub_fields'   => array(
                    array(
                        'key'          => 'field_wa_footer_address',
                        'label'        => 'Adresse',
                        'name'         => 'address',
                        'type'         => 'textarea',
                        'instructions' => 'HTML erlaubt (z.B. <a href="...">Maps-Link</a>).',
                        'rows'         => 3,
                        'new_lines'    => '',
                    ),
                    array(
                        'key'     => 'field_wa_footer_email',
                        'label'   => 'E-Mail',
                        'name'    => 'email',
                        'type'    => 'email',
                        'wrapper' => array( 'width' => '50' ),
                    ),
                    array(
                        'key'     => 'field_wa_footer_phone',
                        'label'   => 'Telefon',
                        'name'    => 'phone',
                        'type'    => 'text',
                        'wrapper' => array( 'width' => '50' ),
                    ),
                    array(
                        'key'           => 'field_wa_footer_opening_hours',
                        'label'         => 'Oeffnungszeiten',
                        'name'          => 'opening_hours',
                        'type'          => 'repeater',
                        'instructions'  => 'Pro Zeile ein Tag mit Oeffnungszeiten. Reihenfolge wird im Footer uebernommen.',
                        'min'           => 0,
                        'max'           => 10,
                        'layout'        => 'table',
                        'button_label'  => 'Tag hinzufuegen',
                        'default_value' => $hours_default,
                        'sub_fields'    => array(
                            array(
                                'key'         => 'field_wa_footer_opening_hours_day',
                                'label'       => 'Tag',
                                'name'        => 'day',
                                'type'        => 'text',
                                'required'    => 1,
                                'placeholder' => 'z.B. Montag',
                                'wrapper'     => array( 'width' => '40' ),
                            ),
                            array(
                                'key'         => 'field_wa_footer_opening_hours_hours',
                                'label'       => 'Oeffnungszeiten',
                                'name'        => 'hours',
                                'type'        => 'text',
                                'required'    => 1,
                                'placeholder' => 'z.B. 08:30 – 19:00',
                                'wrapper'     => array( 'width' => '60' ),
                            ),
                        ),
                    ),
                    array(
                        'key'          => 'field_wa_footer_newsletter_heading',
                        'label'        => 'Newsletter-Headline',
                        'name'         => 'newsletter_heading',
                        'type'         => 'text',
                        'instructions' => 'Headline ueber dem Newsletter (Typo: H4-Stil).',
                        'placeholder'  => 'Newsletter',
                    ),
                    array(
                        'key'          => 'field_wa_footer_newsletter_intro',
                        'label'        => 'Newsletter-Intro',
                        'name'         => 'newsletter_intro',
                        'type'         => 'text',
                        'instructions' => 'Lead-Zeile unter der Newsletter-Headline (Typo: --fs-lead).',
                        'placeholder'  => 'Beauty-Insights und exklusive Angebote direkt ins Postfach.',
                    ),
                    array(
                        'key'          => 'field_wa_footer_social_links',
                        'label'        => 'Social Links',
                        'name'         => 'social_links',
                        'type'         => 'repeater',
                        'instructions' => 'Plattform + URL. Icons werden automatisch eingesetzt (siehe wa_footer_social_icon()).',
                        'min'          => 0,
                        'layout'       => 'table',
                        'button_label' => 'Social Link hinzufuegen',
                        'sub_fields'   => array(
                            array(
                                'key'        => 'field_wa_footer_social_links_platform',
                                'label'      => 'Plattform',
                                'name'       => 'platform',
                                'type'       => 'select',
                                'choices'    => $social_choices,
                                'allow_null' => 0,
                                'ui'         => 1,
                                'wrapper'    => array( 'width' => '40' ),
                            ),
                            array(
                                'key'         => 'field_wa_footer_social_links_url',
                                'label'       => 'URL',
                                'name'        => 'url',
                                'type'        => 'url',
                                'placeholder' => 'https://...',
                                'wrapper'     => array( 'width' => '60' ),
                            ),
                        ),
                    ),
                    array(
                        'key'          => 'field_wa_footer_copyright_text',
                        'label'        => 'Copyright-Text',
                        'name'         => 'copyright_text',
                        'type'         => 'text',
                        'instructions' => 'Text nach "© {Jahr} ". Beispiel: "www.drperi.at - Alle Rechte vorbehalten."',
                        'placeholder'  => 'www.drperi.at - Alle Rechte vorbehalten.',
                    ),
                ),
            ),
        ),
    ) );
}

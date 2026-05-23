<?php
/* ============================================================
   DATEI: includes/acf/product-volume-field.php
   ZWECK: ACF-Local-Field-Group fuer Produkt-Zusatzdaten.
          Felder:
            - volume_ml         (Text, Fallback fuer WC-Attribut "inhalt")
            - clean_featured_image (Image, "Beitragsbild Clean" fuer
              Set-Tabelle und Catalog-Accordions)
============================================================ */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'acf/init', 'wa_register_product_volume_field' );

function wa_register_product_volume_field() {
    if ( ! function_exists( 'acf_add_local_field_group' ) ) {
        return;
    }

    acf_add_local_field_group( array(
        'key'                   => 'group_wa_product_volume',
        'title'                 => 'Produkt-Zusatz (Inhalt + Clean-Bild)',
        'menu_order'            => 9,
        'position'              => 'normal',
        'style'                 => 'default',
        'label_placement'       => 'top',
        'instruction_placement' => 'label',
        'active'                => true,
        'description'           => 'Wird in Set-Tabellen und im Produkt-Catalog ausgegeben.',
        'location'              => array(
            array(
                array(
                    'param'    => 'post_type',
                    'operator' => '==',
                    'value'    => 'product',
                ),
            ),
        ),
        'fields' => array(
            array(
                'key'           => 'field_wa_product_volume_ml',
                'label'         => 'Inhalt (Fallback)',
                'name'          => 'volume_ml',
                'type'          => 'text',
                'maxlength'     => 12,
                'placeholder'   => 'z.B. 50 ml',
                'instructions'  => 'Optional. Wird nur verwendet, wenn das WooCommerce-Attribut "Inhalt" leer ist.',
                'wrapper'       => array( 'width' => '40' ),
            ),
            array(
                'key'           => 'field_wa_product_clean_image',
                'label'         => 'Beitragsbild Clean',
                'name'          => 'clean_featured_image',
                'type'          => 'image',
                'return_format' => 'array',
                'preview_size'  => 'thumbnail',
                'library'       => 'all',
                'instructions'  => 'Freigestelltes Produktbild fuer Set-Tabelle + Produkt-Catalog (transparent / weisser Hintergrund empfohlen).',
                'wrapper'       => array( 'width' => '60' ),
            ),
        ),
    ) );
}

/**
 * Single-Source-of-Truth fuer die Inhaltsmenge eines Produkts.
 * Reihenfolge:
 *   1. WC-Produkt-Attribut "Inhalt" (case-insensitive Match auf
 *      Slug "inhalt" oder Label "Inhalt")
 *   2. ACF-Field volume_ml (Fallback)
 *   3. Leerer String
 */
function wa_get_product_volume( $product ) {
    if ( ! $product instanceof WC_Product ) {
        return '';
    }

    foreach ( $product->get_attributes() as $key => $attr ) {
        $name = strtolower( (string) $attr->get_name() );
        if ( in_array( $name, array( 'inhalt', 'volumen', 'volume' ), true ) || $key === 'inhalt' ) {
            $options = $attr->get_options();
            if ( ! empty( $options ) ) {
                return trim( (string) reset( $options ) );
            }
        }
    }

    if ( function_exists( 'get_field' ) ) {
        $val = trim( (string) get_field( 'volume_ml', $product->get_id() ) );
        if ( '' !== $val ) {
            return $val;
        }
    }

    return '';
}

/**
 * Liefert die "Beitragsbild Clean"-URL fuer ein Produkt, oder leer.
 * Returnformat: ACF "array" -> wir bevorzugen die "medium" Size.
 */
function wa_get_product_clean_image( $product_id, $size = 'medium' ) {
    if ( ! function_exists( 'get_field' ) ) {
        return array();
    }
    $img = get_field( 'clean_featured_image', $product_id );
    if ( empty( $img ) || ! is_array( $img ) ) {
        return array();
    }
    $url = '';
    if ( isset( $img['sizes'][ $size ] ) ) {
        $url = $img['sizes'][ $size ];
    } elseif ( isset( $img['url'] ) ) {
        $url = $img['url'];
    }
    return array(
        'url' => $url,
        'alt' => isset( $img['alt'] ) ? (string) $img['alt'] : '',
        'id'  => isset( $img['ID'] ) ? (int) $img['ID'] : 0,
    );
}

<?php
/* ============================================================
   DATEI: includes/shortcodes/at-a-glance.php
   ZWECK: [wa_at_a_glance] Shortcode -> "Auf einen Blick"-Tabelle.
          Gruppiert publizierte Produkte nach product_cat. Pro
          Gruppe schmaler 10px Color-Bar links (Term-Meta
          wa_bg_color / wa_fg_color). Daten-Bereich = 5 Spalten:
            Bild | Produkt | Preis | Facts | Keypoints

   Editorial-Spec-Sheet Design (siehe assets/css/40-blocks/at-a-glance.css):
     - Container: weisser BG, soft shadow, radius-lg
     - Cat-Strip:  10px Color-Bar (kein rotierter Text); der Chip
                   neben dem Produktnamen tragt die Identitaet
     - Bild:       quadratisch, soft tint + inset hairline
     - Hierarchie: --fs-product-title > --fs-price > --fs-meta
     - Facts:      Soft-Pill-Chips mit Icon (wrap-Layout)
     - Keypoints:  Label/Value-Stack in accent-2 / accent
     - Hover:      Row-BG faded zu --color-bg-soft
     - Responsive: 1100 (compact) / 980 (mobile-stack) / 767 (refinement)

   Beispiel-Nutzung:
     [wa_at_a_glance]
     [wa_at_a_glance categories="reinigung,vorbeugung,pflege,specials"]
     [wa_at_a_glance categories="pflege" order="title"]

   Attribute (alle optional):
     categories  Slug-Liste in Renderreihenfolge.
                 Default: "reinigung,vorbeugung,pflege,specials,sets"
     order       WP_Query orderby (default: "menu_order").

   Abhaengigkeiten:
     - WooCommerce (wc_get_product)
     - ACF Felder facts + keypoints + clean_featured_image + volume_ml
       (von der Test-Bundle ACF-Group registriert; Frontend bleibt
       defensiv und rendert ohne wenn die Helper fehlen)
     - Werbeauf_Single_Product_Renderer::icon_svg() fuer Icons
     - wa_get_term_by_slug_localized() fuer WPML
============================================================ */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'wp_enqueue_scripts', 'wa_at_a_glance_register_assets' );
function wa_at_a_glance_register_assets() {
    wp_register_style(
        'wa-at-a-glance',
        WERBEAUF_PLUGIN_URL . 'assets/css/40-blocks/at-a-glance.css',
        array( 'wa-tokens' ),
        '1.0.0'
    );
}

add_shortcode( 'wa_at_a_glance', 'wa_render_at_a_glance' );

function wa_render_at_a_glance( $atts ) {
    if ( ! function_exists( 'wc_get_product' ) ) {
        return '';
    }

    $atts = shortcode_atts(
        array(
            'categories' => 'reinigung,vorbeugung,pflege,specials,sets',
            'order'      => 'menu_order',
        ),
        $atts,
        'wa_at_a_glance'
    );

    $slugs = array_filter( array_map( 'trim', explode( ',', (string) $atts['categories'] ) ) );
    if ( empty( $slugs ) ) {
        return '';
    }

    // Kategorien in der angegebenen Reihenfolge laden. Localized
    // Helper: DE-Slugs werden auf der EN-Site auf die EN-Term-IDs
    // gemappt.
    $terms_by_slug = array();
    foreach ( $slugs as $slug ) {
        $term = function_exists( 'wa_get_term_by_slug_localized' )
            ? wa_get_term_by_slug_localized( $slug, 'product_cat' )
            : get_term_by( 'slug', $slug, 'product_cat' );
        if ( $term && ! is_wp_error( $term ) ) {
            $terms_by_slug[ $slug ] = $term;
        }
    }
    if ( empty( $terms_by_slug ) ) {
        return '';
    }

    // Produkte je Kategorie holen.
    $groups = array();
    foreach ( $terms_by_slug as $slug => $term ) {
        $query = new WP_Query( array(
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => $atts['order'],
            'order'          => 'ASC',
            'tax_query'      => array(
                array(
                    'taxonomy'         => 'product_cat',
                    'field'            => 'term_id',
                    'terms'            => array( (int) $term->term_id ),
                    'include_children' => false,
                ),
            ),
            'no_found_rows'  => true,
        ) );
        if ( ! empty( $query->posts ) ) {
            $groups[ $slug ] = array(
                'term'     => $term,
                'products' => $query->posts,
            );
        }
        wp_reset_postdata();
    }

    if ( empty( $groups ) ) {
        return '';
    }

    wp_enqueue_style( 'wa-at-a-glance' );

    ob_start();

    echo '<div class="wa-at-a-glance" role="table" aria-label="' . esc_attr__( 'Produktuebersicht nach Kategorie', 'werbeauf-customs' ) . '">';

    // Einmalige Header-Zeile ueber dem Daten-Bereich. Spannt nur die
    // 4 sichtbaren Daten-Spalten + leere Bild-Spalte (5 cells), skippt
    // die 10px Cat-Bar links. Auf <= 980px ausgeblendet.
    echo '<div class="wa-at-a-glance__header" role="row">';
    echo '<div class="wa-at-a-glance__header-spacer" aria-hidden="true"></div>';
    echo '<div class="wa-at-a-glance__header-cells">';
    echo '<div class="wa-at-a-glance__header-cell wa-at-a-glance__header-cell--image" aria-hidden="true"></div>';
    echo '<div class="wa-at-a-glance__header-cell" role="columnheader">' . esc_html__( 'Produkt', 'werbeauf-customs' ) . '</div>';
    echo '<div class="wa-at-a-glance__header-cell" role="columnheader">' . esc_html__( 'Preis', 'werbeauf-customs' ) . '</div>';
    echo '<div class="wa-at-a-glance__header-cell" role="columnheader">' . esc_html__( 'Fakten', 'werbeauf-customs' ) . '</div>';
    echo '<div class="wa-at-a-glance__header-cell" role="columnheader">' . esc_html__( 'Wichtigste Punkte', 'werbeauf-customs' ) . '</div>';
    echo '</div>';
    echo '</div>';

    foreach ( $groups as $slug => $group ) {
        $term = $group['term'];
        $bg   = (string) get_term_meta( $term->term_id, 'wa_bg_color', true );
        $fg   = (string) get_term_meta( $term->term_id, 'wa_fg_color', true );

        $style_parts = array();
        if ( '' !== $bg ) {
            $style_parts[] = '--wa-cat-bg:' . $bg;
        }
        if ( '' !== $fg ) {
            $style_parts[] = '--wa-cat-fg:' . $fg;
        }
        $style_attr = empty( $style_parts ) ? '' : ' style="' . esc_attr( implode( ';', $style_parts ) ) . '"';

        echo '<div class="wa-at-a-glance__group" data-cat="' . esc_attr( $slug ) . '"' . $style_attr . '>';

        // 10px Cat-Bar links (visually-hidden Label fuer Screen-Reader,
        // wird auf <= 980px wieder als Banner sichtbar).
        echo '<div class="wa-at-a-glance__cat" role="rowheader">';
        echo '<span class="wa-at-a-glance__cat-label">' . esc_html( $term->name ) . '</span>';
        echo '</div>';

        echo '<div class="wa-at-a-glance__rows">';
        foreach ( $group['products'] as $post ) {
            $product = wc_get_product( $post );
            if ( ! $product || $product->get_status() !== 'publish' ) {
                continue;
            }
            $pid       = $product->get_id();
            $permalink = get_permalink( $pid );
            $name      = $product->get_name();
            $volume    = function_exists( 'wa_get_product_volume' ) ? wa_get_product_volume( $product ) : '';
            $clean     = function_exists( 'wa_get_product_clean_image' ) ? wa_get_product_clean_image( $pid, 'medium' ) : array();

            echo '<div class="wa-at-a-glance__row" role="row">';

            // Bild (eigene Spalte, quadratisch).
            echo '<div class="wa-at-a-glance__cell wa-at-a-glance__cell--image" role="cell">';
            if ( ! empty( $clean['url'] ) ) {
                echo '<a class="wa-at-a-glance__image" href="' . esc_url( $permalink ) . '" tabindex="-1" aria-hidden="true">';
                echo '<img src="' . esc_url( $clean['url'] ) . '" alt="' . esc_attr( '' !== $clean['alt'] ? $clean['alt'] : $name ) . '" loading="lazy" />';
                echo '</a>';
            } else {
                echo '<span class="wa-at-a-glance__image wa-at-a-glance__image--empty" aria-hidden="true"></span>';
            }
            echo '</div>';

            // Produkt-Titel + Kategorie-Chip.
            echo '<div class="wa-at-a-glance__cell wa-at-a-glance__cell--name" role="cell">';
            echo '<span class="wa-at-a-glance__row-label" aria-hidden="true">' . esc_html__( 'Produkt', 'werbeauf-customs' ) . '</span>';
            echo '<div class="wa-at-a-glance__name-stack">';
            echo '<a class="wa-at-a-glance__name" href="' . esc_url( $permalink ) . '">' . esc_html( $name ) . '</a>';
            echo '<span class="wa-at-a-glance__cat-tag">' . esc_html( $term->name ) . '</span>';
            echo '</div>';
            echo '</div>';

            // Preis + Volumen darunter in Klammern.
            echo '<div class="wa-at-a-glance__cell wa-at-a-glance__cell--price" role="cell">';
            echo '<span class="wa-at-a-glance__row-label" aria-hidden="true">' . esc_html__( 'Preis', 'werbeauf-customs' ) . '</span>';
            echo '<div class="wa-at-a-glance__price-stack">';
            $price_html = $product->get_price_html();
            if ( '' !== $price_html ) {
                echo '<span class="wa-at-a-glance__price">' . wp_kses_post( $price_html ) . '</span>';
            } else {
                echo '<span class="wa-at-a-glance__price">&mdash;</span>';
            }
            if ( '' !== $volume ) {
                echo '<span class="wa-at-a-glance__price-volume">(' . esc_html( $volume ) . ')</span>';
            }
            echo '</div>';
            echo '</div>';

            // Facts (Pill-Chips).
            echo '<div class="wa-at-a-glance__cell wa-at-a-glance__cell--facts" role="cell">';
            echo '<span class="wa-at-a-glance__section-label" aria-hidden="true">' . esc_html__( 'Fakten', 'werbeauf-customs' ) . '</span>';
            echo wa_render_at_a_glance_facts( $pid );
            echo '</div>';

            // Keypoints (Label/Value-Stack).
            echo '<div class="wa-at-a-glance__cell wa-at-a-glance__cell--keypoints" role="cell">';
            echo '<span class="wa-at-a-glance__section-label" aria-hidden="true">' . esc_html__( 'Wichtigste Punkte', 'werbeauf-customs' ) . '</span>';
            echo wa_render_at_a_glance_keypoints( $pid );
            echo '</div>';

            echo '</div>'; // .wa-at-a-glance__row
        }
        echo '</div>'; // .wa-at-a-glance__rows

        echo '</div>'; // .wa-at-a-glance__group
    }

    echo '</div>'; // .wa-at-a-glance

    return ob_get_clean();
}

/**
 * Rendert die Facts eines Produkts als kompakte Stack-Liste fuer
 * die Cell (Icon + Text pro Zeile, max 3 Items).
 */
function wa_render_at_a_glance_facts( $pid ) {
    if ( ! function_exists( 'get_field' ) || ! class_exists( 'Werbeauf_Single_Product_Renderer' ) ) {
        return '';
    }
    $facts = get_field( 'facts', $pid );
    if ( empty( $facts ) || ! is_array( $facts ) ) {
        return '';
    }
    $out = '<ul class="wa-at-a-glance__list" role="list">';
    foreach ( array_slice( $facts, 0, 3 ) as $fact ) {
        $icon_key = isset( $fact['icon'] ) ? (string) $fact['icon'] : '';
        $text     = isset( $fact['text'] ) ? trim( (string) $fact['text'] ) : '';
        if ( '' === $text ) {
            continue;
        }
        $out .= '<li class="wa-at-a-glance__list-item">';
        $svg  = '' === $icon_key ? '' : Werbeauf_Single_Product_Renderer::icon_svg( $icon_key, 'wa-at-a-glance__icon' );
        $out .= $svg;
        $out .= '<span>' . esc_html( $text ) . '</span>';
        $out .= '</li>';
    }
    $out .= '</ul>';
    return $out;
}

/**
 * Rendert die Keypoints eines Produkts als Label/Value-Stack fuer
 * die Cell (Icon + Label \n Value pro Zeile, max 5 Items).
 */
function wa_render_at_a_glance_keypoints( $pid ) {
    if ( ! function_exists( 'get_field' ) || ! class_exists( 'Werbeauf_Single_Product_Renderer' ) ) {
        return '';
    }
    $points = get_field( 'keypoints', $pid );
    if ( empty( $points ) || ! is_array( $points ) ) {
        return '';
    }
    $out = '<ul class="wa-at-a-glance__list" role="list">';
    foreach ( array_slice( $points, 0, 5 ) as $row ) {
        $icon_key = isset( $row['icon'] ) ? (string) $row['icon'] : '';
        $label    = isset( $row['label'] ) ? trim( (string) $row['label'] ) : '';
        $value    = isset( $row['value'] ) ? trim( (string) $row['value'] ) : '';
        if ( '' === $label && '' === wp_strip_all_tags( $value ) ) {
            continue;
        }
        $out .= '<li class="wa-at-a-glance__list-item">';
        $svg  = '' === $icon_key ? '' : Werbeauf_Single_Product_Renderer::icon_svg( $icon_key, 'wa-at-a-glance__icon' );
        $out .= $svg;
        $out .= '<span class="wa-at-a-glance__kp-text">';
        if ( '' !== $label ) {
            $out .= '<strong class="wa-at-a-glance__kp-label">' . esc_html( $label ) . '</strong>';
        }
        if ( '' !== $value ) {
            $out .= '<span class="wa-at-a-glance__kp-value">' . wp_kses_post( $value ) . '</span>';
        }
        $out .= '</span>';
        $out .= '</li>';
    }
    $out .= '</ul>';
    return $out;
}

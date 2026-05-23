<?php
/* ============================================================
   DATEI: admin/product-facts-column.php
   ZWECK: Custom Admin-Spalte "Facts" auf
          wp-admin/edit.php?post_type=product

   - Liest die ACF-Repeater-Group `wa_product_facts` (Icon + Text)
     und rendert pro Produkt bis zu 3 Eintraege als kompakte
     Icon+Text-Liste.
   - Spalte ist standardmaessig sichtbar und kann ueber
     "Ansicht anpassen" (Screen Options) je User ein-/ausgeblendet
     werden -- WordPress baut den Toggle automatisch fuer
     manage_edit-{posttype}_columns Filter.
   - Icons werden aus Werbeauf_Single_Product_Renderer::icon_svg()
     gerendert (Single-Source-of-Truth, siehe
     includes/woocommerce/single-product-renderer.php).
============================================================ */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Spalte registrieren -- direkt nach der "Name"-Spalte einsortiert.
 * WordPress bringt damit automatisch den Screen-Options-Toggle.
 */
add_filter( 'manage_edit-product_columns', 'wa_product_facts_column_register' );
function wa_product_facts_column_register( $columns ) {
    $new = array();
    foreach ( $columns as $key => $label ) {
        $new[ $key ] = $label;
        if ( 'name' === $key ) {
            $new['wa_facts'] = __( 'Facts', 'werbeauf-customs' );
        }
    }
    // Falls "name" fehlt (custom Filterketten), Spalte am Ende anhaengen.
    if ( ! isset( $new['wa_facts'] ) ) {
        $new['wa_facts'] = __( 'Facts', 'werbeauf-customs' );
    }
    return $new;
}

/**
 * Inhalte der Spalte rendern.
 */
add_action( 'manage_product_posts_custom_column', 'wa_product_facts_column_render', 10, 2 );
function wa_product_facts_column_render( $column, $post_id ) {
    if ( 'wa_facts' !== $column ) {
        return;
    }

    if ( ! function_exists( 'get_field' ) ) {
        echo '<span class="wa-admin-facts__empty" aria-hidden="true">&mdash;</span>';
        return;
    }

    $facts = get_field( 'facts', $post_id );
    if ( empty( $facts ) || ! is_array( $facts ) ) {
        echo '<span class="wa-admin-facts__empty" aria-hidden="true">&mdash;</span>';
        return;
    }

    $facts = array_slice( $facts, 0, 3 );

    $rendered = 0;
    ob_start();
    echo '<ul class="wa-admin-facts" role="list">';
    foreach ( $facts as $fact ) {
        $text = isset( $fact['text'] ) ? trim( (string) $fact['text'] ) : '';
        $icon = isset( $fact['icon'] ) ? (string) $fact['icon'] : 'check';
        if ( '' === $text ) {
            continue;
        }

        $svg = '';
        if ( class_exists( 'Werbeauf_Single_Product_Renderer' ) ) {
            $svg = Werbeauf_Single_Product_Renderer::icon_svg( $icon );
        }

        echo '<li class="wa-admin-facts__item">';
        if ( '' !== $svg ) {
            echo '<span class="wa-admin-facts__icon">' . $svg . '</span>';
        }
        echo '<span class="wa-admin-facts__text">' . esc_html( $text ) . '</span>';
        echo '</li>';
        $rendered++;
    }
    echo '</ul>';

    if ( $rendered > 0 ) {
        echo ob_get_clean();
    } else {
        ob_end_clean();
        echo '<span class="wa-admin-facts__empty" aria-hidden="true">&mdash;</span>';
    }
}

/**
 * Inline-CSS nur auf der Produkt-Liste.
 * Hook: admin_print_styles-edit.php feuert spezifisch fuer die
 * Listen-Ansicht; zusaetzlich Screen-Check fuer alle Edge-Cases.
 */
add_action( 'admin_print_styles-edit.php', 'wa_product_facts_column_styles' );
function wa_product_facts_column_styles() {
    $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
    if ( ! $screen || 'edit-product' !== $screen->id ) {
        return;
    }
    ?>
    <style>
        /* Kompakte Spalten-Optik fuer wp-admin/edit.php?post_type=product */
        .wp-list-table .column-wa_facts { width: 240px; }
        @media (max-width: 1200px) {
            .wp-list-table .column-wa_facts { width: 200px; }
        }
        @media (max-width: 782px) {
            .wp-list-table .column-wa_facts { width: auto; }
        }

        ul.wa-admin-facts {
            margin: 0;
            padding: 0;
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        ul.wa-admin-facts li.wa-admin-facts__item {
            margin: 0;
            padding: 0;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            line-height: 1.4;
            color: #5a6f85;
        }
        .wa-admin-facts__icon {
            flex-shrink: 0;
            width: 16px;
            height: 16px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #495f76;
        }
        .wa-admin-facts__icon svg {
            width: 16px;
            height: 16px;
            display: block;
        }
        .wa-admin-facts__text {
            flex: 1 1 auto;
            min-width: 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .wa-admin-facts__empty {
            color: #c5cdd9;
        }

        /* Mobile-Stack: WP nutzt unter 782px Card-Layout fuer Listen.
           Spalten-Header braucht Label, sonst zeigt WP nur den Wert. */
        @media (max-width: 782px) {
            .wp-list-table .wa_facts.column-wa_facts::before {
                content: '<?php echo esc_attr__( 'Facts', 'werbeauf-customs' ); ?>';
            }
        }
    </style>
    <?php
}

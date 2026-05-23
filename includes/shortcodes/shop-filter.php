<?php
/* ============================================================
   DATEI: includes/shop-filter.php
   ZWECK: [wa_shop_filter] Shortcode -> Pill-Bar mit
          dynamisch ausgelesenen WooCommerce-Kategorien.

   Beispiel-Nutzung im Page-Editor:
     [wa_shop_filter exclude="sets"]
     [products limit="12" columns="4" category="sets" cat_operator="NOT IN" orderby="menu_order" order="DESC"]

   Filtert die rechts darauf folgende ul.products clientseitig
   per JS (CSS-Klasse product_cat-{slug} wird von WooCommerce
   automatisch an jedes <li class="product"> gehaengt).

   Attribute:
     exclude   - kommagetrennte Slugs, die NICHT als Pill erscheinen
     include   - kommagetrennte Slugs, NUR diese werden gezeigt
     parent    - Slug einer Eltern-Kategorie -> nur direkte Kinder
     hide_empty- 'yes' (default) | 'no'
     all_label - Beschriftung des "Alle"-Pills (default: Alle)
     target    - optionaler CSS-Selector, falls die ul.products
                 nicht in derselben <section> liegt
============================================================ */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'wp_enqueue_scripts', 'wa_shop_filter_register_assets' );
function wa_shop_filter_register_assets() {
    wp_register_style(
        'wa-shop-filter',
        WERBEAUF_PLUGIN_URL . 'assets/css/20-components/shop-filter.css',
        array( 'wa-tokens' ),
        '1.0.0'
    );
    wp_register_script(
        'wa-shop-filter',
        WERBEAUF_PLUGIN_URL . 'assets/js/shop-filter.js',
        array(),
        '1.0.0',
        true
    );
}

add_shortcode( 'wa_shop_filter', 'wa_shop_filter_shortcode' );
function wa_shop_filter_shortcode( $atts ) {

    if ( ! taxonomy_exists( 'product_cat' ) ) {
        return '';
    }

    $atts = shortcode_atts( array(
        'exclude'    => '',
        'include'    => '',
        'parent'     => '',
        'hide_empty' => 'yes',
        'all_label'  => __( 'Alle', 'werbeauf-customs' ),
        'target'     => '',
    ), $atts, 'wa_shop_filter' );

    $args = array(
        'taxonomy'   => 'product_cat',
        'hide_empty' => filter_var( $atts['hide_empty'], FILTER_VALIDATE_BOOLEAN ),
        'orderby'    => 'menu_order',
        'order'      => 'ASC',
    );

    // Slug-Liste -> Term-IDs (filtert nicht-existente Slugs raus).
    // Nutzt den localized Helper -- DE-Slugs koennen auch auf der EN-Site
    // angegeben werden, das Mapping passiert ueber wpml_object_id.
    $slugs_to_ids = function ( $csv ) {
        $ids = array();
        foreach ( array_filter( array_map( 'trim', explode( ',', (string) $csv ) ) ) as $slug ) {
            $term = function_exists( 'wa_get_term_by_slug_localized' )
                ? wa_get_term_by_slug_localized( $slug, 'product_cat' )
                : get_term_by( 'slug', $slug, 'product_cat' );
            if ( $term && ! is_wp_error( $term ) ) {
                $ids[] = (int) $term->term_id;
            }
        }
        return $ids;
    };

    $excluded_ids = $slugs_to_ids( $atts['exclude'] );
    if ( ! empty( $excluded_ids ) ) {
        $args['exclude'] = $excluded_ids;
    }

    $included_ids = $slugs_to_ids( $atts['include'] );
    if ( ! empty( $included_ids ) ) {
        $args['include'] = $included_ids;
    }

    if ( ! empty( $atts['parent'] ) ) {
        $parent = function_exists( 'wa_get_term_by_slug_localized' )
            ? wa_get_term_by_slug_localized( $atts['parent'], 'product_cat' )
            : get_term_by( 'slug', $atts['parent'], 'product_cat' );
        if ( $parent && ! is_wp_error( $parent ) ) {
            $args['parent'] = (int) $parent->term_id;
        }
    }

    $terms = get_terms( $args );

    if ( is_wp_error( $terms ) || empty( $terms ) ) {
        return '';
    }

    wp_enqueue_style( 'wa-shop-filter' );
    wp_enqueue_script( 'wa-shop-filter' );

    $target_attr = '';
    if ( ! empty( $atts['target'] ) ) {
        $target_attr = ' data-target="' . esc_attr( $atts['target'] ) . '"';
    }

    ob_start();
    ?>
    <nav class="wa-shop-filter" role="tablist" aria-label="<?php echo esc_attr__( 'Kategorie-Filter', 'werbeauf-customs' ); ?>"<?php echo $target_attr; ?>>
        <button type="button" class="wa-shop-filter__pill is-active" data-cat="" role="tab" aria-selected="true">
            <?php echo esc_html( $atts['all_label'] ); ?>
        </button>
        <?php foreach ( $terms as $term ) : ?>
            <button type="button" class="wa-shop-filter__pill" data-cat="<?php echo esc_attr( $term->slug ); ?>" role="tab" aria-selected="false">
                <?php echo esc_html( $term->name ); ?>
            </button>
        <?php endforeach; ?>
    </nav>
    <?php
    return ob_get_clean();
}

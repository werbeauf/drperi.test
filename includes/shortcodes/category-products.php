<?php
/* ============================================================
   DATEI: includes/shortcodes/category-products.php
   ZWECK: [wa_category_products] -> Listet die Produkte der
          AKTUELL aufgerufenen WooCommerce-Kategorie.

   Erkennt den Kontext in dieser Reihenfolge:
     1. Attribut category="slug" (explizit, gewinnt immer)
     2. is_product_category() -> get_queried_object()->slug
     3. URL-Parameter ?product_cat=slug
     4. Fallback: leerer String -> alle Produkte (oder fallback="slug")

   Rendert:
     1. <h1> mit dem Kategorie-Namen (default an, abschaltbar)
     2. optional Term-Description
     3. [products category="..."] Grid
     4. <h3> "Andere Kategorien entdecken" + [product_categories]
        ohne die aktuelle Kategorie (default an, abschaltbar)

   Gedacht fuer Divi Theme Builder, Layouts auf
   "All Product Category Archive Pages" zugewiesen.

   Beispiel-Nutzung:
     [wa_category_products]
     [wa_category_products limit="16" columns="4"]
     [wa_category_products category="sets"]            (Override)
     [wa_category_products fallback="alle"]            (wenn Kontext fehlt)
     [wa_category_products show_browse_other="no"]     (Andere-Kategorien-Block aus)
============================================================ */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'wp_enqueue_scripts', 'wa_category_products_register_assets' );
function wa_category_products_register_assets() {
    if ( ! wp_style_is( 'wa-product-card', 'registered' ) ) {
        wp_register_style(
            'wa-product-card',
            WERBEAUF_PLUGIN_URL . 'assets/css/20-components/product-card.css',
            array( 'wa-tokens' ),
            '3.3.0'
        );
    }
    if ( ! wp_style_is( 'wa-category-card', 'registered' ) ) {
        wp_register_style(
            'wa-category-card',
            WERBEAUF_PLUGIN_URL . 'assets/css/20-components/category-card.css',
            array( 'wa-tokens', 'wa-product-card' ),
            '1.1.0'
        );
    }
    if ( ! wp_style_is( 'wa-shop-layout', 'registered' ) ) {
        wp_register_style(
            'wa-shop-layout',
            WERBEAUF_PLUGIN_URL . 'assets/css/40-blocks/shop-layout.css',
            array( 'wa-tokens' ),
            '1.1.0'
        );
    }
}

add_shortcode( 'wa_category_products', 'wa_category_products_shortcode' );

function wa_category_products_shortcode( $atts ) {

    if ( ! function_exists( 'is_woocommerce' ) ) {
        return '';
    }

    $defaults = array(
        'category'           => '',
        'fallback'           => '',
        'limit'              => '12',
        'columns'            => '4',
        'orderby'            => 'menu_order',
        'order'              => 'ASC',
        'paginate'           => 'yes',
        'on_sale'            => '',
        'best_selling'       => '',
        'show_title'         => 'yes',
        'show_description'   => 'yes',

        'show_browse_other'  => 'yes',
        'browse_other_title' => 'Andere Kategorien entdecken',
        'browse_columns'     => '5',
        'browse_parent'      => '0',
        'browse_hide_empty'  => 'yes',
    );
    $atts = shortcode_atts( $defaults, $atts, 'wa_category_products' );

    $is_yes = static function ( $v ) {
        return filter_var( $v, FILTER_VALIDATE_BOOLEAN ) || $v === 'yes';
    };

    wp_enqueue_style( 'wa-product-card' );
    wp_enqueue_style( 'wa-category-card' );
    wp_enqueue_style( 'wa-shop-layout' );

    /* ----------------------------------------------------------------
       1. Kategorie-Kontext aufloesen
    ---------------------------------------------------------------- */
    $term = null;
    $slug = sanitize_title( $atts['category'] );

    if ( $slug !== '' ) {
        $term = function_exists( 'wa_get_term_by_slug_localized' )
            ? wa_get_term_by_slug_localized( $slug, 'product_cat' )
            : get_term_by( 'slug', $slug, 'product_cat' );
    } elseif ( function_exists( 'is_product_category' ) && is_product_category() ) {
        $queried = get_queried_object();
        if ( $queried instanceof WP_Term && $queried->taxonomy === 'product_cat' ) {
            $term = $queried;
            $slug = $queried->slug;
        }
    } elseif ( ! empty( $_GET['product_cat'] ) ) {
        $slug = sanitize_title( wp_unslash( $_GET['product_cat'] ) );
        $term = function_exists( 'wa_get_term_by_slug_localized' )
            ? wa_get_term_by_slug_localized( $slug, 'product_cat' )
            : get_term_by( 'slug', $slug, 'product_cat' );
    }

    if ( ! $term && $atts['fallback'] !== '' ) {
        $slug = sanitize_title( $atts['fallback'] );
        $term = function_exists( 'wa_get_term_by_slug_localized' )
            ? wa_get_term_by_slug_localized( $slug, 'product_cat' )
            : get_term_by( 'slug', $slug, 'product_cat' );
    }

    /* ----------------------------------------------------------------
       2. [products ...]-Attribute zusammenbauen
    ---------------------------------------------------------------- */
    $limit    = max( 1, (int) $atts['limit'] );
    $columns  = max( 1, (int) $atts['columns'] );
    $orderby  = sanitize_key( $atts['orderby'] );
    $order    = strtoupper( $atts['order'] ) === 'DESC' ? 'DESC' : 'ASC';
    $paginate = $is_yes( $atts['paginate'] ) ? 'true' : 'false';

    $products_atts = sprintf(
        'limit="%d" columns="%d" orderby="%s" order="%s" paginate="%s"',
        $limit,
        $columns,
        esc_attr( $orderby ),
        esc_attr( $order ),
        esc_attr( $paginate )
    );

    if ( $slug !== '' ) {
        $products_atts .= sprintf( ' category="%s"', esc_attr( $slug ) );
    }
    if ( $is_yes( $atts['on_sale'] ) ) {
        $products_atts .= ' on_sale="true"';
    }
    if ( $is_yes( $atts['best_selling'] ) ) {
        $products_atts .= ' best_selling="true"';
    }

    /* ----------------------------------------------------------------
       3. "Andere Kategorien"-Block: alle product_cat Terms holen,
          aktuelle Kategorie ausblenden, IDs an [product_categories]
          uebergeben (WC akzeptiert kein "exclude", aber "ids").
    ---------------------------------------------------------------- */
    $browse_ids   = array();
    $browse_html  = '';
    $browse_cols  = max( 1, (int) $atts['browse_columns'] );
    $browse_empty = $is_yes( $atts['browse_hide_empty'] );

    if ( $is_yes( $atts['show_browse_other'] ) ) {
        $term_args = array(
            'taxonomy'   => 'product_cat',
            'hide_empty' => $browse_empty,
        );
        // Nur direkte Kinder eines Parents (browse_parent="slug") oder Top-Level (default 0).
        if ( $atts['browse_parent'] !== '' ) {
            if ( ctype_digit( (string) $atts['browse_parent'] ) ) {
                $term_args['parent'] = (int) $atts['browse_parent'];
            } else {
                $parent_slug = sanitize_title( $atts['browse_parent'] );
                $parent_term = function_exists( 'wa_get_term_by_slug_localized' )
                    ? wa_get_term_by_slug_localized( $parent_slug, 'product_cat' )
                    : get_term_by( 'slug', $parent_slug, 'product_cat' );
                if ( $parent_term ) {
                    $term_args['parent'] = (int) $parent_term->term_id;
                }
            }
        }

        $browse_terms = get_terms( $term_args );
        if ( ! is_wp_error( $browse_terms ) && $browse_terms ) {
            foreach ( $browse_terms as $bt ) {
                if ( $term && (int) $bt->term_id === (int) $term->term_id ) {
                    continue; // aktuelle Kategorie raus
                }
                $browse_ids[] = (int) $bt->term_id;
            }
        }

        if ( $browse_ids ) {
            $browse_html = do_shortcode( sprintf(
                '[product_categories columns="%d" hide_empty="%d" ids="%s"]',
                $browse_cols,
                $browse_empty ? 1 : 0,
                esc_attr( implode( ',', $browse_ids ) )
            ) );
        }
    }

    /* ----------------------------------------------------------------
       4. Render
    ---------------------------------------------------------------- */
    ob_start();
    ?>
    <div class="wa-category-products" data-category="<?php echo esc_attr( $slug ); ?>">

        <?php if ( $term && $is_yes( $atts['show_title'] ) ) : ?>
            <h1 class="wa-category-products__title">
                <?php echo esc_html( $term->name ); ?>
            </h1>
        <?php endif; ?>

        <?php
        if ( $term && $is_yes( $atts['show_description'] ) ) {
            $description = term_description( $term->term_id, 'product_cat' );
            if ( $description ) {
                echo '<div class="wa-category-products__description">'
                    . wp_kses_post( $description )
                    . '</div>';
            }
        }
        ?>

        <?php echo do_shortcode( '[products ' . $products_atts . ']' ); ?>

        <?php if ( $browse_html !== '' ) : ?>
            <section class="wa-category-products__browse">
                <h3 class="shop-categories-title">
                    <?php echo esc_html( $atts['browse_other_title'] ); ?>
                </h3>
                <?php echo $browse_html; // bereits via do_shortcode escaped ?>
            </section>
        <?php endif; ?>

    </div>
    <?php
    return ob_get_clean();
}

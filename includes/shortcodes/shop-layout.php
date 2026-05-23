<?php
/* ============================================================
   DATEI: includes/shortcodes/shop-layout.php
   ZWECK: [wa_shop_layout] Shortcode -> Komplettes Shop-Layout
          (Intro + Featured Sets Hero + Hauptkatalog mit Filter
           + Kategorien am Ende).

   Backcompat: [drperi_shop_layout] ist als Alias registriert,
   damit existierende Pages nicht brechen. Neuer Name in der
   Doku ist [wa_shop_layout].

   Beispiel-Nutzung im Page-Editor / Divi Code Module:
     [wa_shop_layout]
     [wa_shop_layout featured_category="sets" all_limit="16"]
     [wa_shop_layout show_featured="no" show_categories="no"]

   Reihenfolge: Intro -> Featured Sets Hero -> Hauptkatalog mit Filter
                -> Kategorien (am Ende).

   Attribute (alle optional):
     intro_title          H1 ueber dem Shop
                          (default: "Shop D. Peri Skincare")
     intro_text           Lead-Absatz unter der H1
                          (default: lange DE-Version)

     show_featured        "yes" | "no" (default: yes)
     featured_title       (default: "Unsere Top-Sets")
     featured_text        (default: "Sorgfaeltig kombinierte ...")
     featured_category    Slug der Featured-Kategorie und gleichzeitig
                          die Kategorie, die im Hauptkatalog AUSGE-
                          SCHLOSSEN wird (default: "sets")
     featured_limit       (default: "3")
     featured_columns     (default: "3")

     all_title            (default: "Alle Produkte")
     all_limit            (default: "12")
     all_columns          (default: "4")

     show_categories      "yes" | "no" (default: yes) -- Block am Ende
     categories_title     (default: "Kategorien entdecken")
     categories_columns   (default: "5")
============================================================ */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'wp_enqueue_scripts', 'wa_shop_layout_register_assets' );
function wa_shop_layout_register_assets() {
    wp_register_style(
        'wa-shop-layout',
        WERBEAUF_PLUGIN_URL . 'assets/css/40-blocks/shop-layout.css',
        array( 'wa-tokens' ),
        '1.1.0'
    );
}

add_shortcode( 'wa_shop_layout', 'wa_shop_layout_shortcode' );
add_shortcode( 'drperi_shop_layout', 'wa_shop_layout_shortcode' );

function wa_shop_layout_shortcode( $atts ) {

    // Ohne WooCommerce keine [products]/[product_categories] -> abbrechen.
    if ( ! function_exists( 'is_woocommerce' ) ) {
        return '';
    }

    $defaults = array(
        'intro_title'        => __( 'Shop Dr. Peri Skincare', 'werbeauf-customs' ),
        'intro_text'         => __( 'Erleben Sie die Dr. Peri Skincare Linie – hochwertige Pflegeprodukte für eine rundum gesunde, ausgeglichene Haut. Unsere präzise entwickelten Formulierungen reinigen sanft, pflegen intensiv und beugen Hautalterung und Hautunreinheiten gezielt vor. Für ein sichtbar reines, harmonisches Hautbild – Tag für Tag.', 'werbeauf-customs' ),
        'show_featured'      => 'yes',
        'featured_title'     => __( 'Unsere Top-Sets', 'werbeauf-customs' ),
        'featured_text'      => __( 'Sorgfältig kombinierte Pflegesets für deine Hauttypen-Routine.', 'werbeauf-customs' ),
        'featured_category'  => 'sets',
        'featured_limit'     => '3',
        'featured_columns'   => '3',
        'all_title'          => __( 'Alle Produkte', 'werbeauf-customs' ),
        'all_limit'          => '12',
        'all_columns'        => '4',
        'show_categories'    => 'yes',
        'categories_title'   => __( 'Kategorien entdecken', 'werbeauf-customs' ),
        'categories_columns' => '5',
    );
    $atts = shortcode_atts( $defaults, $atts, 'wa_shop_layout' );

    // Eigenes Stylesheet + die zwei Card-Grids enqueuen.
    // (wa-shop-filter wird vom inneren [wa_shop_filter] selbst geladen.)
    // Card-Grids ggf. selbst registrieren, falls Shortcode auf einer Seite
    // laeuft, die nicht von wa_should_load_products_grid() erfasst wird.
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
    wp_enqueue_style( 'wa-product-card' );
    wp_enqueue_style( 'wa-category-card' );
    wp_enqueue_style( 'wa-shop-layout' );

    $is_yes = function ( $v ) {
        return filter_var( $v, FILTER_VALIDATE_BOOLEAN ) || $v === 'yes';
    };

    $cat_cols  = max( 1, (int) $atts['categories_columns'] );
    $feat_lim  = max( 1, (int) $atts['featured_limit'] );
    $feat_cols = max( 1, (int) $atts['featured_columns'] );
    $feat_cat  = sanitize_title( $atts['featured_category'] );
    $all_lim   = max( 1, (int) $atts['all_limit'] );
    $all_cols  = max( 1, (int) $atts['all_columns'] );

    ob_start();
    ?>
    <h1 class="shop-intro-title"><?php echo esc_html( $atts['intro_title'] ); ?></h1>
    <p class="shop-intro-text"><?php echo esc_html( $atts['intro_text'] ); ?></p>

    <?php if ( $is_yes( $atts['show_featured'] ) && $feat_cat !== '' ) : ?>
        <section class="featured-sets-hero">
            <h3 class="featured-title"><?php echo esc_html( $atts['featured_title'] ); ?></h3>
            <p class="featured-sub"><?php echo esc_html( $atts['featured_text'] ); ?></p>
            <?php
            echo do_shortcode( sprintf(
                '[products limit="%d" columns="%d" category="%s" orderby="menu_order" order="ASC"]',
                $feat_lim,
                $feat_cols,
                esc_attr( $feat_cat )
            ) );
            ?>
        </section>
    <?php endif; ?>

    <h3 class="all-products-title"><?php echo esc_html( $atts['all_title'] ); ?></h3>

    <?php
    if ( shortcode_exists( 'wa_shop_filter' ) && $feat_cat !== '' ) {
        echo do_shortcode( sprintf( '[wa_shop_filter exclude="%s"]', esc_attr( $feat_cat ) ) );
    } elseif ( shortcode_exists( 'wa_shop_filter' ) ) {
        echo do_shortcode( '[wa_shop_filter]' );
    }

    if ( $feat_cat !== '' ) {
        echo do_shortcode( sprintf(
            '[products limit="%d" columns="%d" category="%s" cat_operator="NOT IN" orderby="menu_order" order="DESC"]',
            $all_lim,
            $all_cols,
            esc_attr( $feat_cat )
        ) );
    } else {
        echo do_shortcode( sprintf(
            '[products limit="%d" columns="%d" orderby="menu_order" order="DESC"]',
            $all_lim,
            $all_cols
        ) );
    }
    ?>

    <?php if ( $is_yes( $atts['show_categories'] ) ) : ?>
        <h3 class="shop-categories-title"><?php echo esc_html( $atts['categories_title'] ); ?></h3>
        <?php echo do_shortcode( sprintf( '[product_categories columns="%d"]', $cat_cols ) ); ?>
    <?php endif; ?>
    <?php
    return ob_get_clean();
}

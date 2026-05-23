<?php
/* ============================================================
   DATEI: includes/woocommerce/single-product-renderer.php
   Custom Single-Product-Layout fuer Dr. Peri
   Modernes Hero-Layout, Montserrat, Brand-Palette
============================================================ */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'Werbeauf_Single_Product_Renderer' ) ) :

class Werbeauf_Single_Product_Renderer {

    public function __construct() {
        add_action( 'wp', array( $this, 'init_renderer' ) );
    }

    public function init_renderer() {
        if ( is_admin() || ! function_exists( 'is_product' ) || ! is_product() ) {
            return;
        }

        // Breadcrumb aus dem Default-Container entfernen — wird intern im Wrapper gerendert
        remove_action( 'woocommerce_before_main_content', 'woocommerce_breadcrumb', 20 );

        // Default-Hooks abraeumen
        remove_action( 'woocommerce_before_single_product_summary', 'woocommerce_show_product_images', 20 );
        remove_action( 'woocommerce_before_single_product_summary', 'woocommerce_show_product_sale_flash', 10 );

        remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_title', 5 );
        remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_rating', 10 );
        remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_price', 10 );
        remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_excerpt', 20 );
        remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30 );
        remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_meta', 40 );
        remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_sharing', 50 );

        // Custom Layout aufbauen
        add_action( 'woocommerce_before_single_product_summary', array( $this, 'open_grid' ), 1 );
        add_action( 'woocommerce_before_single_product_summary', array( $this, 'open_gallery_col' ), 5 );
        add_action( 'woocommerce_before_single_product_summary', array( $this, 'render_sale_flash' ), 8 );
        add_action( 'woocommerce_before_single_product_summary', 'woocommerce_show_product_images', 20 );
        add_action( 'woocommerce_before_single_product_summary', array( $this, 'render_product_facts' ), 22 );
        add_action( 'woocommerce_before_single_product_summary', array( $this, 'close_gallery_col' ), 25 );

        // Summary-Inhalte (rechte Spalte)
        add_action( 'woocommerce_single_product_summary', array( $this, 'render_category_label' ), 3 );
        add_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_title', 5 );
        add_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_rating', 8 );
        add_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_excerpt', 12 );
        // Direkt nach Short Description: dezenter "Produktbeschreibung lesen"-Link
        // (nur wenn Long Description vorhanden). Springt zu #wa-panel-description.
        add_action( 'woocommerce_single_product_summary', array( $this, 'render_short_description_more_link' ), 12 );
        add_action( 'woocommerce_single_product_summary', array( $this, 'render_key_points' ), 13 );
        add_action( 'woocommerce_single_product_summary', array( $this, 'render_divider' ), 14 );
        add_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_price', 16 );
        add_action( 'woocommerce_single_product_summary', array( $this, 'render_features' ), 22 );

        // Action-Block: form.cart + trust + meta visuell separiert
        // (eigener Container am Boden der Summary-Card).
        add_action( 'woocommerce_single_product_summary', array( $this, 'open_action_block' ), 28 );
        add_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30 );
        add_action( 'woocommerce_single_product_summary', array( $this, 'render_trust_row' ), 34 );
        add_action( 'woocommerce_single_product_summary', array( $this, 'render_meta_compact' ), 40 );
        add_action( 'woocommerce_single_product_summary', array( $this, 'close_action_block' ), 41 );

        // Grid schliessen
        add_action( 'woocommerce_after_single_product_summary', array( $this, 'close_grid' ), 1 );

        // WC-Tabs durch Detail-Block (Sticky-Tab-Card) ersetzen.
        // Reihenfolge der Story unterhalb des Hero:
        //   Detail-Block (10) -> Reviews (15) -> FAQ (20) -> Related (25)
        remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_output_product_data_tabs', 10 );
        add_action( 'woocommerce_after_single_product_summary', array( $this, 'render_detail_block' ), 10 );
        add_action( 'woocommerce_after_single_product_summary', array( $this, 'render_reviews_section' ), 15 );

        // FAQ-Card bleibt unveraendert (eigene Section mit "Haeufige Fragen").
        add_action( 'woocommerce_after_single_product_summary', array( $this, 'render_faq' ), 20 );
        add_action( 'wp_head', array( $this, 'output_faq_schema' ), 50 );

        // Related Products: Default-Hook entfernen, eigene Variante mit Fallback
        remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_output_related_products', 20 );
        add_action( 'woocommerce_after_single_product_summary', array( $this, 'render_related_products' ), 25 );

        // Body-Klasse + Wrapper-Container
        add_filter( 'body_class', array( $this, 'add_body_class' ) );
        add_action( 'woocommerce_before_single_product', array( $this, 'open_wrapper' ), 5 );
        add_action( 'woocommerce_after_single_product', array( $this, 'close_wrapper' ), 5 );
    }

    public function add_body_class( $classes ) {
        $classes[] = 'wa-single-product';
        return $classes;
    }

    public function open_wrapper() {
        echo '<div class="wa-product-page">';
        if ( function_exists( 'woocommerce_breadcrumb' ) ) {
            woocommerce_breadcrumb();
        }
    }

    public function close_wrapper() {
        echo '</div>'; // .wa-product-page
    }

    public function open_grid() {
        echo '<div class="wa-product-hero"><div class="wa-product-hero__inner">';
    }

    public function close_grid() {
        echo '</div></div>'; // .wa-product-hero__inner / .wa-product-hero
    }

    public function open_gallery_col() {
        echo '<div class="wa-product-hero__gallery">';
    }

    public function close_gallery_col() {
        echo '</div>';
    }

    /**
     * Action-Block: visueller Wrapper um form.cart + Trust-Row +
     * Meta-Block. Sitzt am Boden der Summary-Card mit Negative-
     * Margins + softerer Background-Farbe (--wa-bg) -- damit
     * Lese-Inhalt (Eyebrow/Titel/Excerpt/Keypoints/Price/Features)
     * klar von der Aktions-/Konversions-Zone abgegrenzt ist.
     */
    public function open_action_block() {
        echo '<div class="wa-product-action">';
    }

    public function close_action_block() {
        echo '</div>';
    }

    public function render_sale_flash() {
        global $product;
        if ( ! $product ) {
            return;
        }
        if ( $product->is_on_sale() ) {
            echo '<span class="wa-sale-flash">' . esc_html__( 'Sale', 'werbeauf-customs' ) . '</span>';
        }
    }

    public function render_category_label() {
        global $product;
        if ( ! $product ) {
            return;
        }
        $terms = wc_get_product_category_list( $product->get_id(), ' &middot; ' );
        if ( $terms ) {
            echo '<div class="wa-product-eyebrow">' . wp_kses_post( $terms ) . '</div>';
        }
    }

    public function render_divider() {
        echo '<hr class="wa-divider" aria-hidden="true">';
    }

    /**
     * Dezenter "Produktbeschreibung lesen"-Link direkt unter der
     * Short Description. Wird nur ausgegeben, wenn das Produkt eine
     * (nicht leere) Long Description hat -- sonst gibt es im Detail-
     * Block ohnehin keine "description"-Tab.
     *
     * Ziel: #wa-panel-description (siehe render_detail_block()).
     * Smooth-Scroll + Tab-Aktivierung uebernimmt detail-block.js.
     */
    public function render_short_description_more_link() {
        global $product;
        if ( ! $product ) {
            return;
        }

        $description = $product->get_description();
        if ( '' === trim( wp_strip_all_tags( $description ) ) ) {
            return;
        }

        echo '<a href="#wa-panel-description" class="wa-readmore-link" data-wa-readmore="description">'
            . '<span class="wa-readmore-link__text">' . esc_html__( 'Produktbeschreibung lesen', 'werbeauf-customs' ) . '</span>'
            . '<svg class="wa-readmore-link__arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
            . '<path d="M5 12h14"/><path d="M13 6l6 6-6 6"/>'
            . '</svg>'
            . '</a>';
    }

    public function render_features() {
        global $product;
        if ( ! $product ) {
            return;
        }

        $features = array();
        $tag_names = wp_list_pluck( get_the_terms( $product->get_id(), 'product_tag' ) ?: array(), 'name' );
        if ( $tag_names ) {
            $features = array_slice( $tag_names, 0, 4 );
        }

        if ( ! $features ) {
            return;
        }

        echo '<ul class="wa-product-features" role="list">';
        foreach ( $features as $f ) {
            echo '<li class="wa-product-features__pill">' . esc_html( $f ) . '</li>';
        }
        echo '</ul>';
    }

    /**
     * Facts unter dem Produktbild (1-3 Eintraege, Icon + Text).
     * Quelle: ACF Group `wa_product_facts` (siehe includes/acf/single-product-fields.php).
     */
    public function render_product_facts() {
        global $product;
        if ( ! $product || ! function_exists( 'get_field' ) ) {
            return;
        }

        $facts = get_field( 'facts', $product->get_id() );
        if ( empty( $facts ) || ! is_array( $facts ) ) {
            return;
        }

        // Defensiv: max 3 Eintraege erzwingen, falls jemand das ACF-Limit umgeht.
        $facts = array_slice( $facts, 0, 3 );

        // Vorab leere Eintraege filtern, damit die count-Modifier-Klasse stimmt.
        $valid = array();
        foreach ( $facts as $fact ) {
            $text = isset( $fact['text'] ) ? trim( (string) $fact['text'] ) : '';
            if ( '' === $text ) {
                continue;
            }
            $valid[] = array(
                'icon' => isset( $fact['icon'] ) ? (string) $fact['icon'] : 'check',
                'text' => $text,
            );
        }
        if ( empty( $valid ) ) {
            return;
        }

        $count = count( $valid );
        $count_class = 'wa-product-facts--count-' . $count;

        echo '<ul class="wa-product-facts ' . esc_attr( $count_class ) . '" role="list">';
        foreach ( $valid as $fact ) {
            echo '<li class="wa-product-facts__item">';
            echo $this->icon( $fact['icon'] );
            echo '<span class="wa-product-facts__text">' . esc_html( $fact['text'] ) . '</span>';
            echo '</li>';
        }
        echo '</ul>';
    }

    /**
     * Key-Points zwischen Short Description und Divider.
     * Quelle: ACF Group `wa_product_keypoints`.
     */
    public function render_key_points() {
        global $product;
        if ( ! $product || ! function_exists( 'get_field' ) ) {
            return;
        }

        $points = get_field( 'keypoints', $product->get_id() );
        if ( empty( $points ) || ! is_array( $points ) ) {
            return;
        }

        $points = array_slice( $points, 0, 5 );

        $rendered = 0;
        ob_start();
        echo '<dl class="wa-product-keypoints">';
        foreach ( $points as $row ) {
            $label = isset( $row['label'] ) ? trim( (string) $row['label'] ) : '';
            $value = isset( $row['value'] ) ? (string) $row['value'] : '';
            if ( '' === $label && '' === trim( wp_strip_all_tags( $value ) ) ) {
                continue;
            }
            echo '<div class="wa-product-keypoints__row">';
            $label_html = '<dt class="wa-product-keypoints__label">' . esc_html( $label ) . '</dt>';
            echo apply_filters( 'wa_keypoint_label_html', $label_html, $row, $product );
            echo '<dd class="wa-product-keypoints__value">' . wp_kses_post( $value ) . '</dd>';
            echo '</div>';
            $rendered++;
        }
        echo '</dl>';
        $html = ob_get_clean();

        if ( $rendered > 0 ) {
            echo $html;
        }
    }

    /**
     * FAQ-Section am Seitenende.
     *
     * Markup-Hinweise:
     *  - Title ist fix "Haeufige Fragen" (keine ACF-Steuerung mehr).
     *    Der ACF-Field bleibt aus Gruenden der Datenkompatibilitaet
     *    bestehen, wird aber bewusst ignoriert.
     *  - Kein .wa-accordion-Wrapper: <details>-Items werden direkt
     *    in die .wa-product-faq Card eingelassen (single-Card-Optik).
     *  - Alle Items starten geschlossen (kein open-Attribut).
     *
     * Quelle: ACF Group `wa_product_faq`.
     */
    public function render_faq() {
        global $product;
        if ( ! $product || ! function_exists( 'get_field' ) ) {
            return;
        }

        $items = get_field( 'faq_items', $product->get_id() );
        if ( empty( $items ) || ! is_array( $items ) ) {
            return;
        }

        // Sub-Items vorab filtern -- wenn keiner ein Frage/Antwort-Paar liefert,
        // gar nichts rendern (kein leeres <section>).
        $valid = array();
        foreach ( $items as $row ) {
            $q = isset( $row['question'] ) ? trim( (string) $row['question'] ) : '';
            $a = isset( $row['answer'] )   ? (string) $row['answer']           : '';
            if ( '' === $q || '' === trim( wp_strip_all_tags( $a ) ) ) {
                continue;
            }
            $valid[] = array( 'q' => $q, 'a' => $a );
        }
        if ( empty( $valid ) ) {
            return;
        }

        $custom_headline = trim( (string) get_field( 'faq_headline', $product->get_id() ) );
        $headline        = '' !== $custom_headline
            ? $custom_headline
            : __( 'Häufig gestellte Fragen', 'werbeauf-customs' );
        $description     = trim( (string) get_field( 'faq_description', $product->get_id() ) );

        echo '<section class="wa-product-faq" aria-labelledby="wa-product-faq-title">';
        echo '<header class="wa-product-faq__head">';
        echo '<h2 id="wa-product-faq-title" class="wa-section__heading wa-product-faq__title">' . esc_html( $headline ) . '</h2>';
        if ( '' !== $description ) {
            echo '<p class="wa-product-faq__description">' . wp_kses_post( $description ) . '</p>';
        }
        echo '</header>';

        // Items sind direkte Kinder von .wa-product-faq -- kein
        // zusaetzlicher .wa-accordion-Wrapper. CSS zieht die Card-
        // Optik weg und setzt nur dezente Innen-Trennlinien.
        foreach ( $valid as $row ) {
            echo '<details class="wa-accordion__item" name="wa-product-faq">';
            echo '<summary class="wa-accordion__head">';
            echo '<span class="wa-accordion__title">' . esc_html( $row['q'] ) . '</span>';
            echo $this->accordion_icon_svg();
            echo '</summary>';
            echo '<div class="wa-accordion__body">';
            // Antwort wurde mit new_lines=br gespeichert -> wp_kses_post erlaubt <br>.
            echo wp_kses_post( wpautop( $row['a'] ) );
            echo '</div></details>';
        }

        echo '</section>';
    }

    /**
     * JSON-LD FAQPage-Schema im <head>. Wird nur ausgegeben,
     * wenn auf einer Single-Product-Seite UND das Produkt
     * gueltige FAQ-Items hat. Antwort-Text wird auf reines
     * Plain Text reduziert (Google verlangt das fuer Rich Results).
     */
    public function output_faq_schema() {
        if ( ! function_exists( 'is_product' ) || ! is_product() || ! function_exists( 'get_field' ) ) {
            return;
        }

        $product_id = get_queried_object_id();
        if ( ! $product_id ) {
            return;
        }

        $items = get_field( 'faq_items', $product_id );
        if ( empty( $items ) || ! is_array( $items ) ) {
            return;
        }

        $main_entity = array();
        foreach ( $items as $row ) {
            $q = isset( $row['question'] ) ? trim( (string) $row['question'] ) : '';
            $a = isset( $row['answer'] )   ? (string) $row['answer']           : '';
            if ( '' === $q || '' === trim( wp_strip_all_tags( $a ) ) ) {
                continue;
            }

            // <br>/Block-Tags zu Whitespace -> dann strippen, damit der
            // JSON-LD-String sauber lesbar bleibt.
            $a_plain = preg_replace( '#<br\s*/?>#i', "\n", $a );
            $a_plain = wp_strip_all_tags( $a_plain );
            $a_plain = trim( preg_replace( "/\s+\n/", "\n", $a_plain ) );

            $main_entity[] = array(
                '@type'          => 'Question',
                'name'           => $q,
                'acceptedAnswer' => array(
                    '@type' => 'Answer',
                    'text'  => $a_plain,
                ),
            );
        }

        if ( empty( $main_entity ) ) {
            return;
        }

        $schema = array(
            '@context'   => 'https://schema.org',
            '@type'      => 'FAQPage',
            'inLanguage' => function_exists( 'wa_wpml_current_lang' ) ? wa_wpml_current_lang() : 'de',
            'mainEntity' => $main_entity,
        );

        echo "\n<script type=\"application/ld+json\">"
            . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
            . "</script>\n";
    }

    public function render_trust_row() {
        $items = $this->get_trust_items();
        if ( empty( $items ) ) {
            return;
        }

        // Vorab leere Eintraege filtern, damit die count-Modifier-Klasse
        // genau die *gerenderten* Items spiegelt.
        $valid = array();
        foreach ( $items as $i ) {
            $icon = isset( $i['icon'] ) ? (string) $i['icon'] : 'check';
            $text = isset( $i['text'] ) ? (string) $i['text'] : ( isset( $i['label'] ) ? (string) $i['label'] : '' );
            $text = trim( $text );
            if ( '' === $text ) {
                continue;
            }
            $valid[] = array(
                'icon' => $icon,
                'text' => $text,
            );
        }
        if ( empty( $valid ) ) {
            return;
        }

        // Modifier-Klasse parallel zu .wa-product-facts.
        // 1/2/3 = exakte Spaltenzahl. 4+ = 2x2-Grid (lesbarer als
        // ein 4-spaltiger Mikro-Row).
        $count       = count( $valid );
        $count_class = 'wa-product-trust--count-' . min( $count, 4 );

        echo '<ul class="wa-product-trust ' . esc_attr( $count_class ) . '" role="list">';
        foreach ( $valid as $row ) {
            echo '<li class="wa-product-trust__item">';
            echo $this->icon( $row['icon'] );
            echo '<span class="wa-product-trust__text">' . esc_html( $row['text'] ) . '</span>';
            echo '</li>';
        }
        echo '</ul>';
    }

    private function get_trust_items() {
        if ( ! function_exists( 'wa_get_options_field' ) ) {
            return array();
        }
        $items = wa_get_options_field( 'single_product', 'trust_items', array() );
        return is_array( $items ) ? $items : array();
    }

    public function render_meta_compact() {
        global $product;
        if ( ! $product ) {
            return;
        }

        $cats = wc_get_product_category_list( $product->get_id(), ', ' );
        if ( ! $cats ) {
            return;
        }

        echo '<div class="wa-product-meta">';
        echo '<div class="wa-product-meta__row"><span>' . esc_html__( 'Kategorie', 'werbeauf-customs' ) . '</span><strong>' . wp_kses_post( $cats ) . '</strong></div>';
        echo '</div>';
    }

    public function render_related_products() {
        global $product;
        if ( ! $product ) {
            return;
        }

        $current_id = $product->get_id();
        $limit      = 3;
        $columns    = 3;

        $related_ids = function_exists( 'wc_get_related_products' )
            ? wc_get_related_products( $current_id, $limit )
            : array();

        // Fallback: neueste veroeffentlichte Produkte
        if ( empty( $related_ids ) ) {
            $recent = wc_get_products( array(
                'limit'   => $limit,
                'exclude' => array( $current_id ),
                'orderby' => 'date',
                'order'   => 'DESC',
                'status'  => 'publish',
                'return'  => 'ids',
            ) );
            $related_ids = $recent ? $recent : array();
        }

        if ( empty( $related_ids ) ) {
            return;
        }

        $related_ids = array_slice( array_values( $related_ids ), 0, $limit );

        $query = new WP_Query( array(
            'post_type'           => 'product',
            'posts_per_page'      => $limit,
            'post__in'            => $related_ids,
            'orderby'             => 'post__in',
            'ignore_sticky_posts' => 1,
            'no_found_rows'       => true,
            'post_status'         => 'publish',
        ) );

        if ( ! $query->have_posts() ) {
            wp_reset_postdata();
            return;
        }

        // Section-Wrapper braucht die `woocommerce`-Klasse, damit die
        // body-prefixed Card-Selektoren in 20-components/product-card.css
        // (z.B. `body .woocommerce ul.products[class*="columns-"] li.product`)
        // matchen. Ohne diesen Wrapper greift nur die Token-Scope, das
        // Grid-Layout/Card-Design faellt auf Divi-Defaults zurueck und
        // sieht nicht mehr aus wie ein normales [products].
        echo '<section class="related products woocommerce wa-related" aria-labelledby="wa-related-title">';
        echo '<h2 id="wa-related-title" class="wa-section__heading">' . esc_html__( 'Ähnliche Produkte', 'werbeauf-customs' ) . '</h2>';

        // Loop-Props vorbereiten:
        //   - is_shortcode = true sorgt dafuer, dass `wc_get_loop_class()`
        //     die `columns-N` Klasse liefert (siehe WC Core, sonst greift
        //     der Default-Loop-Pfad und der Modifier-Class wird nicht gesetzt).
        //   - name = 'related' bleibt fuer Hooks/Filter erhalten.
        if ( function_exists( 'wc_set_loop_prop' ) ) {
            wc_set_loop_prop( 'columns', $columns );
            wc_set_loop_prop( 'is_shortcode', true );
            wc_set_loop_prop( 'name', 'related' );
            wc_set_loop_prop( 'total', count( $related_ids ) );
            wc_set_loop_prop( 'total_pages', 1 );
            wc_set_loop_prop( 'per_page', $limit );
            wc_set_loop_prop( 'current_page', 1 );
        }

        woocommerce_product_loop_start();
        while ( $query->have_posts() ) {
            $query->the_post();
            wc_get_template_part( 'content', 'product' );
        }
        woocommerce_product_loop_end();
        echo '</section>';

        wp_reset_postdata();
    }

    /**
     * Detail-Block: Card mit Sticky-Tab-Navigator.
     *
     * Mergt die WC-Tabs `description` und `additional_information`
     * in eine Tab-Card. Reviews + FAQ bleiben separate Sections.
     *
     * Verhalten:
     *  - Tab "Produktdetails" ist initial aktiv
     *  - Pill-Klick wechselt das sichtbare Panel + smooth-scroll
     *    zur Block-Top (siehe assets/js/detail-block.js)
     *  - Sticky-Pin der Pill-Bar unter dem Site-Header,
     *    loest sich am Ende der Card automatisch
     *
     * Edge-Case: Nur 1 sichtbare WC-Tab vorhanden -> Fallback auf
     * .wa-single-panel-Card (kein 1-Pill-UI rendern).
     */
    public function render_detail_block() {
        $tabs = apply_filters( 'woocommerce_product_tabs', array() );
        if ( empty( $tabs ) ) {
            return;
        }

        // Nur description + additional_information beruecksichtigen.
        // Reviews -> render_reviews_section(); FAQ -> render_faq().
        $allowed = array( 'description', 'additional_information' );

        // Custom Pill-Labels (override der WC-Default-Titles).
        $labels = array(
            'description'            => __( 'Produktdetails', 'werbeauf-customs' ),
            'additional_information' => __( 'Zusätzliche Informationen', 'werbeauf-customs' ),
        );

        $panels = array();
        foreach ( $allowed as $key ) {
            if ( empty( $tabs[ $key ]['callback'] ) || ! is_callable( $tabs[ $key ]['callback'] ) ) {
                continue;
            }
            $panels[ $key ] = array(
                'label'    => isset( $labels[ $key ] ) ? $labels[ $key ] : $tabs[ $key ]['title'],
                'callback' => $tabs[ $key ]['callback'],
                'tab'      => $tabs[ $key ],
                'key'      => $key,
            );
        }

        $count = count( $panels );
        if ( 0 === $count ) {
            return;
        }

        // 1 Tab -> kein Tab-Navigator, nur die Single-Panel-Card.
        // ID `wa-panel-{key}` bleibt erhalten, damit In-Page-Anker
        // (z.B. der "Produktbeschreibung lesen"-Link unter der Short
        // Description) auch hier ein gueltiges Sprungziel haben.
        // Title "Produktdetails" sitzt analog zur Multi-Tab-Variante
        // ausserhalb der Card im normalen Flow (ueber aria-labelledby
        // verknuepft).
        if ( 1 === $count ) {
            $panel = reset( $panels );
            echo '<section class="wa-single-panel" id="wa-panel-' . esc_attr( $panel['key'] ) . '" aria-labelledby="wa-single-panel-title">';
            echo '<h2 id="wa-single-panel-title" class="wa-single-panel__title wa-section__heading">' . esc_html__( 'Produktdetails', 'werbeauf-customs' ) . '</h2>';
            echo '<div class="wa-single-panel__body">';
            call_user_func( $panel['callback'], $panel['key'], $panel['tab'] );
            echo '</div></section>';
            return;
        }

        // 2 Tabs -> Sticky-Tab-Card.
        // H2 sitzt bewusst AUSSERHALB des sticky <header> -- damit das
        // grosse Section-Heading im normalen Flow am Card-Top bleibt
        // und nur die kompakte Pill-Bar unter dem Site-Header gepinnt
        // wird (kein Clipping-Risiko durch zu hohes Heading im Pin).
        echo '<section class="wa-detail-block" aria-labelledby="wa-detail-block-title">';
        echo '<h2 id="wa-detail-block-title" class="wa-detail-block__title wa-section__heading">' . esc_html__( 'Produktdetails', 'werbeauf-customs' ) . '</h2>';
        echo '<header class="wa-detail-block__header">';
        echo '<nav class="wa-detail-block__nav" role="tablist" aria-label="' . esc_attr__( 'Produkt-Sektionen', 'werbeauf-customs' ) . '">';

        $first = true;
        foreach ( $panels as $id => $panel ) {
            $is_active     = $first;
            $aria_selected = $is_active ? 'true' : 'false';
            $tabindex      = $is_active ? '0' : '-1';
            $active_class  = $is_active ? ' is-active' : '';
            echo '<button type="button" class="wa-detail-block__pill' . $active_class . '" '
                . 'role="tab" aria-selected="' . $aria_selected . '" tabindex="' . $tabindex . '" '
                . 'data-target="wa-panel-' . esc_attr( $id ) . '" '
                . 'aria-controls="wa-panel-' . esc_attr( $id ) . '" '
                . 'id="wa-tab-' . esc_attr( $id ) . '">'
                . esc_html( $panel['label'] )
                . '</button>';
            $first = false;
        }

        echo '</nav>';
        echo '</header>';

        echo '<div class="wa-detail-block__panels">';
        $first = true;
        foreach ( $panels as $id => $panel ) {
            $hidden_attr = $first ? '' : ' hidden';
            echo '<div class="wa-detail-block__panel" id="wa-panel-' . esc_attr( $id ) . '" '
                . 'role="tabpanel" tabindex="0" '
                . 'aria-labelledby="wa-tab-' . esc_attr( $id ) . '"' . $hidden_attr . '>';
            call_user_func( $panel['callback'], $panel['key'], $panel['tab'] );
            echo '</div>';
            $first = false;
        }
        echo '</div>';

        echo '</section>';
    }

    /**
     * Reviews-Section -- separat unterhalb des Detail-Blocks.
     * Eigene H2 im .wa-section__heading-Stil + Standard-WC-Reviews
     * Callback. Wird nur gerendert, wenn Reviews aktiv sind und der
     * Tab existiert.
     */
    public function render_reviews_section() {
        $tabs = apply_filters( 'woocommerce_product_tabs', array() );
        if ( empty( $tabs['reviews']['callback'] ) || ! is_callable( $tabs['reviews']['callback'] ) ) {
            return;
        }

        echo '<section class="wa-product-reviews" aria-labelledby="wa-reviews-title">';
        echo '<h2 id="wa-reviews-title" class="wa-section__heading">' . esc_html__( 'Bewertungen', 'werbeauf-customs' ) . '</h2>';
        call_user_func( $tabs['reviews']['callback'], 'reviews', $tabs['reviews'] );
        echo '</section>';
    }

    private function icon( $name ) {
        return self::icon_svg( $name, 'wa-icon' );
    }

    /**
     * Plus/Minus-Toggle-Icon fuer .wa-accordion__head.
     *
     * Markup: zwei <line>-Elemente in einem <svg>:
     *   - .wa-accordion__icon-h : horizontale Linie (immer sichtbar)
     *   - .wa-accordion__icon-v : vertikale Linie (CSS skaliert auf 0
     *                              wenn das umschliessende <details>
     *                              den open-State hat)
     *
     * Single-Source-of-Truth -- wird sowohl von render_accordion()
     * als auch von render_faq() konsumiert (gleicher Apple-Style).
     */
    private function accordion_icon_svg() {
        return '<svg class="wa-accordion__icon" viewBox="0 0 24 24" aria-hidden="true">'
             . '<line class="wa-accordion__icon-h" x1="5" y1="12" x2="19" y2="12" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>'
             . '<line class="wa-accordion__icon-v" x1="12" y1="5" x2="12" y2="19" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>'
             . '</svg>';
    }

    /**
     * Liefert den fertigen <svg>-Markup-String fuer einen Fact-Icon-Key.
     *
     * Public-static, damit auch Admin-Code (z.B. die Product-List-
     * Column in admin/product-facts-column.php) auf dieselbe SVG-Map
     * zugreifen kann -- ohne die Pfade zu duplizieren.
     *
     * @param string $name        Icon-Key aus self::icon_paths().
     * @param string $wrap_class  Optionaler Wrapper-Span (leer = kein Wrapper).
     * @return string             HTML oder '' wenn Key unbekannt.
     */
    public static function icon_svg( $name, $wrap_class = '' ) {
        $paths = self::icon_paths();
        if ( ! isset( $paths[ $name ] ) ) {
            return '';
        }
        $svg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
             . $paths[ $name ]
             . '</svg>';
        if ( '' === $wrap_class ) {
            return $svg;
        }
        return '<span class="' . esc_attr( $wrap_class ) . '">' . $svg . '</span>';
    }

    /**
     * Single-Source-of-Truth fuer die Fact-Icon-Map.
     * Bei Erweiterung -> includes/acf/single-product-fields.php
     * ($icon_choices) ebenfalls anpassen.
     */
    public static function icon_paths() {
        return apply_filters( 'wa_icon_paths', array(
            'truck'    => '<path d="M3 7h11v9H3z"/><path d="M14 10h4l3 3v3h-7"/><circle cx="7" cy="18" r="2"/><circle cx="17" cy="18" r="2"/>',
            'leaf'     => '<path d="M5 19c0-9 7-14 16-14 0 9-5 16-14 16-3 0-2-2-2-2z"/><path d="M5 19c4-4 8-7 11-9"/>',
            'shield'   => '<path d="M12 3l8 3v6c0 5-4 8-8 9-4-1-8-4-8-9V6l8-3z"/><path d="M9 12l2 2 4-4"/>',
            'heart'    => '<path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.29 1.51 4.04 3 5.5l7 7 7-7Z"/>',
            'sparkles' => '<path d="M12 3l1.6 4.8L18 9l-4.4 1.2L12 15l-1.6-4.8L6 9l4.4-1.2z"/><path d="M19 14l.7 2.1 2.1.7-2.1.7-.7 2.1-.7-2.1-2.1-.7 2.1-.7z"/><path d="M5 4l.5 1.5 1.5.5-1.5.5L5 8l-.5-1.5L3 6l1.5-.5z"/>',
            'droplet'  => '<path d="M12 2.7l5.66 5.66a8 8 0 1 1-11.31 0z"/>',
            'flask'    => '<path d="M9 2v6L4 18a2 2 0 0 0 2 3h12a2 2 0 0 0 2-3L15 8V2"/><path d="M8 2h8"/><path d="M7 14h10"/>',
            'check'    => '<circle cx="12" cy="12" r="10"/><path d="M9 12l2 2 4-4"/>',
        ) );
    }
}

new Werbeauf_Single_Product_Renderer();

endif;

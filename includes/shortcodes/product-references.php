<?php
/* ============================================================
   DATEI: includes/shortcodes/product-references.php
   ZWECK: Produkt-Verweise innerhalb der WC-Beschreibungen.

   Zwei Shortcodes:
     [wa_product_refs ids="1,4,9" title="Das Set besteht aus" extra="Extra-Eintrag|Noch einer"]
       Inline-Liste verlinkter Produktnamen ("A, B und C").
       Optionaler "title" wird als <strong> mit Linebreak vorangestellt.
       Optionaler "extra" (pipe-getrennt) haengt nicht-verlinkte
       Eintraege an die Liste an.

     [wa_product_ref id="123" image="https://..."]Optionaler Custom-Text[/wa_product_ref]
       Tabellen-Zeile (Bild | Name + Text | Link). "image" ueberschreibt
       das Produkt-Standardbild. Wenn ohne Inhalt geschrieben, wird die
       Short-Description des referenzierten Produkts als Fallback geholt.

     [wa_product_ref name="Custom Title" image="https://..."]Custom Text[/wa_product_ref]
       Manueller Eintrag ohne Produkt-ID: kein Link, kein "Zum Produkt"-CTA.
       "image" optional. "name" ist Pflicht im manuellen Modus.

   Mehrere Eintraege untereinander rendern als zusammenhaengende
   Tabelle (Border-Top + Border-Bottom auf first/last). Auf Mobile
   stacken die drei Spalten zu Image-Body-CTA-Layout.
============================================================ */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'wp_enqueue_scripts', 'wa_product_refs_register_assets' );
function wa_product_refs_register_assets() {
    wp_register_style(
        'wa-product-refs',
        WERBEAUF_PLUGIN_URL . 'assets/css/40-blocks/product-refs.css',
        array( 'wa-tokens' ),
        '1.2.0'
    );
}

add_shortcode( 'wa_product_ref',  'wa_render_product_ref_block' );
add_shortcode( 'wa_product_refs', 'wa_render_product_ref_inline' );

/**
 * Wrappt sich eine ID -> WC_Product-Instanz, validiert publiziert und
 * vom richtigen Post-Type. Liefert null bei ungueltigen IDs.
 */
function wa_get_product_ref( $id ) {
    if ( ! function_exists( 'wc_get_product' ) ) {
        return null;
    }
    $id = absint( $id );
    if ( ! $id ) {
        return null;
    }
    $product = wc_get_product( $id );
    if ( ! $product || $product->get_status() !== 'publish' ) {
        return null;
    }
    return $product;
}

/**
 * [wa_product_ref id="N" image="https://..."]Custom Text[/wa_product_ref]
 * [wa_product_ref name="Title" image="https://..."]Custom Text[/wa_product_ref]
 * Single Tabellen-Zeile (Image | Name + Text | "Zum Produkt"-Link).
 */
function wa_render_product_ref_block( $atts, $content = '' ) {
    $atts = shortcode_atts(
        array(
            'id'    => 0,
            'image' => '',
            'name'  => '',
        ),
        $atts,
        'wa_product_ref'
    );

    $product   = wa_get_product_ref( $atts['id'] );
    $image_url = trim( (string) $atts['image'] );

    // Modus bestimmen: mit Produkt = verlinkt + CTA, ohne = manueller Eintrag.
    if ( $product ) {
        $id        = $product->get_id();
        $name      = $product->get_name();
        $permalink = get_permalink( $id );

        $text = trim( (string) $content );
        if ( $text === '' ) {
            $text = $product->get_short_description();
        }

        if ( $image_url !== '' ) {
            $image_html = sprintf(
                '<img class="wa-product-ref__img" src="%s" alt="%s" loading="lazy" />',
                esc_url( $image_url ),
                esc_attr( $name )
            );
        } else {
            $image_html = wp_get_attachment_image(
                $product->get_image_id(),
                'medium',
                false,
                array(
                    'class'   => 'wa-product-ref__img',
                    'loading' => 'lazy',
                    'alt'     => $name,
                )
            );
        }
    } else {
        // Manueller Modus: braucht mindestens einen Namen.
        $name = trim( (string) $atts['name'] );
        if ( $name === '' ) {
            return '';
        }
        $permalink = '';
        $text      = trim( (string) $content );
        $image_html = '';
        if ( $image_url !== '' ) {
            $image_html = sprintf(
                '<img class="wa-product-ref__img" src="%s" alt="%s" loading="lazy" />',
                esc_url( $image_url ),
                esc_attr( $name )
            );
        }
    }

    if ( $text !== '' ) {
        $text = wpautop( do_shortcode( $text ) );
    }

    $classes = array( 'wa-product-ref' );
    if ( ! $product ) {
        $classes[] = 'wa-product-ref--no-cta';
    }
    if ( $image_html === '' ) {
        $classes[] = 'wa-product-ref--no-image';
    }

    wp_enqueue_style( 'wa-product-refs' );

    ob_start();
    ?>
    <div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>">
<?php if ( $image_html !== '' ) : ?>
<?php if ( $permalink !== '' ) : ?>
        <a class="wa-product-ref__image" href="<?php echo esc_url( $permalink ); ?>" tabindex="-1" aria-hidden="true">
            <?php echo $image_html; // safe: WP-API Output ?>
        </a>
<?php else : ?>
        <span class="wa-product-ref__image">
            <?php echo $image_html; // safe ?>
        </span>
<?php endif; ?>
<?php endif; ?>
        <div class="wa-product-ref__body">
            <h3 class="wa-product-ref__name">
<?php if ( $permalink !== '' ) : ?>
                <a href="<?php echo esc_url( $permalink ); ?>"><?php echo esc_html( $name ); ?></a>
<?php else : ?>
                <?php echo esc_html( $name ); ?>
<?php endif; ?>
            </h3>
<?php if ( $text !== '' ) : ?>
            <div class="wa-product-ref__text">
                <?php echo wp_kses_post( $text ); ?>
            </div>
<?php endif; ?>
        </div>
<?php if ( $permalink !== '' ) : ?>
        <a class="wa-product-ref__cta" href="<?php echo esc_url( $permalink ); ?>">
            <span class="wa-product-ref__cta-label"><?php esc_html_e( 'Zum Produkt', 'werbeauf-customs' ); ?></span>
            <span class="wa-product-ref__cta-arrow" aria-hidden="true">&rarr;</span>
        </a>
<?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * [wa_product_refs ids="1,4,9" title="Das Set besteht aus" extra="A|B"]
 * Inline-Liste verlinkter Produktnamen, ", " getrennt mit "und"
 * vor dem letzten Eintrag. Optional Title (strong + br) davor.
 * Optional "extra" (pipe-getrennte, nicht-verlinkte) Eintraege.
 */
function wa_render_product_ref_inline( $atts ) {
    $atts = shortcode_atts(
        array(
            'ids'   => '',
            'title' => '',
            'extra' => '',
        ),
        $atts,
        'wa_product_refs'
    );

    $items = array();

    $ids = array_filter( array_map( 'absint', explode( ',', (string) $atts['ids'] ) ) );
    foreach ( $ids as $id ) {
        $product = wa_get_product_ref( $id );
        if ( ! $product ) {
            continue;
        }
        $items[] = sprintf(
            '<a href="%s">%s</a>',
            esc_url( get_permalink( $product->get_id() ) ),
            esc_html( $product->get_name() )
        );
    }

    // Custom, nicht-verlinkte Eintraege (z.B. "Eigenes Produkt").
    $extras = array_filter( array_map( 'trim', explode( '|', (string) $atts['extra'] ) ) );
    foreach ( $extras as $extra ) {
        $items[] = esc_html( $extra );
    }

    $count = count( $items );
    if ( $count === 0 ) {
        return '';
    }

    if ( $count === 1 ) {
        $list = $items[0];
    } elseif ( $count === 2 ) {
        $list = sprintf(
            '%1$s %2$s %3$s',
            $items[0],
            esc_html__( 'und', 'werbeauf-customs' ),
            $items[1]
        );
    } else {
        $last = array_pop( $items );
        $list = sprintf(
            '%1$s %2$s %3$s',
            implode( ', ', $items ),
            esc_html__( 'und', 'werbeauf-customs' ),
            $last
        );
    }

    $title = trim( (string) $atts['title'] );
    if ( $title !== '' ) {
        return sprintf(
            '<p class="wa-product-refs"><strong>%s</strong> %s</p>',
            esc_html( $title ),
            $list
        );
    }

    return '<p class="wa-product-refs">' . $list . '</p>';
}

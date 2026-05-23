<?php
/* ============================================================
   DATEI: includes/woocommerce-customs.php
============================================================ */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'woocommerce_init', 'wa_setup_external_product_hooks' );

function wa_setup_external_product_hooks() {
    remove_action( 'woocommerce_external_add_to_cart', 'woocommerce_external_add_to_cart', 30 );
    add_action( 'woocommerce_external_add_to_cart', 'wa_external_add_to_cart_blank', 30 );
    add_filter( 'woocommerce_loop_add_to_cart_link', 'wa_external_loop_button_blank', 10, 2 );
}

function wa_external_add_to_cart_blank() {
    global $product;

    if ( ! $product || ! $product->is_type( 'external' ) ) {
        return;
    }

    $product_url = $product->add_to_cart_url();
    $button_text = $product->single_add_to_cart_text();

    do_action( 'woocommerce_before_add_to_cart_button' ); ?>

    <p class="cart">
        <a href="<?php echo esc_url( $product_url ); ?>" 
           class="single_add_to_cart_button button alt" 
           rel="nofollow noopener noreferrer" 
           target="_blank">
            <?php echo esc_html( $button_text ); ?>
        </a>
    </p>

    <?php
    do_action( 'woocommerce_after_add_to_cart_button' );
}

function wa_external_loop_button_blank( $link, $product ) {
    if ( $product && $product->is_type( 'external' ) ) {
        
        $link = sprintf(
            '<a rel="nofollow noopener noreferrer" href="%s" data-quantity="1" data-product_id="%s" class="%s" target="_blank" aria-label="%s">%s</a>',
            esc_url( $product->add_to_cart_url() ),
            esc_attr( $product->get_id() ),
            esc_attr( 'button product_type_external' ),
            esc_attr( $product->add_to_cart_description() ),
            esc_html( $product->add_to_cart_text() )
        );
    }
    
    return $link;
}

// Zoom-Funktion für das Hauptbild deaktivieren
add_action( 'after_setup_theme', function() {
    remove_theme_support( 'wc-product-gallery-zoom' );
}, 100 );

/**
 * Sale-Badge: zeigt den prozentualen Rabatt statt "Sale!" / "Reduziert!".
 * - Greift auf Loop, Single-Product und Mini-Cart.
 * - Variable Produkte: nimmt die groesste Ersparnis aller Varianten,
 *   damit die Customer-Erwartung "bis zu -X%" stimmt.
 */
add_filter( 'woocommerce_sale_flash', 'wa_percentage_sale_flash', 20, 3 );
function wa_percentage_sale_flash( $html, $post, $product ) {
    if ( ! $product instanceof WC_Product || ! $product->is_on_sale() ) {
        return $html;
    }

    $percent = wa_calculate_sale_percent( $product );
    if ( $percent <= 0 ) {
        return $html;
    }

    return sprintf(
        '<span class="onsale onsale--percent" aria-label="%s">%s%d%%</span>',
        esc_attr( sprintf( __( '%d%% Rabatt', 'werbeauf-customs' ), $percent ) ),
        '−', // U+2212 - typografisches Minus
        (int) $percent
    );
}

function wa_calculate_sale_percent( $product ) {
    if ( ! $product instanceof WC_Product ) {
        return 0;
    }

    if ( $product->is_type( 'variable' ) ) {
        $best = 0;
        foreach ( $product->get_visible_children() as $child_id ) {
            $variation = wc_get_product( $child_id );
            if ( ! $variation || ! $variation->is_on_sale() ) {
                continue;
            }
            $best = max( $best, wa_calculate_sale_percent( $variation ) );
        }
        return $best;
    }

    $regular = (float) $product->get_regular_price();
    $sale    = (float) $product->get_sale_price();

    if ( $regular <= 0 || $sale <= 0 || $sale >= $regular ) {
        return 0;
    }

    return (int) round( 100 - ( $sale / $regular * 100 ) );
}

/**
 * Shop-Loop Card-Actions:
 * Rendert "Produkt anzeigen" + "In den Warenkorb" gemeinsam in einem
 * .drp-card-actions Flex-Container. Divi unterdrueckt im Shop-Loop den
 * Default-Hook woocommerce_template_loop_add_to_cart, deshalb rufen wir die
 * Funktion explizit auf. Defensiv removen wir die Default-Aktion vorher,
 * damit der Button nie doppelt erscheint - falls eine andere Stelle sie
 * (re)registriert.
 */
add_action( 'init', 'wa_loop_actions_setup', 20 );
function wa_loop_actions_setup() {
    remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 10 );
    add_action( 'woocommerce_after_shop_loop_item', 'wa_loop_actions_render', 10 );

    // Subcategory-Card im Shop-Loop neu rendern (Eyebrow + Titel + Entdecken-CTA).
    remove_action( 'woocommerce_shop_loop_subcategory_title', 'woocommerce_template_loop_category_title', 10 );
    add_action( 'woocommerce_shop_loop_subcategory_title', 'wa_template_loop_category_title', 10 );
}

/**
 * Rendert die Subcategory-Card im Shop-Loop im Dr.-Peri-Stil.
 *
 * Markup:
 *   <div class="wa-cat-card__body">
 *       <span class="wa-cat-card__eyebrow">5 Produkte</span>
 *       <h2 class="woocommerce-loop-category__title wa-cat-card__title">Pflege</h2>
 *       <span class="wa-cat-card__cta" aria-hidden="true">Entdecken</span>
 *   </div>
 *
 * Greift auf:
 *   - Shop-Archiv mit Subcategory-Display
 *   - Category-Archiv mit Subcategory-Display
 *   - Shortcodes [product_categories] und [product_category]
 *
 * @param WP_Term $category Aktueller Term im Loop.
 */
function wa_template_loop_category_title( $category ) {
    if ( ! ( $category instanceof WP_Term ) ) {
        return;
    }

    $count        = (int) $category->count;
    $eyebrow_html = '';

    if ( $count > 0 ) {
        $eyebrow_text = sprintf(
            _n( '%d Produkt', '%d Produkte', $count, 'werbeauf-customs' ),
            $count
        );
        $eyebrow_html = '<span class="wa-cat-card__eyebrow">' . esc_html( $eyebrow_text ) . '</span>';
    }

    $cta_label = esc_html__( 'Entdecken', 'werbeauf-customs' );

    printf(
        '<div class="wa-cat-card__body">%1$s<h2 class="woocommerce-loop-category__title wa-cat-card__title">%2$s</h2><span class="wa-cat-card__cta" aria-hidden="true">%3$s</span></div>',
        $eyebrow_html, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — bereits escaped
        esc_html( $category->name ),
        $cta_label // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — bereits escaped via esc_html__
    );
}

function wa_loop_actions_render() {
    global $product;
    if ( ! $product instanceof WC_Product ) {
        return;
    }

    echo '<div class="drp-card-actions">';

    $view_label = esc_html__( 'Produkt anzeigen', 'werbeauf-customs' );
    $view_aria  = sprintf( __( '%s ansehen', 'werbeauf-customs' ), wp_strip_all_tags( $product->get_name() ) );

    printf(
        '<a href="%s" class="drp-card-actions__btn drp-card-actions__view" aria-label="%s" title="%s">'
            . '%s'
            . '<span class="drp-card-actions__label">%s</span>'
            . '<span class="screen-reader-text">%s</span>'
        . '</a>',
        esc_url( get_permalink( $product->get_id() ) ),
        esc_attr( $view_aria ),
        esc_attr( $view_label ),
        wa_loop_actions_icon( 'info' ),
        $view_label,
        $view_label
    );

    if ( function_exists( 'woocommerce_template_loop_add_to_cart' ) ) {
        woocommerce_template_loop_add_to_cart();
    }

    echo '</div>';
}

/**
 * Loop-Add-to-Cart Filter:
 * Tauscht den sichtbaren Button-Text gegen ein SVG-Icon + sr-only Label,
 * haengt zusaetzlich einen .drp-card-actions__label-Span an, der per CSS
 * im Hover-State eingeblendet werden kann. Externe Produkte bleiben unveraendert.
 */
add_filter( 'woocommerce_loop_add_to_cart_link', 'wa_loop_add_to_cart_icon', 20, 2 );
function wa_loop_add_to_cart_icon( $html, $product ) {
    if ( ! $product instanceof WC_Product ) {
        return $html;
    }
    if ( $product->is_type( 'external' ) ) {
        return $html;
    }

    $label = $product->add_to_cart_text();
    $icon  = wa_loop_actions_icon( 'bag' );

    $replacement =
        $icon
        . '<span class="drp-card-actions__label">' . esc_html( $label ) . '</span>'
        . '<span class="screen-reader-text">' . esc_html( $label ) . '</span>';

    /*
     * WC rendert "<a ...>TEXT</a>" - wir tauschen genau diesen Text-Knoten,
     * damit alle aria-/data-Attribute, AJAX-Klassen und Sibling-screen-reader-Spans
     * unangetastet bleiben.
     */
    $html = str_replace(
        '>' . esc_html( $label ) . '</a>',
        '>' . $replacement . '</a>',
        $html
    );

    /* Title-Attribut + .drp-card-actions__btn Klasse fuer einheitliches Styling. */
    $html = preg_replace(
        '/class="(button[^"]*)"/',
        'class="$1 drp-card-actions__btn drp-card-actions__cart" title="' . esc_attr( $label ) . '"',
        $html,
        1
    );

    return $html;
}

/**
 * Inline-SVG Icons fuer Card-Actions (Phosphor-/Lucide-Style, currentColor).
 *
 * @param string $name 'info' | 'bag'
 * @return string SVG-Markup (escaped fuer direkte Ausgabe)
 */
function wa_loop_actions_icon( $name ) {
    $svgs = array(
        'info' => '<svg class="drp-card-actions__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9.25"/><line x1="12" y1="11" x2="12" y2="16.5"/><circle cx="12" cy="8" r="0.6" fill="currentColor" stroke="none"/></svg>',
        'bag'  => '<svg class="drp-card-actions__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M5.2 8h13.6l-1.2 10.3a2 2 0 0 1-2 1.7H8.4a2 2 0 0 1-2-1.7L5.2 8z"/><path d="M9 8V5.8a3 3 0 0 1 6 0V8"/></svg>',
    );
    return isset( $svgs[ $name ] ) ? $svgs[ $name ] : '';
}

/**
 * Cart item count for header badges (WooCommerce).
 *
 * @return string
 */
function wa_header_cart_count_display() {
    if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
        return '0';
    }
    $n = (int) WC()->cart->get_cart_contents_count();
    return $n > 99 ? '99+' : (string) $n;
}

/**
 * Refresh header cart counts after AJAX add/remove (WooCommerce fragments).
 *
 * @param array $fragments Fragments array.
 * @return array
 */
function wa_header_cart_link_fragments( $fragments ) {
    if ( ! function_exists( 'wc_get_cart_url' ) ) {
        return $fragments;
    }
    $n          = ( function_exists( 'WC' ) && WC()->cart ) ? (int) WC()->cart->get_cart_contents_count() : 0;
    $count_text = wa_header_cart_count_display();
    $empty_cls  = ( 0 === $n ) ? ' wa-header-cart__count--empty' : '';

    $fragments['#wa-header-cart-count-d'] = '<span id="wa-header-cart-count-d" class="wa-header-cart__count' . $empty_cls . '" aria-hidden="true">' . esc_html( $count_text ) . '</span>';
    $fragments['#wa-header-cart-count-m'] = '<span id="wa-header-cart-count-m" class="wa-header-cart__count wa-mobile-cart__badge' . $empty_cls . '" aria-hidden="true">' . esc_html( $count_text ) . '</span>';

    return $fragments;
}
add_filter( 'woocommerce_add_to_cart_fragments', 'wa_header_cart_link_fragments' );

/**
 * Prueft, ob die aktuelle Singular-Page einen bestimmten Shortcode enthaelt.
 * Wird benutzt, um Pages, die [woocommerce_cart]/[woocommerce_checkout]/
 * [woocommerce_my_account] einbetten, den selben Body-Class- und Stylesheet-
 * Apparat zu geben wie die nativen WC-Endpunkt-Seiten.
 *
 * Bevorzugt WCs eigenes wc_post_content_has_shortcode(), faellt sonst auf
 * has_shortcode() gegen post_content zurueck.
 *
 * @param string $tag Shortcode-Tag ohne Klammern.
 * @return bool
 */
function wa_post_has_wc_shortcode( $tag ) {
    if ( function_exists( 'wc_post_content_has_shortcode' ) ) {
        return (bool) wc_post_content_has_shortcode( $tag );
    }
    if ( ! is_singular() ) {
        return false;
    }
    $post = get_post();
    if ( ! $post || empty( $post->post_content ) ) {
        return false;
    }
    return has_shortcode( (string) $post->post_content, $tag );
}

/**
 * Body-Klassen fuer einheitliches Layout-/Design-Shell:
 * - wa-woocommerce  : alle WC-Seiten (Shop, Cart, Checkout, Account, Single-Product, ...)
 *                     UND alle Pages, die [woocommerce_cart] / [woocommerce_checkout] /
 *                     [woocommerce_my_account] einbetten.
 * - wa-content-shell: rechtliche/informationelle Seiten ausserhalb WC (AGB, Datenschutz,
 *                     Impressum, Widerruf, Rueckerstattung, Versand, Zahlung, ...)
 *                     -> bekommen den gleichen Layout-Container (max-width 1400 + Padding)
 *                     wie WC-Seiten, aber nicht die WC-Designtokens.
 *
 * Zusaetzlich werden auf Shortcode-Pages die WC-Body-Klassen
 * (woocommerce-cart / -checkout / -account / -page) gespiegelt, damit
 * die unter body.wa-woocommerce .woocommerce-cart / -checkout / -account
 * verschachtelten Stylesheets greifen.
 */
add_filter( 'body_class', 'wa_woocommerce_body_class', 5 );
function wa_woocommerce_body_class( $classes ) {
    if ( function_exists( 'is_woocommerce' ) ) {

        $is_cart_page     = function_exists( 'is_cart' )         && is_cart();
        $is_checkout_page = function_exists( 'is_checkout' )     && is_checkout();
        $is_account_page  = function_exists( 'is_account_page' ) && is_account_page();

        // Shortcode-Fallback: Pages mit [woocommerce_*]-Shortcode behandeln
        // wir genau wie die nativen Endpunkte.
        if ( ! $is_cart_page && wa_post_has_wc_shortcode( 'woocommerce_cart' ) ) {
            $is_cart_page = true;
            $classes[]    = 'woocommerce-cart';
            $classes[]    = 'woocommerce-page';
        }
        if ( ! $is_checkout_page && wa_post_has_wc_shortcode( 'woocommerce_checkout' ) ) {
            $is_checkout_page = true;
            $classes[]        = 'woocommerce-checkout';
            $classes[]        = 'woocommerce-page';
        }
        if ( ! $is_account_page && wa_post_has_wc_shortcode( 'woocommerce_my_account' ) ) {
            $is_account_page = true;
            $classes[]       = 'woocommerce-account';
            $classes[]       = 'woocommerce-page';
        }

        $is_wc_page = is_woocommerce()
            || $is_cart_page
            || $is_checkout_page
            || $is_account_page;

        // Harte Exclusion: bestimmte Pages werden von WooCommerce Germanized
        // (bzw. dessen eu-order-withdrawal-button Paket) als WC-Seiten
        // behandelt, sollen aber das wa-woocommerce Layout-Shell NICHT erben.
        $current_id = get_queried_object_id();
        if ( $is_wc_page && $current_id && in_array( (int) $current_id, wa_wc_shell_excluded_page_ids(), true ) ) {
            $is_wc_page = false;
        }

        if ( $is_wc_page ) {
            $classes[] = 'wa-woocommerce';
        }
    }
    if ( wa_is_content_shell_page() ) {
        $classes[] = 'wa-content-shell';
    }
    return array_values( array_unique( $classes ) );
}

/**
 * Page-IDs, die NIE das wa-woocommerce Layout-Shell bekommen sollen,
 * auch wenn is_woocommerce() oder ein WC-Shortcode triggern wuerde.
 * Per Filter erweiterbar.
 *
 * @return int[]
 */
function wa_wc_shell_excluded_page_ids() {
    /*
     * Vertrag widerrufen (DE) / Cancel the contract (EN):
     * Beide nutzen den [eu_owb_order_withdrawal_request_form] Shortcode
     * aus WooCommerce Germanized (Paket eu-order-withdrawal-button-for-
     * woocommerce), das diese Pages als WC-Seiten klassifiziert. Inhalt
     * ist aber Divi-Code-Modul + .legal-styling Wrapper -- soll nicht
     * unter dem wa-woocommerce Layout-Shell landen.
     */
    $ids = array( 19380210, 19380222 );
    return apply_filters( 'wa_wc_shell_excluded_page_ids', $ids );
}

/**
 * Liefert die Slug-Liste der Seiten, die das gleiche Layout-Shell wie WC-Seiten nutzen sollen.
 * Per Filter erweiterbar: add_filter( 'wa_content_shell_slugs', fn($s) => array_merge($s, ['mein-slug']) );
 *
 * @return string[]
 */
function wa_content_shell_slugs() {
    /*
     * Legal pages on drperi (AGB, Datenschutz, Impressum, Widerrufsbelehrung,
     * Versand, Zahlungsmethoden) are built with the Divi Code Module +
     * .legal-styling wrapper, so they don't need the plugin's content-shell
     * layout. List intentionally empty. Extend via the wa_content_shell_slugs
     * filter below if a future plain-WP page should opt into the shell.
     */
    $slugs = array();
    return apply_filters( 'wa_content_shell_slugs', $slugs );
}

/**
 * @return bool true wenn die aktuelle Seite Content-Shell-Layout nutzen soll.
 */
function wa_is_content_shell_page() {
    if ( ! is_singular( 'page' ) ) {
        return false;
    }
    $post = get_post();
    if ( ! $post || empty( $post->post_name ) ) {
        return false;
    }
    return in_array( $post->post_name, wa_content_shell_slugs(), true );
}

/**
 * Endpoint-spezifischer Page-Title fuer /mein-konto/-Seiten.
 *
 * @return string
 */
function wa_account_page_title() {
    $endpoint = '';
    if ( function_exists( 'WC' ) && WC()->query ) {
        $endpoint = (string) WC()->query->get_current_endpoint();
    }

    $titles = array(
        ''                   => __( 'Mein Konto', 'werbeauf-customs' ),
        'orders'             => __( 'Bestellungen', 'werbeauf-customs' ),
        'view-order'         => __( 'Bestellung', 'werbeauf-customs' ),
        'downloads'          => __( 'Downloads', 'werbeauf-customs' ),
        'edit-address'       => __( 'Adressen', 'werbeauf-customs' ),
        'payment-methods'    => __( 'Zahlungsmethoden', 'werbeauf-customs' ),
        'add-payment-method' => __( 'Zahlungsmethode hinzufuegen', 'werbeauf-customs' ),
        'edit-account'       => __( 'Kontodetails', 'werbeauf-customs' ),
        'lost-password'      => __( 'Passwort zuruecksetzen', 'werbeauf-customs' ),
    );

    /**
     * Filter: erlaubt Erweitern/Ueberschreiben der Account-Endpoint-Titel.
     *
     * @param array  $titles
     * @param string $endpoint
     */
    $titles = apply_filters( 'wa_account_endpoint_titles', $titles, $endpoint );

    return isset( $titles[ $endpoint ] ) ? $titles[ $endpoint ] : __( 'Mein Konto', 'werbeauf-customs' );
}

/**
 * Statisches Flag, damit die H1 niemals doppelt gerendert wird, egal ueber
 * welchen Render-Pfad sie eingehakt wird (Action / Shortcode-Filter).
 *
 * @param bool|null $set Setze auf true/false, oder null zum Lesen.
 * @return bool
 */
function wa_account_h1_rendered( ?bool $set = null ): bool {
    static $rendered = false;
    if ( null !== $set ) {
        $rendered = (bool) $set;
    }
    return $rendered;
}

/**
 * Markup-Generator fuer die Account-H1.
 *
 * @param string $title Titel.
 * @return string
 */
function wa_account_h1_markup( $title ) {
    return '<h1 class="wa-account-title">' . esc_html( $title ) . '</h1>';
}

/**
 * Rendert eine H1 oberhalb der My-Account-Navigation.
 * Bulletproof Setup: 3 Render-Pfade mit gemeinsamem Static-Guard, damit kein
 * Pfad doppelt rendert.
 *
 * 1. Action 'woocommerce_before_account_navigation' (eingeloggter Default)
 * 2. Action 'woocommerce_before_customer_login_form' (Logged-Out State)
 * 3. Filter 'do_shortcode_tag' fuer [woocommerce_my_account] -> faengt jeden
 *    Render des Shortcodes ab, auch wenn ein Theme die WC-Templates ueberschreibt
 *    und den Action-Hook verschluckt.
 */
add_action( 'woocommerce_before_account_navigation', 'wa_account_render_h1', 5 );
function wa_account_render_h1() {
    if ( wa_account_h1_rendered() ) {
        return;
    }
    if ( ! function_exists( 'is_account_page' ) || ! is_account_page() ) {
        return;
    }
    wa_account_h1_rendered( true );
    echo wa_account_h1_markup( wa_account_page_title() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — escaped in helper
}

add_action( 'woocommerce_before_customer_login_form', 'wa_account_render_h1_login', 5 );
function wa_account_render_h1_login() {
    if ( wa_account_h1_rendered() ) {
        return;
    }
    if ( ! function_exists( 'is_account_page' ) || ! is_account_page() ) {
        return;
    }
    wa_account_h1_rendered( true );
    echo wa_account_h1_markup( __( 'Ihr Zugang', 'werbeauf-customs' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — escaped in helper
}

/**
 * Letzter Sicherheits-Pfad: Wenn die Action-Hooks nicht greifen (Theme
 * ueberschreibt navigation.php / form-login.php), wrappen wir den
 * Shortcode-Output direkt mit einer H1.
 */
add_filter( 'do_shortcode_tag', 'wa_account_shortcode_prepend_h1', 10, 2 );
function wa_account_shortcode_prepend_h1( $output, $tag ) {
    if ( 'woocommerce_my_account' !== $tag ) {
        return $output;
    }
    if ( wa_account_h1_rendered() ) {
        return $output;
    }
    if ( false !== strpos( $output, 'wa-account-title' ) ) {
        wa_account_h1_rendered( true );
        return $output;
    }
    wa_account_h1_rendered( true );

    $title = is_user_logged_in()
        ? wa_account_page_title()
        : __( 'Ihr Zugang', 'werbeauf-customs' );

    return wa_account_h1_markup( $title ) . $output;
}

/**
 * Account-Content-Forcer: Wenn die /mein-konto/ Seite ueber Divi Theme
 * Builder gerendert wird und die Page selbst leer ist (oder das TB-Body-
 * Layout keinen [woocommerce_my_account] Shortcode enthaelt), bleibt der
 * <div class="et_builder_inner_content"> leer und der User sieht eine
 * weisse Seite.
 *
 * Dieser Filter laeuft auf Priority 5 — VOR Divis
 * `et_builder_add_builder_content_wrapper` (Priority 10) — und injiziert
 * `[woocommerce_my_account]` (bzw. die volle My-Account-UI) in den Content,
 * sodass Divi den Output sauber in seine Wrapper packt.
 *
 * Greift NUR auf Account-Seiten und NUR im Main-Loop, niemals in Excerpts,
 * Related-Posts-Schleifen oder Admin-Vorschauen.
 */
// HINWEIS: Der frueher hier registrierte Filter `wa_account_force_content_render`
// wurde wieder entfernt -- er konnte je nach Plugin-Combo (WPML / Divi /
// woocommerce_my_account-Endpoints) eine Endlosschleife in `the_content`
// erzeugen und die Seite blockieren. Falls die Mein-Konto-Seite leer
// rendert (z.B. weil das Divi Theme Builder Body-Layout den Shortcode nicht
// enthaelt), ergaenze den Shortcode bitte manuell in der Page bzw. im
// Theme-Builder-Layout.

/**
 * WooCommerce cart fragments (header badge counts after AJAX add-to-cart).
 */
function wa_enqueue_mini_cart_scripts() {
    global $wa_show_fallback_header;

    if ( empty( $wa_show_fallback_header ) || ! class_exists( 'WooCommerce' ) ) {
        return;
    }

    if ( wp_script_is( 'wc-cart-fragments', 'registered' ) ) {
        wp_enqueue_script( 'wc-cart-fragments' );
    }
    if ( wp_script_is( 'wc-add-to-cart', 'registered' ) ) {
        wp_enqueue_script( 'wc-add-to-cart' );
    }
}
add_action( 'wp_enqueue_scripts', 'wa_enqueue_mini_cart_scripts', 25 );
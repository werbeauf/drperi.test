<?php
/* ============================================================
   DATEI: includes/shortcodes/newsletter-form.php
   ZWECK: [wa_newsletter_signup] Shortcode in zwei Varianten:
            - regular  (default): Card-Layout mit Titel + Lead + 3 Feldern
            - footer   (kompakt): Inline-Form fuer Footer-Slots

   Beispiele:
     [wa_newsletter_signup]
     [wa_newsletter_signup variant="footer"]
     [wa_newsletter_signup title="Stay in the Loop" lead="Beauty-Tipps direkt..."]

   Submit POSTet via REST an /wp-json/wa/v1/newsletter (siehe
   includes/phorest/newsletter.php).
============================================================ */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'wp_enqueue_scripts', 'wa_newsletter_register_assets' );
function wa_newsletter_register_assets() {
    wp_register_style(
        'wa-newsletter',
        WERBEAUF_PLUGIN_URL . 'assets/css/40-blocks/newsletter.css',
        array( 'wa-tokens' ),
        '1.2.0'
    );

    wp_register_script(
        'wa-newsletter',
        WERBEAUF_PLUGIN_URL . 'assets/js/newsletter.js',
        array(),
        '1.0.2',
        true
    );

    // Hinweis: 'restNonce' ist der WordPress-Standard-REST-Nonce
    // (Action 'wp_rest'), der als X-WP-Nonce Header mitgeschickt werden
    // muss. Sonst bricht die REST-Schicht fuer eingeloggte User mit
    // "Die Cookie-Pruefung ist fehlgeschlagen" ab, bevor unser eigener
    // Form-Nonce (Action 'wa_newsletter', im Body) ueberhaupt geprueft
    // werden kann.
    wp_localize_script( 'wa-newsletter', 'WA_NEWSLETTER', array(
        'restUrl'   => esc_url_raw( rest_url( 'wa/v1/newsletter' ) ),
        'restNonce' => wp_create_nonce( 'wp_rest' ),
        'i18n'      => array(
            'optin_label'   => __( 'Ich möchte den Newsletter erhalten.', 'werbeauf-customs' ),
            'submitting'    => __( 'Sende...', 'werbeauf-customs' ),
            'generic_error' => __( 'Es ist ein Fehler aufgetreten.', 'werbeauf-customs' ),
            'network_error' => __( 'Netzwerkfehler. Bitte erneut versuchen.', 'werbeauf-customs' ),
        ),
    ) );
}

add_shortcode( 'wa_newsletter_signup', 'wa_newsletter_render_shortcode' );

function wa_newsletter_render_shortcode( $atts ) {
    $atts = shortcode_atts( array(
        'variant'     => 'regular',
        'title'       => __( 'Newsletter', 'werbeauf-customs' ),
        'lead'        => __( 'Erhalte Hautwissen und exklusive Angebote direkt in dein Postfach!', 'werbeauf-customs' ),
        'button'      => __( 'Anmelden', 'werbeauf-customs' ),
        // home_url() ist WPML-aware: liefert lokal /datenschutz/, EN /en/privacy/ etc.
        // wenn die Seite uebersetzt + WPML "Translation of URLs" aktiv ist.
        'privacy_url' => home_url( '/datenschutz/' ),
    ), $atts, 'wa_newsletter_signup' );

    $variant = in_array( $atts['variant'], array( 'regular', 'footer' ), true ) ? $atts['variant'] : 'regular';
    $is_footer = ( $variant === 'footer' );

    wp_enqueue_style( 'wa-newsletter' );
    wp_enqueue_script( 'wa-newsletter' );

    $nonce      = wp_create_nonce( 'wa_newsletter' );
    $rendered_t = time();
    $form_id    = 'wa-newsletter-' . wp_generate_uuid4();
    $source     = $is_footer ? 'footer' : 'widget';

    $consent_label = sprintf(
        /* translators: %s: link to privacy policy */
        __( 'Ich möchte den Newsletter erhalten. <a href="%s">Datenschutz</a>', 'werbeauf-customs' ),
        esc_url( $atts['privacy_url'] )
    );

    ob_start();
    ?>
    <form
        class="wa-newsletter wa-newsletter--<?php echo esc_attr( $variant ); ?>"
        id="<?php echo esc_attr( $form_id ); ?>"
        data-wa-newsletter
        data-wa-source="<?php echo esc_attr( $source ); ?>"
        novalidate
    >
        <?php if ( ! $is_footer && $atts['title'] !== '' ) : ?>
            <h3 class="wa-newsletter__title"><?php echo esc_html( $atts['title'] ); ?></h3>
        <?php endif; ?>
        <?php if ( ! $is_footer && $atts['lead'] !== '' ) : ?>
            <p class="wa-newsletter__lead"><?php echo esc_html( $atts['lead'] ); ?></p>
        <?php endif; ?>

        <div class="wa-newsletter__row">
            <label class="wa-newsletter__field">
                <span class="wa-newsletter__label"><?php esc_html_e( 'Vorname', 'werbeauf-customs' ); ?></span>
                <input type="text" name="first_name" autocomplete="given-name" required>
            </label>
            <label class="wa-newsletter__field">
                <span class="wa-newsletter__label"><?php esc_html_e( 'Nachname', 'werbeauf-customs' ); ?></span>
                <input type="text" name="last_name" autocomplete="family-name" required>
            </label>
        </div>

        <label class="wa-newsletter__field wa-newsletter__field--email">
            <span class="wa-newsletter__label"><?php esc_html_e( 'E-Mail', 'werbeauf-customs' ); ?></span>
            <input type="email" name="email" autocomplete="email" required>
        </label>

        <label class="wa-newsletter__consent">
            <input type="checkbox" name="consent" value="1" required>
            <span><?php echo wp_kses( $consent_label, array( 'a' => array( 'href' => array() ) ) ); ?></span>
        </label>

        <input type="text"   name="_hp"           class="wa-newsletter__hp" tabindex="-1" autocomplete="off" aria-hidden="true">
        <input type="hidden" name="_t"            value="<?php echo esc_attr( $rendered_t ); ?>">
        <input type="hidden" name="_wa_nl_nonce"  value="<?php echo esc_attr( $nonce ); ?>">
        <input type="hidden" name="source"        value="<?php echo esc_attr( $source ); ?>">

        <button type="submit" class="wa-newsletter__submit"><?php echo esc_html( $atts['button'] ); ?></button>
        <p class="wa-newsletter__msg" role="status" aria-live="polite" hidden></p>
    </form>
    <?php
    return ob_get_clean();
}

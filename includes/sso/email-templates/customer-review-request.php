<?php
/**
 * Customer Review-Request Template — gefolgt 14 Tage nach completed.
 * Klinik-warm, dankbar, ohne Druck. Pointiert auf einen Review-Link
 * (Default: My-Account-View-Order; filterbar fuer
 * Trustpilot/Google/WC-Product-Page-Anchor).
 *
 * Erwartete Variablen:
 *   $brand          array
 *   $order          WC_Order
 *   $rule           array
 *   $review_url     string  Ziel-URL fuer das Review
 *   $greeting       string
 *   $product_count  int     Anzahl Items in der Bestellung (fuer Copy)
 *
 * @package werbeauf-customs/sso
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$heading = __( 'Wie gefallen Ihnen unsere Produkte?', 'werbeauf-customs' );
$primary = isset( $brand['primary'] ) ? (string) $brand['primary'] : '#475e76';
$count   = isset( $product_count ) ? max( 1, (int) $product_count ) : 1;

include __DIR__ . '/_partials/header.php';
?>

<p style="margin:0 0 18px;"><?php echo esc_html( $greeting ); ?></p>

<p style="margin:0 0 18px;">
    <?php
    printf(
        /* translators: %s order number */
        esc_html__( 'vor zwei Wochen haben Sie bei uns Ihre Bestellung %s erhalten. Wir hoffen, alles ist gut bei Ihnen angekommen und Sie sind mit den Produkten zufrieden.', 'werbeauf-customs' ),
        esc_html( '#' . $order->get_order_number() )
    );
    ?>
</p>

<p style="margin:0 0 24px;">
    <?php
    echo esc_html(
        _n(
            'Falls Sie einen Moment Zeit haben: Ihre kurze Bewertung hilft anderen Kundinnen bei der Auswahl. Wir wuerden uns sehr darueber freuen.',
            'Falls Sie einen Moment Zeit haben: Ihre kurze Bewertung der Produkte hilft anderen Kundinnen bei der Auswahl. Wir wuerden uns sehr darueber freuen.',
            $count,
            'werbeauf-customs'
        )
    );
    ?>
</p>

<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:0 0 24px;">
    <tr>
        <td align="center">
            <a href="<?php echo esc_url( $review_url ); ?>"
               style="display:inline-block;padding:14px 32px;background:<?php echo esc_attr( $primary ); ?>;color:#ffffff;text-decoration:none;border-radius:6px;font-weight:600;font-size:15px;">
                <?php esc_html_e( 'Bewertung abgeben', 'werbeauf-customs' ); ?>
            </a>
        </td>
    </tr>
</table>

<p style="margin:0 0 18px;font-size:13px;color:#9aa9b6;text-align:center;">
    <?php esc_html_e( 'Dauert weniger als eine Minute. Sie koennen den Text spaeter jederzeit aendern.', 'werbeauf-customs' ); ?>
</p>

<p style="margin:24px 0 0;">
    <?php esc_html_e( 'Herzlichen Dank fuer Ihr Vertrauen.', 'werbeauf-customs' ); ?><br>
    <?php
    printf(
        /* translators: %s shop name */
        esc_html__( 'Ihr %s Team', 'werbeauf-customs' ),
        esc_html( $brand['site_name'] ?? '' )
    );
    ?>
</p>

<?php
include __DIR__ . '/_partials/footer.php';

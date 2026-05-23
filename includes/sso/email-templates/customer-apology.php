<?php
/**
 * Customer Delayed-Apology Template — Entschuldigung an die Kundin,
 * wenn die Bestellung 3+ Tage unversendet ist. Klinik-Touch: ehrlich,
 * konkret, keine Floskeln.
 *
 * Erwartete Variablen:
 *   $brand        array
 *   $order        WC_Order
 *   $rule         array
 *   $age_hours    int
 *   $view_url     string  My-Account-Link zur Bestellung
 *   $greeting     string
 *
 * @package werbeauf-customs/sso
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$heading = sprintf(
    /* translators: %s order number */
    __( 'Entschuldigen Sie die Verzögerung Ihrer Bestellung %s', 'werbeauf-customs' ),
    '#' . $order->get_order_number()
);
$primary     = isset( $brand['primary'] ) ? (string) $brand['primary'] : '#475e76';
$brand_phone = isset( $brand['phone'] ) ? (string) $brand['phone'] : '';
$brand_email = isset( $brand['email'] ) ? (string) $brand['email'] : '';
$days        = max( 1, (int) round( (int) $age_hours / 24 ) );

include __DIR__ . '/_partials/header.php';
?>

<p style="margin:0 0 18px;"><?php echo esc_html( $greeting ); ?></p>

<p style="margin:0 0 18px;">
    <?php
    printf(
        /* translators: 1: order number 2: days */
        esc_html__( 'Ihre Bestellung %1$s ist seit %2$d Tagen bei uns und wurde noch nicht versendet. Das tut uns sehr leid.', 'werbeauf-customs' ),
        esc_html( '#' . $order->get_order_number() ),
        (int) $days
    );
    ?>
</p>

<p style="margin:0 0 18px;">
    <?php esc_html_e( 'Wir kümmern uns sofort um den Versand und Sie erhalten in Kürze die Tracking-Nummer. Falls Sie Fragen haben oder die Lieferung dringend benötigen, melden Sie sich bitte direkt bei uns.', 'werbeauf-customs' ); ?>
</p>

<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:0 0 24px;">
    <tr>
        <td align="center">
            <a href="<?php echo esc_url( $view_url ); ?>"
               style="display:inline-block;padding:13px 28px;background:<?php echo esc_attr( $primary ); ?>;color:#ffffff;text-decoration:none;border-radius:6px;font-weight:600;font-size:14px;">
                <?php esc_html_e( 'Bestellung ansehen', 'werbeauf-customs' ); ?>
            </a>
        </td>
    </tr>
</table>

<p style="margin:0 0 12px;color:#475e76;">
    <?php esc_html_e( 'Direkter Kontakt:', 'werbeauf-customs' ); ?><br>
    <?php if ( $brand_phone !== '' ) : ?>
        📞 <?php echo esc_html( $brand_phone ); ?> &nbsp;
    <?php endif; ?>
    <?php if ( $brand_email !== '' ) : ?>
        ✉ <a href="mailto:<?php echo esc_attr( $brand_email ); ?>" style="color:<?php echo esc_attr( $primary ); ?>;text-decoration:underline;"><?php echo esc_html( $brand_email ); ?></a>
    <?php endif; ?>
</p>

<p style="margin:24px 0 0;">
    <?php esc_html_e( 'Mit herzlichen Grüßen', 'werbeauf-customs' ); ?><br>
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

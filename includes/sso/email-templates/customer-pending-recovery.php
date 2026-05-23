<?php
/**
 * Customer Pending-Recovery Template — abgebrochener Checkout.
 * Anderer Tonfall als customer-recovery.php: hier hat die Kundin den
 * Bezahl-Schritt nicht gemacht (nicht: Zahlung fehlgeschlagen). Weniger
 * "Hilfe-Modus", mehr "freundliche Erinnerung". Sehr defensive Copy,
 * weil das Trigger-Timing (T+30min) als aggressiv empfunden werden kann
 * — daher per Default in den Settings AUS.
 *
 * Erwartete Variablen:
 *   $brand          array
 *   $order          WC_Order
 *   $rule           array
 *   $retry_url      string  Checkout-Pay-URL
 *   $greeting       string
 *
 * @package werbeauf-customs/sso
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$heading = sprintf(
    /* translators: %s order number */
    __( 'Ihre Bestellung %s wartet auf Sie', 'werbeauf-customs' ),
    '#' . $order->get_order_number()
);
$primary     = isset( $brand['primary'] ) ? (string) $brand['primary'] : '#475e76';
$brand_phone = isset( $brand['phone'] ) ? (string) $brand['phone'] : '';

include __DIR__ . '/_partials/header.php';
?>

<p style="margin:0 0 18px;"><?php echo esc_html( $greeting ); ?></p>

<p style="margin:0 0 18px;">
    <?php esc_html_e( 'Sie haben kürzlich Produkte in unserem Shop ausgewählt, die Bestellung aber noch nicht abgeschlossen. Ihr Warenkorb ist noch für Sie reserviert.', 'werbeauf-customs' ); ?>
</p>

<p style="margin:0 0 24px;">
    <?php esc_html_e( 'Falls Sie es sich anders überlegt haben — kein Problem. Falls Sie weitermachen möchten, geht es hier direkt zur Bezahlung:', 'werbeauf-customs' ); ?>
</p>

<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:0 0 28px;">
    <tr>
        <td align="center">
            <a href="<?php echo esc_url( $retry_url ); ?>"
               style="display:inline-block;padding:14px 32px;background:<?php echo esc_attr( $primary ); ?>;color:#ffffff;text-decoration:none;border-radius:6px;font-weight:600;font-size:15px;">
                <?php esc_html_e( 'Bestellung abschließen', 'werbeauf-customs' ); ?>
            </a>
        </td>
    </tr>
</table>

<?php if ( $brand_phone !== '' ) : ?>
<p style="margin:0 0 18px;color:#475e76;">
    <?php
    printf(
        /* translators: %s phone number */
        esc_html__( 'Fragen zur Bestellung? Rufen Sie uns gerne an: %s (Mo–Fr 8:30–19:00 Uhr).', 'werbeauf-customs' ),
        esc_html( $brand_phone )
    );
    ?>
</p>
<?php endif; ?>

<p style="margin:24px 0 0;">
    <?php esc_html_e( 'Herzliche Grüße', 'werbeauf-customs' ); ?><br>
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

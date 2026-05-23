<?php
/**
 * Customer Recovery Template — Soft-Follow-up nach Zahlungsfehlschlag.
 * Klinik-Touch: persönlich, hilfsbereit, kein Druck.
 *
 * Erwartete Variablen:
 *   $brand          array
 *   $order          WC_Order
 *   $rule           array
 *   $hours_ago      int
 *   $retry_url      string  Direktlink zum Bezahlen
 *   $greeting       string  z.B. "Liebe Frau Mustermann," oder "Liebe Kundin,"
 *
 * @package werbeauf-customs/sso
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$heading = sprintf(
    /* translators: %s order number */
    __( 'Brauchen Sie Hilfe bei Ihrer Bestellung %s?', 'werbeauf-customs' ),
    '#' . $order->get_order_number()
);
$primary = isset( $brand['primary'] ) ? (string) $brand['primary'] : '#475e76';
$brand_phone = isset( $brand['phone'] ) ? (string) $brand['phone'] : '';
$brand_email = isset( $brand['email'] ) ? (string) $brand['email'] : '';

include __DIR__ . '/_partials/header.php';
?>

<p style="margin:0 0 18px;"><?php echo esc_html( $greeting ); ?></p>

<p style="margin:0 0 18px;">
    <?php
    printf(
        /* translators: %d hours */
        esc_html__( 'wir haben gesehen, dass die Bezahlung Ihrer Bestellung vor %d Stunden nicht erfolgreich war.', 'werbeauf-customs' ),
        (int) $hours_ago
    );
    ?>
</p>

<p style="margin:0 0 24px;">
    <?php esc_html_e( 'Das ist kein Problem — manchmal werden Kreditkarten von Banken vorübergehend blockiert oder es gibt einen technischen Fehler. So können Sie weitermachen:', 'werbeauf-customs' ); ?>
</p>

<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:0 0 28px;">
    <tr>
        <td align="center">
            <a href="<?php echo esc_url( $retry_url ); ?>"
               style="display:inline-block;padding:14px 32px;background:<?php echo esc_attr( $primary ); ?>;color:#ffffff;text-decoration:none;border-radius:6px;font-weight:600;font-size:15px;">
                <?php esc_html_e( 'Hier erneut bezahlen', 'werbeauf-customs' ); ?>
            </a>
        </td>
    </tr>
</table>

<p style="margin:0 0 12px;font-weight:600;color:<?php echo esc_attr( $primary ); ?>;">
    <?php esc_html_e( 'Lieber persönlich?', 'werbeauf-customs' ); ?>
</p>

<ul style="margin:0 0 24px;padding-left:18px;color:#475e76;">
    <li style="margin-bottom:6px;">
        <?php
        printf(
            /* translators: %s phone number */
            esc_html__( 'Rufen Sie uns an: %s (Mo–Fr 8:30–19:00 Uhr)', 'werbeauf-customs' ),
            esc_html( $brand_phone )
        );
        ?>
    </li>
    <li style="margin-bottom:6px;">
        <?php
        printf(
            /* translators: %s email */
            esc_html__( 'Antworten Sie einfach auf diese E-Mail oder schreiben an %s', 'werbeauf-customs' ),
            esc_html( $brand_email )
        );
        ?>
    </li>
</ul>

<p style="margin:0 0 18px;">
    <?php esc_html_e( 'Ihre Bestellung ist noch bis morgen Abend für Sie reserviert.', 'werbeauf-customs' ); ?>
</p>

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

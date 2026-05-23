<?php
/**
 * Admin Reminder Template — Mitarbeiterin-Erinnerung an einer offenen
 * Bestellung. Klinik-Tonfall: sachlich, deutlich, ohne aggressiv zu sein.
 *
 * Erwartete Variablen:
 *   $brand        array
 *   $order        WC_Order
 *   $rule         array
 *   $age_hours    int
 *   $status_label string
 *   $order_total  string  (gestripte wc_price)
 *   $cust_name    string
 *   $sso_button   string  Optional CTA-HTML (Bestellung ansehen via SSO)
 *   $admin_url    string  Fallback-URL
 *
 * @package werbeauf-customs/sso
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$heading = sprintf(
    /* translators: %s order number */
    __( 'Versand fällig: Bestellung %s', 'werbeauf-customs' ),
    '#' . $order->get_order_number()
);
$primary = isset( $brand['primary'] ) ? (string) $brand['primary'] : '#475e76';

include __DIR__ . '/_partials/header.php';
?>

<p style="margin:0 0 18px;">
    <?php
    printf(
        /* translators: 1: hours, 2: status label */
        esc_html__( 'Eine bezahlte Bestellung wartet seit %1$d Stunden im Status "%2$s" auf den Versand. Bitte handeln Sie heute noch.', 'werbeauf-customs' ),
        (int) $age_hours,
        esc_html( $status_label )
    );
    ?>
</p>

<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background:#f7f8fa;border-radius:6px;margin:0 0 24px;">
    <tr>
        <td style="padding:16px 20px;">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                <tr>
                    <td style="padding:4px 0;font-size:13px;color:#769cc1;text-transform:uppercase;letter-spacing:0.6px;font-weight:600;">
                        <?php esc_html_e( 'Bestellung', 'werbeauf-customs' ); ?>
                    </td>
                    <td style="padding:4px 0;font-size:15px;color:<?php echo esc_attr( $primary ); ?>;font-weight:600;text-align:right;">
                        #<?php echo esc_html( $order->get_order_number() ); ?>
                    </td>
                </tr>
                <?php if ( $cust_name !== '' ) : ?>
                <tr>
                    <td style="padding:4px 0;font-size:13px;color:#769cc1;text-transform:uppercase;letter-spacing:0.6px;font-weight:600;">
                        <?php esc_html_e( 'Kundin', 'werbeauf-customs' ); ?>
                    </td>
                    <td style="padding:4px 0;font-size:15px;color:<?php echo esc_attr( $primary ); ?>;text-align:right;">
                        <?php echo esc_html( $cust_name ); ?>
                    </td>
                </tr>
                <?php endif; ?>
                <tr>
                    <td style="padding:4px 0;font-size:13px;color:#769cc1;text-transform:uppercase;letter-spacing:0.6px;font-weight:600;">
                        <?php esc_html_e( 'Summe', 'werbeauf-customs' ); ?>
                    </td>
                    <td style="padding:4px 0;font-size:15px;color:<?php echo esc_attr( $primary ); ?>;font-weight:600;text-align:right;">
                        <?php echo esc_html( $order_total ); ?>
                    </td>
                </tr>
                <tr>
                    <td style="padding:4px 0;font-size:13px;color:#769cc1;text-transform:uppercase;letter-spacing:0.6px;font-weight:600;">
                        <?php esc_html_e( 'Aktueller Status', 'werbeauf-customs' ); ?>
                    </td>
                    <td style="padding:4px 0;font-size:15px;color:<?php echo esc_attr( $primary ); ?>;text-align:right;">
                        <?php echo esc_html( $status_label ); ?>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<?php if ( ! empty( $sso_button ) ) : ?>
    <p style="margin:0 0 20px;text-align:center;"><?php echo $sso_button; // phpcs:ignore WordPress.Security.EscapeOutput ?></p>
<?php else : ?>
    <p style="margin:0 0 20px;text-align:center;">
        <a href="<?php echo esc_url( $admin_url ); ?>"
           style="display:inline-block;padding:14px 28px;background:<?php echo esc_attr( $primary ); ?>;color:#ffffff;text-decoration:none;border-radius:6px;font-weight:600;font-size:14px;">
            <?php esc_html_e( 'Im Dashboard öffnen', 'werbeauf-customs' ); ?>
        </a>
    </p>
<?php endif; ?>

<p style="margin:24px 0 0;font-size:13px;color:#9aa9b6;">
    <?php esc_html_e( 'Sie erhalten diese Erinnerung automatisch vom Active Order System. Wenn Sie nichts unternehmen, folgt eine weitere Nachricht in 24 Stunden.', 'werbeauf-customs' ); ?>
</p>

<?php
include __DIR__ . '/_partials/footer.php';

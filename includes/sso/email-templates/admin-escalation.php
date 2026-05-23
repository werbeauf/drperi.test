<?php
/**
 * Admin Escalation Template — Entscheidungs-Email für die Mitarbeiterin
 * nach mehrfach verfehlter Reaktion. Erkennbarer Eskalations-Tonfall
 * (rot), aber bleibt höflich.
 *
 * Erwartete Variablen siehe admin-reminder.php.
 *
 * @package werbeauf-customs/sso
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$heading = sprintf(
    /* translators: %s order number */
    __( 'Dringend: Bestellung %s braucht Aufmerksamkeit', 'werbeauf-customs' ),
    '#' . $order->get_order_number()
);
$primary = isset( $brand['primary'] ) ? (string) $brand['primary'] : '#475e76';
$alert   = '#d63638';

include __DIR__ . '/_partials/header.php';
?>

<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background:#fdf2f2;border-left:3px solid <?php echo esc_attr( $alert ); ?>;border-radius:4px;margin:0 0 24px;">
    <tr>
        <td style="padding:14px 18px;font-size:14px;color:<?php echo esc_attr( $alert ); ?>;">
            <strong><?php esc_html_e( 'Entscheidung erforderlich.', 'werbeauf-customs' ); ?></strong><br>
            <?php
            printf(
                /* translators: 1: hours, 2: status label */
                esc_html__( 'Diese Bestellung steht seit %1$d Stunden im Status "%2$s". Die Kundin wurde bereits über die Verzögerung informiert.', 'werbeauf-customs' ),
                (int) $age_hours,
                esc_html( $status_label )
            );
            ?>
        </td>
    </tr>
</table>

<p style="margin:0 0 18px;">
    <?php esc_html_e( 'Bitte treffen Sie heute eine Entscheidung: versenden, stornieren oder die Kundin direkt kontaktieren. Die Bestellung wird in vier Tagen automatisch storniert, wenn nichts passiert.', 'werbeauf-customs' ); ?>
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
            </table>
        </td>
    </tr>
</table>

<?php if ( ! empty( $sso_button ) ) : ?>
    <p style="margin:0 0 12px;text-align:center;"><?php echo $sso_button; // phpcs:ignore WordPress.Security.EscapeOutput ?></p>
<?php endif; ?>

<p style="margin:0 0 24px;text-align:center;">
    <a href="<?php echo esc_url( $admin_url ); ?>"
       style="display:inline-block;padding:12px 24px;background:<?php echo esc_attr( $alert ); ?>;color:#ffffff;text-decoration:none;border-radius:6px;font-weight:600;font-size:14px;">
        <?php esc_html_e( 'Bestellung jetzt im Dashboard öffnen', 'werbeauf-customs' ); ?>
    </a>
</p>

<p style="margin:0;font-size:13px;color:#9aa9b6;text-align:center;">
    <?php esc_html_e( 'Automatischer Storno: in 4 Tagen.', 'werbeauf-customs' ); ?>
</p>

<?php
include __DIR__ . '/_partials/footer.php';

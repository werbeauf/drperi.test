<?php
/**
 * Email-Footer-Partial — Klinik-Adresse, Kontakt, Datenschutz.
 *
 * Erwartete Variablen:
 *   $brand  array  Brand-Assets
 *
 * @package werbeauf-customs/sso
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$brand_site = isset( $brand['site_name'] ) ? (string) $brand['site_name'] : '';
$brand_url  = isset( $brand['site_url'] )  ? (string) $brand['site_url']  : home_url( '/' );
$address    = isset( $brand['address'] )   ? wp_strip_all_tags( (string) $brand['address'] ) : '';
$phone      = isset( $brand['phone'] )     ? (string) $brand['phone'] : '';
$email      = isset( $brand['email'] )     ? (string) $brand['email'] : '';
$primary    = isset( $brand['primary'] )   ? (string) $brand['primary'] : '#475e76';
$muted      = '#9aa9b6';
?>
                </td><!-- /body cell -->
                </tr>

                <!-- Footer cell -->
                <tr>
                    <td style="padding:24px 36px 32px;border-top:1px solid #e5eaee;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;font-size:12px;line-height:1.6;color:<?php echo esc_attr( $muted ); ?>;text-align:center;">
                        <div style="margin-top:8px;color:<?php echo esc_attr( $primary ); ?>;font-weight:600;">
                            <?php echo esc_html( $brand_site ); ?>
                        </div>
                        <?php if ( $address !== '' ) : ?>
                            <div style="margin-top:4px;"><?php echo esc_html( $address ); ?></div>
                        <?php endif; ?>
                        <div style="margin-top:8px;">
                            <?php if ( $phone !== '' ) : ?>
                                <a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $phone ) ); ?>" style="color:<?php echo esc_attr( $muted ); ?>;text-decoration:none;">📞 <?php echo esc_html( $phone ); ?></a>
                            <?php endif; ?>
                            <?php if ( $phone !== '' && $email !== '' ) echo '<span style="margin:0 6px;">·</span>'; ?>
                            <?php if ( $email !== '' ) : ?>
                                <a href="mailto:<?php echo esc_attr( $email ); ?>" style="color:<?php echo esc_attr( $muted ); ?>;text-decoration:none;">✉ <?php echo esc_html( $email ); ?></a>
                            <?php endif; ?>
                        </div>
                        <div style="margin-top:12px;">
                            <a href="<?php echo esc_url( $brand_url ); ?>" style="color:<?php echo esc_attr( $muted ); ?>;text-decoration:underline;"><?php echo esc_html( $brand_url ); ?></a>
                        </div>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>

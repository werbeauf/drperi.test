<?php
/**
 * Email-Header-Partial — Brand-Logo + farbige Akzent-Linie.
 *
 * Erwartete Variablen (vom Loader gesetzt):
 *   $brand    array  Brand-Assets aus wa_workflow_brand_assets()
 *   $heading  string Optional. Wenn gesetzt: <h1> direkt im Header-Bereich.
 *
 * @package werbeauf-customs/sso
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$brand_logo  = isset( $brand['logo_url'] ) ? (string) $brand['logo_url'] : '';
$brand_site  = isset( $brand['site_name'] ) ? (string) $brand['site_name'] : '';
$brand_url   = isset( $brand['site_url'] ) ? (string) $brand['site_url'] : home_url( '/' );
$primary     = isset( $brand['primary'] ) ? (string) $brand['primary'] : '#475e76';
$heading_str = isset( $heading ) ? (string) $heading : '';
?>
<!doctype html>
<html lang="de-DE">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo esc_html( $heading_str !== '' ? $heading_str . ' — ' . $brand_site : $brand_site ); ?></title>
</head>
<body style="margin:0;padding:0;background:#f7f8fa;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;color:#475e76;">
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background:#f7f8fa;">
    <tr>
        <td align="center" style="padding:24px 12px;">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="600" style="max-width:600px;width:100%;background:#ffffff;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,0.04);">
                <!-- Header -->
                <tr>
                    <td style="padding:32px 36px 16px;border-bottom:3px solid <?php echo esc_attr( $primary ); ?>;">
                        <a href="<?php echo esc_url( $brand_url ); ?>" style="display:inline-block;text-decoration:none;">
                            <?php if ( $brand_logo ) : ?>
                                <img src="<?php echo esc_url( $brand_logo ); ?>" alt="<?php echo esc_attr( $brand_site ); ?>" width="120" style="display:block;border:0;width:120px;max-width:120px;height:auto;">
                            <?php else : ?>
                                <span style="font-size:20px;font-weight:700;color:<?php echo esc_attr( $primary ); ?>;letter-spacing:-0.5px;"><?php echo esc_html( $brand_site ); ?></span>
                            <?php endif; ?>
                        </a>
                    </td>
                </tr>

                <?php if ( $heading_str !== '' ) : ?>
                <tr>
                    <td style="padding:28px 36px 0;">
                        <h1 style="margin:0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;font-size:22px;line-height:1.3;font-weight:700;color:<?php echo esc_attr( $primary ); ?>;"><?php echo esc_html( $heading_str ); ?></h1>
                    </td>
                </tr>
                <?php endif; ?>

                <!-- Body cell -->
                <tr>
                    <td style="padding:24px 36px 0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;font-size:15px;line-height:1.6;color:#475e76;">

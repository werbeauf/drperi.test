<?php
if (!defined('ABSPATH')) exit;

$logo_url = '';
$cta_btn  = null;

// Logo: use Divi theme logo
$et_divi = get_option('et_divi');
if ( is_array($et_divi) && !empty($et_divi['divi_logo']) ) {
    $logo_url = $et_divi['divi_logo'];
}

// Header ACF group via WPML-aware helper (options_{lang} → options_{default} → options).
$header_group = function_exists( 'wa_get_options_field' )
    ? wa_get_options_field( 'header' )
    : ( function_exists( 'get_field' ) ? get_field( 'header', 'option' ) : null );

if ( is_array( $header_group ) && ! empty( $header_group['cta_button'] ) ) {
    $cta_btn = $header_group['cta_button'];
}

$wa_show_cart = function_exists( 'wc_get_cart_url' ) && class_exists( 'WooCommerce' );
$wa_cart_url  = $wa_show_cart ? wc_get_cart_url() : '';
$wa_cart_n    = 0;
if ( $wa_show_cart && function_exists( 'WC' ) && WC()->cart ) {
    $wa_cart_n = (int) WC()->cart->get_cart_contents_count();
}
?>

<header id="wa-header">
    <div class="wa-header-container">

        <div class="wa-header-logo">
            <a href="<?php echo esc_url( home_url('/') ); ?>">
<?php if ( $logo_url ) : ?>
                <img src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr( get_bloginfo('name') ); ?>">
<?php else : ?>
                <span class="wa-logo-text"><?php echo esc_html( get_bloginfo('name') ); ?></span>
<?php endif; ?>
            </a>
        </div>

        <nav class="wa-header-nav">
            <?php
            wp_nav_menu( array(
                'theme_location' => 'primary-menu',
                'container'      => false,
                'menu_class'     => 'wa-nav-list',
                'fallback_cb'    => false,
                'depth'          => 1,
            ) );
            ?>
        </nav>

        <div class="wa-header-actions">
<?php if ( $wa_show_cart && $wa_cart_url ) : ?>
            <a href="<?php echo esc_url( $wa_cart_url ); ?>"
               class="wa-header-cart"
               aria-label="<?php echo esc_attr( sprintf( _n( 'Zum Warenkorb, %d Artikel', 'Zum Warenkorb, %d Artikel', $wa_cart_n, 'werbeauf-customs' ), $wa_cart_n ) ); ?>">
                <span class="wa-header-cart__icon" aria-hidden="true">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" focusable="false">
                        <path d="M9 22a1 1 0 1 0 0-2 1 1 0 0 0 0 2zm7 0a1 1 0 1 0 0-2 1 1 0 0 0 0 2z" fill="currentColor"/>
                        <path d="M3 4h2l.4 2M7 13h10l3-8H6.4M7 13 5.4 5M7 13l-1.3 5.2A1 1 0 0 0 6.7 20h10.6a1 1 0 0 0 .98-.8L19 9" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </span>
                <span id="wa-header-cart-count-d" class="wa-header-cart__count<?php echo $wa_cart_n === 0 ? ' wa-header-cart__count--empty' : ''; ?>" aria-hidden="true"><?php echo esc_html( function_exists( 'wa_header_cart_count_display' ) ? wa_header_cart_count_display() : '0' ); ?></span>
            </a>
<?php endif; ?>
<?php if ( $cta_btn && !empty($cta_btn['url']) ) : ?>
            <a href="<?php echo esc_url($cta_btn['url']); ?>"
               class="wa-header-cta"
               <?php echo !empty($cta_btn['target']) ? 'target="' . esc_attr($cta_btn['target']) . '"' : ''; ?>>
                <?php echo esc_html($cta_btn['title'] ?: __('Kontakt', 'werbeauf-customs')); ?>
            </a>
<?php endif; ?>
        </div>

        <button class="wa-header-hamburger" aria-label="<?php esc_attr_e('Menu', 'werbeauf-customs'); ?>" aria-expanded="false">
            <span class="wa-hamburger-line"></span>
            <span class="wa-hamburger-line"></span>
            <span class="wa-hamburger-line"></span>
        </button>

    </div>
</header>

<div class="wa-mobile-dropdown" aria-hidden="true">
    <nav class="wa-mobile-dropdown-nav">
        <?php
        wp_nav_menu( array(
            'theme_location' => 'primary-menu',
            'container'      => false,
            'menu_class'     => 'wa-mobile-nav-list',
            'fallback_cb'    => false,
            'depth'          => 1,
        ) );
        ?>
<?php if ( $wa_show_cart && $wa_cart_url ) : ?>
        <a href="<?php echo esc_url( $wa_cart_url ); ?>"
           class="wa-mobile-cart"
           aria-label="<?php echo esc_attr( sprintf( _n( 'Zum Warenkorb, %d Artikel', 'Zum Warenkorb, %d Artikel', $wa_cart_n, 'werbeauf-customs' ), $wa_cart_n ) ); ?>">
            <span class="wa-mobile-cart__label"><?php esc_html_e( 'Warenkorb', 'werbeauf-customs' ); ?></span>
            <span id="wa-header-cart-count-m" class="wa-header-cart__count wa-mobile-cart__badge<?php echo $wa_cart_n === 0 ? ' wa-header-cart__count--empty' : ''; ?>" aria-hidden="true"><?php echo esc_html( function_exists( 'wa_header_cart_count_display' ) ? wa_header_cart_count_display() : '0' ); ?></span>
        </a>
<?php endif; ?>
<?php if ( $cta_btn && !empty($cta_btn['url']) ) : ?>
        <a href="<?php echo esc_url($cta_btn['url']); ?>"
           class="wa-mobile-cta"
           <?php echo !empty($cta_btn['target']) ? 'target="' . esc_attr($cta_btn['target']) . '"' : ''; ?>>
            <?php echo esc_html($cta_btn['title'] ?: __('Kontakt', 'werbeauf-customs')); ?>
        </a>
<?php endif; ?>
    </nav>
</div>

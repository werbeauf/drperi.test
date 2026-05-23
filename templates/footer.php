<?php
/* ============================================================
   DATEI: templates/footer.php
   ZWECK: Markup des Custom-Footers.

   Layout (2 Zeilen):
     Row 1 (align-items: center) -> 1/4 Brand (Logo, Adresse, Email,
                                                Telefon, Social-Icons)
                                    1/4 Newsletter-Intro (ACF Lead-Text)
                                    2/4 Newsletter-Form (Shortcode)
     Row 2 (align-items: start)  -> 1/4 Oeffnungszeiten (ACF Repeater)
                                    1/4 Aktuelle Beitraege (post_type=blog, 3 latest)
                                    1/4 Footer Menu 2 (theme_location footer-menu-2)
                                    1/4 Footer Menu 3 (theme_location footer-menu-3)
     Bottom -> Divider + Legal-Menu (theme_location footer-menu,
               .legal-menu Stil) + Copyright (full width, zentriert)

   Eingebunden via footer-controller.php (wp_footer prio 5).
   Inhalte werden aus der ACF-Options-Gruppe "footer" gelesen
   (mit WPML-Fallback-Kette analog zu header.php).
============================================================ */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Liest ein Feld aus der ACF-Options-Gruppe "footer".
 * Duenner Wrapper um den zentralen WPML-aware Helper aus
 * includes/core/wpml-helpers.php.
 */
if ( ! function_exists( 'wa_get_footer_field' ) ) {
    function wa_get_footer_field( $key ) {
        return function_exists( 'wa_get_options_field' )
            ? wa_get_options_field( 'footer', $key )
            : null;
    }
}

/**
 * Liefert ein Inline-SVG fuer einen Kontakt-Typ (address, email, phone).
 * Bewusst kompakte 14px-Icons, stroke=currentColor.
 */
if ( ! function_exists( 'wa_footer_contact_icon' ) ) {
    function wa_footer_contact_icon( $type ) {
        $icons = array(
            'address' => '<svg class="wa-footer-contact__icon" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0z"/><circle cx="12" cy="10" r="3"/></svg>',
            'email'   => '<svg class="wa-footer-contact__icon" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>',
            'phone'   => '<svg class="wa-footer-contact__icon" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.79 19.79 0 0 1 2.12 4.18 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.37 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.33 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/></svg>',
        );
        return isset( $icons[ $type ] ) ? $icons[ $type ] : '';
    }
}

/**
 * Liefert ein Inline-SVG fuer eine bekannte Social-Plattform.
 * stroke=currentColor, sodass die Icons die CSS-Farbe erben.
 */
if ( ! function_exists( 'wa_footer_social_icon' ) ) {
    function wa_footer_social_icon( $platform ) {
        $svgs = array(
            'instagram' => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="0.6" fill="currentColor" stroke="none"/></svg>',
            'facebook'  => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M14 9h3V6h-3a3 3 0 0 0-3 3v2H8v3h3v7h3v-7h2.5l.5-3H14V9.5a.5.5 0 0 1 .5-.5z"/></svg>',
            'tiktok'    => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M14 4v9.5a3.5 3.5 0 1 1-3.5-3.5"/><path d="M14 4a4 4 0 0 0 4 4"/></svg>',
            'youtube'   => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="3" y="6" width="18" height="12" rx="3"/><path d="M10 9.5v5l4.5-2.5z" fill="currentColor" stroke="none"/></svg>',
            'linkedin'  => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="3" y="3" width="18" height="18" rx="3"/><path d="M8 10v7M8 7v.01M12 17v-4a2 2 0 0 1 4 0v4M12 13v4"/></svg>',
            'x'         => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M5 5l14 14M19 5L5 19"/></svg>',
            'twitter'   => '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M5 5l14 14M19 5L5 19"/></svg>',
        );

        $key = strtolower( (string) $platform );
        return isset( $svgs[ $key ] ) ? $svgs[ $key ] : '';
    }
}

// Logo aus den Divi Theme Optionen (gleicher Loader wie header.php).
$wa_footer_logo = '';
$et_divi        = get_option( 'et_divi' );
if ( is_array( $et_divi ) && ! empty( $et_divi['divi_logo'] ) ) {
    $wa_footer_logo = $et_divi['divi_logo'];
}

$wa_footer_address    = wa_get_footer_field( 'address' );
$wa_footer_email      = wa_get_footer_field( 'email' );
$wa_footer_phone      = wa_get_footer_field( 'phone' );
$wa_footer_hours      = wa_get_footer_field( 'opening_hours' );
$wa_footer_nl_heading = wa_get_footer_field( 'newsletter_heading' );
$wa_footer_nl_intro   = wa_get_footer_field( 'newsletter_intro' );
$wa_footer_socials    = wa_get_footer_field( 'social_links' );
$wa_footer_copyright  = wa_get_footer_field( 'copyright_text' );
$wa_has_menu_1        = has_nav_menu( 'footer-menu-2' );
$wa_has_menu_2        = has_nav_menu( 'footer-menu-3' );
$wa_has_menu_legal    = has_nav_menu( 'footer-menu' );
$wa_phone_tel         = $wa_footer_phone ? preg_replace( '/[^+0-9]/', '', $wa_footer_phone ) : '';
$wa_hours_rows        = is_array( $wa_footer_hours ) ? $wa_footer_hours : array();

/**
 * Liefert den im WP-Admin vergebenen Menue-Namen einer theme_location.
 * Fallback: leerer String, falls kein Menue zugewiesen.
 */
$wa_get_menu_title = function ( $location ) {
    $locations = get_nav_menu_locations();
    if ( empty( $locations[ $location ] ) ) {
        return '';
    }
    $menu = wp_get_nav_menu_object( $locations[ $location ] );
    return $menu ? (string) $menu->name : '';
};

// Latest 3 Blog-Posts (Custom Post Type 'blog'). Sprach-scoped via WPML.
$wa_footer_blog_args = array(
    'post_type'      => 'blog',
    'posts_per_page' => 3,
    'post_status'    => 'publish',
    'orderby'        => 'date',
    'order'          => 'DESC',
    'no_found_rows'  => true,
);
if ( function_exists( 'wa_wpml_current_lang' ) ) {
    $wa_footer_blog_args['lang'] = wa_wpml_current_lang();
}
$wa_footer_blog_posts = get_posts( $wa_footer_blog_args );
?>
<footer id="wa-footer" class="wa-footer" role="contentinfo">
    <div class="wa-footer-container">

        <div class="wa-footer-row wa-footer-row--1">

            <div class="wa-footer-col wa-footer-col--brand">
                <a class="wa-footer-logo" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
<?php if ( $wa_footer_logo ) : ?>
                    <img src="<?php echo esc_url( $wa_footer_logo ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
<?php else : ?>
                    <span class="wa-footer-logo__text"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></span>
<?php endif; ?>
                </a>

<?php if ( $wa_footer_address || $wa_footer_email || $wa_footer_phone ) : ?>
                <address class="wa-footer-contact">
<?php if ( $wa_footer_address ) : ?>
                    <p class="wa-footer-contact__row wa-footer-contact__row--address">
                        <?php echo wa_footer_contact_icon( 'address' ); // safe: kuratiertes inline-SVG ?>
                        <span class="wa-footer-contact__text"><?php echo wp_kses_post( $wa_footer_address ); ?></span>
                    </p>
<?php endif; ?>
<?php if ( $wa_footer_email ) : ?>
                    <p class="wa-footer-contact__row wa-footer-contact__row--email">
                        <?php echo wa_footer_contact_icon( 'email' ); // safe: kuratiertes inline-SVG ?>
                        <a class="wa-footer-contact__link" href="mailto:<?php echo esc_attr( $wa_footer_email ); ?>">
                            <?php echo esc_html( $wa_footer_email ); ?>
                        </a>
                    </p>
<?php endif; ?>
<?php if ( $wa_footer_phone ) : ?>
                    <p class="wa-footer-contact__row wa-footer-contact__row--phone">
                        <?php echo wa_footer_contact_icon( 'phone' ); // safe: kuratiertes inline-SVG ?>
                        <a class="wa-footer-contact__link" href="tel:<?php echo esc_attr( $wa_phone_tel ); ?>">
                            <?php echo esc_html( $wa_footer_phone ); ?>
                        </a>
                    </p>
<?php endif; ?>
                </address>
<?php endif; ?>

<?php if ( is_array( $wa_footer_socials ) && ! empty( $wa_footer_socials ) ) : ?>
                <ul class="wa-footer-social" aria-label="<?php esc_attr_e( 'Social Media', 'werbeauf-customs' ); ?>">
<?php foreach ( $wa_footer_socials as $row ) :
        if ( ! is_array( $row ) ) {
            continue;
        }
        $platform = isset( $row['platform'] ) ? (string) $row['platform'] : '';
        $url      = isset( $row['url'] )      ? (string) $row['url']      : '';
        if ( empty( $platform ) || empty( $url ) ) {
            continue;
        }
        $icon = wa_footer_social_icon( $platform );
        if ( empty( $icon ) ) {
            continue;
        }
        $label = ucfirst( $platform );
?>
                    <li class="wa-footer-social__item">
                        <a class="wa-footer-social__link" href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php echo esc_attr( $label ); ?>">
                            <?php echo $icon; // safe: kuratiertes inline-SVG ?>
                        </a>
                    </li>
<?php endforeach; ?>
                </ul>
<?php endif; ?>
            </div>

<?php if ( $wa_footer_nl_heading || $wa_footer_nl_intro ) : ?>
            <div class="wa-footer-col wa-footer-col--nl-intro">
<?php if ( $wa_footer_nl_heading ) : ?>
                <h4 class="wa-footer-newsletter__heading"><?php echo esc_html( $wa_footer_nl_heading ); ?></h4>
<?php endif; ?>
<?php if ( $wa_footer_nl_intro ) : ?>
                <p class="wa-footer-newsletter__intro"><?php echo esc_html( $wa_footer_nl_intro ); ?></p>
<?php endif; ?>
            </div>
<?php endif; ?>

            <div class="wa-footer-col wa-footer-col--newsletter">
                <?php echo do_shortcode( '[wa_newsletter_signup variant="footer"]' ); ?>
            </div>

        </div><!-- /.wa-footer-row--1 -->

        <div class="wa-footer-row wa-footer-row--2">

<?php if ( ! empty( $wa_hours_rows ) ) : ?>
            <div class="wa-footer-col wa-footer-col--hours">
                <p class="wa-footer-coltitle"><?php esc_html_e( 'Oeffnungszeiten', 'werbeauf-customs' ); ?></p>
                <dl class="wa-footer-hours">
<?php foreach ( $wa_hours_rows as $row ) :
    if ( ! is_array( $row ) ) {
        continue;
    }
    $day   = isset( $row['day'] )   ? (string) $row['day']   : '';
    $hours = isset( $row['hours'] ) ? (string) $row['hours'] : '';
    if ( $day === '' && $hours === '' ) {
        continue;
    }
?>
                    <div class="wa-footer-hours__row">
                        <dt class="wa-footer-hours__day"><?php echo esc_html( $day ); ?></dt>
                        <dd class="wa-footer-hours__time"><?php echo esc_html( $hours ); ?></dd>
                    </div>
<?php endforeach; ?>
                </dl>
            </div>
<?php endif; ?>

<?php if ( ! empty( $wa_footer_blog_posts ) ) : ?>
            <div class="wa-footer-col wa-footer-col--posts">
                <p class="wa-footer-coltitle"><?php esc_html_e( 'Aktuelle Beitraege', 'werbeauf-customs' ); ?></p>
                <ul class="wa-footer-posts">
<?php foreach ( $wa_footer_blog_posts as $wa_fp ) : ?>
                    <li class="wa-footer-posts__item">
                        <a class="wa-footer-posts__link" href="<?php echo esc_url( get_permalink( $wa_fp ) ); ?>">
                            <span class="wa-footer-posts__title"><?php echo esc_html( get_the_title( $wa_fp ) ); ?></span>
                            <time class="wa-footer-posts__date" datetime="<?php echo esc_attr( get_the_date( 'c', $wa_fp ) ); ?>">
                                <?php echo esc_html( get_the_date( '', $wa_fp ) ); ?>
                            </time>
                        </a>
                    </li>
<?php endforeach; ?>
                </ul>
            </div>
<?php endif; ?>

<?php if ( $wa_has_menu_1 ) :
    $wa_menu_1_title = $wa_get_menu_title( 'footer-menu-2' );
?>
            <div class="wa-footer-col wa-footer-col--menu">
<?php if ( $wa_menu_1_title ) : ?>
                <p class="wa-footer-coltitle"><?php echo esc_html( $wa_menu_1_title ); ?></p>
<?php endif; ?>
                <nav class="wa-footer-menu" aria-label="<?php echo esc_attr( $wa_menu_1_title ?: __( 'Footer Menu 2', 'werbeauf-customs' ) ); ?>">
                    <?php
                    wp_nav_menu( array(
                        'theme_location' => 'footer-menu-2',
                        'container'      => false,
                        'menu_class'     => 'wa-footer-ul',
                        'fallback_cb'    => false,
                        'depth'          => 1,
                    ) );
                    ?>
                </nav>
            </div>
<?php endif; ?>

<?php if ( $wa_has_menu_2 ) :
    $wa_menu_2_title = $wa_get_menu_title( 'footer-menu-3' );
?>
            <div class="wa-footer-col wa-footer-col--menu">
<?php if ( $wa_menu_2_title ) : ?>
                <p class="wa-footer-coltitle"><?php echo esc_html( $wa_menu_2_title ); ?></p>
<?php endif; ?>
                <nav class="wa-footer-menu" aria-label="<?php echo esc_attr( $wa_menu_2_title ?: __( 'Footer Menu 3', 'werbeauf-customs' ) ); ?>">
                    <?php
                    wp_nav_menu( array(
                        'theme_location' => 'footer-menu-3',
                        'container'      => false,
                        'menu_class'     => 'wa-footer-ul',
                        'fallback_cb'    => false,
                        'depth'          => 1,
                    ) );
                    ?>
                </nav>
            </div>
<?php endif; ?>

        </div><!-- /.wa-footer-row--2 -->

        <span class="wa-footer-divider" aria-hidden="true"></span>

<?php if ( $wa_has_menu_legal ) : ?>
        <nav class="wa-footer-legal legal-menu" aria-label="<?php esc_attr_e( 'Rechtliches', 'werbeauf-customs' ); ?>">
            <?php
            wp_nav_menu( array(
                'theme_location' => 'footer-menu',
                'container'      => false,
                'menu_class'     => 'wa-footer-ul',
                'fallback_cb'    => false,
                'depth'          => 1,
            ) );
            ?>
        </nav>
<?php endif; ?>

        <p class="wa-footer-copyright">
            <?php
            $year = date_i18n( 'Y' );
            printf(
                /* translators: 1: year, 2: copyright text from ACF */
                esc_html__( '© %1$s %2$s', 'werbeauf-customs' ),
                esc_html( $year ),
                esc_html( (string) $wa_footer_copyright )
            );
            ?>
        </p>

    </div>
</footer>

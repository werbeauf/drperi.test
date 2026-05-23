<?php
/**
 * Shortcode: [global-blog3-authorbox]
 * Autoren-Box mit Avatar, Name, Bio und Link auf alle Beitraege des Autors.
 * Sprachauswahl ueber den Plugin-WPML-Helper (de/en).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Zusaetzliches Profilfeld im Backend: URL fuer benutzerdefiniertes Profilbild.
add_filter('user_contactmethods', function($methods) {
    $methods['custom_profile_image'] = __( 'Benutzerdefiniertes Profilbild (URL)', 'werbeauf-customs' );
    return $methods;
});

add_shortcode('global-blog3-authorbox', function() {
    $author_id          = get_the_author_meta('ID');
    $author_name        = get_the_author_meta('display_name');
    $author_description = get_the_author_meta('description');

    $custom_pic = get_the_author_meta('custom_profile_image', $author_id);
    if (!empty($custom_pic)) {
        $author_avatar = '<img src="' . esc_url($custom_pic) . '" alt="' . esc_attr($author_name) . '" width="120" height="120" class="avatar" />';
    } else {
        $author_avatar = get_avatar($author_id, 120);
    }
    $author_posts_url = get_author_posts_url($author_id);

    if ( empty($author_description) ) {
        $author_description = __( 'Bitte fülle die Biografie im Benutzerprofil aus!', 'werbeauf-customs' );
    }

    ob_start();
    ?>
    <section class="blog-author-box">
        <div class="blog-author-container">
            <div class="blog-author-avatar">
<?php echo $author_avatar; ?>
            </div>
            <div class="blog-author-content">
                <span class="blog-author-label"><?php esc_html_e( 'Über den Autor', 'werbeauf-customs' ); ?></span>
                <h3 class="blog-author-name"><?php echo esc_html($author_name); ?></h3>
                <div class="blog-author-bio">
                    <p><?php echo wp_kses_post($author_description); ?></p>
                </div>
                <a href="<?php echo esc_url($author_posts_url); ?>" class="blog-author-link">
<?php esc_html_e( 'Alle Beiträge ansehen', 'werbeauf-customs' ); ?>
                </a>
            </div>
        </div>
    </section>
<?php
    return ob_get_clean();
});

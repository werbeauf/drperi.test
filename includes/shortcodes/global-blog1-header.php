<?php
/**
 * Shortcode: [global-blog1-header]
 * Hero-Header fuer Blog-CPT-Singles. Zieht Beitragsbild als Background,
 * Titel und Excerpt aus dem aktuellen Post. Markup: <section class="blog-header-hero">.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_shortcode('global-blog1-header', function() {

    $post_id = get_the_ID();

    // Beitragsbild als Hintergrund; Fallback auf Platzhalter, wenn keines gesetzt ist.
    $thumb_url = get_the_post_thumbnail_url($post_id, 'full');
    if (!$thumb_url) {
        $thumb_url = '/wp-content/uploads/default-placeholder.jpg';
    }

    $title   = get_the_title($post_id);
    $excerpt = get_the_excerpt($post_id);

    ob_start();
    ?>

    <section class="blog-header-hero" style="background-image: url('<?php echo esc_url($thumb_url); ?>');">
        <div class="blog-header-overlay">
            <div class="blog-header-container">
                <div class="blog-header-content">

                    <h1 class="blog-header-title">
<?php echo esc_html($title); ?>
                    </h1>

<?php if ($excerpt) : ?>
                        <div class="blog-header-desc">
<?php echo esc_html($excerpt); ?>
                        </div>
<?php endif; ?>

                </div>
            </div>
        </div>
    </section>

<?php
    return ob_get_clean();
});

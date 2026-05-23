<?php
/* ============================================================
   DATEI: includes/shortcodes/blog-archive-grid.php
   ZWECK: [blog-archive-grid] Shortcode — paginiertes Grid aller
          Blog-CPT-Artikel mit Thema-Filter und Sortierung.

   Server-side: Filter und Pagination ueber URL-Query-Args
   (?thema=…&order=…&paged=…). Kein AJAX.

   Beispiele:
     [blog-archive-grid]
     [blog-archive-grid posts_per_page="6"]
     [blog-archive-grid show_filter="no" show_sort="no"]
============================================================ */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Lazy registriert das CSS — wird erst per wp_enqueue_style() geladen,
 * wenn der Shortcode tatsaechlich auf der Seite vorkommt.
 */
add_action( 'wp_enqueue_scripts', 'wa_blog_archive_register_assets', 9 );
function wa_blog_archive_register_assets() {
    wp_register_style(
        'wa-blog-card',
        WERBEAUF_PLUGIN_URL . 'assets/css/20-components/blog-card.css',
        array(),
        '1.0.0'
    );
    wp_register_style(
        'wa-blog-archive-grid',
        WERBEAUF_PLUGIN_URL . 'assets/css/40-blocks/blog-archive-grid.css',
        array( 'wa-blog-card' ),
        '1.0.0'
    );
}

add_shortcode( 'blog-archive-grid', 'wa_blog_archive_grid_render' );

function wa_blog_archive_grid_render( $atts ) {
    $atts = shortcode_atts( array(
        'posts_per_page' => 9,
        'taxonomy'       => 'thema',
        'show_filter'    => 'yes',
        'show_sort'      => 'yes',
        'default_order'  => 'desc',
    ), $atts, 'blog-archive-grid' );

    wp_enqueue_style( 'wa-blog-archive-grid' );

    $per_page    = max( 1, (int) $atts['posts_per_page'] );
    $taxonomy    = sanitize_key( $atts['taxonomy'] );
    $show_filter = ( 'yes' === strtolower( (string) $atts['show_filter'] ) ) && taxonomy_exists( $taxonomy );
    $show_sort   = ( 'yes' === strtolower( (string) $atts['show_sort'] ) );

    // ---- Query-Args aus der URL einlesen + saeubern ---------------
    $current_thema = isset( $_GET['thema'] ) ? sanitize_title( wp_unslash( $_GET['thema'] ) ) : '';
    $raw_order     = isset( $_GET['order'] ) ? strtolower( sanitize_key( wp_unslash( $_GET['order'] ) ) ) : '';
    $current_order = in_array( $raw_order, array( 'asc', 'desc' ), true )
        ? $raw_order
        : ( in_array( strtolower( (string) $atts['default_order'] ), array( 'asc', 'desc' ), true )
            ? strtolower( $atts['default_order'] )
            : 'desc' );
    $paged = max( 1, (int) ( $_GET['paged'] ?? 1 ) );

    // ---- WP_Query bauen --------------------------------------------
    $query_args = array(
        'post_type'      => 'blog',
        'post_status'    => 'publish',
        'posts_per_page' => $per_page,
        'paged'          => $paged,
        'orderby'        => 'date',
        'order'          => strtoupper( $current_order ),
        'no_found_rows'  => false,
    );

    if ( function_exists( 'wa_wpml_current_lang' ) ) {
        $query_args['lang'] = wa_wpml_current_lang();
    }

    $resolved_term_id = 0;
    if ( $current_thema && taxonomy_exists( $taxonomy ) ) {
        $term = function_exists( 'wa_get_term_by_slug_localized' )
            ? wa_get_term_by_slug_localized( $current_thema, $taxonomy )
            : get_term_by( 'slug', $current_thema, $taxonomy );

        if ( $term && ! is_wp_error( $term ) ) {
            $resolved_term_id        = (int) $term->term_id;
            $query_args['tax_query'] = array(
                array(
                    'taxonomy' => $taxonomy,
                    'field'    => 'term_id',
                    'terms'    => $resolved_term_id,
                ),
            );
        }
    }

    $query = new WP_Query( $query_args );

    // ---- Filter-/Sort-Optionen vorbereiten -------------------------
    $thema_terms = $show_filter
        ? get_terms( array(
            'taxonomy'   => $taxonomy,
            'hide_empty' => true,
        ) )
        : array();
    if ( is_wp_error( $thema_terms ) ) {
        $thema_terms = array();
    }

    $i18n   = array(
        'filter_label'   => __( 'Thema',                    'werbeauf-customs' ),
        'filter_all'     => __( 'Alle Themen',              'werbeauf-customs' ),
        'sort_label'     => __( 'Sortieren',                'werbeauf-customs' ),
        'sort_desc'      => __( 'Neueste zuerst',           'werbeauf-customs' ),
        'sort_asc'       => __( 'Älteste zuerst',           'werbeauf-customs' ),
        'apply'          => __( 'Anwenden',                 'werbeauf-customs' ),
        'empty'          => __( 'Keine Beiträge gefunden.', 'werbeauf-customs' ),
        'read_more'      => __( 'Weiterlesen →',            'werbeauf-customs' ),
        'pagination_aria'=> __( 'Seitennavigation',         'werbeauf-customs' ),
        'prev'           => __( '‹ Vorherige',              'werbeauf-customs' ),
        'next'           => __( 'Nächste ›',                'werbeauf-customs' ),
    );

    ob_start();
    ?>
    <section class="wa-blog-archive">

        <?php if ( $show_filter || $show_sort ) : ?>
        <form class="wa-blog-archive__controls" method="get" action="">

            <?php if ( $show_filter ) : ?>
            <label class="wa-blog-archive__control">
                <span class="wa-blog-archive__control-label"><?php echo esc_html( $i18n['filter_label'] ); ?></span>
                <select name="thema" class="wa-blog-archive__select" onchange="this.form.submit()">
                    <option value=""><?php echo esc_html( $i18n['filter_all'] ); ?></option>
                    <?php foreach ( $thema_terms as $term ) : ?>
                        <option value="<?php echo esc_attr( $term->slug ); ?>" <?php selected( $current_thema, $term->slug ); ?>>
                            <?php echo esc_html( $term->name ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <?php endif; ?>

            <?php if ( $show_sort ) : ?>
            <label class="wa-blog-archive__control">
                <span class="wa-blog-archive__control-label"><?php echo esc_html( $i18n['sort_label'] ); ?></span>
                <select name="order" class="wa-blog-archive__select" onchange="this.form.submit()">
                    <option value="desc" <?php selected( $current_order, 'desc' ); ?>><?php echo esc_html( $i18n['sort_desc'] ); ?></option>
                    <option value="asc"  <?php selected( $current_order, 'asc' );  ?>><?php echo esc_html( $i18n['sort_asc'] );  ?></option>
                </select>
            </label>
            <?php endif; ?>

            <noscript>
                <button type="submit" class="wa-blog-archive__apply"><?php echo esc_html( $i18n['apply'] ); ?></button>
            </noscript>
        </form>
        <?php endif; ?>

        <div class="wa-blog-archive__grid">
            <?php if ( $query->have_posts() ) : ?>
                <?php while ( $query->have_posts() ) : $query->the_post();
                    $post_id      = get_the_ID();
                    $permalink    = get_permalink( $post_id );
                    $title        = get_the_title( $post_id );
                    $thumb_id     = get_post_thumbnail_id( $post_id );
                    $excerpt      = get_the_excerpt( $post_id );
                    $excerpt_trim = $excerpt ? wp_trim_words( $excerpt, 25, '…' ) : '';
                    $timestamp    = get_post_timestamp( $post_id );
                    $date_iso     = $timestamp ? wp_date( 'Y-m-d', $timestamp ) : '';
                    $date_human   = $timestamp ? wp_date( get_option( 'date_format' ), $timestamp ) : '';

                    // Badge: erstes thema-Term des Posts (falls vorhanden).
                    $badge_term = null;
                    if ( taxonomy_exists( $taxonomy ) ) {
                        $post_terms = get_the_terms( $post_id, $taxonomy );
                        if ( ! empty( $post_terms ) && ! is_wp_error( $post_terms ) ) {
                            $badge_term = $post_terms[0];
                        }
                    }
                ?>
                <article class="wa-blog-card">
                    <a href="<?php echo esc_url( $permalink ); ?>" class="wa-blog-card__media" aria-label="<?php echo esc_attr( $title ); ?>">
                        <?php if ( $thumb_id ) : ?>
                            <?php echo wp_get_attachment_image(
                                $thumb_id,
                                'medium_large',
                                false,
                                array(
                                    'class'    => 'wa-blog-card__img',
                                    'loading'  => 'lazy',
                                    'decoding' => 'async',
                                    'alt'      => $title,
                                )
                            ); ?>
                        <?php else : ?>
                            <span class="wa-blog-card__img wa-blog-card__img--placeholder" aria-hidden="true"></span>
                        <?php endif; ?>

                        <?php if ( $badge_term ) : ?>
                            <span class="wa-blog-card__badge"><?php echo esc_html( $badge_term->name ); ?></span>
                        <?php endif; ?>
                    </a>

                    <div class="wa-blog-card__body">
                        <?php if ( $date_human ) : ?>
                            <time class="wa-blog-card__date" datetime="<?php echo esc_attr( $date_iso ); ?>">
                                <?php echo esc_html( $date_human ); ?>
                            </time>
                        <?php endif; ?>

                        <h3 class="wa-blog-card__title">
                            <a href="<?php echo esc_url( $permalink ); ?>"><?php echo esc_html( $title ); ?></a>
                        </h3>

                        <?php if ( $excerpt_trim ) : ?>
                            <p class="wa-blog-card__excerpt"><?php echo esc_html( $excerpt_trim ); ?></p>
                        <?php endif; ?>

                        <a href="<?php echo esc_url( $permalink ); ?>" class="wa-blog-card__more">
                            <?php echo esc_html( $i18n['read_more'] ); ?>
                        </a>
                    </div>
                </article>
                <?php endwhile; ?>
            <?php else : ?>
                <p class="wa-blog-archive__empty"><?php echo esc_html( $i18n['empty'] ); ?></p>
            <?php endif; ?>
        </div>

        <?php
        // Pagination nur wenn mehr als eine Seite existiert.
        if ( $query->max_num_pages > 1 ) :
            $add_args = array();
            if ( $current_thema )                  $add_args['thema'] = $current_thema;
            if ( $current_order !== 'desc' )       $add_args['order'] = $current_order;

            $links = paginate_links( array(
                'base'      => add_query_arg( 'paged', '%#%' ),
                'format'    => '?paged=%#%',
                'current'   => $paged,
                'total'     => $query->max_num_pages,
                'add_args'  => $add_args,
                'prev_text' => $i18n['prev'],
                'next_text' => $i18n['next'],
                'mid_size'  => 1,
                'type'      => 'list',
            ) );

            if ( $links ) :
        ?>
        <nav class="wa-blog-archive__pagination" aria-label="<?php echo esc_attr( $i18n['pagination_aria'] ); ?>">
            <?php echo $links; // bereits durch WP escaped ?>
        </nav>
        <?php
            endif;
        endif;
        wp_reset_postdata();
        ?>
    </section>
    <?php
    return ob_get_clean();
}

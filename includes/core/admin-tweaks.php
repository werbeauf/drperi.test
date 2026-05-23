<?php
/* ============================================================
   DATEI: includes/core/admin-tweaks.php
   ZWECK: Admin-seitige Anpassungen ohne eigenen Page-Output:
     - Divi Project-Posttype komplett deaktivieren
     - WP-Comments / Pings / Recent-Comments-Widget global aus
     - Plugin-Editor immer auf werbeauf-customs vorselektiert
     - Code-Mirror in Divi General-Settings hoeher
     - Admin-Spalte "Bild" fuer den CPT 'blog'
============================================================ */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_filter( 'et_project_posttype_args', function ( $args ) {
    return array_replace( $args, array(
        'public'              => false,
        'exclude_from_search' => false,
        'publicly_queryable'  => false,
        'show_in_nav_menus'   => false,
        'show_ui'             => false,
    ) );
} );

add_action( 'admin_head', function () {
    echo '<style>
        #wrap-general .CodeMirror.cm-s-et.CodeMirror-wrap { min-height: 1200px; }
        th.column-featured_image, td.column-featured_image {
            width: 80px !important; text-align: center; vertical-align: middle;
        }
    </style>';
} );

add_action( 'admin_menu', function () {
    remove_menu_page( 'edit.php' );
    add_filter( 'comments_open', '__return_false', 20 );
    add_filter( 'pings_open', '__return_false', 20 );
    add_filter( 'comments_array', '__return_empty_array', 10 );
    remove_menu_page( 'edit-comments.php' );
    remove_meta_box( 'dashboard_recent_comments', 'dashboard', 'normal' );
} );

add_action( 'admin_init', function () {
    foreach ( get_post_types() as $post_type ) {
        remove_post_type_support( $post_type, 'comments' );
        remove_post_type_support( $post_type, 'trackbacks' );
    }
} );

add_action( 'admin_init', function () {
    global $pagenow;
    if ( $pagenow !== 'plugin-editor.php' ) {
        return;
    }
    if ( isset( $_GET['plugin'] ) ) {
        return;
    }
    wp_safe_redirect(
        admin_url( 'plugin-editor.php?plugin=werbeauf-customs/werbeauf-customs.php' )
    );
    exit;
} );

add_filter( 'manage_blog_posts_columns', function ( $columns ) {
    $new = array();
    foreach ( $columns as $key => $label ) {
        if ( $key === 'title' ) {
            $new['featured_image'] = 'Bild';
        }
        $new[ $key ] = $label;
    }
    return $new;
} );

add_action( 'manage_blog_posts_custom_column', function ( $column, $post_id ) {
    if ( $column !== 'featured_image' ) {
        return;
    }
    $thumb = get_the_post_thumbnail( $post_id, array( 60, 60 ), array(
        'style' => 'border-radius:6px; object-fit:cover;',
    ) );
    echo $thumb ?: '<span style="color:#999;">&mdash;</span>';
}, 10, 2 );

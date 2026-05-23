<?php
/* ============================================================
   DATEI: admin/product-cat-colors.php
   ZWECK: Zwei Color-Picker (Hintergrund + Schrift) auf jedem
          product_cat Term. Term-Meta-Keys:
            - wa_bg_color
            - wa_fg_color
   Konsumiert von [wa_at_a_glance] via --wa-cat-bg / --wa-cat-fg CSS-Vars.
============================================================ */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ------------------------------------------------------------
   Admin-UI: Color-Picker enqueuen auf den Term-Edit-Screens.
------------------------------------------------------------ */
add_action( 'admin_enqueue_scripts', 'wa_term_colors_enqueue' );

function wa_term_colors_enqueue( $hook ) {
    if ( ! in_array( $hook, array( 'edit-tags.php', 'term.php' ), true ) ) {
        return;
    }
    $screen = get_current_screen();
    if ( ! $screen || 'product_cat' !== $screen->taxonomy ) {
        return;
    }
    wp_enqueue_style( 'wp-color-picker' );
    wp_enqueue_script( 'wp-color-picker' );
    wp_add_inline_script(
        'wp-color-picker',
        'jQuery(function($){ $(".wa-color-field").wpColorPicker(); });'
    );
}

/* ------------------------------------------------------------
   "Neuer Begriff"-Formular (Add).
------------------------------------------------------------ */
add_action( 'product_cat_add_form_fields', 'wa_term_colors_add_fields' );

function wa_term_colors_add_fields() {
    ?>
    <div class="form-field">
        <label for="wa_bg_color"><?php esc_html_e( 'Hintergrundfarbe', 'werbeauf-customs' ); ?></label>
        <input type="text" name="wa_bg_color" id="wa_bg_color" class="wa-color-field" value="" />
        <p class="description"><?php esc_html_e( 'Wird in Set-Tabellen als Spalten-Hintergrund verwendet.', 'werbeauf-customs' ); ?></p>
    </div>
    <div class="form-field">
        <label for="wa_fg_color"><?php esc_html_e( 'Schriftfarbe', 'werbeauf-customs' ); ?></label>
        <input type="text" name="wa_fg_color" id="wa_fg_color" class="wa-color-field" value="" />
    </div>
    <?php
}

/* ------------------------------------------------------------
   "Begriff bearbeiten"-Formular (Edit).
------------------------------------------------------------ */
add_action( 'product_cat_edit_form_fields', 'wa_term_colors_edit_fields' );

function wa_term_colors_edit_fields( $term ) {
    $bg = (string) get_term_meta( $term->term_id, 'wa_bg_color', true );
    $fg = (string) get_term_meta( $term->term_id, 'wa_fg_color', true );
    ?>
    <tr class="form-field">
        <th scope="row"><label for="wa_bg_color"><?php esc_html_e( 'Hintergrundfarbe', 'werbeauf-customs' ); ?></label></th>
        <td>
            <input type="text" name="wa_bg_color" id="wa_bg_color" class="wa-color-field" value="<?php echo esc_attr( $bg ); ?>" />
            <p class="description"><?php esc_html_e( 'Wird in Set-Tabellen als Spalten-Hintergrund verwendet.', 'werbeauf-customs' ); ?></p>
        </td>
    </tr>
    <tr class="form-field">
        <th scope="row"><label for="wa_fg_color"><?php esc_html_e( 'Schriftfarbe', 'werbeauf-customs' ); ?></label></th>
        <td>
            <input type="text" name="wa_fg_color" id="wa_fg_color" class="wa-color-field" value="<?php echo esc_attr( $fg ); ?>" />
        </td>
    </tr>
    <?php
}

/* ------------------------------------------------------------
   Persist (Create + Edit).
------------------------------------------------------------ */
add_action( 'created_product_cat', 'wa_term_colors_save' );
add_action( 'edited_product_cat',  'wa_term_colors_save' );

function wa_term_colors_save( $term_id ) {
    foreach ( array( 'wa_bg_color', 'wa_fg_color' ) as $key ) {
        if ( ! isset( $_POST[ $key ] ) ) {
            continue;
        }
        $raw   = (string) wp_unslash( $_POST[ $key ] );
        $clean = sanitize_hex_color( $raw );
        if ( $clean ) {
            update_term_meta( $term_id, $key, $clean );
        } elseif ( '' === trim( $raw ) ) {
            delete_term_meta( $term_id, $key );
        }
    }
}

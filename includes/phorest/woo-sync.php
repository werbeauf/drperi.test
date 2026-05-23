<?php
/* ============================================================
   DATEI: includes/phorest-woo-sync.php
   Connects Phorest API products to WooCommerce products.
============================================================ */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ----------------------------------------------------------
   Constants
---------------------------------------------------------- */
defined( 'WA_PHOREST_LINK_META' ) || define( 'WA_PHOREST_LINK_META', '_phorest_product_id' );
defined( 'WA_PHOREST_CRON_HOOK' ) || define( 'WA_PHOREST_CRON_HOOK', 'wa_phorest_auto_sync' );

/* ----------------------------------------------------------
   1. Meta box – register
---------------------------------------------------------- */
add_action( 'add_meta_boxes', function () {
    add_meta_box(
        'wa-phorest-link',
        'Phorest API Verknüpfung',
        'wa_phorest_woo_render_meta_box',
        'product',
        'side',
        'default'
    );
} );

/* ----------------------------------------------------------
   2. Meta box – render
---------------------------------------------------------- */
function wa_phorest_woo_render_meta_box( $post ) {
    wp_nonce_field( 'wa_phorest_link_save', 'wa_phorest_link_nonce' );

    $linked_id = get_post_meta( $post->ID, WA_PHOREST_LINK_META, true );
    $products  = get_transient( 'wa_phorest_products_cache' );
    $last_sync = get_post_meta( $post->ID, '_phorest_last_sync', true );
    ?>
    <style>
        #wa-phorest-link select { width: 100%; margin-bottom: 10px; }
        #wa-phorest-link .wa-sync-meta-row { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
        #wa-phorest-link .wa-sync-info { font-size: 11px; color: #646970; margin-top: 6px; }
        #wa-phorest-link .wa-sync-ok  { color: #00a32a; font-size: 11px; margin-top: 6px; }
        #wa-phorest-link .wa-sync-err { color: #d63638; font-size: 11px; margin-top: 6px; }
        #wa-phorest-manual-sync { width: 100%; text-align: center; margin-top: 4px; }
    </style>

    <?php if ( empty( $products ) ) : ?>
        <p style="color:#d63638;font-size:12px;">
            Keine Phorest-Produkte im Cache. Bitte zuerst unter <strong>Phorest Produkte → Sync now</strong> synchronisieren.
        </p>
    <?php else : ?>
        <label for="wa_phorest_product_id" style="font-weight:600;font-size:12px;display:block;margin-bottom:4px;">
            Phorest Produkt
        </label>
        <select name="wa_phorest_product_id" id="wa_phorest_product_id">
            <option value="">— Nicht verknüpft —</option>
            <?php foreach ( $products as $p ) :
                $pid   = $p['productId'] ?? '';
                $label = ( $p['name'] ?? '?' ) . ( ! empty( $p['brandName'] ) ? ' (' . $p['brandName'] . ')' : '' );
                ?>
                <option value="<?php echo esc_attr( $pid ); ?>" <?php selected( $linked_id, $pid ); ?>>
                    <?php echo esc_html( $label ); ?>
                </option>
            <?php endforeach; ?>
        </select>

        <?php if ( $linked_id ) : ?>
            <div id="wa-phorest-manual-sync-wrap">
                <button type="button" id="wa-phorest-manual-sync" class="button button-secondary">
                    Jetzt synchronisieren
                </button>
                <?php if ( $last_sync ) : ?>
                    <p class="wa-sync-info">Letzter Sync: <?php echo esc_html( wp_date( 'd.m.Y H:i', strtotime( $last_sync ) ) ); ?> Uhr</p>
                <?php endif; ?>
                <p id="wa-phorest-sync-result" style="display:none;"></p>
            </div>
            <script>
            (function () {
                var btn = document.getElementById('wa-phorest-manual-sync');
                var result = document.getElementById('wa-phorest-sync-result');
                if (!btn) return;
                btn.addEventListener('click', function () {
                    btn.disabled = true;
                    btn.textContent = 'Synchronisiere …';
                    result.style.display = 'none';
                    fetch(ajaxurl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({
                            action: 'wa_phorest_sync_single_product',
                            nonce:  '<?php echo esc_js( wp_create_nonce( 'wa_phorest_sync_single' ) ); ?>',
                            post_id: '<?php echo esc_js( $post->ID ); ?>',
                        }),
                    })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        result.style.display = 'block';
                        result.className = data.success ? 'wa-sync-ok' : 'wa-sync-err';
                        result.textContent = data.data || (data.success ? 'Synchronisiert.' : 'Fehler.');
                    })
                    .catch(function () {
                        result.style.display = 'block';
                        result.className = 'wa-sync-err';
                        result.textContent = 'Netzwerkfehler.';
                    })
                    .finally(function () {
                        btn.disabled = false;
                        btn.textContent = 'Jetzt synchronisieren';
                    });
                });
            })();
            </script>
        <?php endif; ?>
    <?php endif; ?>
    <?php
}

/* ----------------------------------------------------------
   3. Meta box – save & immediate sync
---------------------------------------------------------- */
add_action( 'woocommerce_process_product_meta', function ( $post_id ) {
    if ( ! isset( $_POST['wa_phorest_link_nonce'] ) ) return;
    if ( ! wp_verify_nonce( $_POST['wa_phorest_link_nonce'], 'wa_phorest_link_save' ) ) return;
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;

    $phorest_id = sanitize_text_field( $_POST['wa_phorest_product_id'] ?? '' );

    if ( empty( $phorest_id ) ) {
        delete_post_meta( $post_id, WA_PHOREST_LINK_META );
        return;
    }

    update_post_meta( $post_id, WA_PHOREST_LINK_META, $phorest_id );

    // Sync immediately on save
    $phorest_product = wa_phorest_find_product( $phorest_id );
    if ( $phorest_product ) {
        wa_phorest_apply_to_woo( $post_id, $phorest_product );
    }
}, 10, 1 );

/* ----------------------------------------------------------
   4. AJAX – manual sync per product
---------------------------------------------------------- */
add_action( 'wp_ajax_wa_phorest_sync_single_product', function () {
    check_ajax_referer( 'wa_phorest_sync_single', 'nonce' );
    if ( ! current_user_can( 'edit_products' ) ) {
        wp_send_json_error( 'Unauthorized' );
    }

    $post_id    = (int) ( $_POST['post_id'] ?? 0 );
    $phorest_id = get_post_meta( $post_id, WA_PHOREST_LINK_META, true );

    if ( ! $post_id || ! $phorest_id ) {
        wp_send_json_error( 'Kein verknüpftes Phorest-Produkt gefunden.' );
    }

    $phorest_product = wa_phorest_find_product( $phorest_id );
    if ( ! $phorest_product ) {
        wp_send_json_error( 'Phorest-Produkt nicht im Cache. Bitte zuerst Phorest Sync ausführen.' );
    }

    wa_phorest_apply_to_woo( $post_id, $phorest_product );

    wp_send_json_success( 'Produkt synchronisiert (' . wp_date( 'd.m.Y H:i' ) . ' Uhr).' );
} );

/* ----------------------------------------------------------
   5. Core sync – apply Phorest data to WooCommerce product
---------------------------------------------------------- */
function wa_phorest_apply_to_woo( $post_id, $phorest ) {
    static $running = [];
    if ( isset( $running[ $post_id ] ) ) return;
    $running[ $post_id ] = true;
    $product = wc_get_product( $post_id );
    if ( ! $product ) return;

    // 1. productId → SKU (Artikelnummer)
    if ( ! empty( $phorest['productId'] ) ) {
        $product->set_sku( sanitize_text_field( $phorest['productId'] ) );
    }

    // 2. name → product title.
    // WPML-aware: nur Default-Sprache-Posts (DE) werden mit dem Phorest-Namen
    // ueberschrieben. Translations (EN) behalten ihren manuell uebersetzten
    // Title -- sonst wuerde der Phorest-Sync sie bei jedem Run zurueck-
    // ueberschreiben. Stock/Price/SKU laufen weiter auf alle Translations.
    if ( ! empty( $phorest['name'] ) ) {
        $is_default_lang = function_exists( 'wa_wpml_is_default_lang_post' )
            ? wa_wpml_is_default_lang_post( $post_id )
            : true;
        if ( $is_default_lang ) {
            wp_update_post( [
                'ID'         => $post_id,
                'post_title' => sanitize_text_field( $phorest['name'] ),
            ] );
        }
    }

    // 3. price → regular price
    if ( isset( $phorest['price'] ) && is_numeric( $phorest['price'] ) ) {
        $product->set_regular_price( (string) $phorest['price'] );
    }

    // 4. barcode → GTIN (_wc_gtin — native WooCommerce 9.2+ field)
    if ( ! empty( $phorest['barcode'] ) ) {
        update_post_meta( $post_id, '_wc_gtin', sanitize_text_field( $phorest['barcode'] ) );
    }

    // 5. quantityInStock → stock quantity
    if ( isset( $phorest['quantityInStock'] ) && is_numeric( $phorest['quantityInStock'] ) && ! $product->is_type( 'external' ) ) {
        $product->set_manage_stock( true );
        $product->set_stock_quantity( (int) $phorest['quantityInStock'] );
        $product->set_stock_status( (int) $phorest['quantityInStock'] > 0 ? 'instock' : 'outofstock' );
    }

    $product->save();

    update_post_meta( $post_id, '_phorest_last_sync', current_time( 'mysql' ) );
}

/* ----------------------------------------------------------
   6. Helper – find a Phorest product in cache by productId
---------------------------------------------------------- */
function wa_phorest_find_product( $phorest_id ) {
    $products = get_transient( 'wa_phorest_products_cache' );
    if ( empty( $products ) ) return null;

    foreach ( $products as $p ) {
        if ( ( $p['productId'] ?? '' ) === $phorest_id ) {
            return $p;
        }
    }
    return null;
}

/* ----------------------------------------------------------
   7. Sync all linked WooCommerce products (called by cron
      and also hooked into the Phorest "Sync now" AJAX)
---------------------------------------------------------- */
function wa_phorest_sync_all_linked() {
    $products = get_transient( 'wa_phorest_products_cache' );
    if ( empty( $products ) ) return;

    // Index by productId for fast lookup
    $index = [];
    foreach ( $products as $p ) {
        if ( ! empty( $p['productId'] ) ) {
            $index[ $p['productId'] ] = $p;
        }
    }

    // Find all WC products that have a Phorest link
    $linked = get_posts( [
        'post_type'      => 'product',
        'posts_per_page' => -1,
        'post_status'    => 'any',
        'fields'         => 'ids',
        'meta_query'     => [ [
            'key'     => WA_PHOREST_LINK_META,
            'compare' => 'EXISTS',
        ] ],
    ] );

    foreach ( $linked as $post_id ) {
        $phorest_id = get_post_meta( $post_id, WA_PHOREST_LINK_META, true );
        if ( ! empty( $phorest_id ) && isset( $index[ $phorest_id ] ) ) {
            wa_phorest_apply_to_woo( $post_id, $index[ $phorest_id ] );
        }
    }
}

// Hook into the Phorest "Sync now" AJAX so WC products update automatically
add_action( 'wa_phorest_after_sync', 'wa_phorest_sync_all_linked' );

/* ----------------------------------------------------------
   8. WP Cron – hourly auto-sync
---------------------------------------------------------- */
add_action( 'wp', function () {
    if ( ! wp_next_scheduled( WA_PHOREST_CRON_HOOK ) ) {
        wp_schedule_event( time(), 'hourly', WA_PHOREST_CRON_HOOK );
    }
} );

add_action( WA_PHOREST_CRON_HOOK, function () {
    // Re-fetch Phorest products
    $products = wa_phorest_fetch_products();
    if ( is_wp_error( $products ) || empty( $products ) ) return;

    set_transient( 'wa_phorest_products_cache', $products, 6 * HOUR_IN_SECONDS );
    update_option( 'wa_phorest_last_sync', current_time( 'mysql' ) );

    // Sync all linked WC products with fresh data
    wa_phorest_sync_all_linked();
} );

// Clean up cron on plugin deactivation
register_deactivation_hook(
    WERBEAUF_PLUGIN_PATH . 'werbeauf-customs.php',
    function () {
        $timestamp = wp_next_scheduled( WA_PHOREST_CRON_HOOK );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, WA_PHOREST_CRON_HOOK );
        }
    }
);

/* ----------------------------------------------------------
   9. Admin products list – Phorest column
---------------------------------------------------------- */
add_filter( 'manage_edit-product_columns', function ( $columns ) {
    $columns['phorest_link'] = 'Phorest Produkt';
    return $columns;
} );

add_action( 'manage_product_posts_custom_column', function ( $column, $post_id ) {
    if ( $column !== 'phorest_link' ) return;

    $phorest_id = get_post_meta( $post_id, WA_PHOREST_LINK_META, true );

    if ( empty( $phorest_id ) ) {
        echo '<span style="color:#c3c4c7;">&#8212;</span>';
        return;
    }

    $products = get_transient( 'wa_phorest_products_cache' );
    $name     = $phorest_id;

    if ( ! empty( $products ) ) {
        foreach ( $products as $p ) {
            if ( ( $p['productId'] ?? '' ) === $phorest_id ) {
                $name = $p['name'] ?? $phorest_id;
                break;
            }
        }
    }

    $last_sync = get_post_meta( $post_id, '_phorest_last_sync', true );
    $sync_info = $last_sync
        ? '<br><span style="color:#646970;font-size:11px;">Sync: ' . esc_html( wp_date( 'd.m.Y H:i', strtotime( $last_sync ) ) ) . '</span>'
        : '';

    echo '<span style="color:#2271b1;font-weight:600;">' . esc_html( $name ) . '</span>' . $sync_info;
}, 10, 2 );

add_filter( 'manage_edit-product_sortable_columns', function ( $columns ) {
    $columns['phorest_link'] = 'phorest_link';
    return $columns;
} );

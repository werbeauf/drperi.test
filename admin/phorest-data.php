<?php
/* ============================================================
   DATEI: admin/phorest-data.php
============================================================ */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'WA_PHOREST_PRODUCTS_TRANSIENT', 'wa_phorest_products_cache' );

/* ----------------------------------------------------------
   Helper – fetch products from API
---------------------------------------------------------- */
function wa_phorest_fetch_products() {
    $business_id = get_option( 'wa_phorest_business_id', '' );
    $branch_id   = get_option( 'wa_phorest_branch_id',   '' );
    $api_url     = get_option( 'wa_phorest_api_url',     '' );
    $api_token   = get_option( 'wa_phorest_api_token',   '' );

    if ( empty( $business_id ) || empty( $branch_id ) || empty( $api_url ) || empty( $api_token ) ) {
        return new WP_Error( 'missing_config', 'API-Zugangsdaten nicht vollständig konfiguriert.' );
    }

    $base_endpoint = trailingslashit( $api_url )
        . 'api/business/' . rawurlencode( $business_id )
        . '/branch/'      . rawurlencode( $branch_id )
        . '/product';

    $all_products = [];
    $page         = 0;
    $page_size    = 100;

    do {
        $url = add_query_arg( [ 'pageSize' => $page_size, 'page' => $page ], $base_endpoint );

        $response = wp_remote_get( $url, [
            'headers' => [
                'Authorization' => 'Basic ' . $api_token,
                'Content-Type'  => 'application/json',
            ],
            'timeout'   => 30,
            'sslverify' => true,
        ] );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );

        if ( $code !== 200 ) {
            return new WP_Error( 'api_error', 'HTTP ' . $code . ': ' . wp_remote_retrieve_response_message( $response ) );
        }

        $data = json_decode( $body, true );
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return new WP_Error( 'json_error', 'JSON-Fehler: ' . json_last_error_msg() );
        }

        // Phorest HAL structure: _embedded.products
        $page_products = $data['_embedded']['products'] ?? $data['products'] ?? [];
        $all_products  = array_merge( $all_products, $page_products );

        // Check if more pages exist via HAL pagination info
        $total_pages = $data['page']['totalPages'] ?? 1;
        $page++;

    } while ( $page < $total_pages );

    return array_values( array_filter( $all_products, function( $p ) {
        return ( $p['brandName'] ?? '' ) === 'Dr. Peri Skincare' && ( $p['categoryName'] ?? '' ) !== '1 Kabinettware';
    } ) );
}

/* ----------------------------------------------------------
   AJAX – Sync now
---------------------------------------------------------- */
add_action( 'wp_ajax_wa_phorest_sync_products', function () {
    check_ajax_referer( 'wa_phorest_data', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Unauthorized' );
    }

    $products = wa_phorest_fetch_products();

    if ( is_wp_error( $products ) ) {
        wp_send_json_error( $products->get_error_message() );
    }

    set_transient( WA_PHOREST_PRODUCTS_TRANSIENT, $products, 6 * HOUR_IN_SECONDS );
    update_option( 'wa_phorest_last_sync', current_time( 'mysql' ) );
    do_action( 'wa_phorest_after_sync' );

    wp_send_json_success( [
        'count'   => count( $products ),
        'message' => count( $products ) . ' Produkte synchronisiert.',
    ] );
} );

/* ----------------------------------------------------------
   AJAX – Clear cache
---------------------------------------------------------- */
add_action( 'wp_ajax_wa_phorest_clear_cache', function () {
    check_ajax_referer( 'wa_phorest_data', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Unauthorized' );
    }

    delete_transient( WA_PHOREST_PRODUCTS_TRANSIENT );
    delete_option( 'wa_phorest_last_sync' );

    wp_send_json_success( 'Cache geleert.' );
} );

/* ----------------------------------------------------------
   Render page
---------------------------------------------------------- */
function wa_render_phorest_data_content() {
    $products  = get_transient( WA_PHOREST_PRODUCTS_TRANSIENT );
    $last_sync = get_option( 'wa_phorest_last_sync', '' );
    $nonce     = wp_create_nonce( 'wa_phorest_data' );
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">Phorest Produkte</h1>
        <hr class="wp-header-end">

        <style>
            .wa-data-wrap { max-width: 1100px; margin-top: 24px; }
            .wa-data-toolbar { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; margin-bottom: 24px; }
            .wa-data-toolbar .wa-sync-info { color: #646970; font-size: 13px; margin-left: auto; }
            .wa-status-badge { display: inline-flex; align-items: center; gap: 6px; padding: 5px 12px; border-radius: 3px; font-size: 12px; font-weight: 600; }
            .wa-status-badge.success { background: #edfaef; color: #00a32a; }
            .wa-status-badge.error   { background: #fcf0f1; color: #d63638; }
            .wa-status-badge .dot { width: 8px; height: 8px; border-radius: 50%; background: currentColor; flex-shrink: 0; }
            .wa-products-card { background: #fff; border: 1px solid #c3c4c7; box-shadow: 0 1px 1px rgba(0,0,0,.04); }
            .wa-products-card-header { padding: 14px 20px; border-bottom: 1px solid #eaecf0; background: #fcfcfc; display: flex; align-items: center; justify-content: space-between; }
            .wa-products-card-header h2 { margin: 0; font-size: 14px; font-weight: 600; }
            .wa-count-badge { background: #f0f0f1; color: #646970; font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 10px; }
            #wa-products-table-wrap { overflow-x: auto; }
            #wa-products-table { width: 100%; border-collapse: collapse; font-size: 13px; }
            #wa-products-table th { background: #f6f7f7; padding: 10px 14px; text-align: left; font-weight: 600; border-bottom: 2px solid #eaecf0; white-space: nowrap; }
            #wa-products-table td { padding: 10px 14px; border-bottom: 1px solid #f0f0f1; vertical-align: top; }
            #wa-products-table tr:last-child td { border-bottom: none; }
            #wa-products-table tr:hover td { background: #f6f7f7; }
            .wa-badge-active   { background: #edfaef; color: #00a32a; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 600; }
            .wa-badge-inactive { background: #f0f0f1; color: #646970; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 600; }
            .wa-empty-state { padding: 60px 20px; text-align: center; color: #646970; }
            .wa-empty-state p { font-size: 14px; margin: 8px 0 0; }
        </style>

        <div class="wa-data-wrap">

            <div class="wa-data-toolbar">
                <button type="button" id="wa-sync-btn" class="button button-primary">Sync now</button>
                <button type="button" id="wa-clear-btn" class="button button-secondary">Clear Cache</button>
                <span id="wa-action-badge" class="wa-status-badge" style="display:none">
                    <span class="dot"></span>
                    <span id="wa-action-text"></span>
                </span>
                <?php if ( $last_sync ) : ?>
                    <span class="wa-sync-info">Letzter Sync: <?php echo esc_html( wp_date( 'd.m.Y H:i', strtotime( $last_sync ) ) ); ?> Uhr</span>
                <?php endif; ?>
            </div>

            <div class="wa-products-card">
                <div class="wa-products-card-header">
                    <h2><?php esc_html_e( 'Produkte', 'werbeauf-customs' ); ?></h2>
                    <span class="wa-count-badge" id="wa-product-count">
                        <?php echo is_array( $products ) ? count( $products ) : 0; ?> <?php esc_html_e( 'Produkte', 'werbeauf-customs' ); ?>
                    </span>
                </div>
                <div id="wa-products-table-wrap">
                    <?php if ( ! empty( $products ) && is_array( $products ) ) : ?>
                        <?php echo wa_phorest_render_products_table( $products ); ?>
                    <?php else : ?>
                        <div class="wa-empty-state" id="wa-empty-state">
                            <strong><?php esc_html_e( 'Keine Produkte geladen.', 'werbeauf-customs' ); ?></strong>
                            <p><?php esc_html_e( 'Klicke auf „Sync now" um Produkte von der Phorest API zu laden.', 'werbeauf-customs' ); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>

    <script>
    (function () {
        var nonce      = '<?php echo esc_js( $nonce ); ?>';
        var syncBtn    = document.getElementById('wa-sync-btn');
        var clearBtn   = document.getElementById('wa-clear-btn');
        var badge      = document.getElementById('wa-action-badge');
        var badgeText  = document.getElementById('wa-action-text');
        var countBadge = document.getElementById('wa-product-count');
        var tableWrap  = document.getElementById('wa-products-table-wrap');

        function showBadge(type, msg) {
            badge.style.display = 'inline-flex';
            badge.className = 'wa-status-badge ' + type;
            badgeText.textContent = msg;
        }

        function lock(state) {
            syncBtn.disabled  = state;
            clearBtn.disabled = state;
        }

        syncBtn.addEventListener('click', function () {
            lock(true);
            badge.style.display = 'none';
            syncBtn.textContent = 'Synchronisiere …';

            fetch(ajaxurl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'wa_phorest_sync_products', nonce: nonce }),
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success) {
                    showBadge('success', data.data.message);
                    window.location.reload();
                } else {
                    showBadge('error', data.data || 'Sync fehlgeschlagen.');
                }
            })
            .catch(function () { showBadge('error', 'Netzwerkfehler.'); })
            .finally(function () { lock(false); syncBtn.textContent = 'Sync now'; });
        });

        clearBtn.addEventListener('click', function () {
            lock(true);
            badge.style.display = 'none';
            clearBtn.textContent = 'Leere Cache …';

            fetch(ajaxurl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'wa_phorest_clear_cache', nonce: nonce }),
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success) {
                    showBadge('success', data.data);
                    countBadge.textContent = '0 Produkte';
                    tableWrap.innerHTML = '<div class="wa-empty-state"><strong><?php echo esc_js( __( 'Keine Produkte geladen.', 'werbeauf-customs' ) ); ?></strong><p><?php echo esc_js( __( 'Klicke auf „Sync now" um Produkte von der Phorest API zu laden.', 'werbeauf-customs' ) ); ?></p></div>';
                } else {
                    showBadge('error', data.data || 'Fehler beim Leeren.');
                }
            })
            .catch(function () { showBadge('error', 'Netzwerkfehler.'); })
            .finally(function () { lock(false); clearBtn.textContent = 'Clear Cache'; });
        });
    })();
    </script>
    <?php
}

/* ----------------------------------------------------------
   Helper – render products table HTML
---------------------------------------------------------- */
function wa_phorest_render_products_table( $products ) {
    if ( empty( $products ) ) return '';
    // Fixed column order
    $columns  = [ 'brandName', 'name', 'productId', 'categoryName', 'price', 'barcode', 'quantityInStock', 'updatedAt' ];

    $required = [ 'name', 'productId', 'price', 'barcode', 'quantityInStock' ];

    $labels = [
        'brandName'       => 'Marke',
        'name'            => 'Name',
        'productId'       => 'Produkt-ID',
        'categoryName'    => 'Kategorie',
        'price'           => 'Preis',
        'barcode'         => 'Barcode',
        'quantityInStock' => 'Lagerbestand',
        'updatedAt'       => 'Aktualisiert',
    ];


    ob_start();
    ?>
    <table id="wa-products-table">
        <thead>
            <tr>
                <?php foreach ( $columns as $col ) : ?>
                    <th><?php
                        $label = $labels[ $col ] ?? ucfirst( $col );
                        echo esc_html( $label );
                        if ( in_array( $col, $required, true ) ) echo ' <span style="color:#d63638;font-weight:700;">*</span>';
                    ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ( $products as $product ) :
                if ( ! is_array( $product ) ) continue; ?>
                <tr>
                    <?php foreach ( $columns as $col ) :
                        $val = $product[ $col ] ?? '—'; ?>
                        <td>
                            <?php
                            if ( $col === 'active' ) {
                                echo $val
                                    ? '<span class="wa-badge-active">Aktiv</span>'
                                    : '<span class="wa-badge-inactive">Inaktiv</span>';
                            } elseif ( $col === 'price' && is_numeric( $val ) ) {
                                echo esc_html( number_format( (float) $val, 2, ',', '.' ) . ' €' );
                            } elseif ( is_array( $val ) ) {
                                echo esc_html( wp_json_encode( $val ) );
                            } else {
                                echo esc_html( (string) $val );
                            }
                            ?>
                        </td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php
    return ob_get_clean();
}

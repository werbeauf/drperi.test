<?php
/* ============================================================
   DATEI: includes/phorest-order-sync.php
   Syncs completed WooCommerce orders to Phorest as purchases.
============================================================ */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ----------------------------------------------------------
   Shared API helper. Guarded — stock-sync.php registers the same
   helper conditionally; ensure both can load in any order.
---------------------------------------------------------- */
if ( ! function_exists( 'wa_phorest_api' ) ) :
function wa_phorest_api( string $method, string $path, mixed $body = null ) {
    $api_url   = get_option( 'wa_phorest_api_url',     '' );
    $token     = get_option( 'wa_phorest_api_token',   '' );

    if ( empty( $api_url ) || empty( $token ) ) {
        return new WP_Error( 'missing_config', 'Phorest API nicht konfiguriert.' );
    }

    $url  = trailingslashit( $api_url ) . ltrim( $path, '/' );
    $args = [
        'method'  => strtoupper( $method ),
        'headers' => [
            'Authorization' => 'Basic ' . $token,
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
        ],
        'timeout'   => 20,
        'sslverify' => true,
    ];

    if ( $body !== null ) {
        $args['body'] = wp_json_encode( $body );
    }

    $response = wp_remote_request( $url, $args );

    if ( is_wp_error( $response ) ) {
        return $response;
    }

    $code      = wp_remote_retrieve_response_code( $response );
    $raw_body  = wp_remote_retrieve_body( $response );
    $data      = json_decode( $raw_body, true );

    if ( $code >= 400 ) {
        $msg = $data['message'] ?? $data['error'] ?? wp_remote_retrieve_response_message( $response );
        return new WP_Error( 'api_http_' . $code, 'HTTP ' . $code . ': ' . $msg . ' | ' . $raw_body );
    }

    return $data;
}
endif;

/* ----------------------------------------------------------
   1. Hook: order completed
---------------------------------------------------------- */
add_action( 'woocommerce_order_status_completed', 'wa_phorest_sync_order', 10, 1 );

function wa_phorest_sync_order( $order_id ) {
    // Only run if Phorest integration is active
    if ( ! get_option( 'wa_phorest_active', 0 ) ) return;

    $order = wc_get_order( $order_id );
    if ( ! $order ) return;

    // Skip if already synced
    if ( $order->get_meta( '_phorest_purchase_synced' ) ) return;

    // Build items — only include products that have a Phorest link
    $items = wa_phorest_build_items( $order );
    if ( empty( $items ) ) {
        $order->add_order_note( 'Phorest Sync übersprungen: Keine verknüpften Phorest-Produkte im Warenkorb.' );
        return;
    }

    // Get or create Phorest client
    $client_id = wa_phorest_get_or_create_client( $order );
    if ( is_wp_error( $client_id ) ) {
        $order->update_meta_data( '_phorest_purchase_error', $client_id->get_error_message() );
        $order->add_order_note( 'Phorest Sync Fehler (Client): ' . $client_id->get_error_message() );
        $order->save();
        return;
    }

    // Build full payload
    $payload = wa_phorest_build_purchase_payload( $order, $client_id, $items );

    // Send to Phorest
    $business_id = get_option( 'wa_phorest_business_id', '' );
    $branch_id   = get_option( 'wa_phorest_branch_id',   '' );
    $path        = 'api/business/' . rawurlencode( $business_id ) . '/branch/' . rawurlencode( $branch_id ) . '/purchase';

    $order->save();
    $result = wa_phorest_api( 'POST', $path, $payload );

    if ( is_wp_error( $result ) ) {
        $order->update_meta_data( '_phorest_purchase_error', $result->get_error_message() );
        $order->add_order_note( 'Phorest Sync Fehler: ' . $result->get_error_message() );
        $order->save();
        return;
    }

    // Success
    $phorest_purchase_id = $result['purchaseId'] ?? $result['id'] ?? '–';
    $order->update_meta_data( '_phorest_purchase_synced', current_time( 'mysql' ) );
    $order->update_meta_data( '_phorest_purchase_id',     $phorest_purchase_id );
    $order->delete_meta_data( '_phorest_purchase_error' );
    $order->add_order_note( 'Phorest Purchase erstellt. ID: ' . $phorest_purchase_id );
    $order->save();
}

/* ----------------------------------------------------------
   2. Client lookup or create
---------------------------------------------------------- */
function wa_phorest_get_or_create_client( $order ) {
    $business_id = get_option( 'wa_phorest_business_id', '' );
    $email       = sanitize_email( $order->get_billing_email() );
    $user_id     = $order->get_user_id();

    if ( empty( $email ) || ! is_email( $email ) ) {
        return new WP_Error( 'invalid_email', 'Order has no valid billing email — Phorest client lookup skipped.' );
    }

    // Check WC user meta first
    if ( $user_id ) {
        $cached = get_user_meta( $user_id, '_phorest_client_id', true );
        if ( $cached ) return $cached;
    }

    // Search Phorest by email
    $search = wa_phorest_api( 'GET',
        'api/business/' . rawurlencode( $business_id ) . '/client?email=' . rawurlencode( $email )
    );

    if ( ! is_wp_error( $search ) ) {
        $clients   = $search['_embedded']['clients'] ?? $search['clients'] ?? [];
        $client_id = $clients[0]['clientId'] ?? null;

        if ( $client_id ) {
            if ( $user_id ) update_user_meta( $user_id, '_phorest_client_id', $client_id );
            $order->update_meta_data( '_phorest_client_id', $client_id );
            return $client_id;
        }
    }

    // Create new Phorest client
    $new_client = wa_phorest_api( 'POST',
        'api/business/' . rawurlencode( $business_id ) . '/client',
        [
            'firstName'   => sanitize_text_field( $order->get_billing_first_name() ),
            'lastName'    => sanitize_text_field( $order->get_billing_last_name() ),
            'email'       => $email,
            'mobilePhone' => sanitize_text_field( $order->get_billing_phone() ),
        ]
    );

    if ( is_wp_error( $new_client ) ) {
        return $new_client;
    }

    $client_id = $new_client['clientId'] ?? null;
    if ( ! $client_id ) {
        return new WP_Error( 'no_client_id', 'Phorest Client ID nicht in der Antwort gefunden.' );
    }

    if ( $user_id ) update_user_meta( $user_id, '_phorest_client_id', $client_id );
    $order->update_meta_data( '_phorest_client_id', $client_id );

    return $client_id;
}

/* ----------------------------------------------------------
   3. Build items array from order line items
---------------------------------------------------------- */
function wa_phorest_build_items( $order ) {
    $items = [];

    foreach ( $order->get_items() as $item ) {
        $product_id  = $item->get_product_id();
        $phorest_id  = get_post_meta( $product_id, WA_PHOREST_LINK_META, true );

        if ( empty( $phorest_id ) ) continue; // skip unlinked products

        $quantity    = (int) $item->get_quantity();
        $line_total  = (float) $item->get_total(); // net total
        $line_tax    = (float) $item->get_total_tax();
        $price_gross = $quantity > 0 ? round( ( $line_total + $line_tax ) / $quantity, 2 ) : 0;

        $items[] = [
            'branchProductId' => $phorest_id,
            'price'           => $price_gross,
            'quantity'        => $quantity,
        ];
    }

    return $items;
}

/* ----------------------------------------------------------
   4. Build full purchase payload
---------------------------------------------------------- */
function wa_phorest_build_purchase_payload( $order, $client_id, $items ) {
    $shipping_method  = wa_phorest_detect_shipping_method( $order );

    // Payment must exactly match items total (excludes shipping/unlinked items)
    $total = (float) round( array_sum( array_map( fn($i) => $i["price"] * $i["quantity"], $items ) ), 2 );

    $notes = $shipping_method === 'pickup'
        ? 'Lieferung: Abholung im Salon'
        : 'Lieferung: Versand an ' . $order->get_formatted_shipping_address();

    return [
        'number'   => 'woo-' . $order->get_order_number(),
        'clientId' => $client_id,
        'notes'    => $notes,
        'payments' => [ [
            'type'                     => 'OTHER',
            'customPaymentTypeId' => 'KP81EEXV9WrwwSKYuGgSOg',
            'amount'                   => (float) $total,
        ] ],
        'items'    => $items,
    ];
}

/* ----------------------------------------------------------
   5. Detect In Person Pickup vs Shipment
---------------------------------------------------------- */
function wa_phorest_detect_shipping_method( $order ) {
    foreach ( $order->get_shipping_methods() as $method ) {
        $method_id = $method->get_method_id();
        // Common pickup method IDs
        if ( in_array( $method_id, [ 'local_pickup', 'local_pickup_plus', 'pickup_location' ], true ) ) {
            return 'pickup';
        }
    }
    // If no shipping methods at all, treat as pickup
    if ( count( $order->get_shipping_methods() ) === 0 ) {
        return 'pickup';
    }
    return 'shipment';
}
/* ----------------------------------------------------------
   7. Admin: order list column + order detail meta box
---------------------------------------------------------- */

// Column in orders list
add_filter( 'manage_woocommerce_page_wc-orders_columns', 'wa_phorest_order_column', 20 );
add_filter( 'manage_edit-shop_order_columns',            'wa_phorest_order_column', 20 );

function wa_phorest_order_column( $columns ) {
    $columns['phorest_sync'] = 'Phorest';
    return $columns;
}

add_action( 'manage_woocommerce_page_wc-orders_custom_column', 'wa_phorest_order_column_content', 10, 2 );
add_action( 'manage_shop_order_posts_custom_column',            'wa_phorest_order_column_content', 10, 2 );

function wa_phorest_order_column_content( $column, $order_or_id ) {
    if ( $column !== 'phorest_sync' ) return;

    $order = is_object( $order_or_id ) ? $order_or_id : wc_get_order( $order_or_id );
    if ( ! $order ) return;

    $synced   = $order->get_meta( '_phorest_purchase_synced' );
    $error    = $order->get_meta( '_phorest_purchase_error' );
    $purch_id = $order->get_meta( '_phorest_purchase_id' );

    if ( $synced ) {
        echo '<span style="color:#00a32a;font-weight:600;" title="Purchase ID: ' . esc_attr( $purch_id ) . '">&#10003; Synced</span>';
        echo '<br><span style="color:#646970;font-size:11px;">' . esc_html( wp_date( 'd.m.Y H:i', strtotime( $synced ) ) ) . '</span>';
    } elseif ( $error ) {
        echo '<span style="color:#d63638;" title="' . esc_attr( $error ) . '">&#10007; Fehler</span>';
    } else {
        echo '<span style="color:#c3c4c7;">&#8212;</span>';
    }
}

// Meta box on order edit page with manual re-sync button
add_action( 'add_meta_boxes', function () {
    foreach ( [ 'shop_order', 'woocommerce_page_wc-orders' ] as $screen ) {
        add_meta_box(
            'wa-phorest-order',
            'Phorest Sync',
            'wa_phorest_order_meta_box',
            $screen,
            'side',
            'default'
        );
    }
} );

function wa_phorest_order_meta_box( $post_or_order ) {
    $order = is_a( $post_or_order, 'WC_Order' ) ? $post_or_order : wc_get_order( $post_or_order->ID );
    if ( ! $order ) return;

    $synced   = $order->get_meta( '_phorest_purchase_synced' );
    $error    = $order->get_meta( '_phorest_purchase_error' );
    $purch_id = $order->get_meta( '_phorest_purchase_id' );
    $client   = $order->get_meta( '_phorest_client_id' );
    ?>
    <style>
        #wa-phorest-order .wa-row { margin-bottom: 6px; font-size: 12px; }
        #wa-phorest-order .wa-label { color: #646970; }
        #wa-phorest-order .wa-ok  { color: #00a32a; font-weight: 600; }
        #wa-phorest-order .wa-err { color: #d63638; }
        #wa-phorest-resync { width: 100%; margin-top: 8px; }
        #wa-phorest-resync-result { font-size: 12px; margin-top: 6px; }
    </style>

    <?php if ( $synced ) : ?>
        <div class="wa-row"><span class="wa-ok">&#10003; Synchronisiert</span></div>
        <div class="wa-row"><span class="wa-label">Datum:</span> <?php echo esc_html( wp_date( 'd.m.Y H:i', strtotime( $synced ) ) ); ?></div>
        <?php if ( $purch_id ) : ?>
            <div class="wa-row"><span class="wa-label">Purchase ID:</span> <?php echo esc_html( $purch_id ); ?></div>
        <?php endif; ?>
        <?php if ( $client ) : ?>
            <div class="wa-row"><span class="wa-label">Client ID:</span> <?php echo esc_html( $client ); ?></div>
        <?php endif; ?>
    <?php elseif ( $error ) : ?>
        <div class="wa-row wa-err">&#10007; <?php echo esc_html( $error ); ?></div>
    <?php else : ?>
        <div class="wa-row" style="color:#646970;">Noch nicht synchronisiert.</div>
    <?php endif; ?>

    <button type="button" id="wa-phorest-resync" class="button button-secondary">
        <?php echo $synced ? 'Erneut synchronisieren' : 'Jetzt synchronisieren'; ?>
    </button>
    <div id="wa-phorest-resync-result"></div>

    <script>
    (function () {
        var btn    = document.getElementById('wa-phorest-resync');
        var result = document.getElementById('wa-phorest-resync-result');
        if (!btn) return;
        btn.addEventListener('click', function () {
            btn.disabled = true;
            btn.textContent = 'Synchronisiere …';
            result.textContent = '';
            fetch(ajaxurl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action:   'wa_phorest_resync_order',
                    nonce:    '<?php echo esc_js( wp_create_nonce( 'wa_phorest_resync_order' ) ); ?>',
                    order_id: '<?php echo esc_js( $order->get_id() ); ?>',
                }),
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                result.style.color = data.success ? '#00a32a' : '#d63638';
                result.textContent = data.data || (data.success ? 'Fertig.' : 'Fehler.');
                if (data.success) setTimeout(function () { location.reload(); }, 1500);
            })
            .catch(function () { result.style.color='#d63638'; result.textContent='Netzwerkfehler.'; })
            .finally(function () { btn.disabled = false; btn.textContent = 'Erneut synchronisieren'; });
        });
    })();
    </script>
    <?php
}

// AJAX re-sync handler
add_action( 'wp_ajax_wa_phorest_resync_order', function () {
    check_ajax_referer( 'wa_phorest_resync_order', 'nonce' );
    if ( ! current_user_can( 'edit_shop_orders' ) ) wp_send_json_error( 'Unauthorized' );

    $order_id = (int) ( $_POST['order_id'] ?? 0 );
    $order    = wc_get_order( $order_id );
    if ( ! $order ) wp_send_json_error( 'Bestellung nicht gefunden.' );

    // Reset sync state so it runs again
    $order->delete_meta_data( '_phorest_purchase_synced' );
    $order->delete_meta_data( '_phorest_purchase_error' );
    $order->save();

    wa_phorest_sync_order( $order_id );

    $order = wc_get_order( $order_id ); // reload
    if ( $order->get_meta( '_phorest_purchase_synced' ) ) {
        wp_send_json_success( 'Synchronisiert. Purchase ID: ' . $order->get_meta( '_phorest_purchase_id' ) );
    } else {
        wp_send_json_error( $order->get_meta( '_phorest_purchase_error' ) ?: 'Unbekannter Fehler.' );
    }
} );

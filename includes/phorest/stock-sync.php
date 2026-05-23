<?php
/* ============================================================
   DATEI: includes/phorest-stock-sync.php
   Syncs stock adjustments to Phorest when WC orders
   complete, are cancelled, or refunded.
============================================================ */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'WA_PHOREST_STOCK_DB_VERSION', '1.0' );
defined( 'WA_PHOREST_LINK_META' ) || define( 'WA_PHOREST_LINK_META', '_phorest_product_id' );

/* ----------------------------------------------------------
   Shared API helper (used by phorest-woo-sync.php too)
---------------------------------------------------------- */
if ( ! function_exists( 'wa_phorest_api' ) ) :
function wa_phorest_api( string $method, string $path, mixed $body = null ) {
    $api_url = get_option( 'wa_phorest_api_url', '' );
    $token   = get_option( 'wa_phorest_api_token', '' );

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

    $code     = wp_remote_retrieve_response_code( $response );
    $raw_body = wp_remote_retrieve_body( $response );

    if ( $code === 204 ) {
        return true; // No Content = success
    }

    $data = json_decode( $raw_body, true );

    if ( $code >= 400 ) {
        $msg = $data['message'] ?? $data['detail'] ?? $data['error'] ?? wp_remote_retrieve_response_message( $response );
        return new WP_Error( 'api_http_' . $code, 'HTTP ' . $code . ': ' . $msg . ' | ' . $raw_body );
    }

    return $data ?? true;
}
endif;

/* ----------------------------------------------------------
   DB table
---------------------------------------------------------- */
function wa_phorest_stock_table() {
    global $wpdb;
    return $wpdb->prefix . 'wa_phorest_stock_log';
}

function wa_phorest_stock_maybe_install() {
    if ( get_option( 'wa_phorest_stock_db_version' ) === WA_PHOREST_STOCK_DB_VERSION ) return;

    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    $table   = wa_phorest_stock_table();

    $sql = "CREATE TABLE {$table} (
        id           bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        order_id     bigint(20) UNSIGNED NOT NULL,
        product_id   bigint(20) UNSIGNED NOT NULL,
        product_name varchar(255) NOT NULL DEFAULT '',
        barcode      varchar(100) NOT NULL DEFAULT '',
        quantity     int(11)      NOT NULL DEFAULT 0,
        operation    varchar(10)  NOT NULL DEFAULT '',
        status       varchar(10)  NOT NULL DEFAULT '',
        error_msg    text,
        created_at   datetime     NOT NULL,
        PRIMARY KEY  (id),
        KEY order_id   (order_id),
        KEY created_at (created_at)
    ) {$charset};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );
    update_option( 'wa_phorest_stock_db_version', WA_PHOREST_STOCK_DB_VERSION );
}
add_action( 'init', 'wa_phorest_stock_maybe_install' );

/* ----------------------------------------------------------
   Log helper
---------------------------------------------------------- */
function wa_phorest_stock_log_entry( $order_id, $product_id, $product_name, $barcode, $quantity, $operation, $status, $error_msg = '' ) {
    global $wpdb;
    $wpdb->insert( wa_phorest_stock_table(), [
        'order_id'     => (int) $order_id,
        'product_id'   => (int) $product_id,
        'product_name' => $product_name,
        'barcode'      => $barcode,
        'quantity'     => (int) $quantity,
        'operation'    => $operation,
        'status'       => $status,
        'error_msg'    => $error_msg,
        'created_at'   => current_time( 'mysql' ),
    ], [ '%d', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s' ] );
}

/* ----------------------------------------------------------
   Core: send stock adjustment to Phorest
---------------------------------------------------------- */
function wa_phorest_send_stock_adjustment( $order_id, $operation ) {
    if ( ! get_option( 'wa_phorest_active', 0 ) ) return;

    $order = wc_get_order( $order_id );
    if ( ! $order ) return;

    $business_id = get_option( 'wa_phorest_business_id', '' );
    $branch_id   = get_option( 'wa_phorest_branch_id',   '' );

    $stocks_meta = [];
    $stocks_api  = [];

    foreach ( $order->get_items() as $item ) {
        $product_id = $item->get_product_id();
        if ( ! get_post_meta( $product_id, WA_PHOREST_LINK_META, true ) ) continue;

        $barcode = get_post_meta( $product_id, '_wc_gtin', true );
        if ( empty( $barcode ) ) continue;

        $stocks_meta[] = [
            'product_id'   => $product_id,
            'product_name' => $item->get_name(),
            'barcode'      => $barcode,
            'quantity'     => (int) $item->get_quantity(),
        ];
        $stocks_api[] = [
            'barcode'       => $barcode,
            'quantity'      => (int) $item->get_quantity(),
            'operationType' => $operation,
        ];
    }

    if ( empty( $stocks_api ) ) return;

    $path   = 'api/business/' . rawurlencode( $business_id ) . '/branch/' . rawurlencode( $branch_id ) . '/stock/adjustment';
    $result = wa_phorest_api( 'POST', $path, [ 'stocks' => $stocks_api ] );

    $is_error = is_wp_error( $result );
    $err_msg  = $is_error ? $result->get_error_message() : '';

    foreach ( $stocks_meta as $s ) {
        wa_phorest_stock_log_entry(
            $order_id, $s['product_id'], $s['product_name'],
            $s['barcode'], $s['quantity'], $operation,
            $is_error ? 'error' : 'success',
            $err_msg
        );
    }

    if ( $is_error ) {
        $order->add_order_note( 'Phorest Lager ' . $operation . ' Fehler: ' . $err_msg );
    } else {
        $label = $operation === 'DEDUCT' ? 'abgezogen' : 'erhöht';
        $order->add_order_note( 'Phorest Lager ' . $label . ': ' . count( $stocks_api ) . ' Produkt(e).' );
    }
    $order->save();
}

/* ----------------------------------------------------------
   Hooks
---------------------------------------------------------- */

// Order completed → DEDUCT
add_action( 'woocommerce_order_status_completed', function ( $order_id ) {
    $order = wc_get_order( $order_id );
    if ( ! $order || $order->get_meta( '_phorest_stock_deducted' ) ) return;

    wa_phorest_send_stock_adjustment( $order_id, 'DEDUCT' );

    $order->update_meta_data( '_phorest_stock_deducted', current_time( 'mysql' ) );
    $order->save();
}, 10 );

// Order cancelled → INCREASE (only if stock was previously deducted)
add_action( 'woocommerce_order_status_cancelled', function ( $order_id ) {
    $order = wc_get_order( $order_id );
    if ( ! $order || ! $order->get_meta( '_phorest_stock_deducted' ) ) return;

    wa_phorest_send_stock_adjustment( $order_id, 'INCREASE' );

    $order->delete_meta_data( '_phorest_stock_deducted' );
    $order->save();
}, 10 );

// Order refunded → INCREASE (only if stock was previously deducted)
add_action( 'woocommerce_order_status_refunded', function ( $order_id ) {
    $order = wc_get_order( $order_id );
    if ( ! $order || ! $order->get_meta( '_phorest_stock_deducted' ) ) return;

    wa_phorest_send_stock_adjustment( $order_id, 'INCREASE' );

    $order->delete_meta_data( '_phorest_stock_deducted' );
    $order->save();
}, 10 );

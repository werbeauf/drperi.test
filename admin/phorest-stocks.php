<?php
/* ============================================================
   DATEI: admin/phorest-stocks.php
   Stock adjustment history + manual adjustments.
============================================================ */

if ( ! defined( 'ABSPATH' ) ) exit;

/* ----------------------------------------------------------
   AJAX: manual stock adjustment
---------------------------------------------------------- */
add_action( 'wp_ajax_wa_phorest_manual_stock', function () {
    check_ajax_referer( 'wa_phorest_manual_stock', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized' );

    $product_id = (int) ( $_POST['product_id'] ?? 0 );
    $quantity   = (int) ( $_POST['quantity']   ?? 0 );
    $operation  = sanitize_text_field( $_POST['operation'] ?? '' );

    if ( ! $product_id || $quantity <= 0 || ! in_array( $operation, [ 'DEDUCT', 'INCREASE' ], true ) ) {
        wp_send_json_error( 'Ungültige Eingabe.' );
    }

    $barcode      = get_post_meta( $product_id, '_wc_gtin', true );
    $product_name = get_the_title( $product_id );

    if ( empty( $barcode ) ) {
        wp_send_json_error( 'Produkt hat keinen Barcode (GTIN).' );
    }

    $business_id = get_option( 'wa_phorest_business_id', '' );
    $branch_id   = get_option( 'wa_phorest_branch_id',   '' );
    $path        = 'api/business/' . rawurlencode( $business_id ) . '/branch/' . rawurlencode( $branch_id ) . '/stock/adjustment';

    $result   = wa_phorest_api( 'POST', $path, [
        'stocks' => [ [
            'barcode'       => $barcode,
            'quantity'      => $quantity,
            'operationType' => $operation,
        ] ],
    ] );

    $is_error = is_wp_error( $result );
    $err_msg  = $is_error ? $result->get_error_message() : '';

    wa_phorest_stock_log_entry(
        0, $product_id, $product_name, $barcode, $quantity, $operation,
        $is_error ? 'error' : 'success',
        $err_msg
    );

    if ( $is_error ) {
        wp_send_json_error( $err_msg );
    }

    $label = $operation === 'DEDUCT' ? 'abgezogen' : 'erhöht';
    wp_send_json_success( $quantity . 'x ' . $product_name . ' ' . $label . '.' );
} );

/* ----------------------------------------------------------
   Render page
---------------------------------------------------------- */
function wa_render_phorest_stocks_content() {
    global $wpdb;
    $table = wa_phorest_stock_table();

    // Products with Phorest link + barcode for manual form
    $linked_products = get_posts( [
        'post_type'      => 'product',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
        'meta_query'     => [
            'relation' => 'AND',
            [ 'key' => WA_PHOREST_LINK_META, 'compare' => 'EXISTS' ],
            [ 'key' => '_wc_gtin',           'compare' => 'EXISTS' ],
            [ 'key' => '_wc_gtin',           'value' => '',         'compare' => '!=' ],
        ],
    ] );

    // Filters
    $filter_op     = sanitize_text_field( $_GET['op']     ?? '' );
    $filter_status = sanitize_text_field( $_GET['status'] ?? '' );
    $filter_search = sanitize_text_field( $_GET['s']      ?? '' );
    $per_page      = 50;
    $current_page  = max( 1, (int) ( $_GET['paged'] ?? 1 ) );
    $offset        = ( $current_page - 1 ) * $per_page;

    // Build WHERE
    $where  = '1=1';
    $values = [];
    if ( $filter_op )     { $where .= ' AND operation = %s'; $values[] = $filter_op; }
    if ( $filter_status ) { $where .= ' AND status = %s';    $values[] = $filter_status; }
    if ( $filter_search ) {
        $like     = '%' . $wpdb->esc_like( $filter_search ) . '%';
        $where   .= ' AND (product_name LIKE %s OR barcode LIKE %s OR order_id = %d)';
        $values[] = $like;
        $values[] = $like;
        $values[] = (int) $filter_search;
    }

    $where_sql = $values ? $wpdb->prepare( $where, $values ) : $where;

    $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}" );
    $rows  = $wpdb->get_results( "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY created_at DESC LIMIT {$per_page} OFFSET {$offset}" );

    // Stats (always global, not filtered)
    $stats     = $wpdb->get_results( "SELECT operation, status, COUNT(*) as cnt FROM {$table} GROUP BY operation, status" );
    $total_all = $deducts = $increases = $errors = 0;
    foreach ( $stats as $s ) {
        $total_all += $s->cnt;
        if ( $s->operation === 'DEDUCT'   && $s->status === 'success' ) $deducts++;
        if ( $s->operation === 'INCREASE' && $s->status === 'success' ) $increases++;
        if ( $s->status === 'error' ) $errors += $s->cnt;
    }

    $total_pages = (int) ceil( $total / $per_page );
    $base_url    = add_query_arg( array_filter( [
        'page'   => 'wa-phorest-stocks',
        'op'     => $filter_op,
        'status' => $filter_status,
        's'      => $filter_search,
    ] ), admin_url( 'admin.php' ) );
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">Phorest Lager</h1>
        <hr class="wp-header-end">

        <style>
            .wa-stocks-wrap { max-width: 1100px; margin-top: 20px; }
            /* Manual card */
            .wa-manual-card { background: #fff; border: 1px solid #c3c4c7; margin-bottom: 24px; }
            .wa-manual-card-header { padding: 12px 18px; border-bottom: 1px solid #eaecf0; background: #fcfcfc; }
            .wa-manual-card-header h2 { margin: 0; font-size: 13px; font-weight: 600; }
            .wa-manual-card-body { padding: 16px 18px; display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap; }
            .wa-manual-card-body .wa-field { display: flex; flex-direction: column; gap: 4px; }
            .wa-manual-card-body label { font-size: 12px; font-weight: 600; color: #646970; }
            .wa-manual-card-body select { height: 32px; font-size: 13px; min-width: 200px; }
            .wa-manual-card-body input[type="number"] { height: 32px; font-size: 13px; width: 80px; }
            .wa-manual-card-body .button-primary { height: 32px; line-height: 30px; }
            #wa-manual-result { font-size: 13px; padding: 4px 0; min-width: 200px; }
            /* Stats */
            .wa-stats { display: flex; gap: 14px; margin-bottom: 24px; flex-wrap: wrap; }
            .wa-stat-box { background: #fff; border: 1px solid #c3c4c7; border-radius: 3px; padding: 14px 20px; min-width: 120px; text-align: center; }
            .wa-stat-box .num { font-size: 26px; font-weight: 700; line-height: 1.1; margin-bottom: 4px; }
            .wa-stat-box .lbl { font-size: 12px; color: #646970; }
            .wa-stat-box.deduct   .num { color: #d63638; }
            .wa-stat-box.increase .num { color: #00a32a; }
            .wa-stat-box.err      .num { color: #dba617; }
            /* Filters */
            .wa-filters { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; margin-bottom: 16px; }
            .wa-filters select, .wa-filters input[type="search"] { height: 32px; font-size: 13px; }
            /* Table */
            table.wa-stock-table { border-collapse: collapse; width: 100%; background: #fff; border: 1px solid #c3c4c7; }
            table.wa-stock-table th { background: #f6f7f7; padding: 8px 12px; text-align: left; font-size: 12px; font-weight: 600; border-bottom: 2px solid #c3c4c7; white-space: nowrap; }
            table.wa-stock-table td { padding: 8px 12px; border-bottom: 1px solid #f0f0f1; font-size: 13px; vertical-align: middle; }
            table.wa-stock-table tr:last-child td { border-bottom: none; }
            table.wa-stock-table tr:hover td { background: #f9f9f9; }
            .op-badge { display: inline-block; padding: 2px 8px; border-radius: 3px; font-size: 11px; font-weight: 700; letter-spacing: .3px; }
            .op-badge.DEDUCT   { background: #fcf0f1; color: #d63638; }
            .op-badge.INCREASE { background: #edfaef; color: #00a32a; }
            .status-dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-right: 5px; vertical-align: middle; }
            .status-dot.success { background: #00a32a; }
            .status-dot.error   { background: #d63638; }
            .wa-err-tip { color: #d63638; font-size: 11px; cursor: help; border-bottom: 1px dashed #d63638; }
            .wa-tag-manual { display: inline-block; padding: 1px 6px; border-radius: 3px; font-size: 10px; font-weight: 600; background: #e8e8e8; color: #646970; vertical-align: middle; }
            /* Pagination */
            .wa-pagination { margin-top: 14px; display: flex; align-items: center; gap: 4px; }
            .wa-pagination a, .wa-pagination span.pg { padding: 4px 9px; border: 1px solid #c3c4c7; border-radius: 3px; font-size: 13px; text-decoration: none; color: #2271b1; background: #fff; }
            .wa-pagination span.current { padding: 4px 9px; border: 1px solid #2271b1; border-radius: 3px; font-size: 13px; background: #2271b1; color: #fff; }
            .wa-pagination a:hover { background: #f0f0f1; }
            .wa-empty { padding: 40px; text-align: center; color: #646970; background: #fff; border: 1px solid #c3c4c7; }
        </style>

        <div class="wa-stocks-wrap">

            <!-- Manual adjustment -->
            <div class="wa-manual-card">
                <div class="wa-manual-card-header"><h2>Lager manuell anpassen</h2></div>
                <div class="wa-manual-card-body">
                    <?php if ( empty( $linked_products ) ) : ?>
                        <p style="color:#646970;font-size:13px;margin:0;"><?php esc_html_e( 'Keine Produkte mit Phorest-Verknüpfung und Barcode gefunden.', 'werbeauf-customs' ); ?></p>
                    <?php else : ?>
                    <div class="wa-field">
                        <label for="wa-manual-product">Produkt</label>
                        <select id="wa-manual-product">
                            <option value="">— Produkt wählen —</option>
                            <?php foreach ( $linked_products as $p ) :
                                $bc = get_post_meta( $p->ID, '_wc_gtin', true );
                            ?>
                                <option value="<?php echo esc_attr( $p->ID ); ?>"
                                        data-barcode="<?php echo esc_attr( $bc ); ?>">
                                    <?php echo esc_html( $p->post_title ); ?> &mdash; <small><?php echo esc_html( $bc ); ?></small>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="wa-field">
                        <label for="wa-manual-qty">Menge</label>
                        <input type="number" id="wa-manual-qty" value="1" min="1" max="9999">
                    </div>
                    <div class="wa-field">
                        <label for="wa-manual-op">Operation</label>
                        <select id="wa-manual-op" style="min-width:130px;">
                            <option value="DEDUCT">DEDUCT</option>
                            <option value="INCREASE">INCREASE</option>
                        </select>
                    </div>
                    <div class="wa-field">
                        <label>&nbsp;</label>
                        <button type="button" id="wa-manual-submit" class="button button-primary"><?php esc_html_e( 'Anpassen', 'werbeauf-customs' ); ?></button>
                    </div>
                    <div class="wa-field">
                        <label>&nbsp;</label>
                        <div id="wa-manual-result"></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Stats -->
            <div class="wa-stats">
                <div class="wa-stat-box">
                    <div class="num"><?php echo esc_html( $total_all ); ?></div>
                    <div class="lbl"><?php esc_html_e( 'Gesamt', 'werbeauf-customs' ); ?></div>
                </div>
                <div class="wa-stat-box deduct">
                    <div class="num"><?php echo esc_html( $deducts ); ?></div>
                    <div class="lbl"><?php esc_html_e( 'Abgezogen', 'werbeauf-customs' ); ?></div>
                </div>
                <div class="wa-stat-box increase">
                    <div class="num"><?php echo esc_html( $increases ); ?></div>
                    <div class="lbl"><?php esc_html_e( 'Erhöht', 'werbeauf-customs' ); ?></div>
                </div>
                <?php if ( $errors > 0 ) : ?>
                <div class="wa-stat-box err">
                    <div class="num"><?php echo esc_html( $errors ); ?></div>
                    <div class="lbl"><?php esc_html_e( 'Fehler', 'werbeauf-customs' ); ?></div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Filters -->
            <form method="get" action="">
                <input type="hidden" name="page" value="wa-phorest-stocks">
                <div class="wa-filters">
                    <input type="search" name="s" value="<?php echo esc_attr( $filter_search ); ?>"
                           placeholder="<?php esc_attr_e( 'Bestellung #, Produkt, Barcode …', 'werbeauf-customs' ); ?>" style="width:220px;">
                    <select name="op">
                        <option value=""><?php esc_html_e( 'Alle Operationen', 'werbeauf-customs' ); ?></option>
                        <option value="DEDUCT"   <?php selected( $filter_op, 'DEDUCT' ); ?>>DEDUCT</option>
                        <option value="INCREASE" <?php selected( $filter_op, 'INCREASE' ); ?>>INCREASE</option>
                    </select>
                    <select name="status">
                        <option value=""><?php esc_html_e( 'Alle Status', 'werbeauf-customs' ); ?></option>
                        <option value="success" <?php selected( $filter_status, 'success' ); ?>><?php esc_html_e( 'Erfolgreich', 'werbeauf-customs' ); ?></option>
                        <option value="error"   <?php selected( $filter_status, 'error' ); ?>><?php esc_html_e( 'Fehler', 'werbeauf-customs' ); ?></option>
                    </select>
                    <button type="submit" class="button"><?php esc_html_e( 'Filtern', 'werbeauf-customs' ); ?></button>
                    <?php if ( $filter_op || $filter_status || $filter_search ) : ?>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=wa-phorest-stocks' ) ); ?>" class="button"><?php esc_html_e( 'Zurücksetzen', 'werbeauf-customs' ); ?></a>
                    <?php endif; ?>
                </div>
            </form>

            <!-- History table -->
            <?php if ( empty( $rows ) ) : ?>
                <div class="wa-empty"><?php esc_html_e( 'Keine Einträge gefunden.', 'werbeauf-customs' ); ?></div>
            <?php else : ?>

            <table class="wa-stock-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Datum', 'werbeauf-customs' ); ?></th>
                        <th><?php esc_html_e( 'Quelle', 'werbeauf-customs' ); ?></th>
                        <th><?php esc_html_e( 'Produkt', 'werbeauf-customs' ); ?></th>
                        <th><?php esc_html_e( 'Barcode', 'werbeauf-customs' ); ?></th>
                        <th style="text-align:center;"><?php esc_html_e( 'Menge', 'werbeauf-customs' ); ?></th>
                        <th><?php esc_html_e( 'Operation', 'werbeauf-customs' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'werbeauf-customs' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ( $rows as $row ) : ?>
                    <tr>
                        <td style="white-space:nowrap;color:#646970;font-size:12px;">
                            <?php echo esc_html( wp_date( 'd.m.Y H:i', strtotime( $row->created_at ) ) ); ?>
                        </td>
                        <td>
                            <?php if ( (int) $row->order_id === 0 ) : ?>
                                <span class="wa-tag-manual"><?php esc_html_e( 'Manuell', 'werbeauf-customs' ); ?></span>
                            <?php else : ?>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-orders&action=edit&id=' . $row->order_id ) ); ?>">
                                    #<?php echo esc_html( $row->order_id ); ?>
                                </a>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html( $row->product_name ); ?></td>
                        <td><code style="font-size:11px;"><?php echo esc_html( $row->barcode ); ?></code></td>
                        <td style="text-align:center;font-weight:600;"><?php echo esc_html( $row->quantity ); ?></td>
                        <td><span class="op-badge <?php echo esc_attr( $row->operation ); ?>"><?php echo esc_html( $row->operation ); ?></span></td>
                        <td>
                            <span class="status-dot <?php echo esc_attr( $row->status ); ?>"></span>
                            <?php if ( $row->status === 'error' && $row->error_msg ) : ?>
                                <span class="wa-err-tip" title="<?php echo esc_attr( $row->error_msg ); ?>"><?php esc_html_e( 'Fehler', 'werbeauf-customs' ); ?> &#9432;</span>
                            <?php else : ?>
                                <?php echo $row->status === 'success' ? esc_html__( 'OK', 'werbeauf-customs' ) : esc_html( $row->status ); ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <?php if ( $total_pages > 1 ) : ?>
            <div class="wa-pagination">
                <?php
                if ( $current_page > 1 ) {
                    echo '<a href="' . esc_url( add_query_arg( 'paged', $current_page - 1, $base_url ) ) . '">&laquo;</a>';
                }
                for ( $p = 1; $p <= $total_pages; $p++ ) {
                    if ( $p === $current_page ) {
                        echo '<span class="current">' . $p . '</span>';
                    } elseif ( $p === 1 || $p === $total_pages || abs( $p - $current_page ) <= 2 ) {
                        echo '<a href="' . esc_url( add_query_arg( 'paged', $p, $base_url ) ) . '">' . $p . '</a>';
                    } elseif ( abs( $p - $current_page ) === 3 ) {
                        echo '<span class="pg">&hellip;</span>';
                    }
                }
                if ( $current_page < $total_pages ) {
                    echo '<a href="' . esc_url( add_query_arg( 'paged', $current_page + 1, $base_url ) ) . '">&raquo;</a>';
                }
                ?>
            </div>
            <?php endif; ?>

            <p style="color:#646970;font-size:12px;margin-top:12px;">
                <?php echo esc_html( sprintf( _n( '%d Eintrag gefunden', '%d Einträge gefunden', $total, 'werbeauf-customs' ), $total ) ); ?>
            </p>

            <?php endif; ?>

        </div><!-- .wa-stocks-wrap -->
    </div><!-- .wrap -->

    <script>
    (function () {
        var btn    = document.getElementById('wa-manual-submit');
        var result = document.getElementById('wa-manual-result');
        if ( ! btn ) return;

        btn.addEventListener('click', function () {
            var product_id = document.getElementById('wa-manual-product').value;
            var quantity   = document.getElementById('wa-manual-qty').value;
            var operation  = document.getElementById('wa-manual-op').value;

            if ( ! product_id ) {
                result.style.color = '#d63638';
                result.textContent = 'Bitte Produkt wählen.';
                return;
            }

            btn.disabled = true;
            btn.textContent = 'Wird gesendet …';
            result.textContent = '';

            fetch(ajaxurl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action:     'wa_phorest_manual_stock',
                    nonce:      '<?php echo esc_js( wp_create_nonce( 'wa_phorest_manual_stock' ) ); ?>',
                    product_id: product_id,
                    quantity:   quantity,
                    operation:  operation,
                }),
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                result.style.color = data.success ? '#00a32a' : '#d63638';
                result.textContent = data.data || ( data.success ? 'Fertig.' : 'Fehler.' );
                if ( data.success ) {
                    setTimeout(function () { location.reload(); }, 1200 );
                }
            })
            .catch(function () {
                result.style.color = '#d63638';
                result.textContent = 'Netzwerkfehler.';
            })
            .finally(function () {
                btn.disabled = false;
                btn.textContent = 'Anpassen';
            });
        });
    })();
    </script>
    <?php
}

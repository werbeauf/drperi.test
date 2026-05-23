<?php
/* ============================================================
   DATEI: admin/phorest-newsletter.php
   ZWECK: Newsletter-Log Admin-Page (?page=wa-phorest-newsletter)

   Liest den Ringbuffer wp_options['wa_newsletter_log'] (siehe
   includes/phorest/newsletter.php) und rendert ihn als Tabelle.

   Render-Funktion: wa_render_phorest_newsletter_content()
   wird von admin/admin-menu.php als Submenue geladen.
============================================================ */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ----------------------------------------------------------
   AJAX -- Clear log
---------------------------------------------------------- */
add_action( 'wp_ajax_wa_phorest_newsletter_clear', function () {
    check_ajax_referer( 'wa_phorest_newsletter', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Unauthorized' );
    }

    delete_option( WA_NL_LOG_OPTION );
    wp_send_json_success( 'Log geleert.' );
} );

/* ----------------------------------------------------------
   Render page
---------------------------------------------------------- */
function wa_render_phorest_newsletter_content() {
    $log = get_option( WA_NL_LOG_OPTION, array() );
    if ( ! is_array( $log ) ) {
        $log = array();
    }

    // Newest first
    $log = array_reverse( $log );

    // Counts by status
    $counts = array( 'created' => 0, 'updated' => 0, 'already_subscribed' => 0, 'error' => 0 );
    foreach ( $log as $entry ) {
        $s = isset( $entry['status'] ) ? $entry['status'] : 'error';
        if ( ! isset( $counts[ $s ] ) ) {
            $counts[ $s ] = 0;
        }
        $counts[ $s ]++;
    }

    $nonce = wp_create_nonce( 'wa_phorest_newsletter' );
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">Newsletter Log</h1>
        <hr class="wp-header-end">

        <style>
            .wa-nl-wrap { max-width: 1100px; margin-top: 24px; }
            .wa-nl-toolbar { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; margin-bottom: 24px; }
            .wa-nl-stats { display: flex; gap: 12px; flex-wrap: wrap; margin-left: auto; }
            .wa-nl-pill { display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; background: #f0f0f1; color: #1d2327; }
            .wa-nl-pill .dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
            .wa-nl-pill.created  { background: #edfaef; color: #00a32a; }
            .wa-nl-pill.updated  { background: #e6f0fa; color: #1769aa; }
            .wa-nl-pill.already  { background: #f0f0f1; color: #646970; }
            .wa-nl-pill.error    { background: #fcf0f1; color: #d63638; }
            .wa-nl-card { background: #fff; border: 1px solid #c3c4c7; box-shadow: 0 1px 1px rgba(0,0,0,.04); }
            .wa-nl-card-header { padding: 14px 20px; border-bottom: 1px solid #eaecf0; background: #fcfcfc; display: flex; align-items: center; justify-content: space-between; }
            .wa-nl-card-header h2 { margin: 0; font-size: 14px; font-weight: 600; }
            .wa-nl-table { width: 100%; border-collapse: collapse; font-size: 13px; }
            .wa-nl-table th { background: #f6f7f7; padding: 10px 14px; text-align: left; font-weight: 600; border-bottom: 2px solid #eaecf0; white-space: nowrap; }
            .wa-nl-table td { padding: 10px 14px; border-bottom: 1px solid #f0f0f1; vertical-align: top; }
            .wa-nl-table tr:last-child td { border-bottom: none; }
            .wa-nl-table tr:hover td { background: #f6f7f7; }
            .wa-nl-table code { font-size: 11px; background: #f0f0f1; padding: 2px 6px; border-radius: 3px; }
            .wa-nl-status { display: inline-block; padding: 2px 10px; border-radius: 10px; font-size: 11px; font-weight: 600; }
            .wa-nl-status.created  { background: #edfaef; color: #00a32a; }
            .wa-nl-status.updated  { background: #e6f0fa; color: #1769aa; }
            .wa-nl-status.already  { background: #f0f0f1; color: #646970; }
            .wa-nl-status.error    { background: #fcf0f1; color: #d63638; }
            .wa-nl-source { display: inline-block; padding: 2px 8px; border-radius: 3px; font-size: 11px; font-weight: 600; background: #f0f0f1; color: #646970; }
            .wa-nl-empty { padding: 60px 20px; text-align: center; color: #646970; }
            .wa-nl-empty p { font-size: 14px; margin: 8px 0 0; }
            #wa-nl-action-badge { display: none; padding: 5px 12px; border-radius: 3px; font-size: 12px; font-weight: 600; }
            #wa-nl-action-badge.success { background: #edfaef; color: #00a32a; }
            #wa-nl-action-badge.error   { background: #fcf0f1; color: #d63638; }
        </style>

        <div class="wa-nl-wrap">

            <div class="wa-nl-toolbar">
                <button type="button" id="wa-nl-clear-btn" class="button button-secondary"<?php disabled( empty( $log ) ); ?>><?php esc_html_e( 'Log leeren', 'werbeauf-customs' ); ?></button>
                <span id="wa-nl-action-badge"></span>
                <div class="wa-nl-stats">
                    <span class="wa-nl-pill created"><span class="dot" style="background:currentColor"></span> <?php echo (int) $counts['created']; ?> <?php esc_html_e( 'Created', 'werbeauf-customs' ); ?></span>
                    <span class="wa-nl-pill updated"><span class="dot" style="background:currentColor"></span> <?php echo (int) $counts['updated']; ?> <?php esc_html_e( 'Updated', 'werbeauf-customs' ); ?></span>
                    <span class="wa-nl-pill already"><span class="dot" style="background:currentColor"></span> <?php echo (int) $counts['already_subscribed']; ?> <?php esc_html_e( 'Bereits', 'werbeauf-customs' ); ?></span>
                    <span class="wa-nl-pill error"><span class="dot" style="background:currentColor"></span> <?php echo (int) $counts['error']; ?> <?php esc_html_e( 'Fehler', 'werbeauf-customs' ); ?></span>
                </div>
            </div>

            <div class="wa-nl-card">
                <div class="wa-nl-card-header">
                    <h2><?php esc_html_e( 'Einträge', 'werbeauf-customs' ); ?></h2>
                    <span style="color:#646970;font-size:12px;">
                        <?php esc_html_e( 'Neueste oben', 'werbeauf-customs' ); ?> &middot; <?php echo esc_html( sprintf( __( 'max %d Einträge', 'werbeauf-customs' ), (int) WA_NL_LOG_MAX ) ); ?>
                    </span>
                </div>

                <?php if ( empty( $log ) ) : ?>
                    <div class="wa-nl-empty">
                        <strong><?php esc_html_e( 'Noch keine Anmeldungen.', 'werbeauf-customs' ); ?></strong>
                        <p><?php esc_html_e( 'Sobald sich jemand ueber das Widget oder den Checkout anmeldet, erscheint hier ein Eintrag.', 'werbeauf-customs' ); ?></p>
                    </div>
                <?php else : ?>
                    <table class="wa-nl-table">
                        <thead>
                            <tr>
                                <th>Datum</th>
                                <th>Status</th>
                                <th>Quelle</th>
                                <th>Email-Hash (sha1)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $log as $entry ) :
                                $time   = isset( $entry['time'] )   ? $entry['time']   : '';
                                $status = isset( $entry['status'] ) ? $entry['status'] : 'error';
                                $source = isset( $entry['source'] ) ? $entry['source'] : '';
                                $hash   = isset( $entry['hash'] )   ? $entry['hash']   : '';

                                $status_class = $status === 'already_subscribed' ? 'already' : $status;
                                $status_label = array(
                                    'created'            => 'Created',
                                    'updated'            => 'Updated',
                                    'already_subscribed' => 'Bereits',
                                    'error'              => 'Fehler',
                                );
                                ?>
                                <tr>
                                    <td><?php echo esc_html( $time ? wp_date( 'd.m.Y H:i:s', strtotime( $time ) ) : '-' ); ?></td>
                                    <td><span class="wa-nl-status <?php echo esc_attr( $status_class ); ?>"><?php echo esc_html( $status_label[ $status ] ?? $status ); ?></span></td>
                                    <td><span class="wa-nl-source"><?php echo esc_html( $source ); ?></span></td>
                                    <td><code><?php echo esc_html( $hash ); ?></code></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
    (function () {
        var clearBtn = document.getElementById('wa-nl-clear-btn');
        var badge    = document.getElementById('wa-nl-action-badge');
        if ( ! clearBtn ) return;

        clearBtn.addEventListener('click', function () {
            if ( ! confirm('<?php echo esc_js( __( 'Wirklich alle Log-Einträge löschen?', 'werbeauf-customs' ) ); ?>') ) return;
            clearBtn.disabled = true;
            clearBtn.textContent = '<?php echo esc_js( __( 'Lösche...', 'werbeauf-customs' ) ); ?>';
            badge.style.display = 'none';

            fetch(ajaxurl, {
                method:  'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body:    new URLSearchParams({
                    action: 'wa_phorest_newsletter_clear',
                    nonce:  '<?php echo esc_js( $nonce ); ?>'
                })
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                badge.style.display = 'inline-block';
                if (data.success) {
                    badge.className = 'success';
                    badge.textContent = data.data;
                    setTimeout(function () { window.location.reload(); }, 800);
                } else {
                    badge.className = 'error';
                    badge.textContent = data.data || 'Fehler.';
                    clearBtn.disabled = false;
                    clearBtn.textContent = 'Log leeren';
                }
            })
            .catch(function () {
                badge.style.display = 'inline-block';
                badge.className = 'error';
                badge.textContent = 'Netzwerkfehler.';
                clearBtn.disabled = false;
                clearBtn.textContent = 'Log leeren';
            });
        });
    })();
    </script>
    <?php
}

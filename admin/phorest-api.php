<?php
/* ============================================================
   DATEI: admin/phorest-api.php
============================================================ */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ----------------------------------------------------------
   Save settings
---------------------------------------------------------- */
add_action( 'admin_post_wa_save_phorest_settings', function () {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Unauthorized' );
    }
    check_admin_referer( 'wa_phorest_settings' );

    update_option( 'wa_phorest_active',      isset( $_POST['wa_phorest_active'] ) ? 1 : 0 );
    update_option( 'wa_phorest_business_id', sanitize_text_field( $_POST['wa_phorest_business_id'] ?? '' ) );
    update_option( 'wa_phorest_branch_id',   sanitize_text_field( $_POST['wa_phorest_branch_id'] ?? '' ) );
    update_option( 'wa_phorest_api_url',     esc_url_raw( $_POST['wa_phorest_api_url'] ?? '' ) );
    update_option( 'wa_phorest_api_token',   sanitize_text_field( $_POST['wa_phorest_api_token'] ?? '' ) );

    wp_redirect( add_query_arg( [
        'page'    => 'wa-phorest-api',
        'updated' => '1',
    ], admin_url( 'admin.php' ) ) );
    exit;
} );

/* ----------------------------------------------------------
   AJAX – Test Connection
---------------------------------------------------------- */
add_action( 'wp_ajax_wa_phorest_test_connection', function () {
    check_ajax_referer( 'wa_phorest_test', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Unauthorized' );
    }

    $business_id = get_option( 'wa_phorest_business_id', '' );
    $api_url     = get_option( 'wa_phorest_api_url',     '' );
    $api_token   = get_option( 'wa_phorest_api_token',   '' );

    if ( empty( $business_id ) || empty( $api_url ) || empty( $api_token ) ) {
        wp_send_json_error( 'Bitte zuerst alle Pflichtfelder speichern.' );
    }

    $endpoint = trailingslashit( $api_url ) . 'api/business/' . rawurlencode( $business_id ) . '/branch';

    $response = wp_remote_get( $endpoint, [
        'headers' => [
            'Authorization' => 'Basic ' . $api_token,
            'Content-Type'  => 'application/json',
        ],
        'timeout'   => 10,
        'sslverify' => true,
    ] );

    if ( is_wp_error( $response ) ) {
        wp_send_json_error( $response->get_error_message() );
    }

    $code = wp_remote_retrieve_response_code( $response );
    if ( $code === 200 ) {
        wp_send_json_success( 'Verbindung erfolgreich (HTTP 200).' );
    } else {
        wp_send_json_error( 'HTTP ' . $code . ' – ' . wp_remote_retrieve_response_message( $response ) );
    }
} );

/* ----------------------------------------------------------
   Render page
---------------------------------------------------------- */
function wa_render_phorest_api_content() {
    $active      = (bool) get_option( 'wa_phorest_active',      false );
    $business_id = get_option( 'wa_phorest_business_id', '' );
    $branch_id   = get_option( 'wa_phorest_branch_id',   '' );
    $api_url     = get_option( 'wa_phorest_api_url',     'https://api-gateway-eu.phorest.com/third-party-api-server' );
    $api_token   = get_option( 'wa_phorest_api_token',   '' );
    $updated     = isset( $_GET['updated'] );
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline">Phorest API</h1>
        <hr class="wp-header-end">

        <?php if ( $updated ) : ?>
            <div class="notice notice-success is-dismissible"><p>Einstellungen gespeichert.</p></div>
        <?php endif; ?>

        <style>
            .wa-phorest-wrap { max-width: 780px; margin-top: 24px; }
            .wa-phorest-card { background: #fff; border: 1px solid #c3c4c7; box-shadow: 0 1px 1px rgba(0,0,0,.04); margin-bottom: 24px; }
            .wa-phorest-card-header { padding: 16px 20px; border-bottom: 1px solid #eaecf0; background: #fcfcfc; display: flex; align-items: center; gap: 10px; }
            .wa-phorest-card-header h2 { margin: 0; font-size: 14px; font-weight: 600; }
            .wa-phorest-card-body { padding: 20px; }
            .wa-field { margin-bottom: 18px; }
            .wa-field label { display: block; font-weight: 600; margin-bottom: 6px; font-size: 13px; }
            .wa-field input[type="text"],
            .wa-field input[type="url"],
            .wa-field input[type="password"] { width: 100%; max-width: 540px; }
            .wa-field .wa-desc { color: #646970; font-size: 12px; margin-top: 4px; }
            /* Toggle switch */
            .wa-toggle-wrap { display: flex; align-items: center; gap: 12px; }
            .wa-toggle { position: relative; display: inline-block; width: 44px; height: 24px; flex-shrink: 0; }
            .wa-toggle input { opacity: 0; width: 0; height: 0; }
            .wa-toggle-slider { position: absolute; inset: 0; background: #c3c4c7; border-radius: 24px; cursor: pointer; transition: background .2s; }
            .wa-toggle-slider:before { content: ''; position: absolute; width: 18px; height: 18px; left: 3px; top: 3px; background: #fff; border-radius: 50%; transition: transform .2s; }
            .wa-toggle input:checked + .wa-toggle-slider { background: #00a32a; }
            .wa-toggle input:checked + .wa-toggle-slider:before { transform: translateX(20px); }
            .wa-toggle-label { font-weight: 600; font-size: 13px; }
            /* API Status */
            .wa-status-row { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }
            .wa-status-badge { display: inline-flex; align-items: center; gap: 6px; padding: 5px 12px; border-radius: 3px; font-size: 12px; font-weight: 600; background: #f0f0f1; color: #646970; }
            .wa-status-badge.success { background: #edfaef; color: #00a32a; }
            .wa-status-badge.error   { background: #fcf0f1; color: #d63638; }
            .wa-status-badge .dot { width: 8px; height: 8px; border-radius: 50%; background: currentColor; flex-shrink: 0; }
            #wa-test-btn { min-width: 140px; }
            #wa-test-btn.loading { opacity: .7; pointer-events: none; }
        </style>

        <div class="wa-phorest-wrap">

            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'wa_phorest_settings' ); ?>
                <input type="hidden" name="action" value="wa_save_phorest_settings">

                <!-- Activate -->
                <div class="wa-phorest-card">
                    <div class="wa-phorest-card-header">
                        <h2>Status</h2>
                    </div>
                    <div class="wa-phorest-card-body">
                        <div class="wa-toggle-wrap">
                            <label class="wa-toggle">
                                <input type="checkbox" name="wa_phorest_active" value="1" <?php checked( $active ); ?>>
                                <span class="wa-toggle-slider"></span>
                            </label>
                            <span class="wa-toggle-label">Phorest API aktivieren</span>
                        </div>
                    </div>
                </div>

                <!-- Credentials -->
                <div class="wa-phorest-card">
                    <div class="wa-phorest-card-header">
                        <h2>API Zugangsdaten</h2>
                    </div>
                    <div class="wa-phorest-card-body">

                        <div class="wa-field">
                            <label for="wa_phorest_business_id">Business ID <span style="color:#d63638">*</span></label>
                            <input type="text" id="wa_phorest_business_id" name="wa_phorest_business_id"
                                   value="<?php echo esc_attr( $business_id ); ?>" required class="regular-text">
                            <p class="wa-desc">Die Phorest Business ID deines Unternehmens.</p>
                        </div>

                        <div class="wa-field">
                            <label for="wa_phorest_branch_id">Branch ID <span style="color:#d63638">*</span></label>
                            <input type="text" id="wa_phorest_branch_id" name="wa_phorest_branch_id"
                                   value="<?php echo esc_attr( $branch_id ); ?>" required class="regular-text">
                            <p class="wa-desc">Die ID des Phorest-Standorts (Branch).</p>
                        </div>

                        <div class="wa-field">
                            <label for="wa_phorest_api_url">API-URL Third Party <span style="color:#d63638">*</span></label>
                            <input type="url" id="wa_phorest_api_url" name="wa_phorest_api_url"
                                   value="<?php echo esc_attr( $api_url ); ?>" required class="regular-text"
                                   placeholder="https://api-gateway-eu.phorest.com/third-party-api-server">
                            <p class="wa-desc">Basis-URL der Phorest Third-Party API (ohne abschließenden Slash).</p>
                        </div>

                        <div class="wa-field">
                            <label for="wa_phorest_api_token">API-Token (Base64)</label>
                            <input type="password" id="wa_phorest_api_token" name="wa_phorest_api_token"
                                   value="<?php echo esc_attr( $api_token ); ?>" class="regular-text"
                                   autocomplete="new-password">
                            <p class="wa-desc">Base64-kodierter Token im Format <code>username:password</code>. Wird als <code>Authorization: Basic …</code> Header gesendet.</p>
                        </div>

                        <?php submit_button( 'Einstellungen speichern' ); ?>
                    </div>
                </div>

            </form>

            <!-- API Status / Test Connection -->
            <div class="wa-phorest-card">
                <div class="wa-phorest-card-header">
                    <h2>API Status</h2>
                </div>
                <div class="wa-phorest-card-body">
                    <div class="wa-status-row">
                        <button type="button" id="wa-test-btn" class="button button-secondary">
                            Verbindung testen
                        </button>
                        <span id="wa-status-badge" class="wa-status-badge" style="display:none">
                            <span class="dot"></span>
                            <span id="wa-status-text"></span>
                        </span>
                    </div>
                </div>
            </div>


        </div><!-- .wa-phorest-wrap -->
    </div><!-- .wrap -->

    <script>
    (function () {
        var btn    = document.getElementById('wa-test-btn');
        var badge  = document.getElementById('wa-status-badge');
        var text   = document.getElementById('wa-status-text');

        btn.addEventListener('click', function () {
            btn.classList.add('loading');
            btn.textContent = 'Teste …';
            badge.style.display = 'none';
            badge.className = 'wa-status-badge';

            fetch(ajaxurl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'wa_phorest_test_connection',
                    nonce:  '<?php echo esc_js( wp_create_nonce( 'wa_phorest_test' ) ); ?>',
                }),
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                badge.style.display = 'inline-flex';
                if (data.success) {
                    badge.classList.add('success');
                    text.textContent = data.data;
                } else {
                    badge.classList.add('error');
                    text.textContent = data.data || 'Verbindung fehlgeschlagen.';
                }
            })
            .catch(function () {
                badge.style.display = 'inline-flex';
                badge.classList.add('error');
                text.textContent = 'Netzwerkfehler.';
            })
            .finally(function () {
                btn.classList.remove('loading');
                btn.textContent = 'Verbindung testen';
            });
        });
    })();
    </script>
    <?php
}

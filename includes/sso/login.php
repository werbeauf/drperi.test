<?php
/* ============================================================
   DATEI: includes/sso/login.php
   ZWECK: Login-Endpoint. Erkennt ?wa_sso=TOKEN, validiert,
          loggt User ein, konsumiert das Token, und routet
          entweder direkt zum Redirect (safe Action) oder zur
          Confirm-Page (state-changing Action).

   Hook-Reihenfolge:
     init priority  5  -> 00-bootstrap.php self-install
     init priority  7  -> actions.php register defaults
     init priority  9  -> THIS file (Login-Endpoint)
     init priority 10  -> confirm.php (Confirm-Page render)

   Endpoint laueft auf jeder URL via $_GET['wa_sso']. Wir nutzen
   bewusst KEINE rewrite rule -- spart das flush_rewrite_rules
   beim Aktivieren und funktioniert auf jeder Permalink-Struktur.
============================================================ */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'init', 'wa_sso_handle_login_endpoint', 9 );

function wa_sso_handle_login_endpoint() {
    if ( empty( $_GET[ WA_SSO_QUERY_VAR ] ) ) {
        return;
    }
    if ( ! wa_sso_is_enabled() ) {
        wa_sso_render_error_page(
            __( 'SSO-Login ist deaktiviert', 'werbeauf-customs' ),
            __( 'Bitte mit Benutzername und Passwort anmelden.', 'werbeauf-customs' )
        );
    }

    // Nur GET zulassen -- Email-Links sind GET.
    if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'GET' !== strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) ) {
        wa_sso_render_error_page(
            __( 'Ungueltige Anfrage', 'werbeauf-customs' ),
            __( 'Dieser Link kann nur per Klick aus einer Email geoeffnet werden.', 'werbeauf-customs' )
        );
    }

    $raw_token = sanitize_text_field( wp_unslash( $_GET[ WA_SSO_QUERY_VAR ] ) );

    /**
     * Optional: IP-Beschraenkung. Standard: alle IPs erlaubt.
     * Filter kann false zurueckgeben um einen Login zu verweigern.
     *
     * @param bool   $allowed
     * @param string $client_ip
     */
    $allowed = apply_filters( 'wa_sso_allowed_ip', true, wa_sso_client_ip() );
    if ( ! $allowed ) {
        wa_sso_render_error_page(
            __( 'Zugriff verweigert', 'werbeauf-customs' ),
            __( 'Dieser Login darf von Ihrer IP nicht ausgefuehrt werden.', 'werbeauf-customs' )
        );
    }

    // Validate (kein State-Change).
    $row = wa_sso_validate_token( $raw_token );
    if ( is_wp_error( $row ) ) {
        wa_sso_render_error_page(
            __( 'Link ungueltig', 'werbeauf-customs' ),
            $row->get_error_message()
        );
    }

    $action = wa_sso_get_action( $row['action_slug'] );
    if ( ! $action ) {
        wa_sso_render_error_page(
            __( 'Unbekannte Aktion', 'werbeauf-customs' ),
            sprintf(
                /* translators: %s action slug */
                __( 'Die Aktion "%s" ist nicht (mehr) registriert.', 'werbeauf-customs' ),
                esc_html( $row['action_slug'] )
            )
        );
    }

    // Cap-Check VOR dem Login: User muss die Cap haben, sonst zwecklos.
    $user = get_userdata( (int) $row['user_id'] );
    if ( ! $user || ! $user->exists() ) {
        wa_sso_render_error_page(
            __( 'Benutzer nicht gefunden', 'werbeauf-customs' ),
            __( 'Bitte den Administrator kontaktieren.', 'werbeauf-customs' )
        );
    }
    if ( ! user_can( $user, (string) $action['capability'] ) ) {
        wa_sso_render_error_page(
            __( 'Keine Berechtigung', 'werbeauf-customs' ),
            __( 'Sie duerfen diese Aktion nicht ausfuehren.', 'werbeauf-customs' )
        );
    }

    // Auth-Cookie setzen BEVOR wir den Token konsumieren -- so ist die
    // Session sicher etabliert. `secure` automatisch wenn HTTPS.
    wp_clear_auth_cookie();
    wp_set_current_user( $user->ID );
    wp_set_auth_cookie( $user->ID, false, is_ssl() );

    /**
     * Erfolgreicher Login via SSO. Hook fuer Audit/Notifikationen.
     *
     * @param int    $user_id
     * @param string $action_slug
     * @param array  $action_args
     */
    do_action( 'wa_sso_login_success', $user->ID, $row['action_slug'], $row['action_args'] );

    /* --------------------------------------------------
       ROUTING + TOKEN-KONSUM:
       - confirm=true   -> Confirm-Page rendern, Token bleibt LEBEN
                           (wird erst beim Confirm-POST konsumiert).
                           Dadurch ueberlebt der Link, wenn die
                           Mitarbeiterin durch Kundinnen unterbrochen
                           wird und spaeter zurueck-klickt.
       - confirm=false  -> Token JETZT konsumieren (single-use),
                           dann handler + redirect.
       - safe + handler -> handler ausfuehren, dann redirect zur
                           Ziel-URL.
       -------------------------------------------------- */

    if ( ! empty( $action['confirm'] ) ) {
        // Confirm-Action: KEIN consume hier. Token-Hash wird per URL an
        // confirm.php weitergereicht; dort findet die atomare consume +
        // handler-execution statt.
        $url = add_query_arg(
            array(
                WA_SSO_CONFIRM_QUERY_VAR => rawurlencode( $raw_token ),
            ),
            home_url( '/' )
        );
        wp_safe_redirect( $url );
        exit;
    }

    // SAFE-Action: atomar konsumieren. Wenn fehlschlaegt = Race-Condition
    // (zweiter Tab, Mail-Scanner Prefetch, ...).
    $consumed = wa_sso_consume_token( $raw_token );
    if ( is_wp_error( $consumed ) ) {
        wa_sso_render_error_page(
            __( 'Link bereits benutzt', 'werbeauf-customs' ),
            $consumed->get_error_message()
        );
    }

    // Safe action: optional handler, dann redirect.
    if ( is_callable( $action['handler_cb'] ) ) {
        $result = call_user_func( $action['handler_cb'], $row['action_args'], $user->ID, $row['action_slug'] );
        if ( is_array( $result ) && empty( $result['success'] ) ) {
            wa_sso_render_error_page(
                __( 'Aktion fehlgeschlagen', 'werbeauf-customs' ),
                isset( $result['message'] ) ? (string) $result['message'] : __( 'Unbekannter Fehler.', 'werbeauf-customs' )
            );
        }
    }

    $redirect = '';
    if ( is_callable( $action['redirect_cb'] ) ) {
        $redirect = (string) call_user_func( $action['redirect_cb'], $row['action_args'], $user->ID );
    }
    if ( '' === $redirect ) {
        $redirect = admin_url();
    }
    wp_safe_redirect( $redirect );
    exit;
}

/* ----------------------------------------------------------
   ERROR-PAGE RENDERER

   Standalone HTML (kein wp-admin Chrome), klare Sprache,
   Login-Link als Fallback. Beendet den Request mit exit.
---------------------------------------------------------- */

/**
 * @param string $title    Kurzer Titel.
 * @param string $message  Detail-Text. Wird mit esc_html behandelt.
 * @return never
 */
function wa_sso_render_error_page( $title, $message ) {
    status_header( 403 );
    nocache_headers();
    if ( ! headers_sent() ) {
        header( 'Content-Type: text/html; charset=utf-8' );
    }
    $login_url = wp_login_url( admin_url() );

    $html = wa_sso_page_layout(
        $title,
        sprintf(
            '<p style="margin:0 0 24px;color:#475e76;font-size:16px;line-height:1.5;">%s</p>
             <p style="margin:0;font-size:14px;"><a href="%s" style="color:#0073aa;text-decoration:none;">%s</a></p>',
            esc_html( $message ),
            esc_url( $login_url ),
            esc_html__( 'Zur Anmeldeseite', 'werbeauf-customs' )
        )
    );
    echo $html; // phpcs:ignore WordPress.Security.EscapeOutput -- HTML komponiert intern.
    exit;
}

/**
 * Minimales standalone HTML-Layout fuer SSO Error / Confirm Pages.
 * Kein wp-admin Chrome, kein Theme-Loader -- nur eine Card.
 *
 * @param string $title    HTML-Title + H1.
 * @param string $body_html Bereits-escaped HTML fuer den Body.
 * @return string
 */
function wa_sso_page_layout( $title, $body_html ) {
    $site_name = get_bloginfo( 'name' );
    ob_start();
    ?><!doctype html>
<html lang="<?php echo esc_attr( get_bloginfo( 'language' ) ); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title><?php echo esc_html( $title . ' — ' . $site_name ); ?></title>
    <style>
        html, body { margin:0; padding:0; background:#f4f6f8; font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif; color:#1d2327; }
        .wa-sso-wrap { min-height:100vh; display:flex; align-items:center; justify-content:center; padding:24px; }
        .wa-sso-card { background:#fff; border-radius:12px; box-shadow:0 4px 24px rgba(0,0,0,.06); padding:40px 32px; max-width:480px; width:100%; }
        .wa-sso-card h1 { margin:0 0 16px; font-size:22px; line-height:1.3; }
        .wa-sso-brand { font-size:12px; text-transform:uppercase; letter-spacing:.08em; color:#646970; margin:0 0 24px; }
        .wa-sso-btn { display:inline-block; padding:12px 24px; border-radius:8px; background:#0073aa; color:#fff; text-decoration:none; font-weight:600; border:0; cursor:pointer; font-size:15px; }
        .wa-sso-btn--secondary { background:#f0f0f1; color:#1d2327; }
        .wa-sso-btn--success { background:#00a32a; }
        .wa-sso-actions { display:flex; gap:12px; flex-wrap:wrap; margin-top:28px; }
        .wa-sso-meta { font-size:13px; color:#646970; margin:16px 0; }
    </style>
</head>
<body>
    <div class="wa-sso-wrap">
        <div class="wa-sso-card">
            <p class="wa-sso-brand"><?php echo esc_html( $site_name ); ?></p>
            <h1><?php echo esc_html( $title ); ?></h1>
            <?php echo $body_html; // phpcs:ignore WordPress.Security.EscapeOutput -- caller is responsible. ?>
        </div>
    </div>
</body>
</html><?php
    return (string) ob_get_clean();
}

<?php
/* ============================================================
   DATEI: includes/sso/settings.php
   ZWECK: Settings-Page unter Einstellungen -> SSO Login.

   Bewusst KEIN ACF -- damit das Modul beim Extrahieren in ein
   eigenes Plugin keine externen Dependencies hat. Standard
   WP Settings UI.

   Form-Felder:
     - enabled                 Checkbox
     - token_ttl_minutes       Select (5 / 15 / 30 / 60)
     - allowed_roles           Multi-Checkbox
     - inject_into_wc_email    Checkbox
     - audit_retention_days    Number (1..365)

   Plus: "Alle Tokens zurueckziehen"-Button (Nuke-Switch) und
   ein "Letzte Aktivitaet" Audit-Log am Ende.
============================================================ */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ----------------------------------------------------------
   MENU

   Default-Parent ist "Einstellungen" (options-general.php),
   damit das Modul ohne Site-Konfiguration funktioniert. Per
   Filter "wa_sso_settings_menu_parent" laesst sich das Submenu
   in eine beliebige Top-Level-Page haengen (z.B. project-spezifisch
   unter "Dr. Peri").

   admin_menu Priority 999, damit Custom-Parents (z.B. ACF Options
   Pages) bereits existieren wenn wir add_submenu_page aufrufen.
---------------------------------------------------------- */

add_action( 'admin_menu', 'wa_sso_register_settings_menu', 999 );

function wa_sso_register_settings_menu() {
    add_submenu_page(
        wa_sso_settings_menu_parent(),
        __( 'SSO Login', 'werbeauf-customs' ),
        __( 'SSO Login', 'werbeauf-customs' ),
        'manage_options',
        'wa-sso-settings',
        'wa_sso_render_settings_page'
    );
}

/**
 * Liefert den Parent-Slug fuer das SSO Settings Submenu.
 * Filterbar damit Sites das Menu unter eine eigene Top-Level-Page
 * haengen koennen.
 *
 * @return string Default 'options-general.php' (= "Einstellungen").
 */
function wa_sso_settings_menu_parent() {
    return (string) apply_filters( 'wa_sso_settings_menu_parent', 'options-general.php' );
}

/**
 * Liefert die admin-URL der SSO Settings Page -- abhaengig vom
 * gewaehlten Parent. Wird von allen Redirect-Targets in den
 * admin_post-Handlers genutzt.
 *
 * @param array $extra_query Optionale weitere Query-Args.
 * @return string
 */
function wa_sso_settings_url( array $extra_query = array() ) {
    $parent = wa_sso_settings_menu_parent();
    // Native WP-Settings-Parent benutzt options-general.php?page=...,
    // alles andere haengt unter admin.php?page=...
    $base = ( 'options-general.php' === $parent ) ? 'options-general.php' : 'admin.php';
    $args = array_merge( array( 'page' => 'wa-sso-settings' ), $extra_query );
    return add_query_arg( $args, admin_url( $base ) );
}

/* ----------------------------------------------------------
   FORM-HANDLER (admin-post)

   Wir nutzen bewusst admin_post statt der Settings API, weil:
     - mehrere Aktionen auf einer Seite (Save + Revoke All)
     - bessere Kontrolle ueber Sanitisierung
     - kein Hidden-Fields-Mismatch wenn das Modul extrahiert wird
---------------------------------------------------------- */

add_action( 'admin_post_wa_sso_save', 'wa_sso_handle_save' );
add_action( 'admin_post_wa_sso_revoke_all', 'wa_sso_handle_revoke_all' );
add_action( 'admin_post_wa_sso_send_self_test', 'wa_sso_handle_send_self_test' );
add_action( 'admin_post_wa_sso_workflow_save', 'wa_sso_handle_workflow_save' );
add_action( 'admin_post_wa_sso_workflow_run_now', 'wa_sso_handle_workflow_run_now' );
add_action( 'admin_post_wa_sso_workflow_resend', 'wa_sso_handle_workflow_resend' );
add_action( 'admin_post_wa_sso_template_test', 'wa_sso_handle_template_test' );
add_action( 'admin_post_wa_sso_template_preview', 'wa_sso_handle_template_preview' );

function wa_sso_handle_save() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Keine Berechtigung.', 'werbeauf-customs' ), '', array( 'response' => 403 ) );
    }
    check_admin_referer( 'wa_sso_save' );

    $raw = wp_unslash( $_POST );

    $new = array(
        'enabled'              => ! empty( $raw['wa_sso_enabled'] ),
        'token_ttl_minutes'    => isset( $raw['wa_sso_ttl'] ) ? max( 1, min( 20160, (int) $raw['wa_sso_ttl'] ) ) : 10080,
        'allowed_roles'        => isset( $raw['wa_sso_roles'] ) && is_array( $raw['wa_sso_roles'] )
            ? array_values( array_filter( array_map( 'sanitize_key', $raw['wa_sso_roles'] ) ) )
            : array( 'administrator', 'shop_manager' ),
        'inject_into_wc_email' => ! empty( $raw['wa_sso_inject'] ),
        'audit_retention_days' => isset( $raw['wa_sso_audit_days'] ) ? max( 1, min( 365, (int) $raw['wa_sso_audit_days'] ) ) : 30,
    );

    wa_sso_settings_save( $new );

    wp_safe_redirect( wa_sso_settings_url( array( 'updated' => '1' ) ) );
    exit;
}

/**
 * Sendet eine echte SSO-Test-Email an den eingeloggten Admin.
 * Bypasst das WC-Send-Test-Email-Feature komplett (das benutzt
 * dummy Order-Daten + ignoriert die Send-To-Adresse fuer
 * Hooks). Erzeugt ein echtes dashboard-Token + verschickt es per
 * wp_mail an die eigene Email-Adresse. Direkt verifizierbar.
 */
function wa_sso_handle_send_self_test() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Keine Berechtigung.', 'werbeauf-customs' ), '', array( 'response' => 403 ) );
    }
    check_admin_referer( 'wa_sso_send_self_test' );

    $user = wp_get_current_user();
    $back = wa_sso_settings_url();

    if ( ! $user || ! $user->exists() ) {
        set_transient( 'wa_sso_msg_' . get_current_user_id(), array(
            'text' => __( 'Kein eingeloggter Benutzer.', 'werbeauf-customs' ),
            'type' => 'error',
        ), MINUTE_IN_SECONDS * 5 );
        wp_safe_redirect( $back );
        exit;
    }
    if ( ! wa_sso_user_is_allowed( $user->ID ) ) {
        set_transient( 'wa_sso_msg_' . get_current_user_id(), array(
            'text' => __( 'Ihre Benutzerrolle steht nicht in der SSO-Whitelist -- bitte passen Sie zuerst die erlaubten Rollen an.', 'werbeauf-customs' ),
            'type' => 'error',
        ), MINUTE_IN_SECONDS * 5 );
        wp_safe_redirect( $back );
        exit;
    }

    $url = wa_sso_action_url( $user->ID, 'dashboard', array() );
    if ( is_wp_error( $url ) ) {
        set_transient( 'wa_sso_msg_' . get_current_user_id(), array(
            'text' => sprintf( __( 'Token-Erstellung fehlgeschlagen: %s', 'werbeauf-customs' ), $url->get_error_message() ),
            'type' => 'error',
        ), MINUTE_IN_SECONDS * 5 );
        wp_safe_redirect( $back );
        exit;
    }

    $button = wa_sso_button( $url, __( 'Dashboard oeffnen (Test-Login)', 'werbeauf-customs' ), '#00a32a' );
    $ttl    = (int) wa_sso_settings_get( 'token_ttl_minutes', 15 );

    ob_start();
    ?>
    <p style="font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;color:#1d2327;">
        <?php esc_html_e( 'Dies ist eine SSO-Login-Test-Email, ausgeloest aus den Plugin-Einstellungen.', 'werbeauf-customs' ); ?>
    </p>
    <p style="font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;color:#1d2327;">
        <?php
        printf(
            esc_html__( 'Der Button unten loggt %s automatisch ins WordPress-Dashboard ein. Gueltig %d Minuten, einmalig verwendbar.', 'werbeauf-customs' ),
            esc_html( $user->display_name ),
            (int) $ttl
        );
        ?>
    </p>
    <p><?php echo $button; ?></p>
    <p style="font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;font-size:12px;color:#646970;">
        <?php esc_html_e( 'Falls dieser Test funktioniert, wird das gleiche Verfahren in den WooCommerce-Bestell-Emails benutzt -- sofern die Empfaenger-Adresse zu einem WP-Benutzer mit erlaubter Rolle gehoert.', 'werbeauf-customs' ); ?>
    </p>
    <?php
    $body = ob_get_clean();

    $headers = array( 'Content-Type: text/html; charset=UTF-8' );
    $subject = sprintf( '[%s] SSO Login Test', wp_specialchars_decode( get_bloginfo( 'name' ) ) );

    $sent = wp_mail( $user->user_email, $subject, $body, $headers );

    set_transient( 'wa_sso_msg_' . get_current_user_id(), array(
        'text' => $sent
            ? sprintf( __( 'Test-Email an %s gesendet.', 'werbeauf-customs' ), $user->user_email )
            : __( 'wp_mail() hat false zurueckgegeben -- siehe Server-Log fuer den Grund.', 'werbeauf-customs' ),
        'type' => $sent ? 'success' : 'error',
    ), MINUTE_IN_SECONDS * 5 );

    wp_safe_redirect( $back );
    exit;
}

/* ----------------------------------------------------------
   WORKFLOW-REGELN: Speichern + Test-Run
---------------------------------------------------------- */

function wa_sso_handle_workflow_save() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Keine Berechtigung.', 'werbeauf-customs' ), '', array( 'response' => 403 ) );
    }
    check_admin_referer( 'wa_sso_workflow_save' );

    if ( ! function_exists( 'wa_workflow_default_rules' ) ) {
        wp_safe_redirect( wa_sso_settings_url() );
        exit;
    }

    $raw  = wp_unslash( $_POST );
    $rules_in = isset( $raw['wa_workflow_rules'] ) && is_array( $raw['wa_workflow_rules'] ) ? $raw['wa_workflow_rules'] : array();

    foreach ( wa_workflow_default_rules() as $slug => $defaults ) {
        $vals = isset( $rules_in[ $slug ] ) && is_array( $rules_in[ $slug ] ) ? $rules_in[ $slug ] : array();
        wa_workflow_save_rule( $slug, array(
            'enabled'        => ! empty( $vals['enabled'] ),
            'after_minutes'  => isset( $vals['after_minutes'] )  ? max( 1,  min( 43200, (int) $vals['after_minutes'] ) )  : (int) $defaults['after_minutes'],
            'repeat_minutes' => isset( $vals['repeat_minutes'] ) ? max( 0,  min( 43200, (int) $vals['repeat_minutes'] ) ) : (int) $defaults['repeat_minutes'],
            'max_repeats'    => isset( $vals['max_repeats'] )    ? max( 1,  min( 100,   (int) $vals['max_repeats'] ) )    : (int) $defaults['max_repeats'],
        ) );
    }

    // Pause-Toggle separat speichern.
    update_option( WA_WORKFLOW_OPTION_PAUSED, ! empty( $raw['wa_workflow_paused'] ), false );

    set_transient( 'wa_sso_msg_' . get_current_user_id(), array(
        'text' => __( 'Workflow-Regeln gespeichert.', 'werbeauf-customs' ),
        'type' => 'success',
    ), MINUTE_IN_SECONDS * 5 );

    wp_safe_redirect( wa_sso_settings_url() );
    exit;
}

function wa_sso_handle_workflow_run_now() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Keine Berechtigung.', 'werbeauf-customs' ), '', array( 'response' => 403 ) );
    }
    check_admin_referer( 'wa_sso_workflow_run_now' );

    if ( ! function_exists( 'wa_workflow_scan_now' ) ) {
        wp_safe_redirect( wa_sso_settings_url() );
        exit;
    }

    $res = wa_workflow_scan_now();

    $msg = $res['paused']
        ? __( 'Scan uebersprungen — Workflow ist pausiert.', 'werbeauf-customs' )
        : sprintf(
            /* translators: 1: sent count, 2: error count */
            __( 'Scan ausgefuehrt. %1$d Mail(s) gesendet, %2$d Fehler.', 'werbeauf-customs' ),
            (int) $res['total_sent'],
            (int) $res['total_err']
        );

    set_transient( 'wa_sso_msg_' . get_current_user_id(), array(
        'text' => $msg,
        'type' => $res['total_err'] > 0 ? 'warning' : 'success',
    ), MINUTE_IN_SECONDS * 5 );

    wp_safe_redirect( wa_sso_settings_url() );
    exit;
}

/* ----------------------------------------------------------
   WORKFLOW-LOG: per-row Resend
---------------------------------------------------------- */

function wa_sso_handle_workflow_resend() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Keine Berechtigung.', 'werbeauf-customs' ), '', array( 'response' => 403 ) );
    }
    check_admin_referer( 'wa_sso_workflow_resend' );

    $order_id  = isset( $_POST['order_id'] )  ? (int) $_POST['order_id'] : 0;
    $rule_slug = isset( $_POST['rule_slug'] ) ? sanitize_key( wp_unslash( $_POST['rule_slug'] ) ) : '';

    if ( ! $order_id || '' === $rule_slug ) {
        wp_safe_redirect( wa_sso_settings_url() );
        exit;
    }
    if ( ! function_exists( 'wa_workflow_get_rules' ) || ! function_exists( 'wa_workflow_dispatch' ) ) {
        wp_safe_redirect( wa_sso_settings_url() );
        exit;
    }
    $rules = wa_workflow_get_rules();
    if ( ! isset( $rules[ $rule_slug ] ) ) {
        wa_sso_msg( __( 'Regel nicht mehr definiert.', 'werbeauf-customs' ), 'error' );
        wp_safe_redirect( wa_sso_settings_url() );
        exit;
    }
    $order = wc_get_order( $order_id );
    if ( ! $order ) {
        wa_sso_msg( __( 'Bestellung nicht gefunden.', 'werbeauf-customs' ), 'error' );
        wp_safe_redirect( wa_sso_settings_url() );
        exit;
    }

    $result = wa_workflow_dispatch( $order, $rule_slug, $rules[ $rule_slug ] );

    if ( $result['ok'] ) {
        wa_sso_msg(
            sprintf(
                /* translators: 1: rule slug, 2: recipient */
                __( '"%1$s" erneut versendet an %2$s.', 'werbeauf-customs' ),
                $rule_slug,
                $result['recipient']
            ),
            'success'
        );
    } else {
        wa_sso_msg(
            sprintf(
                /* translators: %s error string */
                __( 'Erneutes Senden fehlgeschlagen: %s', 'werbeauf-customs' ),
                $result['error'] ?? '?'
            ),
            'error'
        );
    }

    wp_safe_redirect( wa_sso_settings_url() );
    exit;
}

/**
 * Kurz-Wrapper damit die Handler nicht jedes Mal das Transient-Boilerplate
 * wiederholen.
 */
function wa_sso_msg( $text, $type = 'info' ) {
    set_transient(
        'wa_sso_msg_' . get_current_user_id(),
        array( 'text' => (string) $text, 'type' => (string) $type ),
        MINUTE_IN_SECONDS * 5
    );
}

/* ----------------------------------------------------------
   TEMPLATE-PREVIEW + TEST-SEND
---------------------------------------------------------- */

/**
 * Bewertet eine Template-Slug-Eingabe + Order-ID + Render-Kontext.
 * Returns Render-Array oder WP_Error.
 *
 * @param string $template_slug
 * @param int    $order_id
 * @return array{subject:string,html:string}|WP_Error
 */
function wa_sso_render_template_for_preview( $template_slug, $order_id ) {
    if ( ! function_exists( 'wc_get_order' ) ) {
        return new WP_Error( 'no_wc', __( 'WooCommerce ist nicht aktiv.', 'werbeauf-customs' ) );
    }
    $order = wc_get_order( (int) $order_id );
    if ( ! $order ) {
        return new WP_Error( 'no_order', __( 'Bestellung nicht gefunden.', 'werbeauf-customs' ) );
    }

    // Erlaubte Template-Slugs aus den vorhandenen .php-Dateien.
    $allowed = wa_sso_available_template_slugs();
    if ( ! in_array( $template_slug, $allowed, true ) ) {
        return new WP_Error( 'no_template', __( 'Unbekanntes Template.', 'werbeauf-customs' ) );
    }

    // Wir konstruieren einen Pseudo-Rule basierend auf dem Slug damit
    // der Renderer denselben Code-Pfad nimmt wie ein Live-Workflow.
    list( $channel, $tone ) = wa_sso_template_slug_split( $template_slug );

    // Finde eine passende Regel: erst exakter template_slug-Match,
    // dann channel+tone-Match. Wichtig damit Subject-Overrides
    // (z.B. rule-spezifische Subjects) korrekt greifen.
    $rules         = wa_workflow_get_rules();
    $rule          = null;
    $matched_slug  = '';
    foreach ( $rules as $slug => $r ) {
        if ( ( $r['template_slug'] ?? '' ) === $template_slug ) {
            $rule         = $r;
            $matched_slug = $slug;
            break;
        }
    }
    if ( ! $rule ) {
        foreach ( $rules as $slug => $r ) {
            if ( $r['channel'] === $channel && $r['tone'] === $tone ) {
                $rule         = $r;
                $matched_slug = $slug;
                break;
            }
        }
    }
    if ( ! $rule ) {
        $rule = array(
            'channel' => $channel,
            'tone'    => $tone,
            'label'   => ucfirst( $tone ),
            'enabled' => true,
        );
        $matched_slug = 'preview_' . $template_slug;
    }
    $rule['template_slug'] = $template_slug;

    return wa_workflow_render( $order, $matched_slug, $rule, $channel );
}

/**
 * Listet alle Template-Slugs auf Basis der vorhandenen .php-Dateien
 * im email-templates/-Verzeichnis (ohne _partials).
 *
 * @return string[]
 */
function wa_sso_available_template_slugs() {
    $dir = WERBEAUF_PLUGIN_PATH . 'includes/sso/email-templates';
    if ( ! is_dir( $dir ) ) {
        return array();
    }
    $files = (array) glob( $dir . '/*.php' );
    $out   = array();
    foreach ( $files as $f ) {
        $slug = basename( $f, '.php' );
        if ( '' === $slug || strpos( $slug, '_' ) === 0 ) {
            continue;
        }
        $out[] = $slug;
    }
    sort( $out );
    return $out;
}

/**
 * Zerlegt einen Slug wie "customer-pending-recovery" in
 * channel + tone. Channel ist immer das erste Segment.
 */
function wa_sso_template_slug_split( $slug ) {
    $parts = explode( '-', (string) $slug, 2 );
    $channel = $parts[0] ?? 'admin';
    $tone    = $parts[1] ?? 'reminder';
    // Tone kann "pending-recovery" sein -- letzter Segment ist der
    // semantische Tone (recovery / apology / reminder / escalation).
    if ( false !== strpos( $tone, '-' ) ) {
        $tone_parts = explode( '-', $tone );
        $tone       = end( $tone_parts );
    }
    return array( $channel, $tone );
}

function wa_sso_handle_template_test() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Keine Berechtigung.', 'werbeauf-customs' ), '', array( 'response' => 403 ) );
    }
    check_admin_referer( 'wa_sso_template_test' );

    $template_slug = isset( $_POST['template_slug'] ) ? sanitize_text_field( wp_unslash( $_POST['template_slug'] ) ) : '';
    $order_id      = isset( $_POST['order_id'] )      ? (int) $_POST['order_id'] : 0;
    $recipient     = isset( $_POST['recipient'] )     ? sanitize_email( wp_unslash( $_POST['recipient'] ) ) : '';
    if ( '' === $recipient ) {
        $recipient = wp_get_current_user()->user_email;
    }

    $r = wa_sso_render_template_for_preview( $template_slug, $order_id );
    if ( is_wp_error( $r ) ) {
        wa_sso_msg( $r->get_error_message(), 'error' );
        wp_safe_redirect( wa_sso_settings_url() );
        exit;
    }

    $ok = wp_mail( $recipient, '[TEST] ' . $r['subject'], $r['html'], array( 'Content-Type: text/html; charset=UTF-8' ) );

    wa_sso_msg(
        $ok
            ? sprintf( __( 'Test-Email "%1$s" an %2$s versendet.', 'werbeauf-customs' ), $template_slug, $recipient )
            : sprintf( __( 'wp_mail() fehlgeschlagen fuer %s — Postmark-Log pruefen.', 'werbeauf-customs' ), $recipient ),
        $ok ? 'success' : 'error'
    );
    wp_safe_redirect( wa_sso_settings_url() );
    exit;
}

function wa_sso_handle_template_preview() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Keine Berechtigung.', 'werbeauf-customs' ), '', array( 'response' => 403 ) );
    }
    check_admin_referer( 'wa_sso_template_preview' );

    $template_slug = isset( $_POST['template_slug'] ) ? sanitize_text_field( wp_unslash( $_POST['template_slug'] ) ) : '';
    $order_id      = isset( $_POST['order_id'] )      ? (int) $_POST['order_id'] : 0;

    $r = wa_sso_render_template_for_preview( $template_slug, $order_id );
    if ( is_wp_error( $r ) ) {
        wp_die( esc_html( $r->get_error_message() ), '', array( 'response' => 400 ) );
    }

    // Direkter HTML-Output. Wir fuegen einen X-Frame-Options-Header NICHT
    // -- damit das iframe srcdoc nicht greift, gehen wir nicht ueber die
    // URL. (Preview rendert via srcdoc inline; kein Cross-Origin.)
    if ( ! headers_sent() ) {
        header( 'Content-Type: text/html; charset=UTF-8' );
        header( 'X-Robots-Tag: noindex' );
    }
    echo $r['html']; // phpcs:ignore WordPress.Security.EscapeOutput
    exit;
}

function wa_sso_handle_revoke_all() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Keine Berechtigung.', 'werbeauf-customs' ), '', array( 'response' => 403 ) );
    }
    check_admin_referer( 'wa_sso_revoke_all' );

    $count = wa_sso_revoke_user_tokens( 0 );

    set_transient(
        'wa_sso_msg_' . get_current_user_id(),
        array(
            'text' => sprintf(
                /* translators: %d number of tokens */
                _n( '%d Token zurueckgezogen.', '%d Tokens zurueckgezogen.', $count, 'werbeauf-customs' ),
                $count
            ),
            'type' => 'success',
        ),
        MINUTE_IN_SECONDS * 5
    );

    wp_safe_redirect( wa_sso_settings_url() );
    exit;
}

/* ----------------------------------------------------------
   RENDER
---------------------------------------------------------- */

function wa_sso_render_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'Keine Berechtigung.', 'werbeauf-customs' ), '', array( 'response' => 403 ) );
    }

    $s             = wa_sso_settings_defaults();
    $stored        = get_option( WA_SSO_OPTION_SETTINGS, array() );
    $s             = array_merge( $s, is_array( $stored ) ? $stored : array() );
    $roles         = function_exists( 'wp_roles' ) ? wp_roles()->role_names : array();
    $is_updated    = ! empty( $_GET['updated'] );
    $save_action   = esc_url( admin_url( 'admin-post.php' ) );
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'SSO Login — passwordless Email-Buttons', 'werbeauf-customs' ); ?></h1>

        <?php if ( $is_updated ) : ?>
            <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Einstellungen gespeichert.', 'werbeauf-customs' ); ?></p></div>
        <?php endif; ?>

        <?php wa_sso_render_recipient_warning_banner(); ?>

        <p style="max-width:740px;">
            <?php esc_html_e( 'Erlaubt es Mitarbeiterinnen, aus Admin-Emails (z.B. neue Bestellungen) per Klick direkt ins Dashboard einzuloggen und einzelne Aktionen auszufuehren. Jeder Link ist ein einmaliger, zeitbegrenzter Token, der an einen Benutzer + eine konkrete Aktion gebunden ist.', 'werbeauf-customs' ); ?>
        </p>

        <form method="post" action="<?php echo $save_action; ?>" style="background:#fff;padding:20px 24px;border:1px solid #e2e4e7;border-radius:8px;max-width:760px;">
            <?php wp_nonce_field( 'wa_sso_save' ); ?>
            <input type="hidden" name="action" value="wa_sso_save">

            <h2 style="margin-top:0;"><?php esc_html_e( 'Allgemein', 'werbeauf-customs' ); ?></h2>

            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><?php esc_html_e( 'Feature aktiv', 'werbeauf-customs' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="wa_sso_enabled" value="1" <?php checked( ! empty( $s['enabled'] ) ); ?>>
                            <?php esc_html_e( 'SSO-Login per Email-Link erlauben', 'werbeauf-customs' ); ?>
                        </label>
                        <p class="description"><?php esc_html_e( 'Master-Switch. Bei "Aus" werden keine Tokens validiert und keine Buttons in Emails injiziert.', 'werbeauf-customs' ); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php esc_html_e( 'Token-Lebensdauer', 'werbeauf-customs' ); ?></th>
                    <td>
                        <select name="wa_sso_ttl">
                            <?php
                            $current = (int) $s['token_ttl_minutes'];
                            // Format pro Stufe: minute-Wert => Label
                            $options = array(
                                15    => __( '15 Minuten',  'werbeauf-customs' ),
                                60    => __( '1 Stunde',    'werbeauf-customs' ),
                                240   => __( '4 Stunden',   'werbeauf-customs' ),
                                1440  => __( '24 Stunden',  'werbeauf-customs' ),
                                4320  => __( '3 Tage',      'werbeauf-customs' ),
                                10080 => __( '7 Tage',      'werbeauf-customs' ),
                                20160 => __( '14 Tage',     'werbeauf-customs' ),
                            );
                            // Wenn der aktuelle Wert nicht in der Liste ist (z.B. manuell gesetzt), zeigen wir ihn trotzdem.
                            if ( ! isset( $options[ $current ] ) && $current > 0 ) {
                                $options[ $current ] = sprintf( __( '%d Minuten (benutzerdefiniert)', 'werbeauf-customs' ), $current );
                                ksort( $options );
                            }
                            foreach ( $options as $opt_val => $opt_label ) :
                                ?>
                                <option value="<?php echo esc_attr( $opt_val ); ?>" <?php selected( $current, $opt_val ); ?>>
                                    <?php echo esc_html( $opt_label ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php esc_html_e( 'Wie lange ist ein per Email versendeter Link gueltig? Kuerzer = sicherer, laenger = mehr Komfort bei verzoegerten Email-Ankuenften.', 'werbeauf-customs' ); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php esc_html_e( 'Erlaubte Benutzerrollen', 'werbeauf-customs' ); ?></th>
                    <td>
                        <?php foreach ( $roles as $role_slug => $role_name ) : ?>
                            <label style="display:block;margin:2px 0;">
                                <input type="checkbox" name="wa_sso_roles[]" value="<?php echo esc_attr( $role_slug ); ?>"
                                    <?php checked( in_array( $role_slug, (array) $s['allowed_roles'], true ) ); ?>>
                                <?php echo esc_html( translate_user_role( $role_name ) ); ?>
                                <code style="opacity:.6;font-size:12px;"><?php echo esc_html( $role_slug ); ?></code>
                            </label>
                        <?php endforeach; ?>
                        <p class="description"><?php esc_html_e( 'Nur Benutzer dieser Rollen koennen Tokens erhalten und einloggen. Empfohlen: Administrator + Shop-Manager.', 'werbeauf-customs' ); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php esc_html_e( 'In WooCommerce-Bestell-Emails einfuegen', 'werbeauf-customs' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="wa_sso_inject" value="1" <?php checked( ! empty( $s['inject_into_wc_email'] ) ); ?>>
                            <?php esc_html_e( 'Buttons "Ansehen / In Bearbeitung / Versandt" automatisch in der "Neue Bestellung"-Admin-Email einbinden', 'werbeauf-customs' ); ?>
                        </label>
                        <p class="description"><?php esc_html_e( 'Wenn aus, koennen Buttons trotzdem manuell per wa_sso_button() in eigenen Email-Templates verwendet werden.', 'werbeauf-customs' ); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php esc_html_e( 'Audit-Aufbewahrung', 'werbeauf-customs' ); ?></th>
                    <td>
                        <input type="number" name="wa_sso_audit_days" min="1" max="365" value="<?php echo esc_attr( (int) $s['audit_retention_days'] ); ?>" class="small-text">
                        <?php esc_html_e( 'Tage', 'werbeauf-customs' ); ?>
                        <p class="description"><?php esc_html_e( 'Konsumierte Tokens werden so lange behalten, dann automatisch geloescht.', 'werbeauf-customs' ); ?></p>
                    </td>
                </tr>
            </table>

            <p><button type="submit" class="button button-primary"><?php esc_html_e( 'Speichern', 'werbeauf-customs' ); ?></button></p>
        </form>

        <h2 style="margin-top:32px;"><?php esc_html_e( 'Empfaenger-Status (WooCommerce New Order)', 'werbeauf-customs' ); ?></h2>
        <?php wa_sso_render_recipient_panel(); ?>

        <h2 style="margin-top:32px;"><?php esc_html_e( 'Workflow-Regeln (Active Order System)', 'werbeauf-customs' ); ?></h2>
        <?php wa_sso_render_workflow_section(); ?>

        <h2 style="margin-top:32px;"><?php esc_html_e( 'Template-Vorschau', 'werbeauf-customs' ); ?></h2>
        <?php wa_sso_render_template_preview_section(); ?>

        <h2 style="margin-top:32px;"><?php esc_html_e( 'Selbst-Test', 'werbeauf-customs' ); ?></h2>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="background:#fff;padding:16px 24px;border:1px solid #e2e4e7;border-radius:8px;max-width:760px;">
            <?php wp_nonce_field( 'wa_sso_send_self_test' ); ?>
            <input type="hidden" name="action" value="wa_sso_send_self_test">
            <p style="margin:0 0 12px;">
                <?php
                printf(
                    /* translators: %s: user's email address */
                    esc_html__( 'Sendet eine echte SSO-Test-Email an %s mit einem Dashboard-Login-Button. Bypasst WC vollstaendig -- echter Token, echter wp_mail, sofort verifizierbar.', 'werbeauf-customs' ),
                    '<code>' . esc_html( wp_get_current_user()->user_email ) . '</code>'
                );
                ?>
            </p>
            <button type="submit" class="button button-primary"><?php esc_html_e( 'Test-Email an mich senden', 'werbeauf-customs' ); ?></button>
        </form>

        <h2 style="margin-top:32px;"><?php esc_html_e( 'Notfall', 'werbeauf-customs' ); ?></h2>
        <form method="post" action="<?php echo $save_action; ?>" style="background:#fff;padding:16px 24px;border:1px solid #e2e4e7;border-radius:8px;max-width:760px;">
            <?php wp_nonce_field( 'wa_sso_revoke_all' ); ?>
            <input type="hidden" name="action" value="wa_sso_revoke_all">
            <p style="margin:0 0 12px;"><?php esc_html_e( 'Falls Email-Postfaecher kompromittiert sind oder ein Test schief gelaufen ist: alle ausstehenden Tokens auf einen Schlag zurueckziehen. Bereits versandte Links werden ungueltig.', 'werbeauf-customs' ); ?></p>
            <button type="submit" class="button button-secondary" onclick="return confirm('<?php echo esc_js( __( 'Alle ausstehenden SSO-Tokens unwiderruflich loeschen?', 'werbeauf-customs' ) ); ?>');">
                <?php esc_html_e( 'Alle Tokens zurueckziehen', 'werbeauf-customs' ); ?>
            </button>
        </form>

        <h2 style="margin-top:32px;"><?php esc_html_e( 'Letzte Aktivitaet', 'werbeauf-customs' ); ?></h2>
        <?php wa_sso_render_audit_table(); ?>

        <h2 style="margin-top:32px;"><?php esc_html_e( 'Hilfe', 'werbeauf-customs' ); ?></h2>
        <div style="background:#f6f7f7;padding:16px 20px;border-left:4px solid #0073aa;max-width:760px;">
            <p style="margin:0 0 8px;"><strong><?php esc_html_e( 'So funktioniert es:', 'werbeauf-customs' ); ?></strong></p>
            <ol style="margin:0 0 8px 18px;">
                <li><?php esc_html_e( 'Wenn eine neue Bestellung eingeht, sendet WooCommerce die Admin-Email an die in WC -> Einstellungen -> Emails konfigurierten Empfaenger.', 'werbeauf-customs' ); ?></li>
                <li><?php esc_html_e( 'Wenn diese Email-Adresse zu einem WP-Benutzer mit erlaubter Rolle gehoert, fuegt SSO Login automatisch Aktions-Buttons in die Email ein.', 'werbeauf-customs' ); ?></li>
                <li><?php esc_html_e( 'Klicken Sie auf einen Button -> automatischer Login als dieser Benutzer.', 'werbeauf-customs' ); ?></li>
                <li><?php esc_html_e( 'Bei Status-Aenderungen folgt eine kurze Bestaetigungsseite (Schutz gegen automatische Email-Scanner).', 'werbeauf-customs' ); ?></li>
            </ol>
            <p style="margin:0;"><strong><?php esc_html_e( 'Tipp:', 'werbeauf-customs' ); ?></strong>
                <?php esc_html_e( 'Damit Buttons in der Email erscheinen, muss die Empfaenger-Email-Adresse in WC -> Einstellungen -> Emails identisch zur Email-Adresse eines WP-Benutzers mit der Rolle "Shop-Manager" oder "Administrator" sein.', 'werbeauf-customs' ); ?>
            </p>
        </div>
    </div>
    <?php
}

/* ----------------------------------------------------------
   RECIPIENT-STATUS PANEL + WARN-BANNER

   Greift live auf woocommerce_new_order_settings.recipient zu,
   splittet die Komma-Liste und prueft pro Eintrag, ob er auf
   einen WP-User mit erlaubter Rolle gemapped werden kann.

   Liefert sofortiges Feedback warum keine Buttons in der
   WC-Admin-Email erscheinen -- der haeufigste Verwirrungsfall.
---------------------------------------------------------- */

/**
 * Analysiert die WC-New-Order-Recipient-Liste.
 *
 * @return array{recipients: array<int, array<string, mixed>>, mapped_count:int, total:int, raw:string}
 */
function wa_sso_analyze_wc_recipients() {
    $opt   = get_option( 'woocommerce_new_order_settings', array() );
    $raw   = is_array( $opt ) && isset( $opt['recipient'] ) ? (string) $opt['recipient'] : '';
    $list  = preg_split( '/[\s,;]+/', $raw, -1, PREG_SPLIT_NO_EMPTY );
    $rows  = array();
    $ok    = 0;

    if ( is_array( $list ) ) {
        foreach ( $list as $email ) {
            $email = sanitize_email( trim( (string) $email ) );
            if ( '' === $email || ! is_email( $email ) ) {
                $rows[] = array( 'email' => $email, 'status' => 'invalid', 'user' => null );
                continue;
            }
            $user = get_user_by( 'email', $email );
            if ( ! $user ) {
                $rows[] = array( 'email' => $email, 'status' => 'no_user', 'user' => null );
                continue;
            }
            $allowed = wa_sso_user_is_allowed( $user->ID );
            $rows[] = array(
                'email'  => $email,
                'status' => $allowed ? 'ok' : 'role_denied',
                'user'   => $user,
            );
            if ( $allowed ) {
                $ok++;
            }
        }
    }

    return array(
        'recipients'   => $rows,
        'mapped_count' => $ok,
        'total'        => is_array( $list ) ? count( $list ) : 0,
        'raw'          => $raw,
    );
}

/**
 * Gelbes Warn-Banner direkt unter dem Page-Titel, wenn die
 * Recipient-Liste keine SSO-buttonfaehigen Empfaenger enthaelt.
 */
function wa_sso_render_recipient_warning_banner() {
    if ( ! wa_sso_is_enabled() ) {
        return;
    }
    if ( ! wa_sso_settings_get( 'inject_into_wc_email', true ) ) {
        return;
    }
    $analysis = wa_sso_analyze_wc_recipients();
    if ( $analysis['mapped_count'] > 0 ) {
        return;
    }

    $wc_email_url = admin_url( 'admin.php?page=wc-settings&tab=email&section=wc_email_new_order' );
    $users_url    = admin_url( 'users.php' );

    echo '<div class="notice notice-warning" style="border-left-color:#daa520;">';
    echo '<p><strong>' . esc_html__( 'SSO-Buttons erscheinen aktuell NICHT in WooCommerce-Bestell-Emails.', 'werbeauf-customs' ) . '</strong></p>';
    if ( 0 === $analysis['total'] ) {
        echo '<p>' . esc_html__( 'Grund: in der WooCommerce-New-Order-Email ist kein Empfaenger konfiguriert.', 'werbeauf-customs' ) . '</p>';
    } else {
        echo '<p>' . esc_html__( 'Grund: keiner der konfigurierten Empfaenger ist einem WP-Benutzer mit erlaubter SSO-Rolle zugeordnet (siehe Liste unten).', 'werbeauf-customs' ) . '</p>';
    }
    echo '<p>' . sprintf(
        /* translators: 1: WC settings link, 2: WP users link */
        wp_kses(
            __( 'Loesung: tragen Sie in <a href="%1$s">WooCommerce → E-Mails → Neue Bestellung</a> eine Empfaenger-Adresse ein, die zu einem <a href="%2$s">WP-Benutzer</a> mit Rolle Administrator oder Shop-Manager gehoert. Oder legen Sie einen neuen WP-Benutzer mit der bestehenden Empfaenger-Email-Adresse an.', 'werbeauf-customs' ),
            array( 'a' => array( 'href' => array() ) )
        ),
        esc_url( $wc_email_url ),
        esc_url( $users_url )
    ) . '</p>';
    echo '</div>';
}

/**
 * Detail-Tabelle: pro konfiguriertem Empfaenger ein Eintrag mit
 * gruenem Check / rotem X / gelbem Warnzeichen + Erklaerung.
 */
function wa_sso_render_recipient_panel() {
    $analysis = wa_sso_analyze_wc_recipients();

    if ( 0 === $analysis['total'] ) {
        echo '<p style="opacity:.7;">' . esc_html__( 'Kein Empfaenger in WooCommerce konfiguriert.', 'werbeauf-customs' ) . '</p>';
        return;
    }

    echo '<table class="widefat striped" style="max-width:1000px;">';
    echo '<thead><tr>';
    echo '<th>' . esc_html__( 'Empfaenger-Adresse', 'werbeauf-customs' ) . '</th>';
    echo '<th>' . esc_html__( 'WP-Benutzer', 'werbeauf-customs' ) . '</th>';
    echo '<th>' . esc_html__( 'SSO-Status', 'werbeauf-customs' ) . '</th>';
    echo '<th>' . esc_html__( 'Hinweis', 'werbeauf-customs' ) . '</th>';
    echo '</tr></thead><tbody>';

    foreach ( $analysis['recipients'] as $row ) {
        echo '<tr>';
        echo '<td><code>' . esc_html( $row['email'] ) . '</code></td>';

        switch ( $row['status'] ) {
            case 'ok':
                /** @var WP_User $u */
                $u = $row['user'];
                echo '<td>' . esc_html( $u->display_name ) . ' <code style="opacity:.6;">#' . (int) $u->ID . ' / ' . esc_html( implode( ',', (array) $u->roles ) ) . '</code></td>';
                echo '<td><span style="color:#00a32a;font-weight:600;">✓ ' . esc_html__( 'Buttons werden injiziert', 'werbeauf-customs' ) . '</span></td>';
                echo '<td>—</td>';
                break;
            case 'role_denied':
                /** @var WP_User $u */
                $u = $row['user'];
                echo '<td>' . esc_html( $u->display_name ) . ' <code style="opacity:.6;">#' . (int) $u->ID . ' / ' . esc_html( implode( ',', (array) $u->roles ) ) . '</code></td>';
                echo '<td><span style="color:#daa520;font-weight:600;">⚠ ' . esc_html__( 'Rolle nicht in der SSO-Whitelist', 'werbeauf-customs' ) . '</span></td>';
                echo '<td>' . esc_html__( 'Fuegen Sie die Rolle den "Erlaubten Benutzerrollen" oben hinzu, oder weisen Sie dem Benutzer eine erlaubte Rolle zu.', 'werbeauf-customs' ) . '</td>';
                break;
            case 'no_user':
                echo '<td>—</td>';
                echo '<td><span style="color:#d63638;font-weight:600;">✗ ' . esc_html__( 'Kein passender WP-Benutzer', 'werbeauf-customs' ) . '</span></td>';
                echo '<td>' . sprintf(
                    /* translators: %s users admin URL */
                    wp_kses(
                        __( 'Legen Sie einen <a href="%s">WP-Benutzer</a> mit dieser Email-Adresse an (Rolle Administrator oder Shop-Manager), oder aendern Sie die Empfaenger-Adresse in WooCommerce auf einen vorhandenen Benutzer.', 'werbeauf-customs' ),
                        array( 'a' => array( 'href' => array() ) )
                    ),
                    esc_url( admin_url( 'user-new.php' ) )
                ) . '</td>';
                break;
            case 'invalid':
            default:
                echo '<td>—</td>';
                echo '<td><span style="color:#d63638;font-weight:600;">✗ ' . esc_html__( 'Ungueltige Email-Adresse', 'werbeauf-customs' ) . '</span></td>';
                echo '<td>' . esc_html__( 'Pruefen Sie die WooCommerce-Empfaenger-Konfiguration.', 'werbeauf-customs' ) . '</td>';
                break;
        }
        echo '</tr>';
    }
    echo '</tbody></table>';

    if ( $analysis['mapped_count'] > 0 && $analysis['mapped_count'] < $analysis['total'] ) {
        echo '<p style="margin-top:8px;font-size:12px;color:#646970;">' . sprintf(
            /* translators: %d count */
            esc_html( _n( 'Hinweis: aktuell werden Buttons fuer den ersten gemappten Empfaenger generiert (%d insgesamt gemappt). Multi-Recipient-Dispatch ist Phase 2.', 'Hinweis: aktuell werden Buttons fuer den ersten gemappten Empfaenger generiert (%d insgesamt gemappt). Multi-Recipient-Dispatch ist Phase 2.', $analysis['mapped_count'], 'werbeauf-customs' ) ),
            $analysis['mapped_count']
        ) . '</p>';
    }
}

/* ----------------------------------------------------------
   WORKFLOW-REGELN SECTION

   Tabellarische Anzeige aller pre-seeded Regeln. Jede Zeile hat:
     - Enabled-Checkbox
     - Trigger-Status (read-only Badge)
     - Channel + Tone (read-only)
     - after_minutes + repeat_minutes + max_repeats (Number-Inputs)

   Plus globaler Pause-Toggle (Q7: Urlaubs-Modus) + Test-Run-Button.
   Plus Letzte 25 Workflow-Log-Eintraege.
---------------------------------------------------------- */

function wa_sso_render_workflow_section() {
    if ( ! function_exists( 'wa_workflow_get_rules' ) ) {
        echo '<p style="opacity:.7;">' . esc_html__( 'Workflow-Engine nicht geladen.', 'werbeauf-customs' ) . '</p>';
        return;
    }

    $rules     = wa_workflow_get_rules();
    $paused    = wa_workflow_is_paused();
    $next_cron = wp_next_scheduled( WA_WORKFLOW_CRON_HOOK );
    $action    = esc_url( admin_url( 'admin-post.php' ) );

    // Erklaerung + Pause-Toggle + Save in einem Form.
    ?>
    <p style="max-width:760px;">
        <?php esc_html_e( 'Zeitgesteuerte Erinnerungen, Recovery-Mails und Eskalationen — ein stuendlicher Cron prueft welche Bestellungen Handlungsbedarf signalisieren und sendet die entsprechende Email.', 'werbeauf-customs' ); ?>
    </p>

    <form method="post" action="<?php echo $action; ?>" style="background:#fff;padding:20px 24px;border:1px solid #e2e4e7;border-radius:8px;max-width:1100px;">
        <?php wp_nonce_field( 'wa_sso_workflow_save' ); ?>
        <input type="hidden" name="action" value="wa_sso_workflow_save">

        <!-- Globale Pause-Schaltung -->
        <div style="display:flex;align-items:center;gap:16px;padding:12px 16px;background:<?php echo $paused ? '#fff8e5' : '#f0f6fc'; ?>;border:1px solid <?php echo $paused ? '#daa520' : '#0073aa'; ?>;border-radius:6px;margin-bottom:20px;">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;margin:0;">
                <input type="checkbox" name="wa_workflow_paused" value="1" <?php checked( $paused ); ?>>
                <strong><?php esc_html_e( 'Workflow pausieren (Urlaubs-Modus)', 'werbeauf-customs' ); ?></strong>
            </label>
            <span style="font-size:13px;color:#646970;">
                <?php esc_html_e( 'Cron laeuft weiter, sendet aber nichts. Aktuelle Status:', 'werbeauf-customs' ); ?>
                <strong style="color:<?php echo $paused ? '#daa520' : '#00a32a'; ?>;">
                    <?php echo $paused ? esc_html__( 'PAUSIERT', 'werbeauf-customs' ) : esc_html__( 'AKTIV', 'werbeauf-customs' ); ?>
                </strong>
            </span>
        </div>

        <!-- Regel-Tabelle -->
        <table class="widefat striped" style="margin-bottom:16px;">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Regel', 'werbeauf-customs' ); ?></th>
                    <th><?php esc_html_e( 'Status', 'werbeauf-customs' ); ?></th>
                    <th><?php esc_html_e( 'Kanal', 'werbeauf-customs' ); ?></th>
                    <th><?php esc_html_e( 'Trigger', 'werbeauf-customs' ); ?></th>
                    <th><?php esc_html_e( 'Nach (Min)', 'werbeauf-customs' ); ?></th>
                    <th><?php esc_html_e( 'Wiederhole (Min)', 'werbeauf-customs' ); ?></th>
                    <th><?php esc_html_e( 'Max', 'werbeauf-customs' ); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ( $rules as $slug => $rule ) :
                $channel_icon = 'admin' === $rule['channel'] ? '👤' : '📨';
                $tone_label = array(
                    'reminder'   => __( 'Erinnerung', 'werbeauf-customs' ),
                    'escalation' => __( 'Eskalation', 'werbeauf-customs' ),
                    'apology'    => __( 'Entschuldigung', 'werbeauf-customs' ),
                    'recovery'   => __( 'Recovery', 'werbeauf-customs' ),
                );
                $tone_text = $tone_label[ $rule['tone'] ?? '' ] ?? $rule['tone'];
                ?>
                <tr>
                    <td style="max-width:340px;">
                        <label style="display:flex;align-items:flex-start;gap:8px;cursor:pointer;">
                            <input type="checkbox" name="wa_workflow_rules[<?php echo esc_attr( $slug ); ?>][enabled]" value="1" <?php checked( ! empty( $rule['enabled'] ) ); ?> style="margin-top:3px;">
                            <span>
                                <strong><?php echo esc_html( $rule['label'] ); ?></strong>
                                <br><span style="font-size:12px;color:#646970;line-height:1.4;"><?php echo esc_html( $rule['description'] ); ?></span>
                                <br><code style="font-size:11px;opacity:.6;"><?php echo esc_html( $slug ); ?></code>
                            </span>
                        </label>
                    </td>
                    <td><?php echo esc_html( $tone_text ); ?></td>
                    <td><span style="font-size:13px;"><?php echo $channel_icon; ?> <?php echo esc_html( $rule['channel'] === 'admin' ? __( 'Mitarbeiterin', 'werbeauf-customs' ) : __( 'Kundin', 'werbeauf-customs' ) ); ?></span></td>
                    <td><code><?php echo esc_html( $rule['trigger_status'] ); ?></code></td>
                    <td>
                        <input type="number" name="wa_workflow_rules[<?php echo esc_attr( $slug ); ?>][after_minutes]"
                               value="<?php echo esc_attr( (int) $rule['after_minutes'] ); ?>"
                               min="1" max="43200" step="1" class="small-text">
                        <br><span style="font-size:11px;color:#646970;">≈ <?php echo esc_html( wa_sso_minutes_human( (int) $rule['after_minutes'] ) ); ?></span>
                    </td>
                    <td>
                        <input type="number" name="wa_workflow_rules[<?php echo esc_attr( $slug ); ?>][repeat_minutes]"
                               value="<?php echo esc_attr( (int) $rule['repeat_minutes'] ); ?>"
                               min="0" max="43200" step="1" class="small-text">
                        <br><span style="font-size:11px;color:#646970;"><?php echo (int) $rule['repeat_minutes'] === 0 ? esc_html__( 'einmalig', 'werbeauf-customs' ) : esc_html( '≈ ' . wa_sso_minutes_human( (int) $rule['repeat_minutes'] ) ); ?></span>
                    </td>
                    <td>
                        <input type="number" name="wa_workflow_rules[<?php echo esc_attr( $slug ); ?>][max_repeats]"
                               value="<?php echo esc_attr( (int) $rule['max_repeats'] ); ?>"
                               min="1" max="100" step="1" class="small-text">
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <p>
            <button type="submit" class="button button-primary"><?php esc_html_e( 'Workflow-Regeln speichern', 'werbeauf-customs' ); ?></button>
        </p>
    </form>

    <!-- Test-Run + Cron-Status -->
    <div style="display:flex;gap:12px;align-items:center;margin-top:16px;">
        <form method="post" action="<?php echo $action; ?>" style="margin:0;">
            <?php wp_nonce_field( 'wa_sso_workflow_run_now' ); ?>
            <input type="hidden" name="action" value="wa_sso_workflow_run_now">
            <button type="submit" class="button button-secondary"><?php esc_html_e( 'Scan jetzt manuell ausfuehren', 'werbeauf-customs' ); ?></button>
        </form>
        <span style="font-size:13px;color:#646970;">
            <?php
            if ( $next_cron ) {
                printf(
                    /* translators: %s a human-readable date */
                    esc_html__( 'Naechster automatischer Scan: %s (Lokalzeit)', 'werbeauf-customs' ),
                    esc_html( get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $next_cron ), 'Y-m-d H:i' ) )
                );
            } else {
                esc_html_e( 'Cron noch nicht geplant (wird beim naechsten Seitenaufruf registriert).', 'werbeauf-customs' );
            }
            ?>
        </span>
    </div>

    <!-- Workflow-Log -->
    <h3 style="margin-top:32px;"><?php esc_html_e( 'Letzte Workflow-Aktivitaet', 'werbeauf-customs' ); ?></h3>
    <?php wa_sso_render_workflow_log_table(); ?>
    <?php
}

/**
 * Konvertiert Minuten in eine menschenlesbare Kurzform.
 *
 * @param int $minutes
 * @return string
 */
function wa_sso_minutes_human( $minutes ) {
    $minutes = max( 1, (int) $minutes );
    if ( $minutes < 60 ) {
        return sprintf( _n( '%d Min', '%d Min', $minutes, 'werbeauf-customs' ), $minutes );
    }
    if ( $minutes < 1440 ) {
        $h = round( $minutes / 60, 1 );
        return sprintf( __( '%s h', 'werbeauf-customs' ), rtrim( rtrim( number_format( $h, 1, ',', '' ), '0' ), ',' ) );
    }
    $d = round( $minutes / 1440, 1 );
    return sprintf( __( '%s Tage', 'werbeauf-customs' ), rtrim( rtrim( number_format( $d, 1, ',', '' ), '0' ), ',' ) );
}

function wa_sso_render_workflow_log_table() {
    if ( ! function_exists( 'wa_workflow_log_recent' ) ) {
        return;
    }
    $rows = wa_workflow_log_recent( 25 );

    if ( empty( $rows ) ) {
        echo '<p style="opacity:.7;">' . esc_html__( 'Noch keine Workflow-Mails versendet.', 'werbeauf-customs' ) . '</p>';
        return;
    }
    $action_url = esc_url( admin_url( 'admin-post.php' ) );
    ?>
    <table class="widefat striped" style="max-width:1100px;">
        <thead>
            <tr>
                <th><?php esc_html_e( 'Wann', 'werbeauf-customs' ); ?></th>
                <th><?php esc_html_e( 'Regel', 'werbeauf-customs' ); ?></th>
                <th><?php esc_html_e( 'Bestellung', 'werbeauf-customs' ); ?></th>
                <th><?php esc_html_e( 'Kanal', 'werbeauf-customs' ); ?></th>
                <th><?php esc_html_e( 'Empfaenger', 'werbeauf-customs' ); ?></th>
                <th><?php esc_html_e( 'Status', 'werbeauf-customs' ); ?></th>
                <th><?php esc_html_e( 'Aktion', 'werbeauf-customs' ); ?></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ( $rows as $row ) :
            $status_color = 'sent' === $row['send_status'] ? '#00a32a' : '#d63638';
            ?>
            <tr>
                <td><?php echo esc_html( get_date_from_gmt( $row['sent_at'], 'Y-m-d H:i' ) ); ?></td>
                <td><code><?php echo esc_html( $row['rule_slug'] ); ?></code></td>
                <td>#<?php echo esc_html( (int) $row['order_id'] ); ?></td>
                <td><?php echo esc_html( $row['channel'] ); ?></td>
                <td style="font-family:monospace;font-size:12px;"><?php echo esc_html( $row['recipient'] ?: '—' ); ?></td>
                <td>
                    <span style="color:<?php echo esc_attr( $status_color ); ?>;font-weight:600;"><?php echo esc_html( $row['send_status'] ); ?></span>
                    <?php if ( $row['send_error'] ) : ?>
                        <br><span style="font-size:11px;color:#d63638;"><?php echo esc_html( $row['send_error'] ); ?></span>
                    <?php endif; ?>
                </td>
                <td>
                    <form method="post" action="<?php echo $action_url; ?>" style="margin:0;display:inline;">
                        <?php wp_nonce_field( 'wa_sso_workflow_resend' ); ?>
                        <input type="hidden" name="action" value="wa_sso_workflow_resend">
                        <input type="hidden" name="order_id" value="<?php echo esc_attr( (int) $row['order_id'] ); ?>">
                        <input type="hidden" name="rule_slug" value="<?php echo esc_attr( $row['rule_slug'] ); ?>">
                        <button type="submit" class="button-link" style="font-size:12px;text-decoration:underline;color:#475e76;cursor:pointer;background:none;border:0;padding:0;"
                            onclick="return confirm('<?php echo esc_js( __( 'Diese Email erneut senden?', 'werbeauf-customs' ) ); ?>');">
                            <?php esc_html_e( 'Erneut senden', 'werbeauf-customs' ); ?>
                        </button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}

/* ----------------------------------------------------------
   TEMPLATE-VORSCHAU-SEKTION

   Dropdown mit allen verfuegbaren Templates + Order-ID +
   Empfaenger-Adresse. Zwei Buttons: Vorschau (inline iframe via
   srcdoc) und Test-Email senden (echtes wp_mail).
---------------------------------------------------------- */

function wa_sso_render_template_preview_section() {
    if ( ! function_exists( 'wa_sso_available_template_slugs' ) ) {
        return;
    }
    $templates = wa_sso_available_template_slugs();
    if ( empty( $templates ) ) {
        echo '<p style="opacity:.7;">' . esc_html__( 'Keine Templates gefunden.', 'werbeauf-customs' ) . '</p>';
        return;
    }

    // Letzte Order-ID als Default fuer das Input.
    $last_order_id = 0;
    if ( function_exists( 'wc_get_orders' ) ) {
        $ids = (array) wc_get_orders( array( 'limit' => 1, 'return' => 'ids', 'orderby' => 'date', 'order' => 'DESC' ) );
        $last_order_id = ! empty( $ids ) ? (int) $ids[0] : 0;
    }
    $action_url = esc_url( admin_url( 'admin-post.php' ) );
    $own_email  = wp_get_current_user()->user_email;

    ?>
    <p style="max-width:760px;">
        <?php esc_html_e( 'Render ein beliebiges Template mit den Daten einer echten Bestellung. Vorschau bleibt im Browser; Test-Email geht via wp_mail (Postmark) an die angegebene Adresse.', 'werbeauf-customs' ); ?>
    </p>

    <form method="post" action="<?php echo $action_url; ?>" target="wa_sso_preview_frame" style="background:#fff;padding:16px 20px;border:1px solid #e2e4e7;border-radius:8px;max-width:760px;">
        <?php wp_nonce_field( 'wa_sso_template_preview' ); ?>
        <input type="hidden" name="action" value="wa_sso_template_preview">

        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><label for="wa_sso_tpl_slug"><?php esc_html_e( 'Template', 'werbeauf-customs' ); ?></label></th>
                <td>
                    <select name="template_slug" id="wa_sso_tpl_slug">
                        <?php foreach ( $templates as $slug ) : ?>
                            <option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $slug ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="wa_sso_tpl_order"><?php esc_html_e( 'Bestell-ID', 'werbeauf-customs' ); ?></label></th>
                <td>
                    <input type="number" name="order_id" id="wa_sso_tpl_order" value="<?php echo esc_attr( $last_order_id ); ?>" min="1" class="small-text">
                    <p class="description"><?php esc_html_e( 'Wird zum Befuellen aller Variablen im Template benutzt (Name, Summe, Status, etc.).', 'werbeauf-customs' ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Empfaenger fuer Test-Email', 'werbeauf-customs' ); ?></th>
                <td>
                    <input type="email" name="recipient" value="<?php echo esc_attr( $own_email ); ?>" class="regular-text">
                    <p class="description"><?php esc_html_e( 'Nur fuer den "Test-Email senden"-Button. Postmark-Account-Limits (Pending-Approval) gelten.', 'werbeauf-customs' ); ?></p>
                </td>
            </tr>
        </table>

        <p>
            <button type="submit" class="button button-secondary"><?php esc_html_e( 'Vorschau anzeigen', 'werbeauf-customs' ); ?></button>
        </p>
    </form>

    <!-- Inline-Vorschau (iframe target) -->
    <iframe name="wa_sso_preview_frame" style="width:100%;max-width:760px;height:600px;border:1px solid #e2e4e7;border-radius:8px;background:#f7f8fa;margin-top:12px;"></iframe>

    <!-- Test-Send-Form (separater Submit) -->
    <form method="post" action="<?php echo $action_url; ?>" style="margin-top:16px;max-width:760px;">
        <?php wp_nonce_field( 'wa_sso_template_test' ); ?>
        <input type="hidden" name="action" value="wa_sso_template_test">
        <!-- Dupliziere die Werte aus dem ersten Form via JS-frei: Browser submit waehlt das andere Form, daher hier explizit nochmal die Felder. -->
        <select name="template_slug">
            <?php foreach ( $templates as $slug ) : ?>
                <option value="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $slug ); ?></option>
            <?php endforeach; ?>
        </select>
        <input type="number" name="order_id" value="<?php echo esc_attr( $last_order_id ); ?>" min="1" class="small-text" style="width:90px;">
        <input type="email" name="recipient" value="<?php echo esc_attr( $own_email ); ?>" class="regular-text" style="width:260px;">
        <button type="submit" class="button button-primary"><?php esc_html_e( 'Test-Email senden', 'werbeauf-customs' ); ?></button>
    </form>
    <?php
}

/* ----------------------------------------------------------
   AUDIT-TABELLE
---------------------------------------------------------- */

function wa_sso_render_audit_table() {
    $rows = wa_sso_recent_tokens( 0, 25 );

    if ( empty( $rows ) ) {
        echo '<p style="opacity:.7;">' . esc_html__( 'Noch keine SSO-Logins.', 'werbeauf-customs' ) . '</p>';
        return;
    }

    ?>
    <table class="widefat striped" style="max-width:1000px;">
        <thead>
            <tr>
                <th><?php esc_html_e( 'Erstellt', 'werbeauf-customs' ); ?></th>
                <th><?php esc_html_e( 'Benutzer', 'werbeauf-customs' ); ?></th>
                <th><?php esc_html_e( 'Aktion', 'werbeauf-customs' ); ?></th>
                <th><?php esc_html_e( 'Status', 'werbeauf-customs' ); ?></th>
                <th><?php esc_html_e( 'Verbraucht', 'werbeauf-customs' ); ?></th>
                <th><?php esc_html_e( 'IP', 'werbeauf-customs' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ( $rows as $row ) :
                $user = get_userdata( (int) $row['user_id'] );
                $is_consumed = ! empty( $row['consumed_at'] );
                $is_expired  = ! $is_consumed && strtotime( $row['expires_at'] . ' UTC' ) < time();
                ?>
                <tr>
                    <td><?php echo esc_html( get_date_from_gmt( $row['created_at'] ) ); ?></td>
                    <td><?php echo $user ? esc_html( $user->display_name ) : '<em>' . esc_html__( 'geloescht', 'werbeauf-customs' ) . '</em>'; ?></td>
                    <td><code><?php echo esc_html( $row['action_slug'] ); ?></code></td>
                    <td>
                        <?php
                        if ( $is_consumed ) {
                            echo '<span style="color:#00a32a;">' . esc_html__( 'benutzt', 'werbeauf-customs' ) . '</span>';
                        } elseif ( $is_expired ) {
                            echo '<span style="color:#646970;">' . esc_html__( 'abgelaufen', 'werbeauf-customs' ) . '</span>';
                        } else {
                            echo '<span style="color:#daa520;">' . esc_html__( 'aktiv', 'werbeauf-customs' ) . '</span>';
                        }
                        ?>
                    </td>
                    <td><?php echo $row['consumed_at'] ? esc_html( get_date_from_gmt( $row['consumed_at'] ) ) : '—'; ?></td>
                    <td><?php echo esc_html( $row['consumed_ip'] ?: $row['created_ip'] ?: '—' ); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}

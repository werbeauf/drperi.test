<?php
/* ============================================================
   DATEI: includes/sso/confirm.php
   ZWECK: Confirm-Page fuer state-changing SSO-Actions.

   Architektur (refactored 2.2.0):
   ───────────────────────────────
   - Token wird beim Login-Endpoint NICHT mehr konsumiert. Erst
     hier beim Confirm-POST. Vorteil: wenn die Mitarbeiterin nach
     dem Klick durch Kundinnen unterbrochen wird und Stunden
     spaeter zurueck-klickt, lebt der Link noch.
   - Token IST der CSRF-Schutz. Statt WP-Nonce: bei jedem POST wird
     der Token validiert (existiert + nicht konsumiert + nicht
     abgelaufen + User-Rolle erlaubt) und atomar konsumiert (UPDATE
     mit consumed_at IS NULL Praedikat). Race-Conditions sind
     dadurch ausgeschlossen.
   - action_slug + action_args werden aus der Token-Row gelesen,
     NICHT aus Form-Feldern. Tamper-resistent.
   - Falls die Session zwischendurch abgelaufen ist (default WP
     Auth-Cookie = 2 Tage), wird sie automatisch re-etabliert --
     der Token ist Authentifizierungs-Beweis genug.

   Email-Scanner-Schutz (Outlook ATP / Gmail prefetch):
   - Scanner GET-fetcht den Login-Link -> setzt Auth-Cookie auf
     dem Scanner-Server -> redirected zur Confirm-Page -> rendert
     Form -> tut nichts weiter (kein POST).
   - Token bleibt unverbraucht. Echte Mitarbeiterin klickt
     spaeter, Form wird gerendert, sie drueckt "Bestaetigen" ->
     POST consume + handler. Fertig.

   Hook-Reihenfolge: init priority 10 (nach login.php @9).
============================================================ */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'init', 'wa_sso_handle_confirm_endpoint', 10 );

function wa_sso_handle_confirm_endpoint() {
    $is_post = 'POST' === strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? '' ) );

    // GET: Token kommt aus URL-Param. POST: aus dem versteckten Form-Feld.
    if ( $is_post ) {
        $raw_token = isset( $_POST['wa_sso_token'] ) ? sanitize_text_field( wp_unslash( $_POST['wa_sso_token'] ) ) : '';
    } else {
        $raw_token = isset( $_GET[ WA_SSO_CONFIRM_QUERY_VAR ] ) ? sanitize_text_field( wp_unslash( $_GET[ WA_SSO_CONFIRM_QUERY_VAR ] ) ) : '';
    }

    if ( '' === $raw_token ) {
        return; // kein Token = nicht unser Endpoint
    }
    if ( ! wa_sso_is_enabled() ) {
        return; // stilles Ignorieren -- Bookmark/Reload nicht panisch werden lassen
    }

    /* --------------------------------------------------
       Token-Validation (read-only).
       -------------------------------------------------- */
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
            __( 'Die Aktion ist nicht (mehr) registriert.', 'werbeauf-customs' )
        );
    }

    /* --------------------------------------------------
       Session-Sync: wenn die Mitarbeiterin Tage spaeter wiederkommt
       und der Auth-Cookie abgelaufen ist, oder sie versehentlich
       als anderer User eingeloggt ist, etablieren wir die Session
       basierend auf dem Token neu. Der Token IST der Auth-Beweis.
       -------------------------------------------------- */
    $token_user_id = (int) $row['user_id'];
    if ( ! is_user_logged_in() || get_current_user_id() !== $token_user_id ) {
        wp_clear_auth_cookie();
        wp_set_current_user( $token_user_id );
        wp_set_auth_cookie( $token_user_id, false, is_ssl() );
    }

    // Cap-Recheck (User koennte zwischen Klick + Confirm Rollen verloren haben).
    if ( ! user_can( $token_user_id, (string) $action['capability'] ) ) {
        wa_sso_render_error_page(
            __( 'Keine Berechtigung', 'werbeauf-customs' ),
            __( 'Sie duerfen diese Aktion nicht ausfuehren.', 'werbeauf-customs' )
        );
    }

    /* --------------------------------------------------
       POST -> atomar konsumieren + Handler ausfuehren
       -------------------------------------------------- */
    if ( $is_post ) {
        if ( ! is_callable( $action['handler_cb'] ) ) {
            wa_sso_render_error_page(
                __( 'Aktion nicht ausfuehrbar', 'werbeauf-customs' ),
                __( 'Die Aktion ist falsch konfiguriert (kein Handler).', 'werbeauf-customs' )
            );
        }

        // Falls die Action User-Inputs definiert (z.B. tracking_number,
        // note): die werden VOR consume eingesammelt, damit ein Fehlschlag
        // bei Pflichtfeldern den Token nicht verbraucht.
        $user_inputs       = array();
        $missing_required  = array();
        if ( ! empty( $action['inputs'] ) && is_array( $action['inputs'] ) ) {
            foreach ( $action['inputs'] as $name => $spec ) {
                $field_key = 'wa_sso_input_' . sanitize_key( $name );
                $raw_val   = isset( $_POST[ $field_key ] ) ? wp_unslash( $_POST[ $field_key ] ) : '';
                $type      = isset( $spec['type'] ) ? (string) $spec['type'] : 'text';
                if ( 'textarea' === $type ) {
                    $val = sanitize_textarea_field( (string) $raw_val );
                } else {
                    $val = sanitize_text_field( (string) $raw_val );
                }
                if ( ! empty( $spec['required'] ) && '' === $val ) {
                    $missing_required[] = isset( $spec['label'] ) ? (string) $spec['label'] : $name;
                }
                $user_inputs[ $name ] = $val;
            }
        }
        if ( ! empty( $missing_required ) ) {
            wa_sso_render_error_page(
                __( 'Pflichtfeld fehlt', 'werbeauf-customs' ),
                sprintf(
                    /* translators: %s comma-list of field labels */
                    __( 'Bitte fuellen Sie folgende Felder aus: %s. Gehen Sie zurueck und versuchen Sie es erneut.', 'werbeauf-customs' ),
                    implode( ', ', $missing_required )
                )
            );
        }

        // Atomar konsumieren. Schlaegt fehl, wenn zweiter Tab schneller war.
        $consumed = wa_sso_consume_token( $raw_token );
        if ( is_wp_error( $consumed ) ) {
            wa_sso_render_error_page(
                __( 'Link bereits benutzt', 'werbeauf-customs' ),
                $consumed->get_error_message()
            );
        }

        // Args = token row (server-side, tamper-safe) MERGED mit user-inputs.
        $args = array_merge( (array) $row['action_args'], $user_inputs );

        $result = call_user_func( $action['handler_cb'], $args, $token_user_id, $row['action_slug'] );

        if ( ! is_array( $result ) || empty( $result['success'] ) ) {
            wa_sso_render_error_page(
                __( 'Aktion fehlgeschlagen', 'werbeauf-customs' ),
                isset( $result['message'] ) ? (string) $result['message'] : __( 'Unbekannter Fehler.', 'werbeauf-customs' )
            );
        }

        // Erfolgs-Redirect.
        $redirect = '';
        if ( ! empty( $result['redirect'] ) ) {
            $redirect = (string) $result['redirect'];
        } elseif ( is_callable( $action['redirect_cb'] ) ) {
            $redirect = (string) call_user_func( $action['redirect_cb'], $args, $token_user_id );
        }
        if ( '' === $redirect ) {
            $redirect = admin_url();
        }

        // Toast-Message via Transient -> wird im Admin-Header gerendert.
        if ( isset( $result['message'] ) ) {
            set_transient(
                'wa_sso_msg_' . $token_user_id,
                array( 'text' => (string) $result['message'], 'type' => 'success' ),
                MINUTE_IN_SECONDS * 5
            );
        }

        wp_safe_redirect( $redirect );
        exit;
    }

    /* --------------------------------------------------
       GET -> Confirm-Page rendern.
       Token wird NICHT konsumiert -- nur dargestellt damit der
       Form-POST ihn referenzieren kann.
       -------------------------------------------------- */
    $args = (array) $row['action_args'];

    $summary = '';
    if ( is_callable( $action['summary_cb'] ) ) {
        $summary = (string) call_user_func( $action['summary_cb'], $args );
    }

    // Form-action ist dieselbe URL (Token bleibt im Pfad), Token zusaetzlich
    // im versteckten Feld damit POST ihn auch ohne URL-Param hat.
    $form  = '<form method="post" action="' . esc_url( wa_sso_current_url() ) . '">';
    $form .= '<input type="hidden" name="wa_sso_token" value="' . esc_attr( $raw_token ) . '">';

    // Optionale User-Input-Felder (z.B. Tracking-Nummer, Notiz).
    if ( ! empty( $action['inputs'] ) && is_array( $action['inputs'] ) ) {
        $form .= '<div style="margin:20px 0 4px;">';
        foreach ( $action['inputs'] as $name => $spec ) {
            $field_key = 'wa_sso_input_' . sanitize_key( $name );
            $type      = isset( $spec['type'] ) ? (string) $spec['type'] : 'text';
            $label     = isset( $spec['label'] ) ? (string) $spec['label'] : $name;
            $ph        = isset( $spec['placeholder'] ) ? (string) $spec['placeholder'] : '';
            $required  = ! empty( $spec['required'] );

            $form .= '<label style="display:block;margin:0 0 6px;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;font-size:13px;font-weight:600;color:#475e76;">';
            $form .= esc_html( $label );
            if ( $required ) {
                $form .= ' <span style="color:#d63638;" aria-hidden="true">*</span>';
            }
            $form .= '</label>';

            if ( 'textarea' === $type ) {
                $rows = max( 2, min( 10, isset( $spec['rows'] ) ? (int) $spec['rows'] : 4 ) );
                $form .= '<textarea name="' . esc_attr( $field_key ) . '"'
                      .  ' rows="' . (int) $rows . '"'
                      .  ' placeholder="' . esc_attr( $ph ) . '"'
                      .  ' style="width:100%;box-sizing:border-box;padding:10px 12px;font-family:inherit;font-size:14px;border:1px solid #c3c4c7;border-radius:6px;resize:vertical;margin-bottom:14px;"'
                      .  ( $required ? ' required' : '' ) . '></textarea>';
            } else {
                $form .= '<input type="text" name="' . esc_attr( $field_key ) . '"'
                      .  ' placeholder="' . esc_attr( $ph ) . '"'
                      .  ' style="width:100%;box-sizing:border-box;padding:10px 12px;font-family:inherit;font-size:14px;border:1px solid #c3c4c7;border-radius:6px;margin-bottom:14px;"'
                      .  ( $required ? ' required' : '' ) . '>';
            }
        }
        $form .= '</div>';
    }

    $cancel_url = admin_url();

    $form .= '<div class="wa-sso-actions">';
    $form .= '<button type="submit" class="wa-sso-btn wa-sso-btn--success">'
          .  esc_html( (string) $action['label'] )
          .  '</button>';
    $form .= '<a href="' . esc_url( $cancel_url ) . '" class="wa-sso-btn wa-sso-btn--secondary">'
          .  esc_html__( 'Abbrechen', 'werbeauf-customs' )
          .  '</a>';
    $form .= '</div></form>';

    $body  = '';
    if ( '' !== $summary ) {
        $body .= '<p class="wa-sso-meta">' . esc_html( $summary ) . '</p>';
    }
    $body .= '<p style="margin:0 0 8px;color:#475e76;font-size:16px;line-height:1.5;">'
          .  sprintf(
                /* translators: %s the action label, e.g. "Als versandt markieren" */
                esc_html__( 'Aktion bestaetigen: %s', 'werbeauf-customs' ),
                '<strong>' . esc_html( (string) $action['label'] ) . '</strong>'
             )
          .  '</p>';
    $body .= $form;

    echo wa_sso_page_layout( __( 'Aktion bestaetigen', 'werbeauf-customs' ), $body ); // phpcs:ignore WordPress.Security.EscapeOutput
    exit;
}

/**
 * Liefert die aktuelle URL (ohne Query-String-Manipulation),
 * brauchbar als Action-Target im POST-Form.
 *
 * @return string
 */
function wa_sso_current_url() {
    $scheme = is_ssl() ? 'https' : 'http';
    $host   = isset( $_SERVER['HTTP_HOST'] ) ? (string) $_SERVER['HTTP_HOST'] : parse_url( home_url(), PHP_URL_HOST );
    $uri    = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '/';
    return esc_url_raw( $scheme . '://' . $host . $uri );
}

/**
 * Decodiert die action_args JSON aus URL/POST sicher in ein Array.
 *
 * @param string $raw
 * @return array
 */
function wa_sso_decode_args( $raw ) {
    $raw = (string) $raw;
    if ( '' === $raw ) {
        return array();
    }
    // Wenn das eine rawurlencoded Variante ist (GET), erstmal decoden.
    $maybe = rawurldecode( $raw );
    $data  = json_decode( $maybe, true );
    if ( ! is_array( $data ) ) {
        $data = json_decode( $raw, true );
    }
    return is_array( $data ) ? $data : array();
}

/* ----------------------------------------------------------
   ADMIN-NOTICE: Erfolgs-Toast nach Confirm-Redirect.
   Liest den Transient (Schluessel pro User) und rendert eine
   Standard-WP-admin-notice.
---------------------------------------------------------- */

add_action( 'admin_notices', 'wa_sso_print_admin_message' );

function wa_sso_print_admin_message() {
    $uid = get_current_user_id();
    if ( ! $uid ) {
        return;
    }
    $key = 'wa_sso_msg_' . $uid;
    $msg = get_transient( $key );
    if ( ! is_array( $msg ) || empty( $msg['text'] ) ) {
        return;
    }
    delete_transient( $key );
    $type  = isset( $msg['type'] ) && in_array( $msg['type'], array( 'success', 'error', 'warning', 'info' ), true )
        ? $msg['type'] : 'info';
    printf(
        '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
        esc_attr( $type ),
        esc_html( $msg['text'] )
    );
}

<?php
/* ============================================================
   DATEI: includes/sso/emails.php
   ZWECK: Email-seitige Integration.

   1. Public helpers:
        wa_sso_action_url()  -- erzeugt Token + URL
        wa_sso_button()      -- inline-CSS Button (Email-tauglich)
        wa_sso_button_row()  -- mehrere Buttons als Tabelle

   2. WC-Auto-Injection:
        Hook woocommerce_email_after_order_table fuer das
        "WC_Email_New_Order" Template. Wenn die Empfaenger-Email
        auf einen WP-User mit erlaubter Rolle gemappt werden kann,
        rendert eine Button-Reihe (Ansehen / In Bearbeitung /
        Versandt) am Ende des Tables.

   Multi-Recipient: WC sendet eine einzige Email an alle
   Empfaenger. Wir generieren Buttons nur fuer den ERSTEN
   erkannten WP-User-Empfaenger. Fuer per-User-Buttons muesste
   man die Email pro Empfaenger einzeln senden -- ist Phase 2.
============================================================ */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ----------------------------------------------------------
   PUBLIC HELPERS
---------------------------------------------------------- */

/**
 * Erzeugt ein Token fuer $user_id + $action_slug und liefert
 * eine fertige URL fuer den Email-Button zurueck.
 *
 * @param int    $user_id
 * @param string $action_slug
 * @param array  $action_args
 * @return string|WP_Error
 */
function wa_sso_action_url( $user_id, $action_slug, array $action_args = array() ) {
    if ( ! wa_sso_is_enabled() ) {
        return new WP_Error( 'sso_disabled', __( 'SSO ist deaktiviert.', 'werbeauf-customs' ) );
    }
    $raw = wa_sso_create_token( $user_id, $action_slug, $action_args );
    if ( is_wp_error( $raw ) ) {
        return $raw;
    }
    return add_query_arg( array( WA_SSO_QUERY_VAR => $raw ), home_url( '/' ) );
}

/**
 * Email-fest gestylter Button als HTML-String.
 * Inline-CSS only -- viele Email-Clients (Outlook!) ignorieren
 * <style>-Tags.
 *
 * @param string $url
 * @param string $label
 * @param string $bg     Background-Hex (Default WP-Blau).
 * @param string $fg     Foreground-Hex (Default Weiss).
 * @return string
 */
function wa_sso_button( $url, $label, $bg = '#0073aa', $fg = '#ffffff' ) {
    if ( '' === $url || '' === $label ) {
        return '';
    }
    return sprintf(
        '<a href="%1$s" style="display:inline-block;padding:14px 22px;background:%3$s;color:%4$s;text-decoration:none;border-radius:6px;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;font-weight:600;font-size:14px;line-height:1;mso-padding-alt:0;text-align:center;">%2$s</a>',
        esc_url( $url ),
        esc_html( $label ),
        esc_attr( $bg ),
        esc_attr( $fg )
    );
}

/**
 * Reiht mehrere Buttons in einer (Outlook-vertraeglichen)
 * Email-Tabelle. Items: [ ['url' => ..., 'label' => ..., 'bg' => ...], ... ].
 *
 * @param array<int, array{url:string,label:string,bg?:string,fg?:string}> $items
 * @return string
 */
function wa_sso_button_row( array $items ) {
    if ( empty( $items ) ) {
        return '';
    }
    $cells = '';
    foreach ( $items as $item ) {
        if ( empty( $item['url'] ) || empty( $item['label'] ) ) {
            continue;
        }
        $btn   = wa_sso_button(
            (string) $item['url'],
            (string) $item['label'],
            isset( $item['bg'] ) ? (string) $item['bg'] : '#0073aa',
            isset( $item['fg'] ) ? (string) $item['fg'] : '#ffffff'
        );
        $cells .= '<td style="padding:0 6px 8px 0;">' . $btn . '</td>';
    }
    return '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:8px 0;"><tr>' . $cells . '</tr></table>';
}

/* ----------------------------------------------------------
   WC AUTO-INJECTION

   Hook nach der Order-Item-Tabelle in WC-Emails. Wir filtern
   auf die "new_order" Email (admin-only) und auf erkannte
   WP-User-Empfaenger.
---------------------------------------------------------- */

add_action( 'woocommerce_email_after_order_table', 'wa_sso_inject_admin_email_buttons', 20, 4 );

/**
 * @param WC_Order $order
 * @param bool     $sent_to_admin
 * @param bool     $plain_text
 * @param WC_Email $email
 */
function wa_sso_inject_admin_email_buttons( $order, $sent_to_admin, $plain_text, $email ) {
    if ( ! wa_sso_is_enabled() ) {
        return;
    }
    if ( ! wa_sso_settings_get( 'inject_into_wc_email', true ) ) {
        return;
    }
    if ( ! $sent_to_admin || $plain_text ) {
        return;
    }
    if ( ! is_object( $order ) || ! method_exists( $order, 'get_id' ) ) {
        return;
    }
    // WC_Email exposes $id as a public property, not a method -- use property check.
    if ( ! is_object( $email ) || ! isset( $email->id ) ) {
        return;
    }
    // Nur fuer Admin-Emails die "neue Bestellung" signalisieren.
    $allowed_email_ids = apply_filters(
        'wa_sso_inject_into_email_ids',
        array( 'new_order' )
    );
    if ( ! in_array( $email->id, (array) $allowed_email_ids, true ) ) {
        return;
    }

    $recipients = $email->get_recipient();
    $user_id    = wa_sso_first_recipient_user_id( $recipients );
    if ( ! $user_id ) {
        return; // kein WP-User unter den Empfaengern -- keine Buttons.
    }

    $order_id = (int) $order->get_id();

    $items = array();

    $url = wa_sso_action_url( $user_id, 'view_order', array( 'order_id' => $order_id ) );
    if ( ! is_wp_error( $url ) ) {
        $items[] = array(
            'url'   => $url,
            'label' => __( 'Bestellung ansehen', 'werbeauf-customs' ),
            'bg'    => '#0073aa',
        );
    }

    $url = wa_sso_action_url( $user_id, 'mark_processing', array( 'order_id' => $order_id ) );
    if ( ! is_wp_error( $url ) ) {
        $items[] = array(
            'url'   => $url,
            'label' => __( 'In Bearbeitung', 'werbeauf-customs' ),
            'bg'    => '#daa520',
        );
    }

    $url = wa_sso_action_url( $user_id, 'mark_completed', array( 'order_id' => $order_id ) );
    if ( ! is_wp_error( $url ) ) {
        $items[] = array(
            'url'   => $url,
            'label' => __( 'Als versandt markieren', 'werbeauf-customs' ),
            'bg'    => '#00a32a',
        );
    }

    // cancel_order: nur unbezahlt sinnvoll -- wir injizieren den
    // Button trotzdem immer, der Handler weist bezahlte Bestellungen
    // hoeflich ab (siehe wa_sso_handler_cancel_order). So muss die
    // Mitarbeiterin nicht wissen ob die Bestellung schon bezahlt ist
    // bevor sie klickt.
    $url = wa_sso_action_url( $user_id, 'cancel_order', array( 'order_id' => $order_id ) );
    if ( ! is_wp_error( $url ) ) {
        $items[] = array(
            'url'   => $url,
            'label' => __( 'Stornieren', 'werbeauf-customs' ),
            'bg'    => '#d63638',
        );
    }

    // Sekundaer-Aktionen als kleinere Text-Links (visuelle Hierarchie).
    $secondary = array();
    foreach ( array(
        'mark_picked_up'    => __( 'Vor Ort abgeholt', 'werbeauf-customs' ),
        'add_internal_note' => __( 'Notiz hinzufuegen', 'werbeauf-customs' ),
        'print_label'       => __( 'Label drucken', 'werbeauf-customs' ),
        'contact_customer'  => __( 'Kundin schreiben', 'werbeauf-customs' ),
    ) as $slug => $label ) {
        $u = wa_sso_action_url( $user_id, $slug, array( 'order_id' => $order_id ) );
        if ( ! is_wp_error( $u ) ) {
            $secondary[] = array( 'url' => $u, 'label' => $label );
        }
    }

    if ( empty( $items ) ) {
        return;
    }

    // TTL-Label dynamisch aus den Settings (statt hardcoded "15 Min").
    $ttl_min   = (int) wa_sso_settings_get( 'token_ttl_minutes', 10080 );
    $ttl_label = wa_sso_format_ttl_label( $ttl_min );

    /* === Schnellaktionen-Block === */
    echo '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="width:100%;margin:24px 0 0;border-top:1px solid #e2e4e7;padding-top:16px;"><tr><td>';
    echo '<p style="margin:0 0 12px;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;font-size:14px;color:#1d2327;">';
    printf(
        /* translators: %s e.g. "7 Tage" */
        esc_html__( 'Schnellaktionen (Single-Sign-On-Login, gueltig %s):', 'werbeauf-customs' ),
        esc_html( $ttl_label )
    );
    echo '</p>';
    echo wa_sso_button_row( $items ); // phpcs:ignore WordPress.Security.EscapeOutput -- HTML komponiert.

    // Sekundaer-Aktionen: kleine Text-Links unterhalb der primaeren Buttons.
    if ( ! empty( $secondary ) ) {
        echo '<p style="margin:14px 0 0;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;font-size:13px;color:#475e76;line-height:1.6;">';
        $links = array();
        foreach ( $secondary as $item ) {
            $links[] = '<a href="' . esc_url( $item['url'] ) . '" style="color:#475e76;text-decoration:underline;">' . esc_html( $item['label'] ) . '</a>';
        }
        echo implode( ' &nbsp;·&nbsp; ', $links ); // phpcs:ignore WordPress.Security.EscapeOutput -- a-tags pre-escaped above.
        echo '</p>';
    }

    echo '<p style="margin:12px 0 0;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;font-size:12px;color:#646970;">';
    echo esc_html__( 'Jeder Link ist einmalig und zeitbegrenzt. Statusaenderungen erfordern eine zusaetzliche Bestaetigung.', 'werbeauf-customs' );
    echo '</p>';
    echo '</td></tr></table>';

    /* === Bestell-Verlauf (Notes + Status) === */
    wa_sso_render_order_history_block( $order );
}

/**
 * Liefert ein menschenlesbares Label fuer eine Minuten-TTL,
 * z.B. 15 -> "15 Minuten", 10080 -> "7 Tage".
 *
 * @param int $minutes
 * @return string
 */
function wa_sso_format_ttl_label( $minutes ) {
    $minutes = max( 1, (int) $minutes );
    if ( $minutes < 60 ) {
        return sprintf( _n( '%d Minute', '%d Minuten', $minutes, 'werbeauf-customs' ), $minutes );
    }
    if ( $minutes < 1440 ) {
        $h = (int) round( $minutes / 60 );
        return sprintf( _n( '%d Stunde', '%d Stunden', $h, 'werbeauf-customs' ), $h );
    }
    $d = (int) round( $minutes / 1440 );
    return sprintf( _n( '%d Tag', '%d Tage', $d, 'werbeauf-customs' ), $d );
}

/**
 * Rendert einen Bestell-Verlauf-Block (aktueller Status + letzte 5
 * Order-Notes) am Boden der Admin-Email. So muss die Mitarbeiterin
 * nicht ins WP-Admin wechseln um den Kontext zu sehen.
 *
 * Notes werden aus WC_Order_Notes geladen (System + manuell, nur
 * private/internal -- Kunden-Notes filtern wir raus).
 *
 * @param WC_Order $order
 */
function wa_sso_render_order_history_block( $order ) {
    if ( ! is_object( $order ) || ! method_exists( $order, 'get_id' ) ) {
        return;
    }
    if ( ! function_exists( 'wc_get_order_notes' ) || ! function_exists( 'wc_get_order_status_name' ) ) {
        return;
    }

    $status_label   = wc_get_order_status_name( $order->get_status() );
    $modified_date  = $order->get_date_modified();
    $modified_label = '';
    if ( $modified_date && is_object( $modified_date ) && method_exists( $modified_date, 'getTimestamp' ) ) {
        $modified_label = wp_date( 'Y-m-d H:i', $modified_date->getTimestamp() );
    }

    // Notes laden: letzte 5, nur internal (kunden-sichtbare ausschliessen).
    $notes = (array) wc_get_order_notes( array(
        'order_id' => $order->get_id(),
        'limit'    => 5,
        'orderby'  => 'date_created',
        'order'    => 'DESC',
        'type'     => 'internal',
    ) );

    $font = '-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif';

    echo '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="width:100%;margin:16px 0 0;border-top:1px solid #e2e4e7;padding-top:16px;"><tr><td>';

    echo '<p style="margin:0 0 12px;font-family:' . esc_attr( $font ) . ';font-size:14px;font-weight:600;color:#1d2327;">';
    echo esc_html__( 'Bestell-Verlauf', 'werbeauf-customs' );
    echo '</p>';

    // Aktueller Status.
    echo '<p style="margin:0 0 10px;font-family:' . esc_attr( $font ) . ';font-size:13px;color:#1d2327;line-height:1.5;">';
    echo '<strong>' . esc_html__( 'Aktueller Status:', 'werbeauf-customs' ) . '</strong> ';
    echo esc_html( $status_label );
    if ( $modified_label ) {
        echo ' <span style="color:#646970;">(' . esc_html(
            sprintf( __( 'zuletzt geaendert %s', 'werbeauf-customs' ), $modified_label )
        ) . ')</span>';
    }
    echo '</p>';

    // Notes-Liste.
    if ( ! empty( $notes ) ) {
        echo '<p style="margin:0 0 6px;font-family:' . esc_attr( $font ) . ';font-size:12px;color:#646970;">';
        echo esc_html__( 'Letzte interne Notizen:', 'werbeauf-customs' );
        echo '</p>';
        echo '<ul style="margin:0;padding-left:18px;font-family:' . esc_attr( $font ) . ';font-size:12px;color:#475e76;line-height:1.5;">';
        foreach ( $notes as $note ) {
            $note_date = '';
            if ( isset( $note->date_created ) && is_object( $note->date_created ) && method_exists( $note->date_created, 'getTimestamp' ) ) {
                $note_date = wp_date( 'Y-m-d H:i', $note->date_created->getTimestamp() );
            }
            $note_author = isset( $note->added_by ) && '' !== (string) $note->added_by ? (string) $note->added_by : __( 'System', 'werbeauf-customs' );
            $note_text   = isset( $note->content ) ? wp_strip_all_tags( (string) $note->content ) : '';
            // Lange Notes auf 200 Zeichen kuerzen damit Email nicht aufgeblasen wird.
            if ( strlen( $note_text ) > 200 ) {
                $note_text = mb_substr( $note_text, 0, 200 ) . '…';
            }

            echo '<li style="margin-bottom:4px;">';
            if ( '' !== $note_date ) {
                echo '<span style="color:#646970;">' . esc_html( $note_date ) . '</span> · ';
            }
            echo '<strong>' . esc_html( $note_author ) . ':</strong> ';
            echo esc_html( $note_text );
            echo '</li>';
        }
        echo '</ul>';
    } else {
        echo '<p style="margin:0;font-family:' . esc_attr( $font ) . ';font-size:12px;color:#646970;font-style:italic;">';
        echo esc_html__( 'Noch keine internen Notizen zu dieser Bestellung.', 'werbeauf-customs' );
        echo '</p>';
    }

    echo '</td></tr></table>';
}

/**
 * Findet den ersten Empfaenger aus einer Komma-Liste, der zu
 * einem WP-User mit erlaubter Rolle gehoert.
 *
 * @param string $recipients   Komma- oder Semikolon-separierte Liste.
 * @return int 0 wenn keiner gefunden.
 */
function wa_sso_first_recipient_user_id( $recipients ) {
    if ( ! is_string( $recipients ) || '' === $recipients ) {
        return 0;
    }
    $emails = preg_split( '/[\s,;]+/', $recipients, -1, PREG_SPLIT_NO_EMPTY );
    if ( ! is_array( $emails ) ) {
        return 0;
    }
    foreach ( $emails as $email ) {
        $email = sanitize_email( trim( $email ) );
        if ( '' === $email || ! is_email( $email ) ) {
            continue;
        }
        $user = get_user_by( 'email', $email );
        if ( $user && wa_sso_user_is_allowed( $user->ID ) ) {
            return (int) $user->ID;
        }
    }
    return 0;
}

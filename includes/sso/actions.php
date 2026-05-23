<?php
/* ============================================================
   DATEI: includes/sso/actions.php
   ZWECK: Action-Registry + built-in Actions.

   Eine "Action" ist ein registrierter Handler, der nach
   erfolgreichem SSO-Login ausgefuehrt werden kann. Drei Typen:

   SAFE (confirm=false):    Reine Navigation, keine State-Aenderung.
                            Direktes Redirect nach Login.
   STATE (confirm=true):    Aendert Bestelldaten o.ae.
                            Confirm-Page wird zwischen Login + Mutation
                            geschoben (Email-Scanner-Schutz, Cap-Check
                            mit Nonce).
   CUSTOM:                  Andere Plugins koennen via Filter
                            wa_sso_register_default_actions eigene
                            Actions registrieren.

   Schema einer Action:
     [
       'label'       => string       UI-Text fuer Buttons / Confirm-Page
       'capability'  => string       Cap die der User braucht (sonst 403)
       'confirm'     => bool         Confirm-Page anzeigen?
       'handler_cb'  => callable     fn(array $args, int $user_id): array
                                     Returns [success:bool, message:string, redirect?:string]
       'redirect_cb' => callable     fn(array $args, int $user_id): string
                                     URL nach Action / nach direkt-Login
       'summary_cb'  => callable     fn(array $args): string
                                     Kurzbeschreibung fuer Confirm-Page
                                     (z.B. "Bestellung #1234 von Max Mustermann")
     ]
============================================================ */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ----------------------------------------------------------
   REGISTRY
---------------------------------------------------------- */

/**
 * Internal storage. Wird beim ersten Zugriff initialisiert,
 * erlaubt Registrierung VOR und NACH dem 'init' Hook.
 *
 * @return array<string, array<string, mixed>>
 */
function &wa_sso_action_registry() {
    static $registry = array();
    return $registry;
}

/**
 * Registriert eine Action.
 *
 * @param string $slug Maschinen-Slug (sanitize_key).
 * @param array  $args Siehe Schema oben.
 * @return bool
 */
function wa_sso_register_action( $slug, array $args ) {
    $slug = sanitize_key( $slug );
    if ( '' === $slug ) {
        return false;
    }
    $defaults = array(
        'label'       => $slug,
        'capability'  => 'read',
        'confirm'     => false,
        'handler_cb'  => null,
        'redirect_cb' => null,
        'summary_cb'  => null,
    );
    $registry          = &wa_sso_action_registry();
    $registry[ $slug ] = array_merge( $defaults, $args );
    return true;
}

/**
 * Liefert eine Action oder null.
 *
 * @param string $slug
 * @return array|null
 */
function wa_sso_get_action( $slug ) {
    $slug     = sanitize_key( $slug );
    $registry = wa_sso_action_registry();
    return isset( $registry[ $slug ] ) ? $registry[ $slug ] : null;
}

/* ----------------------------------------------------------
   REGISTRIERUNG DER BUILT-INS

   Auf 'init' priority 7 -- nach dem Self-Install (priority 5),
   vor dem Login-Endpoint (priority 9, siehe login.php).
---------------------------------------------------------- */

add_action( 'init', 'wa_sso_register_default_actions', 7 );

function wa_sso_register_default_actions() {

    /* ---------- dashboard ---------- */
    wa_sso_register_action( 'dashboard', array(
        'label'       => __( 'Zum Dashboard', 'werbeauf-customs' ),
        'capability'  => 'read',
        'confirm'     => false,
        'redirect_cb' => function ( $args, $user_id ) {
            return admin_url();
        },
    ) );

    /* ---------- view_order ---------- */
    wa_sso_register_action( 'view_order', array(
        'label'       => __( 'Bestellung ansehen', 'werbeauf-customs' ),
        'capability'  => 'edit_shop_orders',
        'confirm'     => false,
        'redirect_cb' => function ( $args, $user_id ) {
            $order_id = isset( $args['order_id'] ) ? (int) $args['order_id'] : 0;
            return wa_sso_admin_order_url( $order_id );
        },
        'summary_cb'  => function ( $args ) {
            return wa_sso_order_summary( isset( $args['order_id'] ) ? (int) $args['order_id'] : 0 );
        },
    ) );

    /* ---------- mark_processing ---------- */
    wa_sso_register_action( 'mark_processing', array(
        'label'       => __( 'In Bearbeitung setzen', 'werbeauf-customs' ),
        'capability'  => 'edit_shop_orders',
        'confirm'     => true,
        'handler_cb'  => 'wa_sso_handler_set_order_status',
        'redirect_cb' => function ( $args, $user_id ) {
            $order_id = isset( $args['order_id'] ) ? (int) $args['order_id'] : 0;
            return wa_sso_admin_order_url( $order_id );
        },
        'summary_cb'  => function ( $args ) {
            return wa_sso_order_summary( isset( $args['order_id'] ) ? (int) $args['order_id'] : 0 );
        },
    ) );

    /* ---------- mark_completed (= "versandt" semantisch fuer drperi) ----------
       Optional: Tracking-Nummer-Eingabe direkt auf der Confirm-Page.
       Leer lassen = nur Status setzen, kein Tracking. Mit Nummer = Status
       setzen + Tracking-Meta + Hook 'wa_sso_tracking_number_added' fuer
       downstream-Integrationen (z.B. Email an Kundin via Tracking-Plugin). */
    wa_sso_register_action( 'mark_completed', array(
        'label'       => __( 'Als versandt markieren', 'werbeauf-customs' ),
        'capability'  => 'edit_shop_orders',
        'confirm'     => true,
        'handler_cb'  => 'wa_sso_handler_set_order_status',
        'inputs'      => array(
            'tracking_number' => array(
                'type'        => 'text',
                'label'       => __( 'Tracking-Nummer (optional)', 'werbeauf-customs' ),
                'placeholder' => __( 'z.B. RR123456789AT', 'werbeauf-customs' ),
                'required'    => false,
            ),
        ),
        'redirect_cb' => function ( $args, $user_id ) {
            $order_id = isset( $args['order_id'] ) ? (int) $args['order_id'] : 0;
            return wa_sso_admin_order_url( $order_id );
        },
        'summary_cb'  => function ( $args ) {
            return wa_sso_order_summary( isset( $args['order_id'] ) ? (int) $args['order_id'] : 0 );
        },
    ) );

    /* ---------- add_internal_note ----------
       Textarea-Input auf der Confirm-Page. Speichert als private
       WC-Order-Note (nicht kundensichtbar). Sichtbar im Bestell-Verlauf-
       Block am Boden der naechsten Admin-Email. */
    wa_sso_register_action( 'add_internal_note', array(
        'label'       => __( 'Notiz hinzufuegen', 'werbeauf-customs' ),
        'capability'  => 'edit_shop_orders',
        'confirm'     => true,
        'handler_cb'  => 'wa_sso_handler_add_internal_note',
        'inputs'      => array(
            'note' => array(
                'type'        => 'textarea',
                'label'       => __( 'Interne Notiz', 'werbeauf-customs' ),
                'placeholder' => __( 'z.B. Kundin will Versand erst Montag, Lieferung an Filiale ...', 'werbeauf-customs' ),
                'required'    => true,
                'rows'        => 5,
            ),
        ),
        'redirect_cb' => function ( $args, $user_id ) {
            return wa_sso_admin_order_url( isset( $args['order_id'] ) ? (int) $args['order_id'] : 0 );
        },
        'summary_cb'  => function ( $args ) {
            return wa_sso_order_summary( isset( $args['order_id'] ) ? (int) $args['order_id'] : 0 );
        },
    ) );

    /* ---------- mark_picked_up ----------
       Status -> completed, mit "Lokal abgeholt"-Note + Meta-Flag.
       Funktional identisch zu mark_completed, aber semantisch klarer:
       kein Versand, kein Tracking, Kundin holt vor Ort ab. */
    wa_sso_register_action( 'mark_picked_up', array(
        'label'       => __( 'Als abgeholt markieren', 'werbeauf-customs' ),
        'capability'  => 'edit_shop_orders',
        'confirm'     => true,
        'handler_cb'  => 'wa_sso_handler_mark_picked_up',
        'redirect_cb' => function ( $args, $user_id ) {
            return wa_sso_admin_order_url( isset( $args['order_id'] ) ? (int) $args['order_id'] : 0 );
        },
        'summary_cb'  => function ( $args ) {
            return wa_sso_order_summary( isset( $args['order_id'] ) ? (int) $args['order_id'] : 0 );
        },
    ) );

    /* ---------- print_label ----------
       Safe Action (kein Confirm). Loggt User ein + redirected zur
       Label-Print-URL. Default: WC-Order-Edit. Filterbar:
         add_filter( 'wa_sso_print_label_url', fn($default, $order_id) => '...' )
       so kann ein Austrian-Post-Plugin seine eigene URL injizieren. */
    wa_sso_register_action( 'print_label', array(
        'label'       => __( 'Versand-Label drucken', 'werbeauf-customs' ),
        'capability'  => 'edit_shop_orders',
        'confirm'     => false,
        'redirect_cb' => function ( $args, $user_id ) {
            $order_id = isset( $args['order_id'] ) ? (int) $args['order_id'] : 0;
            $default  = wa_sso_admin_order_url( $order_id );
            return (string) apply_filters( 'wa_sso_print_label_url', $default, $order_id );
        },
    ) );

    /* ---------- contact_customer ----------
       Safe Action. Loggt User ein + redirected zu mailto:-URL mit
       vor-ausgefuelltem Subject. Browser oeffnet den Email-Client der
       Mitarbeiterin. Keine eigene Mail durch unser System -- die
       Kundin antwortet auf das echte Postfach der Mitarbeiterin. */
    wa_sso_register_action( 'contact_customer', array(
        'label'       => __( 'Kundin per Email kontaktieren', 'werbeauf-customs' ),
        'capability'  => 'edit_shop_orders',
        'confirm'     => false,
        'redirect_cb' => function ( $args, $user_id ) {
            $order_id = isset( $args['order_id'] ) ? (int) $args['order_id'] : 0;
            return wa_sso_build_mailto_for_order( $order_id );
        },
    ) );

    /* ---------- cancel_order ----------
       Nur fuer NICHT bezahlte Bestellungen (pending / on-hold).
       Bei processing/completed wird abgelehnt -- bezahlte Stornos
       benoetigen einen echten Refund, sonst bleibt das Geld beim
       Shop und Kunde bekommt nichts zurueck. Defensiver Pfad
       fuer nicht-technisches Personal. */
    wa_sso_register_action( 'cancel_order', array(
        'label'       => __( 'Bestellung stornieren', 'werbeauf-customs' ),
        'capability'  => 'edit_shop_orders',
        'confirm'     => true,
        'handler_cb'  => 'wa_sso_handler_cancel_order',
        'redirect_cb' => function ( $args, $user_id ) {
            return wa_sso_admin_order_url( isset( $args['order_id'] ) ? (int) $args['order_id'] : 0 );
        },
        'summary_cb'  => function ( $args ) {
            return wa_sso_order_summary( isset( $args['order_id'] ) ? (int) $args['order_id'] : 0 );
        },
    ) );

    /**
     * Andere Module / Themes koennen hier eigene Actions ergaenzen.
     * Beispiel siehe docs/SSO.md.
     */
    do_action( 'wa_sso_register_actions' );
}

/* ----------------------------------------------------------
   TRACKING-NUMMER bei mark_completed (extended Handler)

   Wir erweitern den generischen Status-Handler nicht direkt --
   stattdessen lauschen wir auf wa_sso_after_status_change und
   speichern die Tracking-Nummer wenn vorhanden.
---------------------------------------------------------- */

add_action( 'wa_sso_after_status_change', 'wa_sso_maybe_save_tracking_number', 10, 3 );

/**
 * @param int    $order_id
 * @param string $target_status
 * @param array  $args  Confirm-Page-Inputs + token args
 */
function wa_sso_maybe_save_tracking_number( $order_id, $target_status, $args ) {
    if ( 'completed' !== $target_status ) {
        return; // Tracking macht nur Sinn beim Versand-Trigger.
    }
    $tracking = isset( $args['tracking_number'] ) ? trim( (string) $args['tracking_number'] ) : '';
    if ( '' === $tracking ) {
        return;
    }
    if ( ! function_exists( 'wc_get_order' ) ) {
        return;
    }
    $order = wc_get_order( (int) $order_id );
    if ( ! $order ) {
        return;
    }

    // Standard-Meta-Key (kompatibel mit den meisten Tracking-Plugins).
    $order->update_meta_data( '_tracking_number', $tracking );

    // Order-Note (sichtbar im Verlauf der Mitarbeiterin).
    $note = sprintf(
        /* translators: %s tracking number */
        __( 'Tracking-Nummer hinterlegt: %s', 'werbeauf-customs' ),
        $tracking
    );
    $order->add_order_note( $note, false /* not customer-visible */ );
    $order->save();

    /**
     * Downstream-Hook: ein Tracking-Email-Plugin kann hier reagieren
     * und der Kundin die Tracking-Nummer per Email schicken.
     *
     * @param int    $order_id
     * @param string $tracking
     */
    do_action( 'wa_sso_tracking_number_added', $order_id, $tracking );
}

/* ----------------------------------------------------------
   ADD-INTERNAL-NOTE HANDLER
---------------------------------------------------------- */

function wa_sso_handler_add_internal_note( $args, $user_id ) {
    if ( ! function_exists( 'wc_get_order' ) ) {
        return array( 'success' => false, 'message' => __( 'WooCommerce ist nicht aktiv.', 'werbeauf-customs' ) );
    }
    $order_id = isset( $args['order_id'] ) ? (int) $args['order_id'] : 0;
    $note     = isset( $args['note'] ) ? trim( (string) $args['note'] ) : '';

    if ( $order_id <= 0 ) {
        return array( 'success' => false, 'message' => __( 'Keine Bestell-ID angegeben.', 'werbeauf-customs' ) );
    }
    if ( '' === $note ) {
        return array( 'success' => false, 'message' => __( 'Notiz-Text fehlt.', 'werbeauf-customs' ) );
    }
    $order = wc_get_order( $order_id );
    if ( ! $order ) {
        return array( 'success' => false, 'message' => __( 'Bestellung nicht gefunden.', 'werbeauf-customs' ) );
    }

    $u    = get_userdata( (int) $user_id );
    $note_with_author = sprintf( '[%s] %s', $u ? $u->display_name : 'unknown', $note );

    $order->add_order_note( $note_with_author, false /* internal */ );
    $order->save();

    return array(
        'success' => true,
        'message' => sprintf(
            /* translators: %s order number */
            __( 'Notiz zu Bestellung %s gespeichert.', 'werbeauf-customs' ),
            $order->get_order_number()
        ),
    );
}

/* ----------------------------------------------------------
   MARK-PICKED-UP HANDLER
---------------------------------------------------------- */

function wa_sso_handler_mark_picked_up( $args, $user_id ) {
    if ( ! function_exists( 'wc_get_order' ) ) {
        return array( 'success' => false, 'message' => __( 'WooCommerce ist nicht aktiv.', 'werbeauf-customs' ) );
    }
    $order_id = isset( $args['order_id'] ) ? (int) $args['order_id'] : 0;
    $order    = $order_id ? wc_get_order( $order_id ) : null;
    if ( ! $order ) {
        return array( 'success' => false, 'message' => __( 'Bestellung nicht gefunden.', 'werbeauf-customs' ) );
    }
    if ( 'completed' === $order->get_status() ) {
        return array(
            'success' => true,
            'message' => sprintf( __( 'Bestellung %s ist bereits abgeschlossen.', 'werbeauf-customs' ), $order->get_order_number() ),
        );
    }

    $u = get_userdata( (int) $user_id );
    $note = sprintf(
        /* translators: %s user display name */
        __( 'Vor Ort abgeholt -- erfasst von %s.', 'werbeauf-customs' ),
        $u ? $u->display_name : 'unknown'
    );

    $order->update_meta_data( '_picked_up_locally', '1' );
    $order->update_meta_data( '_picked_up_at',     current_time( 'mysql' ) );
    $changed = $order->update_status( 'completed', $note, true );
    if ( ! $changed ) {
        return array( 'success' => false, 'message' => __( 'Status konnte nicht aktualisiert werden.', 'werbeauf-customs' ) );
    }

    return array(
        'success' => true,
        'message' => sprintf(
            /* translators: %s order number */
            __( 'Bestellung %s als abgeholt markiert.', 'werbeauf-customs' ),
            $order->get_order_number()
        ),
    );
}

/* ----------------------------------------------------------
   MAILTO-URL BUILDER fuer contact_customer
---------------------------------------------------------- */

/**
 * Baut eine mailto:-URL fuer die Kundin der Bestellung mit
 * vor-ausgefuelltem Subject. Browser oeffnet den Email-Client
 * der Mitarbeiterin direkt.
 *
 * @param int $order_id
 * @return string
 */
function wa_sso_build_mailto_for_order( $order_id ) {
    $order_id = (int) $order_id;
    if ( $order_id <= 0 || ! function_exists( 'wc_get_order' ) ) {
        return admin_url();
    }
    $order = wc_get_order( $order_id );
    if ( ! $order ) {
        return admin_url();
    }
    $to = (string) $order->get_billing_email();
    if ( '' === $to || ! is_email( $to ) ) {
        // Kein Kunden-Email -- back to admin.
        return wa_sso_admin_order_url( $order_id );
    }

    $first   = (string) $order->get_billing_first_name();
    $last    = (string) $order->get_billing_last_name();
    $shop    = wp_specialchars_decode( (string) get_bloginfo( 'name' ) );
    $no      = $order->get_order_number();
    $subject = sprintf( __( 'Ihre Bestellung %s bei %s', 'werbeauf-customs' ), '#' . $no, $shop );
    $body    = $last !== ''
        ? sprintf( __( "Liebe Frau %s,\n\n", 'werbeauf-customs' ), $last )
        : __( "Liebe Kundin,\n\n", 'werbeauf-customs' );

    // mailto: per RFC 6068 -- subject + body als query params, RFC2396-encoded.
    $url = 'mailto:' . rawurlencode( $to )
         . '?subject=' . rawurlencode( $subject )
         . '&body=' . rawurlencode( $body );

    /**
     * Erlaubt downstream-Anpassungen (z.B. cc:, anderes Subject-Format).
     */
    return (string) apply_filters( 'wa_sso_contact_customer_mailto', $url, $order );
}

/* ----------------------------------------------------------
   CANCEL-ORDER HANDLER

   Erlaubt Stornierung NUR aus Status pending oder on-hold
   (unbezahlt). Bezahlte Bestellungen (processing/completed)
   benoetigen einen Refund, der Geldfluss umkehrt -- ein
   simples update_status("cancelled") wuerde nur den Status
   aendern und Phorest-Lager zurueckschreiben, aber das Geld
   bliebe beim Shop.
---------------------------------------------------------- */

/**
 * @param array $args      ['order_id' => int]
 * @param int   $user_id
 * @return array{success:bool, message:string}
 */
function wa_sso_handler_cancel_order( $args, $user_id ) {
    if ( ! function_exists( 'wc_get_order' ) ) {
        return array(
            'success' => false,
            'message' => __( 'WooCommerce ist nicht aktiv.', 'werbeauf-customs' ),
        );
    }
    $order_id = isset( $args['order_id'] ) ? (int) $args['order_id'] : 0;
    if ( $order_id <= 0 ) {
        return array(
            'success' => false,
            'message' => __( 'Keine Bestell-ID angegeben.', 'werbeauf-customs' ),
        );
    }
    $order = wc_get_order( $order_id );
    if ( ! $order ) {
        return array(
            'success' => false,
            'message' => __( 'Bestellung nicht gefunden.', 'werbeauf-customs' ),
        );
    }

    $status = $order->get_status();

    // Bereits storniert -> idempotent ok.
    if ( 'cancelled' === $status ) {
        return array(
            'success' => true,
            'message' => sprintf(
                /* translators: %s order number */
                __( 'Bestellung %s ist bereits storniert.', 'werbeauf-customs' ),
                $order->get_order_number()
            ),
        );
    }

    // Nur unbezahlte Stornos via SSO. Bezahlte Bestellungen brauchen
    // einen Refund. Wir blocken explizit damit kein Geld haengen bleibt.
    $cancellable = array( 'pending', 'on-hold' );
    if ( ! in_array( $status, $cancellable, true ) ) {
        $msg = sprintf(
            /* translators: 1: order number, 2: human-readable status name */
            __( 'Bestellung %1$s hat Status "%2$s" und kann hier nicht storniert werden. Bezahlte Bestellungen muessen ueber den WooCommerce-Refund-Flow zurueckerstattet werden -- bitte oeffnen Sie sie im WooCommerce-Admin.', 'werbeauf-customs' ),
            $order->get_order_number(),
            wc_get_order_status_name( $status )
        );
        return array( 'success' => false, 'message' => $msg );
    }

    $u    = get_userdata( (int) $user_id );
    $note = sprintf(
        /* translators: %s user display name */
        __( 'Storniert via SSO-Link von %s.', 'werbeauf-customs' ),
        $u ? $u->display_name : 'unknown'
    );

    $changed = $order->update_status( 'cancelled', $note, true );
    if ( ! $changed ) {
        return array(
            'success' => false,
            'message' => __( 'Stornierung konnte nicht gespeichert werden.', 'werbeauf-customs' ),
        );
    }

    return array(
        'success' => true,
        'message' => sprintf(
            /* translators: %s order number */
            __( 'Bestellung %s wurde storniert.', 'werbeauf-customs' ),
            $order->get_order_number()
        ),
    );
}

/* ----------------------------------------------------------
   GENERISCHER ORDER-STATUS HANDLER

   Wird von mark_processing + mark_completed wiederverwendet.
   Welcher Status gesetzt wird ergibt sich aus dem action_slug.
---------------------------------------------------------- */

/**
 * @param array $args        action_args aus dem Token, erwartet ['order_id' => int]
 * @param int   $user_id     der einloggende User
 * @param string $action_slug aktueller Slug ('mark_processing' | 'mark_completed' | ...)
 * @return array{success:bool, message:string, redirect?:string}
 */
function wa_sso_handler_set_order_status( $args, $user_id, $action_slug = '' ) {
    if ( ! function_exists( 'wc_get_order' ) ) {
        return array(
            'success' => false,
            'message' => __( 'WooCommerce ist nicht aktiv.', 'werbeauf-customs' ),
        );
    }
    $order_id = isset( $args['order_id'] ) ? (int) $args['order_id'] : 0;
    if ( $order_id <= 0 ) {
        return array(
            'success' => false,
            'message' => __( 'Keine Bestell-ID angegeben.', 'werbeauf-customs' ),
        );
    }
    $order = wc_get_order( $order_id );
    if ( ! $order ) {
        return array(
            'success' => false,
            'message' => __( 'Bestellung nicht gefunden.', 'werbeauf-customs' ),
        );
    }

    $map = array(
        'mark_processing' => 'processing',
        'mark_completed'  => 'completed',
    );
    if ( ! isset( $map[ $action_slug ] ) ) {
        return array(
            'success' => false,
            'message' => __( 'Unbekannte Statusaktion.', 'werbeauf-customs' ),
        );
    }
    $target_status = $map[ $action_slug ];

    if ( $order->get_status() === $target_status ) {
        return array(
            'success' => true,
            'message' => sprintf(
                /* translators: %s order number */
                __( 'Bestellung %s hat bereits den gewuenschten Status.', 'werbeauf-customs' ),
                $order->get_order_number()
            ),
        );
    }

    $note = sprintf(
        /* translators: 1: status label, 2: user display name */
        __( 'Status via SSO-Link auf "%1$s" gesetzt von %2$s.', 'werbeauf-customs' ),
        wc_get_order_status_name( $target_status ),
        ( $u = get_userdata( (int) $user_id ) ) ? $u->display_name : 'unknown'
    );

    $changed = $order->update_status( $target_status, $note, true );

    if ( ! $changed ) {
        return array(
            'success' => false,
            'message' => __( 'Status konnte nicht aktualisiert werden.', 'werbeauf-customs' ),
        );
    }

    // Downstream-Hook: erlaubt zusatzliche Logik nach Status-Aenderung
    // ohne den Handler zu modifizieren (z.B. Tracking-Nummer speichern).
    do_action( 'wa_sso_after_status_change', $order_id, $target_status, $args );

    return array(
        'success' => true,
        'message' => sprintf(
            /* translators: 1: order number, 2: target status */
            __( 'Bestellung %1$s ist jetzt "%2$s".', 'werbeauf-customs' ),
            $order->get_order_number(),
            wc_get_order_status_name( $target_status )
        ),
    );
}

/* ----------------------------------------------------------
   SUMMARY HELPERS

   Verwendet von der Confirm-Page um dem User Kontext zu geben,
   bevor er auf "Bestaetigen" klickt. "Bestellung #1234 von
   Max Mustermann, 89,90 EUR".
---------------------------------------------------------- */

/**
 * Kurze menschenlesbare Beschreibung einer Bestellung.
 *
 * @param int $order_id
 * @return string
 */
function wa_sso_order_summary( $order_id ) {
    if ( ! function_exists( 'wc_get_order' ) ) {
        return '';
    }
    $order = wc_get_order( (int) $order_id );
    if ( ! $order ) {
        return '';
    }
    $customer = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
    if ( '' === $customer ) {
        $customer = $order->get_billing_email();
    }
    $total_html = wp_strip_all_tags( wc_price( $order->get_total(), array( 'currency' => $order->get_currency() ) ) );

    return sprintf(
        /* translators: 1: order number, 2: customer name, 3: total */
        __( 'Bestellung %1$s von %2$s · %3$s', 'werbeauf-customs' ),
        $order->get_order_number(),
        $customer ? $customer : __( 'unbekannt', 'werbeauf-customs' ),
        $total_html
    );
}

/**
 * Liefert die admin-URL fuer eine Bestellung. HPOS-aware: bei
 * aktivem High-Performance Order Storage liegt die Edit-URL
 * unter admin.php?page=wc-orders, sonst unter post.php.
 *
 * @param int $order_id
 * @return string
 */
function wa_sso_admin_order_url( $order_id ) {
    $order_id = (int) $order_id;
    if ( $order_id <= 0 ) {
        return admin_url();
    }
    if (
        class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' )
        && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()
    ) {
        return admin_url( 'admin.php?page=wc-orders&action=edit&id=' . $order_id );
    }
    return admin_url( 'post.php?post=' . $order_id . '&action=edit' );
}

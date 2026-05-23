<?php
/* ============================================================
   DATEI: includes/sso/workflow.php
   ZWECK: Workflow-Engine fuer den Active Order System Plan.

   Erweitert das SSO-Modul um zeitgesteuerte Recovery + Reminder +
   Eskalations-Mails. Funktioniert auf zwei Achsen:

   1. RULES   (Konfiguration -- WP-Option)
      Pro Regel: trigger_status + after_minutes + repeat_minutes +
      max_repeats + channel (admin|customer) + tone (reminder|
      escalation|apology|recovery) + enabled flag.

   2. SCAN    (stuendlicher Cron-Job)
      Fuer jede aktive Regel werden Orders gesucht, die im
      trigger_status sind und das Throttling-Window erfuellen.
      Pro Treffer wird ein Template gerendert + per wp_mail
      versendet + in wp_wa_workflow_log protokolliert.

   3. LOG     (wp_wa_workflow_log)
      Ein Eintrag pro (Rule, Order, Sent-Zeitpunkt). Wird
      verwendet um max_repeats zu zaehlen, repeat_minutes
      durchzusetzen, und im Settings-UI die Aktivitaet zu
      zeigen.

   Pause-Toggle:
      Globaler Schalter "wa_workflow_paused" -- wenn an, laeuft
      der Scan zwar, sendet aber nichts. Use-Case: Klinik-Urlaub.

   Templates:
      Diese Datei nutzt eine Default-Renderer-Funktion. Batch D
      wird sie ablosen durch ein File-basiertes Template-System.
============================================================ */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ----------------------------------------------------------
   KONSTANTEN
---------------------------------------------------------- */

if ( ! defined( 'WA_WORKFLOW_DB_VERSION' ) ) {
    define( 'WA_WORKFLOW_DB_VERSION', '1.0.0' );
}
if ( ! defined( 'WA_WORKFLOW_CRON_HOOK' ) ) {
    define( 'WA_WORKFLOW_CRON_HOOK', 'wa_workflow_scan' );
}
if ( ! defined( 'WA_WORKFLOW_OPTION_RULES' ) ) {
    define( 'WA_WORKFLOW_OPTION_RULES', 'wa_workflow_rules' );
}
if ( ! defined( 'WA_WORKFLOW_OPTION_PAUSED' ) ) {
    define( 'WA_WORKFLOW_OPTION_PAUSED', 'wa_workflow_paused' );
}
if ( ! defined( 'WA_WORKFLOW_OPTION_DB_VERSION' ) ) {
    define( 'WA_WORKFLOW_OPTION_DB_VERSION', 'wa_workflow_db_version' );
}

/* ----------------------------------------------------------
   DEFAULT RULES (vor-konfiguriert basierend auf Master-Plan)

   Schema pro Regel:
     - enabled         bool
     - label           string  UI-Anzeige + Email-Subject-Bestandteil
     - description     string  fuer Settings-UI
     - trigger_status  string  WC-Status-Slug (ohne 'wc-' Prefix)
     - after_minutes   int     ab wann ein Order gilt
     - repeat_minutes  int     0 = einmalig; sonst Wiederholungs-Intervall
     - max_repeats     int     1 = einmalig
     - channel         string  'admin' (Mitarbeiterin) | 'customer'
     - tone            string  'reminder' | 'escalation' | 'apology' | 'recovery'
---------------------------------------------------------- */

function wa_workflow_default_rules() {
    return array(
        'processing_reminder' => array(
            'enabled'        => true,
            'label'          => __( 'Versand-Erinnerung', 'werbeauf-customs' ),
            'description'    => __( 'Erinnert die Mitarbeiterin, wenn eine bezahlte Bestellung nicht versendet wurde.', 'werbeauf-customs' ),
            'trigger_status' => 'processing',
            'after_minutes'  => 1440,   // 24h
            'repeat_minutes' => 1440,   // alle 24h
            'max_repeats'    => 5,
            'channel'        => 'admin',
            'tone'           => 'reminder',
        ),
        'processing_customer_apology' => array(
            'enabled'        => true,
            'label'          => __( 'Versand-Verzoegerung Entschuldigung', 'werbeauf-customs' ),
            'description'    => __( 'Sendet der Kundin eine Entschuldigung, wenn ihre Bestellung 3 Tage unversendet ist.', 'werbeauf-customs' ),
            'trigger_status' => 'processing',
            'after_minutes'  => 4320,   // 72h
            'repeat_minutes' => 0,
            'max_repeats'    => 1,
            'channel'        => 'customer',
            'tone'           => 'apology',
        ),
        'failed_customer_recovery' => array(
            'enabled'        => true,
            'label'          => __( 'Failed-Payment Recovery (Kundin)', 'werbeauf-customs' ),
            'description'    => __( 'Soft-Follow-up an die Kundin 2h nach Zahlungsfehlschlag.', 'werbeauf-customs' ),
            'trigger_status' => 'failed',
            'after_minutes'  => 120,    // 2h
            'repeat_minutes' => 0,
            'max_repeats'    => 1,
            'channel'        => 'customer',
            'tone'           => 'recovery',
        ),
        'failed_admin_reminder' => array(
            'enabled'        => true,
            'label'          => __( 'Failed-Payment Reminder (Mitarbeiterin)', 'werbeauf-customs' ),
            'description'    => __( 'Erinnert die Mitarbeiterin 24h nach Zahlungsfehlschlag.', 'werbeauf-customs' ),
            'trigger_status' => 'failed',
            'after_minutes'  => 1440,
            'repeat_minutes' => 0,
            'max_repeats'    => 1,
            'channel'        => 'admin',
            'tone'           => 'reminder',
        ),
        'failed_admin_escalation' => array(
            'enabled'        => true,
            'label'          => __( 'Failed-Payment Eskalation', 'werbeauf-customs' ),
            'description'    => __( 'Eskalation 72h nach Zahlungsfehlschlag — Entscheidung noetig.', 'werbeauf-customs' ),
            'trigger_status' => 'failed',
            'after_minutes'  => 4320,
            'repeat_minutes' => 0,
            'max_repeats'    => 1,
            'channel'        => 'admin',
            'tone'           => 'escalation',
        ),
        'completed_review_request' => array(
            'enabled'        => false,  // Q5: ship default-aus, aktivieren wenn drperi bereit ist
            'label'          => __( 'Review-Anfrage 14 Tage nach Versand', 'werbeauf-customs' ),
            'description'    => __( 'Fragt die Kundin nach einer Produktbewertung. Wirkt am besten wenn die Bestellung wirklich angekommen + ausprobiert wurde.', 'werbeauf-customs' ),
            'trigger_status' => 'completed',
            'after_minutes'  => 20160,  // 14 Tage
            'repeat_minutes' => 0,
            'max_repeats'    => 1,
            'channel'        => 'customer',
            'tone'           => 'review',
            'template_slug'  => 'customer-review-request',
        ),
        'pending_customer_recovery' => array(
            'enabled'        => false,  // Q3: default-aus
            'label'          => __( 'Pending-Order Recovery (Kundin)', 'werbeauf-customs' ),
            'description'    => __( 'Erinnert die Kundin an die unbezahlte Bestellung (kann als aggressiv wahrgenommen werden).', 'werbeauf-customs' ),
            'trigger_status' => 'pending',
            'after_minutes'  => 30,
            'repeat_minutes' => 1440,
            'max_repeats'    => 1,
            'channel'        => 'customer',
            'tone'           => 'recovery',
            // Eigenes Template: anderer Tonfall als failed-payment recovery
            // (Cart-Erinnerung vs. Zahlung-Fehlschlag-Hilfe).
            'template_slug'  => 'customer-pending-recovery',
        ),
    );
}

/**
 * Liefert die aktuelle Rules-Konfiguration -- gemerged aus
 * Option + Defaults, damit neue Regeln aus dem Code automatisch
 * sichtbar werden.
 *
 * @return array<string, array<string, mixed>>
 */
function wa_workflow_get_rules() {
    $stored   = get_option( WA_WORKFLOW_OPTION_RULES, array() );
    $defaults = wa_workflow_default_rules();
    if ( ! is_array( $stored ) ) {
        return $defaults;
    }
    // Merge per-rule: gespeicherte Werte ueberschreiben Defaults.
    $merged = $defaults;
    foreach ( $stored as $slug => $rule ) {
        if ( ! is_array( $rule ) ) {
            continue;
        }
        if ( ! isset( $merged[ $slug ] ) ) {
            // Unknown rule (vielleicht zukuenftig entfernt) -- ignorieren.
            continue;
        }
        $merged[ $slug ] = array_merge( $merged[ $slug ], $rule );
    }
    return $merged;
}

/**
 * Speichert nur die mutierbaren Felder einer Regel
 * (enabled, after_minutes, repeat_minutes, max_repeats).
 * Labels + tone + trigger_status bleiben in den Defaults.
 *
 * @param string $slug
 * @param array  $new_values
 * @return bool
 */
function wa_workflow_save_rule( $slug, array $new_values ) {
    $defaults = wa_workflow_default_rules();
    if ( ! isset( $defaults[ $slug ] ) ) {
        return false;
    }
    $allowed = array( 'enabled', 'after_minutes', 'repeat_minutes', 'max_repeats' );
    $clean   = array();
    foreach ( $allowed as $k ) {
        if ( ! array_key_exists( $k, $new_values ) ) {
            continue;
        }
        if ( 'enabled' === $k ) {
            $clean[ $k ] = (bool) $new_values[ $k ];
        } else {
            $clean[ $k ] = max( 0, (int) $new_values[ $k ] );
        }
    }
    $stored          = get_option( WA_WORKFLOW_OPTION_RULES, array() );
    $stored          = is_array( $stored ) ? $stored : array();
    $stored[ $slug ] = array_merge( isset( $stored[ $slug ] ) ? (array) $stored[ $slug ] : array(), $clean );
    return (bool) update_option( WA_WORKFLOW_OPTION_RULES, $stored, false );
}

/**
 * Master-Pause: laeuft der Workflow ueberhaupt? Im Urlaubs-
 * Modus soll der Cron nichts senden.
 *
 * @return bool
 */
function wa_workflow_is_paused() {
    return (bool) get_option( WA_WORKFLOW_OPTION_PAUSED, false );
}

/* ----------------------------------------------------------
   LOG-TABELLE
---------------------------------------------------------- */

function wa_workflow_log_table_name() {
    global $wpdb;
    return $wpdb->prefix . 'wa_workflow_log';
}

function wa_workflow_log_install() {
    global $wpdb;
    $table   = wa_workflow_log_table_name();
    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE {$table} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        order_id BIGINT UNSIGNED NOT NULL,
        rule_slug VARCHAR(64) NOT NULL,
        sent_at DATETIME NOT NULL,
        channel VARCHAR(16) NOT NULL DEFAULT 'admin',
        recipient VARCHAR(255) NULL,
        send_status VARCHAR(16) NOT NULL DEFAULT 'sent',
        send_error TEXT NULL,
        PRIMARY KEY  (id),
        KEY order_id (order_id),
        KEY rule_slug (rule_slug),
        KEY sent_at (sent_at)
    ) {$charset};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );
}

/**
 * Liest den juengsten Log-Eintrag fuer ein (Order, Rule)-Paar.
 *
 * @param int    $order_id
 * @param string $rule_slug
 * @return array|null
 */
function wa_workflow_log_last( $order_id, $rule_slug ) {
    global $wpdb;
    $row = $wpdb->get_row(
        $wpdb->prepare(
            'SELECT id, sent_at, channel, recipient, send_status, send_error
             FROM ' . wa_workflow_log_table_name() . '
             WHERE order_id = %d AND rule_slug = %s
             ORDER BY sent_at DESC
             LIMIT 1',
            (int) $order_id,
            $rule_slug
        ),
        ARRAY_A
    );
    return $row ? $row : null;
}

/**
 * Zaehlt SENT-Eintraege fuer ein (Order, Rule)-Paar -- ignoriert
 * Fehlschlaege.
 */
function wa_workflow_log_count_sent( $order_id, $rule_slug ) {
    global $wpdb;
    return (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM " . wa_workflow_log_table_name() . "
             WHERE order_id = %d AND rule_slug = %s AND send_status = 'sent'",
            (int) $order_id,
            $rule_slug
        )
    );
}

function wa_workflow_log_insert( $order_id, $rule_slug, $channel, $recipient, $status, $error = null ) {
    global $wpdb;
    return (bool) $wpdb->insert(
        wa_workflow_log_table_name(),
        array(
            'order_id'    => (int) $order_id,
            'rule_slug'   => $rule_slug,
            'sent_at'     => gmdate( 'Y-m-d H:i:s' ),
            'channel'     => $channel,
            'recipient'   => $recipient ? substr( (string) $recipient, 0, 255 ) : null,
            'send_status' => $status,
            'send_error'  => $error ? (string) $error : null,
        ),
        array( '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
    );
}

function wa_workflow_log_recent( $limit = 50 ) {
    global $wpdb;
    $limit = max( 1, min( 500, (int) $limit ) );
    $rows  = $wpdb->get_results(
        $wpdb->prepare(
            'SELECT id, order_id, rule_slug, sent_at, channel, recipient, send_status, send_error
             FROM ' . wa_workflow_log_table_name() . '
             ORDER BY sent_at DESC
             LIMIT %d',
            $limit
        ),
        ARRAY_A
    );
    return is_array( $rows ) ? $rows : array();
}

/* ----------------------------------------------------------
   SELF-INSTALL (init priority 6, nach SSO @5).
---------------------------------------------------------- */

add_action( 'init', 'wa_workflow_maybe_install', 6 );

function wa_workflow_maybe_install() {
    if ( get_option( WA_WORKFLOW_OPTION_DB_VERSION ) === WA_WORKFLOW_DB_VERSION ) {
        return;
    }
    wa_workflow_log_install();
    update_option( WA_WORKFLOW_OPTION_DB_VERSION, WA_WORKFLOW_DB_VERSION, false );
}

/* ----------------------------------------------------------
   CRON-REGISTRIERUNG
---------------------------------------------------------- */

add_action( 'wp', 'wa_workflow_cron_schedule' );

function wa_workflow_cron_schedule() {
    if ( ! wp_next_scheduled( WA_WORKFLOW_CRON_HOOK ) ) {
        wp_schedule_event( time() + 10 * MINUTE_IN_SECONDS, 'hourly', WA_WORKFLOW_CRON_HOOK );
    }
}

add_action( WA_WORKFLOW_CRON_HOOK, 'wa_workflow_scan_now' );

/* ----------------------------------------------------------
   SCAN-LOOP (Cron-Target + manuell aufrufbar)

   Returns ein Result-Array fuer Smoke-Tests + Admin-UI:
     [
       'paused'   => bool,
       'rules'    => [ rule_slug => [ matched_orders => int, sent => int, errors => int ] ],
       'total_sent' => int,
       'total_err'  => int,
     ]
---------------------------------------------------------- */

function wa_workflow_scan_now() {
    $result = array(
        'paused'     => false,
        'rules'      => array(),
        'total_sent' => 0,
        'total_err'  => 0,
    );

    if ( ! wa_sso_is_enabled() ) {
        // Workflow ist Teil des SSO-Moduls -- wenn SSO aus, kein Scan.
        return $result;
    }
    if ( wa_workflow_is_paused() ) {
        $result['paused'] = true;
        return $result;
    }
    if ( ! function_exists( 'wc_get_orders' ) ) {
        return $result;
    }

    $rules = wa_workflow_get_rules();

    foreach ( $rules as $slug => $rule ) {
        $stats = array( 'matched_orders' => 0, 'sent' => 0, 'errors' => 0 );

        if ( empty( $rule['enabled'] ) ) {
            $result['rules'][ $slug ] = $stats;
            continue;
        }

        // Cutoff: Orders die mindestens "after_minutes" alt sind.
        $cutoff_ts        = time() - max( 1, (int) $rule['after_minutes'] ) * MINUTE_IN_SECONDS;
        $hard_lower_ts    = time() - 30 * DAY_IN_SECONDS; // hard upper bound auf 30 Tage zurueck

        // wc_get_orders mit Status-Filter; Age-Check in PHP (date_modified-Range
        // im Query-Builder ist version-fragil zwischen HPOS und CPT-Storage).
        $orders = wc_get_orders( array(
            'status' => $rule['trigger_status'],
            'limit'  => 200, // groesseres Limit, weil wir noch filtern
            'return' => 'objects',
        ) );

        if ( empty( $orders ) || ! is_array( $orders ) ) {
            $result['rules'][ $slug ] = $stats;
            continue;
        }

        foreach ( $orders as $order ) {
            if ( ! is_object( $order ) || ! method_exists( $order, 'get_id' ) ) {
                continue;
            }

            // Age-Check: muss zwischen hard_lower (30d zurueck) und cutoff (after_minutes alt) liegen.
            $modified_ts = 0;
            $dm = $order->get_date_modified();
            if ( $dm && method_exists( $dm, 'getTimestamp' ) ) {
                $modified_ts = (int) $dm->getTimestamp();
            }
            if ( $modified_ts <= 0 ) {
                continue;
            }
            if ( $modified_ts > $cutoff_ts ) {
                continue; // zu jung -- noch nicht ueberfaellig
            }
            if ( $modified_ts < $hard_lower_ts ) {
                continue; // zu alt -- ueber 30 Tage, wir lassen die liegen
            }

            $stats['matched_orders']++;

            // Throttling: max_repeats nicht ueberschreiten.
            $sent_count = wa_workflow_log_count_sent( $order->get_id(), $slug );
            if ( $sent_count >= (int) $rule['max_repeats'] ) {
                continue;
            }
            // repeat_minutes-Throttle.
            $last = wa_workflow_log_last( $order->get_id(), $slug );
            if ( $last && (int) $rule['repeat_minutes'] > 0 ) {
                $last_ts = strtotime( $last['sent_at'] . ' UTC' );
                if ( $last_ts && ( time() - $last_ts ) < (int) $rule['repeat_minutes'] * MINUTE_IN_SECONDS ) {
                    continue;
                }
            } elseif ( $last && (int) $rule['repeat_minutes'] === 0 ) {
                // einmalig + es gibt schon einen Eintrag -> skip.
                continue;
            }

            $send = wa_workflow_dispatch( $order, $slug, $rule );
            if ( $send['ok'] ) {
                $stats['sent']++;
                $result['total_sent']++;
            } else {
                $stats['errors']++;
                $result['total_err']++;
            }
        }

        $result['rules'][ $slug ] = $stats;
    }

    do_action( 'wa_workflow_scan_complete', $result );

    return $result;
}

/* ----------------------------------------------------------
   DISPATCH (rendert + sendet eine einzelne Workflow-Mail)
---------------------------------------------------------- */

/**
 * @param WC_Order $order
 * @param string   $rule_slug
 * @param array    $rule
 * @return array{ok:bool, recipient:string, error:string|null}
 */
function wa_workflow_dispatch( $order, $rule_slug, array $rule ) {
    $channel   = isset( $rule['channel'] ) ? (string) $rule['channel'] : 'admin';
    $recipient = wa_workflow_resolve_recipient( $order, $channel );

    if ( '' === $recipient ) {
        wa_workflow_log_insert( $order->get_id(), $rule_slug, $channel, null, 'error', 'no recipient' );
        return array( 'ok' => false, 'recipient' => '', 'error' => 'no recipient' );
    }

    $render = wa_workflow_render( $order, $rule_slug, $rule, $channel );
    if ( ! is_array( $render ) || empty( $render['subject'] ) || empty( $render['html'] ) ) {
        wa_workflow_log_insert( $order->get_id(), $rule_slug, $channel, $recipient, 'error', 'render failed' );
        return array( 'ok' => false, 'recipient' => $recipient, 'error' => 'render failed' );
    }

    $headers = array( 'Content-Type: text/html; charset=UTF-8' );

    /**
     * Hook fuer letzte Anpassungen vor dem Versand.
     */
    $render = apply_filters( 'wa_workflow_pre_send', $render, $order, $rule_slug, $rule, $channel );

    $sent = wp_mail( $recipient, $render['subject'], $render['html'], $headers );

    if ( $sent ) {
        wa_workflow_log_insert( $order->get_id(), $rule_slug, $channel, $recipient, 'sent' );
        do_action( 'wa_workflow_sent', $order->get_id(), $rule_slug, $channel, $recipient );
        return array( 'ok' => true, 'recipient' => $recipient, 'error' => null );
    }

    wa_workflow_log_insert( $order->get_id(), $rule_slug, $channel, $recipient, 'error', 'wp_mail returned false' );
    return array( 'ok' => false, 'recipient' => $recipient, 'error' => 'wp_mail returned false' );
}

/**
 * Findet die Ziel-Email-Adresse fuer eine Regel.
 *
 * @param WC_Order $order
 * @param string   $channel 'admin' | 'customer'
 * @return string  '' wenn keine plausible Adresse.
 */
function wa_workflow_resolve_recipient( $order, $channel ) {
    if ( 'customer' === $channel ) {
        $email = (string) $order->get_billing_email();
        return is_email( $email ) ? $email : '';
    }

    // admin: nimm den WC-Email-New-Order-Recipient (kann Komma-Liste sein).
    $opt    = get_option( 'woocommerce_new_order_settings', array() );
    $recips = isset( $opt['recipient'] ) ? (string) $opt['recipient'] : '';
    if ( '' === $recips ) {
        return (string) get_option( 'admin_email' );
    }
    return $recips;
}

/* ----------------------------------------------------------
   BRAND-ASSETS

   Liefert Logo + Farben + Kontakt aus dem Footer-ACF-Group
   (oder Fallbacks). Filterbar via wa_workflow_brand_assets.
---------------------------------------------------------- */

/**
 * @return array<string, string>
 */
function wa_workflow_brand_assets() {
    $logo_url = '';
    $et_divi  = get_option( 'et_divi' );
    if ( is_array( $et_divi ) && ! empty( $et_divi['divi_logo'] ) ) {
        $logo_url = (string) $et_divi['divi_logo'];
    }

    // ACF-Footer-Helper (drperi-spezifisch, WPML-aware).
    $address = '';
    $phone   = '';
    $email   = '';
    if ( function_exists( 'wa_get_options_field' ) ) {
        $address = (string) wa_get_options_field( 'footer', 'address', '' );
        $phone   = (string) wa_get_options_field( 'footer', 'phone', '' );
        $email   = (string) wa_get_options_field( 'footer', 'email', '' );
    }
    if ( '' === $email ) {
        $email = (string) get_option( 'admin_email' );
    }

    // Brand-Farben aus den CSS-Tokens (00-base/tokens.css).
    $primary   = '#475e76'; // --color-accent
    $secondary = '#769cc1'; // --color-accent-2

    $assets = array(
        'site_name' => (string) get_bloginfo( 'name' ),
        'site_url'  => (string) home_url( '/' ),
        'logo_url'  => $logo_url,
        'primary'   => $primary,
        'secondary' => $secondary,
        'address'   => $address,
        'phone'     => $phone,
        'email'     => $email,
    );

    /**
     * Allow overriding brand assets (logo, colors, contact) for
     * Workflow-Emails. Useful when extracting this module to a
     * standalone plugin on a non-drperi site.
     */
    return (array) apply_filters( 'wa_workflow_brand_assets', $assets );
}

/* ----------------------------------------------------------
   TEMPLATE-LOADER

   Laedt eine PHP-Datei aus includes/sso/email-templates/ mit
   Variablen-Injektion via extract(). Fehlt das Template, wird
   ein leerer String zurueckgegeben -- der Renderer faellt dann
   auf die inline-HTML-Variante zurueck (siehe wa_workflow_render_*).
---------------------------------------------------------- */

/**
 * @param string $slug  Template-Slug (ohne .php) -- sanitize_file_name
 *                      schuetzt vor Directory-Traversal.
 * @param array  $vars  Variablen die ins Template injiziert werden.
 * @return string Gerendertes HTML oder leer bei Fehler.
 */
function wa_workflow_render_template( $slug, array $vars ) {
    $slug = sanitize_file_name( (string) $slug );
    if ( '' === $slug || strpos( $slug, '_' ) === 0 || strpos( $slug, '..' ) !== false ) {
        return '';
    }
    $file = WERBEAUF_PLUGIN_PATH . 'includes/sso/email-templates/' . $slug . '.php';
    if ( ! file_exists( $file ) ) {
        return '';
    }

    /**
     * Erlaubt downstream-Modulen die Template-Variablen kurz
     * vor dem Render anzupassen (z.B. zusaetzliche Felder).
     */
    $vars = (array) apply_filters( 'wa_workflow_template_vars', $vars, $slug );

    // Brand-Assets immer mitgeben.
    if ( ! isset( $vars['brand'] ) ) {
        $vars['brand'] = wa_workflow_brand_assets();
    }

    ob_start();
    // phpcs:disable WordPress.PHP.DontExtract.extract_extract
    extract( $vars, EXTR_SKIP );
    // phpcs:enable
    include $file;
    return (string) ob_get_clean();
}

/* ----------------------------------------------------------
   RENDER-DISPATCH

   Versucht zuerst ein File-Template; wenn fehlt, fallback auf
   die inline-Variante (definiert weiter unten).
---------------------------------------------------------- */

function wa_workflow_render( $order, $rule_slug, array $rule, $channel ) {
    $tone     = isset( $rule['tone'] ) ? (string) $rule['tone'] : 'reminder';
    $label    = isset( $rule['label'] ) ? (string) $rule['label'] : ucfirst( $rule_slug );
    $order_no = $order->get_order_number();
    $first    = $order->get_billing_first_name();
    $last     = $order->get_billing_last_name();
    $cust     = trim( $first . ' ' . $last );

    // Template-Slug-Resolution:
    //   1. Regel-spezifisch (rule['template_slug']) ueberschreibt
    //   2. Sonst auto-compose aus channel-tone
    // Dispatcher faellt auf inline-Renderer zurueck wenn beides fehlt.
    $template_slug = ! empty( $rule['template_slug'] )
        ? (string) $rule['template_slug']
        : $channel . '-' . $tone;
    $brand         = wa_workflow_brand_assets();

    // Gemeinsame Variablen.
    $vars = array(
        'brand'      => $brand,
        'order'      => $order,
        'rule'       => $rule,
        'rule_slug'  => $rule_slug,
        'cust_name'  => $cust,
        'order_no'   => $order_no,
    );

    if ( 'customer' === $channel ) {
        $greeting = $last !== ''
            ? sprintf( __( 'Liebe Frau %s,', 'werbeauf-customs' ), $last )
            : __( 'Liebe Kundin,', 'werbeauf-customs' );
        $vars['greeting']  = $greeting;
        $vars['hours_ago'] = wa_workflow_order_age_hours( $order );
        $vars['age_hours'] = $vars['hours_ago'];
        if ( 'recovery' === $tone ) {
            $vars['retry_url'] = $order->get_checkout_payment_url();
        }
        $vars['view_url'] = $order->get_view_order_url();

        // Phase H · Review-Request-Variablen
        if ( 'review' === $tone ) {
            $default_review_url = $order->get_view_order_url();
            /**
             * Override the review-CTA URL. Default: WC My-Account view-order
             * page. Use this filter to point at Trustpilot / Google Reviews /
             * a custom review-collection page.
             *
             * @param string   $default_url
             * @param WC_Order $order
             */
            $vars['review_url']    = (string) apply_filters( 'wa_sso_review_request_url', $default_review_url, $order );
            $vars['product_count'] = (int) count( $order->get_items() );
        }

        $html = wa_workflow_render_template( $template_slug, $vars );
        if ( '' !== $html ) {
            return array(
                'subject' => wa_workflow_subject_for( $channel, $tone, $order, $rule_slug ),
                'html'    => $html,
            );
        }
        return wa_workflow_render_customer( $order, $rule_slug, $rule, $tone, $cust );
    }

    // ADMIN
    $vars['age_hours']    = wa_workflow_order_age_hours( $order );
    $vars['status_label'] = wc_get_order_status_name( $order->get_status() );
    $vars['order_total']  = wp_strip_all_tags( wc_price( $order->get_total(), array( 'currency' => $order->get_currency() ) ) );
    $vars['admin_url']    = function_exists( 'wa_sso_admin_order_url' )
        ? wa_sso_admin_order_url( $order->get_id() )
        : admin_url();

    // SSO-Button "Bestellung ansehen" wenn Recipient zu einem WP-User mappt.
    $vars['sso_button'] = '';
    $user_id = function_exists( 'wa_sso_first_recipient_user_id' )
        ? wa_sso_first_recipient_user_id( wa_workflow_resolve_recipient( $order, 'admin' ) )
        : 0;
    if ( $user_id && function_exists( 'wa_sso_action_url' ) && function_exists( 'wa_sso_button' ) ) {
        $url = wa_sso_action_url( $user_id, 'view_order', array( 'order_id' => $order->get_id() ) );
        if ( ! is_wp_error( $url ) ) {
            $vars['sso_button'] = wa_sso_button( $url, __( 'Bestellung ansehen (Einmal-Login)', 'werbeauf-customs' ), $brand['primary'] );
        }
    }

    $html = wa_workflow_render_template( $template_slug, $vars );
    if ( '' !== $html ) {
        return array(
            'subject' => wa_workflow_subject_for( $channel, $tone, $order, $rule_slug ),
            'html'    => $html,
        );
    }
    // Fallback auf inline-Renderer.
    return wa_workflow_render_admin( $order, $rule_slug, $rule, $tone, $label, $order_no, $cust );
}

/**
 * Subject-Builder fuer Workflow-Emails. Pro (channel, tone, rule_slug).
 * Rule-spezifische Overrides kommen vor channel/tone-Defaults.
 *
 * @param string   $channel
 * @param string   $tone
 * @param WC_Order $order
 * @param string   $rule_slug Optional, fuer rule-spezifische Subjects.
 * @return string
 */
function wa_workflow_subject_for( $channel, $tone, $order, $rule_slug = '' ) {
    $no = $order->get_order_number();
    $site = wp_specialchars_decode( (string) get_bloginfo( 'name' ) );

    // Rule-spezifische Subject-Overrides.
    switch ( $rule_slug ) {
        case 'pending_customer_recovery':
            return sprintf( __( 'Ihre Bestellung #%s wartet auf Sie — %s', 'werbeauf-customs' ), $no, $site );
        case 'processing_customer_apology':
            return sprintf( __( 'Entschuldigung wegen Verzögerung Ihrer Bestellung #%s — %s', 'werbeauf-customs' ), $no, $site );
        case 'failed_customer_recovery':
            return sprintf( __( 'Brauchen Sie Hilfe bei Ihrer Bestellung #%s? — %s', 'werbeauf-customs' ), $no, $site );
        case 'completed_review_request':
            return sprintf( __( 'Wie gefallen Ihnen Ihre Produkte von %s?', 'werbeauf-customs' ), $site );
    }

    if ( 'customer' === $channel ) {
        switch ( $tone ) {
            case 'recovery':
                return sprintf( __( 'Brauchen Sie Hilfe bei Ihrer Bestellung #%s? — %s', 'werbeauf-customs' ), $no, $site );
            case 'apology':
                return sprintf( __( 'Entschuldigung wegen Verzögerung Ihrer Bestellung #%s — %s', 'werbeauf-customs' ), $no, $site );
        }
        return sprintf( __( 'Update zu Ihrer Bestellung #%s — %s', 'werbeauf-customs' ), $no, $site );
    }

    // admin
    switch ( $tone ) {
        case 'escalation':
            return sprintf( __( '[%s] DRINGEND: Bestellung #%s — Entscheidung nötig', 'werbeauf-customs' ), $site, $no );
        case 'apology':
            return sprintf( __( '[%s] Erinnerung: Kundin wurde wegen Verzögerung #%s informiert', 'werbeauf-customs' ), $site, $no );
        case 'recovery':
        case 'reminder':
        default:
            return sprintf( __( '[%s] Erinnerung: Bestellung #%s wartet', 'werbeauf-customs' ), $site, $no );
    }
}

function wa_workflow_render_admin( $order, $rule_slug, array $rule, $tone, $label, $order_no, $cust ) {
    $icon = array(
        'reminder'   => '🔔',
        'escalation' => '🚨',
        'apology'    => '⚠️',
        'recovery'   => 'ℹ️',
    );
    $emoji   = $icon[ $tone ] ?? '🔔';
    $age_h   = wa_workflow_order_age_hours( $order );
    $subject = sprintf( '%s %s #%s (%s) — seit %dh im Status', $emoji, $label, $order_no, $cust ?: __( 'unbekannt', 'werbeauf-customs' ), $age_h );

    $intro = sprintf(
        /* translators: 1: order number 2: hours */
        __( 'Bestellung %1$s wartet seit %2$d Stunden im Status "%3$s". Bitte handeln Sie.', 'werbeauf-customs' ),
        $order_no,
        $age_h,
        wc_get_order_status_name( $order->get_status() )
    );

    $admin_url = function_exists( 'wa_sso_admin_order_url' )
        ? wa_sso_admin_order_url( $order->get_id() )
        : admin_url();

    // SSO-Button "Bestellung ansehen" -- nur wenn der Recipient zu einem User mappt.
    $user_id    = function_exists( 'wa_sso_first_recipient_user_id' )
        ? wa_sso_first_recipient_user_id( wa_workflow_resolve_recipient( $order, 'admin' ) )
        : 0;
    $sso_button = '';
    if ( $user_id && function_exists( 'wa_sso_action_url' ) && function_exists( 'wa_sso_button' ) ) {
        $url = wa_sso_action_url( $user_id, 'view_order', array( 'order_id' => $order->get_id() ) );
        if ( ! is_wp_error( $url ) ) {
            $sso_button = wa_sso_button( $url, __( 'Bestellung ansehen (SSO-Login)', 'werbeauf-customs' ), '#0073aa' );
        }
    }

    $font = '-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif';

    $html  = '<div style="font-family:' . esc_attr( $font ) . ';font-size:14px;color:#1d2327;max-width:600px;">';
    $html .= '<h2 style="margin:0 0 16px;font-size:18px;">' . esc_html( $emoji . ' ' . $label ) . '</h2>';
    $html .= '<p style="margin:0 0 16px;line-height:1.5;">' . esc_html( $intro ) . '</p>';
    $html .= '<p style="margin:0 0 8px;"><strong>' . esc_html__( 'Bestellung:', 'werbeauf-customs' ) . '</strong> ' . esc_html( '#' . $order_no );
    if ( $cust ) {
        $html .= ' — ' . esc_html( $cust );
    }
    $html .= '</p>';
    $html .= '<p style="margin:0 0 8px;"><strong>' . esc_html__( 'Aktueller Status:', 'werbeauf-customs' ) . '</strong> '
          .  esc_html( wc_get_order_status_name( $order->get_status() ) ) . '</p>';
    $html .= '<p style="margin:0 0 24px;"><strong>' . esc_html__( 'Summe:', 'werbeauf-customs' ) . '</strong> '
          .  esc_html( wp_strip_all_tags( wc_price( $order->get_total(), array( 'currency' => $order->get_currency() ) ) ) ) . '</p>';
    if ( $sso_button ) {
        $html .= '<p style="margin:0 0 16px;">' . $sso_button . '</p>';
    } else {
        $html .= '<p style="margin:0 0 16px;"><a href="' . esc_url( $admin_url ) . '" style="color:#0073aa;">'
              .  esc_html__( 'Im WP-Admin oeffnen', 'werbeauf-customs' ) . '</a></p>';
    }
    $html .= '<p style="margin:24px 0 0;font-size:12px;color:#646970;">'
          .  esc_html__( 'Diese Email kommt vom Active Order System (werbeauf-customs).', 'werbeauf-customs' )
          .  '</p>';
    $html .= '</div>';

    return array(
        'subject' => $subject,
        'html'    => $html,
    );
}

function wa_workflow_render_customer( $order, $rule_slug, array $rule, $tone, $cust ) {
    $shop_name = get_bloginfo( 'name' );

    $tone_map = array(
        'recovery' => array(
            'subject' => sprintf( __( 'Brauchen Sie Hilfe bei Ihrer Bestellung #%s?', 'werbeauf-customs' ), $order->get_order_number() ),
            'lead'    => __( 'wir haben gesehen, dass die Bezahlung Ihrer Bestellung nicht erfolgreich war. So koennen Sie weitermachen:', 'werbeauf-customs' ),
            'cta'     => __( 'Hier erneut bezahlen', 'werbeauf-customs' ),
            'cta_bg'  => '#daa520',
        ),
        'apology'  => array(
            'subject' => sprintf( __( 'Entschuldigen Sie die Verzoegerung Ihrer Bestellung #%s', 'werbeauf-customs' ), $order->get_order_number() ),
            'lead'    => __( 'Ihre Bestellung wird in Kuerze verschickt. Wir entschuldigen uns fuer die Verzoegerung.', 'werbeauf-customs' ),
            'cta'     => __( 'Bestellung ansehen', 'werbeauf-customs' ),
            'cta_bg'  => '#0073aa',
        ),
        'reminder' => array(
            'subject' => sprintf( __( 'Erinnerung zu Ihrer Bestellung #%s', 'werbeauf-customs' ), $order->get_order_number() ),
            'lead'    => __( 'Wir wollten Sie nur kurz an Ihre Bestellung erinnern.', 'werbeauf-customs' ),
            'cta'     => __( 'Bestellung ansehen', 'werbeauf-customs' ),
            'cta_bg'  => '#0073aa',
        ),
    );
    $t = $tone_map[ $tone ] ?? $tone_map['reminder'];

    $greeting = $cust !== '' ? sprintf( __( 'Liebe Frau %s,', 'werbeauf-customs' ), $cust ) : __( 'Liebe Kundin,', 'werbeauf-customs' );

    // Retry/View URL je nach Tone.
    if ( 'recovery' === $tone && function_exists( 'wc_get_endpoint_url' ) ) {
        $retry_url = $order->get_checkout_payment_url();
    } else {
        $retry_url = $order->get_view_order_url();
    }

    $font = '-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif';

    $html  = '<div style="font-family:' . esc_attr( $font ) . ';font-size:15px;color:#1d2327;max-width:600px;line-height:1.5;">';
    $html .= '<p style="margin:0 0 16px;">' . esc_html( $greeting ) . '</p>';
    $html .= '<p style="margin:0 0 16px;">' . esc_html( $t['lead'] ) . '</p>';
    $html .= '<p style="margin:24px 0;"><a href="' . esc_url( $retry_url ) . '" style="display:inline-block;padding:14px 28px;background:' . esc_attr( $t['cta_bg'] ) . ';color:#fff;border-radius:6px;text-decoration:none;font-weight:600;">'
          .  esc_html( $t['cta'] ) . '</a></p>';
    $html .= '<p style="margin:0 0 8px;">' . esc_html__( 'Sie koennen auch direkt antworten, wenn Sie Fragen haben.', 'werbeauf-customs' ) . '</p>';
    $html .= '<p style="margin:24px 0 0;">' . esc_html__( 'Herzliche Gruesse', 'werbeauf-customs' ) . '<br>'
          .  esc_html__( 'Ihr ', 'werbeauf-customs' ) . esc_html( $shop_name ) . esc_html__( ' Team', 'werbeauf-customs' ) . '</p>';
    $html .= '</div>';

    return array(
        'subject' => $t['subject'],
        'html'    => $html,
    );
}

/* ----------------------------------------------------------
   HELPERS
---------------------------------------------------------- */

function wa_workflow_order_age_hours( $order ) {
    $d = $order->get_date_modified();
    if ( ! $d || ! method_exists( $d, 'getTimestamp' ) ) {
        return 0;
    }
    return max( 0, (int) floor( ( time() - $d->getTimestamp() ) / HOUR_IN_SECONDS ) );
}

/**
 * Wenn der Order-Status sich aendert: relevante Workflow-Log-Eintraege
 * loeschen, damit wiederkehrende Reminders nicht weiterlaufen.
 *
 * Z.B. wenn die Mitarbeiterin endlich versendet -> status ist nicht
 * mehr 'processing' -> wir wollen keine "wartet seit 5 Tagen"-Mail
 * mehr.
 */
add_action( 'woocommerce_order_status_changed', 'wa_workflow_clear_on_status_change', 50, 4 );

function wa_workflow_clear_on_status_change( $order_id, $old_status, $new_status, $order = null ) {
    global $wpdb;
    // Wir loeschen NUR die Logs der Regeln, deren trigger_status nicht mehr matcht.
    $rules = wa_workflow_get_rules();
    $slugs_to_clear = array();
    foreach ( $rules as $slug => $rule ) {
        if ( $rule['trigger_status'] !== $new_status ) {
            $slugs_to_clear[] = $slug;
        }
    }
    if ( empty( $slugs_to_clear ) ) {
        return;
    }
    $placeholders = implode( ',', array_fill( 0, count( $slugs_to_clear ), '%s' ) );
    $params       = array_merge( array( (int) $order_id ), $slugs_to_clear );
    $wpdb->query(
        $wpdb->prepare(
            'DELETE FROM ' . wa_workflow_log_table_name() . ' WHERE order_id = %d AND rule_slug IN (' . $placeholders . ')',
            $params
        )
    );
}

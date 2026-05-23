<?php
/* ============================================================
   DATEI: includes/sso/tokens.php
   ZWECK: Token-Tabelle + CRUD. Tokens werden gehasht
          (SHA-256) gespeichert, nicht raw. Konstanten-Zeit-
          Vergleich via hash_equals().

   Lifecycle eines Tokens:
     1. wa_sso_create_token() -> raw token zurueck, gehasht in DB
     2. wa_sso_validate_token( raw ) -> Row mit user_id/action/args
     3. wa_sso_consume_token( raw ) -> markiert consumed_at, NOT-loescht
     4. wa_sso_cleanup() -> loescht expired + alte consumed (cron)
============================================================ */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Vollstaendiger Tabellenname inkl. WP-Prefix.
 *
 * @return string
 */
function wa_sso_tokens_table_name() {
    global $wpdb;
    return $wpdb->prefix . 'wa_sso_tokens';
}

/**
 * Tabelle installieren / Schema-Migration via dbDelta. Idempotent.
 * Wird vom Bootstrap-Self-Install (00-bootstrap.php) aufgerufen.
 */
function wa_sso_tokens_install() {
    global $wpdb;
    $table   = wa_sso_tokens_table_name();
    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE {$table} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        token_hash CHAR(64) NOT NULL,
        user_id BIGINT UNSIGNED NOT NULL,
        action_slug VARCHAR(64) NOT NULL,
        action_args LONGTEXT NULL,
        expires_at DATETIME NOT NULL,
        consumed_at DATETIME NULL DEFAULT NULL,
        created_at DATETIME NOT NULL,
        created_ip VARCHAR(45) NULL DEFAULT NULL,
        consumed_ip VARCHAR(45) NULL DEFAULT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY token_hash (token_hash),
        KEY user_id (user_id),
        KEY expires_at (expires_at)
    ) {$charset};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );
}

/* ----------------------------------------------------------
   INTERNE HELPERS
---------------------------------------------------------- */

/**
 * Hasht ein raw Token zu seinem Storage-Wert.
 *
 * @param string $raw
 * @return string 64-Zeichen Hex (SHA-256).
 */
function wa_sso_hash_token( $raw ) {
    return hash( 'sha256', (string) $raw );
}

/**
 * Erzeugt ein neues raw Token mit Base64URL-Encoding (URL-safe).
 *
 * @return string ~43 Zeichen bei 32 Byte Entropie.
 */
function wa_sso_generate_raw_token() {
    $bytes = random_bytes( WA_SSO_TOKEN_BYTES );
    // Base64URL: + -> -, / -> _, padding entfernen.
    return rtrim( strtr( base64_encode( $bytes ), '+/', '-_' ), '=' );
}

/* ----------------------------------------------------------
   CREATE
---------------------------------------------------------- */

/**
 * Legt ein neues Token in der DB an und liefert das raw Token
 * zurueck. Caller embedded das raw Token in eine URL und
 * verschickt sie. Server speichert nur den SHA-256-Hash.
 *
 * @param int    $user_id        WP-User der eingeloggt werden soll.
 * @param string $action_slug    Registrierte Action (siehe actions.php).
 * @param array  $action_args    Argumente fuer die Action (z.B. ['order_id' => 123]).
 * @param int|null $ttl_minutes  Optional. Default aus Settings.
 * @return string|WP_Error raw Token bei Erfolg, WP_Error sonst.
 */
function wa_sso_create_token( $user_id, $action_slug, array $action_args = array(), $ttl_minutes = null ) {
    global $wpdb;

    $user_id = (int) $user_id;
    if ( $user_id <= 0 ) {
        return new WP_Error( 'invalid_user', __( 'Ungueltige Benutzer-ID.', 'werbeauf-customs' ) );
    }
    if ( ! wa_sso_user_is_allowed( $user_id ) ) {
        return new WP_Error( 'role_not_allowed', __( 'Diese Benutzerrolle darf kein SSO-Token erhalten.', 'werbeauf-customs' ) );
    }

    if ( $ttl_minutes === null ) {
        $ttl_minutes = (int) wa_sso_settings_get( 'token_ttl_minutes', 15 );
    }
    $ttl_minutes = max( 1, min( 20160, (int) $ttl_minutes ) ); // 1 Min .. 14 Tage

    $raw  = wa_sso_generate_raw_token();
    $hash = wa_sso_hash_token( $raw );
    // expires_at in UTC speichern; current_time('mysql') liefert je nach
    // Konfiguration site-time -- daher fuer Vergleiche immer
    // gmdate() / time() benutzen.
    $exp  = gmdate( 'Y-m-d H:i:s', time() + $ttl_minutes * MINUTE_IN_SECONDS );

    $inserted = $wpdb->insert(
        wa_sso_tokens_table_name(),
        array(
            'token_hash'  => $hash,
            'user_id'     => $user_id,
            'action_slug' => substr( sanitize_key( $action_slug ), 0, 64 ),
            'action_args' => wp_json_encode( $action_args ),
            'expires_at'  => $exp,
            'created_at'  => gmdate( 'Y-m-d H:i:s' ),
            'created_ip'  => wa_sso_client_ip(),
        ),
        array( '%s', '%d', '%s', '%s', '%s', '%s', '%s' )
    );

    if ( $inserted === false ) {
        return new WP_Error( 'db_insert_failed', __( 'Token konnte nicht gespeichert werden.', 'werbeauf-customs' ) );
    }

    /**
     * Fires after a successful token creation. Useful for audit hooks.
     *
     * @param int    $token_id
     * @param int    $user_id
     * @param string $action_slug
     * @param array  $action_args
     */
    do_action( 'wa_sso_token_created', (int) $wpdb->insert_id, $user_id, $action_slug, $action_args );

    return $raw;
}

/* ----------------------------------------------------------
   VALIDATE
---------------------------------------------------------- */

/**
 * Sucht ein Token via Hash und prueft Status. KEINE State-Aenderung
 * -- nur Lese-Pruefung. Vor wa_sso_consume_token() aufrufen.
 *
 * @param string $raw
 * @return array{id:int,user_id:int,action_slug:string,action_args:array,expires_at:string}|WP_Error
 */
function wa_sso_validate_token( $raw ) {
    global $wpdb;

    if ( ! is_string( $raw ) || $raw === '' ) {
        return new WP_Error( 'empty_token', __( 'Token fehlt.', 'werbeauf-customs' ) );
    }
    $hash = wa_sso_hash_token( $raw );

    $row = $wpdb->get_row(
        $wpdb->prepare(
            'SELECT id, token_hash, user_id, action_slug, action_args, expires_at, consumed_at
             FROM ' . wa_sso_tokens_table_name() . '
             WHERE token_hash = %s
             LIMIT 1',
            $hash
        ),
        ARRAY_A
    );

    if ( ! $row ) {
        return new WP_Error( 'token_not_found', __( 'Dieser Link ist ungueltig.', 'werbeauf-customs' ) );
    }

    // Konstanten-Zeit-Vergleich gegen Timing-Attacken.
    if ( ! hash_equals( (string) $row['token_hash'], $hash ) ) {
        return new WP_Error( 'token_mismatch', __( 'Dieser Link ist ungueltig.', 'werbeauf-customs' ) );
    }

    if ( ! empty( $row['consumed_at'] ) ) {
        return new WP_Error( 'token_consumed', __( 'Dieser Link wurde bereits benutzt.', 'werbeauf-customs' ) );
    }

    // Vergleich in UTC: expires_at wird in UTC gespeichert (siehe create).
    $expires_ts = strtotime( $row['expires_at'] . ' UTC' );
    if ( $expires_ts === false || $expires_ts < time() ) {
        return new WP_Error( 'token_expired', __( 'Dieser Link ist abgelaufen.', 'werbeauf-customs' ) );
    }

    if ( ! wa_sso_user_is_allowed( (int) $row['user_id'] ) ) {
        return new WP_Error( 'role_not_allowed', __( 'Dieser Benutzer darf nicht mehr per SSO einloggen.', 'werbeauf-customs' ) );
    }

    return array(
        'id'           => (int) $row['id'],
        'user_id'      => (int) $row['user_id'],
        'action_slug'  => (string) $row['action_slug'],
        'action_args'  => is_string( $row['action_args'] ) ? (array) json_decode( $row['action_args'], true ) : array(),
        'expires_at'   => (string) $row['expires_at'],
    );
}

/* ----------------------------------------------------------
   CONSUME
---------------------------------------------------------- */

/**
 * Markiert das Token als verbraucht. Returns nach dem Setzen die
 * aktualisierte Row (per validate vorher abgefragt).
 *
 * @param string $raw
 * @return array|WP_Error
 */
function wa_sso_consume_token( $raw ) {
    global $wpdb;

    $row = wa_sso_validate_token( $raw );
    if ( is_wp_error( $row ) ) {
        return $row;
    }

    $updated = $wpdb->update(
        wa_sso_tokens_table_name(),
        array(
            'consumed_at' => gmdate( 'Y-m-d H:i:s' ),
            'consumed_ip' => wa_sso_client_ip(),
        ),
        array( 'id' => $row['id'], 'consumed_at' => null ),
        array( '%s', '%s' ),
        array( '%d', '%s' )
    );

    if ( $updated === false || $updated === 0 ) {
        // Race-Condition: jemand anders hat in der Zwischenzeit konsumiert.
        return new WP_Error( 'token_race', __( 'Dieser Link wurde gerade benutzt.', 'werbeauf-customs' ) );
    }

    /**
     * Fires after a token is consumed. Audit/monitoring hook.
     *
     * @param int    $token_id
     * @param int    $user_id
     * @param string $action_slug
     */
    do_action( 'wa_sso_token_consumed', $row['id'], $row['user_id'], $row['action_slug'] );

    return $row;
}

/* ----------------------------------------------------------
   REVOKE
---------------------------------------------------------- */

/**
 * Revokes (loescht) alle ausstehenden Tokens fuer einen User.
 * Wird vom Settings-UI "Alle Tokens zurueckziehen" aufgerufen,
 * oder per CLI / Filter wenn ein User deaktiviert wird.
 *
 * @param int $user_id  0 = ALL users (Achtung).
 * @return int Geloeschte Rows.
 */
function wa_sso_revoke_user_tokens( $user_id = 0 ) {
    global $wpdb;
    $user_id = (int) $user_id;
    if ( $user_id > 0 ) {
        return (int) $wpdb->delete( wa_sso_tokens_table_name(), array( 'user_id' => $user_id ), array( '%d' ) );
    }
    // Alle Tokens (Notfall-Switch fuer Key-Rotation).
    return (int) $wpdb->query( 'DELETE FROM ' . wa_sso_tokens_table_name() );
}

/* ----------------------------------------------------------
   CLEANUP (cron-target)
---------------------------------------------------------- */

/**
 * Loescht abgelaufene + nicht konsumierte Tokens sofort,
 * sowie konsumierte Tokens aelter als audit_retention_days.
 *
 * @return array{expired:int,old_audit:int}
 */
function wa_sso_cleanup_expired() {
    global $wpdb;
    $table = wa_sso_tokens_table_name();

    $now = gmdate( 'Y-m-d H:i:s' );

    $expired = (int) $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$table} WHERE consumed_at IS NULL AND expires_at < %s",
            $now
        )
    );

    $retention_days = max( 1, (int) wa_sso_settings_get( 'audit_retention_days', 30 ) );
    $cutoff         = gmdate( 'Y-m-d H:i:s', time() - $retention_days * DAY_IN_SECONDS );
    $old_audit      = (int) $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$table} WHERE consumed_at IS NOT NULL AND consumed_at < %s",
            $cutoff
        )
    );

    return array( 'expired' => $expired, 'old_audit' => $old_audit );
}

/* ----------------------------------------------------------
   AUDIT-READ (fuer Settings-UI)
---------------------------------------------------------- */

/**
 * Letzte N Token-Eintraege (created + consumed) fuer das Audit-Log
 * in der Settings-UI.
 *
 * @param int $user_id 0 = alle.
 * @param int $limit
 * @return array<int, array<string, mixed>>
 */
function wa_sso_recent_tokens( $user_id = 0, $limit = 50 ) {
    global $wpdb;
    $limit = max( 1, min( 500, (int) $limit ) );
    $table = wa_sso_tokens_table_name();

    if ( $user_id > 0 ) {
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, user_id, action_slug, expires_at, consumed_at, created_at, created_ip, consumed_ip
                 FROM {$table} WHERE user_id = %d ORDER BY created_at DESC LIMIT %d",
                (int) $user_id,
                $limit
            ),
            ARRAY_A
        );
    } else {
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, user_id, action_slug, expires_at, consumed_at, created_at, created_ip, consumed_ip
                 FROM {$table} ORDER BY created_at DESC LIMIT %d",
                $limit
            ),
            ARRAY_A
        );
    }

    return is_array( $rows ) ? $rows : array();
}

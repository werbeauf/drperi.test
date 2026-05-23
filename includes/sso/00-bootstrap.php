<?php
/* ============================================================
   DATEI: includes/sso/00-bootstrap.php
   ZWECK: SSO-Modul Bootstrap. Konstanten, Default-Settings,
          Self-Install der Token-Tabelle, gemeinsame Helpers.

   Modul: passwordless Single-Sign-On per Email-Button. Tokens
   werden serverseitig erzeugt, gehasht gespeichert, sind
   single-use + zeitbegrenzt, und an einen User-Account + eine
   konkrete Action (z.B. "mark order #1234 completed") gebunden.

   Lade-Reihenfolge in dieser Datei beachten:
     1. Konstanten
     2. Settings-Defaults + getter
     3. Capability-/Role-Check Helper
     4. Self-Install (init Hook, ueberprueft DB-Version)

   Die eigentlichen Sub-Module:
     - tokens.php      CRUD + Tabellen-DDL
     - actions.php     Action-Registry + 4 built-ins
     - login.php       ?wa_sso=TOKEN Endpoint
     - confirm.php     Confirm-Page fuer state-changing Actions
     - emails.php      Button-Helper + WC-Email-Hooks
     - settings.php    Settings-Page (Settings -> SSO Login)
     - cron.php        taegliches Cleanup
============================================================ */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ----------------------------------------------------------
   KONSTANTEN
---------------------------------------------------------- */

if ( ! defined( 'WA_SSO_VERSION' ) ) {
    /** Datenbank-Schema-Version. Bei Aenderungen am Tabellen-DDL hochzaehlen. */
    define( 'WA_SSO_VERSION', '1.0.0' );
}

if ( ! defined( 'WA_SSO_TOKEN_BYTES' ) ) {
    /** Anzahl Bytes aus random_bytes() pro Token. 32 = 256 Bit Entropie. */
    define( 'WA_SSO_TOKEN_BYTES', 32 );
}

if ( ! defined( 'WA_SSO_QUERY_VAR' ) ) {
    /** URL-Parameter fuer den Login-Endpoint. ?wa_sso=TOKEN. */
    define( 'WA_SSO_QUERY_VAR', 'wa_sso' );
}

if ( ! defined( 'WA_SSO_CONFIRM_QUERY_VAR' ) ) {
    /** URL-Parameter fuer die Confirm-Page. */
    define( 'WA_SSO_CONFIRM_QUERY_VAR', 'wa_sso_confirm' );
}

if ( ! defined( 'WA_SSO_CRON_HOOK' ) ) {
    define( 'WA_SSO_CRON_HOOK', 'wa_sso_cleanup' );
}

if ( ! defined( 'WA_SSO_OPTION_SETTINGS' ) ) {
    define( 'WA_SSO_OPTION_SETTINGS', 'wa_sso_settings' );
}

if ( ! defined( 'WA_SSO_OPTION_DB_VERSION' ) ) {
    define( 'WA_SSO_OPTION_DB_VERSION', 'wa_sso_db_version' );
}

/* ----------------------------------------------------------
   SETTINGS DEFAULTS + GETTER

   Settings liegen als assoziatives Array in einem WP-Option.
   wa_sso_settings_get() liefert immer einen sinnvollen Default,
   auch wenn das Option noch nie geschrieben wurde.
---------------------------------------------------------- */

/**
 * Liefert die Default-Settings fuer das SSO-Modul.
 *
 * Wird vom Settings-UI als Anfangs-State genutzt und vom Getter
 * fuer fehlende Keys.
 *
 * @return array<string, mixed>
 */
function wa_sso_settings_defaults() {
    return array(
        'enabled'              => false,                  // Master-Switch, default AUS
        'token_ttl_minutes'    => 10080,                  // Token-Lebensdauer = 7 Tage (Klinik-Workflow:
                                                          // Mitarbeiterin soll auch nach Wochenende noch klicken koennen)
        'allowed_roles'        => array( 'administrator', 'shop_manager' ),
        'inject_into_wc_email' => true,                   // Buttons in WC-New-Order-Admin-Email
        'audit_retention_days' => 30,                     // konsumierte Tokens X Tage behalten
    );
}

/**
 * Liest einen Settings-Wert mit Default-Fallback.
 *
 * @param string $key
 * @param mixed  $default Optional. Default falls Key nicht in Options.
 * @return mixed
 */
function wa_sso_settings_get( $key, $default = null ) {
    static $cache = null;
    if ( $cache === null ) {
        $stored   = get_option( WA_SSO_OPTION_SETTINGS, array() );
        $defaults = wa_sso_settings_defaults();
        $cache    = is_array( $stored ) ? array_merge( $defaults, $stored ) : $defaults;
    }
    if ( array_key_exists( $key, $cache ) ) {
        return $cache[ $key ];
    }
    return $default;
}

/**
 * Schreibt das gesamte Settings-Array zurueck. Settings-UI ruft
 * das nach Form-Submit. Cache wird invalidiert.
 *
 * @param array<string, mixed> $new
 * @return bool
 */
function wa_sso_settings_save( array $new ) {
    $defaults = wa_sso_settings_defaults();
    $merged   = array_merge( $defaults, $new );
    $ok       = update_option( WA_SSO_OPTION_SETTINGS, $merged, false );
    // Cache invalidieren -- die statische Variable in wa_sso_settings_get()
    // ueberlebt nur den Request, aber zur Sicherheit triggern wir nochmal.
    wp_cache_delete( WA_SSO_OPTION_SETTINGS, 'options' );
    return $ok;
}

/* ----------------------------------------------------------
   ENABLE-CHECK + USER ROLE CHECK
---------------------------------------------------------- */

/**
 * Master-Switch: laeuft das Modul ueberhaupt?
 *
 * @return bool
 */
function wa_sso_is_enabled() {
    return (bool) wa_sso_settings_get( 'enabled', false );
}

/**
 * Darf dieser User per SSO einloggen? Prueft die Rollen-Whitelist
 * aus den Settings gegen die Rollen des Users.
 *
 * @param int $user_id
 * @return bool
 */
function wa_sso_user_is_allowed( $user_id ) {
    $user_id = (int) $user_id;
    if ( ! $user_id ) {
        return false;
    }
    $user = get_userdata( $user_id );
    if ( ! $user || ! $user->exists() ) {
        return false;
    }
    $allowed = (array) wa_sso_settings_get( 'allowed_roles', array() );
    if ( empty( $allowed ) ) {
        return false;
    }
    $user_roles = (array) $user->roles;
    return (bool) array_intersect( $allowed, $user_roles );
}

/* ----------------------------------------------------------
   IP-Helper (fuer Audit-Log, defensiv gegenueber Proxies).
---------------------------------------------------------- */

/**
 * Beste Schaetzung der Client-IP, mit X-Forwarded-For-Auswertung
 * NUR wenn die Site hinter einem vertrauenswuerdigen Proxy laeuft
 * (per Filter wa_sso_trust_forwarded_for konfigurierbar).
 *
 * @return string|null
 */
function wa_sso_client_ip() {
    $candidate = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '';

    if ( apply_filters( 'wa_sso_trust_forwarded_for', false ) && ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
        $parts     = explode( ',', (string) $_SERVER['HTTP_X_FORWARDED_FOR'] );
        $candidate = trim( $parts[0] );
    }

    if ( '' === $candidate ) {
        return null;
    }
    if ( ! filter_var( $candidate, FILTER_VALIDATE_IP ) ) {
        return null;
    }
    return $candidate;
}

/* ----------------------------------------------------------
   SELF-INSTALL (Token-Tabelle anlegen / Schema migrieren).
   Laeuft idempotent auf init Hook. Trigger ist eine
   versionierte Option (WA_SSO_OPTION_DB_VERSION).

   Die Tabelle wird in tokens.php definiert. Diese Funktion
   delegiert nur den dbDelta-Call -- wir wollen die Tabellen-
   Definition zusammen mit den CRUD-Funktionen lokalisieren.
---------------------------------------------------------- */

add_action( 'init', 'wa_sso_maybe_install', 5 );

function wa_sso_maybe_install() {
    if ( get_option( WA_SSO_OPTION_DB_VERSION ) === WA_SSO_VERSION ) {
        return;
    }
    if ( ! function_exists( 'wa_sso_tokens_install' ) ) {
        // tokens.php nicht (noch nicht) geladen -- ignorieren, naechster Tick.
        return;
    }
    wa_sso_tokens_install();
    update_option( WA_SSO_OPTION_DB_VERSION, WA_SSO_VERSION, false );
}

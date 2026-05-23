<?php
/* ============================================================
   DATEI: includes/sso/cron.php
   ZWECK: Taegliches Cleanup. Loescht abgelaufene Tokens und
          alte konsumierte Tokens (Audit-Retention).

   Registriert WA_SSO_CRON_HOOK mit 'daily' schedule auf 'wp'.
   Hook-Callback ruft wa_sso_cleanup_expired() (in tokens.php).
   Unschedule wird vom uninstall.php (am Plugin-Root) gemacht.
============================================================ */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'wp', 'wa_sso_cron_schedule' );

function wa_sso_cron_schedule() {
    if ( ! wp_next_scheduled( WA_SSO_CRON_HOOK ) ) {
        wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', WA_SSO_CRON_HOOK );
    }
}

add_action( WA_SSO_CRON_HOOK, 'wa_sso_cron_run' );

function wa_sso_cron_run() {
    if ( ! function_exists( 'wa_sso_cleanup_expired' ) ) {
        return;
    }
    $result = wa_sso_cleanup_expired();
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG && is_array( $result ) ) {
        error_log( sprintf(
            '[wa_sso] cleanup expired=%d old_audit=%d',
            (int) $result['expired'],
            (int) $result['old_audit']
        ) );
    }
}

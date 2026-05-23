<?php
/**
 * Uninstall handler for werbeauf-customs.
 *
 * Fires when the plugin is deleted from the WP-Admin "Plugins" screen.
 * Clears sensitive credentials (Phorest API token) and ephemeral state.
 * Post/term meta and the stock-log table are intentionally preserved —
 * they may be re-used if the plugin is re-installed.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Sensitive: Phorest API credentials.
delete_option( 'wa_phorest_api_token' );
delete_option( 'wa_phorest_api_url' );
delete_option( 'wa_phorest_business_id' );
delete_option( 'wa_phorest_branch_id' );
delete_option( 'wa_phorest_active' );

// Ephemeral state: caches, sync timestamps, queue, logs.
delete_option( 'wa_phorest_last_sync' );
delete_option( 'wa_newsletter_log' );
delete_transient( 'wa_phorest_products_cache' );

// Scheduled cron events.
$timestamp = wp_next_scheduled( 'wa_phorest_auto_sync' );
if ( $timestamp ) {
	wp_unschedule_event( $timestamp, 'wa_phorest_auto_sync' );
}
wp_clear_scheduled_hook( 'wa_phorest_auto_sync' );

/* ----------------------------------------------------------
   SSO module: drop token table + settings + cron.
   The audit log is genuinely ephemeral -- no reason to keep.
---------------------------------------------------------- */

global $wpdb;

// Drop the SSO token table (safe: nothing references it outside the module).
$sso_table = $wpdb->prefix . 'wa_sso_tokens';
$wpdb->query( "DROP TABLE IF EXISTS {$sso_table}" );

// Settings + schema version.
delete_option( 'wa_sso_settings' );
delete_option( 'wa_sso_db_version' );

// Cron.
$ts = wp_next_scheduled( 'wa_sso_cleanup' );
if ( $ts ) {
	wp_unschedule_event( $ts, 'wa_sso_cleanup' );
}
wp_clear_scheduled_hook( 'wa_sso_cleanup' );

/* ----------------------------------------------------------
   Workflow-Engine (Batch C): drop log table + options + cron.
---------------------------------------------------------- */

$wf_table = $wpdb->prefix . 'wa_workflow_log';
$wpdb->query( "DROP TABLE IF EXISTS {$wf_table}" );

delete_option( 'wa_workflow_rules' );
delete_option( 'wa_workflow_paused' );
delete_option( 'wa_workflow_db_version' );

$ts = wp_next_scheduled( 'wa_workflow_scan' );
if ( $ts ) {
	wp_unschedule_event( $ts, 'wa_workflow_scan' );
}
wp_clear_scheduled_hook( 'wa_workflow_scan' );

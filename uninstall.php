<?php
/**
 * Uninstall script for Ocade Fusion AnythingLLM Chatbot
 *
 * This file is executed when the plugin is deleted via WordPress admin.
 * It removes all plugin data including database tables and options.
 *
 * @package OcadeFusion_AnythingLLM_Chatbot
 * @since 1.0.0
 */

// Exit if not called from WordPress uninstall
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// Delete all plugin options
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'ofac_%'" );

// Delete all plugin transients
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_ofac_%'" );
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_ofac_%'" );

// Drop plugin database tables
$tables = array(
    $wpdb->prefix . 'ofac_conversations',
    $wpdb->prefix . 'ofac_messages',
    $wpdb->prefix . 'ofac_stats',
    $wpdb->prefix . 'ofac_feedback',
    $wpdb->prefix . 'ofac_consents',
);

foreach ( $tables as $table ) {
    $wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

// Delete user meta related to the plugin
$wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'ofac_%'" );

// Clear any scheduled cron jobs
wp_clear_scheduled_hook( 'ofac_cleanup_logs' );
wp_clear_scheduled_hook( 'ofac_cleanup_cache' );
wp_clear_scheduled_hook( 'ofac_cleanup_gdpr_data' );
wp_clear_scheduled_hook( 'ofac_daily_stats_aggregation' );

// Clear object cache if available
if ( function_exists( 'wp_cache_flush' ) ) {
    wp_cache_flush();
}

/**
 * Fires after plugin uninstallation is complete
 *
 * @since 1.0.0
 */
do_action( 'ofac_uninstalled' );

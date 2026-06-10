<?php
/**
 * Uninstall routine.
 *
 * Removes the plugin's stored options, scheduled actions and custom table on deletion.
 *
 * @package Rankea\BillingAbby
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'bafw_abby_api_key' );
delete_option( 'bafw_product_type' );
delete_option( 'bafw_sync_log_db_version' );

if ( function_exists( 'as_unschedule_all_actions' ) ) {
	as_unschedule_all_actions( 'bafw_prune_sync_log', array(), 'billing-abby' );
}

global $wpdb;
$bafw_log_table = $wpdb->prefix . 'bafw_sync_log';

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Drop the plugin's own table on uninstall.
$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $bafw_log_table ) );

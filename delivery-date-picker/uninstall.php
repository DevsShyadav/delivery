<?php
/**
 * Uninstall routine — removes all plugin data.
 *
 * Runs only when the plugin is deleted from the WordPress admin.
 *
 * @package DeliveryDatePicker
 */

// Exit if not called by WordPress during uninstall.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Capability safeguard (uninstall already checks delete_plugins, this is belt-and-braces).
if ( ! current_user_can( 'delete_plugins' ) ) {
	return;
}

global $wpdb;

/*
 * Drop custom tables.
 */
$tables = array(
	$wpdb->prefix . 'ddp_time_slots',
	$wpdb->prefix . 'ddp_blocked_dates',
	$wpdb->prefix . 'ddp_bookings',
);

foreach ( $tables as $table ) {
	// Table names cannot be passed through prepare(); they are built from the trusted prefix.
	$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL, WordPress.DB.DirectDatabaseQuery
}

/*
 * Delete options.
 */
$options = array(
	'ddp_settings',
	'ddp_db_version',
	'ddp_onboarded',
	'ddp_cache_gen',
);

foreach ( $options as $option ) {
	delete_option( $option );
}

/*
 * Delete leftover transients created by the cache layer.
 */
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_ddp\_%' OR option_name LIKE '\_transient\_timeout\_ddp\_%'" ); // phpcs:ignore WordPress.DB.PreparedSQL, WordPress.DB.DirectDatabaseQuery

/*
 * Note: order meta (_ddp_*) is intentionally preserved so historical orders keep
 * their delivery information even after the plugin is removed.
 */

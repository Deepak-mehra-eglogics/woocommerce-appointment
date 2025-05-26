<?php
/**
 * Appointments Uninstall
 */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit();
}

global $wpdb;

wp_clear_scheduled_hook( 'wc-appointment-sync-from-gcal' );
wp_clear_scheduled_hook( 'wc-appointment-sync-full-from-gcal' );

/*
 * Only remove ALL product and page data if WC_REMOVE_ALL_DATA constant is set to true in user's
 * wp-config.php. This is to prevent data loss when deleting the plugin from the backend
 * and to ensure only the site owner can perform this action.
 */
if ( defined( 'WC_REMOVE_ALL_DATA' ) && true === WC_REMOVE_ALL_DATA ) {
	// Delete tables.
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wc_appointment_relationships" );
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wc_appointments_availability" );

	// Delete posts + data.
	$wpdb->query( "DELETE FROM {$wpdb->posts} WHERE post_type IN ( 'wc_appointment' );" );
	$wpdb->query( "DELETE meta FROM {$wpdb->postmeta} meta LEFT JOIN {$wpdb->posts} posts ON posts.ID = meta.post_id WHERE posts.ID IS NULL;" );

	// Delete options.
	$wpdb->query( "DELETE FROM $wpdb->options WHERE option_name LIKE 'wc_appointments\_%';" );

	// Delete usermeta.
	$wpdb->query( "DELETE FROM $wpdb->usermeta WHERE meta_key LIKE 'wc_appointments\_%';" );

	// Clear any cached data that has been removed.
	wp_cache_flush();
}

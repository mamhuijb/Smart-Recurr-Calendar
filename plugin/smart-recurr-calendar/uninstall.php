<?php
/**
 * SmartRecur uninstall handler.
 *
 * Honors the `deleteDataOnUninstall` flag stored in the plugin settings: when
 * true, drops every plugin table and removes every option and transient.
 * When false, the data stays intact for a possible reinstall.
 *
 * @package SmartRecur
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$settings = get_option( 'smartrecur_settings', array() );
if ( empty( $settings['deleteDataOnUninstall'] ) ) {
	// Even when keeping data, drop scheduled events so they don't fire against
	// a now-uninstalled plugin and trigger fatals.
	wp_unschedule_hook( 'smartrecur_o365_sync_tick' );
	return;
}

wp_unschedule_hook( 'smartrecur_o365_sync_tick' );

global $wpdb;
$prefix = $wpdb->prefix;

$tables = array(
	$prefix . 'smartrecur_appointments',
	$prefix . 'smartrecur_clients',
	$prefix . 'smartrecur_assets',
	$prefix . 'smartrecur_services',
	$prefix . 'smartrecur_technicians',
	$prefix . 'smartrecur_recurring_rules',
	$prefix . 'smartrecur_email_logs',
	$prefix . 'smartrecur_integration_configs',
);
foreach ( $tables as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
}

delete_option( 'smartrecur_settings' );
delete_option( 'smartrecur_db_version' );
delete_option( 'smartrecur_plugin_version' );

// Clean up rate-limiter transients.
$transients = $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '_transient_smartrecur_%' OR option_name LIKE '_transient_timeout_smartrecur_%'" );
foreach ( (array) $transients as $opt ) {
	delete_option( $opt );
}

// Strip the capabilities from every role.
$caps  = array( 'smartrecur_manage', 'smartrecur_book', 'smartrecur_view', 'smartrecur_manage_clients' );
$roles = array( 'administrator', 'editor', 'author', 'contributor', 'subscriber' );
foreach ( $roles as $role_slug ) {
	$role = get_role( $role_slug );
	if ( ! $role ) {
		continue;
	}
	foreach ( $caps as $cap ) {
		$role->remove_cap( $cap );
	}
}

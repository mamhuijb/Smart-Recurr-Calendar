<?php
/**
 * Plugin deactivation: strip capabilities, flush rewrite rules. Never deletes data.
 *
 * @package SmartRecur
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SmartRecur_Deactivator {

	/**
	 * Run deactivation steps.
	 */
	public static function deactivate() {
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

		// Stop any scheduled cron jobs so an inactive plugin doesn't keep firing them.
		if ( class_exists( 'SmartRecur_Sync_Office365' ) ) {
			SmartRecur_Sync_Office365::unschedule();
		}
		if ( class_exists( 'SmartRecur_Reminders' ) ) {
			SmartRecur_Reminders::unschedule();
		}

		flush_rewrite_rules();
	}
}

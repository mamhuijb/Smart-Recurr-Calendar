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

		flush_rewrite_rules();
	}
}

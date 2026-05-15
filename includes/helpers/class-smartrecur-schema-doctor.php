<?php
/**
 * Self-healing migrations: ensure expected columns exist on every load.
 *
 * dbDelta only runs at activation, and silently skips changes it doesn't fully
 * understand. This class compares the live schema against the columns the rest
 * of the plugin assumes are present, and ALTER TABLE-adds anything missing.
 *
 * Runs once per request, gated by a short transient so it doesn't hammer the
 * DB on every page load.
 *
 * @package SmartRecur
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SmartRecur_Schema_Doctor {

	const CACHE_KEY = 'smartrecur_schema_ok';
	const CACHE_TTL = 5 * MINUTE_IN_SECONDS;

	/**
	 * Run all column-existence checks. Cheap when cached.
	 */
	public static function ensure() {
		if ( get_transient( self::CACHE_KEY ) ) {
			return;
		}

		global $wpdb;
		$prefix = $wpdb->prefix;

		self::ensure_columns(
			$prefix . 'smartrecur_clients',
			array(
				'invoiceninja_id' => "ADD COLUMN invoiceninja_id varchar(255) DEFAULT NULL",
				'user_id'         => "ADD COLUMN user_id bigint(20) UNSIGNED DEFAULT NULL",
				'created_by'      => "ADD COLUMN created_by bigint(20) UNSIGNED DEFAULT NULL",
				'modified_by'     => "ADD COLUMN modified_by bigint(20) UNSIGNED DEFAULT NULL",
				'address'         => "ADD COLUMN address text NULL",
				'postcode'        => "ADD COLUMN postcode varchar(20) DEFAULT ''",
				'syncro_id'       => "ADD COLUMN syncro_id varchar(50) DEFAULT NULL",
			)
		);

		self::ensure_columns(
			$prefix . 'smartrecur_appointments',
			array(
				'created_by'    => "ADD COLUMN created_by bigint(20) UNSIGNED DEFAULT NULL",
				'modified_by'   => "ADD COLUMN modified_by bigint(20) UNSIGNED DEFAULT NULL",
				'start_time'    => "ADD COLUMN start_time varchar(5) DEFAULT NULL",
				'end_time'      => "ADD COLUMN end_time varchar(5) DEFAULT NULL",
				'o365_event_id' => "ADD COLUMN o365_event_id longtext DEFAULT NULL",
			)
		);

		self::ensure_columns(
			$prefix . 'smartrecur_services',
			array(
				'reminder_days' => "ADD COLUMN reminder_days longtext DEFAULT NULL",
			)
		);

		self::ensure_columns(
			$prefix . 'smartrecur_technicians',
			array(
				'user_id' => "ADD COLUMN user_id bigint(20) UNSIGNED DEFAULT NULL",
			)
		);

		set_transient( self::CACHE_KEY, 1, self::CACHE_TTL );
	}

	/**
	 * Check that every column in $columns exists on $table, ALTER TABLE-add any that don't.
	 *
	 * @param string $table   Fully-qualified table name.
	 * @param array  $columns Map of column name → ALTER TABLE clause.
	 */
	private static function ensure_columns( $table, array $columns ) {
		global $wpdb;

		$existing_table = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( ! $existing_table ) {
			return; // Table itself missing — activator should run.
		}

		$rows = $wpdb->get_results( "DESCRIBE {$table}", ARRAY_A );
		if ( ! is_array( $rows ) ) {
			return;
		}
		$have = array();
		foreach ( $rows as $row ) {
			$have[ $row['Field'] ] = true;
		}

		foreach ( $columns as $column => $clause ) {
			if ( isset( $have[ $column ] ) ) {
				continue;
			}
			$wpdb->query( "ALTER TABLE {$table} {$clause}" );
			error_log( "[SmartRecur] schema doctor: ALTER TABLE {$table} {$clause}" );
		}
	}

	/**
	 * Force a re-check on the next request. Call after activation or migrations.
	 */
	public static function flush() {
		delete_transient( self::CACHE_KEY );
	}
}

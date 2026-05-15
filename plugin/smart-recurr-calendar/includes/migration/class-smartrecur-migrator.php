<?php
/**
 * One-shot importer for the standalone SmartRecur MariaDB schema.
 *
 * Reads from a foreign DSN supplied by the admin, INSERTs into the prefixed
 * WordPress tables. Idempotent on the primary keys (skips duplicates).
 *
 * @package SmartRecur
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SmartRecur_Migrator {

	/**
	 * Run from POSTed credentials.
	 *
	 * @param array $post Posted form values (will be unslash + sanitized).
	 * @return array
	 */
	public static function run_from_post( array $post ) {
		$host = isset( $post['db_host'] ) ? sanitize_text_field( wp_unslash( $post['db_host'] ) ) : '';
		$port = isset( $post['db_port'] ) ? absint( $post['db_port'] ) : 3306;
		$name = isset( $post['db_name'] ) ? sanitize_text_field( wp_unslash( $post['db_name'] ) ) : '';
		$user = isset( $post['db_user'] ) ? sanitize_text_field( wp_unslash( $post['db_user'] ) ) : '';
		$pass = isset( $post['db_pass'] ) ? (string) wp_unslash( $post['db_pass'] ) : '';

		try {
			$dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
			$pdo = new PDO( $dsn, $user, $pass, array( PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION ) );
		} catch ( Exception $e ) {
			return array( 'success' => false, 'message' => __( 'Could not connect to source database: ', 'smartrecur' ) . $e->getMessage() );
		}

		global $wpdb;
		$prefix = $wpdb->prefix;
		$stats  = array();

		try {
			$stats['customers']   = self::copy_customers( $pdo, $wpdb, $prefix );
			$stats['services']    = self::copy_services( $pdo, $wpdb, $prefix );
			$stats['technicians'] = self::copy_technicians( $pdo, $wpdb, $prefix );
			$stats['events']      = self::copy_events( $pdo, $wpdb, $prefix );
		} catch ( Exception $e ) {
			return array(
				'success' => false,
				'message' => __( 'Migration aborted: ', 'smartrecur' ) . $e->getMessage(),
				'stats'   => $stats,
			);
		}

		return array(
			'success' => true,
			'message' => __( 'Migration complete.', 'smartrecur' ),
			'stats'   => $stats,
		);
	}

	/**
	 * Copy customer rows.
	 *
	 * @param PDO    $pdo    Source DB.
	 * @param wpdb   $wpdb   WP DB.
	 * @param string $prefix Table prefix.
	 * @return int Rows imported.
	 */
	private static function copy_customers( PDO $pdo, $wpdb, $prefix ) {
		$rows  = $pdo->query( 'SELECT id, company, name, email, phone, address, postcode, syncro_id, invoiceninja_id FROM customers' )->fetchAll( PDO::FETCH_ASSOC );
		$table = $prefix . 'smartrecur_clients';
		$count = 0;
		foreach ( $rows as $row ) {
			$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE id = %s", $row['id'] ) );
			if ( $exists ) {
				continue;
			}
			$wpdb->insert(
				$table,
				array(
					'id'              => $row['id'],
					'company'         => $row['company'] ?? '',
					'name'            => $row['name'] ?? '',
					'email'           => $row['email'] ?? '',
					'phone'           => $row['phone'] ?? '',
					'address'         => $row['address'] ?? '',
					'postcode'        => $row['postcode'] ?? '',
					'syncro_id'       => $row['syncro_id'] ?? null,
					'invoiceninja_id' => $row['invoiceninja_id'] ?? null,
				),
				array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
			);
			$count++;
		}
		return $count;
	}

	/**
	 * Copy services.
	 *
	 * @param PDO    $pdo    Source DB.
	 * @param wpdb   $wpdb   WP DB.
	 * @param string $prefix Prefix.
	 * @return int Rows imported.
	 */
	private static function copy_services( PDO $pdo, $wpdb, $prefix ) {
		$rows  = $pdo->query( 'SELECT * FROM services' )->fetchAll( PDO::FETCH_ASSOC );
		$table = $prefix . 'smartrecur_services';
		$count = 0;
		foreach ( $rows as $row ) {
			$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE id = %s", $row['id'] ) );
			if ( $exists ) {
				continue;
			}
			$wpdb->insert(
				$table,
				array(
					'id'                     => $row['id'],
					'name'                   => $row['name'] ?? '',
					'type'                   => $row['type'] ?? 'RECURRING',
					'default_duration_min'   => (int) ( $row['default_duration_min'] ?? 60 ),
					'default_location'       => $row['default_location'] ?? 'ON_SITE',
					'color'                  => $row['color'] ?? '#4F46E5',
					'create_ticket'          => (int) ( $row['create_ticket'] ?? 0 ),
					'email_template_subject' => $row['email_template_subject'] ?? null,
					'email_template_body'    => $row['email_template_body'] ?? null,
					'reminder_days'          => $row['reminder_days'] ?? null,
				),
				array( '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%s', '%s', '%s' )
			);
			$count++;
		}
		return $count;
	}

	/**
	 * Copy technicians.
	 *
	 * @param PDO    $pdo    Source DB.
	 * @param wpdb   $wpdb   WP DB.
	 * @param string $prefix Prefix.
	 * @return int Rows imported.
	 */
	private static function copy_technicians( PDO $pdo, $wpdb, $prefix ) {
		$rows  = $pdo->query( 'SELECT * FROM technicians' )->fetchAll( PDO::FETCH_ASSOC );
		$table = $prefix . 'smartrecur_technicians';
		$count = 0;
		foreach ( $rows as $row ) {
			$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE id = %s", $row['id'] ) );
			if ( $exists ) {
				continue;
			}
			$wpdb->insert(
				$table,
				array(
					'id'     => $row['id'],
					'name'   => $row['name'] ?? '',
					'email'  => $row['email'] ?? '',
					'color'  => $row['color'] ?? '#10B981',
					'skills' => $row['skills'] ?? '[]',
				),
				array( '%s', '%s', '%s', '%s', '%s' )
			);
			$count++;
		}
		return $count;
	}

	/**
	 * Copy events.
	 *
	 * @param PDO    $pdo    Source DB.
	 * @param wpdb   $wpdb   WP DB.
	 * @param string $prefix Prefix.
	 * @return int Rows imported.
	 */
	private static function copy_events( PDO $pdo, $wpdb, $prefix ) {
		$rows  = $pdo->query( 'SELECT * FROM events' )->fetchAll( PDO::FETCH_ASSOC );
		$table = $prefix . 'smartrecur_appointments';
		$count = 0;
		foreach ( $rows as $row ) {
			$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE id = %s", $row['id'] ) );
			if ( $exists ) {
				continue;
			}
			$wpdb->insert(
				$table,
				array(
					'id'               => $row['id'],
					'title'            => $row['title'] ?? '',
					'client_id'        => $row['customer_id'] ?? '',
					'service_id'       => $row['service_id'] ?? '',
					'technician_id'    => $row['technician_id'] ?? null,
					'asset_id'         => $row['asset_id'] ?? null,
					'syncro_ticket_id' => $row['syncro_ticket_id'] ?? null,
					'location_type'    => $row['location_type'] ?? 'ON_SITE',
					'description'      => $row['description'] ?? null,
					'recurrence_rule'  => $row['recurrence_rule'] ?? null,
					'generated_dates'  => $row['generated_dates'] ?? '[]',
					'start_time'       => $row['start_time'] ?? null,
					'end_time'         => $row['end_time'] ?? null,
					'status'           => $row['status'] ?? 'SCHEDULED',
				),
				array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
			);
			$count++;
		}
		return $count;
	}
}

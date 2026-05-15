<?php
/**
 * Plugin activation: env checks, table creation, default options, capabilities.
 *
 * @package SmartRecur
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SmartRecur_Activator {

	/**
	 * Run activation steps. Aborts with an admin notice if minimum requirements are not met.
	 */
	public static function activate() {
		self::check_environment();
		self::create_tables();
		self::set_default_options();
		self::register_capabilities();
		flush_rewrite_rules();
		update_option( 'smartrecur_db_version', SMARTRECUR_DB_VERSION );
		update_option( 'smartrecur_plugin_version', SMARTRECUR_VERSION );
		// Trigger a one-time post-activation notice via transient.
		set_transient( 'smartrecur_just_activated', 1, 60 );
	}

	/**
	 * Bail out with a fatal admin notice if the host can't run this plugin.
	 */
	private static function check_environment() {
		if ( version_compare( PHP_VERSION, '8.4', '<' ) ) {
			deactivate_plugins( SMARTRECUR_PLUGIN_BASENAME );
			wp_die(
				esc_html__( 'SmartRecur Calendar requires PHP 8.4 or higher.', 'smartrecur' ),
				esc_html__( 'Plugin activation failed', 'smartrecur' ),
				array( 'back_link' => true )
			);
		}

		global $wp_version;
		if ( version_compare( $wp_version, '6.0', '<' ) ) {
			deactivate_plugins( SMARTRECUR_PLUGIN_BASENAME );
			wp_die(
				esc_html__( 'SmartRecur Calendar requires WordPress 6.0 or higher.', 'smartrecur' ),
				esc_html__( 'Plugin activation failed', 'smartrecur' ),
				array( 'back_link' => true )
			);
		}
	}

	/**
	 * Create or upgrade all plugin tables via dbDelta.
	 */
	public static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$prefix          = $wpdb->prefix;

		$sql = array();

		$sql[] = "CREATE TABLE {$prefix}smartrecur_clients (
			id varchar(36) NOT NULL,
			company varchar(255) NOT NULL DEFAULT '',
			name varchar(255) NOT NULL,
			email varchar(255) DEFAULT '',
			phone varchar(50) DEFAULT '',
			address text NULL,
			postcode varchar(20) DEFAULT '',
			syncro_id varchar(50) DEFAULT NULL,
			invoiceninja_id varchar(255) DEFAULT NULL,
			user_id bigint(20) UNSIGNED DEFAULT NULL,
			created_by bigint(20) UNSIGNED DEFAULT NULL,
			modified_by bigint(20) UNSIGNED DEFAULT NULL,
			created_at timestamp DEFAULT CURRENT_TIMESTAMP,
			updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY idx_company (company),
			KEY idx_syncro_id (syncro_id),
			KEY idx_invoiceninja_id (invoiceninja_id),
			KEY idx_user (user_id)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$prefix}smartrecur_assets (
			id varchar(36) NOT NULL,
			client_id varchar(36) NOT NULL,
			name varchar(255) NOT NULL,
			type varchar(100) DEFAULT '',
			created_at timestamp DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY idx_client (client_id)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$prefix}smartrecur_services (
			id varchar(36) NOT NULL,
			name varchar(255) NOT NULL,
			type varchar(20) NOT NULL DEFAULT 'RECURRING',
			default_duration_min int NOT NULL DEFAULT 60,
			default_location varchar(20) NOT NULL DEFAULT 'ON_SITE',
			color varchar(7) NOT NULL DEFAULT '#4F46E5',
			create_ticket tinyint(1) NOT NULL DEFAULT 0,
			email_template_subject varchar(500) DEFAULT NULL,
			email_template_body text DEFAULT NULL,
			reminder_days longtext DEFAULT NULL,
			created_at timestamp DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$prefix}smartrecur_technicians (
			id varchar(36) NOT NULL,
			name varchar(255) NOT NULL,
			email varchar(255) DEFAULT '',
			color varchar(7) NOT NULL DEFAULT '#10B981',
			skills longtext DEFAULT NULL,
			user_id bigint(20) UNSIGNED DEFAULT NULL,
			created_at timestamp DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY idx_user (user_id)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$prefix}smartrecur_appointments (
			id varchar(36) NOT NULL,
			title varchar(255) NOT NULL,
			client_id varchar(36) NOT NULL,
			service_id varchar(36) NOT NULL,
			technician_id varchar(36) DEFAULT NULL,
			asset_id varchar(36) DEFAULT NULL,
			syncro_ticket_id varchar(50) DEFAULT NULL,
			location_type varchar(20) NOT NULL DEFAULT 'ON_SITE',
			description text DEFAULT NULL,
			recurrence_rule text DEFAULT NULL,
			generated_dates longtext NOT NULL,
			start_time varchar(5) DEFAULT NULL,
			end_time varchar(5) DEFAULT NULL,
			status varchar(20) NOT NULL DEFAULT 'SCHEDULED',
			created_by bigint(20) UNSIGNED DEFAULT NULL,
			modified_by bigint(20) UNSIGNED DEFAULT NULL,
			created_at timestamp DEFAULT CURRENT_TIMESTAMP,
			updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY idx_client (client_id),
			KEY idx_service (service_id),
			KEY idx_technician (technician_id),
			KEY idx_status (status),
			KEY idx_created_by (created_by)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$prefix}smartrecur_recurring_rules (
			id varchar(36) NOT NULL,
			name varchar(255) NOT NULL DEFAULT '',
			rule_text text NOT NULL,
			generated_dates longtext NOT NULL,
			created_at timestamp DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$prefix}smartrecur_email_logs (
			id varchar(36) NOT NULL,
			recipient varchar(255) NOT NULL,
			subject varchar(500) NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'sent',
			method varchar(20) NOT NULL DEFAULT 'wp_mail',
			error text DEFAULT NULL,
			created_at timestamp DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY idx_status (status),
			KEY idx_created (created_at)
		) {$charset_collate};";

		$sql[] = "CREATE TABLE {$prefix}smartrecur_integration_configs (
			id varchar(50) NOT NULL,
			config longtext NOT NULL,
			is_connected tinyint(1) NOT NULL DEFAULT 0,
			last_checked timestamp NULL DEFAULT NULL,
			updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id)
		) {$charset_collate};";

		foreach ( $sql as $statement ) {
			dbDelta( $statement );
		}

		self::seed_integration_rows();
	}

	/**
	 * Ensure every supported integration has a config row (with empty defaults).
	 */
	private static function seed_integration_rows() {
		global $wpdb;
		$table = $wpdb->prefix . 'smartrecur_integration_configs';

		$defaults = array(
			'office365'    => array( 'clientId' => '', 'tenantId' => 'common', 'redirectUri' => '', 'accessToken' => '', 'userEmail' => '' ),
			'syncro'       => array( 'apiKey' => '', 'subdomain' => '' ),
			'invoiceninja' => array( 'apiKey' => '', 'endpoint' => 'https://app.invoiceninja.com' ),
			'zoho'         => array( 'apiKey' => '', 'apiSecret' => '', 'endpoint' => 'https://www.zohoapis.com' ),
			// Stored only so the legacy AdminPanel UI doesn't 404 on status checks.
			// Email delivery goes through wp_mail() — install an SMTP plugin if needed.
			'smtp'         => array( 'host' => '', 'port' => 587, 'username' => '', 'fromEmail' => '', 'fromName' => 'SmartRecur', 'encryption' => 'tls' ),
		);

		foreach ( $defaults as $id => $config ) {
			$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE id = %s", $id ) );
			if ( ! $exists ) {
				$wpdb->insert(
					$table,
					array(
						'id'           => $id,
						'config'       => SmartRecur_Encryption::encrypt( wp_json_encode( $config ) ),
						'is_connected' => 0,
					),
					array( '%s', '%s', '%d' )
				);
			}
		}
	}

	/**
	 * Seed plugin defaults if no settings have been saved yet.
	 */
	private static function set_default_options() {
		if ( false === get_option( 'smartrecur_settings' ) ) {
			add_option( 'smartrecur_settings', array(
				'branding'               => array(
					'logoUrl'         => '',
					'primaryColorHex' => '#4f46e5',
					'themeMode'       => 'dark',
				),
				'businessHours'          => array(
					'start'      => '09:00',
					'end'        => '17:00',
					'closedDays' => array( 0 ),
					'timezone'   => 'Europe/Amsterdam',
				),
				'reminders'              => array( 'days' => array( 14, 7, 1 ) ),
				'holidays'               => array( '2025-01-01', '2025-04-27', '2025-12-25', '2025-12-26' ),
				'manualClosures'         => array(),
				'durations'              => array( 15, 30, 45, 60, 90, 120 ),
				'bookingRules'           => array(
					'bufferMinutes' => 0,
					'maxFutureDays' => 365,
					'maxPerDay'     => 0,
				),
				'notifications'          => array(
					'emailEnabled' => true,
				),
				'templates'              => array(
					'reminder' => array(
						'subject' => 'Appointment: {service_name} - {date}',
						'body'    => "Hi {customer_name},\n\nWe have scheduled a technician ({tech_name}) for {service_name} on {date}.\nLocation: {location_type}\n\nMet vriendelijke groet,\n{company_name}",
					),
				),
				'dashboardWidget'        => array(
					'enabled'   => true,
					'limit'     => 10,
					'daysAhead' => 30,
				),
				'deleteDataOnUninstall'  => false,
			) );
		}
	}

	/**
	 * Add SmartRecur capabilities and map them onto the default WordPress roles.
	 */
	public static function register_capabilities() {
		$caps = array( 'smartrecur_manage', 'smartrecur_book', 'smartrecur_view', 'smartrecur_manage_clients' );

		$role_map = array(
			'administrator' => $caps,
			'editor'        => array( 'smartrecur_book', 'smartrecur_view', 'smartrecur_manage_clients' ),
			'subscriber'    => array( 'smartrecur_view' ),
		);

		foreach ( $role_map as $role_slug => $role_caps ) {
			$role = get_role( $role_slug );
			if ( ! $role ) {
				continue;
			}
			foreach ( $role_caps as $cap ) {
				$role->add_cap( $cap );
			}
		}
	}
}

<?php
/**
 * REST controller for plugin settings (single serialized option).
 *
 * @package SmartRecur
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SmartRecur_Settings_Controller extends WP_REST_Controller {

	use SmartRecur_REST_Security;

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	protected $namespace = SMARTRECUR_REST_NAMESPACE;

	/**
	 * REST base.
	 *
	 * @var string
	 */
	protected $rest_base = 'settings';

	/**
	 * Register routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_settings' ),
					'permission_callback' => $this->require_cap( 'smartrecur_view' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_settings' ),
					'permission_callback' => $this->require_cap( 'smartrecur_manage' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/backup',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'backup' ),
				'permission_callback' => $this->require_cap( 'smartrecur_manage' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/restore',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'restore' ),
				'permission_callback' => $this->require_cap( 'smartrecur_manage' ),
			)
		);
	}

	/**
	 * GET /settings
	 *
	 * @return WP_REST_Response
	 */
	public function get_settings() {
		$settings = (array) get_option( 'smartrecur_settings', array() );
		return $this->with_no_store( rest_ensure_response( array( 'settings' => $settings ) ) );
	}

	/**
	 * PUT /settings — merges only known top-level keys to avoid drive-by writes.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function update_settings( $request ) {
		$body = $request->get_json_params();
		if ( ! is_array( $body ) ) {
			$body = (array) $request->get_params();
		}

		$current = (array) get_option( 'smartrecur_settings', array() );
		$valid   = array( 'branding', 'businessHours', 'reminders', 'holidays', 'manualClosures', 'durations', 'bookingRules', 'notifications', 'templates', 'deleteDataOnUninstall' );

		foreach ( $body as $key => $value ) {
			if ( ! in_array( $key, $valid, true ) ) {
				continue;
			}
			$current[ $key ] = $value;
		}

		update_option( 'smartrecur_settings', $current );
		return $this->with_no_store( rest_ensure_response( array( 'success' => true ) ) );
	}

	/**
	 * GET /settings/backup — dump the plugin's tables and the settings option as JSON.
	 *
	 * @return WP_REST_Response
	 */
	public function backup() {
		global $wpdb;
		$prefix = $wpdb->prefix;

		$backup = array(
			'version'   => SMARTRECUR_VERSION,
			'timestamp' => time(),
			'data'      => array(
				'settings'    => get_option( 'smartrecur_settings', array() ),
				'events'      => $wpdb->get_results( "SELECT * FROM {$prefix}smartrecur_appointments", ARRAY_A ),
				'customers'   => $wpdb->get_results( "SELECT * FROM {$prefix}smartrecur_clients", ARRAY_A ),
				'services'    => $wpdb->get_results( "SELECT * FROM {$prefix}smartrecur_services", ARRAY_A ),
				'technicians' => $wpdb->get_results( "SELECT * FROM {$prefix}smartrecur_technicians", ARRAY_A ),
			),
		);

		return $this->with_no_store( rest_ensure_response( $backup ) );
	}

	/**
	 * POST /settings/restore — restore a previously exported backup.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function restore( $request ) {
		$body = $request->get_json_params();
		if ( empty( $body['data'] ) ) {
			return new WP_Error( 'smartrecur_bad_backup', __( 'Invalid backup format.', 'smartrecur' ), array( 'status' => 400 ) );
		}

		global $wpdb;
		$prefix = $wpdb->prefix;

		if ( isset( $body['data']['settings'] ) && is_array( $body['data']['settings'] ) ) {
			update_option( 'smartrecur_settings', $body['data']['settings'] );
		}

		foreach ( array( 'events' => 'smartrecur_appointments', 'customers' => 'smartrecur_clients', 'services' => 'smartrecur_services', 'technicians' => 'smartrecur_technicians' ) as $key => $tbl ) {
			if ( empty( $body['data'][ $key ] ) || ! is_array( $body['data'][ $key ] ) ) {
				continue;
			}
			foreach ( $body['data'][ $key ] as $row ) {
				if ( ! is_array( $row ) || empty( $row['id'] ) ) {
					continue;
				}
				$wpdb->replace( $prefix . $tbl, $row );
			}
		}

		return $this->with_no_store( rest_ensure_response( array( 'success' => true, 'message' => 'Backup restored.' ) ) );
	}
}

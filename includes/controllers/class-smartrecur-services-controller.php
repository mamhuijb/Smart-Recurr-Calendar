<?php
/**
 * REST controller for services.
 *
 * @package SmartRecur
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SmartRecur_Services_Controller extends WP_REST_Controller {

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
	protected $rest_base = 'services';

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
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => $this->require_cap( 'smartrecur_view' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => $this->require_cap( 'smartrecur_manage' ),
					'args'                => $this->args_schema(),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[a-f0-9\-]+)',
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => $this->require_cap( 'smartrecur_manage' ),
					'args'                => $this->args_schema(),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => $this->require_cap( 'smartrecur_manage' ),
				),
			)
		);
	}

	/**
	 * Args schema.
	 *
	 * @return array
	 */
	private function args_schema() {
		return array(
			'name'               => array( 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ),
			'type'               => array( 'sanitize_callback' => 'sanitize_text_field' ),
			'defaultDurationMin' => array( 'sanitize_callback' => 'absint' ),
			'defaultLocation'    => array( 'sanitize_callback' => 'sanitize_text_field' ),
			'color'              => array( 'sanitize_callback' => 'sanitize_hex_color' ),
			'createTicket'       => array( 'sanitize_callback' => 'rest_sanitize_boolean' ),
			'reminderDays'       => array( 'sanitize_callback' => array( __CLASS__, 'sanitize_array' ) ),
		);
	}

	/**
	 * GET /services
	 *
	 * @return WP_REST_Response
	 */
	public function get_items() {
		global $wpdb;
		$table = $wpdb->prefix . 'smartrecur_services';
		$rows  = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY name ASC", ARRAY_A );

		$services = array();
		foreach ( (array) $rows as $row ) {
			$services[] = $this->to_frontend( $row );
		}
		return $this->with_no_store( rest_ensure_response( array( 'services' => $services ) ) );
	}

	/**
	 * POST /services
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function create_item( $request ) {
		global $wpdb;
		$table = $wpdb->prefix . 'smartrecur_services';
		$id    = $request['id'] && self::validate_uuid( $request['id'] ) ? $request['id'] : SmartRecur_UUID::v4();

		$email_tpl    = is_array( $request['emailTemplate'] ) ? $request['emailTemplate'] : array();
		$reminders    = self::sanitize_array( $request['reminderDays'] );
		$reminders_js = ! empty( $reminders ) ? wp_json_encode( array_map( 'absint', $reminders ) ) : null;

		$wpdb->insert(
			$table,
			array(
				'id'                     => $id,
				'name'                   => sanitize_text_field( $request['name'] ),
				'type'                   => in_array( $request['type'], array( 'RECURRING', 'ONE_TIME' ), true ) ? $request['type'] : 'RECURRING',
				'default_duration_min'   => (int) ( $request['defaultDurationMin'] ?? 60 ),
				'default_location'       => in_array( $request['defaultLocation'], array( 'REMOTE', 'ON_SITE' ), true ) ? $request['defaultLocation'] : 'ON_SITE',
				'color'                  => $request['color'] ? sanitize_hex_color( $request['color'] ) : '#4F46E5',
				'create_ticket'          => $request['createTicket'] ? 1 : 0,
				'email_template_subject' => isset( $email_tpl['subject'] ) ? sanitize_text_field( $email_tpl['subject'] ) : null,
				'email_template_body'    => isset( $email_tpl['body'] ) ? sanitize_textarea_field( $email_tpl['body'] ) : null,
				'reminder_days'          => $reminders_js,
			),
			array( '%s', '%s', '%s', '%d', '%s', '%s', '%d', '%s', '%s', '%s' )
		);

		return $this->with_no_store( rest_ensure_response( array( 'service' => array( 'id' => $id ) ) ), 201 );
	}

	/**
	 * PUT /services/{id}
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function update_item( $request ) {
		global $wpdb;
		$table = $wpdb->prefix . 'smartrecur_services';
		$id    = sanitize_text_field( $request['id'] );

		$email_tpl    = is_array( $request['emailTemplate'] ) ? $request['emailTemplate'] : array();
		$reminders    = self::sanitize_array( $request['reminderDays'] );
		$reminders_js = ! empty( $reminders ) ? wp_json_encode( array_map( 'absint', $reminders ) ) : null;

		$wpdb->update(
			$table,
			array(
				'name'                   => sanitize_text_field( $request['name'] ),
				'type'                   => in_array( $request['type'], array( 'RECURRING', 'ONE_TIME' ), true ) ? $request['type'] : 'RECURRING',
				'default_duration_min'   => (int) ( $request['defaultDurationMin'] ?? 60 ),
				'default_location'       => in_array( $request['defaultLocation'], array( 'REMOTE', 'ON_SITE' ), true ) ? $request['defaultLocation'] : 'ON_SITE',
				'color'                  => $request['color'] ? sanitize_hex_color( $request['color'] ) : '#4F46E5',
				'create_ticket'          => $request['createTicket'] ? 1 : 0,
				'email_template_subject' => isset( $email_tpl['subject'] ) ? sanitize_text_field( $email_tpl['subject'] ) : null,
				'email_template_body'    => isset( $email_tpl['body'] ) ? sanitize_textarea_field( $email_tpl['body'] ) : null,
				'reminder_days'          => $reminders_js,
			),
			array( 'id' => $id ),
			array( '%s', '%s', '%d', '%s', '%s', '%d', '%s', '%s', '%s' ),
			array( '%s' )
		);

		return $this->with_no_store( rest_ensure_response( array( 'success' => true ) ) );
	}

	/**
	 * DELETE /services/{id}
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function delete_item( $request ) {
		global $wpdb;
		$table = $wpdb->prefix . 'smartrecur_services';
		$wpdb->delete( $table, array( 'id' => sanitize_text_field( $request['id'] ) ), array( '%s' ) );
		return $this->with_no_store( rest_ensure_response( array( 'success' => true ) ) );
	}

	/**
	 * Frontend serialization.
	 *
	 * @param array $row Row.
	 * @return array
	 */
	private function to_frontend( array $row ) {
		$template = null;
		if ( ! empty( $row['email_template_subject'] ) || ! empty( $row['email_template_body'] ) ) {
			$template = array(
				'subject' => $row['email_template_subject'] ?? '',
				'body'    => $row['email_template_body'] ?? '',
			);
		}

		$reminder_days = null;
		if ( ! empty( $row['reminder_days'] ) ) {
			$decoded = json_decode( $row['reminder_days'], true );
			if ( is_array( $decoded ) && count( $decoded ) > 0 ) {
				$reminder_days = $decoded;
			}
		}

		return array(
			'id'                 => $row['id'],
			'name'               => $row['name'],
			'type'               => $row['type'],
			'defaultDurationMin' => (int) $row['default_duration_min'],
			'defaultLocation'    => $row['default_location'],
			'color'              => $row['color'],
			'createTicket'       => (bool) $row['create_ticket'],
			'emailTemplate'      => $template,
			'reminderDays'       => $reminder_days,
		);
	}
}

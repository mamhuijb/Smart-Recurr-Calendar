<?php
/**
 * REST controller for appointments. Maps the legacy /api/events endpoints to
 * /wp-json/smartrecur/v1/appointments with capability + nonce enforcement.
 *
 * @package SmartRecur
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SmartRecur_Appointments_Controller extends WP_REST_Controller {

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
	protected $rest_base = 'appointments';

	/**
	 * Register all routes for the resource.
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
					'permission_callback' => $this->require_cap( 'smartrecur_book' ),
					'args'                => $this->args_schema(),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[a-f0-9\-]+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => $this->require_cap( 'smartrecur_view' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => $this->require_cap( 'smartrecur_book' ),
					'args'                => $this->args_schema(),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => $this->require_cap( 'smartrecur_book' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/generate',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'generate' ),
				'permission_callback' => $this->require_cap( 'smartrecur_book' ),
			)
		);
	}

	/**
	 * Args schema for create/update.
	 *
	 * @return array
	 */
	private function args_schema() {
		return array(
			'title'           => array( 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ),
			'customerId'      => array( 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ),
			'serviceId'       => array( 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ),
			'technicianId'    => array( 'sanitize_callback' => 'sanitize_text_field' ),
			'assetId'         => array( 'sanitize_callback' => 'sanitize_text_field' ),
			'syncroTicketId'  => array( 'sanitize_callback' => 'sanitize_text_field' ),
			'locationType'    => array( 'sanitize_callback' => 'sanitize_text_field' ),
			'description'     => array( 'sanitize_callback' => 'sanitize_textarea_field' ),
			'recurrenceRule'  => array( 'sanitize_callback' => 'sanitize_text_field' ),
			'generatedDates'  => array( 'sanitize_callback' => array( __CLASS__, 'sanitize_array' ) ),
			'startTime'       => array( 'sanitize_callback' => 'sanitize_text_field' ),
			'endTime'         => array( 'sanitize_callback' => 'sanitize_text_field' ),
			'status'          => array( 'sanitize_callback' => 'sanitize_text_field' ),
		);
	}

	/**
	 * GET /appointments
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_items( $request ) {
		global $wpdb;
		$table = $wpdb->prefix . 'smartrecur_appointments';
		$rows  = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC", ARRAY_A );

		$events = array();
		foreach ( (array) $rows as $row ) {
			$events[] = $this->to_frontend( $row );
		}

		return $this->with_no_store( rest_ensure_response( array( 'events' => $events ) ) );
	}

	/**
	 * GET /appointments/{id}
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_item( $request ) {
		global $wpdb;
		$table = $wpdb->prefix . 'smartrecur_appointments';
		$id    = sanitize_text_field( $request['id'] );
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %s", $id ), ARRAY_A );
		if ( ! $row ) {
			return new WP_Error( 'smartrecur_not_found', __( 'Appointment not found.', 'smartrecur' ), array( 'status' => 404 ) );
		}
		return $this->with_no_store( rest_ensure_response( array( 'event' => $this->to_frontend( $row ) ) ) );
	}

	/**
	 * POST /appointments
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_item( $request ) {
		$actor = SmartRecur_Rate_Limiter::actor();
		if ( ! SmartRecur_Rate_Limiter::check( 'book:' . $actor, 30, 60 ) ) {
			return new WP_Error( 'smartrecur_rate_limited', __( 'Too many booking attempts. Slow down.', 'smartrecur' ), array( 'status' => 429 ) );
		}

		global $wpdb;
		$table = $wpdb->prefix . 'smartrecur_appointments';
		$id    = $request['id'] && self::validate_uuid( $request['id'] ) ? $request['id'] : SmartRecur_UUID::v4();

		$wpdb->insert(
			$table,
			array(
				'id'               => $id,
				'title'            => sanitize_text_field( $request['title'] ),
				'client_id'        => sanitize_text_field( $request['customerId'] ),
				'service_id'       => sanitize_text_field( $request['serviceId'] ),
				'technician_id'    => $request['technicianId'] ? sanitize_text_field( $request['technicianId'] ) : null,
				'asset_id'         => $request['assetId'] ? sanitize_text_field( $request['assetId'] ) : null,
				'syncro_ticket_id' => $request['syncroTicketId'] ? sanitize_text_field( $request['syncroTicketId'] ) : null,
				'location_type'    => in_array( $request['locationType'], array( 'REMOTE', 'ON_SITE' ), true ) ? $request['locationType'] : 'ON_SITE',
				'description'      => sanitize_textarea_field( (string) $request['description'] ),
				'recurrence_rule'  => sanitize_text_field( (string) $request['recurrenceRule'] ),
				'generated_dates'  => wp_json_encode( self::sanitize_array( $request['generatedDates'] ) ),
				'start_time'       => $request['startTime'] ? sanitize_text_field( $request['startTime'] ) : null,
				'end_time'         => $request['endTime'] ? sanitize_text_field( $request['endTime'] ) : null,
				'status'           => in_array( $request['status'], array( 'SCHEDULED', 'COMPLETED', 'MISSED' ), true ) ? $request['status'] : 'SCHEDULED',
				'created_by'       => get_current_user_id(),
				'modified_by'      => get_current_user_id(),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d' )
		);

		$row    = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %s", $id ), ARRAY_A );
		$frontend = $this->to_frontend( $row );
		$this->purge_caches();

		do_action( 'smartrecur_appointment_saved', $id, $frontend );

		return $this->with_no_store( rest_ensure_response( array( 'event' => $frontend ) ), 201 );
	}

	/**
	 * PUT /appointments/{id}
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_item( $request ) {
		global $wpdb;
		$table = $wpdb->prefix . 'smartrecur_appointments';
		$id    = sanitize_text_field( $request['id'] );

		$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE id = %s", $id ) );
		if ( ! $exists ) {
			return new WP_Error( 'smartrecur_not_found', __( 'Appointment not found.', 'smartrecur' ), array( 'status' => 404 ) );
		}

		$wpdb->update(
			$table,
			array(
				'title'            => sanitize_text_field( $request['title'] ),
				'client_id'        => sanitize_text_field( $request['customerId'] ),
				'service_id'       => sanitize_text_field( $request['serviceId'] ),
				'technician_id'    => $request['technicianId'] ? sanitize_text_field( $request['technicianId'] ) : null,
				'asset_id'         => $request['assetId'] ? sanitize_text_field( $request['assetId'] ) : null,
				'syncro_ticket_id' => $request['syncroTicketId'] ? sanitize_text_field( $request['syncroTicketId'] ) : null,
				'location_type'    => in_array( $request['locationType'], array( 'REMOTE', 'ON_SITE' ), true ) ? $request['locationType'] : 'ON_SITE',
				'description'      => sanitize_textarea_field( (string) $request['description'] ),
				'recurrence_rule'  => sanitize_text_field( (string) $request['recurrenceRule'] ),
				'generated_dates'  => wp_json_encode( self::sanitize_array( $request['generatedDates'] ) ),
				'start_time'       => $request['startTime'] ? sanitize_text_field( $request['startTime'] ) : null,
				'end_time'         => $request['endTime'] ? sanitize_text_field( $request['endTime'] ) : null,
				'status'           => in_array( $request['status'], array( 'SCHEDULED', 'COMPLETED', 'MISSED' ), true ) ? $request['status'] : 'SCHEDULED',
				'modified_by'      => get_current_user_id(),
			),
			array( 'id' => $id ),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d' ),
			array( '%s' )
		);

		$this->purge_caches();

		$row      = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %s", $id ), ARRAY_A );
		$frontend = $row ? $this->to_frontend( $row ) : array( 'id' => $id );
		do_action( 'smartrecur_appointment_saved', $id, $frontend );

		return $this->with_no_store( rest_ensure_response( array( 'success' => true ) ) );
	}

	/**
	 * DELETE /appointments/{id}
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function delete_item( $request ) {
		global $wpdb;
		$table = $wpdb->prefix . 'smartrecur_appointments';
		$id    = sanitize_text_field( $request['id'] );

		// Fire the hook before deleting so listeners can read o365_event_id etc.
		do_action( 'smartrecur_appointment_deleted', $id );

		$wpdb->delete( $table, array( 'id' => $id ), array( '%s' ) );
		$this->purge_caches();
		return $this->with_no_store( rest_ensure_response( array( 'success' => true ) ) );
	}

	/**
	 * POST /appointments/generate — preview the occurrence dates for a structured
	 * recurrence rule, without saving. Powers the live preview in the booking form.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function generate( $request ) {
		$config = array(
			'frequency'    => in_array( $request['frequency'], array( 'YEARLY', 'HALF_YEARLY', 'QUARTERLY', 'MONTHLY' ), true ) ? $request['frequency'] : 'MONTHLY',
			'pattern_type' => ( 'RELATIVE' === $request['patternType'] ) ? 'RELATIVE' : 'ABSOLUTE',
			'day_of_month' => absint( $request['dayOfMonth'] ?? 1 ),
			'ordinal'      => (int) ( $request['ordinal'] ?? 1 ),
			'weekday'      => absint( $request['weekday'] ?? 1 ),
			'start_month'  => absint( $request['startMonth'] ?? gmdate( 'n' ) ),
			'start_year'   => absint( $request['startYear'] ?? gmdate( 'Y' ) ),
			'max_years'    => 5,
		);

		$dates = SmartRecur_Recurrence_Engine::generate( $config );
		$rule  = SmartRecur_Recurrence_Engine::describe( $config );

		return $this->with_no_store(
			rest_ensure_response(
				array(
					'dates' => $dates,
					'rule'  => $rule,
					'count' => count( $dates ),
				)
			)
		);
	}

	/**
	 * Convert a DB row to the camelCase shape the frontend expects.
	 *
	 * @param array $row Row.
	 * @return array
	 */
	private function to_frontend( array $row ) {
		$generated = json_decode( $row['generated_dates'] ?? '[]', true );
		return array(
			'id'             => $row['id'],
			'title'          => $row['title'],
			'customerId'     => $row['client_id'],
			'serviceId'      => $row['service_id'],
			'technicianId'   => $row['technician_id'] ?? '',
			'assetId'        => $row['asset_id'] ?? '',
			'syncroTicketId' => $row['syncro_ticket_id'] ?? '',
			'locationType'   => $row['location_type'],
			'description'    => $row['description'] ?? '',
			'recurrenceRule' => $row['recurrence_rule'] ?? '',
			'generatedDates' => is_array( $generated ) ? $generated : array(),
			'startTime'      => $row['start_time'] ?? null,
			'endTime'        => $row['end_time'] ?? null,
			'status'         => $row['status'],
			'createdAt'      => isset( $row['created_at'] ) ? strtotime( $row['created_at'] ) * 1000 : 0,
		);
	}
}

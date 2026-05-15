<?php
/**
 * REST controller for calendar-view aggregation. Combines appointments, holidays,
 * and manual closures within a range so the frontend can render a calendar with
 * a single network call.
 *
 * @package SmartRecur
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SmartRecur_Calendar_Controller extends WP_REST_Controller {

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
	protected $rest_base = 'calendar';

	/**
	 * Register routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_calendar' ),
				'permission_callback' => $this->require_cap( 'smartrecur_view' ),
				'args'                => array(
					'from'     => array( 'sanitize_callback' => 'sanitize_text_field' ),
					'to'       => array( 'sanitize_callback' => 'sanitize_text_field' ),
					'clientId' => array( 'sanitize_callback' => 'sanitize_text_field' ),
				),
			)
		);
	}

	/**
	 * GET /calendar?from=YYYY-MM-DD&to=YYYY-MM-DD&clientId=...
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_calendar( $request ) {
		global $wpdb;
		$table = $wpdb->prefix . 'smartrecur_appointments';

		$from      = $request['from'] ? gmdate( 'Y-m-d', strtotime( $request['from'] ) ) : gmdate( 'Y-m-01' );
		$to        = $request['to'] ? gmdate( 'Y-m-d', strtotime( $request['to'] ) ) : gmdate( 'Y-m-t', strtotime( '+11 months', strtotime( $from ) ) );
		$client_id = $request['clientId'] ? sanitize_text_field( $request['clientId'] ) : '';

		if ( $client_id ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE client_id = %s ORDER BY created_at DESC",
					$client_id
				),
				ARRAY_A
			);
		} else {
			$rows = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC", ARRAY_A );
		}

		$events = array();
		foreach ( (array) $rows as $row ) {
			$dates = json_decode( $row['generated_dates'] ?? '[]', true );
			$dates = is_array( $dates ) ? $dates : array();

			$filtered = array();
			foreach ( $dates as $date ) {
				if ( $date >= $from && $date <= $to ) {
					$filtered[] = $date;
				}
			}
			if ( empty( $filtered ) ) {
				continue;
			}

			$events[] = array(
				'id'             => $row['id'],
				'title'          => $row['title'],
				'customerId'     => $row['client_id'],
				'serviceId'      => $row['service_id'],
				'technicianId'   => $row['technician_id'] ?? '',
				'generatedDates' => $filtered,
				'startTime'      => $row['start_time'] ?? null,
				'endTime'        => $row['end_time'] ?? null,
				'locationType'   => $row['location_type'],
				'status'         => $row['status'],
			);
		}

		$settings = (array) get_option( 'smartrecur_settings', array() );
		$response = rest_ensure_response(
			array(
				'from'           => $from,
				'to'             => $to,
				'events'         => $events,
				'holidays'       => $settings['holidays'] ?? array(),
				'manualClosures' => $settings['manualClosures'] ?? array(),
				'businessHours'  => $settings['businessHours'] ?? array(),
			)
		);

		return $this->with_no_store( $response );
	}
}

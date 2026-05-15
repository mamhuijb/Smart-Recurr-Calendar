<?php
/**
 * REST controller for reusable recurring rule definitions.
 *
 * @package SmartRecur
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SmartRecur_Recurring_Rules_Controller extends WP_REST_Controller {

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
	protected $rest_base = 'recurring-rules';

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
					'permission_callback' => $this->require_cap( 'smartrecur_book' ),
					'args'                => array(
						'name'           => array( 'sanitize_callback' => 'sanitize_text_field' ),
						'ruleText'       => array( 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ),
						'generatedDates' => array( 'sanitize_callback' => array( __CLASS__, 'sanitize_array' ) ),
					),
				),
			)
		);
	}

	/**
	 * GET /recurring-rules
	 *
	 * @return WP_REST_Response
	 */
	public function get_items() {
		global $wpdb;
		$table = $wpdb->prefix . 'smartrecur_recurring_rules';
		$rows  = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC", ARRAY_A );

		$rules = array();
		foreach ( (array) $rows as $row ) {
			$rules[] = array(
				'id'             => $row['id'],
				'name'           => $row['name'],
				'ruleText'       => $row['rule_text'],
				'generatedDates' => json_decode( $row['generated_dates'], true ) ?: array(),
				'createdAt'      => isset( $row['created_at'] ) ? strtotime( $row['created_at'] ) * 1000 : 0,
			);
		}
		return $this->with_no_store( rest_ensure_response( array( 'rules' => $rules ) ) );
	}

	/**
	 * POST /recurring-rules
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function create_item( $request ) {
		global $wpdb;
		$table = $wpdb->prefix . 'smartrecur_recurring_rules';
		$id    = SmartRecur_UUID::v4();

		$wpdb->insert(
			$table,
			array(
				'id'              => $id,
				'name'            => sanitize_text_field( (string) $request['name'] ),
				'rule_text'       => sanitize_text_field( $request['ruleText'] ),
				'generated_dates' => wp_json_encode( self::sanitize_array( $request['generatedDates'] ) ),
			),
			array( '%s', '%s', '%s', '%s' )
		);

		return $this->with_no_store( rest_ensure_response( array( 'rule' => array( 'id' => $id ) ) ) )->set_status( 201 );
	}
}

<?php
/**
 * REST controller for technicians.
 *
 * @package SmartRecur
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SmartRecur_Technicians_Controller extends WP_REST_Controller {

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
	protected $rest_base = 'technicians';

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
	 * GET /technicians
	 *
	 * @return WP_REST_Response
	 */
	public function get_items() {
		global $wpdb;
		$table = $wpdb->prefix . 'smartrecur_technicians';
		$rows  = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY name ASC", ARRAY_A );

		$techs = array();
		foreach ( (array) $rows as $row ) {
			$techs[] = $this->to_frontend( $row );
		}
		return $this->with_no_store( rest_ensure_response( array( 'technicians' => $techs ) ) );
	}

	/**
	 * POST /technicians
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function create_item( $request ) {
		global $wpdb;
		$table = $wpdb->prefix . 'smartrecur_technicians';
		$id    = $request['id'] && self::validate_uuid( $request['id'] ) ? $request['id'] : SmartRecur_UUID::v4();

		$wpdb->insert(
			$table,
			array(
				'id'     => $id,
				'name'   => sanitize_text_field( (string) $request['name'] ),
				'email'  => sanitize_email( (string) $request['email'] ),
				'color'  => $request['color'] ? sanitize_hex_color( $request['color'] ) : '#10B981',
				'skills' => wp_json_encode( self::sanitize_array( $request['skills'] ) ),
			),
			array( '%s', '%s', '%s', '%s', '%s' )
		);

		return $this->with_no_store( rest_ensure_response( array( 'technician' => array( 'id' => $id ) ) ) )->set_status( 201 );
	}

	/**
	 * PUT /technicians/{id}
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function update_item( $request ) {
		global $wpdb;
		$table = $wpdb->prefix . 'smartrecur_technicians';
		$wpdb->update(
			$table,
			array(
				'name'   => sanitize_text_field( (string) $request['name'] ),
				'email'  => sanitize_email( (string) $request['email'] ),
				'color'  => $request['color'] ? sanitize_hex_color( $request['color'] ) : '#10B981',
				'skills' => wp_json_encode( self::sanitize_array( $request['skills'] ) ),
			),
			array( 'id' => sanitize_text_field( $request['id'] ) ),
			array( '%s', '%s', '%s', '%s' ),
			array( '%s' )
		);
		return $this->with_no_store( rest_ensure_response( array( 'success' => true ) ) );
	}

	/**
	 * DELETE /technicians/{id}
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function delete_item( $request ) {
		global $wpdb;
		$table = $wpdb->prefix . 'smartrecur_technicians';
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
		return array(
			'id'     => $row['id'],
			'name'   => $row['name'],
			'email'  => $row['email'] ?? '',
			'color'  => $row['color'],
			'skills' => json_decode( $row['skills'] ?? '[]', true ) ?: array(),
		);
	}
}

<?php
/**
 * REST controller for clients (formerly "customers").
 *
 * @package SmartRecur
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SmartRecur_Clients_Controller extends WP_REST_Controller {

	use SmartRecur_REST_Security;

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	protected $namespace = SMARTRECUR_REST_NAMESPACE;

	/**
	 * REST base. Exposed as "clients", the legacy frontend still calls it "customers"
	 * via a second alias for backwards compatibility with already-built bundles.
	 *
	 * @var string
	 */
	protected $rest_base = 'clients';

	/**
	 * Register routes (and a /customers alias).
	 */
	public function register_routes() {
		foreach ( array( 'clients', 'customers' ) as $base ) {
			register_rest_route(
				$this->namespace,
				'/' . $base,
				array(
					array(
						'methods'             => WP_REST_Server::READABLE,
						'callback'            => array( $this, 'get_items' ),
						'permission_callback' => $this->require_cap( 'smartrecur_view' ),
					),
					array(
						'methods'             => WP_REST_Server::CREATABLE,
						'callback'            => array( $this, 'create_item' ),
						'permission_callback' => $this->require_cap( 'smartrecur_manage_clients' ),
						'args'                => $this->args_schema(),
					),
				)
			);

			register_rest_route(
				$this->namespace,
				'/' . $base . '/(?P<id>[a-f0-9\-]+)',
				array(
					array(
						'methods'             => WP_REST_Server::EDITABLE,
						'callback'            => array( $this, 'update_item' ),
						'permission_callback' => $this->require_cap( 'smartrecur_manage_clients' ),
						'args'                => $this->args_schema(),
					),
					array(
						'methods'             => WP_REST_Server::DELETABLE,
						'callback'            => array( $this, 'delete_item' ),
						'permission_callback' => $this->require_cap( 'smartrecur_manage_clients' ),
					),
				)
			);
		}
	}

	/**
	 * Args schema.
	 *
	 * @return array
	 */
	private function args_schema() {
		return array(
			'company'         => array( 'sanitize_callback' => 'sanitize_text_field' ),
			'name'            => array( 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ),
			'email'           => array( 'sanitize_callback' => 'sanitize_email' ),
			'phone'           => array( 'sanitize_callback' => 'sanitize_text_field' ),
			'address'         => array( 'sanitize_callback' => 'sanitize_textarea_field' ),
			'postcode'        => array( 'sanitize_callback' => 'sanitize_text_field' ),
			'syncroId'        => array( 'sanitize_callback' => 'sanitize_text_field' ),
			'invoiceninjaId'  => array( 'sanitize_callback' => 'sanitize_text_field' ),
		);
	}

	/**
	 * GET /clients
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function get_items( $request ) {
		global $wpdb;
		$clients_table = $wpdb->prefix . 'smartrecur_clients';
		$assets_table  = $wpdb->prefix . 'smartrecur_assets';

		$rows = $wpdb->get_results( "SELECT * FROM {$clients_table} ORDER BY company ASC, name ASC", ARRAY_A );

		$out = array();
		foreach ( (array) $rows as $row ) {
			$assets        = $wpdb->get_results( $wpdb->prepare( "SELECT id, name, type FROM {$assets_table} WHERE client_id = %s", $row['id'] ), ARRAY_A );
			$row['assets'] = is_array( $assets ) ? $assets : array();
			$out[]         = $this->to_frontend( $row );
		}

		return $this->with_no_store( rest_ensure_response( array( 'customers' => $out ) ) );
	}

	/**
	 * POST /clients
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function create_item( $request ) {
		global $wpdb;
		$table = $wpdb->prefix . 'smartrecur_clients';
		$id    = $request['id'] && self::validate_uuid( $request['id'] ) ? $request['id'] : SmartRecur_UUID::v4();

		$wpdb->insert(
			$table,
			array(
				'id'              => $id,
				'company'         => sanitize_text_field( (string) $request['company'] ),
				'name'            => sanitize_text_field( $request['name'] ),
				'email'           => sanitize_email( (string) $request['email'] ),
				'phone'           => sanitize_text_field( (string) $request['phone'] ),
				'address'         => sanitize_textarea_field( (string) $request['address'] ),
				'postcode'        => sanitize_text_field( (string) $request['postcode'] ),
				'syncro_id'       => $request['syncroId'] ? sanitize_text_field( $request['syncroId'] ) : null,
				'invoiceninja_id' => $request['invoiceninjaId'] ? sanitize_text_field( $request['invoiceninjaId'] ) : null,
				'created_by'      => get_current_user_id(),
				'modified_by'     => get_current_user_id(),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d' )
		);

		return $this->with_no_store( rest_ensure_response( array( 'customer' => array( 'id' => $id ) ) ), 201 );
	}

	/**
	 * PUT /clients/{id}
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function update_item( $request ) {
		global $wpdb;
		$table = $wpdb->prefix . 'smartrecur_clients';
		$id    = sanitize_text_field( $request['id'] );

		$wpdb->update(
			$table,
			array(
				'company'     => sanitize_text_field( (string) $request['company'] ),
				'name'        => sanitize_text_field( $request['name'] ),
				'email'       => sanitize_email( (string) $request['email'] ),
				'phone'       => sanitize_text_field( (string) $request['phone'] ),
				'address'     => sanitize_textarea_field( (string) $request['address'] ),
				'postcode'    => sanitize_text_field( (string) $request['postcode'] ),
				'syncro_id'   => $request['syncroId'] ? sanitize_text_field( $request['syncroId'] ) : null,
				'modified_by' => get_current_user_id(),
			),
			array( 'id' => $id ),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d' ),
			array( '%s' )
		);

		return $this->with_no_store( rest_ensure_response( array( 'success' => true ) ) );
	}

	/**
	 * DELETE /clients/{id} (cascades to assets in a transaction).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function delete_item( $request ) {
		global $wpdb;
		$clients_table = $wpdb->prefix . 'smartrecur_clients';
		$assets_table  = $wpdb->prefix . 'smartrecur_assets';
		$id            = sanitize_text_field( $request['id'] );

		$wpdb->delete( $assets_table, array( 'client_id' => $id ), array( '%s' ) );
		$wpdb->delete( $clients_table, array( 'id' => $id ), array( '%s' ) );

		return $this->with_no_store( rest_ensure_response( array( 'success' => true ) ) );
	}

	/**
	 * Frontend serialization.
	 *
	 * @param array $row DB row with optional 'assets' key already merged.
	 * @return array
	 */
	private function to_frontend( array $row ) {
		return array(
			'id'             => $row['id'],
			'company'        => $row['company'] ?? '',
			'name'           => $row['name'],
			'email'          => $row['email'] ?? '',
			'phone'          => $row['phone'] ?? '',
			'address'        => $row['address'] ?? '',
			'postcode'       => $row['postcode'] ?? '',
			'syncroId'       => $row['syncro_id'] ?? '',
			'invoiceninjaId' => $row['invoiceninja_id'] ?? '',
			'assets'         => $row['assets'] ?? array(),
		);
	}
}

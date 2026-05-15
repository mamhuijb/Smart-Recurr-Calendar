<?php
/**
 * Syncro MSP integration.
 *
 * @package SmartRecur
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SmartRecur_Syncro {

	/**
	 * Build a vetted base URL for the configured Syncro subdomain.
	 *
	 * @param array $config Config.
	 * @return string|false
	 */
	private static function base_url( array $config ) {
		$subdomain = $config['subdomain'] ?? '';
		if ( ! SmartRecur_SSRF_Guard::is_valid_subdomain( $subdomain ) ) {
			return false;
		}
		return 'https://' . $subdomain . '.syncromsp.com';
	}

	/**
	 * Test connectivity.
	 *
	 * @param array $config Config.
	 * @return array [bool, string]
	 */
	public static function test( array $config ) {
		$base = self::base_url( $config );
		if ( ! $base || empty( $config['apiKey'] ) ) {
			return array( false, __( 'API Key and Subdomain are required.', 'smartrecur' ) );
		}

		$result = self::http_get( $base . '/api/v1/customers?page=1', $config['apiKey'] );
		if ( isset( $result['error'] ) ) {
			return array( false, $result['error'] );
		}

		$count = $result['meta']['total_entries'] ?? count( $result['customers'] ?? array() );
		return array( true, sprintf( __( 'Connected. Found %d customers.', 'smartrecur' ), $count ) );
	}

	/**
	 * Fetch the first page of customers, mapping them to the SmartRecur shape.
	 *
	 * @param array $config Config.
	 * @return array|WP_Error
	 */
	public static function get_customers( array $config ) {
		$base = self::base_url( $config );
		if ( ! $base || empty( $config['apiKey'] ) ) {
			return new WP_Error( 'smartrecur_syncro_config', __( 'Syncro is not configured.', 'smartrecur' ), array( 'status' => 400 ) );
		}

		$result = self::http_get( $base . '/api/v1/customers?page=1', $config['apiKey'] );
		if ( ! isset( $result['customers'] ) ) {
			return new WP_Error( 'smartrecur_syncro_fetch', __( 'Failed to fetch customers from Syncro.', 'smartrecur' ), array( 'status' => 502 ) );
		}

		$out = array();
		foreach ( $result['customers'] as $c ) {
			$out[] = array(
				'id'       => (string) $c['id'],
				'name'     => trim( ( $c['firstname'] ?? '' ) . ' ' . ( $c['lastname'] ?? '' ) ),
				'company'  => $c['business_name'] ?? trim( ( $c['firstname'] ?? '' ) . ' ' . ( $c['lastname'] ?? '' ) ),
				'email'    => $c['email'] ?? '',
				'phone'    => $c['phone'] ?? '',
				'address'  => $c['address'] ?? '',
				'syncroId' => (string) $c['id'],
			);
		}
		return $out;
	}

	/**
	 * Create a ticket in Syncro.
	 *
	 * @param array $config Config.
	 * @param array $body   Request data (customerId / subject / description).
	 * @return array|WP_Error
	 */
	public static function create_ticket( array $config, array $body ) {
		$base = self::base_url( $config );
		if ( ! $base || empty( $config['apiKey'] ) ) {
			return new WP_Error( 'smartrecur_syncro_config', __( 'Syncro is not configured.', 'smartrecur' ), array( 'status' => 400 ) );
		}

		$payload = array(
			'customer_id' => (int) ( $body['customerId'] ?? 0 ),
			'subject'     => $body['subject'] ?: 'SmartRecur Appointment',
			'description' => $body['description'] ?: '',
			'status'      => 'New',
		);
		$result = self::http_post_json( $base . '/api/v1/tickets', $payload, $config['apiKey'] );

		if ( ! isset( $result['ticket'] ) ) {
			return new WP_Error( 'smartrecur_syncro_ticket', __( 'Failed to create Syncro ticket.', 'smartrecur' ), array( 'status' => 502 ) );
		}
		return array( 'ticketId' => (string) ( $result['ticket']['number'] ?? $result['ticket']['id'] ?? '' ) );
	}

	/**
	 * GET helper.
	 *
	 * @param string $url     URL.
	 * @param string $api_key Bearer.
	 * @return array
	 */
	private static function http_get( $url, $api_key ) {
		if ( ! SmartRecur_SSRF_Guard::is_safe_url( $url ) ) {
			return array( 'error' => 'Blocked unsafe URL.' );
		}
		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 15,
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Accept'        => 'application/json',
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			return array( 'error' => $response->get_error_message() );
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( $code >= 400 ) {
			return array( 'error' => 'HTTP ' . $code );
		}
		return is_array( $body ) ? $body : array();
	}

	/**
	 * POST JSON helper.
	 *
	 * @param string $url     URL.
	 * @param array  $data    Body.
	 * @param string $api_key Bearer.
	 * @return array
	 */
	private static function http_post_json( $url, array $data, $api_key ) {
		if ( ! SmartRecur_SSRF_Guard::is_safe_url( $url ) ) {
			return array( 'error' => 'Blocked unsafe URL.' );
		}
		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 15,
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
					'Accept'        => 'application/json',
				),
				'body'    => wp_json_encode( $data ),
			)
		);
		if ( is_wp_error( $response ) ) {
			return array( 'error' => $response->get_error_message() );
		}
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		return is_array( $body ) ? $body : array();
	}
}

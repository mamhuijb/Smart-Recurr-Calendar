<?php
/**
 * Invoice Ninja v5 integration.
 *
 * @package SmartRecur
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SmartRecur_Invoice_Ninja {

	/**
	 * Test connectivity against /api/v1/ping.
	 *
	 * @param array $config Config.
	 * @return array [bool, string]
	 */
	public static function test( array $config ) {
		$endpoint = rtrim( (string) ( $config['endpoint'] ?? 'https://app.invoiceninja.com' ), '/' );

		if ( empty( $config['apiKey'] ) ) {
			return array( false, __( 'API Key is required.', 'smartrecur' ) );
		}
		if ( ! SmartRecur_SSRF_Guard::is_safe_url( $endpoint ) ) {
			return array( false, __( 'Endpoint must be HTTPS with a public hostname.', 'smartrecur' ) );
		}

		$result = self::http_get( $endpoint . '/api/v1/ping', $config['apiKey'] );
		if ( isset( $result['error'] ) ) {
			return array( false, $result['error'] );
		}
		return array( true, __( 'Connected to Invoice Ninja.', 'smartrecur' ) );
	}

	/**
	 * GET /api/v1/clients?status=active.
	 *
	 * @param array $config Config.
	 * @return array|WP_Error
	 */
	public static function get_clients( array $config ) {
		$endpoint = rtrim( (string) ( $config['endpoint'] ?? 'https://app.invoiceninja.com' ), '/' );

		if ( empty( $config['apiKey'] ) || ! SmartRecur_SSRF_Guard::is_safe_url( $endpoint ) ) {
			return new WP_Error( 'smartrecur_in_config', __( 'Invoice Ninja is not configured.', 'smartrecur' ), array( 'status' => 400 ) );
		}

		$result = self::http_get( $endpoint . '/api/v1/clients?per_page=100&status=active', $config['apiKey'] );
		if ( ! isset( $result['data'] ) ) {
			return new WP_Error( 'smartrecur_in_fetch', __( 'Failed to fetch clients from Invoice Ninja.', 'smartrecur' ), array( 'status' => 502 ) );
		}
		return $result['data'];
	}

	/**
	 * GET helper. Returns plain array on success or ['error' => string] on failure.
	 *
	 * @param string $url     URL.
	 * @param string $api_key API key.
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
					'X-Api-Token'      => $api_key,
					'Accept'           => 'application/json',
					'X-Requested-With' => 'XMLHttpRequest',
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			return array( 'error' => $response->get_error_message() );
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$json = json_decode( $body, true );

		if ( $code >= 400 ) {
			return array( 'error' => 'HTTP ' . $code . ': ' . substr( $body, 0, 200 ) );
		}

		// /ping returns plain text "pong".
		if ( null === $json && false !== stripos( $body, 'pong' ) ) {
			return array( 'status' => 'ok' );
		}

		return is_array( $json ) ? $json : array();
	}
}

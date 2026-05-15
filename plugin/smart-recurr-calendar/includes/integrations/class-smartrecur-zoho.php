<?php
/**
 * Zoho CRM / Books integration.
 *
 * @package SmartRecur
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SmartRecur_Zoho {

	/**
	 * Test connectivity. Tries CRM /org first, falls back to Books /organizations.
	 *
	 * @param array $config Config.
	 * @return array [bool, string]
	 */
	public static function test( array $config ) {
		$endpoint = rtrim( (string) ( $config['endpoint'] ?? 'https://www.zohoapis.com' ), '/' );
		if ( empty( $config['apiKey'] ) ) {
			return array( false, __( 'API Key / Access Token is required.', 'smartrecur' ) );
		}
		if ( ! SmartRecur_SSRF_Guard::is_valid_zoho_endpoint( $endpoint ) ) {
			return array( false, __( 'Endpoint must be a Zoho API domain (zohoapis.*).', 'smartrecur' ) );
		}

		$result = self::http_get( $endpoint . '/crm/v2/org', $config['apiKey'] );
		if ( isset( $result['org'] ) || isset( $result['data'] ) ) {
			$org = $result['data'][0]['company_name'] ?? $result['org'][0]['company_name'] ?? 'Unknown';
			return array( true, sprintf( __( 'Connected to Zoho (%s).', 'smartrecur' ), $org ) );
		}

		$result = self::http_get( $endpoint . '/books/v3/organizations', $config['apiKey'] );
		if ( isset( $result['organizations'] ) ) {
			$org = $result['organizations'][0]['name'] ?? 'Unknown';
			return array( true, sprintf( __( 'Connected to Zoho Books (%s).', 'smartrecur' ), $org ) );
		}

		return array( false, $result['message'] ?? __( 'Zoho connection failed.', 'smartrecur' ) );
	}

	/**
	 * GET helper using Zoho-oauthtoken auth header.
	 *
	 * @param string $url   URL.
	 * @param string $token Access token.
	 * @return array
	 */
	private static function http_get( $url, $token ) {
		if ( ! SmartRecur_SSRF_Guard::is_valid_zoho_endpoint( $url ) ) {
			return array( 'message' => 'Blocked unsafe URL.' );
		}
		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 15,
				'headers' => array(
					'Authorization' => 'Zoho-oauthtoken ' . $token,
					'Accept'        => 'application/json',
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			return array( 'message' => $response->get_error_message() );
		}
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		return is_array( $body ) ? $body : array();
	}
}

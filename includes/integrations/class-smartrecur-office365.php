<?php
/**
 * Office 365 (Microsoft Graph) integration. OAuth2 auth-code flow, calendar push,
 * Mail.Send. Uses the WordPress HTTP API.
 *
 * @package SmartRecur
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SmartRecur_Office365 {

	const TOKEN_URL = 'https://login.microsoftonline.com/{tenant}/oauth2/v2.0/token';
	const AUTH_URL  = 'https://login.microsoftonline.com/{tenant}/oauth2/v2.0/authorize';
	const GRAPH     = 'https://graph.microsoft.com/v1.0';
	const SCOPES    = 'User.Read Calendars.ReadWrite Mail.Send offline_access';

	/**
	 * Build the consent URL the user is redirected to during OAuth.
	 *
	 * @param array  $config Integration config.
	 * @param string $state  CSRF state token.
	 * @return string
	 */
	public static function get_auth_url( array $config, $state ) {
		$tenant = ! empty( $config['tenantId'] ) ? $config['tenantId'] : 'common';
		$params = http_build_query( array(
			'client_id'     => $config['clientId'] ?? '',
			'response_type' => 'code',
			'redirect_uri'  => $config['redirectUri'] ?? '',
			'response_mode' => 'query',
			'scope'         => self::SCOPES,
			'state'         => $state,
		) );
		return str_replace( '{tenant}', rawurlencode( $tenant ), self::AUTH_URL ) . '?' . $params;
	}

	/**
	 * Exchange an OAuth code for an access + refresh token.
	 *
	 * @param string $code   Auth code.
	 * @param array  $config Config.
	 * @return array
	 */
	public static function exchange_code( $code, array $config ) {
		$tenant = ! empty( $config['tenantId'] ) ? $config['tenantId'] : 'common';
		$url    = str_replace( '{tenant}', rawurlencode( $tenant ), self::TOKEN_URL );

		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 15,
				'headers' => array( 'Accept' => 'application/json' ),
				'body'    => array(
					'client_id'     => $config['clientId'] ?? '',
					'client_secret' => $config['clientSecret'] ?? '',
					'code'          => $code,
					'redirect_uri'  => $config['redirectUri'] ?? '',
					'grant_type'    => 'authorization_code',
					'scope'         => self::SCOPES,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array( 'success' => false, 'error' => $response->get_error_message() );
		}
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $data['access_token'] ) ) {
			return array( 'success' => false, 'error' => $data['error_description'] ?? 'Token exchange failed' );
		}

		$profile = self::http_get( self::GRAPH . '/me', $data['access_token'] );
		$email   = $profile['mail'] ?? ( $profile['userPrincipalName'] ?? '' );

		return array(
			'success'      => true,
			'accessToken'  => $data['access_token'],
			'refreshToken' => $data['refresh_token'] ?? '',
			'expiresAt'    => time() + (int) ( $data['expires_in'] ?? 3600 ),
			'email'        => $email,
		);
	}

	/**
	 * Refresh tokens via refresh_token grant. Returns updated config or null on failure.
	 *
	 * @param array $config Config.
	 * @return array|null
	 */
	public static function refresh_token( array $config ) {
		if ( empty( $config['refreshToken'] ) ) {
			return null;
		}
		$tenant = ! empty( $config['tenantId'] ) ? $config['tenantId'] : 'common';
		$url    = str_replace( '{tenant}', rawurlencode( $tenant ), self::TOKEN_URL );

		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 15,
				'body'    => array(
					'client_id'     => $config['clientId'] ?? '',
					'client_secret' => $config['clientSecret'] ?? '',
					'refresh_token' => $config['refreshToken'],
					'grant_type'    => 'refresh_token',
					'scope'         => self::SCOPES,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return null;
		}
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $data['access_token'] ) ) {
			return null;
		}

		$config['accessToken']  = $data['access_token'];
		$config['refreshToken'] = $data['refresh_token'] ?? $config['refreshToken'];
		$config['expiresAt']    = time() + (int) ( $data['expires_in'] ?? 3600 );
		return $config;
	}

	/**
	 * Refresh the token in-place if it expires within five minutes. Updates the
	 * caller's array by reference and persists if needed.
	 *
	 * @param array $config Config (mutated).
	 * @return bool True if we have a usable token after this call.
	 */
	public static function ensure_valid_token( array &$config ) {
		if ( empty( $config['accessToken'] ) ) {
			return false;
		}
		$expires = (int) ( $config['expiresAt'] ?? 0 );
		if ( $expires && $expires < ( time() + 300 ) ) {
			$refreshed = self::refresh_token( $config );
			if ( $refreshed ) {
				$config = $refreshed;
				return true;
			}
			return false;
		}
		return true;
	}

	/**
	 * Test connectivity by calling /me.
	 *
	 * @param array $config Config.
	 * @return array [bool, string]
	 */
	public static function test( array $config ) {
		if ( empty( $config['accessToken'] ) ) {
			return array( false, __( 'Not connected. Run the OAuth flow first.', 'smartrecur' ) );
		}
		$result = self::http_get( self::GRAPH . '/me', $config['accessToken'] );
		if ( isset( $result['error'] ) ) {
			return array( false, $result['error']['message'] ?? 'Token rejected by Microsoft Graph.' );
		}
		$email = $result['mail'] ?? ( $result['userPrincipalName'] ?? 'Unknown' );
		return array( true, sprintf( __( 'Connected as %s', 'smartrecur' ), $email ) );
	}

	/**
	 * GET /me/calendars
	 *
	 * @param array $config Config.
	 * @return array
	 */
	public static function get_calendars( array $config ) {
		if ( empty( $config['accessToken'] ) ) {
			return array();
		}
		$result = self::http_get( self::GRAPH . '/me/calendars', $config['accessToken'] );
		return $result['value'] ?? array();
	}

	/**
	 * Create a calendar event.
	 *
	 * @param array  $config      Config.
	 * @param array  $event       Event payload.
	 * @param string $calendar_id Optional calendar id; default calendar is used otherwise.
	 * @return array
	 */
	public static function create_calendar_event( array $config, array $event, $calendar_id = '' ) {
		if ( empty( $config['accessToken'] ) ) {
			return array( 'success' => false, 'error' => 'Not connected to Office 365' );
		}
		$endpoint = $calendar_id
			? self::GRAPH . '/me/calendars/' . rawurlencode( $calendar_id ) . '/events'
			: self::GRAPH . '/me/calendar/events';
		$result = self::http_post_json( $endpoint, $event, $config['accessToken'] );
		if ( isset( $result['id'] ) ) {
			return array( 'success' => true, 'eventId' => $result['id'] );
		}
		return array( 'success' => false, 'error' => $result['error']['message'] ?? 'Failed to create event' );
	}

	/**
	 * GET helper.
	 *
	 * @param string $url   URL.
	 * @param string $token Access token.
	 * @return array
	 */
	private static function http_get( $url, $token ) {
		if ( ! SmartRecur_SSRF_Guard::is_safe_url( $url ) ) {
			return array( 'error' => array( 'message' => 'Blocked unsafe URL.' ) );
		}
		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 15,
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Accept'        => 'application/json',
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			return array( 'error' => array( 'message' => $response->get_error_message() ) );
		}
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		return is_array( $body ) ? $body : array();
	}

	/**
	 * POST JSON helper.
	 *
	 * @param string $url   URL.
	 * @param array  $data  Body.
	 * @param string $token Access token.
	 * @return array
	 */
	private static function http_post_json( $url, array $data, $token ) {
		if ( ! SmartRecur_SSRF_Guard::is_safe_url( $url ) ) {
			return array( 'error' => array( 'message' => 'Blocked unsafe URL.' ) );
		}
		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 15,
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
					'Accept'        => 'application/json',
				),
				'body'    => wp_json_encode( $data ),
			)
		);
		if ( is_wp_error( $response ) ) {
			return array( 'error' => array( 'message' => $response->get_error_message() ) );
		}
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		return is_array( $body ) ? $body : array();
	}
}

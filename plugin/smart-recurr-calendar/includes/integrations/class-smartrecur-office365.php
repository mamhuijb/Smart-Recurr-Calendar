<?php
/**
 * Office 365 (Microsoft Graph) integration.
 *
 * OAuth 2.0 authorization-code flow with PKCE — no client secret required, so
 * the plugin can ship a single multi-tenant client_id and every site just
 * clicks "Connect" without registering its own Azure app.
 *
 * Two-way calendar sync:
 *   - Push: SmartRecur_Sync_Office365::push_appointment() creates/updates
 *     calendar events whenever a SmartRecur appointment changes.
 *   - Pull: SmartRecur_Sync_Office365::pull_calendar() runs on WP-Cron
 *     (every 15 min) and upserts SmartRecur appointments from the configured
 *     Outlook calendar.
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
	 * Default bundled multi-tenant client_id maintained by the plugin author.
	 *
	 * Override per site by defining `SMARTRECUR_O365_CLIENT_ID` in wp-config.php
	 * if you'd rather use your own Azure app registration.
	 *
	 * @return string
	 */
	public static function client_id() {
		if ( defined( 'SMARTRECUR_O365_CLIENT_ID' ) && SMARTRECUR_O365_CLIENT_ID ) {
			return (string) SMARTRECUR_O365_CLIENT_ID;
		}
		// Placeholder — replace with the multi-tenant Application (client) ID
		// from the Azure AD app registration owned by the plugin author.
		// See README "Office 365" section for one-time setup instructions.
		return '00000000-0000-0000-0000-000000000000';
	}

	/**
	 * True when the bundled / configured client_id is something other than the
	 * placeholder. Used to short-circuit the OAuth flow with a clear message.
	 *
	 * @return bool
	 */
	public static function is_configured() {
		$id = self::client_id();
		if ( '' === $id ) {
			return false;
		}
		if ( '00000000-0000-0000-0000-000000000000' === $id ) {
			return false;
		}
		return (bool) preg_match( '/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/', $id );
	}

	/**
	 * Tenant identifier. Multi-tenant apps use `common`; per-site apps may pin
	 * to a single directory ID.
	 *
	 * @param array $config Stored config.
	 * @return string
	 */
	public static function tenant( array $config ) {
		$tenant = ! empty( $config['tenantId'] ) ? (string) $config['tenantId'] : 'common';

		// Reserved Microsoft labels.
		if ( in_array( $tenant, array( 'common', 'organizations', 'consumers' ), true ) ) {
			return $tenant;
		}
		// Tenant GUID.
		if ( preg_match( '/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/', $tenant ) ) {
			return $tenant;
		}
		// Domain (RFC-1035-ish: each label 1-63 chars, no leading/trailing hyphens, dots between).
		if ( preg_match( '/^(?=.{1,253}$)[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?(\.[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?)+$/', $tenant ) ) {
			return $tenant;
		}
		return 'common';
	}

	/**
	 * Build the consent URL for the PKCE auth-code flow.
	 *
	 * @param array  $config         Stored config (must contain redirectUri).
	 * @param string $state          CSRF state token (transient-backed).
	 * @param string $code_challenge S256 PKCE code challenge.
	 * @return string
	 */
	public static function get_auth_url( array $config, $state, $code_challenge ) {
		$tenant = self::tenant( $config );
		$params = http_build_query( array(
			'client_id'             => self::client_id(),
			'response_type'         => 'code',
			'redirect_uri'          => $config['redirectUri'] ?? '',
			'response_mode'         => 'query',
			'scope'                 => self::SCOPES,
			'state'                 => $state,
			'code_challenge'        => $code_challenge,
			'code_challenge_method' => 'S256',
			'prompt'                => 'select_account',
		) );
		return str_replace( '{tenant}', rawurlencode( $tenant ), self::AUTH_URL ) . '?' . $params;
	}

	/**
	 * Exchange an OAuth code + PKCE verifier for access + refresh tokens.
	 *
	 * @param string $code          Authorization code returned by Microsoft.
	 * @param string $code_verifier PKCE verifier originally used to derive the challenge.
	 * @param array  $config        Stored config (redirectUri, tenantId).
	 * @return array success/failure structure.
	 */
	public static function exchange_code( $code, $code_verifier, array $config ) {
		$tenant = self::tenant( $config );
		$url    = str_replace( '{tenant}', rawurlencode( $tenant ), self::TOKEN_URL );

		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 15,
				'headers' => array( 'Accept' => 'application/json' ),
				'body'    => array(
					'client_id'     => self::client_id(),
					'code'          => $code,
					'redirect_uri'  => $config['redirectUri'] ?? '',
					'grant_type'    => 'authorization_code',
					'scope'         => self::SCOPES,
					'code_verifier' => $code_verifier,
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
	 * Refresh tokens via refresh_token grant. PKCE-issued tokens still refresh
	 * with public-client requests (no client_secret needed).
	 *
	 * @param array $config Config (mutated only via copy).
	 * @return array|null
	 */
	public static function refresh_token( array $config ) {
		if ( empty( $config['refreshToken'] ) ) {
			return null;
		}
		$tenant = self::tenant( $config );
		$url    = str_replace( '{tenant}', rawurlencode( $tenant ), self::TOKEN_URL );

		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 15,
				'body'    => array(
					'client_id'     => self::client_id(),
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
	 * Refresh in-place if the token is within 5 minutes of expiry.
	 *
	 * @param array $config Config (mutated by reference).
	 * @return bool True when a usable token is present after this call.
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
			return array( false, __( 'Not connected. Click "Connect to Office 365" first.', 'smartrecur' ) );
		}
		$result = self::http_get( self::GRAPH . '/me', $config['accessToken'] );
		if ( isset( $result['error'] ) ) {
			return array( false, $result['error']['message'] ?? 'Token rejected by Microsoft Graph.' );
		}
		$email = $result['mail'] ?? ( $result['userPrincipalName'] ?? 'Unknown' );
		return array( true, sprintf( __( 'Connected as %s', 'smartrecur' ), $email ) );
	}

	/**
	 * GET /me/calendars.
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
	 * @param string $calendar_id Calendar id (empty = default).
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
	 * Update a calendar event by ID (PATCH).
	 *
	 * @param array  $config      Config.
	 * @param string $event_id    Graph event ID.
	 * @param array  $event       Patch payload.
	 * @param string $calendar_id Calendar id.
	 * @return array
	 */
	public static function update_calendar_event( array $config, $event_id, array $event, $calendar_id = '' ) {
		if ( empty( $config['accessToken'] ) || empty( $event_id ) ) {
			return array( 'success' => false, 'error' => 'Missing token or event id' );
		}
		$endpoint = $calendar_id
			? self::GRAPH . '/me/calendars/' . rawurlencode( $calendar_id ) . '/events/' . rawurlencode( $event_id )
			: self::GRAPH . '/me/calendar/events/' . rawurlencode( $event_id );
		$result = self::http_patch_json( $endpoint, $event, $config['accessToken'] );
		if ( isset( $result['id'] ) ) {
			return array( 'success' => true, 'eventId' => $result['id'] );
		}
		return array( 'success' => false, 'error' => $result['error']['message'] ?? 'Failed to update event' );
	}

	/**
	 * Delete a calendar event.
	 *
	 * @param array  $config      Config.
	 * @param string $event_id    Graph event ID.
	 * @param string $calendar_id Calendar id.
	 * @return bool
	 */
	public static function delete_calendar_event( array $config, $event_id, $calendar_id = '' ) {
		if ( empty( $config['accessToken'] ) || empty( $event_id ) ) {
			return false;
		}
		$endpoint = $calendar_id
			? self::GRAPH . '/me/calendars/' . rawurlencode( $calendar_id ) . '/events/' . rawurlencode( $event_id )
			: self::GRAPH . '/me/calendar/events/' . rawurlencode( $event_id );
		$response = wp_remote_request(
			$endpoint,
			array(
				'method'  => 'DELETE',
				'timeout' => 15,
				'headers' => array(
					'Authorization' => 'Bearer ' . $config['accessToken'],
					'Accept'        => 'application/json',
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			return false;
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		return $code >= 200 && $code < 300;
	}

	/**
	 * List events from a calendar in a date range.
	 *
	 * @param array  $config      Config.
	 * @param string $calendar_id Calendar id.
	 * @param string $from        ISO datetime (UTC) start.
	 * @param string $to          ISO datetime (UTC) end.
	 * @return array
	 */
	public static function list_events( array $config, $calendar_id, $from, $to ) {
		if ( empty( $config['accessToken'] ) || empty( $calendar_id ) ) {
			return array();
		}
		$query = http_build_query( array(
			'startDateTime' => $from,
			'endDateTime'   => $to,
			'$top'          => 250,
			'$orderby'      => 'start/dateTime',
		) );
		$endpoint = self::GRAPH . '/me/calendars/' . rawurlencode( $calendar_id ) . '/calendarView?' . $query;
		$result   = self::http_get( $endpoint, $config['accessToken'] );
		return $result['value'] ?? array();
	}

	/* ---------------------------------------------------------------------
	 * Internal HTTP helpers.
	 * ------------------------------------------------------------------- */

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

	/**
	 * PATCH JSON helper.
	 *
	 * @param string $url   URL.
	 * @param array  $data  Body.
	 * @param string $token Access token.
	 * @return array
	 */
	private static function http_patch_json( $url, array $data, $token ) {
		if ( ! SmartRecur_SSRF_Guard::is_safe_url( $url ) ) {
			return array( 'error' => array( 'message' => 'Blocked unsafe URL.' ) );
		}
		$response = wp_remote_request(
			$url,
			array(
				'method'  => 'PATCH',
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


<?php
/**
 * REST controller for the four supported integrations + email.
 *
 * @package SmartRecur
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SmartRecur_Integrations_Controller extends WP_REST_Controller {

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
	protected $rest_base = 'integrations';

	/**
	 * Register all integration-related routes.
	 */
	public function register_routes() {
		// Status (any logged-in user with view cap can read connection booleans).
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/status',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'status' ),
				'permission_callback' => $this->require_cap( 'smartrecur_view' ),
			)
		);

		// Config save / test (admin only).
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<type>[a-z0-9_-]+)/config',
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'save_config' ),
				'permission_callback' => $this->require_cap( 'smartrecur_manage' ),
			)
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<type>[a-z0-9_-]+)/test',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'test' ),
				'permission_callback' => $this->require_cap( 'smartrecur_manage' ),
			)
		);

		// Office 365.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/office365/connect',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'office365_connect' ),
				'permission_callback' => $this->require_cap( 'smartrecur_manage' ),
			)
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/office365/callback',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'office365_callback' ),
				'permission_callback' => '__return_true', // External OAuth callback validates via state parameter.
			)
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/office365/disconnect',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'office365_disconnect' ),
				'permission_callback' => $this->require_cap( 'smartrecur_manage' ),
			)
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/office365/sync-now',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'office365_sync_now' ),
				'permission_callback' => $this->require_cap( 'smartrecur_manage' ),
			)
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/office365/calendars',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'office365_calendars' ),
				'permission_callback' => $this->require_cap( 'smartrecur_manage' ),
			)
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/office365/select-calendar',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'office365_select_calendar' ),
				'permission_callback' => $this->require_cap( 'smartrecur_manage' ),
			)
		);

		// Syncro MSP.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/syncro/import',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'syncro_import' ),
				'permission_callback' => $this->require_cap( 'smartrecur_manage_clients' ),
			)
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/syncro/tickets',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'syncro_create_ticket' ),
				'permission_callback' => $this->require_cap( 'smartrecur_book' ),
			)
		);

		// Invoice Ninja.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/invoiceninja/import',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'invoiceninja_import' ),
				'permission_callback' => $this->require_cap( 'smartrecur_manage_clients' ),
			)
		);

		// Email sending + logs.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/email/send',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'send_email' ),
				'permission_callback' => $this->require_cap( 'smartrecur_book' ),
				'args'                => array(
					'to'      => array( 'required' => true, 'sanitize_callback' => 'sanitize_email' ),
					'subject' => array( 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ),
					'body'    => array( 'required' => true, 'sanitize_callback' => 'wp_kses_post' ),
				),
			)
		);
		register_rest_route(
			$this->namespace,
			'/email-logs',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'email_logs' ),
				'permission_callback' => $this->require_cap( 'smartrecur_manage' ),
			)
		);
	}

	/**
	 * GET /integrations/status
	 *
	 * @return WP_REST_Response
	 */
	public function status() {
		$all = SmartRecur_Integration_Config::all();
		foreach ( $all as $id => &$entry ) {
			$entry['config'] = SmartRecur_Integration_Config::public_view( $entry['config'] );
		}
		unset( $entry );

		return $this->with_no_store( rest_ensure_response( array( 'integrations' => $all ) ) );
	}

	/**
	 * Whitelist of writable config keys per integration. Keys outside the
	 * whitelist (including `clientSecret`, `_oauth_state`, `accessToken`,
	 * `refreshToken`, anything underscore-prefixed) are rejected to prevent
	 * privilege escalation via the public config endpoint.
	 *
	 * @return array<string, string[]>
	 */
	private function writable_keys() {
		return array(
			'office365'    => array( 'clientId', 'tenantId' ),
			'syncro'       => array( 'apiKey', 'subdomain' ),
			'invoiceninja' => array( 'apiKey', 'endpoint' ),
			'zoho'         => array( 'apiKey', 'apiSecret', 'endpoint' ),
			// Accepted for backwards compatibility with the legacy admin UI; emails
			// in this plugin go through wp_mail() so any SMTP plugin is honoured.
			'smtp'         => array( 'host', 'port', 'username', 'password', 'fromEmail', 'fromName', 'encryption' ),
		);
	}

	/**
	 * PUT /integrations/{type}/config
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function save_config( $request ) {
		$type    = sanitize_key( $request['type'] );
		$allowed = $this->writable_keys();
		if ( ! isset( $allowed[ $type ] ) ) {
			return new WP_Error( 'smartrecur_unknown_integration', __( 'Unknown integration.', 'smartrecur' ), array( 'status' => 400 ) );
		}

		$body = $request->get_json_params();
		if ( ! is_array( $body ) ) {
			$body = (array) $request->get_params();
		}

		$existing = SmartRecur_Integration_Config::get( $type );

		foreach ( $body as $key => $value ) {
			if ( '••••••••' === $value ) {
				continue;
			}
			if ( ! in_array( $key, $allowed[ $type ], true ) ) {
				continue;
			}
			$existing[ $key ] = is_string( $value ) ? sanitize_text_field( $value ) : $value;
		}

		SmartRecur_Integration_Config::put( $type, $existing );
		return $this->with_no_store( rest_ensure_response( array( 'success' => true ) ) );
	}

	/**
	 * POST /integrations/{type}/test
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function test( $request ) {
		$type   = sanitize_key( $request['type'] );
		$config = SmartRecur_Integration_Config::get( $type );

		switch ( $type ) {
			case 'office365':
				SmartRecur_Office365::ensure_valid_token( $config );
				SmartRecur_Integration_Config::put( 'office365', $config );
				list( $ok, $msg ) = SmartRecur_Office365::test( $config );
				break;
			case 'syncro':
				list( $ok, $msg ) = SmartRecur_Syncro::test( $config );
				break;
			case 'invoiceninja':
				list( $ok, $msg ) = SmartRecur_Invoice_Ninja::test( $config );
				break;
			case 'zoho':
				list( $ok, $msg ) = SmartRecur_Zoho::test( $config );
				break;
			case 'smtp':
				// SMTP is handled by wp_mail() + any installed SMTP plugin. We don't
				// actually test the SMTP socket here — just confirm wp_mail is callable.
				$ok  = function_exists( 'wp_mail' );
				$msg = $ok
					? __( 'Email is sent via wp_mail(). Configure an SMTP plugin (WP Mail SMTP, FluentSMTP) for delivery.', 'smartrecur' )
					: __( 'wp_mail() is not available on this site.', 'smartrecur' );
				break;
			default:
				return new WP_Error( 'smartrecur_unknown_integration', __( 'Unknown integration.', 'smartrecur' ), array( 'status' => 400 ) );
		}

		SmartRecur_Integration_Config::set_status( $type, $ok );
		return $this->with_no_store( rest_ensure_response( array( 'isConnected' => (bool) $ok, 'message' => $msg ) ) );
	}

	/* ---------------------------------------------------------------------
	 * Office 365.
	 * ------------------------------------------------------------------- */

	/**
	 * Transient key for per-state OAuth records (CSRF + PKCE verifier).
	 *
	 * @param string $state State token.
	 * @return string
	 */
	private function oauth_state_key( $state ) {
		return 'smartrecur_o365_state_' . hash( 'sha256', $state );
	}

	/**
	 * POST /integrations/office365/connect — start the PKCE auth-code flow.
	 *
	 * Generates a per-flow state + PKCE code verifier, stashes them in a
	 * 10-minute transient bound to the current admin user, and returns the
	 * Microsoft consent URL.
	 *
	 * @return WP_REST_Response
	 */
	public function office365_connect() {
		if ( ! SmartRecur_Office365::is_configured() ) {
			return new WP_Error(
				'smartrecur_o365_not_configured',
				__( 'Office 365 integration is not configured. The plugin author needs to set the bundled SMARTRECUR_O365_CLIENT_ID, or define SMARTRECUR_O365_CLIENT_ID in wp-config.php with your own Azure AD app client ID. See the README for the one-time Azure setup.', 'smartrecur' ),
				array( 'status' => 503 )
			);
		}

		$config = SmartRecur_Integration_Config::get( 'office365' );
		$config['redirectUri'] = rest_url( SMARTRECUR_REST_NAMESPACE . '/integrations/office365/callback' );

		$state    = bin2hex( random_bytes( 16 ) );
		$verifier = self::pkce_verifier();
		$challenge = self::pkce_challenge( $verifier );

		set_transient(
			$this->oauth_state_key( $state ),
			array(
				'user_id'  => get_current_user_id(),
				'verifier' => $verifier,
				'created'  => time(),
			),
			10 * MINUTE_IN_SECONDS
		);

		SmartRecur_Integration_Config::put( 'office365', $config );

		return $this->with_no_store(
			rest_ensure_response(
				array(
					'url' => SmartRecur_Office365::get_auth_url( $config, $state, $challenge ),
				)
			)
		);
	}

	/**
	 * GET /integrations/office365/callback — OAuth redirect handler.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function office365_callback( $request ) {
		$code  = sanitize_text_field( (string) $request->get_param( 'code' ) );
		$state = sanitize_text_field( (string) $request->get_param( 'state' ) );

		if ( ! $code || ! $state ) {
			return $this->oauth_html( false, __( 'Missing OAuth code or state.', 'smartrecur' ) );
		}

		$state_key = $this->oauth_state_key( $state );
		$stored    = get_transient( $state_key );
		delete_transient( $state_key );

		if ( ! is_array( $stored ) || empty( $stored['user_id'] ) || empty( $stored['verifier'] ) ) {
			return $this->oauth_html( false, __( 'OAuth state expired or unknown. Start the connection again.', 'smartrecur' ) );
		}
		if ( get_current_user_id() && (int) $stored['user_id'] !== get_current_user_id() ) {
			return $this->oauth_html( false, __( 'OAuth state belongs to a different user.', 'smartrecur' ) );
		}

		$config = SmartRecur_Integration_Config::get( 'office365' );

		$result = SmartRecur_Office365::exchange_code( $code, $stored['verifier'], $config );
		if ( empty( $result['success'] ) ) {
			return $this->oauth_html( false, $result['error'] ?? 'Token exchange failed' );
		}

		$config['accessToken']  = $result['accessToken'];
		$config['refreshToken'] = $result['refreshToken'] ?? '';
		$config['expiresAt']    = $result['expiresAt'] ?? ( time() + 3600 );
		$config['userEmail']    = $result['email'] ?? '';
		SmartRecur_Integration_Config::put( 'office365', $config );
		SmartRecur_Integration_Config::set_status( 'office365', true );

		return $this->oauth_html( true, '', $config['userEmail'] );
	}

	/**
	 * Generate a PKCE code verifier (RFC 7636 §4.1).
	 *
	 * @return string
	 */
	private static function pkce_verifier() {
		// 64 bytes → 86-char base64url, well within the 43-128 char spec.
		return rtrim( strtr( base64_encode( random_bytes( 64 ) ), '+/', '-_' ), '=' );
	}

	/**
	 * Derive the S256 code challenge from a verifier (RFC 7636 §4.2).
	 *
	 * @param string $verifier Code verifier.
	 * @return string
	 */
	private static function pkce_challenge( $verifier ) {
		return rtrim( strtr( base64_encode( hash( 'sha256', $verifier, true ) ), '+/', '-_' ), '=' );
	}

	/**
	 * Render the OAuth popup completion page (postMessage back to opener).
	 *
	 * WordPress's REST server forces a JSON content type and serializes responses
	 * to JSON by default. To return raw HTML, we register a `rest_pre_serve_request`
	 * filter that overrides the Content-Type header, echoes the HTML, and returns
	 * true so the server skips its normal serialization step.
	 *
	 * @param bool   $success Outcome.
	 * @param string $error   Error message.
	 * @param string $email   Connected account email.
	 * @return WP_REST_Response
	 */
	private function oauth_html( $success, $error = '', $email = '' ) {
		$payload = $success
			? wp_json_encode( array( 'type' => 'OAUTH_SUCCESS', 'email' => $email ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT )
			: wp_json_encode( array( 'type' => 'OAUTH_ERROR', 'error' => $error ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT );

		$origin  = wp_json_encode( site_url() );
		$heading = $success
			? esc_html( sprintf( __( 'Connected as %s. You can close this window.', 'smartrecur' ), $email ) )
			: esc_html( sprintf( __( 'Error: %s', 'smartrecur' ), $error ) );

		$html  = '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>OAuth</title></head>';
		$html .= '<body><h3>' . $heading . '</h3>';
		$html .= '<script>if(window.opener){window.opener.postMessage(' . $payload . ',' . $origin . ');}window.close();</script>';
		$html .= '</body></html>';

		add_filter(
			'rest_pre_serve_request',
			function ( $served ) use ( $html ) {
				// Overwrite the Content-Type already sent by the REST server.
				if ( ! headers_sent() ) {
					header( 'Content-Type: text/html; charset=UTF-8' );
					header( 'Cache-Control: no-store, private, max-age=0' );
					header( 'X-LiteSpeed-Cache-Control: no-cache, no-vary' );
				}
				echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — content is pre-escaped above.
				return true;
			},
			999
		);

		return new WP_REST_Response( null, 200 );
	}

	/**
	 * POST /integrations/office365/disconnect — clear stored tokens and stop sync.
	 *
	 * @return WP_REST_Response
	 */
	public function office365_disconnect() {
		$config = SmartRecur_Integration_Config::get( 'office365' );
		foreach ( array( 'accessToken', 'refreshToken', 'expiresAt', 'userEmail', 'calendarId', 'lastSyncedAt' ) as $key ) {
			unset( $config[ $key ] );
		}
		SmartRecur_Integration_Config::put( 'office365', $config );
		SmartRecur_Integration_Config::set_status( 'office365', false );
		SmartRecur_Sync_Office365::unschedule();
		return $this->with_no_store( rest_ensure_response( array( 'success' => true ) ) );
	}

	/**
	 * POST /integrations/office365/sync-now — trigger an immediate two-way sync.
	 *
	 * @return WP_REST_Response
	 */
	public function office365_sync_now() {
		SmartRecur_Sync_Office365::pull_calendar();
		$config = SmartRecur_Integration_Config::get( 'office365' );
		return $this->with_no_store(
			rest_ensure_response(
				array(
					'success'       => true,
					'lastSyncedAt'  => $config['lastSyncedAt'] ?? null,
				)
			)
		);
	}

	/**
	 * POST /integrations/office365/select-calendar — set which calendar to sync.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function office365_select_calendar( $request ) {
		$calendar_id   = sanitize_text_field( (string) $request['calendarId'] );
		$calendar_name = sanitize_text_field( (string) ( $request['calendarName'] ?? '' ) );
		if ( '' === $calendar_id ) {
			return new WP_Error( 'smartrecur_no_calendar', __( 'Pick a calendar.', 'smartrecur' ), array( 'status' => 400 ) );
		}

		$config = SmartRecur_Integration_Config::get( 'office365' );
		SmartRecur_Office365::ensure_valid_token( $config );
		SmartRecur_Integration_Config::put( 'office365', $config );

		// Reject IDs not present in the user's actual calendar list — prevents
		// a manage-cap user from binding sync to an arbitrary string.
		$calendars = SmartRecur_Office365::get_calendars( $config );
		$valid     = false;
		foreach ( (array) $calendars as $cal ) {
			if ( isset( $cal['id'] ) && $cal['id'] === $calendar_id ) {
				$valid = true;
				if ( '' === $calendar_name && isset( $cal['name'] ) ) {
					$calendar_name = sanitize_text_field( $cal['name'] );
				}
				break;
			}
		}
		if ( ! $valid ) {
			return new WP_Error( 'smartrecur_invalid_calendar', __( 'That calendar does not belong to the connected Office 365 account.', 'smartrecur' ), array( 'status' => 400 ) );
		}

		$config['calendarId']   = $calendar_id;
		$config['calendarName'] = $calendar_name;
		SmartRecur_Integration_Config::put( 'office365', $config );

		// Re-arm the cron job in case it wasn't scheduled before.
		SmartRecur_Sync_Office365::unschedule();
		if ( SmartRecur_Sync_Office365::is_enabled() ) {
			wp_schedule_event( time() + 60, 'smartrecur_15min', SmartRecur_Sync_Office365::CRON_HOOK );
		}

		return $this->with_no_store( rest_ensure_response( array( 'success' => true, 'calendarId' => $calendar_id ) ) );
	}

	/**
	 * GET /integrations/office365/calendars — list user's calendars.
	 *
	 * @return WP_REST_Response
	 */
	public function office365_calendars() {
		if ( ! SmartRecur_Office365::is_configured() ) {
			return new WP_Error( 'smartrecur_o365_not_configured', __( 'Office 365 client ID is not configured.', 'smartrecur' ), array( 'status' => 503 ) );
		}
		$config = SmartRecur_Integration_Config::get( 'office365' );
		if ( empty( $config['accessToken'] ) ) {
			return new WP_Error( 'smartrecur_o365_not_connected', __( 'Connect to Office 365 first.', 'smartrecur' ), array( 'status' => 400 ) );
		}
		SmartRecur_Office365::ensure_valid_token( $config );
		SmartRecur_Integration_Config::put( 'office365', $config );

		return $this->with_no_store( rest_ensure_response( array( 'calendars' => SmartRecur_Office365::get_calendars( $config ) ) ) );
	}

	/* ---------------------------------------------------------------------
	 * Syncro MSP.
	 * ------------------------------------------------------------------- */

	/**
	 * POST /integrations/syncro/import — fetch Syncro customers as Customer-shaped
	 * rows. The React layer dedupes against local state and creates them via the
	 * /clients endpoint, so this stays a pure proxy with no DB writes.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function syncro_import() {
		$config    = SmartRecur_Integration_Config::get( 'syncro' );
		$customers = SmartRecur_Syncro::get_customers( $config );
		if ( is_wp_error( $customers ) ) {
			return $customers;
		}
		return $this->with_no_store(
			rest_ensure_response( array(
				'customers' => $customers,
				'total'     => count( $customers ),
			) )
		);
	}

	/**
	 * POST /integrations/syncro/tickets — create a ticket linked to an appointment.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function syncro_create_ticket( $request ) {
		$body   = array(
			'customerId'  => sanitize_text_field( (string) $request['customerId'] ),
			'subject'     => sanitize_text_field( (string) $request['subject'] ),
			'description' => sanitize_textarea_field( (string) $request['description'] ),
		);
		$config = SmartRecur_Integration_Config::get( 'syncro' );
		$result = SmartRecur_Syncro::create_ticket( $config, $body );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return $this->with_no_store( rest_ensure_response( $result ) );
	}

	/* ---------------------------------------------------------------------
	 * Invoice Ninja.
	 * ------------------------------------------------------------------- */

	/**
	 * POST /integrations/invoiceninja/import — fetch + upsert clients.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function invoiceninja_import() {
		global $wpdb;
		$table  = $wpdb->prefix . 'smartrecur_clients';
		$config = SmartRecur_Integration_Config::get( 'invoiceninja' );

		$clients = SmartRecur_Invoice_Ninja::get_clients( $config );
		if ( is_wp_error( $clients ) ) {
			return $clients;
		}

		$imported = 0;
		foreach ( $clients as $client ) {
			$ninja_id = (string) ( $client['id'] ?? '' );
			if ( ! $ninja_id ) {
				continue;
			}
			$existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE invoiceninja_id = %s", $ninja_id ) );
			if ( $existing ) {
				continue;
			}

			$contacts = $client['contacts'] ?? array();
			$primary  = $contacts[0] ?? array();
			$name     = trim( ( $primary['first_name'] ?? '' ) . ' ' . ( $primary['last_name'] ?? '' ) );

			$wpdb->insert(
				$table,
				array(
					'id'              => SmartRecur_UUID::v4(),
					'name'            => $name ?: ( $client['name'] ?? 'Unknown' ),
					'email'           => $primary['email'] ?? '',
					'phone'           => $primary['phone'] ?? ( $client['phone'] ?? '' ),
					'company'         => $client['name'] ?? '',
					'address'         => trim( ( $client['address1'] ?? '' ) . ' ' . ( $client['address2'] ?? '' ) ),
					'postcode'        => $client['postal_code'] ?? '',
					'invoiceninja_id' => $ninja_id,
				),
				array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
			);
			$imported++;
		}

		return $this->with_no_store(
			rest_ensure_response( array(
				'imported' => $imported,
				'total'    => count( $clients ),
				'message'  => sprintf( __( 'Imported %1$d new clients from Invoice Ninja (%2$d total).', 'smartrecur' ), $imported, count( $clients ) ),
			) )
		);
	}

	/* ---------------------------------------------------------------------
	 * Email.
	 * ------------------------------------------------------------------- */

	/**
	 * POST /integrations/email/send — send an email via wp_mail().
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function send_email( $request ) {
		$to      = sanitize_email( $request['to'] );
		$subject = sanitize_text_field( $request['subject'] );
		$body    = wp_kses_post( (string) $request['body'] );

		if ( ! is_email( $to ) ) {
			return new WP_Error( 'smartrecur_bad_email', __( 'Invalid recipient address.', 'smartrecur' ), array( 'status' => 400 ) );
		}

		$headers = array( 'Content-Type: text/html; charset=UTF-8' );
		$sent    = wp_mail( $to, $subject, wpautop( $body ), $headers );

		$this->log_email( $to, $subject, $sent ? 'sent' : 'failed', 'wp_mail', $sent ? '' : 'wp_mail returned false' );

		if ( ! $sent ) {
			return new WP_Error( 'smartrecur_mail_failed', __( 'Failed to send email via wp_mail().', 'smartrecur' ), array( 'status' => 500 ) );
		}
		return $this->with_no_store( rest_ensure_response( array( 'success' => true, 'method' => 'wp_mail' ) ) );
	}

	/**
	 * GET /email-logs
	 *
	 * @return WP_REST_Response
	 */
	public function email_logs() {
		global $wpdb;
		$table = $wpdb->prefix . 'smartrecur_email_logs';
		$rows  = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT 100", ARRAY_A );
		return $this->with_no_store( rest_ensure_response( array( 'logs' => $rows ?: array() ) ) );
	}

	/**
	 * Insert an email log row.
	 *
	 * @param string $to      Recipient.
	 * @param string $subject Subject.
	 * @param string $status  'sent' or 'failed'.
	 * @param string $method  Transport identifier.
	 * @param string $error   Error message if any.
	 */
	private function log_email( $to, $subject, $status, $method, $error = '' ) {
		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'smartrecur_email_logs',
			array(
				'id'        => SmartRecur_UUID::v4(),
				'recipient' => $to,
				'subject'   => $subject,
				'status'    => $status,
				'method'    => $method,
				'error'     => $error ?: null,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s' )
		);
	}
}

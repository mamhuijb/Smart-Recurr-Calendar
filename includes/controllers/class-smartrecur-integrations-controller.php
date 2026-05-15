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
			'/' . $this->rest_base . '/office365/sync',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'office365_sync' ),
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
	 * PUT /integrations/{type}/config
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function save_config( $request ) {
		$type = sanitize_key( $request['type'] );
		if ( ! in_array( $type, array( 'office365', 'syncro', 'invoiceninja', 'zoho' ), true ) ) {
			return new WP_Error( 'smartrecur_unknown_integration', __( 'Unknown integration.', 'smartrecur' ), array( 'status' => 400 ) );
		}

		$body = $request->get_json_params();
		if ( ! is_array( $body ) ) {
			$body = (array) $request->get_params();
		}

		$existing = SmartRecur_Integration_Config::get( $type );

		// Merge — never overwrite secrets with placeholders.
		foreach ( $body as $key => $value ) {
			if ( '••••••••' === $value ) {
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
	 * POST /integrations/office365/connect — return the OAuth consent URL.
	 *
	 * @return WP_REST_Response
	 */
	public function office365_connect() {
		$config = SmartRecur_Integration_Config::get( 'office365' );

		// Inject the secret from wp-config.php so it's never persisted in the DB.
		if ( defined( 'SMARTRECUR_O365_CLIENT_SECRET' ) ) {
			$config['clientSecret'] = SMARTRECUR_O365_CLIENT_SECRET;
		}

		$config['redirectUri'] = rest_url( SMARTRECUR_REST_NAMESPACE . '/integrations/office365/callback' );

		$state                  = bin2hex( random_bytes( 16 ) );
		$config['_oauth_state'] = $state;
		SmartRecur_Integration_Config::put( 'office365', $config );

		return $this->with_no_store( rest_ensure_response( array( 'url' => SmartRecur_Office365::get_auth_url( $config, $state ) ) ) );
	}

	/**
	 * GET /integrations/office365/callback — OAuth redirect handler (renders an HTML page).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function office365_callback( $request ) {
		$code  = sanitize_text_field( (string) $request->get_param( 'code' ) );
		$state = sanitize_text_field( (string) $request->get_param( 'state' ) );

		$config = SmartRecur_Integration_Config::get( 'office365' );
		$expected = $config['_oauth_state'] ?? '';

		if ( ! $code || ! $expected || ! hash_equals( $expected, $state ) ) {
			return $this->oauth_html( false, __( 'Invalid OAuth state.', 'smartrecur' ) );
		}
		unset( $config['_oauth_state'] );

		if ( defined( 'SMARTRECUR_O365_CLIENT_SECRET' ) ) {
			$config['clientSecret'] = SMARTRECUR_O365_CLIENT_SECRET;
		}

		$result = SmartRecur_Office365::exchange_code( $code, $config );
		if ( ! $result['success'] ) {
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
	 * Render the OAuth popup completion page (postMessage back to opener).
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

		$origin     = wp_json_encode( site_url() );
		$heading    = $success
			? esc_html( sprintf( __( 'Connected as %s. You can close this window.', 'smartrecur' ), $email ) )
			: esc_html( sprintf( __( 'Error: %s', 'smartrecur' ), $error ) );

		$html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>OAuth</title></head>';
		$html .= '<body><h3>' . $heading . '</h3>';
		$html .= '<script>if(window.opener){window.opener.postMessage(' . $payload . ',' . $origin . ');}window.close();</script>';
		$html .= '</body></html>';

		$response = new WP_REST_Response( null );
		$response->header( 'Content-Type', 'text/html; charset=UTF-8' );
		$response->header( 'Cache-Control', 'no-store, private' );
		$response->set_data( $html );
		// Tell the REST server to send the raw HTML instead of JSON.
		add_filter( 'rest_pre_serve_request', function ( $served, $r ) use ( $html ) {
			echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — pre-escaped above.
			return true;
		}, 10, 2 );
		return $response;
	}

	/**
	 * POST /integrations/office365/disconnect — clear stored tokens.
	 *
	 * @return WP_REST_Response
	 */
	public function office365_disconnect() {
		$config = SmartRecur_Integration_Config::get( 'office365' );
		foreach ( array( 'accessToken', 'refreshToken', 'expiresAt', 'userEmail' ) as $key ) {
			unset( $config[ $key ] );
		}
		SmartRecur_Integration_Config::put( 'office365', $config );
		SmartRecur_Integration_Config::set_status( 'office365', false );
		return $this->with_no_store( rest_ensure_response( array( 'success' => true ) ) );
	}

	/**
	 * POST /integrations/office365/sync — push a SmartRecur appointment to O365 calendar.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function office365_sync( $request ) {
		$config = SmartRecur_Integration_Config::get( 'office365' );
		if ( defined( 'SMARTRECUR_O365_CLIENT_SECRET' ) ) {
			$config['clientSecret'] = SMARTRECUR_O365_CLIENT_SECRET;
		}
		SmartRecur_Office365::ensure_valid_token( $config );
		SmartRecur_Integration_Config::put( 'office365', $config );

		$calendar_id = sanitize_text_field( (string) $request['calendarId'] );
		$event       = array(
			'subject' => sanitize_text_field( (string) $request['subject'] ),
			'body'    => array(
				'contentType' => 'Text',
				'content'     => sanitize_textarea_field( (string) $request['description'] ),
			),
			'start'   => array(
				'dateTime' => sanitize_text_field( (string) $request['startDateTime'] ),
				'timeZone' => sanitize_text_field( (string) ( $request['timeZone'] ?: 'Europe/Amsterdam' ) ),
			),
			'end'     => array(
				'dateTime' => sanitize_text_field( (string) $request['endDateTime'] ),
				'timeZone' => sanitize_text_field( (string) ( $request['timeZone'] ?: 'Europe/Amsterdam' ) ),
			),
		);

		$result = SmartRecur_Office365::create_calendar_event( $config, $event, $calendar_id );
		if ( ! $result['success'] ) {
			return new WP_Error( 'smartrecur_o365_sync_failed', $result['error'] ?? 'Sync failed', array( 'status' => 502 ) );
		}
		return $this->with_no_store( rest_ensure_response( $result ) );
	}

	/**
	 * GET /integrations/office365/calendars
	 *
	 * @return WP_REST_Response
	 */
	public function office365_calendars() {
		$config = SmartRecur_Integration_Config::get( 'office365' );
		if ( defined( 'SMARTRECUR_O365_CLIENT_SECRET' ) ) {
			$config['clientSecret'] = SMARTRECUR_O365_CLIENT_SECRET;
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

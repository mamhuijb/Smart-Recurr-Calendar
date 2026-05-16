<?php
/**
 * Public, login-free appointment view.
 *
 * When a URL carries `?smartrecur_appt=<token>` we look the appointment up by
 * its per-appointment view token and render a standalone read-only page. The
 * token only ever reveals that one appointment — no listing, no editing.
 *
 * @package SmartRecur
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SmartRecur_Public {

	/**
	 * Register the hook.
	 */
	public static function register() {
		add_action( 'template_redirect', array( __CLASS__, 'maybe_render' ) );
	}

	/**
	 * If the request carries a valid appointment token, render the view page
	 * and stop WordPress from rendering anything else.
	 */
	public static function maybe_render() {
		if ( empty( $_GET['smartrecur_appt'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$token = sanitize_text_field( wp_unslash( $_GET['smartrecur_appt'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! preg_match( '/^[a-f0-9]{32}$/', $token ) ) {
			self::render_error();
			return;
		}

		global $wpdb;
		$table       = $wpdb->prefix . 'smartrecur_appointments';
		$appointment = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE view_token = %s", $token ), ARRAY_A );

		if ( ! $appointment ) {
			self::render_error();
			return;
		}

		nocache_headers();
		$ctx = self::context( $appointment );
		status_header( 200 );
		header( 'Content-Type: text/html; charset=UTF-8' );
		include SMARTRECUR_PLUGIN_DIR . 'templates/public/appointment.php';
		exit;
	}

	/**
	 * Build the template context for an appointment.
	 *
	 * @param array $appointment Appointment row.
	 * @return array
	 */
	private static function context( array $appointment ) {
		$client  = $appointment['client_id'] ? SmartRecur_Data::get_client( $appointment['client_id'] ) : null;
		$service = $appointment['service_id'] ? SmartRecur_Data::get_service( $appointment['service_id'] ) : null;
		$tech    = $appointment['technician_id'] ? SmartRecur_Data::get_technician( $appointment['technician_id'] ) : null;

		$dates = json_decode( $appointment['generated_dates'] ?? '[]', true );
		$dates = is_array( $dates ) ? $dates : array();
		sort( $dates );
		$today = current_time( 'Y-m-d' );
		$next  = '';
		foreach ( $dates as $d ) {
			if ( $d >= $today ) {
				$next = $d;
				break;
			}
		}

		$palette = SmartRecur_Theme::palette();

		return array(
			'title'         => $appointment['title'],
			'company'       => get_bloginfo( 'name' ),
			'client'        => $client ? ( $client['company'] ?: $client['name'] ) : '',
			'service'       => $service ? $service['name'] : '',
			'technician'    => $tech ? $tech['name'] : __( 'To be assigned', 'smartrecur' ),
			'location'      => 'REMOTE' === $appointment['location_type'] ? __( 'Remote', 'smartrecur' ) : __( 'On site', 'smartrecur' ),
			'status'        => $appointment['status'],
			'start_time'    => $appointment['start_time'] ?? '',
			'end_time'      => $appointment['end_time'] ?? '',
			'next_date'     => $next,
			'all_dates'     => $dates,
			'description'   => $appointment['description'] ?? '',
			'recurrence'    => $appointment['recurrence_rule'] ?? '',
			'header_color'  => $palette['emailHeader'],
			'accent_color'  => $palette['emailAccent'],
		);
	}

	/**
	 * Render a generic "not found" page for bad/expired tokens.
	 */
	private static function render_error() {
		nocache_headers();
		status_header( 404 );
		header( 'Content-Type: text/html; charset=UTF-8' );
		$palette = SmartRecur_Theme::palette();
		echo '<!DOCTYPE html><html><head><meta charset="UTF-8">';
		echo '<meta name="viewport" content="width=device-width,initial-scale=1">';
		echo '<title>' . esc_html__( 'Appointment not found', 'smartrecur' ) . '</title></head>';
		echo '<body style="font-family:-apple-system,Segoe UI,Roboto,sans-serif;background:#0a0e1a;color:#e7ecf3;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;">';
		echo '<div style="text-align:center;padding:40px;">';
		echo '<h1 style="font-size:20px;">' . esc_html__( 'Appointment not found', 'smartrecur' ) . '</h1>';
		echo '<p style="color:#9aa6bd;">' . esc_html__( 'This link is invalid or has expired.', 'smartrecur' ) . '</p>';
		echo '</div></body></html>';
		exit;
	}
}

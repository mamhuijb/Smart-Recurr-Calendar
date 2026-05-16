<?php
/**
 * Reminder email rendering, sending, logging — plus the public appointment link.
 *
 * Emails go out via wp_mail() so any WP SMTP plugin is honoured. The HTML body
 * is built from templates/email/reminder.php, recoloured by the Appearance
 * settings, with the {token} placeholders substituted.
 *
 * @package SmartRecur
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SmartRecur_Mailer {

	/**
	 * Ensure an appointment row has a public view token; create one if missing.
	 *
	 * @param array $appointment Appointment row.
	 * @return string The token.
	 */
	public static function ensure_token( array $appointment ) {
		if ( ! empty( $appointment['view_token'] ) ) {
			return $appointment['view_token'];
		}
		global $wpdb;
		$token = bin2hex( random_bytes( 16 ) );
		$wpdb->update(
			SmartRecur_Data::table( 'appointments' ),
			array( 'view_token' => $token ),
			array( 'id' => $appointment['id'] ),
			array( '%s' ),
			array( '%s' )
		);
		return $token;
	}

	/**
	 * Public, login-free URL where a client can view one appointment.
	 *
	 * @param array $appointment Appointment row.
	 * @return string
	 */
	public static function appointment_link( array $appointment ) {
		$token = self::ensure_token( $appointment );
		return add_query_arg( 'smartrecur_appt', $token, home_url( '/' ) );
	}

	/**
	 * Resolve the {token} placeholders for an appointment occurrence.
	 *
	 * @param array  $appointment Appointment row.
	 * @param string $date        Occurrence date (YYYY-MM-DD).
	 * @return array placeholder => value
	 */
	public static function tokens( array $appointment, $date ) {
		$client  = $appointment['client_id'] ? SmartRecur_Data::get_client( $appointment['client_id'] ) : null;
		$service = $appointment['service_id'] ? SmartRecur_Data::get_service( $appointment['service_id'] ) : null;
		$tech    = $appointment['technician_id'] ? SmartRecur_Data::get_technician( $appointment['technician_id'] ) : null;

		return array(
			'{customer_name}' => $client ? ( $client['name'] ?: $client['company'] ) : '',
			'{company_name}'  => get_bloginfo( 'name' ),
			'{service_name}'  => $service ? $service['name'] : $appointment['title'],
			'{tech_name}'     => $tech ? $tech['name'] : __( 'a technician', 'smartrecur' ),
			'{date}'          => date_i18n( get_option( 'date_format', 'j F Y' ), strtotime( $date ) ),
			'{location_type}' => 'REMOTE' === $appointment['location_type'] ? __( 'Remote', 'smartrecur' ) : __( 'On site', 'smartrecur' ),
			'{link}'          => self::appointment_link( $appointment ),
		);
	}

	/**
	 * Render the HTML email body for a reminder.
	 *
	 * @param array  $appointment Appointment row.
	 * @param string $date        Occurrence date.
	 * @return array { subject, html, recipient }
	 */
	public static function render_reminder( array $appointment, $date ) {
		$settings = (array) get_option( 'smartrecur_settings', array() );
		$tpl      = $settings['templates']['reminder'] ?? array();
		$tokens   = self::tokens( $appointment, $date );

		$subject = strtr( (string) ( $tpl['subject'] ?? 'Appointment reminder' ), $tokens );
		$body    = strtr( (string) ( $tpl['body'] ?? '' ), $tokens );

		$client    = $appointment['client_id'] ? SmartRecur_Data::get_client( $appointment['client_id'] ) : null;
		$recipient = $client ? $client['email'] : '';

		$palette = SmartRecur_Theme::palette();
		$vars    = array(
			'subject'      => $subject,
			'body'         => $body,
			'company'      => get_bloginfo( 'name' ),
			'logo'         => $settings['branding']['logoUrl'] ?? '',
			'header_color' => $palette['emailHeader'],
			'accent_color' => $palette['emailAccent'],
			'link'         => $tokens['{link}'],
			'link_label'   => __( 'View appointment', 'smartrecur' ),
		);

		ob_start();
		$smartrecur_email = $vars; // phpcs:ignore -- consumed by the template.
		include SMARTRECUR_PLUGIN_DIR . 'templates/email/reminder.php';
		$html = (string) ob_get_clean();

		return array(
			'subject'   => $subject,
			'html'      => $html,
			'recipient' => $recipient,
		);
	}

	/**
	 * Send a reminder for one appointment occurrence.
	 *
	 * @param array  $appointment Appointment row.
	 * @param string $date        Occurrence date.
	 * @return bool
	 */
	public static function send_reminder( array $appointment, $date ) {
		$rendered = self::render_reminder( $appointment, $date );
		if ( ! $rendered['recipient'] || ! is_email( $rendered['recipient'] ) ) {
			self::log( $rendered['recipient'] ?: '(no client email)', $rendered['subject'], 'failed', 'No valid recipient' );
			return false;
		}
		$ok = self::dispatch( $rendered['recipient'], $rendered['subject'], $rendered['html'] );
		self::log( $rendered['recipient'], $rendered['subject'], $ok ? 'sent' : 'failed', $ok ? '' : 'wp_mail returned false' );
		return $ok;
	}

	/**
	 * Send a test reminder with sample data so the admin can review the layout.
	 *
	 * @param string $to Recipient email.
	 * @return bool
	 */
	public static function send_test( $to ) {
		$to = sanitize_email( $to );
		if ( ! is_email( $to ) ) {
			return false;
		}

		// Build a fake appointment + date so the template renders fully.
		$sample = array(
			'id'            => 'sample',
			'title'         => __( 'Sample appointment', 'smartrecur' ),
			'client_id'     => '',
			'service_id'    => '',
			'technician_id' => '',
			'location_type' => 'ON_SITE',
			'view_token'    => '',
		);
		$settings = (array) get_option( 'smartrecur_settings', array() );
		$tpl      = $settings['templates']['reminder'] ?? array();
		$tokens   = array(
			'{customer_name}' => __( 'Jane Example', 'smartrecur' ),
			'{company_name}'  => get_bloginfo( 'name' ),
			'{service_name}'  => __( 'Quarterly maintenance', 'smartrecur' ),
			'{tech_name}'     => __( 'Alex Technician', 'smartrecur' ),
			'{date}'          => date_i18n( get_option( 'date_format', 'j F Y' ) ),
			'{location_type}' => __( 'On site', 'smartrecur' ),
			'{link}'          => home_url( '/' ),
		);
		$subject = strtr( (string) ( $tpl['subject'] ?? 'Appointment reminder' ), $tokens ) . ' (' . __( 'test', 'smartrecur' ) . ')';
		$body    = strtr( (string) ( $tpl['body'] ?? '' ), $tokens );

		$palette          = SmartRecur_Theme::palette();
		$smartrecur_email = array(
			'subject'      => $subject,
			'body'         => $body,
			'company'      => get_bloginfo( 'name' ),
			'logo'         => $settings['branding']['logoUrl'] ?? '',
			'header_color' => $palette['emailHeader'],
			'accent_color' => $palette['emailAccent'],
			'link'         => $tokens['{link}'],
			'link_label'   => __( 'View appointment', 'smartrecur' ),
		);
		ob_start();
		include SMARTRECUR_PLUGIN_DIR . 'templates/email/reminder.php';
		$html = (string) ob_get_clean();

		$ok = self::dispatch( $to, $subject, $html );
		self::log( $to, $subject, $ok ? 'sent' : 'failed', $ok ? '' : 'wp_mail returned false' );
		return $ok;
	}

	/**
	 * Low-level send via wp_mail() with an HTML content type.
	 *
	 * @param string $to      Recipient.
	 * @param string $subject Subject.
	 * @param string $html    HTML body.
	 * @return bool
	 */
	private static function dispatch( $to, $subject, $html ) {
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );
		return (bool) wp_mail( $to, $subject, $html, $headers );
	}

	/**
	 * Insert a row into the email log table.
	 *
	 * @param string $to      Recipient.
	 * @param string $subject Subject.
	 * @param string $status  'sent' | 'failed'.
	 * @param string $error   Error detail.
	 */
	public static function log( $to, $subject, $status, $error = '' ) {
		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'smartrecur_email_logs',
			array(
				'id'        => SmartRecur_UUID::v4(),
				'recipient' => $to,
				'subject'   => $subject,
				'status'    => $status,
				'method'    => 'wp_mail',
				'error'     => $error ?: null,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Recent email log rows.
	 *
	 * @param int $limit Max rows.
	 * @return array
	 */
	public static function recent_logs( $limit = 100 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'smartrecur_email_logs';
		$limit = max( 1, min( 500, (int) $limit ) );
		$rows  = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d", $limit ),
			ARRAY_A
		);
		return is_array( $rows ) ? $rows : array();
	}
}

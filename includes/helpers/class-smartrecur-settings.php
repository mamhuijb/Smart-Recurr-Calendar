<?php
/**
 * Plugin settings: typed accessors + the admin form persister.
 *
 * Settings live in a single serialized option, `smartrecur_settings`.
 *
 * @package SmartRecur
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SmartRecur_Settings {

	const OPTION = 'smartrecur_settings';

	/**
	 * Full settings array.
	 *
	 * @return array
	 */
	public static function all() {
		return (array) get_option( self::OPTION, array() );
	}

	/**
	 * Get a top-level setting.
	 *
	 * @param string $key     Key.
	 * @param mixed  $default Default.
	 * @return mixed
	 */
	public static function get( $key, $default = null ) {
		$all = self::all();
		return array_key_exists( $key, $all ) ? $all[ $key ] : $default;
	}

	/**
	 * Persist the SmartRecur → Settings admin form.
	 *
	 * @param array $post Unslashed POST data.
	 */
	public static function save_form( array $post ) {
		$settings = self::all();

		// Branding.
		$settings['branding'] = array(
			'logoUrl'         => isset( $post['logo_url'] ) ? esc_url_raw( $post['logo_url'] ) : '',
			'primaryColorHex' => isset( $post['primary_color'] ) ? sanitize_hex_color( $post['primary_color'] ) : '#4f46e5',
			'themeMode'       => ( isset( $post['theme_mode'] ) && 'light' === $post['theme_mode'] ) ? 'light' : 'dark',
		);

		// Business hours. Timezone is validated against the real list — a spoofed
		// POST can't inject a value PHP's date functions would choke on.
		$tz = isset( $post['business_timezone'] ) ? sanitize_text_field( $post['business_timezone'] ) : 'Europe/Amsterdam';
		if ( ! in_array( $tz, timezone_identifiers_list(), true ) ) {
			$tz = 'Europe/Amsterdam';
		}
		$settings['businessHours'] = array(
			'start'      => isset( $post['business_start'] ) ? sanitize_text_field( $post['business_start'] ) : '09:00',
			'end'        => isset( $post['business_end'] ) ? sanitize_text_field( $post['business_end'] ) : '17:00',
			'timezone'   => $tz,
			'closedDays' => isset( $post['closed_days'] ) && is_array( $post['closed_days'] )
				? array_map( 'absint', $post['closed_days'] )
				: array(),
		);

		// Durations.
		$settings['durations'] = isset( $post['durations'] ) && is_array( $post['durations'] )
			? array_map( 'absint', $post['durations'] )
			: array();

		// Booking rules.
		$settings['bookingRules'] = array(
			'bufferMinutes' => isset( $post['buffer_minutes'] ) ? absint( $post['buffer_minutes'] ) : 0,
			'maxFutureDays' => isset( $post['max_future_days'] ) ? absint( $post['max_future_days'] ) : 365,
			'maxPerDay'     => isset( $post['max_per_day'] ) ? absint( $post['max_per_day'] ) : 0,
		);

		// Notifications + reminder template.
		$settings['notifications'] = array( 'emailEnabled' => ! empty( $post['email_enabled'] ) );
		$settings['templates']     = array(
			'reminder' => array(
				'subject' => isset( $post['template_subject'] ) ? sanitize_text_field( $post['template_subject'] ) : '',
				'body'    => isset( $post['template_body'] ) ? sanitize_textarea_field( $post['template_body'] ) : '',
			),
		);
		$reminders                 = isset( $post['reminder_days'] ) ? sanitize_text_field( $post['reminder_days'] ) : '';
		$settings['reminders']     = array(
			'days' => array_values( array_filter( array_map( 'absint', explode( ',', $reminders ) ) ) ),
		);

		// Holidays + closures (one date per line).
		$settings['holidays']       = self::parse_date_lines( $post['holidays'] ?? '' );
		$settings['manualClosures'] = self::parse_date_lines( $post['manual_closures'] ?? '' );

		// Dashboard widget.
		$settings['dashboardWidget'] = array(
			'enabled'   => ! empty( $post['dashboard_widget_enabled'] ),
			'limit'     => isset( $post['dashboard_widget_limit'] ) ? max( 1, min( 50, absint( $post['dashboard_widget_limit'] ) ) ) : 10,
			'daysAhead' => isset( $post['dashboard_widget_days'] ) ? max( 1, min( 365, absint( $post['dashboard_widget_days'] ) ) ) : 30,
		);

		// Data management.
		$settings['deleteDataOnUninstall'] = ! empty( $post['delete_data_on_uninstall'] );

		update_option( self::OPTION, $settings );
	}

	/**
	 * Parse a textarea of one date per line into a clean array of YYYY-MM-DD.
	 *
	 * @param string $raw Raw textarea contents.
	 * @return array
	 */
	private static function parse_date_lines( $raw ) {
		$lines = preg_split( '/[\r\n,]+/', (string) $raw );
		$out   = array();
		foreach ( (array) $lines as $line ) {
			$line = trim( $line );
			// Guard against pathological input; a real date line is short.
			if ( '' === $line || strlen( $line ) > 40 ) {
				continue;
			}
			$ts = strtotime( $line );
			if ( $ts ) {
				$out[] = gmdate( 'Y-m-d', $ts );
			}
		}
		return array_values( array_unique( $out ) );
	}
}

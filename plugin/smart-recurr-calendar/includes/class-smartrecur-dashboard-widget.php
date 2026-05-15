<?php
/**
 * WordPress dashboard widget: upcoming SmartRecur appointments.
 *
 * Configurable from SmartRecur → Settings → "Dashboard widget":
 *   - enable/disable
 *   - max items shown
 *   - how many days ahead to scan
 *
 * Hooks into `wp_dashboard_setup`. Per-user "Screen Options" hide/show works
 * automatically because we go through the standard `wp_add_dashboard_widget`.
 *
 * @package SmartRecur
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SmartRecur_Dashboard_Widget {

	const WIDGET_ID = 'smartrecur_upcoming_appointments';

	/**
	 * Register the widget on the WordPress dashboard if enabled in settings.
	 */
	public static function register() {
		add_action( 'wp_dashboard_setup', array( __CLASS__, 'add_widget' ) );
	}

	/**
	 * Add the widget if the current user can view appointments and the option is on.
	 */
	public static function add_widget() {
		if ( ! current_user_can( 'smartrecur_view' ) ) {
			return;
		}

		$config = self::config();
		if ( empty( $config['enabled'] ) ) {
			return;
		}

		wp_add_dashboard_widget(
			self::WIDGET_ID,
			__( 'SmartRecur — Upcoming Appointments', 'smartrecur' ),
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * Return the widget configuration merged with defaults.
	 *
	 * @return array
	 */
	public static function config() {
		$settings = (array) get_option( 'smartrecur_settings', array() );
		$widget   = isset( $settings['dashboardWidget'] ) && is_array( $settings['dashboardWidget'] ) ? $settings['dashboardWidget'] : array();

		return array(
			'enabled'   => array_key_exists( 'enabled', $widget ) ? (bool) $widget['enabled'] : true,
			'limit'     => isset( $widget['limit'] ) ? max( 1, min( 50, (int) $widget['limit'] ) ) : 10,
			'daysAhead' => isset( $widget['daysAhead'] ) ? max( 1, min( 365, (int) $widget['daysAhead'] ) ) : 30,
		);
	}

	/**
	 * Render the widget body.
	 */
	public static function render() {
		$config = self::config();
		$rows   = self::fetch_upcoming( $config['limit'], $config['daysAhead'] );

		if ( empty( $rows ) ) {
			echo '<p>' . esc_html(
				sprintf(
					/* translators: %d days ahead */
					__( 'No appointments scheduled in the next %d days.', 'smartrecur' ),
					$config['daysAhead']
				)
			) . '</p>';
			echo self::footer_links(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — pre-escaped below.
			return;
		}

		echo '<table class="widefat striped" style="border:0;">';
		echo '<thead><tr>';
		echo '<th style="padding:6px 0;">' . esc_html__( 'When', 'smartrecur' ) . '</th>';
		echo '<th style="padding:6px 8px;">' . esc_html__( 'Client', 'smartrecur' ) . '</th>';
		echo '<th style="padding:6px 8px;">' . esc_html__( 'Service', 'smartrecur' ) . '</th>';
		echo '<th style="padding:6px 8px;">' . esc_html__( 'Tech', 'smartrecur' ) . '</th>';
		echo '<th style="padding:6px 0;">' . esc_html__( 'Location', 'smartrecur' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $rows as $row ) {
			echo '<tr>';
			echo '<td style="padding:6px 0;white-space:nowrap;"><strong>' . esc_html( self::format_date( $row['date'] ) ) . '</strong>';
			if ( ! empty( $row['time'] ) ) {
				echo '<br><small>' . esc_html( $row['time'] ) . '</small>';
			}
			echo '</td>';

			echo '<td style="padding:6px 8px;">' . esc_html( $row['client'] );
			if ( ! empty( $row['syncro_ticket'] ) ) {
				echo ' <span style="font-size:11px;color:#10b981;">#' . esc_html( $row['syncro_ticket'] ) . '</span>';
			}
			echo '</td>';

			echo '<td style="padding:6px 8px;">';
			if ( ! empty( $row['service_color'] ) ) {
				echo '<span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:' . esc_attr( $row['service_color'] ) . ';margin-right:6px;vertical-align:middle;"></span>';
			}
			echo esc_html( $row['service'] ?: __( 'Unknown', 'smartrecur' ) );
			echo '</td>';

			echo '<td style="padding:6px 8px;">' . esc_html( $row['tech'] ?: __( 'Unassigned', 'smartrecur' ) ) . '</td>';

			$location_label = 'ON_SITE' === $row['location'] ? __( 'On site', 'smartrecur' ) : __( 'Remote', 'smartrecur' );
			echo '<td style="padding:6px 0;font-size:11px;color:#6b7280;">' . esc_html( $location_label ) . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';

		echo self::footer_links(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — pre-escaped below.
	}

	/**
	 * Footer of the widget: link to full calendar + settings.
	 *
	 * @return string
	 */
	private static function footer_links() {
		$calendar = esc_url( admin_url( 'admin.php?page=smartrecur-appointments' ) );
		$settings = esc_url( admin_url( 'admin.php?page=smartrecur-settings#dashboard-widget' ) );
		$out  = '<p class="textright" style="margin-top:12px;text-align:right;">';
		$out .= '<a href="' . $calendar . '">' . esc_html__( 'View calendar', 'smartrecur' ) . '</a>';
		$out .= ' &middot; ';
		$out .= '<a href="' . $settings . '">' . esc_html__( 'Widget settings', 'smartrecur' ) . '</a>';
		$out .= '</p>';
		return $out;
	}

	/**
	 * Pull upcoming appointment occurrences from the DB.
	 *
	 * Each appointment has a JSON array of `generated_dates`. We flatten them
	 * out, filter by date window, sort, and trim to the limit.
	 *
	 * @param int $limit      Max occurrences.
	 * @param int $days_ahead Window in days.
	 * @return array
	 */
	private static function fetch_upcoming( $limit, $days_ahead ) {
		global $wpdb;
		$appts_table = $wpdb->prefix . 'smartrecur_appointments';
		$clients_tbl = $wpdb->prefix . 'smartrecur_clients';
		$services_tbl = $wpdb->prefix . 'smartrecur_services';
		$techs_tbl   = $wpdb->prefix . 'smartrecur_technicians';

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT a.id, a.title, a.generated_dates, a.start_time, a.location_type, a.syncro_ticket_id,
					   c.company AS client_company, c.name AS client_name,
					   s.name AS service_name, s.color AS service_color,
					   t.name AS tech_name
				 FROM {$appts_table} a
				 LEFT JOIN {$clients_tbl}   c ON a.client_id = c.id
				 LEFT JOIN {$services_tbl}  s ON a.service_id = s.id
				 LEFT JOIN {$techs_tbl}     t ON a.technician_id = t.id
				 WHERE a.status = %s
				 ORDER BY a.created_at DESC",
				'SCHEDULED'
			),
			ARRAY_A
		);

		$today  = current_time( 'Y-m-d' );
		$cutoff = gmdate( 'Y-m-d', strtotime( '+' . (int) $days_ahead . ' days', strtotime( $today ) ) );

		$flat = array();
		foreach ( (array) $rows as $row ) {
			$dates = json_decode( $row['generated_dates'] ?? '[]', true );
			if ( ! is_array( $dates ) ) {
				continue;
			}
			foreach ( $dates as $date ) {
				if ( $date < $today || $date > $cutoff ) {
					continue;
				}
				$flat[] = array(
					'date'          => $date,
					'time'          => $row['start_time'] ?? '',
					'title'         => $row['title'],
					'client'        => $row['client_company'] ?: ( $row['client_name'] ?: __( 'Unknown client', 'smartrecur' ) ),
					'service'       => $row['service_name'] ?? '',
					'service_color' => $row['service_color'] ?? '',
					'tech'          => $row['tech_name'] ?? '',
					'location'      => $row['location_type'] ?? 'ON_SITE',
					'syncro_ticket' => $row['syncro_ticket_id'] ?? '',
				);
			}
		}

		usort(
			$flat,
			static function ( $a, $b ) {
				return strcmp( $a['date'] . $a['time'], $b['date'] . $b['time'] );
			}
		);

		return array_slice( $flat, 0, max( 1, (int) $limit ) );
	}

	/**
	 * Format a date string for the widget. Uses the WP date format.
	 *
	 * @param string $date YYYY-MM-DD.
	 * @return string
	 */
	private static function format_date( $date ) {
		$ts = strtotime( $date );
		if ( ! $ts ) {
			return $date;
		}
		$today    = current_time( 'Y-m-d' );
		$tomorrow = gmdate( 'Y-m-d', strtotime( '+1 day', strtotime( $today ) ) );

		if ( $date === $today ) {
			return __( 'Today', 'smartrecur' );
		}
		if ( $date === $tomorrow ) {
			return __( 'Tomorrow', 'smartrecur' );
		}
		return date_i18n( get_option( 'date_format', 'D, M j' ), $ts );
	}
}

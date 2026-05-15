<?php
/**
 * Server-rendered calendar view.
 *
 * Builds the month-grid context (weeks of day cells, each with the
 * appointments occurring on that date) for the calendar template. No
 * JavaScript calendar library — pure PHP, navigated with prev/next links.
 *
 * @package SmartRecur
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SmartRecur_Calendar_View {

	/**
	 * Build the month context for the calendar template.
	 *
	 * Reads `?year` and `?month` from the query string, defaulting to the
	 * current month.
	 *
	 * @param string|null $base_url Base URL for navigation links. Defaults to
	 *                              the SmartRecur admin calendar page.
	 * @param string|null $add_url  Base URL for "add appointment" links.
	 * @return array {
	 *     @type int    $year        Displayed year.
	 *     @type int    $month       Displayed month (1-12).
	 *     @type string $label       Human month label.
	 *     @type array  $weeks       Array of weeks; each week is 7 day cells.
	 *     @type string $prev_url    URL of the previous month.
	 *     @type string $next_url    URL of the next month.
	 *     @type string $today_url   URL of the current month.
	 *     @type array  $weekday_labels Localised weekday headers.
	 * }
	 */
	public static function month_context( $base_url = null, $add_url = null ) {
		$today = current_time( 'Y-m-d' );
		$year  = isset( $_GET['year'] ) ? (int) $_GET['year'] : (int) substr( $today, 0, 4 );   // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$month = isset( $_GET['month'] ) ? (int) $_GET['month'] : (int) substr( $today, 5, 2 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		// Normalise.
		if ( $month < 1 )  { $month = 1; }
		if ( $month > 12 ) { $month = 12; }
		if ( $year < 1970 ) { $year = (int) substr( $today, 0, 4 ); }
		if ( $year > 2200 ) { $year = (int) substr( $today, 0, 4 ); }

		$add_url = $add_url ? $add_url : admin_url( 'admin.php?page=smartrecur-appointments&action=edit' );

		$occurrences = self::occurrences_for_month( $year, $month, $add_url );

		$first_dow   = (int) gmdate( 'w', mktime( 0, 0, 0, $month, 1, $year ) ); // 0 = Sunday.
		$days_in_mo  = (int) gmdate( 't', mktime( 0, 0, 0, $month, 1, $year ) );

		$settings    = (array) get_option( 'smartrecur_settings', array() );
		$hours       = $settings['businessHours'] ?? array();
		$closed_days = array_map( 'intval', $hours['closedDays'] ?? array() );
		$holidays    = (array) ( $settings['holidays'] ?? array() );
		$closures    = (array) ( $settings['manualClosures'] ?? array() );

		// Calendar is laid out Monday-first; convert PHP's Sunday=0 offset.
		$lead = ( $first_dow + 6 ) % 7;

		// Build a flat list of cells: leading blanks + day cells + trailing blanks.
		$cells = array();
		for ( $i = 0; $i < $lead; $i++ ) {
			$cells[] = null;
		}
		for ( $d = 1; $d <= $days_in_mo; $d++ ) {
			$date    = sprintf( '%04d-%02d-%02d', $year, $month, $d );
			$dow     = (int) gmdate( 'w', mktime( 0, 0, 0, $month, $d, $year ) );
			$cells[] = array(
				'day'     => $d,
				'date'    => $date,
				'today'   => ( $date === $today ),
				'closed'  => in_array( $dow, $closed_days, true ) || in_array( $date, $holidays, true ) || in_array( $date, $closures, true ),
				'events'  => $occurrences[ $date ] ?? array(),
			);
		}
		while ( count( $cells ) % 7 !== 0 ) {
			$cells[] = null;
		}
		$weeks = array_chunk( $cells, 7 );

		$prev_month = $month - 1;
		$prev_year  = $year;
		if ( $prev_month < 1 ) { $prev_month = 12; $prev_year--; }
		$next_month = $month + 1;
		$next_year  = $year;
		if ( $next_month > 12 ) { $next_month = 1; $next_year++; }

		$base = $base_url ? $base_url : admin_url( 'admin.php?page=smartrecur' );

		return array(
			'year'           => $year,
			'month'          => $month,
			'label'          => date_i18n( 'F Y', mktime( 0, 0, 0, $month, 1, $year ) ),
			'weeks'          => $weeks,
			'add_url'        => $add_url,
			'prev_url'       => add_query_arg( array( 'year' => $prev_year, 'month' => $prev_month ), $base ),
			'next_url'       => add_query_arg( array( 'year' => $next_year, 'month' => $next_month ), $base ),
			'today_url'      => add_query_arg( array( 'year' => (int) substr( $today, 0, 4 ), 'month' => (int) substr( $today, 5, 2 ) ), $base ),
			'weekday_labels' => self::weekday_labels(),
			'can_book'       => current_user_can( 'smartrecur_book' ),
			'hours_label'    => ( $hours['start'] ?? '09:00' ) . ' – ' . ( $hours['end'] ?? '17:00' ),
			'upcoming'       => self::upcoming( 90, $add_url ),
		);
	}

	/**
	 * Upcoming appointment occurrences within the next N days, sorted by date.
	 *
	 * @param int    $days    Look-ahead window.
	 * @param string $add_url Base edit URL.
	 * @return array
	 */
	public static function upcoming( $days = 90, $add_url = '' ) {
		$appointments = SmartRecur_Data::get_appointments();
		$clients      = SmartRecur_Data::index_by_id( SmartRecur_Data::get_clients(), 'company', 'name' );
		$services     = array();
		foreach ( SmartRecur_Data::get_services() as $svc ) {
			$services[ $svc['id'] ] = $svc;
		}

		$today  = current_time( 'Y-m-d' );
		$cutoff = gmdate( 'Y-m-d', strtotime( '+' . (int) $days . ' days', strtotime( $today ) ) );
		$out    = array();

		foreach ( $appointments as $appt ) {
			$dates = json_decode( $appt['generated_dates'] ?? '[]', true );
			if ( ! is_array( $dates ) ) {
				continue;
			}
			$svc = $services[ $appt['service_id'] ] ?? null;
			foreach ( $dates as $date ) {
				if ( $date < $today || $date > $cutoff ) {
					continue;
				}
				$out[] = array(
					'id'       => $appt['id'],
					'title'    => $appt['title'],
					'client'   => $clients[ $appt['client_id'] ] ?? '',
					'date'     => $date,
					'time'     => $appt['start_time'] ?? '',
					'color'    => $svc['color'] ?? '#6366f1',
					'status'   => $appt['status'],
					'edit_url' => $add_url
						? add_query_arg( array( 'action' => 'edit', 'id' => $appt['id'] ), remove_query_arg( 'date', $add_url ) )
						: '',
				);
			}
		}

		usort(
			$out,
			static function ( $a, $b ) {
				return strcmp( $a['date'] . $a['time'], $b['date'] . $b['time'] );
			}
		);

		return $out;
	}

	/**
	 * Map every appointment occurrence in the given month to its date.
	 *
	 * @param int    $year    Year.
	 * @param int    $month   Month.
	 * @param string $add_url Base "add appointment" URL (used only for edit links).
	 * @return array date => array of event descriptors.
	 */
	private static function occurrences_for_month( $year, $month, $add_url = '' ) {
		$appointments = SmartRecur_Data::get_appointments();
		$clients      = SmartRecur_Data::index_by_id( SmartRecur_Data::get_clients(), 'company', 'name' );
		$services     = SmartRecur_Data::get_services();
		$service_idx  = array();
		foreach ( $services as $svc ) {
			$service_idx[ $svc['id'] ] = $svc;
		}

		$prefix = sprintf( '%04d-%02d-', $year, $month );
		$out    = array();

		foreach ( $appointments as $appt ) {
			$dates = json_decode( $appt['generated_dates'] ?? '[]', true );
			if ( ! is_array( $dates ) ) {
				continue;
			}
			foreach ( $dates as $date ) {
				if ( 0 !== strpos( (string) $date, $prefix ) ) {
					continue;
				}
				$svc   = $service_idx[ $appt['service_id'] ] ?? null;
				$color = $svc['color'] ?? '#4F46E5';
				$out[ $date ][] = array(
					'id'       => $appt['id'],
					'title'    => $appt['title'],
					'client'   => $clients[ $appt['client_id'] ] ?? '',
					'time'     => $appt['start_time'] ?? '',
					'color'    => $color,
					'status'   => $appt['status'],
					'edit_url' => add_query_arg(
						array( 'page' => 'smartrecur-appointments', 'action' => 'edit', 'id' => $appt['id'] ),
						admin_url( 'admin.php' )
					),
				);
			}
		}

		foreach ( $out as &$list ) {
			usort(
				$list,
				static function ( $a, $b ) {
					return strcmp( (string) $a['time'], (string) $b['time'] );
				}
			);
		}
		unset( $list );

		return $out;
	}

	/**
	 * Localised short weekday labels, Sunday first.
	 *
	 * @return array
	 */
	private static function weekday_labels() {
		global $wp_locale;
		$labels = array();
		for ( $i = 0; $i < 7; $i++ ) {
			if ( $wp_locale && isset( $wp_locale->weekday_abbrev ) ) {
				$full     = $wp_locale->weekday[ $i ] ?? '';
				$labels[] = $wp_locale->weekday_abbrev[ $full ] ?? substr( $full, 0, 3 );
			} else {
				$labels[] = gmdate( 'D', mktime( 0, 0, 0, 1, 4 + $i, 2026 ) ); // 2026-01-04 is a Sunday.
			}
		}
		return $labels;
	}
}

<?php
/**
 * Server-side recurrence engine.
 *
 * Mirrors the original JavaScript engine (`utils/recurrenceEngine.ts`) so the
 * full date generation runs in PHP without any browser-side dependency.
 *
 * Supported frequencies: YEARLY, HALF_YEARLY, QUARTERLY, MONTHLY.
 * Two pattern types:
 *   - ABSOLUTE: fixed day of month (e.g. the 15th).
 *   - RELATIVE: nth weekday of the month (e.g. first Monday, last Friday).
 *
 * Output: an array of ISO date strings (YYYY-MM-DD), bounded by `max_years`.
 *
 * @package SmartRecur
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SmartRecur_Recurrence_Engine {

	/**
	 * Allowed frequency values.
	 *
	 * @return array
	 */
	public static function frequencies() {
		return array(
			'YEARLY'       => __( 'Yearly', 'smartrecur' ),
			'HALF_YEARLY'  => __( 'Every 6 months', 'smartrecur' ),
			'QUARTERLY'    => __( 'Quarterly', 'smartrecur' ),
			'MONTHLY'      => __( 'Monthly', 'smartrecur' ),
		);
	}

	/**
	 * Ordinal labels for the relative pattern.
	 *
	 * @return array
	 */
	public static function ordinals() {
		return array(
			1  => __( 'First', 'smartrecur' ),
			2  => __( 'Second', 'smartrecur' ),
			3  => __( 'Third', 'smartrecur' ),
			4  => __( 'Fourth', 'smartrecur' ),
			-1 => __( 'Last', 'smartrecur' ),
		);
	}

	/**
	 * Weekday labels (0 = Sunday, 6 = Saturday).
	 *
	 * @return array
	 */
	public static function weekdays() {
		return array(
			0 => __( 'Sunday', 'smartrecur' ),
			1 => __( 'Monday', 'smartrecur' ),
			2 => __( 'Tuesday', 'smartrecur' ),
			3 => __( 'Wednesday', 'smartrecur' ),
			4 => __( 'Thursday', 'smartrecur' ),
			5 => __( 'Friday', 'smartrecur' ),
			6 => __( 'Saturday', 'smartrecur' ),
		);
	}

	/**
	 * Months for the start-month picker.
	 *
	 * @return array
	 */
	public static function months() {
		return array(
			1  => __( 'January', 'smartrecur' ),   2  => __( 'February', 'smartrecur' ),
			3  => __( 'March', 'smartrecur' ),     4  => __( 'April', 'smartrecur' ),
			5  => __( 'May', 'smartrecur' ),       6  => __( 'June', 'smartrecur' ),
			7  => __( 'July', 'smartrecur' ),      8  => __( 'August', 'smartrecur' ),
			9  => __( 'September', 'smartrecur' ), 10 => __( 'October', 'smartrecur' ),
			11 => __( 'November', 'smartrecur' ),  12 => __( 'December', 'smartrecur' ),
		);
	}

	/**
	 * Generate occurrence dates from a structured rule config.
	 *
	 * @param array $config Rule config with keys:
	 *   - frequency (string)
	 *   - pattern_type ('ABSOLUTE' | 'RELATIVE')
	 *   - day_of_month (int, for ABSOLUTE)
	 *   - ordinal (int, for RELATIVE: 1..4 or -1)
	 *   - weekday (int 0-6, for RELATIVE)
	 *   - start_month (int 1-12)
	 *   - start_year (int)
	 *   - max_years (int)  default 5
	 * @return array<string> ISO YYYY-MM-DD dates.
	 */
	public static function generate( array $config ) {
		$frequency    = isset( $config['frequency'] ) ? (string) $config['frequency'] : 'MONTHLY';
		$pattern      = isset( $config['pattern_type'] ) ? (string) $config['pattern_type'] : 'ABSOLUTE';
		$day_of_month = isset( $config['day_of_month'] ) ? max( 1, min( 31, (int) $config['day_of_month'] ) ) : 1;
		$ordinal      = isset( $config['ordinal'] ) ? (int) $config['ordinal'] : 1;
		$weekday      = isset( $config['weekday'] ) ? max( 0, min( 6, (int) $config['weekday'] ) ) : 1;
		$start_month  = isset( $config['start_month'] ) ? max( 1, min( 12, (int) $config['start_month'] ) ) : (int) gmdate( 'n' );
		$start_year   = isset( $config['start_year'] ) ? max( 1970, min( 2200, (int) $config['start_year'] ) ) : (int) gmdate( 'Y' );
		$max_years    = isset( $config['max_years'] ) ? max( 1, min( 10, (int) $config['max_years'] ) ) : 5;

		$step = self::months_per_period( $frequency );
		if ( ! $step ) {
			return array();
		}

		$dates  = array();
		$cursor = self::make_date( $start_year, $start_month, 1 );
		$end    = self::make_date( $start_year + $max_years, $start_month, 1 );

		while ( $cursor <= $end ) {
			$y = (int) $cursor->format( 'Y' );
			$m = (int) $cursor->format( 'n' );

			if ( 'ABSOLUTE' === $pattern ) {
				$dim = (int) gmdate( 't', $cursor->getTimestamp() );
				$d   = min( $day_of_month, $dim );
				$dates[] = sprintf( '%04d-%02d-%02d', $y, $m, $d );
			} else {
				$resolved = self::nth_weekday_of_month( $y, $m, $ordinal, $weekday );
				if ( $resolved ) {
					$dates[] = $resolved;
				}
			}

			$cursor->modify( '+' . $step . ' months' );
		}

		return $dates;
	}

	/**
	 * Number of months between recurrences for each frequency.
	 *
	 * @param string $frequency Frequency code.
	 * @return int|null
	 */
	private static function months_per_period( $frequency ) {
		switch ( $frequency ) {
			case 'YEARLY':      return 12;
			case 'HALF_YEARLY': return 6;
			case 'QUARTERLY':   return 3;
			case 'MONTHLY':     return 1;
		}
		return null;
	}

	/**
	 * Resolve the Nth weekday of a given month — e.g. "third Monday of March 2026".
	 *
	 * @param int $year     Year.
	 * @param int $month    Month 1-12.
	 * @param int $ordinal  1, 2, 3, 4 or -1 (last).
	 * @param int $weekday  0 (Sun) - 6 (Sat).
	 * @return string|null ISO date or null.
	 */
	public static function nth_weekday_of_month( $year, $month, $ordinal, $weekday ) {
		$dim = (int) gmdate( 't', mktime( 0, 0, 0, $month, 1, $year ) );

		if ( $ordinal === -1 ) {
			// Walk back from the last day.
			for ( $d = $dim; $d >= 1; $d-- ) {
				if ( (int) gmdate( 'w', mktime( 0, 0, 0, $month, $d, $year ) ) === $weekday ) {
					return sprintf( '%04d-%02d-%02d', $year, $month, $d );
				}
			}
			return null;
		}

		$found = 0;
		for ( $d = 1; $d <= $dim; $d++ ) {
			if ( (int) gmdate( 'w', mktime( 0, 0, 0, $month, $d, $year ) ) === $weekday ) {
				$found++;
				if ( $found === $ordinal ) {
					return sprintf( '%04d-%02d-%02d', $year, $month, $d );
				}
			}
		}
		return null;
	}

	/**
	 * Render a human-readable description of the rule.
	 *
	 * @param array $config Rule config.
	 * @return string
	 */
	public static function describe( array $config ) {
		$freqs  = self::frequencies();
		$ords   = self::ordinals();
		$days   = self::weekdays();
		$months = self::months();

		$f = $config['frequency'] ?? 'MONTHLY';
		$out = $freqs[ $f ] ?? $f;

		if ( ( $config['pattern_type'] ?? 'ABSOLUTE' ) === 'RELATIVE' ) {
			$ord = $ords[ $config['ordinal'] ?? 1 ] ?? '';
			$wd  = $days[ $config['weekday'] ?? 1 ] ?? '';
			$out .= ' — ' . strtolower( $ord ) . ' ' . $wd;
		} else {
			$out .= ' — ' . __( 'day', 'smartrecur' ) . ' ' . ( $config['day_of_month'] ?? 1 );
		}

		if ( ! empty( $config['start_month'] ) ) {
			$mo = $months[ (int) $config['start_month'] ] ?? '';
			$out .= ' (' . __( 'from', 'smartrecur' ) . ' ' . $mo . ')';
		}
		return $out;
	}

	/**
	 * Helper: build a DateTime in UTC at midnight.
	 *
	 * @param int $year  Year.
	 * @param int $month Month.
	 * @param int $day   Day.
	 * @return DateTime
	 */
	private static function make_date( $year, $month, $day ) {
		return new DateTime( sprintf( '%04d-%02d-%02d', $year, $month, $day ), new DateTimeZone( 'UTC' ) );
	}
}

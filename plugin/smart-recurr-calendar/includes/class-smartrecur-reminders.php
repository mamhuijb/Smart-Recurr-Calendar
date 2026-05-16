<?php
/**
 * Reminder email scheduler.
 *
 * A once-daily WP-Cron job scans scheduled appointments and, for any occurrence
 * landing exactly N days from today (where N is one of the configured reminder
 * days), emails the client. A per-occurrence "sent" marker stored in a
 * transient stops the same reminder going out twice.
 *
 * @package SmartRecur
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SmartRecur_Reminders {

	const CRON_HOOK = 'smartrecur_send_reminders';

	/**
	 * Wire the cron hook + schedule.
	 */
	public static function register() {
		add_action( self::CRON_HOOK, array( __CLASS__, 'run' ) );
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			// First run tomorrow at ~07:00 site time.
			wp_schedule_event( self::next_morning(), 'daily', self::CRON_HOOK );
		}
	}

	/**
	 * Timestamp for the next 07:00 in the site timezone.
	 *
	 * @return int
	 */
	private static function next_morning() {
		$now    = current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
		$target = strtotime( gmdate( 'Y-m-d 07:00:00', $now ) );
		if ( $target <= $now ) {
			$target += DAY_IN_SECONDS;
		}
		// Convert site-local target back to a UTC timestamp for wp_schedule_event.
		return $target - ( (int) ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) );
	}

	/**
	 * Cron callback: send any reminders due today.
	 */
	public static function run() {
		$settings = (array) get_option( 'smartrecur_settings', array() );
		if ( empty( $settings['notifications']['emailEnabled'] ) ) {
			return;
		}

		$global_days = isset( $settings['reminders']['days'] ) && is_array( $settings['reminders']['days'] )
			? array_map( 'absint', $settings['reminders']['days'] )
			: array( 14, 7, 1 );

		$today        = current_time( 'Y-m-d' );
		$appointments = SmartRecur_Data::get_appointments();

		foreach ( $appointments as $appt ) {
			if ( 'SCHEDULED' !== ( $appt['status'] ?? '' ) ) {
				continue;
			}
			$dates = json_decode( $appt['generated_dates'] ?? '[]', true );
			if ( ! is_array( $dates ) ) {
				continue;
			}

			// Per-service reminder-day override, falling back to the global list.
			$days = $global_days;
			if ( ! empty( $appt['service_id'] ) ) {
				$service = SmartRecur_Data::get_service( $appt['service_id'] );
				if ( $service && ! empty( $service['reminder_days'] ) ) {
					$svc_days = json_decode( $service['reminder_days'], true );
					if ( is_array( $svc_days ) && $svc_days ) {
						$days = array_map( 'absint', $svc_days );
					}
				}
			}

			foreach ( $dates as $date ) {
				if ( $date < $today ) {
					continue;
				}
				$days_until = (int) round( ( strtotime( $date ) - strtotime( $today ) ) / DAY_IN_SECONDS );
				if ( ! in_array( $days_until, $days, true ) ) {
					continue;
				}

				// De-dupe: one reminder per appointment+occurrence+interval.
				$marker = 'smartrecur_rem_' . md5( $appt['id'] . '|' . $date . '|' . $days_until );
				if ( get_transient( $marker ) ) {
					continue;
				}
				set_transient( $marker, 1, 3 * DAY_IN_SECONDS );

				SmartRecur_Mailer::send_reminder( $appt, $date );
			}
		}
	}

	/**
	 * Drop the scheduled cron event.
	 */
	public static function unschedule() {
		$ts = wp_next_scheduled( self::CRON_HOOK );
		while ( $ts ) {
			wp_unschedule_event( $ts, self::CRON_HOOK );
			$ts = wp_next_scheduled( self::CRON_HOOK );
		}
	}
}

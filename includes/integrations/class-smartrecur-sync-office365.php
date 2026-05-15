<?php
/**
 * Two-way Office 365 calendar sync engine.
 *
 * Push (SmartRecur → Outlook): hooks into appointment save/delete actions.
 * Pull (Outlook → SmartRecur): WP-Cron, every 15 minutes by default.
 *
 * Each SmartRecur appointment row carries an `o365_event_id` column. Whenever
 * we push an appointment to Outlook we store the returned event ID. Whenever
 * we pull from Outlook, we look up the row by this ID — present means update,
 * absent means create a new SmartRecur appointment so the user sees their
 * Outlook entry inside the plugin.
 *
 * "Last write wins" conflict resolution: pull treats Outlook as the source of
 * truth for events that exist on both sides; push always overwrites the
 * Outlook copy when the SmartRecur side changes.
 *
 * @package SmartRecur
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SmartRecur_Sync_Office365 {

	const CRON_HOOK = 'smartrecur_o365_sync_tick';

	/**
	 * Register hooks.
	 */
	public static function register() {
		add_action( 'smartrecur_appointment_saved',   array( __CLASS__, 'on_saved' ),   10, 2 );
		add_action( 'smartrecur_appointment_deleted', array( __CLASS__, 'on_deleted' ), 10, 1 );
		add_action( self::CRON_HOOK,                  array( __CLASS__, 'pull_calendar' ) );
		add_filter( 'cron_schedules',                 array( __CLASS__, 'cron_schedules' ) );

		if ( ! wp_next_scheduled( self::CRON_HOOK ) && self::is_enabled() ) {
			wp_schedule_event( time() + 60, 'smartrecur_15min', self::CRON_HOOK );
		}
	}

	/**
	 * Add a 15-minute interval to WP-Cron.
	 *
	 * @param array $schedules Existing schedules.
	 * @return array
	 */
	public static function cron_schedules( $schedules ) {
		if ( ! isset( $schedules['smartrecur_15min'] ) ) {
			$schedules['smartrecur_15min'] = array(
				'interval' => 15 * MINUTE_IN_SECONDS,
				'display'  => __( 'Every 15 minutes (SmartRecur)', 'smartrecur' ),
			);
		}
		return $schedules;
	}

	/**
	 * Sync is enabled when Office 365 is connected AND a calendar is selected.
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		$config = SmartRecur_Integration_Config::get( 'office365' );
		return ! empty( $config['accessToken'] ) && ! empty( $config['calendarId'] );
	}

	/**
	 * Hook: an appointment was saved (created or updated). Push it to Outlook
	 * and store the resulting Graph event ID.
	 *
	 * @param string $appointment_id SmartRecur UUID.
	 * @param array  $appointment    Frontend-shaped appointment.
	 */
	public static function on_saved( $appointment_id, $appointment ) {
		if ( ! self::is_enabled() ) {
			return;
		}
		$config = SmartRecur_Integration_Config::get( 'office365' );
		if ( ! SmartRecur_Office365::ensure_valid_token( $config ) ) {
			return;
		}
		SmartRecur_Integration_Config::put( 'office365', $config );

		// Avoid loops: if this save was triggered by the pull, skip the push.
		if ( get_transient( 'smartrecur_o365_inbound_' . $appointment_id ) ) {
			delete_transient( 'smartrecur_o365_inbound_' . $appointment_id );
			return;
		}

		self::push_appointment( $appointment_id, $appointment, $config );
	}

	/**
	 * Hook: an appointment was deleted from SmartRecur. Mirror the deletion in
	 * Outlook for any linked events.
	 *
	 * @param string $appointment_id SmartRecur UUID.
	 */
	public static function on_deleted( $appointment_id ) {
		if ( ! self::is_enabled() ) {
			return;
		}
		$event_ids = self::lookup_o365_event_ids_for_deleted( $appointment_id );
		if ( empty( $event_ids ) ) {
			return;
		}

		$config = SmartRecur_Integration_Config::get( 'office365' );
		if ( ! SmartRecur_Office365::ensure_valid_token( $config ) ) {
			return;
		}
		SmartRecur_Integration_Config::put( 'office365', $config );

		foreach ( $event_ids as $event_id ) {
			SmartRecur_Office365::delete_calendar_event( $config, $event_id, $config['calendarId'] );
		}
	}

	/**
	 * Push a single SmartRecur appointment to Outlook.
	 *
	 * One appointment can have many `generated_dates`; we create one Graph
	 * event per date and store the IDs as a JSON array in `o365_event_id`.
	 *
	 * @param string $appointment_id Appointment UUID.
	 * @param array  $appointment    Appointment data (frontend shape).
	 * @param array  $config         Office 365 config (already token-refreshed).
	 */
	public static function push_appointment( $appointment_id, array $appointment, array $config ) {
		global $wpdb;
		$table       = $wpdb->prefix . 'smartrecur_appointments';
		$calendar_id = $config['calendarId'];
		$timezone    = self::timezone();

		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %s", $appointment_id ), ARRAY_A );
		if ( ! $row ) {
			return;
		}

		$dates       = json_decode( $row['generated_dates'] ?? '[]', true );
		$dates       = is_array( $dates ) ? $dates : array();
		$existing    = json_decode( $row['o365_event_id'] ?? '[]', true );
		$existing    = is_array( $existing ) ? $existing : array();
		$start_time  = $row['start_time'] ?: '09:00';
		$end_time    = $row['end_time']   ?: '10:00';
		$description = self::compose_description( $row );

		$updated_ids = array();
		foreach ( $dates as $i => $date ) {
			$payload = self::event_payload( $row['title'], $description, $date, $start_time, $end_time, $timezone, $appointment_id );
			$existing_id = $existing[ $i ] ?? '';

			if ( $existing_id ) {
				$result = SmartRecur_Office365::update_calendar_event( $config, $existing_id, $payload, $calendar_id );
				if ( ! empty( $result['success'] ) ) {
					$updated_ids[] = $result['eventId'];
					continue;
				}
				// Update failed (event maybe deleted in Outlook) — fall through to create.
			}

			$result = SmartRecur_Office365::create_calendar_event( $config, $payload, $calendar_id );
			if ( ! empty( $result['success'] ) ) {
				$updated_ids[] = $result['eventId'];
			}
		}

		// If we have fewer dates now than before, delete the leftover Graph events.
		if ( count( $existing ) > count( $updated_ids ) ) {
			for ( $i = count( $updated_ids ); $i < count( $existing ); $i++ ) {
				if ( ! empty( $existing[ $i ] ) ) {
					SmartRecur_Office365::delete_calendar_event( $config, $existing[ $i ], $calendar_id );
				}
			}
		}

		$wpdb->update(
			$table,
			array( 'o365_event_id' => wp_json_encode( $updated_ids ) ),
			array( 'id' => $appointment_id ),
			array( '%s' ),
			array( '%s' )
		);
	}

	/**
	 * Pull from the configured Outlook calendar. Runs on WP-Cron.
	 *
	 * Window: today − 7 days through today + 365 days. Each Outlook event is
	 * matched against an existing SmartRecur appointment by its Graph event ID;
	 * unmatched events become new SmartRecur appointments.
	 */
	public static function pull_calendar() {
		if ( ! self::is_enabled() ) {
			return;
		}

		// Single-flight lock — prevents two cron runs (or a cron run racing a
		// "Sync now" click) from double-importing the same Outlook events.
		$lock = 'smartrecur_o365_sync_lock';
		if ( get_transient( $lock ) ) {
			return;
		}
		set_transient( $lock, 1, 5 * MINUTE_IN_SECONDS );

		try {
			self::pull_calendar_unlocked();
		} finally {
			delete_transient( $lock );
		}
	}

	/**
	 * Actual pull body, run inside the single-flight lock.
	 */
	private static function pull_calendar_unlocked() {
		$config = SmartRecur_Integration_Config::get( 'office365' );
		if ( ! SmartRecur_Office365::ensure_valid_token( $config ) ) {
			return;
		}
		SmartRecur_Integration_Config::put( 'office365', $config );

		$tz       = self::timezone();
		$from_ts  = strtotime( '-7 days' );
		$to_ts    = strtotime( '+365 days' );
		$from_iso = gmdate( 'Y-m-d\TH:i:s\Z', $from_ts );
		$to_iso   = gmdate( 'Y-m-d\TH:i:s\Z', $to_ts );

		$events = SmartRecur_Office365::list_events( $config, $config['calendarId'], $from_iso, $to_iso );
		if ( empty( $events ) ) {
			SmartRecur_Integration_Config::put( 'office365', array_merge( $config, array( 'lastSyncedAt' => time() ) ) );
			return;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'smartrecur_appointments';

		// Build an index of o365_event_id → SmartRecur appointment id.
		$rows  = $wpdb->get_results( "SELECT id, o365_event_id FROM {$table} WHERE o365_event_id IS NOT NULL AND o365_event_id != ''", ARRAY_A );
		$index = array();
		foreach ( (array) $rows as $r ) {
			$ids = json_decode( $r['o365_event_id'] ?? '[]', true );
			if ( ! is_array( $ids ) ) {
				continue;
			}
			foreach ( $ids as $oid ) {
				if ( $oid ) {
					$index[ $oid ] = $r['id'];
				}
			}
		}

		foreach ( $events as $event ) {
			if ( empty( $event['id'] ) || empty( $event['start']['dateTime'] ) || empty( $event['end']['dateTime'] ) ) {
				continue;
			}
			$o365_id = $event['id'];

			$start_dt = self::to_local_datetime( $event['start']['dateTime'], $event['start']['timeZone'] ?? 'UTC', $tz );
			$end_dt   = self::to_local_datetime( $event['end']['dateTime'],   $event['end']['timeZone']   ?? 'UTC', $tz );
			if ( ! $start_dt || ! $end_dt ) {
				continue;
			}
			$date  = $start_dt->format( 'Y-m-d' );
			$start = $start_dt->format( 'H:i' );
			$end   = $end_dt->format( 'H:i' );

			$title = isset( $event['subject'] ) ? wp_strip_all_tags( (string) $event['subject'] ) : __( '(Outlook event)', 'smartrecur' );

			if ( isset( $index[ $o365_id ] ) ) {
				// Existing — update title/time/date if Outlook side changed.
				$appt_id = $index[ $o365_id ];
				set_transient( 'smartrecur_o365_inbound_' . $appt_id, 1, 30 );
				$wpdb->update(
					$table,
					array(
						'title'           => $title,
						'start_time'      => $start,
						'end_time'        => $end,
						'generated_dates' => wp_json_encode( array( $date ) ),
					),
					array( 'id' => $appt_id ),
					array( '%s', '%s', '%s', '%s' ),
					array( '%s' )
				);
			} else {
				// New — create a placeholder SmartRecur appointment so the
				// Outlook entry shows up inside the plugin.
				$new_id = SmartRecur_UUID::v4();
				set_transient( 'smartrecur_o365_inbound_' . $new_id, 1, 30 );
				$wpdb->insert(
					$table,
					array(
						'id'               => $new_id,
						'title'            => $title,
						'client_id'        => '',
						'service_id'       => '',
						'technician_id'    => null,
						'asset_id'         => null,
						'syncro_ticket_id' => null,
						'location_type'    => 'ON_SITE',
						'description'      => isset( $event['bodyPreview'] ) ? wp_strip_all_tags( (string) $event['bodyPreview'] ) : '',
						'recurrence_rule'  => '',
						'generated_dates'  => wp_json_encode( array( $date ) ),
						'start_time'       => $start,
						'end_time'         => $end,
						'status'           => 'SCHEDULED',
						'o365_event_id'    => wp_json_encode( array( $o365_id ) ),
					),
					array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
				);
			}
		}

		$config['lastSyncedAt'] = time();
		SmartRecur_Integration_Config::put( 'office365', $config );
	}

	/**
	 * Compose the Outlook event body from a SmartRecur appointment row.
	 *
	 * @param array $row DB row.
	 * @return string
	 */
	private static function compose_description( array $row ) {
		global $wpdb;
		$client_table  = $wpdb->prefix . 'smartrecur_clients';
		$service_table = $wpdb->prefix . 'smartrecur_services';
		$tech_table    = $wpdb->prefix . 'smartrecur_technicians';

		$client_name = '';
		$service     = '';
		$tech        = '';
		if ( ! empty( $row['client_id'] ) ) {
			$client = $wpdb->get_row( $wpdb->prepare( "SELECT company, name FROM {$client_table} WHERE id = %s", $row['client_id'] ), ARRAY_A );
			$client_name = $client['company'] ?: $client['name'] ?? '';
		}
		if ( ! empty( $row['service_id'] ) ) {
			$service = (string) $wpdb->get_var( $wpdb->prepare( "SELECT name FROM {$service_table} WHERE id = %s", $row['service_id'] ) );
		}
		if ( ! empty( $row['technician_id'] ) ) {
			$tech = (string) $wpdb->get_var( $wpdb->prepare( "SELECT name FROM {$tech_table} WHERE id = %s", $row['technician_id'] ) );
		}

		$lines = array();
		if ( $client_name ) { $lines[] = 'Client: ' . $client_name; }
		if ( $service )     { $lines[] = 'Service: ' . $service; }
		if ( $tech )        { $lines[] = 'Technician: ' . $tech; }
		$lines[] = 'Location: ' . ( $row['location_type'] ?? 'ON_SITE' );
		if ( ! empty( $row['description'] ) ) {
			$lines[] = '';
			$lines[] = $row['description'];
		}
		return implode( "\n", $lines );
	}

	/**
	 * Build the Microsoft Graph event payload.
	 *
	 * @param string $subject     Event title.
	 * @param string $description Body content.
	 * @param string $date        YYYY-MM-DD.
	 * @param string $start       HH:MM.
	 * @param string $end         HH:MM.
	 * @param string $tz          Timezone name.
	 * @param string $smartrecur_id  SmartRecur appointment UUID for round-trip linking.
	 * @return array
	 */
	private static function event_payload( $subject, $description, $date, $start, $end, $tz, $smartrecur_id ) {
		return array(
			'subject' => $subject,
			'body'    => array(
				'contentType' => 'Text',
				'content'     => $description,
			),
			'start'   => array(
				'dateTime' => $date . 'T' . $start . ':00',
				'timeZone' => $tz,
			),
			'end'     => array(
				'dateTime' => $date . 'T' . $end . ':00',
				'timeZone' => $tz,
			),
			'singleValueExtendedProperties' => array(
				array(
					// String type, GUID matches our plugin namespace.
					'id'    => 'String {18ed1f6c-c9b8-4b87-92a5-1b6a47b35e2a} Name SmartRecurId',
					'value' => $smartrecur_id,
				),
			),
		);
	}

	/**
	 * Convert a Graph dateTime + timezone into the configured local timezone.
	 *
	 * @param string $datetime    ISO datetime without offset.
	 * @param string $source_tz   Timezone name from Graph.
	 * @param string $target_tz   Local timezone name.
	 * @return DateTime|null
	 */
	private static function to_local_datetime( $datetime, $source_tz, $target_tz ) {
		try {
			$dt = new DateTime( $datetime, new DateTimeZone( $source_tz ?: 'UTC' ) );
			$dt->setTimezone( new DateTimeZone( $target_tz ) );
			return $dt;
		} catch ( Exception $e ) {
			return null;
		}
	}

	/**
	 * Configured timezone (settings → Europe/Amsterdam fallback).
	 *
	 * @return string
	 */
	private static function timezone() {
		$settings = (array) get_option( 'smartrecur_settings', array() );
		return isset( $settings['businessHours']['timezone'] ) && $settings['businessHours']['timezone']
			? $settings['businessHours']['timezone']
			: 'Europe/Amsterdam';
	}

	/**
	 * Look up Graph event IDs that were associated with a now-deleted appointment.
	 *
	 * Called on the `smartrecur_appointment_deleted` hook *before* the row is
	 * actually removed, so we still have the o365_event_id JSON to read.
	 *
	 * @param string $appointment_id SmartRecur UUID.
	 * @return array
	 */
	private static function lookup_o365_event_ids_for_deleted( $appointment_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'smartrecur_appointments';
		$json  = (string) $wpdb->get_var( $wpdb->prepare( "SELECT o365_event_id FROM {$table} WHERE id = %s", $appointment_id ) );
		$ids   = json_decode( $json, true );
		return is_array( $ids ) ? array_filter( $ids ) : array();
	}

	/**
	 * Drop the cron schedule. Called from the deactivator and when O365 is
	 * disconnected.
	 */
	public static function unschedule() {
		$ts = wp_next_scheduled( self::CRON_HOOK );
		while ( $ts ) {
			wp_unschedule_event( $ts, self::CRON_HOOK );
			$ts = wp_next_scheduled( self::CRON_HOOK );
		}
	}
}

<?php
/**
 * Central data-access layer for SmartRecur entities.
 *
 * Both the native admin pages and the REST controllers go through this class
 * so query logic, sanitisation, and the camelCase ↔ snake_case mapping live
 * in exactly one place.
 *
 * @package SmartRecur
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SmartRecur_Data {

	/**
	 * Fully-qualified table name.
	 *
	 * @param string $entity One of: appointments, clients, assets, services, technicians, recurring_rules.
	 * @return string
	 */
	public static function table( $entity ) {
		global $wpdb;
		return $wpdb->prefix . 'smartrecur_' . $entity;
	}

	/* ---------------------------------------------------------------------
	 * Clients.
	 * ------------------------------------------------------------------- */

	/**
	 * All clients ordered by company then name.
	 *
	 * @return array
	 */
	public static function get_clients() {
		global $wpdb;
		$table = self::table( 'clients' );
		$rows  = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY company ASC, name ASC", ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Single client by ID.
	 *
	 * @param string $id Client UUID.
	 * @return array|null
	 */
	public static function get_client( $id ) {
		global $wpdb;
		$table = self::table( 'clients' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %s", $id ), ARRAY_A );
	}

	/**
	 * Insert or update a client.
	 *
	 * @param array $data Sanitised client fields (snake_case columns).
	 * @return string Client ID.
	 */
	public static function save_client( array $data ) {
		global $wpdb;
		$table = self::table( 'clients' );
		$id    = ! empty( $data['id'] ) ? $data['id'] : SmartRecur_UUID::v4();

		$fields = array(
			'company'         => isset( $data['company'] ) ? sanitize_text_field( $data['company'] ) : '',
			'name'            => isset( $data['name'] ) ? sanitize_text_field( $data['name'] ) : '',
			'email'           => isset( $data['email'] ) ? sanitize_email( $data['email'] ) : '',
			'phone'           => isset( $data['phone'] ) ? sanitize_text_field( $data['phone'] ) : '',
			'address'         => isset( $data['address'] ) ? sanitize_textarea_field( $data['address'] ) : '',
			'postcode'        => isset( $data['postcode'] ) ? sanitize_text_field( $data['postcode'] ) : '',
			'syncro_id'       => ! empty( $data['syncro_id'] ) ? sanitize_text_field( $data['syncro_id'] ) : null,
			'invoiceninja_id' => ! empty( $data['invoiceninja_id'] ) ? sanitize_text_field( $data['invoiceninja_id'] ) : null,
			'modified_by'     => get_current_user_id(),
		);
		$formats = array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d' );

		if ( self::get_client( $id ) ) {
			$wpdb->update( $table, $fields, array( 'id' => $id ), $formats, array( '%s' ) );
		} else {
			$fields['id']         = $id;
			$fields['created_by'] = get_current_user_id();
			$insert_formats       = array_merge( $formats, array( '%s', '%d' ) );
			$wpdb->insert( $table, $fields, $insert_formats );
		}
		return $id;
	}

	/**
	 * Delete a client and its assets.
	 *
	 * @param string $id Client UUID.
	 */
	public static function delete_client( $id ) {
		global $wpdb;
		$wpdb->delete( self::table( 'assets' ), array( 'client_id' => $id ), array( '%s' ) );
		$wpdb->delete( self::table( 'clients' ), array( 'id' => $id ), array( '%s' ) );
	}

	/* ---------------------------------------------------------------------
	 * Services.
	 * ------------------------------------------------------------------- */

	/**
	 * All services ordered by name.
	 *
	 * @return array
	 */
	public static function get_services() {
		global $wpdb;
		$table = self::table( 'services' );
		$rows  = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY name ASC", ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Single service by ID.
	 *
	 * @param string $id Service UUID.
	 * @return array|null
	 */
	public static function get_service( $id ) {
		global $wpdb;
		$table = self::table( 'services' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %s", $id ), ARRAY_A );
	}

	/**
	 * Insert or update a service.
	 *
	 * @param array $data Sanitised service fields.
	 * @return string Service ID.
	 */
	public static function save_service( array $data ) {
		global $wpdb;
		$table = self::table( 'services' );
		$id    = ! empty( $data['id'] ) ? $data['id'] : SmartRecur_UUID::v4();

		$reminders = isset( $data['reminder_days'] ) && is_array( $data['reminder_days'] )
			? array_values( array_filter( array_map( 'absint', $data['reminder_days'] ) ) )
			: array();

		$fields = array(
			'name'                   => isset( $data['name'] ) ? sanitize_text_field( $data['name'] ) : '',
			'type'                   => in_array( $data['type'] ?? '', array( 'RECURRING', 'ONE_TIME' ), true ) ? $data['type'] : 'RECURRING',
			'default_duration_min'   => isset( $data['default_duration_min'] ) ? absint( $data['default_duration_min'] ) : 60,
			'default_location'       => in_array( $data['default_location'] ?? '', array( 'REMOTE', 'ON_SITE' ), true ) ? $data['default_location'] : 'ON_SITE',
			'color'                  => ! empty( $data['color'] ) ? sanitize_hex_color( $data['color'] ) : '#4F46E5',
			'create_ticket'          => ! empty( $data['create_ticket'] ) ? 1 : 0,
			'email_template_subject' => isset( $data['email_template_subject'] ) ? sanitize_text_field( $data['email_template_subject'] ) : null,
			'email_template_body'    => isset( $data['email_template_body'] ) ? sanitize_textarea_field( $data['email_template_body'] ) : null,
			'reminder_days'          => ! empty( $reminders ) ? wp_json_encode( $reminders ) : null,
		);
		$formats = array( '%s', '%s', '%d', '%s', '%s', '%d', '%s', '%s', '%s' );

		if ( self::get_service( $id ) ) {
			$wpdb->update( $table, $fields, array( 'id' => $id ), $formats, array( '%s' ) );
		} else {
			$fields['id'] = $id;
			$wpdb->insert( $table, $fields, array_merge( $formats, array( '%s' ) ) );
		}
		return $id;
	}

	/**
	 * Delete a service.
	 *
	 * @param string $id Service UUID.
	 */
	public static function delete_service( $id ) {
		global $wpdb;
		$wpdb->delete( self::table( 'services' ), array( 'id' => $id ), array( '%s' ) );
	}

	/* ---------------------------------------------------------------------
	 * Technicians.
	 * ------------------------------------------------------------------- */

	/**
	 * All technicians ordered by name.
	 *
	 * @return array
	 */
	public static function get_technicians() {
		global $wpdb;
		$table = self::table( 'technicians' );
		$rows  = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY name ASC", ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Single technician by ID.
	 *
	 * @param string $id Technician UUID.
	 * @return array|null
	 */
	public static function get_technician( $id ) {
		global $wpdb;
		$table = self::table( 'technicians' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %s", $id ), ARRAY_A );
	}

	/**
	 * Insert or update a technician.
	 *
	 * @param array $data Sanitised technician fields.
	 * @return string Technician ID.
	 */
	public static function save_technician( array $data ) {
		global $wpdb;
		$table = self::table( 'technicians' );
		$id    = ! empty( $data['id'] ) ? $data['id'] : SmartRecur_UUID::v4();

		$skills = isset( $data['skills'] ) && is_array( $data['skills'] )
			? array_map( 'sanitize_text_field', $data['skills'] )
			: array();

		$fields = array(
			'name'   => isset( $data['name'] ) ? sanitize_text_field( $data['name'] ) : '',
			'email'  => isset( $data['email'] ) ? sanitize_email( $data['email'] ) : '',
			'color'  => ! empty( $data['color'] ) ? sanitize_hex_color( $data['color'] ) : '#10B981',
			'skills' => wp_json_encode( $skills ),
		);
		$formats = array( '%s', '%s', '%s', '%s' );

		if ( self::get_technician( $id ) ) {
			$wpdb->update( $table, $fields, array( 'id' => $id ), $formats, array( '%s' ) );
		} else {
			$fields['id'] = $id;
			$wpdb->insert( $table, $fields, array_merge( $formats, array( '%s' ) ) );
		}
		return $id;
	}

	/**
	 * Delete a technician.
	 *
	 * @param string $id Technician UUID.
	 */
	public static function delete_technician( $id ) {
		global $wpdb;
		$wpdb->delete( self::table( 'technicians' ), array( 'id' => $id ), array( '%s' ) );
	}

	/* ---------------------------------------------------------------------
	 * Appointments.
	 * ------------------------------------------------------------------- */

	/**
	 * All appointments, newest first.
	 *
	 * @return array
	 */
	public static function get_appointments() {
		global $wpdb;
		$table = self::table( 'appointments' );
		$rows  = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC", ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Single appointment by ID.
	 *
	 * @param string $id Appointment UUID.
	 * @return array|null
	 */
	public static function get_appointment( $id ) {
		global $wpdb;
		$table = self::table( 'appointments' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %s", $id ), ARRAY_A );
	}

	/**
	 * Insert or update an appointment. Fires the smartrecur_appointment_saved
	 * hook so the Office 365 sync engine can push the change.
	 *
	 * @param array $data Sanitised appointment fields (snake_case).
	 * @return string Appointment ID.
	 */
	public static function save_appointment( array $data ) {
		global $wpdb;
		$table     = self::table( 'appointments' );
		$id        = ! empty( $data['id'] ) ? $data['id'] : SmartRecur_UUID::v4();
		$is_update = (bool) self::get_appointment( $id );

		$generated = isset( $data['generated_dates'] ) && is_array( $data['generated_dates'] )
			? array_values( array_filter( array_map( 'sanitize_text_field', $data['generated_dates'] ) ) )
			: array();

		$fields = array(
			'title'            => isset( $data['title'] ) ? sanitize_text_field( $data['title'] ) : '',
			'client_id'        => isset( $data['client_id'] ) ? sanitize_text_field( $data['client_id'] ) : '',
			'service_id'       => isset( $data['service_id'] ) ? sanitize_text_field( $data['service_id'] ) : '',
			'technician_id'    => ! empty( $data['technician_id'] ) ? sanitize_text_field( $data['technician_id'] ) : null,
			'asset_id'         => ! empty( $data['asset_id'] ) ? sanitize_text_field( $data['asset_id'] ) : null,
			'syncro_ticket_id' => ! empty( $data['syncro_ticket_id'] ) ? sanitize_text_field( $data['syncro_ticket_id'] ) : null,
			'location_type'    => in_array( $data['location_type'] ?? '', array( 'REMOTE', 'ON_SITE' ), true ) ? $data['location_type'] : 'ON_SITE',
			'description'      => isset( $data['description'] ) ? sanitize_textarea_field( $data['description'] ) : '',
			'recurrence_rule'  => isset( $data['recurrence_rule'] ) ? sanitize_text_field( $data['recurrence_rule'] ) : '',
			'generated_dates'  => wp_json_encode( $generated ),
			'start_time'       => ! empty( $data['start_time'] ) ? sanitize_text_field( $data['start_time'] ) : null,
			'end_time'         => ! empty( $data['end_time'] ) ? sanitize_text_field( $data['end_time'] ) : null,
			'status'           => in_array( $data['status'] ?? '', array( 'SCHEDULED', 'COMPLETED', 'MISSED' ), true ) ? $data['status'] : 'SCHEDULED',
			'modified_by'      => get_current_user_id(),
		);
		$formats = array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d' );

		if ( $is_update ) {
			$wpdb->update( $table, $fields, array( 'id' => $id ), $formats, array( '%s' ) );
		} else {
			$fields['id']         = $id;
			$fields['created_by'] = get_current_user_id();
			$wpdb->insert( $table, $fields, array_merge( $formats, array( '%s', '%d' ) ) );
		}

		$row = self::get_appointment( $id );
		if ( $row ) {
			do_action( 'smartrecur_appointment_saved', $id, self::appointment_to_frontend( $row ) );
		}
		return $id;
	}

	/**
	 * Delete an appointment. Fires smartrecur_appointment_deleted first so the
	 * sync engine can remove the Outlook copy.
	 *
	 * @param string $id Appointment UUID.
	 */
	public static function delete_appointment( $id ) {
		global $wpdb;
		do_action( 'smartrecur_appointment_deleted', $id );
		$wpdb->delete( self::table( 'appointments' ), array( 'id' => $id ), array( '%s' ) );
	}

	/* ---------------------------------------------------------------------
	 * Shared serialisers.
	 * ------------------------------------------------------------------- */

	/**
	 * Convert an appointment DB row to the camelCase shape used by REST + JS.
	 *
	 * @param array $row DB row.
	 * @return array
	 */
	public static function appointment_to_frontend( array $row ) {
		$generated = json_decode( $row['generated_dates'] ?? '[]', true );
		return array(
			'id'             => $row['id'],
			'title'          => $row['title'],
			'customerId'     => $row['client_id'],
			'serviceId'      => $row['service_id'],
			'technicianId'   => $row['technician_id'] ?? '',
			'assetId'        => $row['asset_id'] ?? '',
			'syncroTicketId' => $row['syncro_ticket_id'] ?? '',
			'locationType'   => $row['location_type'],
			'description'    => $row['description'] ?? '',
			'recurrenceRule' => $row['recurrence_rule'] ?? '',
			'generatedDates' => is_array( $generated ) ? $generated : array(),
			'startTime'      => $row['start_time'] ?? null,
			'endTime'        => $row['end_time'] ?? null,
			'status'         => $row['status'],
			'createdAt'      => isset( $row['created_at'] ) ? strtotime( $row['created_at'] ) * 1000 : 0,
		);
	}

	/**
	 * Build a quick id → label lookup for a list of rows.
	 *
	 * @param array  $rows       Rows.
	 * @param string $label_key  Column to use as the label.
	 * @param string $fallback   Secondary column if the primary is empty.
	 * @return array
	 */
	public static function index_by_id( array $rows, $label_key, $fallback = '' ) {
		$out = array();
		foreach ( $rows as $row ) {
			$label = $row[ $label_key ] ?? '';
			if ( '' === $label && $fallback ) {
				$label = $row[ $fallback ] ?? '';
			}
			$out[ $row['id'] ] = $label;
		}
		return $out;
	}
}

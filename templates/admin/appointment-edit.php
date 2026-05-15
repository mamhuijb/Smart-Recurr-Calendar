<?php
/**
 * Appointment add/edit form — modern card layout with recurrence builder.
 *
 * Expects: $appointment (array|null), $clients, $services, $technicians (arrays).
 *
 * @package SmartRecur
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** @var array|null $appointment */
/** @var array $clients */
/** @var array $services */
/** @var array $technicians */
$clients     = isset( $clients ) && is_array( $clients ) ? $clients : array();
$services    = isset( $services ) && is_array( $services ) ? $services : array();
$technicians = isset( $technicians ) && is_array( $technicians ) ? $technicians : array();
$is_edit     = is_array( $appointment );
$heading     = $is_edit ? __( 'Edit Appointment', 'smartrecur' ) : __( 'Add Appointment', 'smartrecur' );
$back_url    = admin_url( 'admin.php?page=smartrecur-appointments' );
$theme       = ( ( ( (array) get_option( 'smartrecur_settings', array() ) )['branding']['themeMode'] ?? 'dark' ) === 'light' ) ? ' smartrecur-theme-light' : '';
$f           = static function ( $key, $default = '' ) use ( $appointment ) {
	return isset( $appointment[ $key ] ) ? $appointment[ $key ] : $default;
};

$dates        = json_decode( (string) $f( 'generated_dates', '[]' ), true );
$dates        = is_array( $dates ) ? $dates : array();
$is_recurring = count( $dates ) > 1;
$single_date  = ( 1 === count( $dates ) ) ? $dates[0] : ( isset( $_GET['date'] ) ? sanitize_text_field( wp_unslash( $_GET['date'] ) ) : '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
?>
<div class="wrap smartrecur-admin<?php echo esc_attr( $theme ); ?>">

	<div class="sr-appbar">
		<div class="sr-appbar-brand">
			<div class="sr-appbar-logo"><span class="dashicons dashicons-calendar-alt"></span></div>
			<div>
				<div class="sr-appbar-title"><?php echo esc_html( $heading ); ?></div>
				<div class="sr-appbar-sub"><?php esc_html_e( 'Appointments', 'smartrecur' ); ?></div>
			</div>
		</div>
		<div class="sr-appbar-actions">
			<a class="sr-btn" href="<?php echo esc_url( $back_url ); ?>">
				<span class="dashicons dashicons-arrow-left-alt2"></span> <?php esc_html_e( 'Back', 'smartrecur' ); ?>
			</a>
		</div>
	</div>

	<form method="post" class="sr-form" id="smartrecur-appointment-form">
		<?php wp_nonce_field( 'smartrecur_save_appointment' ); ?>
		<input type="hidden" name="id" value="<?php echo esc_attr( $f( 'id' ) ); ?>">

		<div class="sr-card">
			<h2><span class="dashicons dashicons-edit"></span> <?php esc_html_e( 'Details', 'smartrecur' ); ?></h2>

			<div class="sr-field">
				<label for="sr-title"><?php esc_html_e( 'Title', 'smartrecur' ); ?> <span class="sr-req">*</span></label>
				<input type="text" id="sr-title" name="title" required value="<?php echo esc_attr( $f( 'title' ) ); ?>">
			</div>
			<div class="sr-field">
				<label for="sr-client"><?php esc_html_e( 'Client', 'smartrecur' ); ?></label>
				<select id="sr-client" name="client_id">
					<option value=""><?php esc_html_e( '— none —', 'smartrecur' ); ?></option>
					<?php foreach ( $clients as $c ) : ?>
						<option value="<?php echo esc_attr( $c['id'] ); ?>" <?php selected( $f( 'client_id' ), $c['id'] ); ?>>
							<?php echo esc_html( $c['company'] ?: $c['name'] ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="sr-field">
				<label for="sr-service"><?php esc_html_e( 'Service', 'smartrecur' ); ?></label>
				<select id="sr-service" name="service_id">
					<option value=""><?php esc_html_e( '— none —', 'smartrecur' ); ?></option>
					<?php foreach ( $services as $s ) : ?>
						<option value="<?php echo esc_attr( $s['id'] ); ?>" <?php selected( $f( 'service_id' ), $s['id'] ); ?>>
							<?php echo esc_html( $s['name'] ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="sr-field">
				<label for="sr-technician"><?php esc_html_e( 'Technician', 'smartrecur' ); ?></label>
				<select id="sr-technician" name="technician_id">
					<option value=""><?php esc_html_e( '— unassigned —', 'smartrecur' ); ?></option>
					<?php foreach ( $technicians as $t ) : ?>
						<option value="<?php echo esc_attr( $t['id'] ); ?>" <?php selected( $f( 'technician_id' ), $t['id'] ); ?>>
							<?php echo esc_html( $t['name'] ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="sr-field">
				<label for="sr-location"><?php esc_html_e( 'Location', 'smartrecur' ); ?></label>
				<select id="sr-location" name="location_type">
					<option value="ON_SITE" <?php selected( $f( 'location_type', 'ON_SITE' ), 'ON_SITE' ); ?>><?php esc_html_e( 'On site', 'smartrecur' ); ?></option>
					<option value="REMOTE" <?php selected( $f( 'location_type' ), 'REMOTE' ); ?>><?php esc_html_e( 'Remote', 'smartrecur' ); ?></option>
				</select>
			</div>
			<div class="sr-field">
				<label><?php esc_html_e( 'Time', 'smartrecur' ); ?></label>
				<div class="sr-inline">
					<input type="time" name="start_time" value="<?php echo esc_attr( $f( 'start_time', '09:00' ) ); ?>" style="max-width:140px;">
					<span style="color:var(--sr-text-muted);">&ndash;</span>
					<input type="time" name="end_time" value="<?php echo esc_attr( $f( 'end_time', '10:00' ) ); ?>" style="max-width:140px;">
				</div>
			</div>
			<div class="sr-field">
				<label for="sr-status"><?php esc_html_e( 'Status', 'smartrecur' ); ?></label>
				<select id="sr-status" name="status">
					<option value="SCHEDULED" <?php selected( $f( 'status', 'SCHEDULED' ), 'SCHEDULED' ); ?>><?php esc_html_e( 'Scheduled', 'smartrecur' ); ?></option>
					<option value="COMPLETED" <?php selected( $f( 'status' ), 'COMPLETED' ); ?>><?php esc_html_e( 'Completed', 'smartrecur' ); ?></option>
					<option value="MISSED" <?php selected( $f( 'status' ), 'MISSED' ); ?>><?php esc_html_e( 'Missed', 'smartrecur' ); ?></option>
				</select>
			</div>
			<div class="sr-field">
				<label for="sr-description"><?php esc_html_e( 'Description', 'smartrecur' ); ?></label>
				<textarea id="sr-description" name="description" rows="3"><?php echo esc_textarea( $f( 'description' ) ); ?></textarea>
			</div>
		</div>

		<div class="sr-card">
			<h2><span class="dashicons dashicons-update"></span> <?php esc_html_e( 'Scheduling', 'smartrecur' ); ?></h2>

			<div class="sr-field">
				<label><?php esc_html_e( 'Schedule type', 'smartrecur' ); ?></label>
				<div class="sr-inline">
					<label class="sr-check"><input type="radio" name="is_recurring" value="0" <?php checked( ! $is_recurring ); ?>> <?php esc_html_e( 'One-time', 'smartrecur' ); ?></label>
					<label class="sr-check"><input type="radio" name="is_recurring" value="1" <?php checked( $is_recurring ); ?>> <?php esc_html_e( 'Recurring', 'smartrecur' ); ?></label>
				</div>
			</div>

			<div id="sr-onetime-fields">
				<div class="sr-field">
					<label for="sr-single-date"><?php esc_html_e( 'Date', 'smartrecur' ); ?></label>
					<input type="date" id="sr-single-date" name="single_date" value="<?php echo esc_attr( $single_date ); ?>" style="max-width:200px;">
				</div>
			</div>

			<div id="sr-recurring-fields">
				<div class="sr-field">
					<label for="sr-frequency"><?php esc_html_e( 'Frequency', 'smartrecur' ); ?></label>
					<select id="sr-frequency" name="frequency">
						<?php foreach ( SmartRecur_Recurrence_Engine::frequencies() as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="sr-field">
					<label><?php esc_html_e( 'Pattern', 'smartrecur' ); ?></label>
					<div class="sr-inline">
						<label class="sr-check"><input type="radio" name="pattern_type" value="ABSOLUTE" checked> <?php esc_html_e( 'Fixed day of month', 'smartrecur' ); ?></label>
						<label class="sr-check"><input type="radio" name="pattern_type" value="RELATIVE"> <?php esc_html_e( 'Nth weekday', 'smartrecur' ); ?></label>
					</div>
				</div>
				<div class="sr-field sr-pattern-absolute">
					<label for="sr-day-of-month"><?php esc_html_e( 'Day of month', 'smartrecur' ); ?></label>
					<input type="number" id="sr-day-of-month" name="day_of_month" min="1" max="31" value="1" style="max-width:120px;">
				</div>
				<div class="sr-field sr-pattern-relative" style="display:none;">
					<label><?php esc_html_e( 'Weekday', 'smartrecur' ); ?></label>
					<div class="sr-inline">
						<select name="ordinal" style="max-width:160px;">
							<?php foreach ( SmartRecur_Recurrence_Engine::ordinals() as $key => $label ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
						<select name="weekday" style="max-width:160px;">
							<?php foreach ( SmartRecur_Recurrence_Engine::weekdays() as $key => $label ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $key, 1 ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
				</div>
				<div class="sr-field">
					<label><?php esc_html_e( 'Starting', 'smartrecur' ); ?></label>
					<div class="sr-inline">
						<select name="start_month" style="max-width:170px;">
							<?php foreach ( SmartRecur_Recurrence_Engine::months() as $key => $label ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $key, (int) gmdate( 'n' ) ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
						<input type="number" name="start_year" min="2020" max="2100" value="<?php echo esc_attr( gmdate( 'Y' ) ); ?>" style="max-width:110px;">
					</div>
					<p class="description"><?php esc_html_e( 'Dates are generated for 5 years from the start and recalculated on save.', 'smartrecur' ); ?></p>
				</div>
			</div>

			<?php if ( $is_edit && ! empty( $dates ) ) : ?>
				<div class="sr-field">
					<label><?php esc_html_e( 'Generated dates', 'smartrecur' ); ?></label>
					<p class="description"><?php echo esc_html( implode( ', ', array_slice( $dates, 0, 24 ) ) ); ?><?php echo count( $dates ) > 24 ? ' …' : ''; ?></p>
				</div>
			<?php endif; ?>

			<div class="sr-form-actions">
				<button type="submit" name="smartrecur_save_appointment" value="1" class="sr-btn sr-btn-primary">
					<span class="dashicons dashicons-saved"></span>
					<?php echo $is_edit ? esc_html__( 'Update Appointment', 'smartrecur' ) : esc_html__( 'Create Appointment', 'smartrecur' ); ?>
				</button>
				<a class="sr-btn" href="<?php echo esc_url( $back_url ); ?>"><?php esc_html_e( 'Cancel', 'smartrecur' ); ?></a>
			</div>
		</div>
	</form>
</div>

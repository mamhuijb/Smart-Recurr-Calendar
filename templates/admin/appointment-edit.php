<?php
/**
 * Appointment add/edit form, including the recurrence builder.
 *
 * Expects: $appointment (array|null), $clients, $services, $technicians (arrays),
 * $engine (SmartRecur_Recurrence_Engine class name).
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
$is_edit  = is_array( $appointment );
$heading  = $is_edit ? __( 'Edit Appointment', 'smartrecur' ) : __( 'Add Appointment', 'smartrecur' );
$back_url = admin_url( 'admin.php?page=smartrecur-appointments' );
$f        = static function ( $key, $default = '' ) use ( $appointment ) {
	return isset( $appointment[ $key ] ) ? $appointment[ $key ] : $default;
};

$dates       = json_decode( (string) $f( 'generated_dates', '[]' ), true );
$dates       = is_array( $dates ) ? $dates : array();
$is_recurring = count( $dates ) > 1;
$single_date = ( 1 === count( $dates ) ) ? $dates[0] : ( isset( $_GET['date'] ) ? sanitize_text_field( wp_unslash( $_GET['date'] ) ) : '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
?>
<div class="wrap smartrecur-admin">
	<h1 class="wp-heading-inline"><?php echo esc_html( $heading ); ?></h1>
	<a href="<?php echo esc_url( $back_url ); ?>" class="page-title-action"><?php esc_html_e( 'Back to list', 'smartrecur' ); ?></a>
	<hr class="wp-header-end">

	<form method="post" class="smartrecur-form" id="smartrecur-appointment-form">
		<?php wp_nonce_field( 'smartrecur_save_appointment' ); ?>
		<input type="hidden" name="id" value="<?php echo esc_attr( $f( 'id' ) ); ?>">

		<h2><?php esc_html_e( 'Details', 'smartrecur' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th><label for="sr-title"><?php esc_html_e( 'Title', 'smartrecur' ); ?> <span class="description">*</span></label></th>
				<td><input type="text" id="sr-title" name="title" class="regular-text" required value="<?php echo esc_attr( $f( 'title' ) ); ?>"></td>
			</tr>
			<tr>
				<th><label for="sr-client"><?php esc_html_e( 'Client', 'smartrecur' ); ?></label></th>
				<td>
					<select id="sr-client" name="client_id">
						<option value=""><?php esc_html_e( '— none —', 'smartrecur' ); ?></option>
						<?php foreach ( $clients as $c ) : ?>
							<option value="<?php echo esc_attr( $c['id'] ); ?>" <?php selected( $f( 'client_id' ), $c['id'] ); ?>>
								<?php echo esc_html( $c['company'] ?: $c['name'] ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="sr-service"><?php esc_html_e( 'Service', 'smartrecur' ); ?></label></th>
				<td>
					<select id="sr-service" name="service_id">
						<option value=""><?php esc_html_e( '— none —', 'smartrecur' ); ?></option>
						<?php foreach ( $services as $s ) : ?>
							<option value="<?php echo esc_attr( $s['id'] ); ?>" <?php selected( $f( 'service_id' ), $s['id'] ); ?>>
								<?php echo esc_html( $s['name'] ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="sr-technician"><?php esc_html_e( 'Technician', 'smartrecur' ); ?></label></th>
				<td>
					<select id="sr-technician" name="technician_id">
						<option value=""><?php esc_html_e( '— unassigned —', 'smartrecur' ); ?></option>
						<?php foreach ( $technicians as $t ) : ?>
							<option value="<?php echo esc_attr( $t['id'] ); ?>" <?php selected( $f( 'technician_id' ), $t['id'] ); ?>>
								<?php echo esc_html( $t['name'] ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="sr-location"><?php esc_html_e( 'Location', 'smartrecur' ); ?></label></th>
				<td>
					<select id="sr-location" name="location_type">
						<option value="ON_SITE" <?php selected( $f( 'location_type', 'ON_SITE' ), 'ON_SITE' ); ?>><?php esc_html_e( 'On site', 'smartrecur' ); ?></option>
						<option value="REMOTE" <?php selected( $f( 'location_type' ), 'REMOTE' ); ?>><?php esc_html_e( 'Remote', 'smartrecur' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Time', 'smartrecur' ); ?></th>
				<td>
					<input type="time" name="start_time" value="<?php echo esc_attr( $f( 'start_time', '09:00' ) ); ?>">
					&ndash;
					<input type="time" name="end_time" value="<?php echo esc_attr( $f( 'end_time', '10:00' ) ); ?>">
				</td>
			</tr>
			<tr>
				<th><label for="sr-status"><?php esc_html_e( 'Status', 'smartrecur' ); ?></label></th>
				<td>
					<select id="sr-status" name="status">
						<option value="SCHEDULED" <?php selected( $f( 'status', 'SCHEDULED' ), 'SCHEDULED' ); ?>><?php esc_html_e( 'Scheduled', 'smartrecur' ); ?></option>
						<option value="COMPLETED" <?php selected( $f( 'status' ), 'COMPLETED' ); ?>><?php esc_html_e( 'Completed', 'smartrecur' ); ?></option>
						<option value="MISSED" <?php selected( $f( 'status' ), 'MISSED' ); ?>><?php esc_html_e( 'Missed', 'smartrecur' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="sr-description"><?php esc_html_e( 'Description', 'smartrecur' ); ?></label></th>
				<td><textarea id="sr-description" name="description" class="large-text" rows="3"><?php echo esc_textarea( $f( 'description' ) ); ?></textarea></td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Scheduling', 'smartrecur' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th><?php esc_html_e( 'Schedule type', 'smartrecur' ); ?></th>
				<td>
					<label><input type="radio" name="is_recurring" value="0" <?php checked( ! $is_recurring ); ?>> <?php esc_html_e( 'One-time', 'smartrecur' ); ?></label>
					&nbsp;&nbsp;
					<label><input type="radio" name="is_recurring" value="1" <?php checked( $is_recurring ); ?>> <?php esc_html_e( 'Recurring', 'smartrecur' ); ?></label>
				</td>
			</tr>
		</table>

		<div id="sr-onetime-fields" class="smartrecur-sched-block">
			<table class="form-table" role="presentation">
				<tr>
					<th><label for="sr-single-date"><?php esc_html_e( 'Date', 'smartrecur' ); ?></label></th>
					<td><input type="date" id="sr-single-date" name="single_date" value="<?php echo esc_attr( $single_date ); ?>"></td>
				</tr>
			</table>
		</div>

		<div id="sr-recurring-fields" class="smartrecur-sched-block">
			<table class="form-table" role="presentation">
				<tr>
					<th><label for="sr-frequency"><?php esc_html_e( 'Frequency', 'smartrecur' ); ?></label></th>
					<td>
						<select id="sr-frequency" name="frequency">
							<?php foreach ( SmartRecur_Recurrence_Engine::frequencies() as $key => $label ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Pattern', 'smartrecur' ); ?></th>
					<td>
						<label><input type="radio" name="pattern_type" value="ABSOLUTE" checked> <?php esc_html_e( 'Fixed day of month', 'smartrecur' ); ?></label>
						&nbsp;&nbsp;
						<label><input type="radio" name="pattern_type" value="RELATIVE"> <?php esc_html_e( 'Nth weekday', 'smartrecur' ); ?></label>
					</td>
				</tr>
				<tr class="sr-pattern-absolute">
					<th><label for="sr-day-of-month"><?php esc_html_e( 'Day of month', 'smartrecur' ); ?></label></th>
					<td><input type="number" id="sr-day-of-month" name="day_of_month" min="1" max="31" value="1"></td>
				</tr>
				<tr class="sr-pattern-relative" style="display:none;">
					<th><?php esc_html_e( 'Weekday', 'smartrecur' ); ?></th>
					<td>
						<select name="ordinal">
							<?php foreach ( SmartRecur_Recurrence_Engine::ordinals() as $key => $label ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
						<select name="weekday">
							<?php foreach ( SmartRecur_Recurrence_Engine::weekdays() as $key => $label ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $key, 1 ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Starting', 'smartrecur' ); ?></th>
					<td>
						<select name="start_month">
							<?php foreach ( SmartRecur_Recurrence_Engine::months() as $key => $label ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $key, (int) gmdate( 'n' ) ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
						<input type="number" name="start_year" min="2020" max="2100" value="<?php echo esc_attr( gmdate( 'Y' ) ); ?>" style="width:90px;">
					</td>
				</tr>
			</table>
			<p class="description">
				<?php esc_html_e( 'Dates are generated for 5 years from the start. They are recalculated when you save.', 'smartrecur' ); ?>
			</p>
		</div>

		<?php if ( $is_edit && ! empty( $dates ) ) : ?>
			<h2><?php esc_html_e( 'Generated dates', 'smartrecur' ); ?></h2>
			<p class="description"><?php echo esc_html( implode( ', ', array_slice( $dates, 0, 24 ) ) ); ?><?php echo count( $dates ) > 24 ? ' …' : ''; ?></p>
		<?php endif; ?>

		<?php submit_button( $is_edit ? __( 'Update Appointment', 'smartrecur' ) : __( 'Create Appointment', 'smartrecur' ), 'primary', 'smartrecur_save_appointment' ); ?>
	</form>
</div>

<?php
/**
 * Appointment add/edit form — two-column layout with a live Schedule Builder.
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
$heading     = $is_edit ? __( 'Edit Appointment', 'smartrecur' ) : __( 'New Appointment', 'smartrecur' );
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
			<a class="sr-btn sr-btn-icon" href="<?php echo esc_url( $back_url ); ?>" aria-label="<?php esc_attr_e( 'Back', 'smartrecur' ); ?>">
				<span class="dashicons dashicons-arrow-left-alt2"></span>
			</a>
			<div class="sr-appbar-logo"><span class="dashicons dashicons-portfolio"></span></div>
			<div>
				<div class="sr-appbar-title"><?php echo esc_html( $heading ); ?></div>
				<div class="sr-appbar-sub"><?php esc_html_e( 'Appointments', 'smartrecur' ); ?></div>
			</div>
		</div>
	</div>

	<form method="post" class="sr-form" id="smartrecur-appointment-form" style="max-width:none;">
		<?php wp_nonce_field( 'smartrecur_save_appointment' ); ?>
		<input type="hidden" name="id" value="<?php echo esc_attr( $f( 'id' ) ); ?>">

		<div class="sr-dash">

			<!-- LEFT: details -->
			<div>
				<div class="sr-card">
					<h2><span class="dashicons dashicons-admin-users"></span> <?php esc_html_e( 'Client', 'smartrecur' ); ?></h2>
					<div class="sr-field">
						<select id="sr-client" name="client_id">
							<option value=""><?php esc_html_e( 'Select client…', 'smartrecur' ); ?></option>
							<?php foreach ( $clients as $c ) : ?>
								<option value="<?php echo esc_attr( $c['id'] ); ?>" <?php selected( $f( 'client_id' ), $c['id'] ); ?>>
									<?php echo esc_html( $c['company'] ?: $c['name'] ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
				</div>

				<div class="sr-card">
					<h2><span class="dashicons dashicons-portfolio"></span> <?php esc_html_e( 'Service & technician', 'smartrecur' ); ?></h2>
					<div class="sr-field">
						<label for="sr-title"><?php esc_html_e( 'Title', 'smartrecur' ); ?> <span class="sr-req">*</span></label>
						<input type="text" id="sr-title" name="title" required value="<?php echo esc_attr( $f( 'title' ) ); ?>">
					</div>
					<div class="sr-field">
						<label for="sr-service"><?php esc_html_e( 'Service', 'smartrecur' ); ?></label>
						<select id="sr-service" name="service_id">
							<option value=""><?php esc_html_e( 'Select service…', 'smartrecur' ); ?></option>
							<?php foreach ( $services as $s ) : ?>
								<option value="<?php echo esc_attr( $s['id'] ); ?>" <?php selected( $f( 'service_id' ), $s['id'] ); ?>>
									<?php echo esc_html( $s['name'] ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="sr-field">
						<label><?php esc_html_e( 'Location', 'smartrecur' ); ?></label>
						<div class="sr-inline">
							<label class="sr-check"><input type="radio" name="location_type" value="ON_SITE" <?php checked( $f( 'location_type', 'ON_SITE' ), 'ON_SITE' ); ?>> <?php esc_html_e( 'On site', 'smartrecur' ); ?></label>
							<label class="sr-check"><input type="radio" name="location_type" value="REMOTE" <?php checked( $f( 'location_type' ), 'REMOTE' ); ?>> <?php esc_html_e( 'Remote', 'smartrecur' ); ?></label>
						</div>
					</div>
					<div class="sr-field">
						<label for="sr-technician"><?php esc_html_e( 'Technician', 'smartrecur' ); ?></label>
						<select id="sr-technician" name="technician_id">
							<option value=""><?php esc_html_e( 'Unassigned', 'smartrecur' ); ?></option>
							<?php foreach ( $technicians as $t ) : ?>
								<option value="<?php echo esc_attr( $t['id'] ); ?>" <?php selected( $f( 'technician_id' ), $t['id'] ); ?>>
									<?php echo esc_html( $t['name'] ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
				</div>

				<div class="sr-card">
					<h2><span class="dashicons dashicons-clock"></span> <?php esc_html_e( 'Time', 'smartrecur' ); ?></h2>
					<div class="sr-inline">
						<div class="sr-field" style="margin:0;">
							<label><?php esc_html_e( 'Start', 'smartrecur' ); ?></label>
							<input type="time" name="start_time" value="<?php echo esc_attr( $f( 'start_time', '09:00' ) ); ?>" style="max-width:150px;">
						</div>
						<div class="sr-field" style="margin:0;">
							<label><?php esc_html_e( 'End', 'smartrecur' ); ?></label>
							<input type="time" name="end_time" value="<?php echo esc_attr( $f( 'end_time', '10:00' ) ); ?>" style="max-width:150px;">
						</div>
						<div class="sr-field" style="margin:0;">
							<label for="sr-status"><?php esc_html_e( 'Status', 'smartrecur' ); ?></label>
							<select id="sr-status" name="status">
								<option value="SCHEDULED" <?php selected( $f( 'status', 'SCHEDULED' ), 'SCHEDULED' ); ?>><?php esc_html_e( 'Scheduled', 'smartrecur' ); ?></option>
								<option value="COMPLETED" <?php selected( $f( 'status' ), 'COMPLETED' ); ?>><?php esc_html_e( 'Completed', 'smartrecur' ); ?></option>
								<option value="MISSED" <?php selected( $f( 'status' ), 'MISSED' ); ?>><?php esc_html_e( 'Missed', 'smartrecur' ); ?></option>
							</select>
						</div>
					</div>
					<div class="sr-field" style="margin-top:16px;">
						<label for="sr-description"><?php esc_html_e( 'Description', 'smartrecur' ); ?></label>
						<textarea id="sr-description" name="description" rows="2"><?php echo esc_textarea( $f( 'description' ) ); ?></textarea>
					</div>
				</div>
			</div>

			<!-- RIGHT: schedule builder -->
			<div>
				<div class="sr-card">
					<h2><span class="dashicons dashicons-calendar-alt"></span> <?php esc_html_e( 'Schedule Builder', 'smartrecur' ); ?></h2>

					<div class="sr-segment" style="display:flex;width:100%;margin-bottom:16px;">
						<label class="sr-seg-opt" style="flex:1;text-align:center;">
							<input type="radio" name="is_recurring" value="0" <?php checked( ! $is_recurring ); ?> style="display:none;">
							<span><?php esc_html_e( 'One time', 'smartrecur' ); ?></span>
						</label>
						<label class="sr-seg-opt" style="flex:1;text-align:center;">
							<input type="radio" name="is_recurring" value="1" <?php checked( $is_recurring ); ?> style="display:none;">
							<span><?php esc_html_e( 'Recurring', 'smartrecur' ); ?></span>
						</label>
					</div>

					<div id="sr-onetime-fields">
						<div class="sr-field">
							<label for="sr-single-date"><?php esc_html_e( 'Date', 'smartrecur' ); ?></label>
							<input type="date" id="sr-single-date" name="single_date" value="<?php echo esc_attr( $single_date ); ?>">
						</div>
					</div>

					<div id="sr-recurring-fields">
						<div class="sr-inline" style="gap:14px;">
							<div class="sr-field" style="flex:1;margin:0;">
								<label for="sr-frequency"><?php esc_html_e( 'Frequency', 'smartrecur' ); ?></label>
								<select id="sr-frequency" name="frequency">
									<?php foreach ( SmartRecur_Recurrence_Engine::frequencies() as $key => $label ) : ?>
										<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
									<?php endforeach; ?>
								</select>
							</div>
							<div class="sr-field" style="flex:1;margin:0;">
								<label for="sr-start-month"><?php esc_html_e( 'Start month', 'smartrecur' ); ?></label>
								<select id="sr-start-month" name="start_month">
									<?php foreach ( SmartRecur_Recurrence_Engine::months() as $key => $label ) : ?>
										<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $key, (int) gmdate( 'n' ) ); ?>><?php echo esc_html( $label ); ?></option>
									<?php endforeach; ?>
								</select>
							</div>
						</div>

						<div class="sr-field" style="margin-top:14px;">
							<div class="sr-segment" style="display:flex;width:100%;">
								<label class="sr-seg-opt" style="flex:1;text-align:center;">
									<input type="radio" name="pattern_type" value="RELATIVE" checked style="display:none;">
									<span><?php esc_html_e( 'Weekday pattern', 'smartrecur' ); ?></span>
								</label>
								<label class="sr-seg-opt" style="flex:1;text-align:center;">
									<input type="radio" name="pattern_type" value="ABSOLUTE" style="display:none;">
									<span><?php esc_html_e( 'Fixed date', 'smartrecur' ); ?></span>
								</label>
							</div>
						</div>

						<div class="sr-field sr-pattern-relative" style="margin-top:14px;">
							<label><?php esc_html_e( 'On the', 'smartrecur' ); ?></label>
							<div class="sr-inline">
								<select name="ordinal" style="max-width:130px;">
									<?php foreach ( SmartRecur_Recurrence_Engine::ordinals() as $key => $label ) : ?>
										<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $key, 2 ); ?>><?php echo esc_html( $label ); ?></option>
									<?php endforeach; ?>
								</select>
								<select name="weekday" style="flex:1;">
									<?php foreach ( SmartRecur_Recurrence_Engine::weekdays() as $key => $label ) : ?>
										<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $key, 2 ); ?>><?php echo esc_html( $label ); ?></option>
									<?php endforeach; ?>
								</select>
							</div>
						</div>

						<div class="sr-field sr-pattern-absolute" style="margin-top:14px;display:none;">
							<label for="sr-day-of-month"><?php esc_html_e( 'Day of month', 'smartrecur' ); ?></label>
							<input type="number" id="sr-day-of-month" name="day_of_month" min="1" max="31" value="1" style="max-width:120px;">
						</div>

						<input type="hidden" name="start_year" id="sr-start-year" value="<?php echo esc_attr( gmdate( 'Y' ) ); ?>">

						<div class="sr-preview" style="margin-top:18px;">
							<div class="sr-card-head" style="margin-bottom:10px;">
								<span class="sr-card-title" style="font-size:12px;text-transform:uppercase;letter-spacing:.06em;color:var(--sr-text-muted);">
									<?php esc_html_e( 'Preview (5 years)', 'smartrecur' ); ?>
								</span>
								<span class="sr-badge sr-badge-scheduled" id="sr-preview-count">0</span>
							</div>
							<div id="sr-preview-list" class="sr-upcoming-list"></div>
							<p class="description" id="sr-preview-rule"></p>
						</div>
					</div>

					<div class="sr-form-actions">
						<button type="submit" name="smartrecur_save_appointment" value="1" class="sr-btn sr-btn-primary" style="flex:1;justify-content:center;">
							<span class="dashicons dashicons-yes-alt"></span>
							<?php echo $is_edit ? esc_html__( 'Update appointment', 'smartrecur' ) : esc_html__( 'Confirm schedule', 'smartrecur' ); ?>
						</button>
					</div>
				</div>
			</div>

		</div>
	</form>
</div>

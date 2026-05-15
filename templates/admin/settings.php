<?php
/**
 * SmartRecur settings admin page.
 *
 * @package SmartRecur
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** @var array $settings */
$settings        = isset( $settings ) ? $settings : array();
$business_hours  = $settings['businessHours'] ?? array();
$reminders       = $settings['reminders']['days'] ?? array( 14, 7, 1 );
$durations       = $settings['durations'] ?? array( 15, 30, 45, 60, 90, 120 );
$booking_rules   = $settings['bookingRules'] ?? array();
$notifications   = $settings['notifications'] ?? array();
$template_subject = $settings['templates']['reminder']['subject'] ?? '';
$template_body    = $settings['templates']['reminder']['body'] ?? '';
$delete_on_unin   = ! empty( $settings['deleteDataOnUninstall'] );
$days_of_week     = array(
	0 => __( 'Sunday', 'smartrecur' ),
	1 => __( 'Monday', 'smartrecur' ),
	2 => __( 'Tuesday', 'smartrecur' ),
	3 => __( 'Wednesday', 'smartrecur' ),
	4 => __( 'Thursday', 'smartrecur' ),
	5 => __( 'Friday', 'smartrecur' ),
	6 => __( 'Saturday', 'smartrecur' ),
);
$closed_days      = array_map( 'intval', $business_hours['closedDays'] ?? array() );
$timezones        = timezone_identifiers_list();
?>
<div class="wrap">
	<h1><?php esc_html_e( 'SmartRecur Settings', 'smartrecur' ); ?></h1>

	<form method="post">
		<?php wp_nonce_field( 'smartrecur_settings' ); ?>

		<h2><?php esc_html_e( 'Business Hours', 'smartrecur' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th><?php esc_html_e( 'Start time', 'smartrecur' ); ?></th>
				<td><input type="time" name="business_start" value="<?php echo esc_attr( $business_hours['start'] ?? '09:00' ); ?>"></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'End time', 'smartrecur' ); ?></th>
				<td><input type="time" name="business_end" value="<?php echo esc_attr( $business_hours['end'] ?? '17:00' ); ?>"></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Timezone', 'smartrecur' ); ?></th>
				<td>
					<select name="business_timezone">
						<?php
						$current_tz = $business_hours['timezone'] ?? 'Europe/Amsterdam';
						foreach ( $timezones as $tz ) {
							printf( '<option value="%1$s" %2$s>%1$s</option>', esc_attr( $tz ), selected( $current_tz, $tz, false ) );
						}
						?>
					</select>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Closed days', 'smartrecur' ); ?></th>
				<td>
					<?php foreach ( $days_of_week as $day_num => $day_name ) : ?>
						<label style="margin-right:12px">
							<input type="checkbox" name="closed_days[]" value="<?php echo esc_attr( $day_num ); ?>" <?php checked( in_array( $day_num, $closed_days, true ) ); ?>>
							<?php echo esc_html( $day_name ); ?>
						</label>
					<?php endforeach; ?>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Appointment Durations', 'smartrecur' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th><?php esc_html_e( 'Allowed durations (minutes)', 'smartrecur' ); ?></th>
				<td>
					<?php foreach ( array( 15, 30, 45, 60, 90, 120 ) as $opt ) : ?>
						<label style="margin-right:12px">
							<input type="checkbox" name="durations[]" value="<?php echo esc_attr( $opt ); ?>" <?php checked( in_array( $opt, $durations, true ) ); ?>>
							<?php echo esc_html( $opt ); ?> min
						</label>
					<?php endforeach; ?>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Booking Rules', 'smartrecur' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th><?php esc_html_e( 'Buffer between appointments (min)', 'smartrecur' ); ?></th>
				<td><input type="number" min="0" max="240" name="buffer_minutes" value="<?php echo esc_attr( (int) ( $booking_rules['bufferMinutes'] ?? 0 ) ); ?>"></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Maximum booking horizon (days)', 'smartrecur' ); ?></th>
				<td><input type="number" min="1" max="1825" name="max_future_days" value="<?php echo esc_attr( (int) ( $booking_rules['maxFutureDays'] ?? 365 ) ); ?>"></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Max appointments per day (0 = unlimited)', 'smartrecur' ); ?></th>
				<td><input type="number" min="0" max="500" name="max_per_day" value="<?php echo esc_attr( (int) ( $booking_rules['maxPerDay'] ?? 0 ) ); ?>"></td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Notifications', 'smartrecur' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th><?php esc_html_e( 'Email notifications', 'smartrecur' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="email_enabled" value="1" <?php checked( ! empty( $notifications['emailEnabled'] ) ); ?>>
						<?php esc_html_e( 'Send appointment confirmations and reminders via wp_mail()', 'smartrecur' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Reminder days', 'smartrecur' ); ?></th>
				<td>
					<input type="text" name="reminder_days" value="<?php echo esc_attr( implode( ',', $reminders ) ); ?>" class="regular-text">
					<p class="description"><?php esc_html_e( 'Comma-separated days before appointment (e.g. 14, 7, 1).', 'smartrecur' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Reminder subject', 'smartrecur' ); ?></th>
				<td><input type="text" name="template_subject" class="large-text" value="<?php echo esc_attr( $template_subject ); ?>"></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Reminder body', 'smartrecur' ); ?></th>
				<td>
					<textarea name="template_body" rows="6" class="large-text"><?php echo esc_textarea( $template_body ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Tokens: {customer_name}, {service_name}, {tech_name}, {date}, {location_type}, {company_name}, {link}', 'smartrecur' ); ?></p>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Data Management', 'smartrecur' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th><?php esc_html_e( 'On uninstall', 'smartrecur' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="delete_data_on_uninstall" value="1" <?php checked( $delete_on_unin ); ?>>
						<?php esc_html_e( 'Delete all SmartRecur tables and options when the plugin is uninstalled.', 'smartrecur' ); ?>
					</label>
				</td>
			</tr>
		</table>

		<?php submit_button( __( 'Save Settings', 'smartrecur' ), 'primary', 'smartrecur_settings_save' ); ?>
	</form>
</div>

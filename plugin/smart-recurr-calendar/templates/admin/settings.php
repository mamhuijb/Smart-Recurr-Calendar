<?php
/**
 * SmartRecur settings screen.
 *
 * Expects: $settings (array).
 *
 * @package SmartRecur
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** @var array $settings */
$settings    = isset( $settings ) ? $settings : array();
$branding    = $settings['branding'] ?? array();
$hours       = $settings['businessHours'] ?? array();
$reminders   = $settings['reminders']['days'] ?? array( 14, 7, 1 );
$durations   = $settings['durations'] ?? array( 15, 30, 45, 60, 90, 120 );
$rules       = $settings['bookingRules'] ?? array();
$notif       = $settings['notifications'] ?? array();
$tpl_subject = $settings['templates']['reminder']['subject'] ?? 'Appointment: {service_name} - {date}';
$tpl_body    = $settings['templates']['reminder']['body'] ?? '';
$widget      = $settings['dashboardWidget'] ?? array();
$closed_days = array_map( 'intval', $hours['closedDays'] ?? array() );
$days_of_week = array(
	0 => __( 'Sunday', 'smartrecur' ),    1 => __( 'Monday', 'smartrecur' ),
	2 => __( 'Tuesday', 'smartrecur' ),   3 => __( 'Wednesday', 'smartrecur' ),
	4 => __( 'Thursday', 'smartrecur' ),  5 => __( 'Friday', 'smartrecur' ),
	6 => __( 'Saturday', 'smartrecur' ),
);
?>
<?php
$sr_theme = ( ( $branding['themeMode'] ?? 'dark' ) === 'light' ) ? ' smartrecur-theme-light' : '';
?>
<div class="wrap smartrecur-admin<?php echo esc_attr( $sr_theme ); ?>">

	<div class="sr-appbar">
		<div class="sr-appbar-brand">
			<div class="sr-appbar-logo"><span class="dashicons dashicons-admin-generic"></span></div>
			<div>
				<div class="sr-appbar-title"><?php esc_html_e( 'Settings', 'smartrecur' ); ?></div>
				<div class="sr-appbar-sub">SmartRecur</div>
			</div>
		</div>
	</div>

	<form method="post">
		<?php wp_nonce_field( 'smartrecur_settings' ); ?>
		<div class="sr-card">

		<h2><?php esc_html_e( 'Branding', 'smartrecur' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th><label for="sr-logo"><?php esc_html_e( 'Logo URL', 'smartrecur' ); ?></label></th>
				<td><input type="url" id="sr-logo" name="logo_url" class="large-text" value="<?php echo esc_attr( $branding['logoUrl'] ?? '' ); ?>"></td>
			</tr>
			<tr>
				<th><label for="sr-primary"><?php esc_html_e( 'Primary color', 'smartrecur' ); ?></label></th>
				<td><input type="color" id="sr-primary" name="primary_color" value="<?php echo esc_attr( $branding['primaryColorHex'] ?? '#4f46e5' ); ?>"></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Theme', 'smartrecur' ); ?></th>
				<td>
					<select name="theme_mode">
						<option value="dark" <?php selected( $branding['themeMode'] ?? 'dark', 'dark' ); ?>><?php esc_html_e( 'Dark', 'smartrecur' ); ?></option>
						<option value="light" <?php selected( $branding['themeMode'] ?? 'dark', 'light' ); ?>><?php esc_html_e( 'Light', 'smartrecur' ); ?></option>
					</select>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Business Hours', 'smartrecur' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th><?php esc_html_e( 'Open', 'smartrecur' ); ?></th>
				<td>
					<input type="time" name="business_start" value="<?php echo esc_attr( $hours['start'] ?? '09:00' ); ?>">
					&ndash;
					<input type="time" name="business_end" value="<?php echo esc_attr( $hours['end'] ?? '17:00' ); ?>">
				</td>
			</tr>
			<tr>
				<th><label for="sr-tz"><?php esc_html_e( 'Timezone', 'smartrecur' ); ?></label></th>
				<td>
					<select id="sr-tz" name="business_timezone">
						<?php
						$current_tz = $hours['timezone'] ?? 'Europe/Amsterdam';
						foreach ( timezone_identifiers_list() as $tz ) {
							printf( '<option value="%1$s" %2$s>%1$s</option>', esc_attr( $tz ), selected( $current_tz, $tz, false ) );
						}
						?>
					</select>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Closed days', 'smartrecur' ); ?></th>
				<td>
					<?php foreach ( $days_of_week as $num => $name ) : ?>
						<label style="margin-right:12px;">
							<input type="checkbox" name="closed_days[]" value="<?php echo esc_attr( $num ); ?>" <?php checked( in_array( $num, $closed_days, true ) ); ?>>
							<?php echo esc_html( $name ); ?>
						</label>
					<?php endforeach; ?>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Appointment Durations', 'smartrecur' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th><?php esc_html_e( 'Allowed durations', 'smartrecur' ); ?></th>
				<td>
					<?php foreach ( array( 15, 30, 45, 60, 90, 120 ) as $opt ) : ?>
						<label style="margin-right:12px;">
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
				<th><label for="sr-buffer"><?php esc_html_e( 'Buffer between appointments (min)', 'smartrecur' ); ?></label></th>
				<td><input type="number" id="sr-buffer" name="buffer_minutes" min="0" max="240" value="<?php echo esc_attr( (int) ( $rules['bufferMinutes'] ?? 0 ) ); ?>"></td>
			</tr>
			<tr>
				<th><label for="sr-horizon"><?php esc_html_e( 'Booking horizon (days)', 'smartrecur' ); ?></label></th>
				<td><input type="number" id="sr-horizon" name="max_future_days" min="1" max="1825" value="<?php echo esc_attr( (int) ( $rules['maxFutureDays'] ?? 365 ) ); ?>"></td>
			</tr>
			<tr>
				<th><label for="sr-perday"><?php esc_html_e( 'Max appointments/day (0 = unlimited)', 'smartrecur' ); ?></label></th>
				<td><input type="number" id="sr-perday" name="max_per_day" min="0" max="500" value="<?php echo esc_attr( (int) ( $rules['maxPerDay'] ?? 0 ) ); ?>"></td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Holidays & Closures', 'smartrecur' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th><label for="sr-holidays"><?php esc_html_e( 'Holidays', 'smartrecur' ); ?></label></th>
				<td>
					<textarea id="sr-holidays" name="holidays" class="large-text" rows="4"><?php echo esc_textarea( implode( "\n", (array) ( $settings['holidays'] ?? array() ) ) ); ?></textarea>
					<p class="description"><?php esc_html_e( 'One date per line (YYYY-MM-DD).', 'smartrecur' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="sr-closures"><?php esc_html_e( 'Manual closures', 'smartrecur' ); ?></label></th>
				<td>
					<textarea id="sr-closures" name="manual_closures" class="large-text" rows="3"><?php echo esc_textarea( implode( "\n", (array) ( $settings['manualClosures'] ?? array() ) ) ); ?></textarea>
					<p class="description"><?php esc_html_e( 'One date per line (YYYY-MM-DD).', 'smartrecur' ); ?></p>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Notifications', 'smartrecur' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th><?php esc_html_e( 'Email notifications', 'smartrecur' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="email_enabled" value="1" <?php checked( ! empty( $notif['emailEnabled'] ) ); ?>>
						<?php esc_html_e( 'Send appointment confirmations and reminders via wp_mail().', 'smartrecur' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th><label for="sr-reminders"><?php esc_html_e( 'Reminder days', 'smartrecur' ); ?></label></th>
				<td>
					<input type="text" id="sr-reminders" name="reminder_days" class="regular-text" value="<?php echo esc_attr( implode( ', ', $reminders ) ); ?>">
					<p class="description"><?php esc_html_e( 'Comma-separated days before the appointment (e.g. 14, 7, 1).', 'smartrecur' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="sr-tpl-subject"><?php esc_html_e( 'Reminder subject', 'smartrecur' ); ?></label></th>
				<td><input type="text" id="sr-tpl-subject" name="template_subject" class="large-text" value="<?php echo esc_attr( $tpl_subject ); ?>"></td>
			</tr>
			<tr>
				<th><label for="sr-tpl-body"><?php esc_html_e( 'Reminder body', 'smartrecur' ); ?></label></th>
				<td>
					<textarea id="sr-tpl-body" name="template_body" class="large-text" rows="6"><?php echo esc_textarea( $tpl_body ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Tokens: {customer_name}, {service_name}, {tech_name}, {date}, {location_type}, {company_name}, {link}', 'smartrecur' ); ?></p>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Dashboard Widget', 'smartrecur' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th><?php esc_html_e( 'Show widget', 'smartrecur' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="dashboard_widget_enabled" value="1" <?php checked( ! array_key_exists( 'enabled', $widget ) || ! empty( $widget['enabled'] ) ); ?>>
						<?php esc_html_e( 'Show upcoming appointments on the WordPress dashboard.', 'smartrecur' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th><label for="sr-w-limit"><?php esc_html_e( 'Items to show', 'smartrecur' ); ?></label></th>
				<td><input type="number" id="sr-w-limit" name="dashboard_widget_limit" min="1" max="50" value="<?php echo esc_attr( (int) ( $widget['limit'] ?? 10 ) ); ?>"></td>
			</tr>
			<tr>
				<th><label for="sr-w-days"><?php esc_html_e( 'Days ahead', 'smartrecur' ); ?></label></th>
				<td><input type="number" id="sr-w-days" name="dashboard_widget_days" min="1" max="365" value="<?php echo esc_attr( (int) ( $widget['daysAhead'] ?? 30 ) ); ?>"></td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Data Management', 'smartrecur' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th><?php esc_html_e( 'On uninstall', 'smartrecur' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="delete_data_on_uninstall" value="1" <?php checked( ! empty( $settings['deleteDataOnUninstall'] ) ); ?>>
						<?php esc_html_e( 'Delete all SmartRecur tables and options when the plugin is uninstalled.', 'smartrecur' ); ?>
					</label>
				</td>
			</tr>
		</table>

		<?php submit_button( __( 'Save Settings', 'smartrecur' ), 'primary', 'smartrecur_settings_save' ); ?>
		</div>
	</form>
</div>

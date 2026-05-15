<?php
/**
 * Service add/edit form — modern card layout.
 *
 * Expects: $service (array|null).
 *
 * @package SmartRecur
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** @var array|null $service */
$is_edit  = is_array( $service );
$heading  = $is_edit ? __( 'Edit Service', 'smartrecur' ) : __( 'Add Service', 'smartrecur' );
$back_url = admin_url( 'admin.php?page=smartrecur-services' );
$theme    = ( ( ( (array) get_option( 'smartrecur_settings', array() ) )['branding']['themeMode'] ?? 'dark' ) === 'light' ) ? ' smartrecur-theme-light' : '';
$f        = static function ( $key, $default = '' ) use ( $service ) {
	return isset( $service[ $key ] ) ? $service[ $key ] : $default;
};
$reminders = json_decode( (string) $f( 'reminder_days', '[]' ), true );
$reminders = is_array( $reminders ) ? implode( ', ', $reminders ) : '';
?>
<div class="wrap smartrecur-admin<?php echo esc_attr( $theme ); ?>">

	<div class="sr-appbar">
		<div class="sr-appbar-brand">
			<div class="sr-appbar-logo"><span class="dashicons dashicons-portfolio"></span></div>
			<div>
				<div class="sr-appbar-title"><?php echo esc_html( $heading ); ?></div>
				<div class="sr-appbar-sub"><?php esc_html_e( 'Services', 'smartrecur' ); ?></div>
			</div>
		</div>
		<div class="sr-appbar-actions">
			<a class="sr-btn" href="<?php echo esc_url( $back_url ); ?>">
				<span class="dashicons dashicons-arrow-left-alt2"></span> <?php esc_html_e( 'Back', 'smartrecur' ); ?>
			</a>
		</div>
	</div>

	<form method="post" class="sr-form">
		<?php wp_nonce_field( 'smartrecur_save_service' ); ?>
		<input type="hidden" name="id" value="<?php echo esc_attr( $f( 'id' ) ); ?>">

		<div class="sr-card">
			<h2><span class="dashicons dashicons-portfolio"></span> <?php esc_html_e( 'Service details', 'smartrecur' ); ?></h2>

			<div class="sr-field">
				<label for="sr-name"><?php esc_html_e( 'Name', 'smartrecur' ); ?> <span class="sr-req">*</span></label>
				<input type="text" id="sr-name" name="name" required value="<?php echo esc_attr( $f( 'name' ) ); ?>">
			</div>
			<div class="sr-field">
				<label for="sr-type"><?php esc_html_e( 'Type', 'smartrecur' ); ?></label>
				<select id="sr-type" name="type">
					<option value="RECURRING" <?php selected( $f( 'type', 'RECURRING' ), 'RECURRING' ); ?>><?php esc_html_e( 'Recurring', 'smartrecur' ); ?></option>
					<option value="ONE_TIME" <?php selected( $f( 'type' ), 'ONE_TIME' ); ?>><?php esc_html_e( 'One-time', 'smartrecur' ); ?></option>
				</select>
			</div>
			<div class="sr-field">
				<label for="sr-duration"><?php esc_html_e( 'Default duration (minutes)', 'smartrecur' ); ?></label>
				<input type="number" id="sr-duration" name="default_duration_min" min="5" max="600" value="<?php echo esc_attr( $f( 'default_duration_min', 60 ) ); ?>">
			</div>
			<div class="sr-field">
				<label for="sr-location"><?php esc_html_e( 'Default location', 'smartrecur' ); ?></label>
				<select id="sr-location" name="default_location">
					<option value="ON_SITE" <?php selected( $f( 'default_location', 'ON_SITE' ), 'ON_SITE' ); ?>><?php esc_html_e( 'On site', 'smartrecur' ); ?></option>
					<option value="REMOTE" <?php selected( $f( 'default_location' ), 'REMOTE' ); ?>><?php esc_html_e( 'Remote', 'smartrecur' ); ?></option>
				</select>
			</div>
			<div class="sr-field">
				<label for="sr-color"><?php esc_html_e( 'Calendar color', 'smartrecur' ); ?></label>
				<input type="color" id="sr-color" name="color" value="<?php echo esc_attr( $f( 'color', '#4F46E5' ) ); ?>">
			</div>
			<div class="sr-field">
				<label class="sr-check">
					<input type="checkbox" name="create_ticket" value="1" <?php checked( (int) $f( 'create_ticket' ), 1 ); ?>>
					<?php esc_html_e( 'Create a Syncro ticket when an appointment with this service is booked.', 'smartrecur' ); ?>
				</label>
			</div>
			<div class="sr-field">
				<label for="sr-reminders"><?php esc_html_e( 'Reminder days', 'smartrecur' ); ?></label>
				<input type="text" id="sr-reminders" name="reminder_days" value="<?php echo esc_attr( $reminders ); ?>">
				<p class="description"><?php esc_html_e( 'Comma-separated days before the appointment (e.g. 14, 7, 1). Blank uses the global default.', 'smartrecur' ); ?></p>
			</div>
			<div class="sr-field">
				<label for="sr-tpl-subject"><?php esc_html_e( 'Email subject override', 'smartrecur' ); ?></label>
				<input type="text" id="sr-tpl-subject" name="email_template_subject" value="<?php echo esc_attr( $f( 'email_template_subject' ) ); ?>">
			</div>
			<div class="sr-field">
				<label for="sr-tpl-body"><?php esc_html_e( 'Email body override', 'smartrecur' ); ?></label>
				<textarea id="sr-tpl-body" name="email_template_body" rows="4"><?php echo esc_textarea( $f( 'email_template_body' ) ); ?></textarea>
			</div>

			<div class="sr-form-actions">
				<button type="submit" name="smartrecur_save_service" value="1" class="sr-btn sr-btn-primary">
					<span class="dashicons dashicons-saved"></span>
					<?php echo $is_edit ? esc_html__( 'Update Service', 'smartrecur' ) : esc_html__( 'Create Service', 'smartrecur' ); ?>
				</button>
				<a class="sr-btn" href="<?php echo esc_url( $back_url ); ?>"><?php esc_html_e( 'Cancel', 'smartrecur' ); ?></a>
			</div>
		</div>
	</form>
</div>

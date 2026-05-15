<?php
/**
 * Service add/edit form.
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
$f        = static function ( $key, $default = '' ) use ( $service ) {
	return isset( $service[ $key ] ) ? $service[ $key ] : $default;
};
$reminders = json_decode( (string) $f( 'reminder_days', '[]' ), true );
$reminders = is_array( $reminders ) ? implode( ', ', $reminders ) : '';
?>
<div class="wrap smartrecur-admin">
	<h1 class="wp-heading-inline"><?php echo esc_html( $heading ); ?></h1>
	<a href="<?php echo esc_url( $back_url ); ?>" class="page-title-action"><?php esc_html_e( 'Back to list', 'smartrecur' ); ?></a>
	<hr class="wp-header-end">

	<form method="post" class="smartrecur-form">
		<?php wp_nonce_field( 'smartrecur_save_service' ); ?>
		<input type="hidden" name="id" value="<?php echo esc_attr( $f( 'id' ) ); ?>">

		<table class="form-table" role="presentation">
			<tr>
				<th><label for="sr-name"><?php esc_html_e( 'Name', 'smartrecur' ); ?> <span class="description">*</span></label></th>
				<td><input type="text" id="sr-name" name="name" class="regular-text" required value="<?php echo esc_attr( $f( 'name' ) ); ?>"></td>
			</tr>
			<tr>
				<th><label for="sr-type"><?php esc_html_e( 'Type', 'smartrecur' ); ?></label></th>
				<td>
					<select id="sr-type" name="type">
						<option value="RECURRING" <?php selected( $f( 'type', 'RECURRING' ), 'RECURRING' ); ?>><?php esc_html_e( 'Recurring', 'smartrecur' ); ?></option>
						<option value="ONE_TIME" <?php selected( $f( 'type' ), 'ONE_TIME' ); ?>><?php esc_html_e( 'One-time', 'smartrecur' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="sr-duration"><?php esc_html_e( 'Default duration (minutes)', 'smartrecur' ); ?></label></th>
				<td><input type="number" id="sr-duration" name="default_duration_min" min="5" max="600" value="<?php echo esc_attr( $f( 'default_duration_min', 60 ) ); ?>"></td>
			</tr>
			<tr>
				<th><label for="sr-location"><?php esc_html_e( 'Default location', 'smartrecur' ); ?></label></th>
				<td>
					<select id="sr-location" name="default_location">
						<option value="ON_SITE" <?php selected( $f( 'default_location', 'ON_SITE' ), 'ON_SITE' ); ?>><?php esc_html_e( 'On site', 'smartrecur' ); ?></option>
						<option value="REMOTE" <?php selected( $f( 'default_location' ), 'REMOTE' ); ?>><?php esc_html_e( 'Remote', 'smartrecur' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="sr-color"><?php esc_html_e( 'Calendar color', 'smartrecur' ); ?></label></th>
				<td><input type="color" id="sr-color" name="color" value="<?php echo esc_attr( $f( 'color', '#4F46E5' ) ); ?>"></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Syncro ticket', 'smartrecur' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="create_ticket" value="1" <?php checked( (int) $f( 'create_ticket' ), 1 ); ?>>
						<?php esc_html_e( 'Create a Syncro ticket when an appointment with this service is booked.', 'smartrecur' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th><label for="sr-reminders"><?php esc_html_e( 'Reminder days', 'smartrecur' ); ?></label></th>
				<td>
					<input type="text" id="sr-reminders" name="reminder_days" class="regular-text" value="<?php echo esc_attr( $reminders ); ?>">
					<p class="description"><?php esc_html_e( 'Comma-separated days before the appointment (e.g. 14, 7, 1). Leave blank to use the global default.', 'smartrecur' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="sr-tpl-subject"><?php esc_html_e( 'Email subject override', 'smartrecur' ); ?></label></th>
				<td><input type="text" id="sr-tpl-subject" name="email_template_subject" class="large-text" value="<?php echo esc_attr( $f( 'email_template_subject' ) ); ?>"></td>
			</tr>
			<tr>
				<th><label for="sr-tpl-body"><?php esc_html_e( 'Email body override', 'smartrecur' ); ?></label></th>
				<td><textarea id="sr-tpl-body" name="email_template_body" class="large-text" rows="4"><?php echo esc_textarea( $f( 'email_template_body' ) ); ?></textarea></td>
			</tr>
		</table>

		<?php submit_button( $is_edit ? __( 'Update Service', 'smartrecur' ) : __( 'Create Service', 'smartrecur' ), 'primary', 'smartrecur_save_service' ); ?>
	</form>
</div>

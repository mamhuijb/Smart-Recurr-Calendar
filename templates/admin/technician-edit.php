<?php
/**
 * Technician add/edit form.
 *
 * Expects: $technician (array|null).
 *
 * @package SmartRecur
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** @var array|null $technician */
$is_edit  = is_array( $technician );
$heading  = $is_edit ? __( 'Edit Technician', 'smartrecur' ) : __( 'Add Technician', 'smartrecur' );
$back_url = admin_url( 'admin.php?page=smartrecur-technicians' );
$f        = static function ( $key, $default = '' ) use ( $technician ) {
	return isset( $technician[ $key ] ) ? $technician[ $key ] : $default;
};
$skills = json_decode( (string) $f( 'skills', '[]' ), true );
$skills = is_array( $skills ) ? implode( ', ', $skills ) : '';
?>
<div class="wrap smartrecur-admin">
	<h1 class="wp-heading-inline"><?php echo esc_html( $heading ); ?></h1>
	<a href="<?php echo esc_url( $back_url ); ?>" class="page-title-action"><?php esc_html_e( 'Back to list', 'smartrecur' ); ?></a>
	<hr class="wp-header-end">

	<form method="post" class="smartrecur-form">
		<?php wp_nonce_field( 'smartrecur_save_technician' ); ?>
		<input type="hidden" name="id" value="<?php echo esc_attr( $f( 'id' ) ); ?>">

		<table class="form-table" role="presentation">
			<tr>
				<th><label for="sr-name"><?php esc_html_e( 'Name', 'smartrecur' ); ?> <span class="description">*</span></label></th>
				<td><input type="text" id="sr-name" name="name" class="regular-text" required value="<?php echo esc_attr( $f( 'name' ) ); ?>"></td>
			</tr>
			<tr>
				<th><label for="sr-email"><?php esc_html_e( 'Email', 'smartrecur' ); ?></label></th>
				<td><input type="email" id="sr-email" name="email" class="regular-text" value="<?php echo esc_attr( $f( 'email' ) ); ?>"></td>
			</tr>
			<tr>
				<th><label for="sr-color"><?php esc_html_e( 'Calendar color', 'smartrecur' ); ?></label></th>
				<td><input type="color" id="sr-color" name="color" value="<?php echo esc_attr( $f( 'color', '#10B981' ) ); ?>"></td>
			</tr>
			<tr>
				<th><label for="sr-skills"><?php esc_html_e( 'Skills', 'smartrecur' ); ?></label></th>
				<td>
					<input type="text" id="sr-skills" name="skills" class="large-text" value="<?php echo esc_attr( $skills ); ?>">
					<p class="description"><?php esc_html_e( 'Comma-separated list.', 'smartrecur' ); ?></p>
				</td>
			</tr>
		</table>

		<?php submit_button( $is_edit ? __( 'Update Technician', 'smartrecur' ) : __( 'Create Technician', 'smartrecur' ), 'primary', 'smartrecur_save_technician' ); ?>
	</form>
</div>

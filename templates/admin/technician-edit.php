<?php
/**
 * Technician add/edit form — modern card layout.
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
$theme    = ( ( ( (array) get_option( 'smartrecur_settings', array() ) )['branding']['themeMode'] ?? 'dark' ) === 'light' ) ? ' smartrecur-theme-light' : '';
$f        = static function ( $key, $default = '' ) use ( $technician ) {
	return isset( $technician[ $key ] ) ? $technician[ $key ] : $default;
};
$skills = json_decode( (string) $f( 'skills', '[]' ), true );
$skills = is_array( $skills ) ? implode( ', ', $skills ) : '';
?>
<div class="wrap smartrecur-admin<?php echo esc_attr( $theme ); ?>">

	<div class="sr-appbar">
		<div class="sr-appbar-brand">
			<div class="sr-appbar-logo"><span class="dashicons dashicons-admin-users"></span></div>
			<div>
				<div class="sr-appbar-title"><?php echo esc_html( $heading ); ?></div>
				<div class="sr-appbar-sub"><?php esc_html_e( 'Technicians', 'smartrecur' ); ?></div>
			</div>
		</div>
		<div class="sr-appbar-actions">
			<a class="sr-btn" href="<?php echo esc_url( $back_url ); ?>">
				<span class="dashicons dashicons-arrow-left-alt2"></span> <?php esc_html_e( 'Back', 'smartrecur' ); ?>
			</a>
		</div>
	</div>

	<form method="post" class="sr-form">
		<?php wp_nonce_field( 'smartrecur_save_technician' ); ?>
		<input type="hidden" name="id" value="<?php echo esc_attr( $f( 'id' ) ); ?>">

		<div class="sr-card">
			<h2><span class="dashicons dashicons-admin-users"></span> <?php esc_html_e( 'Technician details', 'smartrecur' ); ?></h2>

			<div class="sr-field">
				<label for="sr-name"><?php esc_html_e( 'Name', 'smartrecur' ); ?> <span class="sr-req">*</span></label>
				<input type="text" id="sr-name" name="name" required value="<?php echo esc_attr( $f( 'name' ) ); ?>">
			</div>
			<div class="sr-field">
				<label for="sr-email"><?php esc_html_e( 'Email', 'smartrecur' ); ?></label>
				<input type="email" id="sr-email" name="email" value="<?php echo esc_attr( $f( 'email' ) ); ?>">
			</div>
			<div class="sr-field">
				<label for="sr-color"><?php esc_html_e( 'Calendar color', 'smartrecur' ); ?></label>
				<input type="color" id="sr-color" name="color" value="<?php echo esc_attr( $f( 'color', '#10B981' ) ); ?>">
			</div>
			<div class="sr-field">
				<label for="sr-skills"><?php esc_html_e( 'Skills', 'smartrecur' ); ?></label>
				<input type="text" id="sr-skills" name="skills" value="<?php echo esc_attr( $skills ); ?>">
				<p class="description"><?php esc_html_e( 'Comma-separated list.', 'smartrecur' ); ?></p>
			</div>

			<div class="sr-form-actions">
				<button type="submit" name="smartrecur_save_technician" value="1" class="sr-btn sr-btn-primary">
					<span class="dashicons dashicons-saved"></span>
					<?php echo $is_edit ? esc_html__( 'Update Technician', 'smartrecur' ) : esc_html__( 'Create Technician', 'smartrecur' ); ?>
				</button>
				<a class="sr-btn" href="<?php echo esc_url( $back_url ); ?>"><?php esc_html_e( 'Cancel', 'smartrecur' ); ?></a>
			</div>
		</div>
	</form>
</div>

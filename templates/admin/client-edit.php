<?php
/**
 * Client add/edit form — modern card layout.
 *
 * Expects: $client (array|null).
 *
 * @package SmartRecur
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** @var array|null $client */
$is_edit  = is_array( $client );
$heading  = $is_edit ? __( 'Edit Client', 'smartrecur' ) : __( 'Add Client', 'smartrecur' );
$back_url = admin_url( 'admin.php?page=smartrecur-clients' );
$theme    = ( ( ( (array) get_option( 'smartrecur_settings', array() ) )['branding']['themeMode'] ?? 'dark' ) === 'light' ) ? ' smartrecur-theme-light' : '';
$f        = static function ( $key, $default = '' ) use ( $client ) {
	return isset( $client[ $key ] ) ? $client[ $key ] : $default;
};
?>
<div class="wrap smartrecur-admin<?php echo esc_attr( $theme ); ?>">

	<div class="sr-appbar">
		<div class="sr-appbar-brand">
			<div class="sr-appbar-logo"><span class="dashicons dashicons-groups"></span></div>
			<div>
				<div class="sr-appbar-title"><?php echo esc_html( $heading ); ?></div>
				<div class="sr-appbar-sub"><?php esc_html_e( 'Clients', 'smartrecur' ); ?></div>
			</div>
		</div>
		<div class="sr-appbar-actions">
			<a class="sr-btn" href="<?php echo esc_url( $back_url ); ?>">
				<span class="dashicons dashicons-arrow-left-alt2"></span> <?php esc_html_e( 'Back', 'smartrecur' ); ?>
			</a>
		</div>
	</div>

	<form method="post" class="sr-form">
		<?php wp_nonce_field( 'smartrecur_save_client' ); ?>
		<input type="hidden" name="id" value="<?php echo esc_attr( $f( 'id' ) ); ?>">

		<div class="sr-card">
			<h2><span class="dashicons dashicons-businessperson"></span> <?php esc_html_e( 'Client details', 'smartrecur' ); ?></h2>

			<div class="sr-field">
				<label for="sr-company"><?php esc_html_e( 'Company', 'smartrecur' ); ?></label>
				<input type="text" id="sr-company" name="company" value="<?php echo esc_attr( $f( 'company' ) ); ?>">
			</div>
			<div class="sr-field">
				<label for="sr-name"><?php esc_html_e( 'Contact name', 'smartrecur' ); ?> <span class="sr-req">*</span></label>
				<input type="text" id="sr-name" name="name" required value="<?php echo esc_attr( $f( 'name' ) ); ?>">
			</div>
			<div class="sr-field">
				<label for="sr-email"><?php esc_html_e( 'Email', 'smartrecur' ); ?></label>
				<input type="email" id="sr-email" name="email" value="<?php echo esc_attr( $f( 'email' ) ); ?>">
			</div>
			<div class="sr-field">
				<label for="sr-phone"><?php esc_html_e( 'Phone', 'smartrecur' ); ?></label>
				<input type="text" id="sr-phone" name="phone" value="<?php echo esc_attr( $f( 'phone' ) ); ?>">
			</div>
			<div class="sr-field">
				<label for="sr-address"><?php esc_html_e( 'Address', 'smartrecur' ); ?></label>
				<textarea id="sr-address" name="address" rows="2"><?php echo esc_textarea( $f( 'address' ) ); ?></textarea>
			</div>
			<div class="sr-field">
				<label for="sr-postcode"><?php esc_html_e( 'Postcode', 'smartrecur' ); ?></label>
				<input type="text" id="sr-postcode" name="postcode" value="<?php echo esc_attr( $f( 'postcode' ) ); ?>">
			</div>

			<div class="sr-form-actions">
				<button type="submit" name="smartrecur_save_client" value="1" class="sr-btn sr-btn-primary">
					<span class="dashicons dashicons-saved"></span>
					<?php echo $is_edit ? esc_html__( 'Update Client', 'smartrecur' ) : esc_html__( 'Create Client', 'smartrecur' ); ?>
				</button>
				<a class="sr-btn" href="<?php echo esc_url( $back_url ); ?>"><?php esc_html_e( 'Cancel', 'smartrecur' ); ?></a>
			</div>
		</div>
	</form>
</div>

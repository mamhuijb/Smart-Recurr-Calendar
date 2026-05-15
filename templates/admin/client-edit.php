<?php
/**
 * Client add/edit form.
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
$f        = static function ( $key, $default = '' ) use ( $client ) {
	return isset( $client[ $key ] ) ? $client[ $key ] : $default;
};
?>
<div class="wrap smartrecur-admin">
	<h1 class="wp-heading-inline"><?php echo esc_html( $heading ); ?></h1>
	<a href="<?php echo esc_url( $back_url ); ?>" class="page-title-action"><?php esc_html_e( 'Back to list', 'smartrecur' ); ?></a>
	<hr class="wp-header-end">

	<form method="post" class="smartrecur-form">
		<?php wp_nonce_field( 'smartrecur_save_client' ); ?>
		<input type="hidden" name="id" value="<?php echo esc_attr( $f( 'id' ) ); ?>">

		<table class="form-table" role="presentation">
			<tr>
				<th><label for="sr-company"><?php esc_html_e( 'Company', 'smartrecur' ); ?></label></th>
				<td><input type="text" id="sr-company" name="company" class="regular-text" value="<?php echo esc_attr( $f( 'company' ) ); ?>"></td>
			</tr>
			<tr>
				<th><label for="sr-name"><?php esc_html_e( 'Contact name', 'smartrecur' ); ?> <span class="description">*</span></label></th>
				<td><input type="text" id="sr-name" name="name" class="regular-text" required value="<?php echo esc_attr( $f( 'name' ) ); ?>"></td>
			</tr>
			<tr>
				<th><label for="sr-email"><?php esc_html_e( 'Email', 'smartrecur' ); ?></label></th>
				<td><input type="email" id="sr-email" name="email" class="regular-text" value="<?php echo esc_attr( $f( 'email' ) ); ?>"></td>
			</tr>
			<tr>
				<th><label for="sr-phone"><?php esc_html_e( 'Phone', 'smartrecur' ); ?></label></th>
				<td><input type="text" id="sr-phone" name="phone" class="regular-text" value="<?php echo esc_attr( $f( 'phone' ) ); ?>"></td>
			</tr>
			<tr>
				<th><label for="sr-address"><?php esc_html_e( 'Address', 'smartrecur' ); ?></label></th>
				<td><textarea id="sr-address" name="address" class="large-text" rows="2"><?php echo esc_textarea( $f( 'address' ) ); ?></textarea></td>
			</tr>
			<tr>
				<th><label for="sr-postcode"><?php esc_html_e( 'Postcode', 'smartrecur' ); ?></label></th>
				<td><input type="text" id="sr-postcode" name="postcode" class="regular-text" value="<?php echo esc_attr( $f( 'postcode' ) ); ?>"></td>
			</tr>
		</table>

		<?php submit_button( $is_edit ? __( 'Update Client', 'smartrecur' ) : __( 'Create Client', 'smartrecur' ), 'primary', 'smartrecur_save_client' ); ?>
	</form>
</div>

<?php
/**
 * Migration tool admin page.
 *
 * @package SmartRecur
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** @var array|null $result */
$result = isset( $result ) ? $result : null;
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Import from Standalone SmartRecur', 'smartrecur' ); ?></h1>
	<p class="description">
		<?php esc_html_e( 'Pull data from a legacy SmartRecur MariaDB database into this WordPress install. One-time tool.', 'smartrecur' ); ?>
	</p>

	<?php if ( $result ) : ?>
		<div class="notice notice-<?php echo ! empty( $result['success'] ) ? 'success' : 'error'; ?>">
			<p><strong><?php echo esc_html( $result['message'] ?? '' ); ?></strong></p>
			<?php if ( ! empty( $result['stats'] ) ) : ?>
				<ul>
					<?php foreach ( $result['stats'] as $label => $count ) : ?>
						<li><?php echo esc_html( $label ); ?>: <?php echo esc_html( $count ); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<form method="post">
		<?php wp_nonce_field( 'smartrecur_migration' ); ?>

		<table class="form-table" role="presentation">
			<tr>
				<th><label for="mg_host"><?php esc_html_e( 'DB Host', 'smartrecur' ); ?></label></th>
				<td><input type="text" id="mg_host" name="db_host" class="regular-text" placeholder="localhost"></td>
			</tr>
			<tr>
				<th><label for="mg_port"><?php esc_html_e( 'DB Port', 'smartrecur' ); ?></label></th>
				<td><input type="number" id="mg_port" name="db_port" value="3306"></td>
			</tr>
			<tr>
				<th><label for="mg_name"><?php esc_html_e( 'DB Name', 'smartrecur' ); ?></label></th>
				<td><input type="text" id="mg_name" name="db_name" class="regular-text" placeholder="smartrecur"></td>
			</tr>
			<tr>
				<th><label for="mg_user"><?php esc_html_e( 'DB User', 'smartrecur' ); ?></label></th>
				<td><input type="text" id="mg_user" name="db_user" class="regular-text" autocomplete="off"></td>
			</tr>
			<tr>
				<th><label for="mg_pass"><?php esc_html_e( 'DB Password', 'smartrecur' ); ?></label></th>
				<td><input type="password" id="mg_pass" name="db_pass" class="regular-text" autocomplete="new-password"></td>
			</tr>
		</table>

		<?php submit_button( __( 'Run Migration', 'smartrecur' ), 'primary', 'smartrecur_migration_run' ); ?>
	</form>
</div>

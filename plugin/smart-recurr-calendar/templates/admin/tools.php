<?php
/**
 * Tools screen: migration from the legacy standalone database.
 *
 * Expects: $result (array|null) from SmartRecur_Migrator::run_from_post().
 *
 * @package SmartRecur
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** @var array|null $result */
?>
<?php
$sr_theme = ( ( ( (array) get_option( 'smartrecur_settings', array() ) )['branding']['themeMode'] ?? 'dark' ) === 'light' ) ? ' smartrecur-theme-light' : '';
?>
<div class="wrap smartrecur-admin<?php echo esc_attr( $sr_theme ); ?>">

	<div class="sr-appbar">
		<div class="sr-appbar-brand">
			<div class="sr-appbar-logo"><span class="dashicons dashicons-admin-tools"></span></div>
			<div>
				<div class="sr-appbar-title"><?php esc_html_e( 'Tools', 'smartrecur' ); ?></div>
				<div class="sr-appbar-sub">SmartRecur</div>
			</div>
		</div>
	</div>

	<div class="sr-card">
	<h2><?php esc_html_e( 'Import from standalone SmartRecur', 'smartrecur' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Pull clients, services, technicians and appointments from a legacy standalone SmartRecur MariaDB database into this WordPress install. One-time tool.', 'smartrecur' ); ?>
	</p>

	<?php if ( ! is_ssl() ) : ?>
		<div class="notice notice-warning inline">
			<p><strong><?php esc_html_e( 'This admin session is not on HTTPS.', 'smartrecur' ); ?></strong>
			<?php esc_html_e( 'Database credentials submitted here would travel in cleartext. Switch to HTTPS first.', 'smartrecur' ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( $result ) : ?>
		<div class="notice notice-<?php echo ! empty( $result['success'] ) ? 'success' : 'error'; ?> inline">
			<p><strong><?php echo esc_html( $result['message'] ?? '' ); ?></strong></p>
			<?php if ( ! empty( $result['stats'] ) ) : ?>
				<ul style="list-style:disc;margin-left:20px;">
					<?php foreach ( $result['stats'] as $label => $count ) : ?>
						<li><?php echo esc_html( ucfirst( $label ) . ': ' . $count ); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<form method="post">
		<?php wp_nonce_field( 'smartrecur_migration' ); ?>
		<table class="form-table" role="presentation">
			<tr><th><label for="mg-host"><?php esc_html_e( 'DB host', 'smartrecur' ); ?></label></th>
				<td><input type="text" id="mg-host" name="db_host" class="regular-text" placeholder="localhost"></td></tr>
			<tr><th><label for="mg-port"><?php esc_html_e( 'DB port', 'smartrecur' ); ?></label></th>
				<td><input type="number" id="mg-port" name="db_port" value="3306"></td></tr>
			<tr><th><label for="mg-name"><?php esc_html_e( 'DB name', 'smartrecur' ); ?></label></th>
				<td><input type="text" id="mg-name" name="db_name" class="regular-text" placeholder="smartrecur"></td></tr>
			<tr><th><label for="mg-user"><?php esc_html_e( 'DB user', 'smartrecur' ); ?></label></th>
				<td><input type="text" id="mg-user" name="db_user" class="regular-text" autocomplete="off"></td></tr>
			<tr><th><label for="mg-pass"><?php esc_html_e( 'DB password', 'smartrecur' ); ?></label></th>
				<td><input type="password" id="mg-pass" name="db_pass" class="regular-text" autocomplete="new-password"></td></tr>
		</table>
		<?php submit_button( __( 'Run migration', 'smartrecur' ), 'primary', 'smartrecur_migration_run' ); ?>
	</form>

	</div>

	<div class="sr-card">
	<h2><?php esc_html_e( 'Backup', 'smartrecur' ); ?></h2>
	<p class="description">
		<?php
		printf(
			/* translators: %s REST endpoint */
			esc_html__( 'Export a full JSON backup via the REST endpoint %s (requires the smartrecur_manage capability and a valid nonce).', 'smartrecur' ),
			'<code>' . esc_html( rest_url( SMARTRECUR_REST_NAMESPACE . '/settings/backup' ) ) . '</code>'
		);
		?>
	</p>
	</div>
</div>

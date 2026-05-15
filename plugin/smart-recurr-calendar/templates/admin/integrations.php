<?php
/**
 * Integrations screen.
 *
 * Expects: $integrations (array from SmartRecur_Integration_Config::all()),
 * $o365_ready (bool — whether the bundled/overridden client ID is set).
 *
 * @package SmartRecur
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** @var array $integrations */
/** @var bool $o365_ready */
$mask = static function ( $value ) {
	return '' !== (string) $value ? '••••••••' : '';
};
$o365          = $integrations['office365'] ?? array( 'isConnected' => false, 'config' => array() );
$o365_config   = $o365['config'] ?? array();
$o365_email    = $o365_config['userEmail'] ?? '';
$o365_calendar = $o365_config['calendarName'] ?? '';
$o365_last     = isset( $o365_config['lastSyncedAt'] ) ? (int) $o365_config['lastSyncedAt'] : 0;
$syncro        = $integrations['syncro']['config'] ?? array();
$invoiceninja  = $integrations['invoiceninja']['config'] ?? array();
$zoho          = $integrations['zoho']['config'] ?? array();
?>
<?php
$sr_theme = ( ( ( (array) get_option( 'smartrecur_settings', array() ) )['branding']['themeMode'] ?? 'dark' ) === 'light' ) ? ' smartrecur-theme-light' : '';
?>
<div class="wrap smartrecur-admin<?php echo esc_attr( $sr_theme ); ?>">

	<div class="sr-appbar">
		<div class="sr-appbar-brand">
			<div class="sr-appbar-logo"><span class="dashicons dashicons-networking"></span></div>
			<div>
				<div class="sr-appbar-title"><?php esc_html_e( 'Integrations', 'smartrecur' ); ?></div>
				<div class="sr-appbar-sub">SmartRecur</div>
			</div>
		</div>
	</div>

	<div class="sr-card">
		<h2><?php esc_html_e( 'Office 365 Calendar', 'smartrecur' ); ?></h2>

		<?php if ( ! $o365_ready ) : ?>
			<div class="notice notice-warning inline">
				<p>
					<?php esc_html_e( 'Office 365 is not configured yet. The plugin author must set the bundled Microsoft Graph client ID, or you can define SMARTRECUR_O365_CLIENT_ID in wp-config.php. See the plugin README for the one-time Azure setup.', 'smartrecur' ); ?>
				</p>
			</div>
		<?php elseif ( ! empty( $o365['isConnected'] ) ) : ?>
			<p>
				<span class="sr-badge sr-badge-ok"><?php esc_html_e( 'Connected', 'smartrecur' ); ?></span>
				<?php echo esc_html( $o365_email ); ?>
			</p>
			<table class="form-table" role="presentation">
				<tr>
					<th><label for="sr-o365-calendar"><?php esc_html_e( 'Calendar to sync', 'smartrecur' ); ?></label></th>
					<td>
						<select id="sr-o365-calendar" data-current="<?php echo esc_attr( $o365_config['calendarId'] ?? '' ); ?>">
							<option value=""><?php esc_html_e( 'Loading calendars…', 'smartrecur' ); ?></option>
						</select>
						<button type="button" class="button" id="sr-o365-save-calendar"><?php esc_html_e( 'Use this calendar', 'smartrecur' ); ?></button>
						<?php if ( $o365_calendar ) : ?>
							<p class="description"><?php echo esc_html( sprintf( /* translators: %s calendar name */ __( 'Currently syncing: %s', 'smartrecur' ), $o365_calendar ) ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Two-way sync', 'smartrecur' ); ?></th>
					<td>
						<button type="button" class="button button-secondary" id="sr-o365-sync-now"><?php esc_html_e( 'Sync now', 'smartrecur' ); ?></button>
						<button type="button" class="button" id="sr-o365-disconnect"><?php esc_html_e( 'Disconnect', 'smartrecur' ); ?></button>
						<p class="description">
							<?php
							if ( $o365_last ) {
								echo esc_html( sprintf( /* translators: %s human time diff */ __( 'Last synced %s ago.', 'smartrecur' ), human_time_diff( $o365_last ) ) );
							} else {
								esc_html_e( 'Appointments push to Outlook instantly; Outlook changes pull in every 15 minutes.', 'smartrecur' );
							}
							?>
						</p>
					</td>
				</tr>
			</table>
		<?php else : ?>
			<p><span class="sr-badge sr-badge-off"><?php esc_html_e( 'Not connected', 'smartrecur' ); ?></span></p>
			<p><button type="button" class="button button-primary" id="sr-o365-connect"><?php esc_html_e( 'Connect to Office 365', 'smartrecur' ); ?></button></p>
			<p class="description"><?php esc_html_e( 'Opens a Microsoft sign-in window. After you consent, pick a calendar and two-way sync starts automatically.', 'smartrecur' ); ?></p>
		<?php endif; ?>
	</div>

	<form method="post">
		<?php wp_nonce_field( 'smartrecur_integrations' ); ?>

		<div class="sr-card">
			<h2><?php esc_html_e( 'Syncro MSP', 'smartrecur' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th><label for="sr-syncro-sub"><?php esc_html_e( 'Subdomain', 'smartrecur' ); ?></label></th>
					<td><input type="text" id="sr-syncro-sub" name="syncro[subdomain]" class="regular-text" value="<?php echo esc_attr( $syncro['subdomain'] ?? '' ); ?>"> <span class="description">.syncromsp.com</span></td>
				</tr>
				<tr>
					<th><label for="sr-syncro-key"><?php esc_html_e( 'API key', 'smartrecur' ); ?></label></th>
					<td><input type="password" id="sr-syncro-key" name="syncro[apiKey]" class="regular-text" value="<?php echo esc_attr( $mask( $syncro['apiKey'] ?? '' ) ); ?>" autocomplete="off"></td>
				</tr>
			</table>
		</div>

		<div class="sr-card">
			<h2><?php esc_html_e( 'Invoice Ninja', 'smartrecur' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th><label for="sr-in-endpoint"><?php esc_html_e( 'Endpoint URL', 'smartrecur' ); ?></label></th>
					<td><input type="url" id="sr-in-endpoint" name="invoiceninja[endpoint]" class="regular-text" value="<?php echo esc_attr( $invoiceninja['endpoint'] ?? 'https://app.invoiceninja.com' ); ?>"></td>
				</tr>
				<tr>
					<th><label for="sr-in-key"><?php esc_html_e( 'API key', 'smartrecur' ); ?></label></th>
					<td><input type="password" id="sr-in-key" name="invoiceninja[apiKey]" class="regular-text" value="<?php echo esc_attr( $mask( $invoiceninja['apiKey'] ?? '' ) ); ?>" autocomplete="off"></td>
				</tr>
			</table>
		</div>

		<div class="sr-card">
			<h2><?php esc_html_e( 'Zoho', 'smartrecur' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th><label for="sr-zoho-endpoint"><?php esc_html_e( 'Endpoint URL', 'smartrecur' ); ?></label></th>
					<td><input type="url" id="sr-zoho-endpoint" name="zoho[endpoint]" class="regular-text" value="<?php echo esc_attr( $zoho['endpoint'] ?? 'https://www.zohoapis.com' ); ?>"></td>
				</tr>
				<tr>
					<th><label for="sr-zoho-key"><?php esc_html_e( 'Access token', 'smartrecur' ); ?></label></th>
					<td><input type="password" id="sr-zoho-key" name="zoho[apiKey]" class="regular-text" value="<?php echo esc_attr( $mask( $zoho['apiKey'] ?? '' ) ); ?>" autocomplete="off"></td>
				</tr>
			</table>
		</div>

		<?php submit_button( __( 'Save Integrations', 'smartrecur' ), 'primary', 'smartrecur_integration_save' ); ?>
	</form>
</div>

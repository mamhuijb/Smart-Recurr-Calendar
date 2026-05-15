<?php
/**
 * Integrations admin form.
 *
 * @package SmartRecur
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** @var array $integrations */
$integrations = isset( $integrations ) ? $integrations : array();

$mask = function ( $value ) {
	return '' !== (string) $value ? '••••••••' : '';
};

$callback_url = rest_url( SMARTRECUR_REST_NAMESPACE . '/integrations/office365/callback' );
?>
<div class="wrap">
	<h1><?php esc_html_e( 'SmartRecur Integrations', 'smartrecur' ); ?></h1>
	<p class="description">
		<?php esc_html_e( 'Connect SmartRecur to Office 365, Syncro MSP, Invoice Ninja, and Zoho. Secrets are encrypted at rest.', 'smartrecur' ); ?>
	</p>

	<form method="post">
		<?php wp_nonce_field( 'smartrecur_integrations' ); ?>

		<h2><?php esc_html_e( 'Office 365', 'smartrecur' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'OAuth callback URL (register this in Azure AD):', 'smartrecur' ); ?>
			<code><?php echo esc_html( $callback_url ); ?></code>
		</p>
		<p class="description">
			<?php
			printf(
				/* translators: %s: php constant name */
				esc_html__( 'Add %s to wp-config.php; the secret is never stored in the database.', 'smartrecur' ),
				'<code>define( \'SMARTRECUR_O365_CLIENT_SECRET\', \'...\' );</code>'
			);
			?>
		</p>
		<table class="form-table" role="presentation">
			<tr>
				<th><label for="o365_client_id"><?php esc_html_e( 'Client ID', 'smartrecur' ); ?></label></th>
				<td><input type="text" id="o365_client_id" name="office365[clientId]" class="regular-text" value="<?php echo esc_attr( $integrations['office365']['config']['clientId'] ?? '' ); ?>"></td>
			</tr>
			<tr>
				<th><label for="o365_tenant_id"><?php esc_html_e( 'Tenant ID', 'smartrecur' ); ?></label></th>
				<td><input type="text" id="o365_tenant_id" name="office365[tenantId]" class="regular-text" value="<?php echo esc_attr( $integrations['office365']['config']['tenantId'] ?? 'common' ); ?>"></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Status', 'smartrecur' ); ?></th>
				<td>
					<?php echo ! empty( $integrations['office365']['isConnected'] ) ? '<span style="color:#46b450">' . esc_html__( 'Connected', 'smartrecur' ) . '</span>' : '<span style="color:#a00">' . esc_html__( 'Disconnected', 'smartrecur' ) . '</span>'; ?>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Syncro MSP', 'smartrecur' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th><label for="syncro_subdomain"><?php esc_html_e( 'Subdomain', 'smartrecur' ); ?></label></th>
				<td><input type="text" id="syncro_subdomain" name="syncro[subdomain]" class="regular-text" value="<?php echo esc_attr( $integrations['syncro']['config']['subdomain'] ?? '' ); ?>"> <span class="description">.syncromsp.com</span></td>
			</tr>
			<tr>
				<th><label for="syncro_api_key"><?php esc_html_e( 'API Key', 'smartrecur' ); ?></label></th>
				<td><input type="password" id="syncro_api_key" name="syncro[apiKey]" class="regular-text" value="<?php echo esc_attr( $mask( $integrations['syncro']['config']['apiKey'] ?? '' ) ); ?>" autocomplete="off"></td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Invoice Ninja', 'smartrecur' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th><label for="in_endpoint"><?php esc_html_e( 'Endpoint URL', 'smartrecur' ); ?></label></th>
				<td><input type="url" id="in_endpoint" name="invoiceninja[endpoint]" class="regular-text" value="<?php echo esc_attr( $integrations['invoiceninja']['config']['endpoint'] ?? 'https://app.invoiceninja.com' ); ?>"></td>
			</tr>
			<tr>
				<th><label for="in_api_key"><?php esc_html_e( 'API Key', 'smartrecur' ); ?></label></th>
				<td><input type="password" id="in_api_key" name="invoiceninja[apiKey]" class="regular-text" value="<?php echo esc_attr( $mask( $integrations['invoiceninja']['config']['apiKey'] ?? '' ) ); ?>" autocomplete="off"></td>
			</tr>
		</table>

		<h2><?php esc_html_e( 'Zoho', 'smartrecur' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th><label for="zoho_endpoint"><?php esc_html_e( 'Endpoint URL', 'smartrecur' ); ?></label></th>
				<td><input type="url" id="zoho_endpoint" name="zoho[endpoint]" class="regular-text" value="<?php echo esc_attr( $integrations['zoho']['config']['endpoint'] ?? 'https://www.zohoapis.com' ); ?>"></td>
			</tr>
			<tr>
				<th><label for="zoho_api_key"><?php esc_html_e( 'Access Token', 'smartrecur' ); ?></label></th>
				<td><input type="password" id="zoho_api_key" name="zoho[apiKey]" class="regular-text" value="<?php echo esc_attr( $mask( $integrations['zoho']['config']['apiKey'] ?? '' ) ); ?>" autocomplete="off"></td>
			</tr>
		</table>

		<?php submit_button( __( 'Save Integrations', 'smartrecur' ), 'primary', 'smartrecur_integration_save' ); ?>
	</form>
</div>

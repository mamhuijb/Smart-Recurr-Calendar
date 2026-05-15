<?php
/**
 * Generic entity list screen (clients / services / technicians / appointments).
 *
 * Expects: $table (SmartRecur_List_Table), $add_url (string), $title (string).
 *
 * @package SmartRecur
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** @var SmartRecur_List_Table $table */
/** @var string $add_url */
/** @var string $title */
$page_slug = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
// WP_List_Table builds its bulk-action nonce as `bulk-{plural}`; the generic
// table sets {plural} to the page slug minus the `smartrecur-` prefix.
$bulk_nonce_action = 'bulk-' . str_replace( 'smartrecur-', '', $page_slug );
$theme             = ( ( ( (array) get_option( 'smartrecur_settings', array() ) )['branding']['themeMode'] ?? 'dark' ) === 'light' ) ? ' smartrecur-theme-light' : '';
?>
<div class="wrap smartrecur-admin<?php echo esc_attr( $theme ); ?>">

	<div class="sr-appbar">
		<div class="sr-appbar-brand">
			<div class="sr-appbar-logo"><span class="dashicons dashicons-calendar-alt"></span></div>
			<div>
				<div class="sr-appbar-title"><?php echo esc_html( $title ); ?></div>
				<div class="sr-appbar-sub">SmartRecur</div>
			</div>
		</div>
		<div class="sr-appbar-actions">
			<a class="sr-btn sr-btn-primary" href="<?php echo esc_url( $add_url ); ?>">
				<span class="dashicons dashicons-plus-alt2"></span> <?php esc_html_e( 'Add New', 'smartrecur' ); ?>
			</a>
		</div>
	</div>

	<div class="sr-card sr-card-pad-0" style="padding:16px 18px;">
		<form method="get" style="margin-bottom:10px;">
			<input type="hidden" name="page" value="<?php echo esc_attr( $page_slug ); ?>">
			<?php $table->search_box( __( 'Search', 'smartrecur' ), 'smartrecur-search' ); ?>
		</form>
		<form method="post">
			<input type="hidden" name="page" value="<?php echo esc_attr( $page_slug ); ?>">
			<?php
			wp_nonce_field( $bulk_nonce_action );
			$table->display();
			?>
		</form>
	</div>
</div>

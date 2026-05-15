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
// WP_List_Table generates its bulk-action nonce as `bulk-{plural}`; the generic
// table sets {plural} to the page slug minus the `smartrecur-` prefix.
$bulk_nonce_action = 'bulk-' . str_replace( 'smartrecur-', '', $page_slug );
?>
<div class="wrap smartrecur-admin">
	<h1 class="wp-heading-inline"><?php echo esc_html( $title ); ?></h1>
	<a href="<?php echo esc_url( $add_url ); ?>" class="page-title-action"><?php esc_html_e( 'Add New', 'smartrecur' ); ?></a>
	<hr class="wp-header-end">

	<form method="get">
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

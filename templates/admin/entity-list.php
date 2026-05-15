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
?>
<div class="wrap smartrecur-admin">
	<h1 class="wp-heading-inline"><?php echo esc_html( $title ); ?></h1>
	<a href="<?php echo esc_url( $add_url ); ?>" class="page-title-action"><?php esc_html_e( 'Add New', 'smartrecur' ); ?></a>
	<hr class="wp-header-end">

	<form method="get">
		<input type="hidden" name="page" value="<?php echo esc_attr( isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '' ); ?>">
		<?php $table->search_box( __( 'Search', 'smartrecur' ), 'smartrecur-search' ); ?>
		<?php $table->display(); ?>
	</form>
</div>

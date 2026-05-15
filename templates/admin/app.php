<?php
/**
 * Generic admin React-mount page.
 *
 * Expects `$view` to be set by the caller.
 *
 * @package SmartRecur
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** @var string $view */
$view = isset( $view ) ? $view : 'dashboard';
?>
<div class="wrap">
	<div id="smartrecur-app" class="smartrecur-wrap" data-view="<?php echo esc_attr( $view ); ?>"></div>
</div>

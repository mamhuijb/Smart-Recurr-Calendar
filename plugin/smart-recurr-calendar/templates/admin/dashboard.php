<?php
/**
 * Dashboard admin page — mounts the React app with the "dashboard" view.
 *
 * @package SmartRecur
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap">
	<h1><?php esc_html_e( 'SmartRecur Dashboard', 'smartrecur' ); ?></h1>
	<p class="description"><?php esc_html_e( 'Overview of upcoming appointments, recent activity, and integration status.', 'smartrecur' ); ?></p>
	<div id="smartrecur-app" class="smartrecur-wrap" data-view="dashboard"></div>
</div>

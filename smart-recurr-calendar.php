<?php
/**
 * Plugin Name:       SmartRecur Calendar
 * Plugin URI:        https://github.com/mamhuijb/Smart-Recurr-Calendar
 * Description:       Recurring appointment scheduler for MSPs. Office 365 + Syncro + Invoice Ninja + Zoho integrations. Embed via shortcode or Elementor widget.
 * Version:           2026.06.3.1
 * Requires at least: 6.0
 * Requires PHP:      8.4
 * Author:            Huijbregts ICT
 * Author URI:        https://github.com/mamhuijb
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       smartrecur
 * Domain Path:       /languages
 *
 * @package SmartRecur
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SMARTRECUR_VERSION', '2026.06.3.1' );
define( 'SMARTRECUR_DB_VERSION', '1.0.0' );
define( 'SMARTRECUR_PLUGIN_FILE', __FILE__ );
define( 'SMARTRECUR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SMARTRECUR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SMARTRECUR_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'SMARTRECUR_REST_NAMESPACE', 'smartrecur/v1' );

require_once SMARTRECUR_PLUGIN_DIR . 'includes/class-smartrecur-autoloader.php';
SmartRecur_Autoloader::register();

register_activation_hook( __FILE__, array( 'SmartRecur_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'SmartRecur_Deactivator', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'SmartRecur_Plugin', 'instance' ) );

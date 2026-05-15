<?php
/**
 * Main plugin orchestrator. Boots controllers, admin, shortcode, Elementor widget.
 *
 * @package SmartRecur
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SmartRecur_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var SmartRecur_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return SmartRecur_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Wire up WordPress hooks.
	 */
	private function __construct() {
		load_plugin_textdomain( 'smartrecur', false, dirname( SMARTRECUR_PLUGIN_BASENAME ) . '/languages' );

		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
		add_action( 'init', array( $this, 'register_shortcode' ) );
		add_action( 'init', array( $this, 'maybe_upgrade_db' ) );
		add_action( 'admin_menu', array( 'SmartRecur_Admin', 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( 'SmartRecur_Admin', 'enqueue_admin_assets' ) );
		add_action( 'admin_notices', array( 'SmartRecur_Admin', 'maybe_welcome_notice' ) );

		if ( did_action( 'elementor/loaded' ) || defined( 'ELEMENTOR_VERSION' ) ) {
			add_action( 'elementor/widgets/register', array( $this, 'register_elementor_widget' ) );
			add_action( 'elementor/elements/categories_registered', array( $this, 'register_elementor_category' ) );
		}
	}

	/**
	 * Run incremental DB upgrades if the stored version is older than the bundled one.
	 */
	public function maybe_upgrade_db() {
		$installed = get_option( 'smartrecur_db_version', '0.0.0' );
		if ( version_compare( $installed, SMARTRECUR_DB_VERSION, '<' ) ) {
			SmartRecur_Activator::create_tables();
			update_option( 'smartrecur_db_version', SMARTRECUR_DB_VERSION );
		}
	}

	/**
	 * Register all REST controllers under the smartrecur/v1 namespace.
	 */
	public function register_rest_routes() {
		( new SmartRecur_Appointments_Controller() )->register_routes();
		( new SmartRecur_Clients_Controller() )->register_routes();
		( new SmartRecur_Services_Controller() )->register_routes();
		( new SmartRecur_Technicians_Controller() )->register_routes();
		( new SmartRecur_Recurring_Rules_Controller() )->register_routes();
		( new SmartRecur_Calendar_Controller() )->register_routes();
		( new SmartRecur_Settings_Controller() )->register_routes();
		( new SmartRecur_Integrations_Controller() )->register_routes();
	}

	/**
	 * Register the [smartrecur] shortcode.
	 */
	public function register_shortcode() {
		SmartRecur_Shortcode::register();
	}

	/**
	 * Hook the Elementor widget into Elementor's registration cycle.
	 *
	 * @param \Elementor\Widgets_Manager $widgets_manager Elementor's widget manager.
	 */
	public function register_elementor_widget( $widgets_manager ) {
		if ( ! class_exists( '\\Elementor\\Widget_Base' ) ) {
			return;
		}
		require_once SMARTRECUR_PLUGIN_DIR . 'includes/class-smartrecur-elementor-widget.php';
		$widgets_manager->register( new SmartRecur_Elementor_Widget() );
	}

	/**
	 * Register a dedicated Elementor category for SmartRecur widgets.
	 *
	 * @param \Elementor\Elements_Manager $elements_manager Elementor's elements manager.
	 */
	public function register_elementor_category( $elements_manager ) {
		$elements_manager->add_category(
			'smartrecur',
			array(
				'title' => __( 'SmartRecur', 'smartrecur' ),
				'icon'  => 'eicon-calendar',
			)
		);
	}
}

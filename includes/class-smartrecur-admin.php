<?php
/**
 * WP admin: menu structure, page renderers, asset enqueueing.
 *
 * @package SmartRecur
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SmartRecur_Admin {

	/**
	 * Display a one-shot "welcome" admin notice immediately after activation,
	 * pointing the admin at the Integrations and Settings screens. The notice is
	 * driven by a 60-second transient set in the activator.
	 */
	public static function maybe_welcome_notice() {
		if ( ! get_transient( 'smartrecur_just_activated' ) ) {
			return;
		}
		if ( ! current_user_can( 'smartrecur_manage' ) ) {
			return;
		}
		delete_transient( 'smartrecur_just_activated' );

		$integrations = esc_url( admin_url( 'admin.php?page=smartrecur-integrations' ) );
		$settings     = esc_url( admin_url( 'admin.php?page=smartrecur-settings' ) );

		echo '<div class="notice notice-success is-dismissible"><p>';
		echo '<strong>' . esc_html__( 'SmartRecur Calendar is installed and ready.', 'smartrecur' ) . '</strong> ';
		echo wp_kses(
			sprintf(
				/* translators: %1$s integrations URL, %2$s settings URL */
				__( 'Next: <a href="%1$s">connect your integrations</a> and review your <a href="%2$s">business hours</a>. Add <code>[smartrecur]</code> to any page to display the calendar.', 'smartrecur' ),
				$integrations,
				$settings
			),
			array( 'a' => array( 'href' => array() ), 'code' => array() )
		);
		echo '</p></div>';
	}

	/**
	 * Register admin menu + submenus.
	 */
	public static function register_menu() {
		add_menu_page(
			__( 'SmartRecur', 'smartrecur' ),
			__( 'SmartRecur', 'smartrecur' ),
			'smartrecur_view',
			'smartrecur',
			array( __CLASS__, 'render_dashboard' ),
			'dashicons-calendar-alt',
			28
		);

		add_submenu_page( 'smartrecur', __( 'Dashboard', 'smartrecur' ), __( 'Dashboard', 'smartrecur' ), 'smartrecur_view', 'smartrecur', array( __CLASS__, 'render_dashboard' ) );
		add_submenu_page( 'smartrecur', __( 'Appointments', 'smartrecur' ), __( 'Appointments', 'smartrecur' ), 'smartrecur_view', 'smartrecur-appointments', array( __CLASS__, 'render_app_page' ) );
		add_submenu_page( 'smartrecur', __( 'Clients', 'smartrecur' ), __( 'Clients', 'smartrecur' ), 'smartrecur_view', 'smartrecur-clients', array( __CLASS__, 'render_app_page' ) );
		add_submenu_page( 'smartrecur', __( 'Integrations', 'smartrecur' ), __( 'Integrations', 'smartrecur' ), 'smartrecur_manage', 'smartrecur-integrations', array( __CLASS__, 'render_integrations' ) );
		add_submenu_page( 'smartrecur', __( 'Settings', 'smartrecur' ), __( 'Settings', 'smartrecur' ), 'smartrecur_manage', 'smartrecur-settings', array( __CLASS__, 'render_settings' ) );
		add_submenu_page( 'smartrecur', __( 'Import from Standalone', 'smartrecur' ), __( 'Migration Tool', 'smartrecur' ), 'smartrecur_manage', 'smartrecur-migration', array( __CLASS__, 'render_migration' ) );
	}

	/**
	 * Enqueue React bundle on SmartRecur admin pages.
	 *
	 * @param string $hook Current admin hook name.
	 */
	public static function enqueue_admin_assets( $hook ) {
		if ( ! is_string( $hook ) || strpos( $hook, 'smartrecur' ) === false ) {
			return;
		}
		// Side-effect: register + enqueue the front-end bundle.
		SmartRecur_Shortcode::register_assets();
		SmartRecur_Shortcode::enqueue_runtime();
	}

	/**
	 * Dashboard view (React mount).
	 */
	public static function render_dashboard() {
		include SMARTRECUR_PLUGIN_DIR . 'templates/admin/dashboard.php';
	}

	/**
	 * Generic React-mount admin page; the data-view attribute is derived from the page slug.
	 */
	public static function render_app_page() {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		$view = 'dashboard';
		if ( 'smartrecur-appointments' === $page ) {
			$view = 'calendar';
		} elseif ( 'smartrecur-clients' === $page ) {
			$view = 'clients';
		}
		include SMARTRECUR_PLUGIN_DIR . 'templates/admin/app.php';
	}

	/**
	 * Integrations admin page (PHP-rendered form, not React).
	 */
	public static function render_integrations() {
		if ( ! current_user_can( 'smartrecur_manage' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage integrations.', 'smartrecur' ) );
		}

		if ( ! empty( $_POST['smartrecur_integration_save'] ) ) {
			check_admin_referer( 'smartrecur_integrations' );
			self::save_integrations_form();
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Integrations saved.', 'smartrecur' ) . '</p></div>';
		}

		$integrations = SmartRecur_Integration_Config::all();
		include SMARTRECUR_PLUGIN_DIR . 'templates/admin/integrations.php';
	}

	/**
	 * Settings admin page (PHP-rendered).
	 */
	public static function render_settings() {
		if ( ! current_user_can( 'smartrecur_manage' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage settings.', 'smartrecur' ) );
		}

		if ( ! empty( $_POST['smartrecur_settings_save'] ) ) {
			check_admin_referer( 'smartrecur_settings' );
			self::save_settings_form();
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'smartrecur' ) . '</p></div>';
		}

		$settings = (array) get_option( 'smartrecur_settings', array() );
		include SMARTRECUR_PLUGIN_DIR . 'templates/admin/settings.php';
	}

	/**
	 * Migration admin page.
	 */
	public static function render_migration() {
		if ( ! current_user_can( 'smartrecur_manage' ) ) {
			wp_die( esc_html__( 'You are not allowed to run the migration tool.', 'smartrecur' ) );
		}

		$result = null;
		if ( ! empty( $_POST['smartrecur_migration_run'] ) ) {
			check_admin_referer( 'smartrecur_migration' );
			$result = SmartRecur_Migrator::run_from_post( $_POST );
		}
		include SMARTRECUR_PLUGIN_DIR . 'templates/admin/migration.php';
	}

	/**
	 * Persist the integrations admin form.
	 */
	private static function save_integrations_form() {
		$types = array( 'office365', 'syncro', 'invoiceninja', 'zoho' );
		foreach ( $types as $type ) {
			$incoming = isset( $_POST[ $type ] ) && is_array( $_POST[ $type ] ) ? wp_unslash( $_POST[ $type ] ) : array();
			if ( empty( $incoming ) ) {
				continue;
			}
			$existing = SmartRecur_Integration_Config::get( $type );
			foreach ( $incoming as $key => $value ) {
				if ( '••••••••' === $value || '' === $value ) {
					continue;
				}
				$existing[ $key ] = sanitize_text_field( (string) $value );
			}
			SmartRecur_Integration_Config::put( $type, $existing );
		}
	}

	/**
	 * Persist the settings admin form.
	 */
	private static function save_settings_form() {
		$settings = (array) get_option( 'smartrecur_settings', array() );

		// Business hours.
		if ( isset( $_POST['business_start'] ) ) {
			$settings['businessHours']['start'] = sanitize_text_field( wp_unslash( $_POST['business_start'] ) );
		}
		if ( isset( $_POST['business_end'] ) ) {
			$settings['businessHours']['end'] = sanitize_text_field( wp_unslash( $_POST['business_end'] ) );
		}
		if ( isset( $_POST['business_timezone'] ) ) {
			$settings['businessHours']['timezone'] = sanitize_text_field( wp_unslash( $_POST['business_timezone'] ) );
		}
		$settings['businessHours']['closedDays'] = array();
		if ( ! empty( $_POST['closed_days'] ) && is_array( $_POST['closed_days'] ) ) {
			$settings['businessHours']['closedDays'] = array_map( 'absint', $_POST['closed_days'] );
		}

		// Durations.
		$settings['durations'] = array();
		if ( ! empty( $_POST['durations'] ) && is_array( $_POST['durations'] ) ) {
			$settings['durations'] = array_map( 'absint', $_POST['durations'] );
		}

		// Booking rules.
		$settings['bookingRules'] = array(
			'bufferMinutes' => isset( $_POST['buffer_minutes'] ) ? absint( $_POST['buffer_minutes'] ) : 0,
			'maxFutureDays' => isset( $_POST['max_future_days'] ) ? absint( $_POST['max_future_days'] ) : 365,
			'maxPerDay'     => isset( $_POST['max_per_day'] ) ? absint( $_POST['max_per_day'] ) : 0,
		);

		// Notifications.
		$settings['notifications'] = array(
			'emailEnabled' => ! empty( $_POST['email_enabled'] ),
		);
		if ( isset( $_POST['template_subject'] ) ) {
			$settings['templates']['reminder']['subject'] = sanitize_text_field( wp_unslash( $_POST['template_subject'] ) );
		}
		if ( isset( $_POST['template_body'] ) ) {
			$settings['templates']['reminder']['body'] = sanitize_textarea_field( wp_unslash( $_POST['template_body'] ) );
		}

		// Reminders.
		$reminders = isset( $_POST['reminder_days'] ) ? sanitize_text_field( wp_unslash( $_POST['reminder_days'] ) ) : '';
		$settings['reminders']['days'] = array_values( array_filter( array_map( 'absint', explode( ',', $reminders ) ) ) );

		// Data management.
		$settings['deleteDataOnUninstall'] = ! empty( $_POST['delete_data_on_uninstall'] );

		update_option( 'smartrecur_settings', $settings );
	}
}

<?php
/**
 * [smartrecur] shortcode — renders the React app mount point on the front end.
 *
 * @package SmartRecur
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SmartRecur_Shortcode {

	/**
	 * Register the shortcode with WordPress.
	 */
	public static function register() {
		add_shortcode( 'smartrecur', array( __CLASS__, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
	}

	/**
	 * Register (but don't enqueue) the script and stylesheet so they can be lazy-loaded
	 * only on pages where the shortcode or widget renders.
	 */
	public static function register_assets() {
		wp_register_script(
			'smartrecur-app',
			SMARTRECUR_PLUGIN_URL . 'assets/js/smartrecur-app.js',
			array(),
			SMARTRECUR_VERSION,
			true
		);

		wp_register_style(
			'smartrecur-app',
			SMARTRECUR_PLUGIN_URL . 'assets/css/smartrecur-app.css',
			array(),
			SMARTRECUR_VERSION
		);
	}

	/**
	 * Render the shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public static function render( $atts ) {
		$atts = shortcode_atts(
			array(
				'view'      => 'calendar',
				'client_id' => '',
			),
			$atts,
			'smartrecur'
		);

		if ( ! is_user_logged_in() ) {
			$login = wp_login_url( get_permalink() );
			return '<div class="smartrecur-wrap smartrecur-login-required">'
				. esc_html__( 'You must be signed in to view this calendar.', 'smartrecur' )
				. ' <a href="' . esc_url( $login ) . '">' . esc_html__( 'Sign in', 'smartrecur' ) . '</a>'
				. '</div>';
		}

		self::enqueue_runtime();

		$view = in_array( $atts['view'], array( 'calendar', 'booking', 'admin', 'dashboard' ), true ) ? $atts['view'] : 'calendar';

		return sprintf(
			'<div id="smartrecur-app" class="smartrecur-wrap" data-view="%s" data-client-id="%s"></div>',
			esc_attr( $view ),
			esc_attr( $atts['client_id'] )
		);
	}

	/**
	 * Enqueue assets and localize bootstrap data.
	 */
	public static function enqueue_runtime() {
		wp_enqueue_script( 'smartrecur-app' );
		wp_enqueue_style( 'smartrecur-app' );

		$user      = wp_get_current_user();
		$user_caps = array(
			'manage'         => current_user_can( 'smartrecur_manage' ),
			'book'           => current_user_can( 'smartrecur_book' ),
			'view'           => current_user_can( 'smartrecur_view' ),
			'manage_clients' => current_user_can( 'smartrecur_manage_clients' ),
		);

		$settings = (array) get_option( 'smartrecur_settings', array() );
		$timezone = $settings['businessHours']['timezone'] ?? 'Europe/Amsterdam';

		// Build a safe self-URL for login/logout redirects. Strip any control chars and
		// disallow protocol-relative or off-host paths.
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		$request_uri = wp_kses_bad_protocol( $request_uri, array( 'http', 'https' ) );
		$current_url = home_url( $request_uri ?: '/' );

		wp_localize_script(
			'smartrecur-app',
			'smartrecurData',
			array(
				'restUrl'    => esc_url_raw( rest_url( SMARTRECUR_REST_NAMESPACE . '/' ) ),
				'nonce'      => wp_create_nonce( 'wp_rest' ),
				'userId'     => $user ? (int) $user->ID : 0,
				'userName'   => $user ? $user->display_name : '',
				'userCaps'   => $user_caps,
				'timezone'   => $timezone,
				'locale'     => get_locale(),
				'loginUrl'   => esc_url_raw( wp_login_url( $current_url ) ),
				'logoutUrl'  => esc_url_raw( wp_logout_url( $current_url ) ),
				'siteUrl'    => esc_url_raw( site_url() ),
				'pluginUrl'  => esc_url_raw( SMARTRECUR_PLUGIN_URL ),
				'version'    => SMARTRECUR_VERSION,
			)
		);

		// Mark this page as private so LiteSpeed / page caches don't store it.
		nocache_headers();
	}
}

<?php
/**
 * PSR-style autoloader for SmartRecur classes.
 *
 * Maps `SmartRecur_Foo_Bar` → `includes/{controllers|integrations|helpers|traits|migration}/class-smartrecur-foo-bar.php`.
 *
 * @package SmartRecur
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SmartRecur_Autoloader {

	/**
	 * Register the autoloader with SPL.
	 */
	public static function register() {
		spl_autoload_register( array( __CLASS__, 'load_class' ) );
	}

	/**
	 * Load a class file based on its name.
	 *
	 * @param string $class_name Class name being instantiated.
	 */
	public static function load_class( $class_name ) {
		if ( strpos( $class_name, 'SmartRecur' ) !== 0 ) {
			return;
		}

		$slug = strtolower( str_replace( '_', '-', $class_name ) );
		// Look for both `class-` (default) and `trait-` (traits/) file prefixes.
		$files = array( 'class-' . $slug . '.php', 'trait-' . $slug . '.php' );

		$dirs = array(
			'includes/',
			'includes/controllers/',
			'includes/integrations/',
			'includes/helpers/',
			'includes/traits/',
			'includes/migration/',
			'includes/admin/',
		);

		foreach ( $dirs as $dir ) {
			foreach ( $files as $file ) {
				$path = SMARTRECUR_PLUGIN_DIR . $dir . $file;
				if ( file_exists( $path ) ) {
					require_once $path;
					return;
				}
			}
		}
	}
}

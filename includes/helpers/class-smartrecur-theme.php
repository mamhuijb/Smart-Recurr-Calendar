<?php
/**
 * Appearance / theming helper.
 *
 * Stores a small palette in the `appearance` key of smartrecur_settings and
 * turns it into a CSS-custom-property override block injected after the main
 * stylesheet. Lets the site owner recolour the calendar UI and the reminder
 * emails without touching code.
 *
 * @package SmartRecur
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SmartRecur_Theme {

	/**
	 * Built-in dark palette — also the default values for the settings panel.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'mode'       => 'dark',
			'primary'    => '#6366f1',
			'primary600' => '#4f46e5',
			'bg'         => '#0a0e1a',
			'surface'    => '#111726',
			'border'     => '#232c44',
			'text'       => '#e7ecf3',
			'emailHeader' => '#4f46e5',
			'emailAccent' => '#6366f1',
		);
	}

	/**
	 * Current palette (defaults merged with the saved appearance settings).
	 *
	 * @return array
	 */
	public static function palette() {
		$settings = (array) get_option( 'smartrecur_settings', array() );
		$saved    = isset( $settings['appearance'] ) && is_array( $settings['appearance'] ) ? $settings['appearance'] : array();
		return array_merge( self::defaults(), $saved );
	}

	/**
	 * Persist the appearance section of the settings form.
	 *
	 * @param array $post Unslashed POST data.
	 */
	public static function save_form( array $post ) {
		$settings = (array) get_option( 'smartrecur_settings', array() );
		$hex      = static function ( $key, $fallback ) use ( $post ) {
			$value = isset( $post[ $key ] ) ? sanitize_hex_color( $post[ $key ] ) : '';
			return $value ? $value : $fallback;
		};
		$d = self::defaults();

		$settings['appearance'] = array(
			'mode'        => ( isset( $post['appearance_mode'] ) && 'light' === $post['appearance_mode'] ) ? 'light' : 'dark',
			'primary'     => $hex( 'appearance_primary', $d['primary'] ),
			'primary600'  => $hex( 'appearance_primary600', $d['primary600'] ),
			'bg'          => $hex( 'appearance_bg', $d['bg'] ),
			'surface'     => $hex( 'appearance_surface', $d['surface'] ),
			'border'      => $hex( 'appearance_border', $d['border'] ),
			'text'        => $hex( 'appearance_text', $d['text'] ),
			'emailHeader' => $hex( 'appearance_email_header', $d['emailHeader'] ),
			'emailAccent' => $hex( 'appearance_email_accent', $d['emailAccent'] ),
		);
		update_option( 'smartrecur_settings', $settings );
	}

	/**
	 * CSS custom-property override block for the admin + front-end UI.
	 *
	 * @return string
	 */
	public static function inline_css() {
		$p = self::palette();
		$lines = array(
			'--sr-primary: '     . $p['primary'] . ';',
			'--sr-primary-600: ' . $p['primary600'] . ';',
		);
		if ( 'light' !== $p['mode'] ) {
			$lines[] = '--sr-bg: '      . $p['bg'] . ';';
			$lines[] = '--sr-surface: ' . $p['surface'] . ';';
			$lines[] = '--sr-border: '  . $p['border'] . ';';
			$lines[] = '--sr-text: '    . $p['text'] . ';';
		}
		$block = '.smartrecur-admin{' . implode( '', $lines ) . '}';

		// Also recolour the darkened wp-admin content column.
		if ( 'light' !== $p['mode'] ) {
			$block .= 'body[class*="page_smartrecur"] #wpcontent,'
				. 'body[class*="page_smartrecur"] #wpbody-content,'
				. 'body.toplevel_page_smartrecur #wpcontent,'
				. 'body.toplevel_page_smartrecur #wpbody-content{background:' . $p['bg'] . ';}';
		}
		return $block;
	}
}

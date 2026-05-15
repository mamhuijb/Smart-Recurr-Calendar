<?php
/**
 * UUID v4 generator. Uses random_bytes; PHP 8.4 has random_bytes built in.
 *
 * @package SmartRecur
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SmartRecur_UUID {

	/**
	 * Generate a UUID v4 string.
	 *
	 * @return string
	 */
	public static function v4() {
		$data    = random_bytes( 16 );
		$data[6] = chr( ord( $data[6] ) & 0x0f | 0x40 );
		$data[8] = chr( ord( $data[8] ) & 0x3f | 0x80 );
		return vsprintf( '%s%s-%s-%s-%s-%s%s%s', str_split( bin2hex( $data ), 4 ) );
	}
}

<?php
/**
 * Simple transient-backed sliding-window rate limiter.
 *
 * @package SmartRecur
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SmartRecur_Rate_Limiter {

	/**
	 * Check whether a key has exceeded `$limit` hits within `$window` seconds.
	 * Atomically increments the counter when called.
	 *
	 * @param string $key    Identifier (already namespaced — e.g. "book:42:1.2.3.4").
	 * @param int    $limit  Max allowed attempts.
	 * @param int    $window Window in seconds.
	 * @return bool True if request is allowed, false if blocked.
	 */
	public static function check( $key, $limit, $window ) {
		$transient = 'smartrecur_rl_' . md5( $key );
		$count     = (int) get_transient( $transient );

		if ( $count >= $limit ) {
			return false;
		}

		set_transient( $transient, $count + 1, $window );
		return true;
	}

	/**
	 * Identify the current actor for rate limiting — user ID if logged in, else hashed IP.
	 *
	 * @return string
	 */
	public static function actor() {
		$user_id = get_current_user_id();
		if ( $user_id ) {
			return 'u:' . $user_id;
		}
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : '';
		return 'ip:' . hash( 'sha256', $ip );
	}
}

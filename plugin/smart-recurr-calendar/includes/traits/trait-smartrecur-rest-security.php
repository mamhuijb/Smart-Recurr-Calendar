<?php
/**
 * Shared REST helpers: capability checks, cache headers, common error responses.
 *
 * @package SmartRecur
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait SmartRecur_REST_Security {

	/**
	 * Permission callback: user must be logged in and hold the given capability.
	 *
	 * @param string $cap Capability slug.
	 * @return callable
	 */
	protected function require_cap( $cap ) {
		return function ( $request ) use ( $cap ) {
			if ( ! is_user_logged_in() ) {
				return new WP_Error( 'smartrecur_unauthenticated', __( 'Authentication required.', 'smartrecur' ), array( 'status' => 401 ) );
			}
			if ( ! current_user_can( $cap ) ) {
				return new WP_Error( 'smartrecur_forbidden', __( 'You are not allowed to do that.', 'smartrecur' ), array( 'status' => 403 ) );
			}
			return true;
		};
	}

	/**
	 * Emit Cache-Control headers that mark user-specific REST responses as private,
	 * preventing LiteSpeed / Varnish / browser shared caches from storing them.
	 *
	 * Optionally set the HTTP status code. WP_REST_Response::set_status() returns
	 * void, so wrapping it here lets controllers keep using a fluent return.
	 *
	 * @param WP_REST_Response $response Response.
	 * @param int|null         $status   Optional HTTP status to apply.
	 * @return WP_REST_Response
	 */
	protected function with_no_store( WP_REST_Response $response, $status = null ) {
		$response->header( 'Cache-Control', 'no-store, private, max-age=0' );
		$response->header( 'X-LiteSpeed-Cache-Control', 'no-cache, no-vary' );
		if ( null !== $status ) {
			$response->set_status( (int) $status );
		}
		return $response;
	}

	/**
	 * Trigger LiteSpeed / page-cache purges after data changes.
	 */
	protected function purge_caches() {
		do_action( 'litespeed_purge_posttype', 'page' );
		do_action( 'litespeed_purge_posttype', 'post' );
	}

	/**
	 * Validate that the supplied UUID is well-formed.
	 *
	 * @param mixed $value Candidate.
	 * @return bool
	 */
	public static function validate_uuid( $value ) {
		return is_string( $value ) && (bool) preg_match( '/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/', $value );
	}

	/**
	 * Sanitize and decode a JSON-encoded field (sent as a PHP array from the client).
	 *
	 * @param mixed $value Field value.
	 * @return array
	 */
	public static function sanitize_array( $value ) {
		if ( is_string( $value ) ) {
			$decoded = json_decode( $value, true );
			return is_array( $decoded ) ? $decoded : array();
		}
		return is_array( $value ) ? $value : array();
	}
}

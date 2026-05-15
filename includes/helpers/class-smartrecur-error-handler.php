<?php
/**
 * REST error catcher. Wraps every callback in our namespace so an uncaught
 * Throwable or DB error returns a clean JSON WP_Error instead of the bare
 * "There has been a critical error on this website" HTML page that breaks
 * the React client's error parser.
 *
 * @package SmartRecur
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SmartRecur_Error_Handler {

	/**
	 * Register the hooks.
	 */
	public static function register() {
		add_filter( 'rest_dispatch_request', array( __CLASS__, 'wrap_dispatch' ), 10, 4 );
		add_filter( 'rest_request_after_callbacks', array( __CLASS__, 'inspect_response' ), 10, 3 );
		register_shutdown_function( array( __CLASS__, 'shutdown_safety_net' ) );
	}

	/**
	 * Wrap the REST callback with a try/catch so PHP Throwables become
	 * structured WP_Error responses for SmartRecur routes.
	 *
	 * @param mixed           $dispatch_result Default null (continue).
	 * @param WP_REST_Request $request         Incoming request.
	 * @param string          $route           Matched route.
	 * @param array           $handler         Route handler.
	 * @return mixed
	 */
	public static function wrap_dispatch( $dispatch_result, $request, $route, $handler ) {
		if ( strpos( $route, '/' . SMARTRECUR_REST_NAMESPACE ) !== 0 ) {
			return $dispatch_result;
		}
		if ( null !== $dispatch_result ) {
			return $dispatch_result;
		}
		if ( empty( $handler['callback'] ) || ! is_callable( $handler['callback'] ) ) {
			return $dispatch_result;
		}

		try {
			$response = call_user_func( $handler['callback'], $request );
		} catch ( Throwable $e ) {
			error_log(
				sprintf(
					'[SmartRecur] uncaught %s on %s: %s in %s:%d',
					get_class( $e ),
					$route,
					$e->getMessage(),
					$e->getFile(),
					$e->getLine()
				)
			);
			return new WP_Error(
				'smartrecur_internal',
				sprintf(
					/* translators: %s: error message */
					__( 'SmartRecur internal error: %s', 'smartrecur' ),
					$e->getMessage()
				),
				array( 'status' => 500 )
			);
		}

		// Surface a wpdb error if the callback didn't notice.
		global $wpdb;
		if ( ! empty( $wpdb->last_error ) ) {
			$msg = $wpdb->last_error;
			$wpdb->last_error = '';
			error_log( "[SmartRecur] wpdb error on {$route}: {$msg}" );
		}

		return $response;
	}

	/**
	 * Replace null/empty responses with a clear error.
	 *
	 * @param mixed           $response Response.
	 * @param array           $handler  Route handler.
	 * @param WP_REST_Request $request  Request.
	 * @return mixed
	 */
	public static function inspect_response( $response, $handler, $request ) {
		$route = $request->get_route();
		if ( strpos( $route, '/' . SMARTRECUR_REST_NAMESPACE ) !== 0 ) {
			return $response;
		}
		if ( null === $response ) {
			return new WP_Error( 'smartrecur_empty_response', __( 'SmartRecur returned no response. Check server error logs.', 'smartrecur' ), array( 'status' => 500 ) );
		}
		return $response;
	}

	/**
	 * Last-resort: if PHP fatals during a SmartRecur REST request, emit a JSON
	 * body so the React client sees a useful error instead of a HTML blob.
	 */
	public static function shutdown_safety_net() {
		$error = error_get_last();
		if ( ! $error ) {
			return;
		}
		$fatal_types = E_ERROR | E_PARSE | E_COMPILE_ERROR | E_CORE_ERROR | E_USER_ERROR | E_RECOVERABLE_ERROR;
		if ( ! ( (int) $error['type'] & $fatal_types ) ) {
			return;
		}
		if ( ! defined( 'REST_REQUEST' ) || ! REST_REQUEST ) {
			return;
		}
		$uri = $_SERVER['REQUEST_URI'] ?? '';
		if ( false === strpos( $uri, SMARTRECUR_REST_NAMESPACE ) ) {
			return;
		}

		error_log( '[SmartRecur] shutdown fatal: ' . $error['message'] . ' in ' . $error['file'] . ':' . $error['line'] );

		if ( ! headers_sent() ) {
			header( 'Content-Type: application/json; charset=UTF-8' );
			http_response_code( 500 );
		}
		echo wp_json_encode(
			array(
				'code'    => 'smartrecur_fatal',
				'message' => 'SmartRecur fatal: ' . $error['message'],
				'data'    => array( 'status' => 500 ),
			)
		);
	}
}

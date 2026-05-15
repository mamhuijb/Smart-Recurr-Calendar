<?php
/**
 * Read/write integration configs with at-rest encryption.
 *
 * @package SmartRecur
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SmartRecur_Integration_Config {

	/**
	 * Get the integration_configs table name.
	 *
	 * @return string
	 */
	private static function table() {
		global $wpdb;
		return $wpdb->prefix . 'smartrecur_integration_configs';
	}

	/**
	 * Load and decrypt the config for a given integration.
	 *
	 * @param string $id Integration slug.
	 * @return array
	 */
	public static function get( $id ) {
		global $wpdb;
		$table = self::table();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT config, is_connected, last_checked FROM {$table} WHERE id = %s", $id ), ARRAY_A );
		if ( ! $row ) {
			return array();
		}
		$decrypted = SmartRecur_Encryption::decrypt( $row['config'] );
		$decoded   = json_decode( $decrypted, true );
		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * Persist a config (encrypted), upserting the row if needed.
	 *
	 * @param string $id     Integration slug.
	 * @param array  $config Plain config array.
	 */
	public static function put( $id, array $config ) {
		global $wpdb;
		$table   = self::table();
		$payload = SmartRecur_Encryption::encrypt( wp_json_encode( $config ) );

		$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE id = %s", $id ) );
		if ( $exists ) {
			$wpdb->update( $table, array( 'config' => $payload ), array( 'id' => $id ), array( '%s' ), array( '%s' ) );
		} else {
			$wpdb->insert( $table, array( 'id' => $id, 'config' => $payload, 'is_connected' => 0 ), array( '%s', '%s', '%d' ) );
		}
	}

	/**
	 * Update only the connection status fields (does not touch the config blob).
	 *
	 * @param string $id        Integration slug.
	 * @param bool   $connected Status.
	 */
	public static function set_status( $id, $connected ) {
		global $wpdb;
		$table = self::table();
		$wpdb->update(
			$table,
			array(
				'is_connected' => $connected ? 1 : 0,
				'last_checked' => current_time( 'mysql' ),
			),
			array( 'id' => $id ),
			array( '%d', '%s' ),
			array( '%s' )
		);
	}

	/**
	 * Return all integration rows, with config decrypted.
	 *
	 * @return array
	 */
	public static function all() {
		global $wpdb;
		$table = self::table();
		$rows  = $wpdb->get_results( "SELECT id, config, is_connected, last_checked FROM {$table}", ARRAY_A );
		$out   = array();
		foreach ( (array) $rows as $row ) {
			$decoded     = json_decode( SmartRecur_Encryption::decrypt( $row['config'] ), true );
			$out[ $row['id'] ] = array(
				'isConnected' => (bool) $row['is_connected'],
				'lastChecked' => $row['last_checked'],
				'config'      => is_array( $decoded ) ? $decoded : array(),
			);
		}
		return $out;
	}

	/**
	 * Return a sanitised version of the config for REST responses.
	 *
	 * - Mask known secret fields with the placeholder string.
	 * - Drop access/refresh tokens entirely.
	 * - Drop every underscore-prefixed key (treated as internal state).
	 *
	 * @param array $config Raw config.
	 * @return array
	 */
	public static function public_view( array $config ) {
		$secret_keys = array( 'clientSecret', 'apiKey', 'apiSecret', 'password' );
		foreach ( $secret_keys as $key ) {
			if ( isset( $config[ $key ] ) && '' !== $config[ $key ] ) {
				$config[ $key ] = '••••••••';
			}
		}
		unset( $config['accessToken'], $config['refreshToken'], $config['expiresAt'] );
		foreach ( array_keys( $config ) as $key ) {
			if ( is_string( $key ) && '' !== $key && '_' === $key[0] ) {
				unset( $config[ $key ] );
			}
		}
		return $config;
	}
}

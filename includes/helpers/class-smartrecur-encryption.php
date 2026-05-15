<?php
/**
 * At-rest encryption for integration secrets.
 *
 * Uses sodium_crypto_secretbox when available, falling back to openssl_encrypt
 * with AES-256-CBC + HMAC. Both branches derive a key from AUTH_SALT (or wp_salt()
 * when the constant is unset), so secrets are useless if exfiltrated without the
 * site's salts.
 *
 * Encrypted values are prefixed with "sr1:" so plaintext values written before
 * the plugin upgrade can still be read transparently.
 *
 * @package SmartRecur
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SmartRecur_Encryption {

	const PREFIX = 'sr1:';

	/**
	 * Derive a 32-byte key from WordPress salts.
	 *
	 * @return string
	 */
	private static function key() {
		$material = defined( 'AUTH_SALT' ) && AUTH_SALT ? AUTH_SALT : wp_salt( 'auth' );
		return hash( 'sha256', 'smartrecur|' . $material, true );
	}

	/**
	 * Encrypt a string. Returns the original value unchanged on failure.
	 *
	 * @param string $plaintext Plain value.
	 * @return string
	 */
	public static function encrypt( $plaintext ) {
		if ( ! is_string( $plaintext ) || '' === $plaintext ) {
			return $plaintext;
		}

		$key = self::key();

		if ( function_exists( 'sodium_crypto_secretbox' ) ) {
			$nonce      = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			$ciphertext = sodium_crypto_secretbox( $plaintext, $nonce, $key );
			return self::PREFIX . 'sodium:' . base64_encode( $nonce . $ciphertext );
		}

		$iv         = random_bytes( 16 );
		$ciphertext = openssl_encrypt( $plaintext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv );
		if ( false === $ciphertext ) {
			return $plaintext;
		}
		$mac = hash_hmac( 'sha256', $iv . $ciphertext, $key, true );
		return self::PREFIX . 'openssl:' . base64_encode( $iv . $mac . $ciphertext );
	}

	/**
	 * Decrypt a string previously produced by encrypt(). Returns the input as-is
	 * if no recognised prefix is present (allows legacy plaintext rows to keep working).
	 *
	 * @param string $value Ciphertext.
	 * @return string
	 */
	public static function decrypt( $value ) {
		if ( ! is_string( $value ) || strpos( $value, self::PREFIX ) !== 0 ) {
			return $value;
		}

		$body = substr( $value, strlen( self::PREFIX ) );
		$key  = self::key();

		if ( 0 === strpos( $body, 'sodium:' ) && function_exists( 'sodium_crypto_secretbox_open' ) ) {
			$raw = base64_decode( substr( $body, strlen( 'sodium:' ) ), true );
			if ( false === $raw || strlen( $raw ) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
				return '';
			}
			$nonce      = substr( $raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			$ciphertext = substr( $raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			$plain      = sodium_crypto_secretbox_open( $ciphertext, $nonce, $key );
			return false === $plain ? '' : $plain;
		}

		if ( 0 === strpos( $body, 'openssl:' ) ) {
			$raw = base64_decode( substr( $body, strlen( 'openssl:' ) ), true );
			if ( false === $raw || strlen( $raw ) < 48 ) {
				return '';
			}
			$iv         = substr( $raw, 0, 16 );
			$mac        = substr( $raw, 16, 32 );
			$ciphertext = substr( $raw, 48 );
			$check      = hash_hmac( 'sha256', $iv . $ciphertext, $key, true );
			if ( ! hash_equals( $mac, $check ) ) {
				return '';
			}
			$plain = openssl_decrypt( $ciphertext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv );
			return false === $plain ? '' : $plain;
		}

		return $value;
	}
}

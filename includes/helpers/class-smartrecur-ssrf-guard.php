<?php
/**
 * SSRF protection for outbound HTTP from integration code.
 *
 * @package SmartRecur
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SmartRecur_SSRF_Guard {

	/**
	 * Validate a URL is HTTPS and resolves to a public IP.
	 *
	 * @param string $url Candidate URL.
	 * @return bool
	 */
	public static function is_safe_url( $url ) {
		$parsed = wp_parse_url( $url );
		if ( ! $parsed || empty( $parsed['scheme'] ) || empty( $parsed['host'] ) ) {
			return false;
		}
		if ( 'https' !== $parsed['scheme'] ) {
			return false;
		}
		return self::is_public_host( $parsed['host'] );
	}

	/**
	 * Validate the host resolves to at least one public, routable IP address.
	 *
	 * @param string $host Hostname.
	 * @return bool
	 */
	public static function is_public_host( $host ) {
		$records = @dns_get_record( $host, DNS_A | DNS_AAAA );
		if ( empty( $records ) ) {
			$resolved = @gethostbyname( $host );
			if ( $resolved === $host ) {
				return false;
			}
			return self::is_public_ip( $resolved );
		}

		foreach ( $records as $record ) {
			$ip = $record['ip'] ?? ( $record['ipv6'] ?? '' );
			if ( $ip && ! self::is_public_ip( $ip ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Check whether an IP is publicly routable (rejects private + reserved ranges).
	 *
	 * @param string $ip IP address.
	 * @return bool
	 */
	public static function is_public_ip( $ip ) {
		return false !== filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE );
	}

	/**
	 * Validate a Syncro subdomain (alphanumeric + single hyphens only).
	 *
	 * @param string $subdomain Subdomain.
	 * @return bool
	 */
	public static function is_valid_subdomain( $subdomain ) {
		return (bool) preg_match( '/^[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?$/', (string) $subdomain );
	}

	/**
	 * Validate a Zoho API endpoint (must be on the zohoapis.com regional family).
	 *
	 * @param string $endpoint URL.
	 * @return bool
	 */
	public static function is_valid_zoho_endpoint( $endpoint ) {
		$parsed = wp_parse_url( $endpoint );
		if ( ! $parsed || empty( $parsed['scheme'] ) || empty( $parsed['host'] ) ) {
			return false;
		}
		if ( 'https' !== $parsed['scheme'] ) {
			return false;
		}
		return (bool) preg_match( '/^(www\.)?zohoapis\.(com|eu|in|com\.au|jp|com\.cn)$/', $parsed['host'] );
	}
}

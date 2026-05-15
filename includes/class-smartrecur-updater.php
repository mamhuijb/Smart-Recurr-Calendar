<?php
/**
 * Self-updater backed by GitHub Releases.
 *
 * Hooks into the standard WordPress plugin-update flow so users see updates
 * in Plugins screen → Updates available, identical UX to plugins from the WP
 * repository. No external libraries.
 *
 * Release workflow:
 *   1. Tag a commit (e.g. `2026.05.2` for a feature release, `2026.05.1.1`
 *      for a patch on top of 2026.05.1).
 *   2. Create a GitHub Release for that tag.
 *   3. Attach `smart-recurr-calendar-VERSION.zip` (or any zip whose name
 *      contains "smart-recurr-calendar") as a release asset.
 *
 * Sites running an older tag will see the update appear within 12 hours
 * (transient cache). Click "Update Now" → WordPress downloads the asset
 * and replaces the plugin in place.
 *
 * @package SmartRecur
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SmartRecur_Updater {

	const GITHUB_OWNER     = 'mamhuijb';
	const GITHUB_REPO      = 'Smart-Recurr-Calendar';
	const TRANSIENT_KEY    = 'smartrecur_latest_release';
	const TRANSIENT_TTL    = 12 * HOUR_IN_SECONDS;
	const FORCE_REFRESH_GET = 'smartrecur_force_update_check';

	/**
	 * Register all hooks.
	 */
	public static function register() {
		// Inject our update info into the standard plugin-update transient.
		add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'inject_update' ) );

		// Serve "View details" popup data for the plugins screen.
		add_filter( 'plugins_api', array( __CLASS__, 'plugins_api' ), 10, 3 );

		// Rename the extracted folder back to `smart-recurr-calendar` after
		// downloading from GitHub (GitHub-style zips often include a hash suffix).
		add_filter( 'upgrader_source_selection', array( __CLASS__, 'fix_folder_name' ), 10, 4 );

		// Manual "force check" link via ?smartrecur_force_update_check=1 in the admin.
		add_action( 'admin_init', array( __CLASS__, 'maybe_force_refresh' ) );
	}

	/**
	 * Plugin slug used by WP to identify the plugin in update payloads.
	 * Matches the plugin's basename: `smart-recurr-calendar/smart-recurr-calendar.php`.
	 *
	 * @return string
	 */
	public static function plugin_basename() {
		return defined( 'SMARTRECUR_PLUGIN_BASENAME' ) ? SMARTRECUR_PLUGIN_BASENAME : 'smart-recurr-calendar/smart-recurr-calendar.php';
	}

	/**
	 * Plugin slug for plugins_api responses (folder name).
	 *
	 * @return string
	 */
	public static function plugin_slug() {
		return dirname( self::plugin_basename() );
	}

	/**
	 * Fetch the latest release from GitHub, caching the result for TRANSIENT_TTL.
	 *
	 * @param bool $force Bypass the cache.
	 * @return array|null Normalised release info, or null on failure.
	 */
	public static function get_latest_release( $force = false ) {
		if ( ! $force ) {
			$cached = get_transient( self::TRANSIENT_KEY );
			if ( is_array( $cached ) ) {
				return $cached;
			}
		}

		$url = sprintf( 'https://api.github.com/repos/%s/%s/releases/latest', self::GITHUB_OWNER, self::GITHUB_REPO );

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 10,
				'headers' => array(
					'Accept'     => 'application/vnd.github+json',
					'User-Agent' => 'SmartRecur-Updater/' . SMARTRECUR_VERSION,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			set_transient( self::TRANSIENT_KEY, array( 'error' => $response->get_error_message() ), HOUR_IN_SECONDS );
			return null;
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			set_transient( self::TRANSIENT_KEY, array( 'error' => 'HTTP ' . $code ), HOUR_IN_SECONDS );
			return null;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) || empty( $body['tag_name'] ) ) {
			set_transient( self::TRANSIENT_KEY, array( 'error' => 'Malformed release payload' ), HOUR_IN_SECONDS );
			return null;
		}

		$normalised = self::normalise_release( $body );
		set_transient( self::TRANSIENT_KEY, $normalised, self::TRANSIENT_TTL );
		return $normalised;
	}

	/**
	 * Strip leading `v`, locate the right release asset, build a normalised array.
	 *
	 * @param array $body GitHub release object.
	 * @return array
	 */
	private static function normalise_release( array $body ) {
		$tag     = preg_replace( '/^v/i', '', (string) $body['tag_name'] );
		$assets  = isset( $body['assets'] ) && is_array( $body['assets'] ) ? $body['assets'] : array();
		$zip_url = '';

		// Prefer an asset whose name contains the plugin slug and ends in .zip.
		foreach ( $assets as $asset ) {
			$name = strtolower( $asset['name'] ?? '' );
			if ( '' === $name ) {
				continue;
			}
			if ( str_contains( $name, 'smart-recurr-calendar' ) && str_ends_with( $name, '.zip' ) ) {
				$zip_url = $asset['browser_download_url'] ?? '';
				break;
			}
		}

		// Fall back to any zip asset.
		if ( '' === $zip_url ) {
			foreach ( $assets as $asset ) {
				$name = strtolower( $asset['name'] ?? '' );
				if ( str_ends_with( $name, '.zip' ) ) {
					$zip_url = $asset['browser_download_url'] ?? '';
					break;
				}
			}
		}

		return array(
			'version'       => $tag,
			'zip_url'       => $zip_url,
			'html_url'      => $body['html_url'] ?? '',
			'body'          => $body['body'] ?? '',
			'published_at'  => $body['published_at'] ?? '',
			'tag_name'      => $body['tag_name'] ?? '',
			'name'          => $body['name'] ?? $tag,
		);
	}

	/**
	 * Inject an update entry into the WP plugins update transient when a newer
	 * release exists on GitHub.
	 *
	 * @param mixed $transient WP plugins update transient.
	 * @return mixed
	 */
	public static function inject_update( $transient ) {
		if ( ! is_object( $transient ) ) {
			return $transient;
		}

		$release = self::get_latest_release();
		if ( ! $release || empty( $release['version'] ) || empty( $release['zip_url'] ) ) {
			return $transient;
		}

		$current = SMARTRECUR_VERSION;
		if ( version_compare( $release['version'], $current, '<=' ) ) {
			// Up to date — clear any stale "update available" entry.
			if ( isset( $transient->response[ self::plugin_basename() ] ) ) {
				unset( $transient->response[ self::plugin_basename() ] );
			}
			$transient->no_update[ self::plugin_basename() ] = self::build_update_obj( $current, $release );
			return $transient;
		}

		$transient->response[ self::plugin_basename() ] = self::build_update_obj( $release['version'], $release );
		return $transient;
	}

	/**
	 * Build the std plugin update object that WP expects.
	 *
	 * @param string $version New version string.
	 * @param array  $release Normalised release info.
	 * @return object
	 */
	private static function build_update_obj( $version, array $release ) {
		return (object) array(
			'id'            => 'smartrecur/' . self::plugin_slug(),
			'slug'          => self::plugin_slug(),
			'plugin'        => self::plugin_basename(),
			'new_version'   => $version,
			'url'           => $release['html_url'],
			'package'       => $release['zip_url'],
			'icons'         => array(),
			'banners'       => array(),
			'banners_rtl'   => array(),
			'tested'        => '6.6',
			'requires_php'  => '8.4',
			'compatibility' => new stdClass(),
		);
	}

	/**
	 * Provide details for the "View details" popup on the Plugins screen.
	 *
	 * @param false|object|array $result Default false.
	 * @param string             $action API action.
	 * @param object             $args   Request args.
	 * @return false|object
	 */
	public static function plugins_api( $result, $action, $args ) {
		if ( 'plugin_information' !== $action ) {
			return $result;
		}
		if ( ! isset( $args->slug ) || $args->slug !== self::plugin_slug() ) {
			return $result;
		}

		$release = self::get_latest_release();
		if ( ! $release ) {
			return $result;
		}

		return (object) array(
			'name'           => 'SmartRecur Calendar',
			'slug'           => self::plugin_slug(),
			'version'        => $release['version'],
			'author'         => '<a href="https://github.com/' . esc_attr( self::GITHUB_OWNER ) . '">SmartRecur</a>',
			'homepage'       => 'https://github.com/' . self::GITHUB_OWNER . '/' . self::GITHUB_REPO,
			'requires'       => '6.0',
			'tested'         => '6.6',
			'requires_php'   => '8.4',
			'last_updated'   => $release['published_at'],
			'download_link'  => $release['zip_url'],
			'sections'       => array(
				'description' => '<p>' . esc_html__( 'Recurring appointment scheduler for MSPs with Office 365, Syncro, Invoice Ninja and Zoho integrations.', 'smartrecur' ) . '</p>',
				'changelog'   => self::format_changelog( $release ),
			),
		);
	}

	/**
	 * Convert markdown-ish release notes to a safe HTML snippet.
	 *
	 * @param array $release Normalised release.
	 * @return string
	 */
	private static function format_changelog( array $release ) {
		$body = $release['body'];
		// Very small subset of markdown → HTML: newlines + headings + bullets.
		$body = wp_kses_post( $body );
		$body = preg_replace( '/^### (.+)$/m', '<h4>$1</h4>', $body );
		$body = preg_replace( '/^## (.+)$/m', '<h3>$1</h3>', $body );
		$body = preg_replace( '/^# (.+)$/m', '<h2>$1</h2>', $body );
		$body = preg_replace( '/^[\*\-] (.+)$/m', '<li>$1</li>', $body );
		$body = preg_replace( '/(<li>.*<\/li>\s*)+/s', '<ul>$0</ul>', $body );
		$body = nl2br( $body );

		$out  = '<h4>' . esc_html( $release['name'] ?: $release['version'] );
		if ( $release['published_at'] ) {
			$out .= ' <small>(' . esc_html( date_i18n( 'Y-m-d', strtotime( $release['published_at'] ) ) ) . ')</small>';
		}
		$out .= '</h4>';
		$out .= $body;
		$out .= '<p><a href="' . esc_url( $release['html_url'] ) . '" target="_blank" rel="noopener">'
			. esc_html__( 'View this release on GitHub', 'smartrecur' )
			. '</a></p>';
		return $out;
	}

	/**
	 * After WP downloads and extracts the GitHub asset, the extracted folder
	 * name may not match the installed plugin folder. Rename it to ensure the
	 * plugin upgrade replaces the existing folder cleanly.
	 *
	 * @param string       $source        Extracted folder path.
	 * @param string       $remote_source Remote source path.
	 * @param WP_Upgrader  $upgrader      Upgrader instance.
	 * @param array        $hook_extra    Extra info about the upgrade.
	 * @return string|WP_Error
	 */
	public static function fix_folder_name( $source, $remote_source, $upgrader, $hook_extra = array() ) {
		if ( empty( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== self::plugin_basename() ) {
			return $source;
		}

		// `$source` is the extracted directory. We want the leaf folder to be
		// our plugin slug, regardless of what GitHub named the zip's root.
		$desired_leaf = self::plugin_slug();
		$current_leaf = basename( untrailingslashit( $source ) );

		if ( $current_leaf === $desired_leaf ) {
			return $source;
		}

		$new_source = trailingslashit( dirname( $source ) ) . $desired_leaf;

		global $wp_filesystem;
		if ( ! $wp_filesystem ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		if ( $wp_filesystem && $wp_filesystem->move( $source, $new_source, true ) ) {
			return trailingslashit( $new_source );
		}

		return new WP_Error( 'smartrecur_rename_failed', __( 'SmartRecur updater: failed to rename extracted folder.', 'smartrecur' ) );
	}

	/**
	 * Allow the admin to bust the cache by visiting any admin page with
	 * `?smartrecur_force_update_check=1`. Drops the transient and re-fetches.
	 */
	public static function maybe_force_refresh() {
		if ( empty( $_GET[ self::FORCE_REFRESH_GET ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		if ( ! current_user_can( 'update_plugins' ) ) {
			return;
		}
		delete_transient( self::TRANSIENT_KEY );
		delete_site_transient( 'update_plugins' );
		self::get_latest_release( true );
		wp_safe_redirect( remove_query_arg( self::FORCE_REFRESH_GET ) );
		exit;
	}
}

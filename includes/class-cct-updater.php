<?php
/**
 * Self-updater from GitHub Releases.
 *
 * How it works
 * - Every release on GitHub (tag vX.Y.Z) has an asset casinos-cc-tools.zip, built by
 *   .github/workflows/release.yml.
 * - WordPress' normal update check asks GitHub for the latest release. If its version is
 *   higher than CCT_VERSION, the plugin shows "Update available" on the Plugins screen and can
 *   be updated with one click, or automatically if auto-updates are switched on.
 * - "Check for updates" (Plugins screen row link and Casino Tools → Settings) forces a fresh check.
 * - Private repositories: paste a fine-grained GitHub token with read-only "Contents" access in
 *   Casino Tools → Settings, or define CCT_GITHUB_TOKEN in wp-config.php.
 */

defined( 'ABSPATH' ) || exit;

class CCT_Updater {

	const CACHE_KEY   = 'cct_github_release';
	const CACHE_TTL   = 6 * HOUR_IN_SECONDS;
	const ASSET_NAME  = 'casinos-cc-tools.zip';
	const PACKAGE_TAG = 'cct-github-asset://';

	public static function init() {
		add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'inject_update' ) );
		add_filter( 'plugins_api', array( __CLASS__, 'plugin_info' ), 20, 3 );
		add_filter( 'upgrader_pre_download', array( __CLASS__, 'download_package' ), 10, 4 );
		add_filter( 'upgrader_source_selection', array( __CLASS__, 'fix_folder_name' ), 10, 4 );
		add_filter( 'plugin_row_meta', array( __CLASS__, 'row_links' ), 10, 2 );
		add_action( 'admin_post_cct_check_updates', array( __CLASS__, 'handle_check_now' ) );
		add_action( 'admin_notices', array( __CLASS__, 'check_notice' ) );
		add_action( 'upgrader_process_complete', function () {
			delete_site_transient( self::CACHE_KEY );
		} );
	}

	public static function basename() {
		return plugin_basename( CCT_FILE );
	}

	public static function token() {
		if ( defined( 'CCT_GITHUB_TOKEN' ) && CCT_GITHUB_TOKEN ) {
			return CCT_GITHUB_TOKEN;
		}
		$settings = get_option( 'cct_settings', array() );
		return isset( $settings['github_token'] ) ? trim( $settings['github_token'] ) : '';
	}

	private static function api_headers( $accept = 'application/vnd.github+json' ) {
		$headers = array(
			'Accept'               => $accept,
			'User-Agent'           => 'casinos-cc-tools/' . CCT_VERSION . ' (WordPress)',
			'X-GitHub-Api-Version' => '2022-11-28',
		);
		if ( self::token() ) {
			$headers['Authorization'] = 'Bearer ' . self::token();
		}
		return $headers;
	}

	/**
	 * Latest release info, cached.
	 *
	 * @return array|WP_Error { version, tag, notes, published, url, asset_api, asset_public, zipball }
	 */
	public static function get_release( $force = false ) {
		if ( ! $force ) {
			$cached = get_site_transient( self::CACHE_KEY );
			if ( is_array( $cached ) || is_wp_error( $cached ) ) {
				return $cached;
			}
		}

		$res = wp_remote_get( 'https://api.github.com/repos/' . CCT_GITHUB_REPO . '/releases/latest', array(
			'timeout' => 15,
			'headers' => self::api_headers(),
		) );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$code = wp_remote_retrieve_response_code( $res );
		if ( 200 !== $code ) {
			if ( 404 === $code ) {
				$msg = __( 'No release found. Publish a release on GitHub, or for a private repository add a GitHub token in Casino Tools → Settings.', 'casinos-cc-tools' );
			} elseif ( 401 === $code || 403 === $code ) {
				$msg = sprintf( __( 'GitHub refused the request (HTTP %d). Check the GitHub token, or wait an hour if the API rate limit was reached.', 'casinos-cc-tools' ), $code );
			} else {
				$msg = sprintf( __( 'GitHub answered with HTTP %d.', 'casinos-cc-tools' ), $code );
			}
			// Cache failures briefly so a broken token doesn't slow every admin page.
			set_site_transient( self::CACHE_KEY, new WP_Error( 'cct_github', $msg ), 15 * MINUTE_IN_SECONDS );
			return new WP_Error( 'cct_github', $msg );
		}

		$body    = json_decode( wp_remote_retrieve_body( $res ), true );
		$release = array(
			'version'      => ltrim( (string) ( $body['tag_name'] ?? '' ), 'vV' ),
			'tag'          => (string) ( $body['tag_name'] ?? '' ),
			'notes'        => (string) ( $body['body'] ?? '' ),
			'published'    => (string) ( $body['published_at'] ?? '' ),
			'url'          => (string) ( $body['html_url'] ?? '' ),
			'asset_api'    => '',
			'asset_public' => '',
			'zipball'      => (string) ( $body['zipball_url'] ?? '' ),
		);
		foreach ( (array) ( $body['assets'] ?? array() ) as $asset ) {
			if ( self::ASSET_NAME === ( $asset['name'] ?? '' ) ) {
				$release['asset_api']    = (string) $asset['url'];
				$release['asset_public'] = (string) $asset['browser_download_url'];
			}
		}

		set_site_transient( self::CACHE_KEY, $release, self::CACHE_TTL );
		return $release;
	}

	/** Add our plugin to WordPress' update list. */
	public static function inject_update( $transient ) {
		if ( ! is_object( $transient ) ) {
			$transient = new stdClass();
		}
		$release = self::get_release();
		if ( is_wp_error( $release ) || empty( $release['version'] ) ) {
			return $transient;
		}

		$item = (object) array(
			'id'            => CCT_GITHUB_REPO,
			'slug'          => dirname( self::basename() ),
			'plugin'        => self::basename(),
			'new_version'   => $release['version'],
			'url'           => 'https://github.com/' . CCT_GITHUB_REPO,
			'package'       => self::PACKAGE_TAG . $release['tag'],
			'requires_php'  => '7.4',
			'icons'         => array(),
			'banners'       => array(),
			'tested'        => get_bloginfo( 'version' ),
		);

		if ( version_compare( $release['version'], CCT_VERSION, '>' ) ) {
			$transient->response[ self::basename() ] = $item;
			unset( $transient->no_update[ self::basename() ] );
		} else {
			$transient->no_update[ self::basename() ] = $item;
			unset( $transient->response[ self::basename() ] );
		}
		return $transient;
	}

	/** "View details" popup. */
	public static function plugin_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || dirname( self::basename() ) !== $args->slug ) {
			return $result;
		}
		$release = self::get_release();
		if ( is_wp_error( $release ) ) {
			return $result;
		}
		return (object) array(
			'name'          => 'Casinos.cc Tools',
			'slug'          => $args->slug,
			'version'       => $release['version'],
			'author'        => 'Casinos.cc',
			'homepage'      => 'https://github.com/' . CCT_GITHUB_REPO,
			'requires'      => '6.0',
			'requires_php'  => '7.4',
			'last_updated'  => $release['published'],
			'sections'      => array(
				'description' => esc_html__( 'Interactive casino tools driven by per-casino tool profiles.', 'casinos-cc-tools' ),
				'changelog'   => wpautop( esc_html( $release['notes'] ?: __( 'No release notes.', 'casinos-cc-tools' ) ) ),
			),
			'download_link' => self::PACKAGE_TAG . $release['tag'],
		);
	}

	/**
	 * Download the release zip ourselves. Private-repo assets need the token on the first request
	 * but not on GitHub's redirect to its file storage, so the redirect is resolved manually.
	 */
	public static function download_package( $reply, $package, $upgrader, $hook_extra = array() ) {
		if ( 0 !== strpos( (string) $package, self::PACKAGE_TAG ) ) {
			return $reply;
		}
		$release = self::get_release( true );
		if ( is_wp_error( $release ) ) {
			return $release;
		}

		$url = '';
		if ( $release['asset_api'] ) {
			$res = wp_remote_get( $release['asset_api'], array(
				'timeout'     => 30,
				'redirection' => 0,
				'headers'     => self::api_headers( 'application/octet-stream' ),
			) );
			if ( is_wp_error( $res ) ) {
				return $res;
			}
			$code = wp_remote_retrieve_response_code( $res );
			if ( in_array( $code, array( 301, 302, 303, 307, 308 ), true ) ) {
				$url = wp_remote_retrieve_header( $res, 'location' );
			} elseif ( 200 === $code ) {
				$tmp = wp_tempnam( self::ASSET_NAME );
				file_put_contents( $tmp, wp_remote_retrieve_body( $res ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
				return $tmp;
			}
		}
		if ( ! $url ) {
			$url = $release['asset_public'];
		}
		if ( ! $url ) {
			return new WP_Error( 'cct_no_asset', sprintf(
				/* translators: %s: file name */
				__( 'The latest GitHub release has no %s file attached.', 'casinos-cc-tools' ),
				self::ASSET_NAME
			) );
		}

		if ( $upgrader && isset( $upgrader->skin ) ) {
			$upgrader->skin->feedback( 'downloading_package', esc_html( $release['tag'] ) );
		}
		return download_url( $url, 300 );
	}

	/** Make sure the unzipped folder is always casinos-cc-tools/, whatever the zip calls it. */
	public static function fix_folder_name( $source, $remote_source, $upgrader, $hook_extra = array() ) {
		if ( empty( $hook_extra['plugin'] ) || self::basename() !== $hook_extra['plugin'] ) {
			return $source;
		}
		$wanted = trailingslashit( $remote_source ) . dirname( self::basename() ) . '/';
		if ( untrailingslashit( $source ) === untrailingslashit( $wanted ) ) {
			return $source;
		}
		global $wp_filesystem;
		if ( $wp_filesystem && $wp_filesystem->move( $source, $wanted, true ) ) {
			return $wanted;
		}
		return $source;
	}

	public static function check_url() {
		return wp_nonce_url( admin_url( 'admin-post.php?action=cct_check_updates' ), 'cct_check_updates' );
	}

	public static function row_links( $links, $file ) {
		if ( self::basename() === $file && current_user_can( 'update_plugins' ) ) {
			$links[] = '<a href="' . esc_url( self::check_url() ) . '">' . esc_html__( 'Check for updates', 'casinos-cc-tools' ) . '</a>';
		}
		return $links;
	}

	public static function handle_check_now() {
		if ( ! current_user_can( 'update_plugins' ) ) {
			wp_die( esc_html__( 'You are not allowed to update plugins.', 'casinos-cc-tools' ) );
		}
		check_admin_referer( 'cct_check_updates' );

		delete_site_transient( self::CACHE_KEY );
		$release = self::get_release( true );
		delete_site_transient( 'update_plugins' );
		wp_update_plugins();

		if ( is_wp_error( $release ) ) {
			$state = 'error';
			set_transient( 'cct_check_msg_' . get_current_user_id(), $release->get_error_message(), 60 );
		} else {
			$state = version_compare( $release['version'], CCT_VERSION, '>' ) ? 'available' : 'current';
			set_transient( 'cct_check_msg_' . get_current_user_id(), $release['version'], 60 );
		}

		$back = wp_get_referer() ?: admin_url( 'plugins.php' );
		wp_safe_redirect( add_query_arg( 'cct_check', $state, $back ) );
		exit;
	}

	public static function check_notice() {
		if ( empty( $_GET['cct_check'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		$state = sanitize_key( $_GET['cct_check'] ); // phpcs:ignore WordPress.Security.NonceVerification
		$msg   = get_transient( 'cct_check_msg_' . get_current_user_id() );

		if ( 'available' === $state ) {
			$text = sprintf( __( 'Casinos.cc Tools %1$s is available (you have %2$s).', 'casinos-cc-tools' ), $msg, CCT_VERSION );
			$link = ' <a href="' . esc_url( admin_url( 'plugins.php?plugin_status=upgrade' ) ) . '">' . esc_html__( 'Update now', 'casinos-cc-tools' ) . '</a>';
			echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html( $text ) . $link . '</p></div>'; // phpcs:ignore
		} elseif ( 'current' === $state ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( sprintf( __( 'Casinos.cc Tools is up to date (%s).', 'casinos-cc-tools' ), CCT_VERSION ) ) . '</p></div>';
		} elseif ( 'error' === $state ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( sprintf( __( 'Update check failed: %s', 'casinos-cc-tools' ), $msg ) ) . '</p></div>';
		}
	}
}

<?php
/**
 * Moves the dashboard from /wp-admin to a custom address, such as /office.
 *
 * The wp-admin folder is real, so the new address needs a server rewrite rule
 * that maps /office/... to /wp-admin/.... On Apache and LiteSpeed the rule is
 * written to .htaccess automatically; on Nginx the owner adds it by hand. The
 * address only switches on after a test request proves the rule works, so a
 * missing rule can never lock anyone out.
 *
 * Add `define( 'ADMINSTUDIO_DISABLE_ADMIN_URL', true );` to wp-config.php to
 * switch this off if something goes wrong.
 *
 * @package AdminStudio
 */

namespace AdminStudio\Modules\Security;

use AdminStudio\Files;
use AdminStudio\Settings;
use AdminStudio\Modules\Error_Pages\Error_Pages;

defined( 'ABSPATH' ) || exit;

/**
 * Custom dashboard address.
 */
class Admin_Url {

	const STATE  = 'adminstudio_admin_url';
	const BACKUP = 'adminstudio_htaccess_backup';
	const MARKER = 'Admin Studio';

	/**
	 * Files in wp-admin that front-end code calls directly; they stay at
	 * their usual address and are never blocked or redirected.
	 *
	 * @var string[]
	 */
	const OPEN_FILES = array( 'admin-ajax.php', 'admin-post.php' );

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		if ( self::disabled() ) {
			return;
		}

		add_action( 'wp_ajax_adminstudio_ping', array( __CLASS__, 'ping' ) );
		add_action( 'wp_ajax_nopriv_adminstudio_ping', array( __CLASS__, 'ping' ) );

		if ( ! self::active() ) {
			return;
		}

		add_filter( 'admin_url', array( __CLASS__, 'filter_url' ), 99 );
		add_filter( 'site_url', array( __CLASS__, 'filter_url' ), 99 );
		add_filter( 'wp_redirect', array( __CLASS__, 'filter_url' ), 99 );
		add_action( 'set_auth_cookie', array( __CLASS__, 'set_cookie' ), 10, 6 );
		add_action( 'clear_auth_cookie', array( __CLASS__, 'clear_cookie' ) );
		add_action( 'wp_loaded', array( __CLASS__, 'route' ), 5 );
	}

	/**
	 * Whether the feature is switched off by constant or unsupported.
	 *
	 * @return bool
	 */
	public static function disabled() {
		return ( defined( 'ADMINSTUDIO_DISABLE_ADMIN_URL' ) && ADMINSTUDIO_DISABLE_ADMIN_URL ) || is_multisite();
	}

	/**
	 * The configured slug, if usable.
	 *
	 * @return string
	 */
	public static function slug() {
		$slug = Settings::get( 'security.admin_slug' );

		return self::is_valid( $slug ) ? $slug : '';
	}

	/**
	 * Whether a slug can be used for the dashboard.
	 *
	 * @param string $slug Slug.
	 * @return bool
	 */
	public static function is_valid( $slug ) {
		return '' !== $slug
			&& ! in_array( $slug, Login_Url::RESERVED, true )
			&& Settings::get( 'security.login_slug' ) !== $slug;
	}

	/**
	 * Whether the address has passed its test and is in use.
	 *
	 * @return bool
	 */
	public static function active() {
		$state = get_option( self::STATE, array() );
		$slug  = self::slug();

		return '' !== $slug && is_array( $state ) && isset( $state['verified'] ) && $state['verified'] === $slug;
	}

	/**
	 * Path of the site (where wp-admin lives), without a trailing slash.
	 *
	 * @return string
	 */
	private static function site_path() {
		return untrailingslashit( (string) wp_parse_url( site_url(), PHP_URL_PATH ) );
	}

	/**
	 * Public URL of the dashboard at the new address.
	 *
	 * @return string
	 */
	public static function url() {
		return untrailingslashit( site_url() ) . '/' . self::slug() . '/';
	}

	/**
	 * Rewrites wp-admin URLs to the new address.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	public static function filter_url( $url ) {
		if ( ! is_string( $url ) || false === strpos( $url, '/wp-admin' ) ) {
			return $url;
		}

		foreach ( self::OPEN_FILES as $file ) {
			if ( false !== strpos( $url, '/wp-admin/' . $file ) ) {
				return $url;
			}
		}

		$base = self::site_path() . '/wp-admin';

		return (string) preg_replace(
			'#(^|//[^/]+)' . preg_quote( $base, '#' ) . '(?=/|\?|$)#',
			'${1}' . self::site_path() . '/' . self::slug(),
			$url,
			1
		);
	}

	/**
	 * Current request path, decoded.
	 *
	 * @return string
	 */
	private static function request_path() {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';

		return (string) wp_parse_url( rawurldecode( $uri ), PHP_URL_PATH );
	}

	/**
	 * Blocks the old address for logged-out visitors and moves logged-in users
	 * to the new one, so old bookmarks and plugin links keep working.
	 *
	 * @return void
	 */
	public static function route() {
		$path = self::request_path();
		$base = self::site_path() . '/wp-admin';

		if ( 0 !== strpos( $path, $base ) || ! preg_match( '#^' . preg_quote( $base, '#' ) . '(/|$)#', $path ) ) {
			return;
		}

		if ( in_array( basename( $path ), self::OPEN_FILES, true ) ) {
			return;
		}

		if ( ! is_user_logged_in() ) {
			status_header( 404 );
			nocache_headers();
			header( 'Content-Type: text/html; charset=utf-8' );
			Error_Pages::output( 'notfound' );
			exit;
		}

		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : 'get';

		// Forms that post to a hard-coded wp-admin address are left to finish.
		if ( 'get' !== $method && 'head' !== $method ) {
			return;
		}

		self::copy_current_cookie();

		$uri    = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$origin = (string) preg_replace( '#^(https?://[^/]+).*$#', '$1', site_url() );
		$target = self::filter_url( $origin . $uri );

		wp_safe_redirect( $target );
		exit;
	}

	/**
	 * Name of the auth cookie for a scheme.
	 *
	 * @param string $scheme `auth` or `secure_auth`.
	 * @return string
	 */
	private static function cookie_name( $scheme ) {
		return 'secure_auth' === $scheme ? SECURE_AUTH_COOKIE : AUTH_COOKIE;
	}

	/**
	 * Cookie path for the new address.
	 *
	 * @return string
	 */
	private static function cookie_path() {
		return SITECOOKIEPATH . self::slug();
	}

	/**
	 * Issues the dashboard login cookie for the new address as well.
	 *
	 * WordPress scopes this cookie to /wp-admin, so without a copy the browser
	 * would not send it to the new address. Respects send_auth_cookies, so the
	 * two-factor step still withholds it until a valid code is entered.
	 *
	 * @param string $cookie     Cookie value.
	 * @param int    $expire     Expiry for the browser, or 0 for a session cookie.
	 * @param int    $expiration Expiry of the login itself.
	 * @param int    $user_id    User ID.
	 * @param string $scheme     `auth` or `secure_auth`.
	 * @param string $token      Session token.
	 * @return void
	 */
	public static function set_cookie( $cookie, $expire, $expiration, $user_id, $scheme, $token ) {
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core filter.
		if ( ! apply_filters( 'send_auth_cookies', true, $expire, $expiration, $user_id, $scheme, $token ) || headers_sent() ) {
			return;
		}

		setcookie( self::cookie_name( $scheme ), $cookie, $expire, self::cookie_path(), COOKIE_DOMAIN, 'secure_auth' === $scheme, true );
	}

	/**
	 * Removes the copied cookie on logout.
	 *
	 * @return void
	 */
	public static function clear_cookie() {
		if ( headers_sent() ) {
			return;
		}

		foreach ( array( AUTH_COOKIE, SECURE_AUTH_COOKIE ) as $name ) {
			setcookie( $name, ' ', time() - YEAR_IN_SECONDS, self::cookie_path(), COOKIE_DOMAIN );
		}
	}

	/**
	 * Copies the current request's dashboard cookie to the new address, so
	 * people already logged in move across without logging in again.
	 *
	 * @return void
	 */
	public static function copy_current_cookie() {
		if ( headers_sent() || '' === self::slug() ) {
			return;
		}

		$scheme = is_ssl() ? 'secure_auth' : 'auth';
		$name   = self::cookie_name( $scheme );

		if ( empty( $_COOKIE[ $name ] ) ) {
			return;
		}

		$value  = sanitize_text_field( wp_unslash( $_COOKIE[ $name ] ) );
		$parsed = wp_parse_auth_cookie( $value, $scheme );

		if ( ! $parsed || ! wp_validate_auth_cookie( $value, $scheme ) ) {
			return;
		}

		setcookie( $name, $value, (int) $parsed['expiration'], self::cookie_path(), COOKIE_DOMAIN, 'secure_auth' === $scheme, true );
	}

	/**
	 * Test endpoint: answers only when reached through the new address.
	 *
	 * @return void
	 */
	public static function ping() {
		$slug = self::slug();
		$path = self::request_path();

		if ( '' === $slug || false === strpos( $path, '/' . $slug . '/' ) ) {
			wp_send_json_error( null, 404 );
		}

		wp_send_json_success( array( 'slug' => $slug ) );
	}

	/**
	 * Detected web server: apache (including LiteSpeed), nginx or other.
	 *
	 * @return string
	 */
	public static function server() {
		global $is_apache, $is_nginx;

		if ( $is_apache ) {
			return 'apache';
		}

		return $is_nginx ? 'nginx' : 'other';
	}

	/**
	 * Path of the .htaccess file WordPress uses.
	 *
	 * @return string
	 */
	private static function htaccess_path() {
		if ( ! function_exists( 'get_home_path' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		return get_home_path() . '.htaccess';
	}

	/**
	 * Apache rewrite rules for the current slug.
	 *
	 * @return string[]
	 */
	public static function apache_rules() {
		$home   = untrailingslashit( (string) wp_parse_url( home_url(), PHP_URL_PATH ) );
		$site   = self::site_path();
		$prefix = ltrim( (string) substr( $site, strlen( $home ) ), '/' );
		$prefix = '' === $prefix ? '' : $prefix . '/';
		$slug   = self::slug();

		return array(
			'<IfModule mod_rewrite.c>',
			'RewriteEngine On',
			'RewriteRule ^' . $prefix . $slug . '$ ' . $site . '/' . $slug . '/ [R=301,L]',
			'RewriteRule ^' . $prefix . $slug . '/(.*)$ ' . $site . '/wp-admin/$1 [L,QSA]',
			'</IfModule>',
		);
	}

	/**
	 * Nginx rewrite rules for the current slug.
	 *
	 * @return string
	 */
	public static function nginx_rules() {
		$site = self::site_path();
		$slug = self::slug();

		return 'rewrite ^' . $site . '/' . $slug . '$ ' . $site . '/' . $slug . '/ permanent;' . "\n"
			. 'rewrite ^' . $site . '/' . $slug . '/(.*)$ ' . $site . '/wp-admin/$1 last;';
	}

	/**
	 * Removes this plugin's block from .htaccess content.
	 *
	 * @param string $content File content.
	 * @return string
	 */
	private static function strip_block( $content ) {
		return (string) preg_replace( '/# BEGIN ' . self::MARKER . '.*?# END ' . self::MARKER . '\R*/s', '', $content );
	}

	/**
	 * Writes the rules at the top of .htaccess, before the WordPress block.
	 *
	 * @return bool
	 */
	private static function write_htaccess() {
		$path = self::htaccess_path();

		if ( ! Files::writable( dirname( $path ) ) || ( file_exists( $path ) && ! wp_is_writable( $path ) ) ) {
			return false;
		}

		$content = file_exists( $path ) ? Files::get( $path ) : '';

		if ( false === get_option( self::BACKUP, false ) ) {
			update_option( self::BACKUP, $content, false );
		}

		$block = '# BEGIN ' . self::MARKER . "\n" . implode( "\n", self::apache_rules() ) . "\n" . '# END ' . self::MARKER . "\n\n";

		return Files::put( $path, $block . ltrim( self::strip_block( $content ) ) );
	}

	/**
	 * Removes the rules from .htaccess.
	 *
	 * @return void
	 */
	public static function remove_htaccess() {
		$path = self::htaccess_path();

		if ( ! file_exists( $path ) ) {
			return;
		}

		$content = Files::get( $path );

		if ( false !== strpos( $content, '# BEGIN ' . self::MARKER ) ) {
			Files::put( $path, self::strip_block( $content ) );
		}
	}

	/**
	 * Requests the test endpoint through the new address.
	 *
	 * @return bool
	 */
	public static function test() {
		$slug = self::slug();

		if ( '' === $slug ) {
			return false;
		}

		$response = wp_remote_get(
			untrailingslashit( site_url() ) . '/' . $slug . '/admin-ajax.php?action=adminstudio_ping',
			array(
				'timeout'     => 10,
				'redirection' => 0,
				// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core filter used for loopback requests.
				'sslverify'   => apply_filters( 'https_local_ssl_verify', false ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		return is_array( $body ) && ! empty( $body['success'] ) && isset( $body['data']['slug'] ) && $body['data']['slug'] === $slug;
	}

	/**
	 * Marks the current slug as working, or clears it.
	 *
	 * @param bool $verified Whether the slug works.
	 * @return void
	 */
	private static function set_verified( $verified ) {
		update_option( self::STATE, array( 'verified' => $verified ? self::slug() : '' ) );
	}

	/**
	 * Applies the current settings: writes or removes the rules and tests them.
	 *
	 * @return string active, pending, unwritable or off.
	 */
	public static function apply() {
		if ( self::disabled() || ! Settings::get( 'security.admin_url' ) || '' === self::slug() ) {
			self::remove_htaccess();
			self::set_verified( false );
			return 'off';
		}

		$written = 'apache' === self::server() ? self::write_htaccess() : false;

		if ( self::test() ) {
			self::set_verified( true );
			self::copy_current_cookie();
			return 'active';
		}

		self::set_verified( false );

		return 'apache' === self::server() && ! $written ? 'unwritable' : 'pending';
	}

	/**
	 * Switches the address on without a successful test, for servers that
	 * cannot request their own pages. The owner has confirmed it works.
	 *
	 * @return void
	 */
	public static function force() {
		if ( '' !== self::slug() ) {
			self::set_verified( true );
			self::copy_current_cookie();
		}
	}

	/**
	 * Removes the rules and turns the address off, for deactivation.
	 *
	 * @return void
	 */
	public static function teardown() {
		self::remove_htaccess();
		delete_option( self::STATE );
	}
}

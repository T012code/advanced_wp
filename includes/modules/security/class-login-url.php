<?php
/**
 * Moves the login page to a custom address.
 *
 * Logged-out requests to wp-login.php and /wp-admin are blocked. Every login
 * link WordPress builds is rewritten to the new address. Add
 * `define( 'ADMINORA_DISABLE_LOGIN_URL', true );` to wp-config.php to switch this
 * off if you get locked out.
 *
 * @package Adminora
 */

namespace Adminora\Modules\Security;

use Adminora\Settings;
use Adminora\Modules\Error_Pages\Error_Pages;

defined( 'ABSPATH' ) || exit;

/**
 * Custom login URL.
 */
class Login_Url {

	/**
	 * Slugs that would clash with WordPress or common pages.
	 *
	 * @var string[]
	 */
	const RESERVED = array( 'wp-admin', 'wp-login', 'wp-login-php', 'login', 'admin', 'dashboard', 'wp-content', 'wp-includes', 'wp-json', 'feed', 'xmlrpc', 'xmlrpc-php' );

	/**
	 * Whether this request is for the custom login address.
	 *
	 * @var bool
	 */
	private static $is_login = false;

	/**
	 * Whether this request is for wp-login.php directly.
	 *
	 * @var bool
	 */
	private static $is_direct = false;

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		if ( defined( 'ADMINORA_DISABLE_LOGIN_URL' ) && ADMINORA_DISABLE_LOGIN_URL ) {
			return;
		}

		if ( '' === self::slug() ) {
			return;
		}

		add_action( 'plugins_loaded', array( __CLASS__, 'detect' ), 1 );
		add_action( 'wp_loaded', array( __CLASS__, 'route' ) );
		add_filter( 'site_url', array( __CLASS__, 'filter_url' ), 10, 1 );
		add_filter( 'network_site_url', array( __CLASS__, 'filter_url' ), 10, 1 );
		add_filter( 'wp_redirect', array( __CLASS__, 'filter_url' ), 10, 1 );

		// Stop /login and /admin redirecting to the new address and revealing it.
		remove_action( 'template_redirect', 'wp_redirect_admin_locations', 1000 );
	}

	/**
	 * The configured slug, if it is usable.
	 *
	 * @return string
	 */
	public static function slug() {
		$slug = Settings::get( 'security.login_slug' );

		return self::is_valid( $slug ) ? $slug : '';
	}

	/**
	 * Whether a slug can be used as the login address.
	 *
	 * @param string $slug Slug.
	 * @return bool
	 */
	public static function is_valid( $slug ) {
		return '' !== $slug && ! in_array( $slug, self::RESERVED, true );
	}

	/**
	 * Public URL of the custom login page.
	 *
	 * @return string
	 */
	public static function url() {
		return user_trailingslashit( home_url( '/' . self::slug() ) );
	}

	/**
	 * Works out what the current request is for.
	 *
	 * @return void
	 */
	public static function detect() {
		global $pagenow;

		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$path = (string) wp_parse_url( rawurldecode( $uri ), PHP_URL_PATH );
		$home = (string) wp_parse_url( home_url(), PHP_URL_PATH );

		if ( untrailingslashit( $path ) === untrailingslashit( $home ) . '/' . self::slug() ) {
			self::$is_login = true;
			$pagenow        = 'wp-login.php'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- wp-login.php expects it.
			return;
		}

		if ( 'wp-login.php' === $pagenow || 'wp-login.php' === basename( untrailingslashit( $path ) ) ) {
			self::$is_direct = true;
		}
	}

	/**
	 * Serves the login page at the custom address and blocks the old ones.
	 *
	 * @return void
	 */
	public static function route() {
		global $pagenow;

		if ( self::$is_login ) {
			self::serve_login();
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only check of the requested action.
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';

		if ( self::$is_direct && ! is_user_logged_in() && ! in_array( $action, array( 'postpass', 'confirmaction' ), true ) ) {
			self::block();
		}

		$open_admin = array( 'admin-ajax.php', 'admin-post.php', 'async-upload.php' );

		if ( is_admin() && ! is_user_logged_in() && ! wp_doing_ajax() && ! wp_doing_cron() && ! in_array( $pagenow, $open_admin, true ) ) {
			self::block();
		}
	}

	/**
	 * Loads wp-login.php in the global scope it expects.
	 *
	 * @return void
	 */
	private static function serve_login() {
		// wp-login.php reads and writes these as globals.
		global $error, $interim_login, $action, $user_login, $errors, $redirect_to, $user;

		require_once ABSPATH . 'wp-login.php';
		exit;
	}

	/**
	 * Responds to a blocked request.
	 *
	 * @return void
	 */
	private static function block() {
		$mode = Settings::get( 'security.blocked_action' );

		if ( 'home' === $mode ) {
			wp_safe_redirect( home_url( '/' ) );
			exit;
		}

		if ( 'custom' === $mode && '' !== Settings::get( 'security.blocked_url' ) ) {
			wp_redirect( Settings::get( 'security.blocked_url' ) ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- Admin-chosen URL, may be external.
			exit;
		}

		status_header( 404 );
		nocache_headers();
		header( 'Content-Type: text/html; charset=utf-8' );
		Error_Pages::output( 'notfound' );
		exit;
	}

	/**
	 * Rewrites wp-login.php links to the custom address.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	public static function filter_url( $url ) {
		if ( ! is_string( $url ) || false === strpos( $url, 'wp-login.php' ) || false !== strpos( $url, 'action=postpass' ) ) {
			return $url;
		}

		$parts = wp_parse_url( $url );
		$new   = self::url();

		if ( ! empty( $parts['scheme'] ) ) {
			$new = set_url_scheme( $new, $parts['scheme'] );
		}

		if ( ! empty( $parts['query'] ) ) {
			$new .= '?' . $parts['query'];
		}

		if ( ! empty( $parts['fragment'] ) ) {
			$new .= '#' . $parts['fragment'];
		}

		return $new;
	}
}

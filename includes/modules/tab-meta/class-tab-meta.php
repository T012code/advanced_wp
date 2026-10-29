<?php
/**
 * Browser tab titles, favicon, theme colour and WordPress traces.
 *
 * @package Adminora
 */

namespace Adminora\Modules\Tab_Meta;

use Adminora\Brand_Kit;
use Adminora\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Tab and page details module.
 */
class Tab_Meta {

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'admin_title', array( __CLASS__, 'admin_title' ), 10, 2 );
		add_filter( 'login_title', array( __CLASS__, 'login_title' ), 10, 2 );

		add_filter( 'document_title_separator', array( __CLASS__, 'separator' ), 20 );
		add_filter( 'document_title_parts', array( __CLASS__, 'title_parts' ), 20 );

		if ( '' !== (string) Settings::get( 'tab_meta.favicon_image' ) ) {
			add_filter( 'get_site_icon_url', array( __CLASS__, 'custom_icon' ), 20 );
		}

		if ( Settings::get( 'tab_meta.favicon_ico' ) ) {
			add_action( 'do_faviconico', array( __CLASS__, 'favicon_ico' ), 1 );
		}

		if ( Settings::get( 'tab_meta.favicon' ) ) {
			// WordPress prints the site icon in the admin and on login, but not the
			// fallback logo, and not on pages where no site icon is set.
			add_action( 'login_head', array( __CLASS__, 'fallback_icon' ), 100 );
			add_action( 'admin_head', array( __CLASS__, 'fallback_icon' ), 100 );
			add_action( 'wp_head', array( __CLASS__, 'fallback_icon' ), 100 );
		}

		if ( Settings::get( 'tab_meta.theme_color' ) ) {
			add_action( 'wp_head', array( __CLASS__, 'theme_color' ), 1 );
			add_action( 'login_head', array( __CLASS__, 'theme_color' ), 1 );
			add_action( 'admin_head', array( __CLASS__, 'theme_color' ), 1 );
		}

		if ( Settings::get( 'tab_meta.hide_generator' ) ) {
			remove_action( 'wp_head', 'wp_generator' );
			add_filter( 'the_generator', '__return_empty_string' );
		}

		if ( Settings::get( 'tab_meta.hide_versions' ) ) {
			add_filter( 'style_loader_src', array( __CLASS__, 'strip_version' ), 99 );
			add_filter( 'script_loader_src', array( __CLASS__, 'strip_version' ), 99 );
		}

		if ( Settings::get( 'tab_meta.hide_rsd_wlw' ) ) {
			remove_action( 'wp_head', 'rsd_link' );
			remove_action( 'wp_head', 'wlwmanifest_link' );
		}

		if ( Settings::get( 'tab_meta.hide_rest_links' ) ) {
			remove_action( 'wp_head', 'rest_output_link_wp_head' );
			remove_action( 'template_redirect', 'rest_output_link_header', 11 );
			remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
			remove_action( 'wp_head', 'wp_shortlink_wp_head' );
			remove_action( 'template_redirect', 'wp_shortlink_header', 11 );
		}

		if ( Settings::get( 'tab_meta.hide_emoji' ) ) {
			remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
			remove_action( 'wp_print_styles', 'print_emoji_styles' );
			remove_action( 'wp_enqueue_scripts', 'wp_enqueue_emoji_styles' );
			remove_action( 'login_head', 'print_emoji_detection_script' );
			remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
			remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
			remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
		}

		if ( Settings::get( 'tab_meta.disable_xmlrpc' ) ) {
			add_action( 'init', array( __CLASS__, 'refuse_xmlrpc' ), 0 );
			add_filter( 'xmlrpc_enabled', '__return_false' );
			add_filter( 'xmlrpc_methods', '__return_empty_array' );
			add_filter( 'pings_open', '__return_false', 20 );
			add_filter( 'wp_headers', array( __CLASS__, 'remove_pingback_header' ) );
		}

		if ( Settings::get( 'tab_meta.block_user_lookup' ) ) {
			add_action( 'template_redirect', array( __CLASS__, 'block_author_scan' ), 1 );
			add_filter( 'rest_endpoints', array( __CLASS__, 'hide_user_endpoints' ) );
			add_filter( 'wp_sitemaps_add_provider', array( __CLASS__, 'drop_user_sitemap' ), 10, 2 );
		}

		if ( Settings::get( 'tab_meta.hide_login_wp_link' ) && ! Settings::module_on( 'login' ) ) {
			add_filter( 'login_headerurl', array( __CLASS__, 'home' ) );
			add_filter( 'login_headertext', array( __CLASS__, 'site_name' ) );
		}
	}

	/**
	 * Replaces tags in a title pattern.
	 *
	 * @param string $pattern Pattern with {page} and {site_name}.
	 * @param string $page    Page title.
	 * @return string
	 */
	private static function format( $pattern, $page ) {
		$site = get_bloginfo( 'name', 'display' );

		if ( '' === trim( $page ) ) {
			return $site;
		}

		return trim( str_replace( array( '{page}', '{site_name}' ), array( $page, $site ), $pattern ) );
	}

	/**
	 * Admin screen titles.
	 *
	 * @param string $admin_title Full title.
	 * @param string $title       Screen title.
	 * @return string
	 */
	public static function admin_title( $admin_title, $title ) {
		$pattern = Settings::get( 'tab_meta.admin_title' );

		return '' === $pattern ? $admin_title : esc_html( wp_strip_all_tags( self::format( $pattern, (string) $title ) ) );
	}

	/**
	 * Login screen titles.
	 *
	 * @param string $login_title Full title.
	 * @param string $title       Screen title.
	 * @return string
	 */
	public static function login_title( $login_title, $title ) {
		$pattern = Settings::get( 'tab_meta.login_title' );

		return '' === $pattern ? $login_title : esc_html( wp_strip_all_tags( self::format( $pattern, (string) $title ) ) );
	}

	/**
	 * Separator between the parts of front-end page titles.
	 *
	 * @param string $sep Separator.
	 * @return string
	 */
	public static function separator( $sep ) {
		$custom = (string) Settings::get( 'tab_meta.front_separator' );

		return '' !== trim( $custom ) ? trim( $custom ) : $sep;
	}

	/**
	 * Front-end title parts for the 404, search and home pages.
	 *
	 * @param array $parts Title parts.
	 * @return array
	 */
	public static function title_parts( $parts ) {
		$site = get_bloginfo( 'name', 'display' );

		if ( is_404() ) {
			$custom = trim( (string) Settings::get( 'tab_meta.front_404_title' ) );
			if ( '' !== $custom ) {
				$parts['title'] = str_replace( '{site_name}', $site, $custom );
			}
		} elseif ( is_search() ) {
			$custom = trim( (string) Settings::get( 'tab_meta.front_search_title' ) );
			if ( '' !== $custom ) {
				$parts['title'] = str_replace( array( '{query}', '{site_name}' ), array( get_search_query( false ), $site ), $custom );
			}
		}

		if ( Settings::get( 'tab_meta.hide_home_tagline' ) ) {
			unset( $parts['tagline'] );
		}

		return $parts;
	}

	/**
	 * The uploaded favicon replaces the site icon everywhere WordPress prints it.
	 *
	 * @param string $url Site icon URL.
	 * @return string
	 */
	public static function custom_icon( $url ) {
		$custom = (string) Settings::get( 'tab_meta.favicon_image' );

		return '' !== $custom ? $custom : $url;
	}

	/**
	 * Answers requests for /favicon.ico, which WordPress otherwise sends to its
	 * own logo when the site has no icon.
	 *
	 * @return void
	 */
	public static function favicon_ico() {
		$icon = get_site_icon_url( 32 );
		$icon = '' !== $icon ? $icon : Brand_Kit::logo_url();

		if ( '' !== $icon ) {
			$host = wp_parse_url( $icon, PHP_URL_HOST );

			// The icon may live on a media domain or CDN; allow that host for this redirect only.
			add_filter(
				'allowed_redirect_hosts',
				static function ( $hosts ) use ( $host ) {
					return $host ? array_merge( $hosts, array( $host ) ) : $hosts;
				}
			);

			wp_safe_redirect( esc_url_raw( $icon ), 302, 'Adminora' );
			exit;
		}

		status_header( 204 );
		exit;
	}

	/**
	 * Answers XML-RPC requests with 403. WordPress's own switch only turns off
	 * the methods that need a login, and still lists the rest.
	 *
	 * @return void
	 */
	public static function refuse_xmlrpc() {
		if ( ! defined( 'XMLRPC_REQUEST' ) || ! XMLRPC_REQUEST ) {
			return;
		}

		status_header( 403 );
		header( 'Content-Type: text/plain; charset=utf-8' );
		echo esc_html__( 'XML-RPC is turned off on this site.', 'adminora' );
		exit;
	}

	/**
	 * Removes the X-Pingback header.
	 *
	 * @param array $headers Headers.
	 * @return array
	 */
	public static function remove_pingback_header( $headers ) {
		unset( $headers['X-Pingback'] );

		return $headers;
	}

	/**
	 * Stops ?author=1 style requests from revealing usernames to visitors.
	 *
	 * @return void
	 */
	public static function block_author_scan() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only check of a public query string.
		if ( is_user_logged_in() || ! isset( $_GET['author'] ) ) {
			return;
		}

		global $wp_query;

		$wp_query->set_404();
		status_header( 404 );
		nocache_headers();
	}

	/**
	 * Hides the REST API user list from visitors who are not logged in.
	 *
	 * @param array $endpoints Endpoints.
	 * @return array
	 */
	public static function hide_user_endpoints( $endpoints ) {
		if ( is_user_logged_in() ) {
			return $endpoints;
		}

		foreach ( array_keys( $endpoints ) as $route ) {
			if ( 0 === strpos( $route, '/wp/v2/users' ) ) {
				unset( $endpoints[ $route ] );
			}
		}

		return $endpoints;
	}

	/**
	 * Leaves author pages out of the sitemap, since their addresses contain usernames.
	 *
	 * @param \WP_Sitemaps_Provider $provider Provider.
	 * @param string                $name     Provider name.
	 * @return \WP_Sitemaps_Provider|false
	 */
	public static function drop_user_sitemap( $provider, $name ) {
		return 'users' === $name ? false : $provider;
	}

	/**
	 * Uses the brand logo as the icon when no site icon is set.
	 *
	 * @return void
	 */
	public static function fallback_icon() {
		if ( has_site_icon() ) {
			return;
		}

		$logo = Brand_Kit::logo_url();

		if ( '' !== $logo ) {
			printf( '<link rel="icon" href="%s">' . "\n", esc_url( $logo ) );
		}
	}

	/**
	 * Mobile browser address bar colour.
	 *
	 * @return void
	 */
	public static function theme_color() {
		$brand = Brand_Kit::get();

		printf( '<meta name="theme-color" content="%s">' . "\n", esc_attr( $brand['primary'] ) );
	}

	/**
	 * Removes the WordPress version from core asset URLs on the front end.
	 *
	 * Plugin and theme versions are kept so their cache busting still works.
	 *
	 * @param string $src Asset URL.
	 * @return string
	 */
	public static function strip_version( $src ) {
		if ( is_admin() || ! is_string( $src ) ) {
			return $src;
		}

		if ( false !== strpos( $src, 'ver=' . get_bloginfo( 'version' ) ) ) {
			$src = remove_query_arg( 'ver', $src );
		}

		return $src;
	}

	/**
	 * Home URL, for the login logo link.
	 *
	 * @return string
	 */
	public static function home() {
		return home_url( '/' );
	}

	/**
	 * Site name, for the login logo text.
	 *
	 * @return string
	 */
	public static function site_name() {
		return esc_html( get_bloginfo( 'name' ) );
	}
}

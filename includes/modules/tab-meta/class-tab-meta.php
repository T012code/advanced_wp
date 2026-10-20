<?php
/**
 * Browser tab titles, favicon, theme colour and WordPress traces.
 *
 * @package AdvancedWP
 */

namespace AdvancedWP\Modules\Tab_Meta;

use AdvancedWP\Brand_Kit;
use AdvancedWP\Settings;

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

		if ( Settings::get( 'tab_meta.favicon' ) ) {
			// WordPress prints the site icon in the admin and on login, but not the
			// fallback logo, and not on pages where no site icon is set.
			add_action( 'login_head', array( __CLASS__, 'fallback_icon' ), 100 );
			add_action( 'admin_head', array( __CLASS__, 'fallback_icon' ), 100 );
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

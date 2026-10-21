<?php
/**
 * White-label the admin: admin bar logo, footer credit, dashboard widgets
 * and a colour scheme built from the brand kit.
 *
 * @package AdminStudio
 */

namespace AdminStudio\Modules\Admin_Branding;

use AdminStudio\Brand_Kit;
use AdminStudio\Color;
use AdminStudio\Settings;
use AdminStudio\Modules\Emails\Renderer;

defined( 'ABSPATH' ) || exit;

/**
 * Admin branding module.
 */
class Admin_Branding {

	const SCHEME = 'adminstudio';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		$logo = Settings::get( 'admin_branding.adminbar_logo' );

		if ( 'keep' !== $logo ) {
			add_action( 'admin_bar_menu', array( __CLASS__, 'adminbar_logo' ), 5 );
			add_action( 'admin_bar_menu', array( __CLASS__, 'remove_wp_logo' ), 999 );
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'adminbar_css' ) );
			add_action( 'wp_enqueue_scripts', array( __CLASS__, 'adminbar_css' ) );
		}

		if ( 'keep' !== Settings::get( 'admin_branding.footer_left' ) ) {
			add_filter( 'admin_footer_text', array( __CLASS__, 'footer_left' ), 99 );
		}

		if ( 'keep' !== Settings::get( 'admin_branding.footer_right' ) ) {
			add_filter( 'update_footer', array( __CLASS__, 'footer_right' ), 99 );
		}

		add_action( 'wp_dashboard_setup', array( __CLASS__, 'dashboard' ), 999 );

		if ( Settings::get( 'admin_branding.color_scheme' ) ) {
			add_action( 'admin_init', array( __CLASS__, 'register_scheme' ) );
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'scheme_vars' ), 99 );
			add_action( 'wp_enqueue_scripts', array( __CLASS__, 'scheme_vars' ), 99 );

			if ( Settings::get( 'admin_branding.default_scheme' ) ) {
				add_action( 'user_register', array( __CLASS__, 'default_scheme' ) );
			}
		}

		if ( Settings::get( 'admin_branding.hide_howdy' ) ) {
			add_action( 'admin_bar_menu', array( __CLASS__, 'remove_howdy' ), 9999 );
		}

		if ( Settings::get( 'admin_branding.hide_help' ) ) {
			add_action( 'admin_head', array( __CLASS__, 'remove_help' ) );
		}

		if ( Settings::get( 'admin_branding.hide_screen_opts' ) ) {
			add_filter( 'screen_options_show_screen', '__return_false' );
		}
	}

	/**
	 * Adds the replacement logo node.
	 *
	 * @param \WP_Admin_Bar $bar Admin bar.
	 * @return void
	 */
	public static function adminbar_logo( $bar ) {
		if ( 'replace' !== Settings::get( 'admin_branding.adminbar_logo' ) ) {
			return;
		}

		$image = Settings::get( 'admin_branding.adminbar_image' );
		$image = '' !== $image ? $image : ( has_site_icon() ? get_site_icon_url( 64 ) : Brand_Kit::logo_url() );
		$link  = Settings::get( 'admin_branding.adminbar_link' );
		$name  = get_bloginfo( 'name' );

		$title = '' !== $image
			? '<img src="' . esc_url( $image ) . '" alt="" class="adminstudio-ab-logo"><span class="screen-reader-text">' . esc_html( $name ) . '</span>'
			: '<span class="ab-label">' . esc_html( $name ) . '</span>';

		$bar->add_node(
			array(
				'id'    => 'adminstudio-logo',
				'title' => $title,
				'href'  => '' !== $link ? $link : home_url( '/' ),
				'meta'  => array( 'title' => $name ),
			)
		);
	}

	/**
	 * Removes the WordPress logo menu.
	 *
	 * @param \WP_Admin_Bar $bar Admin bar.
	 * @return void
	 */
	public static function remove_wp_logo( $bar ) {
		$bar->remove_node( 'wp-logo' );
	}

	/**
	 * Sizes the admin bar logo.
	 *
	 * @return void
	 */
	public static function adminbar_css() {
		if ( ! is_admin_bar_showing() ) {
			return;
		}

		wp_add_inline_style( 'admin-bar', '#wpadminbar #wp-admin-bar-adminstudio-logo .ab-item{display:flex;align-items:center;padding:0 8px}#wpadminbar .adminstudio-ab-logo{display:block;height:20px;width:auto;max-width:120px}' );
	}

	/**
	 * Left admin footer text.
	 *
	 * @return string
	 */
	public static function footer_left() {
		if ( 'hide' === Settings::get( 'admin_branding.footer_left' ) ) {
			return '';
		}

		return wp_kses_post( Renderer::replace( Settings::get( 'admin_branding.footer_left_text' ), Renderer::common_context(), true ) );
	}

	/**
	 * Right admin footer text (the WordPress version by default).
	 *
	 * @return string
	 */
	public static function footer_right() {
		if ( 'hide' === Settings::get( 'admin_branding.footer_right' ) ) {
			return '';
		}

		return wp_kses_post( Renderer::replace( Settings::get( 'admin_branding.footer_right_text' ), Renderer::common_context(), true ) );
	}

	/**
	 * Removes chosen widgets and adds the custom welcome panel.
	 *
	 * @return void
	 */
	public static function dashboard() {
		// Core adds the welcome panel after plugins load, so remove it here.
		if ( in_array( 'welcome_panel', (array) Settings::get( 'admin_branding.hide_widgets' ), true ) ) {
			remove_action( 'welcome_panel', 'wp_welcome_panel' );
		}

		foreach ( (array) Settings::get( 'admin_branding.hide_widgets' ) as $widget ) {
			remove_meta_box( $widget, 'dashboard', 'normal' );
			remove_meta_box( $widget, 'dashboard', 'side' );
		}

		if ( ! Settings::get( 'admin_branding.welcome' ) ) {
			return;
		}

		$title = Renderer::replace( Settings::get( 'admin_branding.welcome_title' ), Renderer::common_context(), false );

		wp_add_dashboard_widget( 'adminstudio_welcome', esc_html( $title ), array( __CLASS__, 'welcome_widget' ), null, null, 'normal', 'high' );
	}

	/**
	 * Renders the welcome widget.
	 *
	 * @return void
	 */
	public static function welcome_widget() {
		$content = Renderer::replace( Settings::get( 'admin_branding.welcome_content' ), Renderer::common_context(), true );
		$button  = Settings::get( 'admin_branding.welcome_button' );
		$url     = Settings::get( 'admin_branding.welcome_button_url' );

		echo wp_kses_post( wpautop( $content ) );

		if ( '' !== $button && '' !== $url ) {
			printf( '<p><a class="button button-primary" href="%s">%s</a></p>', esc_url( $url ), esc_html( $button ) );
		}
	}

	/**
	 * Registers the brand colour scheme.
	 *
	 * @return void
	 */
	public static function register_scheme() {
		$b = Brand_Kit::get();

		wp_admin_css_color(
			self::SCHEME,
			__( 'Brand (Admin Studio)', 'admin-studio' ),
			add_query_arg( 'v', \AdminStudio\Plugin::asset_version( 'assets/css/admin-scheme.css' ), ADMINSTUDIO_URL . 'assets/css/admin-scheme.css' ),
			array( self::menu_bg( $b ), $b['primary'], $b['secondary'], Color::readable_on( self::menu_bg( $b ) ) ),
			array(
				'base'    => Color::mix( Color::readable_on( self::menu_bg( $b ) ), self::menu_bg( $b ), 0.3 ),
				'focus'   => '#ffffff',
				'current' => $b['on_primary'],
			)
		);
	}

	/**
	 * Admin menu background: the brand text colour if dark, else a dark mix of primary.
	 *
	 * @param array $b Brand values.
	 * @return string
	 */
	private static function menu_bg( array $b ) {
		return Color::is_light( $b['text'] ) ? Color::mix( $b['primary'], '#000000', 0.7 ) : $b['text'];
	}

	/**
	 * Prints the scheme's colour variables when the current user uses it.
	 *
	 * @return void
	 */
	public static function scheme_vars() {
		if ( ! is_user_logged_in() || self::SCHEME !== get_user_option( 'admin_color' ) ) {
			return;
		}

		$b    = Brand_Kit::get();
		$menu = self::menu_bg( $b );
		$text = Color::readable_on( $menu );

		// The current menu item needs to stand out from the menu itself.
		$current = $b['primary'];
		if ( Color::contrast( $current, $menu ) < 1.6 ) {
			$current = Color::contrast( $b['secondary'], $menu ) >= 1.6 ? $b['secondary'] : Color::mix( $menu, '#ffffff', 0.25 );
		}

		$vars = array(
			'menu-bg'      => $menu,
			'menu-text'    => $text,
			'menu-muted'   => Color::mix( $text, $menu, 0.35 ),
			'menu-sub-bg'  => Color::mix( $menu, '#000000', 0.25 ),
			'menu-hover'   => Color::mix( $menu, $text, 0.12 ),
			'current'      => $current,
			'on-current'   => Color::readable_on( $current ),
			'accent'       => $b['primary'],
			'on-accent'    => $b['on_primary'],
			'accent-dark'  => Color::mix( $b['primary'], '#000000', 0.15 ),
			'link'         => Color::contrast( $b['primary'], '#ffffff' ) >= 4.5 ? $b['primary'] : Color::mix( $b['primary'], '#000000', 0.4 ),
			'notification' => $b['error'],
		);

		$css = ':root{';
		foreach ( $vars as $name => $value ) {
			$css .= '--adminstudio-admin-' . $name . ':' . $value . ';';
		}
		$css .= '}';

		// The admin bar shows in the admin and on the front end; the rest of the
		// scheme loads in the admin only, through the `colors` handle.
		if ( is_admin_bar_showing() ) {
			wp_enqueue_style( 'adminstudio-admin-bar-scheme', ADMINSTUDIO_URL . 'assets/css/admin-bar-scheme.css', array( 'admin-bar' ), \AdminStudio\Plugin::asset_version( 'assets/css/admin-bar-scheme.css' ) );
			wp_add_inline_style( 'adminstudio-admin-bar-scheme', $css );
		}

		if ( is_admin() ) {
			wp_add_inline_style( 'colors', $css );
		}
	}

	/**
	 * Gives new users the brand colour scheme.
	 *
	 * @param int $user_id New user ID.
	 * @return void
	 */
	public static function default_scheme( $user_id ) {
		update_user_meta( $user_id, 'admin_color', self::SCHEME );
	}

	/**
	 * Replaces "Howdy, Name" with just the name.
	 *
	 * @param \WP_Admin_Bar $bar Admin bar.
	 * @return void
	 */
	public static function remove_howdy( $bar ) {
		$node = $bar->get_node( 'my-account' );

		if ( ! $node ) {
			return;
		}

		$user = wp_get_current_user();

		$bar->add_node(
			array(
				'id'    => 'my-account',
				'title' => '<span class="display-name">' . esc_html( $user->display_name ) . '</span>' . get_avatar( $user->ID, 26 ),
			)
		);
	}

	/**
	 * Removes the Help tab.
	 *
	 * @return void
	 */
	public static function remove_help() {
		$screen = get_current_screen();

		if ( $screen ) {
			$screen->remove_help_tabs();
		}
	}
}

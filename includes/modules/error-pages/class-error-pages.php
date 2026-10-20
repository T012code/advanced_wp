<?php
/**
 * Branded error and system pages.
 *
 * The critical error, database error and update maintenance pages are written
 * as drop-in files (see Dropins). Notice screens from wp_die() and the 404 page
 * are handled live through filters.
 *
 * @package AdminStudio
 */

namespace AdminStudio\Modules\Error_Pages;

use AdminStudio\Brand_Kit;
use AdminStudio\Preview;
use AdminStudio\Settings;
use AdminStudio\System_Page;

defined( 'ABSPATH' ) || exit;

/**
 * Error pages module.
 */
class Error_Pages {

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		if ( Settings::get( 'error_pages.wp_die' ) ) {
			add_filter( 'wp_die_handler', array( __CLASS__, 'die_handler_name' ) );
		}

		if ( Settings::get( 'error_pages.notfound' ) ) {
			add_filter( 'template_include', array( __CLASS__, 'notfound_template' ), 99 );
		}
	}

	/**
	 * Brand values for system pages.
	 *
	 * @return array
	 */
	public static function brand() {
		return Brand_Kit::get(
			array(
				'background' => Settings::get( 'error_pages.bg_color' ),
				'text'       => Settings::get( 'error_pages.text_color' ),
				'primary'    => Settings::get( 'error_pages.primary' ),
				'surface'    => Settings::get( 'error_pages.surface' ),
			)
		);
	}

	/**
	 * Page arguments for a system page.
	 *
	 * @param string $page critical, db, maintenance, wp_die or notfound.
	 * @return array
	 */
	public static function args( $page ) {
		$p    = 'error_pages.';
		$site = get_bloginfo( 'name' );
		$base = array(
			'show_logo' => (bool) Settings::get( $p . 'show_logo' ),
			'image'     => Settings::get( $p . 'image' ),
		);

		switch ( $page ) {
			case 'db':
				$args = array(
					'heading' => Settings::get( $p . 'db_title' ),
					'message' => Settings::get( $p . 'db_message' ),
				);
				break;

			case 'maintenance':
				$args = array(
					'heading' => Settings::get( $p . 'maintenance_title' ),
					'message' => Settings::get( $p . 'maintenance_message' ),
				);
				break;

			case 'wp_die':
				$args = array(
					'heading' => Settings::get( $p . 'wp_die_title' ),
					'message' => __( 'Sorry, you are not allowed to access this page.', 'admin-studio' ),
					'buttons' => array( array( Settings::get( $p . 'wp_die_button' ), home_url( '/' ) ) ),
				);
				break;

			case 'notfound':
				$args = array(
					'heading' => Settings::get( $p . 'notfound_title' ),
					'message' => Settings::get( $p . 'notfound_message' ),
					'extra'   => Settings::get( $p . 'notfound_search' ) ? self::search_form() : '',
					'buttons' => array_filter( array( self::button( Settings::get( $p . 'notfound_button' ), home_url( '/' ) ) ) ),
					'noindex' => false,
				);
				break;

			case 'critical':
			default:
				$url  = Settings::get( $p . 'critical_button_url' );
				$args = array(
					'heading' => Settings::get( $p . 'critical_title' ),
					'message' => Settings::get( $p . 'critical_message' ),
					'buttons' => array_filter( array( self::button( Settings::get( $p . 'critical_button' ), '' !== $url ? $url : home_url( '/' ) ) ) ),
				);
		}

		$args['title'] = $args['heading'] . ' | ' . $site;

		return array_merge( $base, $args );
	}

	/**
	 * Button pair, or null when the text is empty.
	 *
	 * @param string $text Button text.
	 * @param string $url  Button URL.
	 * @return array|null
	 */
	private static function button( $text, $url ) {
		return '' !== trim( $text ) ? array( $text, $url ) : null;
	}

	/**
	 * Plain search form that does not depend on the theme.
	 *
	 * @return string
	 */
	private static function search_form() {
		return sprintf(
			'<form class="adminstudio-page__search" role="search" method="get" action="%1$s"><label class="screen-reader-text" for="adminstudio-s">%2$s</label><input type="search" id="adminstudio-s" name="s" placeholder="%2$s"><button type="submit">%3$s</button></form>',
			esc_url( home_url( '/' ) ),
			esc_attr__( 'Search this site', 'admin-studio' ),
			esc_html__( 'Search', 'admin-studio' )
		);
	}

	/**
	 * Renders a system page to a string.
	 *
	 * @param string $page  Page key.
	 * @param array  $extra Arguments that override the defaults.
	 * @return string
	 */
	public static function render( $page, array $extra = array() ) {
		return System_Page::render( array_merge( self::args( $page ), $extra ), self::brand() );
	}

	/**
	 * Prints a system page.
	 *
	 * @param string $page  Page key.
	 * @param array  $extra Arguments that override the defaults.
	 * @return void
	 */
	public static function output( $page, array $extra = array() ) {
		System_Page::output( array_merge( self::args( $page ), $extra ), self::brand() );
	}

	/**
	 * Prints a page inside the Customizer preview.
	 *
	 * @param string $page Page key.
	 * @return void
	 */
	public static function render_preview( $page ) {
		self::output( $page, array( 'preview' => true ) );
	}

	/**
	 * Returns the callable name for wp_die_handler.
	 *
	 * @param callable $handler Default handler.
	 * @return callable
	 */
	public static function die_handler_name( $handler ) {
		// Translations and settings defaults are not ready before init.
		if ( ! did_action( 'init' ) ) {
			return $handler;
		}

		return array( __CLASS__, 'die_handler' );
	}

	/**
	 * Branded replacement for the default HTML wp_die() handler.
	 *
	 * @param string|\WP_Error $message Message.
	 * @param string|int       $title   Title, or response code.
	 * @param string|array|int $args    Arguments, or response code.
	 * @return void
	 */
	public static function die_handler( $message, $title = '', $args = array() ) {
		if ( is_int( $args ) ) {
			$args = array( 'response' => $args );
		} elseif ( is_int( $title ) ) {
			$args  = array( 'response' => $title );
			$title = '';
		}

		$args = wp_parse_args(
			$args,
			array(
				'response'  => 500,
				'exit'      => true,
				'back_link' => false,
				'link_url'  => '',
				'link_text' => '',
			)
		);

		if ( is_wp_error( $message ) ) {
			$data = $message->get_error_data();

			if ( is_array( $data ) ) {
				if ( ! empty( $data['status'] ) ) {
					$args['response'] = (int) $data['status'];
				}
				if ( '' === $title && ! empty( $data['title'] ) ) {
					$title = $data['title'];
				}
			}

			$errors  = $message->get_error_messages();
			$message = count( $errors ) > 1
				? '<ul><li>' . implode( '</li><li>', $errors ) . '</li></ul>'
				: ( $errors ? $errors[0] : '' );
		}

		$title = is_string( $title ) && '' !== $title ? $title : Settings::get( 'error_pages.wp_die_title' );

		$buttons = array();

		if ( $args['link_url'] && $args['link_text'] ) {
			$buttons[] = array( $args['link_text'], $args['link_url'] );
		}

		$referer   = wp_get_referer();
		$buttons[] = array( Settings::get( 'error_pages.wp_die_button' ), $referer ? $referer : home_url( '/' ) );

		if ( ! headers_sent() ) {
			status_header( $args['response'] );
			nocache_headers();
			header( 'Content-Type: text/html; charset=utf-8' );
		}

		self::output(
			'wp_die',
			array(
				'title'   => wp_strip_all_tags( $title ) . ' | ' . get_bloginfo( 'name' ),
				'heading' => wp_strip_all_tags( $title ),
				'message' => (string) $message,
				'buttons' => $buttons,
			)
		);

		if ( $args['exit'] ) {
			die();
		}
	}

	/**
	 * Swaps in the branded 404 template.
	 *
	 * @param string $template Template path.
	 * @return string
	 */
	public static function notfound_template( $template ) {
		return is_404() ? ADMINSTUDIO_DIR . 'templates/notfound.php' : $template;
	}
}

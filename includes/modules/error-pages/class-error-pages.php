<?php
/**
 * Branded error and system pages.
 *
 * The critical error, database error and update maintenance pages are written
 * as drop-in files (see Dropins). Notice screens from wp_die() and the 404 page
 * are handled live through filters.
 *
 * @package Adminora
 */

namespace Adminora\Modules\Error_Pages;

use Adminora\Brand_Kit;
use Adminora\Preview;
use Adminora\Settings;
use Adminora\System_Page;
use Adminora\Modules\Login\Login_Text;

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
	 * Placeholder in the critical error drop-in for the administrators' note.
	 */
	const NOTE_MARKER = 'ADMINORA_ADMIN_NOTE';

	/**
	 * The administrators' note on the critical error page, with tags filled in.
	 *
	 * @return string Plain text, or '' when there is none.
	 */
	public static function admin_note_text() {
		$text = trim( (string) Settings::get( 'error_pages.critical_admin_note' ) );

		return str_replace( array( '{admin_email}', '{site_name}' ), array( get_option( 'admin_email' ), get_bloginfo( 'name' ) ), $text );
	}

	/**
	 * Markup for the administrators' note.
	 *
	 * @param bool $for_dropin Leave a placeholder for the drop-in to fill when it runs.
	 * @return string
	 */
	private static function admin_note( $for_dropin ) {
		$text = self::admin_note_text();

		if ( '' === $text ) {
			return '';
		}

		if ( $for_dropin ) {
			return '<p class="adminora-page__note">' . self::NOTE_MARKER . '</p>';
		}

		return current_user_can( 'manage_options' ) ? '<p class="adminora-page__note">' . esc_html( $text ) . '</p>' : '';
	}

	/**
	 * Page arguments for a system page.
	 *
	 * @param string $page       critical, db, maintenance, wp_die, denied, expired or notfound.
	 * @param bool   $for_dropin Whether the page is being saved as a drop-in file.
	 * @return array
	 */
	public static function args( $page, $for_dropin = false ) {
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

			case 'denied':
				$args = array(
					'heading' => Settings::get( $p . 'denied_title' ),
					'message' => Settings::get( $p . 'denied_message' ),
					'buttons' => array_filter( array( self::button( Settings::get( $p . 'denied_button' ), self::denied_url() ) ) ),
				);
				break;

			case 'expired':
				$args = array(
					'heading' => Settings::get( $p . 'expired_title' ),
					'message' => Settings::get( $p . 'expired_message' ),
					'buttons' => array( array( Settings::get( $p . 'wp_die_button' ), home_url( '/' ) ) ),
				);
				break;

			case 'wp_die':
				$args = array(
					'heading' => Settings::get( $p . 'wp_die_title' ),
					'message' => __( 'Sorry, you are not allowed to access this page.', 'adminora' ),
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
					'extra'   => self::admin_note( $for_dropin ),
				);
		}

		$args['title'] = self::tab_title( $args['heading'] );

		return array_merge( $base, $args );
	}

	/**
	 * Browser tab title for a system page, from the pattern on the Tab and
	 * page details tab.
	 *
	 * @param string $heading Page heading.
	 * @return string
	 */
	public static function tab_title( $heading ) {
		$pattern = (string) Settings::get( 'tab_meta.system_title' );
		$pattern = '' !== trim( $pattern ) ? $pattern : '{page} | {site_name}';

		return trim( str_replace( array( '{page}', '{site_name}' ), array( wp_strip_all_tags( $heading ), get_bloginfo( 'name' ) ), $pattern ) );
	}

	/**
	 * Where the "no permission" button goes.
	 *
	 * @return string
	 */
	private static function denied_url() {
		$url = (string) Settings::get( 'error_pages.denied_button_url' );

		if ( '' !== $url ) {
			return $url;
		}

		return is_user_logged_in() && current_user_can( 'read' ) ? admin_url() : home_url( '/' );
	}

	/**
	 * Which kind of notice screen a wp_die() call is.
	 *
	 * @param string $message  Plain message.
	 * @param int    $response HTTP status.
	 * @return string critical, denied, expired or wp_die.
	 */
	public static function kind( $message, $response ) {
		$message = trim( wp_strip_all_tags( $message ) );

		// WordPress's own critical error screen, shown when the drop-in file is missing.
		if ( 0 === strpos( $message, Login_Text::core( 'There has been a critical error on this website.' ) ) ) {
			return 'critical';
		}

		// WordPress adds a "Please try again" link after this message.
		if ( 0 === strpos( $message, Login_Text::core( 'The link you followed has expired.' ) ) ) {
			return 'expired';
		}

		if ( 403 === (int) $response ) {
			// WordPress words dozens of these screens "Sorry, you are not allowed to ...",
			// so the start of its most common one identifies the rest in any language.
			$common = Login_Text::core( 'Sorry, you are not allowed to access this page.' );
			$starts = array(
				$common,
				Login_Text::core( 'You need a higher level of permission.' ),
				'Sorry, you are not allowed to',
				(string) strtok( $common, ',' ),
			);

			foreach ( $starts as $start ) {
				if ( strlen( $start ) > 4 && 0 === strpos( $message, $start ) ) {
					return 'denied';
				}
			}
		}

		return 'wp_die';
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
			'<form class="adminora-page__search" role="search" method="get" action="%1$s"><label class="screen-reader-text" for="adminora-s">%2$s</label><input type="search" id="adminora-s" name="s" placeholder="%2$s"><button type="submit">%3$s</button></form>',
			esc_url( home_url( '/' ) ),
			esc_attr__( 'Search this site', 'adminora' ),
			esc_html__( 'Search', 'adminora' )
		);
	}

	/**
	 * Renders a system page to a string.
	 *
	 * @param string $page       Page key.
	 * @param array  $extra      Arguments that override the defaults.
	 * @param bool   $for_dropin Leave placeholders for a drop-in file.
	 * @return string
	 */
	public static function render( $page, array $extra = array(), $for_dropin = false ) {
		return System_Page::render( array_merge( self::args( $page, $for_dropin ), $extra ), self::brand() );
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

		$kind = self::kind( (string) $message, (int) $args['response'] );

		if ( 'critical' === $kind && Settings::get( 'error_pages.critical' ) ) {
			self::send_headers( 500 );
			self::output( 'critical' );
			if ( $args['exit'] ) {
				die();
			}
			return;
		}

		if ( 'denied' === $kind && Settings::get( 'error_pages.denied' ) ) {
			self::send_headers( $args['response'] );
			$custom = trim( (string) Settings::get( 'error_pages.denied_message' ) );
			self::output( 'denied', '' !== $custom ? array() : array( 'message' => (string) $message ) );
			if ( $args['exit'] ) {
				die();
			}
			return;
		}

		if ( 'expired' === $kind ) {
			$referer = wp_get_referer();
			self::send_headers( $args['response'] );
			self::output( 'expired', array( 'buttons' => array( array( Settings::get( 'error_pages.wp_die_button' ), $referer ? $referer : home_url( '/' ) ) ) ) );
			if ( $args['exit'] ) {
				die();
			}
			return;
		}

		$title = is_string( $title ) && '' !== $title ? $title : Settings::get( 'error_pages.wp_die_title' );

		$buttons = array();

		if ( $args['link_url'] && $args['link_text'] ) {
			$buttons[] = array( $args['link_text'], $args['link_url'] );
		}

		$referer   = wp_get_referer();
		$buttons[] = array( Settings::get( 'error_pages.wp_die_button' ), $referer ? $referer : home_url( '/' ) );

		self::send_headers( $args['response'] );

		self::output(
			'wp_die',
			array(
				'title'   => self::tab_title( $title ),
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
	 * Status and cache headers for a notice screen.
	 *
	 * @param int $response HTTP status.
	 * @return void
	 */
	private static function send_headers( $response ) {
		if ( ! headers_sent() ) {
			status_header( (int) $response );
			nocache_headers();
			header( 'Content-Type: text/html; charset=utf-8' );
		}
	}

	/**
	 * Swaps in the branded 404 template.
	 *
	 * @param string $template Template path.
	 * @return string
	 */
	public static function notfound_template( $template ) {
		return is_404() ? ADMINORA_DIR . 'templates/notfound.php' : $template;
	}
}

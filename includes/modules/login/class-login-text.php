<?php
/**
 * Editable wording on the login, registration and password screens.
 *
 * Each entry replaces one piece of WordPress's own text, matched on the
 * original English string so it works in every language. Replacements only
 * apply while a login screen is being built.
 *
 * @package Adminora
 */

namespace Adminora\Modules\Login;

use Adminora\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Login screen wording.
 */
class Login_Text {

	/**
	 * Original text => replacement, built on login_init.
	 *
	 * @var array
	 */
	private static $map = array();

	/**
	 * Editable texts: key => [ screen, type, original English text ].
	 *
	 * Holds no translated strings, so the settings schema can read it before init.
	 *
	 * @return array
	 */
	public static function catalogue() {
		return array(
			'login_title'       => array( 'login', 'text', 'Log In' ),
			'username_label'    => array( 'login', 'text', 'Username or Email Address' ),
			'password_label'    => array( 'login', 'text', 'Password' ),
			'remember'          => array( 'login', 'text', 'Remember Me' ),
			'lost_link'         => array( 'login', 'text', 'Lost your password?' ),
			'register_link'     => array( 'login', 'text', 'Register' ),
			'login_link'        => array( 'login', 'text', 'Log in' ),
			'logged_out'        => array( 'login', 'text', 'You are now logged out.' ),
			'session_expired'   => array( 'login', 'text', 'Your session has expired. Please log in to continue where you left off.' ),
			'lost_title'        => array( 'lost', 'text', 'Lost Password' ),
			'lost_message'      => array( 'lost', 'html', 'Please enter your username or email address. You will receive an email message with instructions on how to reset your password.' ),
			'lost_button'       => array( 'lost', 'text', 'Get New Password' ),
			'check_email_title' => array( 'lost', 'text', 'Check your email' ),
			'check_email'       => array( 'lost', 'html', 'Check your email for the confirmation link, then visit the <a href="%s">login page</a>.' ),
			'reset_title'       => array( 'reset', 'text', 'Reset Password' ),
			'reset_message'     => array( 'reset', 'html', 'Enter your new password below or generate one.' ),
			'new_password'      => array( 'reset', 'text', 'New password' ),
			'save_password'     => array( 'reset', 'text', 'Save Password' ),
			'reset_done'        => array( 'reset', 'html', 'Your password has been reset.' ),
			'register_title'    => array( 'register', 'text', 'Registration Form' ),
			'register_message'  => array( 'register', 'html', 'Register For This Site' ),
			'register_note'     => array( 'register', 'html', 'Registration confirmation will be emailed to you.' ),
			'register_done'     => array( 'register', 'html', 'Registration complete. Please check your email, then visit the <a href="%s">login page</a>.' ),
		);
	}

	/**
	 * Translated labels for the Customizer: key => label.
	 *
	 * @return array
	 */
	public static function labels() {
		return array(
			'login_title'       => __( '"Log In" button and screen name', 'adminora' ),
			'username_label'    => __( 'Username field label', 'adminora' ),
			'password_label'    => __( 'Password field label', 'adminora' ),
			'remember'          => __( '"Remember Me"', 'adminora' ),
			'lost_link'         => __( '"Lost your password?" link', 'adminora' ),
			'register_link'     => __( '"Register" link and button', 'adminora' ),
			'login_link'        => __( '"Log in" link on the other screens', 'adminora' ),
			'logged_out'        => __( 'Message after logging out', 'adminora' ),
			'session_expired'   => __( 'Message when the session has expired', 'adminora' ),
			'lost_title'        => __( 'Lost password: screen name', 'adminora' ),
			'lost_message'      => __( 'Lost password: instructions', 'adminora' ),
			'lost_button'       => __( 'Lost password: button', 'adminora' ),
			'check_email_title' => __( 'Check your email: screen name', 'adminora' ),
			'check_email'       => __( 'Check your email: message', 'adminora' ),
			'reset_title'       => __( 'Reset password: screen name', 'adminora' ),
			'reset_message'     => __( 'Reset password: instructions', 'adminora' ),
			'new_password'      => __( 'Reset password: field label', 'adminora' ),
			'save_password'     => __( 'Reset password: button', 'adminora' ),
			'reset_done'        => __( 'Reset password: success message', 'adminora' ),
			'register_title'    => __( 'Register: screen name', 'adminora' ),
			'register_message'  => __( 'Register: message above the form', 'adminora' ),
			'register_note'     => __( 'Register: note under the fields', 'adminora' ),
			'register_done'     => __( 'Register: success message', 'adminora' ),
		);
	}

	/**
	 * WordPress's own wording for a key, in the site language.
	 *
	 * @param string $key Catalogue key.
	 * @return string
	 */
	public static function original( $key ) {
		$catalogue = self::catalogue();

		if ( ! isset( $catalogue[ $key ] ) ) {
			return '';
		}

		$text = self::core( $catalogue[ $key ][2] );

		return wp_strip_all_tags( str_replace( '%s', '', $text ) );
	}

	/**
	 * WordPress's own translation of one of its strings, in the site language.
	 *
	 * @param string $text Original English text.
	 * @return string
	 */
	public static function core( $text ) {
		return get_translations_for_domain( 'default' )->translate( $text );
	}

	/**
	 * Saved wording for a key, or WordPress's own. Used by the preview.
	 *
	 * @param string $key Catalogue key.
	 * @return string Plain text.
	 */
	public static function display( $key ) {
		$value = trim( wp_strip_all_tags( (string) Settings::get( 'login_words.' . $key ) ) );

		return '' !== $value ? str_replace( '{site_name}', get_bloginfo( 'name', 'display' ), $value ) : self::original( $key );
	}

	/**
	 * Text of the link back to the site, for the preview.
	 *
	 * @return string
	 */
	public static function back_text() {
		$text = trim( (string) Settings::get( 'login.back_link_text' ) );
		$site = get_bloginfo( 'name', 'display' );

		/* translators: %s: site title. */
		return '' !== $text ? str_replace( '{site_name}', $site, $text ) : sprintf( __( '&larr; Go to %s', 'adminora' ), $site );
	}

	/**
	 * Hooks, called from the login module.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'login_init', array( __CLASS__, 'start' ) );
		add_filter( 'login_site_html_link', array( __CLASS__, 'back_link' ), 5 );
		add_filter( 'wp_login_errors', array( __CLASS__, 'errors' ) );
	}

	/**
	 * Builds the replacement map and starts translating.
	 *
	 * @return void
	 */
	public static function start() {
		$site = get_bloginfo( 'name', 'display' );

		foreach ( self::catalogue() as $key => $entry ) {
			$value = (string) Settings::get( 'login_words.' . $key );

			if ( '' === trim( $value ) ) {
				continue;
			}

			$value = str_replace( '{site_name}', $site, $value );

			if ( 'text' === $entry[1] ) {
				$value = esc_html( $value );
			}

			// Strings WordPress fills with sprintf(): keep one placeholder for the login address.
			if ( false !== strpos( $entry[2], '%s' ) ) {
				$value = str_replace( array( '%', '{login_url}' ), array( '%%', '%s' ), $value );
			}

			self::$map[ $entry[2] ] = $value;
		}

		if ( self::$map ) {
			add_filter( 'gettext', array( __CLASS__, 'gettext' ), 10, 3 );
		}
	}

	/**
	 * Swaps WordPress's text for the saved wording.
	 *
	 * @param string $translation Translated text.
	 * @param string $text        Original text.
	 * @param string $domain      Text domain.
	 * @return string
	 */
	public static function gettext( $translation, $text, $domain ) {
		if ( 'default' !== $domain || ! isset( self::$map[ $text ] ) ) {
			return $translation;
		}

		return self::$map[ $text ];
	}

	/**
	 * Replaces the "← Go to site" link text.
	 *
	 * @param string $link Link markup.
	 * @return string
	 */
	public static function back_link( $link ) {
		$text = (string) Settings::get( 'login.back_link_text' );

		if ( '' === trim( $text ) || '' === $link ) {
			return $link;
		}

		return '<a href="' . esc_url( home_url( '/' ) ) . '">' . esc_html( str_replace( '{site_name}', get_bloginfo( 'name', 'display' ), $text ) ) . '</a>';
	}

	/**
	 * One message for every failed log in, so the screen does not reveal
	 * whether a username exists.
	 *
	 * @param \WP_Error $errors Login errors.
	 * @return \WP_Error
	 */
	public static function errors( $errors ) {
		$message = (string) Settings::get( 'login.error_message' );

		if ( '' === trim( $message ) || ! is_wp_error( $errors ) ) {
			return $errors;
		}

		$replaced = false;

		foreach ( array( 'invalid_username', 'invalid_email', 'incorrect_password' ) as $code ) {
			if ( $errors->get_error_message( $code ) ) {
				$errors->remove( $code );
				$replaced = true;
			}
		}

		if ( $replaced ) {
			$errors->add( 'adminora_failed', esc_html( $message ) );
		}

		return $errors;
	}
}

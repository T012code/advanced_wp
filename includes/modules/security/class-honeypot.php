<?php
/**
 * A hidden field that people never fill in but spam bots often do.
 *
 * @package AdminStudio
 */

namespace AdminStudio\Modules\Security;

defined( 'ABSPATH' ) || exit;

/**
 * Honeypot field on the login, registration and lost password forms.
 */
class Honeypot {

	const FIELD = 'adminstudio_website';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'login_form', array( __CLASS__, 'field' ) );
		add_action( 'register_form', array( __CLASS__, 'field' ) );
		add_action( 'lostpassword_form', array( __CLASS__, 'field' ) );
		// After core's password check (priority 20), which would otherwise replace the error.
		add_filter( 'authenticate', array( __CLASS__, 'check_login' ), 100 );
		add_filter( 'registration_errors', array( __CLASS__, 'check_errors' ) );
		add_action( 'lostpassword_post', array( __CLASS__, 'check_errors' ) );
	}

	/**
	 * Prints the hidden field.
	 *
	 * @return void
	 */
	public static function field() {
		printf(
			'<p style="position:absolute;left:-10000px;top:auto;width:1px;height:1px;overflow:hidden;" aria-hidden="true"><label for="%1$s">%2$s</label><input type="text" name="%1$s" id="%1$s" value="" tabindex="-1" autocomplete="off"></p>',
			esc_attr( self::FIELD ),
			esc_html__( 'Leave this field empty', 'admin-studio' )
		);
	}

	/**
	 * Whether the hidden field was filled in.
	 *
	 * @return bool
	 */
	private static function tripped() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Login forms have no nonce; this only rejects.
		return ! empty( $_POST[ self::FIELD ] );
	}

	/**
	 * Rejects logins that filled the field.
	 *
	 * @param \WP_User|\WP_Error|null $user Result so far.
	 * @return \WP_User|\WP_Error|null
	 */
	public static function check_login( $user ) {
		return self::tripped() ? new \WP_Error( 'adminstudio_honeypot', __( '<strong>Error:</strong> Your request could not be processed.', 'admin-studio' ) ) : $user;
	}

	/**
	 * Adds an error to registration and lost password requests that filled the field.
	 *
	 * @param \WP_Error $errors Errors.
	 * @return \WP_Error
	 */
	public static function check_errors( $errors ) {
		if ( self::tripped() && $errors instanceof \WP_Error ) {
			$errors->add( 'adminstudio_honeypot', __( '<strong>Error:</strong> Your request could not be processed.', 'admin-studio' ) );
		}

		return $errors;
	}
}

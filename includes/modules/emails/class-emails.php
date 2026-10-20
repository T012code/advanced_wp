<?php
/**
 * Hooks into each core WordPress email and swaps in the branded version.
 *
 * Each email filter replaces the message with branded HTML carrying a marker.
 * A late `wp_mail` filter then sets the subject, the HTML content type and the
 * plain-text alternative for any message with that marker. Doing the subject
 * there avoids core's sprintf() on some subjects and covers emails that have
 * no subject filter at all.
 *
 * @package AdvancedWP
 */

namespace AdvancedWP\Modules\Emails;

use AdvancedWP\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Emails module.
 */
class Emails {

	/**
	 * Rendered emails waiting to be sent, keyed by marker id.
	 *
	 * @var array
	 */
	private static $pending = array();

	/**
	 * Plain-text body for the message being sent.
	 *
	 * @var string
	 */
	private static $alt_body = '';

	/**
	 * Template key => [ hook, callback, accepted args ].
	 *
	 * @return array
	 */
	private static function hooks() {
		return array(
			'welcome'                    => array( 'wp_new_user_notification_email', 'welcome', 3 ),
			'admin_new_user'             => array( 'wp_new_user_notification_email_admin', 'admin_new_user', 3 ),
			'password_reset'             => array( 'retrieve_password_notification_email', 'password_reset', 4 ),
			'password_changed'           => array( 'password_change_email', 'password_changed', 3 ),
			'email_changed'              => array( 'email_change_email', 'email_changed', 3 ),
			'email_change_confirm'       => array( 'new_user_email_content', 'email_change_confirm', 2 ),
			'admin_email_change_confirm' => array( 'new_admin_email_content', 'admin_email_change_confirm', 2 ),
			'admin_email_changed'        => array( 'site_admin_email_change_email', 'admin_email_changed', 3 ),
			'admin_password_changed'     => array( 'wp_password_change_notification_email', 'admin_password_changed', 2 ),
			'comment_notify'             => array( 'comment_notification_text', 'comment_notify', 2 ),
			'comment_moderation'         => array( 'comment_moderation_text', 'comment_moderation', 2 ),
			'data_export'                => array( 'wp_privacy_personal_data_email_content', 'data_export', 3 ),
			'data_erased'                => array( 'user_erasure_fulfillment_email_content', 'data_erased', 2 ),
			'recovery_mode'              => array( 'recovery_mode_email', 'recovery_mode', 2 ),
		);
	}

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		foreach ( self::hooks() as $key => $hook ) {
			if ( Settings::get( 'emails.templates.' . $key . '.enabled' ) ) {
				add_filter( $hook[0], array( __CLASS__, $hook[1] ), 99, $hook[2] );
			}
		}

		if ( Settings::get( 'emails.templates.auto_update.enabled' ) ) {
			add_filter( 'auto_core_update_email', array( __CLASS__, 'auto_update' ), 99 );
			add_filter( 'auto_plugin_theme_update_email', array( __CLASS__, 'auto_update' ), 99 );
		}

		add_filter( 'wp_mail', array( __CLASS__, 'finalize' ), 1000 );
		add_action( 'phpmailer_init', array( __CLASS__, 'alt_body' ) );
	}

	/**
	 * Renders a template and returns the HTML with a marker for finalize().
	 *
	 * @param string $key     Template key.
	 * @param array  $context Tag values.
	 * @return string
	 */
	public static function queue( $key, array $context ) {
		$email = Renderer::render( $key, $context );
		$id    = $key . '-' . count( self::$pending );

		self::$pending[ $id ] = $email;

		return str_replace( '<!-- advwp-email -->', '<!-- advwp-email:' . $id . ' -->', $email['html'] );
	}

	/**
	 * Sets subject, content type and plain-text body for branded messages.
	 *
	 * @param array $args wp_mail() arguments.
	 * @return array
	 */
	public static function finalize( $args ) {
		self::$alt_body = '';

		if ( ! is_string( $args['message'] ) || ! preg_match( '/<!-- advwp-email:([a-z0-9_-]+) -->/', $args['message'], $m ) || ! isset( self::$pending[ $m[1] ] ) ) {
			return $args;
		}

		$email = self::$pending[ $m[1] ];

		$args['subject'] = $email['subject'];
		$args['headers'] = self::html_headers( $args['headers'] );
		self::$alt_body  = $email['text'];

		return $args;
	}

	/**
	 * Replaces any Content-Type header with text/html.
	 *
	 * @param string|array $headers Headers.
	 * @return array
	 */
	private static function html_headers( $headers ) {
		if ( ! is_array( $headers ) ) {
			$headers = '' === trim( (string) $headers ) ? array() : explode( "\n", str_replace( "\r\n", "\n", $headers ) );
		}

		$headers = array_filter(
			$headers,
			static function ( $header ) {
				return '' !== trim( $header ) && 0 !== stripos( trim( $header ), 'content-type:' );
			}
		);

		$headers[] = 'Content-Type: text/html; charset=UTF-8';

		return array_values( $headers );
	}

	/**
	 * Adds the plain-text alternative.
	 *
	 * @param \PHPMailer\PHPMailer\PHPMailer $phpmailer Mailer.
	 * @return void
	 */
	public static function alt_body( $phpmailer ) {
		if ( '' !== self::$alt_body ) {
			$phpmailer->AltBody = self::$alt_body; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			self::$alt_body     = '';
		}
	}

	/**
	 * Finds the password link core put in the original message.
	 *
	 * @param string $message Original message.
	 * @return string
	 */
	private static function find_rp_link( $message ) {
		return preg_match( '#(https?://[^\s<>"]+action=rp[^\s<>"]*)#', (string) $message, $m ) ? $m[1] : wp_login_url();
	}

	/**
	 * New account email to the user.
	 *
	 * @param array    $email Email args.
	 * @param \WP_User $user  User.
	 * @return array
	 */
	public static function welcome( $email, $user ) {
		$email['message'] = self::queue(
			'welcome',
			array_merge( Renderer::user_context( $user ), array( 'set_password_link' => self::find_rp_link( $email['message'] ) ) )
		);

		return $email;
	}

	/**
	 * New user email to the admin.
	 *
	 * @param array    $email Email args.
	 * @param \WP_User $user  User.
	 * @return array
	 */
	public static function admin_new_user( $email, $user ) {
		$email['message'] = self::queue(
			'admin_new_user',
			array_merge( Renderer::user_context( $user ), array( 'user_edit_link' => admin_url( 'user-edit.php?user_id=' . $user->ID ) ) )
		);

		return $email;
	}

	/**
	 * Password reset request.
	 *
	 * @param array    $email      Email args.
	 * @param string   $key        Reset key.
	 * @param string   $user_login Username.
	 * @param \WP_User $user       User.
	 * @return array
	 */
	public static function password_reset( $email, $key, $user_login, $user ) {
		$link = network_site_url( 'wp-login.php?action=rp&key=' . $key . '&login=' . rawurlencode( $user_login ), 'login' );

		if ( ! is_user_logged_in() ) {
			$link .= '&wp_lang=' . get_user_locale( $user );
		}

		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		$email['message'] = self::queue(
			'password_reset',
			array_merge(
				Renderer::user_context( $user ),
				array(
					'reset_link' => $link,
					'ip_address' => $ip,
				)
			)
		);

		return $email;
	}

	/**
	 * Password changed notice to the user.
	 *
	 * @param array $email Email args.
	 * @param array $user  Old user data.
	 * @return array
	 */
	public static function password_changed( $email, $user ) {
		$email['message'] = self::queue( 'password_changed', Renderer::user_context( $user ) );

		return $email;
	}

	/**
	 * Email changed notice, sent to the old address.
	 *
	 * @param array $email    Email args.
	 * @param array $user     Old user data.
	 * @param array $userdata New user data.
	 * @return array
	 */
	public static function email_changed( $email, $user, $userdata ) {
		$email['message'] = self::queue(
			'email_changed',
			array_merge(
				Renderer::user_context( $user ),
				array(
					'old_email' => isset( $user['user_email'] ) ? $user['user_email'] : '',
					'new_email' => isset( $userdata['user_email'] ) ? $userdata['user_email'] : '',
				)
			)
		);

		return $email;
	}

	/**
	 * Confirmation sent to a new address from the profile screen.
	 *
	 * @param string $text Email text.
	 * @param array  $data Hash and new email.
	 * @return string
	 */
	public static function email_change_confirm( $text, $data ) {
		return self::queue(
			'email_change_confirm',
			array_merge(
				Renderer::user_context( wp_get_current_user() ),
				array(
					'new_email'    => $data['newemail'],
					'confirm_link' => admin_url( 'profile.php?newuseremail=' . $data['hash'] ),
				)
			)
		);
	}

	/**
	 * Confirmation for a new site admin email.
	 *
	 * @param string $text Email text.
	 * @param array  $data Hash and new email.
	 * @return string
	 */
	public static function admin_email_change_confirm( $text, $data ) {
		return self::queue(
			'admin_email_change_confirm',
			array(
				'new_email'    => $data['newemail'],
				'confirm_link' => admin_url( 'options.php?adminhash=' . $data['hash'] ),
			)
		);
	}

	/**
	 * Notice to the old site admin address.
	 *
	 * @param array  $email     Email args.
	 * @param string $old_email Old address.
	 * @param string $new_email New address.
	 * @return array
	 */
	public static function admin_email_changed( $email, $old_email, $new_email ) {
		$email['message'] = self::queue(
			'admin_email_changed',
			array(
				'old_email' => $old_email,
				'new_email' => $new_email,
			)
		);

		return $email;
	}

	/**
	 * Notice to the admin that a user reset their password.
	 *
	 * @param array    $email Email args.
	 * @param \WP_User $user  User.
	 * @return array
	 */
	public static function admin_password_changed( $email, $user ) {
		$email['message'] = self::queue( 'admin_password_changed', Renderer::user_context( $user ) );

		return $email;
	}

	/**
	 * Context for comment emails.
	 *
	 * @param int $comment_id Comment ID.
	 * @return array
	 */
	private static function comment_context( $comment_id ) {
		$comment = get_comment( $comment_id );

		if ( ! $comment ) {
			return array();
		}

		$post = get_post( $comment->comment_post_ID );

		return array(
			'post_title'           => $post ? get_the_title( $post ) : '',
			'post_link'            => $post ? get_permalink( $post ) : home_url( '/' ),
			'comment_author'       => $comment->comment_author,
			'comment_author_email' => $comment->comment_author_email,
			'comment_content'      => wpautop( esc_html( wp_specialchars_decode( $comment->comment_content ) ) ),
			'comment_link'         => get_comment_link( $comment ),
			'moderate_link'        => admin_url( 'comment.php?action=editcomment&c=' . $comment->comment_ID ),
			'pending_count'        => (string) get_comment_count()['awaiting_moderation'],
		);
	}

	/**
	 * New comment email to the post author.
	 *
	 * @param string $text       Email text.
	 * @param int    $comment_id Comment ID.
	 * @return string
	 */
	public static function comment_notify( $text, $comment_id ) {
		return self::queue( 'comment_notify', self::comment_context( $comment_id ) );
	}

	/**
	 * Comment moderation email.
	 *
	 * @param string $text       Email text.
	 * @param int    $comment_id Comment ID.
	 * @return string
	 */
	public static function comment_moderation( $text, $comment_id ) {
		return self::queue( 'comment_moderation', self::comment_context( $comment_id ) );
	}

	/**
	 * Personal data export ready.
	 *
	 * @param string $text       Email text.
	 * @param int    $request_id Request ID.
	 * @param array  $data       Email data.
	 * @return string
	 */
	public static function data_export( $text, $request_id, $data ) {
		return self::queue(
			'data_export',
			array(
				'user_email'    => isset( $data['message_recipient'] ) ? $data['message_recipient'] : '',
				'download_link' => isset( $data['export_file_url'] ) ? $data['export_file_url'] : '',
				'expiry_date'   => isset( $data['expiration_date'] ) ? $data['expiration_date'] : '',
			)
		);
	}

	/**
	 * Personal data erased.
	 *
	 * @param string $text Email text.
	 * @param array  $data Email data.
	 * @return string
	 */
	public static function data_erased( $text, $data ) {
		return self::queue(
			'data_erased',
			array(
				'user_email'         => isset( $data['message_recipient'] ) ? $data['message_recipient'] : '',
				'privacy_policy_url' => ! empty( $data['privacy_policy_url'] ) ? $data['privacy_policy_url'] : home_url( '/' ),
			)
		);
	}

	/**
	 * Automatic update reports, for core, plugins and themes.
	 *
	 * @param array $email Email args with a `body` key.
	 * @return array
	 */
	public static function auto_update( $email ) {
		$email['body'] = self::queue(
			'auto_update',
			array(
				'update_subject' => wp_specialchars_decode( $email['subject'], ENT_QUOTES ),
				'update_details' => wpautop( make_clickable( esc_html( $email['body'] ) ) ),
				'updates_link'   => admin_url( 'update-core.php' ),
			)
		);

		return $email;
	}

	/**
	 * Recovery mode email.
	 *
	 * @param array  $email Email args.
	 * @param string $url   Recovery URL.
	 * @return array
	 */
	public static function recovery_mode( $email, $url ) {
		$email['message'] = self::queue(
			'recovery_mode',
			array(
				'recovery_link'     => $url,
				'technical_details' => '<pre>' . esc_html( $email['message'] ) . '</pre>',
			)
		);

		return $email;
	}

	/**
	 * Sends a template filled with sample data.
	 *
	 * @param string $key Template key.
	 * @param string $to  Recipient.
	 * @return bool
	 */
	public static function send_test( $key, $to ) {
		$html = self::queue( $key, Renderer::sample_context() );

		$hooked = has_filter( 'wp_mail', array( __CLASS__, 'finalize' ) );

		if ( ! $hooked ) {
			add_filter( 'wp_mail', array( __CLASS__, 'finalize' ), 1000 );
			add_action( 'phpmailer_init', array( __CLASS__, 'alt_body' ) );
		}

		return wp_mail( $to, '', $html );
	}
}

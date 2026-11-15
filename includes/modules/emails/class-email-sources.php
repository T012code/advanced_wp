<?php
/**
 * Finds WordPress emails that the theme or another plugin has already
 * customised, shows what that code produces, and copies it into this
 * plugin's templates on request.
 *
 * Each WordPress email has its own filters (for example
 * retrieve_password_message for the password reset email). Any callback on
 * those filters that is not part of WordPress or of this plugin is a
 * customisation. To preview it, only those callbacks are run, on sample
 * input made for a made-up person, so the result can be turned back into
 * {tags} and saved as a template that works for everyone.
 *
 * @package LayoDT
 */

namespace LayoDT\Modules\Emails;

use LayoDT\Login_Pages;
use LayoDT\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Email customisation detection.
 */
class Email_Sources {

	const COPIED = 'layodt_email_copied';

	/**
	 * Sample person used for previews, so their details can be told apart
	 * from the rest of the text and turned back into tags.
	 */
	const SAMPLE_LOGIN = 'sample-person-4821';
	const SAMPLE_NAME  = 'Sample Person 4821';
	const SAMPLE_EMAIL = 'sample.person.4821@example.com';
	const SAMPLE_KEY   = 'SampleKey4821';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_post_layodt_email_copy', array( __CLASS__, 'handle_copy' ) );
		add_action( 'admin_post_layodt_email_design', array( __CLASS__, 'handle_design' ) );
	}

	/**
	 * Filters that shape each email: [ message filter, subject filter or '', kind ].
	 * Kind "array" filters receive and return the whole email as an array.
	 *
	 * @return array
	 */
	private static function filters() {
		return array(
			'welcome'                    => array( 'wp_new_user_notification_email', '', 'array' ),
			'admin_new_user'             => array( 'wp_new_user_notification_email_admin', '', 'array' ),
			'password_reset'             => array( 'retrieve_password_message', 'retrieve_password_title', 'text' ),
			'password_changed'           => array( 'password_change_email', '', 'array' ),
			'email_changed'              => array( 'email_change_email', '', 'array' ),
			'email_change_confirm'       => array( 'new_user_email_content', '', 'text' ),
			'admin_email_change_confirm' => array( 'new_admin_email_content', '', 'text' ),
			'admin_email_changed'        => array( 'site_admin_email_change_email', '', 'array' ),
			'admin_password_changed'     => array( 'wp_password_change_notification_email', '', 'array' ),
			'comment_notify'             => array( 'comment_notification_text', 'comment_notification_subject', 'text' ),
			'comment_moderation'         => array( 'comment_moderation_text', 'comment_moderation_subject', 'text' ),
			'data_export'                => array( 'wp_privacy_personal_data_email_content', 'wp_privacy_personal_data_email_subject', 'text' ),
			'data_erased'                => array( 'user_erasure_fulfillment_email_content', 'user_erasure_fulfillment_email_subject', 'text' ),
			'recovery_mode'              => array( 'recovery_mode_email', '', 'array' ),
		);
	}

	/**
	 * Every customised email: key => [ sources, hooks ].
	 *
	 * @return array
	 */
	public static function customised() {
		$found = array();

		foreach ( self::filters() as $key => $info ) {
			$hooks = array_filter( array( $info[0], $info[1], 'password_reset' === $key ? 'retrieve_password_notification_email' : '' ) );
			$names = array();

			foreach ( $hooks as $hook ) {
				foreach ( self::outside_callbacks( $hook ) as $callback ) {
					$names[] = Login_Pages::describe_file( Login_Pages::file_of( $callback['function'] ) );
				}
			}

			if ( $names ) {
				$found[ $key ] = array(
					'sources' => array_values( array_unique( $names ) ),
					'hooks'   => array_values( $hooks ),
				);
			}
		}

		return $found;
	}

	/**
	 * Callbacks on a hook that come from neither WordPress nor this plugin.
	 *
	 * @param string $hook Hook name.
	 * @return array
	 */
	private static function outside_callbacks( $hook ) {
		global $wp_filter;

		$out = array();

		if ( ! isset( $wp_filter[ $hook ] ) || ! $wp_filter[ $hook ] instanceof \WP_Hook ) {
			return $out;
		}

		$core = array( wp_normalize_path( ABSPATH . WPINC ), wp_normalize_path( ABSPATH . 'wp-admin' ) );

		foreach ( $wp_filter[ $hook ]->callbacks as $callbacks ) {
			foreach ( $callbacks as $callback ) {
				$file = wp_normalize_path( Login_Pages::file_of( $callback['function'] ) );

				if ( '' === $file || Login_Pages::is_ours( $file ) || 0 === strpos( $file, $core[0] ) || 0 === strpos( $file, $core[1] ) ) {
					continue;
				}

				$out[] = $callback;
			}
		}

		return $out;
	}

	/**
	 * Runs the outside callbacks of a hook on a value.
	 *
	 * @param string $hook  Hook name.
	 * @param mixed  $value Value.
	 * @param array  $args  Extra arguments.
	 * @return mixed
	 */
	private static function run( $hook, $value, array $args ) {
		foreach ( self::outside_callbacks( $hook ) as $callback ) {
			try {
				$value = call_user_func_array( $callback['function'], array_slice( array_merge( array( $value ), $args ), 0, max( 1, (int) $callback['accepted_args'] ) ) );
			} catch ( \Throwable $e ) {
				continue;
			}
		}

		return $value;
	}

	// ------------------------------------------------------------------
	// Previews.
	// ------------------------------------------------------------------

	/**
	 * The made-up person the previews are written for.
	 *
	 * @return \WP_User
	 */
	private static function sample_user() {
		$user                = new \WP_User();
		$user->ID            = 0;
		$user->user_login    = self::SAMPLE_LOGIN;
		$user->user_email    = self::SAMPLE_EMAIL;
		$user->display_name  = self::SAMPLE_NAME;
		$user->user_nicename = self::SAMPLE_LOGIN;

		return $user;
	}

	/**
	 * Values the previews use, by the tag they stand for.
	 *
	 * @return array Tag => value.
	 */
	private static function sample_values() {
		$login = untrailingslashit( (string) get_option( 'siteurl' ) ) . '/wp-login.php';

		return array(
			'set_password_link' => $login . '?action=rp&key=' . self::SAMPLE_KEY . '&login=' . self::SAMPLE_LOGIN,
			'reset_link'        => $login . '?action=rp&key=' . self::SAMPLE_KEY . '&login=' . self::SAMPLE_LOGIN,
			'confirm_link'      => admin_url( 'profile.php?newuseremail=' . self::SAMPLE_KEY ),
			'user_edit_link'    => admin_url( 'user-edit.php?user_id=4821' ),
			'new_email'         => 'new.address.4821@example.com',
			'old_email'         => 'old.address.4821@example.com',
			'ip_address'        => '203.0.113.42',
			'user_name'         => self::SAMPLE_LOGIN,
			'user_email'        => self::SAMPLE_EMAIL,
			'display_name'      => self::SAMPLE_NAME,
			'login_url'         => wp_login_url(),
			'admin_url'         => admin_url(),
			'admin_email'       => (string) get_option( 'admin_email' ),
			'site_name'         => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			'site_url'          => home_url( '/' ),
		);
	}

	/**
	 * What the outside code makes of one email, written for the sample person.
	 *
	 * @param string $key Template key.
	 * @return array|null { subject, message } or null when it cannot be shown.
	 */
	public static function sample( $key ) {
		$filters = self::filters();

		if ( ! isset( $filters[ $key ] ) ) {
			return null;
		}

		list( $hook, $subject_hook, $kind ) = $filters[ $key ];

		$v        = self::sample_values();
		$user     = self::sample_user();
		$blogname = $v['site_name'];
		$userdata = array(
			'ID'           => 0,
			'user_login'   => self::SAMPLE_LOGIN,
			'user_email'   => self::SAMPLE_EMAIL,
			'display_name' => self::SAMPLE_NAME,
		);

		// What WordPress itself would send, closely enough for code that edits it.
		$subjects = array(
			/* translators: %s: site title. */
			'welcome'                => sprintf( __( '[%s] Login Details', 'layo-dashboard-toolkit' ), $blogname ),
			/* translators: %s: site title. */
			'admin_new_user'         => sprintf( __( '[%s] New User Registration', 'layo-dashboard-toolkit' ), $blogname ),
			/* translators: %s: site title. */
			'password_reset'         => sprintf( __( '[%s] Password Reset', 'layo-dashboard-toolkit' ), $blogname ),
			/* translators: %s: site title. */
			'password_changed'       => sprintf( __( '[%s] Password Changed', 'layo-dashboard-toolkit' ), $blogname ),
			/* translators: %s: site title. */
			'email_changed'          => sprintf( __( '[%s] Email Changed', 'layo-dashboard-toolkit' ), $blogname ),
			/* translators: %s: site title. */
			'admin_email_changed'    => sprintf( __( '[%s] Admin Email Changed', 'layo-dashboard-toolkit' ), $blogname ),
			/* translators: %s: site title. */
			'admin_password_changed' => sprintf( __( '[%s] Password Changed', 'layo-dashboard-toolkit' ), $blogname ),
			/* translators: %s: site title. */
			'recovery_mode'          => sprintf( __( '[%s] Your Site is Experiencing a Technical Issue', 'layo-dashboard-toolkit' ), $blogname ),
		);

		/* translators: 1: username, 2: address. */
		$message = sprintf( __( "Username: %1\$s\r\n\r\nTo continue, visit the following address:\r\n\r\n%2\$s\r\n", 'layo-dashboard-toolkit' ), self::SAMPLE_LOGIN, $v['reset_link'] );
		$subject = isset( $subjects[ $key ] ) ? $subjects[ $key ] : $blogname;

		if ( 'array' === $kind ) {
			$email = array(
				'to'      => self::SAMPLE_EMAIL,
				'subject' => $subject,
				'message' => $message,
				'headers' => '',
			);

			switch ( $key ) {
				case 'welcome':
				case 'admin_new_user':
				case 'admin_password_changed':
					$out = self::run( $hook, $email, array( $user, $blogname ) );
					break;
				case 'password_changed':
				case 'email_changed':
					$out = self::run( $hook, $email, array( $userdata, $userdata ) );
					break;
				case 'admin_email_changed':
					$out = self::run( $hook, $email, array( $v['old_email'], $v['new_email'] ) );
					break;
				default:
					$out = self::run( $hook, $email, array( admin_url() ) );
			}

			return is_array( $out ) && isset( $out['message'] ) ? array(
				'subject' => isset( $out['subject'] ) ? (string) $out['subject'] : $subject,
				'message' => (string) $out['message'],
			) : null;
		}

		switch ( $key ) {
			case 'password_reset':
				$text    = self::run( $hook, $message, array( self::SAMPLE_KEY, self::SAMPLE_LOGIN, $user ) );
				$subject = self::run( $subject_hook, $subject, array( self::SAMPLE_LOGIN, $user ) );
				$email   = self::run(
					'retrieve_password_notification_email',
					array(
						'to'      => self::SAMPLE_EMAIL,
						'subject' => $subject,
						'message' => $text,
						'headers' => '',
					),
					array( self::SAMPLE_KEY, self::SAMPLE_LOGIN, $user )
				);
				return is_array( $email ) ? array(
					'subject' => (string) $email['subject'],
					'message' => (string) $email['message'],
				) : null;

			case 'email_change_confirm':
			case 'admin_email_change_confirm':
				$text = self::run(
					$hook,
					"###USERNAME###\n\n###EMAIL###\n\n###ADMIN_URL###\n\n###SITENAME###\n\n###SITEURL###",
					array(
						array(
							'hash'     => self::SAMPLE_KEY,
							'newemail' => $v['new_email'],
						),
					)
				);
				$text = str_replace(
					array( '###USERNAME###', '###EMAIL###', '###ADMIN_URL###', '###SITENAME###', '###SITEURL###' ),
					array( self::SAMPLE_LOGIN, $v['new_email'], $v['confirm_link'], $blogname, $v['site_url'] ),
					(string) $text
				);
				return array(
					'subject' => $subject,
					'message' => $text,
				);

			default:
				// Comment and privacy emails need real records, which a preview should not touch.
				return null;
		}
	}

	/**
	 * Turns the sample values in a customised email back into tags.
	 *
	 * @param string $text Text.
	 * @param string $key  Template key, to limit tags to the ones it supports.
	 * @return string
	 */
	private static function to_tags( $text, $key ) {
		$template = Templates::get( $key );
		$allowed  = array_merge( Templates::common_tags(), $template ? $template['tags'] : array() );
		$values   = array_intersect_key( self::sample_values(), array_flip( $allowed ) );

		// Longest first, so addresses are replaced before the shorter values inside them.
		uasort(
			$values,
			static function ( $a, $b ) {
				return strlen( $b ) - strlen( $a );
			}
		);

		foreach ( $values as $tag => $value ) {
			if ( '' === $value ) {
				continue;
			}
			$text = str_replace( array( $value, esc_url( $value ), esc_html( $value ) ), '{' . $tag . '}', $text );
		}

		return $text;
	}

	/**
	 * A customised message as template body HTML.
	 *
	 * @param string $message Message, HTML or plain text.
	 * @return string
	 */
	private static function to_body( $message ) {
		if ( preg_match( '#<body[^>]*>(.*)</body>#is', $message, $m ) ) {
			$message = $m[1];
		}

		if ( wp_strip_all_tags( $message ) === $message ) {
			// Plain text: keep the line breaks and make addresses clickable.
			$message = wpautop( esc_html( trim( $message ) ) );
			$message = preg_replace( '#(?<![">])\{([a-z_]+_(?:link|url))\}#', '<a href="{$1}">{$1}</a>', $message );
		}

		return wp_kses_post( $message );
	}

	/**
	 * Copies a customised email into this plugin's template.
	 *
	 * @param string $key Template key.
	 * @return bool
	 */
	public static function copy( $key ) {
		$sample = self::sample( $key );

		if ( ! $sample || ! Templates::get( $key ) ) {
			return false;
		}

		$template = Templates::get( $key );
		$prefix   = 'emails.templates.' . $key . '.';
		$body     = self::to_body( self::to_tags( $sample['message'], $key ) );
		$values   = array(
			$prefix . 'subject' => wp_strip_all_tags( self::to_tags( $sample['subject'], $key ) ),
			$prefix . 'body'    => $body,
		);

		// The link is in the copied text already, so a second button would repeat it.
		if ( '' !== $template['link'] && false !== strpos( $body, '{' . $template['link'] . '}' ) ) {
			$values[ $prefix . 'button' ] = '';
		}

		Settings::update( $values );

		$copied         = get_option( self::COPIED, array() );
		$copied[ $key ] = md5( (string) wp_json_encode( $sample ) );
		update_option( self::COPIED, $copied, false );

		return true;
	}

	/**
	 * Whether the email was copied in and the other code still makes the
	 * same thing.
	 *
	 * @param string     $key    Template key.
	 * @param array|null $sample Current sample.
	 * @return bool
	 */
	public static function is_copied( $key, $sample ) {
		$copied = get_option( self::COPIED, array() );

		return $sample && isset( $copied[ $key ] ) && md5( (string) wp_json_encode( $sample ) ) === $copied[ $key ];
	}

	// ------------------------------------------------------------------
	// Whole-email designs from other plugins.
	// ------------------------------------------------------------------

	/**
	 * Which design to keep when another plugin also wraps emails: "mine",
	 * "theirs" or "" (not chosen yet).
	 *
	 * @return string
	 */
	public static function design_choice() {
		$choice = get_option( Emails::DESIGN, '' );

		return in_array( $choice, array( 'mine', 'theirs' ), true ) ? $choice : '';
	}

	// ------------------------------------------------------------------
	// Handlers.
	// ------------------------------------------------------------------

	/**
	 * Copies one customised email into its template.
	 *
	 * @return void
	 */
	public static function handle_copy() {
		check_admin_referer( 'layodt_email_copy' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'layo-dashboard-toolkit' ), '', array( 'response' => 403 ) );
		}

		$key  = isset( $_POST['template'] ) ? sanitize_key( wp_unslash( $_POST['template'] ) ) : '';
		$done = Templates::get( $key ) && self::copy( $key );

		wp_safe_redirect(
			\LayoDT\Admin\Admin::url(
				'emails',
				array(
					'template'      => $key,
					'layodt_notice' => $done ? 'email_copied' : 'email_copy_failed',
				)
			)
		);
		exit;
	}

	/**
	 * Saves which email design to keep.
	 *
	 * @return void
	 */
	public static function handle_design() {
		check_admin_referer( 'layodt_email_design' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'layo-dashboard-toolkit' ), '', array( 'response' => 403 ) );
		}

		$choice = isset( $_POST['layodt_design'] ) ? sanitize_key( wp_unslash( $_POST['layodt_design'] ) ) : '';

		if ( in_array( $choice, array( 'mine', 'theirs' ), true ) ) {
			update_option( Emails::DESIGN, $choice, false );
		}

		wp_safe_redirect( \LayoDT\Admin\Admin::url( 'emails', array( 'layodt_notice' => 'saved' ) ) );
		exit;
	}
}

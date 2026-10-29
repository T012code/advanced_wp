<?php
/**
 * Signed-in sessions: who is logged in and on which device, a limit on
 * sessions per user, blocking, forced sign-out and a sign-in history.
 *
 * Sessions are WordPress's own (the session_tokens user meta that backs every
 * login cookie), so ending one here signs that device out at once.
 *
 * @package Adminora
 */

namespace Adminora\Modules\Sessions;

use Adminora\Admin\Admin;
use Adminora\Settings;
use Adminora\Modules\Security\Attempts;

defined( 'ABSPATH' ) || exit;

/**
 * Sessions module.
 */
class Sessions {

	const META_BLOCKED = 'adminora_blocked';
	const HISTORY      = 'adminora_signins';
	const HISTORY_MAX  = 1000;

	/**
	 * Seconds between "last active" updates for one session.
	 */
	const TOUCH_EVERY = 300;

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'attach_session_information', array( __CLASS__, 'attach' ), 10, 2 );
		add_filter( 'authenticate', array( __CLASS__, 'gate' ), 90 );
		add_action( 'init', array( __CLASS__, 'touch' ) );
		add_action( 'wp_logout', array( __CLASS__, 'on_logout' ) );
		add_action( 'wp_login_failed', array( __CLASS__, 'on_failed' ), 10, 2 );
		add_filter( 'allow_password_reset', array( __CLASS__, 'allow_reset' ), 10, 2 );
		add_filter( 'wp_is_application_passwords_available_for_user', array( __CLASS__, 'allow_app_passwords' ), 10, 2 );

		if ( is_admin() ) {
			add_action( 'admin_init', array( __CLASS__, 'privacy_text' ) );
			add_filter( 'manage_users_columns', array( __CLASS__, 'users_column' ) );
			add_filter( 'manage_users_custom_column', array( __CLASS__, 'users_cell' ), 10, 3 );
			add_filter( 'user_row_actions', array( __CLASS__, 'row_actions' ), 10, 2 );
		}
	}

	// ------------------------------------------------------------------
	// Reading and ending sessions.
	// ------------------------------------------------------------------

	/**
	 * A user's sessions that have not expired.
	 *
	 * @param int $user_id User ID.
	 * @return array Verifier => session, oldest sign-in first.
	 */
	public static function of( $user_id ) {
		$all = get_user_meta( $user_id, 'session_tokens', true );

		if ( ! is_array( $all ) ) {
			return array();
		}

		$now    = time();
		$active = array_filter(
			$all,
			static function ( $session ) use ( $now ) {
				return is_array( $session ) && isset( $session['expiration'] ) && $session['expiration'] >= $now;
			}
		);

		uasort(
			$active,
			static function ( $a, $b ) {
				return ( isset( $a['login'] ) ? $a['login'] : 0 ) - ( isset( $b['login'] ) ? $b['login'] : 0 );
			}
		);

		return $active;
	}

	/**
	 * Everyone with at least one active session.
	 *
	 * @param string $search Optional name, username or email filter.
	 * @return array List of [ user, sessions ].
	 */
	public static function signed_in( $search = '' ) {
		$args = array(
			'meta_key' => 'session_tokens', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Admin-only list.
			'number'   => 200,
			'orderby'  => 'display_name',
		);

		if ( '' !== $search ) {
			$args['search']         = '*' . $search . '*';
			$args['search_columns'] = array( 'user_login', 'user_email', 'display_name' );
		}

		$rows = array();

		foreach ( get_users( $args ) as $user ) {
			$sessions = self::of( $user->ID );
			if ( $sessions ) {
				$rows[] = array( $user, $sessions );
			}
		}

		return $rows;
	}

	/**
	 * Ends one session.
	 *
	 * @param int    $user_id  User ID.
	 * @param string $verifier Session key.
	 * @return bool Whether a session was ended.
	 */
	public static function end( $user_id, $verifier ) {
		$all = get_user_meta( $user_id, 'session_tokens', true );

		if ( ! is_array( $all ) || ! isset( $all[ $verifier ] ) ) {
			return false;
		}

		unset( $all[ $verifier ] );

		if ( $all ) {
			update_user_meta( $user_id, 'session_tokens', $all );
		} else {
			delete_user_meta( $user_id, 'session_tokens' );
		}

		return true;
	}

	/**
	 * Ends every session of a user.
	 *
	 * @param int $user_id User ID.
	 * @return int Number of sessions ended.
	 */
	public static function end_all( $user_id ) {
		$count = count( self::of( $user_id ) );

		\WP_Session_Tokens::get_instance( $user_id )->destroy_all();

		return $count;
	}

	/**
	 * Key of the session making this request.
	 *
	 * @return string
	 */
	public static function current_verifier() {
		$token = wp_get_session_token();

		return '' !== $token ? hash( 'sha256', $token ) : '';
	}

	// ------------------------------------------------------------------
	// Blocking.
	// ------------------------------------------------------------------

	/**
	 * Whether a user is blocked.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public static function is_blocked( $user_id ) {
		return (bool) get_user_meta( $user_id, self::META_BLOCKED, true );
	}

	/**
	 * Blocks a user and signs them out everywhere.
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	public static function block( $user_id ) {
		update_user_meta(
			$user_id,
			self::META_BLOCKED,
			array(
				'time' => time(),
				'by'   => get_current_user_id(),
			)
		);

		self::end_all( $user_id );
		self::log( 'blocked', $user_id );
	}

	/**
	 * Lets a blocked user sign in again.
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	public static function unblock( $user_id ) {
		delete_user_meta( $user_id, self::META_BLOCKED );
		self::log( 'unblocked', $user_id );
	}

	/**
	 * Blocked users with when and by whom.
	 *
	 * @return array List of [ user, time, blocked by user or null ].
	 */
	public static function blocked() {
		$out = array();

		foreach ( get_users( array( 'meta_key' => self::META_BLOCKED ) ) as $user ) { // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Admin-only list.
			$data  = get_user_meta( $user->ID, self::META_BLOCKED, true );
			$out[] = array(
				$user,
				is_array( $data ) && isset( $data['time'] ) ? (int) $data['time'] : 0,
				is_array( $data ) && ! empty( $data['by'] ) ? get_userdata( (int) $data['by'] ) : null,
			);
		}

		return $out;
	}

	// ------------------------------------------------------------------
	// Login hooks.
	// ------------------------------------------------------------------

	/**
	 * Session limit for a user, 0 for none.
	 *
	 * @param \WP_User $user User.
	 * @return int
	 */
	public static function limit_for( $user ) {
		$limit = (int) Settings::get( 'sessions.limit' );

		if ( $limit < 1 ) {
			return 0;
		}

		if ( Settings::get( 'sessions.exempt_admins' ) && user_can( $user, 'manage_options' ) ) {
			return 0;
		}

		$roles = (array) Settings::get( 'sessions.limit_roles' );

		if ( $roles && ! array_intersect( $roles, (array) $user->roles ) ) {
			return 0;
		}

		return $limit;
	}

	/**
	 * Refuses blocked users, and new sign-ins over the limit when the site
	 * is set to refuse them. Runs after the password has been checked.
	 *
	 * @param \WP_User|\WP_Error|null $user Result so far.
	 * @return \WP_User|\WP_Error|null
	 */
	public static function gate( $user ) {
		if ( ! $user instanceof \WP_User ) {
			return $user;
		}

		if ( self::is_blocked( $user->ID ) ) {
			self::log( 'refused_blocked', $user->ID );

			return new \WP_Error( 'adminora_blocked', esc_html__( 'This account has been blocked. Contact the site administrator.', 'adminora' ) );
		}

		$limit = self::limit_for( $user );

		if ( $limit && 'block' === Settings::get( 'sessions.limit_mode' ) && count( self::of( $user->ID ) ) >= $limit ) {
			self::log( 'refused_limit', $user->ID );

			return new \WP_Error(
				'adminora_session_limit',
				esc_html(
					sprintf(
						/* translators: %d: number of devices. */
						_n( 'You are already signed in on %d device, the most this site allows. Sign out there first, or ask an administrator to sign you out.', 'You are already signed in on %d devices, the most this site allows. Sign out on one of them first, or ask an administrator to sign you out.', $limit, 'adminora' ),
						$limit
					)
				)
			);
		}

		return $user;
	}

	/**
	 * Runs as WordPress creates a session (after the password and any
	 * two-factor step): applies the "sign out the oldest" limit, stores the
	 * visitor's address and records the sign-in.
	 *
	 * @param array $session Session data.
	 * @param int   $user_id User ID.
	 * @return array
	 */
	public static function attach( $session, $user_id ) {
		$user  = get_userdata( $user_id );
		$limit = $user ? self::limit_for( $user ) : 0;

		if ( $limit && 'block' !== Settings::get( 'sessions.limit_mode' ) ) {
			$active = self::of( $user_id );
			$excess = count( $active ) - $limit + 1;

			foreach ( array_keys( $active ) as $verifier ) {
				if ( $excess-- <= 0 ) {
					break;
				}
				self::log( 'ended_limit', $user_id, $active[ $verifier ] );
				self::end( $user_id, $verifier );
			}
		}

		$session['ip']        = Attempts::ip();
		$session['last_seen'] = time();

		self::log( 'login', $user_id, $session );

		return $session;
	}

	/**
	 * Updates "last active" for the current session, at most every five minutes.
	 *
	 * @return void
	 */
	public static function touch() {
		if ( ! is_user_logged_in() ) {
			return;
		}

		$token = wp_get_session_token();

		if ( '' === $token ) {
			return;
		}

		$manager = \WP_Session_Tokens::get_instance( get_current_user_id() );
		$session = $manager->get( $token );

		if ( ! $session || ( isset( $session['last_seen'] ) && time() - (int) $session['last_seen'] < self::TOUCH_EVERY ) ) {
			return;
		}

		$session['last_seen'] = time();
		$manager->update( $token, $session );
	}

	/**
	 * Records a sign-out.
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	public static function on_logout( $user_id ) {
		if ( $user_id ) {
			self::log( 'logout', (int) $user_id );
		}
	}

	/**
	 * Records a failed sign-in.
	 *
	 * @param string    $username Username or email that was tried.
	 * @param \WP_Error $error    Reason.
	 * @return void
	 */
	public static function on_failed( $username, $error = null ) {
		if ( ! Settings::get( 'sessions.history_failed' ) ) {
			return;
		}

		// Blocked and over-limit refusals are recorded on their own.
		if ( is_wp_error( $error ) && array_intersect( $error->get_error_codes(), array( 'adminora_blocked', 'adminora_session_limit' ) ) ) {
			return;
		}

		$user = get_user_by( is_email( $username ) ? 'email' : 'login', $username );

		self::log( 'failed', $user ? $user->ID : 0, array(), (string) $username );
	}

	/**
	 * Blocked users cannot reset their password.
	 *
	 * @param bool $allow   Whether to allow.
	 * @param int  $user_id User ID.
	 * @return bool
	 */
	public static function allow_reset( $allow, $user_id ) {
		return self::is_blocked( $user_id ) ? false : $allow;
	}

	/**
	 * Blocked users cannot use application passwords.
	 *
	 * @param bool     $available Whether available.
	 * @param \WP_User $user      User.
	 * @return bool
	 */
	public static function allow_app_passwords( $available, $user ) {
		return $user instanceof \WP_User && self::is_blocked( $user->ID ) ? false : $available;
	}

	// ------------------------------------------------------------------
	// Sign-in history.
	// ------------------------------------------------------------------

	/**
	 * Event names.
	 *
	 * @return array
	 */
	public static function events() {
		return array(
			'login'           => __( 'Signed in', 'adminora' ),
			'logout'          => __( 'Signed out', 'adminora' ),
			'failed'          => __( 'Failed sign-in', 'adminora' ),
			'ended_admin'     => __( 'Signed out by an administrator', 'adminora' ),
			'ended_limit'     => __( 'Signed out: session limit', 'adminora' ),
			'refused_limit'   => __( 'Refused: session limit', 'adminora' ),
			'refused_blocked' => __( 'Refused: account blocked', 'adminora' ),
			'blocked'         => __( 'Blocked', 'adminora' ),
			'unblocked'       => __( 'Unblocked', 'adminora' ),
		);
	}

	/**
	 * Adds a history entry.
	 *
	 * @param string $event    Event key.
	 * @param int    $user_id  User ID, or 0.
	 * @param array  $session  Session the event is about, if any.
	 * @param string $username Name tried, for failed sign-ins.
	 * @return void
	 */
	public static function log( $event, $user_id, array $session = array(), $username = '' ) {
		$visitor = in_array( $event, array( 'login', 'logout', 'failed', 'refused_limit', 'refused_blocked' ), true );

		if ( $visitor && ! Settings::get( 'sessions.history' ) ) {
			return;
		}

		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';

		$entry = array(
			't'  => time(),
			'e'  => $event,
			'u'  => (int) $user_id,
			'n'  => substr( sanitize_user( $username ), 0, 60 ),
			'ip' => $visitor ? Attempts::ip() : ( isset( $session['ip'] ) ? (string) $session['ip'] : '' ),
			'ua' => substr( $visitor ? $ua : ( isset( $session['ua'] ) ? (string) $session['ua'] : '' ), 0, 300 ),
			'by' => $visitor ? 0 : get_current_user_id(),
		);

		$history = get_option( self::HISTORY, array() );
		$history = is_array( $history ) ? $history : array();

		array_unshift( $history, $entry );

		update_option( self::HISTORY, array_slice( $history, 0, self::HISTORY_MAX ), false );
	}

	/**
	 * History entries, newest first.
	 *
	 * @param string $event   Only this event, or ''.
	 * @param int    $user_id Only this user, or 0.
	 * @return array
	 */
	public static function history( $event = '', $user_id = 0 ) {
		$history = get_option( self::HISTORY, array() );
		$history = is_array( $history ) ? $history : array();

		return array_values(
			array_filter(
				$history,
				static function ( $entry ) use ( $event, $user_id ) {
					return ( '' === $event || $entry['e'] === $event ) && ( ! $user_id || (int) $entry['u'] === (int) $user_id );
				}
			)
		);
	}

	/**
	 * Empties the history.
	 *
	 * @return void
	 */
	public static function clear_history() {
		delete_option( self::HISTORY );
	}

	/**
	 * Suggested wording for the site's privacy policy.
	 *
	 * @return void
	 */
	public static function privacy_text() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		wp_add_privacy_policy_content(
			__( 'Adminora', 'adminora' ),
			wp_kses_post(
				wpautop(
					__( 'When you sign in to this site, we record the time, your IP address and your browser and device type, to show you and the site administrators where your account is signed in, to keep a history of sign-ins and failed attempts, and to protect accounts from misuse. The history keeps the last 1,000 events and is not shared with anyone.', 'adminora' )
				)
			)
		);
	}

	// ------------------------------------------------------------------
	// Users screen.
	// ------------------------------------------------------------------

	/**
	 * Adds a Sessions column to Users.
	 *
	 * @param array $columns Columns.
	 * @return array
	 */
	public static function users_column( $columns ) {
		if ( current_user_can( 'manage_options' ) ) {
			$columns['adminora_sessions'] = __( 'Sessions', 'adminora' );
		}

		return $columns;
	}

	/**
	 * Sessions column content.
	 *
	 * @param string $output  Cell content.
	 * @param string $column  Column key.
	 * @param int    $user_id User ID.
	 * @return string
	 */
	public static function users_cell( $output, $column, $user_id ) {
		if ( 'adminora_sessions' !== $column ) {
			return $output;
		}

		if ( self::is_blocked( $user_id ) ) {
			return '<span class="adminora-badge">' . esc_html__( 'Blocked', 'adminora' ) . '</span>';
		}

		$count = count( self::of( $user_id ) );

		if ( ! $count ) {
			return '<span aria-hidden="true">&mdash;</span><span class="screen-reader-text">' . esc_html__( 'Not signed in', 'adminora' ) . '</span>';
		}

		return '<a href="' . esc_url( Admin::url( 'sessions', array( 'user' => $user_id ) ) ) . '">' . esc_html(
			sprintf(
				/* translators: %d: number of devices. */
				_n( '%d device', '%d devices', $count, 'adminora' ),
				$count
			)
		) . '</a>';
	}

	/**
	 * Sign out and block links under each user.
	 *
	 * @param array    $actions Row actions.
	 * @param \WP_User $user    User.
	 * @return array
	 */
	public static function row_actions( $actions, $user ) {
		if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'edit_user', $user->ID ) || get_current_user_id() === $user->ID ) {
			return $actions;
		}

		if ( self::of( $user->ID ) ) {
			$actions['adminora_signout'] = '<a href="' . esc_url( self::action_url( 'session_end_all', $user->ID ) ) . '">' . esc_html__( 'Sign out everywhere', 'adminora' ) . '</a>';
		}

		if ( self::is_blocked( $user->ID ) ) {
			$actions['adminora_unblock'] = '<a href="' . esc_url( self::action_url( 'user_unblock', $user->ID ) ) . '">' . esc_html__( 'Unblock', 'adminora' ) . '</a>';
		} else {
			$actions['adminora_block'] = '<a class="delete" href="' . esc_url( self::action_url( 'user_block', $user->ID ) ) . '">' . esc_html__( 'Block', 'adminora' ) . '</a>';
		}

		return $actions;
	}

	/**
	 * Signed link to a sessions action, returning to the Users screen.
	 *
	 * @param string $action  Action suffix.
	 * @param int    $user_id User ID.
	 * @return string
	 */
	private static function action_url( $action, $user_id ) {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action' => 'adminora_' . $action,
					'user'   => $user_id,
					'back'   => 'users',
				),
				admin_url( 'admin-post.php' )
			),
			'adminora_' . $action
		);
	}
}

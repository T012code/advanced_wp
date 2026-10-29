<?php
/**
 * Limits failed login attempts per IP address.
 *
 * @package Adminora
 */

namespace Adminora\Modules\Security;

use Adminora\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Login attempt limiter.
 */
class Attempts {

	const LOG_OPTION = 'adminora_lockouts';
	const LOG_SIZE   = 100;

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'authenticate', array( __CLASS__, 'check' ), 99 );
		add_action( 'wp_login_failed', array( __CLASS__, 'failed' ), 10, 2 );
		add_action( 'wp_login', array( __CLASS__, 'succeeded' ) );
		add_filter( 'login_errors', array( __CLASS__, 'remaining' ) );
		add_filter( 'shake_error_codes', array( __CLASS__, 'shake' ) );
	}

	/**
	 * Visitor IP address.
	 *
	 * Uses REMOTE_ADDR unless the site is set to trust proxy headers, because
	 * those headers can be forged by anyone not behind a trusted proxy.
	 *
	 * @return string
	 */
	public static function ip() {
		if ( Settings::get( 'security.trust_proxy' ) ) {
			foreach ( array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP' ) as $header ) {
				if ( empty( $_SERVER[ $header ] ) ) {
					continue;
				}

				$first = trim( explode( ',', sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) ) )[0] );

				if ( filter_var( $first, FILTER_VALIDATE_IP ) ) {
					return $first;
				}
			}
		}

		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '0.0.0.0';
	}

	/**
	 * Whether an IP is on the allow list. Supports single IPs and IPv4 CIDR ranges.
	 *
	 * @param string $ip IP address.
	 * @return bool
	 */
	public static function allowed( $ip ) {
		$lines = preg_split( '/[\r\n,]+/', (string) Settings::get( 'security.allowlist' ) );

		foreach ( $lines as $line ) {
			$line = trim( $line );

			if ( '' === $line ) {
				continue;
			}

			if ( $line === $ip ) {
				return true;
			}

			if ( false !== strpos( $line, '/' ) && filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
				list( $subnet, $bits ) = explode( '/', $line, 2 );
				$bits                  = (int) $bits;

				if ( filter_var( $subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) && $bits >= 0 && $bits <= 32 ) {
					$mask = $bits ? ( -1 << ( 32 - $bits ) ) & 0xFFFFFFFF : 0;

					if ( ( ip2long( $ip ) & $mask ) === ( ip2long( $subnet ) & $mask ) ) {
						return true;
					}
				}
			}
		}

		return false;
	}

	/**
	 * Transient key for an IP.
	 *
	 * @param string $type `count` or `lock`.
	 * @param string $ip   IP address.
	 * @return string
	 */
	private static function key( $type, $ip ) {
		return 'adminora_' . $type . '_' . md5( $ip );
	}

	/**
	 * Unix time the lockout ends, or 0 if not locked.
	 *
	 * @param string $ip IP address.
	 * @return int
	 */
	public static function locked_until( $ip ) {
		$until = (int) get_transient( self::key( 'lock', $ip ) );

		return $until > time() ? $until : 0;
	}

	/**
	 * Refuses logins from locked IPs.
	 *
	 * @param \WP_User|\WP_Error|null $user Result so far.
	 * @return \WP_User|\WP_Error|null
	 */
	public static function check( $user ) {
		$ip    = self::ip();
		$until = self::locked_until( $ip );

		if ( ! $until || self::allowed( $ip ) ) {
			return $user;
		}

		return new \WP_Error(
			'adminora_locked',
			sprintf(
				/* translators: %s: time left, such as "12 mins". */
				__( '<strong>Error:</strong> Too many failed login attempts. Please try again in %s.', 'adminora' ),
				human_time_diff( time(), $until )
			)
		);
	}

	/**
	 * Set when the last refusal was not a wrong password, so no
	 * "attempts left" warning is shown.
	 *
	 * @var bool
	 */
	private static $not_counted = false;

	/**
	 * Counts a failure and locks the IP when the limit is reached.
	 *
	 * @param string    $username Username tried.
	 * @param \WP_Error $error    Why the sign-in failed.
	 * @return void
	 */
	public static function failed( $username, $error = null ) {
		// A blocked account or a full session limit is not a guessed password.
		if ( is_wp_error( $error ) && array_intersect( $error->get_error_codes(), array( 'adminora_blocked', 'adminora_session_limit' ) ) ) {
			self::$not_counted = true;
			return;
		}

		$ip = self::ip();

		if ( self::allowed( $ip ) || self::locked_until( $ip ) ) {
			return;
		}

		$window = (int) Settings::get( 'security.window' ) * MINUTE_IN_SECONDS;
		$data   = get_transient( self::key( 'count', $ip ) );
		$data   = is_array( $data ) ? $data : array( 'count' => 0 );

		++$data['count'];

		if ( $data['count'] >= (int) Settings::get( 'security.max_attempts' ) ) {
			$lockout = (int) Settings::get( 'security.lockout' ) * MINUTE_IN_SECONDS;

			set_transient( self::key( 'lock', $ip ), time() + $lockout, $lockout );
			delete_transient( self::key( 'count', $ip ) );
			self::log( $ip, $username, time() + $lockout );
			return;
		}

		set_transient( self::key( 'count', $ip ), $data, $window );
	}

	/**
	 * Clears the counter after a successful login.
	 *
	 * @return void
	 */
	public static function succeeded() {
		delete_transient( self::key( 'count', self::ip() ) );
	}

	/**
	 * Adds the number of attempts left to the login error.
	 *
	 * @param string $errors Error HTML.
	 * @return string
	 */
	public static function remaining( $errors ) {
		$ip   = self::ip();
		$data = get_transient( self::key( 'count', $ip ) );

		if ( self::$not_counted || ! is_array( $data ) || self::locked_until( $ip ) || self::allowed( $ip ) ) {
			return $errors;
		}

		$left = max( 0, (int) Settings::get( 'security.max_attempts' ) - $data['count'] );

		return $errors . '<br>' . esc_html(
			sprintf(
				/* translators: %d: number of attempts left. */
				_n( '%d attempt left before a temporary lockout.', '%d attempts left before a temporary lockout.', $left, 'adminora' ),
				$left
			)
		);
	}

	/**
	 * Shakes the login form on lockout.
	 *
	 * @param string[] $codes Error codes.
	 * @return string[]
	 */
	public static function shake( $codes ) {
		$codes[] = 'adminora_locked';

		return $codes;
	}

	/**
	 * Records a lockout.
	 *
	 * @param string $ip       IP address.
	 * @param string $username Username tried.
	 * @param int    $until    Lockout end time.
	 * @return void
	 */
	private static function log( $ip, $username, $until ) {
		$log = get_option( self::LOG_OPTION, array() );
		$log = is_array( $log ) ? $log : array();

		array_unshift(
			$log,
			array(
				'time'     => time(),
				'ip'       => $ip,
				'username' => sanitize_user( (string) $username ),
				'until'    => $until,
			)
		);

		update_option( self::LOG_OPTION, array_slice( $log, 0, self::LOG_SIZE ), false );
	}

	/**
	 * Lockout log, newest first.
	 *
	 * @return array
	 */
	public static function get_log() {
		$log = get_option( self::LOG_OPTION, array() );

		return is_array( $log ) ? $log : array();
	}

	/**
	 * Lifts the lockout for an IP.
	 *
	 * @param string $ip IP address.
	 * @return void
	 */
	public static function unlock( $ip ) {
		delete_transient( self::key( 'lock', $ip ) );
		delete_transient( self::key( 'count', $ip ) );
	}

	/**
	 * Empties the log.
	 *
	 * @return void
	 */
	public static function clear_log() {
		delete_option( self::LOG_OPTION );
	}
}

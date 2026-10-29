<?php
/**
 * Two-factor authentication: authenticator apps, email codes and backup codes.
 *
 * After the password check succeeds, no login cookie is sent. The user is sent
 * to a second screen on wp-login.php and only gets the cookie after entering a
 * valid code. Add `define( 'ADMINSTUDIO_DISABLE_2FA', true );` to wp-config.php to
 * switch this off if you get locked out.
 *
 * @package AdminStudio
 */

namespace AdminStudio\Modules\Security;

use AdminStudio\Settings;
use AdminStudio\Modules\Emails\Renderer;

defined( 'ABSPATH' ) || exit;

/**
 * Two-factor module.
 */
class Two_Factor {

	const META_METHOD = 'adminstudio_2fa_method';
	const META_SECRET = 'adminstudio_2fa_secret';
	const META_PEND   = 'adminstudio_2fa_pending';
	const META_BACKUP = 'adminstudio_2fa_backup';
	const META_SLICE  = 'adminstudio_2fa_last_slice';
	const META_LOGIN  = 'adminstudio_2fa_login';
	const META_EMAIL  = 'adminstudio_2fa_email_code';
	const META_SINCE  = 'adminstudio_2fa_required_since';

	const LOGIN_TTL    = 600;
	const MAX_ATTEMPTS = 5;

	/**
	 * User who passed the password check and still needs a code.
	 *
	 * @var int
	 */
	private static $pending_user = 0;

	/**
	 * Session token created for that user, destroyed before the code step.
	 *
	 * @var string
	 */
	private static $pending_token = '';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		if ( defined( 'ADMINSTUDIO_DISABLE_2FA' ) && ADMINSTUDIO_DISABLE_2FA ) {
			return;
		}

		add_filter( 'authenticate', array( __CLASS__, 'intercept' ), PHP_INT_MAX );
		add_action( 'set_auth_cookie', array( __CLASS__, 'capture_token' ), 10, 6 );
		add_action( 'wp_login', array( __CLASS__, 'after_login' ), PHP_INT_MAX, 2 );
		add_action( 'login_form_adminstudio_2fa', array( __CLASS__, 'screen' ) );

		add_action( 'show_user_profile', array( __CLASS__, 'profile' ) );
		add_action( 'edit_user_profile', array( __CLASS__, 'profile' ) );
		add_action( 'edit_user_profile_update', array( __CLASS__, 'admin_reset' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );

		foreach ( array( 'setup', 'verify', 'email_start', 'email_verify', 'backup', 'disable' ) as $action ) {
			add_action( 'wp_ajax_adminstudio_2fa_' . $action, array( __CLASS__, 'ajax_' . $action ) );
		}

		add_action( 'admin_init', array( __CLASS__, 'enforce' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notice' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'notice_style' ) );
	}

	/**
	 * Active method for a user: totp, email or ''.
	 *
	 * @param int $user_id User ID.
	 * @return string
	 */
	public static function method( $user_id ) {
		$method = get_user_meta( $user_id, self::META_METHOD, true );

		return in_array( $method, array( 'totp', 'email' ), true ) ? $method : '';
	}

	/**
	 * Whether a user's role requires two-factor.
	 *
	 * @param \WP_User $user User.
	 * @return bool
	 */
	public static function required( $user ) {
		return (bool) array_intersect( (array) $user->roles, (array) Settings::get( 'security.twofa_roles' ) );
	}

	// ---------------------------------------------------------------------
	// Login flow.
	// ---------------------------------------------------------------------

	/**
	 * Stops the login cookie for users with two-factor on.
	 *
	 * @param \WP_User|\WP_Error|null $user Result so far.
	 * @return \WP_User|\WP_Error|null
	 */
	public static function intercept( $user ) {
		if ( ! $user instanceof \WP_User || '' === self::method( $user->ID ) ) {
			return $user;
		}

		// Application passwords are already a second secret and are allowed through.
		if ( did_action( 'application_password_did_authenticate' ) ) {
			return $user;
		}

		if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
			return new \WP_Error( 'adminstudio_2fa_xmlrpc', __( 'Two-factor authentication is on for this account. Use an application password for XML-RPC.', 'admin-studio' ) );
		}

		add_filter( 'send_auth_cookies', '__return_false', PHP_INT_MAX );
		self::$pending_user = $user->ID;

		return $user;
	}

	/**
	 * Remembers the session token created during the password step.
	 *
	 * @param string $cookie     Cookie value.
	 * @param int    $expire     Expiry.
	 * @param int    $expiration Expiration.
	 * @param int    $user_id    User ID.
	 * @param string $scheme     Scheme.
	 * @param string $token      Session token.
	 * @return void
	 */
	public static function capture_token( $cookie, $expire, $expiration, $user_id, $scheme, $token ) {
		if ( self::$pending_user && (int) $user_id === self::$pending_user ) {
			self::$pending_token = $token;
		}
	}

	/**
	 * Sends the user to the code screen instead of logging them in.
	 *
	 * @param string   $login Username.
	 * @param \WP_User $user  User.
	 * @return void
	 */
	public static function after_login( $login, $user ) {
		if ( ! self::$pending_user || $user->ID !== self::$pending_user ) {
			return;
		}

		remove_filter( 'send_auth_cookies', '__return_false', PHP_INT_MAX );

		if ( self::$pending_token ) {
			\WP_Session_Tokens::get_instance( $user->ID )->destroy( self::$pending_token );
		}

		// phpcs:disable WordPress.Security.NonceVerification -- Login form data, verified by the password step.
		$redirect = isset( $_REQUEST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_REQUEST['redirect_to'] ) ) : admin_url();
		$remember = ! empty( $_POST['rememberme'] );
		$interim  = ! empty( $_REQUEST['interim-login'] );
		// phpcs:enable

		$token = wp_generate_password( 40, false );

		update_user_meta(
			$user->ID,
			self::META_LOGIN,
			array(
				'hash'     => wp_hash( $token ),
				'expires'  => time() + self::LOGIN_TTL,
				'remember' => $remember,
				'redirect' => $redirect,
				'attempts' => 0,
			)
		);

		if ( 'email' === self::method( $user->ID ) ) {
			self::send_email_code( $user );
		}

		$args = array(
			'action' => 'adminstudio_2fa',
			'uid'    => $user->ID,
			'token'  => $token,
		);

		if ( $interim ) {
			$args['interim-login'] = 1;
		}

		wp_safe_redirect( add_query_arg( $args, site_url( 'wp-login.php', 'login_post' ) ) );
		exit;
	}

	/**
	 * Validates the login token from the request.
	 *
	 * @param int    $user_id User ID.
	 * @param string $token   Token.
	 * @return array|null Stored login state, or null when invalid or expired.
	 */
	private static function login_state( $user_id, $token ) {
		$state = get_user_meta( $user_id, self::META_LOGIN, true );

		if ( ! is_array( $state ) || empty( $state['hash'] ) || $state['expires'] < time() ) {
			return null;
		}

		return hash_equals( $state['hash'], wp_hash( $token ) ) ? $state : null;
	}

	/**
	 * The code entry screen, shown on wp-login.php?action=adminstudio_2fa.
	 *
	 * Runs inside wp-login.php, so login_header() and login_footer() are available
	 * and the login page design applies.
	 *
	 * @return void
	 */
	public static function screen() {
		global $interim_login;

		// phpcs:disable WordPress.Security.NonceVerification -- The token check below protects this screen; the form also carries a nonce.
		$user_id = isset( $_REQUEST['uid'] ) ? absint( $_REQUEST['uid'] ) : 0;
		$token   = isset( $_REQUEST['token'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['token'] ) ) : '';
		$interim = ! empty( $_REQUEST['interim-login'] );
		$user    = get_userdata( $user_id );
		$state   = $user ? self::login_state( $user_id, $token ) : null;
		$errors  = new \WP_Error();
		$notice  = '';

		if ( ! $state ) {
			$errors->add( 'adminstudio_2fa_expired', __( 'This sign-in link has expired. Please log in again.', 'admin-studio' ) );
			login_header( __( 'Two-factor authentication', 'admin-studio' ), '', $errors );
			printf( '<p id="nav"><a href="%s">%s</a></p>', esc_url( wp_login_url() ), esc_html__( 'Back to log in', 'admin-studio' ) );
			login_footer();
			exit;
		}

		$method = self::method( $user_id );

		$method_used = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';

		if ( 'post' === $method_used ) {
			if ( ! isset( $_POST['_adminstudio_2fa'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_adminstudio_2fa'] ) ), 'adminstudio_2fa_login_' . $user_id ) ) {
				$errors->add( 'adminstudio_2fa_nonce', __( 'The form expired. Please enter your code again.', 'admin-studio' ) );
			} elseif ( isset( $_POST['adminstudio_send_email'] ) ) {
				self::send_email_code( $user );
				$notice = __( 'We sent a code to your email address.', 'admin-studio' );
			} else {
				$code = isset( $_POST['adminstudio_code'] ) ? sanitize_text_field( wp_unslash( $_POST['adminstudio_code'] ) ) : '';

				if ( self::verify_code( $user, $code ) ) {
					delete_user_meta( $user_id, self::META_LOGIN );
					wp_set_auth_cookie( $user_id, ! empty( $state['remember'] ) );

					if ( $interim ) {
						$interim_login = 'success'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Read by login_header().
						login_header( '', '<p class="message">' . esc_html__( 'You have logged in successfully.', 'admin-studio' ) . '</p>' );
						login_footer();
						exit;
					}

					$redirect = apply_filters( 'login_redirect', $state['redirect'], $state['redirect'], $user ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core hook.
					wp_safe_redirect( $redirect ? $redirect : admin_url() );
					exit;
				}

				++$state['attempts'];

				// Feed the attempt limiter.
				do_action( 'wp_login_failed', $user->user_login, new \WP_Error( 'adminstudio_2fa_invalid' ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core hook.

				if ( $state['attempts'] >= self::MAX_ATTEMPTS ) {
					delete_user_meta( $user_id, self::META_LOGIN );
					$errors->add( 'adminstudio_2fa_locked', __( 'Too many incorrect codes. Please log in again.', 'admin-studio' ) );
					login_header( __( 'Two-factor authentication', 'admin-studio' ), '', $errors );
					printf( '<p id="nav"><a href="%s">%s</a></p>', esc_url( wp_login_url() ), esc_html__( 'Back to log in', 'admin-studio' ) );
					login_footer();
					exit;
				}

				update_user_meta( $user_id, self::META_LOGIN, $state );
				$errors->add( 'adminstudio_2fa_invalid', __( '<strong>Error:</strong> That code is not valid. Please try again.', 'admin-studio' ) );
			}
		}
		// phpcs:enable

		$intro = 'totp' === $method
			? __( 'Enter the 6-digit code from your authenticator app.', 'admin-studio' )
			: __( 'Enter the 6-digit code we sent to your email address.', 'admin-studio' );

		if ( $notice ) {
			$intro = $notice;
		}

		add_filter( 'shake_error_codes', array( __CLASS__, 'shake' ) );
		login_header( __( 'Two-factor authentication', 'admin-studio' ), '<p class="message">' . esc_html( $intro ) . '</p>', $errors );

		$action = add_query_arg( 'action', 'adminstudio_2fa', site_url( 'wp-login.php', 'login_post' ) );
		?>
		<form name="adminstudio2faform" id="loginform" action="<?php echo esc_url( $action ); ?>" method="post" autocomplete="off">
			<input type="hidden" name="uid" value="<?php echo esc_attr( $user_id ); ?>">
			<input type="hidden" name="token" value="<?php echo esc_attr( $token ); ?>">
			<?php if ( $interim ) : ?>
				<input type="hidden" name="interim-login" value="1">
			<?php endif; ?>
			<?php wp_nonce_field( 'adminstudio_2fa_login_' . $user_id, '_adminstudio_2fa', false ); ?>
			<p>
				<label for="adminstudio_code"><?php esc_html_e( 'Authentication code', 'admin-studio' ); ?></label>
				<input type="text" name="adminstudio_code" id="adminstudio_code" class="input" value="" size="20" inputmode="numeric" autocomplete="one-time-code" autocapitalize="off" spellcheck="false" required>
			</p>
			<p class="description" style="margin-bottom:16px;"><?php esc_html_e( 'Lost access to your device? Enter one of your backup codes instead.', 'admin-studio' ); ?></p>
			<p class="submit">
				<input type="submit" class="button button-primary button-large" value="<?php esc_attr_e( 'Verify', 'admin-studio' ); ?>">
			</p>
		</form>
		<?php if ( 'email' === $method || Settings::get( 'security.twofa_email' ) ) : ?>
			<form id="nav" method="post" action="<?php echo esc_url( $action ); ?>">
				<input type="hidden" name="uid" value="<?php echo esc_attr( $user_id ); ?>">
				<input type="hidden" name="token" value="<?php echo esc_attr( $token ); ?>">
				<?php wp_nonce_field( 'adminstudio_2fa_login_' . $user_id, '_adminstudio_2fa', false ); ?>
				<button type="submit" name="adminstudio_send_email" value="1" class="button-link">
					<?php echo 'email' === $method ? esc_html__( 'Send a new code', 'admin-studio' ) : esc_html__( 'Email me a code instead', 'admin-studio' ); ?>
				</button>
			</form>
		<?php endif; ?>
		<?php
		login_footer( 'adminstudio_code' );
		exit;
	}

	/**
	 * Shakes the form on a wrong code.
	 *
	 * @param string[] $codes Error codes.
	 * @return string[]
	 */
	public static function shake( $codes ) {
		$codes[] = 'adminstudio_2fa_invalid';

		return $codes;
	}

	/**
	 * Checks an authenticator, email or backup code.
	 *
	 * @param \WP_User $user User.
	 * @param string   $code Code entered.
	 * @return bool
	 */
	public static function verify_code( $user, $code ) {
		$code = strtolower( preg_replace( '/[\s-]+/', '', (string) $code ) );

		if ( '' === $code ) {
			return false;
		}

		if ( ctype_digit( $code ) && 6 === strlen( $code ) ) {
			if ( 'totp' === self::method( $user->ID ) ) {
				$slice = Totp::verify( get_user_meta( $user->ID, self::META_SECRET, true ), $code, (int) get_user_meta( $user->ID, self::META_SLICE, true ) );

				if ( false !== $slice ) {
					update_user_meta( $user->ID, self::META_SLICE, $slice );
					return true;
				}
			}

			$email = get_user_meta( $user->ID, self::META_EMAIL, true );

			if ( is_array( $email ) && $email['expires'] >= time() && hash_equals( $email['hash'], wp_hash( $code ) ) ) {
				delete_user_meta( $user->ID, self::META_EMAIL );
				return true;
			}

			return false;
		}

		$backup = get_user_meta( $user->ID, self::META_BACKUP, true );

		if ( is_array( $backup ) ) {
			foreach ( $backup as $i => $hash ) {
				if ( wp_check_password( $code, $hash ) ) {
					unset( $backup[ $i ] );
					update_user_meta( $user->ID, self::META_BACKUP, array_values( $backup ) );
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Emails a one-time code, valid for ten minutes.
	 *
	 * @param \WP_User $user User.
	 * @return bool
	 */
	public static function send_email_code( $user ) {
		$code = str_pad( (string) random_int( 0, 999999 ), 6, '0', STR_PAD_LEFT );

		update_user_meta(
			$user->ID,
			self::META_EMAIL,
			array(
				'hash'    => wp_hash( $code ),
				'expires' => time() + self::LOGIN_TTL,
			)
		);

		$site = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$body = '<p>' . esc_html(
			sprintf(
				/* translators: %s: site title. */
				__( 'Use this code to finish signing in to %s:', 'admin-studio' ),
				$site
			)
		) . '</p>'
			. '<p style="font-size:32px;font-weight:700;letter-spacing:8px;margin:24px 0;">' . esc_html( $code ) . '</p>'
			. '<p>' . esc_html__( 'The code expires in 10 minutes. If you did not try to sign in, change your password straight away.', 'admin-studio' ) . '</p>';

		return wp_mail(
			$user->user_email,
			/* translators: %s: site title. */
			sprintf( __( '[%s] Your sign-in code', 'admin-studio' ), $site ),
			Renderer::layout( __( 'Your sign-in code', 'admin-studio' ), $body ),
			array( 'Content-Type: text/html; charset=UTF-8' )
		);
	}

	/**
	 * Creates ten new backup codes and returns them in plain text.
	 *
	 * @param int $user_id User ID.
	 * @return string[]
	 */
	public static function new_backup_codes( $user_id ) {
		$codes  = array();
		$hashes = array();

		for ( $i = 0; $i < 10; $i++ ) {
			$code     = strtolower( wp_generate_password( 10, false ) );
			$codes[]  = substr( $code, 0, 5 ) . '-' . substr( $code, 5 );
			$hashes[] = wp_hash_password( $code );
		}

		update_user_meta( $user_id, self::META_BACKUP, $hashes );

		return $codes;
	}

	// ---------------------------------------------------------------------
	// Profile screen.
	// ---------------------------------------------------------------------

	/**
	 * Loads the profile script.
	 *
	 * @param string $hook Screen hook.
	 * @return void
	 */
	public static function enqueue( $hook ) {
		if ( 'profile.php' !== $hook ) {
			return;
		}

		wp_enqueue_script( 'adminstudio-qrcode', ADMINSTUDIO_URL . 'assets/vendor/qrcode.js', array(), '1.4.4', true );
		wp_enqueue_script( 'adminstudio-two-factor', ADMINSTUDIO_URL . 'assets/js/two-factor.js', array( 'adminstudio-qrcode', 'wp-util' ), \AdminStudio\Plugin::asset_version( 'assets/js/two-factor.js' ), true );
		wp_localize_script(
			'adminstudio-two-factor',
			'adminstudio2fa',
			array(
				'nonce' => wp_create_nonce( 'adminstudio_2fa' ),
				'i18n'  => array(
					'invalid'  => __( 'That code is not valid. Check your app and try again.', 'admin-studio' ),
					'sent'     => __( 'We sent a code to your email address. Enter it below.', 'admin-studio' ),
					'error'    => __( 'Something went wrong. Please reload the page and try again.', 'admin-studio' ),
					'confirm'  => __( 'Turn off two-factor authentication for your account?', 'admin-studio' ),
					'copied'   => __( 'Copied', 'admin-studio' ),
					'enabled'  => __( 'Two-factor authentication is on. Save your backup codes now.', 'admin-studio' ),
					'disabled' => __( 'Two-factor authentication is off.', 'admin-studio' ),
				),
			)
		);
	}

	/**
	 * Two-factor section on the profile and edit user screens.
	 *
	 * @param \WP_User $user User being edited.
	 * @return void
	 */
	public static function profile( $user ) {
		$own      = get_current_user_id() === $user->ID;
		$method   = self::method( $user->ID );
		$backup   = get_user_meta( $user->ID, self::META_BACKUP, true );
		$left     = is_array( $backup ) ? count( $backup ) : 0;
		$required = self::required( $user );
		?>
		<h2 id="adminstudio-2fa"><?php esc_html_e( 'Two-factor authentication', 'admin-studio' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Status', 'admin-studio' ); ?></th>
				<td>
					<p>
						<?php
						if ( 'totp' === $method ) {
							esc_html_e( 'On, using an authenticator app.', 'admin-studio' );
						} elseif ( 'email' === $method ) {
							esc_html_e( 'On, using codes sent by email.', 'admin-studio' );
						} else {
							esc_html_e( 'Off.', 'admin-studio' );
						}

						if ( $method ) {
							echo ' ';
							/* translators: %d: number of unused backup codes. */
							echo esc_html( sprintf( _n( '%d backup code left.', '%d backup codes left.', $left, 'admin-studio' ), $left ) );
						}

						if ( $required && ! $method ) {
							echo ' <strong>' . esc_html__( 'Your role requires two-factor authentication.', 'admin-studio' ) . '</strong>';
						}
						?>
					</p>

					<?php if ( $own ) : ?>
						<div id="adminstudio-2fa-app" data-method="<?php echo esc_attr( $method ); ?>">
							<p class="adminstudio-2fa-actions">
								<?php if ( $method ) : ?>
									<button type="button" class="button" data-adminstudio-2fa="backup"><?php esc_html_e( 'Generate new backup codes', 'admin-studio' ); ?></button>
									<?php if ( ! $required ) : ?>
										<button type="button" class="button button-link-delete" data-adminstudio-2fa="disable"><?php esc_html_e( 'Turn off two-factor', 'admin-studio' ); ?></button>
									<?php endif; ?>
								<?php else : ?>
									<button type="button" class="button button-primary" data-adminstudio-2fa="setup"><?php esc_html_e( 'Set up an authenticator app', 'admin-studio' ); ?></button>
									<?php if ( Settings::get( 'security.twofa_email' ) ) : ?>
										<button type="button" class="button" data-adminstudio-2fa="email_start"><?php esc_html_e( 'Use email codes instead', 'admin-studio' ); ?></button>
									<?php endif; ?>
								<?php endif; ?>
							</p>

							<div class="adminstudio-2fa-panel" data-panel="setup" hidden>
								<p><?php esc_html_e( 'Scan this QR code with your authenticator app, such as Google Authenticator, Microsoft Authenticator, 1Password or Authy.', 'admin-studio' ); ?></p>
								<div class="adminstudio-2fa-qr"></div>
								<p>
									<?php esc_html_e( 'Cannot scan it? Enter this key in the app instead:', 'admin-studio' ); ?>
									<code class="adminstudio-2fa-secret"></code>
								</p>
							</div>

							<div class="adminstudio-2fa-panel" data-panel="verify" hidden>
								<label for="adminstudio-2fa-code"><?php esc_html_e( 'Enter the 6-digit code to confirm', 'admin-studio' ); ?></label><br>
								<input type="text" id="adminstudio-2fa-code" class="regular-text" inputmode="numeric" autocomplete="one-time-code" maxlength="6">
								<button type="button" class="button button-primary" data-adminstudio-2fa="confirm"><?php esc_html_e( 'Verify and turn on', 'admin-studio' ); ?></button>
							</div>

							<div class="adminstudio-2fa-panel" data-panel="codes" hidden>
								<p><strong><?php esc_html_e( 'Backup codes', 'admin-studio' ); ?></strong></p>
								<p class="description"><?php esc_html_e( 'Each code works once. Keep them somewhere safe, such as a password manager. They will not be shown again.', 'admin-studio' ); ?></p>
								<ol class="adminstudio-2fa-codes"></ol>
								<button type="button" class="button" data-adminstudio-2fa="copy"><?php esc_html_e( 'Copy codes', 'admin-studio' ); ?></button>
							</div>

							<p class="adminstudio-2fa-message" role="status" aria-live="polite"></p>
						</div>
					<?php elseif ( $method && current_user_can( 'edit_users' ) ) : ?>
						<label>
							<input type="checkbox" name="adminstudio_2fa_reset" value="1">
							<?php esc_html_e( 'Reset two-factor for this user', 'admin-studio' ); ?>
						</label>
						<?php wp_nonce_field( 'adminstudio_2fa_reset_' . $user->ID, '_adminstudio_2fa_reset', false ); ?>
						<p class="description"><?php esc_html_e( 'Use this if they lost their device and backup codes. They can sign in with only a password until they set it up again.', 'admin-studio' ); ?></p>
					<?php endif; ?>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Lets admins reset another user's two-factor.
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	public static function admin_reset( $user_id ) {
		if ( ! isset( $_POST['_adminstudio_2fa_reset'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_adminstudio_2fa_reset'] ) ), 'adminstudio_2fa_reset_' . $user_id ) ) {
			return;
		}

		if ( empty( $_POST['adminstudio_2fa_reset'] ) || ! current_user_can( 'edit_users' ) || ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}

		self::clear( $user_id );
	}

	/**
	 * Removes all two-factor data for a user.
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	public static function clear( $user_id ) {
		foreach ( array( self::META_METHOD, self::META_SECRET, self::META_PEND, self::META_BACKUP, self::META_SLICE, self::META_LOGIN, self::META_EMAIL, self::META_SINCE ) as $key ) {
			delete_user_meta( $user_id, $key );
		}
	}

	// ---------------------------------------------------------------------
	// AJAX, for the current user's own account only.
	// ---------------------------------------------------------------------

	/**
	 * Starts authenticator setup with a new secret.
	 *
	 * @return void
	 */
	public static function ajax_setup() {
		check_ajax_referer( 'adminstudio_2fa', 'nonce' );

		if ( ! current_user_can( 'read' ) ) {
			wp_send_json_error( null, 403 );
		}

		$user   = wp_get_current_user();
		$secret = Totp::generate_secret();

		update_user_meta( $user->ID, self::META_PEND, $secret );

		wp_send_json_success(
			array(
				'secret' => trim( chunk_split( $secret, 4, ' ' ) ),
				'uri'    => Totp::uri( $secret, $user->user_email, wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) ),
			)
		);
	}

	/**
	 * Confirms the authenticator code and turns two-factor on.
	 *
	 * @return void
	 */
	public static function ajax_verify() {
		check_ajax_referer( 'adminstudio_2fa', 'nonce' );

		if ( ! current_user_can( 'read' ) ) {
			wp_send_json_error( null, 403 );
		}

		$user   = wp_get_current_user();
		$secret = get_user_meta( $user->ID, self::META_PEND, true );
		$code   = isset( $_POST['code'] ) ? preg_replace( '/\D/', '', sanitize_text_field( wp_unslash( $_POST['code'] ) ) ) : '';
		$slice  = $secret ? Totp::verify( $secret, $code ) : false;

		if ( false === $slice ) {
			wp_send_json_error();
		}

		update_user_meta( $user->ID, self::META_SECRET, $secret );
		update_user_meta( $user->ID, self::META_SLICE, $slice );
		update_user_meta( $user->ID, self::META_METHOD, 'totp' );
		delete_user_meta( $user->ID, self::META_PEND );
		delete_user_meta( $user->ID, self::META_SINCE );

		wp_send_json_success( array( 'codes' => self::new_backup_codes( $user->ID ) ) );
	}

	/**
	 * Sends an email code to confirm the email method.
	 *
	 * @return void
	 */
	public static function ajax_email_start() {
		check_ajax_referer( 'adminstudio_2fa', 'nonce' );

		if ( ! current_user_can( 'read' ) ) {
			wp_send_json_error( null, 403 );
		}

		$user = wp_get_current_user();

		if ( ! Settings::get( 'security.twofa_email' ) ) {
			wp_send_json_error( null, 403 );
		}

		self::send_email_code( $user ) ? wp_send_json_success() : wp_send_json_error();
	}

	/**
	 * Confirms the email code and turns two-factor on with email codes.
	 *
	 * @return void
	 */
	public static function ajax_email_verify() {
		check_ajax_referer( 'adminstudio_2fa', 'nonce' );

		if ( ! current_user_can( 'read' ) ) {
			wp_send_json_error( null, 403 );
		}

		$user  = wp_get_current_user();
		$email = get_user_meta( $user->ID, self::META_EMAIL, true );
		$code  = isset( $_POST['code'] ) ? preg_replace( '/\D/', '', sanitize_text_field( wp_unslash( $_POST['code'] ) ) ) : '';

		if ( ! is_array( $email ) || $email['expires'] < time() || ! hash_equals( $email['hash'], wp_hash( $code ) ) ) {
			wp_send_json_error();
		}

		delete_user_meta( $user->ID, self::META_EMAIL );
		update_user_meta( $user->ID, self::META_METHOD, 'email' );
		delete_user_meta( $user->ID, self::META_SINCE );

		wp_send_json_success( array( 'codes' => self::new_backup_codes( $user->ID ) ) );
	}

	/**
	 * Replaces the backup codes.
	 *
	 * @return void
	 */
	public static function ajax_backup() {
		check_ajax_referer( 'adminstudio_2fa', 'nonce' );

		if ( ! current_user_can( 'read' ) ) {
			wp_send_json_error( null, 403 );
		}

		$user = wp_get_current_user();

		if ( '' === self::method( $user->ID ) ) {
			wp_send_json_error();
		}

		wp_send_json_success( array( 'codes' => self::new_backup_codes( $user->ID ) ) );
	}

	/**
	 * Turns two-factor off, unless the user's role requires it.
	 *
	 * @return void
	 */
	public static function ajax_disable() {
		check_ajax_referer( 'adminstudio_2fa', 'nonce' );

		if ( ! current_user_can( 'read' ) ) {
			wp_send_json_error( null, 403 );
		}

		$user = wp_get_current_user();

		if ( self::required( $user ) ) {
			wp_send_json_error( null, 403 );
		}

		self::clear( $user->ID );
		wp_send_json_success();
	}

	// ---------------------------------------------------------------------
	// Enforcement for required roles.
	// ---------------------------------------------------------------------

	/**
	 * Days left before setup becomes mandatory, or -1 if not applicable.
	 *
	 * @param \WP_User $user User.
	 * @return int
	 */
	private static function grace_left( $user ) {
		if ( ! self::required( $user ) || '' !== self::method( $user->ID ) ) {
			return -1;
		}

		$since = (int) get_user_meta( $user->ID, self::META_SINCE, true );

		if ( ! $since ) {
			$since = time();
			update_user_meta( $user->ID, self::META_SINCE, $since );
		}

		$end = $since + (int) Settings::get( 'security.twofa_grace' ) * DAY_IN_SECONDS;

		return max( 0, (int) ceil( ( $end - time() ) / DAY_IN_SECONDS ) );
	}

	/**
	 * Sends users to their profile once the grace period is over.
	 *
	 * @return void
	 */
	public static function enforce() {
		if ( wp_doing_ajax() || ! is_user_logged_in() ) {
			return;
		}

		if ( 0 !== self::grace_left( wp_get_current_user() ) ) {
			return;
		}

		global $pagenow;

		if ( 'profile.php' !== $pagenow ) {
			wp_safe_redirect( admin_url( 'profile.php#adminstudio-2fa' ) );
			exit;
		}
	}

	/**
	 * Styles the reminder with an even border on every admin screen.
	 *
	 * @return void
	 */
	public static function notice_style() {
		if ( self::grace_left( wp_get_current_user() ) >= 0 ) {
			wp_add_inline_style( 'common', '.adminstudio-notice{border:1px solid #dba617;border-radius:4px;background:#fcf9e8;box-shadow:none}' );
		}
	}

	/**
	 * Reminds users who still need to set up two-factor.
	 *
	 * @return void
	 */
	public static function notice() {
		$left = self::grace_left( wp_get_current_user() );

		if ( $left < 0 ) {
			return;
		}

		$message = 0 === $left
			? __( 'Your role requires two-factor authentication. Set it up below to keep using the dashboard.', 'admin-studio' )
			: sprintf(
				/* translators: %d: number of days. */
				_n( 'Your role requires two-factor authentication. Please set it up within %d day.', 'Your role requires two-factor authentication. Please set it up within %d days.', $left, 'admin-studio' ),
				$left
			);

		printf(
			'<div class="notice notice-warning adminstudio-notice"><p>%s <a href="%s">%s</a></p></div>',
			esc_html( $message ),
			esc_url( admin_url( 'profile.php#adminstudio-2fa' ) ),
			esc_html__( 'Set up two-factor', 'admin-studio' )
		);
	}
}

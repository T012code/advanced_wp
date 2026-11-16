<?php
/**
 * Social sign-in for people who already have an account: Google, Microsoft,
 * GitHub, Facebook, LinkedIn, X, Discord, Twitch and WordPress.com.
 *
 * Each provider is used through its own OAuth 2.0 sign-in, with the site
 * owner's app keys; no library is bundled. Nobody can create an account this
 * way. A person is found by a provider account they connected on their
 * profile, or, for providers that confirm email addresses, by the verified
 * email address on their account. The sign-in then goes through the same
 * checks as a password login (blocked accounts, session limits, attempt
 * limits and two-factor).
 *
 * The provider sends people back to a fixed REST address, so the address to
 * register with each provider does not change when the login or dashboard
 * address does.
 *
 * @package LayoDT
 */

namespace LayoDT\Modules\Security;

defined( 'ABSPATH' ) || exit;

/**
 * Social sign-in.
 */
class Social {

	const OPTION = 'layodt_social';
	const META   = 'layodt_social_';
	const STATE  = 'layodt_sso_';
	const COOKIE = 'layodt_sso';

	/**
	 * Icon paths from Simple Icons (CC0), on a 24 by 24 grid.
	 *
	 * @var array
	 */
	const ICONS = array(
		'google'    => 'M12.48 10.92v3.28h7.84c-.24 1.84-.853 3.187-1.787 4.133-1.147 1.147-2.933 2.4-6.053 2.4-4.827 0-8.6-3.893-8.6-8.72s3.773-8.72 8.6-8.72c2.6 0 4.507 1.027 5.907 2.347l2.307-2.307C18.747 1.44 16.133 0 12.48 0 5.867 0 .307 5.387.307 12s5.56 12 12.173 12c3.573 0 6.267-1.173 8.373-3.36 2.16-2.16 2.84-5.213 2.84-7.667 0-.76-.053-1.467-.173-2.053H12.48z',
		'github'    => 'M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61C4.422 18.07 3.633 17.7 3.633 17.7c-1.087-.744.084-.729.084-.729 1.205.084 1.838 1.236 1.838 1.236 1.07 1.835 2.809 1.305 3.495.998.108-.776.417-1.305.76-1.605-2.665-.3-5.466-1.332-5.466-5.93 0-1.31.465-2.38 1.235-3.22-.135-.303-.54-1.523.105-3.176 0 0 1.005-.322 3.3 1.23.96-.267 1.98-.399 3-.405 1.02.006 2.04.138 3 .405 2.28-1.552 3.285-1.23 3.285-1.23.645 1.653.24 2.873.12 3.176.765.84 1.23 1.91 1.23 3.22 0 4.61-2.805 5.625-5.475 5.92.42.36.81 1.096.81 2.22 0 1.606-.015 2.896-.015 3.286 0 .315.21.69.825.57C20.565 22.092 24 17.592 24 12.297c0-6.627-5.373-12-12-12',
		'facebook'  => 'M9.101 23.691v-7.98H6.627v-3.667h2.474v-1.58c0-4.085 1.848-5.978 5.858-5.978.401 0 .955.042 1.468.103a8.68 8.68 0 0 1 1.141.195v3.325a8.623 8.623 0 0 0-.653-.036 26.805 26.805 0 0 0-.733-.009c-.707 0-1.259.096-1.675.309a1.686 1.686 0 0 0-.679.622c-.258.42-.374.995-.374 1.752v1.297h3.919l-.386 2.103-.287 1.564h-3.246v8.245C19.396 23.238 24 18.179 24 12.044c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.628 3.874 10.35 9.101 11.647Z',
		'x'         => 'M14.234 10.162 22.977 0h-2.072l-7.591 8.824L7.251 0H.258l9.168 13.343L.258 24H2.33l8.016-9.318L16.749 24h6.993zm-2.837 3.299-.929-1.329L3.076 1.56h3.182l5.965 8.532.929 1.329 7.754 11.09h-3.182z',
		'discord'   => 'M20.317 4.3698a19.7913 19.7913 0 00-4.8851-1.5152.0741.0741 0 00-.0785.0371c-.211.3753-.4447.8648-.6083 1.2495-1.8447-.2762-3.68-.2762-5.4868 0-.1636-.3933-.4058-.8742-.6177-1.2495a.077.077 0 00-.0785-.037 19.7363 19.7363 0 00-4.8852 1.515.0699.0699 0 00-.0321.0277C.5334 9.0458-.319 13.5799.0992 18.0578a.0824.0824 0 00.0312.0561c2.0528 1.5076 4.0413 2.4228 5.9929 3.0294a.0777.0777 0 00.0842-.0276c.4616-.6304.8731-1.2952 1.226-1.9942a.076.076 0 00-.0416-.1057c-.6528-.2476-1.2743-.5495-1.8722-.8923a.077.077 0 01-.0076-.1277c.1258-.0943.2517-.1923.3718-.2914a.0743.0743 0 01.0776-.0105c3.9278 1.7933 8.18 1.7933 12.0614 0a.0739.0739 0 01.0785.0095c.1202.099.246.1981.3728.2924a.077.077 0 01-.0066.1276 12.2986 12.2986 0 01-1.873.8914.0766.0766 0 00-.0407.1067c.3604.698.7719 1.3628 1.225 1.9932a.076.076 0 00.0842.0286c1.961-.6067 3.9495-1.5219 6.0023-3.0294a.077.077 0 00.0313-.0552c.5004-5.177-.8382-9.6739-3.5485-13.6604a.061.061 0 00-.0312-.0286zM8.02 15.3312c-1.1825 0-2.1569-1.0857-2.1569-2.419 0-1.3332.9555-2.4189 2.157-2.4189 1.2108 0 2.1757 1.0952 2.1568 2.419 0 1.3332-.9555 2.4189-2.1569 2.4189zm7.9748 0c-1.1825 0-2.1569-1.0857-2.1569-2.419 0-1.3332.9554-2.4189 2.1569-2.4189 1.2108 0 2.1757 1.0952 2.1568 2.419 0 1.3332-.946 2.4189-2.1568 2.4189Z',
		'twitch'    => 'M11.571 4.714h1.715v5.143H11.57zm4.715 0H18v5.143h-1.714zM6 0L1.714 4.286v15.428h5.143V24l4.286-4.286h3.428L22.286 12V0zm14.571 11.143l-3.428 3.428h-3.429l-3 3v-3H6.857V1.714h13.714Z',
		'wordpress' => 'M21.469 6.825c.84 1.537 1.318 3.3 1.318 5.175 0 3.979-2.156 7.456-5.363 9.325l3.295-9.527c.615-1.54.82-2.771.82-3.864 0-.405-.026-.78-.07-1.11m-7.981.105c.647-.03 1.232-.105 1.232-.105.582-.075.514-.93-.067-.899 0 0-1.755.135-2.88.135-1.064 0-2.85-.15-2.85-.15-.585-.03-.661.855-.075.885 0 0 .54.061 1.125.09l1.68 4.605-2.37 7.08L5.354 6.9c.649-.03 1.234-.1 1.234-.1.585-.075.516-.93-.065-.896 0 0-1.746.138-2.874.138-.2 0-.438-.008-.69-.015C4.911 3.15 8.235 1.215 12 1.215c2.809 0 5.365 1.072 7.286 2.833-.046-.003-.091-.009-.141-.009-1.06 0-1.812.923-1.812 1.914 0 .89.513 1.643 1.06 2.531.411.72.89 1.643.89 2.977 0 .915-.354 1.994-.821 3.479l-1.075 3.585-3.9-11.61.001.014zM12 22.784c-1.059 0-2.081-.153-3.048-.437l3.237-9.406 3.315 9.087c.024.053.05.101.078.149-1.12.393-2.325.609-3.582.609M1.211 12c0-1.564.336-3.05.935-4.39L7.29 21.709C3.694 19.96 1.212 16.271 1.211 12M12 0C5.385 0 0 5.385 0 12s5.385 12 12 12 12-5.385 12-12S18.615 0 12 0',
	);

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );

		if ( ! self::active_providers() ) {
			return;
		}

		add_action( 'login_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'login_footer', array( __CLASS__, 'buttons' ), 5 );
		add_filter( 'wp_login_errors', array( __CLASS__, 'errors' ) );
		add_action( 'show_user_profile', array( __CLASS__, 'profile' ) );
		add_action( 'admin_post_layodt_social_disconnect', array( __CLASS__, 'handle_disconnect' ) );
	}

	/**
	 * Supported providers.
	 *
	 * `email` says whether the provider confirms email addresses, so a person
	 * can be found by theirs; others must connect from their profile first.
	 *
	 * @return array
	 */
	public static function providers() {
		return array(
			'google'    => array(
				'name'    => 'Google',
				'color'   => '#4285F4',
				'auth'    => 'https://accounts.google.com/o/oauth2/v2/auth',
				'token'   => 'https://oauth2.googleapis.com/token',
				'scope'   => 'openid email profile',
				'pkce'    => true,
				'email'   => true,
				'console' => 'https://console.cloud.google.com/apis/credentials',
				'terms'   => 'https://policies.google.com/terms',
				'privacy' => 'https://policies.google.com/privacy',
				'extra'   => array( 'prompt' => 'select_account' ),
			),
			'microsoft' => array(
				'name'    => 'Microsoft',
				'color'   => '#2F2F2F',
				'auth'    => 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize',
				'token'   => 'https://login.microsoftonline.com/common/oauth2/v2.0/token',
				'scope'   => 'openid profile email',
				'pkce'    => true,
				'email'   => false,
				'console' => 'https://entra.microsoft.com/#view/Microsoft_AAD_RegisteredApps/ApplicationsListBlade',
				'terms'   => 'https://www.microsoft.com/servicesagreement',
				'privacy' => 'https://privacy.microsoft.com/privacystatement',
				'extra'   => array( 'prompt' => 'select_account' ),
			),
			'github'    => array(
				'name'    => 'GitHub',
				'color'   => '#181717',
				'auth'    => 'https://github.com/login/oauth/authorize',
				'token'   => 'https://github.com/login/oauth/access_token',
				'scope'   => 'read:user user:email',
				'pkce'    => false,
				'email'   => true,
				'console' => 'https://github.com/settings/developers',
				'terms'   => 'https://docs.github.com/site-policy/github-terms/github-terms-of-service',
				'privacy' => 'https://docs.github.com/site-policy/privacy-policies/github-general-privacy-statement',
				'extra'   => array(),
			),
			'facebook'  => array(
				'name'    => 'Facebook',
				'color'   => '#0866FF',
				'auth'    => 'https://www.facebook.com/v23.0/dialog/oauth',
				'token'   => 'https://graph.facebook.com/v23.0/oauth/access_token',
				'scope'   => 'email',
				'pkce'    => false,
				'email'   => true,
				'console' => 'https://developers.facebook.com/apps/',
				'terms'   => 'https://www.facebook.com/terms.php',
				'privacy' => 'https://www.facebook.com/privacy/policy/',
				'extra'   => array(),
			),
			'linkedin'  => array(
				'name'    => 'LinkedIn',
				'color'   => '#0A66C2',
				'auth'    => 'https://www.linkedin.com/oauth/v2/authorization',
				'token'   => 'https://www.linkedin.com/oauth/v2/accessToken',
				'scope'   => 'openid profile email',
				'pkce'    => false,
				'email'   => true,
				'console' => 'https://www.linkedin.com/developers/apps',
				'terms'   => 'https://www.linkedin.com/legal/user-agreement',
				'privacy' => 'https://www.linkedin.com/legal/privacy-policy',
				'extra'   => array(),
			),
			'x'         => array(
				'name'    => 'X',
				'color'   => '#000000',
				'auth'    => 'https://x.com/i/oauth2/authorize',
				'token'   => 'https://api.x.com/2/oauth2/token',
				'scope'   => 'users.read tweet.read',
				'pkce'    => true,
				'email'   => false,
				'console' => 'https://developer.x.com/en/portal/dashboard',
				'terms'   => 'https://x.com/en/tos',
				'privacy' => 'https://x.com/en/privacy',
				'extra'   => array(),
			),
			'discord'   => array(
				'name'    => 'Discord',
				'color'   => '#5865F2',
				'auth'    => 'https://discord.com/oauth2/authorize',
				'token'   => 'https://discord.com/api/oauth2/token',
				'scope'   => 'identify email',
				'pkce'    => false,
				'email'   => true,
				'console' => 'https://discord.com/developers/applications',
				'terms'   => 'https://discord.com/terms',
				'privacy' => 'https://discord.com/privacy',
				'extra'   => array(),
			),
			'twitch'    => array(
				'name'    => 'Twitch',
				'color'   => '#9146FF',
				'auth'    => 'https://id.twitch.tv/oauth2/authorize',
				'token'   => 'https://id.twitch.tv/oauth2/token',
				'scope'   => 'user:read:email',
				'pkce'    => false,
				'email'   => false,
				'console' => 'https://dev.twitch.tv/console/apps',
				'terms'   => 'https://www.twitch.tv/p/legal/terms-of-service/',
				'privacy' => 'https://www.twitch.tv/p/legal/privacy-notice/',
				'extra'   => array(),
			),
			'wordpress' => array(
				'name'    => 'WordPress.com',
				'color'   => '#21759B',
				'auth'    => 'https://public-api.wordpress.com/oauth2/authorize',
				'token'   => 'https://public-api.wordpress.com/oauth2/token',
				'scope'   => 'auth',
				'pkce'    => false,
				'email'   => true,
				'console' => 'https://developer.wordpress.com/apps/',
				'terms'   => 'https://wordpress.com/tos/',
				'privacy' => 'https://automattic.com/privacy/',
				'extra'   => array(),
			),
		);
	}

	/**
	 * Saved settings with defaults.
	 *
	 * @return array { on, place, providers: id => { on, client_id, secret } }
	 */
	public static function settings() {
		$saved = get_option( self::OPTION, array() );
		$saved = is_array( $saved ) ? $saved : array();
		$out   = array(
			'on'        => ! empty( $saved['on'] ),
			'place'     => isset( $saved['place'] ) && 'above' === $saved['place'] ? 'above' : 'below',
			'providers' => array(),
		);

		foreach ( array_keys( self::providers() ) as $id ) {
			$one                     = isset( $saved['providers'][ $id ] ) && is_array( $saved['providers'][ $id ] ) ? $saved['providers'][ $id ] : array();
			$out['providers'][ $id ] = array(
				'on'        => ! empty( $one['on'] ),
				'client_id' => isset( $one['client_id'] ) ? (string) $one['client_id'] : '',
				'secret'    => isset( $one['secret'] ) ? (string) $one['secret'] : '',
			);
		}

		return $out;
	}

	/**
	 * Providers that are switched on and have their keys.
	 *
	 * @return string[]
	 */
	public static function active_providers() {
		$settings = self::settings();

		if ( ! $settings['on'] ) {
			return array();
		}

		$active = array();

		foreach ( $settings['providers'] as $id => $one ) {
			if ( $one['on'] && '' !== $one['client_id'] && '' !== $one['secret'] ) {
				$active[] = $id;
			}
		}

		return $active;
	}

	/**
	 * The address a provider sends people back to. It is the same whatever
	 * the login or dashboard address is.
	 *
	 * @param string $id Provider id.
	 * @return string
	 */
	public static function callback_url( $id ) {
		return rest_url( 'layodt/v1/social/' . $id );
	}

	// ------------------------------------------------------------------
	// Login screen.
	// ------------------------------------------------------------------

	/**
	 * Styles and the script that places the buttons.
	 *
	 * @return void
	 */
	public static function enqueue() {
		wp_enqueue_style( 'layodt-social', LAYODT_URL . 'assets/css/social.css', array( 'login' ), \LayoDT\Plugin::asset_version( 'assets/css/social.css' ) );
		wp_enqueue_script( 'layodt-social', LAYODT_URL . 'assets/js/social.js', array(), \LayoDT\Plugin::asset_version( 'assets/js/social.js' ), true );
	}

	/**
	 * The sign-in buttons, on the log in screen only. The script moves them
	 * next to the form; without it they stay under the page.
	 *
	 * @return void
	 */
	public static function buttons() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only decides which screen this is.
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : 'login';

		if ( 'login' !== $action ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Passed on as the page to return to.
		$redirect = isset( $_REQUEST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_REQUEST['redirect_to'] ) ) : '';
		$place    = self::settings()['place'];

		echo '<div class="layodt-social" data-place="' . esc_attr( $place ) . '"><p class="layodt-social__or"><span>' . esc_html__( 'or', 'layo-dashboard-toolkit' ) . '</span></p><ul class="layodt-social__list">';

		foreach ( self::active_providers() as $id ) {
			$provider = self::providers()[ $id ];
			$url      = add_query_arg( array_filter( array( 'redirect_to' => $redirect ) ), rest_url( 'layodt/v1/social/' . $id . '/start' ) );

			printf(
				'<li><a class="layodt-social__button layodt-social__button--%1$s" href="%2$s">%3$s<span>%4$s</span></a></li>',
				esc_attr( $id ),
				esc_url( $url ),
				wp_kses( self::icon( $id ), self::icon_tags() ),
				/* translators: %s: provider name, such as Google. */
				esc_html( sprintf( __( 'Continue with %s', 'layo-dashboard-toolkit' ), $provider['name'] ) )
			);
		}

		echo '</ul></div>';
	}

	/**
	 * A provider's icon.
	 *
	 * @param string $id Provider id.
	 * @return string SVG markup.
	 */
	public static function icon( $id ) {
		if ( 'microsoft' === $id ) {
			return '<svg class="layodt-social__icon" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><rect x="1" y="1" width="10.5" height="10.5" fill="#F25022"/><rect x="12.5" y="1" width="10.5" height="10.5" fill="#7FBA00"/><rect x="1" y="12.5" width="10.5" height="10.5" fill="#00A4EF"/><rect x="12.5" y="12.5" width="10.5" height="10.5" fill="#FFB900"/></svg>';
		}

		if ( 'linkedin' === $id ) {
			return '<svg class="layodt-social__icon" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><rect width="24" height="24" rx="4" fill="#0A66C2"/><text x="12" y="17" text-anchor="middle" font-family="Arial, sans-serif" font-size="13" font-weight="700" fill="#fff">in</text></svg>';
		}

		$path  = isset( self::ICONS[ $id ] ) ? self::ICONS[ $id ] : '';
		$color = 'google' === $id ? '#4285F4' : 'currentColor';

		return '<svg class="layodt-social__icon" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><path fill="' . esc_attr( $color ) . '" d="' . esc_attr( $path ) . '"/></svg>';
	}

	/**
	 * SVG tags and attributes allowed in icons.
	 *
	 * @return array
	 */
	public static function icon_tags() {
		$common = array(
			'class'       => true,
			'fill'        => true,
			'x'           => true,
			'y'           => true,
			'width'       => true,
			'height'      => true,
			'rx'          => true,
			'aria-hidden' => true,
			'focusable'   => true,
		);

		return array(
			'svg'  => $common + array( 'viewbox' => true ),
			'path' => $common + array( 'd' => true ),
			'rect' => $common,
			'text' => $common + array(
				'text-anchor' => true,
				'font-family' => true,
				'font-size'   => true,
				'font-weight' => true,
			),
		);
	}

	/**
	 * Messages after a social sign-in that did not work.
	 *
	 * @param \WP_Error $errors Login screen errors.
	 * @return \WP_Error
	 */
	public static function errors( $errors ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display only.
		$code = isset( $_GET['layodt_social'] ) ? sanitize_key( wp_unslash( $_GET['layodt_social'] ) ) : '';

		$messages = array(
			'no_account' => __( 'No account on this site uses that sign-in. Log in with your username and password, then connect it on your profile, or ask an administrator.', 'layo-dashboard-toolkit' ),
			'expired'    => __( 'That sign-in took too long or was started in another browser. Please try again.', 'layo-dashboard-toolkit' ),
			'cancelled'  => __( 'The sign-in was cancelled.', 'layo-dashboard-toolkit' ),
			'failed'     => __( 'The sign-in could not be completed. Please try again, or log in with your username and password.', 'layo-dashboard-toolkit' ),
			'refused'    => __( 'This account cannot sign in right now.', 'layo-dashboard-toolkit' ),
		);

		if ( isset( $messages[ $code ] ) && $errors instanceof \WP_Error ) {
			$errors->add( 'layodt_social_' . $code, $messages[ $code ] );
		}

		return $errors;
	}

	// ------------------------------------------------------------------
	// Sign-in flow.
	// ------------------------------------------------------------------

	/**
	 * REST routes: start and return. Both are public; the return is only
	 * accepted with the one-time state saved when this browser started.
	 *
	 * @return void
	 */
	public static function routes() {
		register_rest_route(
			'layodt/v1',
			'/social/(?P<provider>[a-z]+)/start',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'start' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			'layodt/v1',
			'/social/(?P<provider>[a-z]+)',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'finish' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Sends the visitor to the provider. Connecting an account to a profile
	 * needs the person to be logged in, with the REST nonce.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function start( $request ) {
		$id = sanitize_key( $request['provider'] );

		if ( ! in_array( $id, self::active_providers(), true ) ) {
			return new \WP_Error( 'layodt_social_off', __( 'This sign-in is not available.', 'layo-dashboard-toolkit' ), array( 'status' => 404 ) );
		}

		$link = 'link' === $request->get_param( 'mode' );

		if ( $link && ! get_current_user_id() ) {
			return new \WP_Error( 'layodt_social_login', __( 'Log in first to connect an account.', 'layo-dashboard-toolkit' ), array( 'status' => 403 ) );
		}

		$provider = self::providers()[ $id ];
		$state    = wp_generate_password( 40, false );
		$verifier = wp_generate_password( 64, false );
		$redirect = (string) $request->get_param( 'redirect_to' );

		set_transient(
			self::STATE . hash( 'sha256', $state ),
			array(
				'provider' => $id,
				'mode'     => $link ? 'link' : 'login',
				'user'     => $link ? get_current_user_id() : 0,
				'redirect' => wp_validate_redirect( esc_url_raw( $redirect ), '' ),
				'verifier' => $verifier,
			),
			10 * MINUTE_IN_SECONDS
		);

		// Ties the state to this browser, so a link made in another browser cannot finish the sign-in here.
		setcookie( self::COOKIE, hash( 'sha256', $state ), time() + 10 * MINUTE_IN_SECONDS, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, is_ssl(), true );

		$args = array(
			'response_type' => 'code',
			'client_id'     => self::settings()['providers'][ $id ]['client_id'],
			'redirect_uri'  => self::callback_url( $id ),
			'scope'         => $provider['scope'],
			'state'         => $state,
		) + $provider['extra'];

		if ( $provider['pkce'] ) {
			$args['code_challenge']        = rtrim( strtr( base64_encode( hash( 'sha256', $verifier, true ) ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- PKCE requires base64url.
			$args['code_challenge_method'] = 'S256';
		}

		return self::redirect( add_query_arg( array_map( 'rawurlencode', $args ), $provider['auth'] ) );
	}

	/**
	 * The provider sends the visitor back here.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public static function finish( $request ) {
		$id     = sanitize_key( $request['provider'] );
		$state  = (string) $request->get_param( 'state' );
		$key    = self::STATE . hash( 'sha256', $state );
		$saved  = '' !== $state ? get_transient( $key ) : false;
		$cookie = isset( $_COOKIE[ self::COOKIE ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE ] ) ) : '';

		delete_transient( $key );
		setcookie( self::COOKIE, '', time() - HOUR_IN_SECONDS, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, is_ssl(), true );

		if ( ! is_array( $saved ) || $saved['provider'] !== $id || ! hash_equals( hash( 'sha256', $state ), $cookie ) || ! in_array( $id, self::active_providers(), true ) ) {
			return self::fail( 'expired' );
		}

		if ( $request->get_param( 'error' ) ) {
			return self::fail( 'cancelled', $saved );
		}

		$token = self::exchange( $id, (string) $request->get_param( 'code' ), $saved['verifier'] );
		$who   = '' !== $token ? self::identity( $id, $token ) : null;

		if ( ! $who || '' === $who['id'] ) {
			return self::fail( 'failed', $saved );
		}

		if ( 'link' === $saved['mode'] ) {
			return self::connect( $id, $who, (int) $saved['user'] );
		}

		$user = self::find_user( $id, $who );

		if ( ! $user ) {
			return self::fail( 'no_account', $saved );
		}

		return self::sign_in( $user, $saved['redirect'] );
	}

	/**
	 * Swaps the code for an access token.
	 *
	 * @param string $id       Provider id.
	 * @param string $code     Code from the provider.
	 * @param string $verifier PKCE verifier.
	 * @return string Access token, or ''.
	 */
	private static function exchange( $id, $code, $verifier ) {
		if ( '' === $code ) {
			return '';
		}

		$provider = self::providers()[ $id ];
		$keys     = self::settings()['providers'][ $id ];
		$body     = array(
			'grant_type'    => 'authorization_code',
			'code'          => $code,
			'redirect_uri'  => self::callback_url( $id ),
			'client_id'     => $keys['client_id'],
			'client_secret' => $keys['secret'],
		);
		$headers  = array( 'Accept' => 'application/json' );

		if ( $provider['pkce'] ) {
			$body['code_verifier'] = $verifier;
		}

		// X wants the keys in a Basic authorization header.
		if ( 'x' === $id ) {
			unset( $body['client_secret'] );
			$headers['Authorization'] = 'Basic ' . base64_encode( $keys['client_id'] . ':' . $keys['secret'] ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- HTTP Basic authorization.
		}

		$response = wp_remote_post(
			$provider['token'],
			array(
				'timeout' => 15,
				'headers' => $headers,
				'body'    => $body,
			)
		);

		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		return is_array( $data ) && ! empty( $data['access_token'] ) ? (string) $data['access_token'] : '';
	}

	/**
	 * Who signed in: the provider's id for them, and their email when the
	 * provider confirms it.
	 *
	 * @param string $id    Provider id.
	 * @param string $token Access token.
	 * @return array|null { id, email }
	 */
	private static function identity( $id, $token ) {
		$auth = array( 'Authorization' => 'Bearer ' . $token );

		switch ( $id ) {
			case 'google':
				$me = self::get_json( 'https://openidconnect.googleapis.com/v1/userinfo', $auth );
				return $me ? self::who( isset( $me['sub'] ) ? $me['sub'] : '', ! empty( $me['email_verified'] ) && isset( $me['email'] ) ? $me['email'] : '' ) : null;

			case 'linkedin':
				$me = self::get_json( 'https://api.linkedin.com/v2/userinfo', $auth );
				return $me ? self::who( isset( $me['sub'] ) ? $me['sub'] : '', ! empty( $me['email_verified'] ) && isset( $me['email'] ) ? $me['email'] : '' ) : null;

			case 'microsoft':
				$me = self::get_json( 'https://graph.microsoft.com/oidc/userinfo', $auth );
				return $me ? self::who( isset( $me['sub'] ) ? $me['sub'] : '', '' ) : null;

			case 'github':
				$headers = $auth + array(
					'Accept'     => 'application/vnd.github+json',
					'User-Agent' => 'Layo-Dashboard-Toolkit',
				);
				$me      = self::get_json( 'https://api.github.com/user', $headers );
				$email   = '';
				foreach ( (array) self::get_json( 'https://api.github.com/user/emails', $headers ) as $row ) {
					if ( is_array( $row ) && ! empty( $row['primary'] ) && ! empty( $row['verified'] ) && ! empty( $row['email'] ) ) {
						$email = $row['email'];
					}
				}
				return $me ? self::who( isset( $me['id'] ) ? $me['id'] : '', $email ) : null;

			case 'facebook':
				$me = self::get_json( add_query_arg( array( 'fields' => 'id,email' ), 'https://graph.facebook.com/v23.0/me' ), $auth );
				// Facebook only returns an address the person has confirmed.
				return $me ? self::who( isset( $me['id'] ) ? $me['id'] : '', isset( $me['email'] ) ? $me['email'] : '' ) : null;

			case 'x':
				$me = self::get_json( 'https://api.x.com/2/users/me', $auth );
				return isset( $me['data']['id'] ) ? self::who( $me['data']['id'], '' ) : null;

			case 'discord':
				$me = self::get_json( 'https://discord.com/api/users/@me', $auth );
				return $me ? self::who( isset( $me['id'] ) ? $me['id'] : '', ! empty( $me['verified'] ) && isset( $me['email'] ) ? $me['email'] : '' ) : null;

			case 'twitch':
				$me = self::get_json( 'https://api.twitch.tv/helix/users', $auth + array( 'Client-Id' => self::settings()['providers']['twitch']['client_id'] ) );
				return isset( $me['data'][0]['id'] ) ? self::who( $me['data'][0]['id'], '' ) : null;

			case 'WordPress':
				$me = self::get_json( 'https://public-api.wordpress.com/rest/v1.1/me', $auth );
				return $me ? self::who( isset( $me['ID'] ) ? $me['ID'] : '', ! empty( $me['email_verified'] ) && isset( $me['email'] ) ? $me['email'] : '' ) : null;
		}

		return null;
	}

	/**
	 * Builds an identity, keeping the email only for providers that confirm it.
	 *
	 * @param mixed  $id    Provider's id for the person.
	 * @param string $email Confirmed email, or ''.
	 * @return array { id, email }
	 */
	private static function who( $id, $email ) {
		return array(
			'id'    => sanitize_text_field( (string) $id ),
			'email' => is_email( (string) $email ) ? sanitize_email( (string) $email ) : '',
		);
	}

	/**
	 * A JSON response from a provider, or null.
	 *
	 * @param string $url     Address.
	 * @param array  $headers Headers.
	 * @return array|null
	 */
	private static function get_json( $url, array $headers ) {
		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 15,
				'headers' => $headers,
			)
		);

		if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		return is_array( $data ) ? $data : null;
	}

	/**
	 * The account a sign-in belongs to: one that connected this provider
	 * account, or, when the provider confirms emails, the account with that
	 * email address.
	 *
	 * @param string $id  Provider id.
	 * @param array  $who Identity.
	 * @return \WP_User|null
	 */
	private static function find_user( $id, array $who ) {
		$linked = get_users(
			array(
				'meta_key'   => self::META . $id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Only on a social sign-in.
				'meta_value' => $who['id'], // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Only on a social sign-in.
				'number'     => 1,
			)
		);

		if ( $linked ) {
			return $linked[0];
		}

		if ( ! self::providers()[ $id ]['email'] || '' === $who['email'] ) {
			return null;
		}

		$user = get_user_by( 'email', $who['email'] );

		if ( $user ) {
			// Remember the link, so a later change of email at the provider still finds them.
			update_user_meta( $user->ID, self::META . $id, $who['id'] );
		}

		return $user ? $user : null;
	}

	/**
	 * Logs the person in through the same checks as a password login.
	 *
	 * @param \WP_User $user     User.
	 * @param string   $redirect Page to return to.
	 * @return \WP_REST_Response
	 */
	private static function sign_in( \WP_User $user, $redirect ) {
		// Two-factor reads the page to return to from the request.
		$_REQUEST['redirect_to'] = $redirect;

		// The same checks as a password login, with the person already identified.
		$checked = apply_filters( 'authenticate', $user, $user->user_login, '' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core hook.

		if ( ! $checked instanceof \WP_User || $checked->ID !== $user->ID ) {
			return self::fail( 'refused' );
		}

		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID, false, is_ssl() );

		// Two-factor takes over here when it is on for the account, and sends the person to the code screen.
		do_action( 'wp_login', $user->user_login, $user ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core hook.

		$to = '' !== $redirect ? $redirect : admin_url();
		$to = apply_filters( 'login_redirect', $to, $redirect, $user ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core hook.

		return self::redirect( wp_validate_redirect( $to, admin_url() ) );
	}

	/**
	 * Connects a provider account to the logged-in person's profile.
	 *
	 * @param string $id      Provider id.
	 * @param array  $who     Identity.
	 * @param int    $user_id User who started the connection.
	 * @return \WP_REST_Response
	 */
	private static function connect( $id, array $who, $user_id ) {
		$profile = admin_url( 'profile.php' );
		$taken   = get_users(
			array(
				'meta_key'   => self::META . $id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Only when connecting.
				'meta_value' => $who['id'], // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Only when connecting.
				'number'     => 1,
				'fields'     => 'ID',
			)
		);

		if ( $taken && (int) $taken[0] !== $user_id ) {
			return self::redirect( add_query_arg( 'layodt_social', 'taken', $profile ) . '#layodt-social' );
		}

		update_user_meta( $user_id, self::META . $id, $who['id'] );

		return self::redirect( add_query_arg( 'layodt_social', 'connected', $profile ) . '#layodt-social' );
	}

	/**
	 * Back to the login screen with a message.
	 *
	 * @param string     $code  Message code.
	 * @param array|null $saved Saved state.
	 * @return \WP_REST_Response
	 */
	private static function fail( $code, $saved = null ) {
		if ( is_array( $saved ) && 'link' === $saved['mode'] ) {
			return self::redirect( add_query_arg( 'layodt_social', 'link_' . $code, admin_url( 'profile.php' ) ) . '#layodt-social' );
		}

		return self::redirect( add_query_arg( 'layodt_social', $code, wp_login_url() ) );
	}

	/**
	 * A REST response that sends the browser elsewhere.
	 *
	 * @param string $url Address.
	 * @return \WP_REST_Response
	 */
	private static function redirect( $url ) {
		$response = new \WP_REST_Response( null, 302 );
		$response->header( 'Location', $url );
		$response->header( 'Cache-Control', 'no-store' );

		return $response;
	}

	// ------------------------------------------------------------------
	// Profile.
	// ------------------------------------------------------------------

	/**
	 * Connect and disconnect buttons on the person's own profile.
	 *
	 * @param \WP_User $user User.
	 * @return void
	 */
	public static function profile( $user ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display only.
		$note = isset( $_GET['layodt_social'] ) ? sanitize_key( wp_unslash( $_GET['layodt_social'] ) ) : '';

		$notes = array(
			'connected'      => array( 'success', __( 'Connected. You can now use that account to sign in.', 'layo-dashboard-toolkit' ) ),
			'disconnected'   => array( 'success', __( 'Disconnected.', 'layo-dashboard-toolkit' ) ),
			'taken'          => array( 'error', __( 'That account is already connected to someone else on this site.', 'layo-dashboard-toolkit' ) ),
			'link_failed'    => array( 'error', __( 'The account could not be connected. Please try again.', 'layo-dashboard-toolkit' ) ),
			'link_cancelled' => array( 'error', __( 'Connecting was cancelled.', 'layo-dashboard-toolkit' ) ),
			'link_expired'   => array( 'error', __( 'That took too long. Please try again.', 'layo-dashboard-toolkit' ) ),
		);

		echo '<h2 id="layodt-social">' . esc_html__( 'Sign in with another account', 'layo-dashboard-toolkit' ) . '</h2>';

		if ( isset( $notes[ $note ] ) ) {
			echo '<div class="notice notice-' . esc_attr( $notes[ $note ][0] ) . ' inline layodt-notice"><p>' . esc_html( $notes[ $note ][1] ) . '</p></div>';
		}

		echo '<p class="description">' . esc_html__( 'Connect an account to use it on the login screen instead of your password. Two-factor still applies.', 'layo-dashboard-toolkit' ) . '</p>';
		echo '<table class="form-table" role="presentation"><tbody>';

		foreach ( self::active_providers() as $id ) {
			$provider  = self::providers()[ $id ];
			$connected = '' !== (string) get_user_meta( $user->ID, self::META . $id, true );

			echo '<tr><th scope="row">' . wp_kses( self::icon( $id ), self::icon_tags() ) . ' ' . esc_html( $provider['name'] ) . '</th><td>';

			if ( $connected ) {
				echo '<span class="layodt-badge">' . esc_html__( 'Connected', 'layo-dashboard-toolkit' ) . '</span> ';
				$url = wp_nonce_url(
					add_query_arg(
						array(
							'action'   => 'layodt_social_disconnect',
							'provider' => $id,
						),
						admin_url( 'admin-post.php' )
					),
					'layodt_social_disconnect_' . $id
				);
				echo '<a class="button button-small" href="' . esc_url( $url ) . '">' . esc_html__( 'Disconnect', 'layo-dashboard-toolkit' ) . '</a>';
			} else {
				$url = wp_nonce_url( add_query_arg( 'mode', 'link', rest_url( 'layodt/v1/social/' . $id . '/start' ) ), 'wp_rest' );
				/* translators: %s: provider name. */
				echo '<a class="button" href="' . esc_url( $url ) . '">' . esc_html( sprintf( __( 'Connect %s', 'layo-dashboard-toolkit' ), $provider['name'] ) ) . '</a>';
				if ( $provider['email'] ) {
					echo '<p class="description">' . esc_html__( 'Signing in also works without connecting when your email address there is the same as here.', 'layo-dashboard-toolkit' ) . '</p>';
				}
			}

			echo '</td></tr>';
		}

		echo '</tbody></table>';
	}

	/**
	 * Disconnects a provider account from the person's own profile.
	 *
	 * @return void
	 */
	public static function handle_disconnect() {
		$id = isset( $_GET['provider'] ) ? sanitize_key( wp_unslash( $_GET['provider'] ) ) : '';

		check_admin_referer( 'layodt_social_disconnect_' . $id );

		if ( ! is_user_logged_in() || ! current_user_can( 'read' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'layo-dashboard-toolkit' ), '', array( 'response' => 403 ) );
		}

		if ( array_key_exists( $id, self::providers() ) ) {
			delete_user_meta( get_current_user_id(), self::META . $id );
		}

		wp_safe_redirect( add_query_arg( 'layodt_social', 'disconnected', admin_url( 'profile.php' ) ) . '#layodt-social' );
		exit;
	}
}

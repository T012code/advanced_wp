<?php
/**
 * Login page customizer.
 *
 * Restyles every screen on wp-login.php: log in, register, lost password,
 * reset password and the interim "session expired" login.
 *
 * @package AdminStudio
 */

namespace AdminStudio\Modules\Login;

use AdminStudio\Brand_Kit;
use AdminStudio\Color;
use AdminStudio\Preview;
use AdminStudio\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Login page module.
 */
class Login {

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'login_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'login_head', array( __CLASS__, 'head' ) );
		add_filter( 'login_headerurl', array( __CLASS__, 'header_url' ) );
		add_filter( 'login_headertext', array( __CLASS__, 'header_text' ) );
		add_filter( 'login_body_class', array( __CLASS__, 'body_class' ) );
		add_filter( 'login_message', array( __CLASS__, 'message' ) );
		add_action( 'login_footer', array( __CLASS__, 'footer' ) );

		Login_Text::init();
		self::carry_over_hidden_links();

		$places = self::link_places();

		if ( 'hidden' === $places['lost'] ) {
			add_filter( 'lost_password_html_link', '__return_empty_string' );
		}

		if ( 'hidden' === $places['back'] ) {
			add_filter( 'login_site_html_link', '__return_empty_string' );
		}

		if ( 'hidden' === $places['register'] ) {
			add_filter( 'register', array( __CLASS__, 'hide_register_link' ) );
		}

		if ( Settings::get( 'login.hide_language_switcher' ) ) {
			add_filter( 'login_display_language_dropdown', '__return_false' );
		}

		if ( Settings::get( 'login.hide_privacy_link' ) ) {
			add_filter( 'the_privacy_policy_link', array( __CLASS__, 'privacy_link' ) );
		}
	}

	/**
	 * Where each link under the form goes: link => below, password, button or hidden.
	 *
	 * @return array
	 */
	public static function link_places() {
		$places = array();

		foreach ( array( 'login', 'register', 'lost', 'back' ) as $link ) {
			$places[ $link ] = (string) Settings::get( 'login.link_' . $link );
		}

		return $places;
	}

	/**
	 * Earlier versions had two "hide" switches. They become link positions once.
	 *
	 * @return void
	 */
	private static function carry_over_hidden_links() {
		$raw   = Settings::raw();
		$login = isset( $raw['login'] ) && is_array( $raw['login'] ) ? $raw['login'] : array();

		if ( empty( $login['hide_lost_password'] ) && empty( $login['hide_back_to_site'] ) ) {
			return;
		}

		$changes = array(
			'login.hide_lost_password' => false,
			'login.hide_back_to_site'  => false,
		);

		if ( ! empty( $login['hide_lost_password'] ) ) {
			$changes['login.link_lost'] = 'hidden';
		}

		if ( ! empty( $login['hide_back_to_site'] ) ) {
			$changes['login.link_back'] = 'hidden';
		}

		Settings::update( $changes );
	}

	/**
	 * Removes the "Register" link, on the login screens only.
	 *
	 * @param string $link Link HTML.
	 * @return string
	 */
	public static function hide_register_link( $link ) {
		return did_action( 'login_init' ) ? '' : $link;
	}

	/**
	 * Settings for the script that arranges the links.
	 *
	 * @return array
	 */
	private static function script_data() {
		return array(
			'layout' => (string) Settings::get( 'login.links_layout' ),
			'places' => self::link_places(),
			'label'  => __( 'Account links', 'admin-studio' ),
		);
	}

	/**
	 * Resolved brand values for the login page.
	 *
	 * @return array
	 */
	public static function brand() {
		return Brand_Kit::get(
			array(
				'primary'    => Settings::get( 'login.button_bg' ),
				'text'       => Settings::get( 'login.text_color' ),
				'surface'    => Settings::get( 'login.form_bg' ),
				'background' => Settings::get( 'login.bg_color' ),
				'logo'       => Settings::get( 'login.logo' ),
				'logo_width' => Settings::get( 'login.logo_width' ),
			)
		);
	}

	/**
	 * Enqueues the stylesheet, inline variables and the small helper script.
	 *
	 * @return void
	 */
	public static function enqueue() {
		wp_enqueue_style( 'adminstudio-login', ADMINSTUDIO_URL . 'assets/css/login.css', array( 'login' ), \AdminStudio\Plugin::asset_version( 'assets/css/login.css' ) );
		wp_add_inline_style( 'adminstudio-login', self::css() );
		wp_enqueue_script( 'adminstudio-login', ADMINSTUDIO_URL . 'assets/js/login.js', array(), \AdminStudio\Plugin::asset_version( 'assets/js/login.js' ), true );
		wp_localize_script( 'adminstudio-login', 'adminstudioLogin', self::script_data() );
	}

	/**
	 * Prints theme fonts when the brand uses them.
	 *
	 * @return void
	 */
	public static function head() {
		Brand_Kit::print_theme_fonts( self::brand() );
	}

	/**
	 * Builds the dynamic CSS for the login page.
	 *
	 * @return string
	 */
	public static function css() {
		$b       = self::brand();
		$radius  = static function ( $key, $fallback ) {
			$value = Settings::get( $key );
			return '' === $value ? $fallback : (int) $value . 'px';
		};
		$btn_bg  = $b['primary'];
		$btn_txt = Settings::get( 'login.button_text' );
		$btn_txt = '' !== $btn_txt ? $btn_txt : Color::readable_on( $btn_bg );
		$link    = Settings::get( 'login.link_color' );
		$link    = '' !== $link ? $link : $b['primary'];
		$layout  = Settings::get( 'login.layout' );

		list( $page_bg, $page_text ) = self::background( $b );

		$logo        = $b['logo'];
		$logo_width  = $b['logo_width'];
		$logo_height = $logo ? self::logo_height( $logo, $logo_width ) : 0;

		$vars = Brand_Kit::css_vars( $b ) . implode(
			'',
			array(
				'--adminstudio-page-bg:' . $page_bg . ';',
				'--adminstudio-page-text:' . $page_text . ';',
				'--adminstudio-link:' . $link . ';',
				'--adminstudio-btn-bg:' . $btn_bg . ';',
				'--adminstudio-btn-text:' . $btn_txt . ';',
				'--adminstudio-btn-radius:' . $radius( 'login.button_radius', 'pill' === $b['button_style'] ? '999px' : $b['radius'] . 'px' ) . ';',
				'--adminstudio-form-width:' . (int) Settings::get( 'login.form_width' ) . 'px;',
				'--adminstudio-form-padding:' . (int) Settings::get( 'login.form_padding' ) . 'px;',
				'--adminstudio-form-radius:' . $radius( 'login.form_radius', $b['radius'] . 'px' ) . ';',
				'--adminstudio-field-fill:' . Color::mix( $b['surface'], $b['text'], 0.06 ) . ';',
				'--adminstudio-logo-h:' . $logo_height . 'px;',
				$logo ? '--adminstudio-logo-url:url("' . esc_url_raw( $logo ) . '");' : '',
			)
		);

		$css  = Brand_Kit::font_faces( $b );
		$css .= 'body.login.adminstudio{' . $vars . '}';

		if ( 'split' === $layout ) {
			$side = Settings::get( 'login.side_image' );
			$side = '' !== $side ? $side : Settings::get( 'login.bg_image' );
			if ( '' !== $side ) {
				$css .= 'body.login.adminstudio-layout-split::before{background-image:' . self::overlay_layer() . 'url("' . esc_url_raw( $side ) . '");}';
			}
		}

		// Hidden links stay hidden even without JavaScript. WordPress keeps the
		// empty wrapper when the "Go to site" link is removed.
		$places = self::link_places();
		$hide   = array(
			'login'    => '#nav .wp-login-log-in',
			'register' => '#nav .wp-login-register',
			'lost'     => '#nav .wp-login-lost-password',
			'back'     => '#backtoblog',
		);
		foreach ( $hide as $link => $selector ) {
			if ( 'hidden' === $places[ $link ] ) {
				$css .= $selector . '{display:none}';
			}
		}

		return $css;
	}

	/**
	 * Page background value and a readable text colour for it.
	 *
	 * @param array $b Brand values.
	 * @return string[] [ CSS background, text colour ].
	 */
	private static function background( array $b ) {
		$type   = Settings::get( 'login.bg_type' );
		$base   = $b['background'];
		$layout = Settings::get( 'login.layout' );

		// The split layout puts the form on a plain panel; the image goes in the side panel.
		if ( 'split' === $layout ) {
			return array( $b['surface'], $b['text'] );
		}

		if ( 'gradient' === $type ) {
			$from = Settings::get( 'login.bg_gradient_from' );
			$to   = Settings::get( 'login.bg_gradient_to' );
			$from = '' !== $from ? $from : $b['primary'];
			$to   = '' !== $to ? $to : $b['secondary'];

			return array(
				sprintf( 'linear-gradient(%ddeg,%s,%s)', (int) Settings::get( 'login.bg_gradient_angle' ), $from, $to ),
				Color::readable_on( Color::mix( $from, $to, 0.5 ) ),
			);
		}

		if ( 'image' === $type && '' !== Settings::get( 'login.bg_image' ) ) {
			return array(
				self::overlay_layer() . 'url("' . esc_url_raw( Settings::get( 'login.bg_image' ) ) . '") center/cover no-repeat fixed,' . $base,
				self::overlay_text( '#ffffff' ),
			);
		}

		if ( 'video' === $type && '' !== Settings::get( 'login.bg_video' ) ) {
			return array( $base, self::overlay_text( '#ffffff' ) );
		}

		return array( $base, Color::readable_on( $base ) );
	}

	/**
	 * A gradient layer that tints an image with the overlay colour.
	 *
	 * @return string Empty when the overlay is off.
	 */
	private static function overlay_layer() {
		$opacity = (int) Settings::get( 'login.overlay_opacity' );

		if ( $opacity <= 0 ) {
			return '';
		}

		$color = Settings::get( 'login.overlay_color' );
		$rgba  = Color::rgba( '' !== $color ? $color : '#000000', $opacity / 100 );

		return 'linear-gradient(' . $rgba . ',' . $rgba . '),';
	}

	/**
	 * Text colour over an image: readable on the overlay when it is strong enough.
	 *
	 * @param string $fallback Colour when the overlay is weak.
	 * @return string
	 */
	private static function overlay_text( $fallback ) {
		$color = Settings::get( 'login.overlay_color' );

		if ( (int) Settings::get( 'login.overlay_opacity' ) >= 40 ) {
			return Color::readable_on( '' !== $color ? $color : '#000000' );
		}

		return $fallback;
	}

	/**
	 * Logo height that keeps the image's aspect ratio at the chosen width.
	 *
	 * @param string $url   Image URL.
	 * @param int    $width Display width.
	 * @return int
	 */
	private static function logo_height( $url, $width ) {
		$id = attachment_url_to_postid( $url );

		if ( $id ) {
			$meta = wp_get_attachment_metadata( $id );
			if ( ! empty( $meta['width'] ) && ! empty( $meta['height'] ) ) {
				return (int) round( $width * $meta['height'] / $meta['width'] );
			}
		}

		// Unknown size: assume square; background-size: contain keeps it undistorted.
		return (int) $width;
	}

	/**
	 * Logo link.
	 *
	 * @return string
	 */
	public static function header_url() {
		$url = Settings::get( 'login.logo_url' );

		return '' !== $url ? $url : home_url( '/' );
	}

	/**
	 * Logo text, read by screen readers and shown when there is no logo image.
	 *
	 * @return string
	 */
	public static function header_text() {
		$text = Settings::get( 'login.logo_title' );

		return esc_html( '' !== $text ? $text : get_bloginfo( 'name' ) );
	}

	/**
	 * Body classes that switch layout and style variants.
	 *
	 * @param string[] $classes Classes.
	 * @return string[]
	 */
	public static function body_class( $classes ) {
		return array_merge( $classes, self::classes() );
	}

	/**
	 * Admin Studio body classes.
	 *
	 * @return string[]
	 */
	public static function classes() {
		$classes = array(
			'adminstudio',
			'adminstudio-layout-' . Settings::get( 'login.layout' ),
			'adminstudio-split-' . Settings::get( 'login.split_side' ),
			'adminstudio-fields-' . Settings::get( 'login.field_style' ),
			'adminstudio-btn-' . Settings::get( 'brand.button_style' ),
			'adminstudio-links-' . Settings::get( 'login.links_layout' ),
		);

		if ( Settings::get( 'login.hide_labels' ) ) {
			$classes[] = 'adminstudio-nolabels';
		}

		if ( Settings::get( 'login.hide_logo' ) ) {
			$classes[] = 'adminstudio-nologo';
		} elseif ( '' === self::brand()['logo'] ) {
			$classes[] = 'adminstudio-textlogo';
		}

		if ( ! Settings::get( 'login.form_shadow' ) ) {
			$classes[] = 'adminstudio-noshadow';
		}

		return $classes;
	}

	/**
	 * Adds the custom message above the form.
	 *
	 * @param string $message Existing message HTML.
	 * @return string
	 */
	public static function message( $message ) {
		$custom = Settings::get( 'login.message' );

		if ( '' === trim( $custom ) ) {
			return $message;
		}

		return '<div class="adminstudio-message">' . wp_kses_post( wpautop( $custom ) ) . '</div>' . $message;
	}

	/**
	 * Prints the footer text and the background video.
	 *
	 * @return void
	 */
	public static function footer() {
		$footer = Settings::get( 'login.footer_text' );

		if ( '' !== trim( $footer ) ) {
			echo '<div class="adminstudio-footer">' . wp_kses_post( $footer ) . '</div>';
		}

		$video = Settings::get( 'login.bg_video' );

		if ( 'video' === Settings::get( 'login.bg_type' ) && '' !== $video ) {
			printf(
				'<video class="adminstudio-bg-video" src="%s" autoplay muted loop playsinline aria-hidden="true"></video>',
				esc_url( $video )
			);
		}
	}

	/**
	 * Hides the privacy policy link on the login screen only.
	 *
	 * @param string $link Link HTML.
	 * @return string
	 */
	public static function privacy_link( $link ) {
		return did_action( 'login_init' ) ? '' : $link;
	}

	/**
	 * Screens the Customizer preview can show: key => label.
	 *
	 * @return array
	 */
	public static function preview_screens() {
		return array(
			'login'        => __( 'Log in', 'admin-studio' ),
			'register'     => __( 'Register', 'admin-studio' ),
			'lostpassword' => __( 'Lost password', 'admin-studio' ),
			'resetpass'    => __( 'Reset password', 'admin-studio' ),
			'twofactor'    => __( 'Two-factor code', 'admin-studio' ),
		);
	}

	/**
	 * Renders a look-alike login screen inside the Customizer preview.
	 *
	 * The real wp-login.php cannot run inside the preview frame, so this prints the same
	 * markup with the same styles applied, for the screen chosen in the Customizer.
	 *
	 * @return void
	 */
	public static function render_preview() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only preview inside the Customizer.
		$screen = isset( $_GET['adminstudio_screen'] ) ? sanitize_key( wp_unslash( $_GET['adminstudio_screen'] ) ) : 'login';
		$screen = array_key_exists( $screen, self::preview_screens() ) ? $screen : 'login';

		$classes  = array_merge( array( 'login', 'no-js', 'login-action-' . $screen, 'wp-core-ui' ), self::classes() );
		$messages = array(
			'register'     => Login_Text::display( 'register_message' ),
			'lostpassword' => Login_Text::display( 'lost_message' ),
			'resetpass'    => Login_Text::display( 'reset_message' ),
			'twofactor'    => __( 'Enter the 6-digit code from your authenticator app.', 'admin-studio' ),
		);
		$titles   = array(
			'login'        => Login_Text::display( 'login_title' ),
			'register'     => Login_Text::display( 'register_title' ),
			'lostpassword' => Login_Text::display( 'lost_title' ),
			'resetpass'    => Login_Text::display( 'reset_title' ),
			'twofactor'    => __( 'Two-factor authentication', 'admin-studio' ),
		);
		$message  = self::message( isset( $messages[ $screen ] ) ? '<p class="message">' . wp_kses_post( $messages[ $screen ] ) . '</p>' : '' );

		// The same links WordPress prints under each form.
		$links  = array(
			'login'        => array( 'register', 'lost' ),
			'register'     => array( 'login', 'lost' ),
			'lostpassword' => array( 'login', 'register' ),
			'resetpass'    => array( 'login', 'register' ),
			'twofactor'    => array(),
		);
		$names  = array(
			'login'    => array( 'wp-login-log-in', Login_Text::display( 'login_link' ) ),
			'register' => array( 'wp-login-register', Login_Text::display( 'register_link' ) ),
			'lost'     => array( 'wp-login-lost-password', Login_Text::display( 'lost_link' ) ),
		);
		$places = self::link_places();

		wp_enqueue_style( 'login' );
		self::enqueue();

		?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo esc_html( $titles[ $screen ] ); ?></title>
		<?php
		// Passing handles prints only these and skips the wp_print_styles action.
		wp_print_styles( array( 'login', 'adminstudio-login' ) );
		self::head();
		?>
</head>
<body class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>">
	<div id="login">
		<h1 role="presentation" class="wp-login-logo"><a href="<?php echo esc_url( self::header_url() ); ?>"><?php echo esc_html( self::header_text() ); ?></a></h1>
		<?php echo wp_kses_post( $message ); ?>
		<form name="loginform" id="loginform" action="#" method="post">
			<?php if ( 'login' === $screen || 'register' === $screen || 'lostpassword' === $screen ) : ?>
				<p>
					<label for="user_login"><?php echo esc_html( 'login' === $screen || 'lostpassword' === $screen ? Login_Text::display( 'username_label' ) : __( 'Username', 'admin-studio' ) ); ?></label>
					<input type="text" name="log" id="user_login" class="input" value="" size="20" autocapitalize="off" autocomplete="username">
				</p>
			<?php endif; ?>
			<?php if ( 'register' === $screen ) : ?>
				<p>
					<label for="user_email"><?php esc_html_e( 'Email', 'admin-studio' ); ?></label>
					<input type="email" name="user_email" id="user_email" class="input" value="" size="25" autocomplete="email">
				</p>
				<p id="reg_passmail"><?php echo esc_html( Login_Text::display( 'register_note' ) ); ?></p>
			<?php endif; ?>
			<?php if ( 'login' === $screen || 'resetpass' === $screen ) : ?>
				<div class="user-pass-wrap">
					<label for="user_pass"><?php echo esc_html( 'login' === $screen ? Login_Text::display( 'password_label' ) : Login_Text::display( 'new_password' ) ); ?></label>
					<div class="wp-pwd">
						<input type="password" name="pwd" id="user_pass" class="input password-input" value="" size="20" autocomplete="<?php echo 'login' === $screen ? 'current-password' : 'new-password'; ?>">
						<button type="button" class="button button-secondary wp-hide-pw hide-if-no-js" aria-label="<?php esc_attr_e( 'Show password', 'admin-studio' ); ?>">
							<span class="dashicons dashicons-visibility" aria-hidden="true"></span>
						</button>
					</div>
				</div>
			<?php endif; ?>
			<?php if ( 'twofactor' === $screen ) : ?>
				<p>
					<label for="adminstudio_code"><?php esc_html_e( 'Authentication code', 'admin-studio' ); ?></label>
					<input type="text" name="adminstudio_code" id="adminstudio_code" class="input" value="" size="20" inputmode="numeric" autocomplete="one-time-code">
				</p>
			<?php endif; ?>
			<?php if ( 'login' === $screen ) : ?>
				<p class="forgetmenot"><input name="rememberme" type="checkbox" id="rememberme" value="forever"> <label for="rememberme"><?php echo esc_html( Login_Text::display( 'remember' ) ); ?></label></p>
			<?php endif; ?>
			<p class="submit">
				<?php
				$buttons = array(
					'login'        => Login_Text::display( 'login_title' ),
					'register'     => Login_Text::display( 'register_link' ),
					'lostpassword' => Login_Text::display( 'lost_button' ),
					'resetpass'    => Login_Text::display( 'save_password' ),
					'twofactor'    => __( 'Verify', 'admin-studio' ),
				);
				?>
				<input type="submit" name="wp-submit" id="wp-submit" class="button button-primary button-large" value="<?php echo esc_attr( $buttons[ $screen ] ); ?>">
			</p>
		</form>
		<?php
		$shown = array();
		foreach ( $links[ $screen ] as $link ) {
			if ( 'hidden' !== $places[ $link ] ) {
				$shown[] = '<a class="' . esc_attr( $names[ $link ][0] ) . '" href="#">' . esc_html( $names[ $link ][1] ) . '</a>';
			}
		}
		?>
		<?php if ( $shown ) : ?>
			<p id="nav">
				<?php
				echo wp_kses(
					implode( ' | ', $shown ),
					array(
						'a' => array(
							'class' => true,
							'href'  => true,
						),
					)
				);
				?>
			</p>
		<?php endif; ?>
		<?php if ( 'hidden' !== $places['back'] ) : ?>
			<p id="backtoblog"><a href="<?php echo esc_url( home_url( '/' ) ); ?>">
				<?php echo wp_kses( Login_Text::back_text(), array() ); ?>
			</a></p>
		<?php endif; ?>
	</div>
		<?php
		self::footer();
		wp_print_scripts( array( 'adminstudio-login' ) );
		Preview::bridge();
		?>
</body>
</html>
		<?php
	}
}

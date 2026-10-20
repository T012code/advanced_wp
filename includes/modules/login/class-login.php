<?php
/**
 * Login page customizer.
 *
 * Restyles every screen on wp-login.php: log in, register, lost password,
 * reset password and the interim "session expired" login.
 *
 * @package AdvancedWP
 */

namespace AdvancedWP\Modules\Login;

use AdvancedWP\Brand_Kit;
use AdvancedWP\Color;
use AdvancedWP\Preview;
use AdvancedWP\Settings;

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

		if ( Settings::get( 'login.hide_lost_password' ) ) {
			add_filter( 'lost_password_html_link', '__return_empty_string' );
		}

		if ( Settings::get( 'login.hide_back_to_site' ) ) {
			add_filter( 'login_site_html_link', '__return_empty_string' );
		}

		if ( Settings::get( 'login.hide_language_switcher' ) ) {
			add_filter( 'login_display_language_dropdown', '__return_false' );
		}

		if ( Settings::get( 'login.hide_privacy_link' ) ) {
			add_filter( 'the_privacy_policy_link', array( __CLASS__, 'privacy_link' ) );
		}
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
		wp_enqueue_style( 'advwp-login', ADVWP_URL . 'assets/css/login.css', array( 'login' ), ADVWP_VERSION );
		wp_add_inline_style( 'advwp-login', self::css() );
		wp_enqueue_script( 'advwp-login', ADVWP_URL . 'assets/js/login.js', array(), ADVWP_VERSION, true );
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
				'--advwp-page-bg:' . $page_bg . ';',
				'--advwp-page-text:' . $page_text . ';',
				'--advwp-link:' . $link . ';',
				'--advwp-btn-bg:' . $btn_bg . ';',
				'--advwp-btn-text:' . $btn_txt . ';',
				'--advwp-btn-radius:' . $radius( 'login.button_radius', 'pill' === $b['button_style'] ? '999px' : $b['radius'] . 'px' ) . ';',
				'--advwp-form-width:' . (int) Settings::get( 'login.form_width' ) . 'px;',
				'--advwp-form-padding:' . (int) Settings::get( 'login.form_padding' ) . 'px;',
				'--advwp-form-radius:' . $radius( 'login.form_radius', $b['radius'] . 'px' ) . ';',
				'--advwp-field-fill:' . Color::mix( $b['surface'], $b['text'], 0.06 ) . ';',
				'--advwp-logo-h:' . $logo_height . 'px;',
				$logo ? '--advwp-logo-url:url("' . esc_url_raw( $logo ) . '");' : '',
			)
		);

		$css  = Brand_Kit::font_faces( $b );
		$css .= 'body.login.advwp{' . $vars . '}';

		if ( 'split' === $layout ) {
			$side = Settings::get( 'login.side_image' );
			$side = '' !== $side ? $side : Settings::get( 'login.bg_image' );
			if ( '' !== $side ) {
				$css .= 'body.login.advwp-layout-split::before{background-image:' . self::overlay_layer() . 'url("' . esc_url_raw( $side ) . '");}';
			}
		}

		$css .= Settings::get( 'login.custom_css' );

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
	 * Advanced WP body classes.
	 *
	 * @return string[]
	 */
	public static function classes() {
		$classes = array(
			'advwp',
			'advwp-layout-' . Settings::get( 'login.layout' ),
			'advwp-split-' . Settings::get( 'login.split_side' ),
			'advwp-fields-' . Settings::get( 'login.field_style' ),
			'advwp-btn-' . Settings::get( 'brand.button_style' ),
		);

		if ( Settings::get( 'login.hide_labels' ) ) {
			$classes[] = 'advwp-nolabels';
		}

		if ( Settings::get( 'login.hide_logo' ) ) {
			$classes[] = 'advwp-nologo';
		} elseif ( '' === self::brand()['logo'] ) {
			$classes[] = 'advwp-textlogo';
		}

		if ( ! Settings::get( 'login.form_shadow' ) ) {
			$classes[] = 'advwp-noshadow';
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

		return '<div class="advwp-message">' . wp_kses_post( wpautop( $custom ) ) . '</div>' . $message;
	}

	/**
	 * Prints the footer text and the background video.
	 *
	 * @return void
	 */
	public static function footer() {
		$footer = Settings::get( 'login.footer_text' );

		if ( '' !== trim( $footer ) ) {
			echo '<div class="advwp-footer">' . wp_kses_post( $footer ) . '</div>';
		}

		$video = Settings::get( 'login.bg_video' );

		if ( 'video' === Settings::get( 'login.bg_type' ) && '' !== $video ) {
			printf(
				'<video class="advwp-bg-video" src="%s" autoplay muted loop playsinline aria-hidden="true"></video>',
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
	 * Renders a look-alike login screen inside the Customizer preview.
	 *
	 * The real wp-login.php cannot run inside the preview frame, so this prints the same
	 * markup with the same styles applied.
	 *
	 * @return void
	 */
	public static function render_preview() {
		$classes = array_merge( array( 'login', 'no-js', 'login-action-login', 'wp-core-ui' ), self::classes() );
		$message = self::message( '' );

		wp_enqueue_style( 'login' );
		self::enqueue();

		?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php esc_html_e( 'Log In', 'advanced-wp' ); ?></title>
		<?php
		// Passing handles prints only these and skips the wp_print_styles action.
		wp_print_styles( array( 'login', 'advwp-login' ) );
		self::head();
		?>
</head>
<body class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>">
	<div id="login">
		<h1 role="presentation" class="wp-login-logo"><a href="<?php echo esc_url( self::header_url() ); ?>"><?php echo self::header_text(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in header_text(). ?></a></h1>
		<?php echo $message; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in message(). ?>
		<form name="loginform" id="loginform" action="#" method="post">
			<p>
				<label for="user_login"><?php esc_html_e( 'Username or Email Address', 'advanced-wp' ); ?></label>
				<input type="text" name="log" id="user_login" class="input" value="" size="20" autocapitalize="off" autocomplete="username">
			</p>
			<div class="user-pass-wrap">
				<label for="user_pass"><?php esc_html_e( 'Password', 'advanced-wp' ); ?></label>
				<div class="wp-pwd">
					<input type="password" name="pwd" id="user_pass" class="input password-input" value="" size="20" autocomplete="current-password">
					<button type="button" class="button button-secondary wp-hide-pw hide-if-no-js" aria-label="<?php esc_attr_e( 'Show password', 'advanced-wp' ); ?>">
						<span class="dashicons dashicons-visibility" aria-hidden="true"></span>
					</button>
				</div>
			</div>
			<p class="forgetmenot"><input name="rememberme" type="checkbox" id="rememberme" value="forever"> <label for="rememberme"><?php esc_html_e( 'Remember Me', 'advanced-wp' ); ?></label></p>
			<p class="submit">
				<input type="submit" name="wp-submit" id="wp-submit" class="button button-primary button-large" value="<?php esc_attr_e( 'Log In', 'advanced-wp' ); ?>">
			</p>
		</form>
		<?php if ( ! Settings::get( 'login.hide_lost_password' ) ) : ?>
			<p id="nav"><a href="#"><?php esc_html_e( 'Lost your password?', 'advanced-wp' ); ?></a></p>
		<?php endif; ?>
		<?php if ( ! Settings::get( 'login.hide_back_to_site' ) ) : ?>
			<p id="backtoblog"><a href="<?php echo esc_url( home_url( '/' ) ); ?>">
				<?php
				/* translators: %s: site title. */
				printf( esc_html__( '&larr; Go to %s', 'advanced-wp' ), esc_html( get_bloginfo( 'name' ) ) );
				?>
			</a></p>
		<?php endif; ?>
	</div>
		<?php
		self::footer();
		wp_print_scripts( array( 'advwp-login' ) );
		Preview::bridge();
		?>
</body>
</html>
		<?php
	}
}

<?php
/**
 * Builds branded HTML emails from templates and tags.
 *
 * @package AdminStudio
 */

namespace AdminStudio\Modules\Emails;

use AdminStudio\Brand_Kit;
use AdminStudio\Color;
use AdminStudio\Fonts;
use AdminStudio\Preview;
use AdminStudio\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Email renderer.
 */
class Renderer {

	/**
	 * Tags whose values are already safe HTML.
	 *
	 * @var string[]
	 */
	const HTML_TAGS = array( 'update_details', 'technical_details', 'comment_content' );

	/**
	 * Tags available in every email, with current values.
	 *
	 * @return array
	 */
	public static function common_context() {
		return array(
			'site_name'   => wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES ),
			'site_url'    => home_url( '/' ),
			'login_url'   => wp_login_url(),
			'admin_url'   => admin_url(),
			'admin_email' => get_option( 'admin_email' ),
			'date'        => date_i18n( get_option( 'date_format' ) ),
			'year'        => wp_date( 'Y' ),
		);
	}

	/**
	 * Context values for a user.
	 *
	 * @param \WP_User|array|null $user User object or user data array.
	 * @return array
	 */
	public static function user_context( $user ) {
		if ( is_array( $user ) ) {
			$user = (object) $user;
		}

		if ( ! $user || empty( $user->user_login ) ) {
			return array();
		}

		return array(
			'user_name'    => $user->user_login,
			'user_email'   => isset( $user->user_email ) ? $user->user_email : '',
			'display_name' => ! empty( $user->display_name ) ? $user->display_name : $user->user_login,
		);
	}

	/**
	 * Renders a template.
	 *
	 * @param string $key     Template key.
	 * @param array  $context Tag => value.
	 * @return array { subject, html, text }
	 */
	public static function render( $key, array $context ) {
		$template = Templates::get( $key );
		$prefix   = 'emails.templates.' . $key . '.';
		$context  = array_merge( self::common_context(), $context );

		$subject = self::replace( Settings::get( $prefix . 'subject' ), $context, false );
		$heading = self::replace( Settings::get( $prefix . 'heading' ), $context, false );
		$body    = self::replace( Settings::get( $prefix . 'body' ), $context, true );

		$button     = trim( Settings::get( $prefix . 'button' ) );
		$button_url = '';

		if ( '' !== $button && $template && '' !== $template['link'] && ! empty( $context[ $template['link'] ] ) ) {
			$button     = self::replace( $button, $context, false );
			$button_url = $context[ $template['link'] ];
		} else {
			$button = '';
		}

		$html = self::layout( $heading, $body, $button, $button_url, $context );

		return array(
			'subject' => wp_specialchars_decode( $subject, ENT_QUOTES ),
			'html'    => $html,
			'text'    => self::to_text( $html ),
		);
	}

	/**
	 * Replaces {tags}. Unknown tags are left as they are.
	 *
	 * @param string $text    Text with tags.
	 * @param array  $context Tag => value.
	 * @param bool   $html    Whether the output is HTML (values are escaped).
	 * @return string
	 */
	public static function replace( $text, array $context, $html ) {
		return preg_replace_callback(
			'/\{([a-z0-9_]+)\}/',
			static function ( $m ) use ( $context, $html ) {
				if ( ! array_key_exists( $m[1], $context ) ) {
					return $m[0];
				}

				$value = (string) $context[ $m[1] ];

				if ( ! $html ) {
					return wp_strip_all_tags( $value );
				}

				if ( in_array( $m[1], self::HTML_TAGS, true ) ) {
					return $value;
				}

				if ( preg_match( '/(_link|_url)$/', $m[1] ) ) {
					return esc_url( $value );
				}

				return esc_html( $value );
			},
			(string) $text
		);
	}

	/**
	 * Wraps content in the branded email layout.
	 *
	 * @param string $heading    Heading text (plain).
	 * @param string $body       Body HTML.
	 * @param string $button     Button text (plain), or ''.
	 * @param string $button_url Button URL.
	 * @param array  $context    Tags, used for the footer.
	 * @return string
	 */
	public static function layout( $heading, $body, $button = '', $button_url = '', array $context = array() ) {
		$s = self::style();

		if ( ! preg_match( '/<(p|div|table|ul|ol|h[1-6]|blockquote)[\s>]/i', $body ) ) {
			$body = wpautop( $body );
		}

		// Inline styles on common tags, since many mail apps ignore <style>.
		$body = self::inline_styles( $body, $s );

		$footer = self::replace( Settings::get( 'emails.footer_text' ), array_merge( self::common_context(), $context ), true );
		$logo   = $s['logo'];
		$title  = '' !== $heading ? $heading : $s['site_name'];

		ob_start();
		?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr( get_bloginfo( 'language' ) ); ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="x-apple-disable-message-reformatting">
<title><?php echo esc_html( $title ); ?></title>
<!-- adminstudio-email -->
</head>
<body style="margin:0;padding:0;background:<?php echo esc_attr( $s['body_bg'] ); ?>;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:<?php echo esc_attr( $s['body_bg'] ); ?>;">
<tr><td align="center" style="padding:32px 16px;">
	<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:<?php echo (int) $s['width']; ?>px;background:<?php echo esc_attr( $s['content_bg'] ); ?>;border-radius:<?php echo (int) $s['radius']; ?>px;border:1px solid <?php echo esc_attr( $s['border'] ); ?>;overflow:hidden;">
		<?php if ( $s['show_logo'] ) : ?>
		<tr><td align="center" style="padding:28px 32px;background:<?php echo esc_attr( $s['header_bg'] ); ?>;">
			<?php if ( '' !== $logo ) : ?>
				<img src="<?php echo esc_url( $logo ); ?>" width="<?php echo (int) $s['logo_width']; ?>" alt="<?php echo esc_attr( $s['site_name'] ); ?>" style="display:block;width:<?php echo (int) $s['logo_width']; ?>px;max-width:100%;height:auto;border:0;">
			<?php else : ?>
				<span style="font-family:<?php echo esc_attr( $s['font'] ); ?>;font-size:20px;font-weight:700;color:<?php echo esc_attr( $s['header_text'] ); ?>;"><?php echo esc_html( $s['site_name'] ); ?></span>
			<?php endif; ?>
		</td></tr>
		<?php endif; ?>
		<tr><td style="padding:32px;font-family:<?php echo esc_attr( $s['font'] ); ?>;font-size:16px;line-height:1.6;color:<?php echo esc_attr( $s['text'] ); ?>;">
			<?php if ( '' !== $heading ) : ?>
				<h1 style="margin:0 0 16px;font-family:<?php echo esc_attr( $s['font'] ); ?>;font-size:24px;line-height:1.3;font-weight:700;color:<?php echo esc_attr( $s['text'] ); ?>;"><?php echo esc_html( $heading ); ?></h1>
			<?php endif; ?>
			<?php echo wp_kses_post( $body ); ?>
			<?php if ( '' !== $button ) : ?>
				<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:28px 0 8px;">
					<tr><td style="border-radius:<?php echo esc_attr( $s['button_radius'] ); ?>;background:<?php echo esc_attr( $s['button_bg'] ); ?>;">
						<a href="<?php echo esc_url( $button_url ); ?>" style="display:inline-block;padding:14px 28px;font-family:<?php echo esc_attr( $s['font'] ); ?>;font-size:16px;font-weight:600;line-height:1;color:<?php echo esc_attr( $s['button_text'] ); ?>;text-decoration:none;border-radius:<?php echo esc_attr( $s['button_radius'] ); ?>;"><?php echo esc_html( $button ); ?></a>
					</td></tr>
				</table>
				<p style="margin:16px 0 0;font-size:13px;line-height:1.5;color:<?php echo esc_attr( $s['muted'] ); ?>;word-break:break-all;">
					<?php esc_html_e( 'If the button does not work, copy this link into your browser:', 'admin-studio' ); ?><br>
					<a href="<?php echo esc_url( $button_url ); ?>" style="color:<?php echo esc_attr( $s['link'] ); ?>;"><?php echo esc_html( $button_url ); ?></a>
				</p>
			<?php endif; ?>
		</td></tr>
	</table>
		<?php if ( '' !== trim( $footer ) ) : ?>
	<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:<?php echo (int) $s['width']; ?>px;">
		<tr><td align="center" style="padding:24px 16px 0;font-family:<?php echo esc_attr( $s['font'] ); ?>;font-size:13px;line-height:1.5;color:<?php echo esc_attr( $s['footer_text'] ); ?>;">
			<?php echo wp_kses_post( $footer ); ?>
		</td></tr>
	</table>
	<?php endif; ?>
</td></tr>
</table>
</body>
</html>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Resolved email styles.
	 *
	 * @return array
	 */
	public static function style() {
		$b = Brand_Kit::get(
			array(
				'primary'    => Settings::get( 'emails.button_bg' ),
				'text'       => Settings::get( 'emails.text_color' ),
				'surface'    => Settings::get( 'emails.content_bg' ),
				'background' => Settings::get( 'emails.body_bg' ),
				'logo'       => Settings::get( 'emails.logo' ),
				'logo_width' => Settings::get( 'emails.logo_width' ),
			)
		);

		$header_bg   = Settings::get( 'emails.header_bg' );
		$header_bg   = '' !== $header_bg ? $header_bg : $b['surface'];
		$header_text = Settings::get( 'emails.header_text' );
		$button_text = Settings::get( 'emails.button_text' );
		$radius      = Settings::get( 'emails.radius' );
		$radius      = '' !== $radius ? (int) $radius : $b['radius'];

		$fonts = array(
			'brand'     => $b['font_body'],
			'system'    => Fonts::system_stack(),
			'arial'     => 'Arial, Helvetica, sans-serif',
			'helvetica' => '"Helvetica Neue", Helvetica, Arial, sans-serif',
			'georgia'   => 'Georgia, "Times New Roman", serif',
			'verdana'   => 'Verdana, Geneva, sans-serif',
			'trebuchet' => '"Trebuchet MS", Tahoma, sans-serif',
		);
		$font  = $fonts[ Settings::get( 'emails.font' ) ] ?? $fonts['system'];

		// Theme font stacks can hold CSS variables, which mail apps cannot read.
		if ( false !== strpos( $font, 'var(' ) ) {
			$font = Fonts::system_stack();
		}

		return array(
			'logo'          => Settings::get( 'emails.show_logo' ) ? $b['logo'] : '',
			'show_logo'     => (bool) Settings::get( 'emails.show_logo' ),
			'logo_width'    => min( (int) $b['logo_width'], 400 ),
			'site_name'     => $b['site_name'],
			'body_bg'       => $b['background'],
			'content_bg'    => $b['surface'],
			'border'        => $b['border'],
			'header_bg'     => $header_bg,
			'header_text'   => '' !== $header_text ? $header_text : Color::readable_on( $header_bg ),
			'text'          => $b['text'],
			'muted'         => $b['muted'],
			'link'          => $b['primary'],
			'button_bg'     => $b['primary'],
			'button_text'   => '' !== $button_text ? $button_text : Color::readable_on( $b['primary'] ),
			'button_radius' => 'pill' === $b['button_style'] ? '999px' : $radius . 'px',
			'radius'        => $radius,
			'width'         => (int) Settings::get( 'emails.width' ),
			'font'          => str_replace( '"', "'", $font ),
			'footer_text'   => Color::mix( $b['text'], $b['background'], 0.4 ),
		);
	}

	/**
	 * Adds inline styles to paragraphs, links and other common tags.
	 *
	 * @param string $html Body HTML.
	 * @param array  $s    Styles.
	 * @return string
	 */
	private static function inline_styles( $html, array $s ) {
		$styles = array(
			'p'          => 'margin:0 0 16px;',
			'a'          => 'color:' . $s['link'] . ';text-decoration:underline;',
			'h2'         => 'margin:24px 0 12px;font-size:20px;line-height:1.3;',
			'h3'         => 'margin:24px 0 8px;font-size:17px;line-height:1.3;',
			'blockquote' => 'margin:16px 0;padding:12px 16px;border:1px solid ' . $s['border'] . ';border-radius:' . (int) $s['radius'] . 'px;background:' . Color::mix( $s['content_bg'], $s['text'], 0.04 ) . ';',
			'ul'         => 'margin:0 0 16px;padding-left:20px;',
			'ol'         => 'margin:0 0 16px;padding-left:20px;',
			'pre'        => 'white-space:pre-wrap;font-size:13px;',
		);

		foreach ( $styles as $tag => $style ) {
			// Only tags without their own style attribute.
			$html = preg_replace( '/<' . $tag . '(?![^>]*\sstyle=)(\s[^>]*)?>/i', '<' . $tag . '$1 style="' . esc_attr( $style ) . '">', $html );
		}

		return $html;
	}

	/**
	 * Plain-text version of an HTML email.
	 *
	 * @param string $html Email HTML.
	 * @return string
	 */
	public static function to_text( $html ) {
		$text = preg_replace( '#<(head|style|script)[^>]*>.*?</\1>#is', '', $html );
		$text = preg_replace_callback(
			'#<a\s[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)</a>#is',
			static function ( $m ) {
				$label = trim( html_entity_decode( wp_strip_all_tags( $m[2] ), ENT_QUOTES ) );
				$url   = html_entity_decode( $m[1], ENT_QUOTES );
				return $label === $url || '' === $label ? $url : $label . ' (' . $url . ')';
			},
			$text
		);
		$text = preg_replace( '#<br\s*/?>#i', "\n", $text );
		$text = preg_replace( '#</(p|div|h[1-6]|li|tr|blockquote|table)>#i', "\n\n", $text );
		$text = html_entity_decode( wp_strip_all_tags( $text ), ENT_QUOTES, 'UTF-8' );
		$text = preg_replace( "/[ \t]+/", ' ', $text );
		$text = preg_replace( "/ *\n */", "\n", $text );
		$text = preg_replace( "/\n{3,}/", "\n\n", $text );

		return trim( $text );
	}

	/**
	 * Sample tag values for previews and test emails.
	 *
	 * @return array
	 */
	public static function sample_context() {
		$user    = wp_get_current_user();
		$home    = home_url( '/' );
		$context = $user->exists() ? self::user_context( $user ) : array(
			'user_name'    => 'jane',
			'user_email'   => 'jane@example.com',
			'display_name' => 'Jane Doe',
		);

		return array_merge(
			$context,
			array(
				'set_password_link'    => wp_login_url() . '?action=rp&key=sample',
				'reset_link'           => wp_login_url() . '?action=rp&key=sample',
				'ip_address'           => '203.0.113.42',
				'new_email'            => 'new.address@example.com',
				'old_email'            => $user->user_email ? $user->user_email : 'old.address@example.com',
				'confirm_link'         => admin_url( 'profile.php' ),
				'download_link'        => $home,
				'expiry_date'          => date_i18n( get_option( 'date_format' ), strtotime( '+3 days' ) ),
				'privacy_policy_url'   => get_privacy_policy_url() ? get_privacy_policy_url() : $home,
				'user_edit_link'       => admin_url( 'users.php' ),
				'post_title'           => __( 'Hello world!', 'admin-studio' ),
				'post_link'            => $home,
				'comment_author'       => 'Ada Lovelace',
				'comment_author_email' => 'ada@example.com',
				'comment_content'      => esc_html__( 'Thanks for writing this. The second section answered exactly what I was looking for.', 'admin-studio' ),
				'comment_link'         => admin_url( 'edit-comments.php' ),
				'moderate_link'        => admin_url( 'edit-comments.php?comment_status=moderated' ),
				'pending_count'        => '3',
				/* translators: %s: site title. */
				'update_subject'       => sprintf( __( '[%s] Some plugins were automatically updated', 'admin-studio' ), get_bloginfo( 'name' ) ),
				'update_details'       => '<p>' . esc_html__( 'These plugins are now up to date: Example Plugin (from version 1.2 to 1.3).', 'admin-studio' ) . '</p>',
				'updates_link'         => admin_url( 'update-core.php' ),
				'recovery_link'        => wp_login_url() . '?action=enter_recovery_mode',
				'technical_details'    => '<pre>' . esc_html__( 'An error of type E_ERROR was caused in line 12 of the file example-plugin.php.', 'admin-studio' ) . '</pre>',
			)
		);
	}

	/**
	 * Full HTML of a template filled with sample data.
	 *
	 * @param string $key Template key.
	 * @return string
	 */
	public static function preview( $key ) {
		if ( ! Templates::get( $key ) ) {
			$key = 'welcome';
		}

		$email = self::render( $key, self::sample_context() );

		return $email['html'];
	}

	/**
	 * Prints a template preview in the browser.
	 *
	 * @param string $key    Template key.
	 * @param bool   $bridge Whether to add the Customizer preview bridge.
	 * @return void
	 */
	public static function print_preview( $key, $bridge = false ) {
		$html = preg_replace( '#^\s*<!DOCTYPE[^>]*>#i', '', self::preview( $key ) );
		$html = preg_replace( '#</body>\s*</html>\s*$#i', '', $html );

		echo '<!DOCTYPE html>';
		echo wp_kses( $html, self::document_tags() );

		if ( $bridge ) {
			Preview::bridge();
		}

		echo '</body></html>';
	}

	/**
	 * Tags allowed in a full email document: post content plus the document wrapper.
	 *
	 * @return array
	 */
	private static function document_tags() {
		$tags = wp_kses_allowed_html( 'post' );

		$tags['html']  = array( 'lang' => true );
		$tags['head']  = array();
		$tags['meta']  = array(
			'charset' => true,
			'name'    => true,
			'content' => true,
		);
		$tags['title'] = array();
		$tags['body']  = array( 'style' => true );

		return $tags;
	}
}

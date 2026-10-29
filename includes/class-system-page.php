<?php
/**
 * Standalone branded page used for error, maintenance and coming soon screens.
 *
 * Pages are self-contained HTML with inline CSS so they also work as static
 * drop-in files when WordPress or the database is unavailable.
 *
 * @package Adminora
 */

namespace Adminora;

defined( 'ABSPATH' ) || exit;

/**
 * Renders system pages.
 */
class System_Page {

	/**
	 * Counter that keeps each page's style handle unique within a request.
	 *
	 * @var int
	 */
	private static $count = 0;

	/**
	 * Renders a page to a string, for the static drop-in copies.
	 *
	 * @param array $args  See output().
	 * @param array $brand Values from Brand_Kit::get().
	 * @return string
	 */
	public static function render( array $args, array $brand ) {
		ob_start();
		self::output( $args, $brand );
		return (string) ob_get_clean();
	}

	/**
	 * Prints a page.
	 *
	 * @param array $args {
	 *     Page content and options.
	 *
	 *     @type string $title     Document title.
	 *     @type string $heading   Main heading.
	 *     @type string $message   Message HTML.
	 *     @type array  $buttons   List of [ text, url ] pairs; the first is primary.
	 *     @type string $extra     Extra HTML after the message, such as a search form or countdown.
	 *     @type string $script    Inline JavaScript printed before </body>.
	 *     @type string $image     Illustration URL.
	 *     @type bool   $show_logo Whether to show the logo.
	 *     @type string $bg_image  Full-page background image URL.
	 *     @type int    $overlay   Background darkening, 0 to 100.
	 *     @type string $variant   `card` (default) or `hero`.
	 *     @type bool   $noindex   Whether to add a noindex meta tag.
	 *     @type bool   $preview   Whether to print the Customizer preview bridge.
	 * }
	 * @param array $brand Values from Brand_Kit::get().
	 * @return void
	 */
	public static function output( array $args, array $brand ) {
		$args = array_merge(
			array(
				'title'     => '',
				'heading'   => '',
				'message'   => '',
				'buttons'   => array(),
				'extra'     => '',
				'script'    => '',
				'image'     => '',
				'show_logo' => true,
				'bg_image'  => '',
				'overlay'   => 0,
				'variant'   => 'card',
				'noindex'   => true,
				'preview'   => false,
			),
			$args
		);

		$handle = 'adminora-system-page-' . ( ++self::$count );

		wp_register_style( $handle, false, array(), ADMINORA_VERSION );
		wp_add_inline_style( $handle, self::css( $args, $brand ) );
		?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr( get_bloginfo( 'language' ) ); ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
		<?php if ( $args['noindex'] ) : ?>
<meta name="robots" content="noindex, nofollow">
		<?php endif; ?>
<title><?php echo esc_html( $args['title'] ); ?></title>
		<?php if ( ! empty( $brand['favicon'] ) ) : ?>
<link rel="icon" href="<?php echo esc_url( $brand['favicon'] ); ?>">
		<?php endif; ?>
<meta name="theme-color" content="<?php echo esc_attr( $brand['primary'] ); ?>">
		<?php
		Brand_Kit::print_theme_fonts( $brand );
		wp_print_styles( array( $handle ) );
		?>
</head>
<body class="adminora-page adminora-page--<?php echo esc_attr( $args['variant'] ); ?>">
<main class="adminora-page__inner">
		<?php if ( $args['show_logo'] && ! empty( $brand['logo'] ) ) : ?>
		<img class="adminora-page__logo" src="<?php echo esc_url( $brand['logo'] ); ?>" alt="<?php echo esc_attr( $brand['site_name'] ); ?>">
	<?php elseif ( $args['show_logo'] ) : ?>
		<p class="adminora-page__sitename"><?php echo esc_html( $brand['site_name'] ); ?></p>
	<?php endif; ?>
	<div class="adminora-page__card">
		<?php if ( '' !== $args['image'] ) : ?>
			<img class="adminora-page__image" src="<?php echo esc_url( $args['image'] ); ?>" alt="">
		<?php endif; ?>
		<h1 class="adminora-page__heading"><?php echo esc_html( $args['heading'] ); ?></h1>
		<?php if ( '' !== trim( $args['message'] ) ) : ?>
			<div class="adminora-page__message"><?php echo wp_kses_post( wpautop( $args['message'] ) ); ?></div>
		<?php endif; ?>
		<?php echo wp_kses( $args['extra'], self::extra_tags() ); ?>
		<?php if ( $args['buttons'] ) : ?>
			<p class="adminora-page__actions">
				<?php foreach ( $args['buttons'] as $i => $button ) : ?>
					<a class="adminora-page__button<?php echo $i ? ' adminora-page__button--secondary' : ''; ?>" href="<?php echo esc_url( $button[1] ); ?>"><?php echo esc_html( $button[0] ); ?></a>
				<?php endforeach; ?>
			</p>
		<?php endif; ?>
	</div>
</main>
		<?php
		if ( '' !== $args['script'] ) {
			wp_print_inline_script_tag( $args['script'] );
		}

		if ( $args['preview'] ) {
			Preview::bridge();
		}
		?>
</body>
</html>
		<?php
	}

	/**
	 * Tags allowed in the `extra` slot: search forms and the countdown.
	 *
	 * @return array
	 */
	private static function extra_tags() {
		$common = array(
			'class'      => true,
			'id'         => true,
			'aria-label' => true,
			'data-*'     => true,
		);

		return array(
			'form'   => array_merge(
				$common,
				array(
					'role'   => true,
					'method' => true,
					'action' => true,
				)
			),
			'label'  => array_merge( $common, array( 'for' => true ) ),
			'input'  => array_merge(
				$common,
				array(
					'type'        => true,
					'name'        => true,
					'value'       => true,
					'placeholder' => true,
				)
			),
			'button' => array_merge( $common, array( 'type' => true ) ),
			'p'      => $common,
			'ul'     => $common,
			'li'     => $common,
			'strong' => $common,
			'span'   => $common,
		);
	}

	/**
	 * CSS for a page: font faces, brand variables and the base stylesheet.
	 *
	 * @param array $args  Page arguments.
	 * @param array $brand Brand values.
	 * @return string
	 */
	private static function css( array $args, array $brand ) {
		$bg      = $brand['background'];
		$page_fg = isset( $brand['page_text'] ) ? $brand['page_text'] : Color::readable_on( $bg );
		$page_bg = $bg;

		if ( '' !== $args['bg_image'] ) {
			$alpha   = max( 0, min( 100, (int) $args['overlay'] ) ) / 100;
			$tint    = Color::rgba( '#000000', $alpha );
			$page_bg = 'linear-gradient(' . $tint . ',' . $tint . '),url("' . esc_url_raw( $args['bg_image'] ) . '") center/cover no-repeat ' . $bg;
		}

		$vars = Brand_Kit::css_vars( $brand ) . '--adminora-page-bg:' . $page_bg . ';--adminora-page-text:' . Brand_Kit::css_value( $page_fg ) . ';';

		return Brand_Kit::font_faces( $brand ) . ':root{' . $vars . '}' . self::stylesheet();
	}

	/**
	 * Base stylesheet for system pages.
	 *
	 * @return string
	 */
	private static function stylesheet() {
		$file = ADMINORA_DIR . 'assets/css/system-page.css';

		// Local plugin file, inlined so the page has no external dependencies.
		return is_readable( $file ) ? (string) file_get_contents( $file ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	}
}

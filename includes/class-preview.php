<?php
/**
 * Front-end preview pages for the Customizer.
 *
 * The Customizer can only preview front-end URLs, so the login page, emails
 * and system pages are rendered at `?adminora_preview=...` for users who can
 * customize the site.
 *
 * @package Adminora
 */

namespace Adminora;

defined( 'ABSPATH' ) || exit;

/**
 * Routes and renders preview requests.
 */
class Preview {

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'route' ), 0 );
	}

	/**
	 * Builds a preview URL.
	 *
	 * @param string $type Preview type: login, email, page or maintenance.
	 * @param array  $args Extra query arguments.
	 * @return string
	 */
	public static function url( $type, array $args = array() ) {
		return add_query_arg( array_merge( array( 'adminora_preview' => $type ), $args ), home_url( '/' ) );
	}

	/**
	 * Whether the current request is an allowed preview.
	 *
	 * @return bool
	 */
	public static function is_preview() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only routing; access is limited to the Customizer preview.
		return isset( $_GET['adminora_preview'] ) && is_customize_preview() && current_user_can( 'customize' );
	}

	/**
	 * Renders the requested preview and stops.
	 *
	 * @return void
	 */
	public static function route() {
		if ( ! self::is_preview() ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- See is_preview().
		$type = isset( $_GET['adminora_preview'] ) ? sanitize_key( wp_unslash( $_GET['adminora_preview'] ) ) : '';

		switch ( $type ) {
			case 'login':
				Modules\Login\Login::render_preview();
				break;

			case 'email':
				$template = isset( $_GET['adminora_template'] ) ? sanitize_key( wp_unslash( $_GET['adminora_template'] ) ) : 'welcome';
				Modules\Emails\Renderer::print_preview( $template, true );
				break;

			case 'page':
				$page = isset( $_GET['adminora_page'] ) ? sanitize_key( wp_unslash( $_GET['adminora_page'] ) ) : 'critical';
				Modules\Error_Pages\Error_Pages::render_preview( $page );
				break;

			case 'maintenance':
				Modules\Maintenance\Maintenance::render_page();
				break;
		}
		// phpcs:enable

		exit;
	}

	/**
	 * Prints the scripts the Customizer needs to talk to the preview frame.
	 *
	 * Our preview pages skip wp_head() and wp_footer() so the theme's styles
	 * do not leak in, so the Customizer bridge is printed by hand.
	 *
	 * @return void
	 */
	public static function bridge() {
		global $wp_customize;

		if ( ! is_customize_preview() || ! $wp_customize instanceof \WP_Customize_Manager ) {
			return;
		}

		wp_print_scripts( array( 'customize-preview' ) );
		$wp_customize->customize_preview_settings();
	}
}

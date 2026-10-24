<?php
/**
 * Coming soon and maintenance mode.
 *
 * Visitors see a branded holding page, either the design from the Customizer
 * or a page built in the editor or a page builder such as Elementor. Chosen
 * roles, allow-listed paths and anyone with the bypass link see the real site.
 *
 * @package AdminStudio
 */

namespace AdminStudio\Modules\Maintenance;

use AdminStudio\Brand_Kit;
use AdminStudio\Color;
use AdminStudio\Preview;
use AdminStudio\Settings;
use AdminStudio\System_Page;

defined( 'ABSPATH' ) || exit;

/**
 * Maintenance mode module.
 */
class Maintenance {

	const COOKIE  = 'adminstudio_bypass';
	const PREVIEW = 'adminstudio_holding';

	/**
	 * Holding page being shown on this request.
	 *
	 * @var int
	 */
	private static $page = 0;

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'maybe_show' ), 1 );
		add_action( 'admin_bar_menu', array( __CLASS__, 'badge' ), 100 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'badge_css' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'badge_css' ) );
	}

	/**
	 * Whether the mode is maintenance (as opposed to coming soon).
	 *
	 * @return bool
	 */
	private static function is_maintenance() {
		return 'maintenance' === Settings::get( 'maintenance.mode' );
	}

	/**
	 * Whether the current visitor may see the real site.
	 *
	 * @return bool
	 */
	public static function can_bypass() {
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		$user = wp_get_current_user();

		if ( $user->exists() && array_intersect( (array) $user->roles, (array) Settings::get( 'maintenance.roles' ) ) ) {
			return true;
		}

		$key = Settings::get( 'maintenance.bypass_key' );

		if ( '' !== $key ) {
			$hash = wp_hash( 'adminstudio-bypass-' . $key );

			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- The key itself is the secret.
			if ( isset( $_GET[ self::COOKIE ] ) && hash_equals( $key, sanitize_title( wp_unslash( $_GET[ self::COOKIE ] ) ) ) ) {
				setcookie( self::COOKIE, $hash, time() + WEEK_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
				return true;
			}

			if ( isset( $_COOKIE[ self::COOKIE ] ) && hash_equals( $hash, sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE ] ) ) ) ) {
				return true;
			}
		}

		$uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';

		return self::path_allowed( (string) wp_parse_url( $uri, PHP_URL_PATH ) );
	}

	/**
	 * Whether a path is one of the pages that stay open.
	 *
	 * @param string $path Address path, such as /contact.
	 * @return bool
	 */
	private static function path_allowed( $path ) {
		$path = '/' . ltrim( $path, '/' );

		foreach ( preg_split( '/[\r\n]+/', (string) Settings::get( 'maintenance.allowed_paths' ) ) as $allowed ) {
			$allowed = trim( $allowed );

			if ( '' !== $allowed && 0 === strpos( $path, '/' . ltrim( $allowed, '/' ) ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Makes links into the site on the holding page plain text, so visitors
	 * cannot open any other page from it. The logo, menus and buttons that
	 * point at the site stop being links. Links to other sites, links within
	 * the page (#section), email and phone links, and links to pages that
	 * stay open keep working.
	 *
	 * Used as an output buffer callback for the whole holding page.
	 *
	 * @param string $html Page HTML.
	 * @return string
	 */
	public static function strip_site_links( $html ) {
		if ( '' === $html || ! class_exists( '\WP_HTML_Tag_Processor' ) ) {
			return $html;
		}

		$tags = new \WP_HTML_Tag_Processor( $html );

		while ( $tags->next_tag() ) {
			if ( ! in_array( $tags->get_tag(), array( 'A', 'AREA' ), true ) ) {
				continue;
			}

			$href = $tags->get_attribute( 'href' );

			if ( is_string( $href ) && self::leads_into_site( $href ) ) {
				$tags->remove_attribute( 'href' );
				$tags->remove_attribute( 'target' );
			}
		}

		return $tags->get_updated_html();
	}

	/**
	 * Whether following a link would open another page of this site.
	 *
	 * @param string $href Link address.
	 * @return bool
	 */
	private static function leads_into_site( $href ) {
		$href = trim( $href );

		// A jump within the page.
		if ( '' !== $href && '#' === $href[0] ) {
			return false;
		}

		$scheme = wp_parse_url( $href, PHP_URL_SCHEME );

		// mailto:, tel: and similar do not open a page here.
		if ( $scheme && ! in_array( strtolower( $scheme ), array( 'http', 'https' ), true ) ) {
			return false;
		}

		$host = wp_parse_url( $href, PHP_URL_HOST );

		if ( $host ) {
			$own = array_filter(
				array(
					wp_parse_url( home_url(), PHP_URL_HOST ),
					wp_parse_url( site_url(), PHP_URL_HOST ),
				)
			);

			if ( ! in_array( strtolower( $host ), array_map( 'strtolower', $own ), true ) ) {
				return false;
			}
		}

		// An address on this site, or a relative one (which is always on this site).
		return ! self::path_allowed( (string) wp_parse_url( $href, PHP_URL_PATH ) );
	}

	/**
	 * Shows the holding page to visitors who cannot bypass it.
	 *
	 * @return void
	 */
	public static function maybe_show() {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || is_customize_preview() || Preview::is_preview() ) {
			return;
		}

		// Administrators can open the holding page on purpose to check it.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only view, limited to administrators.
		$preview = isset( $_GET[ self::PREVIEW ] ) && current_user_can( 'manage_options' );

		if ( ! $preview && self::can_bypass() ) {
			return;
		}

		if ( self::is_maintenance() && ! $preview ) {
			status_header( 503 );
			header( 'Retry-After: 3600' );
		} else {
			status_header( 200 );
		}

		nocache_headers();

		$page = self::page_id();

		if ( $page ) {
			self::use_page( $page );
			return;
		}

		header( 'Content-Type: text/html; charset=utf-8' );

		ob_start( array( __CLASS__, 'strip_site_links' ) );
		self::render_page();
		exit;
	}

	/**
	 * The page chosen as the holding page, or 0 to use the Customizer design.
	 *
	 * @return int
	 */
	public static function page_id() {
		if ( 'page' !== Settings::get( 'maintenance.design' ) ) {
			return 0;
		}

		$id   = (int) Settings::get( 'maintenance.page' );
		$post = $id ? get_post( $id ) : null;

		if ( ! $post || ! isset( self::page_types()[ $post->post_type ] ) || in_array( $post->post_status, array( 'trash', 'auto-draft', 'inherit' ), true ) ) {
			return 0;
		}

		return $id;
	}

	/**
	 * Content types a holding page can come from: post type => label.
	 *
	 * @return array
	 */
	public static function page_types() {
		$types = array( 'page' => __( 'Pages', 'admin-studio' ) );

		if ( post_type_exists( 'elementor_library' ) ) {
			$types['elementor_library'] = __( 'Elementor templates', 'admin-studio' );
		}

		return $types;
	}

	/**
	 * Pages and page templates that can be chosen, grouped by type.
	 *
	 * Drafts and private pages are included, so the holding page does not
	 * have to be published on the site.
	 *
	 * @return array Type label => [ id => title ].
	 */
	public static function choices() {
		$groups = array();

		foreach ( self::page_types() as $type => $label ) {
			$args = array(
				'post_type'      => $type,
				'post_status'    => array( 'publish', 'private', 'draft', 'pending', 'future' ),
				'posts_per_page' => 100,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			);

			if ( 'elementor_library' === $type ) {
				// Only whole-page templates, not sections or headers.
				$args['meta_key']   = '_elementor_template_type'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Small admin-only list.
				$args['meta_value'] = 'page'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Small admin-only list.
			}

			$items = array();
			foreach ( get_posts( $args ) as $post ) {
				$title = '' !== $post->post_title ? $post->post_title : __( '(no title)', 'admin-studio' );
				$notes = array();

				if ( 'page' === $type && self::is_elementor( $post->ID ) ) {
					$notes[] = __( 'Elementor', 'admin-studio' );
				}
				if ( 'publish' !== $post->post_status ) {
					$notes[] = get_post_status_object( $post->post_status ) ? get_post_status_object( $post->post_status )->label : $post->post_status;
				}

				$items[ $post->ID ] = $title . ( $notes ? ' (' . implode( ', ', $notes ) . ')' : '' );
			}

			if ( $items ) {
				$groups[ $label ] = $items;
			}
		}

		return $groups;
	}

	/**
	 * Whether a page was built with Elementor.
	 *
	 * @param int $id Post ID.
	 * @return bool
	 */
	public static function is_elementor( $id ) {
		return did_action( 'elementor/loaded' ) && 'builder' === get_post_meta( $id, '_elementor_edit_mode', true );
	}

	/**
	 * Address that edits a holding page, in Elementor when it was built there.
	 *
	 * @param int $id Post ID.
	 * @return string
	 */
	public static function edit_url( $id ) {
		if ( 'elementor_library' === get_post_type( $id ) || self::is_elementor( $id ) ) {
			return add_query_arg(
				array(
					'post'   => $id,
					'action' => 'elementor',
				),
				admin_url( 'post.php' )
			);
		}

		return (string) get_edit_post_link( $id, 'raw' );
	}

	/**
	 * Shows the chosen page in place of whatever was requested.
	 *
	 * The page is printed full screen by our own template.
	 *
	 * @param int $id Post ID.
	 * @return void
	 */
	private static function use_page( $id ) {
		self::$page = $id;

		// The address asked for is not the holding page's own address, so stop WordPress redirecting to it.
		remove_action( 'template_redirect', 'redirect_canonical' );

		// After Elementor's own checks, which turn visitors away from template addresses.
		add_action( 'template_redirect', array( __CLASS__, 'swap_query' ), 11 );

		if ( ! self::is_maintenance() ) {
			add_filter( 'wp_robots', 'wp_robots_no_robots' );
		}

		// Nothing on the holding page may lead into the site: no toolbar, and
		// no head links that give away the page's own address.
		add_filter( 'show_admin_bar', '__return_false' );
		remove_action( 'wp_enqueue_scripts', 'wp_enqueue_admin_bar_bump_styles' );
		remove_action( 'wp_head', 'rel_canonical' );
		remove_action( 'wp_head', 'wp_shortlink_wp_head' );
		remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );

		add_filter( 'template_include', array( __CLASS__, 'page_template' ), PHP_INT_MAX );
	}

	/**
	 * Replaces the main query with the holding page.
	 *
	 * @return void
	 */
	public static function swap_query() {
		global $wp_query;

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- The holding page replaces the page that was asked for.
		$wp_query = new \WP_Query(
			array(
				'p'           => self::$page,
				'post_type'   => get_post_type( self::$page ),
				'post_status' => get_post_status( self::$page ),
			)
		);
	}

	/**
	 * Template for a chosen holding page.
	 *
	 * @return string
	 */
	public static function page_template() {
		ob_start( array( __CLASS__, 'strip_site_links' ) );

		return ADMINSTUDIO_DIR . 'templates/holding-page.php';
	}

	/**
	 * Prints the holding page.
	 *
	 * @return void
	 */
	public static function render_page() {
		$bg_image = Settings::get( 'maintenance.bg_image' );
		$overlay  = (int) Settings::get( 'maintenance.overlay_opacity' );
		$brand    = Brand_Kit::get( array( 'background' => Settings::get( 'maintenance.bg_color' ) ) );
		$text     = Settings::get( 'maintenance.text_color' );

		if ( '' !== $text ) {
			$brand['page_text'] = $text;
		} elseif ( '' !== $bg_image && $overlay >= 40 ) {
			$brand['page_text'] = '#ffffff';
		}

		list( $countdown, $script ) = self::countdown();

		System_Page::output(
			array(
				'title'     => get_bloginfo( 'name' ),
				'heading'   => Settings::get( 'maintenance.headline' ),
				'message'   => Settings::get( 'maintenance.message' ),
				'show_logo' => (bool) Settings::get( 'maintenance.show_logo' ),
				'bg_image'  => $bg_image,
				'overlay'   => $overlay,
				'variant'   => 'hero',
				'extra'     => $countdown,
				'script'    => $script,
				'preview'   => Preview::is_preview(),
				'noindex'   => self::is_maintenance() ? false : true,
			),
			$brand
		);
	}

	/**
	 * Countdown markup and script, or empty strings when there is no future date.
	 *
	 * @return string[]
	 */
	private static function countdown() {
		$value = Settings::get( 'maintenance.countdown' );

		if ( '' === $value ) {
			return array( '', '' );
		}

		try {
			$target = ( new \DateTime( $value, wp_timezone() ) )->getTimestamp();
		} catch ( \Exception $e ) {
			return array( '', '' );
		}

		$left = $target - time();

		if ( $left <= 0 ) {
			return array( '', '' );
		}

		$units = array(
			'd' => array( (int) floor( $left / DAY_IN_SECONDS ), __( 'Days', 'admin-studio' ) ),
			'h' => array( (int) floor( ( $left % DAY_IN_SECONDS ) / HOUR_IN_SECONDS ), __( 'Hours', 'admin-studio' ) ),
			'm' => array( (int) floor( ( $left % HOUR_IN_SECONDS ) / MINUTE_IN_SECONDS ), __( 'Minutes', 'admin-studio' ) ),
			's' => array( $left % MINUTE_IN_SECONDS, __( 'Seconds', 'admin-studio' ) ),
		);

		$html = '<ul class="adminstudio-countdown" data-target="' . esc_attr( $target ) . '" aria-label="' . esc_attr__( 'Time until launch', 'admin-studio' ) . '">';

		foreach ( $units as $unit => $data ) {
			$html .= '<li><strong data-unit="' . esc_attr( $unit ) . '">' . esc_html( str_pad( (string) $data[0], 2, '0', STR_PAD_LEFT ) ) . '</strong><span>' . esc_html( $data[1] ) . '</span></li>';
		}

		$html .= '</ul>';

		$script = '(function(){var el=document.querySelector(".adminstudio-countdown");if(!el){return;}var t=parseInt(el.getAttribute("data-target"),10)*1000;function p(n){return(n<10?"0":"")+n;}function tick(){var s=Math.max(0,Math.floor((t-Date.now())/1000));var v={d:Math.floor(s/86400),h:Math.floor(s%86400/3600),m:Math.floor(s%3600/60),s:s%60};Object.keys(v).forEach(function(k){var n=el.querySelector("[data-unit="+k+"]");if(n){n.textContent=p(v[k]);}});if(s>0){setTimeout(tick,1000);}}tick();})();';

		return array( $html, $script );
	}

	/**
	 * Admin bar reminder that the mode is on.
	 *
	 * @param \WP_Admin_Bar $bar Admin bar.
	 * @return void
	 */
	public static function badge( $bar ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$bar->add_node(
			array(
				'id'     => 'adminstudio-maintenance',
				'parent' => 'top-secondary',
				'title'  => self::is_maintenance() ? esc_html__( 'Maintenance mode on', 'admin-studio' ) : esc_html__( 'Coming soon mode on', 'admin-studio' ),
				'href'   => admin_url( 'options-general.php?page=admin-studio&tab=maintenance' ),
			)
		);
	}

	/**
	 * Colours the admin bar reminder.
	 *
	 * @return void
	 */
	public static function badge_css() {
		if ( ! is_admin_bar_showing() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$bg = Brand_Kit::get()['error'];

		wp_add_inline_style(
			'admin-bar',
			sprintf(
				'#wpadminbar #wp-admin-bar-adminstudio-maintenance > .ab-item{background:%1$s;color:%2$s}',
				Brand_Kit::css_value( $bg ),
				Brand_Kit::css_value( Color::readable_on( $bg ) )
			)
		);
	}
}

<?php
/**
 * Admin templates: the look of the whole dashboard.
 *
 * Templates are CSS layers over WordPress's own admin, driven by colour
 * variables. The sidebar keeps WordPress's menu markup, so plugin menu items
 * keep working; headings, icons and the sidebar footer are added on top.
 *
 * Which template a user gets, most specific first: an administrator trying a
 * template on themselves, the user's own choice (unless locked), a template
 * an administrator set for that user, their role's template, the site default.
 *
 * @package AdminStudio
 */

namespace AdminStudio\Modules\Admin_Look;

use AdminStudio\Brand_Kit;
use AdminStudio\Color;

defined( 'ABSPATH' ) || exit;

/**
 * Admin templates module.
 */
class Admin_Look {

	const OPTION     = 'adminstudio_look';
	const META_ADMIN = 'adminstudio_look_admin';
	const META_SELF  = 'adminstudio_look_self';
	const META_TRY   = 'adminstudio_look_try';
	const META_MODE  = 'adminstudio_look_mode';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		if ( ! is_admin() ) {
			return;
		}

		add_filter( 'admin_body_class', array( __CLASS__, 'body_class' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ), 20 );
		add_action( 'admin_menu', array( __CLASS__, 'relabel_menu' ), PHP_INT_MAX - 1 );
		add_filter( 'custom_menu_order', array( __CLASS__, 'custom_order' ) );
		add_filter( 'menu_order', array( __CLASS__, 'menu_order' ), 99 );
		add_action( 'wp_ajax_adminstudio_look_mode', array( __CLASS__, 'ajax_mode' ) );
		add_action( 'admin_notices', array( __CLASS__, 'try_notice' ) );
		add_action( 'show_user_profile', array( __CLASS__, 'profile' ), 25 );
		add_action( 'edit_user_profile', array( __CLASS__, 'profile' ), 25 );
		add_action( 'personal_options_update', array( __CLASS__, 'save_profile' ) );
		add_action( 'edit_user_profile_update', array( __CLASS__, 'save_profile' ) );
		add_filter( 'get_user_option_admin_color', array( __CLASS__, 'admin_color' ), 10, 3 );
		add_action( 'admin_head-profile.php', array( __CLASS__, 'hide_scheme_picker' ) );
	}

	/**
	 * Templates replace colour schemes, so the standard scheme is loaded
	 * underneath them. The saved choice is kept for when the template is off.
	 *
	 * @param mixed    $value   Saved scheme.
	 * @param string   $option  Option name.
	 * @param \WP_User $user    User.
	 * @return mixed
	 */
	public static function admin_color( $value, $option, $user ) {
		if ( get_current_user_id() !== $user->ID || 'default' === self::current()['template'] ) {
			return $value;
		}

		return 'fresh';
	}

	/**
	 * Removes the colour scheme picker from the user's own profile while a
	 * template is in use.
	 *
	 * @return void
	 */
	public static function hide_scheme_picker() {
		if ( 'default' !== self::current()['template'] ) {
			remove_action( 'admin_color_scheme_picker', 'admin_color_scheme_picker' );
		}
	}

	// ------------------------------------------------------------------
	// Catalogue and configuration.
	// ------------------------------------------------------------------

	/**
	 * Templates: key => [ label, description ].
	 *
	 * @return array
	 */
	public static function templates() {
		return array(
			'default'  => array( __( 'WordPress default', 'admin-studio' ), __( 'The standard WordPress dashboard.', 'admin-studio' ) ),
			'studio'   => array( __( 'Studio', 'admin-studio' ), __( 'Soft tinted sidebar with section headings and outline icons, the active item as a filled pill, rounded cards with soft shadows.', 'admin-studio' ) ),
			'midnight' => array( __( 'Midnight', 'admin-studio' ), __( 'Dark sidebar and top bar, light content area, bright accent.', 'admin-studio' ) ),
			'minimal'  => array( __( 'Minimal', 'admin-studio' ), __( 'White sidebar, no shadows, the accent only on text and icons.', 'admin-studio' ) ),
			'compact'  => array( __( 'Compact', 'admin-studio' ), __( 'An icon rail that expands when you point at it, and denser tables.', 'admin-studio' ) ),
		);
	}

	/**
	 * Palettes: key => [ label, accent ]. Brand uses the brand kit; custom uses
	 * the custom colours.
	 *
	 * @return array
	 */
	public static function palettes() {
		return array(
			'brand'    => array( __( 'Brand kit', 'admin-studio' ), '' ),
			'cobalt'   => array( __( 'Cobalt', 'admin-studio' ), '#2a5bea' ),
			'forest'   => array( __( 'Forest', 'admin-studio' ), '#1f7a4d' ),
			'plum'     => array( __( 'Plum', 'admin-studio' ), '#7c3aed' ),
			'graphite' => array( __( 'Graphite', 'admin-studio' ), '#3f4654' ),
			'sunset'   => array( __( 'Sunset', 'admin-studio' ), '#c2410c' ),
			'custom'   => array( __( 'Custom', 'admin-studio' ), '' ),
		);
	}

	/**
	 * Default sidebar sections: id => label.
	 *
	 * @return array
	 */
	public static function default_sections() {
		return array(
			'overview'   => __( 'Overview', 'admin-studio' ),
			'content'    => __( 'Content', 'admin-studio' ),
			'store'      => __( 'Store', 'admin-studio' ),
			'design'     => __( 'Design', 'admin-studio' ),
			'extensions' => __( 'Extensions', 'admin-studio' ),
			'system'     => __( 'System', 'admin-studio' ),
		);
	}

	/**
	 * Saved configuration with defaults.
	 *
	 * @return array
	 */
	public static function config() {
		$saved = get_option( self::OPTION, array() );
		$saved = is_array( $saved ) ? $saved : array();

		$defaults = array(
			'default'     => array(
				'template' => 'default',
				'palette'  => 'brand',
			),
			'custom'      => array(
				'accent'     => '',
				'sidebar'    => '',
				'background' => '',
				'text'       => '',
			),
			'roles'       => array(),
			'self_choice' => true,
			'sections'    => array(),
			'items'       => array(),
		);

		$config = array_merge( $defaults, $saved );

		$config['default'] = array_merge( $defaults['default'], (array) $config['default'] );
		$config['custom']  = array_merge( $defaults['custom'], (array) $config['custom'] );

		return $config;
	}

	/**
	 * Cleans and saves part of the configuration.
	 *
	 * @param array $changes Keys to replace.
	 * @return void
	 */
	public static function save( array $changes ) {
		$config = array_merge( self::config(), $changes );

		$config['default'] = self::clean_choice( $config['default'] );

		foreach ( array( 'accent', 'sidebar', 'background', 'text' ) as $key ) {
			$config['custom'][ $key ] = (string) sanitize_hex_color( isset( $config['custom'][ $key ] ) ? $config['custom'][ $key ] : '' );
		}

		$roles = array();
		foreach ( (array) $config['roles'] as $role => $choice ) {
			if ( wp_roles()->is_role( $role ) ) {
				$roles[ $role ] = self::clean_choice( (array) $choice, true );
			}
		}
		$config['roles'] = $roles;

		$config['self_choice'] = ! empty( $config['self_choice'] );

		$sections = array();
		foreach ( (array) $config['sections'] as $id => $label ) {
			$id    = sanitize_key( $id );
			$label = sanitize_text_field( $label );
			if ( '' !== $id && '' !== $label ) {
				$sections[ $id ] = $label;
			}
		}
		$config['sections'] = $sections;

		$items = array();
		foreach ( (array) $config['items'] as $slug => $item ) {
			$items[ sanitize_text_field( $slug ) ] = array(
				'section' => sanitize_key( isset( $item['section'] ) ? $item['section'] : '' ),
				'label'   => sanitize_text_field( isset( $item['label'] ) ? $item['label'] : '' ),
				'icon'    => array_key_exists( isset( $item['icon'] ) ? $item['icon'] : '', Icons::all() ) || 'own' === ( isset( $item['icon'] ) ? $item['icon'] : '' ) ? $item['icon'] : '',
				'order'   => isset( $item['order'] ) ? (int) $item['order'] : 0,
			);
		}
		$config['items'] = $items;

		update_option( self::OPTION, $config );
	}

	/**
	 * Cleans a template choice.
	 *
	 * @param array $choice    Choice with template, palette and optionally lock.
	 * @param bool  $allow_inherit Whether an empty template (inherit) is allowed.
	 * @return array
	 */
	public static function clean_choice( array $choice, $allow_inherit = false ) {
		$template = isset( $choice['template'] ) ? sanitize_key( $choice['template'] ) : '';
		$palette  = isset( $choice['palette'] ) ? sanitize_key( $choice['palette'] ) : '';

		if ( ! isset( self::templates()[ $template ] ) ) {
			$template = $allow_inherit ? '' : 'default';
		}

		if ( ! isset( self::palettes()[ $palette ] ) ) {
			$palette = 'brand';
		}

		return array(
			'template' => $template,
			'palette'  => $palette,
			'lock'     => ! empty( $choice['lock'] ),
		);
	}

	// ------------------------------------------------------------------
	// Resolution.
	// ------------------------------------------------------------------

	/**
	 * The template and palette a user gets, and where the choice came from.
	 *
	 * @param \WP_User $user User.
	 * @return array { template, palette, source, locked }
	 */
	public static function resolve( $user ) {
		$config = self::config();

		if ( user_can( $user, 'manage_options' ) ) {
			$try = get_user_meta( $user->ID, self::META_TRY, true );
			if ( is_array( $try ) && ! empty( $try['template'] ) ) {
				$try = self::clean_choice( $try );
				return array_merge(
					$try,
					array(
						'source' => 'try',
						'locked' => false,
					)
				);
			}
		}

		$admin = get_user_meta( $user->ID, self::META_ADMIN, true );
		$admin = is_array( $admin ) ? self::clean_choice( $admin, true ) : array(
			'template' => '',
			'palette'  => 'brand',
			'lock'     => false,
		);

		$role_choice = null;
		foreach ( (array) $user->roles as $role ) {
			if ( ! empty( $config['roles'][ $role ]['template'] ) ) {
				$role_choice = $config['roles'][ $role ];
				break;
			}
		}

		$locked = $admin['lock'] || ( $role_choice && ! empty( $role_choice['lock'] ) );

		if ( ! $locked && $config['self_choice'] ) {
			$self = get_user_meta( $user->ID, self::META_SELF, true );
			if ( is_array( $self ) && ! empty( $self['template'] ) ) {
				return array_merge(
					self::clean_choice( $self ),
					array(
						'source' => 'self',
						'locked' => false,
					)
				);
			}
		}

		if ( '' !== $admin['template'] ) {
			return array_merge(
				$admin,
				array(
					'source' => 'user',
					'locked' => $locked,
				)
			);
		}

		if ( $role_choice ) {
			return array_merge(
				$role_choice,
				array(
					'source' => 'role',
					'locked' => $locked,
				)
			);
		}

		return array_merge(
			$config['default'],
			array(
				'source' => 'default',
				'locked' => $locked,
			)
		);
	}

	/**
	 * Whether a user sees an admin template (anything but WordPress default).
	 *
	 * @param \WP_User $user User.
	 * @return bool
	 */
	public static function active_for( $user ) {
		return $user->exists() && 'default' !== self::resolve( $user )['template'];
	}

	/**
	 * Current user's resolved choice, cached for the request.
	 *
	 * @return array
	 */
	private static function current() {
		static $current = null;

		if ( null === $current ) {
			$current = self::resolve( wp_get_current_user() );
		}

		return $current;
	}

	/**
	 * The user's light/dark preference.
	 *
	 * @param int $user_id User ID.
	 * @return string light, dark or system.
	 */
	public static function mode( $user_id ) {
		$mode = get_user_meta( $user_id, self::META_MODE, true );

		return in_array( $mode, array( 'light', 'dark', 'system' ), true ) ? $mode : 'light';
	}

	// ------------------------------------------------------------------
	// Output.
	// ------------------------------------------------------------------

	/**
	 * Body classes for the template and mode.
	 *
	 * @param string $classes Classes.
	 * @return string
	 */
	public static function body_class( $classes ) {
		$current = self::current();

		if ( 'default' === $current['template'] ) {
			return $classes;
		}

		$mode = self::mode( get_current_user_id() );

		return $classes . ' adminstudio-look adminstudio-t-' . $current['template'] . ' adminstudio-mode-' . $mode . ( 'dark' === $mode ? ' adminstudio-dark' : '' );
	}

	/**
	 * Colour values for the current choice.
	 *
	 * @param array $choice Resolved choice.
	 * @return array [ light vars, dark vars ].
	 */
	public static function colors( array $choice ) {
		$config   = self::config();
		$palettes = self::palettes();
		$custom   = $config['custom'];
		$brand    = Brand_Kit::get();

		$accent = 'brand' === $choice['palette'] ? $brand['primary'] : $palettes[ $choice['palette'] ][1];
		if ( 'custom' === $choice['palette'] ) {
			$accent = '' !== $custom['accent'] ? $custom['accent'] : $brand['primary'];
		}
		$accent = '' !== Color::to_hex( $accent ) ? Color::to_hex( $accent ) : '#2a5bea';

		$bg   = 'custom' === $choice['palette'] && '' !== $custom['background'] ? $custom['background'] : Color::mix( '#f6f8fc', $accent, 0.02 );
		$text = 'custom' === $choice['palette'] && '' !== $custom['text'] ? $custom['text'] : '#1b2340';

		switch ( $choice['template'] ) {
			case 'midnight':
				$sidebar = Color::mix( '#0b1020', $accent, 0.12 );
				break;
			case 'compact':
				$sidebar = Color::mix( '#101522', $accent, 0.1 );
				break;
			case 'minimal':
				$sidebar = '#ffffff';
				break;
			default:
				$sidebar = Color::mix( '#f3f6fc', $accent, 0.07 );
		}

		if ( 'custom' === $choice['palette'] && '' !== $custom['sidebar'] ) {
			$sidebar = $custom['sidebar'];
		}

		$light = self::derive( $accent, $bg, '#ffffff', $text, $sidebar, $choice['template'] );

		$dark_sidebar = in_array( $choice['template'], array( 'midnight', 'compact' ), true ) ? Color::mix( '#070a12', $accent, 0.1 ) : Color::mix( '#121826', $accent, 0.08 );
		$dark_accent  = Color::contrast( $accent, '#161d2e' ) < 3.2 ? Color::mix( $accent, '#ffffff', 0.4 ) : $accent;
		$dark         = self::derive( $dark_accent, '#0f1421', '#161d2e', '#e6e9f2', $dark_sidebar, $choice['template'] );

		return array( $light, $dark );
	}

	/**
	 * Builds the full variable set from base colours.
	 *
	 * @param string $accent   Accent.
	 * @param string $bg       Page background.
	 * @param string $surface  Card background.
	 * @param string $text     Text.
	 * @param string $sidebar  Sidebar background.
	 * @param string $template Template key.
	 * @return array
	 */
	private static function derive( $accent, $bg, $surface, $text, $sidebar, $template ) {
		$side_text = Color::readable_on( $sidebar );
		$side_text = '#111111' === $side_text ? $text : '#eef1f8';

		// The current menu item must stand out from the sidebar even when the
		// accent is close to the sidebar colour (a black brand on a dark sidebar).
		$pill = Color::contrast( $accent, $sidebar ) < 1.8 ? Color::mix( $sidebar, $side_text, 0.85 ) : $accent;

		return array(
			'accent'        => $accent,
			'pill'          => $pill,
			'on-pill'       => Color::readable_on( $pill ),
			'on-accent'     => Color::readable_on( $accent ),
			'accent-soft'   => Color::mix( $surface, $accent, 0.12 ),
			'accent-text'   => Color::contrast( $accent, $surface ) >= 4.5 ? $accent : Color::mix( $accent, Color::is_light( $surface ) ? '#000000' : '#ffffff', 0.35 ),
			'bg'            => $bg,
			'surface'       => $surface,
			'surface-2'     => Color::mix( $surface, $text, 0.04 ),
			'text'          => $text,
			'muted'         => Color::mix( $text, $surface, 0.4 ),
			'border'        => Color::mix( $surface, $text, 0.12 ),
			'sidebar'       => $sidebar,
			'sidebar-text'  => $side_text,
			'sidebar-muted' => Color::mix( $side_text, $sidebar, 0.4 ),
			'sidebar-hover' => Color::mix( $sidebar, $side_text, 0.08 ),
			'bar'           => 'midnight' === $template ? $sidebar : ( 'compact' === $template ? $sidebar : $bg ),
			'bar-text'      => Color::readable_on( 'midnight' === $template || 'compact' === $template ? $sidebar : $bg ) === '#ffffff' ? '#eef1f8' : $text,
		);
	}

	/**
	 * Turns a variable set into CSS declarations.
	 *
	 * @param array $vars Variables.
	 * @return string
	 */
	private static function css_vars( array $vars ) {
		$css = '';

		foreach ( $vars as $name => $value ) {
			$css .= '--as-' . $name . ':' . Brand_Kit::css_value( $value ) . ';';
		}

		return $css;
	}

	/**
	 * Loads the template stylesheet, variables and the sidebar script.
	 *
	 * @return void
	 */
	public static function enqueue() {
		$current = self::current();

		if ( 'default' === $current['template'] ) {
			return;
		}

		list( $light, $dark ) = self::colors( $current );

		wp_enqueue_style( 'adminstudio-look', ADMINSTUDIO_URL . 'assets/css/look.css', array( 'common' ), \AdminStudio\Plugin::asset_version( 'assets/css/look.css' ) );
		wp_add_inline_style(
			'adminstudio-look',
			'body.adminstudio-look{' . self::css_vars( $light ) . '}'
			. 'body.adminstudio-look.adminstudio-dark{' . self::css_vars( $dark ) . 'color-scheme:dark}'
			. '@media (prefers-color-scheme:dark){body.adminstudio-look.adminstudio-mode-system{' . self::css_vars( $dark ) . 'color-scheme:dark}}'
		);

		$user = wp_get_current_user();

		wp_enqueue_script( 'adminstudio-look', ADMINSTUDIO_URL . 'assets/js/look.js', array(), \AdminStudio\Plugin::asset_version( 'assets/js/look.js' ), true );
		wp_localize_script(
			'adminstudio-look',
			'adminstudioLook',
			array(
				'sections' => self::sidebar_sections(),
				'icons'    => self::sidebar_icons(),
				'mode'     => self::mode( $user->ID ),
				'ajax'     => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'adminstudio_look_mode' ),
				'foot'     => array(
					'name'     => $user->display_name,
					'email'    => $user->user_email,
					'avatar'   => get_avatar_url( $user->ID, array( 'size' => 64 ) ),
					'profile'  => admin_url( 'profile.php' ),
					'site'     => home_url( '/' ),
					'logout'   => wp_logout_url(),
					'iconSite' => Icons::svg( 'external', 16 ),
					'iconOut'  => Icons::svg( 'logout', 16 ),
					'iconMode' => array(
						'light'  => Icons::svg( 'sun', 16 ),
						'dark'   => Icons::svg( 'moon', 16 ),
						'system' => Icons::svg( 'monitor', 16 ),
					),
				),
				'greeting' => self::greeting( $user ),
				'i18n'     => array(
					'viewSite'  => __( 'View site', 'admin-studio' ),
					'signOut'   => __( 'Sign out', 'admin-studio' ),
					'modes'     => array(
						'light'  => __( 'Light', 'admin-studio' ),
						'dark'   => __( 'Dark', 'admin-studio' ),
						'system' => __( 'System', 'admin-studio' ),
					),
					/* translators: %s: current colour mode. */
					'modeLabel' => __( 'Colour mode: %s. Click to change.', 'admin-studio' ),
				),
			)
		);
	}

	/**
	 * Greeting for the dashboard home.
	 *
	 * @param \WP_User $user User.
	 * @return array
	 */
	private static function greeting( $user ) {
		$hour = (int) wp_date( 'G' );

		if ( $hour < 12 ) {
			$hello = __( 'Good morning', 'admin-studio' );
		} elseif ( $hour < 18 ) {
			$hello = __( 'Good afternoon', 'admin-studio' );
		} else {
			$hello = __( 'Good evening', 'admin-studio' );
		}

		$first = $user->first_name ? $user->first_name : $user->display_name;

		return array(
			'title' => $hello . ', ' . $first,
			'date'  => wp_date( get_option( 'date_format' ) ),
		);
	}

	// ------------------------------------------------------------------
	// Sidebar menu.
	// ------------------------------------------------------------------

	/**
	 * Section id for a menu slug when no section was chosen.
	 *
	 * @param string $slug Menu slug.
	 * @return string
	 */
	public static function default_section_for( $slug ) {
		if ( 'index.php' === $slug ) {
			return 'overview';
		}

		if ( in_array( $slug, array( 'edit.php', 'upload.php', 'edit-comments.php' ), true ) || ( 0 === strpos( $slug, 'edit.php?post_type=' ) && 'edit.php?post_type=product' !== $slug ) ) {
			return 'content';
		}

		if ( in_array( $slug, array( 'woocommerce', 'edit.php?post_type=product', 'wc-admin&path=/analytics/overview', 'woocommerce-marketing' ), true ) ) {
			return 'store';
		}

		if ( 'themes.php' === $slug ) {
			return 'design';
		}

		if ( in_array( $slug, array( 'plugins.php', 'users.php', 'profile.php', 'tools.php', 'options-general.php' ), true ) ) {
			return 'system';
		}

		return 'extensions';
	}

	/**
	 * Sections in display order: id => label.
	 *
	 * @return array
	 */
	public static function sections() {
		$saved = self::config()['sections'];

		return $saved ? $saved : self::default_sections();
	}

	/**
	 * Section of a slug, from the saved layout or the defaults.
	 *
	 * @param string $slug Menu slug.
	 * @return string
	 */
	public static function section_of( $slug ) {
		$items    = self::config()['items'];
		$sections = self::sections();
		$section  = isset( $items[ $slug ]['section'] ) ? $items[ $slug ]['section'] : '';

		if ( '' === $section || ! isset( $sections[ $section ] ) ) {
			$section = self::default_section_for( $slug );
		}

		if ( ! isset( $sections[ $section ] ) ) {
			$section = isset( $sections['extensions'] ) ? 'extensions' : (string) array_key_last( $sections );
		}

		return $section;
	}

	/**
	 * Top-level menu slugs from the global menu, without separators.
	 *
	 * @return array Slug => [ label, element id ].
	 */
	public static function top_items() {
		global $menu;

		$items = array();

		foreach ( (array) $menu as $entry ) {
			if ( empty( $entry[2] ) || ( isset( $entry[4] ) && false !== strpos( (string) $entry[4], 'wp-menu-separator' ) ) ) {
				continue;
			}

			$items[ $entry[2] ] = array(
				\AdminStudio\Modules\Roles\Access::clean_label( $entry[0] ),
				isset( $entry[5] ) ? $entry[5] : '',
				isset( $entry[6] ) ? $entry[6] : '',
			);
		}

		return $items;
	}

	/**
	 * Tells WordPress the menu has a custom order when a template is active.
	 *
	 * @param bool $custom Whether a custom order is used.
	 * @return bool
	 */
	public static function custom_order( $custom ) {
		return 'default' !== self::current()['template'] ? true : $custom;
	}

	/**
	 * Orders the menu by section, then by the saved order.
	 *
	 * @param string[] $order Slugs in the current order.
	 * @return string[]
	 */
	public static function menu_order( $order ) {
		if ( 'default' === self::current()['template'] || ! is_array( $order ) ) {
			return $order;
		}

		return self::sort_slugs( $order );
	}

	/**
	 * Sorts menu slugs by section, then by the saved order. Separators are dropped.
	 *
	 * @param string[] $order Slugs in WordPress's order.
	 * @return string[]
	 */
	public static function sort_slugs( array $order ) {
		$items    = self::config()['items'];
		$sections = array_keys( self::sections() );
		$position = array_flip( $order );

		$slugs = array_filter(
			$order,
			static function ( $slug ) {
				return 0 !== strpos( $slug, 'separator' );
			}
		);

		usort(
			$slugs,
			static function ( $a, $b ) use ( $items, $sections, $position ) {
				$sa = array_search( self::section_of( $a ), $sections, true );
				$sb = array_search( self::section_of( $b ), $sections, true );

				if ( $sa !== $sb ) {
					return $sa - $sb;
				}

				$oa = isset( $items[ $a ]['order'] ) ? $items[ $a ]['order'] : 1000 + $position[ $a ];
				$ob = isset( $items[ $b ]['order'] ) ? $items[ $b ]['order'] : 1000 + $position[ $b ];

				return $oa - $ob;
			}
		);

		return array_values( $slugs );
	}

	/**
	 * Applies custom labels, keeping any count badges.
	 *
	 * @return void
	 */
	public static function relabel_menu() {
		global $menu;

		if ( 'default' === self::current()['template'] ) {
			return;
		}

		$items = self::config()['items'];

		foreach ( (array) $menu as $key => $entry ) {
			if ( empty( $entry[2] ) || empty( $items[ $entry[2] ]['label'] ) ) {
				continue;
			}

			$badge = preg_match( '#<span.*</span>#s', (string) $entry[0], $m ) ? ' ' . $m[0] : '';

			$menu[ $key ][0] = esc_html( $items[ $entry[2] ]['label'] ) . $badge; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Relabelling menu items is the purpose.
		}
	}

	/**
	 * Section headings and the menu element each one goes before.
	 *
	 * @return array List of [ label, element ids in the section ].
	 */
	public static function sidebar_sections() {
		$out = array();

		foreach ( self::sections() as $id => $label ) {
			$out[ $id ] = array(
				'label' => $label,
				'items' => array(),
			);
		}

		foreach ( self::top_items() as $slug => $item ) {
			$section = self::section_of( $slug );
			if ( '' !== $item[1] && isset( $out[ $section ] ) ) {
				$out[ $section ]['items'][] = $item[1];
			}
		}

		return array_values(
			array_filter(
				$out,
				static function ( $section ) {
					return (bool) $section['items'];
				}
			)
		);
	}

	/**
	 * Icon markup for each menu element id.
	 *
	 * @return array Element id => SVG.
	 */
	public static function sidebar_icons() {
		$items = self::config()['items'];
		$out   = array();

		foreach ( self::top_items() as $slug => $item ) {
			$icon = isset( $items[ $slug ]['icon'] ) && '' !== $items[ $slug ]['icon'] ? $items[ $slug ]['icon'] : Icons::default_for( $slug );

			if ( '' !== $icon && 'own' !== $icon && '' !== $item[1] ) {
				$out[ $item[1] ] = Icons::svg( $icon, 18 );
			}
		}

		return $out;
	}

	// ------------------------------------------------------------------
	// Mode toggle, trial banner and profile.
	// ------------------------------------------------------------------

	/**
	 * Saves the light/dark preference.
	 *
	 * @return void
	 */
	public static function ajax_mode() {
		check_ajax_referer( 'adminstudio_look_mode', 'nonce' );

		if ( ! current_user_can( 'read' ) ) {
			wp_send_json_error( null, 403 );
		}

		$mode = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : '';

		if ( ! in_array( $mode, array( 'light', 'dark', 'system' ), true ) ) {
			wp_send_json_error();
		}

		update_user_meta( get_current_user_id(), self::META_MODE, $mode );
		wp_send_json_success( array( 'mode' => $mode ) );
	}

	/**
	 * Banner shown while an administrator is trying a template.
	 *
	 * @return void
	 */
	public static function try_notice() {
		$current = self::current();

		if ( 'try' !== $current['source'] ) {
			return;
		}

		$templates = self::templates();

		echo '<div class="notice notice-info adminstudio-notice adminstudio-try"><p>';
		echo esc_html(
			sprintf(
				/* translators: %s: template name. */
				__( 'You are trying the %s template. Only you can see it.', 'admin-studio' ),
				$templates[ $current['template'] ][0]
			)
		);
		echo '</p><div class="adminstudio-try__actions">';
		self::try_form( 'look_apply', __( 'Apply to everyone', 'admin-studio' ), 'button-primary', $current );
		echo '<a class="button" href="' . esc_url( admin_url( 'options-general.php?page=admin-studio&tab=look&section=roles' ) ) . '">' . esc_html__( 'Apply to roles', 'admin-studio' ) . '</a>';
		self::try_form( 'look_stop', __( 'Stop trying', 'admin-studio' ), '', $current );
		echo '</div></div>';
	}

	/**
	 * Prints one button form for the trial banner.
	 *
	 * @param string $action  Action suffix.
	 * @param string $label   Button text.
	 * @param string $button_class   Extra button class.
	 * @param array  $current Current choice.
	 * @return void
	 */
	private static function try_form( $action, $label, $button_class, array $current ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="adminstudio_' . esc_attr( $action ) . '">';
		echo '<input type="hidden" name="template" value="' . esc_attr( $current['template'] ) . '">';
		echo '<input type="hidden" name="palette" value="' . esc_attr( $current['palette'] ) . '">';
		wp_nonce_field( 'adminstudio_' . $action );
		echo '<button type="submit" class="button ' . esc_attr( $button_class ) . '">' . esc_html( $label ) . '</button></form>';
	}

	/**
	 * Template section on the profile screen.
	 *
	 * @param \WP_User $user User being edited.
	 * @return void
	 */
	public static function profile( $user ) {
		$own       = get_current_user_id() === $user->ID;
		$is_admin  = current_user_can( 'manage_options' );
		$config    = self::config();
		$resolved  = self::resolve( $user );
		$templates = self::templates();
		$palettes  = self::palettes();
		$self      = get_user_meta( $user->ID, self::META_SELF, true );
		$self      = is_array( $self ) ? self::clean_choice( $self, true ) : array(
			'template' => '',
			'palette'  => 'brand',
		);
		$admin     = get_user_meta( $user->ID, self::META_ADMIN, true );
		$admin     = is_array( $admin ) ? self::clean_choice( $admin, true ) : array(
			'template' => '',
			'palette'  => 'brand',
			'lock'     => false,
		);
		$can_self  = $own && $config['self_choice'] && ! $resolved['locked'];

		if ( ! $own && ! $is_admin ) {
			return;
		}

		$select = static function ( $name, $options, $value, $inherit_label ) {
			$html = '<select name="' . esc_attr( $name ) . '">';
			if ( null !== $inherit_label ) {
				$html .= '<option value="">' . esc_html( $inherit_label ) . '</option>';
			}
			foreach ( $options as $key => $option ) {
				$html .= '<option value="' . esc_attr( $key ) . '"' . selected( $value, $key, false ) . '>' . esc_html( $option[0] ) . '</option>';
			}
			return $html . '</select>';
		};

		$allowed = array(
			'select' => array( 'name' => true ),
			'option' => array(
				'value'    => true,
				'selected' => true,
			),
		);

		echo '<h2 id="adminstudio-look">' . esc_html__( 'Dashboard look', 'admin-studio' ) . '</h2>';
		wp_nonce_field( 'adminstudio_look_profile_' . $user->ID, '_adminstudio_look', false );
		echo '<table class="form-table" role="presentation">';

		if ( $own ) {
			echo '<tr><th scope="row">' . esc_html__( 'Colour mode', 'admin-studio' ) . '</th><td><select name="adminstudio_look_mode">';
			foreach ( array(
				'light'  => __( 'Light', 'admin-studio' ),
				'dark'   => __( 'Dark', 'admin-studio' ),
				'system' => __( 'Follow my device', 'admin-studio' ),
			) as $key => $label ) {
				echo '<option value="' . esc_attr( $key ) . '"' . selected( self::mode( $user->ID ), $key, false ) . '>' . esc_html( $label ) . '</option>';
			}
			echo '</select><p class="description">' . esc_html__( 'Applies when an admin template is in use.', 'admin-studio' ) . '</p></td></tr>';
		}

		if ( $can_self ) {
			echo '<tr><th scope="row">' . esc_html__( 'My template', 'admin-studio' ) . '</th><td>';
			echo wp_kses( $select( 'adminstudio_look_self[template]', $templates, $self['template'], __( 'Use the site setting', 'admin-studio' ) ), $allowed );
			echo ' ';
			echo wp_kses( $select( 'adminstudio_look_self[palette]', $palettes, $self['palette'], null ), $allowed );
			echo '</td></tr>';
		} elseif ( $own && $resolved['locked'] ) {
			echo '<tr><th scope="row">' . esc_html__( 'My template', 'admin-studio' ) . '</th><td>' . esc_html__( 'Set by your administrator.', 'admin-studio' ) . '</td></tr>';
		}

		if ( $is_admin && ! $own ) {
			echo '<tr><th scope="row">' . esc_html__( 'Template for this user', 'admin-studio' ) . '</th><td>';
			echo wp_kses( $select( 'adminstudio_look_admin[template]', $templates, $admin['template'], __( 'Use their role\'s setting', 'admin-studio' ) ), $allowed );
			echo ' ';
			echo wp_kses( $select( 'adminstudio_look_admin[palette]', $palettes, $admin['palette'], null ), $allowed );
			echo '<p><label><input type="checkbox" name="adminstudio_look_admin[lock]" value="1"' . checked( $admin['lock'], true, false ) . '> ' . esc_html__( 'Do not let this user change it', 'admin-studio' ) . '</label></p></td></tr>';
		}

		echo '</table>';
	}

	/**
	 * Saves the profile section.
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	public static function save_profile( $user_id ) {
		if ( ! isset( $_POST['_adminstudio_look'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_adminstudio_look'] ) ), 'adminstudio_look_profile_' . $user_id ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}

		$own = get_current_user_id() === $user_id;

		if ( $own && isset( $_POST['adminstudio_look_mode'] ) ) {
			$mode = sanitize_key( wp_unslash( $_POST['adminstudio_look_mode'] ) );
			if ( in_array( $mode, array( 'light', 'dark', 'system' ), true ) ) {
				update_user_meta( $user_id, self::META_MODE, $mode );
			}
		}

		if ( $own && isset( $_POST['adminstudio_look_self'] ) && is_array( $_POST['adminstudio_look_self'] ) ) {
			$self = self::clean_choice( array_map( 'sanitize_key', wp_unslash( $_POST['adminstudio_look_self'] ) ), true );
			if ( '' === $self['template'] ) {
				delete_user_meta( $user_id, self::META_SELF );
			} else {
				update_user_meta( $user_id, self::META_SELF, $self );
			}
		}

		if ( ! $own && current_user_can( 'manage_options' ) && current_user_can( 'edit_user', $user_id ) && isset( $_POST['adminstudio_look_admin'] ) && is_array( $_POST['adminstudio_look_admin'] ) ) {
			$admin = self::clean_choice( array_map( 'sanitize_key', wp_unslash( $_POST['adminstudio_look_admin'] ) ), true );
			if ( '' === $admin['template'] && ! $admin['lock'] ) {
				delete_user_meta( $user_id, self::META_ADMIN );
			} else {
				update_user_meta( $user_id, self::META_ADMIN, $admin );
			}
		}
	}
}

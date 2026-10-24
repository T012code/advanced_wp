<?php
/**
 * Dashboard widgets: show or hide any widget per role, add custom widgets
 * and give everyone a default layout.
 *
 * Settings live in their own option. Widgets added by other plugins are
 * recorded when an administrator opens the dashboard, so they can be listed
 * on the settings page.
 *
 * @package AdminStudio
 */

namespace AdminStudio\Modules\Widgets;

use AdminStudio\Plugin;
use AdminStudio\Settings;
use AdminStudio\Defaults;
use AdminStudio\Modules\Emails\Renderer;

defined( 'ABSPATH' ) || exit;

/**
 * Dashboard widgets module.
 */
class Widgets {

	const OPTION = 'adminstudio_widgets';
	const SEEN   = 'adminstudio_widgets_seen';
	const PREFIX = 'adminstudio_';

	/**
	 * Dashboard columns: context => label.
	 *
	 * @return array
	 */
	public static function columns() {
		return array(
			'normal'  => __( 'Column 1', 'admin-studio' ),
			'side'    => __( 'Column 2', 'admin-studio' ),
			'column3' => __( 'Column 3', 'admin-studio' ),
			'column4' => __( 'Column 4', 'admin-studio' ),
		);
	}

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		if ( ! is_admin() ) {
			return;
		}

		add_action( 'wp_dashboard_setup', array( __CLASS__, 'record' ), 998 );
		add_action( 'wp_dashboard_setup', array( __CLASS__, 'dashboard' ), 999 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'wp_ajax_meta-box-order', array( __CLASS__, 'block_reorder' ), 0 );

		foreach ( array( 'meta-box-order', 'closedpostboxes', 'metaboxhidden' ) as $option ) {
			add_filter( 'get_user_option_' . $option . '_dashboard', array( __CLASS__, 'layout_option' ), 10, 2 );
		}
	}

	/**
	 * Saved settings with defaults. Carries over the old Admin branding
	 * settings the first time it runs.
	 *
	 * @return array
	 */
	public static function config() {
		$saved = get_option( self::OPTION, null );

		if ( null === $saved ) {
			$saved = self::migrate();
			update_option( self::OPTION, $saved, false );
		}

		$saved = is_array( $saved ) ? $saved : array();

		$config = array_merge(
			array(
				'hide'   => array(),
				'custom' => array(),
				'layout' => array(),
			),
			$saved
		);

		$config['layout'] = array_merge(
			array(
				'order'  => array(),
				'closed' => array(),
				'hidden' => array(),
				'lock'   => false,
				'saved'  => 0,
			),
			(array) $config['layout']
		);

		return $config;
	}

	/**
	 * Saves part of the settings.
	 *
	 * @param array $changes Top-level keys to replace.
	 * @return void
	 */
	public static function save( array $changes ) {
		$config = array_merge( self::config(), $changes );
		$roles  = array_keys( wp_roles()->get_names() );

		$hide = array();
		foreach ( (array) $config['hide'] as $id => $hidden ) {
			$id     = self::clean_id( $id );
			$hidden = array_values( array_intersect( array_map( 'sanitize_key', (array) $hidden ), $roles ) );
			if ( '' !== $id && $hidden ) {
				$hide[ $id ] = $hidden;
			}
		}

		$custom = array();
		foreach ( (array) $config['custom'] as $id => $widget ) {
			$id = sanitize_key( $id );
			if ( '' !== $id ) {
				$custom[ $id ] = self::clean_widget( (array) $widget );
			}
		}

		$layout = (array) $config['layout'];
		$order  = array();
		foreach ( self::columns() as $column => $label ) {
			if ( isset( $layout['order'][ $column ] ) ) {
				$order[ $column ] = implode( ',', array_filter( array_map( array( __CLASS__, 'clean_id' ), explode( ',', (string) $layout['order'][ $column ] ) ) ) );
			}
		}

		update_option(
			self::OPTION,
			array(
				'hide'   => $hide,
				'custom' => $custom,
				'layout' => array(
					'order'  => $order,
					'closed' => array_values( array_filter( array_map( array( __CLASS__, 'clean_id' ), (array) $layout['closed'] ) ) ),
					'hidden' => array_values( array_filter( array_map( array( __CLASS__, 'clean_id' ), (array) $layout['hidden'] ) ) ),
					'lock'   => ! empty( $layout['lock'] ),
					'saved'  => isset( $layout['saved'] ) ? absint( $layout['saved'] ) : 0,
				),
			),
			false
		);
	}

	/**
	 * A widget id as WordPress uses it: letters, numbers, dashes and underscores.
	 *
	 * @param string $id Widget id.
	 * @return string
	 */
	public static function clean_id( $id ) {
		return (string) preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $id );
	}

	/**
	 * Settings from the Admin branding tab of earlier versions.
	 *
	 * @return array
	 */
	private static function migrate() {
		$raw      = Settings::raw();
		$branding = isset( $raw['admin_branding'] ) && is_array( $raw['admin_branding'] ) ? $raw['admin_branding'] : array();
		$config   = array(
			'hide'   => array(),
			'custom' => array(),
			'layout' => array(),
		);
		$roles    = array_keys( wp_roles()->get_names() );

		if ( ! empty( $branding['hide_widgets'] ) ) {
			foreach ( (array) $branding['hide_widgets'] as $id ) {
				$config['hide'][ self::clean_id( $id ) ] = $roles;
			}
		}

		if ( ! empty( $branding['welcome'] ) ) {
			$config['custom']['welcome'] = self::clean_widget(
				array(
					'type'       => 'text',
					'title'      => isset( $branding['welcome_title'] ) ? $branding['welcome_title'] : Defaults::text( 'admin_branding.welcome_title' ),
					'content'    => isset( $branding['welcome_content'] ) ? $branding['welcome_content'] : Defaults::text( 'admin_branding.welcome_content' ),
					'button'     => isset( $branding['welcome_button'] ) ? $branding['welcome_button'] : '',
					'button_url' => isset( $branding['welcome_button_url'] ) ? $branding['welcome_button_url'] : '',
				)
			);
		}

		return $config;
	}

	/**
	 * Custom widget types: key => [ label, description, icon ].
	 *
	 * @return array
	 */
	public static function types() {
		return array(
			'text'     => array( __( 'Text', 'admin-studio' ), __( 'Rich text or HTML, with an optional button.', 'admin-studio' ), 'file-text' ),
			'links'    => array( __( 'Quick links', 'admin-studio' ), __( 'Buttons to admin screens or any address, with icons.', 'admin-studio' ), 'link' ),
			'support'  => array( __( 'Support card', 'admin-studio' ), __( 'Contact details, a message and a button.', 'admin-studio' ), 'heart' ),
			'video'    => array( __( 'Video', 'admin-studio' ), __( 'A video from YouTube, Vimeo or another provider WordPress can embed.', 'admin-studio' ), 'monitor' ),
			'posts'    => array( __( 'What\'s new', 'admin-studio' ), __( 'The latest posts from a category on this site.', 'admin-studio' ), 'bell' ),
			'signups'  => array( __( 'Recent sign-ups', 'admin-studio' ), __( 'The newest user accounts.', 'admin-studio' ), 'users' ),
			'counts'   => array( __( 'Content counts', 'admin-studio' ), __( 'How many posts, pages, products, comments and users there are.', 'admin-studio' ), 'chart' ),
			'comments' => array( __( 'Recent comments', 'admin-studio' ), __( 'The latest comments, with links to moderate them.', 'admin-studio' ), 'message' ),
			'health'   => array( __( 'Site health summary', 'admin-studio' ), __( 'The result of the last Site Health check.', 'admin-studio' ), 'shield' ),
		);
	}

	/**
	 * Cleans one custom widget.
	 *
	 * @param array $widget Widget settings.
	 * @return array
	 */
	public static function clean_widget( array $widget ) {
		$types = self::types();
		$type  = isset( $widget['type'] ) && isset( $types[ $widget['type'] ] ) ? $widget['type'] : 'text';
		$roles = array_keys( wp_roles()->get_names() );

		$links = array();
		foreach ( isset( $widget['links'] ) ? (array) $widget['links'] : array() as $link ) {
			$link  = (array) $link;
			$label = isset( $link['label'] ) ? sanitize_text_field( $link['label'] ) : '';
			$url   = isset( $link['url'] ) ? esc_url_raw( trim( (string) $link['url'] ) ) : '';
			if ( '' !== $label && '' !== $url ) {
				$links[] = array(
					'label' => $label,
					'url'   => $url,
					'icon'  => isset( $link['icon'] ) ? sanitize_key( $link['icon'] ) : '',
				);
			}
		}

		$column = isset( $widget['column'] ) ? sanitize_key( $widget['column'] ) : 'normal';

		return array(
			'type'       => $type,
			'title'      => isset( $widget['title'] ) && '' !== trim( (string) $widget['title'] ) ? sanitize_text_field( $widget['title'] ) : $types[ $type ][0],
			'roles'      => array_values( array_intersect( array_map( 'sanitize_key', isset( $widget['roles'] ) ? (array) $widget['roles'] : array() ), $roles ) ),
			'column'     => array_key_exists( $column, self::columns() ) ? $column : 'normal',
			'content'    => isset( $widget['content'] ) ? wp_kses_post( $widget['content'] ) : '',
			'button'     => isset( $widget['button'] ) ? sanitize_text_field( $widget['button'] ) : '',
			'button_url' => isset( $widget['button_url'] ) ? esc_url_raw( trim( (string) $widget['button_url'] ) ) : '',
			'email'      => isset( $widget['email'] ) ? sanitize_email( $widget['email'] ) : '',
			'phone'      => isset( $widget['phone'] ) ? sanitize_text_field( $widget['phone'] ) : '',
			'video'      => isset( $widget['video'] ) ? esc_url_raw( trim( (string) $widget['video'] ) ) : '',
			'category'   => isset( $widget['category'] ) ? absint( $widget['category'] ) : 0,
			'number'     => isset( $widget['number'] ) ? max( 1, min( 20, absint( $widget['number'] ) ) ) : 5,
			'links'      => array_slice( $links, 0, 12 ),
		);
	}

	/**
	 * Core widgets, listed even before anyone has opened the dashboard.
	 *
	 * @return array Id => title.
	 */
	public static function core_widgets() {
		return array(
			'welcome_panel'         => __( 'Welcome panel', 'admin-studio' ),
			'dashboard_right_now'   => __( 'At a Glance', 'admin-studio' ),
			'dashboard_activity'    => __( 'Activity', 'admin-studio' ),
			'dashboard_quick_press' => __( 'Quick Draft', 'admin-studio' ),
			'dashboard_primary'     => __( 'WordPress Events and News', 'admin-studio' ),
			'dashboard_site_health' => __( 'Site Health Status', 'admin-studio' ),
		);
	}

	/**
	 * Every known widget that is not one of ours: core widgets plus those
	 * recorded from other plugins.
	 *
	 * @return array Id => title.
	 */
	public static function known() {
		$seen = get_option( self::SEEN, array() );

		return array_merge( self::core_widgets(), is_array( $seen ) ? $seen : array() );
	}

	/**
	 * Widget titles by id, including custom widgets, for layout lists.
	 *
	 * @return array
	 */
	public static function titles() {
		$titles = self::known();

		foreach ( self::config()['custom'] as $id => $widget ) {
			$titles[ self::PREFIX . $id ] = $widget['title'];
		}

		return $titles;
	}

	/**
	 * Records the widgets other plugins add, when an administrator views the dashboard.
	 *
	 * @return void
	 */
	public static function record() {
		global $wp_meta_boxes;

		if ( ! current_user_can( 'manage_options' ) || empty( $wp_meta_boxes['dashboard'] ) ) {
			return;
		}

		$found = array();
		foreach ( (array) $wp_meta_boxes['dashboard'] as $priorities ) {
			foreach ( (array) $priorities as $boxes ) {
				foreach ( (array) $boxes as $id => $box ) {
					if ( ! is_array( $box ) || 0 === strpos( $id, self::PREFIX ) || isset( self::core_widgets()[ $id ] ) ) {
						continue;
					}
					$title        = trim( wp_strip_all_tags( (string) $box['title'] ) );
					$found[ $id ] = '' !== $title ? $title : $id;
				}
			}
		}

		$seen = get_option( self::SEEN, array() );
		$seen = is_array( $seen ) ? $seen : array();

		// Keep widgets from plugins that are switched off for now, so their settings stay visible.
		$merged = array_merge( $seen, $found );

		if ( $merged !== $seen ) {
			update_option( self::SEEN, $merged, false );
		}
	}

	/**
	 * Whether the current user should see a widget, by role.
	 *
	 * @param array $hidden_for Roles the widget is hidden for.
	 * @return bool
	 */
	private static function visible( array $hidden_for ) {
		$roles = (array) wp_get_current_user()->roles;

		// Someone with several roles sees a widget if any of their roles does.
		return ! $roles || (bool) array_diff( $roles, $hidden_for );
	}

	/**
	 * Hides widgets and adds custom ones.
	 *
	 * @return void
	 */
	public static function dashboard() {
		$config = self::config();

		foreach ( $config['hide'] as $id => $hidden_for ) {
			if ( self::visible( $hidden_for ) ) {
				continue;
			}

			if ( 'welcome_panel' === $id ) {
				remove_action( 'welcome_panel', 'wp_welcome_panel' );
				continue;
			}

			foreach ( array_keys( self::columns() ) as $column ) {
				remove_meta_box( $id, 'dashboard', $column );
			}
		}

		$roles = (array) wp_get_current_user()->roles;

		foreach ( $config['custom'] as $id => $widget ) {
			if ( $widget['roles'] && ! array_intersect( $roles, $widget['roles'] ) ) {
				continue;
			}

			wp_add_dashboard_widget(
				self::PREFIX . $id,
				esc_html( Renderer::replace( $widget['title'], Renderer::common_context(), false ) ),
				array( Widget_Render::class, 'render' ),
				null,
				$widget,
				$widget['column'],
				'high'
			);
		}
	}

	/**
	 * Whether the layout is locked for the current user. Administrators can
	 * still rearrange, so they can change the default.
	 *
	 * @return bool
	 */
	public static function locked() {
		$layout = self::config()['layout'];

		return $layout['lock'] && ! empty( $layout['order'] ) && ! current_user_can( 'manage_options' );
	}

	/**
	 * Supplies the default layout to people who have not arranged their own,
	 * and to everyone when the layout is locked.
	 *
	 * @param mixed  $value  Stored value.
	 * @param string $option Option name.
	 * @return mixed
	 */
	public static function layout_option( $value, $option ) {
		$layout = self::config()['layout'];

		if ( empty( $layout['saved'] ) ) {
			return $value;
		}

		$keys = array(
			'meta-box-order_dashboard'  => 'order',
			'closedpostboxes_dashboard' => 'closed',
			'metaboxhidden_dashboard'   => 'hidden',
		);
		$key  = $keys[ $option ];

		if ( 'order' === $key && self::locked() ) {
			return $layout['order'];
		}

		return false === $value || ( 'order' === $key && empty( $value ) ) ? $layout[ $key ] : $value;
	}

	/**
	 * Stops dragging from saving a new order when the layout is locked.
	 *
	 * @return void
	 */
	public static function block_reorder() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Only refuses the request; core checks the nonce when it goes ahead.
		$page = isset( $_POST['page'] ) ? sanitize_key( wp_unslash( $_POST['page'] ) ) : '';

		if ( 'dashboard' === $page && self::locked() ) {
			wp_die( 0 );
		}
	}

	/**
	 * Styles for custom widgets, and the lock, on the dashboard.
	 *
	 * @param string $hook Screen hook.
	 * @return void
	 */
	public static function enqueue( $hook ) {
		if ( 'index.php' !== $hook ) {
			return;
		}

		wp_enqueue_style( 'adminstudio-widgets', ADMINSTUDIO_URL . 'assets/css/widgets.css', array(), Plugin::asset_version( 'assets/css/widgets.css' ) );

		if ( self::locked() ) {
			wp_add_inline_script( 'dashboard', 'jQuery(function($){document.body.classList.add("adminstudio-widgets-locked");$(window).on("load",function(){$("#dashboard-widgets .meta-box-sortables").each(function(){if($(this).sortable("instance")){$(this).sortable("disable");}});});});' );
		}
	}

	/**
	 * Copies a user's dashboard arrangement into the default layout.
	 *
	 * @param int  $user_id User whose layout to copy.
	 * @param bool $lock    Whether to lock it.
	 * @return void
	 */
	public static function capture_layout( $user_id, $lock ) {
		$prefix = $GLOBALS['wpdb']->get_blog_prefix();
		$read   = static function ( $key ) use ( $user_id, $prefix ) {
			$value = get_user_meta( $user_id, $prefix . $key, true );
			return '' !== $value ? $value : get_user_meta( $user_id, $key, true );
		};

		$order  = $read( 'meta-box-order_dashboard' );
		$closed = $read( 'closedpostboxes_dashboard' );
		$hidden = $read( 'metaboxhidden_dashboard' );

		// Someone who never moved a widget still has a layout: the one WordPress builds.
		if ( ! is_array( $order ) || ! array_filter( $order ) ) {
			$order = self::current_boxes();
		}

		self::save(
			array(
				'layout' => array(
					'order'  => is_array( $order ) ? $order : array(),
					'closed' => is_array( $closed ) ? $closed : array(),
					'hidden' => is_array( $hidden ) ? $hidden : array(),
					'lock'   => $lock,
					'saved'  => time(),
				),
			)
		);
	}

	/**
	 * The order WordPress would show from the widgets recorded so far.
	 *
	 * @return array Column => comma-separated ids.
	 */
	private static function current_boxes() {
		$columns = array(
			'normal' => array( 'dashboard_site_health', 'dashboard_right_now', 'dashboard_activity' ),
			'side'   => array( 'dashboard_quick_press', 'dashboard_primary' ),
		);
		$custom  = array();

		// Custom widgets are added with high priority, so they come first, in the order they were made.
		foreach ( self::config()['custom'] as $id => $widget ) {
			$custom[ $widget['column'] ][] = self::PREFIX . $id;
		}

		$order = array();
		foreach ( array_keys( self::columns() ) as $column ) {
			$ids = array_merge( isset( $custom[ $column ] ) ? $custom[ $column ] : array(), isset( $columns[ $column ] ) ? $columns[ $column ] : array() );
			if ( $ids ) {
				$order[ $column ] = implode( ',', $ids );
			}
		}

		return $order;
	}

	/**
	 * Clears everyone's own arrangement so the default applies to them.
	 *
	 * @return void
	 */
	public static function reset_users() {
		$prefix = $GLOBALS['wpdb']->get_blog_prefix();

		foreach ( array( 'meta-box-order_dashboard', 'closedpostboxes_dashboard', 'metaboxhidden_dashboard' ) as $key ) {
			delete_metadata( 'user', 0, $key, '', true );
			delete_metadata( 'user', 0, $prefix . $key, '', true );
		}
	}
}

<?php
/**
 * Access rules per role: menu visibility, login redirect, dashboard access and
 * the toolbar.
 *
 * Rules are stored in one option. When a user has several roles, an item is
 * only hidden or blocked if every one of their roles hides or blocks it.
 * Administrators are never restricted.
 *
 * @package AdminStudio
 */

namespace AdminStudio\Modules\Roles;

defined( 'ABSPATH' ) || exit;

/**
 * Access rules.
 */
class Access {

	const OPTION       = 'adminstudio_access';
	const NODES        = 'adminstudio_toolbar_nodes';
	const USER_TOOLBAR = 'adminstudio_toolbar';

	/**
	 * Screens that always stay reachable.
	 *
	 * @var string[]
	 */
	const ALWAYS_OPEN = array( 'index.php', 'profile.php', 'admin-ajax.php', 'admin-post.php', 'async-upload.php' );

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'filter_menu' ), PHP_INT_MAX );
		add_action( 'admin_init', array( __CLASS__, 'block_screens' ), 1 );
		add_filter( 'login_redirect', array( __CLASS__, 'login_redirect' ), 20, 3 );
		add_filter( 'show_admin_bar', array( __CLASS__, 'show_admin_bar' ), 99 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'hide_admin_toolbar' ) );
		add_action( 'admin_bar_menu', array( __CLASS__, 'remove_toolbar_items' ), 9999 );
		add_action( 'wp_before_admin_bar_render', array( __CLASS__, 'remember_toolbar_nodes' ), 9999 );
	}

	/**
	 * All rules with defaults.
	 *
	 * @return array
	 */
	public static function rules() {
		$rules = get_option( self::OPTION, array() );

		return array_merge(
			array(
				'menu'          => array(),
				'redirect'      => array(),
				'block'         => array(),
				'toolbar_front' => array(),
				'toolbar_admin' => array(),
				'toolbar_items' => array(),
			),
			is_array( $rules ) ? $rules : array()
		);
	}

	/**
	 * Saves one group of rules after cleaning it.
	 *
	 * @param string $group Rule group.
	 * @param mixed  $value Raw value.
	 * @return void
	 */
	public static function save( $group, $value ) {
		$rules = self::rules();
		$roles = array_keys( wp_roles()->roles );

		switch ( $group ) {
			case 'menu':
			case 'toolbar_items':
				$clean = array();
				foreach ( (array) $value as $role => $ids ) {
					if ( in_array( $role, $roles, true ) ) {
						$clean[ $role ] = array_values( array_unique( array_map( 'sanitize_text_field', (array) $ids ) ) );
					}
				}
				break;

			case 'redirect':
				$clean = array();
				foreach ( (array) $value as $role => $url ) {
					$url = esc_url_raw( trim( (string) $url ) );
					if ( in_array( $role, $roles, true ) && '' !== $url ) {
						$clean[ $role ] = $url;
					}
				}
				break;

			default:
				$clean = array_values( array_intersect( array_map( 'sanitize_key', (array) $value ), $roles ) );
		}

		$rules[ $group ] = $clean;
		update_option( self::OPTION, $rules );
	}

	/**
	 * Replaces every rule, for imports.
	 *
	 * @param array $rules Rules.
	 * @return void
	 */
	public static function import( array $rules ) {
		foreach ( array( 'menu', 'redirect', 'block', 'toolbar_front', 'toolbar_admin', 'toolbar_items' ) as $group ) {
			self::save( $group, isset( $rules[ $group ] ) ? $rules[ $group ] : array() );
		}
	}

	/**
	 * Whether the user is exempt from restrictions.
	 *
	 * @param \WP_User $user User.
	 * @return bool
	 */
	private static function exempt( $user ) {
		return ! $user->exists() || in_array( 'administrator', (array) $user->roles, true ) || is_super_admin( $user->ID );
	}

	/**
	 * Whether every one of the user's roles is in a list.
	 *
	 * @param \WP_User $user  User.
	 * @param string[] $roles Roles.
	 * @return bool
	 */
	private static function all_roles_in( $user, array $roles ) {
		$mine = (array) $user->roles;

		return $mine && ! array_diff( $mine, $roles );
	}

	/**
	 * Ids hidden for every one of the user's roles in a per-role list.
	 *
	 * @param \WP_User $user  User.
	 * @param array    $lists Role => ids.
	 * @return string[]
	 */
	private static function hidden_for( $user, array $lists ) {
		$hidden = null;

		foreach ( (array) $user->roles as $role ) {
			$ids    = isset( $lists[ $role ] ) ? (array) $lists[ $role ] : array();
			$hidden = null === $hidden ? $ids : array_intersect( $hidden, $ids );
		}

		return $hidden ? array_values( $hidden ) : array();
	}

	/**
	 * Menu items for the editor: top level and submenus.
	 *
	 * @return array Id => [ label, depth ].
	 */
	public static function menu_items() {
		global $menu, $submenu;

		$items = array();

		foreach ( (array) $menu as $entry ) {
			if ( empty( $entry[2] ) || ( isset( $entry[4] ) && false !== strpos( $entry[4], 'wp-menu-separator' ) ) ) {
				continue;
			}

			$slug           = $entry[2];
			$items[ $slug ] = array( self::clean_label( $entry[0] ), 0 );

			foreach ( isset( $submenu[ $slug ] ) ? (array) $submenu[ $slug ] : array() as $sub ) {
				if ( ! empty( $sub[2] ) && $sub[2] !== $slug ) {
					$items[ $slug . '>' . $sub[2] ] = array( self::clean_label( $sub[0] ), 1 );
				}
			}
		}

		return $items;
	}

	/**
	 * Strips counters and markup from a menu label.
	 *
	 * @param string $label Label HTML.
	 * @return string
	 */
	public static function clean_label( $label ) {
		$label = preg_replace( '#<span[^>]*class="[^"]*(count|awaiting|update-plugins|pending)[^"]*"[^>]*>.*?</span>(</span>)?#s', '', (string) $label );

		return trim( wp_strip_all_tags( $label ) );
	}

	/**
	 * Removes hidden menu items for the current user.
	 *
	 * @return void
	 */
	public static function filter_menu() {
		$user = wp_get_current_user();

		if ( self::exempt( $user ) ) {
			return;
		}

		foreach ( self::hidden_for( $user, self::rules()['menu'] ) as $id ) {
			if ( false !== strpos( $id, '>' ) ) {
				list( $parent, $slug ) = explode( '>', $id, 2 );
				remove_submenu_page( $parent, $slug );
			} else {
				remove_menu_page( $id );
			}
		}
	}

	/**
	 * Identifiers for the screen being requested, to match against menu slugs.
	 *
	 * @return string[]
	 */
	private static function requested_ids() {
		global $pagenow;

		$ids = array( $pagenow );

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only comparison against hidden menu slugs.
		if ( isset( $_GET['page'] ) ) {
			$ids[] = sanitize_text_field( wp_unslash( $_GET['page'] ) );
		}

		if ( isset( $_GET['post_type'] ) ) {
			$ids[] = $pagenow . '?post_type=' . sanitize_key( wp_unslash( $_GET['post_type'] ) );
		}

		if ( isset( $_GET['taxonomy'] ) ) {
			$ids[] = $pagenow . '?taxonomy=' . sanitize_key( wp_unslash( $_GET['taxonomy'] ) );
		}
		// phpcs:enable

		return $ids;
	}

	/**
	 * Blocks hidden screens opened by URL, and keeps blocked roles out of the
	 * dashboard.
	 *
	 * @return void
	 */
	public static function block_screens() {
		global $pagenow;

		$user = wp_get_current_user();

		if ( self::exempt( $user ) || wp_doing_ajax() ) {
			return;
		}

		if ( ! in_array( $pagenow, array( 'admin-ajax.php', 'admin-post.php', 'async-upload.php' ), true ) ) {
			self::block_dashboard( $user );
		}

		if ( in_array( $pagenow, self::ALWAYS_OPEN, true ) ) {
			return;
		}

		$requested = self::requested_ids();

		foreach ( self::hidden_for( $user, self::rules()['menu'] ) as $id ) {
			$slug = false !== strpos( $id, '>' ) ? explode( '>', $id, 2 )[1] : $id;

			if ( in_array( $slug, $requested, true ) ) {
				wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'admin-studio' ), 403 );
			}
		}
	}

	/**
	 * Sends roles that are kept out of the dashboard to the front end.
	 *
	 * @param \WP_User $user User.
	 * @return void
	 */
	private static function block_dashboard( $user ) {
		if ( self::all_roles_in( $user, self::rules()['block'] ) && ! user_can( $user, 'manage_options' ) ) {
			wp_safe_redirect( home_url( '/' ) );
			exit;
		}
	}

	/**
	 * Sends each role to its chosen page after logging in, unless a specific
	 * page was requested.
	 *
	 * @param string             $redirect  Destination.
	 * @param string             $requested Requested destination.
	 * @param \WP_User|\WP_Error $user     User.
	 * @return string
	 */
	public static function login_redirect( $redirect, $requested, $user ) {
		if ( ! $user instanceof \WP_User || self::exempt( $user ) ) {
			return $redirect;
		}

		$default = '' === $requested || untrailingslashit( $requested ) === untrailingslashit( admin_url() );

		if ( ! $default ) {
			return $redirect;
		}

		if ( self::all_roles_in( $user, self::rules()['block'] ) ) {
			$redirect = home_url( '/' );
		}

		foreach ( (array) $user->roles as $role ) {
			if ( ! empty( self::rules()['redirect'][ $role ] ) ) {
				return self::rules()['redirect'][ $role ];
			}
		}

		return $redirect;
	}

	/**
	 * Front-end toolbar visibility.
	 *
	 * @param bool $show Whether to show it.
	 * @return bool
	 */
	public static function show_admin_bar( $show ) {
		$user = wp_get_current_user();

		if ( ! $user->exists() ) {
			return $show;
		}

		$choice = get_user_meta( $user->ID, self::USER_TOOLBAR, true );

		if ( 'hide' === $choice ) {
			return false;
		}

		if ( 'show' === $choice ) {
			return $show;
		}

		return self::exempt( $user ) || ! self::all_roles_in( $user, self::rules()['toolbar_front'] ) ? $show : false;
	}

	/**
	 * Whether the dashboard toolbar is hidden for a user. Only applies with an
	 * admin template, which moves the account menu into the sidebar footer.
	 *
	 * @param \WP_User $user User.
	 * @return bool
	 */
	public static function admin_toolbar_hidden( $user ) {
		if ( self::exempt( $user ) || ! self::all_roles_in( $user, self::rules()['toolbar_admin'] ) ) {
			return false;
		}

		return class_exists( '\AdminStudio\Modules\Admin_Look\Admin_Look' ) && \AdminStudio\Modules\Admin_Look\Admin_Look::active_for( $user );
	}

	/**
	 * Hides the toolbar inside the dashboard.
	 *
	 * @return void
	 */
	public static function hide_admin_toolbar() {
		if ( self::admin_toolbar_hidden( wp_get_current_user() ) ) {
			wp_add_inline_style( 'common', 'html.wp-toolbar{padding-top:0}#wpadminbar{display:none}' );
		}
	}

	/**
	 * Removes toolbar items hidden for the user's roles.
	 *
	 * @param \WP_Admin_Bar $bar Toolbar.
	 * @return void
	 */
	public static function remove_toolbar_items( $bar ) {
		$user = wp_get_current_user();

		if ( self::exempt( $user ) ) {
			return;
		}

		foreach ( self::hidden_for( $user, self::rules()['toolbar_items'] ) as $id ) {
			$bar->remove_node( $id );
		}
	}

	/**
	 * Remembers the toolbar's items so the settings page can list them,
	 * including items added by other plugins.
	 *
	 * @return void
	 */
	public static function remember_toolbar_nodes() {
		global $wp_admin_bar;

		if ( ! current_user_can( 'manage_options' ) || ! $wp_admin_bar instanceof \WP_Admin_Bar ) {
			return;
		}

		$nodes = array();

		foreach ( (array) $wp_admin_bar->get_nodes() as $node ) {
			if ( in_array( $node->parent, array( false, '', 'root-default', 'top-secondary' ), true ) || 'root' === $node->parent ) {
				$title              = trim( wp_strip_all_tags( (string) $node->title ) );
				$nodes[ $node->id ] = '' !== $title ? $title : $node->id;
			}
		}

		unset( $nodes['top-secondary'], $nodes['root-default'] );

		$saved = get_option( self::NODES, array() );

		if ( $saved !== $nodes ) {
			update_option( self::NODES, array_merge( is_array( $saved ) ? $saved : array(), $nodes ), false );
		}
	}

	/**
	 * Toolbar items known so far.
	 *
	 * @return array Id => label.
	 */
	public static function toolbar_nodes() {
		$defaults = array(
			'wp-logo'     => __( 'WordPress logo menu', 'admin-studio' ),
			'site-name'   => __( 'Site name', 'admin-studio' ),
			'updates'     => __( 'Updates', 'admin-studio' ),
			'comments'    => __( 'Comments', 'admin-studio' ),
			'new-content' => __( '+ New', 'admin-studio' ),
			'edit'        => __( 'Edit link', 'admin-studio' ),
			'customize'   => __( 'Customize', 'admin-studio' ),
			'search'      => __( 'Search', 'admin-studio' ),
			'my-account'  => __( 'Account menu', 'admin-studio' ),
		);

		$saved = get_option( self::NODES, array() );

		return array_merge( $defaults, is_array( $saved ) ? array_diff_key( $saved, $defaults ) : array() );
	}
}

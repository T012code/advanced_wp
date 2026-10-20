<?php
/**
 * Capability catalogue: every capability on the site, grouped and labelled.
 *
 * @package AdminStudio
 */

namespace AdminStudio\Modules\Roles;

defined( 'ABSPATH' ) || exit;

/**
 * Groups and describes capabilities for the editors.
 */
class Capabilities {

	/**
	 * Core capabilities by group.
	 *
	 * @return array Group key => capability list.
	 */
	private static function core_groups() {
		return array(
			'posts'      => array( 'edit_posts', 'edit_others_posts', 'edit_published_posts', 'edit_private_posts', 'publish_posts', 'read_private_posts', 'delete_posts', 'delete_others_posts', 'delete_published_posts', 'delete_private_posts', 'manage_categories' ),
			'pages'      => array( 'edit_pages', 'edit_others_pages', 'edit_published_pages', 'edit_private_pages', 'publish_pages', 'read_private_pages', 'delete_pages', 'delete_others_pages', 'delete_published_pages', 'delete_private_pages' ),
			'media'      => array( 'upload_files', 'unfiltered_upload' ),
			'comments'   => array( 'moderate_comments' ),
			'users'      => array( 'list_users', 'create_users', 'add_users', 'edit_users', 'promote_users', 'remove_users', 'delete_users' ),
			'appearance' => array( 'switch_themes', 'edit_theme_options', 'install_themes', 'update_themes', 'edit_themes', 'delete_themes' ),
			'plugins'    => array( 'activate_plugins', 'install_plugins', 'update_plugins', 'edit_plugins', 'delete_plugins' ),
			'settings'   => array( 'read', 'manage_options', 'edit_dashboard', 'import', 'export', 'update_core', 'unfiltered_html', 'manage_links', 'edit_files' ),
		);
	}

	/**
	 * Group labels.
	 *
	 * @return array
	 */
	public static function group_labels() {
		return array(
			'posts'       => __( 'Posts', 'admin-studio' ),
			'pages'       => __( 'Pages', 'admin-studio' ),
			'media'       => __( 'Media', 'admin-studio' ),
			'comments'    => __( 'Comments', 'admin-studio' ),
			'users'       => __( 'Users', 'admin-studio' ),
			'appearance'  => __( 'Appearance', 'admin-studio' ),
			'plugins'     => __( 'Plugins', 'admin-studio' ),
			'settings'    => __( 'Settings and tools', 'admin-studio' ),
			'woocommerce' => __( 'WooCommerce', 'admin-studio' ),
			'other'       => __( 'Other', 'admin-studio' ),
		);
	}

	/**
	 * Plain-language descriptions of core capabilities.
	 *
	 * @return array
	 */
	public static function descriptions() {
		return array(
			'read'                   => __( 'Open the dashboard and their own profile.', 'admin-studio' ),
			'edit_posts'             => __( 'Write and edit their own posts.', 'admin-studio' ),
			'edit_others_posts'      => __( 'Edit posts written by anyone.', 'admin-studio' ),
			'edit_published_posts'   => __( 'Edit their posts after publishing.', 'admin-studio' ),
			'edit_private_posts'     => __( 'Edit private posts.', 'admin-studio' ),
			'publish_posts'          => __( 'Publish posts.', 'admin-studio' ),
			'read_private_posts'     => __( 'Read private posts.', 'admin-studio' ),
			'delete_posts'           => __( 'Delete their own posts.', 'admin-studio' ),
			'delete_others_posts'    => __( 'Delete posts written by anyone.', 'admin-studio' ),
			'delete_published_posts' => __( 'Delete their published posts.', 'admin-studio' ),
			'delete_private_posts'   => __( 'Delete private posts.', 'admin-studio' ),
			'manage_categories'      => __( 'Add, edit and delete categories and tags.', 'admin-studio' ),
			'edit_pages'             => __( 'Write and edit their own pages.', 'admin-studio' ),
			'edit_others_pages'      => __( 'Edit pages created by anyone.', 'admin-studio' ),
			'publish_pages'          => __( 'Publish pages.', 'admin-studio' ),
			'delete_pages'           => __( 'Delete their own pages.', 'admin-studio' ),
			'delete_others_pages'    => __( 'Delete pages created by anyone.', 'admin-studio' ),
			'upload_files'           => __( 'Upload images and other files to the media library.', 'admin-studio' ),
			'unfiltered_upload'      => __( 'Upload any file type, including ones WordPress normally blocks.', 'admin-studio' ),
			'moderate_comments'      => __( 'Approve, edit and delete comments.', 'admin-studio' ),
			'list_users'             => __( 'See the list of users.', 'admin-studio' ),
			'create_users'           => __( 'Create new users.', 'admin-studio' ),
			'edit_users'             => __( 'Edit other users\' profiles.', 'admin-studio' ),
			'promote_users'          => __( 'Change other users\' roles.', 'admin-studio' ),
			'remove_users'           => __( 'Remove users from the site.', 'admin-studio' ),
			'delete_users'           => __( 'Delete users.', 'admin-studio' ),
			'switch_themes'          => __( 'Change the active theme.', 'admin-studio' ),
			'edit_theme_options'     => __( 'Use the Customizer, menus and widgets.', 'admin-studio' ),
			'install_themes'         => __( 'Install new themes.', 'admin-studio' ),
			'edit_themes'            => __( 'Edit theme files in the code editor.', 'admin-studio' ),
			'activate_plugins'       => __( 'Turn plugins on and off.', 'admin-studio' ),
			'install_plugins'        => __( 'Install new plugins.', 'admin-studio' ),
			'update_plugins'         => __( 'Update plugins.', 'admin-studio' ),
			'edit_plugins'           => __( 'Edit plugin files in the code editor.', 'admin-studio' ),
			'delete_plugins'         => __( 'Delete plugins.', 'admin-studio' ),
			'manage_options'         => __( 'Change site settings, including Admin Studio.', 'admin-studio' ),
			'edit_dashboard'         => __( 'Change dashboard widgets for everyone.', 'admin-studio' ),
			'import'                 => __( 'Import content.', 'admin-studio' ),
			'export'                 => __( 'Export content.', 'admin-studio' ),
			'update_core'            => __( 'Update WordPress itself.', 'admin-studio' ),
			'unfiltered_html'        => __( 'Add any HTML or scripts to content. Give this only to people you fully trust.', 'admin-studio' ),
		);
	}

	/**
	 * Every capability known on the site, without role names or legacy levels.
	 *
	 * @return string[]
	 */
	public static function all() {
		$caps = array();

		foreach ( self::core_groups() as $list ) {
			$caps = array_merge( $caps, $list );
		}

		foreach ( wp_roles()->roles as $role ) {
			$caps = array_merge( $caps, array_keys( (array) $role['capabilities'] ) );
		}

		$caps = array_filter(
			array_unique( $caps ),
			static function ( $cap ) {
				return ! preg_match( '/^level_\d+$/', $cap ) && ! wp_roles()->is_role( $cap );
			}
		);

		sort( $caps );

		return array_values( $caps );
	}

	/**
	 * Capabilities grouped for display.
	 *
	 * @return array Group key => [ label, capabilities ].
	 */
	public static function grouped() {
		$core   = self::core_groups();
		$labels = self::group_labels();
		$groups = array();
		$placed = array();

		foreach ( $core as $key => $list ) {
			$groups[ $key ] = array( $labels[ $key ], $list );
			$placed         = array_merge( $placed, $list );
		}

		foreach ( self::all() as $cap ) {
			if ( in_array( $cap, $placed, true ) ) {
				continue;
			}

			$key = self::guess_group( $cap );

			if ( ! isset( $groups[ $key ] ) ) {
				$label          = isset( $labels[ $key ] ) ? $labels[ $key ] : ucwords( str_replace( array( '_', '-' ), ' ', $key ) );
				$groups[ $key ] = array( $label, array() );
			}

			$groups[ $key ][1][] = $cap;
		}

		// Keep "Other" last.
		if ( isset( $groups['other'] ) ) {
			$other = $groups['other'];
			unset( $groups['other'] );
			$groups['other'] = $other;
		}

		return $groups;
	}

	/**
	 * Works out a group for a plugin or custom post type capability.
	 *
	 * @param string $cap Capability.
	 * @return string Group key.
	 */
	private static function guess_group( $cap ) {
		if ( preg_match( '/woocommerce|product|shop_order|shop_coupon|shop_webhook/', $cap ) ) {
			return 'woocommerce';
		}

		// Custom post type capabilities such as edit_books or delete_others_events.
		if ( preg_match( '/^(?:edit|delete|publish|read|create)_(?:others_|published_|private_)?([a-z0-9_]+?)s?$/', $cap, $m ) && ! in_array( $m[1], array( 'post', 'page' ), true ) ) {
			return sanitize_key( $m[1] );
		}

		return 'other';
	}

	/**
	 * Human label for a capability.
	 *
	 * @param string $cap Capability.
	 * @return string
	 */
	public static function label( $cap ) {
		return ucfirst( str_replace( '_', ' ', $cap ) );
	}
}

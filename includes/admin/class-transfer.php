<?php
/**
 * Export and import of everything Admin Studio stores, in one JSON file.
 *
 * Pages and categories are referred to by ID inside the settings. IDs differ
 * from site to site, so the file also carries each one's slug, and an import
 * links them to the page or category with the same slug on the new site.
 *
 * @package AdminStudio
 */

namespace AdminStudio\Admin;

use AdminStudio\Brand_Kit;
use AdminStudio\Settings;
use AdminStudio\Modules\Admin_Look\Admin_Look;
use AdminStudio\Modules\Roles\Access;
use AdminStudio\Modules\Roles\Audit;
use AdminStudio\Modules\Roles\Redirects;
use AdminStudio\Modules\Roles\Role_Manager;
use AdminStudio\Modules\Security\Admin_Url;
use AdminStudio\Modules\Security\Login_Url;
use AdminStudio\Modules\Widgets\Widgets;

defined( 'ABSPATH' ) || exit;

/**
 * Settings export and import.
 */
class Transfer {

	const FORMAT = 2;

	/**
	 * Parts of an export: key => [ label, description ].
	 *
	 * @return array
	 */
	public static function parts() {
		return array(
			'settings' => array( __( 'Settings and designs', 'admin-studio' ), __( 'Brand kit, login page, emails, system pages, tab details, admin branding, login protection, sessions and coming soon.', 'admin-studio' ) ),
			'look'     => array( __( 'Dashboard look', 'admin-studio' ), __( 'Templates, colours, templates per role and the sidebar menu layout.', 'admin-studio' ) ),
			'widgets'  => array( __( 'Dashboard widgets', 'admin-studio' ), __( 'Which roles see each widget, custom widgets and the default layout.', 'admin-studio' ) ),
			'access'   => array( __( 'Menu, toolbar and login rules', 'admin-studio' ), __( 'Menu access, toolbar settings, login destinations and dashboard access per role.', 'admin-studio' ) ),
			'roles'    => array( __( 'Roles and capabilities', 'admin-studio' ), __( 'Custom roles and what every role can do. The Administrator role is never changed, and a backup of your roles is saved first.', 'admin-studio' ) ),
			'users'    => array( __( 'Personal settings', 'admin-studio' ), __( 'Login destination, template and toolbar choice of each person, matched by username or email. Roles and permissions given to one person are not included.', 'admin-studio' ) ),
		);
	}

	/**
	 * Parts ticked by default on the import form.
	 *
	 * Roles change what people can do, so they are only imported on purpose.
	 *
	 * @return string[]
	 */
	public static function default_import_parts() {
		return array( 'settings', 'look', 'widgets', 'access', 'users' );
	}

	// ------------------------------------------------------------------
	// Export.
	// ------------------------------------------------------------------

	/**
	 * Builds the export.
	 *
	 * @param string[] $parts Parts to include.
	 * @return array
	 */
	public static function export( array $parts ) {
		$parts = array_intersect( $parts, array_keys( self::parts() ) );
		$data  = array(
			'plugin'   => 'admin-studio',
			'version'  => ADMINSTUDIO_VERSION,
			'format'   => self::FORMAT,
			'exported' => gmdate( 'c' ),
			'site'     => home_url(),
		);
		$refs  = array(
			'posts' => array(),
			'terms' => array(),
		);

		if ( in_array( 'settings', $parts, true ) ) {
			$data['settings'] = Settings::raw();

			$palettes = get_option( Brand_Kit::PALETTES, array() );
			if ( is_array( $palettes ) && ! empty( $palettes['frontend']['colors'] ) ) {
				$data['brand_palette'] = (array) $palettes['frontend']['colors'];
			}

			self::add_post_ref( $refs, (int) Settings::get( 'maintenance.page' ) );
		}

		if ( in_array( 'look', $parts, true ) ) {
			$data['look'] = Admin_Look::config();
		}

		if ( in_array( 'widgets', $parts, true ) ) {
			$widgets         = Widgets::config();
			$data['widgets'] = array(
				'hide'   => $widgets['hide'],
				'custom' => $widgets['custom'],
				'layout' => $widgets['layout'],
				'seen'   => get_option( Widgets::SEEN, array() ),
			);

			foreach ( $widgets['custom'] as $widget ) {
				if ( 'posts' === $widget['type'] && $widget['category'] ) {
					self::add_term_ref( $refs, $widget['category'] );
				}
			}
		}

		if ( in_array( 'access', $parts, true ) ) {
			$data['access']                  = Access::rules();
			$data['access']['toolbar_nodes'] = get_option( Access::NODES, array() );

			foreach ( $data['access']['redirect'] as $token ) {
				self::add_token_ref( $refs, $token );
			}
		}

		if ( in_array( 'roles', $parts, true ) ) {
			$data['roles'] = wp_roles()->roles;
		}

		if ( in_array( 'users', $parts, true ) ) {
			$data['users'] = self::export_users( $refs );
		}

		$data['refs'] = $refs;

		return $data;
	}

	/**
	 * Personal settings of everyone who has any.
	 *
	 * @param array $refs References, added to.
	 * @return array List of { login, email, meta }.
	 */
	private static function export_users( array &$refs ) {
		$keys = self::user_keys();
		$out  = array();

		$users = get_users(
			array(
				'fields'     => array( 'ID', 'user_login', 'user_email' ),
				'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Admin-only export.
					'relation' => 'OR',
					array( 'key' => $keys[0] ),
					array( 'key' => $keys[1] ),
					array( 'key' => $keys[2] ),
				),
			)
		);

		foreach ( $users as $user ) {
			$meta = array();
			foreach ( $keys as $key ) {
				$value = get_user_meta( $user->ID, $key, true );
				if ( '' !== $value && array() !== $value ) {
					$meta[ $key ] = $value;
				}
			}

			if ( isset( $meta[ Redirects::META ] ) ) {
				self::add_token_ref( $refs, $meta[ Redirects::META ] );
			}

			$out[] = array(
				'login' => $user->user_login,
				'email' => $user->user_email,
				'meta'  => $meta,
			);
		}

		return $out;
	}

	/**
	 * User meta keys that hold personal settings an administrator sets.
	 *
	 * @return string[]
	 */
	private static function user_keys() {
		return array( Redirects::META, Admin_Look::META_ADMIN, Access::USER_TOOLBAR );
	}

	/**
	 * Remembers the slug of a page referred to by ID.
	 *
	 * @param array $refs References.
	 * @param int   $id   Post ID.
	 * @return void
	 */
	private static function add_post_ref( array &$refs, $id ) {
		$post = $id ? get_post( $id ) : null;

		if ( $post ) {
			$refs['posts'][ $id ] = array(
				'type'  => $post->post_type,
				'slug'  => $post->post_name,
				'title' => $post->post_title,
			);
		}
	}

	/**
	 * Remembers the slug of a category referred to by ID.
	 *
	 * @param array $refs References.
	 * @param int   $id   Term ID.
	 * @return void
	 */
	private static function add_term_ref( array &$refs, $id ) {
		$term = get_term( $id );

		if ( $term instanceof \WP_Term ) {
			$refs['terms'][ $id ] = array(
				'taxonomy' => $term->taxonomy,
				'slug'     => $term->slug,
			);
		}
	}

	/**
	 * Remembers the page in a login destination such as "page:12".
	 *
	 * @param array  $refs  References.
	 * @param string $token Destination.
	 * @return void
	 */
	private static function add_token_ref( array &$refs, $token ) {
		$parts = Redirects::parse( (string) $token );

		if ( 'page' === $parts['type'] ) {
			self::add_post_ref( $refs, $parts['page'] );
		}
	}

	// ------------------------------------------------------------------
	// Import.
	// ------------------------------------------------------------------

	/**
	 * Whether decoded JSON is an Admin Studio export.
	 *
	 * @param mixed $data Decoded file.
	 * @return bool
	 */
	public static function is_export( $data ) {
		return is_array( $data ) && 'admin-studio' === ( isset( $data['plugin'] ) ? $data['plugin'] : '' ) && array_intersect( array_keys( $data ), array_keys( self::parts() ) );
	}

	/**
	 * Imports the chosen parts that the file contains.
	 *
	 * @param array    $data  Decoded export.
	 * @param string[] $parts Parts to import.
	 * @return array { imported: string[], missing: int, roles_error: string, security: string }
	 */
	public static function import( array $data, array $parts ) {
		$report = array(
			'imported'    => array(),
			'missing'     => 0,
			'roles_error' => '',
			'security'    => '',
		);
		$refs   = isset( $data['refs'] ) && is_array( $data['refs'] ) ? $data['refs'] : array();
		$parts  = array_intersect( $parts, array_keys( self::parts() ) );

		// Roles first, so rules for imported roles are kept by the parts after them.
		if ( in_array( 'roles', $parts, true ) && isset( $data['roles'] ) && is_array( $data['roles'] ) ) {
			$result = self::import_roles( $data['roles'] );
			if ( is_wp_error( $result ) ) {
				$report['roles_error'] = $result->get_error_message();
			} else {
				$report['imported'][] = 'roles';
			}
		}

		if ( in_array( 'settings', $parts, true ) && isset( $data['settings'] ) && is_array( $data['settings'] ) ) {
			$settings = $data['settings'];

			if ( isset( $settings['maintenance']['page'] ) ) {
				$settings['maintenance']['page'] = self::map_post( (int) $settings['maintenance']['page'], $refs, $report );
			}

			Settings::import( $settings );
			$report['security'] = self::check_addresses();

			if ( ! empty( $data['brand_palette'] ) && is_array( $data['brand_palette'] ) ) {
				Brand_Kit::save_frontend_palette( $data['brand_palette'] );
			}

			$report['imported'][] = 'settings';
		}

		if ( in_array( 'look', $parts, true ) && isset( $data['look'] ) && is_array( $data['look'] ) ) {
			Admin_Look::save( self::only( $data['look'], array( 'default', 'custom', 'roles', 'self_choice', 'sections', 'items' ) ) );
			$report['imported'][] = 'look';
		}

		if ( in_array( 'widgets', $parts, true ) && isset( $data['widgets'] ) && is_array( $data['widgets'] ) ) {
			$widgets = self::only( $data['widgets'], array( 'hide', 'custom', 'layout' ) );

			if ( isset( $widgets['custom'] ) && is_array( $widgets['custom'] ) ) {
				foreach ( $widgets['custom'] as $id => $widget ) {
					if ( is_array( $widget ) && ! empty( $widget['category'] ) ) {
						$widgets['custom'][ $id ]['category'] = self::map_term( (int) $widget['category'], $refs, $report );
					}
				}
			}

			Widgets::save( $widgets );
			self::merge_seen( Widgets::SEEN, isset( $data['widgets']['seen'] ) ? $data['widgets']['seen'] : array() );
			$report['imported'][] = 'widgets';
		}

		if ( in_array( 'access', $parts, true ) && isset( $data['access'] ) && is_array( $data['access'] ) ) {
			$rules = $data['access'];

			if ( isset( $rules['redirect'] ) && is_array( $rules['redirect'] ) ) {
				foreach ( $rules['redirect'] as $role => $token ) {
					$rules['redirect'][ $role ] = self::map_token( (string) $token, $refs, $report );
				}
			}

			Access::import( $rules );
			self::merge_seen( Access::NODES, isset( $rules['toolbar_nodes'] ) ? $rules['toolbar_nodes'] : array() );
			$report['imported'][] = 'access';
		}

		if ( in_array( 'users', $parts, true ) && isset( $data['users'] ) && is_array( $data['users'] ) ) {
			self::import_users( $data['users'], $refs, $report );
			$report['imported'][] = 'users';
		}

		if ( $report['imported'] ) {
			Audit::log( 'import', __( 'Settings imported from a file.', 'admin-studio' ) );
		}

		return $report;
	}

	/**
	 * Keeps only known keys.
	 *
	 * @param array    $values Values.
	 * @param string[] $keys   Allowed keys.
	 * @return array
	 */
	private static function only( array $values, array $keys ) {
		return array_intersect_key( $values, array_flip( $keys ) );
	}

	/**
	 * Adds detected widget or toolbar names from the file to this site's list.
	 *
	 * @param string $option Option name.
	 * @param mixed  $names  Id => title.
	 * @return void
	 */
	private static function merge_seen( $option, $names ) {
		if ( ! is_array( $names ) || ! $names ) {
			return;
		}

		$clean = array();
		foreach ( $names as $id => $title ) {
			$id = Widgets::clean_id( $id );
			if ( '' !== $id ) {
				$clean[ $id ] = sanitize_text_field( $title );
			}
		}

		$saved = get_option( $option, array() );
		update_option( $option, array_merge( $clean, is_array( $saved ) ? $saved : array() ), false );
	}

	/**
	 * Adds and updates roles from the file. Roles that are not in the file
	 * stay, the Administrator role is never changed, and nothing may take
	 * away the access of the person importing.
	 *
	 * @param array $roles Role slug => { name, capabilities }.
	 * @return true|\WP_Error
	 */
	private static function import_roles( array $roles ) {
		$clean = array();

		foreach ( $roles as $slug => $role ) {
			$slug = sanitize_key( $slug );

			if ( '' === $slug || Role_Manager::LOCKED === $slug || ! is_array( $role ) || empty( $role['name'] ) ) {
				continue;
			}

			$caps = array();
			foreach ( isset( $role['capabilities'] ) ? (array) $role['capabilities'] : array() as $cap => $grant ) {
				$cap = sanitize_key( $cap );
				if ( '' !== $cap ) {
					$caps[ $cap ] = (bool) $grant;
				}
			}

			$clean[ $slug ] = array(
				'name'         => sanitize_text_field( $role['name'] ),
				'capabilities' => $caps,
			);
		}

		foreach ( $clean as $slug => $role ) {
			if ( ! Role_Manager::keeps_access( $slug, array_keys( array_filter( $role['capabilities'] ) ) ) ) {
				return new \WP_Error( 'adminstudio_self_lockout', __( 'The roles were not imported, because they would remove your own access.', 'admin-studio' ) );
			}
		}

		if ( ! $clean ) {
			return true;
		}

		Role_Manager::backup( 'Before importing roles' );

		$wp_roles = wp_roles();
		$current  = $wp_roles->roles;

		foreach ( $clean as $slug => $role ) {
			$current[ $slug ] = $role;
		}

		update_option( $wp_roles->role_key, $current );
		$wp_roles->for_site();

		return true;
	}

	/**
	 * Applies personal settings to people found by username, then email.
	 *
	 * @param array $users  Exported people.
	 * @param array $refs   References.
	 * @param array $report Report, added to.
	 * @return void
	 */
	private static function import_users( array $users, array $refs, array &$report ) {
		foreach ( $users as $person ) {
			if ( ! is_array( $person ) || empty( $person['meta'] ) || ! is_array( $person['meta'] ) ) {
				continue;
			}

			$user = ! empty( $person['login'] ) ? get_user_by( 'login', sanitize_user( $person['login'] ) ) : false;
			$user = ! $user && ! empty( $person['email'] ) ? get_user_by( 'email', sanitize_email( $person['email'] ) ) : $user;

			if ( ! $user ) {
				continue;
			}

			$meta = $person['meta'];

			if ( isset( $meta[ Redirects::META ] ) ) {
				$token = Redirects::clean( self::map_token( (string) $meta[ Redirects::META ], $refs, $report ) );
				if ( '' !== $token ) {
					update_user_meta( $user->ID, Redirects::META, $token );
				}
			}

			if ( isset( $meta[ Admin_Look::META_ADMIN ] ) && is_array( $meta[ Admin_Look::META_ADMIN ] ) ) {
				$choice = Admin_Look::clean_choice( array_map( 'sanitize_key', $meta[ Admin_Look::META_ADMIN ] ), true );
				if ( '' !== $choice['template'] || $choice['lock'] ) {
					update_user_meta( $user->ID, Admin_Look::META_ADMIN, $choice );
				}
			}

			if ( isset( $meta[ Access::USER_TOOLBAR ] ) && in_array( $meta[ Access::USER_TOOLBAR ], array( 'show', 'hide' ), true ) ) {
				update_user_meta( $user->ID, Access::USER_TOOLBAR, $meta[ Access::USER_TOOLBAR ] );
			}
		}
	}

	/**
	 * This site's ID for a page from the file, or 0 when there is no such page.
	 *
	 * @param int   $id     ID on the exporting site.
	 * @param array $refs   References.
	 * @param array $report Report; counts pages that could not be found.
	 * @return int
	 */
	private static function map_post( $id, array $refs, array &$report ) {
		if ( ! $id ) {
			return 0;
		}

		$ref = isset( $refs['posts'][ $id ] ) && is_array( $refs['posts'][ $id ] ) ? $refs['posts'][ $id ] : null;

		// Files from before references were added keep their ID only if that page exists here.
		if ( ! $ref ) {
			if ( get_post( $id ) ) {
				return $id;
			}
			++$report['missing'];
			return 0;
		}

		$type  = sanitize_key( isset( $ref['type'] ) ? $ref['type'] : 'page' );
		$slug  = sanitize_title( isset( $ref['slug'] ) ? $ref['slug'] : '' );
		$title = sanitize_text_field( isset( $ref['title'] ) ? $ref['title'] : '' );
		$here  = get_post( $id );

		if ( $here && $here->post_type === $type && ( '' !== $slug ? $here->post_name === $slug : $here->post_title === $title ) ) {
			return $id;
		}

		// Drafts have no slug yet, so they are matched by their exact title.
		foreach ( array(
			'name'  => $slug,
			'title' => $title,
		) as $field => $value ) {
			if ( '' === $value ) {
				continue;
			}

			$found = get_posts(
				array(
					$field           => $value,
					'post_type'      => $type,
					'post_status'    => array( 'publish', 'private', 'draft', 'pending', 'future' ),
					'posts_per_page' => 1,
					'fields'         => 'ids',
					'no_found_rows'  => true,
				)
			);

			if ( $found ) {
				return (int) $found[0];
			}
		}

		++$report['missing'];
		return 0;
	}

	/**
	 * This site's ID for a category from the file, or 0 (all categories).
	 *
	 * @param int   $id     ID on the exporting site.
	 * @param array $refs   References.
	 * @param array $report Report; counts categories that could not be found.
	 * @return int
	 */
	private static function map_term( $id, array $refs, array &$report ) {
		$ref = isset( $refs['terms'][ $id ] ) && is_array( $refs['terms'][ $id ] ) ? $refs['terms'][ $id ] : null;

		if ( ! $ref ) {
			if ( get_term( $id ) instanceof \WP_Term ) {
				return $id;
			}
			++$report['missing'];
			return 0;
		}

		$term = get_term_by( 'slug', sanitize_title( isset( $ref['slug'] ) ? $ref['slug'] : '' ), sanitize_key( isset( $ref['taxonomy'] ) ? $ref['taxonomy'] : 'category' ) );

		if ( $term instanceof \WP_Term ) {
			return (int) $term->term_id;
		}

		++$report['missing'];
		return 0;
	}

	/**
	 * A login destination with its page linked to this site, or '' when the page is missing.
	 *
	 * @param string $token  Destination.
	 * @param array  $refs   References.
	 * @param array  $report Report.
	 * @return string
	 */
	private static function map_token( $token, array $refs, array &$report ) {
		$parts = Redirects::parse( $token );

		if ( 'page' !== $parts['type'] ) {
			return $token;
		}

		$id = self::map_post( $parts['page'], $refs, $report );

		return $id ? 'page:' . $id : '';
	}

	/**
	 * Checks the imported login and dashboard addresses the same way saving
	 * the Login protection tab does, and applies the dashboard address.
	 *
	 * @return string Notice key when something was switched off, or ''.
	 */
	private static function check_addresses() {
		$notice  = '';
		$changes = array();
		$slug    = (string) Settings::get( 'security.login_slug' );

		if ( Settings::get( 'security.login_url' ) && ! Login_Url::is_valid( $slug ) ) {
			$changes['security.login_url'] = false;
			$notice                        = 'slug_invalid';
		}

		$admin_slug = (string) Settings::get( 'security.admin_slug' );

		if ( Settings::get( 'security.admin_url' ) && ( '' === $admin_slug || in_array( $admin_slug, Login_Url::RESERVED, true ) || $admin_slug === $slug ) ) {
			$changes['security.admin_url'] = false;
			$notice                        = 'admin_slug_invalid';
		}

		if ( $changes ) {
			Settings::update( $changes );
		}

		// Moves the dashboard only after the server test passes, as on the settings page.
		$state = Admin_Url::apply();

		if ( '' === $notice && in_array( $state, array( 'pending', 'unwritable' ), true ) ) {
			$notice = 'admin_url_' . $state;
		}

		return $notice;
	}
}

<?php
/**
 * Settings → Admin Studio → Roles & permissions.
 *
 * @package AdminStudio
 */

namespace AdminStudio\Admin;

use AdminStudio\Settings;
use AdminStudio\Modules\Roles\Access;
use AdminStudio\Modules\Roles\Audit;
use AdminStudio\Modules\Roles\Capabilities;
use AdminStudio\Modules\Roles\Redirects;
use AdminStudio\Modules\Roles\Role_Manager;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the roles tab and handles its forms.
 */
class Roles_Tab {

	/**
	 * Hooks for form handlers.
	 *
	 * @return void
	 */
	public static function init() {
		foreach ( array( 'role_create', 'role_save', 'role_delete', 'role_restore', 'role_defaults', 'access_save', 'audit_csv' ) as $action ) {
			add_action( 'admin_post_adminstudio_' . $action, array( __CLASS__, 'handle_' . $action ) );
		}
	}

	/**
	 * Sections of the tab.
	 *
	 * @return array
	 */
	private static function sections() {
		return array(
			'roles'   => __( 'Roles', 'admin-studio' ),
			'menu'    => __( 'Menu access', 'admin-studio' ),
			'toolbar' => __( 'Toolbar', 'admin-studio' ),
			'access'  => __( 'Login and access', 'admin-studio' ),
			'backups' => __( 'Backups', 'admin-studio' ),
			'log'     => __( 'Activity log', 'admin-studio' ),
		);
	}

	/**
	 * URL of a section.
	 *
	 * @param string $section Section key.
	 * @param array  $args    Extra arguments.
	 * @return string
	 */
	private static function url( $section, array $args = array() ) {
		return Admin::url( 'roles', array_merge( array( 'section' => $section ), $args ) );
	}

	/**
	 * Redirects back with a notice.
	 *
	 * @param string $section Section.
	 * @param string $notice  Notice key, or an error message prefixed with "error:".
	 * @param array  $args    Extra arguments.
	 * @return void
	 */
	private static function back( $section, $notice, array $args = array() ) {
		if ( 0 === strpos( $notice, 'error:' ) ) {
			set_transient( 'adminstudio_roles_error_' . get_current_user_id(), substr( $notice, 6 ), 60 );
			$notice = 'roles_error';
		}

		wp_safe_redirect( self::url( $section, array_merge( array( 'adminstudio_notice' => $notice ), $args ) ) );
		exit;
	}

	/**
	 * Checks capability and nonce.
	 *
	 * @param string $action Nonce action.
	 * @return void
	 */
	private static function guard( $action ) {
		if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'promote_users' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'admin-studio' ), '', array( 'response' => 403 ) );
		}

		check_admin_referer( $action );
	}

	/**
	 * Opens a form posting to admin-post.php.
	 *
	 * @param string $action Action suffix.
	 * @param array  $extra  Hidden fields.
	 * @param bool   $confirm Whether to ask for confirmation.
	 * @return void
	 */
	private static function form( $action, array $extra = array(), $confirm = false ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"' . ( $confirm ? ' data-adminstudio-confirm' : '' ) . '>';
		echo '<input type="hidden" name="action" value="adminstudio_' . esc_attr( $action ) . '">';
		foreach ( $extra as $name => $value ) {
			echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '">';
		}
		wp_nonce_field( 'adminstudio_' . $action );
	}

	/**
	 * Renders the tab.
	 *
	 * @return void
	 */
	public static function render() {
		if ( ! Settings::module_on( 'roles' ) ) {
			echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'Roles and permissions is switched off on the Overview tab.', 'admin-studio' ) . '</p></div>';
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Navigation only.
		$section = isset( $_GET['section'] ) ? sanitize_key( wp_unslash( $_GET['section'] ) ) : 'roles';
		$role    = isset( $_GET['role'] ) ? sanitize_key( wp_unslash( $_GET['role'] ) ) : '';
		// phpcs:enable

		$section = array_key_exists( $section, self::sections() ) ? $section : 'roles';

		$error = get_transient( 'adminstudio_roles_error_' . get_current_user_id() );
		if ( $error ) {
			delete_transient( 'adminstudio_roles_error_' . get_current_user_id() );
			echo '<div class="notice notice-error inline adminstudio-notice"><p>' . esc_html( $error ) . '</p></div>';
		}

		echo '<nav class="adminstudio-subnav" aria-label="' . esc_attr__( 'Roles and permissions sections', 'admin-studio' ) . '"><ul>';
		foreach ( self::sections() as $key => $label ) {
			printf(
				'<li><a href="%s"%s>%s</a></li>',
				esc_url( self::url( $key ) ),
				$key === $section ? ' class="is-current" aria-current="page"' : '',
				esc_html( $label )
			);
		}
		echo '</ul></nav>';

		if ( 'roles' === $section && '' !== $role && get_role( $role ) ) {
			self::edit_role( $role );
			return;
		}

		$method = 'section_' . $section;
		self::$method();
	}

	/**
	 * Roles list and the add form.
	 *
	 * @return void
	 */
	private static function section_roles() {
		$roles = Role_Manager::roles();

		echo '<p class="adminstudio-intro">' . esc_html__( 'Create roles, copy existing ones and choose exactly what each role can do. A copy of all roles is saved before every change.', 'admin-studio' ) . '</p>';

		echo '<table class="widefat striped adminstudio-roles"><thead><tr><th>' . esc_html__( 'Role', 'admin-studio' ) . '</th><th>' . esc_html__( 'Slug', 'admin-studio' ) . '</th><th>' . esc_html__( 'Users', 'admin-studio' ) . '</th><th>' . esc_html__( 'Capabilities', 'admin-studio' ) . '</th><th><span class="screen-reader-text">' . esc_html__( 'Actions', 'admin-studio' ) . '</span></th></tr></thead><tbody>';

		foreach ( $roles as $slug => $role ) {
			echo '<tr><td><strong>' . esc_html( $role['name'] ) . '</strong>';
			if ( Role_Manager::LOCKED === $slug ) {
				echo ' <span class="adminstudio-badge">' . esc_html__( 'Locked', 'admin-studio' ) . '</span>';
			} elseif ( ! Role_Manager::is_core( $slug ) ) {
				echo ' <span class="adminstudio-badge">' . esc_html__( 'Custom', 'admin-studio' ) . '</span>';
			}
			echo '</td><td><code>' . esc_html( $slug ) . '</code></td>';
			echo '<td><a href="' . esc_url( admin_url( 'users.php?role=' . $slug ) ) . '">' . esc_html( number_format_i18n( $role['count'] ) ) . '</a></td>';
			echo '<td>' . esc_html( number_format_i18n( count( $role['caps'] ) ) ) . '</td>';
			echo '<td class="adminstudio-row-actions"><a class="button button-small" href="' . esc_url( self::url( 'roles', array( 'role' => $slug ) ) ) . '">' . esc_html( Role_Manager::LOCKED === $slug ? __( 'View', 'admin-studio' ) : __( 'Edit', 'admin-studio' ) ) . '</a></td></tr>';
		}

		echo '</tbody></table>';

		echo '<h2 class="title">' . esc_html__( 'Add a role', 'admin-studio' ) . '</h2>';
		self::form( 'role_create' );
		echo '<table class="form-table" role="presentation">';
		echo '<tr><th scope="row"><label for="adminstudio-role-name">' . esc_html__( 'Name', 'admin-studio' ) . '</label></th><td><input type="text" id="adminstudio-role-name" name="name" class="regular-text" required placeholder="' . esc_attr__( 'Shop Manager', 'admin-studio' ) . '"></td></tr>';
		echo '<tr><th scope="row"><label for="adminstudio-role-copy">' . esc_html__( 'Start from', 'admin-studio' ) . '</label></th><td><select id="adminstudio-role-copy" name="copy"><option value="">' . esc_html__( 'Nothing (can only read)', 'admin-studio' ) . '</option>';
		foreach ( $roles as $slug => $role ) {
			echo '<option value="' . esc_attr( $slug ) . '">' . esc_html( sprintf( /* translators: %s: role name. */ __( 'A copy of %s', 'admin-studio' ), $role['name'] ) ) . '</option>';
		}
		echo '</select></td></tr></table>';
		submit_button( __( 'Add role', 'admin-studio' ), 'primary' );
		echo '</form>';
	}

	/**
	 * Role editor.
	 *
	 * @param string $slug Role slug.
	 * @return void
	 */
	private static function edit_role( $slug ) {
		$roles  = Role_Manager::roles();
		$role   = $roles[ $slug ];
		$locked = Role_Manager::LOCKED === $slug;
		$descs  = Capabilities::descriptions();

		echo '<p><a href="' . esc_url( self::url( 'roles' ) ) . '">&larr; ' . esc_html__( 'All roles', 'admin-studio' ) . '</a></p>';
		echo '<h2 class="title">' . esc_html( $role['name'] ) . ' <code>' . esc_html( $slug ) . '</code></h2>';

		if ( $locked ) {
			echo '<div class="notice notice-info inline adminstudio-notice"><p>' . esc_html__( 'The Administrator role is locked so the site always has someone who can manage it. You can see its capabilities but not change them.', 'admin-studio' ) . '</p></div>';
		}

		self::form( 'role_save', array( 'role' => $slug ) );

		echo '<table class="form-table" role="presentation"><tr><th scope="row"><label for="adminstudio-role-rename">' . esc_html__( 'Display name', 'admin-studio' ) . '</label></th><td><input type="text" id="adminstudio-role-rename" name="name" class="regular-text" value="' . esc_attr( wp_roles()->roles[ $slug ]['name'] ) . '"' . disabled( $locked, true, false ) . '></td></tr></table>';

		echo '<div class="adminstudio-caps-toolbar"><label for="adminstudio-cap-search" class="screen-reader-text">' . esc_html__( 'Search capabilities', 'admin-studio' ) . '</label><input type="search" id="adminstudio-cap-search" class="regular-text" placeholder="' . esc_attr__( 'Search capabilities', 'admin-studio' ) . '" data-adminstudio-cap-search></div>';

		echo '<div class="adminstudio-cap-groups">';
		foreach ( Capabilities::grouped() as $key => $group ) {
			if ( ! $group[1] ) {
				continue;
			}
			echo '<fieldset class="adminstudio-cap-group" data-adminstudio-cap-group><legend>' . esc_html( $group[0] ) . '</legend>';
			if ( ! $locked ) {
				echo '<p class="adminstudio-cap-all"><button type="button" class="button-link" data-adminstudio-cap-toggle>' . esc_html__( 'Select all', 'admin-studio' ) . '</button></p>';
			}
			foreach ( $group[1] as $cap ) {
				printf(
					'<label class="adminstudio-cap" data-cap="%1$s"><input type="checkbox" name="caps[]" value="%1$s" %2$s %3$s> <span><code>%1$s</code>%4$s</span></label>',
					esc_attr( $cap ),
					checked( in_array( $cap, $role['caps'], true ), true, false ),
					disabled( $locked, true, false ),
					isset( $descs[ $cap ] ) ? '<small>' . esc_html( $descs[ $cap ] ) . '</small>' : ''
				);
			}
			echo '</fieldset>';
		}
		echo '</div>';

		if ( ! $locked ) {
			echo '<table class="form-table" role="presentation"><tr><th scope="row"><label for="adminstudio-custom-cap">' . esc_html__( 'Add a capability', 'admin-studio' ) . '</label></th><td><input type="text" id="adminstudio-custom-cap" name="custom_cap" class="regular-text" placeholder="manage_bookings"><p class="description">' . esc_html__( 'For capabilities a plugin checks but has not given to any role yet. Lowercase letters, numbers and underscores.', 'admin-studio' ) . '</p></td></tr></table>';
			submit_button( __( 'Save role', 'admin-studio' ) );
		}

		echo '</form>';

		if ( ! $locked && ! Role_Manager::is_core( $slug ) ) {
			echo '<h2 class="title">' . esc_html__( 'Delete this role', 'admin-studio' ) . '</h2>';
			self::form( 'role_delete', array( 'role' => $slug ), true );
			echo '<p><label for="adminstudio-reassign">' . esc_html__( 'Move its users to', 'admin-studio' ) . '</label> <select id="adminstudio-reassign" name="reassign">';
			foreach ( $roles as $other => $data ) {
				if ( $other !== $slug ) {
					echo '<option value="' . esc_attr( $other ) . '"' . selected( $other, get_option( 'default_role' ), false ) . '>' . esc_html( $data['name'] ) . '</option>';
				}
			}
			echo '</select></p>';
			submit_button( __( 'Delete role', 'admin-studio' ), 'delete' );
			echo '</form>';
		}
	}

	/**
	 * Roles that rules can apply to (Administrators are never restricted).
	 *
	 * @return array Slug => name.
	 */
	private static function restrictable_roles() {
		$out = array();

		foreach ( Role_Manager::roles() as $slug => $role ) {
			if ( Role_Manager::LOCKED !== $slug ) {
				$out[ $slug ] = $role['name'];
			}
		}

		return $out;
	}

	/**
	 * Menu access per role.
	 *
	 * @return void
	 */
	private static function section_menu() {
		$roles = self::restrictable_roles();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Navigation only.
		$role  = isset( $_GET['for'] ) ? sanitize_key( wp_unslash( $_GET['for'] ) ) : (string) key( $roles );
		$role  = isset( $roles[ $role ] ) ? $role : (string) key( $roles );
		$rules = Access::rules();
		$hide  = isset( $rules['menu'][ $role ] ) ? (array) $rules['menu'][ $role ] : array();

		echo '<p class="adminstudio-intro">' . esc_html__( 'Choose which dashboard menu items each role sees. Hidden screens are also blocked if someone opens them by address. Administrators always see everything.', 'admin-studio' ) . '</p>';

		echo '<form method="get" action="' . esc_url( admin_url( 'options-general.php' ) ) . '" class="adminstudio-inline-form"><input type="hidden" name="page" value="admin-studio"><input type="hidden" name="tab" value="roles"><input type="hidden" name="section" value="menu">';
		echo '<label for="adminstudio-menu-role">' . esc_html__( 'Role', 'admin-studio' ) . '</label> <select id="adminstudio-menu-role" name="for" data-adminstudio-autosubmit>';
		foreach ( $roles as $slug => $name ) {
			echo '<option value="' . esc_attr( $slug ) . '"' . selected( $slug, $role, false ) . '>' . esc_html( $name ) . '</option>';
		}
		echo '</select> <noscript><button class="button">' . esc_html__( 'Show', 'admin-studio' ) . '</button></noscript></form>';

		self::form(
			'access_save',
			array(
				'group' => 'menu',
				'role'  => $role,
			)
		);
		echo '<input type="hidden" name="present" value="1">';
		// Group each top-level item with the items under it.
		$groups = array();
		foreach ( Access::menu_items() as $id => $item ) {
			if ( '' === $item[0] || false !== strpos( $id, 'admin-studio' ) ) {
				continue;
			}
			if ( $item[1] && $groups ) {
				$groups[ count( $groups ) - 1 ]['subs'][ $id ] = $item[0];
			} else {
				$groups[] = array(
					'id'    => $id,
					'label' => $item[0],
					'subs'  => array(),
				);
			}
		}

		echo '<div class="adminstudio-menu-tools"><label for="adminstudio-menu-filter" class="screen-reader-text">' . esc_html__( 'Find a menu item', 'admin-studio' ) . '</label><input type="search" id="adminstudio-menu-filter" placeholder="' . esc_attr__( 'Find a menu item', 'admin-studio' ) . '" data-adminstudio-menu-filter>';
		echo '<span class="description">' . esc_html(
			sprintf(
				/* translators: %d: number of menus. */
				_n( '%d menu. Untick an item to hide it.', '%d menus. Untick an item to hide it.', count( $groups ), 'admin-studio' ),
				count( $groups )
			)
		) . '</span></div>';

		echo '<div class="adminstudio-menu-groups">';
		foreach ( $groups as $group ) {
			echo '<div class="adminstudio-menu-group">';
			self::menu_check( $group['id'], $group['label'], $hide, 'adminstudio-menu-group__top' );
			if ( $group['subs'] ) {
				echo '<ul>';
				foreach ( $group['subs'] as $id => $label ) {
					echo '<li>';
					self::menu_check( $id, $label, $hide );
					echo '</li>';
				}
				echo '</ul>';
			}
			echo '</div>';
		}
		echo '</div>';
		submit_button( sprintf( /* translators: %s: role name. */ __( 'Save menu for %s', 'admin-studio' ), $roles[ $role ] ) );
		echo '</form>';
	}

	/**
	 * One menu item checkbox.
	 *
	 * @param string $id        Menu item id.
	 * @param string $label     Menu item name.
	 * @param array  $hide      Hidden item ids.
	 * @param string $css_class Label class.
	 * @return void
	 */
	private static function menu_check( $id, $label, array $hide, $css_class = '' ) {
		printf(
			'<input type="hidden" name="all[]" value="%1$s"><label%4$s><input type="checkbox" name="visible[]" value="%1$s" %2$s> %3$s</label>',
			esc_attr( $id ),
			checked( ! in_array( $id, $hide, true ), true, false ),
			esc_html( $label ),
			'' !== $css_class ? ' class="' . esc_attr( $css_class ) . '"' : ''
		);
	}

	/**
	 * Toolbar visibility and items per role.
	 *
	 * @return void
	 */
	private static function section_toolbar() {
		$roles = self::restrictable_roles();
		$rules = Access::rules();
		$nodes = Access::toolbar_nodes();

		echo '<p class="adminstudio-intro">' . esc_html__( 'Hide the top toolbar, or individual items on it, for each role. Each user\'s profile can override the site setting. Hiding it inside the dashboard needs an admin template other than "WordPress default", because the account menu then moves into the sidebar footer.', 'admin-studio' ) . '</p>';

		self::form( 'access_save', array( 'group' => 'toolbar' ) );
		echo '<input type="hidden" name="present" value="1">';
		echo '<div class="adminstudio-table-scroll" tabindex="0" role="region" aria-label="' . esc_attr__( 'Toolbar per role', 'admin-studio' ) . '"><table class="widefat striped adminstudio-matrix"><thead>';
		echo '<tr><th scope="col" rowspan="2">' . esc_html__( 'Role', 'admin-studio' ) . '</th><th scope="colgroup" colspan="2"><span class="adminstudio-matrix__group">' . esc_html__( 'Hide the whole toolbar', 'admin-studio' ) . '</span></th>';
		if ( $nodes ) {
			echo '<th scope="colgroup" colspan="' . (int) count( $nodes ) . '"><span class="adminstudio-matrix__group">' . esc_html__( 'Hide one item', 'admin-studio' ) . '</span></th>';
		}
		echo '</tr><tr><th scope="col">' . esc_html__( 'On the site', 'admin-studio' ) . '</th><th scope="col">' . esc_html__( 'In the dashboard', 'admin-studio' ) . '</th>';
		foreach ( $nodes as $label ) {
			echo '<th scope="col">' . esc_html( $label ) . '</th>';
		}
		echo '</tr></thead><tbody>';

		foreach ( $roles as $slug => $name ) {
			echo '<tr><th scope="row">' . esc_html( $name ) . '</th>';
			printf( '<td><input type="checkbox" name="toolbar_front[]" value="%s" %s aria-label="%s"></td>', esc_attr( $slug ), checked( in_array( $slug, $rules['toolbar_front'], true ), true, false ), esc_attr( $name . ': ' . __( 'Hide on the site', 'admin-studio' ) ) );
			printf( '<td><input type="checkbox" name="toolbar_admin[]" value="%s" %s aria-label="%s"></td>', esc_attr( $slug ), checked( in_array( $slug, $rules['toolbar_admin'], true ), true, false ), esc_attr( $name . ': ' . __( 'Hide in the dashboard', 'admin-studio' ) ) );
			foreach ( $nodes as $id => $label ) {
				$hidden = isset( $rules['toolbar_items'][ $slug ] ) && in_array( $id, (array) $rules['toolbar_items'][ $slug ], true );
				printf( '<td><input type="checkbox" name="toolbar_items[%1$s][]" value="%2$s" %3$s aria-label="%4$s"></td>', esc_attr( $slug ), esc_attr( $id ), checked( $hidden, true, false ), esc_attr( $name . ': ' . $label ) );
			}
			echo '</tr>';
		}

		echo '</tbody></table></div>';
		echo '<p class="description">' . esc_html__( 'Items added by other plugins appear here after you have seen them in your own toolbar once.', 'admin-studio' ) . '</p>';
		submit_button( __( 'Save toolbar', 'admin-studio' ) );
		echo '</form>';
	}

	/**
	 * Login redirect and dashboard access per role.
	 *
	 * @return void
	 */
	private static function section_access() {
		$restrictable = self::restrictable_roles();
		$rules        = Access::rules();

		echo '<p class="adminstudio-intro">' . esc_html__( 'Choose where each role goes after logging in, and keep roles that never need the dashboard out of it. To send one person somewhere else, open their profile under Users; their own choice wins over their role\'s.', 'admin-studio' ) . '</p>';

		self::form( 'access_save', array( 'group' => 'access' ) );
		echo '<input type="hidden" name="present" value="1">';
		echo '<table class="widefat striped adminstudio-matrix"><thead><tr><th>' . esc_html__( 'Role', 'admin-studio' ) . '</th><th>' . esc_html__( 'After logging in, go to', 'admin-studio' ) . '</th><th>' . esc_html__( 'Keep out of the dashboard', 'admin-studio' ) . '</th></tr></thead><tbody>';
		foreach ( Role_Manager::roles() as $slug => $role ) {
			$name = $role['name'];
			echo '<tr><th scope="row">' . esc_html( $name ) . '</th><td>';
			Redirects::field( 'redirect[' . $slug . ']', isset( $rules['redirect'][ $slug ] ) ? $rules['redirect'][ $slug ] : '', $name . ': ' . __( 'After logging in, go to', 'admin-studio' ) );
			echo '</td><td>';
			if ( isset( $restrictable[ $slug ] ) ) {
				printf( '<input type="checkbox" name="block[]" value="%1$s" %2$s aria-label="%3$s">', esc_attr( $slug ), checked( in_array( $slug, $rules['block'], true ), true, false ), esc_attr( $name . ': ' . __( 'Keep out of the dashboard', 'admin-studio' ) ) );
			} else {
				echo '<span aria-hidden="true">&mdash;</span>';
			}
			echo '</td></tr>';
		}
		echo '</tbody></table>';
		echo '<p><label><input type="checkbox" name="redirect_all" value="1"' . checked( ! empty( $rules['redirect_all'] ), true, false ) . '> ' . esc_html__( 'Always use these destinations, even when someone was on their way to a specific page', 'admin-studio' ) . '</label></p>';
		echo '<p class="description">' . esc_html__( 'Without this, someone who logs in from a link to a particular screen still goes to that screen. Kept-out roles are sent to the home page if they open the dashboard; background requests that plugins rely on keep working. Roles that can manage settings are never kept out.', 'admin-studio' ) . '</p>';
		submit_button( __( 'Save login and access', 'admin-studio' ) );
		echo '</form>';

		$people = Redirects::users();

		echo '<h2 class="title">' . esc_html__( 'People with their own destination', 'admin-studio' ) . '</h2>';
		if ( ! $people ) {
			echo '<p>' . esc_html__( 'Nobody yet. Open someone\'s profile under Users and use "After logging in" to send them somewhere of their own.', 'admin-studio' ) . '</p>';
			return;
		}

		echo '<ul class="adminstudio-list">';
		foreach ( $people as $row ) {
			printf(
				'<li><a href="%s">%s</a>: %s</li>',
				esc_url( get_edit_user_link( $row[0]->ID ) . '#adminstudio-login-redirect' ),
				esc_html( $row[0]->display_name ),
				esc_html( Redirects::describe( $row[1] ) )
			);
		}
		echo '</ul>';
	}

	/**
	 * Backups and restore.
	 *
	 * @return void
	 */
	private static function section_backups() {
		$backups = Role_Manager::backups();
		$format  = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );

		echo '<p class="adminstudio-intro">' . esc_html__( 'A copy of every role is saved before each change. The last 20 copies are kept.', 'admin-studio' ) . '</p>';

		if ( ! $backups ) {
			echo '<p>' . esc_html__( 'No backups yet. One is made automatically before your first change.', 'admin-studio' ) . '</p>';
		} else {
			echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Saved', 'admin-studio' ) . '</th><th>' . esc_html__( 'By', 'admin-studio' ) . '</th><th>' . esc_html__( 'Reason', 'admin-studio' ) . '</th><th>' . esc_html__( 'Roles', 'admin-studio' ) . '</th><th><span class="screen-reader-text">' . esc_html__( 'Actions', 'admin-studio' ) . '</span></th></tr></thead><tbody>';
			foreach ( $backups as $i => $backup ) {
				echo '<tr><td>' . esc_html( wp_date( $format, $backup['time'] ) ) . '</td><td>' . esc_html( $backup['user'] ) . '</td><td>' . esc_html( $backup['reason'] ) . '</td><td>' . esc_html( number_format_i18n( count( (array) $backup['roles'] ) ) ) . '</td><td>';
				self::form( 'role_restore', array( 'index' => $i ), true );
				submit_button( __( 'Restore', 'admin-studio' ), 'small', 'submit', false );
				echo '</form></td></tr>';
			}
			echo '</tbody></table>';
		}

		echo '<h2 class="title">' . esc_html__( 'Restore WordPress default roles', 'admin-studio' ) . '</h2>';
		echo '<p>' . esc_html__( 'Resets Editor, Author, Contributor and Subscriber to WordPress\'s original capabilities. Custom roles and the Administrator role are kept.', 'admin-studio' ) . '</p>';
		self::form( 'role_defaults', array(), true );
		submit_button( __( 'Restore default roles', 'admin-studio' ), 'secondary' );
		echo '</form>';
	}

	/**
	 * Activity log.
	 *
	 * @return void
	 */
	private static function section_log() {
		$entries = Audit::entries();
		$labels  = Audit::labels();
		$format  = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );

		echo '<p class="adminstudio-intro">' . esc_html__( 'Who changed which role, permission, menu or access rule, and when. The last 500 changes are kept.', 'admin-studio' ) . '</p>';

		if ( ! $entries ) {
			echo '<p>' . esc_html__( 'No changes recorded yet.', 'admin-studio' ) . '</p>';
			return;
		}

		self::form( 'audit_csv' );
		submit_button( __( 'Download as CSV', 'admin-studio' ), 'secondary', 'submit', false );
		echo '</form>';

		echo '<table class="widefat striped adminstudio-log"><thead><tr><th>' . esc_html__( 'When', 'admin-studio' ) . '</th><th>' . esc_html__( 'Who', 'admin-studio' ) . '</th><th>' . esc_html__( 'What', 'admin-studio' ) . '</th><th>' . esc_html__( 'Details', 'admin-studio' ) . '</th></tr></thead><tbody>';
		foreach ( array_slice( $entries, 0, 200 ) as $entry ) {
			echo '<tr><td>' . esc_html( wp_date( $format, $entry['time'] ) ) . '</td><td>' . esc_html( $entry['user'] ) . '</td><td>' . esc_html( isset( $labels[ $entry['action'] ] ) ? $labels[ $entry['action'] ] : $entry['action'] ) . '</td><td>' . esc_html( $entry['summary'] ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}

	// ------------------------------------------------------------------
	// Handlers.
	// ------------------------------------------------------------------

	/**
	 * Creates a role.
	 *
	 * @return void
	 */
	public static function handle_role_create() {
		self::guard( 'adminstudio_role_create' );

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Checked in guard().
		$name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$copy = isset( $_POST['copy'] ) ? sanitize_key( wp_unslash( $_POST['copy'] ) ) : '';
		// phpcs:enable

		$result = Role_Manager::create( $name, '', $copy );

		if ( is_wp_error( $result ) ) {
			self::back( 'roles', 'error:' . $result->get_error_message() );
		}

		self::back( 'roles', 'role_saved', array( 'role' => $result ) );
	}

	/**
	 * Saves a role's name and capabilities.
	 *
	 * @return void
	 */
	public static function handle_role_save() {
		self::guard( 'adminstudio_role_save' );

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Checked in guard().
		$slug   = isset( $_POST['role'] ) ? sanitize_key( wp_unslash( $_POST['role'] ) ) : '';
		$name   = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$caps   = isset( $_POST['caps'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['caps'] ) ) : array();
		$custom = isset( $_POST['custom_cap'] ) ? sanitize_key( wp_unslash( $_POST['custom_cap'] ) ) : '';
		// phpcs:enable

		if ( '' !== $custom ) {
			$caps[] = $custom;
		}

		$renamed = Role_Manager::rename( $slug, $name );

		if ( is_wp_error( $renamed ) ) {
			self::back( 'roles', 'error:' . $renamed->get_error_message(), array( 'role' => $slug ) );
		}

		$saved = Role_Manager::set_caps( $slug, $caps );

		if ( is_wp_error( $saved ) ) {
			self::back( 'roles', 'error:' . $saved->get_error_message(), array( 'role' => $slug ) );
		}

		self::back( 'roles', 'role_saved', array( 'role' => $slug ) );
	}

	/**
	 * Deletes a role.
	 *
	 * @return void
	 */
	public static function handle_role_delete() {
		self::guard( 'adminstudio_role_delete' );

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Checked in guard().
		$slug     = isset( $_POST['role'] ) ? sanitize_key( wp_unslash( $_POST['role'] ) ) : '';
		$reassign = isset( $_POST['reassign'] ) ? sanitize_key( wp_unslash( $_POST['reassign'] ) ) : '';
		// phpcs:enable

		$result = Role_Manager::delete( $slug, $reassign );

		if ( is_wp_error( $result ) ) {
			self::back( 'roles', 'error:' . $result->get_error_message(), array( 'role' => $slug ) );
		}

		self::back( 'roles', 'role_deleted' );
	}

	/**
	 * Restores a backup.
	 *
	 * @return void
	 */
	public static function handle_role_restore() {
		self::guard( 'adminstudio_role_restore' );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Checked in guard().
		$index  = isset( $_POST['index'] ) ? absint( $_POST['index'] ) : -1;
		$result = Role_Manager::restore( $index );

		self::back( 'backups', is_wp_error( $result ) ? 'error:' . $result->get_error_message() : 'roles_restored' );
	}

	/**
	 * Restores WordPress's default roles.
	 *
	 * @return void
	 */
	public static function handle_role_defaults() {
		self::guard( 'adminstudio_role_defaults' );
		Role_Manager::restore_defaults();
		self::back( 'backups', 'roles_restored' );
	}

	/**
	 * Saves menu, toolbar or access rules.
	 *
	 * @return void
	 */
	public static function handle_access_save() {
		self::guard( 'adminstudio_access_save' );

		// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce checked in guard(); values are cleaned by Access::save().
		$group = isset( $_POST['group'] ) ? sanitize_key( wp_unslash( $_POST['group'] ) ) : '';
		$rules = Access::rules();

		if ( 'menu' === $group ) {
			$role    = isset( $_POST['role'] ) ? sanitize_key( wp_unslash( $_POST['role'] ) ) : '';
			$visible = isset( $_POST['visible'] ) ? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['visible'] ) ) : array();
			$all     = isset( $_POST['all'] ) ? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['all'] ) ) : array();
			$menu    = $rules['menu'];

			$menu[ $role ] = array_values( array_diff( $all, $visible ) );
			Access::save( 'menu', $menu );

			/* translators: 1: role, 2: number of hidden items. */
			Audit::log( 'menu_access', sprintf( __( '%1$s: %2$d menu items hidden.', 'admin-studio' ), $role, count( $menu[ $role ] ) ) );
			self::back( 'menu', 'saved', array( 'for' => $role ) );
		}

		if ( 'toolbar' === $group ) {
			Access::save( 'toolbar_front', isset( $_POST['toolbar_front'] ) ? wp_unslash( $_POST['toolbar_front'] ) : array() );
			Access::save( 'toolbar_admin', isset( $_POST['toolbar_admin'] ) ? wp_unslash( $_POST['toolbar_admin'] ) : array() );
			Access::save( 'toolbar_items', isset( $_POST['toolbar_items'] ) ? wp_unslash( $_POST['toolbar_items'] ) : array() );

			Audit::log( 'toolbar', __( 'Toolbar rules updated.', 'admin-studio' ) );
			self::back( 'toolbar', 'saved' );
		}

		if ( 'access' === $group ) {
			Access::save( 'redirect', isset( $_POST['redirect'] ) ? wp_unslash( $_POST['redirect'] ) : array() );
			Access::save( 'redirect_all', ! empty( $_POST['redirect_all'] ) );
			Access::save( 'block', isset( $_POST['block'] ) ? wp_unslash( $_POST['block'] ) : array() );

			Audit::log( 'access', __( 'Login redirects and dashboard access updated.', 'admin-studio' ) );
			self::back( 'access', 'saved' );
		}
		// phpcs:enable

		self::back( 'roles', 'saved' );
	}

	/**
	 * Downloads the activity log.
	 *
	 * @return void
	 */
	public static function handle_audit_csv() {
		self::guard( 'adminstudio_audit_csv' );
		Audit::download_csv();
		exit;
	}
}

<?php
/**
 * Creates, edits and deletes roles, with backups and lock-out protection.
 *
 * All changes go through WordPress's own role API, so other plugins see them
 * straight away.
 *
 * @package AdminStudio
 */

namespace AdminStudio\Modules\Roles;

defined( 'ABSPATH' ) || exit;

/**
 * Role operations.
 */
class Role_Manager {

	const BACKUPS     = 'adminstudio_role_backups';
	const MAX_BACKUPS = 20;
	const LOCKED      = 'administrator';

	/**
	 * Capabilities the current user must keep, so they cannot lock themselves out.
	 *
	 * @var string[]
	 */
	const KEEP = array( 'manage_options', 'promote_users' );

	/**
	 * Roles with display names and user counts.
	 *
	 * @return array Slug => [ name, count, caps ].
	 */
	public static function roles() {
		$counts = count_users();
		$out    = array();

		foreach ( wp_roles()->roles as $slug => $role ) {
			$out[ $slug ] = array(
				'name'  => translate_user_role( $role['name'] ),
				'count' => isset( $counts['avail_roles'][ $slug ] ) ? (int) $counts['avail_roles'][ $slug ] : 0,
				'caps'  => array_keys( array_filter( (array) $role['capabilities'] ) ),
			);
		}

		return $out;
	}

	/**
	 * Whether a role is one of WordPress's own.
	 *
	 * @param string $slug Role slug.
	 * @return bool
	 */
	public static function is_core( $slug ) {
		return in_array( $slug, array( 'administrator', 'editor', 'author', 'contributor', 'subscriber' ), true );
	}

	/**
	 * Saves a copy of all roles before a change.
	 *
	 * @param string $reason Why the backup was taken.
	 * @return void
	 */
	public static function backup( $reason ) {
		$backups = self::backups();

		array_unshift(
			$backups,
			array(
				'time'   => time(),
				'user'   => wp_get_current_user()->user_login,
				'reason' => sanitize_text_field( $reason ),
				'roles'  => wp_roles()->roles,
			)
		);

		update_option( self::BACKUPS, array_slice( $backups, 0, self::MAX_BACKUPS ), false );
	}

	/**
	 * Stored backups, newest first.
	 *
	 * @return array
	 */
	public static function backups() {
		$backups = get_option( self::BACKUPS, array() );

		return is_array( $backups ) ? $backups : array();
	}

	/**
	 * Whether the current user would keep the capabilities they need after a
	 * role is given a new set of capabilities (or removed, with an empty set).
	 *
	 * @param string     $slug Role being changed.
	 * @param array|null $caps New capability list, or null when the role is deleted.
	 * @return bool
	 */
	public static function keeps_access( $slug, $caps ) {
		$user = wp_get_current_user();

		if ( ! in_array( $slug, (array) $user->roles, true ) ) {
			return true;
		}

		$have = array();

		foreach ( (array) $user->roles as $role ) {
			if ( $role === $slug ) {
				$have = array_merge( $have, (array) $caps );
				continue;
			}

			$object = get_role( $role );
			if ( $object ) {
				$have = array_merge( $have, array_keys( array_filter( $object->capabilities ) ) );
			}
		}

		// Per-user grants and denials still apply on top of roles.
		foreach ( (array) $user->caps as $cap => $grant ) {
			if ( wp_roles()->is_role( $cap ) ) {
				continue;
			}
			if ( $grant ) {
				$have[] = $cap;
			} else {
				$have = array_diff( $have, array( $cap ) );
			}
		}

		return ! array_diff( self::KEEP, $have );
	}

	/**
	 * Creates a role, optionally copying another role's capabilities.
	 *
	 * @param string $name Display name.
	 * @param string $slug Slug; generated from the name when empty.
	 * @param string $copy Role to copy capabilities from, or ''.
	 * @return string|\WP_Error New slug.
	 */
	public static function create( $name, $slug = '', $copy = '' ) {
		$name = sanitize_text_field( $name );
		$slug = sanitize_key( '' !== $slug ? $slug : str_replace( '-', '_', sanitize_title( $name ) ) );

		if ( '' === $name || '' === $slug ) {
			return new \WP_Error( 'adminstudio_role_name', __( 'Give the role a name.', 'admin-studio' ) );
		}

		if ( wp_roles()->is_role( $slug ) ) {
			return new \WP_Error( 'adminstudio_role_exists', __( 'A role with that name already exists.', 'admin-studio' ) );
		}

		$caps = array( 'read' => true );

		if ( '' !== $copy && get_role( $copy ) ) {
			$caps = get_role( $copy )->capabilities;
		}

		self::backup( 'Before creating ' . $name );
		add_role( $slug, $name, $caps );

		Audit::log(
			'role_created',
			'' !== $copy
				/* translators: 1: new role, 2: copied role. */
				? sprintf( __( 'Created "%1$s" as a copy of "%2$s".', 'admin-studio' ), $name, $copy )
				/* translators: %s: role name. */
				: sprintf( __( 'Created "%s".', 'admin-studio' ), $name )
		);

		return $slug;
	}

	/**
	 * Renames a role's display name.
	 *
	 * @param string $slug Role slug.
	 * @param string $name New display name.
	 * @return true|\WP_Error
	 */
	public static function rename( $slug, $name ) {
		$roles = wp_roles();
		$name  = sanitize_text_field( $name );

		if ( '' === $name || ! $roles->is_role( $slug ) ) {
			return new \WP_Error( 'adminstudio_role_name', __( 'Give the role a name.', 'admin-studio' ) );
		}

		$old = $roles->roles[ $slug ]['name'];

		if ( $old === $name ) {
			return true;
		}

		self::backup( 'Before renaming ' . $old );

		$roles->roles[ $slug ]['name'] = $name;
		$roles->role_names[ $slug ]    = $name;
		update_option( $roles->role_key, $roles->roles );

		/* translators: 1: old name, 2: new name. */
		Audit::log( 'role_renamed', sprintf( __( 'Renamed "%1$s" to "%2$s".', 'admin-studio' ), $old, $name ) );

		return true;
	}

	/**
	 * Replaces a role's capabilities.
	 *
	 * @param string   $slug Role slug.
	 * @param string[] $caps Capabilities to grant; all others are removed.
	 * @return true|\WP_Error
	 */
	public static function set_caps( $slug, array $caps ) {
		$role = get_role( $slug );

		if ( ! $role ) {
			return new \WP_Error( 'adminstudio_role_missing', __( 'That role no longer exists.', 'admin-studio' ) );
		}

		if ( self::LOCKED === $slug ) {
			return new \WP_Error( 'adminstudio_role_locked', __( 'The Administrator role is locked so the site always has someone who can manage it.', 'admin-studio' ) );
		}

		$caps = array_values( array_unique( array_filter( array_map( 'sanitize_key', $caps ) ) ) );

		if ( ! self::keeps_access( $slug, $caps ) ) {
			return new \WP_Error( 'adminstudio_self_lockout', __( 'That change would remove your own ability to manage settings or user roles, so it was not saved.', 'admin-studio' ) );
		}

		$current = array_keys( array_filter( $role->capabilities ) );
		$added   = array_diff( $caps, $current );
		$removed = array_diff( $current, $caps );

		if ( ! $added && ! $removed ) {
			return true;
		}

		self::backup( 'Before changing ' . $slug );

		foreach ( $removed as $cap ) {
			$role->remove_cap( $cap );
		}

		foreach ( $added as $cap ) {
			$role->add_cap( $cap );
		}

		Audit::log(
			'role_caps',
			sprintf(
				/* translators: 1: role, 2: added capabilities, 3: removed capabilities. */
				__( '%1$s: added %2$s; removed %3$s.', 'admin-studio' ),
				$slug,
				$added ? implode( ', ', $added ) : '-',
				$removed ? implode( ', ', $removed ) : '-'
			)
		);

		return true;
	}

	/**
	 * Deletes a custom role, moving its users to another role first.
	 *
	 * @param string $slug     Role to delete.
	 * @param string $reassign Role that its users move to.
	 * @return true|\WP_Error
	 */
	public static function delete( $slug, $reassign ) {
		if ( self::is_core( $slug ) ) {
			return new \WP_Error( 'adminstudio_role_core', __( 'WordPress\'s own roles cannot be deleted.', 'admin-studio' ) );
		}

		if ( ! get_role( $slug ) || ! get_role( $reassign ) || $slug === $reassign ) {
			return new \WP_Error( 'adminstudio_role_missing', __( 'Choose a different role for its users.', 'admin-studio' ) );
		}

		if ( ! self::keeps_access( $slug, null ) ) {
			return new \WP_Error( 'adminstudio_self_lockout', __( 'You need this role to manage the site, so it was not deleted.', 'admin-studio' ) );
		}

		self::backup( 'Before deleting ' . $slug );

		foreach ( get_users( array( 'role' => $slug ) ) as $user ) {
			$user->remove_role( $slug );

			if ( ! $user->roles ) {
				$user->add_role( $reassign );
			}
		}

		$name = wp_roles()->roles[ $slug ]['name'];
		remove_role( $slug );

		/* translators: 1: deleted role, 2: role its users moved to. */
		Audit::log( 'role_deleted', sprintf( __( 'Deleted "%1$s"; its users moved to "%2$s".', 'admin-studio' ), $name, $reassign ) );

		return true;
	}

	/**
	 * Restores all roles from a backup.
	 *
	 * @param int $index Backup index.
	 * @return true|\WP_Error
	 */
	public static function restore( $index ) {
		$backups = self::backups();

		if ( ! isset( $backups[ $index ]['roles'] ) || ! is_array( $backups[ $index ]['roles'] ) ) {
			return new \WP_Error( 'adminstudio_backup_missing', __( 'That backup no longer exists.', 'admin-studio' ) );
		}

		$roles = $backups[ $index ]['roles'];

		// Never restore a state that would lock the current user out.
		foreach ( (array) wp_get_current_user()->roles as $role ) {
			if ( ! isset( $roles[ $role ] ) || ! self::keeps_access( $role, array_keys( array_filter( (array) $roles[ $role ]['capabilities'] ) ) ) ) {
				return new \WP_Error( 'adminstudio_self_lockout', __( 'That backup would remove your own access, so it was not restored.', 'admin-studio' ) );
			}
		}

		self::backup( 'Before restoring a backup' );

		$wp_roles = wp_roles();
		update_option( $wp_roles->role_key, $roles );
		$wp_roles->for_site();

		/* translators: %s: backup date. */
		Audit::log( 'roles_restored', sprintf( __( 'Restored the backup from %s.', 'admin-studio' ), wp_date( 'Y-m-d H:i', $backups[ $index ]['time'] ) ) );

		return true;
	}

	/**
	 * Resets WordPress's five core roles to their original capabilities.
	 * Custom roles are kept.
	 *
	 * @return true
	 */
	public static function restore_defaults() {
		require_once ABSPATH . 'wp-admin/includes/schema.php';

		self::backup( 'Before restoring default roles' );

		foreach ( array( 'editor', 'author', 'contributor', 'subscriber' ) as $slug ) {
			remove_role( $slug );
		}

		// Administrator is left in place so the current user never loses access.
		populate_roles();

		Audit::log( 'roles_restored', __( 'Restored WordPress\'s default roles.', 'admin-studio' ) );

		return true;
	}
}

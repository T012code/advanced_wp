<?php
/**
 * Per-user roles and permissions on the profile screen.
 *
 * Extra roles use WordPress's multiple-role support. Granted and denied
 * capabilities use WordPress's own per-user capabilities, where a denied
 * capability overrides what the user's roles allow.
 *
 * @package Adminora
 */

namespace Adminora\Modules\Roles;

defined( 'ABSPATH' ) || exit;

/**
 * Profile section for user permissions.
 */
class User_Permissions {

	const NONCE = 'adminora_user_perms';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'edit_user_profile', array( __CLASS__, 'render' ), 20 );
		add_action( 'show_user_profile', array( __CLASS__, 'render' ), 20 );
		// Runs after WordPress has set the primary role, so extra roles are kept.
		add_action( 'profile_update', array( __CLASS__, 'save' ), 20 );
	}

	/**
	 * Whether the current user can change this user's permissions.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	private static function can_edit( $user_id ) {
		return current_user_can( 'promote_users' ) && current_user_can( 'edit_user', $user_id );
	}

	/**
	 * Per-user capability overrides (grants and denials).
	 *
	 * @param \WP_User $user User.
	 * @return array [ granted, denied ].
	 */
	public static function overrides( $user ) {
		$granted = array();
		$denied  = array();

		foreach ( (array) $user->caps as $cap => $value ) {
			if ( wp_roles()->is_role( $cap ) ) {
				continue;
			}
			if ( $value ) {
				$granted[] = $cap;
			} else {
				$denied[] = $cap;
			}
		}

		return array( $granted, $denied );
	}

	/**
	 * Prints the section.
	 *
	 * @param \WP_User $user User being edited.
	 * @return void
	 */
	public static function render( $user ) {
		if ( ! self::can_edit( $user->ID ) ) {
			return;
		}

		$roles   = Role_Manager::roles();
		$primary = $user->roles ? reset( $user->roles ) : '';
		$extra   = array_slice( array_values( (array) $user->roles ), 1 );
		$caps    = Capabilities::all();
		$choice  = get_user_meta( $user->ID, Access::USER_TOOLBAR, true );

		list( $granted, $denied ) = self::overrides( $user );
		?>
		<h2 id="adminora-permissions"><?php esc_html_e( 'Roles and permissions', 'adminora' ); ?></h2>
		<?php wp_nonce_field( self::NONCE . '_' . $user->ID, '_adminora_perms', false ); ?>
		<input type="hidden" name="adminora_perms_present" value="1">
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Additional roles', 'adminora' ); ?></th>
				<td>
					<fieldset>
						<legend class="screen-reader-text"><?php esc_html_e( 'Additional roles', 'adminora' ); ?></legend>
						<?php foreach ( $roles as $slug => $role ) : ?>
							<?php
							if ( $slug === $primary ) {
								continue;
							}
							?>
							<label class="adminora-check">
								<input type="checkbox" name="adminora_extra_roles[]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, $extra, true ) ); ?>>
								<?php echo esc_html( $role['name'] ); ?>
							</label>
						<?php endforeach; ?>
					</fieldset>
					<p class="description"><?php esc_html_e( 'The main role is set with the Role field above. The user gets the capabilities of every role they have.', 'adminora' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="adminora-grant"><?php esc_html_e( 'Extra capabilities', 'adminora' ); ?></label></th>
				<td>
					<select id="adminora-grant" name="adminora_grant[]" multiple size="8" class="adminora-caps-select">
						<?php foreach ( $caps as $cap ) : ?>
							<option value="<?php echo esc_attr( $cap ); ?>" <?php selected( in_array( $cap, $granted, true ) ); ?>><?php echo esc_html( $cap ); ?></option>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php esc_html_e( 'Given to this user on top of their roles. Hold Ctrl (Cmd on Mac) to pick several.', 'adminora' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="adminora-deny"><?php esc_html_e( 'Denied capabilities', 'adminora' ); ?></label></th>
				<td>
					<select id="adminora-deny" name="adminora_deny[]" multiple size="8" class="adminora-caps-select">
						<?php foreach ( $caps as $cap ) : ?>
							<option value="<?php echo esc_attr( $cap ); ?>" <?php selected( in_array( $cap, $denied, true ) ); ?>><?php echo esc_html( $cap ); ?></option>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php esc_html_e( 'Taken away from this user even if one of their roles allows it. Denials win over everything else.', 'adminora' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="adminora-toolbar-choice"><?php esc_html_e( 'Toolbar on the site', 'adminora' ); ?></label></th>
				<td>
					<select id="adminora-toolbar-choice" name="adminora_toolbar">
						<option value="" <?php selected( $choice, '' ); ?>><?php esc_html_e( 'Follow the role setting', 'adminora' ); ?></option>
						<option value="show" <?php selected( $choice, 'show' ); ?>><?php esc_html_e( 'Always show', 'adminora' ); ?></option>
						<option value="hide" <?php selected( $choice, 'hide' ); ?>><?php esc_html_e( 'Always hide', 'adminora' ); ?></option>
					</select>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Saves the section.
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	public static function save( $user_id ) {
		if ( ! isset( $_POST['_adminora_perms'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_adminora_perms'] ) ), self::NONCE . '_' . $user_id ) ) {
			return;
		}

		if ( ! current_user_can( 'promote_users' ) || ! current_user_can( 'edit_user', $user_id ) || empty( $_POST['adminora_perms_present'] ) ) {
			return;
		}

		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return;
		}

		$extra  = isset( $_POST['adminora_extra_roles'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['adminora_extra_roles'] ) ) : array();
		$grant  = isset( $_POST['adminora_grant'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['adminora_grant'] ) ) : array();
		$deny   = isset( $_POST['adminora_deny'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['adminora_deny'] ) ) : array();
		$choice = isset( $_POST['adminora_toolbar'] ) ? sanitize_key( wp_unslash( $_POST['adminora_toolbar'] ) ) : '';

		$valid_roles = array_keys( wp_roles()->roles );
		$valid_caps  = Capabilities::all();
		$extra       = array_intersect( $extra, $valid_roles );
		$grant       = array_intersect( $grant, $valid_caps );
		$deny        = array_diff( array_intersect( $deny, $valid_caps ), $grant );
		$primary     = $user->roles ? reset( $user->roles ) : '';

		// Never let administrators deny themselves the access they need.
		if ( get_current_user_id() === $user_id ) {
			$deny = array_diff( $deny, Role_Manager::KEEP );
		}

		// Only administrators can hand out the Administrator role.
		if ( ! current_user_can( 'manage_options' ) ) {
			$extra = array_diff( $extra, array( 'administrator' ) );
		}

		$before = wp_json_encode( array( $user->roles, self::overrides( $user ) ) );

		foreach ( array_diff( (array) $user->roles, array( $primary ) ) as $role ) {
			if ( ! in_array( $role, $extra, true ) ) {
				$user->remove_role( $role );
			}
		}

		foreach ( $extra as $role ) {
			if ( $role !== $primary && ! in_array( $role, (array) $user->roles, true ) ) {
				$user->add_role( $role );
			}
		}

		list( $granted, $denied ) = self::overrides( $user );

		foreach ( array_merge( $granted, $denied ) as $cap ) {
			$user->remove_cap( $cap );
		}

		foreach ( $grant as $cap ) {
			$user->add_cap( $cap, true );
		}

		foreach ( $deny as $cap ) {
			$user->add_cap( $cap, false );
		}

		if ( in_array( $choice, array( 'show', 'hide' ), true ) ) {
			update_user_meta( $user_id, Access::USER_TOOLBAR, $choice );
		} else {
			delete_user_meta( $user_id, Access::USER_TOOLBAR );
		}

		$user = get_userdata( $user_id );

		if ( wp_json_encode( array( $user->roles, self::overrides( $user ) ) ) !== $before ) {
			Audit::log(
				'user_perms',
				sprintf(
					/* translators: 1: username, 2: roles, 3: granted capabilities, 4: denied capabilities. */
					__( '%1$s: roles %2$s; extra %3$s; denied %4$s.', 'adminora' ),
					$user->user_login,
					implode( ', ', (array) $user->roles ),
					$grant ? implode( ', ', $grant ) : '-',
					$deny ? implode( ', ', $deny ) : '-'
				)
			);
		}
	}
}

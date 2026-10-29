<?php
/**
 * Activity log for role, permission, menu and access changes.
 *
 * @package Adminora
 */

namespace Adminora\Modules\Roles;

defined( 'ABSPATH' ) || exit;

/**
 * Stores and reads audit entries.
 */
class Audit {

	const OPTION = 'adminora_audit';
	const SIZE   = 500;

	/**
	 * Records a change.
	 *
	 * @param string $action  Short action key, such as role_created.
	 * @param string $summary Human summary.
	 * @return void
	 */
	public static function log( $action, $summary ) {
		$user = wp_get_current_user();
		$log  = self::entries();

		array_unshift(
			$log,
			array(
				'time'    => time(),
				'user'    => $user->exists() ? $user->user_login : '',
				'action'  => sanitize_key( $action ),
				'summary' => sanitize_text_field( $summary ),
			)
		);

		update_option( self::OPTION, array_slice( $log, 0, self::SIZE ), false );
	}

	/**
	 * Entries, newest first.
	 *
	 * @return array
	 */
	public static function entries() {
		$log = get_option( self::OPTION, array() );

		return is_array( $log ) ? $log : array();
	}

	/**
	 * Labels for action keys.
	 *
	 * @return array
	 */
	public static function labels() {
		return array(
			'role_created'   => __( 'Role created', 'adminora' ),
			'role_renamed'   => __( 'Role renamed', 'adminora' ),
			'role_deleted'   => __( 'Role deleted', 'adminora' ),
			'role_caps'      => __( 'Capabilities changed', 'adminora' ),
			'roles_restored' => __( 'Roles restored', 'adminora' ),
			'user_perms'     => __( 'User permissions changed', 'adminora' ),
			'menu_access'    => __( 'Menu access changed', 'adminora' ),
			'access'         => __( 'Login and access changed', 'adminora' ),
			'toolbar'        => __( 'Toolbar changed', 'adminora' ),
			'look'           => __( 'Dashboard look changed', 'adminora' ),
			'widgets'        => __( 'Dashboard widgets changed', 'adminora' ),
			'import'         => __( 'Settings imported', 'adminora' ),
		);
	}

	/**
	 * Sends the log as a CSV download.
	 *
	 * @return void
	 */
	public static function download_csv() {
		$labels = self::labels();

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=adminora-activity-' . gmdate( 'Y-m-d' ) . '.csv' );

		$rows   = array( array( 'time', 'user', 'action', 'summary' ) );
		$format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );

		foreach ( self::entries() as $entry ) {
			$rows[] = array(
				wp_date( $format, $entry['time'] ),
				$entry['user'],
				isset( $labels[ $entry['action'] ] ) ? $labels[ $entry['action'] ] : $entry['action'],
				$entry['summary'],
			);
		}

		foreach ( $rows as $row ) {
			// Quote every field and neutralise spreadsheet formulas.
			$cells = array_map(
				static function ( $cell ) {
					$cell = (string) $cell;
					if ( preg_match( '/^[=+\-@]/', $cell ) ) {
						$cell = "'" . $cell;
					}
					return '"' . str_replace( '"', '""', $cell ) . '"';
				},
				$row
			);

			echo implode( ',', $cells ) . "\r\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV download, every field quoted above.
		}
	}
}

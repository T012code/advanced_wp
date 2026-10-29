<?php
/**
 * Roles and permissions module: role editor, per-user permissions, menu
 * access, login redirect, dashboard access and toolbar control.
 *
 * @package Adminora
 */

namespace Adminora\Modules\Roles;

defined( 'ABSPATH' ) || exit;

/**
 * Starts the roles and permissions features.
 */
class Roles {

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		Access::init();

		if ( is_admin() ) {
			User_Permissions::init();
			Redirects::init();
		}
	}
}

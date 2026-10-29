<?php
/**
 * Login protection: custom login URL, attempt limits, honeypot and 2FA.
 *
 * @package Adminora
 */

namespace Adminora\Modules\Security;

use Adminora\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Starts the security features that are switched on.
 */
class Security {

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		if ( Settings::get( 'security.login_url' ) ) {
			Login_Url::init();
		}

		if ( Settings::get( 'security.admin_url' ) ) {
			Admin_Url::init();
		}

		if ( Settings::get( 'security.limit' ) ) {
			Attempts::init();
		}

		if ( Settings::get( 'security.honeypot' ) ) {
			Honeypot::init();
		}

		if ( Settings::get( 'security.twofa' ) ) {
			Two_Factor::init();
		}
	}
}

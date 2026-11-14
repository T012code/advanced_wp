<?php
/**
 * Plugin Name:       Layò Dashboard Toolkit
 * Description:       Rebrand the login page, system emails, error pages, browser tab details and admin dashboard, using your theme's colours by default.
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            Láyò
 * Author URI:        https://www.layothedeveloper.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       layo-dashboard-toolkit
 * Domain Path:       /languages
 *
 * @package LayoDT
 */

defined( 'ABSPATH' ) || exit;

define( 'LAYODT_VERSION', '1.0.0' );
define( 'LAYODT_FILE', __FILE__ );
define( 'LAYODT_DIR', plugin_dir_path( __FILE__ ) );
define( 'LAYODT_URL', plugin_dir_url( __FILE__ ) );

/**
 * Maps LayoDT\Sub\Class_Name to includes/sub/class-class-name.php.
 */
spl_autoload_register(
	static function ( $class_name ) {
		if ( 0 !== strpos( $class_name, 'LayoDT\\' ) ) {
			return;
		}

		$parts = explode( '\\', substr( $class_name, strlen( 'LayoDT\\' ) ) );
		$name  = array_pop( $parts );
		$path  = '';

		foreach ( $parts as $part ) {
			$path .= strtolower( str_replace( '_', '-', $part ) ) . '/';
		}

		$file = LAYODT_DIR . 'includes/' . $path . 'class-' . strtolower( str_replace( '_', '-', $name ) ) . '.php';

		if ( is_readable( $file ) ) {
			require $file;
		}
	}
);

register_activation_hook( __FILE__, array( 'LayoDT\\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'LayoDT\\Plugin', 'deactivate' ) );

LayoDT\Plugin::instance();

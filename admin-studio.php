<?php
/**
 * Plugin Name:       Admin Studio
 * Description:       Rebrand the login page, system emails, error pages, browser tab details and admin dashboard, using your theme's colours by default.
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      7.4
 * Author:            Láyò
 * Author URI:        https://www.layothedeveloper.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       admin-studio
 * Domain Path:       /languages
 *
 * @package AdminStudio
 */

defined( 'ABSPATH' ) || exit;

define( 'ADMINSTUDIO_VERSION', '1.0.0' );
define( 'ADMINSTUDIO_FILE', __FILE__ );
define( 'ADMINSTUDIO_DIR', plugin_dir_path( __FILE__ ) );
define( 'ADMINSTUDIO_URL', plugin_dir_url( __FILE__ ) );

/**
 * Maps AdminStudio\Sub\Class_Name to includes/sub/class-class-name.php.
 */
spl_autoload_register(
	static function ( $class_name ) {
		if ( 0 !== strpos( $class_name, 'AdminStudio\\' ) ) {
			return;
		}

		$parts = explode( '\\', substr( $class_name, strlen( 'AdminStudio\\' ) ) );
		$name  = array_pop( $parts );
		$path  = '';

		foreach ( $parts as $part ) {
			$path .= strtolower( str_replace( '_', '-', $part ) ) . '/';
		}

		$file = ADMINSTUDIO_DIR . 'includes/' . $path . 'class-' . strtolower( str_replace( '_', '-', $name ) ) . '.php';

		if ( is_readable( $file ) ) {
			require $file;
		}
	}
);

register_activation_hook( __FILE__, array( 'AdminStudio\\Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'AdminStudio\\Plugin', 'deactivate' ) );

AdminStudio\Plugin::instance();

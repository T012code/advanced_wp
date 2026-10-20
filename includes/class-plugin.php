<?php
/**
 * Plugin bootstrap and module registry.
 *
 * @package AdvancedWP
 */

namespace AdvancedWP;

defined( 'ABSPATH' ) || exit;

/**
 * Wires up modules, the Customizer, the settings page and save side effects.
 */
class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Returns the shared instance.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Module key => class name.
	 *
	 * @return array
	 */
	public static function modules() {
		return array(
			'login'          => Modules\Login\Login::class,
			'emails'         => Modules\Emails\Emails::class,
			'error_pages'    => Modules\Error_Pages\Error_Pages::class,
			'tab_meta'       => Modules\Tab_Meta\Tab_Meta::class,
			'admin_branding' => Modules\Admin_Branding\Admin_Branding::class,
			'security'       => Modules\Security\Security::class,
			'maintenance'    => Modules\Maintenance\Maintenance::class,
		);
	}

	/**
	 * Registers hooks. Modules start immediately because some, like the
	 * custom login URL, must act before `plugins_loaded` finishes.
	 */
	private function __construct() {
		foreach ( self::modules() as $key => $class ) {
			if ( Settings::module_on( $key ) && class_exists( $class ) ) {
				$class::init();
			}
		}

		Preview::init();
		Customizer\Customizer::init();

		if ( is_admin() ) {
			Admin\Admin::init();
		}

		add_action( 'add_option_' . Settings::OPTION, array( $this, 'settings_saved' ) );
		add_action( 'update_option_' . Settings::OPTION, array( $this, 'settings_saved' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( ADVWP_FILE ), array( $this, 'action_links' ) );
	}

	/**
	 * Runs side effects after any save, from the Customizer or the settings page.
	 *
	 * Deferred to `shutdown` so a single request that saves several times only
	 * regenerates files once, using the final values.
	 *
	 * @return void
	 */
	public function settings_saved() {
		if ( ! has_action( 'shutdown', array( __CLASS__, 'apply_saved_settings' ) ) ) {
			add_action( 'shutdown', array( __CLASS__, 'apply_saved_settings' ) );
		}
	}

	/**
	 * Downloads chosen fonts and rewrites drop-in files.
	 *
	 * @return void
	 */
	public static function apply_saved_settings() {
		Fonts::sync();
		Dropins::sync();
	}

	/**
	 * Adds Settings and Customize links on the Plugins screen.
	 *
	 * @param array $links Existing links.
	 * @return array
	 */
	public function action_links( $links ) {
		$extra = array(
			'<a href="' . esc_url( admin_url( 'options-general.php?page=advanced-wp' ) ) . '">' . esc_html__( 'Settings', 'advanced-wp' ) . '</a>',
			'<a href="' . esc_url( admin_url( 'customize.php?autofocus[panel]=advwp' ) ) . '">' . esc_html__( 'Customize', 'advanced-wp' ) . '</a>',
		);

		return array_merge( $extra, $links );
	}

	/**
	 * Activation: record the version and write drop-ins.
	 *
	 * @return void
	 */
	public static function activate() {
		update_option( 'advwp_version', ADVWP_VERSION, false );
		Dropins::sync();
	}

	/**
	 * Deactivation: remove the drop-ins this plugin wrote.
	 *
	 * @return void
	 */
	public static function deactivate() {
		Dropins::remove_all();
	}
}

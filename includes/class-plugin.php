<?php
/**
 * Plugin bootstrap and module registry.
 *
 * @package AdminStudio
 */

namespace AdminStudio;

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
			'roles'          => Modules\Roles\Roles::class,
			'admin_look'     => Modules\Admin_Look\Admin_Look::class,
			'widgets'        => Modules\Widgets\Widgets::class,
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
		add_filter( 'plugin_action_links_' . plugin_basename( ADMINSTUDIO_FILE ), array( $this, 'action_links' ) );
		add_action( 'login_enqueue_scripts', array( $this, 'login_notice_borders' ) );
		add_action( 'admin_init', array( __CLASS__, 'reapply_admin_url' ) );
	}

	/**
	 * Gives login screen messages an even border instead of the thick side
	 * stripe from core, even when the login design feature is off.
	 *
	 * @return void
	 */
	public function login_notice_borders() {
		wp_add_inline_style( 'login', '.login .message,.login .notice,.login .success,.login #login_error{border-left-width:1px;border-radius:4px}' );
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
			'<a href="' . esc_url( admin_url( 'options-general.php?page=admin-studio' ) ) . '">' . esc_html__( 'Settings', 'admin-studio' ) . '</a>',
			'<a href="' . esc_url( admin_url( 'customize.php?autofocus[panel]=adminstudio' ) ) . '">' . esc_html__( 'Customize', 'admin-studio' ) . '</a>',
		);

		return array_merge( $extra, $links );
	}

	/**
	 * Activation: record the version and write drop-ins.
	 *
	 * @return void
	 */
	public static function activate() {
		update_option( 'adminstudio_version', ADMINSTUDIO_VERSION, false );
		Dropins::sync();

		// The plugin is not marked active until after this hook, so the dashboard
		// address self-test would fail now. Run it on the next admin page load.
		if ( Settings::module_on( 'security' ) && Settings::get( 'security.admin_url' ) ) {
			update_option( 'adminstudio_reapply_admin_url', 1, false );
		}
	}

	/**
	 * Re-applies the custom dashboard address after activation.
	 *
	 * @return void
	 */
	public static function reapply_admin_url() {
		if ( get_option( 'adminstudio_reapply_admin_url' ) && current_user_can( 'manage_options' ) ) {
			delete_option( 'adminstudio_reapply_admin_url' );
			Modules\Security\Admin_Url::apply();
		}
	}

	/**
	 * Deactivation: remove the drop-ins this plugin wrote.
	 *
	 * @return void
	 */
	public static function deactivate() {
		Dropins::remove_all();
		Modules\Security\Admin_Url::teardown();
	}
}

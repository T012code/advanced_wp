<?php
/**
 * Settings → Advanced WP: page, assets and form handlers.
 *
 * @package AdvancedWP
 */

namespace AdvancedWP\Admin;

use AdvancedWP\Settings;
use AdvancedWP\Modules\Emails\Emails;
use AdvancedWP\Modules\Emails\Renderer;
use AdvancedWP\Modules\Emails\Templates;
use AdvancedWP\Modules\Security\Attempts;
use AdvancedWP\Modules\Security\Login_Url;

defined( 'ABSPATH' ) || exit;

/**
 * Settings page controller.
 */
class Admin {

	const SLUG = 'advanced-wp';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );

		$actions = array( 'save', 'export', 'import', 'reset', 'test_email', 'email_preview', 'reset_template', 'unlock', 'clear_log' );

		foreach ( $actions as $action ) {
			add_action( 'admin_post_advwp_' . $action, array( __CLASS__, 'handle_' . $action ) );
		}
	}

	/**
	 * Tab key => label.
	 *
	 * @return array
	 */
	public static function tabs() {
		return array(
			'overview'       => __( 'Overview', 'advanced-wp' ),
			'emails'         => __( 'Emails', 'advanced-wp' ),
			'tab_meta'       => __( 'Tab & page details', 'advanced-wp' ),
			'admin_branding' => __( 'Admin branding', 'advanced-wp' ),
			'security'       => __( 'Login protection', 'advanced-wp' ),
			'maintenance'    => __( 'Coming soon', 'advanced-wp' ),
			'tools'          => __( 'Tools', 'advanced-wp' ),
		);
	}

	/**
	 * Current tab from the request.
	 *
	 * @return string
	 */
	public static function current_tab() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Navigation only.
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'overview';

		return array_key_exists( $tab, self::tabs() ) ? $tab : 'overview';
	}

	/**
	 * Settings page URL.
	 *
	 * @param string $tab  Tab key.
	 * @param array  $args Extra query arguments.
	 * @return string
	 */
	public static function url( $tab = 'overview', array $args = array() ) {
		return add_query_arg(
			array_merge(
				array(
					'page' => self::SLUG,
					'tab'  => $tab,
				),
				$args
			),
			admin_url( 'options-general.php' )
		);
	}

	/**
	 * Adds the menu item.
	 *
	 * @return void
	 */
	public static function menu() {
		add_options_page(
			__( 'Advanced WP', 'advanced-wp' ),
			__( 'Advanced WP', 'advanced-wp' ),
			'manage_options',
			self::SLUG,
			array( __CLASS__, 'render' )
		);
	}

	/**
	 * Loads assets on the settings page and the profile screen.
	 *
	 * @param string $hook Screen hook.
	 * @return void
	 */
	public static function enqueue( $hook ) {
		if ( in_array( $hook, array( 'profile.php', 'user-edit.php' ), true ) ) {
			wp_enqueue_style( 'advwp-admin', ADVWP_URL . 'assets/css/admin.css', array(), ADVWP_VERSION );
			return;
		}

		if ( 'settings_page_' . self::SLUG !== $hook ) {
			return;
		}

		wp_enqueue_style( 'advwp-admin', ADVWP_URL . 'assets/css/admin.css', array(), ADVWP_VERSION );
		wp_enqueue_media();
		wp_enqueue_script( 'advwp-admin', ADVWP_URL . 'assets/js/admin.js', array( 'jquery' ), ADVWP_VERSION, true );
		wp_localize_script(
			'advwp-admin',
			'advwpAdmin',
			array(
				'chooseImage' => __( 'Choose image', 'advanced-wp' ),
				'useImage'    => __( 'Use this image', 'advanced-wp' ),
				'confirm'     => __( 'Are you sure? This cannot be undone.', 'advanced-wp' ),
			)
		);
	}

	/**
	 * Renders the page.
	 *
	 * @return void
	 */
	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$tab = self::current_tab();
		?>
		<div class="wrap advwp-wrap">
			<h1><?php esc_html_e( 'Advanced WP', 'advanced-wp' ); ?></h1>
			<?php self::notices(); ?>
			<nav class="nav-tab-wrapper" aria-label="<?php esc_attr_e( 'Advanced WP sections', 'advanced-wp' ); ?>">
				<?php foreach ( self::tabs() as $key => $label ) : ?>
					<a href="<?php echo esc_url( self::url( $key ) ); ?>" class="nav-tab<?php echo $key === $tab ? ' nav-tab-active' : ''; ?>"<?php echo $key === $tab ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</nav>
			<div class="advwp-tab">
				<?php Tabs::render( $tab ); ?>
			</div>
			<p class="advwp-credit">
				<?php
				printf(
					/* translators: %s: author name linked to their website. */
					esc_html__( 'Advanced WP by %s', 'advanced-wp' ),
					'<a href="https://www.layothedeveloper.com" target="_blank" rel="noopener">Láyò</a>'
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Shows the result of the last action.
	 *
	 * @return void
	 */
	private static function notices() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display only.
		$notice = isset( $_GET['advwp_notice'] ) ? sanitize_key( wp_unslash( $_GET['advwp_notice'] ) ) : '';

		$messages = array(
			'saved'         => array( 'success', __( 'Settings saved.', 'advanced-wp' ) ),
			'imported'      => array( 'success', __( 'Settings imported.', 'advanced-wp' ) ),
			'import_failed' => array( 'error', __( 'That file is not a valid Advanced WP export.', 'advanced-wp' ) ),
			'reset'         => array( 'success', __( 'Settings reset to their defaults.', 'advanced-wp' ) ),
			'reset_confirm' => array( 'error', __( 'Tick the confirmation box to reset settings.', 'advanced-wp' ) ),
			'test_sent'     => array( 'success', __( 'Test email sent. Check your inbox (and spam folder).', 'advanced-wp' ) ),
			'test_failed'   => array( 'error', __( 'The test email could not be sent. Your server may not be set up to send mail; an SMTP plugin usually fixes this.', 'advanced-wp' ) ),
			'slug_invalid'  => array( 'error', __( 'That login address cannot be used. Choose another one; the custom login URL has been left off.', 'advanced-wp' ) ),
			'unlocked'      => array( 'success', __( 'Lockout lifted.', 'advanced-wp' ) ),
			'log_cleared'   => array( 'success', __( 'Lockout log cleared.', 'advanced-wp' ) ),
		);

		if ( isset( $messages[ $notice ] ) ) {
			printf(
				'<div class="notice notice-%s is-dismissible advwp-notice"><p>%s</p></div>',
				esc_attr( $messages[ $notice ][0] ),
				esc_html( $messages[ $notice ][1] )
			);
		}

		if ( 'saved' === $notice && 'security' === self::current_tab() && Settings::get( 'security.login_url' ) && '' !== Login_Url::slug() ) {
			printf(
				'<div class="notice notice-warning advwp-notice"><p>%s <a href="%2$s"><strong>%2$s</strong></a></p></div>',
				esc_html__( 'Your login page has moved. Bookmark the new address:', 'advanced-wp' ),
				esc_url( Login_Url::url() )
			);
		}
	}

	/**
	 * Stops unless the user can manage options and the nonce is valid.
	 *
	 * @param string $action Nonce action.
	 * @return void
	 */
	private static function guard( $action ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'advanced-wp' ), '', array( 'response' => 403 ) );
		}

		check_admin_referer( $action );
	}

	/**
	 * Redirects back to a tab with a notice.
	 *
	 * @param string $tab    Tab key.
	 * @param string $notice Notice key.
	 * @param array  $args   Extra query arguments.
	 * @return void
	 */
	private static function back( $tab, $notice, array $args = array() ) {
		wp_safe_redirect( self::url( $tab, array_merge( array( 'advwp_notice' => $notice ), $args ) ) );
		exit;
	}

	/**
	 * Setting keys each tab saves.
	 *
	 * @param string $tab      Tab key.
	 * @param string $template Email template key, for the emails tab.
	 * @return string[]
	 */
	private static function tab_keys( $tab, $template = '' ) {
		$prefixes = array(
			'overview'       => array( 'modules.login', 'modules.emails', 'modules.error_pages', 'modules.tab_meta', 'modules.admin_branding', 'general.' ),
			'emails'         => array( 'emails.templates.' . $template . '.' ),
			'tab_meta'       => array( 'tab_meta.' ),
			'admin_branding' => array( 'admin_branding.' ),
			'security'       => array( 'security.' ),
			'maintenance'    => array( 'modules.maintenance', 'maintenance.mode', 'maintenance.roles', 'maintenance.allowed_paths', 'maintenance.bypass_key' ),
		);

		if ( ! isset( $prefixes[ $tab ] ) ) {
			return array();
		}

		return array_values(
			array_filter(
				array_keys( Settings::fields() ),
				static function ( $key ) use ( $prefixes, $tab ) {
					foreach ( $prefixes[ $tab ] as $prefix ) {
						if ( 0 === strpos( $key, $prefix ) ) {
							return true;
						}
					}
					return false;
				}
			)
		);
	}

	/**
	 * Reads a dot path from the posted nested array.
	 *
	 * @param array  $posted Posted data.
	 * @param string $key    Dot path.
	 * @return array [ found, value ]
	 */
	private static function posted_value( array $posted, $key ) {
		$value = $posted;

		foreach ( explode( '.', $key ) as $segment ) {
			if ( ! is_array( $value ) || ! array_key_exists( $segment, $value ) ) {
				return array( false, null );
			}
			$value = $value[ $segment ];
		}

		return array( true, $value );
	}

	/**
	 * Saves a settings tab.
	 *
	 * @return void
	 */
	public static function handle_save() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in guard().
		$tab = isset( $_POST['tab'] ) ? sanitize_key( wp_unslash( $_POST['tab'] ) ) : '';
		self::guard( 'advwp_save_' . $tab );

		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Missing -- Nonce checked in guard(); every value is sanitized by Settings::update() against its field type.
		$posted   = isset( $_POST['advwp'] ) && is_array( $_POST['advwp'] ) ? wp_unslash( $_POST['advwp'] ) : array();
		$template = isset( $_POST['template'] ) ? sanitize_key( wp_unslash( $_POST['template'] ) ) : '';
		// phpcs:enable

		if ( 'emails' === $tab && ! Templates::get( $template ) ) {
			self::back( 'emails', 'saved' );
		}

		$values = array();

		foreach ( self::tab_keys( $tab, $template ) as $key ) {
			list( $found, $value ) = self::posted_value( $posted, $key );

			if ( $found ) {
				$values[ $key ] = $value;
			}
		}

		$notice = 'saved';

		if ( 'security' === $tab ) {
			$slug = isset( $values['security.login_slug'] ) ? sanitize_title( $values['security.login_slug'] ) : '';

			if ( ! empty( $values['security.login_url'] ) && ! Login_Url::is_valid( $slug ) ) {
				$values['security.login_url'] = false;
				$notice                       = 'slug_invalid';
			}

			$values['modules.security'] = ! empty( $values['security.login_url'] ) || ! empty( $values['security.limit'] ) || ! empty( $values['security.honeypot'] ) || ! empty( $values['security.twofa'] );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in guard().
		if ( 'maintenance' === $tab && ! empty( $_POST['advwp_new_bypass'] ) ) {
			$values['maintenance.bypass_key'] = strtolower( wp_generate_password( 16, false ) );
		}

		Settings::update( $values );

		self::back( $tab, $notice, 'emails' === $tab ? array( 'template' => $template ) : array() );
	}

	/**
	 * Downloads all settings as JSON.
	 *
	 * @return void
	 */
	public static function handle_export() {
		self::guard( 'advwp_export' );

		$data = array(
			'plugin'   => 'advanced-wp',
			'version'  => ADVWP_VERSION,
			'exported' => gmdate( 'c' ),
			'site'     => home_url(),
			'settings' => Settings::raw(),
		);

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=advanced-wp-settings-' . gmdate( 'Y-m-d' ) . '.json' );
		echo wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		exit;
	}

	/**
	 * Imports settings from an uploaded JSON file.
	 *
	 * @return void
	 */
	public static function handle_import() {
		self::guard( 'advwp_import' );

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput, WordPress.Security.NonceVerification.Missing -- Nonce checked in guard(); only the temporary path is used, and checked with is_uploaded_file().
		$file = isset( $_FILES['advwp_file']['tmp_name'] ) ? $_FILES['advwp_file']['tmp_name'] : '';

		if ( ! $file || ! is_uploaded_file( $file ) ) {
			self::back( 'tools', 'import_failed' );
		}

		$data = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Uploaded temp file.

		if ( ! is_array( $data ) || 'advanced-wp' !== ( $data['plugin'] ?? '' ) || ! isset( $data['settings'] ) || ! is_array( $data['settings'] ) ) {
			self::back( 'tools', 'import_failed' );
		}

		Settings::import( $data['settings'] );

		self::back( 'tools', 'imported' );
	}

	/**
	 * Resets one section or everything.
	 *
	 * @return void
	 */
	public static function handle_reset() {
		self::guard( 'advwp_reset' );

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Verified in guard().
		if ( empty( $_POST['advwp_confirm'] ) ) {
			self::back( 'tools', 'reset_confirm' );
		}

		$section = isset( $_POST['advwp_section'] ) ? sanitize_key( wp_unslash( $_POST['advwp_section'] ) ) : '';
		// phpcs:enable

		$allowed = array( 'all', 'brand', 'login', 'emails', 'error_pages', 'tab_meta', 'admin_branding', 'security', 'maintenance' );

		if ( in_array( $section, $allowed, true ) ) {
			Settings::reset( 'all' === $section ? '' : $section );
		}

		self::back( 'tools', 'reset' );
	}

	/**
	 * Sends a test email.
	 *
	 * @return void
	 */
	public static function handle_test_email() {
		self::guard( 'advwp_test_email' );

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Verified in guard().
		$template = isset( $_POST['template'] ) ? sanitize_key( wp_unslash( $_POST['template'] ) ) : 'welcome';
		$to       = isset( $_POST['advwp_to'] ) ? sanitize_email( wp_unslash( $_POST['advwp_to'] ) ) : '';
		// phpcs:enable

		$template = Templates::get( $template ) ? $template : 'welcome';
		$sent     = is_email( $to ) && Emails::send_test( $template, $to );

		self::back( 'emails', $sent ? 'test_sent' : 'test_failed', array( 'template' => $template ) );
	}

	/**
	 * Shows a saved email template in the browser.
	 *
	 * @return void
	 */
	public static function handle_email_preview() {
		self::guard( 'advwp_email_preview' );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Verified in guard().
		$template = isset( $_GET['template'] ) ? sanitize_key( wp_unslash( $_GET['template'] ) ) : 'welcome';

		header( 'Content-Type: text/html; charset=utf-8' );
		echo Renderer::preview( $template ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped during rendering.
		exit;
	}

	/**
	 * Restores a template's default text.
	 *
	 * @return void
	 */
	public static function handle_reset_template() {
		self::guard( 'advwp_reset_template' );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in guard().
		$template = isset( $_POST['template'] ) ? sanitize_key( wp_unslash( $_POST['template'] ) ) : '';

		if ( Templates::get( $template ) ) {
			$raw = Settings::raw();
			unset( $raw['emails']['templates'][ $template ] );
			update_option( Settings::OPTION, $raw );
		}

		self::back( 'emails', 'reset', array( 'template' => $template ) );
	}

	/**
	 * Lifts a lockout.
	 *
	 * @return void
	 */
	public static function handle_unlock() {
		self::guard( 'advwp_unlock' );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in guard().
		$ip = isset( $_POST['ip'] ) ? sanitize_text_field( wp_unslash( $_POST['ip'] ) ) : '';

		if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			Attempts::unlock( $ip );
		}

		self::back( 'security', 'unlocked' );
	}

	/**
	 * Clears the lockout log.
	 *
	 * @return void
	 */
	public static function handle_clear_log() {
		self::guard( 'advwp_clear_log' );
		Attempts::clear_log();
		self::back( 'security', 'log_cleared' );
	}

	/**
	 * Plugins whose features overlap with an Advanced WP module.
	 *
	 * @return array Plugin name => module key.
	 */
	public static function conflicts() {
		$known = array(
			'wps-hide-login/wps-hide-login.php'           => array( 'WPS Hide Login', 'security' ),
			'limit-login-attempts-reloaded/limit-login-attempts-reloaded.php' => array( 'Limit Login Attempts Reloaded', 'security' ),
			'two-factor/two-factor.php'                   => array( 'Two Factor', 'security' ),
			'wordfence/wordfence.php'                     => array( 'Wordfence', 'security' ),
			'loginpress/loginpress.php'                   => array( 'LoginPress', 'login' ),
			'custom-login/custom-login.php'               => array( 'Custom Login', 'login' ),
			'email-templates/email-templates.php'         => array( 'Email Templates', 'emails' ),
			'coming-soon/coming-soon.php'                 => array( 'SeedProd Coming Soon', 'maintenance' ),
			'wp-maintenance-mode/wp-maintenance-mode.php' => array( 'LightStart (WP Maintenance Mode)', 'maintenance' ),
			'maintenance/maintenance.php'                 => array( 'Maintenance', 'maintenance' ),
			'white-label-cms/wlcms-plugin.php'            => array( 'White Label CMS', 'admin_branding' ),
			'admin-site-enhancements/admin-site-enhancements.php' => array( 'Admin and Site Enhancements', 'admin_branding' ),
		);

		$found = array();

		foreach ( $known as $file => $info ) {
			if ( is_plugin_active( $file ) && Settings::module_on( $info[1] ) ) {
				$found[ $info[0] ] = $info[1];
			}
		}

		return $found;
	}

	/**
	 * Drop-in status labels.
	 *
	 * @return array
	 */
	public static function dropin_labels() {
		return array(
			'active'     => __( 'Active', 'advanced-wp' ),
			'conflict'   => __( 'Another plugin or your host already uses this file. Advanced WP leaves it alone.', 'advanced-wp' ),
			'off'        => __( 'Off', 'advanced-wp' ),
			'unwritable' => __( 'Cannot write to wp-content. Ask your host to make it writable, or turn this page off.', 'advanced-wp' ),
			'missing'    => __( 'Not written yet. Save any setting to create it.', 'advanced-wp' ),
		);
	}
}

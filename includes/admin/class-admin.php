<?php
/**
 * Settings → Admin Studio: page, assets and form handlers.
 *
 * @package AdminStudio
 */

namespace AdminStudio\Admin;

use AdminStudio\Brand_Kit;
use AdminStudio\Plugin;
use AdminStudio\Settings;
use AdminStudio\Modules\Emails\Emails;
use AdminStudio\Modules\Emails\Renderer;
use AdminStudio\Modules\Emails\Templates;
use AdminStudio\Modules\Security\Admin_Url;
use AdminStudio\Modules\Security\Attempts;
use AdminStudio\Modules\Security\Login_Url;

defined( 'ABSPATH' ) || exit;

/**
 * Settings page controller.
 */
class Admin {

	const SLUG = 'admin-studio';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'admin_init', array( __CLASS__, 'store_brand_source' ) );
		add_action( 'wp_ajax_adminstudio_brand_capture', array( __CLASS__, 'ajax_brand_capture' ) );
		Roles_Tab::init();
		Look_Tab::init();
		Sessions_Tab::init();
		Widgets_Tab::init();

		$actions = array( 'save', 'export', 'import', 'reset', 'test_email', 'email_preview', 'reset_template', 'unlock', 'clear_log', 'admin_url_test', 'admin_url_force' );

		foreach ( $actions as $action ) {
			add_action( 'admin_post_adminstudio_' . $action, array( __CLASS__, 'handle_' . $action ) );
		}
	}

	/**
	 * Tab key => label.
	 *
	 * @return array
	 */
	public static function tabs() {
		return array(
			'overview'       => __( 'Overview', 'admin-studio' ),
			'emails'         => __( 'Emails', 'admin-studio' ),
			'tab_meta'       => __( 'Tab & page details', 'admin-studio' ),
			'admin_branding' => __( 'Admin branding', 'admin-studio' ),
			'look'           => __( 'Dashboard look', 'admin-studio' ),
			'widgets'        => __( 'Dashboard widgets', 'admin-studio' ),
			'roles'          => __( 'Roles & permissions', 'admin-studio' ),
			'sessions'       => __( 'Sessions', 'admin-studio' ),
			'security'       => __( 'Login protection', 'admin-studio' ),
			'maintenance'    => __( 'Coming soon', 'admin-studio' ),
			'tools'          => __( 'Tools', 'admin-studio' ),
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
			__( 'Admin Studio', 'admin-studio' ),
			__( 'Admin Studio', 'admin-studio' ),
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
		if ( in_array( $hook, array( 'profile.php', 'user-edit.php', 'users.php' ), true ) ) {
			wp_enqueue_style( 'adminstudio-admin', ADMINSTUDIO_URL . 'assets/css/admin.css', array(), \AdminStudio\Plugin::asset_version( 'assets/css/admin.css' ) );

			// Shows the page or address box for the login destination picker.
			if ( 'users.php' !== $hook ) {
				wp_enqueue_script( 'adminstudio-admin', ADMINSTUDIO_URL . 'assets/js/admin.js', array( 'jquery' ), \AdminStudio\Plugin::asset_version( 'assets/js/admin.js' ), true );
			}
			return;
		}

		if ( 'settings_page_' . self::SLUG !== $hook ) {
			return;
		}

		wp_enqueue_style( 'adminstudio-admin', ADMINSTUDIO_URL . 'assets/css/admin.css', array(), \AdminStudio\Plugin::asset_version( 'assets/css/admin.css' ) );
		wp_enqueue_media();
		wp_enqueue_script( 'adminstudio-admin', ADMINSTUDIO_URL . 'assets/js/admin.js', array( 'jquery' ), \AdminStudio\Plugin::asset_version( 'assets/js/admin.js' ), true );
		wp_localize_script(
			'adminstudio-admin',
			'adminstudioAdmin',
			array(
				'chooseImage' => __( 'Choose image', 'admin-studio' ),
				'useImage'    => __( 'Use this image', 'admin-studio' ),
				'confirm'     => __( 'Are you sure? This cannot be undone.', 'admin-studio' ),
				'capture'     => array(
					'url'     => home_url( '/' ),
					'ajax'    => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'adminstudio_brand_capture' ),
					'reading' => __( 'Reading your site…', 'admin-studio' ),
					'done'    => __( 'Colours read from your home page. Save to use them.', 'admin-studio' ),
					'note'    => __( 'The colours visitors see on your home page, including page builders. Read just now.', 'admin-studio' ),
					'failed'  => __( 'Your home page could not be read here, possibly because a security setting stops it opening inside the dashboard. Pick another option.', 'admin-studio' ),
				),
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
		<div class="wrap adminstudio-wrap">
			<h1 class="adminstudio-title">
				<img src="<?php echo esc_url( ADMINSTUDIO_URL . 'assets/images/logo.svg' ); ?>" width="32" height="32" alt="">
				<?php esc_html_e( 'Admin Studio', 'admin-studio' ); ?>
			</h1>
			<?php self::notices(); ?>
			<nav class="nav-tab-wrapper" aria-label="<?php esc_attr_e( 'Admin Studio sections', 'admin-studio' ); ?>">
				<?php foreach ( self::tabs() as $key => $label ) : ?>
					<a href="<?php echo esc_url( self::url( $key ) ); ?>" class="nav-tab<?php echo $key === $tab ? ' nav-tab-active' : ''; ?>"<?php echo $key === $tab ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</nav>
			<div class="adminstudio-tab">
				<?php Tabs::render( $tab ); ?>
			</div>
			<p class="adminstudio-credit">
				<?php
				printf(
					/* translators: %s: author name linked to their website. */
					esc_html__( 'Admin Studio by %s', 'admin-studio' ),
					'<a href="https://www.layothedeveloper.com" target="_blank" rel="noopener">Láyò</a>'
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Sites set up before the brand colour choice existed keep the colours they
	 * had, so the Customizer and settings page show the right option.
	 *
	 * @return void
	 */
	public static function store_brand_source() {
		$raw = Settings::raw();

		if ( ! isset( $raw['brand']['source'] ) && current_user_can( 'manage_options' ) ) {
			Settings::update( array( 'brand.source' => Brand_Kit::source() ) );
		}
	}

	/**
	 * Saves the colours read from the home page in the browser.
	 *
	 * @return void
	 */
	public static function ajax_brand_capture() {
		check_ajax_referer( 'adminstudio_brand_capture', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( null, 403 );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Every value is cleaned to a hex colour by save_frontend_palette().
		$colors = isset( $_POST['colors'] ) && is_array( $_POST['colors'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['colors'] ) ) : array();
		$colors = Brand_Kit::save_frontend_palette( $colors );

		if ( ! $colors ) {
			wp_send_json_error();
		}

		if ( 'frontend' === Brand_Kit::source() ) {
			Plugin::apply_saved_settings();
		}

		wp_send_json_success( array( 'colors' => $colors ) );
	}

	/**
	 * Shows the result of the last action.
	 *
	 * @return void
	 */
	private static function notices() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display only.
		$notice = isset( $_GET['adminstudio_notice'] ) ? sanitize_key( wp_unslash( $_GET['adminstudio_notice'] ) ) : '';

		$messages = array(
			'saved'                  => array( 'success', __( 'Settings saved.', 'admin-studio' ) ),
			'imported'               => array( 'success', __( 'Settings imported.', 'admin-studio' ) ),
			'imported_without_roles' => array( 'warning', __( 'Settings imported, except the roles: they would have removed your own access, so your roles were left as they were.', 'admin-studio' ) ),
			'import_roles_failed'    => array( 'error', __( 'Nothing was imported. The roles in the file would have removed your own access.', 'admin-studio' ) ),
			'import_nothing'         => array( 'error', __( 'Nothing was imported. Tick at least one part that the file contains.', 'admin-studio' ) ),
			'import_failed'          => array( 'error', __( 'That file is not a valid Admin Studio export.', 'admin-studio' ) ),
			'reset'                  => array( 'success', __( 'Settings reset to their defaults.', 'admin-studio' ) ),
			'reset_confirm'          => array( 'error', __( 'Tick the confirmation box to reset settings.', 'admin-studio' ) ),
			'test_sent'              => array( 'success', __( 'Test email sent. Check your inbox (and spam folder).', 'admin-studio' ) ),
			'test_failed'            => array( 'error', __( 'The test email could not be sent. Your server may not be set up to send mail; an SMTP plugin usually fixes this.', 'admin-studio' ) ),
			'slug_invalid'           => array( 'error', __( 'That login address cannot be used. Choose another one; the custom login URL has been left off.', 'admin-studio' ) ),
			'unlocked'               => array( 'success', __( 'Lockout lifted.', 'admin-studio' ) ),
			'log_cleared'            => array( 'success', __( 'Lockout log cleared.', 'admin-studio' ) ),
			'role_saved'             => array( 'success', __( 'Role saved. A backup of the previous roles was kept.', 'admin-studio' ) ),
			'role_deleted'           => array( 'success', __( 'Role deleted and its users moved.', 'admin-studio' ) ),
			'roles_restored'         => array( 'success', __( 'Roles restored.', 'admin-studio' ) ),
			'look_stopped'           => array( 'success', __( 'Trial ended. Your dashboard is back to its usual template.', 'admin-studio' ) ),
			'session_ended'          => array( 'success', __( 'That device has been signed out.', 'admin-studio' ) ),
			'sessions_ended'         => array( 'success', __( 'Signed out everywhere.', 'admin-studio' ) ),
			'user_blocked'           => array( 'success', __( 'Account blocked and signed out everywhere.', 'admin-studio' ) ),
			'user_unblocked'         => array( 'success', __( 'Account unblocked. They can sign in again.', 'admin-studio' ) ),
			'user_not_found'         => array( 'error', __( 'No user found with that username or email address.', 'admin-studio' ) ),
			'widget_deleted'         => array( 'success', __( 'Widget deleted.', 'admin-studio' ) ),
			'history_cleared'        => array( 'success', __( 'Sign-in history cleared.', 'admin-studio' ) ),
			'admin_slug_invalid'     => array( 'error', __( 'That dashboard address cannot be used. Choose another one that differs from the login address; the custom dashboard address has been left off.', 'admin-studio' ) ),
			'admin_url_active'       => array( 'success', __( 'Settings saved. The dashboard has moved to its new address.', 'admin-studio' ) ),
			'admin_url_pending'      => array( 'warning', __( 'Settings saved. The new dashboard address is not working yet, so the dashboard stays at /wp-admin. Add the server rules shown below, then click "Test again".', 'admin-studio' ) ),
			'admin_url_unwritable'   => array( 'warning', __( 'Settings saved. The .htaccess file could not be updated, so the dashboard stays at /wp-admin. Add the rules shown below to .htaccess, then click "Test again".', 'admin-studio' ) ),
		);

		if ( isset( $messages[ $notice ] ) ) {
			printf(
				'<div class="notice notice-%s is-dismissible adminstudio-notice"><p>%s</p></div>',
				esc_attr( $messages[ $notice ][0] ),
				esc_html( $messages[ $notice ][1] )
			);
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Display only.
		$missing = isset( $_GET['missing'] ) ? absint( $_GET['missing'] ) : 0;
		$skipped = ! empty( $_GET['roles_skipped'] );
		// phpcs:enable

		if ( $skipped ) {
			echo '<div class="notice notice-warning is-dismissible adminstudio-notice"><p>' . esc_html( $messages['imported_without_roles'][1] ) . '</p></div>';
		}

		if ( $missing ) {
			printf(
				'<div class="notice notice-warning is-dismissible adminstudio-notice"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: %d: number of pages or categories. */
						_n(
							'%d page or category from the file does not exist on this site, so the setting that used it went back to its default: the Customizer design for coming soon, the next login destination rule, or all categories for a "What\'s new" widget. Create it with the same slug and import again, or choose another one.',
							'%d pages or categories from the file do not exist on this site, so the settings that used them went back to their defaults: the Customizer design for coming soon, the next login destination rule, or all categories for a "What\'s new" widget. Create them with the same slugs and import again, or choose others.',
							$missing,
							'admin-studio'
						),
						$missing
					)
				)
			);
		}

		if ( 'saved' === $notice && 'security' === self::current_tab() && Settings::get( 'security.login_url' ) && '' !== Login_Url::slug() ) {
			printf(
				'<div class="notice notice-warning adminstudio-notice"><p>%s <a href="%2$s"><strong>%2$s</strong></a></p></div>',
				esc_html__( 'Your login page has moved. Bookmark the new address:', 'admin-studio' ),
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
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'admin-studio' ), '', array( 'response' => 403 ) );
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
		wp_safe_redirect( self::url( $tab, array_merge( array( 'adminstudio_notice' => $notice ), $args ) ) );
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
			'overview'       => array( 'modules.login', 'modules.emails', 'modules.error_pages', 'modules.tab_meta', 'modules.admin_branding', 'modules.roles', 'modules.admin_look', 'modules.widgets', 'modules.sessions', 'general.' ),
			'emails'         => array( 'emails.templates.' . $template . '.' ),
			'tab_meta'       => array( 'tab_meta.' ),
			'brand'          => array( 'brand.source' ),
			'sessions'       => array( 'sessions.' ),
			'admin_branding' => array( 'admin_branding.' ),
			'security'       => array( 'security.' ),
			'maintenance'    => array( 'modules.maintenance', 'maintenance.mode', 'maintenance.design', 'maintenance.page', 'maintenance.roles', 'maintenance.allowed_paths', 'maintenance.bypass_key' ),
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
		self::guard( 'adminstudio_save_' . $tab );

		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.NonceVerification.Missing -- Nonce checked in guard(); every value is sanitized by Settings::update() against its field type.
		$posted   = isset( $_POST['adminstudio'] ) && is_array( $_POST['adminstudio'] ) ? wp_unslash( $_POST['adminstudio'] ) : array();
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

			$admin_slug = isset( $values['security.admin_slug'] ) ? sanitize_title( $values['security.admin_slug'] ) : '';

			if ( ! empty( $values['security.admin_url'] ) && ( '' === $admin_slug || in_array( $admin_slug, Login_Url::RESERVED, true ) || $admin_slug === $slug ) ) {
				$values['security.admin_url'] = false;
				$notice                       = 'admin_slug_invalid';
			}

			$values['modules.security'] = ! empty( $values['security.login_url'] ) || ! empty( $values['security.admin_url'] ) || ! empty( $values['security.limit'] ) || ! empty( $values['security.honeypot'] ) || ! empty( $values['security.twofa'] );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in guard().
		if ( 'maintenance' === $tab && ! empty( $_POST['adminstudio_new_bypass'] ) ) {
			$values['maintenance.bypass_key'] = strtolower( wp_generate_password( 16, false ) );
		}

		Settings::update( $values );

		if ( 'security' === $tab ) {
			$state = Admin_Url::apply();

			if ( 'saved' === $notice && 'off' !== $state ) {
				$notice = 'admin_url_' . $state;
			}
		}

		$args = 'emails' === $tab ? array( 'template' => $template ) : array();
		$args = 'sessions' === $tab ? array( 'section' => 'settings' ) : $args;

		self::back( $tab, $notice, $args );
	}

	/**
	 * Re-tests the custom dashboard address, for example after adding the
	 * Nginx rules.
	 *
	 * @return void
	 */
	public static function handle_admin_url_test() {
		self::guard( 'adminstudio_admin_url_test' );

		self::back( 'security', 'admin_url_' . Admin_Url::apply() );
	}

	/**
	 * Switches the custom dashboard address on without a passing test, after
	 * the owner has confirmed the test page works in their browser.
	 *
	 * @return void
	 */
	public static function handle_admin_url_force() {
		self::guard( 'adminstudio_admin_url_force' );

		Admin_Url::force();

		self::back( 'security', 'admin_url_active' );
	}

	/**
	 * Downloads all settings as JSON.
	 *
	 * @return void
	 */
	public static function handle_export() {
		self::guard( 'adminstudio_export' );

		$data = Transfer::export( self::posted_parts() );

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=admin-studio-settings-' . gmdate( 'Y-m-d' ) . '.json' );
		echo wp_json_encode( $data, JSON_PRETTY_PRINT );
		exit;
	}

	/**
	 * Imports settings from an uploaded JSON file.
	 *
	 * @return void
	 */
	public static function handle_import() {
		self::guard( 'adminstudio_import' );

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput, WordPress.Security.NonceVerification.Missing -- Nonce checked in guard(); only the temporary path is used, and checked with is_uploaded_file().
		$file = isset( $_FILES['adminstudio_file']['tmp_name'] ) ? $_FILES['adminstudio_file']['tmp_name'] : '';

		if ( ! $file || ! is_uploaded_file( $file ) ) {
			self::back( 'tools', 'import_failed' );
		}

		$data = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Uploaded temp file.

		if ( ! Transfer::is_export( $data ) ) {
			self::back( 'tools', 'import_failed' );
		}

		$report = Transfer::import( $data, self::posted_parts() );

		if ( ! $report['imported'] ) {
			self::back( 'tools', '' !== $report['roles_error'] ? 'import_roles_failed' : 'import_nothing' );
		}

		$notice = '' !== $report['roles_error'] ? 'imported_without_roles' : 'imported';
		$args   = array();

		// A login or dashboard address that had to be switched off matters most.
		if ( '' !== $report['security'] ) {
			$notice = $report['security'];
			if ( '' !== $report['roles_error'] ) {
				$args['roles_skipped'] = 1;
			}
		}

		if ( $report['missing'] ) {
			$args['missing'] = $report['missing'];
		}

		self::back( 'tools', $notice, $args );
	}

	/**
	 * Parts ticked on the export or import form.
	 *
	 * @return string[]
	 */
	private static function posted_parts() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Called from handlers that verify the nonce first.
		$parts = isset( $_POST['adminstudio_parts'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['adminstudio_parts'] ) ) : array();

		return array_values( array_intersect( $parts, array_keys( Transfer::parts() ) ) );
	}

	/**
	 * Resets one section or everything.
	 *
	 * @return void
	 */
	public static function handle_reset() {
		self::guard( 'adminstudio_reset' );

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Verified in guard().
		if ( empty( $_POST['adminstudio_confirm'] ) ) {
			self::back( 'tools', 'reset_confirm' );
		}

		$section = isset( $_POST['adminstudio_section'] ) ? sanitize_key( wp_unslash( $_POST['adminstudio_section'] ) ) : '';
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
		self::guard( 'adminstudio_test_email' );

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Verified in guard().
		$template = isset( $_POST['template'] ) ? sanitize_key( wp_unslash( $_POST['template'] ) ) : 'welcome';
		$to       = isset( $_POST['adminstudio_to'] ) ? sanitize_email( wp_unslash( $_POST['adminstudio_to'] ) ) : '';
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
		self::guard( 'adminstudio_email_preview' );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Verified in guard().
		$template = isset( $_GET['template'] ) ? sanitize_key( wp_unslash( $_GET['template'] ) ) : 'welcome';

		header( 'Content-Type: text/html; charset=utf-8' );
		Renderer::print_preview( $template );
		exit;
	}

	/**
	 * Restores a template's default text.
	 *
	 * @return void
	 */
	public static function handle_reset_template() {
		self::guard( 'adminstudio_reset_template' );

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
		self::guard( 'adminstudio_unlock' );

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
		self::guard( 'adminstudio_clear_log' );
		Attempts::clear_log();
		self::back( 'security', 'log_cleared' );
	}

	/**
	 * Plugins whose features overlap with an Admin Studio module.
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
			'active'     => __( 'Active', 'admin-studio' ),
			'conflict'   => __( 'Another plugin or your host already uses this file. Admin Studio leaves it alone.', 'admin-studio' ),
			'off'        => __( 'Off', 'admin-studio' ),
			'unwritable' => __( 'Cannot write to wp-content. Ask your host to make it writable, or turn this page off.', 'admin-studio' ),
			'missing'    => __( 'Not written yet. Save any setting to create it.', 'admin-studio' ),
		);
	}
}

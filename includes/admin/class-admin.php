<?php
/**
 * Settings → Adminora: page, assets and form handlers.
 *
 * @package Adminora
 */

namespace Adminora\Admin;

use Adminora\Brand_Kit;
use Adminora\Plugin;
use Adminora\Settings;
use Adminora\Modules\Emails\Emails;
use Adminora\Modules\Emails\Renderer;
use Adminora\Modules\Emails\Templates;
use Adminora\Modules\Security\Admin_Url;
use Adminora\Modules\Security\Attempts;
use Adminora\Modules\Security\Login_Url;

defined( 'ABSPATH' ) || exit;

/**
 * Settings page controller.
 */
class Admin {

	const SLUG   = 'adminora';
	const REPORT = 'adminora_import_';
	const UPLOAD = 'adminora_import_file_';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'admin_init', array( __CLASS__, 'store_brand_source' ) );
		add_action( 'wp_ajax_adminora_brand_capture', array( __CLASS__, 'ajax_brand_capture' ) );
		Roles_Tab::init();
		Look_Tab::init();
		Sessions_Tab::init();
		Widgets_Tab::init();

		$actions = array( 'save', 'export', 'import', 'import_apply', 'import_cancel', 'reset', 'test_email', 'email_preview', 'reset_template', 'unlock', 'clear_log', 'admin_url_test', 'admin_url_force' );

		foreach ( $actions as $action ) {
			add_action( 'admin_post_adminora_' . $action, array( __CLASS__, 'handle_' . $action ) );
		}
	}

	/**
	 * Tab key => label.
	 *
	 * @return array
	 */
	public static function tabs() {
		return array(
			'overview'       => __( 'Overview', 'adminora' ),
			'emails'         => __( 'Emails', 'adminora' ),
			'tab_meta'       => __( 'Tab & page details', 'adminora' ),
			'admin_branding' => __( 'Admin branding', 'adminora' ),
			'look'           => __( 'Dashboard look', 'adminora' ),
			'widgets'        => __( 'Dashboard widgets', 'adminora' ),
			'roles'          => __( 'Roles & permissions', 'adminora' ),
			'sessions'       => __( 'Sessions', 'adminora' ),
			'security'       => __( 'Login protection', 'adminora' ),
			'maintenance'    => __( 'Coming soon', 'adminora' ),
			'tools'          => __( 'Tools', 'adminora' ),
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
			__( 'Adminora', 'adminora' ),
			__( 'Adminora', 'adminora' ),
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
			wp_enqueue_style( 'adminora-admin', ADMINORA_URL . 'assets/css/admin.css', array(), \Adminora\Plugin::asset_version( 'assets/css/admin.css' ) );

			// Shows the page or address box for the login destination picker.
			if ( 'users.php' !== $hook ) {
				wp_enqueue_script( 'adminora-admin', ADMINORA_URL . 'assets/js/admin.js', array( 'jquery' ), \Adminora\Plugin::asset_version( 'assets/js/admin.js' ), true );
			}
			return;
		}

		if ( 'settings_page_' . self::SLUG !== $hook ) {
			return;
		}

		wp_enqueue_style( 'adminora-admin', ADMINORA_URL . 'assets/css/admin.css', array(), \Adminora\Plugin::asset_version( 'assets/css/admin.css' ) );
		wp_enqueue_media();
		wp_enqueue_script( 'adminora-admin', ADMINORA_URL . 'assets/js/admin.js', array( 'jquery' ), \Adminora\Plugin::asset_version( 'assets/js/admin.js' ), true );
		wp_localize_script(
			'adminora-admin',
			'adminoraAdmin',
			array(
				'chooseImage' => __( 'Choose image', 'adminora' ),
				'useImage'    => __( 'Use this image', 'adminora' ),
				'confirm'     => __( 'Are you sure? This cannot be undone.', 'adminora' ),
				'capture'     => array(
					'url'     => home_url( '/' ),
					'ajax'    => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'adminora_brand_capture' ),
					'reading' => __( 'Reading your site…', 'adminora' ),
					'done'    => __( 'Colours read from your home page. Save to use them.', 'adminora' ),
					'note'    => __( 'The colours visitors see on your home page, including page builders. Read just now.', 'adminora' ),
					'failed'  => __( 'Your home page could not be read here, possibly because a security setting stops it opening inside the dashboard. Pick another option.', 'adminora' ),
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
		<div class="wrap adminora-wrap">
			<h1 class="adminora-title">
				<img src="<?php echo esc_url( ADMINORA_URL . 'assets/images/logo.svg' ); ?>" width="32" height="32" alt="">
				<?php esc_html_e( 'Adminora', 'adminora' ); ?>
			</h1>
			<?php self::notices(); ?>
			<nav class="nav-tab-wrapper" aria-label="<?php esc_attr_e( 'Adminora sections', 'adminora' ); ?>">
				<?php foreach ( self::tabs() as $key => $label ) : ?>
					<a href="<?php echo esc_url( self::url( $key ) ); ?>" class="nav-tab<?php echo $key === $tab ? ' nav-tab-active' : ''; ?>"<?php echo $key === $tab ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</nav>
			<div class="adminora-tab">
				<?php Tabs::render( $tab ); ?>
			</div>
			<p class="adminora-credit">
				<?php
				printf(
					/* translators: %s: author name linked to their website. */
					esc_html__( 'Adminora by %s', 'adminora' ),
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
		check_ajax_referer( 'adminora_brand_capture', 'nonce' );

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
		$notice = isset( $_GET['adminora_notice'] ) ? sanitize_key( wp_unslash( $_GET['adminora_notice'] ) ) : '';

		$messages = array(
			'saved'                => array( 'success', __( 'Settings saved.', 'adminora' ) ),
			'imported'             => array( 'success', __( 'Settings imported.', 'adminora' ) ),
			'import_roles_failed'  => array( 'error', __( 'Nothing was imported. The roles in the file would have removed your own access.', 'adminora' ) ),
			'import_nothing'       => array( 'error', __( 'Nothing was imported. Tick at least one part or role.', 'adminora' ) ),
			'import_expired'       => array( 'error', __( 'The uploaded file is no longer waiting to be imported. Upload it again.', 'adminora' ) ),
			'import_cancelled'     => array( 'success', __( 'Import cancelled. Nothing was changed.', 'adminora' ) ),
			'import_failed'        => array( 'error', __( 'That file is not a valid Adminora export.', 'adminora' ) ),
			'reset'                => array( 'success', __( 'Settings reset to their defaults.', 'adminora' ) ),
			'reset_confirm'        => array( 'error', __( 'Tick the confirmation box to reset settings.', 'adminora' ) ),
			'test_sent'            => array( 'success', __( 'Test email sent. Check your inbox (and spam folder).', 'adminora' ) ),
			'test_failed'          => array( 'error', __( 'The test email could not be sent. Your server may not be set up to send mail; an SMTP plugin usually fixes this.', 'adminora' ) ),
			'slug_invalid'         => array( 'error', __( 'That login address cannot be used. Choose another one; the custom login URL has been left off.', 'adminora' ) ),
			'unlocked'             => array( 'success', __( 'Lockout lifted.', 'adminora' ) ),
			'log_cleared'          => array( 'success', __( 'Lockout log cleared.', 'adminora' ) ),
			'role_saved'           => array( 'success', __( 'Role saved. A backup of the previous roles was kept.', 'adminora' ) ),
			'role_deleted'         => array( 'success', __( 'Role deleted and its users moved.', 'adminora' ) ),
			'roles_restored'       => array( 'success', __( 'Roles restored.', 'adminora' ) ),
			'look_stopped'         => array( 'success', __( 'Trial ended. Your dashboard is back to its usual template.', 'adminora' ) ),
			'session_ended'        => array( 'success', __( 'That device has been signed out.', 'adminora' ) ),
			'sessions_ended'       => array( 'success', __( 'Signed out everywhere.', 'adminora' ) ),
			'user_blocked'         => array( 'success', __( 'Account blocked and signed out everywhere.', 'adminora' ) ),
			'user_unblocked'       => array( 'success', __( 'Account unblocked. They can sign in again.', 'adminora' ) ),
			'user_not_found'       => array( 'error', __( 'No user found with that username or email address.', 'adminora' ) ),
			'widget_deleted'       => array( 'success', __( 'Widget deleted.', 'adminora' ) ),
			'history_cleared'      => array( 'success', __( 'Sign-in history cleared.', 'adminora' ) ),
			'admin_slug_invalid'   => array( 'error', __( 'That dashboard address cannot be used. Choose another one that differs from the login address; the custom dashboard address has been left off.', 'adminora' ) ),
			'admin_url_active'     => array( 'success', __( 'Settings saved. The dashboard has moved to its new address.', 'adminora' ) ),
			'admin_url_pending'    => array( 'warning', __( 'Settings saved. The new dashboard address is not working yet, so the dashboard stays at /wp-admin. Add the server rules shown below, then click "Test again".', 'adminora' ) ),
			'admin_url_unwritable' => array( 'warning', __( 'Settings saved. The .htaccess file could not be updated, so the dashboard stays at /wp-admin. Add the rules shown below to .htaccess, then click "Test again".', 'adminora' ) ),
		);

		if ( isset( $messages[ $notice ] ) ) {
			printf(
				'<div class="notice notice-%s is-dismissible adminora-notice"><p>%s</p></div>',
				esc_attr( $messages[ $notice ][0] ),
				esc_html( $messages[ $notice ][1] )
			);
		}

		if ( 'import_done' === $notice ) {
			self::import_report();
		}

		if ( 'saved' === $notice && 'security' === self::current_tab() && Settings::get( 'security.login_url' ) && '' !== Login_Url::slug() ) {
			printf(
				'<div class="notice notice-warning adminora-notice"><p>%s <a href="%2$s"><strong>%2$s</strong></a></p></div>',
				esc_html__( 'Your login page has moved. Bookmark the new address:', 'adminora' ),
				esc_url( Login_Url::url() )
			);
		}
	}

	/**
	 * Stops the request with a "not allowed" message.
	 *
	 * @return void
	 */
	private static function deny() {
		wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'adminora' ), '', array( 'response' => 403 ) );
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
		wp_safe_redirect( self::url( $tab, array_merge( array( 'adminora_notice' => $notice ), $args ) ) );
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
		check_admin_referer( 'adminora_save' );

		if ( ! current_user_can( 'manage_options' ) ) {
			self::deny();
		}

		$tab = isset( $_POST['tab'] ) ? sanitize_key( wp_unslash( $_POST['tab'] ) ) : '';

		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Every value is sanitized by Settings::update() against its field type.
		$posted   = isset( $_POST['adminora'] ) && is_array( $_POST['adminora'] ) ? wp_unslash( $_POST['adminora'] ) : array();
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

		if ( 'maintenance' === $tab && ! empty( $_POST['adminora_new_bypass'] ) ) {
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
		check_admin_referer( 'adminora_admin_url_test' );

		if ( ! current_user_can( 'manage_options' ) ) {
			self::deny();
		}

		self::back( 'security', 'admin_url_' . Admin_Url::apply() );
	}

	/**
	 * Switches the custom dashboard address on without a passing test, after
	 * the owner has confirmed the test page works in their browser.
	 *
	 * @return void
	 */
	public static function handle_admin_url_force() {
		check_admin_referer( 'adminora_admin_url_force' );

		if ( ! current_user_can( 'manage_options' ) ) {
			self::deny();
		}

		Admin_Url::force();

		self::back( 'security', 'admin_url_active' );
	}

	/**
	 * Downloads all settings as JSON.
	 *
	 * @return void
	 */
	public static function handle_export() {
		check_admin_referer( 'adminora_export' );

		if ( ! current_user_can( 'manage_options' ) ) {
			self::deny();
		}

		$parts = isset( $_POST['adminora_parts'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['adminora_parts'] ) ) : array();
		$data  = Transfer::export( self::known_parts( $parts ) );

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=adminora-settings-' . gmdate( 'Y-m-d' ) . '.json' );
		echo wp_json_encode( $data, JSON_PRETTY_PRINT );
		exit;
	}

	/**
	 * Imports settings from an uploaded JSON file.
	 *
	 * @return void
	 */
	public static function handle_import() {
		check_admin_referer( 'adminora_import' );

		if ( ! current_user_can( 'manage_options' ) ) {
			self::deny();
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Only the temporary path is used, and checked with is_uploaded_file().
		$file = isset( $_FILES['adminora_file']['tmp_name'] ) ? $_FILES['adminora_file']['tmp_name'] : '';

		if ( ! $file || ! is_uploaded_file( $file ) ) {
			self::back( 'tools', 'import_failed' );
		}

		$data = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Uploaded temp file.

		if ( ! Transfer::is_export( $data ) ) {
			self::back( 'tools', 'import_failed' );
		}

		// Kept until the review screen is confirmed or cancelled.
		set_transient( self::UPLOAD . get_current_user_id(), $data, HOUR_IN_SECONDS );

		self::back( 'tools', '', array( 'step' => 'review' ) );
	}

	/**
	 * The uploaded file waiting on the review screen, or null.
	 *
	 * @return array|null
	 */
	public static function pending_import() {
		$data = get_transient( self::UPLOAD . get_current_user_id() );

		return Transfer::is_export( $data ) ? $data : null;
	}

	/**
	 * Imports the parts and roles chosen on the review screen.
	 *
	 * @return void
	 */
	public static function handle_import_apply() {
		check_admin_referer( 'adminora_import_apply' );

		if ( ! current_user_can( 'manage_options' ) ) {
			self::deny();
		}

		$data = self::pending_import();

		if ( ! $data ) {
			self::back( 'tools', 'import_expired' );
		}

		$parts  = isset( $_POST['adminora_parts'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['adminora_parts'] ) ) : array();
		$roles  = isset( $_POST['adminora_roles'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['adminora_roles'] ) ) : array();
		$report = Transfer::import( $data, self::known_parts( $parts ), $roles );

		delete_transient( self::UPLOAD . get_current_user_id() );

		if ( ! $report['imported'] ) {
			self::back( 'tools', '' !== $report['roles_error'] ? 'import_roles_failed' : 'import_nothing' );
		}

		// The details are shown once on the next page load.
		set_transient( self::REPORT . get_current_user_id(), $report, 10 * MINUTE_IN_SECONDS );

		self::back( 'tools', 'import_done' );
	}

	/**
	 * Drops the uploaded file without importing it.
	 *
	 * @return void
	 */
	public static function handle_import_cancel() {
		check_admin_referer( 'adminora_import_cancel' );

		if ( ! current_user_can( 'manage_options' ) ) {
			self::deny();
		}
		delete_transient( self::UPLOAD . get_current_user_id() );
		self::back( 'tools', 'import_cancelled' );
	}

	/**
	 * Prints what an import did, then forgets it.
	 *
	 * @return void
	 */
	private static function import_report() {
		$key    = self::REPORT . get_current_user_id();
		$report = get_transient( $key );

		if ( ! is_array( $report ) ) {
			return;
		}

		delete_transient( $key );

		$lines = array( array( 'success', __( 'Settings imported.', 'adminora' ) ) );

		if ( ! empty( $report['roles_added'] ) ) {
			/* translators: %s: list of role names. */
			$lines[] = array( 'success', sprintf( __( 'Roles added: %s.', 'adminora' ), implode( ', ', $report['roles_added'] ) ) );
		}

		if ( ! empty( $report['admin_caps'] ) ) {
			$lines[] = array(
				'success',
				sprintf(
					/* translators: %d: number of capabilities. */
					_n( 'The Administrator role was given %d capability from the imported roles, so administrators can still do everything.', 'The Administrator role was given %d capabilities from the imported roles, so administrators can still do everything.', (int) $report['admin_caps'], 'adminora' ),
					(int) $report['admin_caps']
				),
			);
		}

		if ( ! empty( $report['roles_updated'] ) ) {
			/* translators: %s: list of role names. */
			$lines[] = array( 'success', sprintf( __( 'Roles given the capabilities from the file: %s. A backup of the previous roles is under Roles & permissions → Backups.', 'adminora' ), implode( ', ', $report['roles_updated'] ) ) );
		}

		if ( ! empty( $report['roles_error'] ) ) {
			$lines[] = array( 'warning', __( 'The roles were not imported, because they would have removed your own access. Everything else was imported.', 'adminora' ) );
		}

		if ( ! empty( $report['roles_missing'] ) ) {
			$lines[] = array(
				'warning',
				sprintf(
					/* translators: %s: list of role names. */
					__( 'This site does not have these roles, so the rules for them were skipped: %s. Import the file again and tick those roles, or create them under Roles & permissions first.', 'adminora' ),
					implode( ', ', $report['roles_missing'] )
				),
			);
		}

		if ( ! empty( $report['missing'] ) ) {
			$lines[] = array(
				'warning',
				sprintf(
					/* translators: %d: number of pages or categories. */
					_n(
						'%d page or category from the file does not exist on this site, so the setting that used it went back to its default: the Customizer design for coming soon, the next login destination rule, or all categories for a "What\'s new" widget. Create it with the same slug and import again, or choose another one.',
						'%d pages or categories from the file do not exist on this site, so the settings that used them went back to their defaults: the Customizer design for coming soon, the next login destination rule, or all categories for a "What\'s new" widget. Create them with the same slugs and import again, or choose others.',
						(int) $report['missing'],
						'adminora'
					),
					(int) $report['missing']
				),
			);
		}

		$security = array(
			'slug_invalid'         => __( 'The login address in the file cannot be used here, so the custom login address was left off.', 'adminora' ),
			'admin_slug_invalid'   => __( 'The dashboard address in the file cannot be used here, so the custom dashboard address was left off.', 'adminora' ),
			'admin_url_pending'    => __( 'The custom dashboard address is not working on this server yet, so the dashboard stays at /wp-admin. See Login protection for the server rules to add.', 'adminora' ),
			'admin_url_unwritable' => __( 'The .htaccess file could not be updated, so the dashboard stays at /wp-admin. See Login protection for the rules to add.', 'adminora' ),
		);

		if ( ! empty( $report['security'] ) && isset( $security[ $report['security'] ] ) ) {
			$lines[] = array( 'warning', $security[ $report['security'] ] );
		}

		foreach ( $lines as $line ) {
			printf(
				'<div class="notice notice-%s is-dismissible adminora-notice"><p>%s</p></div>',
				esc_attr( $line[0] ),
				esc_html( $line[1] )
			);
		}
	}

	/**
	 * Keeps only the parts Adminora knows from those ticked on the
	 * export or import form.
	 *
	 * @param string[] $parts Ticked part keys.
	 * @return string[]
	 */
	private static function known_parts( array $parts ) {
		return array_values( array_intersect( $parts, array_keys( Transfer::parts() ) ) );
	}

	/**
	 * Resets one section or everything.
	 *
	 * @return void
	 */
	public static function handle_reset() {
		check_admin_referer( 'adminora_reset' );

		if ( ! current_user_can( 'manage_options' ) ) {
			self::deny();
		}

		if ( empty( $_POST['adminora_confirm'] ) ) {
			self::back( 'tools', 'reset_confirm' );
		}

		$section = isset( $_POST['adminora_section'] ) ? sanitize_key( wp_unslash( $_POST['adminora_section'] ) ) : '';

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
		check_admin_referer( 'adminora_test_email' );

		if ( ! current_user_can( 'manage_options' ) ) {
			self::deny();
		}

		$template = isset( $_POST['template'] ) ? sanitize_key( wp_unslash( $_POST['template'] ) ) : 'welcome';
		$to       = isset( $_POST['adminora_to'] ) ? sanitize_email( wp_unslash( $_POST['adminora_to'] ) ) : '';

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
		check_admin_referer( 'adminora_email_preview' );

		if ( ! current_user_can( 'manage_options' ) ) {
			self::deny();
		}

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
		check_admin_referer( 'adminora_reset_template' );

		if ( ! current_user_can( 'manage_options' ) ) {
			self::deny();
		}

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
		check_admin_referer( 'adminora_unlock' );

		if ( ! current_user_can( 'manage_options' ) ) {
			self::deny();
		}

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
		check_admin_referer( 'adminora_clear_log' );

		if ( ! current_user_can( 'manage_options' ) ) {
			self::deny();
		}
		Attempts::clear_log();
		self::back( 'security', 'log_cleared' );
	}

	/**
	 * Plugins whose features overlap with an Adminora module.
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
			'active'     => __( 'Active', 'adminora' ),
			'conflict'   => __( 'Another plugin or your host already uses this file. Adminora leaves it alone.', 'adminora' ),
			'off'        => __( 'Off', 'adminora' ),
			'unwritable' => __( 'Cannot write to wp-content. Ask your host to make it writable, or turn this page off.', 'adminora' ),
			'missing'    => __( 'Not written yet. Save any setting to create it.', 'adminora' ),
		);
	}
}

<?php
/**
 * Settings page tabs.
 *
 * @package AdminStudio
 */

namespace AdminStudio\Admin;

use AdminStudio\Brand_Kit;
use AdminStudio\Dropins;
use AdminStudio\Files;
use AdminStudio\Settings;
use AdminStudio\Modules\Emails\Templates;
use AdminStudio\Modules\Security\Admin_Url;
use AdminStudio\Modules\Security\Attempts;
use AdminStudio\Modules\Security\Login_Url;

defined( 'ABSPATH' ) || exit;

/**
 * Renders each tab of the settings page.
 */
class Tabs {

	/**
	 * Renders a tab.
	 *
	 * @param string $tab Tab key.
	 * @return void
	 */
	public static function render( $tab ) {
		$method = 'tab_' . $tab;

		if ( method_exists( __CLASS__, $method ) ) {
			self::$method();
		}
	}

	/**
	 * Opens a settings form.
	 *
	 * @param string $tab   Tab key.
	 * @param array  $extra Extra hidden fields.
	 * @return void
	 */
	private static function form_start( $tab, array $extra = array() ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="adminstudio_save">';
		echo '<input type="hidden" name="tab" value="' . esc_attr( $tab ) . '">';
		foreach ( $extra as $name => $value ) {
			echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '">';
		}
		wp_nonce_field( 'adminstudio_save_' . $tab );
	}

	/**
	 * Closes a settings form.
	 *
	 * @return void
	 */
	private static function form_end() {
		submit_button();
		echo '</form>';
	}

	/**
	 * Section heading with an optional intro.
	 *
	 * @param string $title Heading.
	 * @param string $intro Intro text (basic HTML allowed).
	 * @return void
	 */
	private static function section( $title, $intro = '' ) {
		echo '<h2 class="title">' . esc_html( $title ) . '</h2>';
		if ( '' !== $intro ) {
			echo '<p class="adminstudio-intro">' . wp_kses_post( $intro ) . '</p>';
		}
	}

	/**
	 * Customizer link that opens a given section.
	 *
	 * @param string $section Customizer section id.
	 * @param string $label   Link text.
	 * @return string
	 */
	private static function customize_link( $section, $label ) {
		return '<a class="button" href="' . esc_url( admin_url( 'customize.php?autofocus[section]=' . $section ) ) . '">' . esc_html( $label ) . '</a>';
	}

	/**
	 * Roles and permissions.
	 *
	 * @return void
	 */
	private static function tab_roles() {
		Roles_Tab::render();
	}

	/**
	 * Overview: module switches.
	 *
	 * @return void
	 */
	private static function tab_overview() {
		$modules = array(
			'login'          => array( __( 'Login page', 'admin-studio' ), __( 'Layouts, colours, logo and text for every screen on wp-login.php.', 'admin-studio' ), 'adminstudio_login_layout' ),
			'emails'         => array( __( 'Emails', 'admin-studio' ), __( 'Branded HTML layout and editable wording for 15 core WordPress emails.', 'admin-studio' ), 'adminstudio_emails' ),
			'error_pages'    => array( __( 'Error and system pages', 'admin-studio' ), __( 'Critical error, database error, update maintenance, permission screens and an optional 404.', 'admin-studio' ), 'adminstudio_errors_design' ),
			'tab_meta'       => array( __( 'Tab and page details', 'admin-studio' ), __( 'Browser tab titles, favicon, address bar colour and hiding WordPress traces.', 'admin-studio' ), '' ),
			'admin_branding' => array( __( 'Admin branding', 'admin-studio' ), __( 'Admin bar logo, footer credit and a brand colour scheme.', 'admin-studio' ), '' ),
			'roles'          => array( __( 'Roles and permissions', 'admin-studio' ), __( 'Role editor, per-user permissions, menu access, login redirects, dashboard access and toolbar control.', 'admin-studio' ), '' ),
			'admin_look'     => array( __( 'Dashboard look', 'admin-studio' ), __( 'Admin templates with icons, colours, dark mode and a menu editor.', 'admin-studio' ), '' ),
			'widgets'        => array( __( 'Dashboard widgets', 'admin-studio' ), __( 'Show or hide widgets per role, custom widgets and a default layout.', 'admin-studio' ), '' ),
		);

		self::section(
			__( 'Brand kit', 'admin-studio' ),
			__( 'Logo, colours and fonts are read from your theme. Adjust them once in the Customizer and every area below follows.', 'admin-studio' ) . ' ' . self::customize_link( 'adminstudio_brand', __( 'Edit brand kit', 'admin-studio' ) )
		);

		self::form_start( 'overview' );
		self::section( __( 'Features', 'admin-studio' ), __( 'Switch off anything you do not need. Switched-off features load no code.', 'admin-studio' ) );

		echo '<table class="form-table" role="presentation">';
		foreach ( $modules as $key => $module ) {
			$desc = $module[1];
			if ( $module[2] && Settings::module_on( $key ) ) {
				$desc .= ' ' . self::customize_link( $module[2], __( 'Design', 'admin-studio' ) );
			}
			Fields::checkbox( 'modules.' . $key, $module[0], __( 'On', 'admin-studio' ), $desc );
		}

		Fields::row_start( __( 'Login protection', 'admin-studio' ) );
		echo esc_html( Settings::module_on( 'security' ) ? __( 'On', 'admin-studio' ) : __( 'Off', 'admin-studio' ) ) . ' &middot; <a href="' . esc_url( Admin::url( 'security' ) ) . '">' . esc_html__( 'Configure', 'admin-studio' ) . '</a>';
		Fields::row_end();

		Fields::row_start( __( 'Coming soon mode', 'admin-studio' ) );
		echo esc_html( Settings::module_on( 'maintenance' ) ? __( 'On', 'admin-studio' ) : __( 'Off', 'admin-studio' ) ) . ' &middot; <a href="' . esc_url( Admin::url( 'maintenance' ) ) . '">' . esc_html__( 'Configure', 'admin-studio' ) . '</a>';
		Fields::row_end();

		Fields::checkbox( 'general.remove_data', __( 'Uninstall', 'admin-studio' ), __( 'Remove all Admin Studio data when the plugin is deleted', 'admin-studio' ), __( 'Leave this off if you might reinstall later.', 'admin-studio' ) );
		echo '</table>';

		self::form_end();
	}

	/**
	 * Emails: one template at a time.
	 *
	 * @return void
	 */
	private static function tab_emails() {
		$templates = Templates::all();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Navigation only.
		$current = isset( $_GET['template'] ) ? sanitize_key( wp_unslash( $_GET['template'] ) ) : 'welcome';
		$current = isset( $templates[ $current ] ) ? $current : 'welcome';
		$tpl     = $templates[ $current ];
		$prefix  = 'emails.templates.' . $current . '.';

		if ( ! Settings::module_on( 'emails' ) ) {
			echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'The Emails feature is switched off on the Overview tab, so these templates are not in use.', 'admin-studio' ) . '</p></div>';
		}

		echo '<p class="adminstudio-intro">' . wp_kses_post(
			sprintf(
				/* translators: %s: Customizer link. */
				__( 'Edit the wording of each email here. Colours, logo and fonts are set in the Customizer. %s', 'admin-studio' ),
				self::customize_link( 'adminstudio_emails', __( 'Design emails', 'admin-studio' ) )
			)
		) . '</p>';

		echo '<div class="adminstudio-emails">';

		echo '<nav class="adminstudio-emails__list" aria-label="' . esc_attr__( 'Email templates', 'admin-studio' ) . '">';
		foreach ( array(
			'user'  => __( 'To users', 'admin-studio' ),
			'admin' => __( 'To admins', 'admin-studio' ),
		) as $group => $label ) {
			echo '<h3>' . esc_html( $label ) . '</h3><ul>';
			foreach ( $templates as $key => $template ) {
				if ( $template['group'] !== $group ) {
					continue;
				}
				$on = Settings::get( 'emails.templates.' . $key . '.enabled' );
				printf(
					'<li><a href="%s" class="%s"%s>%s%s</a></li>',
					esc_url( Admin::url( 'emails', array( 'template' => $key ) ) ),
					$key === $current ? 'is-current' : '',
					$key === $current ? ' aria-current="page"' : '',
					esc_html( $template['label'] ),
					$on ? '' : ' <span class="adminstudio-badge">' . esc_html__( 'Off', 'admin-studio' ) . '</span>'
				);
			}
			echo '</ul>';
		}
		echo '</nav>';

		echo '<div class="adminstudio-emails__editor">';
		echo '<h2>' . esc_html( $tpl['label'] ) . '</h2>';

		self::form_start( 'emails', array( 'template' => $current ) );
		echo '<table class="form-table" role="presentation">';
		Fields::checkbox( $prefix . 'enabled', __( 'Use this template', 'admin-studio' ), __( 'On', 'admin-studio' ), __( 'When off, WordPress sends its default plain-text email.', 'admin-studio' ) );
		Fields::text( $prefix . 'subject', __( 'Subject', 'admin-studio' ), '', array( 'class' => 'large-text' ) );
		Fields::text( $prefix . 'heading', __( 'Heading', 'admin-studio' ), '', array( 'class' => 'large-text' ) );

		Fields::row_start( __( 'Body', 'admin-studio' ), Fields::id( $prefix . 'body' ) );
		wp_editor(
			(string) Settings::get( $prefix . 'body' ),
			'adminstudio_email_body',
			array(
				'textarea_name' => Fields::name( $prefix . 'body' ),
				'textarea_rows' => 12,
				'media_buttons' => false,
				'teeny'         => false,
				'quicktags'     => true,
			)
		);
		Fields::row_end( __( 'Switch between Visual and Code (HTML) with the tabs above the editor.', 'admin-studio' ) );

		if ( '' !== $tpl['link'] ) {
			Fields::text( $prefix . 'button', __( 'Button text', 'admin-studio' ), __( 'Leave empty to hide the button.', 'admin-studio' ) );
		}

		Fields::row_start( __( 'Available tags', 'admin-studio' ) );
		echo '<div class="adminstudio-tags">';
		foreach ( array_merge( $tpl['tags'], Templates::common_tags() ) as $tag ) {
			printf( '<button type="button" class="button button-small" data-adminstudio-tag="%1$s">%1$s</button>', esc_attr( '{' . $tag . '}' ) );
		}
		echo '</div>';
		Fields::row_end( __( 'Click a tag to insert it into the body.', 'admin-studio' ) );
		echo '</table>';

		echo '<p class="submit">';
		submit_button( __( 'Save template', 'admin-studio' ), 'primary', 'submit', false );
		printf(
			' <a class="button" target="_blank" rel="noopener" href="%s">%s</a>',
			esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=adminstudio_email_preview&template=' . $current ), 'adminstudio_email_preview' ) ),
			esc_html__( 'Preview saved version', 'admin-studio' )
		);
		echo '</p></form>';

		echo '<div class="adminstudio-inline-forms">';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="adminstudio-inline-form">';
		echo '<input type="hidden" name="action" value="adminstudio_test_email"><input type="hidden" name="template" value="' . esc_attr( $current ) . '">';
		wp_nonce_field( 'adminstudio_test_email' );
		echo '<label for="adminstudio-to">' . esc_html__( 'Send a test to', 'admin-studio' ) . '</label> ';
		echo '<input type="email" id="adminstudio-to" name="adminstudio_to" class="regular-text" value="' . esc_attr( wp_get_current_user()->user_email ) . '" required> ';
		submit_button( __( 'Send test email', 'admin-studio' ), 'secondary', 'submit', false );
		echo '</form>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="adminstudio-inline-form" data-adminstudio-confirm>';
		echo '<input type="hidden" name="action" value="adminstudio_reset_template"><input type="hidden" name="template" value="' . esc_attr( $current ) . '">';
		wp_nonce_field( 'adminstudio_reset_template' );
		submit_button( __( 'Restore default text', 'admin-studio' ), 'link-delete', 'submit', false );
		echo '</form>';

		echo '</div></div></div>';
	}

	/**
	 * Tab and page details.
	 *
	 * @return void
	 */
	private static function tab_tab_meta() {
		self::form_start( 'tab_meta' );

		self::section( __( 'Browser tab titles', 'admin-studio' ), __( 'Use {page} for the screen name and {site_name} for your site title.', 'admin-studio' ) );
		echo '<table class="form-table" role="presentation">';
		Fields::text( 'tab_meta.admin_title', __( 'Admin screens', 'admin-studio' ), __( 'WordPress default: "Dashboard ‹ Site — WordPress".', 'admin-studio' ) );
		Fields::text( 'tab_meta.login_title', __( 'Login screens', 'admin-studio' ), __( 'WordPress default: "Log In ‹ Site — WordPress".', 'admin-studio' ) );
		echo '</table>';

		self::section( __( 'Icons and colours', 'admin-studio' ) );
		echo '<table class="form-table" role="presentation">';
		Fields::checkbox(
			'tab_meta.favicon',
			__( 'Favicon', 'admin-studio' ),
			__( 'Show the site icon on login, admin and system pages', 'admin-studio' ),
			sprintf(
				/* translators: %s: link to Settings > General. */
				__( 'Set the site icon in <a href="%s">Settings &rarr; General</a>. Without one, the brand logo is used.', 'admin-studio' ),
				esc_url( admin_url( 'options-general.php' ) )
			)
		);
		Fields::checkbox( 'tab_meta.theme_color', __( 'Address bar colour', 'admin-studio' ), __( 'Tint the mobile browser address bar with the brand primary colour', 'admin-studio' ) );
		echo '</table>';

		self::section( __( 'WordPress traces', 'admin-studio' ), __( 'Removes small giveaways in your page source. This is tidying, not a security feature.', 'admin-studio' ) );
		echo '<table class="form-table" role="presentation">';
		Fields::checkbox( 'tab_meta.hide_generator', __( 'Generator tag', 'admin-studio' ), __( 'Remove the WordPress version meta tag and feed generator', 'admin-studio' ) );
		Fields::checkbox( 'tab_meta.hide_versions', __( 'Asset versions', 'admin-studio' ), __( 'Remove the WordPress version from core CSS and JavaScript URLs', 'admin-studio' ) );
		Fields::checkbox( 'tab_meta.hide_rsd_wlw', __( 'Legacy links', 'admin-studio' ), __( 'Remove the RSD and Windows Live Writer links', 'admin-studio' ) );
		Fields::checkbox( 'tab_meta.hide_login_wp_link', __( 'Login logo link', 'admin-studio' ), __( 'Point the login logo to your site instead of wordpress.org', 'admin-studio' ), __( 'Applies when the Login page feature is off; that feature has its own setting.', 'admin-studio' ) );
		echo '</table>';

		self::form_end();
	}

	/**
	 * Admin branding.
	 *
	 * @return void
	 */
	private static function tab_admin_branding() {
		$keep = array(
			'keep'   => __( 'WordPress default', 'admin-studio' ),
			'custom' => __( 'Custom text', 'admin-studio' ),
			'hide'   => __( 'Remove', 'admin-studio' ),
		);

		self::form_start( 'admin_branding' );

		self::section( __( 'Admin bar', 'admin-studio' ) );
		echo '<table class="form-table" role="presentation">';
		Fields::select(
			'admin_branding.adminbar_logo',
			__( 'WordPress logo', 'admin-studio' ),
			array(
				'keep'    => __( 'Keep the WordPress logo', 'admin-studio' ),
				'replace' => __( 'Replace with my logo', 'admin-studio' ),
				'remove'  => __( 'Remove it', 'admin-studio' ),
			)
		);
		Fields::url( 'admin_branding.adminbar_image', __( 'Logo image', 'admin-studio' ), __( 'Shown 20px tall. Defaults to your site icon.', 'admin-studio' ), true, Fields::show_if( 'admin_branding.adminbar_logo', 'replace' ) );
		Fields::url( 'admin_branding.adminbar_link', __( 'Logo link', 'admin-studio' ), __( 'Defaults to your home page.', 'admin-studio' ), false, Fields::show_if( 'admin_branding.adminbar_logo', 'replace' ) );
		Fields::checkbox( 'admin_branding.hide_howdy', __( 'Greeting', 'admin-studio' ), __( 'Show just the name instead of "Howdy, Name"', 'admin-studio' ) );
		echo '</table>';

		self::section( __( 'Footer credit', 'admin-studio' ) );
		echo '<table class="form-table" role="presentation">';
		Fields::select( 'admin_branding.footer_left', __( '"Thank you for creating with WordPress"', 'admin-studio' ), $keep );
		Fields::textarea( 'admin_branding.footer_left_text', __( 'Left footer text', 'admin-studio' ), __( 'Basic HTML and {site_name}, {site_url} and {year} are allowed.', 'admin-studio' ), 2, Fields::show_if( 'admin_branding.footer_left', 'custom' ) );
		Fields::select( 'admin_branding.footer_right', __( 'WordPress version', 'admin-studio' ), $keep );
		Fields::textarea( 'admin_branding.footer_right_text', __( 'Right footer text', 'admin-studio' ), '', 2, Fields::show_if( 'admin_branding.footer_right', 'custom' ) );
		echo '</table>';

		self::section( __( 'Dashboard', 'admin-studio' ) );
		echo '<table class="form-table" role="presentation">';
		Fields::checkboxes(
			'admin_branding.hide_widgets',
			__( 'Hide widgets', 'admin-studio' ),
			array(
				'welcome_panel'         => __( 'Welcome panel', 'admin-studio' ),
				'dashboard_activity'    => __( 'Activity', 'admin-studio' ),
				'dashboard_right_now'   => __( 'At a Glance', 'admin-studio' ),
				'dashboard_quick_press' => __( 'Quick Draft', 'admin-studio' ),
				'dashboard_primary'     => __( 'WordPress Events and News', 'admin-studio' ),
				'dashboard_site_health' => __( 'Site Health Status', 'admin-studio' ),
			)
		);
		Fields::checkbox( 'admin_branding.welcome', __( 'Welcome panel', 'admin-studio' ), __( 'Add my own welcome panel to the dashboard', 'admin-studio' ) );
		Fields::text( 'admin_branding.welcome_title', __( 'Panel title', 'admin-studio' ), '', array(), Fields::show_if( 'admin_branding.welcome' ) );
		Fields::textarea( 'admin_branding.welcome_content', __( 'Panel content', 'admin-studio' ), __( 'Basic HTML is allowed.', 'admin-studio' ), 5, Fields::show_if( 'admin_branding.welcome' ) );
		Fields::text( 'admin_branding.welcome_button', __( 'Button text', 'admin-studio' ), '', array(), Fields::show_if( 'admin_branding.welcome' ) );
		Fields::url( 'admin_branding.welcome_button_url', __( 'Button link', 'admin-studio' ), '', false, Fields::show_if( 'admin_branding.welcome' ) );
		Fields::checkbox( 'admin_branding.hide_help', __( 'Help tab', 'admin-studio' ), __( 'Remove the Help tab from admin screens', 'admin-studio' ) );
		Fields::checkbox( 'admin_branding.hide_screen_opts', __( 'Screen Options', 'admin-studio' ), __( 'Remove the Screen Options tab from admin screens', 'admin-studio' ) );
		echo '</table>';

		self::section( __( 'Colour scheme', 'admin-studio' ) );
		echo '<table class="form-table" role="presentation">';
		Fields::checkbox(
			'admin_branding.color_scheme',
			__( 'Brand scheme', 'admin-studio' ),
			__( 'Add a colour scheme built from the brand kit', 'admin-studio' ),
			sprintf(
				/* translators: %s: profile URL. */
				__( 'Each user picks it under Admin Color Scheme on <a href="%s">their profile</a>.', 'admin-studio' ),
				esc_url( admin_url( 'profile.php' ) )
			)
		);
		Fields::checkbox( 'admin_branding.default_scheme', __( 'New users', 'admin-studio' ), __( 'Give new users the brand scheme by default', 'admin-studio' ), '', Fields::show_if( 'admin_branding.color_scheme' ) );
		echo '</table>';

		self::form_end();
	}

	/**
	 * Custom dashboard address settings and status, inside the security form.
	 *
	 * The test and "activate anyway" buttons belong to small forms printed after
	 * the main form, linked with the `form` attribute.
	 *
	 * @return void
	 */
	private static function admin_url_section() {
		self::section(
			__( 'Custom dashboard address', 'admin-studio' ),
			__( 'Moves the dashboard from /wp-admin to an address you choose. Logged-out visitors who open /wp-admin get a "page not found" page; logged-in users are moved to the new address, so old links keep working. If you ever get locked out, add <code>define( \'ADMINSTUDIO_DISABLE_ADMIN_URL\', true );</code> to wp-config.php.', 'admin-studio' )
		);

		if ( Admin_Url::disabled() ) {
			echo '<div class="notice notice-info inline"><p>' . esc_html__( 'Not available: this needs a single site (not multisite), and the ADMINSTUDIO_DISABLE_ADMIN_URL constant must not be set.', 'admin-studio' ) . '</p></div>';
			return;
		}

		$on     = Settings::get( 'security.admin_url' ) && '' !== Admin_Url::slug();
		$active = $on && Admin_Url::active();
		$server = Admin_Url::server();

		if ( $active ) {
			echo '<div class="notice notice-success inline"><p>' . esc_html__( 'Your dashboard is at:', 'admin-studio' ) . ' <a href="' . esc_url( Admin_Url::url() ) . '"><strong>' . esc_html( Admin_Url::url() ) . '</strong></a></p></div>';
		}

		echo '<table class="form-table" role="presentation">';
		Fields::checkbox( 'security.admin_url', __( 'Custom dashboard address', 'admin-studio' ), __( 'On', 'admin-studio' ) );
		Fields::text(
			'security.admin_slug',
			__( 'Dashboard address', 'admin-studio' ),
			__( 'Letters, numbers and dashes, for example "office". Must differ from the login address.', 'admin-studio' ),
			array(
				'prefix' => trailingslashit( site_url() ),
				'class'  => 'regular-text adminstudio-slug',
			),
			Fields::show_if( 'security.admin_url' )
		);

		if ( $on && ! $active ) {
			Fields::row_start( __( 'Server setup', 'admin-studio' ) );

			if ( 'nginx' === $server ) {
				echo '<p>' . esc_html__( 'Your site runs on Nginx. Add these lines inside the server block of your site\'s Nginx configuration, above the "location /" block, and reload Nginx. Your host can do this for you.', 'admin-studio' ) . '</p>';
				echo '<pre class="adminstudio-code">' . esc_html( Admin_Url::nginx_rules() ) . '</pre>';
			} else {
				echo '<p>' . esc_html__( 'Add these lines at the top of the .htaccess file in your site\'s root folder, above the WordPress block. If your server is not Apache or LiteSpeed, ask your host to rewrite the new address to /wp-admin.', 'admin-studio' ) . '</p>';
				echo '<pre class="adminstudio-code">' . esc_html( "# BEGIN Admin Studio\n" . implode( "\n", Admin_Url::apache_rules() ) . "\n# END Admin Studio" ) . '</pre>';
			}

			$test = untrailingslashit( site_url() ) . '/' . Admin_Url::slug() . '/admin-ajax.php?action=adminstudio_ping';

			echo '<p class="adminstudio-actions">';
			echo '<button type="submit" class="button button-primary" form="adminstudio-admin-url-test">' . esc_html__( 'Test again', 'admin-studio' ) . '</button> ';
			echo '<a class="button" target="_blank" rel="noopener" href="' . esc_url( $test ) . '">' . esc_html__( 'Open the test page', 'admin-studio' ) . '</a> ';
			echo '<button type="submit" class="button button-link-delete" form="adminstudio-admin-url-force">' . esc_html__( 'Activate anyway', 'admin-studio' ) . '</button>';
			echo '</p>';
			Fields::row_end( __( 'The test page should show {"success":true,...}. Some servers cannot test themselves; if the test page works in your browser but "Test again" fails, use "Activate anyway".', 'admin-studio' ) );
		}

		echo '</table>';
	}

	/**
	 * Small forms used by the dashboard address buttons.
	 *
	 * @return void
	 */
	private static function admin_url_forms() {
		foreach ( array( 'test', 'force' ) as $action ) {
			echo '<form id="adminstudio-admin-url-' . esc_attr( $action ) . '" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"' . ( 'force' === $action ? ' data-adminstudio-confirm' : '' ) . '>';
			echo '<input type="hidden" name="action" value="adminstudio_admin_url_' . esc_attr( $action ) . '">';
			wp_nonce_field( 'adminstudio_admin_url_' . $action );
			echo '</form>';
		}
	}

	/**
	 * Login protection.
	 *
	 * @return void
	 */
	private static function tab_security() {
		$on_url = Settings::get( 'security.login_url' ) && '' !== Login_Url::slug();

		self::form_start( 'security' );

		self::section(
			__( 'Custom login URL', 'admin-studio' ),
			__( 'Moves the login page so bots stop hammering wp-login.php. If you ever get locked out, add <code>define( \'ADMINSTUDIO_DISABLE_LOGIN_URL\', true );</code> to wp-config.php.', 'admin-studio' )
		);

		if ( $on_url ) {
			echo '<div class="notice notice-info inline"><p>' . esc_html__( 'Your login page is at:', 'admin-studio' ) . ' <a href="' . esc_url( Login_Url::url() ) . '"><strong>' . esc_html( Login_Url::url() ) . '</strong></a></p></div>';
		}

		echo '<table class="form-table" role="presentation">';
		Fields::checkbox( 'security.login_url', __( 'Custom login URL', 'admin-studio' ), __( 'On', 'admin-studio' ) );
		Fields::text(
			'security.login_slug',
			__( 'Login address', 'admin-studio' ),
			__( 'Letters, numbers and dashes. Not "login", "admin" or "dashboard".', 'admin-studio' ),
			array(
				'prefix' => trailingslashit( home_url() ),
				'class'  => 'regular-text adminstudio-slug',
			),
			Fields::show_if( 'security.login_url' )
		);
		Fields::select(
			'security.blocked_action',
			__( 'When someone opens wp-login.php or /wp-admin', 'admin-studio' ),
			array(
				'404'    => __( 'Show a "page not found" page', 'admin-studio' ),
				'home'   => __( 'Send them to the home page', 'admin-studio' ),
				'custom' => __( 'Send them to another address', 'admin-studio' ),
			),
			'',
			Fields::show_if( 'security.login_url' )
		);
		Fields::url( 'security.blocked_url', __( 'Other address', 'admin-studio' ), '', false, Fields::show_if( 'security.blocked_action', 'custom' ) );
		echo '</table>';

		self::admin_url_section();

		self::section( __( 'Limit login attempts', 'admin-studio' ) );
		echo '<table class="form-table" role="presentation">';
		Fields::checkbox( 'security.limit', __( 'Limit attempts', 'admin-studio' ), __( 'On', 'admin-studio' ) );
		Fields::number( 'security.max_attempts', __( 'Failed attempts allowed', 'admin-studio' ), '', Fields::show_if( 'security.limit' ) );
		Fields::number( 'security.window', __( 'Within (minutes)', 'admin-studio' ), '', Fields::show_if( 'security.limit' ) );
		Fields::number( 'security.lockout', __( 'Lockout length (minutes)', 'admin-studio' ), '', Fields::show_if( 'security.limit' ) );
		Fields::textarea( 'security.allowlist', __( 'Never lock out these IPs', 'admin-studio' ), __( 'One per line. IPv4 ranges such as 192.168.1.0/24 work too.', 'admin-studio' ), 3, Fields::show_if( 'security.limit' ) );
		Fields::checkbox(
			'security.trust_proxy',
			__( 'Proxy headers', 'admin-studio' ),
			__( 'My site is behind Cloudflare or another proxy', 'admin-studio' ),
			/* translators: %s: visitor IP address. */
			sprintf( __( 'Only turn this on if every request passes through a proxy, or visitors could fake their IP. Your IP as seen now: %s', 'admin-studio' ), '<code>' . esc_html( Attempts::ip() ) . '</code>' ),
			Fields::show_if( 'security.limit' )
		);
		echo '</table>';

		self::section( __( 'Honeypot', 'admin-studio' ) );
		echo '<table class="form-table" role="presentation">';
		Fields::checkbox( 'security.honeypot', __( 'Honeypot field', 'admin-studio' ), __( 'Add a hidden field to the login, registration and lost password forms', 'admin-studio' ), __( 'People never see it. Bots that fill in every field get rejected.', 'admin-studio' ) );
		echo '</table>';

		self::section(
			__( 'Two-factor authentication', 'admin-studio' ),
			__( 'Users turn it on from their profile with an authenticator app or email codes, and get one-time backup codes. If you get locked out, add <code>define( \'ADMINSTUDIO_DISABLE_2FA\', true );</code> to wp-config.php.', 'admin-studio' )
		);
		echo '<table class="form-table" role="presentation">';
		Fields::checkbox( 'security.twofa', __( 'Two-factor', 'admin-studio' ), __( 'On', 'admin-studio' ) );
		Fields::checkbox( 'security.twofa_email', __( 'Email codes', 'admin-studio' ), __( 'Allow codes by email, as a method and as a fallback', 'admin-studio' ), __( 'Email is less secure than an app but easier for most people.', 'admin-studio' ), Fields::show_if( 'security.twofa' ) );
		Fields::roles( 'security.twofa_roles', __( 'Require for these roles', 'admin-studio' ), __( 'Users with these roles must set it up before the grace period ends.', 'admin-studio' ), Fields::show_if( 'security.twofa' ) );
		Fields::number( 'security.twofa_grace', __( 'Grace period (days)', 'admin-studio' ), __( '0 means they must set it up straight away.', 'admin-studio' ), Fields::show_if( 'security.twofa' ) );
		echo '</table>';

		self::form_end();
		self::admin_url_forms();

		$log = Attempts::get_log();

		if ( ! $log ) {
			return;
		}

		self::section( __( 'Recent lockouts', 'admin-studio' ) );
		echo '<table class="widefat striped adminstudio-log"><thead><tr><th>' . esc_html__( 'When', 'admin-studio' ) . '</th><th>' . esc_html__( 'IP address', 'admin-studio' ) . '</th><th>' . esc_html__( 'Username tried', 'admin-studio' ) . '</th><th>' . esc_html__( 'Status', 'admin-studio' ) . '</th></tr></thead><tbody>';

		foreach ( array_slice( $log, 0, 50 ) as $entry ) {
			$active = $entry['until'] > time() && Attempts::locked_until( $entry['ip'] );
			echo '<tr><td>' . esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $entry['time'] ) ) . '</td>';
			echo '<td><code>' . esc_html( $entry['ip'] ) . '</code></td><td>' . esc_html( $entry['username'] ) . '</td><td>';

			if ( $active ) {
				echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="adminstudio_unlock"><input type="hidden" name="ip" value="' . esc_attr( $entry['ip'] ) . '">';
				wp_nonce_field( 'adminstudio_unlock' );
				submit_button( __( 'Unlock', 'admin-studio' ), 'small', 'submit', false );
				echo '</form>';
			} else {
				esc_html_e( 'Expired', 'admin-studio' );
			}

			echo '</td></tr>';
		}

		echo '</tbody></table>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" data-adminstudio-confirm><input type="hidden" name="action" value="adminstudio_clear_log">';
		wp_nonce_field( 'adminstudio_clear_log' );
		submit_button( __( 'Clear log', 'admin-studio' ), 'link-delete' );
		echo '</form>';
	}

	/**
	 * Coming soon / maintenance mode.
	 *
	 * @return void
	 */
	private static function tab_maintenance() {
		$key = Settings::get( 'maintenance.bypass_key' );

		self::form_start( 'maintenance' );
		self::section(
			__( 'Coming soon and maintenance mode', 'admin-studio' ),
			__( 'Visitors see a holding page while you work. Design it in the Customizer.', 'admin-studio' ) . ' ' . self::customize_link( 'adminstudio_maintenance', __( 'Design the page', 'admin-studio' ) )
		);

		echo '<table class="form-table" role="presentation">';
		Fields::checkbox( 'modules.maintenance', __( 'Mode', 'admin-studio' ), __( 'On', 'admin-studio' ), __( 'Administrators always see the real site.', 'admin-studio' ) );
		Fields::select(
			'maintenance.mode',
			__( 'Type', 'admin-studio' ),
			array(
				'coming_soon' => __( 'Coming soon (for a site that has not launched)', 'admin-studio' ),
				'maintenance' => __( 'Maintenance (tells search engines to come back later)', 'admin-studio' ),
			)
		);
		Fields::roles( 'maintenance.roles', __( 'Who can see the real site', 'admin-studio' ), __( 'Logged-in users with these roles.', 'admin-studio' ) );
		Fields::textarea( 'maintenance.allowed_paths', __( 'Always open pages', 'admin-studio' ), __( 'One path per line, such as /contact or /privacy-policy.', 'admin-studio' ), 3 );

		Fields::row_start( __( 'Bypass link', 'admin-studio' ) );
		if ( '' !== $key ) {
			$link = add_query_arg( 'adminstudio_bypass', $key, home_url( '/' ) );
			echo '<input type="hidden" name="' . esc_attr( Fields::name( 'maintenance.bypass_key' ) ) . '" value="' . esc_attr( $key ) . '">';
			echo '<code class="adminstudio-copyable">' . esc_html( $link ) . '</code>';
			echo '<p><label><input type="checkbox" name="' . esc_attr( Fields::name( 'maintenance.bypass_key' ) ) . '" value=""> ' . esc_html__( 'Remove this link', 'admin-studio' ) . '</label></p>';
		}
		echo '<p><label><input type="checkbox" name="adminstudio_new_bypass" value="1"> ' . esc_html( '' !== $key ? __( 'Create a new link (the old one stops working)', 'admin-studio' ) : __( 'Create a link', 'admin-studio' ) ) . '</label></p>';
		Fields::row_end( __( 'Anyone who opens this link sees the real site for a week. Useful for clients.', 'admin-studio' ) );
		echo '</table>';

		self::form_end();
	}

	/**
	 * Tools: import, export, reset and status.
	 *
	 * @return void
	 */
	private static function tab_tools() {
		$post = esc_url( admin_url( 'admin-post.php' ) );

		self::section( __( 'Export', 'admin-studio' ), __( 'Download every Admin Studio setting as a JSON file to copy them to another site.', 'admin-studio' ) );
		echo '<form method="post" action="' . esc_url( $post ) . '"><input type="hidden" name="action" value="adminstudio_export">';
		wp_nonce_field( 'adminstudio_export' );
		submit_button( __( 'Download settings', 'admin-studio' ), 'secondary' );
		echo '</form>';

		self::section( __( 'Import', 'admin-studio' ), __( 'Replace the current settings with an exported file. Image addresses still point to the original site.', 'admin-studio' ) );
		echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( $post ) . '" data-adminstudio-confirm><input type="hidden" name="action" value="adminstudio_import">';
		wp_nonce_field( 'adminstudio_import' );
		echo '<label for="adminstudio-file" class="screen-reader-text">' . esc_html__( 'Settings file', 'admin-studio' ) . '</label><input type="file" id="adminstudio-file" name="adminstudio_file" accept=".json,application/json" required>';
		submit_button( __( 'Import settings', 'admin-studio' ), 'secondary' );
		echo '</form>';

		self::section( __( 'Reset', 'admin-studio' ) );
		echo '<form method="post" action="' . esc_url( $post ) . '" data-adminstudio-confirm><input type="hidden" name="action" value="adminstudio_reset">';
		wp_nonce_field( 'adminstudio_reset' );
		$sections = array(
			'brand'          => __( 'Brand kit', 'admin-studio' ),
			'login'          => __( 'Login page', 'admin-studio' ),
			'emails'         => __( 'Emails', 'admin-studio' ),
			'error_pages'    => __( 'Error and system pages', 'admin-studio' ),
			'tab_meta'       => __( 'Tab and page details', 'admin-studio' ),
			'admin_branding' => __( 'Admin branding', 'admin-studio' ),
			'security'       => __( 'Login protection', 'admin-studio' ),
			'maintenance'    => __( 'Coming soon', 'admin-studio' ),
			'all'            => __( 'Everything', 'admin-studio' ),
		);
		echo '<p><label for="adminstudio-section">' . esc_html__( 'Section', 'admin-studio' ) . '</label> <select id="adminstudio-section" name="adminstudio_section">';
		foreach ( $sections as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '">' . esc_html( $label ) . '</option>';
		}
		echo '</select></p>';
		echo '<p><label><input type="checkbox" name="adminstudio_confirm" value="1" required> ' . esc_html__( 'I understand this restores the defaults for the chosen section', 'admin-studio' ) . '</label></p>';
		submit_button( __( 'Reset', 'admin-studio' ), 'delete' );
		echo '</form>';

		self::section( __( 'Status', 'admin-studio' ) );
		echo '<table class="widefat striped adminstudio-status"><tbody>';

		$labels = Admin::dropin_labels();
		$names  = array(
			'php-error.php'   => __( 'Critical error page', 'admin-studio' ),
			'db-error.php'    => __( 'Database error page', 'admin-studio' ),
			'maintenance.php' => __( 'Update maintenance page', 'admin-studio' ),
		);

		foreach ( Dropins::status() as $file => $state ) {
			printf(
				'<tr><th scope="row">%s <code>wp-content/%s</code></th><td><span class="adminstudio-state adminstudio-state--%s">%s</span></td></tr>',
				esc_html( $names[ $file ] ),
				esc_html( $file ),
				esc_attr( $state ),
				esc_html( $labels[ $state ] )
			);
		}

		printf(
			'<tr><th scope="row">%s</th><td>%s</td></tr>',
			esc_html__( 'wp-content writable', 'admin-studio' ),
			Files::writable( WP_CONTENT_DIR ) ? esc_html__( 'Yes', 'admin-studio' ) : esc_html__( 'No', 'admin-studio' )
		);

		$detected = Brand_Kit::detected();
		echo '<tr><th scope="row">' . esc_html__( 'Colours detected from the theme', 'admin-studio' ) . '</th><td><div class="adminstudio-swatches">';
		$found = false;
		foreach ( Brand_Kit::COLORS as $color ) {
			if ( ! empty( $detected[ $color ] ) ) {
				$found = true;
				printf(
					'<span class="adminstudio-swatch"><span class="adminstudio-swatch__chip" data-color="%1$s"></span>%2$s <code>%1$s</code></span>',
					esc_attr( $detected[ $color ] ),
					esc_html( $color )
				);
			}
		}
		if ( ! $found ) {
			esc_html_e( 'None. The built-in palette is used until you pick colours in the brand kit.', 'admin-studio' );
		}
		echo '</div></td></tr>';

		$conflicts = Admin::conflicts();
		echo '<tr><th scope="row">' . esc_html__( 'Overlapping plugins', 'admin-studio' ) . '</th><td>';
		if ( $conflicts ) {
			echo '<ul class="adminstudio-conflicts">';
			foreach ( $conflicts as $name => $module ) {
				/* translators: 1: plugin name, 2: Admin Studio feature name. */
				echo '<li>' . esc_html( sprintf( __( '%1$s does similar things to the %2$s feature. Use one or the other to avoid surprises.', 'admin-studio' ), $name, $sections[ $module ] ) ) . '</li>';
			}
			echo '</ul>';
		} else {
			esc_html_e( 'None found.', 'admin-studio' );
		}
		echo '</td></tr>';

		echo '</tbody></table>';
	}
}

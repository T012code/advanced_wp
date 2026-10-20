<?php
/**
 * Settings page tabs.
 *
 * @package AdvancedWP
 */

namespace AdvancedWP\Admin;

use AdvancedWP\Brand_Kit;
use AdvancedWP\Dropins;
use AdvancedWP\Files;
use AdvancedWP\Settings;
use AdvancedWP\Modules\Emails\Templates;
use AdvancedWP\Modules\Security\Attempts;
use AdvancedWP\Modules\Security\Login_Url;

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
		echo '<input type="hidden" name="action" value="advwp_save">';
		echo '<input type="hidden" name="tab" value="' . esc_attr( $tab ) . '">';
		foreach ( $extra as $name => $value ) {
			echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '">';
		}
		wp_nonce_field( 'advwp_save_' . $tab );
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
			echo '<p class="advwp-intro">' . wp_kses_post( $intro ) . '</p>';
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
	 * Overview: module switches.
	 *
	 * @return void
	 */
	private static function tab_overview() {
		$modules = array(
			'login'          => array( __( 'Login page', 'advanced-wp' ), __( 'Layouts, colours, logo and text for every screen on wp-login.php.', 'advanced-wp' ), 'advwp_login_layout' ),
			'emails'         => array( __( 'Emails', 'advanced-wp' ), __( 'Branded HTML layout and editable wording for 15 core WordPress emails.', 'advanced-wp' ), 'advwp_emails' ),
			'error_pages'    => array( __( 'Error and system pages', 'advanced-wp' ), __( 'Critical error, database error, update maintenance, permission screens and an optional 404.', 'advanced-wp' ), 'advwp_errors_design' ),
			'tab_meta'       => array( __( 'Tab and page details', 'advanced-wp' ), __( 'Browser tab titles, favicon, address bar colour and hiding WordPress traces.', 'advanced-wp' ), '' ),
			'admin_branding' => array( __( 'Admin branding', 'advanced-wp' ), __( 'Admin bar logo, footer credit, dashboard widgets and a brand colour scheme.', 'advanced-wp' ), '' ),
		);

		self::section(
			__( 'Brand kit', 'advanced-wp' ),
			__( 'Logo, colours and fonts are read from your theme. Adjust them once in the Customizer and every area below follows.', 'advanced-wp' ) . ' ' . self::customize_link( 'advwp_brand', __( 'Edit brand kit', 'advanced-wp' ) )
		);

		self::form_start( 'overview' );
		self::section( __( 'Features', 'advanced-wp' ), __( 'Switch off anything you do not need. Switched-off features load no code.', 'advanced-wp' ) );

		echo '<table class="form-table" role="presentation">';
		foreach ( $modules as $key => $module ) {
			$desc = $module[1];
			if ( $module[2] && Settings::module_on( $key ) ) {
				$desc .= ' ' . self::customize_link( $module[2], __( 'Design', 'advanced-wp' ) );
			}
			Fields::checkbox( 'modules.' . $key, $module[0], __( 'On', 'advanced-wp' ), $desc );
		}

		Fields::row_start( __( 'Login protection', 'advanced-wp' ) );
		echo esc_html( Settings::module_on( 'security' ) ? __( 'On', 'advanced-wp' ) : __( 'Off', 'advanced-wp' ) ) . ' &middot; <a href="' . esc_url( Admin::url( 'security' ) ) . '">' . esc_html__( 'Configure', 'advanced-wp' ) . '</a>';
		Fields::row_end();

		Fields::row_start( __( 'Coming soon mode', 'advanced-wp' ) );
		echo esc_html( Settings::module_on( 'maintenance' ) ? __( 'On', 'advanced-wp' ) : __( 'Off', 'advanced-wp' ) ) . ' &middot; <a href="' . esc_url( Admin::url( 'maintenance' ) ) . '">' . esc_html__( 'Configure', 'advanced-wp' ) . '</a>';
		Fields::row_end();

		Fields::checkbox( 'general.remove_data', __( 'Uninstall', 'advanced-wp' ), __( 'Remove all Advanced WP data when the plugin is deleted', 'advanced-wp' ), __( 'Leave this off if you might reinstall later.', 'advanced-wp' ) );
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
			echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'The Emails feature is switched off on the Overview tab, so these templates are not in use.', 'advanced-wp' ) . '</p></div>';
		}

		echo '<p class="advwp-intro">' . wp_kses_post(
			sprintf(
				/* translators: %s: Customizer link. */
				__( 'Edit the wording of each email here. Colours, logo and fonts are set in the Customizer. %s', 'advanced-wp' ),
				self::customize_link( 'advwp_emails', __( 'Design emails', 'advanced-wp' ) )
			)
		) . '</p>';

		echo '<div class="advwp-emails">';

		echo '<nav class="advwp-emails__list" aria-label="' . esc_attr__( 'Email templates', 'advanced-wp' ) . '">';
		foreach ( array(
			'user'  => __( 'To users', 'advanced-wp' ),
			'admin' => __( 'To admins', 'advanced-wp' ),
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
					$on ? '' : ' <span class="advwp-badge">' . esc_html__( 'Off', 'advanced-wp' ) . '</span>'
				);
			}
			echo '</ul>';
		}
		echo '</nav>';

		echo '<div class="advwp-emails__editor">';
		echo '<h2>' . esc_html( $tpl['label'] ) . '</h2>';

		self::form_start( 'emails', array( 'template' => $current ) );
		echo '<table class="form-table" role="presentation">';
		Fields::checkbox( $prefix . 'enabled', __( 'Use this template', 'advanced-wp' ), __( 'On', 'advanced-wp' ), __( 'When off, WordPress sends its default plain-text email.', 'advanced-wp' ) );
		Fields::text( $prefix . 'subject', __( 'Subject', 'advanced-wp' ), '', array( 'class' => 'large-text' ) );
		Fields::text( $prefix . 'heading', __( 'Heading', 'advanced-wp' ), '', array( 'class' => 'large-text' ) );

		Fields::row_start( __( 'Body', 'advanced-wp' ), Fields::id( $prefix . 'body' ) );
		wp_editor(
			(string) Settings::get( $prefix . 'body' ),
			'advwp_email_body',
			array(
				'textarea_name' => Fields::name( $prefix . 'body' ),
				'textarea_rows' => 12,
				'media_buttons' => false,
				'teeny'         => false,
				'quicktags'     => true,
			)
		);
		Fields::row_end( __( 'Switch between Visual and Code (HTML) with the tabs above the editor.', 'advanced-wp' ) );

		if ( '' !== $tpl['link'] ) {
			Fields::text( $prefix . 'button', __( 'Button text', 'advanced-wp' ), __( 'Leave empty to hide the button.', 'advanced-wp' ) );
		}

		Fields::row_start( __( 'Available tags', 'advanced-wp' ) );
		echo '<div class="advwp-tags">';
		foreach ( array_merge( $tpl['tags'], Templates::common_tags() ) as $tag ) {
			printf( '<button type="button" class="button button-small" data-advwp-tag="%1$s">%1$s</button>', esc_attr( '{' . $tag . '}' ) );
		}
		echo '</div>';
		Fields::row_end( __( 'Click a tag to insert it into the body.', 'advanced-wp' ) );
		echo '</table>';

		echo '<p class="submit">';
		submit_button( __( 'Save template', 'advanced-wp' ), 'primary', 'submit', false );
		printf(
			' <a class="button" target="_blank" rel="noopener" href="%s">%s</a>',
			esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=advwp_email_preview&template=' . $current ), 'advwp_email_preview' ) ),
			esc_html__( 'Preview saved version', 'advanced-wp' )
		);
		echo '</p></form>';

		echo '<div class="advwp-inline-forms">';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="advwp-inline-form">';
		echo '<input type="hidden" name="action" value="advwp_test_email"><input type="hidden" name="template" value="' . esc_attr( $current ) . '">';
		wp_nonce_field( 'advwp_test_email' );
		echo '<label for="advwp-to">' . esc_html__( 'Send a test to', 'advanced-wp' ) . '</label> ';
		echo '<input type="email" id="advwp-to" name="advwp_to" class="regular-text" value="' . esc_attr( wp_get_current_user()->user_email ) . '" required> ';
		submit_button( __( 'Send test email', 'advanced-wp' ), 'secondary', 'submit', false );
		echo '</form>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="advwp-inline-form" data-advwp-confirm>';
		echo '<input type="hidden" name="action" value="advwp_reset_template"><input type="hidden" name="template" value="' . esc_attr( $current ) . '">';
		wp_nonce_field( 'advwp_reset_template' );
		submit_button( __( 'Restore default text', 'advanced-wp' ), 'link-delete', 'submit', false );
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

		self::section( __( 'Browser tab titles', 'advanced-wp' ), __( 'Use {page} for the screen name and {site_name} for your site title.', 'advanced-wp' ) );
		echo '<table class="form-table" role="presentation">';
		Fields::text( 'tab_meta.admin_title', __( 'Admin screens', 'advanced-wp' ), __( 'WordPress default: "Dashboard ‹ Site — WordPress".', 'advanced-wp' ) );
		Fields::text( 'tab_meta.login_title', __( 'Login screens', 'advanced-wp' ), __( 'WordPress default: "Log In ‹ Site — WordPress".', 'advanced-wp' ) );
		echo '</table>';

		self::section( __( 'Icons and colours', 'advanced-wp' ) );
		echo '<table class="form-table" role="presentation">';
		Fields::checkbox(
			'tab_meta.favicon',
			__( 'Favicon', 'advanced-wp' ),
			__( 'Show the site icon on login, admin and system pages', 'advanced-wp' ),
			sprintf(
				/* translators: %s: link to Settings > General. */
				__( 'Set the site icon in <a href="%s">Settings &rarr; General</a>. Without one, the brand logo is used.', 'advanced-wp' ),
				esc_url( admin_url( 'options-general.php' ) )
			)
		);
		Fields::checkbox( 'tab_meta.theme_color', __( 'Address bar colour', 'advanced-wp' ), __( 'Tint the mobile browser address bar with the brand primary colour', 'advanced-wp' ) );
		echo '</table>';

		self::section( __( 'WordPress traces', 'advanced-wp' ), __( 'Removes small giveaways in your page source. This is tidying, not a security feature.', 'advanced-wp' ) );
		echo '<table class="form-table" role="presentation">';
		Fields::checkbox( 'tab_meta.hide_generator', __( 'Generator tag', 'advanced-wp' ), __( 'Remove the WordPress version meta tag and feed generator', 'advanced-wp' ) );
		Fields::checkbox( 'tab_meta.hide_versions', __( 'Asset versions', 'advanced-wp' ), __( 'Remove the WordPress version from core CSS and JavaScript URLs', 'advanced-wp' ) );
		Fields::checkbox( 'tab_meta.hide_rsd_wlw', __( 'Legacy links', 'advanced-wp' ), __( 'Remove the RSD and Windows Live Writer links', 'advanced-wp' ) );
		Fields::checkbox( 'tab_meta.hide_login_wp_link', __( 'Login logo link', 'advanced-wp' ), __( 'Point the login logo to your site instead of wordpress.org', 'advanced-wp' ), __( 'Applies when the Login page feature is off; that feature has its own setting.', 'advanced-wp' ) );
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
			'keep'   => __( 'WordPress default', 'advanced-wp' ),
			'custom' => __( 'Custom text', 'advanced-wp' ),
			'hide'   => __( 'Remove', 'advanced-wp' ),
		);

		self::form_start( 'admin_branding' );

		self::section( __( 'Admin bar', 'advanced-wp' ) );
		echo '<table class="form-table" role="presentation">';
		Fields::select(
			'admin_branding.adminbar_logo',
			__( 'WordPress logo', 'advanced-wp' ),
			array(
				'keep'    => __( 'Keep the WordPress logo', 'advanced-wp' ),
				'replace' => __( 'Replace with my logo', 'advanced-wp' ),
				'remove'  => __( 'Remove it', 'advanced-wp' ),
			)
		);
		Fields::url( 'admin_branding.adminbar_image', __( 'Logo image', 'advanced-wp' ), __( 'Shown 20px tall. Defaults to your site icon.', 'advanced-wp' ), true, Fields::show_if( 'admin_branding.adminbar_logo', 'replace' ) );
		Fields::url( 'admin_branding.adminbar_link', __( 'Logo link', 'advanced-wp' ), __( 'Defaults to your home page.', 'advanced-wp' ), false, Fields::show_if( 'admin_branding.adminbar_logo', 'replace' ) );
		Fields::checkbox( 'admin_branding.hide_howdy', __( 'Greeting', 'advanced-wp' ), __( 'Show just the name instead of "Howdy, Name"', 'advanced-wp' ) );
		echo '</table>';

		self::section( __( 'Footer credit', 'advanced-wp' ) );
		echo '<table class="form-table" role="presentation">';
		Fields::select( 'admin_branding.footer_left', __( '"Thank you for creating with WordPress"', 'advanced-wp' ), $keep );
		Fields::textarea( 'admin_branding.footer_left_text', __( 'Left footer text', 'advanced-wp' ), __( 'Basic HTML and {site_name}, {site_url} and {year} are allowed.', 'advanced-wp' ), 2, Fields::show_if( 'admin_branding.footer_left', 'custom' ) );
		Fields::select( 'admin_branding.footer_right', __( 'WordPress version', 'advanced-wp' ), $keep );
		Fields::textarea( 'admin_branding.footer_right_text', __( 'Right footer text', 'advanced-wp' ), '', 2, Fields::show_if( 'admin_branding.footer_right', 'custom' ) );
		echo '</table>';

		self::section( __( 'Dashboard', 'advanced-wp' ) );
		echo '<table class="form-table" role="presentation">';
		Fields::checkboxes(
			'admin_branding.hide_widgets',
			__( 'Hide widgets', 'advanced-wp' ),
			array(
				'welcome_panel'         => __( 'Welcome panel', 'advanced-wp' ),
				'dashboard_activity'    => __( 'Activity', 'advanced-wp' ),
				'dashboard_right_now'   => __( 'At a Glance', 'advanced-wp' ),
				'dashboard_quick_press' => __( 'Quick Draft', 'advanced-wp' ),
				'dashboard_primary'     => __( 'WordPress Events and News', 'advanced-wp' ),
				'dashboard_site_health' => __( 'Site Health Status', 'advanced-wp' ),
			)
		);
		Fields::checkbox( 'admin_branding.welcome', __( 'Welcome panel', 'advanced-wp' ), __( 'Add my own welcome panel to the dashboard', 'advanced-wp' ) );
		Fields::text( 'admin_branding.welcome_title', __( 'Panel title', 'advanced-wp' ), '', array(), Fields::show_if( 'admin_branding.welcome' ) );
		Fields::textarea( 'admin_branding.welcome_content', __( 'Panel content', 'advanced-wp' ), __( 'Basic HTML is allowed.', 'advanced-wp' ), 5, Fields::show_if( 'admin_branding.welcome' ) );
		Fields::text( 'admin_branding.welcome_button', __( 'Button text', 'advanced-wp' ), '', array(), Fields::show_if( 'admin_branding.welcome' ) );
		Fields::url( 'admin_branding.welcome_button_url', __( 'Button link', 'advanced-wp' ), '', false, Fields::show_if( 'admin_branding.welcome' ) );
		Fields::checkbox( 'admin_branding.hide_help', __( 'Help tab', 'advanced-wp' ), __( 'Remove the Help tab from admin screens', 'advanced-wp' ) );
		Fields::checkbox( 'admin_branding.hide_screen_opts', __( 'Screen Options', 'advanced-wp' ), __( 'Remove the Screen Options tab from admin screens', 'advanced-wp' ) );
		echo '</table>';

		self::section( __( 'Colour scheme', 'advanced-wp' ) );
		echo '<table class="form-table" role="presentation">';
		Fields::checkbox(
			'admin_branding.color_scheme',
			__( 'Brand scheme', 'advanced-wp' ),
			__( 'Add a colour scheme built from the brand kit', 'advanced-wp' ),
			sprintf(
				/* translators: %s: profile URL. */
				__( 'Each user picks it under Admin Color Scheme on <a href="%s">their profile</a>.', 'advanced-wp' ),
				esc_url( admin_url( 'profile.php' ) )
			)
		);
		Fields::checkbox( 'admin_branding.default_scheme', __( 'New users', 'advanced-wp' ), __( 'Give new users the brand scheme by default', 'advanced-wp' ), '', Fields::show_if( 'admin_branding.color_scheme' ) );
		echo '</table>';

		self::form_end();
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
			__( 'Custom login URL', 'advanced-wp' ),
			__( 'Moves the login page so bots stop hammering wp-login.php. If you ever get locked out, add <code>define( \'ADVWP_DISABLE_LOGIN_URL\', true );</code> to wp-config.php.', 'advanced-wp' )
		);

		if ( $on_url ) {
			echo '<div class="notice notice-info inline"><p>' . esc_html__( 'Your login page is at:', 'advanced-wp' ) . ' <a href="' . esc_url( Login_Url::url() ) . '"><strong>' . esc_html( Login_Url::url() ) . '</strong></a></p></div>';
		}

		echo '<table class="form-table" role="presentation">';
		Fields::checkbox( 'security.login_url', __( 'Custom login URL', 'advanced-wp' ), __( 'On', 'advanced-wp' ) );
		Fields::text(
			'security.login_slug',
			__( 'Login address', 'advanced-wp' ),
			__( 'Letters, numbers and dashes. Not "login", "admin" or "dashboard".', 'advanced-wp' ),
			array(
				'prefix' => trailingslashit( home_url() ),
				'class'  => 'regular-text advwp-slug',
			),
			Fields::show_if( 'security.login_url' )
		);
		Fields::select(
			'security.blocked_action',
			__( 'When someone opens wp-login.php or /wp-admin', 'advanced-wp' ),
			array(
				'404'    => __( 'Show a "page not found" page', 'advanced-wp' ),
				'home'   => __( 'Send them to the home page', 'advanced-wp' ),
				'custom' => __( 'Send them to another address', 'advanced-wp' ),
			),
			'',
			Fields::show_if( 'security.login_url' )
		);
		Fields::url( 'security.blocked_url', __( 'Other address', 'advanced-wp' ), '', false, Fields::show_if( 'security.blocked_action', 'custom' ) );
		echo '</table>';

		self::section( __( 'Limit login attempts', 'advanced-wp' ) );
		echo '<table class="form-table" role="presentation">';
		Fields::checkbox( 'security.limit', __( 'Limit attempts', 'advanced-wp' ), __( 'On', 'advanced-wp' ) );
		Fields::number( 'security.max_attempts', __( 'Failed attempts allowed', 'advanced-wp' ), '', Fields::show_if( 'security.limit' ) );
		Fields::number( 'security.window', __( 'Within (minutes)', 'advanced-wp' ), '', Fields::show_if( 'security.limit' ) );
		Fields::number( 'security.lockout', __( 'Lockout length (minutes)', 'advanced-wp' ), '', Fields::show_if( 'security.limit' ) );
		Fields::textarea( 'security.allowlist', __( 'Never lock out these IPs', 'advanced-wp' ), __( 'One per line. IPv4 ranges such as 192.168.1.0/24 work too.', 'advanced-wp' ), 3, Fields::show_if( 'security.limit' ) );
		Fields::checkbox(
			'security.trust_proxy',
			__( 'Proxy headers', 'advanced-wp' ),
			__( 'My site is behind Cloudflare or another proxy', 'advanced-wp' ),
			/* translators: %s: visitor IP address. */
			sprintf( __( 'Only turn this on if every request passes through a proxy, or visitors could fake their IP. Your IP as seen now: %s', 'advanced-wp' ), '<code>' . esc_html( Attempts::ip() ) . '</code>' ),
			Fields::show_if( 'security.limit' )
		);
		echo '</table>';

		self::section( __( 'Honeypot', 'advanced-wp' ) );
		echo '<table class="form-table" role="presentation">';
		Fields::checkbox( 'security.honeypot', __( 'Honeypot field', 'advanced-wp' ), __( 'Add a hidden field to the login, registration and lost password forms', 'advanced-wp' ), __( 'People never see it. Bots that fill in every field get rejected.', 'advanced-wp' ) );
		echo '</table>';

		self::section(
			__( 'Two-factor authentication', 'advanced-wp' ),
			__( 'Users turn it on from their profile with an authenticator app or email codes, and get one-time backup codes. If you get locked out, add <code>define( \'ADVWP_DISABLE_2FA\', true );</code> to wp-config.php.', 'advanced-wp' )
		);
		echo '<table class="form-table" role="presentation">';
		Fields::checkbox( 'security.twofa', __( 'Two-factor', 'advanced-wp' ), __( 'On', 'advanced-wp' ) );
		Fields::checkbox( 'security.twofa_email', __( 'Email codes', 'advanced-wp' ), __( 'Allow codes by email, as a method and as a fallback', 'advanced-wp' ), __( 'Email is less secure than an app but easier for most people.', 'advanced-wp' ), Fields::show_if( 'security.twofa' ) );
		Fields::roles( 'security.twofa_roles', __( 'Require for these roles', 'advanced-wp' ), __( 'Users with these roles must set it up before the grace period ends.', 'advanced-wp' ), Fields::show_if( 'security.twofa' ) );
		Fields::number( 'security.twofa_grace', __( 'Grace period (days)', 'advanced-wp' ), __( '0 means they must set it up straight away.', 'advanced-wp' ), Fields::show_if( 'security.twofa' ) );
		echo '</table>';

		self::form_end();

		$log = Attempts::get_log();

		if ( ! $log ) {
			return;
		}

		self::section( __( 'Recent lockouts', 'advanced-wp' ) );
		echo '<table class="widefat striped advwp-log"><thead><tr><th>' . esc_html__( 'When', 'advanced-wp' ) . '</th><th>' . esc_html__( 'IP address', 'advanced-wp' ) . '</th><th>' . esc_html__( 'Username tried', 'advanced-wp' ) . '</th><th>' . esc_html__( 'Status', 'advanced-wp' ) . '</th></tr></thead><tbody>';

		foreach ( array_slice( $log, 0, 50 ) as $entry ) {
			$active = $entry['until'] > time() && Attempts::locked_until( $entry['ip'] );
			echo '<tr><td>' . esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $entry['time'] ) ) . '</td>';
			echo '<td><code>' . esc_html( $entry['ip'] ) . '</code></td><td>' . esc_html( $entry['username'] ) . '</td><td>';

			if ( $active ) {
				echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="advwp_unlock"><input type="hidden" name="ip" value="' . esc_attr( $entry['ip'] ) . '">';
				wp_nonce_field( 'advwp_unlock' );
				submit_button( __( 'Unlock', 'advanced-wp' ), 'small', 'submit', false );
				echo '</form>';
			} else {
				esc_html_e( 'Expired', 'advanced-wp' );
			}

			echo '</td></tr>';
		}

		echo '</tbody></table>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" data-advwp-confirm><input type="hidden" name="action" value="advwp_clear_log">';
		wp_nonce_field( 'advwp_clear_log' );
		submit_button( __( 'Clear log', 'advanced-wp' ), 'link-delete' );
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
			__( 'Coming soon and maintenance mode', 'advanced-wp' ),
			__( 'Visitors see a holding page while you work. Design it in the Customizer.', 'advanced-wp' ) . ' ' . self::customize_link( 'advwp_maintenance', __( 'Design the page', 'advanced-wp' ) )
		);

		echo '<table class="form-table" role="presentation">';
		Fields::checkbox( 'modules.maintenance', __( 'Mode', 'advanced-wp' ), __( 'On', 'advanced-wp' ), __( 'Administrators always see the real site.', 'advanced-wp' ) );
		Fields::select(
			'maintenance.mode',
			__( 'Type', 'advanced-wp' ),
			array(
				'coming_soon' => __( 'Coming soon (for a site that has not launched)', 'advanced-wp' ),
				'maintenance' => __( 'Maintenance (tells search engines to come back later)', 'advanced-wp' ),
			)
		);
		Fields::roles( 'maintenance.roles', __( 'Who can see the real site', 'advanced-wp' ), __( 'Logged-in users with these roles.', 'advanced-wp' ) );
		Fields::textarea( 'maintenance.allowed_paths', __( 'Always open pages', 'advanced-wp' ), __( 'One path per line, such as /contact or /privacy-policy.', 'advanced-wp' ), 3 );

		Fields::row_start( __( 'Bypass link', 'advanced-wp' ) );
		if ( '' !== $key ) {
			$link = add_query_arg( 'advwp_bypass', $key, home_url( '/' ) );
			echo '<input type="hidden" name="' . esc_attr( Fields::name( 'maintenance.bypass_key' ) ) . '" value="' . esc_attr( $key ) . '">';
			echo '<code class="advwp-copyable">' . esc_html( $link ) . '</code>';
			echo '<p><label><input type="checkbox" name="' . esc_attr( Fields::name( 'maintenance.bypass_key' ) ) . '" value=""> ' . esc_html__( 'Remove this link', 'advanced-wp' ) . '</label></p>';
		}
		echo '<p><label><input type="checkbox" name="advwp_new_bypass" value="1"> ' . esc_html( '' !== $key ? __( 'Create a new link (the old one stops working)', 'advanced-wp' ) : __( 'Create a link', 'advanced-wp' ) ) . '</label></p>';
		Fields::row_end( __( 'Anyone who opens this link sees the real site for a week. Useful for clients.', 'advanced-wp' ) );
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

		self::section( __( 'Export', 'advanced-wp' ), __( 'Download every Advanced WP setting as a JSON file to copy them to another site.', 'advanced-wp' ) );
		echo '<form method="post" action="' . esc_url( $post ) . '"><input type="hidden" name="action" value="advwp_export">';
		wp_nonce_field( 'advwp_export' );
		submit_button( __( 'Download settings', 'advanced-wp' ), 'secondary' );
		echo '</form>';

		self::section( __( 'Import', 'advanced-wp' ), __( 'Replace the current settings with an exported file. Image addresses still point to the original site.', 'advanced-wp' ) );
		echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( $post ) . '" data-advwp-confirm><input type="hidden" name="action" value="advwp_import">';
		wp_nonce_field( 'advwp_import' );
		echo '<label for="advwp-file" class="screen-reader-text">' . esc_html__( 'Settings file', 'advanced-wp' ) . '</label><input type="file" id="advwp-file" name="advwp_file" accept=".json,application/json" required>';
		submit_button( __( 'Import settings', 'advanced-wp' ), 'secondary' );
		echo '</form>';

		self::section( __( 'Reset', 'advanced-wp' ) );
		echo '<form method="post" action="' . esc_url( $post ) . '" data-advwp-confirm><input type="hidden" name="action" value="advwp_reset">';
		wp_nonce_field( 'advwp_reset' );
		$sections = array(
			'brand'          => __( 'Brand kit', 'advanced-wp' ),
			'login'          => __( 'Login page', 'advanced-wp' ),
			'emails'         => __( 'Emails', 'advanced-wp' ),
			'error_pages'    => __( 'Error and system pages', 'advanced-wp' ),
			'tab_meta'       => __( 'Tab and page details', 'advanced-wp' ),
			'admin_branding' => __( 'Admin branding', 'advanced-wp' ),
			'security'       => __( 'Login protection', 'advanced-wp' ),
			'maintenance'    => __( 'Coming soon', 'advanced-wp' ),
			'all'            => __( 'Everything', 'advanced-wp' ),
		);
		echo '<p><label for="advwp-section">' . esc_html__( 'Section', 'advanced-wp' ) . '</label> <select id="advwp-section" name="advwp_section">';
		foreach ( $sections as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '">' . esc_html( $label ) . '</option>';
		}
		echo '</select></p>';
		echo '<p><label><input type="checkbox" name="advwp_confirm" value="1" required> ' . esc_html__( 'I understand this restores the defaults for the chosen section', 'advanced-wp' ) . '</label></p>';
		submit_button( __( 'Reset', 'advanced-wp' ), 'delete' );
		echo '</form>';

		self::section( __( 'Status', 'advanced-wp' ) );
		echo '<table class="widefat striped advwp-status"><tbody>';

		$labels = Admin::dropin_labels();
		$names  = array(
			'php-error.php'   => __( 'Critical error page', 'advanced-wp' ),
			'db-error.php'    => __( 'Database error page', 'advanced-wp' ),
			'maintenance.php' => __( 'Update maintenance page', 'advanced-wp' ),
		);

		foreach ( Dropins::status() as $file => $state ) {
			printf(
				'<tr><th scope="row">%s <code>wp-content/%s</code></th><td><span class="advwp-state advwp-state--%s">%s</span></td></tr>',
				esc_html( $names[ $file ] ),
				esc_html( $file ),
				esc_attr( $state ),
				esc_html( $labels[ $state ] )
			);
		}

		printf(
			'<tr><th scope="row">%s</th><td>%s</td></tr>',
			esc_html__( 'wp-content writable', 'advanced-wp' ),
			Files::writable( WP_CONTENT_DIR ) ? esc_html__( 'Yes', 'advanced-wp' ) : esc_html__( 'No', 'advanced-wp' )
		);

		$detected = Brand_Kit::detected();
		echo '<tr><th scope="row">' . esc_html__( 'Colours detected from the theme', 'advanced-wp' ) . '</th><td><div class="advwp-swatches">';
		$found = false;
		foreach ( Brand_Kit::COLORS as $color ) {
			if ( ! empty( $detected[ $color ] ) ) {
				$found = true;
				printf(
					'<span class="advwp-swatch"><span class="advwp-swatch__chip" data-color="%1$s"></span>%2$s <code>%1$s</code></span>',
					esc_attr( $detected[ $color ] ),
					esc_html( $color )
				);
			}
		}
		if ( ! $found ) {
			esc_html_e( 'None. The built-in palette is used until you pick colours in the brand kit.', 'advanced-wp' );
		}
		echo '</div></td></tr>';

		$conflicts = Admin::conflicts();
		echo '<tr><th scope="row">' . esc_html__( 'Overlapping plugins', 'advanced-wp' ) . '</th><td>';
		if ( $conflicts ) {
			echo '<ul class="advwp-conflicts">';
			foreach ( $conflicts as $name => $module ) {
				/* translators: 1: plugin name, 2: Advanced WP feature name. */
				echo '<li>' . esc_html( sprintf( __( '%1$s does similar things to the %2$s feature. Use one or the other to avoid surprises.', 'advanced-wp' ), $name, $sections[ $module ] ) ) . '</li>';
			}
			echo '</ul>';
		} else {
			esc_html_e( 'None found.', 'advanced-wp' );
		}
		echo '</td></tr>';

		echo '</tbody></table>';
	}
}

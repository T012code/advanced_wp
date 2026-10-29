<?php
/**
 * Settings page tabs.
 *
 * @package Adminora
 */

namespace Adminora\Admin;

use Adminora\Brand_Kit;
use Adminora\Dropins;
use Adminora\Files;
use Adminora\Settings;
use Adminora\Modules\Emails\Templates;
use Adminora\Modules\Maintenance\Maintenance;
use Adminora\Modules\Security\Admin_Url;
use Adminora\Modules\Security\Attempts;
use Adminora\Modules\Security\Login_Url;

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
		echo '<input type="hidden" name="action" value="adminora_save">';
		echo '<input type="hidden" name="tab" value="' . esc_attr( $tab ) . '">';
		foreach ( $extra as $name => $value ) {
			echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '">';
		}
		wp_nonce_field( 'adminora_save' );
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
			echo '<p class="adminora-intro">' . wp_kses_post( $intro ) . '</p>';
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
	 * Dashboard look.
	 *
	 * @return void
	 */
	private static function tab_look() {
		Look_Tab::render();
	}

	/**
	 * Dashboard widgets.
	 *
	 * @return void
	 */
	private static function tab_widgets() {
		Widgets_Tab::render();
	}

	/**
	 * Sessions.
	 *
	 * @return void
	 */
	private static function tab_sessions() {
		Sessions_Tab::render();
	}

	/**
	 * Colour swatches.
	 *
	 * @param array $colors Name => hex.
	 * @return void
	 */
	private static function swatches( array $colors ) {
		foreach ( array( 'primary', 'secondary', 'text', 'background' ) as $key ) {
			if ( ! empty( $colors[ $key ] ) ) {
				printf(
					'<span class="adminora-swatch__chip" data-color="%1$s" title="%2$s %1$s"></span>',
					esc_attr( $colors[ $key ] ),
					esc_attr( $key )
				);
			}
		}
	}

	/**
	 * Brand kit card: where the colours come from.
	 *
	 * @return void
	 */
	private static function brand_kit() {
		$current = Brand_Kit::source();
		$logo    = Brand_Kit::logo_url();
		$stored  = get_option( Brand_Kit::PALETTES, array() );
		$read_at = isset( $stored['frontend']['time'] ) ? (int) $stored['frontend']['time'] : 0;

		$options = array(
			'theme'    => array( __( 'Theme', 'adminora' ), __( 'The colours set in your active theme.', 'adminora' ) ),
			'logo'     => array( __( 'Logo', 'adminora' ), '' !== $logo ? __( 'The main colours in your logo.', 'adminora' ) : __( 'Add a logo in the Customizer, your theme or Settings → General first.', 'adminora' ) ),
			'frontend' => array(
				__( 'Front end', 'adminora' ),
				$read_at
					/* translators: %s: how long ago, such as "5 minutes". */
					? sprintf( __( 'The colours visitors see on your home page, including page builders. Read %s ago.', 'adminora' ), human_time_diff( $read_at ) )
					: __( 'The colours visitors see on your home page, including page builders. Not read yet.', 'adminora' ),
			),
			'custom'   => array( __( 'Custom colours', 'adminora' ), __( 'Colours you choose yourself in the Customizer.', 'adminora' ) ),
		);

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="adminora-panel adminora-brand">';
		echo '<input type="hidden" name="action" value="adminora_save"><input type="hidden" name="tab" value="brand">';
		wp_nonce_field( 'adminora_save' );

		echo '<div class="adminora-brand__head"><div><h2>' . esc_html__( 'Brand kit', 'adminora' ) . '</h2><p>' . esc_html__( 'Choose where your brand colours come from. The login page, emails, system pages and dashboard templates all use them. Logo, fonts and corners are set in the Customizer.', 'adminora' ) . '</p></div>';
		echo wp_kses_post( self::customize_link( 'adminora_brand', __( 'Edit logo and fonts', 'adminora' ) ) );
		echo '</div>';

		echo '<fieldset class="adminora-brand__options"><legend class="screen-reader-text">' . esc_html__( 'Brand colours from', 'adminora' ) . '</legend>';
		foreach ( $options as $key => $option ) {
			$colors = 'logo' === $key && '' === $logo ? array() : Brand_Kit::palette_for( $key );

			echo '<label class="adminora-brand__option' . ( $current === $key ? ' is-selected' : '' ) . '" data-adminora-source="' . esc_attr( $key ) . '">';
			echo '<input type="radio" name="' . esc_attr( Fields::name( 'brand.source' ) ) . '" value="' . esc_attr( $key ) . '"' . checked( $current, $key, false ) . ( 'logo' === $key && '' === $logo ? ' disabled' : '' ) . '>';
			echo '<span class="adminora-brand__text"><strong>' . esc_html( $option[0] ) . '</strong><small data-adminora-source-note>' . esc_html( $option[1] ) . '</small></span>';
			echo '<span class="adminora-brand__swatches" data-adminora-swatches>';
			self::swatches( $colors );
			echo '</span>';
			echo '</label>';
		}
		echo '</fieldset>';

		echo '<div class="adminora-brand__actions">';
		submit_button( __( 'Save brand colours', 'adminora' ), 'primary', 'submit', false );
		echo '<button type="button" class="button" data-adminora-capture>' . esc_html__( 'Read colours from my site', 'adminora' ) . '</button>';
		echo '<span class="adminora-brand__status" role="status" aria-live="polite"></span>';
		echo '</div></form>';
	}

	/**
	 * Overview: module switches.
	 *
	 * @return void
	 */
	private static function tab_overview() {
		$modules = array(
			'login'          => array( __( 'Login page', 'adminora' ), __( 'Layouts, colours, logo and text for every screen on wp-login.php.', 'adminora' ), 'adminora_login_layout' ),
			'emails'         => array( __( 'Emails', 'adminora' ), __( 'Branded HTML layout and editable wording for 15 core WordPress emails.', 'adminora' ), 'adminora_emails' ),
			'error_pages'    => array( __( 'Error and system pages', 'adminora' ), __( 'Critical error, database error, update maintenance, permission screens and an optional 404.', 'adminora' ), 'adminora_errors_design' ),
			'tab_meta'       => array( __( 'Tab and page details', 'adminora' ), __( 'Browser tab titles, favicon, address bar colour and hiding WordPress traces.', 'adminora' ), '' ),
			'admin_branding' => array( __( 'Admin branding', 'adminora' ), __( 'Admin bar logo, footer credit and a brand colour scheme.', 'adminora' ), '' ),
			'roles'          => array( __( 'Roles and permissions', 'adminora' ), __( 'Role editor, per-user permissions, menu access, login redirects, dashboard access and toolbar control.', 'adminora' ), '' ),
			'admin_look'     => array( __( 'Dashboard look', 'adminora' ), __( 'Admin templates with icons, colours, dark mode and a menu editor.', 'adminora' ), '' ),
			'widgets'        => array( __( 'Dashboard widgets', 'adminora' ), __( 'Show or hide widgets per role, custom widgets and a default layout.', 'adminora' ), '' ),
			'sessions'       => array( __( 'Sessions', 'adminora' ), __( 'See who is signed in and on which device, limit sessions per user, sign people out, block accounts and keep a sign-in history.', 'adminora' ), '' ),
		);

		self::brand_kit();

		self::form_start( 'overview' );
		self::section( __( 'Features', 'adminora' ), __( 'Switch off anything you do not need. Switched-off features load no code.', 'adminora' ) );

		echo '<table class="form-table adminora-modules" role="presentation">';
		foreach ( $modules as $key => $module ) {
			$desc = $module[1];
			if ( $module[2] && Settings::module_on( $key ) ) {
				$desc .= ' ' . self::customize_link( $module[2], __( 'Design', 'adminora' ) );
			}
			Fields::checkbox( 'modules.' . $key, $module[0], __( 'On', 'adminora' ), $desc );
		}
		echo '</table>';

		self::section( __( 'Site status and data', 'adminora' ) );
		echo '<table class="form-table" role="presentation">';

		Fields::row_start( __( 'Login protection', 'adminora' ) );
		echo esc_html( Settings::module_on( 'security' ) ? __( 'On', 'adminora' ) : __( 'Off', 'adminora' ) ) . ' &middot; <a href="' . esc_url( Admin::url( 'security' ) ) . '">' . esc_html__( 'Configure', 'adminora' ) . '</a>';
		Fields::row_end();

		Fields::row_start( __( 'Coming soon mode', 'adminora' ) );
		echo esc_html( Settings::module_on( 'maintenance' ) ? __( 'On', 'adminora' ) : __( 'Off', 'adminora' ) ) . ' &middot; <a href="' . esc_url( Admin::url( 'maintenance' ) ) . '">' . esc_html__( 'Configure', 'adminora' ) . '</a>';
		Fields::row_end();

		Fields::checkbox( 'general.remove_data', __( 'Uninstall', 'adminora' ), __( 'Remove all Adminora data when the plugin is deleted', 'adminora' ), __( 'Leave this off if you might reinstall later.', 'adminora' ) );
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
			echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'The Emails feature is switched off on the Overview tab, so these templates are not in use.', 'adminora' ) . '</p></div>';
		}

		echo '<p class="adminora-intro">' . wp_kses_post(
			sprintf(
				/* translators: %s: Customizer link. */
				__( 'Edit the wording of each email here. Colours, logo and fonts are set in the Customizer. %s', 'adminora' ),
				self::customize_link( 'adminora_emails', __( 'Design emails', 'adminora' ) )
			)
		) . '</p>';

		echo '<div class="adminora-emails">';

		echo '<nav class="adminora-emails__list" aria-label="' . esc_attr__( 'Email templates', 'adminora' ) . '">';
		foreach ( array(
			'user'  => __( 'To users', 'adminora' ),
			'admin' => __( 'To admins', 'adminora' ),
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
					$on ? '' : ' <span class="adminora-badge">' . esc_html__( 'Off', 'adminora' ) . '</span>'
				);
			}
			echo '</ul>';
		}
		echo '</nav>';

		echo '<div class="adminora-emails__editor">';
		echo '<h2>' . esc_html( $tpl['label'] ) . '</h2>';

		self::form_start( 'emails', array( 'template' => $current ) );
		echo '<table class="form-table" role="presentation">';
		Fields::checkbox( $prefix . 'enabled', __( 'Use this template', 'adminora' ), __( 'On', 'adminora' ), __( 'When off, WordPress sends its default plain-text email.', 'adminora' ) );
		Fields::text( $prefix . 'subject', __( 'Subject', 'adminora' ), '', array( 'class' => 'large-text' ) );
		Fields::text( $prefix . 'heading', __( 'Heading', 'adminora' ), '', array( 'class' => 'large-text' ) );

		Fields::row_start( __( 'Body', 'adminora' ), Fields::id( $prefix . 'body' ) );
		wp_editor(
			(string) Settings::get( $prefix . 'body' ),
			'adminora_email_body',
			array(
				'textarea_name' => Fields::name( $prefix . 'body' ),
				'textarea_rows' => 12,
				'media_buttons' => false,
				'teeny'         => false,
				'quicktags'     => true,
			)
		);
		Fields::row_end( __( 'Switch between Visual and Code (HTML) with the tabs above the editor.', 'adminora' ) );

		if ( '' !== $tpl['link'] ) {
			Fields::text( $prefix . 'button', __( 'Button text', 'adminora' ), __( 'Leave empty to hide the button.', 'adminora' ) );
		}

		Fields::row_start( __( 'Available tags', 'adminora' ) );
		echo '<div class="adminora-tags">';
		foreach ( array_merge( $tpl['tags'], Templates::common_tags() ) as $tag ) {
			printf( '<button type="button" class="button button-small" data-adminora-tag="%1$s">%1$s</button>', esc_attr( '{' . $tag . '}' ) );
		}
		echo '</div>';
		Fields::row_end( __( 'Click a tag to insert it into the body.', 'adminora' ) );
		echo '</table>';

		echo '<p class="submit">';
		submit_button( __( 'Save template', 'adminora' ), 'primary', 'submit', false );
		printf(
			' <a class="button" target="_blank" rel="noopener" href="%s">%s</a>',
			esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=adminora_email_preview&template=' . $current ), 'adminora_email_preview' ) ),
			esc_html__( 'Preview saved version', 'adminora' )
		);
		echo '</p></form>';

		echo '<div class="adminora-inline-forms">';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="adminora-inline-form">';
		echo '<input type="hidden" name="action" value="adminora_test_email"><input type="hidden" name="template" value="' . esc_attr( $current ) . '">';
		wp_nonce_field( 'adminora_test_email' );
		echo '<label for="adminora-to">' . esc_html__( 'Send a test to', 'adminora' ) . '</label> ';
		echo '<input type="email" id="adminora-to" name="adminora_to" class="regular-text" value="' . esc_attr( wp_get_current_user()->user_email ) . '" required> ';
		submit_button( __( 'Send test email', 'adminora' ), 'secondary', 'submit', false );
		echo '</form>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="adminora-inline-form" data-adminora-confirm>';
		echo '<input type="hidden" name="action" value="adminora_reset_template"><input type="hidden" name="template" value="' . esc_attr( $current ) . '">';
		wp_nonce_field( 'adminora_reset_template' );
		submit_button( __( 'Restore default text', 'adminora' ), 'link-delete', 'submit', false );
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

		self::section( __( 'Browser tab titles', 'adminora' ), __( 'Use {page} for the screen name and {site_name} for your site title. Leave a box empty to keep WordPress\'s wording.', 'adminora' ) );
		echo '<table class="form-table" role="presentation">';
		Fields::text( 'tab_meta.admin_title', __( 'Admin screens', 'adminora' ), __( 'WordPress default: "Dashboard ‹ Site — WordPress".', 'adminora' ) );
		Fields::text(
			'tab_meta.login_title',
			__( 'Login screens', 'adminora' ),
			sprintf(
				/* translators: %s: link to the Customizer section. */
				__( 'WordPress default: "Log In ‹ Site — WordPress". To rename the screens themselves ("Log In", "Lost Password" and so on), use <a href="%s">Login: wording</a> in the Customizer.', 'adminora' ),
				esc_url( admin_url( 'customize.php?autofocus[section]=adminora_login_words' ) )
			)
		);
		Fields::text( 'tab_meta.system_title', __( 'Error and notice pages', 'adminora' ), __( 'The critical error, database error, maintenance, no permission, expired link and 404 pages. {page} is the page title.', 'adminora' ) );
		Fields::text( 'tab_meta.front_separator', __( 'Site page separator', 'adminora' ), __( 'The character between the page name and the site name on your site\'s pages, for example | or ·. Usually a dash.', 'adminora' ), array( 'class' => 'small-text' ) );
		Fields::text( 'tab_meta.front_404_title', __( 'Site 404 page', 'adminora' ), __( 'WordPress default: "Page not found". Works with any theme that lets WordPress set the title.', 'adminora' ) );
		Fields::text( 'tab_meta.front_search_title', __( 'Site search results', 'adminora' ), __( 'Use {query} for the search words. WordPress default: "Search Results for “{query}”".', 'adminora' ) );
		Fields::checkbox( 'tab_meta.hide_home_tagline', __( 'Home page', 'adminora' ), __( 'Show only the site name on the home page tab, without the tagline', 'adminora' ) );
		echo '</table>';

		self::section( __( 'Icons and colours', 'adminora' ) );
		echo '<table class="form-table" role="presentation">';
		Fields::url(
			'tab_meta.favicon_image',
			__( 'Favicon', 'adminora' ),
			sprintf(
				/* translators: %s: link to Settings > General. */
				__( 'A square image, at least 512 × 512 pixels. Leave empty to use the site icon from <a href="%s">Settings &rarr; General</a>.', 'adminora' ),
				esc_url( admin_url( 'options-general.php' ) )
			),
			true
		);
		Fields::checkbox( 'tab_meta.favicon', __( 'Icon everywhere', 'adminora' ), __( 'Show the icon on login, admin and system pages, and use the brand logo when there is no icon', 'adminora' ) );
		Fields::checkbox( 'tab_meta.favicon_ico', __( '/favicon.ico', 'adminora' ), __( 'Answer browser requests for /favicon.ico with your icon instead of the WordPress logo', 'adminora' ), __( 'Has no effect if a favicon.ico file is already in your site\'s main folder.', 'adminora' ) );
		Fields::checkbox( 'tab_meta.theme_color', __( 'Address bar colour', 'adminora' ), __( 'Tint the mobile browser address bar with the brand primary colour', 'adminora' ) );
		echo '</table>';

		self::section( __( 'WordPress traces', 'adminora' ), __( 'Removes giveaways in your page source and public addresses. This is tidying, not a full security feature.', 'adminora' ) );
		echo '<table class="form-table" role="presentation">';
		Fields::checkbox( 'tab_meta.hide_generator', __( 'Generator tag', 'adminora' ), __( 'Remove the WordPress version meta tag and feed generator', 'adminora' ) );
		Fields::checkbox( 'tab_meta.hide_versions', __( 'Asset versions', 'adminora' ), __( 'Remove the WordPress version from core CSS and JavaScript URLs', 'adminora' ) );
		Fields::checkbox( 'tab_meta.hide_rsd_wlw', __( 'Legacy links', 'adminora' ), __( 'Remove the RSD and Windows Live Writer links', 'adminora' ) );
		Fields::checkbox( 'tab_meta.hide_rest_links', __( 'API and embed links', 'adminora' ), __( 'Remove the REST API, oEmbed and shortlink tags and headers from your pages', 'adminora' ), __( 'The REST API itself keeps working.', 'adminora' ) );
		Fields::checkbox( 'tab_meta.hide_emoji', __( 'Emoji script', 'adminora' ), __( 'Remove WordPress\'s emoji script and styles', 'adminora' ), __( 'Browsers show emoji on their own.', 'adminora' ) );
		Fields::checkbox( 'tab_meta.disable_xmlrpc', __( 'XML-RPC and pingbacks', 'adminora' ), __( 'Turn off XML-RPC, pingbacks and the X-Pingback header', 'adminora' ), __( 'Leave this off if you use Jetpack or post from the WordPress mobile app.', 'adminora' ) );
		Fields::checkbox( 'tab_meta.block_user_lookup', __( 'Username lookups', 'adminora' ), __( 'Stop visitors finding usernames through ?author= addresses, the REST API user list and the author sitemap', 'adminora' ), __( 'Logged-in users are not affected.', 'adminora' ) );
		Fields::checkbox( 'tab_meta.hide_login_wp_link', __( 'Login logo link', 'adminora' ), __( 'Point the login logo to your site instead of wordpress.org', 'adminora' ), __( 'Applies when the Login page feature is off; that feature has its own setting.', 'adminora' ) );
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
			'keep'   => __( 'WordPress default', 'adminora' ),
			'custom' => __( 'Custom text', 'adminora' ),
			'hide'   => __( 'Remove', 'adminora' ),
		);

		self::form_start( 'admin_branding' );

		self::section( __( 'Admin bar', 'adminora' ) );
		echo '<table class="form-table" role="presentation">';
		Fields::select(
			'admin_branding.adminbar_logo',
			__( 'WordPress logo', 'adminora' ),
			array(
				'keep'    => __( 'Keep the WordPress logo', 'adminora' ),
				'replace' => __( 'Replace with my logo', 'adminora' ),
				'remove'  => __( 'Remove it', 'adminora' ),
			)
		);
		Fields::url( 'admin_branding.adminbar_image', __( 'Logo image', 'adminora' ), __( 'Shown 20px tall. Defaults to your site icon.', 'adminora' ), true, Fields::show_if( 'admin_branding.adminbar_logo', 'replace' ) );
		Fields::url( 'admin_branding.adminbar_link', __( 'Logo link', 'adminora' ), __( 'Defaults to your home page.', 'adminora' ), false, Fields::show_if( 'admin_branding.adminbar_logo', 'replace' ) );
		Fields::checkbox( 'admin_branding.hide_howdy', __( 'Greeting', 'adminora' ), __( 'Show just the name instead of "Howdy, Name"', 'adminora' ) );
		echo '</table>';

		self::section( __( 'Footer credit', 'adminora' ) );
		echo '<table class="form-table" role="presentation">';
		Fields::select( 'admin_branding.footer_left', __( '"Thank you for creating with WordPress"', 'adminora' ), $keep );
		Fields::textarea( 'admin_branding.footer_left_text', __( 'Left footer text', 'adminora' ), __( 'Basic HTML and {site_name}, {site_url} and {year} are allowed.', 'adminora' ), 2, Fields::show_if( 'admin_branding.footer_left', 'custom' ) );
		Fields::select( 'admin_branding.footer_right', __( 'WordPress version', 'adminora' ), $keep );
		Fields::textarea( 'admin_branding.footer_right_text', __( 'Right footer text', 'adminora' ), '', 2, Fields::show_if( 'admin_branding.footer_right', 'custom' ) );
		echo '</table>';

		self::section(
			__( 'Admin screens', 'adminora' ),
			sprintf(
				/* translators: %s: link to the Dashboard widgets tab. */
				__( 'Dashboard widgets, including the welcome panel, are managed on the <a href="%s">Dashboard widgets</a> tab.', 'adminora' ),
				esc_url( Admin::url( 'widgets' ) )
			)
		);
		echo '<table class="form-table" role="presentation">';
		Fields::checkbox( 'admin_branding.hide_help', __( 'Help tab', 'adminora' ), __( 'Remove the Help tab from admin screens', 'adminora' ) );
		Fields::checkbox( 'admin_branding.hide_screen_opts', __( 'Screen Options', 'adminora' ), __( 'Remove the Screen Options tab from admin screens', 'adminora' ) );
		echo '</table>';

		self::section( __( 'Colour scheme', 'adminora' ) );
		echo '<table class="form-table" role="presentation">';
		Fields::checkbox(
			'admin_branding.color_scheme',
			__( 'Brand scheme', 'adminora' ),
			__( 'Add a colour scheme built from the brand kit', 'adminora' ),
			sprintf(
				/* translators: %s: profile URL. */
				__( 'Each user picks it under Admin Color Scheme on <a href="%s">their profile</a>.', 'adminora' ),
				esc_url( admin_url( 'profile.php' ) )
			)
		);
		Fields::checkbox( 'admin_branding.default_scheme', __( 'New users', 'adminora' ), __( 'Give new users the brand scheme by default', 'adminora' ), '', Fields::show_if( 'admin_branding.color_scheme' ) );
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
			__( 'Custom dashboard address', 'adminora' ),
			__( 'Moves the dashboard from /wp-admin to an address you choose. Logged-out visitors who open /wp-admin get a "page not found" page; logged-in users are moved to the new address, so old links keep working. If you ever get locked out, add <code>define( \'ADMINORA_DISABLE_ADMIN_URL\', true );</code> to wp-config.php.', 'adminora' )
		);

		if ( Admin_Url::disabled() ) {
			echo '<div class="notice notice-info inline"><p>' . esc_html__( 'Not available: this needs a single site (not multisite), and the ADMINORA_DISABLE_ADMIN_URL constant must not be set.', 'adminora' ) . '</p></div>';
			return;
		}

		$on     = Settings::get( 'security.admin_url' ) && '' !== Admin_Url::slug();
		$active = $on && Admin_Url::active();
		$server = Admin_Url::server();

		if ( $active ) {
			echo '<div class="notice notice-success inline"><p>' . esc_html__( 'Your dashboard is at:', 'adminora' ) . ' <a href="' . esc_url( Admin_Url::url() ) . '"><strong>' . esc_html( Admin_Url::url() ) . '</strong></a></p></div>';
		}

		echo '<table class="form-table" role="presentation">';
		Fields::checkbox( 'security.admin_url', __( 'Custom dashboard address', 'adminora' ), __( 'On', 'adminora' ) );
		Fields::text(
			'security.admin_slug',
			__( 'Dashboard address', 'adminora' ),
			__( 'Letters, numbers and dashes, for example "office". Must differ from the login address.', 'adminora' ),
			array(
				'prefix' => trailingslashit( site_url() ),
				'class'  => 'regular-text adminora-slug',
			),
			Fields::show_if( 'security.admin_url' )
		);

		if ( $on && ! $active ) {
			Fields::row_start( __( 'Server setup', 'adminora' ) );

			if ( 'nginx' === $server ) {
				echo '<p>' . esc_html__( 'Your site runs on Nginx. Add these lines inside the server block of your site\'s Nginx configuration, above the "location /" block, and reload Nginx. Your host can do this for you.', 'adminora' ) . '</p>';
				echo '<pre class="adminora-code">' . esc_html( Admin_Url::nginx_rules() ) . '</pre>';
			} else {
				echo '<p>' . esc_html__( 'Add these lines at the top of the .htaccess file in your site\'s root folder, above the WordPress block. If your server is not Apache or LiteSpeed, ask your host to rewrite the new address to /wp-admin.', 'adminora' ) . '</p>';
				echo '<pre class="adminora-code">' . esc_html( "# BEGIN Adminora\n" . implode( "\n", Admin_Url::apache_rules() ) . "\n# END Adminora" ) . '</pre>';
			}

			$test = untrailingslashit( site_url() ) . '/' . Admin_Url::slug() . '/admin-ajax.php?action=adminora_ping';

			echo '<p class="adminora-actions">';
			echo '<button type="submit" class="button button-primary" form="adminora-admin-url-test">' . esc_html__( 'Test again', 'adminora' ) . '</button> ';
			echo '<a class="button" target="_blank" rel="noopener" href="' . esc_url( $test ) . '">' . esc_html__( 'Open the test page', 'adminora' ) . '</a> ';
			echo '<button type="submit" class="button button-link-delete" form="adminora-admin-url-force">' . esc_html__( 'Activate anyway', 'adminora' ) . '</button>';
			echo '</p>';
			Fields::row_end( __( 'The test page should show {"success":true,...}. Some servers cannot test themselves; if the test page works in your browser but "Test again" fails, use "Activate anyway".', 'adminora' ) );
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
			echo '<form id="adminora-admin-url-' . esc_attr( $action ) . '" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"' . ( 'force' === $action ? ' data-adminora-confirm' : '' ) . '>';
			echo '<input type="hidden" name="action" value="adminora_admin_url_' . esc_attr( $action ) . '">';
			wp_nonce_field( 'adminora_admin_url_' . $action );
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
			__( 'Custom login URL', 'adminora' ),
			__( 'Moves the login page so bots stop hammering wp-login.php. If you ever get locked out, add <code>define( \'ADMINORA_DISABLE_LOGIN_URL\', true );</code> to wp-config.php.', 'adminora' )
		);

		if ( $on_url ) {
			echo '<div class="notice notice-info inline"><p>' . esc_html__( 'Your login page is at:', 'adminora' ) . ' <a href="' . esc_url( Login_Url::url() ) . '"><strong>' . esc_html( Login_Url::url() ) . '</strong></a></p></div>';
		}

		echo '<table class="form-table" role="presentation">';
		Fields::checkbox( 'security.login_url', __( 'Custom login URL', 'adminora' ), __( 'On', 'adminora' ) );
		Fields::text(
			'security.login_slug',
			__( 'Login address', 'adminora' ),
			__( 'Letters, numbers and dashes. Not "login", "admin" or "dashboard".', 'adminora' ),
			array(
				'prefix' => trailingslashit( home_url() ),
				'class'  => 'regular-text adminora-slug',
			),
			Fields::show_if( 'security.login_url' )
		);
		Fields::select(
			'security.blocked_action',
			__( 'When someone opens wp-login.php or /wp-admin', 'adminora' ),
			array(
				'404'    => __( 'Show a "page not found" page', 'adminora' ),
				'home'   => __( 'Send them to the home page', 'adminora' ),
				'custom' => __( 'Send them to another address', 'adminora' ),
			),
			'',
			Fields::show_if( 'security.login_url' )
		);
		Fields::url( 'security.blocked_url', __( 'Other address', 'adminora' ), '', false, Fields::show_if( 'security.blocked_action', 'custom' ) );
		echo '</table>';

		self::admin_url_section();

		self::section( __( 'Limit login attempts', 'adminora' ) );
		echo '<table class="form-table" role="presentation">';
		Fields::checkbox( 'security.limit', __( 'Limit attempts', 'adminora' ), __( 'On', 'adminora' ) );
		Fields::number( 'security.max_attempts', __( 'Failed attempts allowed', 'adminora' ), '', Fields::show_if( 'security.limit' ) );
		Fields::number( 'security.window', __( 'Within (minutes)', 'adminora' ), '', Fields::show_if( 'security.limit' ) );
		Fields::number( 'security.lockout', __( 'Lockout length (minutes)', 'adminora' ), '', Fields::show_if( 'security.limit' ) );
		Fields::textarea( 'security.allowlist', __( 'Never lock out these IPs', 'adminora' ), __( 'One per line. IPv4 ranges such as 192.168.1.0/24 work too.', 'adminora' ), 3, Fields::show_if( 'security.limit' ) );
		Fields::checkbox(
			'security.trust_proxy',
			__( 'Proxy headers', 'adminora' ),
			__( 'My site is behind Cloudflare or another proxy', 'adminora' ),
			/* translators: %s: visitor IP address. */
			sprintf( __( 'Only turn this on if every request passes through a proxy, or visitors could fake their IP. Your IP as seen now: %s', 'adminora' ), '<code>' . esc_html( Attempts::ip() ) . '</code>' ),
			Fields::show_if( 'security.limit' )
		);
		echo '</table>';

		self::section( __( 'Honeypot', 'adminora' ) );
		echo '<table class="form-table" role="presentation">';
		Fields::checkbox( 'security.honeypot', __( 'Honeypot field', 'adminora' ), __( 'Add a hidden field to the login, registration and lost password forms', 'adminora' ), __( 'People never see it. Bots that fill in every field get rejected.', 'adminora' ) );
		echo '</table>';

		self::section(
			__( 'Two-factor authentication', 'adminora' ),
			__( 'Users turn it on from their profile with an authenticator app or email codes, and get one-time backup codes. If you get locked out, add <code>define( \'ADMINORA_DISABLE_2FA\', true );</code> to wp-config.php.', 'adminora' )
		);
		echo '<table class="form-table" role="presentation">';
		Fields::checkbox( 'security.twofa', __( 'Two-factor', 'adminora' ), __( 'On', 'adminora' ) );
		Fields::checkbox( 'security.twofa_email', __( 'Email codes', 'adminora' ), __( 'Allow codes by email, as a method and as a fallback', 'adminora' ), __( 'Email is less secure than an app but easier for most people.', 'adminora' ), Fields::show_if( 'security.twofa' ) );
		Fields::roles( 'security.twofa_roles', __( 'Require for these roles', 'adminora' ), __( 'Users with these roles must set it up before the grace period ends.', 'adminora' ), Fields::show_if( 'security.twofa' ) );
		Fields::number( 'security.twofa_grace', __( 'Grace period (days)', 'adminora' ), __( '0 means they must set it up straight away.', 'adminora' ), Fields::show_if( 'security.twofa' ) );
		echo '</table>';

		self::form_end();
		self::admin_url_forms();

		$log = Attempts::get_log();

		if ( ! $log ) {
			return;
		}

		self::section( __( 'Recent lockouts', 'adminora' ) );
		echo '<table class="widefat striped adminora-log"><thead><tr><th>' . esc_html__( 'When', 'adminora' ) . '</th><th>' . esc_html__( 'IP address', 'adminora' ) . '</th><th>' . esc_html__( 'Username tried', 'adminora' ) . '</th><th>' . esc_html__( 'Status', 'adminora' ) . '</th></tr></thead><tbody>';

		foreach ( array_slice( $log, 0, 50 ) as $entry ) {
			$active = $entry['until'] > time() && Attempts::locked_until( $entry['ip'] );
			echo '<tr><td>' . esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $entry['time'] ) ) . '</td>';
			echo '<td><code>' . esc_html( $entry['ip'] ) . '</code></td><td>' . esc_html( $entry['username'] ) . '</td><td>';

			if ( $active ) {
				echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="adminora_unlock"><input type="hidden" name="ip" value="' . esc_attr( $entry['ip'] ) . '">';
				wp_nonce_field( 'adminora_unlock' );
				submit_button( __( 'Unlock', 'adminora' ), 'small', 'submit', false );
				echo '</form>';
			} else {
				esc_html_e( 'Expired', 'adminora' );
			}

			echo '</td></tr>';
		}

		echo '</tbody></table>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" data-adminora-confirm><input type="hidden" name="action" value="adminora_clear_log">';
		wp_nonce_field( 'adminora_clear_log' );
		submit_button( __( 'Clear log', 'adminora' ), 'link-delete' );
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
			__( 'Coming soon and maintenance mode', 'adminora' ),
			__( 'Visitors see a holding page while you work: the design from the Customizer, or a page you built yourself, for example with Elementor.', 'adminora' )
		);

		echo '<table class="form-table" role="presentation">';
		Fields::checkbox( 'modules.maintenance', __( 'Mode', 'adminora' ), __( 'On', 'adminora' ), __( 'Administrators always see the real site.', 'adminora' ) );
		Fields::select(
			'maintenance.mode',
			__( 'Type', 'adminora' ),
			array(
				'coming_soon' => __( 'Coming soon (for a site that has not launched)', 'adminora' ),
				'maintenance' => __( 'Maintenance (tells search engines to come back later)', 'adminora' ),
			)
		);
		echo '</table>';

		self::section( __( 'Holding page', 'adminora' ) );
		echo '<table class="form-table" role="presentation">';
		Fields::select(
			'maintenance.design',
			__( 'Show', 'adminora' ),
			array(
				'customizer' => __( 'The page designed in the Customizer', 'adminora' ),
				'page'       => __( 'A page I built', 'adminora' ),
			)
		);

		Fields::row_start( __( 'Design', 'adminora' ), '', Fields::show_if( 'maintenance.design', 'customizer' ) );
		echo wp_kses_post( self::customize_link( 'adminora_maintenance', __( 'Design the page', 'adminora' ) ) );
		Fields::row_end( __( 'Headline, message, countdown, logo and background, with a live preview.', 'adminora' ) );

		$choices = Maintenance::choices();
		$current = (int) Settings::get( 'maintenance.page' );

		Fields::row_start( __( 'Page', 'adminora' ), Fields::id( 'maintenance.page' ), Fields::show_if( 'maintenance.design', 'page' ) );
		if ( $choices ) {
			echo '<select id="' . esc_attr( Fields::id( 'maintenance.page' ) ) . '" name="' . esc_attr( Fields::name( 'maintenance.page' ) ) . '">';
			echo '<option value="0">' . esc_html__( 'Choose a page', 'adminora' ) . '</option>';
			foreach ( $choices as $group => $items ) {
				echo '<optgroup label="' . esc_attr( $group ) . '">';
				foreach ( $items as $id => $title ) {
					echo '<option value="' . esc_attr( (string) $id ) . '"' . selected( $current, $id, false ) . '>' . esc_html( $title ) . '</option>';
				}
				echo '</optgroup>';
			}
			echo '</select>';
			if ( Maintenance::page_id() ) {
				echo ' <a class="button" href="' . esc_url( Maintenance::edit_url( Maintenance::page_id() ) ) . '">' . esc_html( Maintenance::is_elementor( Maintenance::page_id() ) || 'elementor_library' === get_post_type( Maintenance::page_id() ) ? __( 'Edit with Elementor', 'adminora' ) : __( 'Edit page', 'adminora' ) ) . '</a>';
			}
		} else {
			echo '<p>' . esc_html__( 'No pages yet. Create one under Pages, or save a page template in Elementor.', 'adminora' ) . '</p>';
		}
		Fields::row_end( __( 'Any page, including drafts and private pages, or an Elementor page template. It fills the whole screen without your theme\'s header and footer, so it can be a draft that visitors never reach any other way. If the page is deleted, the Customizer design is shown instead.', 'adminora' ) );

		if ( Settings::module_on( 'maintenance' ) ) {
			Fields::row_start( __( 'Check it', 'adminora' ) );
			echo '<a class="button" href="' . esc_url( add_query_arg( Maintenance::PREVIEW, '1', home_url( '/' ) ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'View the holding page', 'adminora' ) . '</a>';
			Fields::row_end( __( 'Opens the page visitors see. Save first if you changed anything.', 'adminora' ) );
		}
		echo '</table>';

		self::section( __( 'Who can see the real site', 'adminora' ) );
		echo '<table class="form-table" role="presentation">';
		Fields::roles( 'maintenance.roles', __( 'Roles', 'adminora' ), __( 'Logged-in users with these roles.', 'adminora' ) );
		Fields::textarea( 'maintenance.allowed_paths', __( 'Always open pages', 'adminora' ), __( 'One path per line, such as /contact or /privacy-policy.', 'adminora' ), 3 );

		Fields::row_start( __( 'Bypass link', 'adminora' ) );
		if ( '' !== $key ) {
			$link = add_query_arg( 'adminora_bypass', $key, home_url( '/' ) );
			echo '<input type="hidden" name="' . esc_attr( Fields::name( 'maintenance.bypass_key' ) ) . '" value="' . esc_attr( $key ) . '">';
			echo '<code class="adminora-copyable">' . esc_html( $link ) . '</code>';
			echo '<p><label><input type="checkbox" name="' . esc_attr( Fields::name( 'maintenance.bypass_key' ) ) . '" value=""> ' . esc_html__( 'Remove this link', 'adminora' ) . '</label></p>';
		}
		echo '<p><label><input type="checkbox" name="adminora_new_bypass" value="1"> ' . esc_html( '' !== $key ? __( 'Create a new link (the old one stops working)', 'adminora' ) : __( 'Create a link', 'adminora' ) ) . '</label></p>';
		Fields::row_end( __( 'Anyone who opens this link sees the real site for a week. Useful for clients.', 'adminora' ) );
		echo '</table>';

		self::form_end();
	}

	/**
	 * Checkboxes for the parts of an export or import.
	 *
	 * @param array    $parts   Part key => [ label, description ].
	 * @param string[] $checked Parts ticked to start with.
	 * @return void
	 */
	private static function transfer_parts( array $parts, array $checked ) {
		echo '<fieldset class="adminora-parts"><legend>' . esc_html__( 'Include', 'adminora' ) . '</legend>';
		foreach ( $parts as $key => $part ) {
			printf(
				'<label><input type="checkbox" name="adminora_parts[]" value="%1$s"%2$s> <span><strong>%3$s</strong><small>%4$s</small></span></label>',
				esc_attr( $key ),
				checked( in_array( $key, $checked, true ), true, false ),
				esc_html( $part[0] ),
				esc_html( $part[1] )
			);
		}
		echo '</fieldset>';
	}

	/**
	 * Prints capability names as code.
	 *
	 * @param string[] $caps Capabilities.
	 * @return void
	 */
	private static function cap_list( array $caps ) {
		foreach ( $caps as $cap ) {
			echo '<code>' . esc_html( $cap ) . '</code> ';
		}
	}

	/**
	 * Second step of an import: choose the parts and the roles.
	 *
	 * @param array $data Uploaded export.
	 * @return void
	 */
	private static function import_review( array $data ) {
		$parts = Transfer::parts_in( $data );
		$rows  = Transfer::role_rows( $data );
		$post  = admin_url( 'admin-post.php' );

		self::section(
			__( 'Review the import', 'adminora' ),
			sprintf(
				/* translators: 1: site address, 2: date. */
				__( 'Exported from %1$s on %2$s. Nothing changes until you click Import.', 'adminora' ),
				isset( $data['site'] ) ? esc_html( $data['site'] ) : esc_html__( 'another site', 'adminora' ),
				isset( $data['exported'] ) ? esc_html( wp_date( get_option( 'date_format' ), (int) strtotime( $data['exported'] ) ) ) : '&mdash;'
			)
		);

		echo '<form method="post" action="' . esc_url( $post ) . '" id="adminora-import-review" data-adminora-confirm><input type="hidden" name="action" value="adminora_import_apply">';
		wp_nonce_field( 'adminora_import_apply' );

		if ( $parts ) {
			echo '<div class="adminora-card">';
			self::transfer_parts( $parts, array_keys( $parts ) );
			echo '<p class="description">' . esc_html__( 'Each ticked part replaces the same part on this site. Pages and categories are matched by their slug. Image addresses still point to the original site.', 'adminora' ) . '</p></div>';
		}

		if ( $rows ) {
			self::section( __( 'Roles', 'adminora' ), __( 'Tick the roles to bring across. Each comes with all of its capabilities: a role this site does not have is added, and a role it already has is given the file\'s capabilities. Roles you leave unticked, and roles that are only on this site, stay as they are. A backup of your roles is saved first.', 'adminora' ) );

			echo '<p class="adminora-actions"><button type="button" class="button button-small" data-adminora-tick="all">' . esc_html__( 'Tick all', 'adminora' ) . '</button> <button type="button" class="button button-small" data-adminora-tick="new">' . esc_html__( 'Only roles new to this site', 'adminora' ) . '</button> <button type="button" class="button button-small" data-adminora-tick="none">' . esc_html__( 'Tick none', 'adminora' ) . '</button></p>';

			echo '<table class="widefat striped adminora-import-roles"><thead><tr><th scope="col" class="check-column"><span class="screen-reader-text">' . esc_html__( 'Import', 'adminora' ) . '</span></th><th scope="col">' . esc_html__( 'Role', 'adminora' ) . '</th><th scope="col">' . esc_html__( 'On this site', 'adminora' ) . '</th><th scope="col">' . esc_html__( 'Capabilities in the file', 'adminora' ) . '</th><th scope="col">' . esc_html__( 'People here', 'adminora' ) . '</th></tr></thead><tbody>';

			foreach ( $rows as $row ) {
				$locked = 'locked' === $row['status'];
				$new    = 'new' === $row['status'];

				echo '<tr data-status="' . esc_attr( $row['status'] ) . '"><th scope="row" class="check-column">';
				if ( $locked ) {
					echo '<input type="checkbox" disabled aria-label="' . esc_attr( $row['name'] ) . '">';
				} else {
					printf( '<input type="checkbox" name="adminora_roles[]" value="%1$s"%2$s aria-label="%3$s">', esc_attr( $row['slug'] ), checked( $new, true, false ), esc_attr( sprintf( /* translators: %s: role name. */ __( 'Import %s', 'adminora' ), $row['name'] ) ) );
				}
				echo '</th><td><strong>' . esc_html( $row['name'] ) . '</strong><br><code>' . esc_html( $row['slug'] ) . '</code>';
				if ( $row['used'] && $new ) {
					echo '<br><small>' . esc_html__( 'Rules in the file use this role. Leave it unticked and they are skipped.', 'adminora' ) . '</small>';
				}
				echo '</td><td>';

				if ( $locked ) {
					echo '<span class="adminora-badge">' . esc_html__( 'Never replaced', 'adminora' ) . '</span><br><small>' . esc_html__( 'Administrators always have every capability. Capabilities the imported roles have are added to it.', 'adminora' ) . '</small>';
				} elseif ( $new ) {
					echo '<span class="adminora-badge adminora-badge--new">' . esc_html__( 'New here', 'adminora' ) . '</span>';
				} elseif ( ! $row['added'] && ! $row['removed'] ) {
					echo esc_html__( 'Exists, same capabilities', 'adminora' );
				} else {
					echo esc_html__( 'Exists, different capabilities', 'adminora' );
					echo '<details class="adminora-diff"><summary>' . esc_html(
						sprintf(
							/* translators: 1: capabilities gained, 2: capabilities lost. */
							__( '%1$d gained, %2$d removed if imported', 'adminora' ),
							count( $row['added'] ),
							count( $row['removed'] )
						)
					) . '</summary>';
					if ( $row['added'] ) {
						echo '<p><strong>' . esc_html__( 'Gained:', 'adminora' ) . '</strong> ';
						self::cap_list( $row['added'] );
						echo '</p>';
					}
					if ( $row['removed'] ) {
						echo '<p><strong>' . esc_html__( 'Removed:', 'adminora' ) . '</strong> ';
						self::cap_list( $row['removed'] );
						echo '</p>';
					}
					echo '</details>';
				}

				echo '</td><td>' . esc_html( number_format_i18n( count( $row['caps'] ) ) ) . '</td><td>' . esc_html( number_format_i18n( $row['users'] ) ) . '</td></tr>';
			}
			echo '</tbody></table>';
		}

		echo '<p class="submit">';
		submit_button( __( 'Import', 'adminora' ), 'primary', 'submit', false );
		echo '</p></form>';

		echo '<form method="post" action="' . esc_url( $post ) . '" class="adminora-inline-form"><input type="hidden" name="action" value="adminora_import_cancel">';
		wp_nonce_field( 'adminora_import_cancel' );
		submit_button( __( 'Cancel and discard the file', 'adminora' ), 'secondary', 'cancel', false );
		echo '</form>';
	}

	/**
	 * Tools: import, export, reset and status.
	 *
	 * @return void
	 */
	private static function tab_tools() {
		$post = esc_url( admin_url( 'admin-post.php' ) );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Navigation only.
		$step    = isset( $_GET['step'] ) ? sanitize_key( wp_unslash( $_GET['step'] ) ) : '';
		$pending = Admin::pending_import();

		if ( 'review' === $step && $pending ) {
			self::import_review( $pending );
			return;
		}

		echo '<div class="adminora-cards">';

		echo '<section class="adminora-card"><h2>' . esc_html__( 'Export', 'adminora' ) . '</h2><p>' . esc_html__( 'Download Adminora\'s settings as a JSON file to copy them to another site.', 'adminora' ) . '</p>';
		echo '<form method="post" action="' . esc_url( $post ) . '"><input type="hidden" name="action" value="adminora_export">';
		wp_nonce_field( 'adminora_export' );
		self::transfer_parts( Transfer::parts(), array_keys( Transfer::parts() ) );
		submit_button( __( 'Download settings', 'adminora' ), 'secondary', 'submit', false );
		echo '</form></section>';

		echo '<section class="adminora-card"><h2>' . esc_html__( 'Import', 'adminora' ) . '</h2><p>' . esc_html__( 'Upload an exported file. The next screen shows what it contains, and you choose the parts and the roles to bring across before anything changes.', 'adminora' ) . '</p>';
		if ( $pending ) {
			echo '<p><a href="' . esc_url( Admin::url( 'tools', array( 'step' => 'review' ) ) ) . '">' . esc_html__( 'Continue the import you started', 'adminora' ) . '</a></p>';
		}
		echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( $post ) . '"><input type="hidden" name="action" value="adminora_import">';
		wp_nonce_field( 'adminora_import' );
		echo '<label for="adminora-file" class="screen-reader-text">' . esc_html__( 'Settings file', 'adminora' ) . '</label><input type="file" id="adminora-file" name="adminora_file" accept=".json,application/json" required>';
		echo '<p>';
		submit_button( __( 'Upload and review', 'adminora' ), 'secondary', 'submit', false );
		echo '</p></form></section>';

		echo '<section class="adminora-card"><h2>' . esc_html__( 'Reset', 'adminora' ) . '</h2><p>' . esc_html__( 'Put one section, or everything, back to its defaults.', 'adminora' ) . '</p>';
		echo '<form method="post" action="' . esc_url( $post ) . '" data-adminora-confirm><input type="hidden" name="action" value="adminora_reset">';
		wp_nonce_field( 'adminora_reset' );
		$sections = array(
			'brand'          => __( 'Brand kit', 'adminora' ),
			'login'          => __( 'Login page', 'adminora' ),
			'emails'         => __( 'Emails', 'adminora' ),
			'error_pages'    => __( 'Error and system pages', 'adminora' ),
			'tab_meta'       => __( 'Tab and page details', 'adminora' ),
			'admin_branding' => __( 'Admin branding', 'adminora' ),
			'security'       => __( 'Login protection', 'adminora' ),
			'maintenance'    => __( 'Coming soon', 'adminora' ),
			'all'            => __( 'Everything', 'adminora' ),
		);
		echo '<p><label for="adminora-section">' . esc_html__( 'Section', 'adminora' ) . '</label> <select id="adminora-section" name="adminora_section">';
		foreach ( $sections as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '">' . esc_html( $label ) . '</option>';
		}
		echo '</select></p>';
		echo '<p><label><input type="checkbox" name="adminora_confirm" value="1" required> ' . esc_html__( 'I understand this restores the defaults for the chosen section', 'adminora' ) . '</label></p>';
		submit_button( __( 'Reset', 'adminora' ), 'delete', 'submit', false );
		echo '</form></section>';

		echo '</div>';

		self::section( __( 'Status', 'adminora' ) );
		echo '<table class="widefat striped adminora-status"><tbody>';

		$labels = Admin::dropin_labels();
		$names  = array(
			'php-error.php'   => __( 'Critical error page', 'adminora' ),
			'db-error.php'    => __( 'Database error page', 'adminora' ),
			'maintenance.php' => __( 'Update maintenance page', 'adminora' ),
		);

		foreach ( Dropins::status() as $file => $state ) {
			printf(
				'<tr><th scope="row">%s <code>wp-content/%s</code></th><td><span class="adminora-state adminora-state--%s">%s</span></td></tr>',
				esc_html( $names[ $file ] ),
				esc_html( $file ),
				esc_attr( $state ),
				esc_html( $labels[ $state ] )
			);
		}

		printf(
			'<tr><th scope="row">%s</th><td>%s</td></tr>',
			esc_html__( 'wp-content writable', 'adminora' ),
			Files::writable( WP_CONTENT_DIR ) ? esc_html__( 'Yes', 'adminora' ) : esc_html__( 'No', 'adminora' )
		);

		$detected = Brand_Kit::detected();
		echo '<tr><th scope="row">' . esc_html__( 'Colours detected from the theme', 'adminora' ) . '</th><td><div class="adminora-swatches">';
		$found = false;
		foreach ( Brand_Kit::COLORS as $color ) {
			if ( ! empty( $detected[ $color ] ) ) {
				$found = true;
				printf(
					'<span class="adminora-swatch"><span class="adminora-swatch__chip" data-color="%1$s"></span>%2$s <code>%1$s</code></span>',
					esc_attr( $detected[ $color ] ),
					esc_html( $color )
				);
			}
		}
		if ( ! $found ) {
			esc_html_e( 'None. The built-in palette is used until you pick colours in the brand kit.', 'adminora' );
		}
		echo '</div></td></tr>';

		$conflicts = Admin::conflicts();
		echo '<tr><th scope="row">' . esc_html__( 'Overlapping plugins', 'adminora' ) . '</th><td>';
		if ( $conflicts ) {
			echo '<ul class="adminora-conflicts">';
			foreach ( $conflicts as $name => $module ) {
				/* translators: 1: plugin name, 2: Adminora feature name. */
				echo '<li>' . esc_html( sprintf( __( '%1$s does similar things to the %2$s feature. Use one or the other to avoid surprises.', 'adminora' ), $name, $sections[ $module ] ) ) . '</li>';
			}
			echo '</ul>';
		} else {
			esc_html_e( 'None found.', 'adminora' );
		}
		echo '</td></tr>';

		echo '</tbody></table>';
	}
}

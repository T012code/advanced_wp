<?php
/**
 * Settings page tabs.
 *
 * @package LayoDT
 */

namespace LayoDT\Admin;

use LayoDT\Brand_Kit;
use LayoDT\Dropins;
use LayoDT\Files;
use LayoDT\Importers\Importers;
use LayoDT\Importers\Sources;
use LayoDT\Login_Pages;
use LayoDT\Modules\Emails\Email_Sources;
use LayoDT\Modules\Emails\Emails;
use LayoDT\Modules\Emails\Renderer;
use LayoDT\Settings;
use LayoDT\Modules\Emails\Templates;
use LayoDT\Modules\Maintenance\Maintenance;
use LayoDT\Modules\Security\Admin_Url;
use LayoDT\Modules\Security\Attempts;
use LayoDT\Modules\Security\Login_Url;
use LayoDT\Modules\Security\Social;

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
		echo '<input type="hidden" name="action" value="layodt_save">';
		echo '<input type="hidden" name="tab" value="' . esc_attr( $tab ) . '">';
		foreach ( $extra as $name => $value ) {
			echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '">';
		}
		wp_nonce_field( 'layodt_save' );
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
			echo '<p class="layodt-intro">' . wp_kses_post( $intro ) . '</p>';
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
					'<span class="layodt-swatch__chip" data-color="%1$s" title="%2$s %1$s"></span>',
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
			'theme'    => array( __( 'Theme', 'layo-dashboard-toolkit' ), __( 'The colours set in your active theme.', 'layo-dashboard-toolkit' ) ),
			'logo'     => array( __( 'Logo', 'layo-dashboard-toolkit' ), '' !== $logo ? __( 'The main colours in your logo.', 'layo-dashboard-toolkit' ) : __( 'Add a logo in the Customizer, your theme or Settings → General first.', 'layo-dashboard-toolkit' ) ),
			'frontend' => array(
				__( 'Front end', 'layo-dashboard-toolkit' ),
				$read_at
					/* translators: %s: how long ago, such as "5 minutes". */
					? sprintf( __( 'The colours visitors see on your home page, including page builders. Read %s ago.', 'layo-dashboard-toolkit' ), human_time_diff( $read_at ) )
					: __( 'The colours visitors see on your home page, including page builders. Not read yet.', 'layo-dashboard-toolkit' ),
			),
			'custom'   => array( __( 'Custom colours', 'layo-dashboard-toolkit' ), __( 'Colours you choose yourself in the Customizer.', 'layo-dashboard-toolkit' ) ),
		);

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="layodt-panel layodt-brand">';
		echo '<input type="hidden" name="action" value="layodt_save"><input type="hidden" name="tab" value="brand">';
		wp_nonce_field( 'layodt_save' );

		echo '<div class="layodt-brand__head"><div><h2>' . esc_html__( 'Brand kit', 'layo-dashboard-toolkit' ) . '</h2><p>' . esc_html__( 'Choose where your brand colours come from. The login page, emails, system pages and dashboard templates all use them. Logo, fonts and corners are set in the Customizer.', 'layo-dashboard-toolkit' ) . '</p></div>';
		echo wp_kses_post( self::customize_link( 'layodt_brand', __( 'Edit logo and fonts', 'layo-dashboard-toolkit' ) ) );
		echo '</div>';

		echo '<fieldset class="layodt-brand__options"><legend class="screen-reader-text">' . esc_html__( 'Brand colours from', 'layo-dashboard-toolkit' ) . '</legend>';
		foreach ( $options as $key => $option ) {
			$colors = 'logo' === $key && '' === $logo ? array() : Brand_Kit::palette_for( $key );

			echo '<label class="layodt-brand__option' . ( $current === $key ? ' is-selected' : '' ) . '" data-layodt-source="' . esc_attr( $key ) . '">';
			echo '<input type="radio" name="' . esc_attr( Fields::name( 'brand.source' ) ) . '" value="' . esc_attr( $key ) . '"' . checked( $current, $key, false ) . ( 'logo' === $key && '' === $logo ? ' disabled' : '' ) . '>';
			echo '<span class="layodt-brand__text"><strong>' . esc_html( $option[0] ) . '</strong><small data-layodt-source-note>' . esc_html( $option[1] ) . '</small></span>';
			echo '<span class="layodt-brand__swatches" data-layodt-swatches>';
			self::swatches( $colors );
			echo '</span>';
			echo '</label>';
		}
		echo '</fieldset>';

		echo '<div class="layodt-brand__actions">';
		submit_button( __( 'Save brand colours', 'layo-dashboard-toolkit' ), 'primary', 'submit', false );
		echo '<button type="button" class="button" data-layodt-capture>' . esc_html__( 'Read colours from my site', 'layo-dashboard-toolkit' ) . '</button>';
		echo '<span class="layodt-brand__status" role="status" aria-live="polite"></span>';
		echo '</div></form>';
	}

	/**
	 * Overview: module switches.
	 *
	 * @return void
	 */
	private static function tab_overview() {
		$modules = array(
			'login'          => array( __( 'Login page', 'layo-dashboard-toolkit' ), __( 'Layouts, colours, logo and text for every screen on wp-login.php.', 'layo-dashboard-toolkit' ), 'layodt_login_layout' ),
			'emails'         => array( __( 'Emails', 'layo-dashboard-toolkit' ), __( 'Branded HTML layout and editable wording for 15 core WordPress emails.', 'layo-dashboard-toolkit' ), 'layodt_emails' ),
			'error_pages'    => array( __( 'Error and system pages', 'layo-dashboard-toolkit' ), __( 'Critical error, database error, update maintenance, permission screens and an optional 404.', 'layo-dashboard-toolkit' ), 'layodt_errors_design' ),
			'tab_meta'       => array( __( 'Tab and page details', 'layo-dashboard-toolkit' ), __( 'Browser tab titles, favicon, address bar colour and hiding WordPress traces.', 'layo-dashboard-toolkit' ), '' ),
			'admin_branding' => array( __( 'Admin branding', 'layo-dashboard-toolkit' ), __( 'Admin bar logo, footer credit and a brand colour scheme.', 'layo-dashboard-toolkit' ), '' ),
			'roles'          => array( __( 'Roles and permissions', 'layo-dashboard-toolkit' ), __( 'Role editor, per-user permissions, menu access, login redirects, dashboard access and toolbar control.', 'layo-dashboard-toolkit' ), '' ),
			'admin_look'     => array( __( 'Dashboard look', 'layo-dashboard-toolkit' ), __( 'Admin templates with icons, colours, dark mode and a menu editor.', 'layo-dashboard-toolkit' ), '' ),
			'widgets'        => array( __( 'Dashboard widgets', 'layo-dashboard-toolkit' ), __( 'Show or hide widgets per role, custom widgets and a default layout.', 'layo-dashboard-toolkit' ), '' ),
			'sessions'       => array( __( 'Sessions', 'layo-dashboard-toolkit' ), __( 'See who is signed in and on which device, limit sessions per user, sign people out, block accounts and keep a sign-in history.', 'layo-dashboard-toolkit' ), '' ),
		);

		self::brand_kit();

		self::form_start( 'overview' );
		self::section( __( 'Features', 'layo-dashboard-toolkit' ), __( 'Switch off anything you do not need. Switched-off features load no code.', 'layo-dashboard-toolkit' ) );

		echo '<table class="form-table layodt-modules" role="presentation">';
		foreach ( $modules as $key => $module ) {
			$desc = $module[1];
			if ( $module[2] && Settings::module_on( $key ) ) {
				$desc .= ' ' . self::customize_link( $module[2], __( 'Design', 'layo-dashboard-toolkit' ) );
			}
			Fields::checkbox( 'modules.' . $key, $module[0], __( 'On', 'layo-dashboard-toolkit' ), $desc );
		}
		echo '</table>';

		self::section( __( 'Site status and data', 'layo-dashboard-toolkit' ) );
		echo '<table class="form-table" role="presentation">';

		Fields::row_start( __( 'Login protection', 'layo-dashboard-toolkit' ) );
		echo esc_html( Settings::module_on( 'security' ) ? __( 'On', 'layo-dashboard-toolkit' ) : __( 'Off', 'layo-dashboard-toolkit' ) ) . ' &middot; <a href="' . esc_url( Admin::url( 'security' ) ) . '">' . esc_html__( 'Configure', 'layo-dashboard-toolkit' ) . '</a>';
		Fields::row_end();

		Fields::row_start( __( 'Coming soon mode', 'layo-dashboard-toolkit' ) );
		echo esc_html( Settings::module_on( 'maintenance' ) ? __( 'On', 'layo-dashboard-toolkit' ) : __( 'Off', 'layo-dashboard-toolkit' ) ) . ' &middot; <a href="' . esc_url( Admin::url( 'maintenance' ) ) . '">' . esc_html__( 'Configure', 'layo-dashboard-toolkit' ) . '</a>';
		Fields::row_end();

		Fields::checkbox( 'general.remove_data', __( 'Uninstall', 'layo-dashboard-toolkit' ), __( 'Remove all Layò Dashboard Toolkit data when the plugin is deleted', 'layo-dashboard-toolkit' ), __( 'Leave this off if you might reinstall later.', 'layo-dashboard-toolkit' ) );
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
			echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'The Emails feature is switched off on the Overview tab, so these templates are not in use.', 'layo-dashboard-toolkit' ) . '</p></div>';
		}

		echo '<p class="layodt-intro">' . wp_kses_post(
			sprintf(
				/* translators: %s: Customizer link. */
				__( 'Edit the wording of each email here. Colours, logo and fonts are set in the Customizer. %s', 'layo-dashboard-toolkit' ),
				self::customize_link( 'layodt_emails', __( 'Design emails', 'layo-dashboard-toolkit' ) )
			)
		) . '</p>';

		self::email_sources();

		echo '<div class="layodt-emails">';

		echo '<nav class="layodt-emails__list" aria-label="' . esc_attr__( 'Email templates', 'layo-dashboard-toolkit' ) . '">';
		foreach ( array(
			'user'  => __( 'To users', 'layo-dashboard-toolkit' ),
			'admin' => __( 'To admins', 'layo-dashboard-toolkit' ),
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
					$on ? '' : ' <span class="layodt-badge">' . esc_html__( 'Off', 'layo-dashboard-toolkit' ) . '</span>'
				);
			}
			echo '</ul>';
		}
		echo '</nav>';

		echo '<div class="layodt-emails__editor">';
		echo '<h2>' . esc_html( $tpl['label'] ) . '</h2>';

		self::form_start( 'emails', array( 'template' => $current ) );
		echo '<table class="form-table" role="presentation">';
		Fields::checkbox( $prefix . 'enabled', __( 'Use this template', 'layo-dashboard-toolkit' ), __( 'On', 'layo-dashboard-toolkit' ), __( 'When off, WordPress sends its default plain-text email.', 'layo-dashboard-toolkit' ) );
		Fields::text( $prefix . 'subject', __( 'Subject', 'layo-dashboard-toolkit' ), '', array( 'class' => 'large-text' ) );
		Fields::text( $prefix . 'heading', __( 'Heading', 'layo-dashboard-toolkit' ), '', array( 'class' => 'large-text' ) );

		Fields::row_start( __( 'Body', 'layo-dashboard-toolkit' ), Fields::id( $prefix . 'body' ) );
		wp_editor(
			(string) Settings::get( $prefix . 'body' ),
			'layodt_email_body',
			array(
				'textarea_name' => Fields::name( $prefix . 'body' ),
				'textarea_rows' => 12,
				'media_buttons' => false,
				'teeny'         => false,
				'quicktags'     => true,
			)
		);
		Fields::row_end( __( 'Switch between Visual and Code (HTML) with the tabs above the editor.', 'layo-dashboard-toolkit' ) );

		if ( '' !== $tpl['link'] ) {
			Fields::text( $prefix . 'button', __( 'Button text', 'layo-dashboard-toolkit' ), __( 'Leave empty to hide the button.', 'layo-dashboard-toolkit' ) );
		}

		Fields::row_start( __( 'Available tags', 'layo-dashboard-toolkit' ) );
		echo '<div class="layodt-tags">';
		foreach ( array_merge( $tpl['tags'], Templates::common_tags() ) as $tag ) {
			printf( '<button type="button" class="button button-small" data-layodt-tag="%1$s">%1$s</button>', esc_attr( '{' . $tag . '}' ) );
		}
		echo '</div>';
		Fields::row_end( __( 'Click a tag to insert it into the body.', 'layo-dashboard-toolkit' ) );
		echo '</table>';

		echo '<p class="submit">';
		submit_button( __( 'Save template', 'layo-dashboard-toolkit' ), 'primary', 'submit', false );
		printf(
			' <a class="button" target="_blank" rel="noopener" href="%s">%s</a>',
			esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=layodt_email_preview&template=' . $current ), 'layodt_email_preview' ) ),
			esc_html__( 'Preview saved version', 'layo-dashboard-toolkit' )
		);
		echo '</p></form>';

		echo '<div class="layodt-inline-forms">';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="layodt-inline-form">';
		echo '<input type="hidden" name="action" value="layodt_test_email"><input type="hidden" name="template" value="' . esc_attr( $current ) . '">';
		wp_nonce_field( 'layodt_test_email' );
		echo '<label for="layodt-to">' . esc_html__( 'Send a test to', 'layo-dashboard-toolkit' ) . '</label> ';
		echo '<input type="email" id="layodt-to" name="layodt_to" class="regular-text" value="' . esc_attr( wp_get_current_user()->user_email ) . '" required> ';
		submit_button( __( 'Send test email', 'layo-dashboard-toolkit' ), 'secondary', 'submit', false );
		echo '</form>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="layodt-inline-form" data-layodt-confirm>';
		echo '<input type="hidden" name="action" value="layodt_reset_template"><input type="hidden" name="template" value="' . esc_attr( $current ) . '">';
		wp_nonce_field( 'layodt_reset_template' );
		submit_button( __( 'Restore default text', 'layo-dashboard-toolkit' ), 'link-delete', 'submit', false );
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

		self::section( __( 'Browser tab titles', 'layo-dashboard-toolkit' ), __( 'Use {page} for the screen name and {site_name} for your site title. Leave a box empty to keep WordPress\'s wording.', 'layo-dashboard-toolkit' ) );
		echo '<table class="form-table" role="presentation">';
		Fields::text( 'tab_meta.admin_title', __( 'Admin screens', 'layo-dashboard-toolkit' ), __( 'WordPress default: "Dashboard ‹ Site — WordPress".', 'layo-dashboard-toolkit' ) );
		Fields::text(
			'tab_meta.login_title',
			__( 'Login screens', 'layo-dashboard-toolkit' ),
			sprintf(
				/* translators: %s: link to the Customizer section. */
				__( 'WordPress default: "Log In ‹ Site — WordPress". To rename the screens themselves ("Log In", "Lost Password" and so on), use <a href="%s">Login: wording</a> in the Customizer.', 'layo-dashboard-toolkit' ),
				esc_url( admin_url( 'customize.php?autofocus[section]=layodt_login_words' ) )
			)
		);
		Fields::text( 'tab_meta.system_title', __( 'Error and notice pages', 'layo-dashboard-toolkit' ), __( 'The critical error, database error, maintenance, no permission, expired link and 404 pages. {page} is the page title.', 'layo-dashboard-toolkit' ) );
		Fields::text( 'tab_meta.front_separator', __( 'Site page separator', 'layo-dashboard-toolkit' ), __( 'The character between the page name and the site name on your site\'s pages, for example | or ·. Usually a dash.', 'layo-dashboard-toolkit' ), array( 'class' => 'small-text' ) );
		Fields::text( 'tab_meta.front_404_title', __( 'Site 404 page', 'layo-dashboard-toolkit' ), __( 'WordPress default: "Page not found". Works with any theme that lets WordPress set the title.', 'layo-dashboard-toolkit' ) );
		Fields::text( 'tab_meta.front_search_title', __( 'Site search results', 'layo-dashboard-toolkit' ), __( 'Use {query} for the search words. WordPress default: "Search Results for “{query}”".', 'layo-dashboard-toolkit' ) );
		Fields::checkbox( 'tab_meta.hide_home_tagline', __( 'Home page', 'layo-dashboard-toolkit' ), __( 'Show only the site name on the home page tab, without the tagline', 'layo-dashboard-toolkit' ) );
		echo '</table>';

		self::section( __( 'Icons and colours', 'layo-dashboard-toolkit' ) );
		echo '<table class="form-table" role="presentation">';
		Fields::url(
			'tab_meta.favicon_image',
			__( 'Favicon', 'layo-dashboard-toolkit' ),
			sprintf(
				/* translators: %s: link to Settings > General. */
				__( 'A square image, at least 512 × 512 pixels. Leave empty to use the site icon from <a href="%s">Settings &rarr; General</a>.', 'layo-dashboard-toolkit' ),
				esc_url( admin_url( 'options-general.php' ) )
			),
			true
		);
		Fields::checkbox( 'tab_meta.favicon', __( 'Icon everywhere', 'layo-dashboard-toolkit' ), __( 'Show the icon on login, admin and system pages, and use the brand logo when there is no icon', 'layo-dashboard-toolkit' ) );
		Fields::checkbox( 'tab_meta.favicon_ico', __( '/favicon.ico', 'layo-dashboard-toolkit' ), __( 'Answer browser requests for /favicon.ico with your icon instead of the WordPress logo', 'layo-dashboard-toolkit' ), __( 'Has no effect if a favicon.ico file is already in your site\'s main folder.', 'layo-dashboard-toolkit' ) );
		Fields::checkbox( 'tab_meta.theme_color', __( 'Address bar colour', 'layo-dashboard-toolkit' ), __( 'Tint the mobile browser address bar with the brand primary colour', 'layo-dashboard-toolkit' ) );
		echo '</table>';

		self::section( __( 'WordPress traces', 'layo-dashboard-toolkit' ), __( 'Removes giveaways in your page source and public addresses. This is tidying, not a full security feature.', 'layo-dashboard-toolkit' ) );
		echo '<table class="form-table" role="presentation">';
		Fields::checkbox( 'tab_meta.hide_generator', __( 'Generator tag', 'layo-dashboard-toolkit' ), __( 'Remove the WordPress version meta tag and feed generator', 'layo-dashboard-toolkit' ) );
		Fields::checkbox( 'tab_meta.hide_versions', __( 'Asset versions', 'layo-dashboard-toolkit' ), __( 'Remove the WordPress version from core CSS and JavaScript URLs', 'layo-dashboard-toolkit' ) );
		Fields::checkbox( 'tab_meta.hide_rsd_wlw', __( 'Legacy links', 'layo-dashboard-toolkit' ), __( 'Remove the RSD and Windows Live Writer links', 'layo-dashboard-toolkit' ) );
		Fields::checkbox( 'tab_meta.hide_rest_links', __( 'API and embed links', 'layo-dashboard-toolkit' ), __( 'Remove the REST API, oEmbed and shortlink tags and headers from your pages', 'layo-dashboard-toolkit' ), __( 'The REST API itself keeps working.', 'layo-dashboard-toolkit' ) );
		Fields::checkbox( 'tab_meta.hide_emoji', __( 'Emoji script', 'layo-dashboard-toolkit' ), __( 'Remove WordPress\'s emoji script and styles', 'layo-dashboard-toolkit' ), __( 'Browsers show emoji on their own.', 'layo-dashboard-toolkit' ) );
		Fields::checkbox( 'tab_meta.disable_xmlrpc', __( 'XML-RPC and pingbacks', 'layo-dashboard-toolkit' ), __( 'Turn off XML-RPC, pingbacks and the X-Pingback header', 'layo-dashboard-toolkit' ), __( 'Leave this off if you use Jetpack or post from the WordPress mobile app.', 'layo-dashboard-toolkit' ) );
		Fields::checkbox( 'tab_meta.block_user_lookup', __( 'Username lookups', 'layo-dashboard-toolkit' ), __( 'Stop visitors finding usernames through ?author= addresses, the REST API user list and the author sitemap', 'layo-dashboard-toolkit' ), __( 'Logged-in users are not affected.', 'layo-dashboard-toolkit' ) );
		Fields::checkbox( 'tab_meta.hide_login_wp_link', __( 'Login logo link', 'layo-dashboard-toolkit' ), __( 'Point the login logo to your site instead of wordpress.org', 'layo-dashboard-toolkit' ), __( 'Applies when the Login page feature is off; that feature has its own setting.', 'layo-dashboard-toolkit' ) );
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
			'keep'   => __( 'WordPress default', 'layo-dashboard-toolkit' ),
			'custom' => __( 'Custom text', 'layo-dashboard-toolkit' ),
			'hide'   => __( 'Remove', 'layo-dashboard-toolkit' ),
		);

		self::form_start( 'admin_branding' );

		self::section( __( 'Admin bar', 'layo-dashboard-toolkit' ) );
		echo '<table class="form-table" role="presentation">';
		Fields::select(
			'admin_branding.adminbar_logo',
			__( 'WordPress logo', 'layo-dashboard-toolkit' ),
			array(
				'keep'    => __( 'Keep the WordPress logo', 'layo-dashboard-toolkit' ),
				'replace' => __( 'Replace with my logo', 'layo-dashboard-toolkit' ),
				'remove'  => __( 'Remove it', 'layo-dashboard-toolkit' ),
			)
		);
		Fields::url( 'admin_branding.adminbar_image', __( 'Logo image', 'layo-dashboard-toolkit' ), __( 'Shown 20px tall. Defaults to your site icon.', 'layo-dashboard-toolkit' ), true, Fields::show_if( 'admin_branding.adminbar_logo', 'replace' ) );
		Fields::url( 'admin_branding.adminbar_link', __( 'Logo link', 'layo-dashboard-toolkit' ), __( 'Defaults to your home page.', 'layo-dashboard-toolkit' ), false, Fields::show_if( 'admin_branding.adminbar_logo', 'replace' ) );
		Fields::checkbox( 'admin_branding.hide_howdy', __( 'Greeting', 'layo-dashboard-toolkit' ), __( 'Show just the name instead of "Howdy, Name"', 'layo-dashboard-toolkit' ) );
		echo '</table>';

		self::section( __( 'Footer credit', 'layo-dashboard-toolkit' ) );
		echo '<table class="form-table" role="presentation">';
		Fields::select( 'admin_branding.footer_left', __( '"Thank you for creating with WordPress"', 'layo-dashboard-toolkit' ), $keep );
		Fields::textarea( 'admin_branding.footer_left_text', __( 'Left footer text', 'layo-dashboard-toolkit' ), __( 'Basic HTML and {site_name}, {site_url} and {year} are allowed.', 'layo-dashboard-toolkit' ), 2, Fields::show_if( 'admin_branding.footer_left', 'custom' ) );
		Fields::select( 'admin_branding.footer_right', __( 'WordPress version', 'layo-dashboard-toolkit' ), $keep );
		Fields::textarea( 'admin_branding.footer_right_text', __( 'Right footer text', 'layo-dashboard-toolkit' ), '', 2, Fields::show_if( 'admin_branding.footer_right', 'custom' ) );
		echo '</table>';

		self::section(
			__( 'Admin screens', 'layo-dashboard-toolkit' ),
			sprintf(
				/* translators: %s: link to the Dashboard widgets tab. */
				__( 'Dashboard widgets, including the welcome panel, are managed on the <a href="%s">Dashboard widgets</a> tab.', 'layo-dashboard-toolkit' ),
				esc_url( Admin::url( 'widgets' ) )
			)
		);
		echo '<table class="form-table" role="presentation">';
		Fields::checkbox( 'admin_branding.hide_help', __( 'Help tab', 'layo-dashboard-toolkit' ), __( 'Remove the Help tab from admin screens', 'layo-dashboard-toolkit' ) );
		Fields::checkbox( 'admin_branding.hide_screen_opts', __( 'Screen Options', 'layo-dashboard-toolkit' ), __( 'Remove the Screen Options tab from admin screens', 'layo-dashboard-toolkit' ) );
		echo '</table>';

		self::section( __( 'Colour scheme', 'layo-dashboard-toolkit' ) );
		echo '<table class="form-table" role="presentation">';
		Fields::checkbox(
			'admin_branding.color_scheme',
			__( 'Brand scheme', 'layo-dashboard-toolkit' ),
			__( 'Add a colour scheme built from the brand kit', 'layo-dashboard-toolkit' ),
			sprintf(
				/* translators: %s: profile URL. */
				__( 'Each user picks it under Admin Color Scheme on <a href="%s">their profile</a>.', 'layo-dashboard-toolkit' ),
				esc_url( admin_url( 'profile.php' ) )
			)
		);
		Fields::checkbox( 'admin_branding.default_scheme', __( 'New users', 'layo-dashboard-toolkit' ), __( 'Give new users the brand scheme by default', 'layo-dashboard-toolkit' ), '', Fields::show_if( 'admin_branding.color_scheme' ) );
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
			__( 'Custom dashboard address', 'layo-dashboard-toolkit' ),
			__( 'Moves the dashboard from /wp-admin to an address you choose. Logged-out visitors who open /wp-admin get a "page not found" page; logged-in users are moved to the new address, so old links keep working. If you ever get locked out, add <code>define( \'LAYODT_DISABLE_ADMIN_URL\', true );</code> to wp-config.php.', 'layo-dashboard-toolkit' )
		);

		if ( Admin_Url::disabled() ) {
			echo '<div class="notice notice-info inline"><p>' . esc_html__( 'Not available: this needs a single site (not multisite), and the LAYODT_DISABLE_ADMIN_URL constant must not be set.', 'layo-dashboard-toolkit' ) . '</p></div>';
			return;
		}

		$on     = Settings::get( 'security.admin_url' ) && '' !== Admin_Url::slug();
		$active = $on && Admin_Url::active();
		$server = Admin_Url::server();

		if ( $active ) {
			echo '<div class="notice notice-success inline"><p>' . esc_html__( 'Your dashboard is at:', 'layo-dashboard-toolkit' ) . ' <a href="' . esc_url( Admin_Url::url() ) . '"><strong>' . esc_html( Admin_Url::url() ) . '</strong></a></p></div>';
		}

		echo '<table class="form-table" role="presentation">';
		Fields::checkbox( 'security.admin_url', __( 'Custom dashboard address', 'layo-dashboard-toolkit' ), __( 'On', 'layo-dashboard-toolkit' ) );
		Fields::text(
			'security.admin_slug',
			__( 'Dashboard address', 'layo-dashboard-toolkit' ),
			__( 'Letters, numbers and dashes, for example "dashboard" or "office". Must differ from the login address.', 'layo-dashboard-toolkit' ),
			array(
				'prefix' => trailingslashit( site_url() ),
				'class'  => 'regular-text layodt-slug',
			),
			Fields::show_if( 'security.admin_url' )
		);

		if ( $on && ! $active ) {
			Fields::row_start( __( 'Server setup', 'layo-dashboard-toolkit' ) );

			if ( 'nginx' === $server ) {
				echo '<p>' . esc_html__( 'Your site runs on Nginx. Add these lines inside the server block of your site\'s Nginx configuration, above the "location /" block, and reload Nginx. Your host can do this for you.', 'layo-dashboard-toolkit' ) . '</p>';
				echo '<pre class="layodt-code">' . esc_html( Admin_Url::nginx_rules() ) . '</pre>';
			} else {
				echo '<p>' . esc_html__( 'Add these lines at the top of the .htaccess file in your site\'s root folder, above the WordPress block. If your server is not Apache or LiteSpeed, ask your host to rewrite the new address to /wp-admin.', 'layo-dashboard-toolkit' ) . '</p>';
				echo '<pre class="layodt-code">' . esc_html( "# BEGIN Layo Dashboard Toolkit\n" . implode( "\n", Admin_Url::apache_rules() ) . "\n# END Layo Dashboard Toolkit" ) . '</pre>';
			}

			$test = untrailingslashit( site_url() ) . '/' . Admin_Url::slug() . '/admin-ajax.php?action=layodt_ping';

			echo '<p class="layodt-actions">';
			echo '<button type="submit" class="button button-primary" form="layodt-admin-url-test">' . esc_html__( 'Test again', 'layo-dashboard-toolkit' ) . '</button> ';
			echo '<a class="button" target="_blank" rel="noopener" href="' . esc_url( $test ) . '">' . esc_html__( 'Open the test page', 'layo-dashboard-toolkit' ) . '</a> ';
			echo '<button type="submit" class="button button-link-delete" form="layodt-admin-url-force">' . esc_html__( 'Activate anyway', 'layo-dashboard-toolkit' ) . '</button>';
			echo '</p>';
			Fields::row_end( __( 'The test page should show {"success":true,...}. Some servers cannot test themselves; if the test page works in your browser but "Test again" fails, use "Activate anyway".', 'layo-dashboard-toolkit' ) );
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
			echo '<form id="layodt-admin-url-' . esc_attr( $action ) . '" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"' . ( 'force' === $action ? ' data-layodt-confirm' : '' ) . '>';
			echo '<input type="hidden" name="action" value="layodt_admin_url_' . esc_attr( $action ) . '">';
			wp_nonce_field( 'layodt_admin_url_' . $action );
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
			__( 'Custom login URL', 'layo-dashboard-toolkit' ),
			__( 'Moves the login page so bots stop hammering wp-login.php. If you ever get locked out, add <code>define( \'LAYODT_DISABLE_LOGIN_URL\', true );</code> to wp-config.php.', 'layo-dashboard-toolkit' )
		);

		if ( $on_url ) {
			echo '<div class="notice notice-info inline"><p>' . esc_html__( 'Your login page is at:', 'layo-dashboard-toolkit' ) . ' <a href="' . esc_url( Login_Url::url() ) . '"><strong>' . esc_html( Login_Url::url() ) . '</strong></a></p></div>';
		}

		$pages = Login_Pages::found( true );
		$other = Login_Pages::other_login();

		if ( $pages ) {
			echo '<div class="layodt-callout"><p><strong>' . esc_html__( 'Login pages set up elsewhere on this site', 'layo-dashboard-toolkit' ) . '</strong></p>';
			echo '<table class="widefat striped layodt-login-pages"><thead><tr><th scope="col">' . esc_html__( 'Page', 'layo-dashboard-toolkit' ) . '</th><th scope="col">' . esc_html__( 'For', 'layo-dashboard-toolkit' ) . '</th><th scope="col">' . esc_html__( 'Address', 'layo-dashboard-toolkit' ) . '</th><th scope="col">' . esc_html__( 'Set by', 'layo-dashboard-toolkit' ) . '</th></tr></thead><tbody>';
			$kinds = Login_Pages::kinds();
			foreach ( $pages as $page ) {
				printf(
					'<tr><td>%s</td><td>%s</td><td><a href="%s">%s</a></td><td>%s</td></tr>',
					esc_html( $kinds[ $page['kind'] ] ),
					'customer' === $page['audience'] ? esc_html__( 'Customers and members', 'layo-dashboard-toolkit' ) : esc_html__( 'Staff', 'layo-dashboard-toolkit' ),
					esc_url( $page['url'] ),
					esc_html( $page['url'] ),
					esc_html( $page['source'] )
				);
			}
			echo '</tbody></table>';
			echo '<p class="description">' . esc_html__( 'The custom login URL on this tab is the staff login: administrators, editors, subscribers and everyone else with an account on the site. Customer and member pages are left to the plugin that made them; they never clash with it, and they close with the rest of the site in coming soon mode. Staff login pages stay open then, and links in emails use whichever page is in use.', 'layo-dashboard-toolkit' ) . '</p>';
			if ( $other ) {
				echo '<p>' . esc_html(
					sprintf(
						/* translators: 1: who set the login page, 2: its address. */
						__( '%1$s already moves the login page to %2$s. Turning on a custom login URL here as well would make the two fight over it, so it stays off unless you tick "Use mine anyway" below.', 'layo-dashboard-toolkit' ),
						$other['source'],
						$other['url']
					)
				) . '</p>';
			}
			echo '</div>';
		}

		echo '<table class="form-table" role="presentation">';
		Fields::checkbox( 'security.login_url', __( 'Custom login URL', 'layo-dashboard-toolkit' ), __( 'On', 'layo-dashboard-toolkit' ) );
		if ( $other ) {
			Fields::row_start( __( 'Another login page', 'layo-dashboard-toolkit' ), 'layodt-clash-ok' );
			echo '<label><input type="checkbox" id="layodt-clash-ok" name="layodt_clash_ok" value="1"' . checked( (bool) Settings::get( 'security.login_url' ), true, false ) . '> ' . esc_html__( 'Use mine anyway', 'layo-dashboard-toolkit' ) . '</label>';
			Fields::row_end( __( 'Only tick this if you are switching the other login page off.', 'layo-dashboard-toolkit' ) );
		}
		Fields::text(
			'security.login_slug',
			__( 'Login address', 'layo-dashboard-toolkit' ),
			__( 'Letters, numbers and dashes, for example "login" or "team-login". Must differ from the dashboard address.', 'layo-dashboard-toolkit' ),
			array(
				'prefix' => trailingslashit( home_url() ),
				'class'  => 'regular-text layodt-slug',
			),
			Fields::show_if( 'security.login_url' )
		);
		Fields::select(
			'security.blocked_action',
			__( 'When someone opens wp-login.php or /wp-admin', 'layo-dashboard-toolkit' ),
			array(
				'404'    => __( 'Show a "page not found" page', 'layo-dashboard-toolkit' ),
				'home'   => __( 'Send them to the home page', 'layo-dashboard-toolkit' ),
				'custom' => __( 'Send them to another address', 'layo-dashboard-toolkit' ),
			),
			'',
			Fields::show_if( 'security.login_url' )
		);
		Fields::url( 'security.blocked_url', __( 'Other address', 'layo-dashboard-toolkit' ), '', false, Fields::show_if( 'security.blocked_action', 'custom' ) );
		echo '</table>';

		self::admin_url_section();

		self::section( __( 'Limit login attempts', 'layo-dashboard-toolkit' ) );
		echo '<table class="form-table" role="presentation">';
		Fields::checkbox( 'security.limit', __( 'Limit attempts', 'layo-dashboard-toolkit' ), __( 'On', 'layo-dashboard-toolkit' ) );
		Fields::number( 'security.max_attempts', __( 'Failed attempts allowed', 'layo-dashboard-toolkit' ), '', Fields::show_if( 'security.limit' ) );
		Fields::number( 'security.window', __( 'Within (minutes)', 'layo-dashboard-toolkit' ), '', Fields::show_if( 'security.limit' ) );
		Fields::number( 'security.lockout', __( 'Lockout length (minutes)', 'layo-dashboard-toolkit' ), '', Fields::show_if( 'security.limit' ) );
		Fields::textarea( 'security.allowlist', __( 'Never lock out these IPs', 'layo-dashboard-toolkit' ), __( 'One per line. IPv4 ranges such as 192.168.1.0/24 work too.', 'layo-dashboard-toolkit' ), 3, Fields::show_if( 'security.limit' ) );
		Fields::checkbox(
			'security.trust_proxy',
			__( 'Proxy headers', 'layo-dashboard-toolkit' ),
			__( 'My site is behind Cloudflare or another proxy', 'layo-dashboard-toolkit' ),
			/* translators: %s: visitor IP address. */
			sprintf( __( 'Only turn this on if every request passes through a proxy, or visitors could fake their IP. Your IP as seen now: %s', 'layo-dashboard-toolkit' ), '<code>' . esc_html( Attempts::ip() ) . '</code>' ),
			Fields::show_if( 'security.limit' )
		);
		echo '</table>';

		self::section( __( 'Honeypot', 'layo-dashboard-toolkit' ) );
		echo '<table class="form-table" role="presentation">';
		Fields::checkbox( 'security.honeypot', __( 'Honeypot field', 'layo-dashboard-toolkit' ), __( 'Add a hidden field to the login, registration and lost password forms', 'layo-dashboard-toolkit' ), __( 'People never see it. Bots that fill in every field get rejected.', 'layo-dashboard-toolkit' ) );
		echo '</table>';

		self::section(
			__( 'Two-factor authentication', 'layo-dashboard-toolkit' ),
			__( 'Users turn it on from their profile with an authenticator app or email codes, and get one-time backup codes. If you get locked out, add <code>define( \'LAYODT_DISABLE_2FA\', true );</code> to wp-config.php.', 'layo-dashboard-toolkit' )
		);
		echo '<table class="form-table" role="presentation">';
		Fields::checkbox( 'security.twofa', __( 'Two-factor', 'layo-dashboard-toolkit' ), __( 'On', 'layo-dashboard-toolkit' ) );
		Fields::checkbox( 'security.twofa_email', __( 'Email codes', 'layo-dashboard-toolkit' ), __( 'Allow codes by email, as a method and as a fallback', 'layo-dashboard-toolkit' ), __( 'Email is less secure than an app but easier for most people.', 'layo-dashboard-toolkit' ), Fields::show_if( 'security.twofa' ) );
		Fields::roles( 'security.twofa_roles', __( 'Require for these roles', 'layo-dashboard-toolkit' ), __( 'Users with these roles must set it up before the grace period ends.', 'layo-dashboard-toolkit' ), Fields::show_if( 'security.twofa' ) );
		Fields::number( 'security.twofa_grace', __( 'Grace period (days)', 'layo-dashboard-toolkit' ), __( '0 means they must set it up straight away.', 'layo-dashboard-toolkit' ), Fields::show_if( 'security.twofa' ) );
		echo '</table>';

		self::form_end();
		self::admin_url_forms();
		self::social_form();

		$log = Attempts::get_log();

		if ( ! $log ) {
			return;
		}

		self::section( __( 'Recent lockouts', 'layo-dashboard-toolkit' ) );
		echo '<table class="widefat striped layodt-log"><thead><tr><th>' . esc_html__( 'When', 'layo-dashboard-toolkit' ) . '</th><th>' . esc_html__( 'IP address', 'layo-dashboard-toolkit' ) . '</th><th>' . esc_html__( 'Username tried', 'layo-dashboard-toolkit' ) . '</th><th>' . esc_html__( 'Status', 'layo-dashboard-toolkit' ) . '</th></tr></thead><tbody>';

		foreach ( array_slice( $log, 0, 50 ) as $entry ) {
			$active = $entry['until'] > time() && Attempts::locked_until( $entry['ip'] );
			echo '<tr><td>' . esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $entry['time'] ) ) . '</td>';
			echo '<td><code>' . esc_html( $entry['ip'] ) . '</code></td><td>' . esc_html( $entry['username'] ) . '</td><td>';

			if ( $active ) {
				echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="layodt_unlock"><input type="hidden" name="ip" value="' . esc_attr( $entry['ip'] ) . '">';
				wp_nonce_field( 'layodt_unlock' );
				submit_button( __( 'Unlock', 'layo-dashboard-toolkit' ), 'small', 'submit', false );
				echo '</form>';
			} else {
				esc_html_e( 'Expired', 'layo-dashboard-toolkit' );
			}

			echo '</td></tr>';
		}

		echo '</tbody></table>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" data-layodt-confirm><input type="hidden" name="action" value="layodt_clear_log">';
		wp_nonce_field( 'layodt_clear_log' );
		submit_button( __( 'Clear log', 'layo-dashboard-toolkit' ), 'link-delete' );
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
			__( 'Coming soon and maintenance mode', 'layo-dashboard-toolkit' ),
			__( 'Visitors see a holding page while you work: the design from the Customizer, or a page you built yourself, for example with Elementor.', 'layo-dashboard-toolkit' )
		);

		echo '<table class="form-table" role="presentation">';
		Fields::checkbox( 'modules.maintenance', __( 'Mode', 'layo-dashboard-toolkit' ), __( 'On', 'layo-dashboard-toolkit' ), __( 'Administrators always see the real site.', 'layo-dashboard-toolkit' ) );
		Fields::select(
			'maintenance.mode',
			__( 'Type', 'layo-dashboard-toolkit' ),
			array(
				'coming_soon' => __( 'Coming soon (for a site that has not launched)', 'layo-dashboard-toolkit' ),
				'maintenance' => __( 'Maintenance (tells search engines to come back later)', 'layo-dashboard-toolkit' ),
			)
		);
		echo '</table>';

		self::section( __( 'Holding page', 'layo-dashboard-toolkit' ) );
		echo '<table class="form-table" role="presentation">';
		Fields::select(
			'maintenance.design',
			__( 'Show', 'layo-dashboard-toolkit' ),
			array(
				'customizer' => __( 'The page designed in the Customizer', 'layo-dashboard-toolkit' ),
				'page'       => __( 'A page I built', 'layo-dashboard-toolkit' ),
			)
		);

		Fields::row_start( __( 'Design', 'layo-dashboard-toolkit' ), '', Fields::show_if( 'maintenance.design', 'customizer' ) );
		echo wp_kses_post( self::customize_link( 'layodt_maintenance', __( 'Design the page', 'layo-dashboard-toolkit' ) ) );
		Fields::row_end( __( 'Headline, message, countdown, logo and background, with a live preview.', 'layo-dashboard-toolkit' ) );

		$choices = Maintenance::choices();
		$current = (int) Settings::get( 'maintenance.page' );

		Fields::row_start( __( 'Page', 'layo-dashboard-toolkit' ), Fields::id( 'maintenance.page' ), Fields::show_if( 'maintenance.design', 'page' ) );
		if ( $choices ) {
			echo '<select id="' . esc_attr( Fields::id( 'maintenance.page' ) ) . '" name="' . esc_attr( Fields::name( 'maintenance.page' ) ) . '">';
			echo '<option value="0">' . esc_html__( 'Choose a page', 'layo-dashboard-toolkit' ) . '</option>';
			foreach ( $choices as $group => $items ) {
				echo '<optgroup label="' . esc_attr( $group ) . '">';
				foreach ( $items as $id => $title ) {
					echo '<option value="' . esc_attr( (string) $id ) . '"' . selected( $current, $id, false ) . '>' . esc_html( $title ) . '</option>';
				}
				echo '</optgroup>';
			}
			echo '</select>';
			if ( Maintenance::page_id() ) {
				echo ' <a class="button" href="' . esc_url( Maintenance::edit_url( Maintenance::page_id() ) ) . '">' . esc_html( Maintenance::is_elementor( Maintenance::page_id() ) || 'elementor_library' === get_post_type( Maintenance::page_id() ) ? __( 'Edit with Elementor', 'layo-dashboard-toolkit' ) : __( 'Edit page', 'layo-dashboard-toolkit' ) ) . '</a>';
			}
		} else {
			echo '<p>' . esc_html__( 'No pages yet. Create one under Pages, or save a page template in Elementor.', 'layo-dashboard-toolkit' ) . '</p>';
		}
		Fields::row_end( __( 'Any page, including drafts and private pages, or an Elementor page template. It fills the whole screen without your theme\'s header and footer, so it can be a draft that visitors never reach any other way. If the page is deleted, the Customizer design is shown instead.', 'layo-dashboard-toolkit' ) );

		if ( Settings::module_on( 'maintenance' ) ) {
			Fields::row_start( __( 'Check it', 'layo-dashboard-toolkit' ) );
			echo '<a class="button" href="' . esc_url( add_query_arg( Maintenance::PREVIEW, '1', home_url( '/' ) ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'View the holding page', 'layo-dashboard-toolkit' ) . '</a>';
			Fields::row_end( __( 'Opens the page visitors see. Save first if you changed anything.', 'layo-dashboard-toolkit' ) );
		}
		echo '</table>';

		self::section( __( 'Who can see the real site', 'layo-dashboard-toolkit' ) );
		echo '<table class="form-table" role="presentation">';
		Fields::roles( 'maintenance.roles', __( 'Roles', 'layo-dashboard-toolkit' ), __( 'Logged-in users with these roles.', 'layo-dashboard-toolkit' ) );
		Fields::textarea( 'maintenance.allowed_paths', __( 'Always open pages', 'layo-dashboard-toolkit' ), __( 'One path per line, such as /contact or /privacy-policy.', 'layo-dashboard-toolkit' ), 3 );

		Fields::row_start( __( 'Bypass link', 'layo-dashboard-toolkit' ) );
		if ( '' !== $key ) {
			$link = add_query_arg( 'layodt_bypass', $key, home_url( '/' ) );
			echo '<input type="hidden" name="' . esc_attr( Fields::name( 'maintenance.bypass_key' ) ) . '" value="' . esc_attr( $key ) . '">';
			echo '<code class="layodt-copyable">' . esc_html( $link ) . '</code>';
			echo '<p><label><input type="checkbox" name="' . esc_attr( Fields::name( 'maintenance.bypass_key' ) ) . '" value=""> ' . esc_html__( 'Remove this link', 'layo-dashboard-toolkit' ) . '</label></p>';
		}
		echo '<p><label><input type="checkbox" name="layodt_new_bypass" value="1"> ' . esc_html( '' !== $key ? __( 'Create a new link (the old one stops working)', 'layo-dashboard-toolkit' ) : __( 'Create a link', 'layo-dashboard-toolkit' ) ) . '</label></p>';
		Fields::row_end( __( 'Anyone who opens this link sees the real site for a week. Useful for clients.', 'layo-dashboard-toolkit' ) );
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
		echo '<fieldset class="layodt-parts"><legend>' . esc_html__( 'Include', 'layo-dashboard-toolkit' ) . '</legend>';
		foreach ( $parts as $key => $part ) {
			printf(
				'<label><input type="checkbox" name="layodt_parts[]" value="%1$s"%2$s> <span><strong>%3$s</strong><small>%4$s</small></span></label>',
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
			__( 'Review the import', 'layo-dashboard-toolkit' ),
			sprintf(
				/* translators: 1: site address, 2: date. */
				__( 'Exported from %1$s on %2$s. Nothing changes until you click Import.', 'layo-dashboard-toolkit' ),
				isset( $data['site'] ) ? esc_html( $data['site'] ) : esc_html__( 'another site', 'layo-dashboard-toolkit' ),
				isset( $data['exported'] ) ? esc_html( wp_date( get_option( 'date_format' ), (int) strtotime( $data['exported'] ) ) ) : '&mdash;'
			)
		);

		echo '<form method="post" action="' . esc_url( $post ) . '" id="layodt-import-review" data-layodt-confirm><input type="hidden" name="action" value="layodt_import_apply">';
		wp_nonce_field( 'layodt_import_apply' );

		if ( $parts ) {
			echo '<div class="layodt-card">';
			self::transfer_parts( $parts, array_keys( $parts ) );
			echo '<p class="description">' . esc_html__( 'Each ticked part replaces the same part on this site. Pages and categories are matched by their slug. Image addresses still point to the original site.', 'layo-dashboard-toolkit' ) . '</p></div>';
		}

		if ( $rows ) {
			self::section( __( 'Roles', 'layo-dashboard-toolkit' ), __( 'Tick the roles to bring across. Each comes with all of its capabilities: a role this site does not have is added, and a role it already has is given the file\'s capabilities. Roles you leave unticked, and roles that are only on this site, stay as they are. A backup of your roles is saved first.', 'layo-dashboard-toolkit' ) );

			echo '<p class="layodt-actions"><button type="button" class="button button-small" data-layodt-tick="all">' . esc_html__( 'Tick all', 'layo-dashboard-toolkit' ) . '</button> <button type="button" class="button button-small" data-layodt-tick="new">' . esc_html__( 'Only roles new to this site', 'layo-dashboard-toolkit' ) . '</button> <button type="button" class="button button-small" data-layodt-tick="none">' . esc_html__( 'Tick none', 'layo-dashboard-toolkit' ) . '</button></p>';

			echo '<table class="widefat striped layodt-import-roles"><thead><tr><th scope="col" class="check-column"><span class="screen-reader-text">' . esc_html__( 'Import', 'layo-dashboard-toolkit' ) . '</span></th><th scope="col">' . esc_html__( 'Role', 'layo-dashboard-toolkit' ) . '</th><th scope="col">' . esc_html__( 'On this site', 'layo-dashboard-toolkit' ) . '</th><th scope="col">' . esc_html__( 'Capabilities in the file', 'layo-dashboard-toolkit' ) . '</th><th scope="col">' . esc_html__( 'People here', 'layo-dashboard-toolkit' ) . '</th></tr></thead><tbody>';

			foreach ( $rows as $row ) {
				$locked = 'locked' === $row['status'];
				$new    = 'new' === $row['status'];

				echo '<tr data-status="' . esc_attr( $row['status'] ) . '"><th scope="row" class="check-column">';
				if ( $locked ) {
					echo '<input type="checkbox" disabled aria-label="' . esc_attr( $row['name'] ) . '">';
				} else {
					printf( '<input type="checkbox" name="layodt_roles[]" value="%1$s"%2$s aria-label="%3$s">', esc_attr( $row['slug'] ), checked( $new, true, false ), esc_attr( sprintf( /* translators: %s: role name. */ __( 'Import %s', 'layo-dashboard-toolkit' ), $row['name'] ) ) );
				}
				echo '</th><td><strong>' . esc_html( $row['name'] ) . '</strong><br><code>' . esc_html( $row['slug'] ) . '</code>';
				if ( $row['used'] && $new ) {
					echo '<br><small>' . esc_html__( 'Rules in the file use this role. Leave it unticked and they are skipped.', 'layo-dashboard-toolkit' ) . '</small>';
				}
				echo '</td><td>';

				if ( $locked ) {
					echo '<span class="layodt-badge">' . esc_html__( 'Never replaced', 'layo-dashboard-toolkit' ) . '</span><br><small>' . esc_html__( 'Administrators always have every capability. Capabilities the imported roles have are added to it.', 'layo-dashboard-toolkit' ) . '</small>';
				} elseif ( $new ) {
					echo '<span class="layodt-badge layodt-badge--new">' . esc_html__( 'New here', 'layo-dashboard-toolkit' ) . '</span>';
				} elseif ( ! $row['added'] && ! $row['removed'] ) {
					echo esc_html__( 'Exists, same capabilities', 'layo-dashboard-toolkit' );
				} else {
					echo esc_html__( 'Exists, different capabilities', 'layo-dashboard-toolkit' );
					echo '<details class="layodt-diff"><summary>' . esc_html(
						sprintf(
							/* translators: 1: capabilities gained, 2: capabilities lost. */
							__( '%1$d gained, %2$d removed if imported', 'layo-dashboard-toolkit' ),
							count( $row['added'] ),
							count( $row['removed'] )
						)
					) . '</summary>';
					if ( $row['added'] ) {
						echo '<p><strong>' . esc_html__( 'Gained:', 'layo-dashboard-toolkit' ) . '</strong> ';
						self::cap_list( $row['added'] );
						echo '</p>';
					}
					if ( $row['removed'] ) {
						echo '<p><strong>' . esc_html__( 'Removed:', 'layo-dashboard-toolkit' ) . '</strong> ';
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
		submit_button( __( 'Import', 'layo-dashboard-toolkit' ), 'primary', 'submit', false );
		echo '</p></form>';

		echo '<form method="post" action="' . esc_url( $post ) . '" class="layodt-inline-form"><input type="hidden" name="action" value="layodt_import_cancel">';
		wp_nonce_field( 'layodt_import_cancel' );
		submit_button( __( 'Cancel and discard the file', 'layo-dashboard-toolkit' ), 'secondary', 'cancel', false );
		echo '</form>';
	}

	/**
	 * Social sign-in settings: one row per provider, with its keys and the
	 * address to register with it. Saved secrets are never printed.
	 *
	 * @return void
	 */
	private static function social_form() {
		$settings  = Social::settings();
		$providers = Social::providers();

		self::section(
			__( 'Social sign-in', 'layo-dashboard-toolkit' ),
			__( 'Let people who already have an account sign in with Google, Microsoft, GitHub and others. Nobody can create an account this way. Each provider needs an app you create in its developer console; paste its Client ID and Client Secret here and give it the return address shown. Sign-ins go through the same checks as a password login, including two-factor.', 'layo-dashboard-toolkit' )
		);

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="layodt_social_save">';
		wp_nonce_field( 'layodt_social_save' );
		echo '<table class="form-table" role="presentation"><tbody>';
		echo '<tr><th scope="row">' . esc_html__( 'Social sign-in', 'layo-dashboard-toolkit' ) . '</th><td><label><input type="checkbox" name="layodt_social[on]" value="1"' . checked( $settings['on'], true, false ) . '> ' . esc_html__( 'Show the sign-in buttons on the login screen', 'layo-dashboard-toolkit' ) . '</label></td></tr>';
		echo '<tr><th scope="row"><label for="layodt-social-place">' . esc_html__( 'Buttons', 'layo-dashboard-toolkit' ) . '</label></th><td><select id="layodt-social-place" name="layodt_social[place]"><option value="below"' . selected( $settings['place'], 'below', false ) . '>' . esc_html__( 'Below the login form', 'layo-dashboard-toolkit' ) . '</option><option value="above"' . selected( $settings['place'], 'above', false ) . '>' . esc_html__( 'Above the login form', 'layo-dashboard-toolkit' ) . '</option></select></td></tr>';
		echo '</tbody></table>';

		echo '<div class="layodt-table-scroll"><table class="widefat layodt-social-providers"><thead><tr><th scope="col">' . esc_html__( 'Provider', 'layo-dashboard-toolkit' ) . '</th><th scope="col">' . esc_html__( 'Keys', 'layo-dashboard-toolkit' ) . '</th><th scope="col">' . esc_html__( 'Return address to give the provider', 'layo-dashboard-toolkit' ) . '</th></tr></thead><tbody>';

		foreach ( $providers as $id => $provider ) {
			$one = $settings['providers'][ $id ];
			$pre = 'layodt-social-' . $id;

			echo '<tr><th scope="row"><label><input type="checkbox" name="layodt_social[providers][' . esc_attr( $id ) . '][on]" value="1"' . checked( $one['on'], true, false ) . '> ' . wp_kses( Social::icon( $id ), Social::icon_tags() ) . ' ' . esc_html( $provider['name'] ) . '</label>';
			echo '<br><span class="description">' . ( $provider['email'] ? esc_html__( 'Finds people by their verified email address, or a connected account.', 'layo-dashboard-toolkit' ) : esc_html__( 'People connect it on their profile first, because this provider does not confirm email addresses.', 'layo-dashboard-toolkit' ) ) . '</span></th>';
			echo '<td><label for="' . esc_attr( $pre ) . '-id">' . esc_html__( 'Client ID', 'layo-dashboard-toolkit' ) . '</label><br><input type="text" class="regular-text" id="' . esc_attr( $pre ) . '-id" name="layodt_social[providers][' . esc_attr( $id ) . '][client_id]" value="' . esc_attr( $one['client_id'] ) . '" autocomplete="off">';
			echo '<br><label for="' . esc_attr( $pre ) . '-secret">' . esc_html__( 'Client Secret', 'layo-dashboard-toolkit' ) . '</label><br><input type="password" class="regular-text" id="' . esc_attr( $pre ) . '-secret" name="layodt_social[providers][' . esc_attr( $id ) . '][secret]" value="" autocomplete="new-password" placeholder="' . esc_attr( '' !== $one['secret'] ? __( 'Saved. Leave empty to keep it.', 'layo-dashboard-toolkit' ) : '' ) . '"></td>';
			echo '<td><code class="layodt-copy">' . esc_html( Social::callback_url( $id ) ) . '</code><br><a href="' . esc_url( $provider['console'] ) . '" target="_blank" rel="noopener">' . esc_html__( 'Open the developer console', 'layo-dashboard-toolkit' ) . '</a></td></tr>';
		}

		echo '</tbody></table></div>';
		submit_button( __( 'Save social sign-in', 'layo-dashboard-toolkit' ) );
		echo '</form>';
	}

	/**
	 * Emails the theme or other plugins customise, and the choice of design
	 * when another plugin wraps every email.
	 *
	 * @return void
	 */
	private static function email_sources() {
		$post      = admin_url( 'admin-post.php' );
		$templates = Templates::all();
		$found     = Email_Sources::customised();
		$wrapped   = get_option( Emails::WRAPPED );

		if ( $found ) {
			echo '<div class="layodt-callout"><p><strong>' . esc_html__( 'Emails customised elsewhere', 'layo-dashboard-toolkit' ) . '</strong></p>';
			echo '<p>' . esc_html__( 'Your theme or another plugin already changes these emails. While a template here is on, it replaces their version. Preview what they send, and copy it into your template to keep their wording in your design.', 'layo-dashboard-toolkit' ) . '</p>';
			echo '<table class="widefat striped layodt-email-sources"><thead><tr><th scope="col">' . esc_html__( 'Email', 'layo-dashboard-toolkit' ) . '</th><th scope="col">' . esc_html__( 'Changed by', 'layo-dashboard-toolkit' ) . '</th><th scope="col">' . esc_html__( 'What it sends', 'layo-dashboard-toolkit' ) . '</th></tr></thead><tbody>';

			foreach ( $found as $key => $info ) {
				$sample = Email_Sources::sample( $key );
				echo '<tr><td>' . esc_html( isset( $templates[ $key ] ) ? $templates[ $key ]['label'] : $key ) . '</td><td>' . esc_html( implode( ', ', $info['sources'] ) ) . '</td><td>';

				if ( ! $sample ) {
					echo esc_html__( 'This email is built from real comments or requests, so it cannot be previewed safely. Open its template here and compare it with the other code.', 'layo-dashboard-toolkit' );
				} else {
					$html = wp_strip_all_tags( $sample['message'] ) === $sample['message'] ? '<pre style="white-space:pre-wrap;font:14px/1.5 sans-serif">' . esc_html( $sample['message'] ) . '</pre>' : $sample['message'];
					echo '<details class="layodt-diff"><summary>' . esc_html( $sample['subject'] ) . '</summary>';
					echo '<iframe class="layodt-email-frame" sandbox="" title="' . esc_attr__( 'Email preview', 'layo-dashboard-toolkit' ) . '" srcdoc="' . esc_attr( $html ) . '"></iframe></details>';

					if ( Email_Sources::is_copied( $key, $sample ) ) {
						echo '<span class="layodt-badge">' . esc_html__( 'Copied into your template', 'layo-dashboard-toolkit' ) . '</span>';
					} else {
						echo '<form method="post" action="' . esc_url( $post ) . '" data-layodt-confirm><input type="hidden" name="action" value="layodt_email_copy"><input type="hidden" name="template" value="' . esc_attr( $key ) . '">';
						wp_nonce_field( 'layodt_email_copy' );
						submit_button( __( 'Copy into my template', 'layo-dashboard-toolkit' ), 'small', 'submit', false );
						echo '</form>';
					}
				}

				echo '</td></tr>';
			}

			echo '</tbody></table>';
			echo '<p class="description">' . esc_html__( 'Copying replaces this template\'s subject and text with theirs, with names, addresses and links turned into tags so it works for everyone. Your colours, logo and layout stay.', 'layo-dashboard-toolkit' ) . '</p></div>';
		}

		if ( is_array( $wrapped ) && ! empty( $wrapped['html'] ) ) {
			$choice = Email_Sources::design_choice();
			$names  = ! empty( $wrapped['sources'] ) ? implode( ', ', $wrapped['sources'] ) : __( 'Another plugin', 'layo-dashboard-toolkit' );

			echo '<div class="layodt-callout"><p><strong>' . esc_html__( 'Another email design was found', 'layo-dashboard-toolkit' ) . '</strong></p>';
			/* translators: %s: plugin or theme names. */
			echo '<p>' . esc_html( sprintf( __( '%s also wraps emails in its own design, so an email could arrive wrapped twice. Choose which design WordPress emails use. The other design keeps working for that plugin\'s own emails.', 'layo-dashboard-toolkit' ), $names ) ) . '</p>';
			echo '<div class="layodt-design-choice"><figure><figcaption>' . esc_html__( 'Your design', 'layo-dashboard-toolkit' ) . '</figcaption><iframe class="layodt-email-frame" sandbox="" title="' . esc_attr__( 'Your design', 'layo-dashboard-toolkit' ) . '" srcdoc="' . esc_attr( Renderer::preview( 'welcome', true ) ) . '"></iframe></figure>';
			/* translators: %s: plugin or theme names. */
			echo '<figure><figcaption>' . esc_html( sprintf( __( 'As sent with %s', 'layo-dashboard-toolkit' ), $names ) ) . '</figcaption><iframe class="layodt-email-frame" sandbox="" title="' . esc_attr__( 'Their design', 'layo-dashboard-toolkit' ) . '" srcdoc="' . esc_attr( (string) $wrapped['html'] ) . '"></iframe></figure></div>';
			echo '<form method="post" action="' . esc_url( $post ) . '"><input type="hidden" name="action" value="layodt_email_design">';
			wp_nonce_field( 'layodt_email_design' );
			echo '<fieldset><legend class="screen-reader-text">' . esc_html__( 'Email design', 'layo-dashboard-toolkit' ) . '</legend>';
			echo '<label><input type="radio" name="layodt_design" value="mine"' . checked( $choice, 'mine', false ) . '> ' . esc_html__( 'Keep my design and remove theirs from WordPress emails', 'layo-dashboard-toolkit' ) . '</label><br>';
			/* translators: %s: plugin or theme names. */
			echo '<label><input type="radio" name="layodt_design" value="theirs"' . checked( $choice, 'theirs', false ) . '> ' . esc_html( sprintf( __( 'Keep the %s design; my templates only change the wording', 'layo-dashboard-toolkit' ), $names ) ) . '</label></fieldset>';
			submit_button( __( 'Save choice', 'layo-dashboard-toolkit' ), 'secondary', 'submit', false );
			echo '</form></div>';
		}
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

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Navigation only.
		$source = isset( $_GET['source'] ) ? sanitize_key( wp_unslash( $_GET['source'] ) ) : '';

		if ( in_array( $step, array( 'other', 'other_done' ), true ) && array_key_exists( $source, Sources::all() ) ) {
			if ( 'other' === $step ) {
				Importers::review( $source );
			} else {
				Importers::result( $source );
			}
			return;
		}

		echo '<div class="layodt-cards">';

		echo '<section class="layodt-card"><h2>' . esc_html__( 'Export', 'layo-dashboard-toolkit' ) . '</h2><p>' . esc_html__( 'Download Layò Dashboard Toolkit\'s settings as a JSON file to copy them to another site.', 'layo-dashboard-toolkit' ) . '</p>';
		echo '<form method="post" action="' . esc_url( $post ) . '"><input type="hidden" name="action" value="layodt_export">';
		wp_nonce_field( 'layodt_export' );
		self::transfer_parts( Transfer::parts(), array_keys( Transfer::parts() ) );
		submit_button( __( 'Download settings', 'layo-dashboard-toolkit' ), 'secondary', 'submit', false );
		echo '</form></section>';

		echo '<section class="layodt-card"><h2>' . esc_html__( 'Import', 'layo-dashboard-toolkit' ) . '</h2><p>' . esc_html__( 'Upload an exported file. The next screen shows what it contains, and you choose the parts and the roles to bring across before anything changes.', 'layo-dashboard-toolkit' ) . '</p>';
		if ( $pending ) {
			echo '<p><a href="' . esc_url( Admin::url( 'tools', array( 'step' => 'review' ) ) ) . '">' . esc_html__( 'Continue the import you started', 'layo-dashboard-toolkit' ) . '</a></p>';
		}
		echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( $post ) . '"><input type="hidden" name="action" value="layodt_import">';
		wp_nonce_field( 'layodt_import' );
		echo '<label for="layodt-file" class="screen-reader-text">' . esc_html__( 'Settings file', 'layo-dashboard-toolkit' ) . '</label><input type="file" id="layodt-file" name="layodt_file" accept=".json,application/json" required>';
		echo '<p>';
		submit_button( __( 'Upload and review', 'layo-dashboard-toolkit' ), 'secondary', 'submit', false );
		echo '</p></form></section>';

		Importers::card();

		echo '<section class="layodt-card"><h2>' . esc_html__( 'Reset', 'layo-dashboard-toolkit' ) . '</h2><p>' . esc_html__( 'Put one section, or everything, back to its defaults.', 'layo-dashboard-toolkit' ) . '</p>';
		echo '<form method="post" action="' . esc_url( $post ) . '" data-layodt-confirm><input type="hidden" name="action" value="layodt_reset">';
		wp_nonce_field( 'layodt_reset' );
		$sections = array(
			'brand'          => __( 'Brand kit', 'layo-dashboard-toolkit' ),
			'login'          => __( 'Login page', 'layo-dashboard-toolkit' ),
			'emails'         => __( 'Emails', 'layo-dashboard-toolkit' ),
			'error_pages'    => __( 'Error and system pages', 'layo-dashboard-toolkit' ),
			'tab_meta'       => __( 'Tab and page details', 'layo-dashboard-toolkit' ),
			'admin_branding' => __( 'Admin branding', 'layo-dashboard-toolkit' ),
			'security'       => __( 'Login protection', 'layo-dashboard-toolkit' ),
			'maintenance'    => __( 'Coming soon', 'layo-dashboard-toolkit' ),
			'all'            => __( 'Everything', 'layo-dashboard-toolkit' ),
		);
		echo '<p><label for="layodt-section">' . esc_html__( 'Section', 'layo-dashboard-toolkit' ) . '</label> <select id="layodt-section" name="layodt_section">';
		foreach ( $sections as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '">' . esc_html( $label ) . '</option>';
		}
		echo '</select></p>';
		echo '<p><label><input type="checkbox" name="layodt_confirm" value="1" required> ' . esc_html__( 'I understand this restores the defaults for the chosen section', 'layo-dashboard-toolkit' ) . '</label></p>';
		submit_button( __( 'Reset', 'layo-dashboard-toolkit' ), 'delete', 'submit', false );
		echo '</form></section>';

		echo '</div>';

		self::section( __( 'Status', 'layo-dashboard-toolkit' ) );
		echo '<table class="widefat striped layodt-status"><tbody>';

		$labels = Admin::dropin_labels();
		$names  = array(
			'php-error.php'   => __( 'Critical error page', 'layo-dashboard-toolkit' ),
			'db-error.php'    => __( 'Database error page', 'layo-dashboard-toolkit' ),
			'maintenance.php' => __( 'Update maintenance page', 'layo-dashboard-toolkit' ),
		);

		foreach ( Dropins::status() as $file => $state ) {
			printf(
				'<tr><th scope="row">%s <code>wp-content/%s</code></th><td><span class="layodt-state layodt-state--%s">%s</span></td></tr>',
				esc_html( $names[ $file ] ),
				esc_html( $file ),
				esc_attr( $state ),
				esc_html( $labels[ $state ] )
			);
		}

		printf(
			'<tr><th scope="row">%s</th><td>%s</td></tr>',
			esc_html__( 'wp-content writable', 'layo-dashboard-toolkit' ),
			Files::writable( WP_CONTENT_DIR ) ? esc_html__( 'Yes', 'layo-dashboard-toolkit' ) : esc_html__( 'No', 'layo-dashboard-toolkit' )
		);

		$detected = Brand_Kit::detected();
		echo '<tr><th scope="row">' . esc_html__( 'Colours detected from the theme', 'layo-dashboard-toolkit' ) . '</th><td><div class="layodt-swatches">';
		$found = false;
		foreach ( Brand_Kit::COLORS as $color ) {
			if ( ! empty( $detected[ $color ] ) ) {
				$found = true;
				printf(
					'<span class="layodt-swatch"><span class="layodt-swatch__chip" data-color="%1$s"></span>%2$s <code>%1$s</code></span>',
					esc_attr( $detected[ $color ] ),
					esc_html( $color )
				);
			}
		}
		if ( ! $found ) {
			esc_html_e( 'None. The built-in palette is used until you pick colours in the brand kit.', 'layo-dashboard-toolkit' );
		}
		echo '</div></td></tr>';

		$conflicts = Admin::conflicts();
		echo '<tr><th scope="row">' . esc_html__( 'Overlapping plugins', 'layo-dashboard-toolkit' ) . '</th><td>';
		if ( $conflicts ) {
			echo '<ul class="layodt-conflicts">';
			foreach ( $conflicts as $name => $module ) {
				/* translators: 1: plugin name, 2: Layò Dashboard Toolkit feature name. */
				echo '<li>' . esc_html( sprintf( __( '%1$s does similar things to the %2$s feature. Use one or the other to avoid surprises.', 'layo-dashboard-toolkit' ), $name, $sections[ $module ] ) ) . '</li>';
			}
			echo '</ul>';
		} else {
			esc_html_e( 'None found.', 'layo-dashboard-toolkit' );
		}
		echo '</td></tr>';

		echo '</tbody></table>';
	}
}

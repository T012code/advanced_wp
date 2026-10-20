<?php
/**
 * Customizer panel, sections and controls.
 *
 * @package AdvancedWP
 */

namespace AdvancedWP\Customizer;

use AdvancedWP\Brand_Kit;
use AdvancedWP\Fonts;
use AdvancedWP\Preview;
use AdvancedWP\Settings;
use AdvancedWP\Modules\Emails\Templates;

defined( 'ABSPATH' ) || exit;

/**
 * Registers everything under Appearance → Customize → Advanced WP.
 */
class Customizer {

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'customize_register', array( __CLASS__, 'register' ) );
		add_action( 'customize_controls_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
	}

	/**
	 * Section id => preview URL loaded when the section opens.
	 *
	 * @return array
	 */
	private static function previews() {
		return array(
			'advwp_brand'              => Preview::url( 'login' ),
			'advwp_login_layout'       => Preview::url( 'login' ),
			'advwp_login_logo'         => Preview::url( 'login' ),
			'advwp_login_form'         => Preview::url( 'login' ),
			'advwp_login_text'         => Preview::url( 'login' ),
			'advwp_login_css'          => Preview::url( 'login' ),
			'advwp_emails'             => Preview::url( 'email', array( 'advwp_template' => 'welcome' ) ),
			'advwp_errors_design'      => Preview::url( 'page', array( 'advwp_page' => 'critical' ) ),
			'advwp_errors_critical'    => Preview::url( 'page', array( 'advwp_page' => 'critical' ) ),
			'advwp_errors_db'          => Preview::url( 'page', array( 'advwp_page' => 'db' ) ),
			'advwp_errors_maintenance' => Preview::url( 'page', array( 'advwp_page' => 'maintenance' ) ),
			'advwp_errors_wp_die'      => Preview::url( 'page', array( 'advwp_page' => 'wp_die' ) ),
			'advwp_errors_notfound'    => Preview::url( 'page', array( 'advwp_page' => 'notfound' ) ),
			'advwp_maintenance'        => Preview::url( 'maintenance' ),
		);
	}

	/**
	 * Registers the panel, sections, settings and controls.
	 *
	 * @param \WP_Customize_Manager $wp Customizer manager.
	 * @return void
	 */
	public static function register( $wp ) {
		$wp->add_panel(
			'advwp',
			array(
				'title'       => __( 'Advanced WP', 'advanced-wp' ),
				'description' => sprintf(
					/* translators: %s: settings page URL. */
					__( 'Design your login page, emails and system pages. Email wording, security and admin branding live on the <a href="%s">settings page</a>.', 'advanced-wp' ),
					esc_url( admin_url( 'options-general.php?page=advanced-wp' ) )
				),
				'priority'    => 160,
			)
		);

		foreach ( self::sections() as $id => $section ) {
			$wp->add_section(
				$id,
				array(
					'title'       => $section['title'],
					'description' => isset( $section['description'] ) ? $section['description'] : '',
					'panel'       => 'advwp',
				)
			);

			foreach ( $section['controls'] as $key => $control ) {
				if ( 0 === strpos( $key, 'picker:' ) ) {
					$wp->add_control(
						new Preview_Picker_Control(
							$wp,
							'advwp_' . str_replace( ':', '_', $key ),
							array(
								'section'  => $id,
								'label'    => $control['label'],
								'choices'  => $control['choices'],
								'settings' => array(),
							)
						)
					);
					continue;
				}

				self::add( $wp, $key, $id, $control );
			}
		}
	}

	/**
	 * Adds one setting and its control.
	 *
	 * @param \WP_Customize_Manager $wp      Customizer manager.
	 * @param string                $key     Dot path.
	 * @param string                $section Section id.
	 * @param array                 $args    Control arguments plus `control` type.
	 * @return void
	 */
	private static function add( $wp, $key, $section, array $args ) {
		$field = Settings::field( $key );

		if ( ! $field ) {
			return;
		}

		$id = Settings::customizer_id( $key );

		$wp->add_setting(
			$id,
			array(
				'type'              => 'option',
				'capability'        => 'manage_options',
				'default'           => Settings::default_for( $key ),
				'transport'         => 'refresh',
				'sanitize_callback' => static function ( $value ) use ( $field ) {
					return Settings::sanitize( $field, $value );
				},
			)
		);

		$type = isset( $args['control'] ) ? $args['control'] : self::control_for( $field );
		unset( $args['control'] );

		$args = array_merge(
			array(
				'section'  => $section,
				'settings' => $id,
			),
			$args
		);

		switch ( $type ) {
			case 'color':
				$wp->add_control( new \WP_Customize_Color_Control( $wp, $id, $args ) );
				break;

			case 'image':
				$wp->add_control( new \WP_Customize_Image_Control( $wp, $id, $args ) );
				break;

			case 'video':
				$args['mime_type'] = 'video';
				$wp->add_control( new \WP_Customize_Upload_Control( $wp, $id, $args ) );
				break;

			case 'css':
				$args['code_type'] = 'text/css';
				$wp->add_control( new \WP_Customize_Code_Editor_Control( $wp, $id, $args ) );
				break;

			default:
				$args['type'] = $type;
				$wp->add_control( $id, $args );
		}
	}

	/**
	 * Default control type for a field type.
	 *
	 * @param array $field Field definition.
	 * @return string
	 */
	private static function control_for( array $field ) {
		$map = array(
			'bool'         => 'checkbox',
			'color'        => 'color',
			'url'          => 'image',
			'int'          => 'number',
			'int_optional' => 'number',
			'html'         => 'textarea',
			'css'          => 'css',
			'datetime'     => 'datetime-local',
		);

		return isset( $map[ $field['type'] ] ) ? $map[ $field['type'] ] : 'text';
	}

	/**
	 * Shortcut for a select control.
	 *
	 * @param string $label   Label.
	 * @param array  $choices Value => label.
	 * @param string $desc    Description.
	 * @return array
	 */
	private static function select( $label, array $choices, $desc = '' ) {
		return array(
			'label'       => $label,
			'control'     => 'select',
			'choices'     => $choices,
			'description' => $desc,
		);
	}

	/**
	 * Shortcut for a labelled control with an optional description.
	 *
	 * @param string $label   Label.
	 * @param string $desc    Description.
	 * @param array  $extra   Extra control args.
	 * @return array
	 */
	private static function c( $label, $desc = '', array $extra = array() ) {
		return array_merge(
			array(
				'label'       => $label,
				'description' => $desc,
			),
			$extra
		);
	}

	/**
	 * Section and control definitions.
	 *
	 * @return array
	 */
	private static function sections() {
		$inherit  = __( 'Leave empty to use the brand kit.', 'advanced-wp' );
		$detected = Brand_Kit::detected();
		$theme    = static function ( $key ) use ( $detected ) {
			return '' !== $detected[ $key ]
				/* translators: %s: colour value detected from the theme. */
				? sprintf( __( 'Following theme: %s. Pick a colour to override, or clear it to follow the theme again.', 'advanced-wp' ), $detected[ $key ] )
				: __( 'Not set by the theme. Using the built-in default until you pick one.', 'advanced-wp' );
		};
		$px       = array(
			'input_attrs' => array(
				'min'         => 0,
				'max'         => 2000,
				'placeholder' => __( 'Brand default', 'advanced-wp' ),
			),
		);
		$range    = array(
			'control'     => 'range',
			'input_attrs' => array(
				'min'  => 0,
				'max'  => 100,
				'step' => 5,
			),
		);

		$email_choices = array();
		foreach ( Templates::all() as $key => $template ) {
			$email_choices[ Preview::url( 'email', array( 'advwp_template' => $key ) ) ] = $template['label'];
		}

		$page_choices = array(
			Preview::url( 'page', array( 'advwp_page' => 'critical' ) )    => __( 'Critical error', 'advanced-wp' ),
			Preview::url( 'page', array( 'advwp_page' => 'db' ) )          => __( 'Database connection error', 'advanced-wp' ),
			Preview::url( 'page', array( 'advwp_page' => 'maintenance' ) ) => __( 'Update maintenance', 'advanced-wp' ),
			Preview::url( 'page', array( 'advwp_page' => 'wp_die' ) )      => __( 'Permission and notice screens', 'advanced-wp' ),
			Preview::url( 'page', array( 'advwp_page' => 'notfound' ) )    => __( 'Page not found (404)', 'advanced-wp' ),
		);

		return array(
			'advwp_brand'              => array(
				'title'       => __( 'Brand kit', 'advanced-wp' ),
				'description' => __( 'Set these once. The login page, emails and system pages all use them unless you override a value in that area.', 'advanced-wp' ),
				'controls'    => array(
					'brand.logo'         => self::c( __( 'Logo', 'advanced-wp' ), __( 'Defaults to your theme logo, then your site icon.', 'advanced-wp' ) ),
					'brand.logo_width'   => self::c( __( 'Logo width (px)', 'advanced-wp' ) ),
					'brand.primary'      => self::c( __( 'Primary colour', 'advanced-wp' ), $theme( 'primary' ) ),
					'brand.secondary'    => self::c( __( 'Secondary colour', 'advanced-wp' ), $theme( 'secondary' ) ),
					'brand.text'         => self::c( __( 'Text colour', 'advanced-wp' ), $theme( 'text' ) ),
					'brand.background'   => self::c( __( 'Background colour', 'advanced-wp' ), $theme( 'background' ) ),
					'brand.surface'      => self::c( __( 'Surface colour', 'advanced-wp' ), __( 'Cards, forms and email bodies.', 'advanced-wp' ) ),
					'brand.border'       => self::c( __( 'Border colour', 'advanced-wp' ) ),
					'brand.success'      => self::c( __( 'Success colour', 'advanced-wp' ) ),
					'brand.error'        => self::c( __( 'Error colour', 'advanced-wp' ) ),
					'brand.font_heading' => self::select( __( 'Heading font', 'advanced-wp' ), Fonts::choices(), __( 'Google fonts are downloaded to your site when you publish, so visitors never load them from Google.', 'advanced-wp' ) ),
					'brand.font_body'    => self::select( __( 'Body font', 'advanced-wp' ), Fonts::choices() ),
					'brand.radius'       => self::c(
						__( 'Corner radius (px)', 'advanced-wp' ),
						'',
						array(
							'input_attrs' => array(
								'min' => 0,
								'max' => 40,
							),
						)
					),
					'brand.button_style' => self::select(
						__( 'Button style', 'advanced-wp' ),
						array(
							'filled'  => __( 'Filled', 'advanced-wp' ),
							'outline' => __( 'Outline', 'advanced-wp' ),
							'pill'    => __( 'Pill', 'advanced-wp' ),
						)
					),
				),
			),
			'advwp_login_layout'       => array(
				'title'    => __( 'Login: layout and background', 'advanced-wp' ),
				'controls' => array(
					'login.layout'            => self::select(
						__( 'Layout', 'advanced-wp' ),
						array(
							'centered' => __( 'Centered card', 'advanced-wp' ),
							'split'    => __( 'Split screen', 'advanced-wp' ),
							'fullbg'   => __( 'Full background', 'advanced-wp' ),
							'minimal'  => __( 'Minimal (WordPress default, restyled)', 'advanced-wp' ),
						)
					),
					'login.split_side'        => self::select(
						__( 'Split screen: image side', 'advanced-wp' ),
						array(
							'left'  => __( 'Left', 'advanced-wp' ),
							'right' => __( 'Right', 'advanced-wp' ),
						)
					),
					'login.side_image'        => self::c( __( 'Split screen: image', 'advanced-wp' ) ),
					'login.bg_type'           => self::select(
						__( 'Background type', 'advanced-wp' ),
						array(
							'color'    => __( 'Colour', 'advanced-wp' ),
							'gradient' => __( 'Gradient', 'advanced-wp' ),
							'image'    => __( 'Image', 'advanced-wp' ),
							'video'    => __( 'Video', 'advanced-wp' ),
						)
					),
					'login.bg_color'          => self::c( __( 'Background colour', 'advanced-wp' ), $inherit ),
					'login.bg_gradient_from'  => self::c( __( 'Gradient start', 'advanced-wp' ) ),
					'login.bg_gradient_to'    => self::c( __( 'Gradient end', 'advanced-wp' ) ),
					'login.bg_gradient_angle' => self::c(
						__( 'Gradient angle (degrees)', 'advanced-wp' ),
						'',
						array(
							'input_attrs' => array(
								'min' => 0,
								'max' => 360,
							),
						)
					),
					'login.bg_image'          => self::c( __( 'Background image', 'advanced-wp' ) ),
					'login.bg_video'          => self::c( __( 'Background video (MP4)', 'advanced-wp' ), __( 'Muted and looped. The background colour shows while it loads.', 'advanced-wp' ), array( 'control' => 'video' ) ),
					'login.overlay_color'     => self::c( __( 'Overlay colour', 'advanced-wp' ) ),
					'login.overlay_opacity'   => array_merge( self::c( __( 'Overlay opacity', 'advanced-wp' ) ), $range ),
				),
			),
			'advwp_login_logo'         => array(
				'title'    => __( 'Login: logo', 'advanced-wp' ),
				'controls' => array(
					'login.hide_logo'  => self::c( __( 'Hide the logo', 'advanced-wp' ) ),
					'login.logo'       => self::c( __( 'Logo', 'advanced-wp' ), $inherit ),
					'login.logo_width' => self::c( __( 'Logo width (px)', 'advanced-wp' ), '', $px ),
					'login.logo_url'   => self::c( __( 'Logo link', 'advanced-wp' ), __( 'Defaults to your home page.', 'advanced-wp' ), array( 'control' => 'url' ) ),
					'login.logo_title' => self::c( __( 'Logo text for screen readers', 'advanced-wp' ), __( 'Defaults to your site title.', 'advanced-wp' ) ),
				),
			),
			'advwp_login_form'         => array(
				'title'    => __( 'Login: form and button', 'advanced-wp' ),
				'controls' => array(
					'login.form_width'    => self::c(
						__( 'Form width (px)', 'advanced-wp' ),
						'',
						array(
							'input_attrs' => array(
								'min' => 280,
								'max' => 640,
							),
						)
					),
					'login.form_padding'  => self::c(
						__( 'Form padding (px)', 'advanced-wp' ),
						'',
						array(
							'input_attrs' => array(
								'min' => 0,
								'max' => 80,
							),
						)
					),
					'login.form_radius'   => self::c( __( 'Form corner radius (px)', 'advanced-wp' ), '', $px ),
					'login.form_bg'       => self::c( __( 'Form background', 'advanced-wp' ), $inherit ),
					'login.form_shadow'   => self::c( __( 'Show a shadow under the form', 'advanced-wp' ) ),
					'login.field_style'   => self::select(
						__( 'Field style', 'advanced-wp' ),
						array(
							'boxed'     => __( 'Boxed', 'advanced-wp' ),
							'underline' => __( 'Underline', 'advanced-wp' ),
							'filled'    => __( 'Filled', 'advanced-wp' ),
						)
					),
					'login.hide_labels'   => self::c( __( 'Hide field labels and use placeholders', 'advanced-wp' ) ),
					'login.text_color'    => self::c( __( 'Text colour', 'advanced-wp' ), $inherit ),
					'login.link_color'    => self::c( __( 'Link colour', 'advanced-wp' ), $inherit ),
					'login.button_bg'     => self::c( __( 'Button colour', 'advanced-wp' ), $inherit ),
					'login.button_text'   => self::c( __( 'Button text colour', 'advanced-wp' ), __( 'Leave empty to pick black or white automatically.', 'advanced-wp' ) ),
					'login.button_radius' => self::c( __( 'Button corner radius (px)', 'advanced-wp' ), '', $px ),
				),
			),
			'advwp_login_text'         => array(
				'title'    => __( 'Login: text and links', 'advanced-wp' ),
				'controls' => array(
					'login.message'                => self::c( __( 'Message above the form', 'advanced-wp' ), __( 'Basic HTML is allowed.', 'advanced-wp' ) ),
					'login.footer_text'            => self::c( __( 'Footer text', 'advanced-wp' ) ),
					'login.hide_lost_password'     => self::c( __( 'Hide "Lost your password?"', 'advanced-wp' ) ),
					'login.hide_back_to_site'      => self::c( __( 'Hide "Go to site"', 'advanced-wp' ) ),
					'login.hide_language_switcher' => self::c( __( 'Hide the language switcher', 'advanced-wp' ) ),
					'login.hide_privacy_link'      => self::c( __( 'Hide the privacy policy link', 'advanced-wp' ) ),
				),
			),
			'advwp_login_css'          => array(
				'title'    => __( 'Login: custom CSS', 'advanced-wp' ),
				'controls' => array(
					'login.custom_css' => self::c( __( 'Custom CSS', 'advanced-wp' ), __( 'Applied to every login, registration and password screen.', 'advanced-wp' ) ),
				),
			),
			'advwp_emails'             => array(
				'title'       => __( 'Emails: design', 'advanced-wp' ),
				'description' => sprintf(
					/* translators: %s: settings page URL. */
					__( 'Edit the wording of each email on the <a href="%s">settings page</a>.', 'advanced-wp' ),
					esc_url( admin_url( 'options-general.php?page=advanced-wp&tab=emails' ) )
				),
				'controls'    => array(
					'picker:email'       => array(
						'label'   => __( 'Preview email', 'advanced-wp' ),
						'choices' => $email_choices,
					),
					'emails.show_logo'   => self::c( __( 'Show the logo in the header', 'advanced-wp' ) ),
					'emails.logo'        => self::c( __( 'Logo', 'advanced-wp' ), $inherit ),
					'emails.logo_width'  => self::c( __( 'Logo width (px)', 'advanced-wp' ), '', $px ),
					'emails.header_bg'   => self::c( __( 'Header background', 'advanced-wp' ), __( 'Leave empty to use the content background.', 'advanced-wp' ) ),
					'emails.header_text' => self::c( __( 'Header text colour', 'advanced-wp' ) ),
					'emails.body_bg'     => self::c( __( 'Outer background', 'advanced-wp' ), $inherit ),
					'emails.content_bg'  => self::c( __( 'Content background', 'advanced-wp' ), $inherit ),
					'emails.text_color'  => self::c( __( 'Text colour', 'advanced-wp' ), $inherit ),
					'emails.button_bg'   => self::c( __( 'Button colour', 'advanced-wp' ), $inherit ),
					'emails.button_text' => self::c( __( 'Button text colour', 'advanced-wp' ) ),
					'emails.font'        => self::select(
						__( 'Font', 'advanced-wp' ),
						array(
							'brand'     => __( 'Brand body font (falls back to a safe font in most mail apps)', 'advanced-wp' ),
							'system'    => __( 'System font', 'advanced-wp' ),
							'arial'     => 'Arial',
							'helvetica' => 'Helvetica',
							'georgia'   => 'Georgia',
							'verdana'   => 'Verdana',
							'trebuchet' => 'Trebuchet MS',
						)
					),
					'emails.width'       => self::c(
						__( 'Content width (px)', 'advanced-wp' ),
						'',
						array(
							'input_attrs' => array(
								'min' => 480,
								'max' => 800,
							),
						)
					),
					'emails.radius'      => self::c( __( 'Corner radius (px)', 'advanced-wp' ), '', $px ),
					'emails.footer_text' => self::c( __( 'Footer text', 'advanced-wp' ), __( 'Tags: {site_name}, {site_url}, {year}.', 'advanced-wp' ) ),
				),
			),
			'advwp_errors_design'      => array(
				'title'       => __( 'System pages: design', 'advanced-wp' ),
				'description' => __( 'Shared by the critical error, database error, update maintenance, permission and 404 pages.', 'advanced-wp' ),
				'controls'    => array(
					'picker:page'            => array(
						'label'   => __( 'Preview page', 'advanced-wp' ),
						'choices' => $page_choices,
					),
					'error_pages.bg_color'   => self::c( __( 'Background colour', 'advanced-wp' ), $inherit ),
					'error_pages.surface'    => self::c( __( 'Card colour', 'advanced-wp' ), $inherit ),
					'error_pages.text_color' => self::c( __( 'Text colour', 'advanced-wp' ), $inherit ),
					'error_pages.primary'    => self::c( __( 'Button colour', 'advanced-wp' ), $inherit ),
					'error_pages.show_logo'  => self::c( __( 'Show the logo', 'advanced-wp' ) ),
					'error_pages.image'      => self::c( __( 'Illustration', 'advanced-wp' ), __( 'Optional image shown above the title.', 'advanced-wp' ) ),
				),
			),
			'advwp_errors_critical'    => array(
				'title'    => __( 'System pages: critical error', 'advanced-wp' ),
				'controls' => array(
					'error_pages.critical'            => self::c( __( 'Replace the critical error page', 'advanced-wp' ), __( 'Shown when a plugin or theme causes a fatal PHP error.', 'advanced-wp' ) ),
					'error_pages.critical_title'      => self::c( __( 'Title', 'advanced-wp' ) ),
					'error_pages.critical_message'    => self::c( __( 'Message', 'advanced-wp' ) ),
					'error_pages.critical_button'     => self::c( __( 'Button text', 'advanced-wp' ), __( 'Leave empty to hide the button.', 'advanced-wp' ) ),
					'error_pages.critical_button_url' => self::c( __( 'Button link', 'advanced-wp' ), __( 'Defaults to your home page.', 'advanced-wp' ), array( 'control' => 'url' ) ),
				),
			),
			'advwp_errors_db'          => array(
				'title'    => __( 'System pages: database error', 'advanced-wp' ),
				'controls' => array(
					'error_pages.db'         => self::c( __( 'Replace the database connection error page', 'advanced-wp' ), __( 'Saved as a static file, because the database is unavailable when this page shows.', 'advanced-wp' ) ),
					'error_pages.db_title'   => self::c( __( 'Title', 'advanced-wp' ) ),
					'error_pages.db_message' => self::c( __( 'Message', 'advanced-wp' ) ),
				),
			),
			'advwp_errors_maintenance' => array(
				'title'    => __( 'System pages: update maintenance', 'advanced-wp' ),
				'controls' => array(
					'error_pages.maintenance'         => self::c( __( 'Replace the "Briefly unavailable" screen', 'advanced-wp' ), __( 'Shown for a few seconds while WordPress installs updates.', 'advanced-wp' ) ),
					'error_pages.maintenance_title'   => self::c( __( 'Title', 'advanced-wp' ) ),
					'error_pages.maintenance_message' => self::c( __( 'Message', 'advanced-wp' ) ),
				),
			),
			'advwp_errors_wp_die'      => array(
				'title'    => __( 'System pages: permission and notice screens', 'advanced-wp' ),
				'controls' => array(
					'error_pages.wp_die'        => self::c( __( 'Restyle WordPress notice screens', 'advanced-wp' ), __( 'For example "Sorry, you are not allowed to access this page." The original message is kept.', 'advanced-wp' ) ),
					'error_pages.wp_die_title'  => self::c( __( 'Fallback title', 'advanced-wp' ), __( 'Used when WordPress does not give the screen a title.', 'advanced-wp' ) ),
					'error_pages.wp_die_button' => self::c( __( 'Back button text', 'advanced-wp' ) ),
				),
			),
			'advwp_errors_notfound'    => array(
				'title'    => __( 'System pages: page not found (404)', 'advanced-wp' ),
				'controls' => array(
					'error_pages.notfound'         => self::c( __( 'Replace the theme 404 page', 'advanced-wp' ), __( 'Off by default, so your theme keeps its own 404 page.', 'advanced-wp' ) ),
					'error_pages.notfound_title'   => self::c( __( 'Title', 'advanced-wp' ) ),
					'error_pages.notfound_message' => self::c( __( 'Message', 'advanced-wp' ) ),
					'error_pages.notfound_button'  => self::c( __( 'Button text', 'advanced-wp' ) ),
					'error_pages.notfound_search'  => self::c( __( 'Show a search box', 'advanced-wp' ) ),
				),
			),
			'advwp_maintenance'        => array(
				'title'       => __( 'Coming soon page', 'advanced-wp' ),
				'description' => sprintf(
					/* translators: %s: settings page URL. */
					__( 'Turn the mode on and choose who can see the site on the <a href="%s">settings page</a>.', 'advanced-wp' ),
					esc_url( admin_url( 'options-general.php?page=advanced-wp&tab=maintenance' ) )
				),
				'controls'    => array(
					'maintenance.headline'        => self::c( __( 'Headline', 'advanced-wp' ) ),
					'maintenance.message'         => self::c( __( 'Message', 'advanced-wp' ) ),
					'maintenance.countdown'       => self::c( __( 'Countdown to', 'advanced-wp' ), __( 'Leave empty to hide the countdown. Uses your site time zone.', 'advanced-wp' ) ),
					'maintenance.show_logo'       => self::c( __( 'Show the logo', 'advanced-wp' ) ),
					'maintenance.bg_color'        => self::c( __( 'Background colour', 'advanced-wp' ), $inherit ),
					'maintenance.bg_image'        => self::c( __( 'Background image', 'advanced-wp' ) ),
					'maintenance.overlay_opacity' => array_merge( self::c( __( 'Image darkening', 'advanced-wp' ) ), $range ),
					'maintenance.text_color'      => self::c( __( 'Text colour', 'advanced-wp' ), __( 'Leave empty to pick a readable colour automatically.', 'advanced-wp' ) ),
				),
			),
		);
	}

	/**
	 * Loads the controls script.
	 *
	 * @return void
	 */
	public static function enqueue() {
		wp_enqueue_script( 'advwp-customizer', ADVWP_URL . 'assets/js/customizer-controls.js', array( 'customize-controls', 'jquery' ), ADVWP_VERSION, true );

		$brand = Brand_Kit::get();

		wp_localize_script(
			'advwp-customizer',
			'advwpCustomizer',
			array(
				'previews' => self::previews(),
				'contrast' => array(
					array(
						'fg'      => Settings::customizer_id( 'login.text_color' ),
						'bg'      => Settings::customizer_id( 'login.form_bg' ),
						'fgFall'  => array( Settings::customizer_id( 'brand.text' ), $brand['text'] ),
						'bgFall'  => array( Settings::customizer_id( 'brand.surface' ), $brand['surface'] ),
						'control' => Settings::customizer_id( 'login.text_color' ),
					),
					array(
						'fg'      => Settings::customizer_id( 'login.button_text' ),
						'bg'      => Settings::customizer_id( 'login.button_bg' ),
						'fgFall'  => array( '', 'auto' ),
						'bgFall'  => array( Settings::customizer_id( 'brand.primary' ), $brand['primary'] ),
						'control' => Settings::customizer_id( 'login.button_bg' ),
					),
					array(
						'fg'      => Settings::customizer_id( 'brand.text' ),
						'bg'      => Settings::customizer_id( 'brand.surface' ),
						'fgFall'  => array( '', $brand['text'] ),
						'bgFall'  => array( '', $brand['surface'] ),
						'control' => Settings::customizer_id( 'brand.text' ),
					),
					array(
						'fg'      => Settings::customizer_id( 'emails.text_color' ),
						'bg'      => Settings::customizer_id( 'emails.content_bg' ),
						'fgFall'  => array( Settings::customizer_id( 'brand.text' ), $brand['text'] ),
						'bgFall'  => array( Settings::customizer_id( 'brand.surface' ), $brand['surface'] ),
						'control' => Settings::customizer_id( 'emails.text_color' ),
					),
				),
				'i18n'     => array(
					/* translators: %s: contrast ratio such as 3.2. */
					'lowContrast' => __( 'Low contrast (%s:1). Text may be hard to read. Aim for at least 4.5:1.', 'advanced-wp' ),
				),
			)
		);
	}
}

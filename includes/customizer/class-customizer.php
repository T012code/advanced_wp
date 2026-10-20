<?php
/**
 * Customizer panel, sections and controls.
 *
 * @package AdminStudio
 */

namespace AdminStudio\Customizer;

use AdminStudio\Brand_Kit;
use AdminStudio\Fonts;
use AdminStudio\Preview;
use AdminStudio\Settings;
use AdminStudio\Modules\Emails\Templates;
use AdminStudio\Modules\Login\Login_Text;

defined( 'ABSPATH' ) || exit;

/**
 * Registers everything under Appearance → Customize → Admin Studio.
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
			'adminstudio_brand'              => Preview::url( 'login' ),
			'adminstudio_login_layout'       => Preview::url( 'login' ),
			'adminstudio_login_logo'         => Preview::url( 'login' ),
			'adminstudio_login_form'         => Preview::url( 'login' ),
			'adminstudio_login_text'         => Preview::url( 'login' ),
			'adminstudio_login_words'        => Preview::url( 'login' ),
			'adminstudio_login_css'          => Preview::url( 'login' ),
			'adminstudio_emails'             => Preview::url( 'email', array( 'adminstudio_template' => 'welcome' ) ),
			'adminstudio_errors_design'      => Preview::url( 'page', array( 'adminstudio_page' => 'critical' ) ),
			'adminstudio_errors_critical'    => Preview::url( 'page', array( 'adminstudio_page' => 'critical' ) ),
			'adminstudio_errors_db'          => Preview::url( 'page', array( 'adminstudio_page' => 'db' ) ),
			'adminstudio_errors_maintenance' => Preview::url( 'page', array( 'adminstudio_page' => 'maintenance' ) ),
			'adminstudio_errors_wp_die'      => Preview::url( 'page', array( 'adminstudio_page' => 'denied' ) ),
			'adminstudio_errors_notfound'    => Preview::url( 'page', array( 'adminstudio_page' => 'notfound' ) ),
			'adminstudio_maintenance'        => Preview::url( 'maintenance' ),
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
			'adminstudio',
			array(
				'title'       => __( 'Admin Studio', 'admin-studio' ),
				'description' => sprintf(
					/* translators: %s: settings page URL. */
					__( 'Design your login page, emails and system pages. Email wording, security and admin branding live on the <a href="%s">settings page</a>.', 'admin-studio' ),
					esc_url( admin_url( 'options-general.php?page=admin-studio' ) )
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
					'panel'       => 'adminstudio',
				)
			);

			foreach ( $section['controls'] as $key => $control ) {
				if ( 0 === strpos( $key, 'picker:' ) ) {
					$wp->add_control(
						new Preview_Picker_Control(
							$wp,
							'adminstudio_' . str_replace( ':', '_', $key ),
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
		$inherit  = __( 'Leave empty to use the brand kit.', 'admin-studio' );
		$detected = Brand_Kit::detected();
		$theme    = static function ( $key ) use ( $detected ) {
			return '' !== $detected[ $key ]
				/* translators: %s: colour value detected from the theme. */
				? sprintf( __( 'Following theme: %s. Pick a colour to override, or clear it to follow the theme again.', 'admin-studio' ), $detected[ $key ] )
				: __( 'Not set by the theme. Using the built-in default until you pick one.', 'admin-studio' );
		};
		$px       = array(
			'input_attrs' => array(
				'min'         => 0,
				'max'         => 2000,
				'placeholder' => __( 'Brand default', 'admin-studio' ),
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
			$email_choices[ Preview::url( 'email', array( 'adminstudio_template' => $key ) ) ] = $template['label'];
		}

		$page_choices = array(
			Preview::url( 'page', array( 'adminstudio_page' => 'critical' ) )    => __( 'Critical error', 'admin-studio' ),
			Preview::url( 'page', array( 'adminstudio_page' => 'db' ) )          => __( 'Database connection error', 'admin-studio' ),
			Preview::url( 'page', array( 'adminstudio_page' => 'maintenance' ) ) => __( 'Update maintenance', 'admin-studio' ),
			Preview::url( 'page', array( 'adminstudio_page' => 'denied' ) )      => __( 'No permission', 'admin-studio' ),
			Preview::url( 'page', array( 'adminstudio_page' => 'expired' ) )     => __( 'Expired link', 'admin-studio' ),
			Preview::url( 'page', array( 'adminstudio_page' => 'wp_die' ) )      => __( 'Other notice screens', 'admin-studio' ),
			Preview::url( 'page', array( 'adminstudio_page' => 'notfound' ) )    => __( 'Page not found (404)', 'admin-studio' ),
		);

		$words = array();
		foreach ( Login_Text::labels() as $key => $label ) {
			$original                       = Login_Text::original( $key );
			$words[ 'login_words.' . $key ] = self::c(
				$label,
				/* translators: %s: WordPress's own wording. */
				sprintf( __( 'WordPress says: "%s"', 'admin-studio' ), $original ),
				array( 'input_attrs' => array( 'placeholder' => $original ) )
			);
		}

		return array(
			'adminstudio_brand'              => array(
				'title'       => __( 'Brand kit', 'admin-studio' ),
				'description' => __( 'Set these once. The login page, emails and system pages all use them unless you override a value in that area.', 'admin-studio' ),
				'controls'    => array(
					'brand.logo'         => self::c( __( 'Logo', 'admin-studio' ), __( 'Defaults to your theme logo, then your site icon.', 'admin-studio' ) ),
					'brand.logo_width'   => self::c( __( 'Logo width (px)', 'admin-studio' ) ),
					'brand.primary'      => self::c( __( 'Primary colour', 'admin-studio' ), $theme( 'primary' ) ),
					'brand.secondary'    => self::c( __( 'Secondary colour', 'admin-studio' ), $theme( 'secondary' ) ),
					'brand.text'         => self::c( __( 'Text colour', 'admin-studio' ), $theme( 'text' ) ),
					'brand.background'   => self::c( __( 'Background colour', 'admin-studio' ), $theme( 'background' ) ),
					'brand.surface'      => self::c( __( 'Surface colour', 'admin-studio' ), __( 'Cards, forms and email bodies.', 'admin-studio' ) ),
					'brand.border'       => self::c( __( 'Border colour', 'admin-studio' ) ),
					'brand.success'      => self::c( __( 'Success colour', 'admin-studio' ) ),
					'brand.error'        => self::c( __( 'Error colour', 'admin-studio' ) ),
					'brand.font_heading' => self::select( __( 'Heading font', 'admin-studio' ), Fonts::choices(), __( 'Google fonts are downloaded to your site when you publish, so visitors never load them from Google.', 'admin-studio' ) ),
					'brand.font_body'    => self::select( __( 'Body font', 'admin-studio' ), Fonts::choices() ),
					'brand.radius'       => self::c(
						__( 'Corner radius (px)', 'admin-studio' ),
						'',
						array(
							'input_attrs' => array(
								'min' => 0,
								'max' => 40,
							),
						)
					),
					'brand.button_style' => self::select(
						__( 'Button style', 'admin-studio' ),
						array(
							'filled'  => __( 'Filled', 'admin-studio' ),
							'outline' => __( 'Outline', 'admin-studio' ),
							'pill'    => __( 'Pill', 'admin-studio' ),
						)
					),
				),
			),
			'adminstudio_login_layout'       => array(
				'title'    => __( 'Login: layout and background', 'admin-studio' ),
				'controls' => array(
					'login.layout'            => self::select(
						__( 'Layout', 'admin-studio' ),
						array(
							'centered' => __( 'Centered card', 'admin-studio' ),
							'split'    => __( 'Split screen', 'admin-studio' ),
							'fullbg'   => __( 'Full background', 'admin-studio' ),
							'minimal'  => __( 'Minimal (WordPress default, restyled)', 'admin-studio' ),
						)
					),
					'login.split_side'        => self::select(
						__( 'Split screen: image side', 'admin-studio' ),
						array(
							'left'  => __( 'Left', 'admin-studio' ),
							'right' => __( 'Right', 'admin-studio' ),
						)
					),
					'login.side_image'        => self::c( __( 'Split screen: image', 'admin-studio' ) ),
					'login.bg_type'           => self::select(
						__( 'Background type', 'admin-studio' ),
						array(
							'color'    => __( 'Colour', 'admin-studio' ),
							'gradient' => __( 'Gradient', 'admin-studio' ),
							'image'    => __( 'Image', 'admin-studio' ),
							'video'    => __( 'Video', 'admin-studio' ),
						)
					),
					'login.bg_color'          => self::c( __( 'Background colour', 'admin-studio' ), $inherit ),
					'login.bg_gradient_from'  => self::c( __( 'Gradient start', 'admin-studio' ) ),
					'login.bg_gradient_to'    => self::c( __( 'Gradient end', 'admin-studio' ) ),
					'login.bg_gradient_angle' => self::c(
						__( 'Gradient angle (degrees)', 'admin-studio' ),
						'',
						array(
							'input_attrs' => array(
								'min' => 0,
								'max' => 360,
							),
						)
					),
					'login.bg_image'          => self::c( __( 'Background image', 'admin-studio' ) ),
					'login.bg_video'          => self::c( __( 'Background video (MP4)', 'admin-studio' ), __( 'Muted and looped. The background colour shows while it loads.', 'admin-studio' ), array( 'control' => 'video' ) ),
					'login.overlay_color'     => self::c( __( 'Overlay colour', 'admin-studio' ) ),
					'login.overlay_opacity'   => array_merge( self::c( __( 'Overlay opacity', 'admin-studio' ) ), $range ),
				),
			),
			'adminstudio_login_logo'         => array(
				'title'    => __( 'Login: logo', 'admin-studio' ),
				'controls' => array(
					'login.hide_logo'  => self::c( __( 'Hide the logo', 'admin-studio' ) ),
					'login.logo'       => self::c( __( 'Logo', 'admin-studio' ), $inherit ),
					'login.logo_width' => self::c( __( 'Logo width (px)', 'admin-studio' ), '', $px ),
					'login.logo_url'   => self::c( __( 'Logo link', 'admin-studio' ), __( 'Defaults to your home page.', 'admin-studio' ), array( 'control' => 'url' ) ),
					'login.logo_title' => self::c( __( 'Logo text for screen readers', 'admin-studio' ), __( 'Defaults to your site title.', 'admin-studio' ) ),
				),
			),
			'adminstudio_login_form'         => array(
				'title'    => __( 'Login: form and button', 'admin-studio' ),
				'controls' => array(
					'login.form_width'    => self::c(
						__( 'Form width (px)', 'admin-studio' ),
						'',
						array(
							'input_attrs' => array(
								'min' => 280,
								'max' => 640,
							),
						)
					),
					'login.form_padding'  => self::c(
						__( 'Form padding (px)', 'admin-studio' ),
						'',
						array(
							'input_attrs' => array(
								'min' => 0,
								'max' => 80,
							),
						)
					),
					'login.form_radius'   => self::c( __( 'Form corner radius (px)', 'admin-studio' ), '', $px ),
					'login.form_bg'       => self::c( __( 'Form background', 'admin-studio' ), $inherit ),
					'login.form_shadow'   => self::c( __( 'Show a shadow under the form', 'admin-studio' ) ),
					'login.field_style'   => self::select(
						__( 'Field style', 'admin-studio' ),
						array(
							'boxed'     => __( 'Boxed', 'admin-studio' ),
							'underline' => __( 'Underline', 'admin-studio' ),
							'filled'    => __( 'Filled', 'admin-studio' ),
						)
					),
					'login.hide_labels'   => self::c( __( 'Hide field labels and use placeholders', 'admin-studio' ) ),
					'login.text_color'    => self::c( __( 'Text colour', 'admin-studio' ), $inherit ),
					'login.link_color'    => self::c( __( 'Link colour', 'admin-studio' ), $inherit ),
					'login.button_bg'     => self::c( __( 'Button colour', 'admin-studio' ), $inherit ),
					'login.button_text'   => self::c( __( 'Button text colour', 'admin-studio' ), __( 'Leave empty to pick black or white automatically.', 'admin-studio' ) ),
					'login.button_radius' => self::c( __( 'Button corner radius (px)', 'admin-studio' ), '', $px ),
				),
			),
			'adminstudio_login_text'         => array(
				'title'    => __( 'Login: text and links', 'admin-studio' ),
				'controls' => array(
					'login.message'                => self::c( __( 'Message above the form', 'admin-studio' ), __( 'Basic HTML is allowed.', 'admin-studio' ) ),
					'login.footer_text'            => self::c( __( 'Footer text', 'admin-studio' ) ),
					'login.hide_lost_password'     => self::c( __( 'Hide "Lost your password?"', 'admin-studio' ) ),
					'login.hide_back_to_site'      => self::c( __( 'Hide "Go to site"', 'admin-studio' ) ),
					'login.hide_language_switcher' => self::c( __( 'Hide the language switcher', 'admin-studio' ) ),
					'login.hide_privacy_link'      => self::c( __( 'Hide the privacy policy link', 'admin-studio' ) ),
					'login.back_link_text'         => self::c( __( '"Go to site" link text', 'admin-studio' ), __( 'For example "Back to {site_name}". Leave empty for WordPress\'s "← Go to" text.', 'admin-studio' ) ),
					'login.error_message'          => self::c( __( 'Message for a failed log in', 'admin-studio' ), __( 'One message for a wrong username, email or password, for example "Those details did not match. Try again." It also stops the screen revealing which usernames exist. Leave empty for WordPress\'s messages.', 'admin-studio' ) ),
				),
			),
			'adminstudio_login_words'        => array(
				'title'       => __( 'Login: wording', 'admin-studio' ),
				'description' => __( 'Replace any text on the log in, lost password, reset password and registration screens. Leave a box empty to keep WordPress\'s wording. You can use {site_name}; in the two messages with a link, use {login_url} for the login address.', 'admin-studio' ),
				'controls'    => $words,
			),
			'adminstudio_login_css'          => array(
				'title'    => __( 'Login: custom CSS', 'admin-studio' ),
				'controls' => array(
					'login.custom_css' => self::c( __( 'Custom CSS', 'admin-studio' ), __( 'Applied to every login, registration and password screen.', 'admin-studio' ) ),
				),
			),
			'adminstudio_emails'             => array(
				'title'       => __( 'Emails: design', 'admin-studio' ),
				'description' => sprintf(
					/* translators: %s: settings page URL. */
					__( 'Edit the wording of each email on the <a href="%s">settings page</a>.', 'admin-studio' ),
					esc_url( admin_url( 'options-general.php?page=admin-studio&tab=emails' ) )
				),
				'controls'    => array(
					'picker:email'       => array(
						'label'   => __( 'Preview email', 'admin-studio' ),
						'choices' => $email_choices,
					),
					'emails.show_logo'   => self::c( __( 'Show the logo in the header', 'admin-studio' ) ),
					'emails.logo'        => self::c( __( 'Logo', 'admin-studio' ), $inherit ),
					'emails.logo_width'  => self::c( __( 'Logo width (px)', 'admin-studio' ), '', $px ),
					'emails.header_bg'   => self::c( __( 'Header background', 'admin-studio' ), __( 'Leave empty to use the content background.', 'admin-studio' ) ),
					'emails.header_text' => self::c( __( 'Header text colour', 'admin-studio' ) ),
					'emails.body_bg'     => self::c( __( 'Outer background', 'admin-studio' ), $inherit ),
					'emails.content_bg'  => self::c( __( 'Content background', 'admin-studio' ), $inherit ),
					'emails.text_color'  => self::c( __( 'Text colour', 'admin-studio' ), $inherit ),
					'emails.button_bg'   => self::c( __( 'Button colour', 'admin-studio' ), $inherit ),
					'emails.button_text' => self::c( __( 'Button text colour', 'admin-studio' ) ),
					'emails.font'        => self::select(
						__( 'Font', 'admin-studio' ),
						array(
							'brand'     => __( 'Brand body font (falls back to a safe font in most mail apps)', 'admin-studio' ),
							'system'    => __( 'System font', 'admin-studio' ),
							'arial'     => 'Arial',
							'helvetica' => 'Helvetica',
							'georgia'   => 'Georgia',
							'verdana'   => 'Verdana',
							'trebuchet' => 'Trebuchet MS',
						)
					),
					'emails.width'       => self::c(
						__( 'Content width (px)', 'admin-studio' ),
						'',
						array(
							'input_attrs' => array(
								'min' => 480,
								'max' => 800,
							),
						)
					),
					'emails.radius'      => self::c( __( 'Corner radius (px)', 'admin-studio' ), '', $px ),
					'emails.footer_text' => self::c( __( 'Footer text', 'admin-studio' ), __( 'Tags: {site_name}, {site_url}, {year}.', 'admin-studio' ) ),
				),
			),
			'adminstudio_errors_design'      => array(
				'title'       => __( 'System pages: design', 'admin-studio' ),
				'description' => __( 'Shared by the critical error, database error, update maintenance, permission and 404 pages.', 'admin-studio' ),
				'controls'    => array(
					'picker:page'            => array(
						'label'   => __( 'Preview page', 'admin-studio' ),
						'choices' => $page_choices,
					),
					'error_pages.bg_color'   => self::c( __( 'Background colour', 'admin-studio' ), $inherit ),
					'error_pages.surface'    => self::c( __( 'Card colour', 'admin-studio' ), $inherit ),
					'error_pages.text_color' => self::c( __( 'Text colour', 'admin-studio' ), $inherit ),
					'error_pages.primary'    => self::c( __( 'Button colour', 'admin-studio' ), $inherit ),
					'error_pages.show_logo'  => self::c( __( 'Show the logo', 'admin-studio' ) ),
					'error_pages.image'      => self::c( __( 'Illustration', 'admin-studio' ), __( 'Optional image shown above the title.', 'admin-studio' ) ),
				),
			),
			'adminstudio_errors_critical'    => array(
				'title'    => __( 'System pages: critical error', 'admin-studio' ),
				'controls' => array(
					'error_pages.critical'            => self::c( __( 'Replace the critical error page', 'admin-studio' ), __( 'Shown when a plugin or theme causes a fatal PHP error.', 'admin-studio' ) ),
					'error_pages.critical_title'      => self::c( __( 'Title', 'admin-studio' ) ),
					'error_pages.critical_message'    => self::c( __( 'Message', 'admin-studio' ) ),
					'error_pages.critical_button'     => self::c( __( 'Button text', 'admin-studio' ), __( 'Leave empty to hide the button.', 'admin-studio' ) ),
					'error_pages.critical_button_url' => self::c( __( 'Button link', 'admin-studio' ), __( 'Defaults to your home page.', 'admin-studio' ), array( 'control' => 'url' ) ),
				),
			),
			'adminstudio_errors_db'          => array(
				'title'    => __( 'System pages: database error', 'admin-studio' ),
				'controls' => array(
					'error_pages.db'         => self::c( __( 'Replace the database connection error page', 'admin-studio' ), __( 'Saved as a static file, because the database is unavailable when this page shows.', 'admin-studio' ) ),
					'error_pages.db_title'   => self::c( __( 'Title', 'admin-studio' ) ),
					'error_pages.db_message' => self::c( __( 'Message', 'admin-studio' ) ),
				),
			),
			'adminstudio_errors_maintenance' => array(
				'title'    => __( 'System pages: update maintenance', 'admin-studio' ),
				'controls' => array(
					'error_pages.maintenance'         => self::c( __( 'Replace the "Briefly unavailable" screen', 'admin-studio' ), __( 'Shown for a few seconds while WordPress installs updates.', 'admin-studio' ) ),
					'error_pages.maintenance_title'   => self::c( __( 'Title', 'admin-studio' ) ),
					'error_pages.maintenance_message' => self::c( __( 'Message', 'admin-studio' ) ),
				),
			),
			'adminstudio_errors_wp_die'      => array(
				'title'    => __( 'System pages: no permission and notices', 'admin-studio' ),
				'controls' => array(
					'error_pages.wp_die'            => self::c( __( 'Restyle WordPress notice screens', 'admin-studio' ), __( 'Every screen WordPress shows with a short message and a back link gets your design.', 'admin-studio' ) ),
					'error_pages.denied'            => self::c( __( 'Use my own "no permission" page', 'admin-studio' ), __( 'Replaces "Sorry, you are not allowed to access this page." and similar messages with the text below.', 'admin-studio' ) ),
					'error_pages.denied_title'      => self::c( __( 'No permission: title', 'admin-studio' ) ),
					'error_pages.denied_message'    => self::c( __( 'No permission: message', 'admin-studio' ), __( 'Leave empty to show WordPress\'s own message.', 'admin-studio' ) ),
					'error_pages.denied_button'     => self::c( __( 'No permission: button text', 'admin-studio' ), __( 'Leave empty to hide the button.', 'admin-studio' ) ),
					'error_pages.denied_button_url' => self::c( __( 'No permission: button link', 'admin-studio' ), __( 'Defaults to the dashboard for people who can use it, otherwise the home page.', 'admin-studio' ), array( 'control' => 'url' ) ),
					'error_pages.expired_title'     => self::c( __( 'Expired link: title', 'admin-studio' ), __( 'Shown instead of "The link you followed has expired."', 'admin-studio' ) ),
					'error_pages.expired_message'   => self::c( __( 'Expired link: message', 'admin-studio' ) ),
					'error_pages.wp_die_title'      => self::c( __( 'Other screens: fallback title', 'admin-studio' ), __( 'Used when WordPress does not give the screen a title.', 'admin-studio' ) ),
					'error_pages.wp_die_button'     => self::c( __( 'Other screens: back button text', 'admin-studio' ) ),
				),
			),
			'adminstudio_errors_notfound'    => array(
				'title'    => __( 'System pages: page not found (404)', 'admin-studio' ),
				'controls' => array(
					'error_pages.notfound'         => self::c( __( 'Replace the theme 404 page', 'admin-studio' ), __( 'Off by default, so your theme keeps its own 404 page.', 'admin-studio' ) ),
					'error_pages.notfound_title'   => self::c( __( 'Title', 'admin-studio' ) ),
					'error_pages.notfound_message' => self::c( __( 'Message', 'admin-studio' ) ),
					'error_pages.notfound_button'  => self::c( __( 'Button text', 'admin-studio' ) ),
					'error_pages.notfound_search'  => self::c( __( 'Show a search box', 'admin-studio' ) ),
				),
			),
			'adminstudio_maintenance'        => array(
				'title'       => __( 'Coming soon page', 'admin-studio' ),
				'description' => sprintf(
					/* translators: %s: settings page URL. */
					__( 'Turn the mode on and choose who can see the site on the <a href="%s">settings page</a>.', 'admin-studio' ),
					esc_url( admin_url( 'options-general.php?page=admin-studio&tab=maintenance' ) )
				),
				'controls'    => array(
					'maintenance.headline'        => self::c( __( 'Headline', 'admin-studio' ) ),
					'maintenance.message'         => self::c( __( 'Message', 'admin-studio' ) ),
					'maintenance.countdown'       => self::c( __( 'Countdown to', 'admin-studio' ), __( 'Leave empty to hide the countdown. Uses your site time zone.', 'admin-studio' ) ),
					'maintenance.show_logo'       => self::c( __( 'Show the logo', 'admin-studio' ) ),
					'maintenance.bg_color'        => self::c( __( 'Background colour', 'admin-studio' ), $inherit ),
					'maintenance.bg_image'        => self::c( __( 'Background image', 'admin-studio' ) ),
					'maintenance.overlay_opacity' => array_merge( self::c( __( 'Image darkening', 'admin-studio' ) ), $range ),
					'maintenance.text_color'      => self::c( __( 'Text colour', 'admin-studio' ), __( 'Leave empty to pick a readable colour automatically.', 'admin-studio' ) ),
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
		wp_enqueue_script( 'adminstudio-customizer', ADMINSTUDIO_URL . 'assets/js/customizer-controls.js', array( 'customize-controls', 'jquery' ), ADMINSTUDIO_VERSION, true );

		$brand = Brand_Kit::get();

		wp_localize_script(
			'adminstudio-customizer',
			'adminstudioCustomizer',
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
					'lowContrast' => __( 'Low contrast (%s:1). Text may be hard to read. Aim for at least 4.5:1.', 'admin-studio' ),
				),
			)
		);
	}
}

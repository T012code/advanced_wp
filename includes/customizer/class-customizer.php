<?php
/**
 * Customizer panel, sections and controls.
 *
 * @package LayoDT
 */

namespace LayoDT\Customizer;

use LayoDT\Brand_Kit;
use LayoDT\Fonts;
use LayoDT\Preview;
use LayoDT\Settings;
use LayoDT\Modules\Emails\Templates;
use LayoDT\Modules\Login\Login;
use LayoDT\Modules\Login\Login_Text;

defined( 'ABSPATH' ) || exit;

/**
 * Registers everything under Appearance → Customize → Layò Dashboard Toolkit.
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
			'layodt_brand'              => Preview::url( 'login' ),
			'layodt_login_layout'       => Preview::url( 'login' ),
			'layodt_login_logo'         => Preview::url( 'login' ),
			'layodt_login_form'         => Preview::url( 'login' ),
			'layodt_login_text'         => Preview::url( 'login' ),
			'layodt_login_words'        => Preview::url( 'login' ),
			'layodt_emails'             => Preview::url( 'email', array( 'layodt_template' => 'welcome' ) ),
			'layodt_errors_design'      => Preview::url( 'page', array( 'layodt_page' => 'critical' ) ),
			'layodt_errors_critical'    => Preview::url( 'page', array( 'layodt_page' => 'critical' ) ),
			'layodt_errors_db'          => Preview::url( 'page', array( 'layodt_page' => 'db' ) ),
			'layodt_errors_maintenance' => Preview::url( 'page', array( 'layodt_page' => 'maintenance' ) ),
			'layodt_errors_wp_die'      => Preview::url( 'page', array( 'layodt_page' => 'denied' ) ),
			'layodt_errors_notfound'    => Preview::url( 'page', array( 'layodt_page' => 'notfound' ) ),
			'layodt_maintenance'        => Preview::url( 'maintenance' ),
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
			'layodt',
			array(
				'title'       => __( 'Layò Dashboard Toolkit', 'layo-dashboard-toolkit' ),
				'description' => sprintf(
					/* translators: %s: settings page URL. */
					__( 'Design your login page, emails and system pages. Email wording, security and admin branding live on the <a href="%s">settings page</a>.', 'layo-dashboard-toolkit' ),
					esc_url( admin_url( 'options-general.php?page=layo-dashboard-toolkit' ) )
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
					'panel'       => 'layodt',
				)
			);

			foreach ( $section['controls'] as $key => $control ) {
				if ( 0 === strpos( $key, 'picker:' ) ) {
					$wp->add_control(
						new Preview_Picker_Control(
							$wp,
							'layodt_' . str_replace( ':', '_', $key ),
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
		$inherit  = __( 'Leave empty to use the brand kit.', 'layo-dashboard-toolkit' );
		$detected = Brand_Kit::detected();
		$custom   = 'custom' === Brand_Kit::source();
		$theme    = static function ( $key ) use ( $detected, $custom ) {
			if ( ! $custom ) {
				return __( 'Used when "Brand colours from" is set to Custom colours.', 'layo-dashboard-toolkit' );
			}

			return '' !== $detected[ $key ]
				/* translators: %s: colour value detected from the theme. */
				? sprintf( __( 'Following theme: %s. Pick a colour to override, or clear it to follow the theme again.', 'layo-dashboard-toolkit' ), $detected[ $key ] )
				: __( 'Not set by the theme. Using the built-in default until you pick one.', 'layo-dashboard-toolkit' );
		};
		$px       = array(
			'input_attrs' => array(
				'min'         => 0,
				'max'         => 2000,
				'placeholder' => __( 'Brand default', 'layo-dashboard-toolkit' ),
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
			$email_choices[ Preview::url( 'email', array( 'layodt_template' => $key ) ) ] = $template['label'];
		}

		$page_choices = array(
			Preview::url( 'page', array( 'layodt_page' => 'critical' ) )    => __( 'Critical error', 'layo-dashboard-toolkit' ),
			Preview::url( 'page', array( 'layodt_page' => 'db' ) )          => __( 'Database connection error', 'layo-dashboard-toolkit' ),
			Preview::url( 'page', array( 'layodt_page' => 'maintenance' ) ) => __( 'Update maintenance', 'layo-dashboard-toolkit' ),
			Preview::url( 'page', array( 'layodt_page' => 'denied' ) )      => __( 'No permission', 'layo-dashboard-toolkit' ),
			Preview::url( 'page', array( 'layodt_page' => 'expired' ) )     => __( 'Expired link', 'layo-dashboard-toolkit' ),
			Preview::url( 'page', array( 'layodt_page' => 'wp_die' ) )      => __( 'Other notice screens', 'layo-dashboard-toolkit' ),
			Preview::url( 'page', array( 'layodt_page' => 'notfound' ) )    => __( 'Page not found (404)', 'layo-dashboard-toolkit' ),
		);

		$login_choices = array();
		foreach ( Login::preview_screens() as $key => $label ) {
			$login_choices[ Preview::url( 'login', array( 'layodt_screen' => $key ) ) ] = $label;
		}

		$link_places = array(
			'below'    => __( 'Below the form', 'layo-dashboard-toolkit' ),
			'password' => __( 'Next to the Password label', 'layo-dashboard-toolkit' ),
			'button'   => __( 'Above the main button', 'layo-dashboard-toolkit' ),
			'hidden'   => __( 'Hidden', 'layo-dashboard-toolkit' ),
		);

		$words = array();
		foreach ( Login_Text::labels() as $key => $label ) {
			$original                       = Login_Text::original( $key );
			$words[ 'login_words.' . $key ] = self::c(
				$label,
				/* translators: %s: WordPress's own wording. */
				sprintf( __( 'WordPress says: "%s"', 'layo-dashboard-toolkit' ), $original ),
				array( 'input_attrs' => array( 'placeholder' => $original ) )
			);
		}

		return array(
			'layodt_brand'              => array(
				'title'       => __( 'Brand kit', 'layo-dashboard-toolkit' ),
				'description' => __( 'Set these once. The login page, emails and system pages all use them unless you override a value in that area.', 'layo-dashboard-toolkit' ),
				'controls'    => array(
					'brand.logo'         => self::c( __( 'Logo', 'layo-dashboard-toolkit' ), __( 'Defaults to your theme logo, then your site icon.', 'layo-dashboard-toolkit' ) ),
					'brand.logo_width'   => self::c( __( 'Logo width (px)', 'layo-dashboard-toolkit' ) ),
					'brand.source'       => self::select(
						__( 'Brand colours from', 'layo-dashboard-toolkit' ),
						array(
							'theme'    => __( 'Theme', 'layo-dashboard-toolkit' ),
							'logo'     => __( 'Logo', 'layo-dashboard-toolkit' ),
							'frontend' => __( 'Front end (what visitors see)', 'layo-dashboard-toolkit' ),
							'custom'   => __( 'Custom colours (below)', 'layo-dashboard-toolkit' ),
						),
						sprintf(
							/* translators: %s: settings page URL. */
							__( 'To read the front end colours again, use the Brand kit card on the <a href="%s">settings page</a>. Fonts, logo and corners are set here whichever you pick.', 'layo-dashboard-toolkit' ),
							esc_url( admin_url( 'options-general.php?page=layo-dashboard-toolkit' ) )
						)
					),
					'brand.primary'      => self::c( __( 'Primary colour', 'layo-dashboard-toolkit' ), $theme( 'primary' ) ),
					'brand.secondary'    => self::c( __( 'Secondary colour', 'layo-dashboard-toolkit' ), $theme( 'secondary' ) ),
					'brand.text'         => self::c( __( 'Text colour', 'layo-dashboard-toolkit' ), $theme( 'text' ) ),
					'brand.background'   => self::c( __( 'Background colour', 'layo-dashboard-toolkit' ), $theme( 'background' ) ),
					'brand.surface'      => self::c( __( 'Surface colour', 'layo-dashboard-toolkit' ), __( 'Cards, forms and email bodies.', 'layo-dashboard-toolkit' ) ),
					'brand.border'       => self::c( __( 'Border colour', 'layo-dashboard-toolkit' ) ),
					'brand.success'      => self::c( __( 'Success colour', 'layo-dashboard-toolkit' ) ),
					'brand.error'        => self::c( __( 'Error colour', 'layo-dashboard-toolkit' ) ),
					'brand.font_heading' => self::select( __( 'Heading font', 'layo-dashboard-toolkit' ), Fonts::choices(), __( 'Google fonts are downloaded to your site when you publish, so visitors never load them from Google.', 'layo-dashboard-toolkit' ) ),
					'brand.font_body'    => self::select( __( 'Body font', 'layo-dashboard-toolkit' ), Fonts::choices() ),
					'brand.radius'       => self::c(
						__( 'Corner radius (px)', 'layo-dashboard-toolkit' ),
						'',
						array(
							'input_attrs' => array(
								'min' => 0,
								'max' => 40,
							),
						)
					),
					'brand.button_style' => self::select(
						__( 'Button style', 'layo-dashboard-toolkit' ),
						array(
							'filled'  => __( 'Filled', 'layo-dashboard-toolkit' ),
							'outline' => __( 'Outline', 'layo-dashboard-toolkit' ),
							'pill'    => __( 'Pill', 'layo-dashboard-toolkit' ),
						)
					),
				),
			),
			'layodt_login_layout'       => array(
				'title'    => __( 'Login: layout and background', 'layo-dashboard-toolkit' ),
				'controls' => array(
					'login.layout'            => self::select(
						__( 'Layout', 'layo-dashboard-toolkit' ),
						array(
							'centered' => __( 'Centered card', 'layo-dashboard-toolkit' ),
							'split'    => __( 'Split screen', 'layo-dashboard-toolkit' ),
							'fullbg'   => __( 'Full background', 'layo-dashboard-toolkit' ),
							'minimal'  => __( 'Minimal (WordPress default, restyled)', 'layo-dashboard-toolkit' ),
						)
					),
					'login.split_side'        => self::select(
						__( 'Split screen: image side', 'layo-dashboard-toolkit' ),
						array(
							'left'  => __( 'Left', 'layo-dashboard-toolkit' ),
							'right' => __( 'Right', 'layo-dashboard-toolkit' ),
						)
					),
					'login.side_image'        => self::c( __( 'Split screen: image', 'layo-dashboard-toolkit' ) ),
					'login.bg_type'           => self::select(
						__( 'Background type', 'layo-dashboard-toolkit' ),
						array(
							'color'    => __( 'Colour', 'layo-dashboard-toolkit' ),
							'gradient' => __( 'Gradient', 'layo-dashboard-toolkit' ),
							'image'    => __( 'Image', 'layo-dashboard-toolkit' ),
							'video'    => __( 'Video', 'layo-dashboard-toolkit' ),
						)
					),
					'login.bg_color'          => self::c( __( 'Background colour', 'layo-dashboard-toolkit' ), $inherit ),
					'login.bg_gradient_from'  => self::c( __( 'Gradient start', 'layo-dashboard-toolkit' ) ),
					'login.bg_gradient_to'    => self::c( __( 'Gradient end', 'layo-dashboard-toolkit' ) ),
					'login.bg_gradient_angle' => self::c(
						__( 'Gradient angle (degrees)', 'layo-dashboard-toolkit' ),
						'',
						array(
							'input_attrs' => array(
								'min' => 0,
								'max' => 360,
							),
						)
					),
					'login.bg_image'          => self::c( __( 'Background image', 'layo-dashboard-toolkit' ) ),
					'login.bg_video'          => self::c( __( 'Background video (MP4)', 'layo-dashboard-toolkit' ), __( 'Muted and looped. The background colour shows while it loads.', 'layo-dashboard-toolkit' ), array( 'control' => 'video' ) ),
					'login.overlay_color'     => self::c( __( 'Overlay colour', 'layo-dashboard-toolkit' ) ),
					'login.overlay_opacity'   => array_merge( self::c( __( 'Overlay opacity', 'layo-dashboard-toolkit' ) ), $range ),
				),
			),
			'layodt_login_logo'         => array(
				'title'    => __( 'Login: logo', 'layo-dashboard-toolkit' ),
				'controls' => array(
					'login.hide_logo'  => self::c( __( 'Hide the logo', 'layo-dashboard-toolkit' ) ),
					'login.logo'       => self::c( __( 'Logo', 'layo-dashboard-toolkit' ), $inherit ),
					'login.logo_width' => self::c( __( 'Logo width (px)', 'layo-dashboard-toolkit' ), '', $px ),
					'login.logo_url'   => self::c( __( 'Logo link', 'layo-dashboard-toolkit' ), __( 'Defaults to your home page.', 'layo-dashboard-toolkit' ), array( 'control' => 'url' ) ),
					'login.logo_title' => self::c( __( 'Logo text for screen readers', 'layo-dashboard-toolkit' ), __( 'Defaults to your site title.', 'layo-dashboard-toolkit' ) ),
				),
			),
			'layodt_login_form'         => array(
				'title'    => __( 'Login: form and button', 'layo-dashboard-toolkit' ),
				'controls' => array(
					'login.form_width'    => self::c(
						__( 'Form width (px)', 'layo-dashboard-toolkit' ),
						'',
						array(
							'input_attrs' => array(
								'min' => 280,
								'max' => 640,
							),
						)
					),
					'login.form_padding'  => self::c(
						__( 'Form padding (px)', 'layo-dashboard-toolkit' ),
						'',
						array(
							'input_attrs' => array(
								'min' => 0,
								'max' => 80,
							),
						)
					),
					'login.form_radius'   => self::c( __( 'Form corner radius (px)', 'layo-dashboard-toolkit' ), '', $px ),
					'login.form_bg'       => self::c( __( 'Form background', 'layo-dashboard-toolkit' ), $inherit ),
					'login.form_shadow'   => self::c( __( 'Show a shadow under the form', 'layo-dashboard-toolkit' ) ),
					'login.field_style'   => self::select(
						__( 'Field style', 'layo-dashboard-toolkit' ),
						array(
							'boxed'     => __( 'Boxed', 'layo-dashboard-toolkit' ),
							'underline' => __( 'Underline', 'layo-dashboard-toolkit' ),
							'filled'    => __( 'Filled', 'layo-dashboard-toolkit' ),
						)
					),
					'login.hide_labels'   => self::c( __( 'Hide field labels and use placeholders', 'layo-dashboard-toolkit' ) ),
					'login.text_color'    => self::c( __( 'Text colour', 'layo-dashboard-toolkit' ), $inherit ),
					'login.link_color'    => self::c( __( 'Link colour', 'layo-dashboard-toolkit' ), $inherit ),
					'login.button_bg'     => self::c( __( 'Button colour', 'layo-dashboard-toolkit' ), $inherit ),
					'login.button_text'   => self::c( __( 'Button text colour', 'layo-dashboard-toolkit' ), __( 'Leave empty to pick black or white automatically.', 'layo-dashboard-toolkit' ) ),
					'login.button_radius' => self::c( __( 'Button corner radius (px)', 'layo-dashboard-toolkit' ), '', $px ),
				),
			),
			'layodt_login_text'         => array(
				'title'       => __( 'Login: text and links', 'layo-dashboard-toolkit' ),
				'description' => __( 'The "Register" link only appears on your site when anyone can register (Settings → General → Membership).', 'layo-dashboard-toolkit' ),
				'controls'    => array(
					'picker:login'                 => array(
						'label'   => __( 'Preview screen', 'layo-dashboard-toolkit' ),
						'choices' => $login_choices,
					),
					'login.links_layout'           => self::select(
						__( 'Links under the form', 'layo-dashboard-toolkit' ),
						array(
							'stacked' => __( 'Stacked, one per line (WordPress default)', 'layo-dashboard-toolkit' ),
							'inline'  => __( 'Inline, side by side', 'layo-dashboard-toolkit' ),
							'split'   => __( 'Split, spread across the width', 'layo-dashboard-toolkit' ),
							'card'    => __( 'Inside the card', 'layo-dashboard-toolkit' ),
							'buttons' => __( 'Full-width buttons', 'layo-dashboard-toolkit' ),
						)
					),
					'login.link_lost'              => self::select( __( '"Lost your password?"', 'layo-dashboard-toolkit' ), $link_places, __( 'Next to the Password label is how most apps place it.', 'layo-dashboard-toolkit' ) ),
					'login.link_register'          => self::select( __( '"Register"', 'layo-dashboard-toolkit' ), $link_places ),
					'login.link_login'             => self::select( __( '"Log in" (on the other screens)', 'layo-dashboard-toolkit' ), $link_places ),
					'login.link_back'              => self::select( __( '"Go to site"', 'layo-dashboard-toolkit' ), $link_places ),
					'login.message'                => self::c( __( 'Message above the form', 'layo-dashboard-toolkit' ), __( 'Basic HTML is allowed.', 'layo-dashboard-toolkit' ) ),
					'login.footer_text'            => self::c( __( 'Footer text', 'layo-dashboard-toolkit' ) ),
					'login.hide_language_switcher' => self::c( __( 'Hide the language switcher', 'layo-dashboard-toolkit' ) ),
					'login.hide_privacy_link'      => self::c( __( 'Hide the privacy policy link', 'layo-dashboard-toolkit' ) ),
					'login.back_link_text'         => self::c( __( '"Go to site" link text', 'layo-dashboard-toolkit' ), __( 'For example "Back to {site_name}". Leave empty for WordPress\'s "← Go to" text.', 'layo-dashboard-toolkit' ) ),
					'login.error_message'          => self::c( __( 'Message for a failed log in', 'layo-dashboard-toolkit' ), __( 'One message for a wrong username, email or password, for example "Those details did not match. Try again." It also stops the screen revealing which usernames exist. Leave empty for WordPress\'s messages.', 'layo-dashboard-toolkit' ) ),
				),
			),
			'layodt_login_words'        => array(
				'title'       => __( 'Login: wording', 'layo-dashboard-toolkit' ),
				'description' => __( 'Replace any text on the log in, lost password, reset password and registration screens. Leave a box empty to keep WordPress\'s wording. You can use {site_name}; in the two messages with a link, use {login_url} for the login address.', 'layo-dashboard-toolkit' ),
				'controls'    => $words,
			),
			'layodt_emails'             => array(
				'title'       => __( 'Emails: design', 'layo-dashboard-toolkit' ),
				'description' => sprintf(
					/* translators: %s: settings page URL. */
					__( 'Edit the wording of each email on the <a href="%s">settings page</a>.', 'layo-dashboard-toolkit' ),
					esc_url( admin_url( 'options-general.php?page=layo-dashboard-toolkit&tab=emails' ) )
				),
				'controls'    => array(
					'picker:email'       => array(
						'label'   => __( 'Preview email', 'layo-dashboard-toolkit' ),
						'choices' => $email_choices,
					),
					'emails.show_logo'   => self::c( __( 'Show the logo in the header', 'layo-dashboard-toolkit' ) ),
					'emails.logo'        => self::c( __( 'Logo', 'layo-dashboard-toolkit' ), $inherit ),
					'emails.logo_width'  => self::c( __( 'Logo width (px)', 'layo-dashboard-toolkit' ), '', $px ),
					'emails.header_bg'   => self::c( __( 'Header background', 'layo-dashboard-toolkit' ), __( 'Leave empty to use the content background.', 'layo-dashboard-toolkit' ) ),
					'emails.header_text' => self::c( __( 'Header text colour', 'layo-dashboard-toolkit' ) ),
					'emails.body_bg'     => self::c( __( 'Outer background', 'layo-dashboard-toolkit' ), $inherit ),
					'emails.content_bg'  => self::c( __( 'Content background', 'layo-dashboard-toolkit' ), $inherit ),
					'emails.text_color'  => self::c( __( 'Text colour', 'layo-dashboard-toolkit' ), $inherit ),
					'emails.button_bg'   => self::c( __( 'Button colour', 'layo-dashboard-toolkit' ), $inherit ),
					'emails.button_text' => self::c( __( 'Button text colour', 'layo-dashboard-toolkit' ) ),
					'emails.font'        => self::select(
						__( 'Font', 'layo-dashboard-toolkit' ),
						array(
							'brand'     => __( 'Brand body font (falls back to a safe font in most mail apps)', 'layo-dashboard-toolkit' ),
							'system'    => __( 'System font', 'layo-dashboard-toolkit' ),
							'arial'     => 'Arial',
							'helvetica' => 'Helvetica',
							'georgia'   => 'Georgia',
							'verdana'   => 'Verdana',
							'trebuchet' => 'Trebuchet MS',
						)
					),
					'emails.width'       => self::c(
						__( 'Content width (px)', 'layo-dashboard-toolkit' ),
						'',
						array(
							'input_attrs' => array(
								'min' => 480,
								'max' => 800,
							),
						)
					),
					'emails.radius'      => self::c( __( 'Corner radius (px)', 'layo-dashboard-toolkit' ), '', $px ),
					'emails.footer_text' => self::c( __( 'Footer text', 'layo-dashboard-toolkit' ), __( 'Tags: {site_name}, {site_url}, {year}.', 'layo-dashboard-toolkit' ) ),
				),
			),
			'layodt_errors_design'      => array(
				'title'       => __( 'System pages: design', 'layo-dashboard-toolkit' ),
				'description' => __( 'Shared by the critical error, database error, update maintenance, permission and 404 pages.', 'layo-dashboard-toolkit' ),
				'controls'    => array(
					'picker:page'            => array(
						'label'   => __( 'Preview page', 'layo-dashboard-toolkit' ),
						'choices' => $page_choices,
					),
					'error_pages.bg_color'   => self::c( __( 'Background colour', 'layo-dashboard-toolkit' ), $inherit ),
					'error_pages.surface'    => self::c( __( 'Card colour', 'layo-dashboard-toolkit' ), $inherit ),
					'error_pages.text_color' => self::c( __( 'Text colour', 'layo-dashboard-toolkit' ), $inherit ),
					'error_pages.primary'    => self::c( __( 'Button colour', 'layo-dashboard-toolkit' ), $inherit ),
					'error_pages.show_logo'  => self::c( __( 'Show the logo', 'layo-dashboard-toolkit' ) ),
					'error_pages.image'      => self::c( __( 'Illustration', 'layo-dashboard-toolkit' ), __( 'Optional image shown above the title.', 'layo-dashboard-toolkit' ) ),
				),
			),
			'layodt_errors_critical'    => array(
				'title'    => __( 'System pages: critical error', 'layo-dashboard-toolkit' ),
				'controls' => array(
					'error_pages.critical'            => self::c( __( 'Replace the critical error page', 'layo-dashboard-toolkit' ), __( 'Shown when a plugin or theme causes a fatal PHP error.', 'layo-dashboard-toolkit' ) ),
					'error_pages.critical_title'      => self::c( __( 'Title', 'layo-dashboard-toolkit' ) ),
					'error_pages.critical_message'    => self::c( __( 'Message', 'layo-dashboard-toolkit' ) ),
					'error_pages.critical_button'     => self::c( __( 'Button text', 'layo-dashboard-toolkit' ), __( 'Leave empty to hide the button.', 'layo-dashboard-toolkit' ) ),
					'error_pages.critical_button_url' => self::c( __( 'Button link', 'layo-dashboard-toolkit' ), __( 'Defaults to your home page.', 'layo-dashboard-toolkit' ), array( 'control' => 'url' ) ),
					'error_pages.critical_admin_note' => self::c( __( 'Message for administrators', 'layo-dashboard-toolkit' ), __( 'Shown under the message only to signed-in administrators, so they know how to recover. Visitors never see it. Use {admin_email} for the site\'s admin email. Leave empty to show nothing extra.', 'layo-dashboard-toolkit' ), array( 'control' => 'textarea' ) ),
				),
			),
			'layodt_errors_db'          => array(
				'title'    => __( 'System pages: database error', 'layo-dashboard-toolkit' ),
				'controls' => array(
					'error_pages.db'         => self::c( __( 'Replace the database connection error page', 'layo-dashboard-toolkit' ), __( 'Saved as a static file, because the database is unavailable when this page shows.', 'layo-dashboard-toolkit' ) ),
					'error_pages.db_title'   => self::c( __( 'Title', 'layo-dashboard-toolkit' ) ),
					'error_pages.db_message' => self::c( __( 'Message', 'layo-dashboard-toolkit' ) ),
				),
			),
			'layodt_errors_maintenance' => array(
				'title'    => __( 'System pages: update maintenance', 'layo-dashboard-toolkit' ),
				'controls' => array(
					'error_pages.maintenance'         => self::c( __( 'Replace the "Briefly unavailable" screen', 'layo-dashboard-toolkit' ), __( 'Shown for a few seconds while WordPress installs updates.', 'layo-dashboard-toolkit' ) ),
					'error_pages.maintenance_title'   => self::c( __( 'Title', 'layo-dashboard-toolkit' ) ),
					'error_pages.maintenance_message' => self::c( __( 'Message', 'layo-dashboard-toolkit' ) ),
				),
			),
			'layodt_errors_wp_die'      => array(
				'title'    => __( 'System pages: no permission and notices', 'layo-dashboard-toolkit' ),
				'controls' => array(
					'error_pages.wp_die'            => self::c( __( 'Restyle WordPress notice screens', 'layo-dashboard-toolkit' ), __( 'Every screen WordPress shows with a short message and a back link gets your design.', 'layo-dashboard-toolkit' ) ),
					'error_pages.denied'            => self::c( __( 'Use my own "no permission" page', 'layo-dashboard-toolkit' ), __( 'Replaces "Sorry, you are not allowed to access this page." and similar messages with the text below.', 'layo-dashboard-toolkit' ) ),
					'error_pages.denied_title'      => self::c( __( 'No permission: title', 'layo-dashboard-toolkit' ) ),
					'error_pages.denied_message'    => self::c( __( 'No permission: message', 'layo-dashboard-toolkit' ), __( 'Leave empty to show WordPress\'s own message.', 'layo-dashboard-toolkit' ) ),
					'error_pages.denied_button'     => self::c( __( 'No permission: button text', 'layo-dashboard-toolkit' ), __( 'Leave empty to hide the button.', 'layo-dashboard-toolkit' ) ),
					'error_pages.denied_button_url' => self::c( __( 'No permission: button link', 'layo-dashboard-toolkit' ), __( 'Defaults to the dashboard for people who can use it, otherwise the home page.', 'layo-dashboard-toolkit' ), array( 'control' => 'url' ) ),
					'error_pages.expired_title'     => self::c( __( 'Expired link: title', 'layo-dashboard-toolkit' ), __( 'Shown instead of "The link you followed has expired."', 'layo-dashboard-toolkit' ) ),
					'error_pages.expired_message'   => self::c( __( 'Expired link: message', 'layo-dashboard-toolkit' ) ),
					'error_pages.wp_die_title'      => self::c( __( 'Other screens: fallback title', 'layo-dashboard-toolkit' ), __( 'Used when WordPress does not give the screen a title.', 'layo-dashboard-toolkit' ) ),
					'error_pages.wp_die_button'     => self::c( __( 'Other screens: back button text', 'layo-dashboard-toolkit' ) ),
				),
			),
			'layodt_errors_notfound'    => array(
				'title'    => __( 'System pages: page not found (404)', 'layo-dashboard-toolkit' ),
				'controls' => array(
					'error_pages.notfound'         => self::c( __( 'Replace the theme 404 page', 'layo-dashboard-toolkit' ), __( 'Off by default, so your theme keeps its own 404 page.', 'layo-dashboard-toolkit' ) ),
					'error_pages.notfound_title'   => self::c( __( 'Title', 'layo-dashboard-toolkit' ) ),
					'error_pages.notfound_message' => self::c( __( 'Message', 'layo-dashboard-toolkit' ) ),
					'error_pages.notfound_button'  => self::c( __( 'Button text', 'layo-dashboard-toolkit' ) ),
					'error_pages.notfound_search'  => self::c( __( 'Show a search box', 'layo-dashboard-toolkit' ) ),
				),
			),
			'layodt_maintenance'        => array(
				'title'       => __( 'Coming soon page', 'layo-dashboard-toolkit' ),
				'description' => sprintf(
					/* translators: %s: settings page URL. */
					__( 'Turn the mode on and choose who can see the site on the <a href="%s">settings page</a>. This design is not used when the settings page is set to show a page you built.', 'layo-dashboard-toolkit' ),
					esc_url( admin_url( 'options-general.php?page=layo-dashboard-toolkit&tab=maintenance' ) )
				),
				'controls'    => array(
					'maintenance.headline'        => self::c( __( 'Headline', 'layo-dashboard-toolkit' ) ),
					'maintenance.message'         => self::c( __( 'Message', 'layo-dashboard-toolkit' ) ),
					'maintenance.countdown'       => self::c( __( 'Countdown to', 'layo-dashboard-toolkit' ), __( 'Leave empty to hide the countdown. Uses your site time zone.', 'layo-dashboard-toolkit' ) ),
					'maintenance.show_logo'       => self::c( __( 'Show the logo', 'layo-dashboard-toolkit' ) ),
					'maintenance.bg_color'        => self::c( __( 'Background colour', 'layo-dashboard-toolkit' ), $inherit ),
					'maintenance.bg_image'        => self::c( __( 'Background image', 'layo-dashboard-toolkit' ) ),
					'maintenance.overlay_opacity' => array_merge( self::c( __( 'Image darkening', 'layo-dashboard-toolkit' ) ), $range ),
					'maintenance.text_color'      => self::c( __( 'Text colour', 'layo-dashboard-toolkit' ), __( 'Leave empty to pick a readable colour automatically.', 'layo-dashboard-toolkit' ) ),
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
		wp_enqueue_script( 'layodt-customizer', LAYODT_URL . 'assets/js/customizer-controls.js', array( 'customize-controls', 'jquery' ), \LayoDT\Plugin::asset_version( 'assets/js/customizer-controls.js' ), true );

		$brand = Brand_Kit::get();

		wp_localize_script(
			'layodt-customizer',
			'layodtCustomizer',
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
					'lowContrast' => __( 'Low contrast (%s:1). Text may be hard to read. Aim for at least 4.5:1.', 'layo-dashboard-toolkit' ),
				),
			)
		);
	}
}

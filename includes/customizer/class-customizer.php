<?php
/**
 * Customizer panel, sections and controls.
 *
 * @package Adminora
 */

namespace Adminora\Customizer;

use Adminora\Brand_Kit;
use Adminora\Fonts;
use Adminora\Preview;
use Adminora\Settings;
use Adminora\Modules\Emails\Templates;
use Adminora\Modules\Login\Login;
use Adminora\Modules\Login\Login_Text;

defined( 'ABSPATH' ) || exit;

/**
 * Registers everything under Appearance → Customize → Adminora.
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
			'adminora_brand'              => Preview::url( 'login' ),
			'adminora_login_layout'       => Preview::url( 'login' ),
			'adminora_login_logo'         => Preview::url( 'login' ),
			'adminora_login_form'         => Preview::url( 'login' ),
			'adminora_login_text'         => Preview::url( 'login' ),
			'adminora_login_words'        => Preview::url( 'login' ),
			'adminora_emails'             => Preview::url( 'email', array( 'adminora_template' => 'welcome' ) ),
			'adminora_errors_design'      => Preview::url( 'page', array( 'adminora_page' => 'critical' ) ),
			'adminora_errors_critical'    => Preview::url( 'page', array( 'adminora_page' => 'critical' ) ),
			'adminora_errors_db'          => Preview::url( 'page', array( 'adminora_page' => 'db' ) ),
			'adminora_errors_maintenance' => Preview::url( 'page', array( 'adminora_page' => 'maintenance' ) ),
			'adminora_errors_wp_die'      => Preview::url( 'page', array( 'adminora_page' => 'denied' ) ),
			'adminora_errors_notfound'    => Preview::url( 'page', array( 'adminora_page' => 'notfound' ) ),
			'adminora_maintenance'        => Preview::url( 'maintenance' ),
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
			'adminora',
			array(
				'title'       => __( 'Adminora', 'adminora' ),
				'description' => sprintf(
					/* translators: %s: settings page URL. */
					__( 'Design your login page, emails and system pages. Email wording, security and admin branding live on the <a href="%s">settings page</a>.', 'adminora' ),
					esc_url( admin_url( 'options-general.php?page=adminora' ) )
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
					'panel'       => 'adminora',
				)
			);

			foreach ( $section['controls'] as $key => $control ) {
				if ( 0 === strpos( $key, 'picker:' ) ) {
					$wp->add_control(
						new Preview_Picker_Control(
							$wp,
							'adminora_' . str_replace( ':', '_', $key ),
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
		$inherit  = __( 'Leave empty to use the brand kit.', 'adminora' );
		$detected = Brand_Kit::detected();
		$custom   = 'custom' === Brand_Kit::source();
		$theme    = static function ( $key ) use ( $detected, $custom ) {
			if ( ! $custom ) {
				return __( 'Used when "Brand colours from" is set to Custom colours.', 'adminora' );
			}

			return '' !== $detected[ $key ]
				/* translators: %s: colour value detected from the theme. */
				? sprintf( __( 'Following theme: %s. Pick a colour to override, or clear it to follow the theme again.', 'adminora' ), $detected[ $key ] )
				: __( 'Not set by the theme. Using the built-in default until you pick one.', 'adminora' );
		};
		$px       = array(
			'input_attrs' => array(
				'min'         => 0,
				'max'         => 2000,
				'placeholder' => __( 'Brand default', 'adminora' ),
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
			$email_choices[ Preview::url( 'email', array( 'adminora_template' => $key ) ) ] = $template['label'];
		}

		$page_choices = array(
			Preview::url( 'page', array( 'adminora_page' => 'critical' ) )    => __( 'Critical error', 'adminora' ),
			Preview::url( 'page', array( 'adminora_page' => 'db' ) )          => __( 'Database connection error', 'adminora' ),
			Preview::url( 'page', array( 'adminora_page' => 'maintenance' ) ) => __( 'Update maintenance', 'adminora' ),
			Preview::url( 'page', array( 'adminora_page' => 'denied' ) )      => __( 'No permission', 'adminora' ),
			Preview::url( 'page', array( 'adminora_page' => 'expired' ) )     => __( 'Expired link', 'adminora' ),
			Preview::url( 'page', array( 'adminora_page' => 'wp_die' ) )      => __( 'Other notice screens', 'adminora' ),
			Preview::url( 'page', array( 'adminora_page' => 'notfound' ) )    => __( 'Page not found (404)', 'adminora' ),
		);

		$login_choices = array();
		foreach ( Login::preview_screens() as $key => $label ) {
			$login_choices[ Preview::url( 'login', array( 'adminora_screen' => $key ) ) ] = $label;
		}

		$link_places = array(
			'below'    => __( 'Below the form', 'adminora' ),
			'password' => __( 'Next to the Password label', 'adminora' ),
			'button'   => __( 'Above the main button', 'adminora' ),
			'hidden'   => __( 'Hidden', 'adminora' ),
		);

		$words = array();
		foreach ( Login_Text::labels() as $key => $label ) {
			$original                       = Login_Text::original( $key );
			$words[ 'login_words.' . $key ] = self::c(
				$label,
				/* translators: %s: WordPress's own wording. */
				sprintf( __( 'WordPress says: "%s"', 'adminora' ), $original ),
				array( 'input_attrs' => array( 'placeholder' => $original ) )
			);
		}

		return array(
			'adminora_brand'              => array(
				'title'       => __( 'Brand kit', 'adminora' ),
				'description' => __( 'Set these once. The login page, emails and system pages all use them unless you override a value in that area.', 'adminora' ),
				'controls'    => array(
					'brand.logo'         => self::c( __( 'Logo', 'adminora' ), __( 'Defaults to your theme logo, then your site icon.', 'adminora' ) ),
					'brand.logo_width'   => self::c( __( 'Logo width (px)', 'adminora' ) ),
					'brand.source'       => self::select(
						__( 'Brand colours from', 'adminora' ),
						array(
							'theme'    => __( 'Theme', 'adminora' ),
							'logo'     => __( 'Logo', 'adminora' ),
							'frontend' => __( 'Front end (what visitors see)', 'adminora' ),
							'custom'   => __( 'Custom colours (below)', 'adminora' ),
						),
						sprintf(
							/* translators: %s: settings page URL. */
							__( 'To read the front end colours again, use the Brand kit card on the <a href="%s">settings page</a>. Fonts, logo and corners are set here whichever you pick.', 'adminora' ),
							esc_url( admin_url( 'options-general.php?page=adminora' ) )
						)
					),
					'brand.primary'      => self::c( __( 'Primary colour', 'adminora' ), $theme( 'primary' ) ),
					'brand.secondary'    => self::c( __( 'Secondary colour', 'adminora' ), $theme( 'secondary' ) ),
					'brand.text'         => self::c( __( 'Text colour', 'adminora' ), $theme( 'text' ) ),
					'brand.background'   => self::c( __( 'Background colour', 'adminora' ), $theme( 'background' ) ),
					'brand.surface'      => self::c( __( 'Surface colour', 'adminora' ), __( 'Cards, forms and email bodies.', 'adminora' ) ),
					'brand.border'       => self::c( __( 'Border colour', 'adminora' ) ),
					'brand.success'      => self::c( __( 'Success colour', 'adminora' ) ),
					'brand.error'        => self::c( __( 'Error colour', 'adminora' ) ),
					'brand.font_heading' => self::select( __( 'Heading font', 'adminora' ), Fonts::choices(), __( 'Google fonts are downloaded to your site when you publish, so visitors never load them from Google.', 'adminora' ) ),
					'brand.font_body'    => self::select( __( 'Body font', 'adminora' ), Fonts::choices() ),
					'brand.radius'       => self::c(
						__( 'Corner radius (px)', 'adminora' ),
						'',
						array(
							'input_attrs' => array(
								'min' => 0,
								'max' => 40,
							),
						)
					),
					'brand.button_style' => self::select(
						__( 'Button style', 'adminora' ),
						array(
							'filled'  => __( 'Filled', 'adminora' ),
							'outline' => __( 'Outline', 'adminora' ),
							'pill'    => __( 'Pill', 'adminora' ),
						)
					),
				),
			),
			'adminora_login_layout'       => array(
				'title'    => __( 'Login: layout and background', 'adminora' ),
				'controls' => array(
					'login.layout'            => self::select(
						__( 'Layout', 'adminora' ),
						array(
							'centered' => __( 'Centered card', 'adminora' ),
							'split'    => __( 'Split screen', 'adminora' ),
							'fullbg'   => __( 'Full background', 'adminora' ),
							'minimal'  => __( 'Minimal (WordPress default, restyled)', 'adminora' ),
						)
					),
					'login.split_side'        => self::select(
						__( 'Split screen: image side', 'adminora' ),
						array(
							'left'  => __( 'Left', 'adminora' ),
							'right' => __( 'Right', 'adminora' ),
						)
					),
					'login.side_image'        => self::c( __( 'Split screen: image', 'adminora' ) ),
					'login.bg_type'           => self::select(
						__( 'Background type', 'adminora' ),
						array(
							'color'    => __( 'Colour', 'adminora' ),
							'gradient' => __( 'Gradient', 'adminora' ),
							'image'    => __( 'Image', 'adminora' ),
							'video'    => __( 'Video', 'adminora' ),
						)
					),
					'login.bg_color'          => self::c( __( 'Background colour', 'adminora' ), $inherit ),
					'login.bg_gradient_from'  => self::c( __( 'Gradient start', 'adminora' ) ),
					'login.bg_gradient_to'    => self::c( __( 'Gradient end', 'adminora' ) ),
					'login.bg_gradient_angle' => self::c(
						__( 'Gradient angle (degrees)', 'adminora' ),
						'',
						array(
							'input_attrs' => array(
								'min' => 0,
								'max' => 360,
							),
						)
					),
					'login.bg_image'          => self::c( __( 'Background image', 'adminora' ) ),
					'login.bg_video'          => self::c( __( 'Background video (MP4)', 'adminora' ), __( 'Muted and looped. The background colour shows while it loads.', 'adminora' ), array( 'control' => 'video' ) ),
					'login.overlay_color'     => self::c( __( 'Overlay colour', 'adminora' ) ),
					'login.overlay_opacity'   => array_merge( self::c( __( 'Overlay opacity', 'adminora' ) ), $range ),
				),
			),
			'adminora_login_logo'         => array(
				'title'    => __( 'Login: logo', 'adminora' ),
				'controls' => array(
					'login.hide_logo'  => self::c( __( 'Hide the logo', 'adminora' ) ),
					'login.logo'       => self::c( __( 'Logo', 'adminora' ), $inherit ),
					'login.logo_width' => self::c( __( 'Logo width (px)', 'adminora' ), '', $px ),
					'login.logo_url'   => self::c( __( 'Logo link', 'adminora' ), __( 'Defaults to your home page.', 'adminora' ), array( 'control' => 'url' ) ),
					'login.logo_title' => self::c( __( 'Logo text for screen readers', 'adminora' ), __( 'Defaults to your site title.', 'adminora' ) ),
				),
			),
			'adminora_login_form'         => array(
				'title'    => __( 'Login: form and button', 'adminora' ),
				'controls' => array(
					'login.form_width'    => self::c(
						__( 'Form width (px)', 'adminora' ),
						'',
						array(
							'input_attrs' => array(
								'min' => 280,
								'max' => 640,
							),
						)
					),
					'login.form_padding'  => self::c(
						__( 'Form padding (px)', 'adminora' ),
						'',
						array(
							'input_attrs' => array(
								'min' => 0,
								'max' => 80,
							),
						)
					),
					'login.form_radius'   => self::c( __( 'Form corner radius (px)', 'adminora' ), '', $px ),
					'login.form_bg'       => self::c( __( 'Form background', 'adminora' ), $inherit ),
					'login.form_shadow'   => self::c( __( 'Show a shadow under the form', 'adminora' ) ),
					'login.field_style'   => self::select(
						__( 'Field style', 'adminora' ),
						array(
							'boxed'     => __( 'Boxed', 'adminora' ),
							'underline' => __( 'Underline', 'adminora' ),
							'filled'    => __( 'Filled', 'adminora' ),
						)
					),
					'login.hide_labels'   => self::c( __( 'Hide field labels and use placeholders', 'adminora' ) ),
					'login.text_color'    => self::c( __( 'Text colour', 'adminora' ), $inherit ),
					'login.link_color'    => self::c( __( 'Link colour', 'adminora' ), $inherit ),
					'login.button_bg'     => self::c( __( 'Button colour', 'adminora' ), $inherit ),
					'login.button_text'   => self::c( __( 'Button text colour', 'adminora' ), __( 'Leave empty to pick black or white automatically.', 'adminora' ) ),
					'login.button_radius' => self::c( __( 'Button corner radius (px)', 'adminora' ), '', $px ),
				),
			),
			'adminora_login_text'         => array(
				'title'       => __( 'Login: text and links', 'adminora' ),
				'description' => __( 'The "Register" link only appears on your site when anyone can register (Settings → General → Membership).', 'adminora' ),
				'controls'    => array(
					'picker:login'                 => array(
						'label'   => __( 'Preview screen', 'adminora' ),
						'choices' => $login_choices,
					),
					'login.links_layout'           => self::select(
						__( 'Links under the form', 'adminora' ),
						array(
							'stacked' => __( 'Stacked, one per line (WordPress default)', 'adminora' ),
							'inline'  => __( 'Inline, side by side', 'adminora' ),
							'split'   => __( 'Split, spread across the width', 'adminora' ),
							'card'    => __( 'Inside the card', 'adminora' ),
							'buttons' => __( 'Full-width buttons', 'adminora' ),
						)
					),
					'login.link_lost'              => self::select( __( '"Lost your password?"', 'adminora' ), $link_places, __( 'Next to the Password label is how most apps place it.', 'adminora' ) ),
					'login.link_register'          => self::select( __( '"Register"', 'adminora' ), $link_places ),
					'login.link_login'             => self::select( __( '"Log in" (on the other screens)', 'adminora' ), $link_places ),
					'login.link_back'              => self::select( __( '"Go to site"', 'adminora' ), $link_places ),
					'login.message'                => self::c( __( 'Message above the form', 'adminora' ), __( 'Basic HTML is allowed.', 'adminora' ) ),
					'login.footer_text'            => self::c( __( 'Footer text', 'adminora' ) ),
					'login.hide_language_switcher' => self::c( __( 'Hide the language switcher', 'adminora' ) ),
					'login.hide_privacy_link'      => self::c( __( 'Hide the privacy policy link', 'adminora' ) ),
					'login.back_link_text'         => self::c( __( '"Go to site" link text', 'adminora' ), __( 'For example "Back to {site_name}". Leave empty for WordPress\'s "← Go to" text.', 'adminora' ) ),
					'login.error_message'          => self::c( __( 'Message for a failed log in', 'adminora' ), __( 'One message for a wrong username, email or password, for example "Those details did not match. Try again." It also stops the screen revealing which usernames exist. Leave empty for WordPress\'s messages.', 'adminora' ) ),
				),
			),
			'adminora_login_words'        => array(
				'title'       => __( 'Login: wording', 'adminora' ),
				'description' => __( 'Replace any text on the log in, lost password, reset password and registration screens. Leave a box empty to keep WordPress\'s wording. You can use {site_name}; in the two messages with a link, use {login_url} for the login address.', 'adminora' ),
				'controls'    => $words,
			),
			'adminora_emails'             => array(
				'title'       => __( 'Emails: design', 'adminora' ),
				'description' => sprintf(
					/* translators: %s: settings page URL. */
					__( 'Edit the wording of each email on the <a href="%s">settings page</a>.', 'adminora' ),
					esc_url( admin_url( 'options-general.php?page=adminora&tab=emails' ) )
				),
				'controls'    => array(
					'picker:email'       => array(
						'label'   => __( 'Preview email', 'adminora' ),
						'choices' => $email_choices,
					),
					'emails.show_logo'   => self::c( __( 'Show the logo in the header', 'adminora' ) ),
					'emails.logo'        => self::c( __( 'Logo', 'adminora' ), $inherit ),
					'emails.logo_width'  => self::c( __( 'Logo width (px)', 'adminora' ), '', $px ),
					'emails.header_bg'   => self::c( __( 'Header background', 'adminora' ), __( 'Leave empty to use the content background.', 'adminora' ) ),
					'emails.header_text' => self::c( __( 'Header text colour', 'adminora' ) ),
					'emails.body_bg'     => self::c( __( 'Outer background', 'adminora' ), $inherit ),
					'emails.content_bg'  => self::c( __( 'Content background', 'adminora' ), $inherit ),
					'emails.text_color'  => self::c( __( 'Text colour', 'adminora' ), $inherit ),
					'emails.button_bg'   => self::c( __( 'Button colour', 'adminora' ), $inherit ),
					'emails.button_text' => self::c( __( 'Button text colour', 'adminora' ) ),
					'emails.font'        => self::select(
						__( 'Font', 'adminora' ),
						array(
							'brand'     => __( 'Brand body font (falls back to a safe font in most mail apps)', 'adminora' ),
							'system'    => __( 'System font', 'adminora' ),
							'arial'     => 'Arial',
							'helvetica' => 'Helvetica',
							'georgia'   => 'Georgia',
							'verdana'   => 'Verdana',
							'trebuchet' => 'Trebuchet MS',
						)
					),
					'emails.width'       => self::c(
						__( 'Content width (px)', 'adminora' ),
						'',
						array(
							'input_attrs' => array(
								'min' => 480,
								'max' => 800,
							),
						)
					),
					'emails.radius'      => self::c( __( 'Corner radius (px)', 'adminora' ), '', $px ),
					'emails.footer_text' => self::c( __( 'Footer text', 'adminora' ), __( 'Tags: {site_name}, {site_url}, {year}.', 'adminora' ) ),
				),
			),
			'adminora_errors_design'      => array(
				'title'       => __( 'System pages: design', 'adminora' ),
				'description' => __( 'Shared by the critical error, database error, update maintenance, permission and 404 pages.', 'adminora' ),
				'controls'    => array(
					'picker:page'            => array(
						'label'   => __( 'Preview page', 'adminora' ),
						'choices' => $page_choices,
					),
					'error_pages.bg_color'   => self::c( __( 'Background colour', 'adminora' ), $inherit ),
					'error_pages.surface'    => self::c( __( 'Card colour', 'adminora' ), $inherit ),
					'error_pages.text_color' => self::c( __( 'Text colour', 'adminora' ), $inherit ),
					'error_pages.primary'    => self::c( __( 'Button colour', 'adminora' ), $inherit ),
					'error_pages.show_logo'  => self::c( __( 'Show the logo', 'adminora' ) ),
					'error_pages.image'      => self::c( __( 'Illustration', 'adminora' ), __( 'Optional image shown above the title.', 'adminora' ) ),
				),
			),
			'adminora_errors_critical'    => array(
				'title'    => __( 'System pages: critical error', 'adminora' ),
				'controls' => array(
					'error_pages.critical'            => self::c( __( 'Replace the critical error page', 'adminora' ), __( 'Shown when a plugin or theme causes a fatal PHP error.', 'adminora' ) ),
					'error_pages.critical_title'      => self::c( __( 'Title', 'adminora' ) ),
					'error_pages.critical_message'    => self::c( __( 'Message', 'adminora' ) ),
					'error_pages.critical_button'     => self::c( __( 'Button text', 'adminora' ), __( 'Leave empty to hide the button.', 'adminora' ) ),
					'error_pages.critical_button_url' => self::c( __( 'Button link', 'adminora' ), __( 'Defaults to your home page.', 'adminora' ), array( 'control' => 'url' ) ),
					'error_pages.critical_admin_note' => self::c( __( 'Message for administrators', 'adminora' ), __( 'Shown under the message only to signed-in administrators, so they know how to recover. Visitors never see it. Use {admin_email} for the site\'s admin email. Leave empty to show nothing extra.', 'adminora' ), array( 'control' => 'textarea' ) ),
				),
			),
			'adminora_errors_db'          => array(
				'title'    => __( 'System pages: database error', 'adminora' ),
				'controls' => array(
					'error_pages.db'         => self::c( __( 'Replace the database connection error page', 'adminora' ), __( 'Saved as a static file, because the database is unavailable when this page shows.', 'adminora' ) ),
					'error_pages.db_title'   => self::c( __( 'Title', 'adminora' ) ),
					'error_pages.db_message' => self::c( __( 'Message', 'adminora' ) ),
				),
			),
			'adminora_errors_maintenance' => array(
				'title'    => __( 'System pages: update maintenance', 'adminora' ),
				'controls' => array(
					'error_pages.maintenance'         => self::c( __( 'Replace the "Briefly unavailable" screen', 'adminora' ), __( 'Shown for a few seconds while WordPress installs updates.', 'adminora' ) ),
					'error_pages.maintenance_title'   => self::c( __( 'Title', 'adminora' ) ),
					'error_pages.maintenance_message' => self::c( __( 'Message', 'adminora' ) ),
				),
			),
			'adminora_errors_wp_die'      => array(
				'title'    => __( 'System pages: no permission and notices', 'adminora' ),
				'controls' => array(
					'error_pages.wp_die'            => self::c( __( 'Restyle WordPress notice screens', 'adminora' ), __( 'Every screen WordPress shows with a short message and a back link gets your design.', 'adminora' ) ),
					'error_pages.denied'            => self::c( __( 'Use my own "no permission" page', 'adminora' ), __( 'Replaces "Sorry, you are not allowed to access this page." and similar messages with the text below.', 'adminora' ) ),
					'error_pages.denied_title'      => self::c( __( 'No permission: title', 'adminora' ) ),
					'error_pages.denied_message'    => self::c( __( 'No permission: message', 'adminora' ), __( 'Leave empty to show WordPress\'s own message.', 'adminora' ) ),
					'error_pages.denied_button'     => self::c( __( 'No permission: button text', 'adminora' ), __( 'Leave empty to hide the button.', 'adminora' ) ),
					'error_pages.denied_button_url' => self::c( __( 'No permission: button link', 'adminora' ), __( 'Defaults to the dashboard for people who can use it, otherwise the home page.', 'adminora' ), array( 'control' => 'url' ) ),
					'error_pages.expired_title'     => self::c( __( 'Expired link: title', 'adminora' ), __( 'Shown instead of "The link you followed has expired."', 'adminora' ) ),
					'error_pages.expired_message'   => self::c( __( 'Expired link: message', 'adminora' ) ),
					'error_pages.wp_die_title'      => self::c( __( 'Other screens: fallback title', 'adminora' ), __( 'Used when WordPress does not give the screen a title.', 'adminora' ) ),
					'error_pages.wp_die_button'     => self::c( __( 'Other screens: back button text', 'adminora' ) ),
				),
			),
			'adminora_errors_notfound'    => array(
				'title'    => __( 'System pages: page not found (404)', 'adminora' ),
				'controls' => array(
					'error_pages.notfound'         => self::c( __( 'Replace the theme 404 page', 'adminora' ), __( 'Off by default, so your theme keeps its own 404 page.', 'adminora' ) ),
					'error_pages.notfound_title'   => self::c( __( 'Title', 'adminora' ) ),
					'error_pages.notfound_message' => self::c( __( 'Message', 'adminora' ) ),
					'error_pages.notfound_button'  => self::c( __( 'Button text', 'adminora' ) ),
					'error_pages.notfound_search'  => self::c( __( 'Show a search box', 'adminora' ) ),
				),
			),
			'adminora_maintenance'        => array(
				'title'       => __( 'Coming soon page', 'adminora' ),
				'description' => sprintf(
					/* translators: %s: settings page URL. */
					__( 'Turn the mode on and choose who can see the site on the <a href="%s">settings page</a>. This design is not used when the settings page is set to show a page you built.', 'adminora' ),
					esc_url( admin_url( 'options-general.php?page=adminora&tab=maintenance' ) )
				),
				'controls'    => array(
					'maintenance.headline'        => self::c( __( 'Headline', 'adminora' ) ),
					'maintenance.message'         => self::c( __( 'Message', 'adminora' ) ),
					'maintenance.countdown'       => self::c( __( 'Countdown to', 'adminora' ), __( 'Leave empty to hide the countdown. Uses your site time zone.', 'adminora' ) ),
					'maintenance.show_logo'       => self::c( __( 'Show the logo', 'adminora' ) ),
					'maintenance.bg_color'        => self::c( __( 'Background colour', 'adminora' ), $inherit ),
					'maintenance.bg_image'        => self::c( __( 'Background image', 'adminora' ) ),
					'maintenance.overlay_opacity' => array_merge( self::c( __( 'Image darkening', 'adminora' ) ), $range ),
					'maintenance.text_color'      => self::c( __( 'Text colour', 'adminora' ), __( 'Leave empty to pick a readable colour automatically.', 'adminora' ) ),
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
		wp_enqueue_script( 'adminora-customizer', ADMINORA_URL . 'assets/js/customizer-controls.js', array( 'customize-controls', 'jquery' ), \Adminora\Plugin::asset_version( 'assets/js/customizer-controls.js' ), true );

		$brand = Brand_Kit::get();

		wp_localize_script(
			'adminora-customizer',
			'adminoraCustomizer',
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
					'lowContrast' => __( 'Low contrast (%s:1). Text may be hard to read. Aim for at least 4.5:1.', 'adminora' ),
				),
			)
		);
	}
}

<?php
/**
 * Settings storage, field schema and sanitizing.
 *
 * Every setting lives in one nested option, `adminora_settings`, and is addressed
 * with a dot path such as `login.layout`. The Customizer stores into the same
 * option through ids like `adminora_settings[login][layout]`.
 *
 * @package Adminora
 */

namespace Adminora;

defined( 'ABSPATH' ) || exit;

/**
 * Reads, writes and sanitizes plugin settings.
 */
class Settings {

	const OPTION = 'adminora_settings';

	/**
	 * Field schema, built once per request.
	 *
	 * @var array|null
	 */
	private static $fields = null;

	/**
	 * Returns the full field schema: dot path => field definition.
	 *
	 * Definitions hold no translated strings so the schema can be read before
	 * `init`. Fields with `i18n => true` take their default from Defaults::text().
	 *
	 * @return array
	 */
	public static function fields() {
		if ( null !== self::$fields ) {
			return self::$fields;
		}

		$color      = array(
			'type'    => 'color',
			'default' => '',
		);
		$text       = array(
			'type' => 'text',
			'i18n' => true,
		);
		$html       = array(
			'type' => 'html',
			'i18n' => true,
		);
		$on         = array(
			'type'    => 'bool',
			'default' => true,
		);
		$off        = array(
			'type'    => 'bool',
			'default' => false,
		);
		$link_place = array(
			'type'    => 'select',
			'default' => 'below',
			'choices' => array( 'below', 'password', 'button', 'hidden' ),
		);
		$url        = array(
			'type'    => 'url',
			'default' => '',
		);
		$px         = array(
			'type'    => 'int_optional',
			'default' => '',
			'min'     => 0,
			'max'     => 2000,
		);
		$layout     = array( 'centered', 'split', 'fullbg', 'minimal' );

		$fields = array(
			// Modules.
			'modules.login'                    => $on,
			'modules.emails'                   => $on,
			'modules.error_pages'              => $on,
			'modules.tab_meta'                 => $on,
			'modules.admin_branding'           => $on,
			'modules.security'                 => $off,
			'modules.maintenance'              => $off,
			'modules.roles'                    => $on,
			'modules.admin_look'               => $on,
			'modules.widgets'                  => $on,
			'modules.sessions'                 => $on,
			'general.remove_data'              => $off,

			// Brand kit.
			'brand.logo'                       => $url,
			'brand.logo_width'                 => array(
				'type'    => 'int',
				'default' => 84,
				'min'     => 16,
				'max'     => 400,
			),
			'brand.source'                     => array(
				'type'    => 'select',
				'default' => 'theme',
				'choices' => array( 'theme', 'logo', 'frontend', 'custom' ),
			),
			'brand.primary'                    => $color,
			'brand.secondary'                  => $color,
			'brand.text'                       => $color,
			'brand.background'                 => $color,
			'brand.surface'                    => $color,
			'brand.border'                     => $color,
			'brand.success'                    => $color,
			'brand.error'                      => $color,
			'brand.font_heading'               => array(
				'type'    => 'select',
				'default' => 'theme',
				'choices' => Fonts::keys(),
			),
			'brand.font_body'                  => array(
				'type'    => 'select',
				'default' => 'theme',
				'choices' => Fonts::keys(),
			),
			'brand.radius'                     => array(
				'type'    => 'int',
				'default' => 8,
				'min'     => 0,
				'max'     => 40,
			),
			'brand.button_style'               => array(
				'type'    => 'select',
				'default' => 'filled',
				'choices' => array( 'filled', 'outline', 'pill' ),
			),

			// Login page.
			'login.layout'                     => array(
				'type'    => 'select',
				'default' => 'centered',
				'choices' => $layout,
			),
			'login.split_side'                 => array(
				'type'    => 'select',
				'default' => 'left',
				'choices' => array( 'left', 'right' ),
			),
			'login.bg_type'                    => array(
				'type'    => 'select',
				'default' => 'color',
				'choices' => array( 'color', 'gradient', 'image', 'video' ),
			),
			'login.bg_color'                   => $color,
			'login.bg_gradient_from'           => $color,
			'login.bg_gradient_to'             => $color,
			'login.bg_gradient_angle'          => array(
				'type'    => 'int',
				'default' => 135,
				'min'     => 0,
				'max'     => 360,
			),
			'login.bg_image'                   => $url,
			'login.bg_video'                   => $url,
			'login.overlay_color'              => $color,
			'login.overlay_opacity'            => array(
				'type'    => 'int',
				'default' => 0,
				'min'     => 0,
				'max'     => 100,
			),
			'login.side_image'                 => $url,
			'login.logo'                       => $url,
			'login.logo_width'                 => $px,
			'login.logo_url'                   => $url,
			'login.logo_title'                 => array(
				'type'    => 'text',
				'default' => '',
			),
			'login.hide_logo'                  => $off,
			'login.form_width'                 => array(
				'type'    => 'int',
				'default' => 360,
				'min'     => 280,
				'max'     => 640,
			),
			'login.form_padding'               => array(
				'type'    => 'int',
				'default' => 28,
				'min'     => 0,
				'max'     => 80,
			),
			'login.form_radius'                => $px,
			'login.form_bg'                    => $color,
			'login.form_shadow'                => $on,
			'login.field_style'                => array(
				'type'    => 'select',
				'default' => 'boxed',
				'choices' => array( 'boxed', 'underline', 'filled' ),
			),
			'login.hide_labels'                => $off,
			'login.text_color'                 => $color,
			'login.link_color'                 => $color,
			'login.button_bg'                  => $color,
			'login.button_text'                => $color,
			'login.button_radius'              => $px,
			'login.message'                    => array(
				'type'    => 'html',
				'default' => '',
			),
			'login.footer_text'                => array(
				'type'    => 'html',
				'default' => '',
			),
			'login.hide_lost_password'         => $off,
			'login.hide_back_to_site'          => $off,
			'login.hide_language_switcher'     => $off,
			'login.hide_privacy_link'          => $off,
			'login.links_layout'               => array(
				'type'    => 'select',
				'default' => 'stacked',
				'choices' => array( 'stacked', 'inline', 'split', 'card', 'buttons' ),
			),
			'login.link_login'                 => $link_place,
			'login.link_register'              => $link_place,
			'login.link_lost'                  => $link_place,
			'login.link_back'                  => $link_place,
			'login.back_link_text'             => array(
				'type'    => 'text',
				'default' => '',
			),
			'login.error_message'              => array(
				'type'    => 'text',
				'default' => '',
			),

			// Email design.
			'emails.show_logo'                 => $on,
			'emails.logo'                      => $url,
			'emails.logo_width'                => $px,
			'emails.header_bg'                 => $color,
			'emails.header_text'               => $color,
			'emails.body_bg'                   => $color,
			'emails.content_bg'                => $color,
			'emails.text_color'                => $color,
			'emails.button_bg'                 => $color,
			'emails.button_text'               => $color,
			'emails.font'                      => array(
				'type'    => 'select',
				'default' => 'brand',
				'choices' => array( 'brand', 'system', 'arial', 'helvetica', 'georgia', 'verdana', 'trebuchet' ),
			),
			'emails.width'                     => array(
				'type'    => 'int',
				'default' => 600,
				'min'     => 480,
				'max'     => 800,
			),
			'emails.radius'                    => $px,
			'emails.footer_text'               => $html,

			// Error and system pages.
			'error_pages.critical'             => $on,
			'error_pages.db'                   => $on,
			'error_pages.maintenance'          => $on,
			'error_pages.wp_die'               => $on,
			'error_pages.notfound'             => $off,
			'error_pages.bg_color'             => $color,
			'error_pages.text_color'           => $color,
			'error_pages.primary'              => $color,
			'error_pages.surface'              => $color,
			'error_pages.show_logo'            => $on,
			'error_pages.image'                => $url,
			'error_pages.critical_title'       => $text,
			'error_pages.critical_message'     => $html,
			'error_pages.critical_button'      => $text,
			'error_pages.critical_button_url'  => $url,
			'error_pages.critical_admin_note'  => $text,
			'error_pages.db_title'             => $text,
			'error_pages.db_message'           => $html,
			'error_pages.maintenance_title'    => $text,
			'error_pages.maintenance_message'  => $html,
			'error_pages.wp_die_title'         => $text,
			'error_pages.wp_die_button'        => $text,
			'error_pages.notfound_title'       => $text,
			'error_pages.notfound_message'     => $html,
			'error_pages.notfound_button'      => $text,
			'error_pages.notfound_search'      => $on,
			'error_pages.denied'               => $on,
			'error_pages.denied_title'         => $text,
			'error_pages.denied_message'       => $html,
			'error_pages.denied_button'        => $text,
			'error_pages.denied_button_url'    => $url,
			'error_pages.expired_title'        => $text,
			'error_pages.expired_message'      => $html,

			// Sessions.
			'sessions.limit'                   => array(
				'type'    => 'int',
				'default' => 0,
				'min'     => 0,
				'max'     => 50,
			),
			'sessions.limit_mode'              => array(
				'type'    => 'select',
				'default' => 'oldest',
				'choices' => array( 'oldest', 'block' ),
			),
			'sessions.limit_roles'             => array(
				'type'    => 'roles',
				'default' => array(),
			),
			'sessions.exempt_admins'           => $on,
			'sessions.history'                 => $on,
			'sessions.history_failed'          => $on,

			// Tab and page details.
			'tab_meta.admin_title'             => array(
				'type'    => 'text',
				'default' => '{page} ‹ {site_name}',
			),
			'tab_meta.login_title'             => array(
				'type'    => 'text',
				'default' => '{page} ‹ {site_name}',
			),
			'tab_meta.system_title'            => array(
				'type'    => 'text',
				'default' => '{page} | {site_name}',
			),
			'tab_meta.front_separator'         => array(
				'type'    => 'text',
				'default' => '',
			),
			'tab_meta.front_404_title'         => array(
				'type'    => 'text',
				'default' => '',
			),
			'tab_meta.front_search_title'      => array(
				'type'    => 'text',
				'default' => '',
			),
			'tab_meta.hide_home_tagline'       => $off,
			'tab_meta.favicon'                 => $on,
			'tab_meta.favicon_image'           => $url,
			'tab_meta.favicon_ico'             => $on,
			'tab_meta.theme_color'             => $off,
			'tab_meta.hide_generator'          => $off,
			'tab_meta.hide_versions'           => $off,
			'tab_meta.hide_rsd_wlw'            => $off,
			'tab_meta.hide_login_wp_link'      => $on,
			'tab_meta.hide_rest_links'         => $off,
			'tab_meta.hide_emoji'              => $off,
			'tab_meta.disable_xmlrpc'          => $off,
			'tab_meta.block_user_lookup'       => $off,

			// Admin branding.
			'admin_branding.adminbar_logo'     => array(
				'type'    => 'select',
				'default' => 'keep',
				'choices' => array( 'keep', 'replace', 'remove' ),
			),
			'admin_branding.adminbar_image'    => $url,
			'admin_branding.adminbar_link'     => $url,
			'admin_branding.footer_left'       => array(
				'type'    => 'select',
				'default' => 'keep',
				'choices' => array( 'keep', 'custom', 'hide' ),
			),
			'admin_branding.footer_left_text'  => $html,
			'admin_branding.footer_right'      => array(
				'type'    => 'select',
				'default' => 'keep',
				'choices' => array( 'keep', 'custom', 'hide' ),
			),
			'admin_branding.footer_right_text' => array(
				'type'    => 'html',
				'default' => '',
			),
			'admin_branding.color_scheme'      => $off,
			'admin_branding.default_scheme'    => $off,
			'admin_branding.hide_howdy'        => $off,
			'admin_branding.hide_help'         => $off,
			'admin_branding.hide_screen_opts'  => $off,

			// Security.
			'security.login_url'               => $off,
			'security.login_slug'              => array(
				'type'    => 'slug',
				'default' => '',
			),
			'security.blocked_action'          => array(
				'type'    => 'select',
				'default' => '404',
				'choices' => array( '404', 'home', 'custom' ),
			),
			'security.blocked_url'             => $url,
			'security.admin_url'               => $off,
			'security.admin_slug'              => array(
				'type'    => 'slug',
				'default' => '',
			),
			'security.limit'                   => $off,
			'security.max_attempts'            => array(
				'type'    => 'int',
				'default' => 5,
				'min'     => 1,
				'max'     => 50,
			),
			'security.window'                  => array(
				'type'    => 'int',
				'default' => 15,
				'min'     => 1,
				'max'     => 1440,
			),
			'security.lockout'                 => array(
				'type'    => 'int',
				'default' => 30,
				'min'     => 1,
				'max'     => 10080,
			),
			'security.allowlist'               => array(
				'type'    => 'lines',
				'default' => '',
			),
			'security.trust_proxy'             => $off,
			'security.honeypot'                => $off,
			'security.twofa'                   => $off,
			'security.twofa_email'             => $on,
			'security.twofa_roles'             => array(
				'type'    => 'roles',
				'default' => array(),
			),
			'security.twofa_grace'             => array(
				'type'    => 'int',
				'default' => 7,
				'min'     => 0,
				'max'     => 90,
			),

			// Coming soon / maintenance mode.
			'maintenance.mode'                 => array(
				'type'    => 'select',
				'default' => 'coming_soon',
				'choices' => array( 'coming_soon', 'maintenance' ),
			),
			'maintenance.roles'                => array(
				'type'    => 'roles',
				'default' => array( 'administrator' ),
			),
			'maintenance.allowed_paths'        => array(
				'type'    => 'lines',
				'default' => '',
			),
			'maintenance.bypass_key'           => array(
				'type'    => 'slug',
				'default' => '',
			),
			'maintenance.design'               => array(
				'type'    => 'select',
				'default' => 'customizer',
				'choices' => array( 'customizer', 'page' ),
			),
			'maintenance.page'                 => array(
				'type'    => 'int',
				'default' => 0,
				'min'     => 0,
			),
			'maintenance.headline'             => $text,
			'maintenance.message'              => $html,
			'maintenance.countdown'            => array(
				'type'    => 'datetime',
				'default' => '',
			),
			'maintenance.show_logo'            => $on,
			'maintenance.bg_color'             => $color,
			'maintenance.bg_image'             => $url,
			'maintenance.overlay_opacity'      => array(
				'type'    => 'int',
				'default' => 40,
				'min'     => 0,
				'max'     => 100,
			),
			'maintenance.text_color'           => $color,
		);

		foreach ( Modules\Emails\Templates::keys() as $key ) {
			$prefix                        = 'emails.templates.' . $key . '.';
			$fields[ $prefix . 'enabled' ] = $on;
			$fields[ $prefix . 'subject' ] = $text;
			$fields[ $prefix . 'heading' ] = $text;
			$fields[ $prefix . 'body' ]    = $html;
			$fields[ $prefix . 'button' ]  = $text;
		}

		foreach ( Modules\Login\Login_Text::catalogue() as $key => $entry ) {
			$fields[ 'login_words.' . $key ] = array(
				'type'    => $entry[1],
				'default' => '',
			);
		}

		self::$fields = $fields;

		return self::$fields;
	}

	/**
	 * Returns one field definition, or null for an unknown key.
	 *
	 * @param string $key Dot path.
	 * @return array|null
	 */
	public static function field( $key ) {
		$fields = self::fields();

		return isset( $fields[ $key ] ) ? $fields[ $key ] : null;
	}

	/**
	 * Returns the default value for a key.
	 *
	 * @param string $key Dot path.
	 * @return mixed
	 */
	public static function default_for( $key ) {
		$field = self::field( $key );

		if ( ! $field ) {
			return null;
		}

		if ( ! empty( $field['i18n'] ) ) {
			return Defaults::text( $key );
		}

		return isset( $field['default'] ) ? $field['default'] : '';
	}

	/**
	 * Returns the raw stored option.
	 *
	 * @return array
	 */
	public static function raw() {
		$raw = get_option( self::OPTION, array() );

		return is_array( $raw ) ? $raw : array();
	}

	/**
	 * Reads a setting, falling back to its default.
	 *
	 * @param string $key Dot path.
	 * @return mixed
	 */
	public static function get( $key ) {
		$value = self::raw();

		foreach ( explode( '.', $key ) as $segment ) {
			if ( ! is_array( $value ) || ! array_key_exists( $segment, $value ) ) {
				return self::default_for( $key );
			}
			$value = $value[ $segment ];
		}

		return $value;
	}

	/**
	 * Whether a module is switched on.
	 *
	 * @param string $module Module key.
	 * @return bool
	 */
	public static function module_on( $module ) {
		return (bool) self::get( 'modules.' . $module );
	}

	/**
	 * Sanitizes and saves a flat map of dot path => value.
	 *
	 * Unknown keys are ignored. Keys present in the map but missing from the
	 * submitted data should be passed explicitly (unchecked boxes as false).
	 *
	 * @param array $values Flat map.
	 * @return void
	 */
	public static function update( array $values ) {
		$raw = self::raw();

		foreach ( $values as $key => $value ) {
			$field = self::field( $key );

			if ( ! $field ) {
				continue;
			}

			self::set_path( $raw, $key, self::sanitize( $field, $value ) );
		}

		update_option( self::OPTION, $raw );
	}

	/**
	 * Removes stored values under a prefix so they fall back to defaults.
	 *
	 * @param string $prefix Top-level group such as `login`, or '' for everything.
	 * @return void
	 */
	public static function reset( $prefix = '' ) {
		if ( '' === $prefix ) {
			update_option( self::OPTION, array() );
			return;
		}

		$raw = self::raw();
		unset( $raw[ $prefix ] );
		update_option( self::OPTION, $raw );
	}

	/**
	 * Replaces all settings with an imported nested array, sanitizing each known field.
	 *
	 * @param array $nested Nested settings.
	 * @return int Number of fields imported.
	 */
	public static function import( array $nested ) {
		$clean = array();
		$count = 0;

		foreach ( self::fields() as $key => $field ) {
			$value = $nested;
			$found = true;

			foreach ( explode( '.', $key ) as $segment ) {
				if ( ! is_array( $value ) || ! array_key_exists( $segment, $value ) ) {
					$found = false;
					break;
				}
				$value = $value[ $segment ];
			}

			if ( $found ) {
				self::set_path( $clean, $key, self::sanitize( $field, $value ) );
				++$count;
			}
		}

		update_option( self::OPTION, $clean );

		return $count;
	}

	/**
	 * Sanitizes one value against its field definition.
	 *
	 * @param array $field Field definition.
	 * @param mixed $value Raw value.
	 * @return mixed
	 */
	public static function sanitize( array $field, $value ) {
		switch ( $field['type'] ) {
			case 'bool':
				return ! empty( $value ) && 'false' !== $value;

			case 'int':
				$value = (int) $value;
				if ( isset( $field['min'] ) ) {
					$value = max( $field['min'], $value );
				}
				if ( isset( $field['max'] ) ) {
					$value = min( $field['max'], $value );
				}
				return $value;

			case 'int_optional':
				if ( '' === $value || null === $value ) {
					return '';
				}
				return self::sanitize( array_merge( $field, array( 'type' => 'int' ) ), $value );

			case 'color':
				$value = is_string( $value ) ? trim( $value ) : '';
				return (string) sanitize_hex_color( $value );

			case 'url':
				return esc_url_raw( is_string( $value ) ? trim( $value ) : '' );

			case 'select':
				$value = is_scalar( $value ) ? (string) $value : '';
				return in_array( $value, $field['choices'], true ) ? $value : $field['default'];

			case 'multi':
				$value = is_array( $value ) ? array_map( 'strval', $value ) : array();
				return array_values( array_intersect( $value, $field['choices'] ) );

			case 'roles':
				$value = is_array( $value ) ? array_map( 'sanitize_key', $value ) : array();
				$roles = array_keys( wp_roles()->roles );
				return array_values( array_intersect( $value, $roles ) );

			case 'html':
				return wp_kses_post( is_string( $value ) ? $value : '' );

			case 'slug':
				return sanitize_title( is_string( $value ) ? $value : '' );

			case 'lines':
				return sanitize_textarea_field( is_string( $value ) ? $value : '' );

			case 'datetime':
				$value = is_string( $value ) ? trim( $value ) : '';
				return preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $value ) ? $value : '';

			case 'text':
			default:
				return sanitize_text_field( is_scalar( $value ) ? (string) $value : '' );
		}
	}

	/**
	 * Writes a value into a nested array by dot path.
	 *
	 * @param array  $data  Target array, by reference.
	 * @param string $key   Dot path.
	 * @param mixed  $value Value.
	 * @return void
	 */
	private static function set_path( array &$data, $key, $value ) {
		$ref = &$data;

		foreach ( explode( '.', $key ) as $segment ) {
			if ( ! isset( $ref[ $segment ] ) || ! is_array( $ref[ $segment ] ) ) {
				$ref[ $segment ] = array();
			}
			$ref = &$ref[ $segment ];
		}

		$ref = $value;
	}

	/**
	 * Converts a dot path into a Customizer setting id.
	 *
	 * @param string $key Dot path.
	 * @return string
	 */
	public static function customizer_id( $key ) {
		return self::OPTION . '[' . implode( '][', explode( '.', $key ) ) . ']';
	}
}

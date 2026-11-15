<?php
/**
 * Readers for settings saved by other plugins.
 *
 * Each reader looks only at the other plugin's saved options, in the format
 * that plugin writes them, and turns what it finds into this plugin's terms.
 * Nothing here changes the other plugin's data.
 *
 * Every reader returns any of these keys:
 * - login         { slug, blocked_action, blocked_url }
 * - admin         { slug }
 * - limits        { max_attempts, window, lockout } in attempts and minutes
 * - menu          role => menu ids to hide ("slug" or "parent>slug")
 * - toolbar_items role => toolbar node ids to hide
 * - toolbar_front roles that do not see the toolbar on the site
 * - toolbar_admin roles that do not see the toolbar in the dashboard
 * - redirect      role => login destination token
 * - widgets_hide  dashboard widget id => roles that do not see it
 * - widgets_new   custom widgets to add
 * - notes         settings that could not be brought across, and why
 *
 * @package LayoDT
 */

namespace LayoDT\Importers;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin readers.
 */
class Sources {

	/**
	 * Every supported plugin: id => [ name, plugin files, reader ].
	 *
	 * @return array
	 */
	public static function all() {
		return array(
			'wps_hide_login' => array( 'WPS Hide Login', array( 'wps-hide-login/wps-hide-login.php' ), 'wps_hide_login' ),
			'loginizer'      => array( 'Loginizer', array( 'loginizer/loginizer.php', 'loginizer-security/loginizer-security.php' ), 'loginizer' ),
			'aios'           => array( 'All-In-One Security', array( 'all-in-one-wp-security-and-firewall/wp-security.php' ), 'aios' ),
			'solid'          => array( 'Solid Security', array( 'better-wp-security/better-wp-security.php', 'ithemes-security-pro/ithemes-security-pro.php' ), 'solid' ),
			'wp_hide'        => array( 'WP Hide & Security Enhancer', array( 'wp-hide-security-enhancer/wp-hide.php', 'wp-hide-security-enhancer-pro/wp-hide.php' ), 'wp_hide' ),
			'defender'       => array( 'Defender', array( 'defender-security/wp-defender.php', 'wp-defender/wp-defender.php' ), 'defender' ),
			'ase'            => array( 'Admin and Site Enhancements', array( 'admin-site-enhancements/admin-site-enhancements.php', 'admin-site-enhancements-pro/admin-site-enhancements.php' ), 'ase' ),
			'adminimize'     => array( 'Adminimize', array( 'adminimize/adminimize.php' ), 'adminimize' ),
			'ame'            => array( 'Admin Menu Editor', array( 'admin-menu-editor/menu-editor.php', 'admin-menu-editor-pro/menu-editor.php' ), 'ame' ),
			'ure'            => array( 'User Role Editor Pro', array( 'user-role-editor-pro/user-role-editor-pro.php' ), 'ure' ),
			'hab'            => array( 'Hide Admin Bar Based on User Roles', array( 'hide-admin-bar-based-on-user-roles/hide-admin-bar-based-on-user-roles.php' ), 'hab' ),
			'dwe'            => array( 'Dashboard Welcome for Elementor', array( 'dashboard-welcome-for-elementor/dashboard-welcome-elementor.php' ), 'dwe' ),
		);
	}

	/**
	 * Reads one plugin's settings.
	 *
	 * @param string $id Source id.
	 * @return array
	 */
	public static function read( $id ) {
		$all = self::all();

		if ( ! isset( $all[ $id ] ) ) {
			return array();
		}

		$data = call_user_func( array( __CLASS__, $all[ $id ][2] ) );

		return self::tidy( is_array( $data ) ? $data : array() );
	}

	// ------------------------------------------------------------------
	// Login protection plugins.
	// ------------------------------------------------------------------

	/**
	 * WPS Hide Login: `whl_page` is the login slug, `whl_redirect_admin` where
	 * wp-admin and wp-login.php send visitors ("404" or a slug).
	 *
	 * @return array
	 */
	private static function wps_hide_login() {
		$slug = (string) self::option( 'whl_page' );

		if ( '' === $slug ) {
			return array();
		}

		$data = array( 'login' => array( 'slug' => $slug ) + self::blocked_to( (string) self::option( 'whl_redirect_admin' ) ) );

		return $data;
	}

	/**
	 * Loginizer: the login and dashboard slugs (set with Loginizer Security)
	 * and the brute force limits, saved in seconds.
	 *
	 * @return array
	 */
	private static function loginizer() {
		$data     = array();
		$security = self::option( 'loginizer_security' );
		$wp_admin = self::option( 'loginizer_wp_admin' );
		$options  = self::option( 'loginizer_options' );

		if ( is_array( $security ) && ! empty( $security['login_slug'] ) ) {
			$data['login'] = array( 'slug' => (string) $security['login_slug'] );

			if ( ! empty( $security['rename_login_secret'] ) ) {
				$data['notes'][] = __( 'Loginizer\'s "secret login" option has no equivalent here: the new address always works as the login page.', 'layo-dashboard-toolkit' );
			}
		}

		if ( is_array( $wp_admin ) && ! empty( $wp_admin['admin_slug'] ) ) {
			$data['admin'] = array( 'slug' => (string) $wp_admin['admin_slug'] );
		}

		// Loginizer always limits attempts; these are its defaults when nothing is saved.
		if ( is_array( $options ) || self::any_active( 'loginizer' ) ) {
			$options        = is_array( $options ) ? $options : array();
			$data['limits'] = array(
				'max_attempts' => empty( $options['max_retries'] ) ? 3 : (int) $options['max_retries'],
				'lockout'      => (int) ceil( ( empty( $options['lockout_time'] ) ? 900 : (int) $options['lockout_time'] ) / 60 ),
				'window'       => (int) ceil( ( empty( $options['reset_retries'] ) ? 86400 : (int) $options['reset_retries'] ) / 60 ),
			);
		}

		return $data;
	}

	/**
	 * All-In-One Security: everything is in `aio_wp_security_configs`.
	 * Times are in minutes.
	 *
	 * @return array
	 */
	private static function aios() {
		$config = self::option( 'aio_wp_security_configs' );

		if ( ! is_array( $config ) ) {
			return array();
		}

		$data = array();

		if ( isset( $config['aiowps_enable_rename_login_page'] ) && '1' === (string) $config['aiowps_enable_rename_login_page'] && ! empty( $config['aiowps_login_page_slug'] ) ) {
			$data['login'] = array( 'slug' => (string) $config['aiowps_login_page_slug'] );
		}

		if ( isset( $config['aiowps_enable_login_lockdown'] ) && '1' === (string) $config['aiowps_enable_login_lockdown'] ) {
			$data['limits'] = array(
				'max_attempts' => isset( $config['aiowps_max_login_attempts'] ) ? (int) $config['aiowps_max_login_attempts'] : 3,
				'window'       => isset( $config['aiowps_retry_time_period'] ) ? (int) $config['aiowps_retry_time_period'] : 5,
				'lockout'      => isset( $config['aiowps_lockout_time_length'] ) ? (int) $config['aiowps_lockout_time_length'] : 5,
			);
		}

		return $data;
	}

	/**
	 * Solid Security (formerly iThemes Security, also sold as Kadence
	 * Security): modules are kept in `itsec-storage`, with minutes for times.
	 *
	 * @return array
	 */
	private static function solid() {
		$storage = self::option( 'itsec-storage' );

		if ( ! is_array( $storage ) ) {
			return array();
		}

		$data   = array();
		$hidden = isset( $storage['hide-backend'] ) && is_array( $storage['hide-backend'] ) ? $storage['hide-backend'] : array();

		if ( ! empty( $hidden['enabled'] ) && ! empty( $hidden['slug'] ) ) {
			$data['login'] = array( 'slug' => (string) $hidden['slug'] );
		}

		$active = self::option( 'itsec_active_modules' );

		if ( is_array( $active ) && ! empty( $active['brute-force'] ) ) {
			$brute  = isset( $storage['brute-force'] ) && is_array( $storage['brute-force'] ) ? $storage['brute-force'] : array();
			$global = isset( $storage['global'] ) && is_array( $storage['global'] ) ? $storage['global'] : array();
			$max    = isset( $brute['max_attempts_host'] ) ? (int) $brute['max_attempts_host'] : 5;

			if ( $max > 0 ) {
				$data['limits'] = array(
					'max_attempts' => $max,
					'window'       => isset( $brute['check_period'] ) ? (int) $brute['check_period'] : 5,
					'lockout'      => isset( $global['lockout_period'] ) ? (int) $global['lockout_period'] : 15,
				);
			}
		}

		return $data;
	}

	/**
	 * WP Hide & Security Enhancer: `wph_settings` holds a value per component.
	 *
	 * @return array
	 */
	private static function wp_hide() {
		$settings = self::option( 'wph_settings' );
		$values   = is_array( $settings ) && isset( $settings['module_settings'] ) && is_array( $settings['module_settings'] ) ? $settings['module_settings'] : array();
		$data     = array();

		if ( ! empty( $values['new_wp_login_php'] ) ) {
			$data['login'] = array( 'slug' => (string) $values['new_wp_login_php'] );
		}

		if ( ! empty( $values['admin_url'] ) ) {
			$data['admin'] = array( 'slug' => (string) $values['admin_url'] );
		}

		return $data;
	}

	/**
	 * Defender: settings are JSON strings in network options.
	 *
	 * @return array
	 */
	private static function defender() {
		$mask    = self::json( get_site_option( 'wd_masking_login_settings' ) );
		$lockout = self::json( get_site_option( 'wd_login_lockout_settings' ) );
		$data    = array();

		if ( ! empty( $mask['enabled'] ) && ! empty( $mask['mask_url'] ) ) {
			$data['login'] = array( 'slug' => (string) $mask['mask_url'] );

			if ( isset( $mask['redirect_traffic'] ) && 'on' === $mask['redirect_traffic'] && ! empty( $mask['redirect_traffic_url'] ) ) {
				$data['login']['blocked_action'] = 'custom';
				$data['login']['blocked_url']    = (string) $mask['redirect_traffic_url'];
			}
		}

		if ( ! empty( $lockout['enabled'] ) ) {
			$units    = array(
				'seconds' => 1 / 60,
				'minutes' => 1,
				'hours'   => 60,
				'days'    => 1440,
			);
			$unit     = isset( $lockout['duration_unit'], $units[ $lockout['duration_unit'] ] ) ? $units[ $lockout['duration_unit'] ] : 1 / 60;
			$duration = isset( $lockout['lockout_type'] ) && 'permanent' === $lockout['lockout_type'] ? 10080 : (int) ceil( ( isset( $lockout['duration'] ) ? (int) $lockout['duration'] : 300 ) * $unit );

			$data['limits'] = array(
				'max_attempts' => isset( $lockout['attempt'] ) ? (int) $lockout['attempt'] : 5,
				'window'       => (int) ceil( ( isset( $lockout['timeframe'] ) ? (int) $lockout['timeframe'] : 300 ) / 60 ),
				'lockout'      => $duration,
			);

			if ( isset( $lockout['lockout_type'] ) && 'permanent' === $lockout['lockout_type'] ) {
				$data['notes'][] = __( 'Defender bans addresses permanently. Here a lockout lasts at most a week, so that is what was set.', 'layo-dashboard-toolkit' );
			}
		}

		return $data;
	}

	// ------------------------------------------------------------------
	// Admin tidy-up plugins.
	// ------------------------------------------------------------------

	/**
	 * Admin and Site Enhancements: one option, `admin_site_enhancements`.
	 *
	 * @return array
	 */
	private static function ase() {
		$options = self::option( 'admin_site_enhancements' );

		if ( ! is_array( $options ) ) {
			return array();
		}

		$data  = array();
		$roles = self::roles();

		if ( ! empty( $options['change_login_url'] ) && ! empty( $options['custom_login_slug'] ) ) {
			$data['login'] = array( 'slug' => (string) $options['custom_login_slug'] );
		}

		if ( ! empty( $options['limit_login_attempts'] ) ) {
			$data['limits'] = array(
				'max_attempts' => ! empty( $options['login_fails_allowed'] ) ? (int) $options['login_fails_allowed'] : 3,
				'lockout'      => 15,
			);
		}

		if ( ! empty( $options['redirect_after_login'] ) && ! empty( $options['redirect_after_login_for'] ) && is_array( $options['redirect_after_login_for'] ) ) {
			$path = isset( $options['redirect_after_login_to_slug'] ) ? trim( trim( (string) $options['redirect_after_login_to_slug'] ), '/' ) : '';

			if ( '' !== $path && ! preg_match( '/[#?]|\.php|\.html/', $path ) ) {
				$path .= '/';
			}

			foreach ( $options['redirect_after_login_for'] as $role => $on ) {
				if ( $on && in_array( $role, $roles, true ) ) {
					$data['redirect'][ $role ] = 'url:' . home_url( '/' . $path );
				}
			}
		}

		if ( ! empty( $options['hide_admin_bar'] ) && ! empty( $options['hide_admin_bar_for'] ) && is_array( $options['hide_admin_bar_for'] ) ) {
			foreach ( $options['hide_admin_bar_for'] as $role => $on ) {
				if ( $on && in_array( $role, $roles, true ) ) {
					$data['toolbar_front'][] = $role;
				}
			}
		}

		$toolbar = array(
			'hide_ab_wp_logo_menu'     => 'wp-logo',
			'hide_ab_site_menu'        => 'site-name',
			'hide_ab_customize_menu'   => 'customize',
			'hide_ab_updates_menu'     => 'updates',
			'hide_ab_comments_menu'    => 'comments',
			'hide_ab_new_content_menu' => 'new-content',
		);

		if ( ! empty( $options['cleanup_admin_bar'] ) ) {
			foreach ( $toolbar as $key => $node ) {
				if ( ! empty( $options[ $key ] ) ) {
					foreach ( $roles as $role ) {
						$data['toolbar_items'][ $role ][] = $node;
					}
				}
			}
		}

		$extra = self::option( 'admin_site_enhancements_extra' );

		if ( ! empty( $options['customize_admin_menu'] ) && is_array( $extra ) && ! empty( $extra['admin_menu']['custom_menu_hidden'] ) ) {
			$data['notes'][] = __( 'Admin and Site Enhancements tucks some menu items behind a "Show all" toggle for everyone. That is not a permission, so it was not imported. Hide items per role under Roles & permissions → Menu access.', 'layo-dashboard-toolkit' );
		}

		return $data;
	}

	/**
	 * Adminimize: one option, `mw_adminimize`, with a list per role for each
	 * kind of item. Submenu entries are either the page slug or
	 * "parent__position".
	 *
	 * @return array
	 */
	private static function adminimize() {
		$options = self::option( 'mw_adminimize' );

		if ( ! is_array( $options ) ) {
			return array();
		}

		$data    = array();
		$missed  = 0;
		$submenu = self::submenus( isset( $options['mw_adminimize_default_submenu'] ) ? $options['mw_adminimize_default_submenu'] : array() );

		foreach ( self::roles() as $role ) {
			foreach ( self::lst( $options, 'mw_adminimize_disabled_menu_' . $role . '_items' ) as $slug ) {
				$data['menu'][ $role ][] = (string) $slug;
			}

			foreach ( self::lst( $options, 'mw_adminimize_disabled_submenu_' . $role . '_items' ) as $entry ) {
				$id = self::submenu_id( (string) $entry, $submenu );
				if ( '' !== $id ) {
					$data['menu'][ $role ][] = $id;
				} else {
					++$missed;
				}
			}

			$nodes = array_merge(
				self::lst( $options, 'mw_adminimize_disabled_admin_bar_' . $role . '_items' ),
				self::lst( $options, 'mw_adminimize_disabled_admin_bar_frontend_' . $role . '_items' )
			);

			foreach ( $nodes as $node ) {
				$data['toolbar_items'][ $role ][] = (string) $node;
			}

			foreach ( self::lst( $options, 'mw_adminimize_disabled_dashboard_option_' . $role . '_items' ) as $widget ) {
				$data['widgets_hide'][ (string) $widget ][] = $role;
			}
		}

		if ( $missed ) {
			$data['notes'][] = sprintf(
				/* translators: %d: number of submenu items. */
				_n( '%d hidden submenu item belongs to a menu this site no longer has, so it was skipped.', '%d hidden submenu items belong to menus this site no longer has, so they were skipped.', $missed, 'layo-dashboard-toolkit' ),
				$missed
			);
		}

		return $data;
	}

	/**
	 * Admin Menu Editor and Admin Menu Editor Pro: menu access in the menu
	 * configuration, plus the Pro modules for toolbar visibility, dashboard
	 * widgets and login redirects.
	 *
	 * @return array
	 */
	private static function ame() {
		$data = array();

		foreach ( array( 'ws_menu_editor_pro', 'ws_menu_editor' ) as $name ) {
			$options = self::ame_options( $name );

			if ( ! empty( $options['custom_menu'] ) && is_array( $options['custom_menu'] ) ) {
				$data = self::merge( $data, self::ame_menu( $options['custom_menu'] ) );
				break;
			}
		}

		$data = self::merge( $data, self::ame_widgets() );

		return self::merge( $data, self::ame_redirects() );
	}

	/**
	 * User Role Editor Pro: blocked admin menu items and dashboard widgets per
	 * role, stored as hashes of each item's link or meta box.
	 *
	 * @return array
	 */
	private static function ure() {
		$data = array();

		$access = self::option( 'ure_admin_menu_access_data' );

		if ( is_array( $access ) ) {
			$items = self::ure_items();

			foreach ( $access as $role => $rule ) {
				if ( ! in_array( $role, self::roles(), true ) || ! is_array( $rule ) ) {
					continue;
				}

				// Old data had no model and meant "block the selected items".
				$model  = isset( $rule['access_model'] ) ? (int) $rule['access_model'] : 1;
				$hashes = isset( $rule['access_model'] ) ? ( isset( $rule['data'] ) ? (array) $rule['data'] : array() ) : $rule;

				foreach ( $items as $id => $item_hashes ) {
					$listed = (bool) array_intersect( $item_hashes, $hashes );
					if ( ( 1 === $model && $listed ) || ( 2 === $model && ! $listed ) ) {
						$data['menu'][ $role ][] = $id;
					}
				}
			}

			if ( ! $items ) {
				$data['notes'][] = __( 'User Role Editor Pro has not saved its copy of the admin menu yet, so its menu rules could not be read. Open Users → User Role Editor once, then import again.', 'layo-dashboard-toolkit' );
			}
		}

		$boxes = self::option( 'ure_meta_boxes_access_data' );

		if ( is_array( $boxes ) ) {
			$known = self::ure_dashboard_boxes();

			foreach ( $boxes as $role => $hashes ) {
				if ( ! in_array( $role, self::roles(), true ) ) {
					continue;
				}
				foreach ( (array) $hashes as $hash ) {
					if ( isset( $known[ $hash ] ) ) {
						$data['widgets_hide'][ $known[ $hash ] ][] = $role;
					}
				}
			}
		}

		return $data;
	}

	/**
	 * Hide Admin Bar Based on User Roles: `hab_settings`.
	 *
	 * @return array
	 */
	private static function hab() {
		$settings = self::option( 'hab_settings' );

		if ( ! is_array( $settings ) ) {
			return array();
		}

		$data  = array();
		$roles = self::roles();

		if ( isset( $settings['hab_disableforall'] ) && 'yes' === $settings['hab_disableforall'] ) {
			$data['toolbar_front'] = $roles;
		} elseif ( ! empty( $settings['hab_userRoles'] ) && is_array( $settings['hab_userRoles'] ) ) {
			$data['toolbar_front'] = array_values( array_intersect( array_map( 'strval', $settings['hab_userRoles'] ), $roles ) );
		}

		if ( ! empty( $settings['hab_capabilities'] ) && is_string( $settings['hab_capabilities'] ) && '' !== trim( $settings['hab_capabilities'] ) ) {
			$data['notes'][] = __( 'Rules based on capabilities were not imported, because the toolbar is set per role here.', 'layo-dashboard-toolkit' );
		}

		return $data;
	}

	/**
	 * Dashboard Welcome for Elementor: `_dwe_templates`, role => template.
	 * Each template becomes a dashboard widget for the roles that used it,
	 * replacing the welcome panel for them as before.
	 *
	 * @return array
	 */
	private static function dwe() {
		$settings = self::option( '_dwe_templates' );

		if ( ! is_array( $settings ) ) {
			return array();
		}

		$by_template = array();

		foreach ( $settings as $role => $choice ) {
			$template = is_array( $choice ) && ! empty( $choice['template'] ) ? absint( $choice['template'] ) : 0;
			if ( $template && in_array( $role, self::roles(), true ) && get_post( $template ) ) {
				$by_template[ $template ][] = $role;
			}
		}

		$data = array();

		foreach ( $by_template as $template => $roles ) {
			$data['widgets_new'][] = array(
				'type'   => 'template',
				'title'  => get_the_title( $template ),
				'page'   => $template,
				'roles'  => $roles,
				'column' => 'normal',
			);

			foreach ( $roles as $role ) {
				$data['widgets_hide']['welcome_panel'][] = $role;
			}
		}

		return $data;
	}

	// ------------------------------------------------------------------
	// Admin Menu Editor details.
	// ------------------------------------------------------------------

	/**
	 * Admin Menu Editor's options, which may be compressed.
	 *
	 * @param string $name Option name.
	 * @return array
	 */
	private static function ame_options( $name ) {
		$options = self::option( $name );

		if ( is_string( $options ) && 0 === strpos( $options, 'gzcompress:' ) && function_exists( 'gzuncompress' ) ) {
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Reading Admin Menu Editor's own stored format.
			$raw     = gzuncompress( base64_decode( substr( $options, strlen( 'gzcompress:' ) ) ) );
			$options = is_string( $raw ) ? maybe_unserialize( $raw ) : array();
		}

		return is_array( $options ) ? $options : array();
	}

	/**
	 * Hidden menu items and toolbar visibility from a menu configuration.
	 *
	 * @param array $config Admin Menu Editor menu configuration.
	 * @return array
	 */
	private static function ame_menu( array $config ) {
		$data   = array();
		$common = isset( $config['format']['compressed'], $config['format']['common']['properties'] ) && ! empty( $config['format']['compressed'] ) ? (array) $config['format']['common']['properties'] : array();
		$custom = 0;

		foreach ( isset( $config['tree'] ) ? (array) $config['tree'] : array() as $item ) {
			self::ame_item( (array) $item, $common, $data, $custom );
		}

		if ( $custom ) {
			$data['notes'][] = sprintf(
				/* translators: %d: number of menu items. */
				_n( '%d menu item you created in Admin Menu Editor was skipped, because only existing menu items can be hidden here.', '%d menu items you created in Admin Menu Editor were skipped, because only existing menu items can be hidden here.', $custom, 'layo-dashboard-toolkit' ),
				$custom
			);
		}

		$visibility = isset( $config['component_visibility'] ) && is_array( $config['component_visibility'] ) ? $config['component_visibility'] : array();
		$front      = isset( $visibility['toolbarFrontend'] ) ? $visibility['toolbarFrontend'] : ( isset( $visibility['toolbar'] ) ? $visibility['toolbar'] : array() );
		$admin      = isset( $visibility['toolbarDashboard'] ) ? $visibility['toolbarDashboard'] : ( isset( $visibility['toolbar'] ) ? $visibility['toolbar'] : array() );

		foreach ( self::denied_roles( (array) $front ) as $role ) {
			$data['toolbar_front'][] = $role;
		}

		foreach ( self::denied_roles( (array) $admin ) as $role ) {
			$data['toolbar_admin'][] = $role;
		}

		return $data;
	}

	/**
	 * Collects the roles one menu item, and its submenu items, is hidden for.
	 *
	 * @param array $item   Menu item.
	 * @param array $common Shared defaults of a compressed configuration.
	 * @param array $data   Result, by reference.
	 * @param int   $custom Count of skipped custom items, by reference.
	 * @return void
	 */
	private static function ame_item( array $item, array $common, array &$data, &$custom ) {
		$item = array_merge( $common, $item );

		if ( ! empty( $item['separator'] ) ) {
			return;
		}

		$template = isset( $item['template_id'] ) ? (string) $item['template_id'] : '';

		if ( ! empty( $item['custom'] ) || '' === $template ) {
			++$custom;
		} else {
			// Template ids are "parent>file"; top-level items have no parent.
			$id = '>' === $template[0] ? substr( $template, 1 ) : $template;

			$hidden = self::denied_roles( isset( $item['grant_access'] ) ? (array) $item['grant_access'] : array() );

			foreach ( isset( $item['hidden_from_actor'] ) ? (array) $item['hidden_from_actor'] : array() as $actor => $flag ) {
				if ( $flag && 0 === strpos( $actor, 'role:' ) ) {
					$hidden[] = substr( $actor, 5 );
				}
			}

			if ( ! empty( $item['hidden'] ) ) {
				$hidden = self::roles();
			}

			foreach ( array_intersect( array_unique( $hidden ), self::roles() ) as $role ) {
				$data['menu'][ $role ][] = $id;
			}
		}

		foreach ( isset( $item['items'] ) ? (array) $item['items'] : array() as $child ) {
			self::ame_item( (array) $child, $common, $data, $custom );
		}
	}

	/**
	 * Admin Menu Editor Pro dashboard widgets: who sees each widget, and
	 * custom HTML widgets.
	 *
	 * @return array
	 */
	private static function ame_widgets() {
		$stored = self::option( 'ws_ame_dashboard_widgets' );

		if ( ! is_string( $stored ) || '' === $stored ) {
			return array();
		}

		$json = $stored;

		// Stored as "WH" + tags + "D" + data, where tags may say base64 ("64") or zlib ("gz").
		if ( 0 === strpos( $stored, 'WH' ) && false !== strpos( substr( $stored, 0, 32 ), 'D', 2 ) ) {
			$marker = strpos( $stored, 'D', 2 );
			$tags   = str_split( substr( $stored, 2, $marker - 2 ), 2 );
			$json   = substr( $stored, $marker + 1 );

			if ( in_array( '64', $tags, true ) ) {
				// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Reading Admin Menu Editor's own stored format.
				$json = base64_decode( $json );
			}

			if ( in_array( 'gz', $tags, true ) ) {
				$json = function_exists( 'gzuncompress' ) ? gzuncompress( $json ) : '';
			}
		}

		$config = json_decode( (string) $json, true );

		if ( ! is_array( $config ) ) {
			return array();
		}

		$data = array();

		foreach ( isset( $config['widgets'] ) ? (array) $config['widgets'] : array() as $widget ) {
			$widget = (array) $widget;
			$id     = isset( $widget['id'] ) ? (string) $widget['id'] : '';
			$denied = self::denied_roles( isset( $widget['grantAccess'] ) ? (array) $widget['grantAccess'] : array() );
			$type   = isset( $widget['widgetType'] ) ? (string) $widget['widgetType'] : '';

			if ( 'custom-html' === $type ) {
				$data['widgets_new'][] = array(
					'type'    => 'text',
					'title'   => isset( $widget['title'] ) ? (string) $widget['title'] : '',
					'content' => isset( $widget['content'] ) ? (string) $widget['content'] : '',
					'roles'   => $denied ? array_values( array_diff( array_keys( wp_roles()->roles ), $denied ) ) : array(),
					'column'  => isset( $widget['location'] ) && 'side' === $widget['location'] ? 'side' : 'normal',
				);
				continue;
			}

			if ( 'custom-rss' === $type ) {
				$data['notes'][] = __( 'Admin Menu Editor\'s RSS feed widgets were not imported. Use a "What\'s new" widget for posts from this site.', 'layo-dashboard-toolkit' );
				continue;
			}

			if ( '' !== $id ) {
				foreach ( $denied as $role ) {
					$data['widgets_hide'][ $id ][] = $role;
				}
			}
		}

		if ( isset( $config['welcomePanel']['grantAccess'] ) ) {
			foreach ( self::denied_roles( (array) $config['welcomePanel']['grantAccess'] ) as $role ) {
				$data['widgets_hide']['welcome_panel'][] = $role;
			}
		}

		return $data;
	}

	/**
	 * Admin Menu Editor Pro login redirects per role.
	 *
	 * @return array
	 */
	private static function ame_redirects() {
		$settings = json_decode( (string) self::option( 'ws_ame_redirects' ), true );

		if ( ! is_array( $settings ) || empty( $settings['redirects']['login'] ) ) {
			return array();
		}

		$data    = array();
		$skipped = 0;

		foreach ( (array) $settings['redirects']['login'] as $rule ) {
			$rule  = (array) $rule;
			$actor = isset( $rule['actorId'] ) ? (string) $rule['actorId'] : '';
			$url   = isset( $rule['urlTemplate'] ) ? trim( (string) $rule['urlTemplate'] ) : '';

			if ( 0 !== strpos( $actor, 'role:' ) || '' === $url || false !== strpos( $url, '[' ) ) {
				++$skipped;
				continue;
			}

			$role = substr( $actor, 5 );

			if ( in_array( $role, self::roles(), true ) ) {
				$data['redirect'][ $role ] = 'url:' . ( preg_match( '#^https?://#i', $url ) ? $url : home_url( '/' . ltrim( $url, '/' ) ) );
			}
		}

		if ( $skipped ) {
			$data['notes'][] = __( 'Login redirects for single people, for everyone, or with shortcodes in the address were not imported. Destinations here are set per role, or per person on their profile.', 'layo-dashboard-toolkit' );
		}

		return $data;
	}

	// ------------------------------------------------------------------
	// User Role Editor details.
	// ------------------------------------------------------------------

	/**
	 * Every menu item User Role Editor knows, with the hashes it may use for
	 * it: md5( "menu" or "submenu" + link ).
	 *
	 * @return array Menu id => hashes.
	 */
	private static function ure_items() {
		$menu    = self::option( 'ure_admin_menu_copy' );
		$submenu = self::option( 'ure_admin_sub_menu_copy' );
		$items   = array();

		foreach ( is_array( $menu ) ? $menu : array() as $entry ) {
			if ( isset( $entry[2], $entry[3] ) && '' !== (string) $entry[3] ) {
				$items[ (string) $entry[2] ] = array( md5( 'menu' . $entry[3] ), md5( 'submenu' . $entry[3] ) );
			}
		}

		foreach ( is_array( $submenu ) ? $submenu : array() as $parent => $entries ) {
			foreach ( (array) $entries as $entry ) {
				if ( isset( $entry[2], $entry[3] ) && '' !== (string) $entry[3] && $entry[2] !== $parent ) {
					$items[ $parent . '>' . $entry[2] ] = array( md5( 'submenu' . $entry[3] ), md5( 'menu' . $entry[3] ) );
				}
			}
		}

		return $items;
	}

	/**
	 * Dashboard meta boxes by User Role Editor's hash: md5( id + screen + context ).
	 *
	 * @return array Hash => widget id.
	 */
	private static function ure_dashboard_boxes() {
		$known = array();
		$copy  = self::option( 'ure_meta_boxes_list_copy' );

		foreach ( is_array( $copy ) ? $copy : array() as $hash => $box ) {
			$box = (array) $box;
			if ( isset( $box['id'], $box['screen'] ) && 'dashboard' === $box['screen'] ) {
				$known[ $hash ] = (string) $box['id'];
			}
		}

		// Widgets the copy does not have yet, in every dashboard column.
		$ids = array_keys( \LayoDT\Modules\Widgets\Widgets::known() );

		foreach ( $ids as $id ) {
			foreach ( array( 'normal', 'side', 'column3', 'column4' ) as $context ) {
				$known[ md5( $id . 'dashboard' . $context ) ] = $id;
			}
		}

		return $known;
	}

	// ------------------------------------------------------------------
	// Helpers.
	// ------------------------------------------------------------------

	/**
	 * An option, falling back to the network option on multisite.
	 *
	 * @param string $name Option name.
	 * @return mixed
	 */
	private static function option( $name ) {
		$value = get_option( $name, null );

		if ( null === $value && is_multisite() ) {
			$value = get_site_option( $name, null );
		}

		return $value;
	}

	/**
	 * Decodes a JSON string option, or returns an array option as it is.
	 *
	 * @param mixed $value Stored value.
	 * @return array
	 */
	private static function json( $value ) {
		if ( is_string( $value ) ) {
			$value = json_decode( $value, true );
		}

		return is_array( $value ) ? $value : array();
	}

	/**
	 * A list value from an options array.
	 *
	 * @param array  $options Options.
	 * @param string $key     Key.
	 * @return array
	 */
	private static function lst( array $options, $key ) {
		return isset( $options[ $key ] ) && is_array( $options[ $key ] ) ? array_filter( $options[ $key ], 'is_scalar' ) : array();
	}

	/**
	 * Roles rules can be imported for. Administrators are never restricted.
	 *
	 * @return string[]
	 */
	public static function roles() {
		return array_values( array_diff( array_keys( wp_roles()->roles ), array( 'administrator' ) ) );
	}

	/**
	 * Whether any of a source's plugin files is active.
	 *
	 * @param string $id     Source id.
	 * @param string $except Plugin file to count as inactive, for the moment
	 *                       it is being deactivated and the list is not saved yet.
	 * @return bool
	 */
	public static function any_active( $id, $except = '' ) {
		$all = self::all();

		if ( ! isset( $all[ $id ] ) ) {
			return false;
		}

		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		foreach ( $all[ $id ][1] as $file ) {
			if ( $file !== $except && is_plugin_active( $file ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Roles an Admin Menu Editor access list denies.
	 *
	 * @param array $grants Actor => allowed.
	 * @return string[]
	 */
	private static function denied_roles( array $grants ) {
		$roles = array();

		foreach ( $grants as $actor => $allowed ) {
			if ( ! $allowed && 0 === strpos( (string) $actor, 'role:' ) ) {
				$roles[] = substr( $actor, 5 );
			}
		}

		return array_values( array_intersect( $roles, self::roles() ) );
	}

	/**
	 * What a "where to send blocked visitors" slug means here.
	 *
	 * @param string $slug "404", "" or a page slug.
	 * @return array { blocked_action, blocked_url }
	 */
	private static function blocked_to( $slug ) {
		$slug = trim( $slug, '/' );

		if ( '' === $slug || '404' === $slug ) {
			return array( 'blocked_action' => '404' );
		}

		return array(
			'blocked_action' => 'custom',
			'blocked_url'    => home_url( '/' . $slug . '/' ),
		);
	}

	/**
	 * The admin submenu: the live one on admin screens, otherwise a stored
	 * copy.
	 *
	 * @param mixed $stored Stored copy.
	 * @return array Parent slug => entries.
	 */
	private static function submenus( $stored ) {
		global $submenu;

		if ( ! empty( $submenu ) && is_array( $submenu ) ) {
			return $submenu;
		}

		if ( is_object( $stored ) ) {
			$stored = json_decode( wp_json_encode( $stored ), true );
		}

		return is_array( $stored ) ? $stored : array();
	}

	/**
	 * Menu id for an Adminimize submenu entry.
	 *
	 * @param string $entry   Page slug or "parent__position".
	 * @param array  $submenu Parent slug => entries.
	 * @return string
	 */
	private static function submenu_id( $entry, array $submenu ) {
		if ( preg_match( '/^(.+)__(\d+)$/', $entry, $m ) ) {
			$child = isset( $submenu[ $m[1] ][ (int) $m[2] ] ) ? (array) $submenu[ $m[1] ][ (int) $m[2] ] : array();
			return isset( $child[2] ) && $child[2] !== $m[1] ? $m[1] . '>' . $child[2] : '';
		}

		foreach ( $submenu as $parent => $children ) {
			foreach ( (array) $children as $child ) {
				$child = (array) $child;
				if ( isset( $child[2] ) && $child[2] === $entry && $entry !== $parent ) {
					return $parent . '>' . $entry;
				}
			}
		}

		return '';
	}

	/**
	 * Merges two results.
	 *
	 * @param array $a First.
	 * @param array $b Second.
	 * @return array
	 */
	private static function merge( array $a, array $b ) {
		foreach ( $b as $key => $value ) {
			if ( in_array( $key, array( 'login', 'admin', 'limits' ), true ) || ! isset( $a[ $key ] ) ) {
				$a[ $key ] = $value;
			} elseif ( 'redirect' === $key ) {
				$a[ $key ] = array_merge( $a[ $key ], $value );
			} else {
				$a[ $key ] = array_merge_recursive( $a[ $key ], $value );
			}
		}

		return $a;
	}

	/**
	 * Removes duplicates and empty groups from a result.
	 *
	 * @param array $data Result.
	 * @return array
	 */
	private static function tidy( array $data ) {
		foreach ( array( 'menu', 'toolbar_items', 'widgets_hide' ) as $key ) {
			if ( isset( $data[ $key ] ) ) {
				foreach ( $data[ $key ] as $k => $list ) {
					$list = array_values( array_unique( array_filter( array_map( 'strval', (array) $list ), 'strlen' ) ) );
					if ( $list ) {
						$data[ $key ][ $k ] = $list;
					} else {
						unset( $data[ $key ][ $k ] );
					}
				}
			}
		}

		foreach ( array( 'toolbar_front', 'toolbar_admin', 'notes' ) as $key ) {
			if ( isset( $data[ $key ] ) ) {
				$data[ $key ] = array_values( array_unique( (array) $data[ $key ] ) );
			}
		}

		return array_filter(
			$data,
			static function ( $value ) {
				return ! empty( $value );
			}
		);
	}
}

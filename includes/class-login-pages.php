<?php
/**
 * Finds login, lost password and registration pages set up by the theme or
 * by other plugins, so this plugin can show them, keep them open during
 * coming soon, use them in links and warn before its own login address
 * clashes with them.
 *
 * Two kinds are found:
 * - addresses changed with WordPress's own filters (login_url,
 *   lostpassword_url, register_url, or site_url rewriting wp-login.php), by
 *   any code: a plugin, a must-use plugin or the theme's functions.php;
 * - pages that carry a login form for well-known plugins, such as
 *   WooCommerce's My Account page.
 *
 * @package LayoDT
 */

namespace LayoDT;

defined( 'ABSPATH' ) || exit;

/**
 * Login page detection.
 */
class Login_Pages {

	const CACHE = 'layodt_login_pages';

	/**
	 * Hooks that can change the login addresses.
	 */
	const HOOKS = array( 'login_url', 'lostpassword_url', 'register_url', 'site_url', 'network_site_url' );

	/**
	 * Pages found, cached for the request.
	 *
	 * @var array|null
	 */
	private static $found = null;

	/**
	 * Labels for the kinds of page.
	 *
	 * @return array
	 */
	public static function kinds() {
		return array(
			'staff'        => __( 'Staff login (wp-login.php moved)', 'layo-dashboard-toolkit' ),
			'login'        => __( 'Log in links', 'layo-dashboard-toolkit' ),
			'lostpassword' => __( 'Lost password', 'layo-dashboard-toolkit' ),
			'register'     => __( 'Registration', 'layo-dashboard-toolkit' ),
			'account'      => __( 'Customer account page', 'layo-dashboard-toolkit' ),
		);
	}

	/**
	 * Every login page set up by other code.
	 *
	 * @param bool $fresh Whether to look again instead of using the cache.
	 * @return array List of [ kind, url, source, audience ], where audience is
	 *               "staff" (the login for administrators, editors and other
	 *               site users) or "customer" (shop and membership pages).
	 */
	public static function found( $fresh = false ) {
		if ( null !== self::$found && ! $fresh ) {
			return self::$found;
		}

		$cached = $fresh ? false : get_transient( self::CACHE );

		if ( is_array( $cached ) && ! in_array( false, array_map( 'is_array', $cached ), true ) && count( $cached ) === count( array_filter( wp_list_pluck( $cached, 'audience' ) ) ) ) {
			self::$found = $cached;
			return $cached;
		}

		$customer    = self::from_plugins();
		self::$found = array_merge( self::from_filters( wp_list_pluck( $customer, 'url' ) ), $customer );
		set_transient( self::CACHE, self::$found, HOUR_IN_SECONDS );

		return self::$found;
	}

	/**
	 * Staff login paths that must stay open while the site is in coming
	 * soon mode, so staff can still log in. Customer and member pages stay
	 * closed with the rest of the site, and the home page is never included.
	 *
	 * @return string[]
	 */
	public static function paths() {
		$paths = array();
		$home  = untrailingslashit( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ) );

		foreach ( self::found() as $page ) {
			$path = untrailingslashit( (string) wp_parse_url( $page['url'], PHP_URL_PATH ) );

			if ( 'staff' === $page['audience'] && '' !== $path && $path !== $home && false === strpos( $path, 'wp-login.php' ) ) {
				$paths[] = $path;
			}
		}

		return array_values( array_unique( $paths ) );
	}

	/**
	 * Another plugin or the theme moving wp-login.php itself, which would
	 * clash with this plugin's custom login URL. Login links that point to a
	 * front-end page, and customer pages, do not clash.
	 *
	 * @return array|null [ kind, url, source, audience ], or null when there is none.
	 */
	public static function other_login() {
		foreach ( self::found() as $page ) {
			if ( 'staff' === $page['kind'] ) {
				return $page;
			}
		}

		return null;
	}

	// ------------------------------------------------------------------
	// Addresses changed with filters.
	// ------------------------------------------------------------------

	/**
	 * Login, lost password and registration addresses as other code makes
	 * them, with this plugin's own filters left out.
	 *
	 * @param string[] $customer Addresses of customer and member pages.
	 * @return array
	 */
	private static function from_filters( array $customer ) {
		global $wp_filter;

		$saved = array();

		foreach ( self::HOOKS as $hook ) {
			if ( isset( $wp_filter[ $hook ] ) && $wp_filter[ $hook ] instanceof \WP_Hook ) {
				$saved[ $hook ]     = $wp_filter[ $hook ];
				$wp_filter[ $hook ] = self::without_ours( $hook, $wp_filter[ $hook ] ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restored straight after.
			}
		}

		$base  = untrailingslashit( (string) get_option( 'siteurl' ) ) . '/wp-login.php';
		$found = array();
		$staff = site_url( 'wp-login.php', 'login' );

		if ( ! self::same( $staff, $base ) ) {
			$found[] = array(
				'kind'     => 'staff',
				'url'      => $staff,
				'source'   => self::source( 'site_url', $base ),
				'audience' => 'staff',
			);
		}

		$urls = array(
			'login'        => array( wp_login_url(), $base, 'login_url' ),
			'lostpassword' => array( wp_lostpassword_url(), $base . '?action=lostpassword', 'lostpassword_url' ),
			'register'     => array( wp_registration_url(), $base . '?action=register', 'register_url' ),
		);

		foreach ( $urls as $kind => $info ) {
			// Unchanged, or only changed because wp-login.php moved (listed above).
			if ( self::same( $info[0], $info[1] ) || self::same( $info[0], str_replace( $base, $staff, $info[1] ) ) ) {
				continue;
			}

			$url = remove_query_arg( 'redirect_to', $info[0] );

			$found[] = array(
				'kind'     => $kind,
				'url'      => $url,
				'source'   => self::source( $info[2], $info[1] ),
				'audience' => self::is_customer( $url, $customer ) ? 'customer' : 'staff',
			);
		}

		foreach ( $saved as $hook => $object ) {
			$wp_filter[ $hook ] = $object; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restoring the original.
		}

		return $found;
	}

	/**
	 * A copy of a hook without this plugin's callbacks.
	 *
	 * @param string   $name Hook name.
	 * @param \WP_Hook $hook Hook.
	 * @return \WP_Hook
	 */
	private static function without_ours( $name, \WP_Hook $hook ) {
		$copy = clone $hook;

		foreach ( $hook->callbacks as $priority => $callbacks ) {
			foreach ( $callbacks as $callback ) {
				if ( self::is_ours( self::file_of( $callback['function'] ) ) ) {
					$copy->remove_filter( $name, $callback['function'], $priority );
				}
			}
		}

		return $copy;
	}

	/**
	 * Whether a file belongs to this plugin.
	 *
	 * @param string $file File path.
	 * @return bool
	 */
	public static function is_ours( $file ) {
		return '' !== $file && 0 === strpos( wp_normalize_path( $file ), wp_normalize_path( LAYODT_DIR ) );
	}

	/**
	 * Who changed an address: the first outside callback that changes it.
	 *
	 * @param string $hook    The address's own filter.
	 * @param string $original The address WordPress would use.
	 * @return string
	 */
	private static function source( $hook, $original ) {
		global $wp_filter;

		$args = array( $original, '' );

		foreach ( array( $hook, 'site_url' ) as $name ) {
			if ( ! isset( $wp_filter[ $name ] ) || ! $wp_filter[ $name ] instanceof \WP_Hook ) {
				continue;
			}

			foreach ( $wp_filter[ $name ]->callbacks as $callbacks ) {
				foreach ( $callbacks as $callback ) {
					$file = self::file_of( $callback['function'] );

					if ( '' === $file || self::is_ours( $file ) || 0 === strpos( wp_normalize_path( $file ), wp_normalize_path( ABSPATH . WPINC ) ) ) {
						continue;
					}

					$value = 'site_url' === $name ? $original : $args[0];
					$given = 'site_url' === $name ? array( $value, 'wp-login.php', 'login', null ) : array( $value, '', false );
					$out   = self::try_callback( $callback, $given );

					if ( is_string( $out ) && ! self::same( $out, $original ) ) {
						return self::describe_file( $file );
					}
				}
			}
		}

		return __( 'Custom code', 'layo-dashboard-toolkit' );
	}

	/**
	 * Whether an address is one of the customer or member pages, or below one.
	 *
	 * @param string   $url      Address.
	 * @param string[] $customer Customer and member page addresses.
	 * @return bool
	 */
	private static function is_customer( $url, array $customer ) {
		foreach ( $customer as $page ) {
			if ( '' !== $page && 0 === strpos( untrailingslashit( $url ), untrailingslashit( $page ) ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Runs one callback on its own, guarding against errors.
	 *
	 * @param array $callback Hook callback.
	 * @param array $args     Arguments.
	 * @return mixed
	 */
	private static function try_callback( array $callback, array $args ) {
		try {
			return call_user_func_array( $callback['function'], array_slice( $args, 0, max( 1, (int) $callback['accepted_args'] ) ) );
		} catch ( \Throwable $e ) {
			return null;
		}
	}

	/**
	 * Whether two addresses are the same, ignoring redirect_to and a
	 * trailing slash.
	 *
	 * @param string $a First address.
	 * @param string $b Second address.
	 * @return bool
	 */
	private static function same( $a, $b ) {
		return untrailingslashit( remove_query_arg( 'redirect_to', (string) $a ) ) === untrailingslashit( remove_query_arg( 'redirect_to', (string) $b ) );
	}

	// ------------------------------------------------------------------
	// Pages from well-known plugins.
	// ------------------------------------------------------------------

	/**
	 * Login pages kept by well-known plugins in their own settings.
	 *
	 * @return array
	 */
	private static function from_plugins() {
		$found = array();

		if ( function_exists( 'wc_get_page_id' ) ) {
			$page = (int) wc_get_page_id( 'myaccount' );
			if ( $page > 0 && 'publish' === get_post_status( $page ) ) {
				$found[] = array(
					'kind'     => 'account',
					'url'      => (string) get_permalink( $page ),
					/* translators: %s: plugin name. */
					'source'   => sprintf( __( 'The %s plugin', 'layo-dashboard-toolkit' ), 'WooCommerce' ),
					'audience' => 'customer',
				);
			}
		}

		$um = get_option( 'um_options' );

		if ( defined( 'UM_VERSION' ) && is_array( $um ) ) {
			$map = array(
				'core_login'          => 'login',
				'core_register'       => 'register',
				'core_password-reset' => 'lostpassword',
			);
			foreach ( $map as $key => $kind ) {
				if ( ! empty( $um[ $key ] ) && 'publish' === get_post_status( (int) $um[ $key ] ) ) {
					$found[] = array(
						'kind'     => $kind,
						'url'      => (string) get_permalink( (int) $um[ $key ] ),
						/* translators: %s: plugin name. */
						'source'   => sprintf( __( 'The %s plugin', 'layo-dashboard-toolkit' ), 'Ultimate Member' ),
						'audience' => 'customer',
					);
				}
			}
		}

		if ( defined( 'PMPRO_VERSION' ) ) {
			$page = (int) get_option( 'pmpro_login_page_id' );
			if ( $page && 'publish' === get_post_status( $page ) ) {
				$found[] = array(
					'kind'     => 'login',
					'url'      => (string) get_permalink( $page ),
					/* translators: %s: plugin name. */
					'source'   => sprintf( __( 'The %s plugin', 'layo-dashboard-toolkit' ), 'Paid Memberships Pro' ),
					'audience' => 'customer',
				);
			}
		}

		// The same address can be found both ways; keep the first.
		$seen = array();
		foreach ( $found as $i => $page ) {
			$key = $page['kind'] . '|' . untrailingslashit( $page['url'] );
			if ( isset( $seen[ $key ] ) ) {
				unset( $found[ $i ] );
			}
			$seen[ $key ] = true;
		}

		return array_values( $found );
	}

	// ------------------------------------------------------------------
	// Where code comes from.
	// ------------------------------------------------------------------

	/**
	 * The file a callback is defined in, or '' when it cannot be told.
	 *
	 * @param mixed $callback Callback.
	 * @return string
	 */
	public static function file_of( $callback ) {
		try {
			if ( is_string( $callback ) && false !== strpos( $callback, '::' ) ) {
				$callback = explode( '::', $callback, 2 );
			}

			if ( is_array( $callback ) && 2 === count( $callback ) ) {
				$reflection = new \ReflectionMethod( $callback[0], $callback[1] );
			} elseif ( $callback instanceof \Closure || ( is_string( $callback ) && function_exists( $callback ) ) ) {
				$reflection = new \ReflectionFunction( $callback );
			} elseif ( is_object( $callback ) && method_exists( $callback, '__invoke' ) ) {
				$reflection = new \ReflectionMethod( $callback, '__invoke' );
			} else {
				return '';
			}

			return (string) $reflection->getFileName();
		} catch ( \ReflectionException $e ) {
			return '';
		}
	}

	/**
	 * A readable name for where a file lives: a plugin, a must-use plugin
	 * or the theme.
	 *
	 * @param string $file File path.
	 * @return string
	 */
	public static function describe_file( $file ) {
		$file    = wp_normalize_path( $file );
		$plugins = wp_normalize_path( WP_PLUGIN_DIR ) . '/';
		$mu      = wp_normalize_path( WPMU_PLUGIN_DIR ) . '/';
		$themes  = wp_normalize_path( get_theme_root() ) . '/';

		if ( 0 === strpos( $file, $plugins ) ) {
			$folder = strtok( substr( $file, strlen( $plugins ) ), '/' );

			if ( ! function_exists( 'get_plugins' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}

			foreach ( get_plugins() as $basename => $data ) {
				if ( 0 === strpos( $basename, $folder . '/' ) || $basename === $folder ) {
					/* translators: %s: plugin name. */
					return sprintf( __( 'The %s plugin', 'layo-dashboard-toolkit' ), $data['Name'] );
				}
			}

			/* translators: %s: plugin folder. */
			return sprintf( __( 'The plugin in %s', 'layo-dashboard-toolkit' ), $folder );
		}

		if ( 0 === strpos( $file, $mu ) ) {
			/* translators: %s: file name. */
			return sprintf( __( 'Must-use plugin %s', 'layo-dashboard-toolkit' ), basename( $file ) );
		}

		if ( 0 === strpos( $file, $themes ) ) {
			$folder = strtok( substr( $file, strlen( $themes ) ), '/' );
			$theme  = wp_get_theme( $folder );
			/* translators: 1: theme name, 2: file name. */
			return sprintf( __( 'The %1$s theme (%2$s)', 'layo-dashboard-toolkit' ), $theme->exists() ? $theme->get( 'Name' ) : $folder, basename( $file ) );
		}

		return __( 'Custom code', 'layo-dashboard-toolkit' );
	}
}

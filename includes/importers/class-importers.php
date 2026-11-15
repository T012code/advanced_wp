<?php
/**
 * Imports settings from other plugins: the Tools card, the review screen,
 * the import itself and the offer to deactivate the other plugin.
 *
 * Login addresses and attempt limits wait until the other plugin is off, so
 * two plugins never move the login page at the same time. Menus, the toolbar,
 * login destinations and dashboard widgets apply straight away.
 *
 * @package LayoDT
 */

namespace LayoDT\Importers;

use LayoDT\Admin\Admin;
use LayoDT\Settings;
use LayoDT\Modules\Roles\Access;
use LayoDT\Modules\Roles\Audit;
use LayoDT\Modules\Roles\Redirects;
use LayoDT\Modules\Security\Admin_Url;
use LayoDT\Modules\Security\Login_Url;
use LayoDT\Modules\Widgets\Widgets;

defined( 'ABSPATH' ) || exit;

/**
 * Importer.
 */
class Importers {

	const WAITING   = 'layodt_other_waiting';
	const DONE      = 'layodt_other_done';
	const SCAN      = 'layodt_other_scan_';
	const REPORT    = 'layodt_other_report_';
	const DISMISSED = 'layodt_other_dismissed';

	/**
	 * Read results for this request.
	 *
	 * @var array|null
	 */
	private static $found = null;

	/**
	 * Hooks. Deactivation can happen anywhere, including WP-CLI, so that hook
	 * is always added.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'deactivated_plugin', array( __CLASS__, 'on_deactivated' ) );

		if ( is_admin() ) {
			foreach ( array( 'apply', 'deactivate', 'dismiss', 'cancel' ) as $action ) {
				add_action( 'admin_post_layodt_other_' . $action, array( __CLASS__, 'handle_' . $action ) );
			}
		}
	}

	/**
	 * What can be imported: key => [ label, settings tab, waits for the other plugin ].
	 *
	 * @return array
	 */
	public static function areas() {
		return array(
			'login'    => array( __( 'Login address', 'layo-dashboard-toolkit' ), 'security', true ),
			'admin'    => array( __( 'Dashboard address', 'layo-dashboard-toolkit' ), 'security', true ),
			'limits'   => array( __( 'Login attempt limits', 'layo-dashboard-toolkit' ), 'security', true ),
			'menu'     => array( __( 'Menu access per role', 'layo-dashboard-toolkit' ), 'roles', false ),
			'toolbar'  => array( __( 'Toolbar per role', 'layo-dashboard-toolkit' ), 'roles', false ),
			'redirect' => array( __( 'Login destinations per role', 'layo-dashboard-toolkit' ), 'roles', false ),
			'widgets'  => array( __( 'Dashboard widgets per role', 'layo-dashboard-toolkit' ), 'widgets', false ),
		);
	}

	/**
	 * The parts of a read result that belong to an area.
	 *
	 * @param array  $data Read result.
	 * @param string $area Area key.
	 * @return array
	 */
	private static function part( array $data, $area ) {
		$keys = array(
			'login'    => array( 'login' ),
			'admin'    => array( 'admin' ),
			'limits'   => array( 'limits' ),
			'menu'     => array( 'menu' ),
			'toolbar'  => array( 'toolbar_items', 'toolbar_front', 'toolbar_admin' ),
			'redirect' => array( 'redirect' ),
			'widgets'  => array( 'widgets_hide', 'widgets_new' ),
		);

		return array_intersect_key( $data, array_flip( $keys[ $area ] ) );
	}

	/**
	 * Areas present in a read result.
	 *
	 * @param array $data Read result.
	 * @return string[]
	 */
	private static function areas_in( array $data ) {
		return array_values(
			array_filter(
				array_keys( self::areas() ),
				static function ( $area ) use ( $data ) {
					return (bool) self::part( $data, $area );
				}
			)
		);
	}

	/**
	 * Every plugin with settings to import: id => read result.
	 *
	 * @return array
	 */
	public static function found() {
		if ( null === self::$found ) {
			self::$found = array();

			foreach ( array_keys( Sources::all() ) as $id ) {
				$data = Sources::read( $id );
				if ( self::areas_in( $data ) ) {
					self::$found[ $id ] = $data;
				}
			}
		}

		return self::$found;
	}

	/**
	 * A plugin's display name.
	 *
	 * @param string $id Source id.
	 * @return string
	 */
	public static function name( $id ) {
		$all = Sources::all();

		return isset( $all[ $id ] ) ? $all[ $id ][0] : $id;
	}

	/**
	 * Fingerprint of an area's data, to tell whether it was imported already.
	 *
	 * @param array  $data Read result.
	 * @param string $area Area key.
	 * @return string
	 */
	private static function fingerprint( array $data, $area ) {
		return md5( (string) wp_json_encode( self::part( $data, $area ) ) );
	}

	/**
	 * Whether this exact data was imported already.
	 *
	 * @param string $id   Source id.
	 * @param array  $data Read result.
	 * @param string $area Area key.
	 * @return bool
	 */
	private static function is_done( $id, array $data, $area ) {
		$done = get_option( self::DONE, array() );

		return isset( $done[ $id ][ $area ] ) && self::fingerprint( $data, $area ) === $done[ $id ][ $area ];
	}

	// ------------------------------------------------------------------
	// Describing what will change.
	// ------------------------------------------------------------------

	/**
	 * Role names for a list of role keys.
	 *
	 * @param string[] $roles Role keys.
	 * @return string
	 */
	private static function role_names( array $roles ) {
		$names = wp_roles()->get_names();
		$out   = array();

		foreach ( $roles as $role ) {
			$out[] = isset( $names[ $role ] ) ? translate_user_role( $names[ $role ] ) : $role;
		}

		return implode( ', ', $out );
	}

	/**
	 * Plain sentences describing an area's data.
	 *
	 * @param string $area Area key.
	 * @param array  $data Read result.
	 * @return string[]
	 */
	private static function describe( $area, array $data ) {
		$lines = array();

		switch ( $area ) {
			case 'login':
				/* translators: %s: login address. */
				$lines[] = sprintf( __( 'Log in at %s', 'layo-dashboard-toolkit' ), home_url( '/' . trim( $data['login']['slug'], '/' ) . '/' ) );
				if ( isset( $data['login']['blocked_action'] ) && 'custom' === $data['login']['blocked_action'] && ! empty( $data['login']['blocked_url'] ) ) {
					/* translators: %s: address. */
					$lines[] = sprintf( __( 'Visitors who open wp-login.php or /wp-admin go to %s', 'layo-dashboard-toolkit' ), $data['login']['blocked_url'] );
				} else {
					$lines[] = __( 'Visitors who open wp-login.php or /wp-admin get a "page not found" page', 'layo-dashboard-toolkit' );
				}
				break;

			case 'admin':
				/* translators: %s: dashboard address. */
				$lines[] = sprintf( __( 'The dashboard moves to %s', 'layo-dashboard-toolkit' ), home_url( '/' . trim( $data['admin']['slug'], '/' ) . '/' ) );
				break;

			case 'limits':
				$limits  = $data['limits'];
				$lines[] = sprintf(
					/* translators: 1: number of attempts, 2: minutes, 3: minutes. */
					__( '%1$d failed attempts within %2$d minutes lock the address out for %3$d minutes', 'layo-dashboard-toolkit' ),
					isset( $limits['max_attempts'] ) ? $limits['max_attempts'] : (int) Settings::get( 'security.max_attempts' ),
					isset( $limits['window'] ) ? $limits['window'] : (int) Settings::get( 'security.window' ),
					isset( $limits['lockout'] ) ? $limits['lockout'] : (int) Settings::get( 'security.lockout' )
				);
				break;

			case 'menu':
				foreach ( $data['menu'] as $role => $ids ) {
					/* translators: 1: role name, 2: number of menu items. */
					$lines[] = sprintf( _n( '%1$s: %2$d menu item hidden', '%1$s: %2$d menu items hidden', count( $ids ), 'layo-dashboard-toolkit' ), self::role_names( array( $role ) ), count( $ids ) );
				}
				break;

			case 'toolbar':
				if ( ! empty( $data['toolbar_front'] ) ) {
					/* translators: %s: role names. */
					$lines[] = sprintf( __( 'No toolbar on the site for: %s', 'layo-dashboard-toolkit' ), self::role_names( $data['toolbar_front'] ) );
				}
				if ( ! empty( $data['toolbar_admin'] ) ) {
					/* translators: %s: role names. */
					$lines[] = sprintf( __( 'No toolbar in the dashboard for: %s (applies with a dashboard template)', 'layo-dashboard-toolkit' ), self::role_names( $data['toolbar_admin'] ) );
				}
				foreach ( isset( $data['toolbar_items'] ) ? $data['toolbar_items'] : array() as $role => $ids ) {
					/* translators: 1: role name, 2: number of toolbar items. */
					$lines[] = sprintf( _n( '%1$s: %2$d toolbar item hidden', '%1$s: %2$d toolbar items hidden', count( $ids ), 'layo-dashboard-toolkit' ), self::role_names( array( $role ) ), count( $ids ) );
				}
				break;

			case 'redirect':
				foreach ( $data['redirect'] as $role => $token ) {
					/* translators: 1: role name, 2: address. */
					$lines[] = sprintf( __( '%1$s goes to %2$s after logging in', 'layo-dashboard-toolkit' ), self::role_names( array( $role ) ), Redirects::url( $token ) );
				}
				break;

			case 'widgets':
				$known = Widgets::known();
				foreach ( isset( $data['widgets_hide'] ) ? $data['widgets_hide'] : array() as $id => $roles ) {
					$title = isset( $known[ $id ] ) ? $known[ $id ] : $id;
					/* translators: 1: widget name, 2: role names. */
					$lines[] = sprintf( __( '"%1$s" hidden from: %2$s', 'layo-dashboard-toolkit' ), $title, self::role_names( $roles ) );
				}
				foreach ( isset( $data['widgets_new'] ) ? $data['widgets_new'] : array() as $widget ) {
					$lines[] = sprintf(
						/* translators: 1: widget title, 2: role names or "everyone". */
						__( 'New widget "%1$s" for %2$s', 'layo-dashboard-toolkit' ),
						$widget['title'],
						$widget['roles'] ? self::role_names( $widget['roles'] ) : __( 'everyone', 'layo-dashboard-toolkit' )
					);
				}
				break;
		}

		return $lines;
	}

	// ------------------------------------------------------------------
	// Screens.
	// ------------------------------------------------------------------

	/**
	 * The "Import from another plugin" card on the Tools tab.
	 *
	 * @return void
	 */
	public static function card() {
		echo '<section class="layodt-card"><h2>' . esc_html__( 'Import from another plugin', 'layo-dashboard-toolkit' ) . '</h2>';
		echo '<p>' . esc_html__( 'Bring across login addresses, attempt limits, menu and toolbar rules, login destinations and dashboard widgets you set up with another plugin.', 'layo-dashboard-toolkit' ) . '</p>';

		$found = self::found();

		if ( ! $found ) {
			echo '<p>' . esc_html__( 'No settings from other plugins were found on this site.', 'layo-dashboard-toolkit' ) . '</p>';
		} else {
			echo '<ul class="layodt-sources">';
			foreach ( $found as $id => $data ) {
				$labels = array();
				foreach ( self::areas_in( $data ) as $area ) {
					$labels[] = self::areas()[ $area ][0];
				}
				printf(
					'<li><strong>%1$s</strong> <span class="layodt-badge">%2$s</span><br><span class="description">%3$s</span><br><a class="button button-small" href="%4$s">%5$s</a></li>',
					esc_html( self::name( $id ) ),
					Sources::any_active( $id ) ? esc_html__( 'Active', 'layo-dashboard-toolkit' ) : esc_html__( 'Inactive', 'layo-dashboard-toolkit' ),
					esc_html( implode( ', ', $labels ) ),
					esc_url(
						Admin::url(
							'tools',
							array(
								'step'   => 'other',
								'source' => $id,
							)
						)
					),
					esc_html__( 'Review and import', 'layo-dashboard-toolkit' )
				);
			}
			echo '</ul>';
		}

		$names = array();
		foreach ( Sources::all() as $source ) {
			$names[] = $source[0];
		}
		/* translators: %s: list of plugin names. */
		echo '<p class="description">' . esc_html( sprintf( __( 'Supported: %s.', 'layo-dashboard-toolkit' ), implode( ', ', $names ) ) ) . '</p>';
		echo '</section>';
	}

	/**
	 * The review screen for one plugin. What it shows is saved, and the import
	 * applies exactly that.
	 *
	 * @param string $id Source id.
	 * @return void
	 */
	public static function review( $id ) {
		$data = Sources::read( $id );
		$back = Admin::url( 'tools' );

		/* translators: %s: plugin name. */
		echo '<h2 class="title">' . esc_html( sprintf( __( 'Import from %s', 'layo-dashboard-toolkit' ), self::name( $id ) ) ) . '</h2>';

		if ( ! self::areas_in( $data ) ) {
			echo '<p>' . esc_html__( 'There is nothing to import from this plugin.', 'layo-dashboard-toolkit' ) . '</p>';
			echo '<p><a class="button" href="' . esc_url( $back ) . '">' . esc_html__( 'Back to Tools', 'layo-dashboard-toolkit' ) . '</a></p>';
			return;
		}

		set_transient(
			self::SCAN . get_current_user_id(),
			array(
				'source' => $id,
				'data'   => $data,
			),
			HOUR_IN_SECONDS
		);

		$active = Sources::any_active( $id );

		echo '<p class="layodt-intro">' . esc_html__( 'Tick what to bring across. Nothing changes until you click Import, and the other plugin\'s own settings are left as they are.', 'layo-dashboard-toolkit' ) . '</p>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="layodt_other_apply"><input type="hidden" name="source" value="' . esc_attr( $id ) . '">';
		wp_nonce_field( 'layodt_other_apply' );

		echo '<div class="layodt-table-scroll"><table class="widefat layodt-import-other"><thead><tr><td class="check-column"></td><th scope="col">' . esc_html__( 'Setting', 'layo-dashboard-toolkit' ) . '</th><th scope="col">' . esc_html__( 'What it does here', 'layo-dashboard-toolkit' ) . '</th><th scope="col">' . esc_html__( 'Takes effect', 'layo-dashboard-toolkit' ) . '</th></tr></thead><tbody>';

		foreach ( self::areas_in( $data ) as $area ) {
			$info  = self::areas()[ $area ];
			$done  = self::is_done( $id, $data, $area );
			$field = 'layodt-other-' . $area;

			if ( $info[2] && $active ) {
				/* translators: %s: plugin name. */
				$when = sprintf( __( 'When %s is deactivated, so the two never run together', 'layo-dashboard-toolkit' ), self::name( $id ) );
			} else {
				$when = __( 'Straight away', 'layo-dashboard-toolkit' );
			}

			echo '<tr><th scope="row" class="check-column"><input type="checkbox" id="' . esc_attr( $field ) . '" name="layodt_areas[]" value="' . esc_attr( $area ) . '"' . checked( ! $done, true, false ) . '></th>';
			echo '<td><label for="' . esc_attr( $field ) . '"><strong>' . esc_html( $info[0] ) . '</strong></label>';
			if ( $done ) {
				echo '<br><span class="layodt-badge">' . esc_html__( 'Imported already', 'layo-dashboard-toolkit' ) . '</span>';
			}
			echo '</td><td><ul class="layodt-plain-list">';
			foreach ( self::describe( $area, $data ) as $line ) {
				echo '<li>' . esc_html( $line ) . '</li>';
			}
			echo '</ul></td><td>' . esc_html( $when ) . '</td></tr>';
		}

		echo '</tbody></table></div>';

		if ( ! empty( $data['notes'] ) ) {
			echo '<h3>' . esc_html__( 'Not brought across', 'layo-dashboard-toolkit' ) . '</h3><ul class="layodt-plain-list">';
			foreach ( $data['notes'] as $note ) {
				echo '<li>' . esc_html( $note ) . '</li>';
			}
			echo '</ul>';
		}

		echo '<p class="layodt-actions">';
		submit_button( __( 'Import', 'layo-dashboard-toolkit' ), 'primary', 'submit', false );
		echo ' <a class="button" href="' . esc_url( $back ) . '">' . esc_html__( 'Cancel', 'layo-dashboard-toolkit' ) . '</a></p>';
		echo '</form>';
	}

	/**
	 * The result screen after an import, with the offer to deactivate.
	 *
	 * @param string $id Source id.
	 * @return void
	 */
	public static function result( $id ) {
		$key    = self::REPORT . get_current_user_id();
		$report = get_transient( $key );

		/* translators: %s: plugin name. */
		echo '<h2 class="title">' . esc_html( sprintf( __( 'Imported from %s', 'layo-dashboard-toolkit' ), self::name( $id ) ) ) . '</h2>';

		if ( is_array( $report ) && isset( $report['source'] ) && $report['source'] === $id ) {
			echo '<ul class="layodt-plain-list">';
			foreach ( $report['lines'] as $line ) {
				echo '<li class="layodt-result layodt-result--' . esc_attr( $line[0] ) . '">' . esc_html( $line[1] ) . '</li>';
			}
			echo '</ul>';
		}

		self::deactivate_offer( $id );

		echo '<p><a class="button" href="' . esc_url( Admin::url( 'tools' ) ) . '">' . esc_html__( 'Back to Tools', 'layo-dashboard-toolkit' ) . '</a></p>';
	}

	/**
	 * Deactivate button for a plugin, while it is active.
	 *
	 * @param string $id Source id.
	 * @return void
	 */
	private static function deactivate_offer( $id ) {
		if ( ! Sources::any_active( $id ) || ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		$waiting = get_option( self::WAITING, array() );

		echo '<div class="layodt-callout">';
		if ( ! empty( $waiting[ $id ] ) ) {
			/* translators: %s: plugin name. */
			echo '<p>' . esc_html( sprintf( __( 'Your login protection settings switch on here as soon as %s is deactivated. Until then it keeps doing that job.', 'layo-dashboard-toolkit' ), self::name( $id ) ) ) . '</p>';
		} else {
			/* translators: %s: plugin name. */
			echo '<p>' . esc_html( sprintf( __( '%s is still active. If you no longer need it, deactivate it so the two plugins do not both apply the same rules.', 'layo-dashboard-toolkit' ), self::name( $id ) ) ) . '</p>';
		}
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" data-layodt-confirm>';
		echo '<input type="hidden" name="action" value="layodt_other_deactivate"><input type="hidden" name="source" value="' . esc_attr( $id ) . '">';
		wp_nonce_field( 'layodt_other_deactivate' );
		/* translators: %s: plugin name. */
		submit_button( sprintf( __( 'Deactivate %s', 'layo-dashboard-toolkit' ), self::name( $id ) ), 'secondary', 'submit', false );
		echo '</form></div>';
	}

	/**
	 * Notes on the Login protection, Roles and Widgets tabs: settings waiting
	 * for another plugin to be deactivated, and settings that can be imported.
	 *
	 * @param string $tab Tab key.
	 * @return void
	 */
	public static function hints( $tab ) {
		if ( ! in_array( $tab, array( 'security', 'roles', 'widgets' ), true ) ) {
			return;
		}

		if ( 'security' === $tab ) {
			foreach ( get_option( self::WAITING, array() ) as $id => $areas ) {
				echo '<div class="notice notice-info layodt-notice"><p>';
				/* translators: 1: plugin name, 2: list of settings. */
				echo esc_html( sprintf( __( 'Imported from %1$s and waiting for it to be deactivated: %2$s.', 'layo-dashboard-toolkit' ), self::name( $id ), implode( ', ', self::area_labels( array_keys( (array) $areas ) ) ) ) );
				echo '</p><div class="layodt-row-actions">';
				/* translators: %s: plugin name. */
				$label = sprintf( __( 'Deactivate %s', 'layo-dashboard-toolkit' ), self::name( $id ) );
				self::small_form( 'deactivate', $id, $label, true );
				self::small_form( 'cancel', $id, __( 'Do not switch these on', 'layo-dashboard-toolkit' ), false );
				echo '</div></div>';
			}
		}

		$dismissed = (array) get_user_meta( get_current_user_id(), self::DISMISSED, true );

		foreach ( self::found() as $id => $data ) {
			$areas = array();
			foreach ( self::areas_in( $data ) as $area ) {
				if ( self::areas()[ $area ][1] === $tab && ! self::is_done( $id, $data, $area ) && ! in_array( $id . ':' . $tab . ':' . self::fingerprint( $data, $area ), $dismissed, true ) ) {
					$areas[] = $area;
				}
			}

			if ( ! $areas ) {
				continue;
			}

			$lines = array();
			foreach ( $areas as $area ) {
				$lines = array_merge( $lines, array_slice( self::describe( $area, $data ), 0, 2 ) );
			}

			echo '<div class="notice notice-info layodt-notice"><p>';
			/* translators: 1: plugin name, 2: summary of its settings. */
			echo esc_html( sprintf( __( '%1$s has settings you can bring across: %2$s.', 'layo-dashboard-toolkit' ), self::name( $id ), implode( '; ', $lines ) ) );
			echo '</p><div class="layodt-row-actions"><a class="button button-small" href="' . esc_url(
				Admin::url(
					'tools',
					array(
						'step'   => 'other',
						'source' => $id,
					)
				)
			) . '">' . esc_html__( 'Review and import', 'layo-dashboard-toolkit' ) . '</a>';
			self::small_form( 'dismiss', $id, __( 'Not now', 'layo-dashboard-toolkit' ), false, array( 'tab' => $tab ) );
			echo '</div></div>';
		}
	}

	/**
	 * Labels for area keys.
	 *
	 * @param string[] $areas Area keys.
	 * @return string[]
	 */
	private static function area_labels( array $areas ) {
		$labels = array();

		foreach ( $areas as $area ) {
			if ( isset( self::areas()[ $area ] ) ) {
				$labels[] = self::areas()[ $area ][0];
			}
		}

		return $labels;
	}

	/**
	 * A one-button form posting to admin-post.php.
	 *
	 * @param string $action  Action suffix.
	 * @param string $id      Source id.
	 * @param string $label   Button text.
	 * @param bool   $confirm Whether to ask first.
	 * @param array  $extra   Extra hidden fields.
	 * @return void
	 */
	private static function small_form( $action, $id, $label, $confirm, array $extra = array() ) {
		echo '<form method="post" class="layodt-inline-form" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"' . ( $confirm ? ' data-layodt-confirm' : '' ) . '>';
		echo '<input type="hidden" name="action" value="layodt_other_' . esc_attr( $action ) . '"><input type="hidden" name="source" value="' . esc_attr( $id ) . '">';
		foreach ( $extra as $name => $value ) {
			echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '">';
		}
		wp_nonce_field( 'layodt_other_' . $action );
		submit_button( $label, 'small', 'submit', false );
		echo '</form>';
	}

	// ------------------------------------------------------------------
	// Handlers.
	// ------------------------------------------------------------------

	/**
	 * Imports the ticked areas from the saved review.
	 *
	 * @return void
	 */
	public static function handle_apply() {
		check_admin_referer( 'layodt_other_apply' );

		if ( ! current_user_can( 'manage_options' ) ) {
			self::deny();
		}

		$id    = isset( $_POST['source'] ) ? sanitize_key( wp_unslash( $_POST['source'] ) ) : '';
		$areas = isset( $_POST['layodt_areas'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['layodt_areas'] ) ) : array();
		$scan  = get_transient( self::SCAN . get_current_user_id() );

		if ( ! is_array( $scan ) || ! isset( $scan['source'], $scan['data'] ) || $scan['source'] !== $id ) {
			self::back( 'other_expired' );
		}

		$areas = array_values( array_intersect( $areas, self::areas_in( $scan['data'] ) ) );

		if ( ! $areas ) {
			self::back(
				'other_nothing',
				array(
					'step'   => 'other',
					'source' => $id,
				)
			);
		}

		$lines = self::apply( $id, $scan['data'], $areas );

		delete_transient( self::SCAN . get_current_user_id() );
		set_transient(
			self::REPORT . get_current_user_id(),
			array(
				'source' => $id,
				'lines'  => $lines,
			),
			10 * MINUTE_IN_SECONDS
		);

		/* translators: 1: list of settings, 2: plugin name. */
		Audit::log( 'import', sprintf( __( '%1$s imported from %2$s.', 'layo-dashboard-toolkit' ), implode( ', ', self::area_labels( $areas ) ), self::name( $id ) ) );

		self::back(
			'',
			array(
				'step'   => 'other_done',
				'source' => $id,
			)
		);
	}

	/**
	 * Deactivates the other plugin, which switches on anything waiting for it.
	 *
	 * @return void
	 */
	public static function handle_deactivate() {
		check_admin_referer( 'layodt_other_deactivate' );

		if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'activate_plugins' ) ) {
			self::deny();
		}

		$id  = isset( $_POST['source'] ) ? sanitize_key( wp_unslash( $_POST['source'] ) ) : '';
		$all = Sources::all();

		if ( ! isset( $all[ $id ] ) ) {
			self::back( '' );
		}

		if ( ! function_exists( 'deactivate_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$active = array_values( array_filter( $all[ $id ][1], 'is_plugin_active' ) );

		if ( $active ) {
			deactivate_plugins( $active );
		}

		$state = get_transient( 'layodt_other_switched_' . get_current_user_id() );
		delete_transient( 'layodt_other_switched_' . get_current_user_id() );

		self::back( is_string( $state ) ? 'other_switched_' . $state : 'other_deactivated', array( 'source' => $id ) );
	}

	/**
	 * Drops imported settings that were waiting for another plugin.
	 *
	 * @return void
	 */
	public static function handle_cancel() {
		check_admin_referer( 'layodt_other_cancel' );

		if ( ! current_user_can( 'manage_options' ) ) {
			self::deny();
		}

		$id      = isset( $_POST['source'] ) ? sanitize_key( wp_unslash( $_POST['source'] ) ) : '';
		$waiting = get_option( self::WAITING, array() );
		unset( $waiting[ $id ] );
		update_option( self::WAITING, $waiting, false );

		wp_safe_redirect( Admin::url( 'security', array( 'layodt_notice' => 'other_cancelled' ) ) );
		exit;
	}

	/**
	 * Hides an import suggestion for the current user until the other
	 * plugin's settings change.
	 *
	 * @return void
	 */
	public static function handle_dismiss() {
		check_admin_referer( 'layodt_other_dismiss' );

		if ( ! current_user_can( 'manage_options' ) ) {
			self::deny();
		}

		$id   = isset( $_POST['source'] ) ? sanitize_key( wp_unslash( $_POST['source'] ) ) : '';
		$tab  = isset( $_POST['tab'] ) ? sanitize_key( wp_unslash( $_POST['tab'] ) ) : '';
		$data = Sources::read( $id );
		$keys = (array) get_user_meta( get_current_user_id(), self::DISMISSED, true );

		foreach ( self::areas_in( $data ) as $area ) {
			if ( self::areas()[ $area ][1] === $tab ) {
				$keys[] = $id . ':' . $tab . ':' . self::fingerprint( $data, $area );
			}
		}

		update_user_meta( get_current_user_id(), self::DISMISSED, array_values( array_unique( array_filter( $keys ) ) ) );

		wp_safe_redirect( Admin::url( array_key_exists( $tab, Admin::tabs() ) ? $tab : 'tools' ) );
		exit;
	}

	/**
	 * Switches on settings that were waiting for a plugin, once none of its
	 * files is active.
	 *
	 * @param string $plugin Deactivated plugin file.
	 * @return void
	 */
	public static function on_deactivated( $plugin ) {
		foreach ( Sources::all() as $id => $source ) {
			// WordPress saves the list of active plugins after this hook runs.
			if ( in_array( $plugin, $source[1], true ) && ! Sources::any_active( $id, $plugin ) ) {
				$state = self::switch_on( $id );
				if ( null !== $state && is_user_logged_in() ) {
					set_transient( 'layodt_other_switched_' . get_current_user_id(), $state, MINUTE_IN_SECONDS );
				}
			}
		}
	}

	// ------------------------------------------------------------------
	// Applying.
	// ------------------------------------------------------------------

	/**
	 * Imports areas and describes the outcome.
	 *
	 * @param string   $id    Source id.
	 * @param array    $data  Read result.
	 * @param string[] $areas Areas to import.
	 * @return array Lines of [ type, text ].
	 */
	private static function apply( $id, array $data, array $areas ) {
		$lines    = array();
		$security = array();
		$done     = get_option( self::DONE, array() );

		foreach ( $areas as $area ) {
			if ( self::areas()[ $area ][2] ) {
				$values = self::security_values( $area, $data, $security, $lines );
				if ( $values ) {
					$security[ $area ] = $values;
				}
			} else {
				self::apply_rules( $area, $data );
				/* translators: %s: setting name. */
				$lines[] = array( 'success', sprintf( __( '%s imported.', 'layo-dashboard-toolkit' ), self::areas()[ $area ][0] ) );
			}

			$done[ $id ][ $area ] = self::fingerprint( $data, $area );
		}

		update_option( self::DONE, $done, false );

		if ( $security ) {
			if ( Sources::any_active( $id ) ) {
				$waiting        = get_option( self::WAITING, array() );
				$waiting[ $id ] = array_merge( isset( $waiting[ $id ] ) ? (array) $waiting[ $id ] : array(), $security );
				update_option( self::WAITING, $waiting, false );

				$lines[] = array(
					'info',
					sprintf(
						/* translators: 1: list of settings, 2: plugin name. */
						__( '%1$s saved. They switch on when %2$s is deactivated, so the two plugins never move the login page or count attempts at the same time.', 'layo-dashboard-toolkit' ),
						implode( ', ', self::area_labels( array_keys( $security ) ) ),
						self::name( $id )
					),
				);
			} else {
				$lines[] = self::security_outcome( self::switch_values( $security ), array_keys( $security ) );
			}
		}

		return $lines;
	}

	/**
	 * Checked settings values for a login protection area, or none when the
	 * imported value cannot be used here.
	 *
	 * @param string $area     Area key.
	 * @param array  $data     Read result.
	 * @param array  $security Areas accepted so far.
	 * @param array  $lines    Report lines, by reference.
	 * @return array
	 */
	private static function security_values( $area, array $data, array $security, array &$lines ) {
		if ( 'limits' === $area ) {
			$limits = $data['limits'];
			$values = array( 'security.limit' => true );

			foreach ( array( 'max_attempts', 'window', 'lockout' ) as $key ) {
				if ( isset( $limits[ $key ] ) ) {
					$values[ 'security.' . $key ] = (int) $limits[ $key ];
				}
			}

			return $values;
		}

		$raw  = strtolower( trim( (string) $data[ $area ]['slug'], '/' ) );
		$slug = sanitize_title( $raw );

		if ( $slug !== $raw || ! Login_Url::is_valid( $slug ) ) {
			$lines[] = array(
				'warning',
				sprintf(
					/* translators: 1: address, 2: setting name. */
					__( '"%1$s" cannot be used as an address here, so the %2$s was not imported. Addresses are a single word with letters, numbers and dashes, and cannot be "login", "admin" or "dashboard".', 'layo-dashboard-toolkit' ),
					$raw,
					strtolower( self::areas()[ $area ][0] )
				),
			);
			return array();
		}

		if ( 'login' === $area ) {
			$values = array(
				'security.login_url'  => true,
				'security.login_slug' => $slug,
			);
			if ( isset( $data['login']['blocked_action'] ) ) {
				$values['security.blocked_action'] = $data['login']['blocked_action'];
				$values['security.blocked_url']    = isset( $data['login']['blocked_url'] ) ? $data['login']['blocked_url'] : '';
			}
			return $values;
		}

		$login = isset( $security['login']['security.login_slug'] ) ? $security['login']['security.login_slug'] : ( Settings::get( 'security.login_url' ) ? Login_Url::slug() : '' );

		if ( $slug === $login ) {
			$lines[] = array( 'warning', __( 'The dashboard address is the same as the login address, so it was not imported.', 'layo-dashboard-toolkit' ) );
			return array();
		}

		return array(
			'security.admin_url'  => true,
			'security.admin_slug' => $slug,
		);
	}

	/**
	 * Saves login protection values and switches the feature on.
	 *
	 * @param array $security Area => settings values.
	 * @return string Dashboard address state: off, active, pending or unwritable.
	 */
	private static function switch_values( array $security ) {
		$values = array( 'modules.security' => true );

		foreach ( $security as $area_values ) {
			$values = array_merge( $values, (array) $area_values );
		}

		Settings::update( $values );

		return isset( $security['admin'] ) ? Admin_Url::apply() : 'off';
	}

	/**
	 * Report line after login protection settings were switched on.
	 *
	 * @param string   $state Dashboard address state.
	 * @param string[] $areas Areas switched on.
	 * @return array [ type, text ]
	 */
	private static function security_outcome( $state, array $areas ) {
		if ( 'pending' === $state || 'unwritable' === $state ) {
			return array( 'warning', __( 'Login protection settings switched on. The new dashboard address needs server rules before it works, so the dashboard stays at /wp-admin for now. See Login protection.', 'layo-dashboard-toolkit' ) );
		}

		/* translators: %s: list of settings. */
		return array( 'success', sprintf( __( 'Switched on: %s.', 'layo-dashboard-toolkit' ), implode( ', ', self::area_labels( $areas ) ) ) );
	}

	/**
	 * Switches on what was waiting for a plugin.
	 *
	 * @param string $id Source id.
	 * @return string|null Dashboard address state, or null when nothing was waiting.
	 */
	private static function switch_on( $id ) {
		$waiting = get_option( self::WAITING, array() );

		if ( empty( $waiting[ $id ] ) ) {
			return null;
		}

		$security = (array) $waiting[ $id ];
		unset( $waiting[ $id ] );
		update_option( self::WAITING, $waiting, false );

		return self::switch_values( $security );
	}

	/**
	 * Merges imported role rules or widgets into this plugin's own.
	 *
	 * @param string $area Area key.
	 * @param array  $data Read result.
	 * @return void
	 */
	private static function apply_rules( $area, array $data ) {
		$rules = Access::rules();

		switch ( $area ) {
			case 'menu':
				Access::save( 'menu', self::union( $rules['menu'], $data['menu'] ) );
				break;

			case 'toolbar':
				if ( ! empty( $data['toolbar_items'] ) ) {
					Access::save( 'toolbar_items', self::union( $rules['toolbar_items'], $data['toolbar_items'] ) );
				}
				foreach ( array( 'toolbar_front', 'toolbar_admin' ) as $group ) {
					if ( ! empty( $data[ $group ] ) ) {
						Access::save( $group, array_unique( array_merge( (array) $rules[ $group ], $data[ $group ] ) ) );
					}
				}
				break;

			case 'redirect':
				Access::save( 'redirect', array_merge( (array) $rules['redirect'], $data['redirect'] ) );
				break;

			case 'widgets':
				$config = Widgets::config();
				$custom = $config['custom'];

				foreach ( isset( $data['widgets_new'] ) ? $data['widgets_new'] : array() as $widget ) {
					do {
						$new = 'w' . strtolower( wp_generate_password( 6, false ) );
					} while ( isset( $custom[ $new ] ) );
					$custom[ $new ] = $widget;
				}

				Widgets::save(
					array(
						'hide'   => self::union( $config['hide'], isset( $data['widgets_hide'] ) ? $data['widgets_hide'] : array() ),
						'custom' => $custom,
					)
				);
				break;
		}
	}

	/**
	 * Merges two key => list maps, keeping every value from both.
	 *
	 * @param array $a Existing.
	 * @param array $b Imported.
	 * @return array
	 */
	private static function union( array $a, array $b ) {
		foreach ( $b as $key => $list ) {
			$a[ $key ] = array_values( array_unique( array_merge( isset( $a[ $key ] ) ? (array) $a[ $key ] : array(), (array) $list ) ) );
		}

		return $a;
	}

	/**
	 * Stops the request with a "not allowed" message.
	 *
	 * @return void
	 */
	private static function deny() {
		wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'layo-dashboard-toolkit' ), '', array( 'response' => 403 ) );
	}

	/**
	 * Back to the Tools tab.
	 *
	 * @param string $notice Notice key.
	 * @param array  $args   Extra query arguments.
	 * @return void
	 */
	private static function back( $notice, array $args = array() ) {
		if ( '' !== $notice ) {
			$args['layodt_notice'] = $notice;
		}

		wp_safe_redirect( Admin::url( 'tools', $args ) );
		exit;
	}
}

<?php
/**
 * Settings → Adminora → Sessions.
 *
 * @package Adminora
 */

namespace Adminora\Admin;

use Adminora\Settings;
use Adminora\Modules\Admin_Look\Icons;
use Adminora\Modules\Sessions\Device;
use Adminora\Modules\Sessions\Sessions;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the sessions tab and handles its forms.
 */
class Sessions_Tab {

	const PER_PAGE = 50;

	/**
	 * Hooks for form handlers.
	 *
	 * @return void
	 */
	public static function init() {
		foreach ( array( 'session_end', 'session_end_all', 'user_block', 'user_unblock', 'history_clear', 'history_csv' ) as $action ) {
			add_action( 'admin_post_adminora_' . $action, array( __CLASS__, 'handle_' . $action ) );
		}

		add_action( 'admin_notices', array( __CLASS__, 'users_notice' ) );
	}

	/**
	 * Sections of the tab.
	 *
	 * @return array
	 */
	private static function sections() {
		return array(
			'active'   => __( 'Signed in now', 'adminora' ),
			'history'  => __( 'Sign-in history', 'adminora' ),
			'blocked'  => __( 'Blocked accounts', 'adminora' ),
			'settings' => __( 'Limits and history', 'adminora' ),
		);
	}

	/**
	 * URL of a section.
	 *
	 * @param string $section Section key.
	 * @param array  $args    Extra arguments.
	 * @return string
	 */
	private static function url( $section, array $args = array() ) {
		return Admin::url( 'sessions', array_merge( array( 'section' => $section ), $args ) );
	}

	/**
	 * Opens a form posting to admin-post.php.
	 *
	 * @param string $action  Action suffix.
	 * @param array  $extra   Hidden fields.
	 * @param bool   $confirm Whether to ask for confirmation.
	 * @param string $css_class Form class.
	 * @return void
	 */
	private static function form( $action, array $extra = array(), $confirm = false, $css_class = '' ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"' . ( $confirm ? ' data-adminora-confirm' : '' ) . ( '' !== $css_class ? ' class="' . esc_attr( $css_class ) . '"' : '' ) . '>';
		echo '<input type="hidden" name="action" value="adminora_' . esc_attr( $action ) . '">';
		foreach ( $extra as $name => $value ) {
			echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '">';
		}
		wp_nonce_field( 'adminora_' . $action );
	}

	/**
	 * A small form with one button.
	 *
	 * @param string $action  Action suffix.
	 * @param array  $extra   Hidden fields.
	 * @param string $label   Button text.
	 * @param string $classes Button classes.
	 * @param bool   $confirm Whether to ask for confirmation.
	 * @return void
	 */
	private static function button( $action, array $extra, $label, $classes = 'button', $confirm = false ) {
		self::form( $action, $extra, $confirm, 'adminora-inline-form' );
		echo '<button type="submit" class="' . esc_attr( $classes ) . '">' . esc_html( $label ) . '</button></form>';
	}

	/**
	 * "5 minutes ago" with the full date as a tooltip.
	 *
	 * @param int  $time   Timestamp.
	 * @param bool $future Whether the time is ahead.
	 * @return void
	 */
	private static function when( $time, $future = false ) {
		if ( ! $time ) {
			echo '<span aria-hidden="true">&mdash;</span>';
			return;
		}

		$format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
		$diff   = human_time_diff( $time, time() );

		printf(
			'<time datetime="%s" title="%s">%s</time>',
			esc_attr( gmdate( 'c', $time ) ),
			esc_attr( wp_date( $format, $time ) ),
			esc_html(
				$future
					/* translators: %s: time span such as "3 days". */
					? sprintf( __( 'in %s', 'adminora' ), $diff )
					/* translators: %s: time span such as "5 minutes". */
					: sprintf( __( '%s ago', 'adminora' ), $diff )
			)
		);
	}

	/**
	 * Device icon, name and type.
	 *
	 * @param string $ua User agent.
	 * @return void
	 */
	private static function device( $ua ) {
		$device = Device::parse( $ua );
		$icons  = array(
			'phone'  => 'smartphone',
			'tablet' => 'tablet',
			'app'    => 'smartphone',
			'other'  => 'globe',
		);
		$icon   = isset( $icons[ $device['type'] ] ) ? $icons[ $device['type'] ] : 'monitor';

		echo '<span class="adminora-device"><span class="adminora-device__icon" aria-hidden="true">' . wp_kses( Icons::svg( $icon, 18 ), Icons::kses() ) . '</span><span><strong>' . esc_html( $device['label'] ) . '</strong><small>' . esc_html( Device::type_label( $device['type'] ) ) . '</small></span></span>';
	}

	/**
	 * Avatar, name and role of a user.
	 *
	 * @param \WP_User $user User.
	 * @return void
	 */
	private static function person( $user ) {
		$roles = array_map(
			static function ( $role ) {
				$names = wp_roles()->get_names();
				return isset( $names[ $role ] ) ? translate_user_role( $names[ $role ] ) : $role;
			},
			(array) $user->roles
		);

		echo '<span class="adminora-person">' . get_avatar( $user->ID, 32 ) . '<span><a href="' . esc_url( get_edit_user_link( $user->ID ) ) . '"><strong>' . esc_html( $user->display_name ) . '</strong></a><small>' . esc_html( $user->user_login . ( $roles ? ' · ' . implode( ', ', $roles ) : '' ) ) . '</small></span></span>';
	}

	/**
	 * Renders the tab.
	 *
	 * @return void
	 */
	public static function render() {
		if ( ! Settings::module_on( 'sessions' ) ) {
			echo '<div class="notice notice-warning inline adminora-notice"><p>' . esc_html__( 'Sessions is switched off on the Overview tab.', 'adminora' ) . '</p></div>';
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Navigation only.
		$section = isset( $_GET['section'] ) ? sanitize_key( wp_unslash( $_GET['section'] ) ) : 'active';
		$section = array_key_exists( $section, self::sections() ) ? $section : 'active';

		echo '<nav class="adminora-subnav" aria-label="' . esc_attr__( 'Sessions sections', 'adminora' ) . '"><ul>';
		foreach ( self::sections() as $key => $label ) {
			printf(
				'<li><a href="%s"%s>%s</a></li>',
				esc_url( self::url( $key ) ),
				$key === $section ? ' class="is-current" aria-current="page"' : '',
				esc_html( $label )
			);
		}
		echo '</ul></nav>';

		$method = 'section_' . $section;
		self::$method();
	}

	/**
	 * Everyone signed in, grouped by person.
	 *
	 * @return void
	 */
	private static function section_active() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Filters only.
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$only   = isset( $_GET['user'] ) ? absint( $_GET['user'] ) : 0;
		// phpcs:enable

		$rows    = Sessions::signed_in( $search );
		$current = Sessions::current_verifier();
		$me      = get_current_user_id();

		if ( $only ) {
			$rows = array_values(
				array_filter(
					$rows,
					static function ( $row ) use ( $only ) {
						return $row[0]->ID === $only;
					}
				)
			);
		}

		$total = array_sum(
			array_map(
				static function ( $row ) {
					return count( $row[1] );
				},
				$rows
			)
		);

		echo '<p class="adminora-intro">' . esc_html__( 'Everyone who is signed in, and the devices they are signed in on. Signing a device out takes effect on its next page load. Blocking an account signs it out everywhere and stops it signing in again.', 'adminora' ) . '</p>';

		echo '<form method="get" action="' . esc_url( admin_url( 'options-general.php' ) ) . '" class="adminora-inline-form adminora-filter"><input type="hidden" name="page" value="adminora"><input type="hidden" name="tab" value="sessions"><input type="hidden" name="section" value="active">';
		echo '<label for="adminora-session-search" class="screen-reader-text">' . esc_html__( 'Search people', 'adminora' ) . '</label><input type="search" id="adminora-session-search" name="s" value="' . esc_attr( $search ) . '" placeholder="' . esc_attr__( 'Name, username or email', 'adminora' ) . '"> <button class="button">' . esc_html__( 'Search', 'adminora' ) . '</button>';
		if ( '' !== $search || $only ) {
			echo ' <a href="' . esc_url( self::url( 'active' ) ) . '">' . esc_html__( 'Show everyone', 'adminora' ) . '</a>';
		}
		echo '</form>';

		printf(
			'<p class="adminora-count">%s</p>',
			esc_html(
				sprintf(
					/* translators: 1: number of people, 2: number of sessions. */
					__( '%1$s signed in on %2$s.', 'adminora' ),
					/* translators: %d: number of people. */
					sprintf( _n( '%d person', '%d people', count( $rows ), 'adminora' ), count( $rows ) ),
					/* translators: %d: number of devices. */
					sprintf( _n( '%d device', '%d devices', $total, 'adminora' ), $total )
				)
			)
		);

		if ( ! $rows ) {
			echo '<p>' . esc_html__( 'Nobody matches.', 'adminora' ) . '</p>';
			return;
		}

		foreach ( $rows as $row ) {
			list( $user, $sessions ) = $row;

			$self = $user->ID === $me;
			$can  = current_user_can( 'edit_user', $user->ID );

			echo '<section class="adminora-session-group">';
			echo '<header class="adminora-session-group__head">';
			self::person( $user );
			echo '<div class="adminora-actions">';
			if ( $can ) {
				if ( $self ) {
					if ( count( $sessions ) > 1 ) {
						self::button( 'session_end_all', array( 'user' => $user->ID ), __( 'Sign out my other devices', 'adminora' ) );
					}
				} else {
					self::button( 'session_end_all', array( 'user' => $user->ID ), __( 'Sign out everywhere', 'adminora' ) );
					self::button( 'user_block', array( 'user' => $user->ID ), __( 'Block', 'adminora' ), 'button button-link-delete', true );
				}
			}
			echo '</div></header>';

			echo '<table class="widefat striped adminora-sessions"><thead><tr><th scope="col">' . esc_html__( 'Device', 'adminora' ) . '</th><th scope="col">' . esc_html__( 'IP address', 'adminora' ) . '</th><th scope="col">' . esc_html__( 'Signed in', 'adminora' ) . '</th><th scope="col">' . esc_html__( 'Last active', 'adminora' ) . '</th><th scope="col">' . esc_html__( 'Expires', 'adminora' ) . '</th><th scope="col"><span class="screen-reader-text">' . esc_html__( 'Actions', 'adminora' ) . '</span></th></tr></thead><tbody>';
			foreach ( array_reverse( $sessions, true ) as $verifier => $session ) {
				$here = $self && $verifier === $current;
				echo '<tr><td>';
				self::device( isset( $session['ua'] ) ? $session['ua'] : '' );
				if ( $here ) {
					echo ' <span class="adminora-badge">' . esc_html__( 'This device', 'adminora' ) . '</span>';
				}
				echo '</td><td><code>' . esc_html( isset( $session['ip'] ) ? $session['ip'] : '' ) . '</code></td><td>';
				self::when( isset( $session['login'] ) ? (int) $session['login'] : 0 );
				echo '</td><td>';
				self::when( isset( $session['last_seen'] ) ? (int) $session['last_seen'] : 0 );
				echo '</td><td>';
				self::when( (int) $session['expiration'], true );
				echo '</td><td class="adminora-row-actions">';
				if ( $can && ! $here ) {
					self::button(
						'session_end',
						array(
							'user'    => $user->ID,
							'session' => $verifier,
						),
						__( 'Sign out', 'adminora' ),
						'button button-small'
					);
				}
				echo '</td></tr>';
			}
			echo '</tbody></table></section>';
		}
	}

	/**
	 * Sign-in history with filters.
	 *
	 * @return void
	 */
	private static function section_history() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Filters only.
		$event = isset( $_GET['event'] ) ? sanitize_key( wp_unslash( $_GET['event'] ) ) : '';
		$who   = isset( $_GET['who'] ) ? sanitize_text_field( wp_unslash( $_GET['who'] ) ) : '';
		$page  = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		// phpcs:enable

		$events = Sessions::events();
		$event  = isset( $events[ $event ] ) ? $event : '';
		$user   = '' !== $who ? get_user_by( is_email( $who ) ? 'email' : 'login', $who ) : false;

		echo '<p class="adminora-intro">' . esc_html__( 'Every sign-in, sign-out, failed attempt and administrator action, with the device and address it came from. The last 1,000 events are kept.', 'adminora' ) . '</p>';

		if ( ! Settings::get( 'sessions.history' ) ) {
			echo '<div class="notice notice-info inline adminora-notice"><p>' . esc_html__( 'Recording sign-ins is switched off under Limits and history. Administrator actions are still recorded.', 'adminora' ) . '</p></div>';
		}

		echo '<form method="get" action="' . esc_url( admin_url( 'options-general.php' ) ) . '" class="adminora-inline-form adminora-filter"><input type="hidden" name="page" value="adminora"><input type="hidden" name="tab" value="sessions"><input type="hidden" name="section" value="history">';
		echo '<label for="adminora-history-event" class="screen-reader-text">' . esc_html__( 'Event', 'adminora' ) . '</label><select id="adminora-history-event" name="event"><option value="">' . esc_html__( 'All events', 'adminora' ) . '</option>';
		foreach ( $events as $key => $label ) {
			echo '<option value="' . esc_attr( $key ) . '"' . selected( $event, $key, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select> <label for="adminora-history-who" class="screen-reader-text">' . esc_html__( 'Username or email', 'adminora' ) . '</label><input type="search" id="adminora-history-who" name="who" value="' . esc_attr( $who ) . '" placeholder="' . esc_attr__( 'Username or email', 'adminora' ) . '"> <button class="button">' . esc_html__( 'Filter', 'adminora' ) . '</button></form>';

		if ( '' !== $who && ! $user ) {
			echo '<p>' . esc_html__( 'No user found with that username or email address.', 'adminora' ) . '</p>';
			return;
		}

		$entries = Sessions::history( $event, $user ? $user->ID : 0 );

		if ( ! $entries ) {
			echo '<p>' . esc_html__( 'Nothing recorded yet.', 'adminora' ) . '</p>';
			return;
		}

		$pages   = (int) ceil( count( $entries ) / self::PER_PAGE );
		$page    = min( $page, $pages );
		$entries = array_slice( $entries, ( $page - 1 ) * self::PER_PAGE, self::PER_PAGE );
		$format  = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );

		echo '<div class="adminora-actions adminora-history-actions">';
		self::button( 'history_csv', array(), __( 'Download as CSV', 'adminora' ) );
		self::button( 'history_clear', array(), __( 'Clear history', 'adminora' ), 'button button-link-delete', true );
		echo '</div>';

		echo '<table class="widefat striped adminora-sessions"><thead><tr><th scope="col">' . esc_html__( 'When', 'adminora' ) . '</th><th scope="col">' . esc_html__( 'Event', 'adminora' ) . '</th><th scope="col">' . esc_html__( 'Account', 'adminora' ) . '</th><th scope="col">' . esc_html__( 'Device', 'adminora' ) . '</th><th scope="col">' . esc_html__( 'IP address', 'adminora' ) . '</th></tr></thead><tbody>';
		foreach ( $entries as $entry ) {
			$account = $entry['u'] ? get_userdata( $entry['u'] ) : false;
			$by      = ! empty( $entry['by'] ) ? get_userdata( $entry['by'] ) : false;

			echo '<tr><td>' . esc_html( wp_date( $format, $entry['t'] ) ) . '</td><td><span class="adminora-event adminora-event--' . esc_attr( $entry['e'] ) . '">' . esc_html( isset( $events[ $entry['e'] ] ) ? $events[ $entry['e'] ] : $entry['e'] ) . '</span>';
			if ( $by ) {
				/* translators: %s: administrator name. */
				echo '<small>' . esc_html( sprintf( __( 'by %s', 'adminora' ), $by->display_name ) ) . '</small>';
			}
			echo '</td><td>';
			if ( $account ) {
				echo '<a href="' . esc_url( self::url( 'history', array( 'who' => $account->user_login ) ) ) . '">' . esc_html( $account->display_name ) . '</a>';
			} else {
				/* translators: %s: username that was tried. */
				echo esc_html( '' !== $entry['n'] ? sprintf( __( '"%s" (no such account)', 'adminora' ), $entry['n'] ) : __( 'Deleted account', 'adminora' ) );
			}
			echo '</td><td>';
			if ( '' !== $entry['ua'] ) {
				self::device( $entry['ua'] );
			} else {
				echo '<span aria-hidden="true">&mdash;</span>';
			}
			echo '</td><td><code>' . esc_html( $entry['ip'] ) . '</code></td></tr>';
		}
		echo '</tbody></table>';

		if ( $pages > 1 ) {
			$links = paginate_links(
				array(
					'base'    => add_query_arg(
						'paged',
						'%#%',
						self::url(
							'history',
							array_filter(
								array(
									'event' => $event,
									'who'   => $who,
								)
							)
						)
					),
					'format'  => '',
					'current' => $page,
					'total'   => $pages,
				)
			);
			echo '<nav class="adminora-pagination" aria-label="' . esc_attr__( 'History pages', 'adminora' ) . '">' . wp_kses_post( $links ) . '</nav>';
		}
	}

	/**
	 * Blocked accounts and the block form.
	 *
	 * @return void
	 */
	private static function section_blocked() {
		$format = get_option( 'date_format' );

		echo '<p class="adminora-intro">' . esc_html__( 'Blocked accounts are signed out everywhere and cannot sign in, reset their password or use application passwords until you unblock them. Their content stays as it is.', 'adminora' ) . '</p>';

		self::form( 'user_block', array(), true, 'adminora-inline-form adminora-filter' );
		echo '<label for="adminora-block-user">' . esc_html__( 'Block an account', 'adminora' ) . '</label> <input type="text" id="adminora-block-user" name="who" class="regular-text" placeholder="' . esc_attr__( 'Username or email', 'adminora' ) . '" required> <button type="submit" class="button">' . esc_html__( 'Block', 'adminora' ) . '</button></form>';

		$blocked = Sessions::blocked();

		if ( ! $blocked ) {
			echo '<p>' . esc_html__( 'No accounts are blocked.', 'adminora' ) . '</p>';
			return;
		}

		echo '<table class="widefat striped adminora-sessions"><thead><tr><th scope="col">' . esc_html__( 'Account', 'adminora' ) . '</th><th scope="col">' . esc_html__( 'Blocked', 'adminora' ) . '</th><th scope="col">' . esc_html__( 'By', 'adminora' ) . '</th><th scope="col"><span class="screen-reader-text">' . esc_html__( 'Actions', 'adminora' ) . '</span></th></tr></thead><tbody>';
		foreach ( $blocked as $row ) {
			list( $user, $time, $by ) = $row;
			echo '<tr><td>';
			self::person( $user );
			echo '</td><td>' . esc_html( $time ? wp_date( $format, $time ) : '' ) . '</td><td>' . esc_html( $by ? $by->display_name : '' ) . '</td><td class="adminora-row-actions">';
			if ( current_user_can( 'edit_user', $user->ID ) ) {
				self::button( 'user_unblock', array( 'user' => $user->ID ), __( 'Unblock', 'adminora' ) );
			}
			echo '</td></tr>';
		}
		echo '</tbody></table>';
	}

	/**
	 * Session limit and history settings.
	 *
	 * @return void
	 */
	private static function section_settings() {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="adminora_save"><input type="hidden" name="tab" value="sessions">';
		wp_nonce_field( 'adminora_save' );

		echo '<h2 class="title">' . esc_html__( 'Session limit', 'adminora' ) . '</h2>';
		echo '<p class="adminora-intro">' . esc_html__( 'Each browser or app someone signs in on is one session. A limit stops one account being shared across many devices.', 'adminora' ) . '</p>';
		echo '<table class="form-table" role="presentation">';
		Fields::number( 'sessions.limit', __( 'Sessions per person', 'adminora' ), __( '0 means no limit.', 'adminora' ) );
		Fields::select(
			'sessions.limit_mode',
			__( 'When someone goes over', 'adminora' ),
			array(
				'oldest' => __( 'Sign out their oldest session', 'adminora' ),
				'block'  => __( 'Refuse the new sign-in', 'adminora' ),
			),
			__( '"Refuse" is stricter, but someone who loses a device then needs an administrator to sign it out.', 'adminora' )
		);
		Fields::roles( 'sessions.limit_roles', __( 'Apply to', 'adminora' ), __( 'Tick nothing to apply the limit to everyone.', 'adminora' ) );
		Fields::checkbox( 'sessions.exempt_admins', __( 'Administrators', 'adminora' ), __( 'No limit for people who can manage the site', 'adminora' ) );
		echo '</table>';

		echo '<h2 class="title">' . esc_html__( 'Sign-in history', 'adminora' ) . '</h2>';
		echo '<table class="form-table" role="presentation">';
		Fields::checkbox( 'sessions.history', __( 'Record sign-ins', 'adminora' ), __( 'Keep a history of sign-ins and sign-outs, with device and IP address', 'adminora' ), __( 'IP addresses are personal data in many countries. Mention this in your privacy policy if you keep it on.', 'adminora' ) );
		Fields::checkbox( 'sessions.history_failed', __( 'Failed sign-ins', 'adminora' ), __( 'Also record wrong passwords and unknown usernames', 'adminora' ) );
		echo '</table>';

		submit_button();
		echo '</form>';
	}

	// ------------------------------------------------------------------
	// Handlers.
	// ------------------------------------------------------------------

	/**
	 * Stops the request with a "not allowed" message.
	 *
	 * @return void
	 */
	private static function deny() {
		wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'adminora' ), '', array( 'response' => 403 ) );
	}

	/**
	 * Finds the account an action is for, by ID or by username or email, and
	 * stops unless the current user may edit it.
	 *
	 * @param int    $id  User ID, or 0.
	 * @param string $who Username or email, used when there is no ID.
	 * @return \WP_User
	 */
	private static function target_user( $id, $who ) {
		$user = $id ? get_userdata( $id ) : ( '' !== $who ? get_user_by( is_email( $who ) ? 'email' : 'login', $who ) : false );

		if ( $user && ! current_user_can( 'edit_user', $user->ID ) ) {
			self::deny();
		}

		if ( ! $user ) {
			self::back( 'blocked', 'user_not_found' );
		}

		return $user;
	}

	/**
	 * Redirects to the section, or to the Users screen when the action came from there.
	 *
	 * @param string $section Section.
	 * @param string $notice  Notice key.
	 * @return void
	 */
	private static function back( $section, $notice ) {
		// phpcs:ignore WordPress.Security.NonceVerification -- Only chooses where to go back to.
		if ( isset( $_REQUEST['back'] ) && 'users' === $_REQUEST['back'] ) {
			wp_safe_redirect( add_query_arg( 'adminora_notice', $notice, admin_url( 'users.php' ) ) );
			exit;
		}

		wp_safe_redirect( self::url( $section, array( 'adminora_notice' => $notice ) ) );
		exit;
	}

	/**
	 * Signs one device out.
	 *
	 * @return void
	 */
	public static function handle_session_end() {
		check_admin_referer( 'adminora_session_end' );

		if ( ! current_user_can( 'manage_options' ) ) {
			self::deny();
		}

		$user = self::target_user(
			isset( $_REQUEST['user'] ) ? absint( $_REQUEST['user'] ) : 0,
			isset( $_REQUEST['who'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['who'] ) ) : ''
		);

		$verifier = isset( $_POST['session'] ) ? sanitize_key( wp_unslash( $_POST['session'] ) ) : '';
		$sessions = Sessions::of( $user->ID );

		if ( isset( $sessions[ $verifier ] ) && Sessions::end( $user->ID, $verifier ) ) {
			Sessions::log( 'ended_admin', $user->ID, $sessions[ $verifier ] );
		}

		self::back( 'active', 'session_ended' );
	}

	/**
	 * Signs a user out everywhere (or, for yourself, everywhere else).
	 *
	 * @return void
	 */
	public static function handle_session_end_all() {
		check_admin_referer( 'adminora_session_end_all' );

		if ( ! current_user_can( 'manage_options' ) ) {
			self::deny();
		}

		$user = self::target_user(
			isset( $_REQUEST['user'] ) ? absint( $_REQUEST['user'] ) : 0,
			isset( $_REQUEST['who'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['who'] ) ) : ''
		);

		if ( get_current_user_id() === $user->ID ) {
			\WP_Session_Tokens::get_instance( $user->ID )->destroy_others( wp_get_session_token() );
		} else {
			Sessions::end_all( $user->ID );
		}

		Sessions::log( 'ended_admin', $user->ID );
		self::back( 'active', 'sessions_ended' );
	}

	/**
	 * Blocks an account.
	 *
	 * @return void
	 */
	public static function handle_user_block() {
		check_admin_referer( 'adminora_user_block' );

		if ( ! current_user_can( 'manage_options' ) ) {
			self::deny();
		}

		$user = self::target_user(
			isset( $_REQUEST['user'] ) ? absint( $_REQUEST['user'] ) : 0,
			isset( $_REQUEST['who'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['who'] ) ) : ''
		);

		if ( get_current_user_id() === $user->ID ) {
			wp_die( esc_html__( 'You cannot block your own account.', 'adminora' ), '', array( 'response' => 400 ) );
		}

		Sessions::block( $user->ID );
		self::back( 'blocked', 'user_blocked' );
	}

	/**
	 * Unblocks an account.
	 *
	 * @return void
	 */
	public static function handle_user_unblock() {
		check_admin_referer( 'adminora_user_unblock' );

		if ( ! current_user_can( 'manage_options' ) ) {
			self::deny();
		}

		$user = self::target_user(
			isset( $_REQUEST['user'] ) ? absint( $_REQUEST['user'] ) : 0,
			isset( $_REQUEST['who'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['who'] ) ) : ''
		);

		Sessions::unblock( $user->ID );
		self::back( 'blocked', 'user_unblocked' );
	}

	/**
	 * Empties the sign-in history.
	 *
	 * @return void
	 */
	public static function handle_history_clear() {
		check_admin_referer( 'adminora_history_clear' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'adminora' ), '', array( 'response' => 403 ) );
		}

		Sessions::clear_history();
		self::back( 'history', 'history_cleared' );
	}

	/**
	 * Downloads the sign-in history.
	 *
	 * @return void
	 */
	public static function handle_history_csv() {
		check_admin_referer( 'adminora_history_csv' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'adminora' ), '', array( 'response' => 403 ) );
		}

		$events = Sessions::events();
		$format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
		$rows   = array( array( 'time', 'event', 'account', 'username tried', 'device', 'ip', 'by' ) );

		foreach ( Sessions::history() as $entry ) {
			$account = $entry['u'] ? get_userdata( $entry['u'] ) : false;
			$by      = ! empty( $entry['by'] ) ? get_userdata( $entry['by'] ) : false;
			$rows[]  = array(
				wp_date( $format, $entry['t'] ),
				isset( $events[ $entry['e'] ] ) ? $events[ $entry['e'] ] : $entry['e'],
				$account ? $account->user_login : '',
				$entry['n'],
				'' !== $entry['ua'] ? Device::parse( $entry['ua'] )['label'] : '',
				$entry['ip'],
				$by ? $by->user_login : '',
			);
		}

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=adminora-sign-ins-' . gmdate( 'Y-m-d' ) . '.csv' );

		foreach ( $rows as $row ) {
			// Quote every field and neutralise spreadsheet formulas.
			$cells = array_map(
				static function ( $cell ) {
					$cell = (string) $cell;
					if ( preg_match( '/^[=+\-@]/', $cell ) ) {
						$cell = "'" . $cell;
					}
					return '"' . str_replace( '"', '""', $cell ) . '"';
				},
				$row
			);

			echo implode( ',', $cells ) . "\r\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV download, every field quoted above.
		}
		exit;
	}

	/**
	 * Result notice on the Users screen after a block or sign-out from there.
	 *
	 * @return void
	 */
	public static function users_notice() {
		global $pagenow;

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display only.
		$notice = isset( $_GET['adminora_notice'] ) ? sanitize_key( wp_unslash( $_GET['adminora_notice'] ) ) : '';

		$messages = array(
			'sessions_ended' => __( 'Signed out everywhere.', 'adminora' ),
			'user_blocked'   => __( 'Account blocked and signed out everywhere.', 'adminora' ),
			'user_unblocked' => __( 'Account unblocked. They can sign in again.', 'adminora' ),
		);

		if ( 'users.php' === $pagenow && isset( $messages[ $notice ] ) ) {
			echo '<div class="notice notice-success is-dismissible adminora-notice"><p>' . esc_html( $messages[ $notice ] ) . '</p></div>';
		}
	}
}

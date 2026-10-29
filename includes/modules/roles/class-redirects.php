<?php
/**
 * Login destinations: where a role or a single person lands after logging in.
 *
 * A destination is stored as a short token: "dashboard", "profile", "home",
 * "admin:edit.php", "page:12" or "url:https://…". Addresses saved by earlier
 * versions (a plain URL) still work.
 *
 * @package Adminora
 */

namespace Adminora\Modules\Roles;

defined( 'ABSPATH' ) || exit;

/**
 * Login destinations.
 */
class Redirects {

	const META = 'adminora_login_redirect';

	/**
	 * Hooks for the per-person setting on the profile screen.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'show_user_profile', array( __CLASS__, 'profile' ), 21 );
		add_action( 'edit_user_profile', array( __CLASS__, 'profile' ), 21 );
		add_action( 'personal_options_update', array( __CLASS__, 'save_profile' ) );
		add_action( 'edit_user_profile_update', array( __CLASS__, 'save_profile' ) );
	}

	/**
	 * Destination choices: value => label. "page" and "url" take a second value.
	 *
	 * @return array
	 */
	public static function choices() {
		return array(
			''                              => __( 'Default (the dashboard, or the page they asked for)', 'adminora' ),
			'dashboard'                     => __( 'Dashboard home', 'adminora' ),
			'profile'                       => __( 'Their profile', 'adminora' ),
			'home'                          => __( 'Site home page', 'adminora' ),
			'admin:edit.php'                => __( 'Posts', 'adminora' ),
			'admin:post-new.php'            => __( 'Add a new post', 'adminora' ),
			'admin:edit.php?post_type=page' => __( 'Pages', 'adminora' ),
			'admin:upload.php'              => __( 'Media', 'adminora' ),
			'admin:edit-comments.php'       => __( 'Comments', 'adminora' ),
			'admin:users.php'               => __( 'Users', 'adminora' ),
			'page'                          => __( 'A page on this site…', 'adminora' ),
			'url'                           => __( 'Another address…', 'adminora' ),
		);
	}

	/**
	 * Splits a token into its parts for the form.
	 *
	 * @param string $token Stored destination.
	 * @return array { type, page, url }
	 */
	public static function parse( $token ) {
		$token = (string) $token;
		$out   = array(
			'type' => '',
			'page' => 0,
			'url'  => '',
		);

		if ( preg_match( '#^https?://#i', $token ) ) {
			$out['type'] = 'url';
			$out['url']  = $token;
		} elseif ( 0 === strpos( $token, 'url:' ) ) {
			$out['type'] = 'url';
			$out['url']  = substr( $token, 4 );
		} elseif ( 0 === strpos( $token, 'page:' ) ) {
			$out['type'] = 'page';
			$out['page'] = absint( substr( $token, 5 ) );
		} elseif ( isset( self::choices()[ $token ] ) ) {
			$out['type'] = $token;
		}

		return $out;
	}

	/**
	 * Builds a clean token from posted form parts, or cleans a token.
	 *
	 * @param array|string $value Posted { type, page, url } or a token.
	 * @return string
	 */
	public static function clean( $value ) {
		if ( ! is_array( $value ) ) {
			$value = self::parse( $value );
		}

		$type = isset( $value['type'] ) ? sanitize_text_field( $value['type'] ) : '';

		if ( 'page' === $type ) {
			$page = isset( $value['page'] ) ? absint( $value['page'] ) : 0;
			return $page && 'page' === get_post_type( $page ) ? 'page:' . $page : '';
		}

		if ( 'url' === $type ) {
			$url = isset( $value['url'] ) ? esc_url_raw( trim( (string) $value['url'] ) ) : '';
			return '' !== $url ? 'url:' . $url : '';
		}

		return isset( self::choices()[ $type ] ) ? $type : '';
	}

	/**
	 * The address a token points to, or '' when it has none.
	 *
	 * @param string $token Stored destination.
	 * @return string
	 */
	public static function url( $token ) {
		$parts = self::parse( $token );

		switch ( $parts['type'] ) {
			case 'dashboard':
				return admin_url();
			case 'profile':
				return admin_url( 'profile.php' );
			case 'home':
				return home_url( '/' );
			case 'page':
				return $parts['page'] && 'publish' === get_post_status( $parts['page'] ) ? (string) get_permalink( $parts['page'] ) : '';
			case 'url':
				return $parts['url'];
			default:
				return 0 === strpos( $parts['type'], 'admin:' ) ? admin_url( substr( $parts['type'], 6 ) ) : '';
		}
	}

	/**
	 * Short description of a token, for lists.
	 *
	 * @param string $token Stored destination.
	 * @return string
	 */
	public static function describe( $token ) {
		$parts = self::parse( $token );

		if ( 'page' === $parts['type'] ) {
			return get_the_title( $parts['page'] );
		}

		if ( 'url' === $parts['type'] ) {
			return $parts['url'];
		}

		$choices = self::choices();

		return isset( $choices[ $parts['type'] ] ) ? $choices[ $parts['type'] ] : '';
	}

	/**
	 * Prints the destination picker.
	 *
	 * @param string $name  Field name; the parts post as name[type], name[page], name[url].
	 * @param string $token Current destination.
	 * @param string $label Accessible label.
	 * @return void
	 */
	public static function field( $name, $token, $label ) {
		$parts = self::parse( $token );
		$id    = 'adminora-' . sanitize_html_class( str_replace( array( '[', ']' ), '-', $name ) );

		echo '<span class="adminora-destination">';
		echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name . '[type]' ) . '" aria-label="' . esc_attr( $label ) . '">';
		foreach ( self::choices() as $value => $text ) {
			echo '<option value="' . esc_attr( $value ) . '"' . selected( $parts['type'], $value, false ) . '>' . esc_html( $text ) . '</option>';
		}
		echo '</select>';

		echo '<span data-adminora-show-if="' . esc_attr( $name . '[type]' ) . '" data-adminora-show-value="page"' . ( 'page' === $parts['type'] ? '' : ' hidden' ) . '>';
		$pages = wp_dropdown_pages(
			array(
				'name'              => esc_attr( $name . '[page]' ),
				'id'                => esc_attr( $id . '-page' ),
				'selected'          => (int) $parts['page'],
				'show_option_none'  => esc_html__( 'Choose a page', 'adminora' ),
				'option_none_value' => '0',
				'echo'              => 0,
			)
		);
		echo wp_kses(
			(string) $pages,
			array(
				'select' => array(
					'name'  => true,
					'id'    => true,
					'class' => true,
				),
				'option' => array(
					'value'    => true,
					'selected' => true,
					'class'    => true,
				),
			)
		);
		echo '</span>';

		echo '<span data-adminora-show-if="' . esc_attr( $name . '[type]' ) . '" data-adminora-show-value="url"' . ( 'url' === $parts['type'] ? '' : ' hidden' ) . '>';
		echo '<input type="url" class="regular-text" name="' . esc_attr( $name . '[url]' ) . '" value="' . esc_attr( $parts['url'] ) . '" placeholder="https://" aria-label="' . esc_attr( $label . ': ' . __( 'Address', 'adminora' ) ) . '">';
		echo '</span></span>';
	}

	/**
	 * People who have a destination of their own.
	 *
	 * @return array List of [ user, token ].
	 */
	public static function users() {
		$out   = array();
		$users = get_users(
			array(
				'meta_key' => self::META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Small admin-only list.
				'number'   => 200,
			)
		);

		foreach ( $users as $user ) {
			$out[] = array( $user, (string) get_user_meta( $user->ID, self::META, true ) );
		}

		return $out;
	}

	/**
	 * Per-person destination on the profile screen, for administrators.
	 *
	 * @param \WP_User $user User being edited.
	 * @return void
	 */
	public static function profile( $user ) {
		if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'edit_user', $user->ID ) ) {
			return;
		}

		echo '<h2 id="adminora-login-redirect">' . esc_html__( 'After logging in', 'adminora' ) . '</h2>';
		wp_nonce_field( 'adminora_redirect_' . $user->ID, '_adminora_redirect', false );
		echo '<table class="form-table" role="presentation"><tr><th scope="row">' . esc_html__( 'Go to', 'adminora' ) . '</th><td>';
		self::field( 'adminora_login_redirect', (string) get_user_meta( $user->ID, self::META, true ), __( 'After logging in, go to', 'adminora' ) );
		echo '<p class="description">' . esc_html__( 'Overrides the destination set for their role under Settings → Adminora → Roles & permissions → Login and access. "Default" uses the role\'s setting.', 'adminora' ) . '</p>';
		echo '</td></tr></table>';
	}

	/**
	 * Saves the per-person destination.
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	public static function save_profile( $user_id ) {
		if ( ! isset( $_POST['_adminora_redirect'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_adminora_redirect'] ) ), 'adminora_redirect_' . $user_id ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Cleaned by clean().
		$token = self::clean( isset( $_POST['adminora_login_redirect'] ) ? (array) wp_unslash( $_POST['adminora_login_redirect'] ) : array() );

		if ( '' === $token ) {
			delete_user_meta( $user_id, self::META );
		} else {
			update_user_meta( $user_id, self::META, $token );
		}
	}
}

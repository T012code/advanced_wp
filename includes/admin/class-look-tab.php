<?php
/**
 * Settings → Admin Studio → Dashboard look.
 *
 * @package AdminStudio
 */

namespace AdminStudio\Admin;

use AdminStudio\Color;
use AdminStudio\Settings;
use AdminStudio\Modules\Admin_Look\Admin_Look;
use AdminStudio\Modules\Admin_Look\Icons;
use AdminStudio\Modules\Roles\Audit;
use AdminStudio\Modules\Roles\Role_Manager;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the dashboard look tab and handles its forms.
 */
class Look_Tab {

	/**
	 * Hooks for form handlers.
	 *
	 * @return void
	 */
	public static function init() {
		foreach ( array( 'look_save', 'look_apply', 'look_stop' ) as $action ) {
			add_action( 'admin_post_adminstudio_' . $action, array( __CLASS__, 'handle_' . $action ) );
		}
	}

	/**
	 * Sections of the tab.
	 *
	 * @return array
	 */
	private static function sections() {
		return array(
			'templates' => __( 'Templates', 'admin-studio' ),
			'roles'     => __( 'Roles and users', 'admin-studio' ),
			'menu'      => __( 'Sidebar menu', 'admin-studio' ),
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
		return Admin::url( 'look', array_merge( array( 'section' => $section ), $args ) );
	}

	/**
	 * Redirects back with a notice.
	 *
	 * @param string $section Section.
	 * @param string $notice  Notice key.
	 * @return void
	 */
	private static function back( $section, $notice ) {
		wp_safe_redirect( self::url( $section, array( 'adminstudio_notice' => $notice ) ) );
		exit;
	}

	/**
	 * Stops the request with a "not allowed" message.
	 *
	 * @return void
	 */
	private static function deny() {
		wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'admin-studio' ), '', array( 'response' => 403 ) );
	}

	/**
	 * Opens the save form for a section.
	 *
	 * @param string $group Section being saved.
	 * @return void
	 */
	private static function form( $group ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="adminstudio_look_save">';
		echo '<input type="hidden" name="group" value="' . esc_attr( $group ) . '">';
		wp_nonce_field( 'adminstudio_look_save' );
	}

	/**
	 * Prints a select.
	 *
	 * @param string      $name    Field name.
	 * @param array       $options Value => label, or value => [ label, ... ].
	 * @param string      $value   Selected value.
	 * @param string|null $placeholder   Label for an empty first option.
	 * @param string      $label   Accessible label.
	 * @return void
	 */
	private static function select( $name, array $options, $value, $placeholder = null, $label = '' ) {
		echo '<select name="' . esc_attr( $name ) . '"' . ( '' !== $label ? ' aria-label="' . esc_attr( $label ) . '"' : '' ) . '>';
		if ( null !== $placeholder ) {
			echo '<option value="">' . esc_html( $placeholder ) . '</option>';
		}
		foreach ( $options as $key => $option ) {
			echo '<option value="' . esc_attr( $key ) . '"' . selected( (string) $value, (string) $key, false ) . '>' . esc_html( is_array( $option ) ? $option[0] : $option ) . '</option>';
		}
		echo '</select>';
	}

	/**
	 * Renders the tab.
	 *
	 * @return void
	 */
	public static function render() {
		if ( ! Settings::module_on( 'admin_look' ) ) {
			echo '<div class="notice notice-warning inline adminstudio-notice"><p>' . esc_html__( 'Dashboard look is switched off on the Overview tab.', 'admin-studio' ) . '</p></div>';
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Navigation only.
		$section = isset( $_GET['section'] ) ? sanitize_key( wp_unslash( $_GET['section'] ) ) : 'templates';
		$section = array_key_exists( $section, self::sections() ) ? $section : 'templates';

		echo '<nav class="adminstudio-subnav" aria-label="' . esc_attr__( 'Dashboard look sections', 'admin-studio' ) . '"><ul>';
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
	 * Inline colour variables for a template thumbnail.
	 *
	 * @param string $template Template key.
	 * @param string $palette  Palette key.
	 * @return string
	 */
	private static function thumb_style( $template, $palette ) {
		if ( 'default' === $template ) {
			return '--t-side:#1d2327;--t-side-text:#c3c4c7;--t-accent:#2271b1;--t-bg:#f0f0f1;--t-card:#ffffff;--t-line:#dcdcde';
		}

		list( $light ) = Admin_Look::colors(
			array(
				'template' => $template,
				'palette'  => $palette,
			)
		);

		$vars = array(
			'side'      => $light['sidebar'],
			'side-text' => $light['sidebar-muted'],
			'accent'    => 'minimal' === $template ? $light['accent-soft'] : $light['accent'],
			'bg'        => $light['bg'],
			'card'      => $light['surface'],
			'line'      => $light['border'],
		);

		$css = array();
		foreach ( $vars as $name => $value ) {
			$css[] = '--t-' . $name . ':' . Color::to_hex( $value );
		}

		return implode( ';', $css );
	}

	/**
	 * Templates, the site default and custom colours.
	 *
	 * @return void
	 */
	private static function section_templates() {
		$config  = Admin_Look::config();
		$current = Admin_Look::resolve( wp_get_current_user() );

		echo '<p class="adminstudio-intro">' . esc_html__( 'Pick the look everyone gets unless their role or profile says otherwise. "Try it on me" switches only your own dashboard so you can look around first.', 'admin-studio' ) . '</p>';

		self::form( 'templates' );

		echo '<h2 class="title">' . esc_html__( 'Template', 'admin-studio' ) . '</h2>';
		echo '<div class="adminstudio-templates" role="radiogroup" aria-label="' . esc_attr__( 'Site template', 'admin-studio' ) . '">';
		foreach ( Admin_Look::templates() as $key => $template ) {
			$is_on = $config['default']['template'] === $key;
			echo '<div class="adminstudio-template' . ( $is_on ? ' is-selected' : '' ) . '">';
			echo '<label class="adminstudio-template__pick">';
			echo '<input type="radio" name="default[template]" value="' . esc_attr( $key ) . '"' . checked( $is_on, true, false ) . '>';
			echo '<span class="adminstudio-thumb adminstudio-thumb--' . esc_attr( $key ) . '" style="' . esc_attr( self::thumb_style( $key, $config['default']['palette'] ) ) . '" aria-hidden="true"><i></i><b></b><b></b><b></b></span>';
			echo '<strong>' . esc_html( $template[0] ) . '</strong>';
			echo '<small>' . esc_html( $template[1] ) . '</small>';
			echo '</label>';
			if ( 'default' !== $key ) {
				$trying = 'try' === $current['source'] && $current['template'] === $key;
				echo '<button type="submit" class="button button-small" name="try" value="' . esc_attr( $key ) . '"' . disabled( $trying, true, false ) . '>' . esc_html( $trying ? __( 'Trying now', 'admin-studio' ) : __( 'Try it on me', 'admin-studio' ) ) . '</button>';
			}
			echo '</div>';
		}
		echo '</div>';

		echo '<table class="form-table" role="presentation">';

		echo '<tr><th scope="row">' . esc_html__( 'Colour palette', 'admin-studio' ) . '</th><td><div class="adminstudio-palettes">';
		foreach ( Admin_Look::palettes() as $key => $palette ) {
			$swatch = '' !== $palette[1] ? $palette[1] : Admin_Look::colors(
				array(
					'template' => 'studio',
					'palette'  => $key,
				)
			)[0]['accent'];
			echo '<label class="adminstudio-palette"><input type="radio" name="default[palette]" value="' . esc_attr( $key ) . '"' . checked( $config['default']['palette'], $key, false ) . '>';
			echo '<span class="adminstudio-palette__dot" style="' . esc_attr( '--dot:' . Color::to_hex( $swatch ) ) . '" aria-hidden="true"></span> ' . esc_html( $palette[0] ) . '</label>';
		}
		echo '</div><p class="description">' . esc_html__( 'Brand kit uses your theme\'s main colour. Dark mode adjusts every palette automatically.', 'admin-studio' ) . '</p></td></tr>';

		echo '<tr data-adminstudio-show-if="default[palette]" data-adminstudio-show-value="custom"><th scope="row">' . esc_html__( 'Custom colours', 'admin-studio' ) . '</th><td><div class="adminstudio-colors">';
		$labels = array(
			'accent'     => __( 'Accent', 'admin-studio' ),
			'sidebar'    => __( 'Sidebar', 'admin-studio' ),
			'background' => __( 'Page background', 'admin-studio' ),
			'text'       => __( 'Text', 'admin-studio' ),
		);
		foreach ( $labels as $key => $label ) {
			echo '<label>' . esc_html( $label ) . '<br><input type="text" class="regular-text adminstudio-hex" name="custom[' . esc_attr( $key ) . ']" value="' . esc_attr( $config['custom'][ $key ] ) . '" placeholder="#2a5bea" pattern="#[0-9a-fA-F]{3,6}"></label>';
		}
		echo '</div><p class="description">' . esc_html__( 'Leave a colour empty to have it worked out from the accent. Text colours are checked for contrast automatically.', 'admin-studio' ) . '</p></td></tr>';

		echo '<tr><th scope="row">' . esc_html__( 'Personal choice', 'admin-studio' ) . '</th><td><label><input type="checkbox" name="self_choice" value="1"' . checked( $config['self_choice'], true, false ) . '> ' . esc_html__( 'Let users pick their own template on their profile', 'admin-studio' ) . '</label><p class="description">' . esc_html__( 'You can still lock a role or a single user to a template.', 'admin-studio' ) . '</p></td></tr>';

		echo '</table>';

		submit_button();
		echo '</form>';
	}

	/**
	 * Template per role, and a list of users with their own setting.
	 *
	 * @return void
	 */
	private static function section_roles() {
		$config    = Admin_Look::config();
		$templates = Admin_Look::templates();
		$palettes  = Admin_Look::palettes();

		echo '<p class="adminstudio-intro">' . esc_html__( 'Give each role its own template. Locking stops people in that role from choosing a different one on their profile. To set a template for one person, open their profile under Users.', 'admin-studio' ) . '</p>';

		self::form( 'roles' );
		echo '<table class="widefat striped adminstudio-rule-table"><thead><tr><th scope="col">' . esc_html__( 'Role', 'admin-studio' ) . '</th><th scope="col">' . esc_html__( 'Template', 'admin-studio' ) . '</th><th scope="col">' . esc_html__( 'Palette', 'admin-studio' ) . '</th><th scope="col">' . esc_html__( 'Lock', 'admin-studio' ) . '</th></tr></thead><tbody>';
		foreach ( Role_Manager::roles() as $slug => $role ) {
			$choice = isset( $config['roles'][ $slug ] ) ? $config['roles'][ $slug ] : array(
				'template' => '',
				'palette'  => 'brand',
				'lock'     => false,
			);
			echo '<tr><th scope="row">' . esc_html( $role['name'] ) . '</th><td>';
			self::select( 'roles[' . $slug . '][template]', $templates, $choice['template'], __( 'Site default', 'admin-studio' ), $role['name'] . ': ' . __( 'Template', 'admin-studio' ) );
			echo '</td><td>';
			self::select( 'roles[' . $slug . '][palette]', $palettes, $choice['palette'], null, $role['name'] . ': ' . __( 'Palette', 'admin-studio' ) );
			echo '</td><td><input type="checkbox" name="roles[' . esc_attr( $slug ) . '][lock]" value="1"' . checked( ! empty( $choice['lock'] ), true, false ) . ' aria-label="' . esc_attr( $role['name'] . ': ' . __( 'Lock', 'admin-studio' ) ) . '"></td></tr>';
		}
		echo '</tbody></table>';
		submit_button();
		echo '</form>';

		$users = get_users(
			array(
				'meta_key' => Admin_Look::META_ADMIN, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Small admin-only list.
				'number'   => 50,
				'fields'   => array( 'ID', 'display_name' ),
			)
		);

		echo '<h2 class="title">' . esc_html__( 'People with their own setting', 'admin-studio' ) . '</h2>';
		if ( ! $users ) {
			echo '<p>' . esc_html__( 'Nobody yet. Open a user\'s profile to give them a template of their own.', 'admin-studio' ) . '</p>';
			return;
		}

		echo '<ul class="adminstudio-list">';
		foreach ( $users as $user ) {
			$resolved = Admin_Look::resolve( get_userdata( $user->ID ) );
			printf(
				'<li><a href="%s">%s</a>: %s%s</li>',
				esc_url( get_edit_user_link( $user->ID ) . '#adminstudio-look' ),
				esc_html( $user->display_name ),
				esc_html( $templates[ $resolved['template'] ][0] ),
				$resolved['locked'] ? ' (' . esc_html__( 'locked', 'admin-studio' ) . ')' : ''
			);
		}
		echo '</ul>';
	}

	/**
	 * Sidebar sections, labels, icons and order.
	 *
	 * @return void
	 */
	private static function section_menu() {
		$config   = Admin_Look::config();
		$sections = Admin_Look::sections();
		$top      = Admin_Look::top_items();
		$slugs    = Admin_Look::sort_slugs( array_keys( $top ) );
		$icons    = array_keys( Icons::all() );

		$icon_options = array(
			''    => __( 'Automatic', 'admin-studio' ),
			'own' => __( 'Keep the plugin\'s own icon', 'admin-studio' ),
		);
		foreach ( $icons as $icon ) {
			$icon_options[ $icon ] = ucfirst( str_replace( '-', ' ', $icon ) );
		}

		echo '<p class="adminstudio-intro">' . esc_html__( 'Group the sidebar into sections, rename items, change their icons and drag them into order. This applies to every admin template; "WordPress default" keeps the standard menu. Which items each role can see is set under Roles & permissions → Menu access.', 'admin-studio' ) . '</p>';

		self::form( 'menu' );

		echo '<h2 class="title">' . esc_html__( 'Sections', 'admin-studio' ) . '</h2>';
		echo '<ol class="adminstudio-sortable adminstudio-section-list" data-adminstudio-sortable>';
		foreach ( $sections as $id => $label ) {
			self::section_row( $id, $label );
		}
		echo '</ol>';
		echo '<p><label for="adminstudio-new-section">' . esc_html__( 'Add a section', 'admin-studio' ) . '</label><br><input type="text" id="adminstudio-new-section" class="regular-text" name="new_section" placeholder="' . esc_attr__( 'For example: Marketing', 'admin-studio' ) . '"></p>';

		echo '<h2 class="title">' . esc_html__( 'Menu items', 'admin-studio' ) . '</h2>';
		echo '<ol class="adminstudio-sortable adminstudio-item-list" data-adminstudio-sortable>';
		foreach ( $slugs as $slug ) {
			if ( ! isset( $top[ $slug ] ) || '' === $top[ $slug ][0] ) {
				continue;
			}

			$item = isset( $config['items'][ $slug ] ) ? $config['items'][ $slug ] : array();
			$name = $top[ $slug ][0];
			$icon = isset( $item['icon'] ) ? $item['icon'] : '';
			$auto = '' !== $icon && 'own' !== $icon ? $icon : Icons::default_for( $slug );

			echo '<li class="adminstudio-sortable__row" draggable="true">';
			echo '<input type="hidden" name="order[]" value="' . esc_attr( $slug ) . '">';
			self::move_buttons( $name );
			echo '<span class="adminstudio-item-icon" aria-hidden="true">' . wp_kses( '' !== $auto ? Icons::svg( $auto, 18 ) : '', Icons::kses() ) . '</span>';
			echo '<input type="text" name="items[' . esc_attr( $slug ) . '][label]" value="' . esc_attr( isset( $item['label'] ) ? $item['label'] : '' ) . '" placeholder="' . esc_attr( $name ) . '" aria-label="' . esc_attr( $name . ': ' . __( 'Label', 'admin-studio' ) ) . '">';
			self::select( 'items[' . $slug . '][section]', $sections, Admin_Look::section_of( $slug ), null, $name . ': ' . __( 'Section', 'admin-studio' ) );
			self::select( 'items[' . $slug . '][icon]', $icon_options, $icon, null, $name . ': ' . __( 'Icon', 'admin-studio' ) );
			echo '</li>';
		}
		echo '</ol>';

		echo '<p class="submit">';
		submit_button( __( 'Save menu', 'admin-studio' ), 'primary', 'submit', false );
		echo ' ';
		submit_button( __( 'Reset to the standard layout', 'admin-studio' ), 'secondary', 'reset_menu', false, array( 'data-adminstudio-confirm-click' => '1' ) );
		echo '</p></form>';
	}

	/**
	 * One row of the section list.
	 *
	 * @param string $id    Section id.
	 * @param string $label Section label.
	 * @return void
	 */
	private static function section_row( $id, $label ) {
		echo '<li class="adminstudio-sortable__row" draggable="true">';
		self::move_buttons( $label );
		echo '<input type="text" name="sections[' . esc_attr( $id ) . ']" value="' . esc_attr( $label ) . '" aria-label="' . esc_attr__( 'Section name', 'admin-studio' ) . '">';
		echo '<label class="adminstudio-sortable__remove"><input type="checkbox" name="remove_sections[]" value="' . esc_attr( $id ) . '"> ' . esc_html__( 'Remove', 'admin-studio' ) . '</label>';
		echo '</li>';
	}

	/**
	 * Drag handle and up/down buttons for a sortable row.
	 *
	 * @param string $name Row name for screen readers.
	 * @return void
	 */
	private static function move_buttons( $name ) {
		echo '<span class="adminstudio-sortable__handle" aria-hidden="true"></span>';
		/* translators: %s: item name. */
		echo '<button type="button" class="button-link adminstudio-sortable__up" data-adminstudio-move="-1" aria-label="' . esc_attr( sprintf( __( 'Move %s up', 'admin-studio' ), $name ) ) . '">&uarr;</button>';
		/* translators: %s: item name. */
		echo '<button type="button" class="button-link adminstudio-sortable__down" data-adminstudio-move="1" aria-label="' . esc_attr( sprintf( __( 'Move %s down', 'admin-studio' ), $name ) ) . '">&darr;</button>';
	}

	/**
	 * Saves one section of the tab, or starts a trial.
	 *
	 * @return void
	 */
	public static function handle_look_save() {
		check_admin_referer( 'adminstudio_look_save' );

		if ( ! current_user_can( 'manage_options' ) ) {
			self::deny();
		}

		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Values are cleaned here and in Admin_Look::save().
		$group = isset( $_POST['group'] ) ? sanitize_key( wp_unslash( $_POST['group'] ) ) : '';

		if ( 'templates' === $group ) {
			$default = isset( $_POST['default'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['default'] ) ) : array();
			$custom  = isset( $_POST['custom'] ) ? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['custom'] ) ) : array();

			if ( isset( $_POST['try'] ) ) {
				$try = Admin_Look::clean_choice(
					array(
						'template' => sanitize_key( wp_unslash( $_POST['try'] ) ),
						'palette'  => isset( $default['palette'] ) ? $default['palette'] : 'brand',
					)
				);
				Admin_Look::save( array( 'custom' => $custom ) );
				update_user_meta( get_current_user_id(), Admin_Look::META_TRY, $try );
				wp_safe_redirect( admin_url( 'index.php' ) );
				exit;
			}

			Admin_Look::save(
				array(
					'default'     => $default,
					'custom'      => $custom,
					'self_choice' => ! empty( $_POST['self_choice'] ),
				)
			);

			$templates = Admin_Look::templates();
			$saved     = Admin_Look::config()['default']['template'];
			/* translators: %s: template name. */
			Audit::log( 'look', sprintf( __( 'Site template set to %s.', 'admin-studio' ), $templates[ $saved ][0] ) );
			self::back( 'templates', 'saved' );
		}

		if ( 'roles' === $group ) {
			$roles = array();
			foreach ( isset( $_POST['roles'] ) ? (array) wp_unslash( $_POST['roles'] ) : array() as $role => $choice ) {
				$choice = array_map( 'sanitize_key', (array) $choice );
				if ( ! empty( $choice['template'] ) || ! empty( $choice['lock'] ) ) {
					$roles[ sanitize_key( $role ) ] = $choice;
				}
			}

			Admin_Look::save( array( 'roles' => $roles ) );
			Audit::log( 'look', __( 'Templates per role updated.', 'admin-studio' ) );
			self::back( 'roles', 'saved' );
		}

		if ( 'menu' === $group ) {
			if ( isset( $_POST['reset_menu'] ) ) {
				Admin_Look::save(
					array(
						'sections' => array(),
						'items'    => array(),
					)
				);
				Audit::log( 'look', __( 'Sidebar menu reset to the standard layout.', 'admin-studio' ) );
				self::back( 'menu', 'saved' );
			}

			$remove   = isset( $_POST['remove_sections'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['remove_sections'] ) ) : array();
			$sections = array();
			foreach ( isset( $_POST['sections'] ) ? (array) wp_unslash( $_POST['sections'] ) : array() as $id => $label ) {
				$id = sanitize_key( $id );
				if ( ! in_array( $id, $remove, true ) ) {
					$sections[ $id ] = sanitize_text_field( $label );
				}
			}

			$new = isset( $_POST['new_section'] ) ? sanitize_text_field( wp_unslash( $_POST['new_section'] ) ) : '';
			if ( '' !== $new ) {
				$id = sanitize_key( sanitize_title( $new ) );
				$id = '' === $id || isset( $sections[ $id ] ) ? 'section-' . ( count( $sections ) + 1 ) : $id;

				$sections[ $id ] = $new;
			}

			if ( ! $sections ) {
				$sections = Admin_Look::default_sections();
			}

			$posted = isset( $_POST['items'] ) ? (array) wp_unslash( $_POST['items'] ) : array();
			$order  = isset( $_POST['order'] ) ? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['order'] ) ) : array();
			$items  = array();

			foreach ( $order as $position => $slug ) {
				$item = isset( $posted[ $slug ] ) ? (array) $posted[ $slug ] : array();

				$items[ $slug ] = array(
					'section' => isset( $item['section'] ) && isset( $sections[ sanitize_key( $item['section'] ) ] ) ? sanitize_key( $item['section'] ) : '',
					'label'   => isset( $item['label'] ) ? sanitize_text_field( $item['label'] ) : '',
					'icon'    => isset( $item['icon'] ) ? sanitize_key( $item['icon'] ) : '',
					'order'   => (int) $position,
				);
			}
			// phpcs:enable

			Admin_Look::save(
				array(
					'sections' => $sections,
					'items'    => $items,
				)
			);
			Audit::log( 'look', __( 'Sidebar menu layout updated.', 'admin-studio' ) );
			self::back( 'menu', 'saved' );
		}

		self::back( 'templates', 'saved' );
	}

	/**
	 * Makes the template being tried the site default.
	 *
	 * @return void
	 */
	public static function handle_look_apply() {
		check_admin_referer( 'adminstudio_look_apply' );

		if ( ! current_user_can( 'manage_options' ) ) {
			self::deny();
		}

		$try = get_user_meta( get_current_user_id(), Admin_Look::META_TRY, true );

		if ( is_array( $try ) && ! empty( $try['template'] ) ) {
			$try = Admin_Look::clean_choice( $try );
			Admin_Look::save(
				array(
					'default' => array(
						'template' => $try['template'],
						'palette'  => $try['palette'],
					),
				)
			);

			$templates = Admin_Look::templates();
			/* translators: %s: template name. */
			Audit::log( 'look', sprintf( __( 'Site template set to %s.', 'admin-studio' ), $templates[ $try['template'] ][0] ) );
		}

		delete_user_meta( get_current_user_id(), Admin_Look::META_TRY );
		self::back( 'templates', 'saved' );
	}

	/**
	 * Ends a trial.
	 *
	 * @return void
	 */
	public static function handle_look_stop() {
		check_admin_referer( 'adminstudio_look_stop' );

		if ( ! current_user_can( 'manage_options' ) ) {
			self::deny();
		}
		delete_user_meta( get_current_user_id(), Admin_Look::META_TRY );
		self::back( 'templates', 'look_stopped' );
	}
}

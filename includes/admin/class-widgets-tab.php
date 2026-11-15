<?php
/**
 * Settings → Layò Dashboard Toolkit → Dashboard widgets.
 *
 * @package LayoDT
 */

namespace LayoDT\Admin;

use LayoDT\Settings;
use LayoDT\Modules\Admin_Look\Icons;
use LayoDT\Modules\Roles\Audit;
use LayoDT\Modules\Roles\Role_Manager;
use LayoDT\Modules\Widgets\Widgets;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the dashboard widgets tab and handles its forms.
 */
class Widgets_Tab {

	/**
	 * Hooks for form handlers.
	 *
	 * @return void
	 */
	public static function init() {
		foreach ( array( 'widgets_visibility', 'widget_save', 'widget_delete', 'widgets_layout' ) as $action ) {
			add_action( 'admin_post_layodt_' . $action, array( __CLASS__, 'handle_' . $action ) );
		}
	}

	/**
	 * Sections of the tab.
	 *
	 * @return array
	 */
	private static function sections() {
		return array(
			'visibility' => __( 'Show and hide', 'layo-dashboard-toolkit' ),
			'custom'     => __( 'Custom widgets', 'layo-dashboard-toolkit' ),
			'layout'     => __( 'Default layout', 'layo-dashboard-toolkit' ),
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
		return Admin::url( 'widgets', array_merge( array( 'section' => $section ), $args ) );
	}

	/**
	 * Redirects back with a notice.
	 *
	 * @param string $section Section.
	 * @param string $notice  Notice key.
	 * @param array  $args    Extra arguments.
	 * @return void
	 */
	private static function back( $section, $notice, array $args = array() ) {
		wp_safe_redirect( self::url( $section, array_merge( array( 'layodt_notice' => $notice ), $args ) ) );
		exit;
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
	 * Opens a form posting to admin-post.php.
	 *
	 * @param string $action  Action suffix.
	 * @param array  $extra   Hidden fields.
	 * @param bool   $confirm Whether to ask for confirmation.
	 * @param string $css_class Form class.
	 * @return void
	 */
	private static function form( $action, array $extra = array(), $confirm = false, $css_class = '' ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"' . ( $confirm ? ' data-layodt-confirm' : '' ) . ( '' !== $css_class ? ' class="' . esc_attr( $css_class ) . '"' : '' ) . '>';
		echo '<input type="hidden" name="action" value="layodt_' . esc_attr( $action ) . '">';
		foreach ( $extra as $name => $value ) {
			echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '">';
		}
		wp_nonce_field( 'layodt_' . $action );
	}

	/**
	 * Role names, translated.
	 *
	 * @return array Slug => name.
	 */
	private static function role_names() {
		$names = array();
		foreach ( Role_Manager::roles() as $slug => $role ) {
			$names[ $slug ] = $role['name'];
		}

		return $names;
	}

	/**
	 * Renders the tab.
	 *
	 * @return void
	 */
	public static function render() {
		if ( ! Settings::module_on( 'widgets' ) ) {
			echo '<div class="notice notice-warning inline layodt-notice"><p>' . esc_html__( 'Dashboard widgets is switched off on the Overview tab.', 'layo-dashboard-toolkit' ) . '</p></div>';
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Navigation only.
		$section = isset( $_GET['section'] ) ? sanitize_key( wp_unslash( $_GET['section'] ) ) : 'visibility';
		$section = array_key_exists( $section, self::sections() ) ? $section : 'visibility';

		echo '<nav class="layodt-subnav" aria-label="' . esc_attr__( 'Dashboard widgets sections', 'layo-dashboard-toolkit' ) . '"><ul>';
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
	 * Which roles see each widget.
	 *
	 * @return void
	 */
	private static function section_visibility() {
		$config = Widgets::config();
		$roles  = self::role_names();
		$known  = Widgets::known();

		echo '<p class="layodt-intro">' . wp_kses_post(
			sprintf(
				/* translators: %s: dashboard URL. */
				__( 'Untick a role to hide a widget from everyone in it. Widgets from other plugins appear here after an administrator opens <a href="%s">the dashboard</a> with those plugins active. Someone with several roles sees a widget if any of their roles does.', 'layo-dashboard-toolkit' ),
				esc_url( admin_url( 'index.php' ) )
			)
		) . '</p>';

		self::form( 'widgets_visibility' );
		echo '<div class="layodt-table-scroll" tabindex="0" role="region" aria-label="' . esc_attr__( 'Widgets per role', 'layo-dashboard-toolkit' ) . '"><table class="widefat striped layodt-matrix layodt-widget-matrix"><thead>';
		echo '<tr><th scope="col" rowspan="2">' . esc_html__( 'Widget', 'layo-dashboard-toolkit' ) . '</th><th scope="colgroup" colspan="' . (int) count( $roles ) . '"><span class="layodt-matrix__group">' . esc_html__( 'Shown to', 'layo-dashboard-toolkit' ) . '</span></th></tr><tr>';
		foreach ( $roles as $name ) {
			echo '<th scope="col">' . esc_html( $name ) . '</th>';
		}
		echo '</tr></thead><tbody>';

		foreach ( $known as $id => $title ) {
			$hidden = isset( $config['hide'][ $id ] ) ? $config['hide'][ $id ] : array();
			// Long ids may break after an underscore or dash rather than mid-word.
			echo '<tr><th scope="row">' . esc_html( $title ) . '<small><code>' . wp_kses( str_replace( array( '_', '-' ), array( '_<wbr>', '-<wbr>' ), esc_html( $id ) ), array( 'wbr' => array() ) ) . '</code></small></th>';
			foreach ( $roles as $slug => $name ) {
				printf(
					'<td><input type="hidden" name="widgets[%1$s][%2$s]" value="0"><input type="checkbox" name="widgets[%1$s][%2$s]" value="1"%3$s aria-label="%4$s"></td>',
					esc_attr( $id ),
					esc_attr( $slug ),
					checked( ! in_array( $slug, $hidden, true ), true, false ),
					/* translators: 1: widget title, 2: role name. */
					esc_attr( sprintf( __( 'Show %1$s to %2$s', 'layo-dashboard-toolkit' ), $title, $name ) )
				);
			}
			echo '</tr>';
		}
		echo '</tbody></table></div>';

		echo '<p class="description">' . esc_html__( 'Custom widgets choose their roles in their own settings, under Custom widgets.', 'layo-dashboard-toolkit' ) . '</p>';
		submit_button();
		echo '</form>';
	}

	/**
	 * Custom widgets: list, add and edit.
	 *
	 * @return void
	 */
	private static function section_custom() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Navigation only.
		$edit = isset( $_GET['edit'] ) ? sanitize_key( wp_unslash( $_GET['edit'] ) ) : '';
		$type = isset( $_GET['type'] ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : '';
		// phpcs:enable

		$config = Widgets::config();
		$types  = Widgets::types();

		if ( 'new' === $edit && isset( $types[ $type ] ) ) {
			self::editor( '', Widgets::clean_widget( array( 'type' => $type ) ) );
			return;
		}

		if ( '' !== $edit && isset( $config['custom'][ $edit ] ) ) {
			self::editor( $edit, $config['custom'][ $edit ] );
			return;
		}

		$roles   = self::role_names();
		$columns = Widgets::columns();

		echo '<p class="layodt-intro">' . esc_html__( 'Add your own widgets to the dashboard: a welcome message, links to the screens people use most, how to get help, a video, or live numbers from the site. Each one can be limited to chosen roles.', 'layo-dashboard-toolkit' ) . '</p>';

		if ( $config['custom'] ) {
			echo '<table class="widefat striped layodt-rule-table layodt-widget-list"><thead><tr><th scope="col">' . esc_html__( 'Widget', 'layo-dashboard-toolkit' ) . '</th><th scope="col">' . esc_html__( 'Type', 'layo-dashboard-toolkit' ) . '</th><th scope="col">' . esc_html__( 'Shown to', 'layo-dashboard-toolkit' ) . '</th><th scope="col">' . esc_html__( 'Column', 'layo-dashboard-toolkit' ) . '</th><th scope="col"><span class="screen-reader-text">' . esc_html__( 'Actions', 'layo-dashboard-toolkit' ) . '</span></th></tr></thead><tbody>';
			foreach ( $config['custom'] as $id => $widget ) {
				$who = $widget['roles'] ? implode( ', ', array_intersect_key( $roles, array_flip( $widget['roles'] ) ) ) : __( 'Everyone', 'layo-dashboard-toolkit' );
				echo '<tr><th scope="row"><a href="' . esc_url( self::url( 'custom', array( 'edit' => $id ) ) ) . '">' . esc_html( $widget['title'] ) . '</a></th>';
				echo '<td>' . esc_html( $types[ $widget['type'] ][0] ) . '</td><td>' . esc_html( $who ) . '</td><td>' . esc_html( $columns[ $widget['column'] ] ) . '</td><td class="layodt-row-actions">';
				echo '<a class="button button-small" href="' . esc_url( self::url( 'custom', array( 'edit' => $id ) ) ) . '">' . esc_html__( 'Edit', 'layo-dashboard-toolkit' ) . '</a> ';
				self::form( 'widget_delete', array( 'widget' => $id ), true, 'layodt-inline-form' );
				echo '<button type="submit" class="button button-small button-link-delete">' . esc_html__( 'Delete', 'layo-dashboard-toolkit' ) . '</button></form>';
				echo '</td></tr>';
			}
			echo '</tbody></table>';
		} else {
			echo '<p>' . esc_html__( 'No custom widgets yet.', 'layo-dashboard-toolkit' ) . '</p>';
		}

		echo '<h2 class="title">' . esc_html__( 'Add a widget', 'layo-dashboard-toolkit' ) . '</h2>';
		echo '<ul class="layodt-widget-types">';
		foreach ( $types as $key => $info ) {
			printf(
				'<li><a href="%s"><span class="layodt-widget-types__icon" aria-hidden="true">%s</span><strong>%s</strong><small>%s</small></a></li>',
				esc_url(
					self::url(
						'custom',
						array(
							'edit' => 'new',
							'type' => $key,
						)
					)
				),
				wp_kses( Icons::svg( $info[2], 22 ), Icons::kses() ),
				esc_html( $info[0] ),
				esc_html( $info[1] )
			);
		}
		echo '</ul>';
	}

	/**
	 * Form for one custom widget.
	 *
	 * @param string $id     Widget id, or '' for a new one.
	 * @param array  $widget Widget settings.
	 * @return void
	 */
	private static function editor( $id, array $widget ) {
		$types = Widgets::types();
		$type  = $widget['type'];

		echo '<p><a href="' . esc_url( self::url( 'custom' ) ) . '">&larr; ' . esc_html__( 'All custom widgets', 'layo-dashboard-toolkit' ) . '</a></p>';
		echo '<h2 class="title">' . esc_html(
			'' === $id
				/* translators: %s: widget type. */
				? sprintf( __( 'New widget: %s', 'layo-dashboard-toolkit' ), $types[ $type ][0] )
				: $widget['title']
		) . '</h2>';
		echo '<p class="layodt-intro">' . esc_html( $types[ $type ][1] ) . '</p>';

		self::form(
			'widget_save',
			array(
				'widget'     => $id,
				'data[type]' => $type,
			)
		);
		echo '<table class="form-table" role="presentation">';

		Fields::row_start( __( 'Title', 'layo-dashboard-toolkit' ), 'layodt-widget-title' );
		echo '<input type="text" id="layodt-widget-title" class="regular-text" name="data[title]" value="' . esc_attr( $widget['title'] ) . '">';
		Fields::row_end( __( 'You can use {site_name}.', 'layo-dashboard-toolkit' ) );

		if ( in_array( $type, array( 'text', 'support', 'video' ), true ) ) {
			$labels = array(
				'text'    => __( 'Content', 'layo-dashboard-toolkit' ),
				'support' => __( 'Message', 'layo-dashboard-toolkit' ),
				'video'   => __( 'Text under the video', 'layo-dashboard-toolkit' ),
			);
			Fields::row_start( $labels[ $type ], 'layodt_widget_content' );
			wp_editor(
				$widget['content'],
				'layodt_widget_content',
				array(
					'textarea_name' => 'data[content]',
					'textarea_rows' => 'text' === $type ? 10 : 5,
					'media_buttons' => 'text' === $type,
					'quicktags'     => true,
				)
			);
			Fields::row_end( __( 'Switch between Visual and Code (HTML) with the tabs above the editor. {site_name}, {site_url} and {admin_email} are replaced.', 'layo-dashboard-toolkit' ) );
		}

		if ( 'support' === $type ) {
			Fields::row_start( __( 'Email', 'layo-dashboard-toolkit' ), 'layodt-widget-email' );
			echo '<input type="email" id="layodt-widget-email" class="regular-text" name="data[email]" value="' . esc_attr( $widget['email'] ) . '">';
			Fields::row_end();
			Fields::row_start( __( 'Phone', 'layo-dashboard-toolkit' ), 'layodt-widget-phone' );
			echo '<input type="tel" id="layodt-widget-phone" class="regular-text" name="data[phone]" value="' . esc_attr( $widget['phone'] ) . '">';
			Fields::row_end();
		}

		if ( in_array( $type, array( 'text', 'support' ), true ) ) {
			Fields::row_start( __( 'Button text', 'layo-dashboard-toolkit' ), 'layodt-widget-button' );
			echo '<input type="text" id="layodt-widget-button" class="regular-text" name="data[button]" value="' . esc_attr( $widget['button'] ) . '">';
			Fields::row_end( __( 'Leave empty for no button.', 'layo-dashboard-toolkit' ) );
			Fields::row_start( __( 'Button link', 'layo-dashboard-toolkit' ), 'layodt-widget-button-url' );
			echo '<input type="text" id="layodt-widget-button-url" class="regular-text" name="data[button_url]" value="' . esc_attr( $widget['button_url'] ) . '" placeholder="https://">';
			Fields::row_end( __( 'An address, or an admin screen such as post-new.php.', 'layo-dashboard-toolkit' ) );
		}

		if ( 'video' === $type ) {
			Fields::row_start( __( 'Video address', 'layo-dashboard-toolkit' ), 'layodt-widget-video' );
			echo '<input type="url" id="layodt-widget-video" class="large-text" name="data[video]" value="' . esc_attr( $widget['video'] ) . '" placeholder="https://www.youtube.com/watch?v=…">';
			Fields::row_end( __( 'The page address of a video on YouTube, Vimeo, or another site WordPress can embed. The player loads from that site when someone views the dashboard.', 'layo-dashboard-toolkit' ) );
		}

		if ( 'links' === $type ) {
			self::links_field( $widget['links'] );
		}

		if ( 'template' === $type ) {
			Fields::row_start( __( 'Page or template', 'layo-dashboard-toolkit' ), 'layodt-widget-page' );
			$choices = Widgets::template_choices( $widget['page'] );
			if ( $choices ) {
				echo '<select id="layodt-widget-page" name="data[page]"><option value="0">' . esc_html__( 'Choose one', 'layo-dashboard-toolkit' ) . '</option>';
				foreach ( $choices as $group => $items ) {
					echo '<optgroup label="' . esc_attr( $group ) . '">';
					foreach ( $items as $id => $title ) {
						echo '<option value="' . esc_attr( (string) $id ) . '"' . selected( $widget['page'], $id, false ) . '>' . esc_html( $title ) . '</option>';
					}
					echo '</optgroup>';
				}
				echo '</select>';
				if ( $widget['page'] ) {
					echo ' <a class="button" href="' . esc_url( \LayoDT\Modules\Maintenance\Maintenance::edit_url( $widget['page'] ) ) . '">' . esc_html__( 'Edit it', 'layo-dashboard-toolkit' ) . '</a>';
				}
			} else {
				echo '<p>' . esc_html__( 'Create a page first. It can stay a draft: only the dashboard shows it.', 'layo-dashboard-toolkit' ) . '</p>';
			}
			Fields::row_end( __( 'Shown inside the widget as you designed it. Pages built with Elementor keep their Elementor styles. A draft or private page works, so it never has to be public.', 'layo-dashboard-toolkit' ) );
		}

		if ( 'posts' === $type ) {
			Fields::row_start( __( 'Category', 'layo-dashboard-toolkit' ), 'layodt-widget-category' );
			wp_dropdown_categories(
				array(
					'name'            => 'data[category]',
					'id'              => 'layodt-widget-category',
					'selected'        => $widget['category'],
					'show_option_all' => __( 'All categories', 'layo-dashboard-toolkit' ),
					'hide_empty'      => false,
					'hierarchical'    => true,
				)
			);
			Fields::row_end();
		}

		if ( in_array( $type, array( 'posts', 'signups', 'comments' ), true ) ) {
			Fields::row_start( __( 'How many to show', 'layo-dashboard-toolkit' ), 'layodt-widget-number' );
			echo '<input type="number" id="layodt-widget-number" class="small-text" name="data[number]" min="1" max="20" value="' . esc_attr( (string) $widget['number'] ) . '">';
			Fields::row_end();
		}

		Fields::row_start( __( 'Column', 'layo-dashboard-toolkit' ), 'layodt-widget-column' );
		echo '<select id="layodt-widget-column" name="data[column]">';
		foreach ( Widgets::columns() as $key => $label ) {
			echo '<option value="' . esc_attr( $key ) . '"' . selected( $widget['column'], $key, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
		Fields::row_end( __( 'Where it starts. People can still move it unless the default layout is locked. Columns 3 and 4 only show on wide screens.', 'layo-dashboard-toolkit' ) );

		Fields::row_start( __( 'Shown to', 'layo-dashboard-toolkit' ) );
		echo '<fieldset><legend class="screen-reader-text">' . esc_html__( 'Shown to', 'layo-dashboard-toolkit' ) . '</legend>';
		foreach ( self::role_names() as $slug => $name ) {
			echo '<label class="layodt-check"><input type="checkbox" name="data[roles][]" value="' . esc_attr( $slug ) . '"' . checked( in_array( $slug, $widget['roles'], true ), true, false ) . '> ' . esc_html( $name ) . '</label>';
		}
		echo '</fieldset>';
		Fields::row_end( __( 'Tick none to show it to everyone who can open the dashboard.', 'layo-dashboard-toolkit' ) );

		if ( in_array( $type, array( 'signups', 'counts', 'comments', 'health' ), true ) ) {
			Fields::row_start( __( 'Privacy', 'layo-dashboard-toolkit' ) );
			echo '<p>' . esc_html__( 'This widget shows site data to everyone it is shown to. Links to edit screens only appear for people allowed to use them.', 'layo-dashboard-toolkit' ) . '</p>';
			Fields::row_end();
		}

		echo '</table>';
		submit_button( '' === $id ? __( 'Add widget', 'layo-dashboard-toolkit' ) : __( 'Save widget', 'layo-dashboard-toolkit' ) );
		echo '</form>';
	}

	/**
	 * Editable, sortable list of quick links, with empty rows to add more.
	 *
	 * @param array $links Saved links.
	 * @return void
	 */
	private static function links_field( array $links ) {
		$icons = array( '' => __( 'Automatic', 'layo-dashboard-toolkit' ) );
		foreach ( array_keys( Icons::all() ) as $icon ) {
			$icons[ $icon ] = ucfirst( str_replace( '-', ' ', $icon ) );
		}

		for ( $i = 0; $i < 3; $i++ ) {
			$links[] = array(
				'label' => '',
				'url'   => '',
				'icon'  => '',
			);
		}

		Fields::row_start( __( 'Links', 'layo-dashboard-toolkit' ) );
		echo '<ol class="layodt-sortable layodt-link-list" data-layodt-sortable>';
		foreach ( array_values( $links ) as $n => $link ) {
			$name = '' !== $link['label'] ? $link['label'] : __( 'New link', 'layo-dashboard-toolkit' );
			echo '<li class="layodt-sortable__row" draggable="true">';
			echo '<span class="layodt-sortable__handle" aria-hidden="true"></span>';
			/* translators: %s: item name. */
			echo '<button type="button" class="button-link layodt-sortable__up" data-layodt-move="-1" aria-label="' . esc_attr( sprintf( __( 'Move %s up', 'layo-dashboard-toolkit' ), $name ) ) . '">&uarr;</button>';
			/* translators: %s: item name. */
			echo '<button type="button" class="button-link layodt-sortable__down" data-layodt-move="1" aria-label="' . esc_attr( sprintf( __( 'Move %s down', 'layo-dashboard-toolkit' ), $name ) ) . '">&darr;</button>';
			echo '<input type="text" name="data[links][' . (int) $n . '][label]" value="' . esc_attr( $link['label'] ) . '" placeholder="' . esc_attr__( 'Label', 'layo-dashboard-toolkit' ) . '" aria-label="' . esc_attr__( 'Label', 'layo-dashboard-toolkit' ) . '">';
			echo '<input type="text" name="data[links][' . (int) $n . '][url]" value="' . esc_attr( $link['url'] ) . '" placeholder="' . esc_attr__( 'post-new.php or https://…', 'layo-dashboard-toolkit' ) . '" aria-label="' . esc_attr__( 'Address', 'layo-dashboard-toolkit' ) . '">';
			echo '<select name="data[links][' . (int) $n . '][icon]" aria-label="' . esc_attr__( 'Icon', 'layo-dashboard-toolkit' ) . '">';
			foreach ( $icons as $key => $label ) {
				echo '<option value="' . esc_attr( $key ) . '"' . selected( $link['icon'], $key, false ) . '>' . esc_html( $label ) . '</option>';
			}
			echo '</select></li>';
		}
		echo '</ol>';
		Fields::row_end( __( 'Drag to reorder. Leave a label empty to remove that link. Use an admin screen such as edit.php?post_type=page, or a full address. Save to get more empty rows.', 'layo-dashboard-toolkit' ) );
	}

	/**
	 * Default layout: save the current arrangement for everyone.
	 *
	 * @return void
	 */
	private static function section_layout() {
		$layout  = Widgets::config()['layout'];
		$titles  = Widgets::titles();
		$columns = Widgets::columns();

		echo '<p class="layodt-intro">' . wp_kses_post(
			sprintf(
				/* translators: %s: dashboard URL. */
				__( 'Arrange <a href="%s">your own dashboard</a> the way everyone should see it: drag widgets into place, collapse the ones that should start closed and hide any with Screen Options. Then come back here and save it as the default layout. People who have not arranged their own dashboard get it straight away.', 'layo-dashboard-toolkit' ),
				esc_url( admin_url( 'index.php' ) )
			)
		) . '</p>';

		echo '<div class="layodt-card">';
		echo '<h2 class="title">' . esc_html__( 'Saved layout', 'layo-dashboard-toolkit' ) . '</h2>';
		if ( empty( $layout['saved'] ) ) {
			echo '<p>' . esc_html__( 'None yet. Everyone sees the WordPress arrangement until you save one.', 'layo-dashboard-toolkit' ) . '</p>';
		} else {
			printf(
				'<p>%s</p>',
				esc_html(
					sprintf(
						/* translators: %s: date and time. */
						__( 'Saved on %s.', 'layo-dashboard-toolkit' ),
						wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $layout['saved'] )
					)
				)
			);
			echo '<div class="layodt-layout">';
			foreach ( $columns as $key => $label ) {
				$ids = isset( $layout['order'][ $key ] ) ? array_filter( explode( ',', $layout['order'][ $key ] ) ) : array();
				echo '<div class="layodt-layout__col"><h3>' . esc_html( $label ) . '</h3><ul>';
				foreach ( $ids as $box ) {
					if ( ! isset( $titles[ $box ] ) ) {
						continue;
					}
					$flags = array();
					if ( in_array( $box, $layout['closed'], true ) ) {
						$flags[] = __( 'collapsed', 'layo-dashboard-toolkit' );
					}
					if ( in_array( $box, $layout['hidden'], true ) ) {
						$flags[] = __( 'hidden', 'layo-dashboard-toolkit' );
					}
					echo '<li>' . esc_html( $titles[ $box ] ) . ( $flags ? ' <small>(' . esc_html( implode( ', ', $flags ) ) . ')</small>' : '' ) . '</li>';
				}
				if ( ! array_intersect( $ids, array_keys( $titles ) ) ) {
					echo '<li class="layodt-layout__empty">' . esc_html__( 'Empty', 'layo-dashboard-toolkit' ) . '</li>';
				}
				echo '</ul></div>';
			}
			echo '</div>';
			echo '<p class="description">' . esc_html__( 'Widgets that are not listed, such as ones added by a plugin later, keep their usual place.', 'layo-dashboard-toolkit' ) . '</p>';
		}
		echo '</div>';

		self::form( 'widgets_layout' );
		echo '<table class="form-table" role="presentation">';
		Fields::row_start( __( 'Lock', 'layo-dashboard-toolkit' ) );
		echo '<label><input type="checkbox" name="lock" value="1"' . checked( $layout['lock'], true, false ) . '> ' . esc_html__( 'Stop people rearranging their dashboard', 'layo-dashboard-toolkit' ) . '</label>';
		Fields::row_end( __( 'Everyone gets the saved order and cannot drag widgets. They can still collapse widgets. Administrators are never locked, so they can change the default.', 'layo-dashboard-toolkit' ) );
		Fields::row_start( __( 'Everyone', 'layo-dashboard-toolkit' ) );
		echo '<label><input type="checkbox" name="reset_users" value="1"> ' . esc_html__( 'Also replace the arrangement people have already made', 'layo-dashboard-toolkit' ) . '</label>';
		Fields::row_end( __( 'Clears each person\'s own order, collapsed and hidden widgets, so the default applies to them too.', 'layo-dashboard-toolkit' ) );
		echo '</table>';

		echo '<p class="submit">';
		submit_button( __( 'Save my dashboard as the default', 'layo-dashboard-toolkit' ), 'primary', 'capture', false );
		if ( ! empty( $layout['saved'] ) ) {
			echo ' ';
			submit_button( __( 'Save lock setting only', 'layo-dashboard-toolkit' ), 'secondary', 'lock_only', false );
			echo ' ';
			submit_button( __( 'Remove the default layout', 'layo-dashboard-toolkit' ), 'secondary', 'clear', false, array( 'data-layodt-confirm-click' => '1' ) );
		}
		echo '</p></form>';
	}

	/**
	 * Saves which roles see each widget.
	 *
	 * @return void
	 */
	public static function handle_widgets_visibility() {
		check_admin_referer( 'layodt_widgets_visibility' );

		if ( ! current_user_can( 'manage_options' ) ) {
			self::deny();
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Keys and values cleaned below and in Widgets::save().
		$posted = isset( $_POST['widgets'] ) ? (array) wp_unslash( $_POST['widgets'] ) : array();
		$hide   = array();

		foreach ( $posted as $id => $roles ) {
			foreach ( (array) $roles as $role => $shown ) {
				if ( '1' !== (string) $shown ) {
					$hide[ Widgets::clean_id( $id ) ][] = sanitize_key( $role );
				}
			}
		}

		Widgets::save( array( 'hide' => $hide ) );
		Audit::log( 'widgets', __( 'Dashboard widget visibility updated.', 'layo-dashboard-toolkit' ) );
		self::back( 'visibility', 'saved' );
	}

	/**
	 * Adds or saves a custom widget.
	 *
	 * @return void
	 */
	public static function handle_widget_save() {
		check_admin_referer( 'layodt_widget_save' );

		if ( ! current_user_can( 'manage_options' ) ) {
			self::deny();
		}

		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Cleaned in Widgets::clean_widget().
		$id   = isset( $_POST['widget'] ) ? sanitize_key( wp_unslash( $_POST['widget'] ) ) : '';
		$data = isset( $_POST['data'] ) ? (array) wp_unslash( $_POST['data'] ) : array();
		// phpcs:enable

		$config = Widgets::config();

		if ( '' === $id || ! isset( $config['custom'][ $id ] ) ) {
			do {
				$id = 'w' . strtolower( wp_generate_password( 6, false ) );
			} while ( isset( $config['custom'][ $id ] ) );
		}

		$config['custom'][ $id ] = Widgets::clean_widget( $data );

		Widgets::save( array( 'custom' => $config['custom'] ) );
		/* translators: %s: widget title. */
		Audit::log( 'widgets', sprintf( __( 'Dashboard widget "%s" saved.', 'layo-dashboard-toolkit' ), $config['custom'][ $id ]['title'] ) );
		self::back( 'custom', 'saved', array( 'edit' => $id ) );
	}

	/**
	 * Deletes a custom widget.
	 *
	 * @return void
	 */
	public static function handle_widget_delete() {
		check_admin_referer( 'layodt_widget_delete' );

		if ( ! current_user_can( 'manage_options' ) ) {
			self::deny();
		}

		$id     = isset( $_POST['widget'] ) ? sanitize_key( wp_unslash( $_POST['widget'] ) ) : '';
		$config = Widgets::config();

		if ( isset( $config['custom'][ $id ] ) ) {
			$title = $config['custom'][ $id ]['title'];
			unset( $config['custom'][ $id ] );
			Widgets::save( array( 'custom' => $config['custom'] ) );
			/* translators: %s: widget title. */
			Audit::log( 'widgets', sprintf( __( 'Dashboard widget "%s" deleted.', 'layo-dashboard-toolkit' ), $title ) );
		}

		self::back( 'custom', 'widget_deleted' );
	}

	/**
	 * Saves, locks or removes the default layout.
	 *
	 * @return void
	 */
	public static function handle_widgets_layout() {
		check_admin_referer( 'layodt_widgets_layout' );

		if ( ! current_user_can( 'manage_options' ) ) {
			self::deny();
		}

		$lock   = ! empty( $_POST['lock'] );
		$config = Widgets::config();

		if ( isset( $_POST['clear'] ) ) {
			Widgets::save( array( 'layout' => array() ) );
			Audit::log( 'widgets', __( 'Default dashboard layout removed.', 'layo-dashboard-toolkit' ) );
			self::back( 'layout', 'saved' );
		}

		if ( isset( $_POST['lock_only'] ) ) {
			$config['layout']['lock'] = $lock;
			Widgets::save( array( 'layout' => $config['layout'] ) );
		} else {
			Widgets::capture_layout( get_current_user_id(), $lock );
		}

		if ( ! empty( $_POST['reset_users'] ) ) {
			Widgets::reset_users();
		}

		Audit::log( 'widgets', $lock ? __( 'Default dashboard layout saved and locked.', 'layo-dashboard-toolkit' ) : __( 'Default dashboard layout saved.', 'layo-dashboard-toolkit' ) );
		self::back( 'layout', 'saved' );
	}
}

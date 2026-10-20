<?php
/**
 * Field renderers for the settings page.
 *
 * Field names follow the setting dot path, so `security.limit` posts as
 * `adminstudio[security][limit]`.
 *
 * @package AdminStudio
 */

namespace AdminStudio\Admin;

use AdminStudio\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Form field helpers.
 */
class Fields {

	/**
	 * Form field name for a dot path.
	 *
	 * @param string $key Dot path.
	 * @return string
	 */
	public static function name( $key ) {
		return 'adminstudio[' . implode( '][', explode( '.', $key ) ) . ']';
	}

	/**
	 * HTML id for a dot path.
	 *
	 * @param string $key Dot path.
	 * @return string
	 */
	public static function id( $key ) {
		return 'adminstudio-' . str_replace( array( '.', '_' ), '-', $key );
	}

	/**
	 * Opens a table row.
	 *
	 * @param string $label Row label.
	 * @param string $label_for Id of the field the label points to, or ''.
	 * @param array  $attrs Extra row attributes.
	 * @return void
	 */
	public static function row_start( $label, $label_for = '', array $attrs = array() ) {
		echo '<tr';
		foreach ( $attrs as $attr => $value ) {
			echo ' ' . esc_attr( $attr ) . '="' . esc_attr( $value ) . '"';
		}
		echo '><th scope="row">';
		if ( $label_for ) {
			echo '<label for="' . esc_attr( $label_for ) . '">' . esc_html( $label ) . '</label>';
		} else {
			echo esc_html( $label );
		}
		echo '</th><td>';
	}

	/**
	 * Closes a table row, with an optional description.
	 *
	 * @param string $desc Description (basic HTML allowed).
	 * @return void
	 */
	public static function row_end( $desc = '' ) {
		if ( '' !== $desc ) {
			echo '<p class="description">' . wp_kses_post( $desc ) . '</p>';
		}
		echo '</td></tr>';
	}

	/**
	 * Row attributes that show the row only when another field has a value.
	 *
	 * @param string $key   Dot path of the controlling field.
	 * @param string $value Value that shows the row.
	 * @return array
	 */
	public static function show_if( $key, $value = '1' ) {
		return array(
			'data-adminstudio-show-if'    => self::name( $key ),
			'data-adminstudio-show-value' => $value,
		);
	}

	/**
	 * Checkbox row.
	 *
	 * @param string $key   Dot path.
	 * @param string $label Row label.
	 * @param string $text  Text next to the box.
	 * @param string $desc  Description.
	 * @param array  $attrs Row attributes.
	 * @return void
	 */
	public static function checkbox( $key, $label, $text, $desc = '', array $attrs = array() ) {
		self::row_start( $label, '', $attrs );
		printf(
			'<label for="%1$s"><input type="hidden" name="%2$s" value="0"><input type="checkbox" id="%1$s" name="%2$s" value="1" %3$s> %4$s</label>',
			esc_attr( self::id( $key ) ),
			esc_attr( self::name( $key ) ),
			checked( (bool) Settings::get( $key ), true, false ),
			esc_html( $text )
		);
		self::row_end( $desc );
	}

	/**
	 * Text input row.
	 *
	 * @param string $key   Dot path.
	 * @param string $label Row label.
	 * @param string $desc  Description.
	 * @param array  $input Extra input attributes.
	 * @param array  $attrs Row attributes.
	 * @return void
	 */
	public static function text( $key, $label, $desc = '', array $input = array(), array $attrs = array() ) {
		$input = array_merge(
			array(
				'type'  => 'text',
				'class' => 'regular-text',
			),
			$input
		);

		self::row_start( $label, self::id( $key ), $attrs );

		if ( isset( $input['prefix'] ) ) {
			echo '<code class="adminstudio-prefix">' . esc_html( $input['prefix'] ) . '</code>';
			unset( $input['prefix'] );
		}

		printf( '<input id="%s" name="%s" value="%s"', esc_attr( self::id( $key ) ), esc_attr( self::name( $key ) ), esc_attr( (string) Settings::get( $key ) ) );
		foreach ( $input as $attr => $value ) {
			echo ' ' . esc_attr( $attr ) . '="' . esc_attr( $value ) . '"';
		}
		echo '>';

		self::row_end( $desc );
	}

	/**
	 * Number input row.
	 *
	 * @param string $key   Dot path.
	 * @param string $label Row label.
	 * @param string $desc  Description.
	 * @param array  $attrs Row attributes.
	 * @return void
	 */
	public static function number( $key, $label, $desc = '', array $attrs = array() ) {
		$field = Settings::field( $key );
		$input = array(
			'type'  => 'number',
			'class' => 'small-text',
		);

		if ( isset( $field['min'] ) ) {
			$input['min'] = $field['min'];
		}
		if ( isset( $field['max'] ) ) {
			$input['max'] = $field['max'];
		}

		self::text( $key, $label, $desc, $input, $attrs );
	}

	/**
	 * URL input row with an optional media picker button.
	 *
	 * @param string $key   Dot path.
	 * @param string $label Row label.
	 * @param string $desc  Description.
	 * @param bool   $media Whether to add a "Choose image" button.
	 * @param array  $attrs Row attributes.
	 * @return void
	 */
	public static function url( $key, $label, $desc = '', $media = false, array $attrs = array() ) {
		self::row_start( $label, self::id( $key ), $attrs );
		printf(
			'<input type="url" id="%s" name="%s" value="%s" class="regular-text">',
			esc_attr( self::id( $key ) ),
			esc_attr( self::name( $key ) ),
			esc_attr( (string) Settings::get( $key ) )
		);
		if ( $media ) {
			printf(
				' <button type="button" class="button" data-adminstudio-media="%s">%s</button>',
				esc_attr( self::id( $key ) ),
				esc_html__( 'Choose image', 'admin-studio' )
			);
		}
		self::row_end( $desc );
	}

	/**
	 * Select row.
	 *
	 * @param string $key     Dot path.
	 * @param string $label   Row label.
	 * @param array  $choices Value => label.
	 * @param string $desc    Description.
	 * @param array  $attrs   Row attributes.
	 * @return void
	 */
	public static function select( $key, $label, array $choices, $desc = '', array $attrs = array() ) {
		self::row_start( $label, self::id( $key ), $attrs );
		printf( '<select id="%s" name="%s">', esc_attr( self::id( $key ) ), esc_attr( self::name( $key ) ) );
		foreach ( $choices as $value => $text ) {
			printf( '<option value="%s" %s>%s</option>', esc_attr( $value ), selected( Settings::get( $key ), $value, false ), esc_html( $text ) );
		}
		echo '</select>';
		self::row_end( $desc );
	}

	/**
	 * Textarea row.
	 *
	 * @param string $key   Dot path.
	 * @param string $label Row label.
	 * @param string $desc  Description.
	 * @param int    $rows  Rows.
	 * @param array  $attrs Row attributes.
	 * @return void
	 */
	public static function textarea( $key, $label, $desc = '', $rows = 4, array $attrs = array() ) {
		self::row_start( $label, self::id( $key ), $attrs );
		printf(
			'<textarea id="%s" name="%s" rows="%d" class="large-text">%s</textarea>',
			esc_attr( self::id( $key ) ),
			esc_attr( self::name( $key ) ),
			(int) $rows,
			esc_textarea( (string) Settings::get( $key ) )
		);
		self::row_end( $desc );
	}

	/**
	 * Group of checkboxes for a multi-value field.
	 *
	 * @param string $key     Dot path.
	 * @param string $label   Row label.
	 * @param array  $choices Value => label.
	 * @param string $desc    Description.
	 * @param array  $attrs   Row attributes.
	 * @return void
	 */
	public static function checkboxes( $key, $label, array $choices, $desc = '', array $attrs = array() ) {
		$current = (array) Settings::get( $key );

		self::row_start( $label, '', $attrs );
		echo '<fieldset><legend class="screen-reader-text">' . esc_html( $label ) . '</legend>';
		printf( '<input type="hidden" name="%s[]" value="">', esc_attr( self::name( $key ) ) );
		foreach ( $choices as $value => $text ) {
			printf(
				'<label class="adminstudio-check"><input type="checkbox" name="%s[]" value="%s" %s> %s</label>',
				esc_attr( self::name( $key ) ),
				esc_attr( $value ),
				checked( in_array( (string) $value, array_map( 'strval', $current ), true ), true, false ),
				esc_html( $text )
			);
		}
		echo '</fieldset>';
		self::row_end( $desc );
	}

	/**
	 * Role checkboxes.
	 *
	 * @param string $key   Dot path.
	 * @param string $label Row label.
	 * @param string $desc  Description.
	 * @param array  $attrs Row attributes.
	 * @return void
	 */
	public static function roles( $key, $label, $desc = '', array $attrs = array() ) {
		$roles = array();

		foreach ( wp_roles()->roles as $role => $info ) {
			$roles[ $role ] = translate_user_role( $info['name'] );
		}

		self::checkboxes( $key, $label, $roles, $desc, $attrs );
	}
}

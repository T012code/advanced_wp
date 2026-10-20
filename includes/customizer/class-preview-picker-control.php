<?php
/**
 * A select box that switches the Customizer preview between pages.
 *
 * @package AdvancedWP
 */

namespace AdvancedWP\Customizer;

defined( 'ABSPATH' ) || exit;

/**
 * Preview switcher. Stores nothing; the controls script reads its value.
 */
class Preview_Picker_Control extends \WP_Customize_Control {

	/**
	 * Control type.
	 *
	 * @var string
	 */
	public $type = 'advwp_picker';

	/**
	 * Renders the select box.
	 *
	 * @return void
	 */
	protected function render_content() {
		$id = 'advwp-picker-' . $this->id;
		?>
		<label for="<?php echo esc_attr( $id ); ?>" class="customize-control-title"><?php echo esc_html( $this->label ); ?></label>
		<select id="<?php echo esc_attr( $id ); ?>" data-advwp-picker>
			<?php foreach ( $this->choices as $url => $label ) : ?>
				<option value="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php
	}
}

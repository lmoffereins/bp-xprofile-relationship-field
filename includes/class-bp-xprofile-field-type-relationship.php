<?php

/**
 * The BP XProfile Field Type Relationship Class
 * 
 * @package BP XProfile Relationship Field
 * @subpackage Main
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'BP_XProfile_Field_Type_Relationship' ) ) :
/**
 * Relationship xprofile field type
 *
 * @since 1.0.0
 */
class BP_XProfile_Field_Type_Relationship extends BP_XProfile_Field_Type {

	/**
	 * Holds the relationship type
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public $related_to;

	/**
	 * Holds the field's selection method
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public $selection_method;

	/**
	 * Holds the relationship query's order by value
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public $order_type;

	/**
	 * Constructor for the single relationship field type
	 *
	 * @since 1.0.0
 	 */
	public function __construct() {
		parent::__construct();

		$this->name = esc_html_x( 'Relationship Field', 'xprofile field type', 'bp-xprofile-relationship-field' );

		$this->accepts_null_value  = true;
		$this->supports_options    = false; // Has effect for select options that are saved in DB
		$this->do_settings_section = true;

		$this->set_format( '/^.+$/', 'replace' );
		do_action( 'bp_xprofile_field_type_relationship', $this );
	}

	/**
	 * Output the edit field HTML for this field type
	 *
	 * Must be used inside the {@see bp_profile_fields()} template loop.
	 *
	 * @since 1.0.0
	 *
	 * @param array $raw_properties Optional key/value array of
	 *                              {@link http://dev.w3.org/html5/markup/input.checkbox.html permitted attributes}
	 *                              that you want to add.
	 */
	public function edit_field_html( array $raw_properties = array() ) {

		/**
		 * Display native field type's edit markup
		 *
		 * Display of possible field options/children in the field HTML relies not
		 * on the type's instance created here, but fetches them from the iterated
		 * field global, using {@link bp_the_profile_field_options()}, which in turn
		 * triggers the proper relationship type's field options HTML method below.
		 */
		$type = bp_xprofile_relationship_field_create_field_type( $this->field_obj );
		$type->field_obj = $this->field_obj;
		$type->edit_field_html( $raw_properties );
	}

	/**
	 * Output the edit field options HTML for this field type.
	 *
	 * BuddyPress considers a field's "options" to be, for example, the items in a selectbox.
	 * These are stored separately in the database, and their templating is handled seperately.
	 *
	 * For Relationship fields, the options are derived from the related object, registered in the
	 * 'related_to' field metadata, so they are not native XProfile field options. As per BP 3.0+,
	 * the options of a field (its 'children') are provided through the filter in
	 * 'BP_XProfile_Field::get_children()', mimicing the native structure of field options from the
	 * database. This allows for usage of any registered selection method/field type for selecting
	 * the relationship field's data.
	 *
	 * This templating is separate from {@link BP_XProfile_Field_Type::edit_field_html()} because
	 * it's also used in the wp-admin screens when creating new fields, and for backwards compatibility.
	 *
	 * Must be used inside the {@link bp_profile_fields()} template loop.
	 *
	 * @since 1.0.0
	 *
	 * @param array $args Optional. The arguments passed to {@link bp_the_profile_field_options()}.
	 */
	public function edit_field_options_html( array $args = array() ) {

		// BP 3.0+
		// TODO: keep only this logic when dropping support for BP pre-3.0
		if ( version_compare( bp_get_version(), '3.0.0', '>=' ) ) {

			// Display native field type's edit markup
			$type = bp_xprofile_relationship_field_create_field_type( $this->field_obj );
			$type->field_obj = $this->field_obj;
			$type->edit_field_options_html( $args );

			return;
		}

		$options       = bp_xprofile_relationship_field_options( $this->field_obj );
		$option_values = BP_XProfile_ProfileData::get_value_byid( $this->field_obj->id, $args['user_id'] );
		$option_values = (array) maybe_unserialize( $option_values );

		$html        = '';
		$method      = bp_xprofile_get_meta( $this->field_obj->id, 'field', 'selection_method' );
		$checkbox    = 'checkbox' === $method ? '[]' : ''; // Multiselectbox input name is defined at the `<select>` level
		$type_map    = array( 'multiselectbox' => 'multiselect', 'checkbox' => 'checkbox', 'radio' => 'radio', 'selectbox' => 'select' );
		$filter_type = isset( $type_map[ $method ] ) ? $type_map[ $method ] : 'relationship';

		// Check for updated posted values, but errors preventing them from being saved first time
		if ( isset( $_POST['field_' . $this->field_obj->id] ) && $option_values !== maybe_serialize( $_POST['field_' . $this->field_obj->id] ) ) {
			if ( ! empty( $_POST['field_' . $this->field_obj->id] ) ) {
				$option_values = array_map( 'sanitize_text_field', (array) $_POST['field_' . $this->field_obj->id] );
			}
		}

		// Build all options
		foreach ( array_values( $options ) as $k => $option ) {
			$selected    = false;
			$option_html = '';

			// Run the allowed option name through the before_save filter, so we'll be sure to get a match
			$allowed_option = xprofile_sanitize_data_value_before_save( $option->id, false, false );

			// First, check to see whether the user's saved values match the option
			if ( in_array( $allowed_option, $option_values ) ) {
				$selected = true;

			// Check if the option name matches the current value. Compare sanitized titles
			} elseif ( in_array( sanitize_title( $option->name ), array_map( 'sanitize_title', $option_values ) ) ) {
				$selected = true;
			}
				
			// Build single option html for (multi)selectbox
			if ( 'selectbox' === $method || 'multiselectbox' === $method ) {

				// Provide a null value before all other options
				if ( 'selectbox' === $method && 0 === $k ) {
					/* translators: no option picked in select box */
					$html .= sprintf( '<option value="">%s</option>', esc_html__( '----', 'buddypress' ) );
				}

				if ( $selected ) {
					$selected = ' selected="selected"';
				}

				// Relationships do not support defaults (yet).

				$option_html = '<option %1$s value="%4$s">%5$s</option>';

			// Build single option html for radio/checkbox
			} elseif ( 'radio' === $method || 'checkbox' === $method ) {

				if ( $selected ) {
					$selected = ' checked="checked"';
				}

				// Relationships do not support defaults (yet).

				$option_html = '<label for="%3$s" class="option-label"><input %1$s type="' . $method . '" name="%2$s" id="%3$s" value="%4$s">%5$s</label>';
			}

			$new_html = sprintf( $option_html,
				$selected,
				esc_attr( bp_get_the_profile_field_input_name() . "{$checkbox}" ),
				esc_attr( "field_{$this->field_obj->id}_{$allowed_option}" ),
				esc_attr( stripslashes( $option->id ) ),
				esc_html( stripslashes( $option->name ) )
			);

			/**
			 * Filters the HTML output for an individual field options checkbox.
			 *
			 * The variable `$filter_type` part refers to the type of options being
			 * filtered. Options are 'multiselectbox', 'selectbox', 'checkbox',
			 * 'radio', or alternatively 'relationship' as a fallback.
			 *
			 * @since 1.0.0
			 *
			 * @param string $new_html Label and checkbox input field.
			 * @param object $option   Current option being rendered for.
			 * @param int    $id       ID of the field object being rendered.
			 * @param string $selected Current selected value.
			 * @param string $k        Current index in the foreach loop.
			 */
			$html .= apply_filters( "bp_get_the_profile_field_options_{$filter_type}", $new_html, $option, $this->field_obj->id, $selected, $k );
		}

		// Wrap radio/checkbox options
		if ( 'radio' === $method || 'checkbox' === $method ) {
			printf( '<div id="%1$s" class="input-options %3$s-options">%2$s</div>',
				esc_attr( 'field_' . $this->field_obj->id ),
				$html,
				'radio' === $method ? 'radio-button' : $method
			);
		} else {
			echo $html;
		}
	}

	/**
	 * Output HTML for this field type on the wp-admin Profile Fields screen.
	 *
	 * Must be used inside the {@link bp_profile_fields()} template loop.
	 *
	 * @since 1.0.0
	 *
	 * @param array $raw_properties Optional key/value array of permitted attributes that you want to add.
	 */
	public function admin_field_html( array $raw_properties = array() ) {

		// Display native field type's admin markup
		$type = bp_xprofile_relationship_field_create_field_type( $this->field_obj );
		$type->field_obj = $this->field_obj;
		$type->admin_field_html( $raw_properties );
	}

	/**
	 * Output HTML for this field type's children options on the wp-admin Profile Fields "Add Field" and "Edit Field" screens.
	 *
	 * Must be used inside the {@link bp_profile_fields()} template loop.
	 *
	 * @see BP_XProfile_Field_Type::admin_new_field_html()
	 *
	 * @since 1.0.0
	 *
	 * @param BP_XProfile_Field $current_field The current profile field on the add/edit screen.
	 * @param string $control_type Optional. HTML input type used to render the current field's child options.
	 */
	public function admin_new_field_html( BP_XProfile_Field $current_field, $control_type = '' ) {
		$type = array_search( get_class( $this ), bp_xprofile_get_field_types() );
		if ( false === $type ) {
			return;
		}

		// Define field details
		$current_field = bp_xprofile_relationship_field()->populate_field( $current_field );
		$class         = $current_field->type !== $type ? 'display: none;' : '';

		// Define field meta ids. Follow BP's pattern of `meta_name_{$type}`
		$esc_type      = esc_attr( $type );
		$related_id    = "related_to_{$esc_type}";
		$selection_id  = "selection_method_{$esc_type}";
		$order_id      = "order_type_{$esc_type}"; // Confusing: BP uses 'sort_order' input field for 'order_by' field property
		$sort_id       = "sort_order_{$esc_type}"; // Confusing: BP uses 'sort_order' input field for 'order_by' field property

		?>

		<div id="<?php echo $esc_type; ?>" class="postbox bp-options-box" style="<?php echo esc_attr( $class ); ?> margin-top: 15px;">
			<h3><?php esc_html_e( 'Please enter options for this Field:', 'buddypress' ); ?></h3>

			<div class="inside" aria-live="polite" aria-atomic="true" aria-relevant="all">
			<table class="form-table bp-relationship-options">
				<tr>
					<th scope="row">
						<label for="<?php echo $related_id; ?>">
							<?php esc_html_e( 'Related To', 'bp-xprofile-relationship-field' ); ?>
						</label>
					</th>

					<td>
						<select name="<?php echo $related_id; ?>" id="<?php echo $related_id; ?>">
							<?php foreach ( bp_xprofile_relationship_field_get_relationships() as $value => $details ) : ?>

							<?php if ( isset( $details['options'] ) && ! empty( $details['options'] ) ) : ?>

							<optgroup label="<?php echo $details['category']; ?>">
								<?php foreach ( $details['options'] as $_value => $label ) : ?>

								<option value="<?php echo $_value; ?>" <?php selected( $current_field->related_to, $_value ); ?>>
									<?php echo $label; ?>
								</option>

								<?php endforeach; ?>
							</optgroup>

							<?php else : ?>

							<option value="<?php echo $value ?>" <?php selected( $current_field->related_to, $value ); ?>>
								<?php echo $details; ?>
							</option>

							<?php endif; ?>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="<?php echo $selection_id; ?>">
							<?php esc_html_e( 'Selection Method', 'bp-xprofile-relationship-field' ); ?>
						</label>
					</th>

					<td>
						<select name="<?php echo $selection_id; ?>" id="<?php echo $selection_id; ?>" >
							<optgroup label="<?php _ex( 'Single Fields', 'xprofile field type category', 'buddypress' ); ?>">
								<option value="radio"     <?php selected( $current_field->selection_method, 'radio'     ); ?>><?php _ex( 'Radio Buttons',        'xprofile field type', 'buddypress' ); ?></option>
								<option value="selectbox" <?php selected( $current_field->selection_method, 'selectbox' ); ?>><?php _ex( 'Drop Down Select Box', 'xprofile field type', 'buddypress' ); ?></option>
							</optgroup>
							<optgroup label="<?php _ex( 'Multi Fields', 'xprofile field type category', 'buddypress' ); ?>">
								<option value="checkbox"       <?php selected( $current_field->selection_method, 'checkbox'       ); ?>><?php _ex( 'Checkboxes',       'xprofile field type', 'buddypress' ); ?></option>
								<option value="multiselectbox" <?php selected( $current_field->selection_method, 'multiselectbox' ); ?>><?php _ex( 'Multi Select Box', 'xprofile field type', 'buddypress' ); ?></option>
							</optgroup>
						</select>
					</td>
				</tr>

				<tr>
					<th>
						<label for="<?php echo $order_id; ?>">
							<?php esc_html_e( 'Order By', 'bp-xprofile-relationship-field' ); ?>
						</label>
					</th>

					<td>
						<select name="<?php echo $order_id; ?>" id="<?php echo $order_id; ?>" >
							<option value="default"><?php esc_html_e( 'Default', 'bp-xprofile-relationship-field' ); ?></option>
							<?php foreach ( bp_xprofile_relationship_field_order_types() as $order => $label ) : ?>

							<option value="<?php echo $order; ?>" <?php selected( $order, $current_field->order_type ); ?>><?php echo esc_html( $label ); ?></option>

							<?php endforeach; ?>
						</select>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="<?php echo $sort_id; ?>">
							<?php esc_html_e( 'Sort Order', 'bp-xprofile-relationship-field' ); ?>
						</label>
					</th>

					<td>
						<select name="<?php echo $sort_id; ?>" id="<?php echo $sort_id; ?>" >
							<option value="default"><?php esc_html_e( 'Default', 'bp-xprofile-relationship-field' ); ?></option>
							<option value="asc"  <?php selected( 'asc',  $current_field->order_by ); ?>><?php esc_html_e( 'Ascending',  'buddypress' ); ?></option>
							<option value="desc" <?php selected( 'desc', $current_field->order_by ); ?>><?php esc_html_e( 'Descending', 'buddypress' ); ?></option>
						</select>
					</td>
				</tr>

			</table>
			</div>

		</div>

		<?php
	}

	/**
	 * Modify the appearance of the Relationship field's value.
	 *
	 * In use as of BP 2.4+ because of the `$field_id` parameter context.
	 *
	 * @since 1.2.0
	 *
	 * @param mixed $field_value Field value
	 * @param string|int $field_id Field ID.
	 * @return mixed
	 */
	public static function display_filter( $field_value, $field_id = '' ) {
		return bp_xprofile_relationship_field()->display_field( $field_value, 'relationship', $field_id );
	}
}

endif;

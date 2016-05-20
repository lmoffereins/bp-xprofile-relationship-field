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
	 * Constructor for the single relationship field type
	 *
	 * @since 1.0.0
 	 */
	public function __construct() {
		parent::__construct();

		$this->category = _x( 'Multi Fields', 'xprofile field type category', 'buddypress' );
		$this->name     = _x( 'Relationship', 'xprofile field type', 'bp-xprofile-relationship-field' );

		$this->accepts_null_value = true;
		$this->supports_options = false; // Has effect for select options that are saved in DB

		$this->set_format( '/^.+$/', 'replace' );
		do_action( 'bp_xprofile_field_type_relationship', $this );
	}

	/**
	 * Output the edit field HTML for this field type
	 *
	 * Must be used inside the {@link bp_profile_fields()} template loop.
	 *
	 * @since 1.0.0
	 *
	 * @param array $raw_properties Optional key/value array of
	 *                              {@see http://dev.w3.org/html5/markup/input.checkbox.html permitted attributes}
	 *                              that you want to add.
	 */
	public function edit_field_html( array $raw_properties = array() ) {

		// Define local variables
		$user_id = bp_displayed_user_id();
		$method  = bp_xprofile_get_meta( bp_get_the_profile_field_id(), 'field', 'selection_method' );
		$args    = array();

		// user_id is a special optional parameter that we pass to {@link bp_the_profile_field_options()}.
		if ( isset( $raw_properties['user_id'] ) ) {
			$user_id = (int) $raw_properties['user_id'];
			unset( $raw_properties['user_id'] );
		}

		// Setup selection method
		switch ( $method ) {

			// Multiselect
			case 'multiselectbox' :
				$args['multiple'] = 'multiple';

			// Select
			case 'selectbox' :
				$multi        = isset( $args['multiple'] ) ? '[]' : '';
				$args['name'] = bp_get_the_profile_field_input_name() . $multi;
				$args['id']   = bp_get_the_profile_field_input_name() . $multi;

				$html = $this->get_edit_field_html_elements( array_merge( $args, $raw_properties ) ); ?>

			<label for="<?php echo $args['name']; ?>">
				<?php bp_the_profile_field_name(); ?>
				<?php bp_the_profile_field_required_label(); ?>
			</label>

			<?php

			/** This action is documented in bp-xprofile/bp-xprofile-classes */
			do_action( bp_get_the_profile_field_errors_action() ); ?>

			<select <?php echo $html; ?>>
				<?php bp_the_profile_field_options( array(
					'user_id' => $user_id
				) ); ?>
			</select>

			<?php if ( isset( $args['multiple'] ) && ! bp_get_the_profile_field_is_required() ) : ?>

				<a class="clear-value" href="javascript:clear( '<?php echo esc_js( bp_get_the_profile_field_input_name() ); ?>[]' );">
					<?php esc_html_e( 'Clear', 'buddypress' ); ?>
				</a>

			<?php endif; ?>

				<?php
				break;

			// Radio
			case 'checkbox' :
			// Checkbox
			case 'radio' : ?>

			<fieldset class="<?php echo $method; ?>">

				<legend>
					<?php bp_the_profile_field_name(); ?>
					<?php bp_the_profile_field_required_label(); ?>
				</legend>

				<?php

				/** This action is documented in bp-xprofile/bp-xprofile-classes */
				do_action( bp_get_the_profile_field_errors_action() ); ?>

				<?php bp_the_profile_field_options( array(
					'user_id' => $user_id
				) ); ?>

				<?php if ( 'radio' == $method && ! bp_get_the_profile_field_is_required() ) : ?>

					<a class="clear-value" href="javascript:clear( '<?php echo esc_js( bp_get_the_profile_field_input_name() ); ?>' );">
						<?php esc_html_e( 'Clear', 'buddypress' ); ?>
					</a>

				<?php endif; ?>

			</fieldset>

				<?php
				break;
		}
	}

	/**
	 * Output the edit field options HTML for this field type.
	 *
	 * BuddyPress considers a field's "options" to be, for example, the items in a selectbox.
	 * These are stored separately in the database, and their templating is handled seperately.
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
		$options       = bp_xprofile_relationship_field()->get_field_options( $this->field_obj );
		$option_values = BP_XProfile_ProfileData::get_value_byid( $this->field_obj->id, $args['user_id'] );
		$option_values = (array) maybe_unserialize( $option_values );

		$html      = '';
		$method    = $this->field_obj->selection_method;
		$checkbox  = 'checkbox' == $method ? '[]' : '';

		// Check for updated posted values, but errors preventing them from being saved first time
		if ( isset( $_POST['field_' . $this->field_obj->id] ) && $option_values != maybe_serialize( $_POST['field_' . $this->field_obj->id] ) ) {
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

			// Build single option html
			switch ( $method ) {

				case 'radio' :
				case 'checkbox' :

					if ( $selected )
						$selected = ' checked="checked"';

					// Relationships do not support defaults (yet).

					$option_html = '<label for="%3$s" class="option-label"><input %1$s type="' . $method . '" name="%2$s" id="%3$s" value="%4$s">%5$s</label>';
					break;

				case 'selectbox' :

					// Provide a null value before all other options
					if ( 0 == $k ) {
						$html .= sprintf( '<option value="0">%s</option>', __( '&mdash; Select &mdash;', 'bp-xprofile-relationship-field' ) );
					}

				case 'multiselectbox' :

					if ( $selected )
						$selected = ' selected="selected"';

					// Relationships do not support defaults (yet).

					$option_html = '<option %1$s value="%4$s">%5$s</option>';
					break;
			}

			$new_html = sprintf( $option_html,
				$selected,
				esc_attr( "field_{$this->field_obj->id}{$checkbox}" ),
				esc_attr( "field_{$this->field_obj->id}_{$allowed_option}" ),
				esc_attr( stripslashes( $option->id ) ),
				esc_html( stripslashes( $option->name ) )
			);

			$html .= apply_filters( 'bp_get_the_profile_field_options_relationship', $new_html, $option, $this->field_obj->id, $selected, $k );
		}

		echo $html;
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
		$method = bp_xprofile_get_meta( bp_get_the_profile_field_id(), 'field', 'selection_method' );

		// Pre-check selection methods
		switch ( $method ) {
			case 'multiselectbox' :
				$raw_properties['multiple'] = 'multiple';
			case 'selectbox' : ?>

			<select <?php echo $this->get_edit_field_html_elements( $raw_properties ); ?>>

				<?php
				break;
		}

		// Output the field options
		bp_the_profile_field_options();

		// Post-check selection methods
		switch ( $method ) {
			case 'selectbox' :
			case 'multiselectbox' : ?>

			</select>

				<?php

				// Continue only for multiselectbox
				if ( 'multiselectbox' != $method ) {
					break;
				}

			case 'radio' :
				$multi = isset( $raw_properties['multiple'] ) ? '[]' : '';

				if ( ! bp_get_the_profile_field_is_required() ) : ?>
					<a class="clear-value" href="javascript:clear( '<?php echo esc_js( bp_get_the_profile_field_input_name() . $multi ); ?>' );"><?php esc_html_e( 'Clear', 'buddypress' ); ?></a>
				<?php endif;

				break;
		}
	}

	/**
	 * Output HTML for this field type's children options on the wp-admin Profile Fields "Add Field" and "Edit Field" screens.
	 *
	 * Must be used inside the {@link bp_profile_fields()} template loop.
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

		// Setup meta values. Define class
		$current_field = bp_xprofile_relationship_field()->populate_field( $current_field );
		$class = $current_field->type != $type ? 'display: none;' : ''; ?>

		<script types="text/javascript">XProfileAdmin.supports_options_field_types.push( '<?php echo $type; ?>' );</script>

		<div id="<?php echo esc_attr( $type ); ?>" class="postbox bp-options-box" style="<?php echo esc_attr( $class ); ?> margin-top: 15px;">
			<h3><?php esc_html_e( 'Settings for this Field:', 'bp-xprofile-relationship-field' ); ?></h3>
			<div class="inside">
				<p>
					<label for="related_to_<?php echo esc_attr( $type ); ?>"><?php esc_html_e( 'Related To:', 'bp-xprofile-relationship-field' ); ?></label><br/>
					<select name="related_to_<?php echo esc_attr( $type ); ?>" id="related_to_<?php echo esc_attr( $type ); ?>" style="max-width: 200px;">
						<?php foreach ( bp_xprofile_relationship_field()->get_relationships() as $optgroup => $group_data ) {

							// Skip empty optgroups
							if ( empty( $group_data['options'] ) )
								continue; ?>

						<optgroup label="<?php echo $group_data['label']; ?>">
							<?php foreach ( $group_data['options'] as $value => $label ) : ?>

							<option value="<?php echo $value ?>" <?php selected( $current_field->related_to, $value ); ?>><?php echo $label; ?></option>

							<?php endforeach; ?>
						</optgroup>

						<?php } ?>
					</select>
				</p>

				<p>
					<label for="selection_method_<?php echo esc_attr( $type ); ?>"><?php esc_html_e( 'Selection Method:', 'bp-xprofile-relationship-field' ); ?></label><br/>
					<select name="selection_method_<?php echo esc_attr( $type ); ?>" id="selection_method_<?php echo esc_attr( $type ); ?>" >
						<optgroup label="<?php _ex( 'Single Fields', 'xprofile field type category', 'buddypress' ); ?>">
							<option value="radio"     <?php selected( $current_field->selection_method, 'radio'     ); ?>><?php _ex( 'Radio Buttons',        'xprofile field type', 'buddypress' ); ?></option>
							<option value="selectbox" <?php selected( $current_field->selection_method, 'selectbox' ); ?>><?php _ex( 'Drop Down Select Box', 'xprofile field type', 'buddypress' ); ?></option>
						</optgroup>
						<optgroup label="<?php _ex( 'Multi Fields', 'xprofile field type category', 'buddypress' ); ?>">
							<option value="checkbox"       <?php selected( $current_field->selection_method, 'checkbox'       ); ?>><?php _ex( 'Checkboxes',       'xprofile field type', 'buddypress' ); ?></option>
							<option value="multiselectbox" <?php selected( $current_field->selection_method, 'multiselectbox' ); ?>><?php _ex( 'Multi Select Box', 'xprofile field type', 'buddypress' ); ?></option>
						</optgroup>
					</select>
				</p>

				<p>
					<?php /* A little confusing: BP uses 'sort_order' input field for 'order_by' field property */ ?>
					<label for="order_type_<?php echo esc_attr( $type ); ?>"><?php esc_html_e( 'Order By:', 'bp-xprofile-relationship-field' ); ?></label><br/>
					<select name="order_type_<?php echo esc_attr( $type ); ?>" id="order_type_<?php echo esc_attr( $type ); ?>" >
						<option value="default"><?php _e( '&mdash; Default Order &mdash;', 'bp-xprofile-relationship-field' ); ?></option>
						<?php foreach ( bp_xprofile_relationship_field()->get_order_types() as $order => $label ) : ?>

						<option value="<?php echo $order; ?>" <?php selected( $order, $current_field->order_type ); ?>><?php echo esc_html( $label ); ?></option>

						<?php endforeach; ?>
					</select>
				</p>

				<p>
					<?php /* A little confusing: BP uses 'sort_order' input field for 'order_by' field property */ ?>
					<label for="sort_order_<?php echo esc_attr( $type ); ?>"><?php esc_html_e( 'Sort Order:', 'buddypress' ); ?></label><br/>
					<select name="sort_order_<?php echo esc_attr( $type ); ?>" id="sort_order_<?php echo esc_attr( $type ); ?>" >
						<option value="default"><?php _e( '&mdash; Default Sort &mdash;', 'bp-xprofile-relationship-field' ); ?></option>
						<option value="asc"  <?php selected( 'asc',  $current_field->order_by ); ?>><?php esc_html_e( 'Ascending',  'buddypress' ); ?></option>
						<option value="desc" <?php selected( 'desc', $current_field->order_by ); ?>><?php esc_html_e( 'Descending', 'buddypress' ); ?></option>
					</select>
				</p>
			</div>
		</div>

		<?php
	}
}

endif;

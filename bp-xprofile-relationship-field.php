<?php

/**
 * The BuddyPress XProfile Relationship Field Plugin
 *
 * Inspired by Pods' relationship connector.
 *
 * @package BP XProfile Relationship Field
 * @subpackage Main
 */

/**
 * Plugin Name:       BP XProfile Relationship Field
 * Description:       Adds a 'relationship' profile field type to connect users to other objects
 * Plugin URI:        https://github.com/lmoffereins/bp-xprofile-relationship-field
 * Version:           1.0.0
 * Author:            Laurens Offereins
 * Author URI:        https://github.com/lmoffereins
 * Text Domain:       bp-xprofile-relationship-field
 * Domain Path:       /languages/
 * GitHub Plugin URI: lmoffereins/bp-xprofile-relationship-field
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'BP_XProfile_Relationship_Field' ) ) :
/**
 * Main Plugin Class
 *
 * @since 1.0.0
 */
final class BP_XProfile_Relationship_Field {

	/**
	 * The profile field type
	 * @var string
	 */
	public $type = 'relationship';

	/**
	 * Setup singleton pattern and return the instance
	 *
	 * @since 1.0.0
	 *
	 * @return BP_XProfile_Relationship_Field
	 */
	public static function instance() {
		static $instance = null;

		if ( null === $instance ) {
			$instance = new BP_XProfile_Relationship_Field;
			$instance->setup_globals();
			$instance->setup_actions();
		}
		return $instance;
	}

	/**
	 * Prevent object from created more than once
	 *
	 * @since 1.0.0
	 */
	private function __construct() { /* Do nothin' */ }

	/** Private Methods *******************************************************/

	/**
	 * Setup class defaults
	 *
	 * @since 1.0.0
	 */
	private function setup_globals() {

		/** Version **************************************************/

		$this->version      = '1.0.0';

		/** Plugin ***************************************************/

		$this->file         = __FILE__;
		$this->basename     = plugin_basename( $this->file );
		$this->plugin_dir   = plugin_dir_path( $this->file );
		$this->plugin_url   = plugin_dir_url(  $this->file );

		// Includes
		$this->includes_dir = trailingslashit( $this->plugin_dir . 'includes' );
		$this->includes_url = trailingslashit( $this->plugin_url . 'includes' );

		// Languages
		$this->lang_dir     = trailingslashit( $this->plugin_dir . 'languages' );
	}

	/**
	 * Setup the default hooks and actions
	 *
	 * @since 1.0.0
	 *
	 * @uses add_action() To add various actions
	 */
	private function setup_actions() {

		// Main
		add_filter( 'bp_xprofile_get_field_types', array( $this, 'add_field_type' ) );

		// Group fields
		add_filter( 'bp_xprofile_get_groups', array( $this, 'groups_add_field_data' ), 10, 2 );

		// Single field
		add_action( 'xprofile_field_after_save',      array( $this, 'save_field'    )        );
		add_filter( 'bp_get_the_profile_field_value', array( $this, 'display_field' ), 10, 3 );

		// Admin
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_css' ) );
	}

	/** Public Methods ********************************************************/

	/**
	 * Add the relationship field type to BP's xprofile field types
	 *
	 * @since 1.0.0
	 *
	 * @param array $fields Fields
	 * @return array Fields
	 */
	public function add_field_type( $fields ) {

		// Declare field type class
		bp_xprofile_field_type_relationship();

		// Add field type
		$fields[ $this->type ] = 'BP_XProfile_Field_Type_Relationship';

		return $fields;
	}

	/**
	 * Return all the possible object relationships
	 *
	 * @since 1.0.0
	 *
	 * @uses get_post_types()
	 * @uses get_taxonomies()
	 * @uses apply_filters() Calls 'bp_xprofile_relationship_field_relationships'
	 * @return array Relationships
	 */
	public function get_relationships() {

		// Post types
		$post_types = get_post_types( array( 'publicly_queryable' => true ), 'objects' );
		$post_type_keys   = array();
		$post_type_labels = array();
		foreach ( $post_types as $post_type ) {

			// Attachments are handled separately
			if ( 'attachment' == $post_type->name )
				continue;

			$post_type_keys[]   = 'post-type-' . $post_type->name;
			$post_type_labels[] = $post_type->labels->name . ' ('. $post_type->name .')';
		}

		// Taxonomies
		$taxonomies = get_taxonomies( array( 'public' => true ), 'objects' );
		$taxonomy_keys   = array();
		$taxonomy_labels = array();
		foreach ( $taxonomies as $taxonomy ) {
			$taxonomy_keys[]   = 'taxonomy-' . $taxonomy->name;
			$taxonomy_labels[] = $taxonomy->labels->name . ' ('. $taxonomy->name .')';
		}

		// Setup and return all relationships
		return apply_filters( 'bp_xprofile_relationship_field_relationships', array(

			// Post Types
			'post_type' => array(
				'label'   => __( 'Post Types', 'bp-xprofile-relationship-field' ),
				'options' => array_combine( $post_type_keys, $post_type_labels ),
			),

			// Taxonomies
			'taxonomy' => array(
				'label'   => __( 'Taxonomies', 'bp-xprofile-relationship-field' ),
				'options' => array_combine( $taxonomy_keys, $taxonomy_labels),
			),

			// Other WP Objects
			'other' => array(
				'label'   => __( 'Other WP objects', 'bp-xprofile-relationship-field' ),
				'options' => array(
					'users'       => __( 'Users' ),
					'roles'       => __( 'User Roles' ),
					'comments'    => __( 'Comments' ),
					// 'attachments' => __( 'Media' ),
				)
			)
		) );
	}

	/**
	 * Return the object's relationship field options
	 *
	 * @since 1.0.0
	 *
	 * @param BP_XProfile_Field $field Field object
	 * @return array Relationship field options
	 */
	public function get_field_options( $field ) {

		// Get relationships
		$relationships = array_keys( call_user_func_array( 'array_merge', wp_list_pluck( $this->get_relationships(), 'options' ) ) );

		// Setup and fetch field meta
		$field   = $this->populate_field( $field );
		$object  = $field->related_to;

		// Bail if object does not exist
		if ( empty( $object ) || ! in_array( $object, $relationships ) )
			return array();

		// Setup option vars
		$options    = array();
		$orderby    = $field->order_type;
		$order      = $field->order_by;
		$query_args = apply_filters( 'bp_xprofile_relationship_field_options_query_args', array(
			'orderby' => 'default' != $orderby ? $orderby : '',
			'order'   => empty( $order ) || 'ASC' != strtoupper( $order ) ? 'DESC' : 'ASC',
		), $object, $field );

		// Check object to get the options
		switch ( $object ) {

			// Post Type
			case ( 'post-type-' == substr( $object, 0, 10 ) ) :
				$query_args = array_merge( $query_args, array(
					'post_type'      => substr( $object, 10 ),
					'posts_per_page' => -1,
				) );

				// Query and list posts
				foreach ( get_posts( $query_args ) as $post ) {
					$options[] = (object) array( 'id' => $post->ID, 'name' => $post->post_title );
				}
				break;

			// Taxonomy
			case ( 'taxonomy-' == substr( $object, 0, 9 ) ) :
				$taxonomy = substr( $object, 9 );

				// Query and list taxonomy terms
				foreach ( get_terms( $taxonomy, $query_args ) as $term ) {
					$options[] = (object) array( 'id' => $term->term_id, 'name' => $term->name );
				}
				break;

			// Users
			case 'users' :
				if ( 'date' == $orderby )
					$query_args['orderby'] = 'user_registered';

				// Query and list users
				foreach ( get_users( $query_args ) as $user ) {
					$options[] = (object) array( 'id' => $user->ID, 'name' => $user->display_name );
				}
				break;

			// Roles
			case 'roles' :
				global $wp_roles;

				// Fetch global roles
				$_roles = $wp_roles->roles;
				$_order = 'ASC' == $query_args['order'] ? SORT_ASC : SORT_DESC;

				// Order roles
				if ( 'name' == $orderby ) {
					array_multisort( wp_list_pluck( $wp_roles->roles, 'name' ), $_order, SORT_STRING | SORT_FLAG_CASE, $_roles );

				// Handle array sorting for default order
				} else {
					if ( SORT_DESC == $_order )
						$_roles = array_reverse( $_roles );
				}

				// List roles
				foreach ( $_roles as $role_id => $role ) {
					$options[] = (object) array( 'id' => $role_id, 'name' => $role['name'] );
				}
				break;

			// Comments
			case 'comments' :

				// Default 'name' and 'date' orderby to comment date
				if ( in_array( $orderby, array( 'name', 'date' ) ) )
					$query_args['orderby'] = 'comment_date';

				// Query and list comments
				foreach ( get_comments( $query_args ) as $comment ) {
					$options[] = (object) array( 'id' => $comment->comment_ID, 'name' => $comment->comment_date ); // Post Title #num comment?
				}
				break;

			// Attachments
			case 'attachments' :
				// To come...
				break;

			// Custom
			default :
				/**
				 * Filter options to support custom relationship types
				 *
				 * Requires options to be objects that have at least an 'id' and 'name' property.
				 *
				 * @since 1.0.0
				 *
				 * @param array $options The relationship options
				 * @param string $object The relationship object type
				 * @param BP_XProfile_Field $field The current field object
				 * @param array $query_args The items query args like 'orderby' and 'order'
				 * @return array Relationship options
				 */
				$options = apply_filters( 'bp_xprofile_get_relationship_field_options_custom', $options, $object, $field, $query_args );
				break;
		}

		return (array) apply_filters( 'bp_xprofile_get_relationship_field_options', $options, $object, $field, $query_args );
	}

	/**
	 * Return the single option display value
	 *
	 * @since 1.0.0
	 *
	 * @param object $option The option to display
	 * @param object $field Field object
	 * @return string Option display value
	 */
	public function get_single_option_value( $option, $field = '' ) {

		// Bail if no valid option is provided
		if ( empty( $option ) || ! isset( $option->id ) || ! isset( $option->name ) )
			return '';

		// Use global field if not provided
		if ( empty( $field ) ) {
			global $field;
		}

		// Default value to option name
		$value = $option->name;

		// Check field type
		switch ( $field->related_to ) {

			// Post Type
			case ( 'post-type-' == substr( $field->related_to, 0, 10 ) ) :
				$value = sprintf( '<a href="%s" title="%s">%s</a>',
					get_permalink( $option->id ),
					sprintf( __( 'Permalink to %s', 'bp-xprofile-relationship-field' ), $option->name ),
					$option->name
				);
				break;

			// Taxonomy
			case ( 'taxonomy-' == substr( $field->related_to, 0, 9 ) ) :
				break;

			// Users
			case 'users' :
				break;

			// Roles
			case 'roles' :
				break;

			// Comments
			case 'comments' :
				break;

			// Attachments
			case 'attachments' :
				break;
		}

		return apply_filters( 'bp_xprofile_relationship_field_option_value', $value, $option, $field );
	}

	/**
	 * Return the order types for the relationship field
	 *
	 * @since 1.0.0
	 *
	 * @return array Order types
	 */
	public function get_order_types() {
		return apply_filters( 'bp_xprofile_relationship_field_order_types', array(
			'name' => __( 'Name', 'bp-xprofile-relationship-field' ),
			'date' => __( 'Date', 'bp-xprofile-relationship-field' ),
		) );
	}

	/** Groups ****************************************************************/

	/**
	 * Manipulate fields data that were queried with profile groups
	 *
	 * @since 1.0.0
	 *
	 * @param array $groups Groups
	 * @param array $args Group query args
	 * @return array Groups
	 */
	public function groups_add_field_data( $groups, $args ) {
		global $wpdb, $bp;

		// Bail if fields are not fetched
		if ( ! isset( $args['fetch_fields'] ) || ! $args['fetch_fields'] )
			return $groups;

		// Setup local vars
		$data = array();
		$field_ids = implode( ',', wp_list_pluck( call_user_func_array( 'array_merge', wp_list_pluck( $groups, 'fields' ) ), 'id' ) );

		// Walk groups
		foreach ( $groups as $k => $group ) {

			// Walk group fields
			foreach ( $group->fields as $i => $field ) {

				// Ensure order_by property presence
				if ( ! isset( $field->order_by ) ) {

					// Query field data for all fields at once
					if ( empty( $data ) ) {
						$data = (array) $wpdb->get_results( "SELECT id, order_by FROM {$bp->profile->table_name_fields} WHERE id IN ( {$field_ids} )" );
					}

					$field_data = wp_list_filter( $data, array( 'id' => $field->id ) );

					// Set order_by value
					$field->order_by = reset( $field_data )->order_by;
				}

				// Update group field
				$groups[$k]->fields[$i] = $field;
			}
		}

		return $groups;
	}

	/** Admin *****************************************************************/

	/**
	 * Enqueue styles for the admin pages
	 *
	 * @since 1.0.0
	 *
	 * @uses wp_enqueue_style()
	 */
	public function enqueue_admin_css() {

		// Bail if not on profile page
		if ( empty( $_GET['page'] ) || ! in_array( $_GET['page'], array( 'bp-profile-setup', 'bp-profile-edit' ) ) )
			return;

		wp_enqueue_style( 'bp-xprofile-relationship-field-admin', $this->includes_url . 'assets/admin.css', array(), $this->version );
	}

	/** BP_XProfile_Field *****************************************************/

	/**
	 * Return the field type meta keys
	 *
	 * @since 1.0.0
	 *
	 * @return array Meta keys
	 */
	public function get_meta_keys() {
		return apply_filters( 'bp_xprofile_relationship_field_meta_keys', array(
			'related_to',
			'selection_method',
			'order_type', // For order_by value
		) );
	}

	/**
	 * Setup field meta on object population
	 *
	 * @since 1.0.0
	 *
	 * @param BP_XProfile_Field $field Field object
	 */
	public function populate_field( $field ) {

		// Populate field meta
		foreach ( $this->get_meta_keys() as $meta ) {
			$field->$meta = bp_xprofile_get_meta( $field->id, 'field', $meta );
		}

		return $field;
	}

	/**
	 * Delete field data object with meta
	 *
	 * @since 1.0.0
	 *
	 * @param BP_XProfile_Field $field Field object
	 */
	public function delete_field( $field ) {

		// Delete field meta
		foreach ( $this->get_meta_keys() as $meta ) {
			bp_xprofile_delete_meta( $field->id, 'field', $meta );
		}
	}

	/**
	 * Save field object
	 *
	 * @since 1.0.0
	 */
	public function save_field( $field ) {

		// Save field meta
		foreach ( $this->get_meta_keys() as $meta ) {
			if ( ! isset( $_POST[ $meta . '_' . $this->type ] ) )
				continue;

			// Update
			bp_xprofile_update_meta( $field->id, 'field', $meta, $_POST[ $meta . '_' . $this->type ] );
		}
	}

	/**
	 * Display field value
	 *
	 * @since 1.0.0
	 *
	 * @param string $field_value Field value
	 * @param string $field_type Field type
	 * @param int $field_id Field id
	 * @return string Field display value
	 */
	public function display_field( $field_value, $field_type, $field_id ) {
		global $field;

		// Bail if this is not a relationship field
		if ( $field_type != $this->type )
			return $field_value;

		// Get possible field values
		$options = $this->get_field_options( $field );

		// Fetch original value
		$values = explode( ',', $field->data->value );
		$new_values = array();

		// Walk all values
		foreach ( (array) $values as $value ) {

			// Sanitize
			$value = trim( $value );

			// Find option
			$option = wp_list_filter( $options, array( 'id' => (int) $value ) );

			// Use option name if item was found
			if ( ! empty( $option ) ) {
				$new_values[] = $this->get_single_option_value( reset( $option ) );
			}
		}

		$values = implode( ', ', $new_values );

		return apply_filters( 'bp_xprofile_relationship_field_display_value', $values, $field );
	}
}

/**
 * Declare relationship field type class
 *
 * @since 1.0.0
 */
function bp_xprofile_field_type_relationship() {

	// Declare field type once
	if ( class_exists( 'BP_XProfile_Field_Type_Relationship' ) )
		return;

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
			// $this->supports_options = true; // Has only meaning for multiselect options that are saved in DB

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
		 * @param array $raw_properties Optional key/value array of {@link http://dev.w3.org/html5/markup/input.checkbox.html permitted attributes} that you want to add.
		 */
		public function edit_field_html( array $raw_properties = array() ) {
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

				case 'multiselectbox' :
					$args['multiple'] = 'multiple';
				case 'selectbox' :
					$multi = isset( $args['multiple'] ) ? '[]' : '';
					$args['name'] = bp_get_the_profile_field_input_name() . $multi;
					$args['id']   = bp_get_the_profile_field_input_name() . $multi;

					$html = $this->get_edit_field_html_elements( array_merge( $args, $raw_properties ) ); ?>

				<label for="<?php echo $args['name']; ?>"><?php bp_the_profile_field_name(); ?> <?php if ( bp_get_the_profile_field_is_required() ) : ?><?php esc_html_e( '(required)', 'buddypress' ); ?><?php endif; ?></label>
				<?php do_action( bp_get_the_profile_field_errors_action() ); ?>
				<select <?php echo $html; ?>>
					<?php bp_the_profile_field_options( "user_id={$user_id}" ); ?>
				</select>

				<?php if ( isset( $args['multiple'] ) && ! bp_get_the_profile_field_is_required() ) : ?>
					<a class="clear-value" href="javascript:clear( '<?php echo esc_js( bp_get_the_profile_field_input_name() ); ?>[]' );"><?php esc_html_e( 'Clear', 'buddypress' ); ?></a>
				<?php endif; ?>

					<?php
					break;

				case 'radio' :
				case 'checkbox' : ?>

				<div class="<?php echo $method; ?>">

					<label for="<?php bp_the_profile_field_input_name(); ?>"><?php bp_the_profile_field_name(); ?> <?php if ( bp_get_the_profile_field_is_required() ) : ?><?php esc_html_e( '(required)', 'buddypress' ); ?><?php endif; ?></label>
					<?php do_action( bp_get_the_profile_field_errors_action() ); ?>
					<?php bp_the_profile_field_options( "user_id={$user_id}" ); ?>

					<?php if ( 'radio' == $method && ! bp_get_the_profile_field_is_required() ) : ?>
						<a class="clear-value" href="javascript:clear( '<?php echo esc_js( bp_get_the_profile_field_input_name() ); ?>' );"><?php esc_html_e( 'Clear', 'buddypress' ); ?></a>
					<?php endif; ?>

				</div>

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
				$selected    = '';
				$option_html = '';

				// Run the allowed option name through the before_save filter, so we'll be sure to get a match
				$allowed_option = xprofile_sanitize_data_value_before_save( $option->id, false, false );

				// Build single option html
				switch ( $method ) {

					case 'radio' :
					case 'checkbox' :

						// First, check to see whether the user's saved values match the option
						if ( in_array( $allowed_option, $option_values ) ) {
							$selected = ' checked="checked"';
						}

						// Relationships do not support defaults (yet).

						$option_html = '<label><input %1$s type="' . $method . '" name="%2$s" id="%3$s" value="%4$s">%5$s</label>';
						break;

					case 'selectbox' :

						// Provide a null value before all other options
						if ( 0 == $k ) {
							$html .= sprintf( '<option value="0">%s</option>', __( '&mdash; Select &mdash;', 'bp-xprofile-relationship-field' ) );
						}

					case 'multiselectbox' :

						// First, check to see whether the user-entered value matches
						if ( in_array( $allowed_option, $option_values ) ) {
							$selected = ' selected="selected"';
						}

						// Relationships do not support defaults (yet).

						$option_html = '<option %1$s value="%4$s">%5$s</option>';
						break;
				}

				$new_html = sprintf( $option_html,
					$selected,
					esc_attr( "field_{$this->field_obj->id}" . $checkbox ),
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

			<div id="<?php echo esc_attr( $type ); ?>" class="postbox bp-options-box" style="<?php echo esc_attr( $class ); ?> margin-top: 15px;">
				<h3><?php esc_html_e( 'Please enter options for this Field:', 'buddypress' ); ?></h3>
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
							<option value="asc"  <?php selected( 'asc',  $current_field->order_by ); ?>><?php esc_html_e( 'Ascending',  'buddypress' ); ?></option>
							<option value="desc" <?php selected( 'desc', $current_field->order_by ); ?>><?php esc_html_e( 'Descending', 'buddypress' ); ?></option>
						</select>
					</p>
				</div>
			</div>

			<?php
		}
	}
}

/**
 * Instantiate plugin class
 *
 * @since 1.0.0
 *
 * @uses BP_XProfile_Relationship_Field
 */
function bp_xprofile_relationship_field() {

	// Bail if profile fields are not active
	if ( ! bp_is_active( 'xprofile' ) )
		return false;

	return BP_XProfile_Relationship_Field::instance();
}

// Fire it up!
add_action( 'bp_loaded', 'bp_xprofile_relationship_field' );

endif; // class_exists


<?php

/**
 * The BuddyPress XProfile Relationship Field Plugin
 *
 * @package BP XProfile Relationship Field
 * @subpackage Main
 */

/**
 * Plugin Name:       BP XProfile Relationship Field
 * Description:       Add a relationship profile field type to BuddyPress to connect members with other objects
 * Plugin URI:        https://github.com/lmoffereins/bp-xprofile-relationship-field
 * Version:           1.0.3
 * Author:            Laurens Offereins
 * Author URI:        https://github.com/lmoffereins
 * Text Domain:       bp-xprofile-relationship-field
 * Domain Path:       /languages/
 * GitHub Plugin URI: lmoffereins/bp-xprofile-relationship-field
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

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
			$instance->includes();
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

		$this->version      = '1.0.3';

		/** Plugin ***************************************************/

		$this->file         = __FILE__;
		$this->basename     = plugin_basename( $this->file );
		$this->plugin_dir   = plugin_dir_path( $this->file );
		$this->plugin_url   = plugin_dir_url(  $this->file );

		// Includes
		$this->includes_dir = trailingslashit( $this->plugin_dir . 'includes' );
		$this->includes_url = trailingslashit( $this->plugin_url . 'includes' );

		// Assets
		$this->assets_dir   = trailingslashit( $this->plugin_dir . 'assets' );
		$this->assets_url   = trailingslashit( $this->plugin_url . 'assets' );

		// Languages
		$this->lang_dir     = trailingslashit( $this->plugin_dir . 'languages' );

		/** Plugin ***************************************************/

		$this->domain       = 'bp-xprofile-relationship-field';
	}

	/**
	 * Include the required files
	 *
	 * @since 1.1.0
	 */
	private function includes() {
		require( $this->includes_dir . 'functions.php' );
	}

	/**
	 * Setup the default hooks and actions
	 *
	 * @since 1.0.0
	 */
	private function setup_actions() {

		// Plugin
		add_action( 'init', array( $this, 'load_textdomain' ) );

		// Main
		add_filter( 'bp_xprofile_get_field_types', array( $this, 'add_field_type' ) );

		// Group fields
		add_filter( 'bp_xprofile_get_groups', array( $this, 'groups_add_field_data' ), 10, 2 );

		// Single field
		add_action( 'xprofile_field_after_save',      array( $this, 'save_field'    )        );
		add_filter( 'bp_get_the_profile_field_value', array( $this, 'display_field' ), 10, 3 );
		add_filter( 'bp_get_member_field_data',       array( $this, 'display_data'  ), 10, 2 );
		add_filter( 'bp_get_profile_field_data',      array( $this, 'display_data'  ), 10, 2 );

		// Admin
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
	}

	/** Plugin ****************************************************************/

	/**
	 * Load the translation file for current language
	 *
	 * Note that custom translation files inside the Plugin folder will
	 * be removed on Plugin updates. If you're creating custom translation
	 * files, please use the global language folder.
	 *
	 * @since 1.0.0
	 *
	 * @uses apply_filters() Calls 'plugin_locale' with {@link get_locale()} value
	 * @uses load_textdomain() To load the textdomain
	 * @uses load_plugin_textdomain() To load the plugin textdomain
	 */
	public function load_textdomain() {

		// Traditional WordPress plugin locale filter
		$locale        = apply_filters( 'plugin_locale', get_locale(), $this->domain );
		$mofile        = sprintf( '%1$s-%2$s.mo', $this->domain, $locale );

		// Setup paths to current locale file
		$mofile_local  = $this->lang_dir . $mofile;
		$mofile_global = WP_LANG_DIR . '/bp-xprofile-relationship-field/' . $mofile;

		// Look in global /wp-content/languages/bp-xprofile-relationship-field folder first
		load_textdomain( $this->domain, $mofile_global );

		// Look in global /wp-content/languages/plugins/ and local plugin languages folder
		load_plugin_textdomain( $this->domain, false, 'bp-xprofile-relationship-field/languages' );
	}

	/** Public Methods ********************************************************/

	/**
	 * Register the relationship field type
	 *
	 * @since 1.0.0
	 *
	 * @param array $fields Fields
	 * @return array Fields
	 */
	public function add_field_type( $fields ) {

		// Declare field type
		require_once( $this->includes_dir . 'class-bp-xprofile-field-type-relationship.php' );

		// Add field type
		if ( class_exists( 'BP_XProfile_Field_Type_Relationship' ) ) {
			$fields[ bp_xprofile_relationship_field_type() ] = 'BP_XProfile_Field_Type_Relationship';
		}

		return $fields;
	}

	/**
	 * Return the order types for the relationship field type
	 *
	 * @since 1.0.0
	 *
	 * @uses apply_filters() Calls 'bp_xprofile_relationship_field_order_types'
	 * @return array Order types
	 */
	public function get_order_types() {

		/**
		 * Filter the order types for the relationship field type
		 *
		 * @since 1.0.0
		 *
		 * @param array $order_types Order types
		 */
		return apply_filters( 'bp_xprofile_relationship_field_order_types', array(
			'name' => _x( 'Name', 'Object order type', 'bp-xprofile-relationship-field' ),
			'date' => _x( 'Date', 'Object order type', 'bp-xprofile-relationship-field' ),
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

		// Bail when fields are not fetched
		if ( ! isset( $args['fetch_fields'] ) || ! $args['fetch_fields'] )
			return $groups;

		// Collect groups with fields. Groups might by empty.
		$groups_with_fields = array();
		foreach ( $groups as $group ) {
			if ( isset( $group->fields ) ) {
				$groups_with_fields[] = $group;
			}
		}

		// Fetch all field ids to query for their data
		$field_ids = implode( ',', wp_list_pluck( call_user_func_array( 'array_merge', wp_list_pluck( $groups_with_fields, 'fields' ) ), 'id' ) );

		// Query field data for all fields at once
		$data = (array) $wpdb->get_results( "SELECT id, order_by FROM {$bp->profile->table_name_fields} WHERE id IN ( $field_ids )" );

		// Walk groups
		foreach ( $groups as $k => $group ) {

			// Skip groups without fields
			if ( ! isset( $group->fields ) )
				continue;

			// Walk group fields
			foreach ( $group->fields as $i => $field ) {

				// Get data of particular field
				$field_data = wp_list_filter( $data, array( 'id' => $field->id ) );
				if ( ! empty( $field_data ) ) {
					$field_data = reset( $field_data );

				// Field data not found
				} else {
					continue;
				}

				// Ensure order_by property presence
				if ( ! isset( $field->order_by ) ) {
					$field->order_by = $field_data->order_by;
				}

				// Update group field
				$groups[ $k ]->fields[ $i ] = $field;
			}
		}

		return $groups;
	}

	/** Admin *****************************************************************/

	/**
	 * Enqueue styles and scripts for the admin pages
	 *
	 * @since 1.0.0
	 */
	public function admin_enqueue_scripts() {

		// Bail when not on profile fields or edit page
		if ( empty( $_GET['page'] ) || ! in_array( $_GET['page'], array( 'bp-profile-setup', 'bp-profile-edit' ) ) )
			return;

		wp_enqueue_style( 'bp-xprofile-relationship-field', $this->assets_url . 'admin.css', array( 'xprofile-admin-css' ), $this->version );
	}

	/** BP_XProfile_Field *****************************************************/

	/**
	 * Return the field type meta keys
	 *
	 * @since 1.0.0
	 *
	 * @uses apply_filters() Calls 'bp_xprofile_relationship_field_meta_keys'
	 * @return array Meta keys
	 */
	public function get_meta_keys() {

		/**
		 * Filter the available field meta keys for the relationship field type
		 *
		 * @since 1.0.0
		 *
		 * @param array $meta_keys The relationship field meta keys
		 */
		return (array) apply_filters( 'bp_xprofile_relationship_field_meta_keys', array(
			'related_to',
			'selection_method',
			'order_type', // For order_by value
		) );
	}

	/**
	 * Setup field meta on field object population
	 *
	 * @since 1.0.0
	 *
	 * @param BP_XProfile_Field $field Field object
	 * @return BP_XProfile_Field Field object
	 */
	public function populate_field( $field ) {

		// Populate field meta
		foreach ( $this->get_meta_keys() as $meta ) {
			$field->$meta = bp_xprofile_get_meta( $field->id, 'field', $meta );
		}

		return $field;
	}

	/**
	 * Display field value
	 *
	 * @since 1.0.0
	 *
	 * @global BP_XProfile_Field $field
	 *
	 * @uses apply_filters() Calls 'bp_xprofile_relationship_field_display_value'
	 *
	 * @param string $field_value Field value
	 * @param string $field_type Field type
	 * @param int $field_id Field id
	 * @return string Field display value
	 */
	public function display_field( $field_value, $field_type, $field_id = 0 ) {

		// Bail when this is not a relationship field
		if ( bp_xprofile_relationship_field_type() !== $field_type ) {
			return $field_value;
		}

		// Get field. Default to the global field
		if ( ! empty( $field_id ) ) {
			$field = xprofile_get_field( $field_id );
		} else {
			$field = $GLOBALS['field'];
		}

		// Get possible field values
		$options = bp_xprofile_relationship_field_options( $field );
		$options_id_by_name = array_combine( wp_list_pluck( $options, 'id' ), wp_list_pluck( $options, 'name' ) );

		// Fetch original value
		$values = explode( ',', $field->data->value );
		$new_values = array();

		// Walk all values
		foreach ( (array) $values as $value ) {

			// Sanitize
			$value = trim( $value );

			// Find option by id value
			if ( $option_by_value = wp_list_filter( $options, array( 'id' => (int) $value ) ) ) {

				// Use option_by_value name if item was found
				$new_values[] = bp_xprofile_relationship_field_option_value( reset( $option_by_value ) );

			// Find option by name value. Compare sanitized titles
			} elseif ( $id_by_name = array_search( sanitize_title( $value ), array_map( 'sanitize_title', $options_id_by_name ) ) ) {

				// Get the option by value
				$option_by_value = wp_list_filter( $options, array( 'id' => (int) $id_by_name ) );

				// Use option_by_value name if item was found
				$new_values[] = bp_xprofile_relationship_field_option_value( reset( $option_by_value ) );

			// Option was not found, display stored value
			} else {
				$new_values[] = $value;
			}
		}

		$values = implode( ', ', $new_values );

		/**
		 * Filter the total display value of the relationship field
		 *
		 * @since 1.0.0
		 *
		 * @param string $values Field display value
		 * @param BP_XProfile_Field $field The current field object
		 */
		return apply_filters( 'bp_xprofile_relationship_field_display_value', $values, $field );
	}

	/**
	 * Display field data
	 *
	 * Filters {@see bp_get_member_field_data()} and {@see bp_get_profile_field_data()} as per BP 2.6.
	 *
	 * @since 1.0.3
	 *
	 * @global BP_XProfile_Field $field
	 *
	 * @param mixed $data Field data
	 * @param array $args Field data arguments
	 * @return mixed Field data
	 */
	public function display_data( $data, $args = array() ) {

		// Valid field was queried, so get the field
		if ( ! empty( $args['field'] ) && ! empty( $args['user_id'] )
			&& $field = xprofile_get_field( is_numeric( $args['field'] ) ? $args['field'] : xprofile_get_field_id_from_name( $args['field'] ) )
		) {

			// Populate field data
			$field->data = $field->get_field_data( $args['user_id'] );

			// Temporary overwrite of the `$field` global
			if ( $global = isset( $GLOBALS['field'] ) ) {
				$_field = $GLOBALS['field'];
			}
			$GLOBALS['field'] = $field;

			// Define value to display
			$data = $this->display_field( $data, $field->type, $field->id );

			// Reset global
			if ( $global ) {
				$GLOBALS['field'] = $_field;
			} else {
				unset( $GLOBALS['field'] );
			}
		}

		return $data;
	}

	/**
	 * Save field object
	 *
	 * @since 1.0.0
	 *
	 * @param BP_XProfile_Field $field Field object
	 */
	public function save_field( $field ) {

		// Type is posted escaped
		$type = esc_attr( bp_xprofile_relationship_field_type() );

		// Save field meta
		foreach ( $this->get_meta_keys() as $meta ) {

			// Skip when the option was not posted
			if ( ! isset( $_POST[ "{$meta}-{$type}" ] ) )
				continue;

			// Update
			bp_xprofile_update_meta( $field->id, 'field', $meta, $_POST[ "{$meta}-{$type}" ] );
		}
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
}

/**
 * Instantiate plugin class
 *
 * @since 1.0.0
 *
 * @uses BP_XProfile_Relationship_Field
 */
function bp_xprofile_relationship_field() {
	return BP_XProfile_Relationship_Field::instance();
}

// Initiate when BP has loaded
add_action( 'bp_xprofile_setup_actions', 'bp_xprofile_relationship_field' );

endif; // class_exists

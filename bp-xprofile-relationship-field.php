<?php

/**
 * The BuddyPress XProfile Relationship Field Plugin
 *
 * @package BP XProfile Relationship Field
 * @subpackage Main
 */

/**
 * Plugin Name:       BP XProfile Relationship Field
 * Description:       Adds a 'relationship' profile field type to BuddyPress to connect users with other objects
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
	 *
	 * @uses bp_is_active() To check if xprofile component is active
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
	 * Add the relationship field type to BP's xprofile field types
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
		$fields[ $this->type ] = 'BP_XProfile_Field_Type_Relationship';

		return $fields;
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
		$relationships = array_keys( bp_xprofile_relationship_field_get_relationship_options() );

		// Setup and fetch field meta
		$field  = $this->populate_field( $field );
		$object = $field->related_to;

		// Bail when object does not exist
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

				// Query all terms
				$query_args['hide_empty'] = false;

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
	 * Return the single field option display value
	 *
	 * @since 1.0.0
	 *
	 * @param object $option The option to display
	 * @param object $field Field object
	 * @return string Option display value
	 */
	public function get_field_option_value( $option, $field = '' ) {

		// Bail when no valid option is provided
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

				// Link posts to their respective pages
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
	 *
	 * @uses wp_enqueue_style()
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
	 * @uses BP_XProfile_Relationship_Field::get_meta_keys()
	 * @uses bp_xprofile_get_meta()
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
	 * Display field value
	 *
	 * @since 1.0.0
	 *
	 * @uses BP_XProfile_Relationship_Field::get_field_options()
	 * @uses BP_XProfile_Relationship_Field::get_field_option_value()
	 * @uses apply_filters() Calls 'bp_xprofile_relationship_field_display_value'
	 *
	 * @param string $field_value Field value
	 * @param string $field_type Field type
	 * @param int $field_id Field id
	 * @return string Field display value
	 */
	public function display_field( $field_value, $field_type, $field_id ) {
		global $field;

		// Bail when this is not a relationship field
		if ( $field_type != $this->type )
			return $field_value;

		// Get possible field values
		$options = $this->get_field_options( $field );
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
				$new_values[] = $this->get_field_option_value( reset( $option_by_value ) );

			// Find option by name value. Compare sanitized titles
			} elseif ( $id_by_name = array_search( sanitize_title( $value ), array_map( 'sanitize_title', $options_id_by_name ) ) ) {

				// Get the option by value
				$option_by_value = wp_list_filter( $options, array( 'id' => (int) $id_by_name ) );

				// Use option_by_value name if item was found
				$new_values[] = $this->get_field_option_value( reset( $option_by_value ) );

			// Option was not found, display stored value
			} else {
				$new_values[] = $value;
			}
		}

		$values = implode( ', ', $new_values );

		return apply_filters( 'bp_xprofile_relationship_field_display_value', $values, $field );
	}

	/**
	 * Display field data
	 *
	 * Filters {@link bp_get_member_field_data()} and {@link bp_get_profile_field_data()} as per BP 2.6.
	 *
	 * @since 1.0.3
	 *
	 * @global BP_XProfile_Field $field
	 *
	 * @uses xprofile_get_field()
	 * @uses xprofile_get_field_id_from_name()
	 * @uses BP_XProfile_Field::get_field_data()
	 * @uses BP_XProfile_Relationship_Field::display_field()
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
	 * @uses BP_XProfile_Relationship_Field::get_meta_keys()
	 * @uses bp_xprofile_update_meta()
	 * @param BP_XProfile_Field $field Field object
	 */
	public function save_field( $field ) {

		// Save field meta
		foreach ( $this->get_meta_keys() as $meta ) {
			if ( ! isset( $_POST[ $meta . '-' . $this->type ] ) )
				continue;

			// Update
			bp_xprofile_update_meta( $field->id, 'field', $meta, $_POST[ $meta . '-' . $this->type ] );
		}
	}

	/**
	 * Delete field data object with meta
	 *
	 * @since 1.0.0
	 *
	 * @uses BP_XProfile_Relationship_Field::get_meta_keys()
	 * @uses bp_xprofile_delete_meta()
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

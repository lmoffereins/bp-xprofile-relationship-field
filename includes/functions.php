<?php

/**
 * BP XProfile Relationship Field Functions
 *
 * @package BP XProfile Relationship Field
 * @subpackage Main
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Return the relationship field type
 *
 * @since 1.1.0
 *
 * @return string Relationship field type
 */
function bp_xprofile_relationship_field_type() {
	return bp_xprofile_relationship_field()->type;
}

/**
 * Return whether the field is a Relationship field
 *
 * @since 1.2.0
 *
 * @param  BP_XProfile_Field|int $field_id Optional. Field object or ID. Defaults to current field.
 * @return bool Is field of type Relationship?
 */
function bp_xprofile_is_relationship_field( $field = 0 ) {

	// Get field
	if ( $field ) {
		$field = xprofile_get_field( $field );
		$type  = $field->type;
	} else {
		$type = bp_get_the_profile_field_type();
	}

	return $type === bp_xprofile_relationship_field_type();
}

/**
 * Return all the possible object relationships
 *
 * @since 1.0.0
 * @since 1.1.0 Moved to standalone function.
 *
 * @uses apply_filters() Calls 'bp_xprofile_relationship_field_relationships'
 * @return array Relationships
 */
function bp_xprofile_relationship_field_get_relationships() {

	// Post types
	$post_types       = get_post_types( array( 'publicly_queryable' => true ), 'objects' );
	$post_type_keys   = array();
	$post_type_labels = array();

	// Collect post types
	foreach ( $post_types as $post_type ) {

		// Attachments are handled separately
		if ( 'attachment' == $post_type->name )
			continue;

		$post_type_keys[]   = 'post-type-' . $post_type->name;
		$post_type_labels[] = $post_type->labels->name . ' ('. $post_type->name .')';
	}

	// Taxonomies
	$taxonomies      = get_taxonomies( array( 'public' => true ), 'objects' );
	$taxonomy_keys   = array();
	$taxonomy_labels = array();

	// Collect taxonomies
	foreach ( $taxonomies as $taxonomy ) {
		$taxonomy_keys[]   = 'taxonomy-' . $taxonomy->name;
		$taxonomy_labels[] = $taxonomy->labels->name . ' ('. $taxonomy->name .')';
	}

	/**
	 * Filter all available relationship object options
	 *
	 * @since 1.0.0
	 *
	 * @param array $objects Relationship object options
	 */
	return apply_filters( 'bp_xprofile_relationship_field_relationships', array(

		// Post Types
		'post_type' => array(
			'category' => esc_html__( 'Post Types', 'bp-xprofile-relationship-field' ),
			'options'  => array_combine( $post_type_keys, $post_type_labels ),
		),

		// Taxonomies
		'taxonomy' => array(
			'category' => esc_html__( 'Taxonomies', 'bp-xprofile-relationship-field' ),
			'options'  => array_combine( $taxonomy_keys, $taxonomy_labels),
		),

		// Other WP Objects
		// 'attachments' => esc_html__( 'Media' ),
		'users'       => esc_html__( 'Users' ),
		'roles'       => esc_html__( 'User Roles' ),
		'comments'    => esc_html__( 'Comments' ),
	) );
}

/**
 * Return all available relationship object options
 *
 * @since 1.1.0
 *
 * @return array Relationship options as array( $key => $label )
 */
function bp_xprofile_relationship_field_get_relationship_options() {

	// Define return value
	$options = array();

	// Walk all relationships
	foreach ( bp_xprofile_relationship_field_get_relationships() as $type => $details ) {
		if ( is_array( $details ) && isset( $details['options'] ) ) {
			$options += $details['options'];
		} else {
			$options[ $type ] = $details;
		}
	}

	return $options;
}

/**
 * Return the object's relationship field options
 *
 * @since 1.0.0
 * @since 1.1.0 Moved to standalone function.
 *
 * @uses apply_filters() Calls 'bp_xprofile_get_relationship_field_options_query_args'
 * @uses apply_filters() Calls 'bp_xprofile_get_relationship_field_{$object}_options'
 * @uses apply_filters() Calls 'bp_xprofile_get_relationship_field_options'
 *
 * @param BP_XProfile_Field $field Field object
 * @return array Relationship field options
 */
function bp_xprofile_relationship_field_options( $field ) {

	// Get relationships
	$relationships = array_keys( bp_xprofile_relationship_field_get_relationship_options() );

	// Setup and fetch field meta
	$field  = bp_xprofile_relationship_field()->populate_field( $field );
	$object = $field->related_to;

	// Bail when object does not exist
	if ( empty( $object ) || ! in_array( $object, $relationships ) )
		return array();

	// Setup option vars
	$options    = array();
	$query_args = array(
		'orderby' => ( 'default' !== $field->order_type ) ? $field->order_type : '',
		'order'   => empty( $field->order_type ) || ( 'DESC' !== strtoupper( $field->order_type ) ) ? 'ASC' : 'DESC',
	);

	/**
	 * Filter options to support custom relationship types
	 *
	 * @since 1.0.0
	 *
	 * @param array $query_args The items query args like 'orderby' and 'order'
	 * @param string $object The relationship object type
	 * @param BP_XProfile_Field $field The current field object
	 */
	$query_args = apply_filters( 'bp_xprofile_relationship_field_options_query_args', $query_args, $object, $field );

	// Check object to get the options
	switch ( $object ) {

		// Post Type
		case ( 'post-type-' === substr( $object, 0, 10 ) ) :
			$query_args = array_merge( $query_args, array(
				'post_type'      => substr( $object, 10 ),
				'posts_per_page' => -1,
			) );

			// Define post query
			$query = new WP_Query( $query_args );

			// Query and list posts
			foreach ( $query->posts as $post ) {
				$options[] = (object) array( 'id' => $post->ID, 'name' => $post->post_title );
			}

			break;

		// Taxonomy
		case ( 'taxonomy-' === substr( $object, 0, 9 ) ) :
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
			if ( 'date' === $query_args['orderby'] )
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
			$_order = 'ASC' === $query_args['order'] ? SORT_ASC : SORT_DESC;

			// Order roles
			if ( 'name' === $query_args['orderby'] ) {
				array_multisort( wp_list_pluck( $wp_roles->roles, 'name' ), $_order, SORT_STRING | SORT_FLAG_CASE, $_roles );

			// Handle array sorting for default order
			} else {
				if ( SORT_DESC === $_order )
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
			if ( in_array( $query_args['orderby'], array( 'name', 'date' ) ) )
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

		// Custom relationship type
		default :

			/**
			 * Filter options to support custom relationship types
			 *
			 * Requires options to be an array of objects having at least an 'id' and 'name' property.
			 *
			 * @since 1.0.0
			 *
			 * @param array $options The relationship options
			 * @param string $object The relationship object type
			 * @param BP_XProfile_Field $field The current field object
			 * @param array $query_args The items query args like 'orderby' and 'order'
			 */
			$options = (array) apply_filters( "bp_xprofile_get_relationship_field_{object}_options", $options, $object, $field, $query_args );
	}

	/**
	 * Filter options for the relationship field type
	 *
	 * Requires options to be an array of objects having at least an 'id' and 'name' property.
	 *
	 * @since 1.0.0
	 *
	 * @param array $options The relationship options
	 * @param string $object The relationship object type
	 * @param BP_XProfile_Field $field The current field object
	 * @param array $query_args The items query args like 'orderby' and 'order'
	 */
	return (array) apply_filters( 'bp_xprofile_get_relationship_field_options', $options, $object, $field, $query_args );
}

/**
 * Return the single field option display value
 *
 * @since 1.0.0
 * @since 1.1.0 Moved to standalone function.
 *
 * @uses apply_filters() Calls 'bp_xprofile_relationship_field_option_value'
 *
 * @param object $option The option to display
 * @param object $field Field object
 * @return string Option display value
 */
function bp_xprofile_relationship_field_option_value( $option, $field = '' ) {

	// Bail when no valid option is provided
	if ( empty( $option ) || ! isset( $option->id ) || ! isset( $option->name ) )
		return '';

	// Default to the option's name
	$value = $option->name;

	// Default to the global field
	if ( empty( $field ) && isset( $GLOBALS['field'] ) ) {
		$field = bp_xprofile_relationship_field()->populate_field( $GLOBALS['field'] );

	// Bail when no field context is present
	} else {
		return $value;
	}

	// Check field type
	switch ( $field->related_to ) {

		// Post Type
		case ( 'post-type-' === substr( $field->related_to, 0, 10 ) ) :

			// Link posts to their respective pages
			$value = sprintf( '<a href="%s" title="%s">%s</a>',
				get_permalink( $option->id ),
				sprintf( esc_html__( 'Permalink to %s', 'bp-xprofile-relationship-field' ), $option->name ),
				$option->name
			);

			break;

		// Taxonomy
		case ( 'taxonomy-' === substr( $field->related_to, 0, 9 ) ) :
			$taxonomy = get_taxonomy( substr( $field->related_to, 9 ) );

			// When the taxonomy has an archive, link the term
			if ( $taxonomy->query_var ) {

				// Link terms to their respective pages
				$value = sprintf( '<a href="%s" title="%s">%s</a>',
					get_term_link( $option->id ),
					sprintf( esc_html__( 'Permalink to %s', 'bp-xprofile-relationship-field' ), $option->name ),
					$option->name
				);
			}

			break;

		// Users
		case 'users' :

			// Link user to the member's profile
			$value = sprintf( '<a href="%s" title="%s">%s</a>',
				bp_core_get_user_domain( $option->id ),
				sprintf( esc_html__( 'Visit the profile of %s', 'bp-xprofile-relationship-field' ), $option->name ),
				$option->name
			);

			break;

		// Comments
		case 'comments' :

			// Link comment to it's location
			$value = sprintf( '<a href="%s" title="%s">%s</a>',
				get_comment_link( $option->id ),
				sprintf( __( 'Permalink to %s', 'bp-xprofile-relationship-field' ), $option->name ),
				$option->name
			);

			break;

		// Attachments
		case 'attachments' :
			break;
	}

	/**
	 * Filter the display value of a single relationship field option
	 *
	 * This could be the value of one of many field options.
	 *
	 * @since 1.0.0
	 *
	 * @param object $option The single field option with 'id' and 'name' properties.
	 * @param BP_XProfile_Field $field The current field object
	 * @param string $value Display value of the option
	 */
	return apply_filters( 'bp_xprofile_relationship_field_option_value', $value, $option, $field );
}

/**
 * Return the order types for the relationship field type
 *
 * @since 1.0.0
 * @since 1.1.0 Moved to standalone function.
 *
 * @uses apply_filters() Calls 'bp_xprofile_relationship_field_order_types'
 * @return array Order types
 */
function bp_xprofile_relationship_field_order_types() {

	/**
	 * Filter the order types for the relationship field type
	 *
	 * @since 1.0.0
	 *
	 * @param array $order_types Order types
	 */
	return apply_filters( 'bp_xprofile_relationship_field_order_types', array(
		'name' => esc_html_x( 'Name', 'Object order type', 'bp-xprofile-relationship-field' ),
		'date' => esc_html_x( 'Date', 'Object order type', 'bp-xprofile-relationship-field' ),
	) );
}

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
 * Return all the possible object relationships
 *
 * @since 1.0.0
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
			'category' => __( 'Post Types', 'bp-xprofile-relationship-field' ),
			'options'  => array_combine( $post_type_keys, $post_type_labels ),
		),

		// Taxonomies
		'taxonomy' => array(
			'category' => __( 'Taxonomies', 'bp-xprofile-relationship-field' ),
			'options'  => array_combine( $taxonomy_keys, $taxonomy_labels),
		),

		// Other WP Objects
		// 'attachments' => __( 'Media' ),
		'users'       => __( 'Users' ),
		'roles'       => __( 'User Roles' ),
		'comments'    => __( 'Comments' ),
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

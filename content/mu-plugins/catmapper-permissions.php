<?php

/**
Plugin Name: Roles & Permissions for CatMapper.ca
Plugin URI: https://catmapper.ca
Description: set Roles & Permissions for CatMapper.ca
Version: 3.0
Author: Joachim Kudish
Author URI: http://jkudish.com/
*/

define( 'CATMAPPER_ROLES_AND_PERMISSIONS_VERSION', 3.0 );

add_filter( 'cat_tracker_map_post_type_args', function( $post_type_args ){
	$post_type_args['capability_type'] = 'map';
	return $post_type_args;
});

add_filter( 'cat_tracker_markers_post_type_args', function( $post_type_args ){
	$post_type_args['capability_type'] = 'marker';
	return $post_type_args;
});

add_filter( 'cat_tracker_marker_type_taxonomy_args', function( $taxonomy_args ){
	$taxonomy_args['capabilities'] = array(
		'manage_terms' => 'manage_marker_types',
		'edit_terms' => 'edit_marker_types',
		'delete_terms' => 'delete_marker_types',
		'assign_terms' => 'edit_markers',
	);
	return $taxonomy_args;
});


add_action( 'init', 'catmapper_roles_and_permissions' );
function catmapper_roles_and_permissions() {

	remove_post_type_support( 'page', 'thumbnail' );

	// only modify permissions when an admin visits the dashboard or via WP CLI
	if ( ( ! is_admin() || ! current_user_can( 'edit_users' ) ) && ( ! defined( 'WP_CLI' ) || ! WP_CLI ) )
		return;

	if ( CATMAPPER_ROLES_AND_PERMISSIONS_VERSION == get_option( 'catmapper_roles_and_permissions_version' ) )
		return;

		remove_role( 'subscriber' );
		remove_role( 'author' );
		remove_role( 'contributor' );
		remove_role( 'editor' );

    $administrator = get_role( 'administrator' );

    // full map permissions
    $administrator->add_cap( 'edit_map' );
    $administrator->add_cap( 'read_map' );
    $administrator->add_cap( 'delete_map' );
    $administrator->add_cap( 'edit_maps' );
    $administrator->add_cap( 'edit_others_maps' );
    $administrator->add_cap( 'publish_maps' );
    $administrator->add_cap( 'read_private_maps' );
    $administrator->add_cap( 'delete_maps' );
    $administrator->add_cap( 'delete_private_maps' );
    $administrator->add_cap( 'delete_published_maps' );
    $administrator->add_cap( 'delete_others_maps' );
    $administrator->add_cap( 'edit_private_maps' );
    $administrator->add_cap( 'edit_published_maps' );

    // full sighting permissions
    $administrator->add_cap( 'edit_marker' );
    $administrator->add_cap( 'read_marker' );
    $administrator->add_cap( 'delete_marker' );
    $administrator->add_cap( 'edit_markers' );
    $administrator->add_cap( 'edit_others_markers' );
    $administrator->add_cap( 'publish_markers' );
    $administrator->add_cap( 'read_private_markers' );
    $administrator->add_cap( 'delete_markers' );
    $administrator->add_cap( 'delete_private_markers' );
    $administrator->add_cap( 'delete_published_markers' );
    $administrator->add_cap( 'delete_others_markers' );
    $administrator->add_cap( 'edit_private_markers' );
    $administrator->add_cap( 'edit_published_markers' );

	// full intake type permissions
	$administrator->add_cap( 'manage_intake_types' );

    // import permissions
    $administrator->add_cap( 'import_markers' );

	// export permissions
    $administrator->add_cap( 'export_markers' );

		remove_role( 'bcspca_employee' );
		add_role( 'bcspca_employee', 'BC SPCA Employee', array(
			// dashboard access
			'read' => true,

			// view map
			'read_map' => true,

			// full sighting permissions
			'edit_marker' => true,
			'read_marker' => true,
			'delete_marker' => true,
			'edit_markers' => true,
			'edit_others_markers' => true,
			'publish_markers' => true,
			'read_private_markers' => true,
			'delete_markers' => true,
			'delete_private_markers' => true,
			'delete_published_markers' => true,
			'delete_others_markers' => true,
			'edit_private_markers' => true,
			'edit_published_markers' => true,

			// marker type permissions
			'manage_marker_types' => true,
			'edit_marker_types' => true,
			'delete_marker_types' => true,

			// import permissions
			'import_markers' => true,

			// export permissions
			'export_markers' => true,

			// limited page permissions
			'edit_pages' => true,
			'delete_pages' => true,
			'edit_others_pages' => true,
			'edit_private_pages' => true,
			'read_private_pages' => true,
			'edit_published_pages' => true,

			// media
			'edit_posts' => true,
			'delete_posts' => true,
			'edit_others_posts' => true,
			'delete_others_posts' => true,
			'upload_files' => true,
			'edit_files' => true,

			// full intake type permissions
			'manage_intake_types' => true,
		) );

		remove_role( 'sightings_administrator' );
		add_role( 'sightings_administrator', 'Sightings Administrator', array(
			// dashboard access
			'read' => true,

			// view map
			'read_map' => true,

			// full sighting permissions
			'edit_marker' => true,
			'read_marker' => true,
			'delete_marker' => true,
			'edit_markers' => true,
			'edit_others_markers' => true,
			'publish_markers' => true,
			'read_private_markers' => true,
			'delete_markers' => true,
			'delete_private_markers' => true,
			'delete_published_markers' => true,
			'delete_others_markers' => true,
			'edit_private_markers' => true,
			'edit_published_markers' => true,

			// marker type permissions
			'manage_marker_types' => true,
			'edit_marker_types' => true,
			'delete_marker_types' => true,

			// limited page permissions
			'edit_pages' => true,
			'delete_pages' => true,
			'edit_others_pages' => true,
			'edit_private_pages' => true,
			'read_private_pages' => true,
			'edit_published_pages' => true,
		) );

		update_option( 'catmapper_roles_and_permissions_version',  CATMAPPER_ROLES_AND_PERMISSIONS_VERSION );
}
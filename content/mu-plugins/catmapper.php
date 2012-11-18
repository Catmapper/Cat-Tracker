<?php

/**
Plugin Name: Cat Tracker Modifier for catmapper.ca
Plugin URI: https://github.com/jkudish/Cat-Tracker
Description: Additional modifiers for the Cat Tracking Software
Version: 1.0
Author: Joachim Kudish
Author URI: http://jkudish.com/
License: GPLv2
*/

/**
 * @package Cat Mapper
 * @author Joachim Kudish
 * @version 1.0
*/

/*
This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

/**
 * remove admin menus that we don't need for the Cat Tracker
 *
 * @since 1.0
 * @return void
 */
add_action( 'admin_menu', 'cat_mapper_remove_admin_menus' );
function cat_mapper_remove_admin_menus() {
	if ( is_network_admin() )
		return;

	remove_menu_page( 'edit.php' ); // Posts
	remove_menu_page( 'edit-comments.php' ); // Comments
	remove_menu_page( 'tools.php' ); // Tools
	remove_menu_page( 'plugins.php' ); // Plugins
	remove_submenu_page( 'options-general.php', 'options-writing.php' ); // Writing options
	remove_submenu_page( 'options-general.php', 'options-discussion.php' ); // Discussion options
	remove_submenu_page( 'options-general.php', 'options-reading.php' ); // Reading options
	remove_submenu_page( 'options-general.php', 'options-media.php' ); // Media options
	remove_submenu_page( 'options-general.php', 'options-permalink.php' ); // Permalink options
}

/**
 * filter the map source to display with the custom styles
 *
 * @since 1.0
 * @return void
 */
add_filter( 'cat_tracker_map_source', 'cat_mapper_map_source' );
function cat_mapper_map_source() {
	if ( Cat_Tracker::is_submission_mode() || is_admin() )
		return 'http://b.tile.cloudmade.com/BC9A493B41014CAABB98F0471D759707/75872/256/{z}/{x}/{y}.png';

	return 'http://b.tile.cloudmade.com/BC9A493B41014CAABB98F0471D759707/75869/256/{z}/{x}/{y}.png';
}

/**
 * filter the map attribution to display a notice about Cloudmade
 *
 * @since 1.0
 * @return void
 */
add_filter( 'cat_tracker_map_attribution', 'cat_mapper_map_attribution' );
function cat_mapper_map_attribution( $map_attribution ) {
	$map_attribution .= ' &mdash; Map Styles © Cloudmade';
	return $map_attribution;
}

/**
 * deregister post types from main site aka blog ID #1
 *
 * @since 1.0
 * @return void
 */
add_action( 'init', 'catmapper_deregister_post_types', 20 );
function catmapper_deregister_post_types() {
	if ( 1 !== get_current_blog_id() )
		return;

	global $wp_post_types;
	foreach ( array( Cat_Tracker::MAP_POST_TYPE, Cat_Tracker::MARKER_POST_TYPE ) as $post_type ) {
		if ( isset( $wp_post_types[$post_type] ) )
			unset( $wp_post_types[$post_type] );
	}
}

 * add new fields specific to catmapper
 *
 * @since 1.0
 * @return void
 */
add_action( 'cat_tracker_did_custom_fields', 'cat_mapper_custom_fields' );
function cat_mapper_custom_fields() {
	x_add_metadata_group( 'bcspca_extra_information', array( Cat_Tracker::MARKER_POST_TYPE ), array( 'label' => 'BC SCPA Import Info', 'priority' => 'high' ) );
	x_add_metadata_field( Cat_Tracker::META_PREFIX . 'animal_id', array( Cat_Tracker::MARKER_POST_TYPE ), array( 'field_type' => 'text', 'group' => 'bcspca_extra_information', 'label' => 'Animal ID', 'readonly' => true ) );
	x_add_metadata_field( Cat_Tracker::META_PREFIX . 'animal_name', array( Cat_Tracker::MARKER_POST_TYPE ), array( 'field_type' => 'text', 'group' => 'bcspca_extra_information', 'label' => 'Animal name', 'readonly' => true ) );
	x_add_metadata_field( Cat_Tracker::META_PREFIX . 'source', array( Cat_Tracker::MARKER_POST_TYPE ), array( 'field_type' => 'text', 'group' => 'bcspca_extra_information', 'label' => 'Animal source', 'readonly' => true ) );
	x_add_metadata_field( Cat_Tracker::META_PREFIX . 'breed', array( Cat_Tracker::MARKER_POST_TYPE ), array( 'field_type' => 'text', 'group' => 'bcspca_extra_information', 'label' => 'Animal breed', 'readonly' => true ) );
	x_add_metadata_field( Cat_Tracker::META_PREFIX . 'color', array( Cat_Tracker::MARKER_POST_TYPE ), array( 'field_type' => 'text', 'group' => 'bcspca_extra_information', 'label' => 'Animal color', 'readonly' => true ) );
	x_add_metadata_field( Cat_Tracker::META_PREFIX . 'gender', array( Cat_Tracker::MARKER_POST_TYPE ), array( 'field_type' => 'text', 'group' => 'bcspca_extra_information', 'label' => 'Animal gender', 'readonly' => true ) );
}

/**
 * exclude bcspca
 *
 * @since 1.0
 * @return void
 */
add_filter( 'cat_tracker_submission_form_dropdown_categories_args', 'cat_mapper_excluded_types_from_submission' );
function cat_mapper_excluded_types_from_submission( $args ) {
	$type_of_sightings = get_terms( Cat_Tracker::MARKER_TAXONOMY, array( 'hide_empty' => false ) );
	if ( empty( $type_of_sightings ) )
		return $args;

	$sighting_ids_and_slugs = array_combine( wp_list_pluck( $type_of_sightings, 'term_id' ), wp_list_pluck( $type_of_sightings, 'slug' ) );
	if ( empty( $sighting_ids_and_slugs ) )
		return $args;

	$excluded_slugs = array(
		'bcspca-unowned-intake-cat',
		'bcspca-unowned-intake-kitten',
		'katies-place-unowned-intake-cat',
		'katies-place-unowned-intake-kitten',
	);

	$excluded_ids = array();
	foreach ( $excluded_slugs as $excluded_slug )
		$excluded_ids[] = array_search( $excluded_slug, $sighting_ids_and_slugs );

	if ( ! empty( $excluded_ids ) )
		$args['exclude'] = $excluded_ids;

	return $args;
}
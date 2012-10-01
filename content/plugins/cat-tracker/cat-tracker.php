<?php
/*
Plugin Name: Cat Tracker
Plugin URI: https://github.com/jkudish/Cat-Tracker
Description: Cat tracking software built on WordPress
Version: 1.0
Author: Joachim Kudish
Author URI: http://jkudish.com/
License: GPLv2
*/

/**
 * @package Cat_Tracker
 * @author Joachim Kudish
 * @version 1.0
 *
 * Note: this plugin requires Custom Metadata Manager plugin in
 * order to properly function, without it custom fields will not work
 * @link http://wordpress.org/extend/plugins/custom-metadata/
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

class Cat_Tracker {

	/**
	 * @var Cat_Tracker The one true Cat Tracker
	 */
	private static $instance;

	/**
	 * current version # of this plugin
	 */
	const VERSION = 1.0;

	/**
	 * Singleton class for this Cat Tracker
	 *
	 * @since 1.0
	 * @return object $instance the singleton instance of this class
	 */
	public static function instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new Cat_Tracker;
			self::$instance->run_hooks();
		}

		return self::$instance;
	}

	/**
	 * do nothing on construct
	 *
	 * @since 1.0
	 * @see instance()
	 */
	public function __construct() {}

	/**
	 * the meat & potatoes of this plugin
	 *
	 * @since 1.0
	 * @return void
	 */
	public function run_hooks() {

		add_action( 'init', array( $this, 'register_post_types' ) );
		add_action( 'admin_menu', array( $this, 'custom_fields' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue' ) );

	}

	/**
	 * register post types needed for the cat tracker
	 *
	 * @since 1.0e
	 * @return void
	 */
	public function register_post_types() {

		$maps_labels = apply_filters( 'cat_tracker_map_post_type_labels', array(
			'name' => __( 'Maps', 'cat_tracker' ),
			'menu_name' => __( 'Maps', 'cat_tracker' ),
			'singular_name' => __( 'Map', 'cat_tracker' ),
			'all_items' => __( 'All Maps', 'cat_tracker' ),
			'add_new' => __( 'New Map', 'cat_tracker' ),
			'add_new_item' => __( 'Create New Map', 'cat_tracker' ),
			'edit' => __( 'Edit', 'cat_tracker' ),
			'edit_item' => __( 'Edit Map', 'cat_tracker' ),
			'new_item' => __( 'New Map', 'cat_tracker' ),
			'view' => __( 'View Map', 'cat_tracker' ),
			'view_item' => __( 'View Map', 'cat_tracker' ),
			'search_items' => __( 'Search Maps', 'cat_tracker' ),
			'not_found' => __( 'No maps found', 'cat_tracker' ),
			'not_found_in_trash' => __( 'No maps found in Trash', 'cat_tracker' ),
			'parent_item_colon' => __( 'Parent Map:', 'cat_tracker' )
		) );

		$maps_cpt_args = apply_filters( 'cat_tracker_map_post_type_args', array(
			'labels' => $maps_labels,
			'rewrite' => array( 'slug' => 'locations', 'with_front' => false ),
			'supports' => array( 'title' ),
			'description' => __( 'Cat Tracker Maps', 'cat_tracker' ),
			'has_archive' => true,
			'exclude_from_search' => true,
			'show_in_nav_menus' => true,
			'public' => true,
			'show_ui' => true,
			'can_export' => true,
			'hierarchical' => false,
			'query_var' => true,
			'menu_icon' => ''
		) );

		register_post_type( 'map', $maps_cpt_args );

		$markers_labels = apply_filters( 'cat_tracker_markers_post_type_labels', array(
			'name' => __( 'Sightings', 'cat_tracker' ),
			'menu_name' => __( 'Sightings', 'cat_tracker' ),
			'singular_name' => __( 'Sighting', 'cat_tracker' ),
			'all_items' => __( 'All Sightings', 'cat_tracker' ),
			'add_new' => __( 'New Sighting', 'cat_tracker' ),
			'add_new_item' => __( 'Create New Sighting', 'cat_tracker' ),
			'edit' => __( 'Edit', 'cat_tracker' ),
			'edit_item' => __( 'Edit Sighting', 'cat_tracker' ),
			'new_item' => __( 'New Sighting', 'cat_tracker' ),
			'view' => __( 'View Sighting', 'cat_tracker' ),
			'view_item' => __( 'View Sighting', 'cat_tracker' ),
			'search_items' => __( 'Search Sightings', 'cat_tracker' ),
			'not_found' => __( 'No sightings found', 'cat_tracker' ),
			'not_found_in_trash' => __( 'No sightings found in Trash', 'cat_tracker' ),
			'parent_item_colon' => __( 'Parent Sighting:', 'cat_tracker' )
		) );

		$markers_cpt_args = apply_filters( 'cat_tracker_markers_post_type_args', array(
			'labels' => $markers_labels,
			'rewrite' => false,
			'supports' => array(),
			'description' => __( 'Cat Tracker Sightings', 'cat_tracker' ),
			'has_archive' => false,
			'exclude_from_search' => true,
			'show_in_nav_menus' => true,
			'public' => false,
			'show_ui' => true,
			'can_export' => true,
			'hierarchical' => false,
			'query_var' => false,
			'menu_icon' => ''
		) );

		register_post_type( 'marker', $markers_cpt_args );

	}

	/**
	 * custom metadata for maps and markers
	 *
	 * @todo provide an error for the user
	 * @since 1.0
	 * @return void
	 */
	public function custom_fields() {

		/**
		 * Note: this plugin requires Custom Metadata Manager plugin in
 		 * order to properly function, without it custom fields will not work
 		 * @link http://wordpress.org/extend/plugins/custom-metadata/
 		 *
 		 * bail if the needed functions don't exist
		 */
		if ( ! function_exists( 'x_add_metadata_field' ) || ! function_exists( 'x_add_metadata_group' ) )
			return;

	}

	public function admin_enqueue() {
		wp_enqueue_style( 'cat-tracker', plugins_url( 'resources/cat-tracker.css', __FILE__ ), array(), self::VERSION );
	}

}

Cat_Tracker::instance();
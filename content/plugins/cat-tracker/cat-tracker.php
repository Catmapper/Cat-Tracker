<?php
/*
Plugin Name: Cat Tracker
Plugin URI: https://github.com/jkudish/Cat-Tracker
Description: Cat tracking software built on WordPress
Version: 1.1
Author: Joachim Kudish
Author URI: http://jkudish.com/
License: GPLv2
*/

/**
 * @package Cat_Tracker
 * @author Joachim Kudish
 * @version 1.1
 *
 * Note: this plugin requires 2 other plugins in
 * order to properly function:
 *
 *	-- 	Custom Metadata Manager, without it custom fields will not work
 * 			@link http://wordpress.org/extend/plugins/custom-metadata/
 *
 * 	-- 	Taxonomy Metadata, without it term meta / sighting type colors will not work
 * 			@link http://wordpress.org/extend/plugins/taxonomy-metadata/
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
	 * current version # of this plugin
	 * @since 1.0
	 */
	const VERSION = 1.1;

	/**
	 * current Leaflet version incldued with this plugin
	 * @since 1.0
	 */
	const LEAFLET_VERSION = '0.4.4';

	/**
	 * current select2 version incldued with this plugin
	 * @since 1.0
	 */
	const SELECT2_VERSION = '3.2';

	/**
	 * cat tracker map post type
	 * @since 1.0
	 */
	const MAP_POST_TYPE = 'cat_tracker_map';

	/**
	 * cat tracker marker post type
	 * @since 1.0
	 */
	const MARKER_POST_TYPE = 'cat_tracker_marker';

	/**
	 * cat tracker sighting taxonomy
	 * @since 1.0
	 */
	const MARKER_TAXONOMY = 'cat_tracker_marker_type';

	/**
	 * cat tracker metadata prefix
	 * @since 1.0
	 */
	const META_PREFIX = 'cat_tracker_';

	/**
	 * cat tracker map drodpdown transient/cache key
	 * @since 1.0
	 */
	const MAP_DROPDOWN_TRANSIENT = 'cat_tracker_map_admin_dropdown_v1';

	/**
	 * cat tracker markers transient/cache key prefix
	 * NOTE: if bumped, please add old key(s) to $transients_to_delete
	 * @since 1.0
	 */
	const MARKERS_CACHE_KEY_PREFIX = 'cat_tracker_marker_v2_';

	/**
	 * cat tracker limit the number of markers per query for performance & cache size reasons
	 * @since 1.0
	 */
	const MARKERS_LIMIT_PER_QUERY = 1300;

	/**
	 * cat tracker default context for displaying map markers
	 * @since 1.0
	 */
	const DEFAULT_MARKER_CONTEXT = 'front_end';

	/**
	 * @var the one true Cat Tracker
	 * @since 1.0
	 */
	private static $instance;

	/**
	 * @var path to this plugin
	 * @since 1.0
	 */
	public $path;


	/**
	 * @var current sighting submission, if there is one
	 * @since 1.0
	 */
	public $sighting_submission;

	/**
	 * @var current context, if there is one
	 * @since 1.0
	 */
	public $current_context;

	/**
	 * cat tracker "intake" sighting type slugs
	 * since there are different "versions" of this, it's an array
	 * @since 1.0
	 */
	public $_marker_intake_types = array(
		'intake',
		'spca-intake-cat',
		'spca-intake-cats',
		'spca-cat-intake',
		'spca-cats-intake',
		'bc-spca-unowned-intake-cat',
		'bc-spca-unowned-intake-kitten',
	);


	/**
	 * Singleton class for this Cat Tracker
	 *
	 * @since 1.0
	 * @return object $instance the singleton instance of this class
	 */
	public static function instance() {
		if ( isset( self::$instance ) )
			return self::$instance;

		self::$instance = new Cat_Tracker;
		self::$instance->includes();
		self::$instance->run_hooks();

		return self::$instance;
	}

	/**
	 * do nothing on construct
	 *
	 * @since 1.0
	 * @see instance()
	 */
	private function __construct() {}

	/**
	 * include included files
	 *
	 * @since 1.0
	 * @return void
	 */
	public function includes() {
		include_once( $this->path . 'classes/cat-tracker-sighting-submissions.php' );
		include_once( $this->path . 'classes/cat-tracker-utils.php' );
		include_once( $this->path . 'classes/cat-tracker-geocode.php' );
	}

	/**
	 * the meat & potatoes of this plugin
	 *
	 * @since 1.0
	 * @return void
	 */
	public function run_hooks() {
		add_action( 'init', array( $this, 'register_post_types_and_taxonomies' ) );
		add_action( 'admin_menu', array( $this, 'custom_fields' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue' ) );
		add_action( 'admin_print_styles', array( $this, 'admin_print_styles' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'frontend_enqueue' ) );
		add_action( 'wp_print_styles', array( $this, 'frontend_print_styles' ) );
		add_action( 'wp_head', array( $this, 'enqueue_ie_styles' ) );
		add_action( 'template_redirect', array( $this, 'maybe_process_submission' ) );
		add_action( 'save_post', array( $this, '_queue_flush_markers_cache_on_save' ), 1001 );
		add_action( 'save_post', array( $this, '_flush_map_dropdown_cache' ) );
		add_action( 'cat_tracker_flush_all_markers_cache', array( $this, '_flush_all_markers_cache' ) );
		add_filter( 'post_updated_messages', array( $this, 'post_updated_messages' ) );
		add_filter( 'the_content', array( $this, 'map_content' ) );
		add_filter( 'the_title', array( $this, 'submission_title' ), 10, 2 );
		add_action( Cat_Tracker::MARKER_TAXONOMY . '_edit_form_fields', array( $this, 'sighting_type_form_fields' ) );
		add_action( 'edited_' . Cat_Tracker::MARKER_TAXONOMY, array( $this, 'edited_sighting_type' ) );
		add_filter( 'cat_tracker_submission_form_dropdown_categories_args', array( $this, 'excluded_types' ) );
		add_filter( 'updated_taxonomy_meta', array( $this, 'updated_taxonomy_meta' ), 10, 4 );
		add_filter( 'wp_ajax_cat_tracker_fetch_address_using_coordinates', array( $this, 'ajax_fetch_address_using_coordinates' ) );
		add_filter( 'wp_ajax_nopriv_cat_tracker_fetch_address_using_coordinates', array( $this, 'ajax_fetch_address_using_coordinates' ) );
		add_filter( 'wp_ajax_cat_tracker_fetch_coordinates_using_address', array( $this, 'ajax_fetch_coordinates_using_address' ) );
		add_filter( 'wp_ajax_nopriv_cat_tracker_fetch_coordinates_using_address', array( $this, 'ajax_fetch_coordinates_using_address' ) );
	}

	/**
	 * setup instance variables
	 *
	 * @since 1.0
	 * @return void
	 */
	public function setup_vars() {
		$this->map_source = apply_filters( 'cat_tracker_map_source', 'http://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png' );
		$this->map_attribution = apply_filters( 'cat_tracker_map_attribution', __( 'Map data © OpenStreetMap contributors', 'cat-tracker' ) );
	}

	/**
	 * register post types & taxonomies
	 *
	 * @since 1.0
	 * @return void
	 */
	public function register_post_types_and_taxonomies() {

		$maps_labels = apply_filters( 'cat_tracker_map_post_type_labels', array(
			'name' => __( 'Maps', 'cat_tracker' ),
			'menu_name' => __( 'Maps', 'cat_tracker' ),
			'singular_name' => __( 'Map', 'cat_tracker' ),
			'all_items' => __( 'All Maps', 'cat_tracker' ),
			'add_new' => __( 'New Map', 'cat_tracker' ),
			'add_new_item' => __( 'Create New Map', 'cat_tracker' ),
			'edit' => __( 'Edit', 'cat_tracker' ),
			'edit_item' => __( 'Edit Map Details', 'cat_tracker' ),
			'new_item' => __( 'New Map', 'cat_tracker' ),
			'view' => __( 'View Public Map', 'cat_tracker' ),
			'view_item' => __( 'View Public Map', 'cat_tracker' ),
			'search_items' => __( 'Search Maps', 'cat_tracker' ),
			'not_found' => __( 'No maps found', 'cat_tracker' ),
			'not_found_in_trash' => __( 'No maps found in Trash', 'cat_tracker' ),
			'parent_item_colon' => __( 'Parent Map:', 'cat_tracker' )
		) );

		$maps_cpt_args = apply_filters( 'cat_tracker_map_post_type_args', array(
			'labels' => $maps_labels,
			'rewrite' => array( 'slug' => 'locations', 'with_front' => false ),
			'supports' => array( 'title', 'revisions' ),
			'description' => __( 'Cat Tracker Maps', 'cat_tracker' ),
			'has_archive' => true,
			'exclude_from_search' => true,
			'show_in_nav_menus' => true,
			'public' => true,
			'show_ui' => true,
			'can_export' => true,
			'hierarchical' => false,
			'query_var' => true,
			'menu_icon' => '',
		) );

		register_post_type( Cat_Tracker::MAP_POST_TYPE, $maps_cpt_args );

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
			'supports' => array( 'revisions' ),
			'description' => __( 'Cat Tracker Sightings', 'cat_tracker' ),
			'has_archive' => false,
			'exclude_from_search' => true,
			'show_in_nav_menus' => true,
			'public' => false,
			'show_ui' => true,
			'can_export' => true,
			'hierarchical' => false,
			'query_var' => false,
			'menu_icon' => '',
		) );

		register_post_type( Cat_Tracker::MARKER_POST_TYPE, $markers_cpt_args );

		$marker_taxonomy_labels = apply_filters( 'cat_tracker_marker_type_taxonomy_labels', array(
			'name' => __( 'Sighting Types', 'cat_tracker' ),
			'singular_name' => __( 'Sighting Type', 'cat_tracker' ),
			'search_items' => __( 'Search Sighting Types', 'cat_tracker' ),
			'all_items' => __( 'All Sighting Types', 'cat_tracker' ),
			'parent_item' => __( 'Parent Sighting Type', 'cat_tracker' ),
			'parent_item_colon' => __( 'Search Sighting:', 'cat_tracker' ),
			'edit_item' => __( 'Edit Sighting Type', 'cat_tracker' ),
			'update_item' => __( 'Update Sighting Type', 'cat_tracker' ),
			'add_new_item' => __( 'Add New Sighting Type', 'cat_tracker' ),
			'new_item_name' => __( 'New Sighting Type', 'cat_tracker' ),
			'separate_items_with_commas' => __( 'Separate Sighting Types with Commas', 'cat_tracker' ),
			'add_or_remove_items' => __( 'Add or remove sighting types', 'cat_tracker' ),
			'choose_from_most_used' => __( 'Choose from the most used sighting types', 'cat_tracker' ),
			'menu_name' => __( 'Types', 'cat_tracker' ),
		) );

		$marker_taxonomy_args = apply_filters( 'cat_tracker_marker_type_taxonomy_args', array(
			'labels' => $marker_taxonomy_labels,
			'hierarchical' => false,
			'query_var' => false,
			'public' => false,
			'show_ui' => true,
			'show_tagcloud' => false,
		) );

		register_taxonomy( Cat_Tracker::MARKER_TAXONOMY, Cat_Tracker::MARKER_POST_TYPE, $marker_taxonomy_args );

	}

	/**
	 * modify the message presented to the user after a cat tracker post type is saved/updated
	 *
	 * @since 1.0
	 * @param (array) $messages the unfiltered messages
	 * @return (array) $messages the filtered messages
	 */
	function post_updated_messages( $messages ) {
		global $post, $post_ID;

		if ( self::MAP_POST_TYPE == get_post_type( $post_ID ) ) {
			$map_url = apply_filters( 'cat_tracker_admin_map_url', esc_url( get_permalink( $post_ID ) ), $post_ID );

			$messages[self::MAP_POST_TYPE] = array(
				1 => sprintf( __( 'Map updated. <a href="%s">View Map</a>', 'cat_tracker' ), $map_url ),
				2 => sprintf( __( 'Map updated. <a href="%s">View Map</a>', 'cat_tracker' ), $map_url ),
				3 => sprintf( __( 'Map updated. <a href="%s">View Map</a>', 'cat_tracker' ), $map_url ),
				4 => sprintf( __( 'Map updated. <a href="%s">View Map</a>', 'cat_tracker' ), $map_url ),
				5 => isset( $_GET['revision'] ) ? sprintf( __( 'Map restored to revision from %s', 'cat_tracker '), wp_post_revision_title( (int) $_GET['revision'], false ) ) : false,
				6 => sprintf( __( 'Map published. <a href="%s">View Map</a>', 'cat_tracker' ), $map_url ),
				7 => __('Book saved.', 'cat_tracker'),
				6 => sprintf( __( 'Map published. <a href="%s">View Map</a>', 'cat_tracker' ), $map_url ),
				7 => __( 'Map saved.', 'cat_tracker' ),
				9 => sprintf( __( 'Map scheduled to appear on: <strong>%1$s</strong>. <a target="_blank" href="%2$s">View Map</a>', 'cat_tracker' ),
					// translators: Publish box date format, see http://php.net/date
					date_i18n( __( 'M j, Y @ G:i' ), strtotime( $post->post_date ) ), $map_url ),
				10 => sprintf( __( 'Map draft updated.', 'cat_tracker' ) ),
				11 => sprintf( __( "The new community site for <strong>%s</strong> has been created. Now, please create a map for this community.", 'cat_tracker' ), get_bloginfo( 'name' ) ),
			);

		}

		if ( self::MARKER_POST_TYPE == get_post_type( $post_ID ) ) {
			$map_url = apply_filters( 'cat_tracker_admin_map_url', esc_url( get_permalink( $this->get_map_id_for_marker( $post_ID ) ) ), $post_ID );

			$messages[self::MARKER_POST_TYPE] = array(
				1 => sprintf( __( 'Sighting updated. <a href="%s">View Map</a>', 'cat_tracker' ), $map_url ),
				2 => sprintf( __( 'Sighting updated. <a href="%s">View Map</a>', 'cat_tracker' ), $map_url ),
				3 => sprintf( __( 'Sighting updated. <a href="%s">View Map</a>', 'cat_tracker' ), $map_url ),
				4 => sprintf( __( 'Sighting updated. <a href="%s">View Map</a>', 'cat_tracker' ), $map_url ),
				5 => isset( $_GET['revision'] ) ? sprintf( __( 'Sighting restored to revision from %s', 'cat_tracker '), wp_post_revision_title( (int) $_GET['revision'], false ) ) : false,
				6 => sprintf( __( 'Sighting published. <a href="%s">View Map</a>', 'cat_tracker' ), $map_url ),
				7 => __('Book saved.', 'cat_tracker'),
				6 => sprintf( __( 'Sighting approved. <a href="%s">View Map</a>', 'cat_tracker' ), $map_url ),
				7 => __( 'Sighting saved.', 'cat_tracker' ),
				9 => sprintf( __( 'Sighting scheduled to appear in map on: <strong>%1$s</strong>. <a target="_blank" href="%2$s">View Map</a>', 'cat_tracker' ),
					// translators: Publish box date format, see http://php.net/date
					date_i18n( __( 'M j, Y @ G:i' ), strtotime( $post->post_date ) ), $map_url ),
				10 => sprintf( __( 'Sighting draft updated.', 'cat_tracker' ) ),
			);

		}

		return $messages;
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

		do_action( 'cat_tracker_pre_custom_fields' );

		x_add_metadata_group( 'map_geo_information', array( Cat_Tracker::MAP_POST_TYPE ), array( 'label' => 'Geographical Information' ) );
		x_add_metadata_field( Cat_Tracker::META_PREFIX . 'latitude', array(  Cat_Tracker::MAP_POST_TYPE ), array( 'field_type' => 'text', 'group' => 'map_geo_information', 'label' => 'Latitude' ) );
		x_add_metadata_field( Cat_Tracker::META_PREFIX . 'longitude', array(  Cat_Tracker::MAP_POST_TYPE ), array( 'field_type' => 'text', 'group' => 'map_geo_information', 'label' => 'Longitude' ) );
		x_add_metadata_field( Cat_Tracker::META_PREFIX . 'north_bounds', array(  Cat_Tracker::MAP_POST_TYPE ), array( 'field_type' => 'text', 'group' => 'map_geo_information', 'label' => 'North bounds' ) );
		x_add_metadata_field( Cat_Tracker::META_PREFIX . 'south_bounds', array(  Cat_Tracker::MAP_POST_TYPE ), array( 'field_type' => 'text', 'group' => 'map_geo_information', 'label' => 'South bounds' ) );
		x_add_metadata_field( Cat_Tracker::META_PREFIX . 'west_bounds', array( Cat_Tracker::MAP_POST_TYPE ), array( 'field_type' => 'text', 'group' => 'map_geo_information', 'label' => 'West bounds' ) );
		x_add_metadata_field( Cat_Tracker::META_PREFIX . 'east_bounds', array( Cat_Tracker::MAP_POST_TYPE ), array( 'field_type' => 'text', 'group' => 'map_geo_information', 'label' => 'East bounds' ) );
		x_add_metadata_field( Cat_Tracker::META_PREFIX . 'zoom_level', array( Cat_Tracker::MAP_POST_TYPE ), array( 'field_type' => 'text', 'group' => 'map_geo_information', 'label' => 'Default Zoom Level' ) );
		x_add_metadata_field( Cat_Tracker::META_PREFIX . 'max_zoom_level', array( Cat_Tracker::MAP_POST_TYPE ), array( 'field_type' => 'text', 'group' => 'map_geo_information', 'label' => 'Max Zoom Level' ) );
		x_add_metadata_field( Cat_Tracker::META_PREFIX . 'rural_map', array( Cat_Tracker::MAP_POST_TYPE ), array( 'field_type' => 'checkbox', 'group' => 'map_geo_information', 'label' => 'This is a rural map' ) );
		x_add_metadata_field( Cat_Tracker::META_PREFIX . 'disallow_submissions', array( Cat_Tracker::MAP_POST_TYPE ), array( 'field_type' => 'checkbox', 'group' => 'map_geo_information', 'label' => 'Disallow community submissions for this map' ) );

		$imported_read_only = (bool) ( ! empty( $_REQUEST['post'] ) ) ? get_post_meta( $_REQUEST['post'], Cat_Tracker::META_PREFIX . 'imported_on', true ) : false;

		x_add_metadata_group( 'marker_information', array( Cat_Tracker::MARKER_POST_TYPE ), array( 'label' => 'Sighting Information', 'priority' => 'high' ) );
		x_add_metadata_field( Cat_Tracker::META_PREFIX . 'description', array( Cat_Tracker::MARKER_POST_TYPE ), array( 'field_type' => 'textarea', 'group' => 'marker_information', 'label' => 'Description of the situation', 'readonly' => $imported_read_only ) );
		x_add_metadata_field( Cat_Tracker::MARKER_TAXONOMY, array( Cat_Tracker::MARKER_POST_TYPE ), array( 'field_type' => 'taxonomy_select', 'taxonomy' => Cat_Tracker::MARKER_TAXONOMY, 'group' => 'marker_information', 'label' => 'Sighting Type', 'readonly' => $imported_read_only ) );
		x_add_metadata_field( Cat_Tracker::META_PREFIX . 'sighting_date', array( Cat_Tracker::MARKER_POST_TYPE ), array( 'field_type' => 'datepicker', 'group' => 'marker_information', 'label' => 'Date of sighting', 'readonly' => $imported_read_only ) );
		x_add_metadata_field( Cat_Tracker::META_PREFIX . 'cat_neuter_status', array( Cat_Tracker::MARKER_POST_TYPE ), array( 'field_type' => 'select', 'values' => $this->get_possible_neuter_status(), 'group' => 'marker_information', 'label' => 'Current or believed neuter status', 'readonly' => $imported_read_only ) );
		x_add_metadata_field( Cat_Tracker::META_PREFIX . 'num_of_cats', array( Cat_Tracker::MARKER_POST_TYPE ), array( 'field_type' => 'number', 'group' => 'marker_information', 'label' => 'Number of cats', 'min' => 1, 'max' => 500, 'default_value' => 1, 'readonly' => $imported_read_only ) );
		x_add_metadata_field( Cat_Tracker::META_PREFIX . 'name_of_reporter', array( Cat_Tracker::MARKER_POST_TYPE ), array( 'field_type' => 'text', 'group' => 'marker_information', 'label' => 'Name of Reporter', 'readonly' => $imported_read_only ) );
		x_add_metadata_field( Cat_Tracker::META_PREFIX . 'email_of_reporter', array( Cat_Tracker::MARKER_POST_TYPE ), array( 'field_type' => 'text', 'group' => 'marker_information', 'label' => 'Email address of Reporter', 'readonly' => $imported_read_only ) );
		x_add_metadata_field( Cat_Tracker::META_PREFIX . 'telephone_of_reporter', array( Cat_Tracker::MARKER_POST_TYPE ), array( 'field_type' => 'text', 'group' => 'marker_information', 'label' => 'Phone number of Reporter', 'readonly' => $imported_read_only ) );
		x_add_metadata_field( Cat_Tracker::META_PREFIX . 'contact_reporter', array( Cat_Tracker::MARKER_POST_TYPE ), array( 'field_type' => 'checkbox', 'group' => 'marker_information', 'label' => 'Reporter would like to be contacted regarding spay/neuter programs', 'readonly' => true ) );
		x_add_metadata_field( Cat_Tracker::META_PREFIX . 'ip_address_of_reporter', array( Cat_Tracker::MARKER_POST_TYPE ), array( 'field_type' => 'text', 'group' => 'marker_information', 'label' => 'IP Address of Reporter', 'readonly' => true ) );
		x_add_metadata_field( Cat_Tracker::META_PREFIX . 'browser_info_of_reporter', array( Cat_Tracker::MARKER_POST_TYPE ), array( 'field_type' => 'text', 'group' => 'marker_information', 'label' => 'Browser Info for Reporter', 'readonly' => true ) );

		x_add_metadata_group( 'marker_geo_information', array( Cat_Tracker::MARKER_POST_TYPE ), array( 'label' => 'Geographical Information', 'priority' => 'high' ) );
		x_add_metadata_field( Cat_Tracker::META_PREFIX . 'sighting_map', array( Cat_Tracker::MARKER_POST_TYPE ), array( 'field_type' => 'text', 'group' => 'marker_geo_information', 'label' => 'Map', 'display_callback' => 'cat_tracker_sighting_map' ) );
		x_add_metadata_field( Cat_Tracker::META_PREFIX . 'address', array( Cat_Tracker::MARKER_POST_TYPE ), array( 'field_type' => 'text', 'group' => 'marker_geo_information', 'label' => 'Address' ) );
		x_add_metadata_field( Cat_Tracker::META_PREFIX . 'confidence_level', array( Cat_Tracker::MARKER_POST_TYPE ), array( 'field_type' => 'text', 'group' => 'marker_geo_information', 'label' => 'Confidence Level', 'readonly' => true ) );

		x_add_metadata_field( Cat_Tracker::META_PREFIX . 'latitude', array( Cat_Tracker::MARKER_POST_TYPE ), array( 'field_type' => 'text', 'group' => 'marker_geo_information', 'label' => 'Latitude', ) );
		x_add_metadata_field( Cat_Tracker::META_PREFIX . 'longitude', array( Cat_Tracker::MARKER_POST_TYPE ), array( 'field_type' => 'text', 'group' => 'marker_geo_information', 'label' => 'Longitude' ) );

		if ( apply_filters( 'cat_tracker_show_map_to_display_sighting_on_admin_field', true ) )
			x_add_metadata_field( Cat_Tracker::META_PREFIX . 'map', array( Cat_Tracker::MARKER_POST_TYPE ), array( 'field_type' => 'select', 'group' => 'marker_geo_information', 'label' => 'Map to display this sighting on', 'values' => $this->get_map_dropdown() ) );

		// remove meta boxes
		remove_meta_box( 'slugdiv', Cat_Tracker::MARKER_POST_TYPE, 'normal' );
		remove_meta_box( 'tagsdiv-cat_tracker_marker_type', Cat_Tracker::MARKER_POST_TYPE, 'side' );

		// move revisions to the side
		remove_meta_box( 'revisionsdiv', Cat_Tracker::MARKER_POST_TYPE, 'side' );
		add_meta_box( 'revisionsdiv', __( 'Revisions' ), 'post_revisions_meta_box', Cat_Tracker::MARKER_POST_TYPE, 'side', 'low' );
		remove_meta_box( 'revisionsdiv', Cat_Tracker::MAP_POST_TYPE, 'side' );
		add_meta_box( 'revisionsdiv', __( 'Revisions' ), 'post_revisions_meta_box', Cat_Tracker::MAP_POST_TYPE, 'side', 'low' );

		// replace the publish metabox
		remove_meta_box( 'submitdiv', Cat_Tracker::MARKER_POST_TYPE, 'side' );
		add_meta_box( 'submitdiv', __( 'Sighting Status' ), array( $this, 'marker_publish_meta_box' ), Cat_Tracker::MARKER_POST_TYPE, 'side', 'high' );

		do_action( 'cat_tracker_did_custom_fields' );

	}

	public function admin_enqueue() {
		wp_enqueue_style( 'cat-tracker-admin-css', plugins_url( 'resources/cat-tracker-admin.css', __FILE__ ), array(), self::VERSION );
		wp_enqueue_script( 'select2-js', plugins_url( 'resources/select2.js', __FILE__ ), array(), self::SELECT2_VERSION, true );
		wp_enqueue_script( 'cat-tracker-admin-js', plugins_url( 'resources/cat-tracker-admin.js', __FILE__ ), array( 'select2-js' ), self::VERSION, true );

		global $post, $current_screen;

		// color picker on sighting type editing screen
		if ( 'edit-' . self::MARKER_TAXONOMY == $current_screen->id ) {
			wp_enqueue_script( 'wp-color-picker' );
			wp_enqueue_style( 'wp-color-picker' );
		}

		if ( apply_filters( 'cat_tracker_admin_should_enqueue_map_scripts', ( 'post' != $current_screen->base || self::MARKER_POST_TYPE != $current_screen->id || empty( $post ) || self::MARKER_POST_TYPE != get_post_type( $post->ID ) ) ) )
			return;

		$this->setup_vars(); // setup map source + attribution
		$map_id = ( ! empty( $post ) ) ? $this->get_map_id_for_marker( $post->ID ) : null;
		$map_id = apply_filters( 'cat_tracker_admin_map_id', $map_id );

		if ( empty( $map_id ) )
			return;

		$markers = ( ! empty( $post ) ) ? $this->get_marker_for_preview( $post->ID ) : array();
		$markers = apply_filters( 'cat_tracker_admin_map_markers', $markers, $map_id );

		wp_enqueue_style( 'leaflet-css', plugins_url( 'resources/leaflet.css', __FILE__ ), array(), self::LEAFLET_VERSION );
		wp_enqueue_script( 'leaflet-js', plugins_url( 'resources/leaflet.js', __FILE__ ), array(), self::LEAFLET_VERSION );
		wp_enqueue_script( 'leaflet-zoomfs-js', plugins_url( 'resources/leaflet-zoomfs.js', __FILE__ ), array( 'leaflet-js' ), self::LEAFLET_VERSION, true );
		wp_enqueue_script( 'cat-tracker-js', plugins_url( 'resources/cat-tracker.js', __FILE__ ), array( 'jquery', 'underscore' ), self::VERSION, true );

		wp_localize_script( 'cat-tracker-js', 'cat_tracker_vars', array(
			'ajax_url' => esc_url( admin_url( 'admin-ajax.php' ) ),
			'map_source' => $this->map_source,
			'map_attribution' => $this->map_attribution,
			'is_admin_submission_mode' => apply_filters( 'cat_tracker_is_admin_submission_mode', true ),
			'submission_map_selector' => '.cat-tracker-map-preview',
			'submission_latitude_selector' => '#cat_tracker_latitude',
			'submission_longitude_selector' => '#cat_tracker_longitude',
			'submission_address_selector' => '#cat_tracker_address',
			'submission_confidence_level_selector' => '#cat_tracker_confidence_level',
			'publish_button_selector' => '#publish, #save-post',
			'publish_button_disabled_title' => __( 'Confirm that you are done relocating the marker before saving', 'cat-tracker' ),
			'relocate_text' => __( 'Use mouse pointer to relocate sighting', 'cat-tracker' ),
			'relocate_done_text' => __( "Ok, I'm done relocating the marker", 'cat-tracker' ),
			'fetching_address_text' => __( "Looking up address... shouldn't be more than a few seconds.", 'cat-tracker' ),
			'default_address' => __( 'n/a', 'cat-tracker' ),
			'address_nonce' => wp_create_nonce( 'cat_tracker_fetch_address' ),
			'do_sorting' => true,
			'sortable_attributes' => $this->get_sortable_attributes(),
			'maps' => array(
				'map-' . $map_id => array(
					'map_id' => 'map-' . $map_id,
					'map_latitude' => $this->get_map_latitude( $map_id ),
					'map_longitude' => $this->get_map_longitude( $map_id ),
					'map_north_bounds' => $this->get_map_north_bounds( $map_id ),
					'map_south_bounds' => $this->get_map_south_bounds( $map_id ),
					'map_west_bounds' => $this->get_map_west_bounds( $map_id ),
					'map_east_bounds' => $this->get_map_east_bounds( $map_id ),
					'map_zoom_level' => $this->get_map_zoom_level( $map_id ),
					'ignore_boundaries' => apply_filters( 'cat_tracker_admin_map_ignore_boundaries', true ),
					'markers' => json_encode( $markers ),
				),
			),
		) );

		do_action( 'cat_tracker_admin_enqueue_scripts', $map_id );

	}

	public function admin_print_styles() {
		global $post, $current_screen;

		if ( apply_filters( 'cat_tracker_admin_should_enqueue_map_scripts', ( 'post' != $current_screen->base || self::MARKER_POST_TYPE != $current_screen->id || empty( $post ) || self::MARKER_POST_TYPE != get_post_type( $post->ID ) ) ) )
			return;

		$map_id = ( ! empty( $post ) ) ? $this->get_map_id_for_marker( $post->ID ) : null;
		$map_id = apply_filters( 'cat_tracker_admin_map_id', $map_id );

		if ( empty( $map_id ) )
			return;

		$marker_types = $this->get_marker_types( $map_id );
		if ( empty( $marker_types ) )
			return;

		echo '<style>' . "\n";
		foreach ( $marker_types as $marker_type ) {
			echo '.cat-tracker-map-icon.' . sanitize_html_class( 'icon-' . $marker_type->slug ) . '::before {' . "\n";
			echo "\t" . 'color: ' . get_term_meta( $marker_type->term_id, 'color', true ) . ';'  . "\n";
			echo '}' . "\n";
		}
		echo '</style>' . "\n";
	}

	public function frontend_enqueue() {

		if ( ! Cat_Tracker::is_showing_map() )
			return;

		wp_enqueue_style( 'leaflet-css', plugins_url( 'resources/leaflet.css', __FILE__ ), array(), self::LEAFLET_VERSION );
		wp_enqueue_style( 'cat-tracker', plugins_url( 'resources/cat-tracker.css', __FILE__ ), array(), self::VERSION );
		wp_enqueue_style( 'leaflet-marker-cluster-css', plugins_url( 'resources/leaflet-markercluster.css', __FILE__ ), array( 'leaflet-css' ), self::LEAFLET_VERSION );
		wp_enqueue_script( 'leaflet-js', plugins_url( 'resources/leaflet.js', __FILE__ ), array(), self::LEAFLET_VERSION, true );
		wp_enqueue_script( 'leaflet-marker-cluster-js', plugins_url( 'resources/leaflet-markercluster.js', __FILE__ ), array( 'leaflet-js' ), self::LEAFLET_VERSION, true );
		wp_enqueue_script( 'leaflet-zoomfs-js', plugins_url( 'resources/leaflet-zoomfs.js', __FILE__ ), array( 'leaflet-js' ), self::LEAFLET_VERSION, true );
		wp_enqueue_script( 'cat-tracker-js', plugins_url( 'resources/cat-tracker.js', __FILE__ ), array( 'jquery', 'underscore' ), self::VERSION, true );

		$this->setup_vars(); // setup map source + attribution
		$map_id = apply_filters( 'cat_tracker_map_content_map_id', get_the_ID() );
		$markers = $this->get_markers( $map_id );

		wp_localize_script( 'cat-tracker-js', 'cat_tracker_vars', array(
			'ajax_url' => esc_url( admin_url( 'admin-ajax.php' ) ),
			'map_source' => $this->map_source,
			'map_attribution' => $this->map_attribution,
			'is_submission_mode' => Cat_Tracker::is_submission_mode(),
			'new_submission_popup_text' => __( 'Your sighting', 'cat-tracker' ),
			'submission_map_selector' => '.cat-tracker-submission-map',
			'submission_latitude_selector' => '#cat-tracker-submission-latitude',
			'submission_longitude_selector' => '#cat-tracker-submission-longitude',
			'submission_address_selector' => '#cat-tracker-submission-address',
			'submission_confidence_level_selector' => '#cat-tracker-submission-confidence-level',
			'publish_button_selector' => '#cat-tracker-submission-submit',
			'address_nonce' => wp_create_nonce( 'cat_tracker_fetch_address' ),
			'fetching_address_text' => __( "Looking up address... shouldn't be more than a few seconds.", 'cat-tracker' ),
			'default_address' => __( 'Not a valid address.', 'cat-tracker' ),
			'do_sorting' => false,
			'maps' => array(
				'map-' . $map_id => array(
					'map_id' => 'map-' . $map_id,
					'map_latitude' => $this->get_map_latitude( $map_id ),
					'map_longitude' => $this->get_map_longitude( $map_id ),
					'map_north_bounds' => $this->get_map_north_bounds( $map_id ),
					'map_south_bounds' => $this->get_map_south_bounds( $map_id ),
					'map_west_bounds' => $this->get_map_west_bounds( $map_id ),
					'map_east_bounds' => $this->get_map_east_bounds( $map_id ),
					'map_zoom_level' => $this->get_map_zoom_level( $map_id ),
					'map_max_zoom_level' => $this->get_map_max_zoom_level( $map_id ),
					'markers' => json_encode( $markers ),
				),
			),
		) );

	}

	public function frontend_print_styles() {

		if ( ! Cat_Tracker::is_showing_map() )
			return;

		$map_id = apply_filters( 'cat_tracker_map_content_map_id', get_the_ID() );

		$marker_types = $this->get_marker_types( $map_id );
		if ( empty( $marker_types ) )
			return;

		echo '<style>' . "\n";
		foreach ( $marker_types as $marker_type ) {
			echo '.cat-tracker-map-icon.' . sanitize_html_class( 'icon-' . $marker_type->slug ) . '::before {' . "\n";
			echo "\t" . 'color: ' . get_term_meta( $marker_type->term_id, 'color', true ) . ';'  . "\n";
			echo '}' . "\n";
		}
		echo '</style>' . "\n";
	}


	public function enqueue_ie_styles() {
		?>
		<!--[if lte IE 8]>
		<link rel="stylesheet" href="<?php echo esc_url( add_query_arg( array( 'ver' => self::LEAFLET_VERSION ), plugins_url( 'resources/leaflet-ie.css', __FILE__ ) ) ); ?>" />
		<link rel="stylesheet" href="<?php echo esc_url( add_query_arg( array( 'ver' => self::LEAFLET_VERSION ), plugins_url( 'resources/leaflet-marker-cluster-ie.css', __FILE__ ) ) ); ?>" />
		<![endif]-->
	<?php
	}

	public function map_content( $content ) {
		if ( Cat_Tracker::is_submission_mode() ) {
			$content = '';
			if ( ! empty( $this->sighting_submission ) && is_object( $this->sighting_submission ) && method_exists( $this->sighting_submission, 'get_errors' ) ) {
				$content .= $this->sighting_submission->get_errors();
			}
			$content .= $this->submission_form();
		} elseif ( Cat_Tracker::is_showing_map() ) {
			$map_id = apply_filters( 'cat_tracker_map_content_map_id', get_the_ID() );
			$submission_link = apply_filters( 'cat_tracker_map_submission_link', add_query_arg( array( 'submission' => 'new' ), get_permalink( get_the_ID() ) ) );

			if ( ! Cat_Tracker::is_community_submissions_disabled_for_map_id( $map_id ) ) {
				$content .= '<a class="cat-tracker-report-new-sighting-button" href="' . esc_url( $submission_link ) . '">' . __( 'Report a new community cat sighting', 'cat-tracker' ) . '</a>';
			}

			$content .= '<div class="cat-tracker-map" id="' . esc_attr( 'map-' . $map_id ) . '"></div>';

			if ( ! Cat_Tracker::has_community_and_intake() )
				return $content;

			$content .= '<div class="leaflet-control-layers leaflet-control leaflet-control-layers-expanded" id="cat-tracker-custom-controls"><div class="leaflet-control-layers-overlays">';
			$content .= '<span>' . __( 'Select types of sightings:', 'cat-tracker' ) . '</span>';
			$content .= '<form>';
				$content .= '<label><input data-marker-type="community" class="cat-tracker-layer-control cat-tracker-layer-control-marker-type" type="checkbox" checked="checked">Community Cats</label>';
				$content .= '<label><input data-marker-type="intake" class="cat-tracker-layer-control cat-tracker-layer-control-marker-type" type="checkbox" checked="checked">SPCA Intake Cats</label>';
			$content .= '</div></form></div>';
		}

		return $content;
	}

	public static function has_community_and_intake() {
		return get_option( 'has_community_and_intake' );
	}

	public static function is_showing_map() {
		return apply_filters( 'cat_tracker_is_showing_map', is_singular( Cat_Tracker::MAP_POST_TYPE ) );
	}

	public static function is_submission_mode() {
		if ( ! is_front_page() && ! is_singular() )
			return false;

		$map_id = apply_filters( 'cat_tracker_map_content_map_id', get_the_ID() );
		return ( ! is_admin() && isset( $_GET['submission'] ) && 'new' == $_GET['submission'] && Cat_Tracker::is_showing_map() && ! Cat_Tracker::is_community_submissions_disabled_for_map_id( $map_id ) );
	}

	public static function is_community_submissions_disabled_for_map_id( $map_id ) {
		return (bool) get_post_meta( $map_id, Cat_Tracker::META_PREFIX . 'disallow_submissions', true );
	}

	public static function is_rural_map( $map_id ) {
		return (bool) get_post_meta( $map_id, Cat_Tracker::META_PREFIX . 'rural_map', true );
	}

	public function maybe_process_submission() {
		if ( ! Cat_Tracker::is_submission_mode() || ! isset( $_POST['cat-tracker-submission-submit'] ) )
			return;

		$this->sighting_submission = new Cat_Tracker_Sighting_Submission();
		$this->sighting_submission->process();
	}

	public function submission_title( $title, $post_id ) {

		if ( Cat_Tracker::is_submission_mode() && in_the_loop() )
			$title = sprintf( _x( 'Have you spotted a community cat? Are you feeding a community cat? Do you have a colony of fixed cats nearby? Report a new sighting for %s.', 'the title of the map', 'cat-tracker' ), $title );

		return $title;
	}

	public function submission_form() {
		$map_id = apply_filters( 'cat_tracker_map_content_map_id', get_the_ID() );
		$submission_form = '<form id="cat-tracker-new-submission" method="post">';
		$submission_form .= '<fieldset><label for="cat-tracker-submitter-name">' . __( 'First name*:', 'cat-tracker' ) . '<input type="text" id="cat-tracker-submitter-name" name="cat-tracker-submitter-name"></label></fieldset>';
		$submission_form .= '<fieldset><label for="cat-tracker-submitter-phone">' . __( 'Your phone*:', 'cat-tracker' ) . '<input type="phone" id="cat-tracker-submitter-phone" name="cat-tracker-submitter-phone"></label></fieldset>';
		$submission_form .= '<fieldset><label for="cat-tracker-submitter-email">' . __( 'Your email address (optional):', 'cat-tracker' ) . '<input type="email" id="cat-tracker-submitter-email" name="cat-tracker-submitter-email"></label></fieldset>';
		$submission_form .= '<fieldset><label for="cat-tracker-submission-date">' . __( 'Date of sighting (optional):', 'cat-tracker' ) . '<input type="date" id="cat-tracker-submission-date" name="cat-tracker-submission-date"></label></fieldset>';
		$submission_form .= '<fieldset><label for="cat-tracker-submission-num-of-cats">' . __( 'Number of cats:', 'cat-tracker' ) . '<input type="number" id="cat-tracker-submission-num-of-cats" name="cat-tracker-submission-num-of-cats" min="1" max="100"></label></fieldset>';
		$submission_form .= '<fieldset><label for="cat-tracker-submission-type">' . __( 'Type of sighting:', 'cat-tracker' );
		$submission_form .= wp_dropdown_categories( apply_filters( 'cat_tracker_submission_form_dropdown_categories_args', array( 'name' => 'cat-tracker-submission-type', 'hide_empty' => false, 'id' => 'cat-tracker-submission-type', 'taxonomy' => Cat_Tracker::MARKER_TAXONOMY, 'echo' => false ) ) );
		$submission_form .= '</label></fieldset>';
		$submission_form .= '<fieldset><label for="cat-tracker-submission-description">' . __( 'Please describe the situation*:', 'cat-tracker' ) . '<textarea id="cat-tracker-submission-description" name="cat-tracker-submission-description"></textarea> <br> <span class="cat-tracker-submission-extra-description">' . __( 'Describe the cats environment. Are they being fed? Are they fixed? What do they look like?', 'cat-tracker' ) . '</label></fieldset>';
		$submission_form .= '<fieldset><label for="cat-tracker-neuter-status">' . __( 'Do you know if this cat is/these cats are spayed/neutered?', 'cat-tracker' );
		$submission_form .= '<select id="cat-tracker-neuter-status" name="cat-tracker-neuter-status">';
		$submission_form .= '<option value="unknown">' . __( 'I am not sure', 'cat-tracker' )  . '</option>';
		$submission_form .= '<option value="yes">' . __( 'Yes, it is/they are spayed/neutered', 'cat-tracker' )  . '</option>';
		$submission_form .= '<option value="no">' . __( 'No, it is/they are not spayed/neutered', 'cat-tracker' )  . '</option>';
		$submission_form .= '</select>';
		$submission_form .= '</label></fieldset>';
		$submission_form .= '<p class="cat-tracker-mandatory-fields">' . __( "Fields marked with '*' are mandatory." ) . '</p>';
		$submission_form .= '<p>' . __( 'Please provide the location of the sighting using the map below or by entering the address manually. You can zoom in using the controls on the left-hand side, or by double clicking on the map. Click on the map once to define the location of the sighting. You can then re-click the map or click and drag the marker to re-set the location of the sighting. When you adjust the marker, the address should auto-populate. When you adjust the address, the marker will adjust its location.', 'cat-tracker' ) . '</p>';
		$submission_form .= '<fieldset><label for="cat-tracker-submission-address">' . __( 'Sighting address:', 'cat-tracker' ) . '<input type="text" id="cat-tracker-submission-address" name="cat-tracker-submission-address"></label></fieldset>';
		$submission_form .= '<div class="cat-tracker-submission-map" id="' . esc_attr( 'map-' . $map_id ) . '"></div>';
		$submission_form .= wp_nonce_field( 'cat_tracker_confirm_submission', 'cat_tracker_confirm_submission', true, false );
		$submission_form .= '<input type="hidden" name="cat-tracker-submission-latitude" id="cat-tracker-submission-latitude">';
		$submission_form .= '<input type="hidden" name="cat-tracker-submission-longitude" id="cat-tracker-submission-longitude">';
		$submission_form .= '<input type="hidden" name="cat-tracker-submission-confidence-level" id="cat-tracker-submission-confidence-level">';
		$submission_form .= '<fieldset><label for="cat-tracker-contact-reporter"><input type="checkbox" id="cat-tracker-contact-reporter" name="cat-tracker-contact-reporter"> ' . __( 'I would like to be contacted about getting this cat or these cats spayed/neutered', 'cat-tracker' ) . '</label></fieldset>';
		$submission_form .= '<input type="submit" name="cat-tracker-submission-submit" id="cat-tracker-submission-submit" value="Submit">';
		$submission_form .= '</form>';
		return $submission_form;
	}

	/**
	 * generate the cache key used for caching the map marker cache keys
	 *
	 * @since 1.0
	 * @param (int) $map_id the map ID
	 * @param (string) $context the context in which to display the map
	 * @return (string) the cache key
	 */
	public function _get_map_cache_keys_cache_key( $map_id, $context ) {
		$context = $this->_validate_markers_context( $context );
		return sanitize_key( Cat_Tracker::MARKERS_CACHE_KEY_PREFIX . 'mid_' . absint( $map_id ) . '_' . $context . '_keys' );
	}

	/**
	 * generate the cache key used for caching the map marker query with offset
	 *
	 * @since 1.0
	 * @param (int) $map_id the map ID
	 * @param (string) $context the context in which to display the map
	 * @param (int) $offset the query offset
	 * @return (string) the cache key
	 */
	public function _get_map_cache_key_for_offset( $map_id, $context, $offset ) {
		$context = $this->_validate_markers_context( $context );
		return sanitize_key( Cat_Tracker::MARKERS_CACHE_KEY_PREFIX . 'mid_' . absint( $map_id ) . '_' . $context . '_offset_' . absint( $offset ) );
	}

	/**
	 * validate a map marker context against the allowed marker contexts
	 *
	 * @since 1.0
	 * @param (int) $map_id the map ID
	 * @param (string) $context the context in which to display the map
	 * @return (string) the cache key
	 */
	public function _validate_markers_context( $context ) {
		if ( ! in_array( $context, $this->_get_valid_marker_contexts() ) ) {
			_doing_it_wrong( __FUNCTION__, 'Cat Tracker invalid marker context called. Context: ' . $context . ' Backtrace: ' . wp_debug_backtrace_summary(), Cat_Tracker::VERSION );
			$context = Cat_Tracker::DEFAULT_MARKER_CONTEXT;
		}
		return (string) $context;
	}

	/**
	 * get valid marker contexts
	 *
	 * @since 1.0
	 * @return (array) valid marker contexts
	 */
	public function _get_valid_marker_contexts() {
		return (array) apply_filters( 'cat_tracker_valid_marker_contexts', array( Cat_Tracker::DEFAULT_MARKER_CONTEXT ) );
	}


	/**
	 * get markers for map ID
	 * gets the cached values if possible
	 *
	 * @since 1.0
	 * @uses _build_markers_array
	 * @param (int) $map_id the map ID
	 * @param (string) $context the context under which the map is being shown
	 * @return (array) $markers the markers for the map
	 */
	public function get_markers( $map_id = null, $context = 'front_end' ) {
		$map_id = ( empty( $map_id ) ) ? get_the_ID() : $map_id;

		if ( $this->_validate_markers_context( $context ) )
			$this->current_context = $context;

		if ( Cat_Tracker::MAP_POST_TYPE != get_post_type( $map_id ) )
			return array();

		$marker_cache_keys = get_transient( $this->_get_map_cache_keys_cache_key( $map_id, $context ) );

		if ( empty( $marker_cache_keys ) )
			return $this->_build_markers_array( $map_id, $context );

		$markers = array();
		foreach( $marker_cache_keys as $cache_key ) {
			$_markers = get_transient( $cache_key );
			if ( empty( $_markers ) )
				return $this->_build_markers_array( $map_id, $context );

			$markers = array_merge_recursive( $markers, $_markers );
		}

		return $markers;
	}

	/**
	 * generate the markers for map ID
	 * and store the results in bucketed transients
	 *
	 * @since 1.0
	 * @param (int) $map_id the map ID
	 * @return (array) $markers the markers for the map
	 */
	public function _build_markers_array( $map_id, $context = 'front_end' ) {

		if ( $this->_validate_markers_context( $context ) )
			$this->current_context = $context;

		$max_num_pages = $offset = 1;
		$markers = $cache_keys = $marker_types = array();
		while ( $offset <= $max_num_pages ) {
			$_markers = $this->_get_markers_query_with_offset( $map_id, $offset, $context );
			if ( empty( $_markers ) )
				return $markers;

			$max_num_pages = $_markers['max_num_pages'];
			$cache_key = $this->_get_map_cache_key_for_offset( $map_id, $context, $offset );
			$cache_keys[] = $cache_key;
			set_transient( $cache_key, $_markers['markers'] );
			$markers = array_merge_recursive( $markers, $_markers['markers'] );
			$offset++;
		}

		set_transient( $this->_get_map_cache_keys_cache_key( $map_id, $context ), $cache_keys );

		return $markers;
	}

	/**
	 * run query to get markers for specified map ID with paging
	 *
	 * @since 1.0
	 * @param (int) $map_id the map ID
	 * @param (int) $paged the offset/page
	 * @return (array) markers, found posts and max offset for this query
	 */
	public function _get_markers_query_with_offset( $map_id, $paged = 1, $context = 'front_end' ) {
		$markers = array();
		$_markers = new WP_Query();
		$_markers->query( array(
			'post_type' => Cat_Tracker::MARKER_POST_TYPE,
			'posts_per_page' => Cat_Tracker::MARKERS_LIMIT_PER_QUERY,
			'fields' => 'ids',
			'paged' => absint( $paged ),
			'meta_query' => array(
				array(
					'key' => Cat_Tracker::META_PREFIX . 'map',
					'value' => $map_id,
					'type' => 'NUMERIC',
				),
			),
		) );

		if ( empty( $_markers->posts ) )
			return $markers;

		foreach( $_markers->posts as $marker_id ) {
			$latitude = $this->get_marker_latitude( $marker_id );
			$longitude = $this->get_marker_longitude( $marker_id );
			if ( ! Cat_Tracker_Utils::validate_latitude( $latitude ) || ! Cat_Tracker_Utils::validate_longitude( $longitude ) )
				continue;

			$marker_type = $this->get_marker_type_slug( $marker_id );

			// front-end should only display 2 types - community vs. intake
			if ( 'front_end' == $context ) {

				$has_intake = $has_community = false;

				if ( in_array( $marker_type, $this->_marker_intake_types ) ) {
					$marker_type = 'intake';
					if ( ! isset( $markers['intake'] ) ) {
						$markers['intake'] = array(
							'title' => 'SPCA Intake Cats',
							'slug' => 'intake',
							'sightings' => array(),
						);
					}
					$has_intake = true;
				} else {
					$marker_type = 'community';
					if ( ! isset( $markers['community'] ) ) {
						$markers['community'] = array(
							'title' => 'Community Cats',
							'slug' => 'community',
							'sightings' => array(),
						);
					}
					$has_community = true;
				}

				// keep track if this site has both community and intake
				update_option( 'has_community_and_intake', ( $has_intake && $has_community ) );
			} else {
				if ( ! isset( $markers[$marker_type] ) ) {
					$markers[$marker_type] = array(
						'title' => $this->get_marker_type( $marker_id ),
						'slug' => $marker_type,
						'sightings' => array(),
					);
				}
			}

			$number_of_markers_to_insert = ( is_numeric( $this->get_marker_num_of_cats( $marker_id ) ) && $this->get_marker_num_of_cats( $marker_id ) > 1 ) ? $this->get_marker_num_of_cats( $marker_id ) : 1;
			$markers_to_insert = range( 1, $number_of_markers_to_insert );

			foreach( $markers_to_insert as $marker_to_insert ) {
				$markers[$marker_type]['sightings'][] = array(
					'id' => $marker_id,
					'title' => $this->get_marker_text( $marker_id ),
					'latitude' => $this->get_marker_latitude( $marker_id ),
					'longitude' => $this->get_marker_longitude( $marker_id ),
					'text' => $this->get_marker_text( $marker_id ),
					'sortable_attributes' => array(
						'neuter_status' => $this->get_marker_neuter_status( $marker_id ),
						'year' => $this->get_marker_year( $marker_id ),
					),
				);
			}
		}

		return array( 'markers' => $markers, 'found_posts' => $_markers->found_posts, 'max_num_pages' => $_markers->max_num_pages );
	}

	/**
	 * whenever a marker or a map is saved
	 * queue a job to flush all marker cache on this site
	 *
	 * @since 1.0
	 * @param (int) $post_id the post ID that was just saved
	 * @return void
	 */
	public function _queue_flush_markers_cache_on_save( $post_id ) {

		// don't flush if importing
		if ( defined( 'CAT_TRACKER_IS_IMPORTING' ) && CAT_TRACKER_IS_IMPORTING )
			return;

		$post_type = get_post_type( $post_id );
		if ( wp_is_post_revision( $post_id ) || ( ! in_array( $post_type, array( Cat_Tracker::MAP_POST_TYPE, Cat_Tracker::MARKER_POST_TYPE ) ) ) )
			return;

		// though not strictly required, passing the time paramater ensures the event is unique enough to run again if it's called shortly after this event has occurred already
		wp_schedule_single_event( time(), 'cat_tracker_flush_all_markers_cache', array( 'time' => time() ) );
	}

	/**
	 * helper function to queue a job to flush marker cache on demand
	 *
	 * @since 1.1
	 * @return void
	 */
	public function queue_flush_marker_cache() {

		// though not strictly required, passing the time paramater ensures the event is unique enough to run again if it's called shortly after this event has occurred already
		wp_schedule_single_event( time(), 'cat_tracker_flush_all_markers_cache', array( 'time' => time() ) );
	}

	/**
	 * completely flush marker cache
	 * deletes old transients and generates new ones
	 *
	 * @since 1.0
	 * @param $map_id
	 * @return void
	 */
	public function _flush_all_markers_cache() {
		$this->_delete_old_marker_transients();
		$maps = get_posts( array( 'post_type' => Cat_Tracker::MAP_POST_TYPE, 'fields' => 'ids', 'posts_per_page' => -1 ) );
		foreach ( $maps as $map ) {
			foreach ( $this->_get_valid_marker_contexts() as $context )
				$this->_build_markers_array( $map, $context );
		}
	}

	/**
	 * delete old marker transients
	 * avoids cache and DB overpopulation
	 *
	 * @since 1.0
	 * @return void
	 */
	public function _delete_old_marker_transients() {
		global $wpdb;

		$transient_prefix = '_transient_';

		$transients_keys_to_delete = array(
			'cat_tracker_marker_v1_',
			Cat_Tracker::MARKERS_CACHE_KEY_PREFIX,
		);

		foreach ( $transients_keys_to_delete as $transient_key ) {
			$transient_lookup = $transient_prefix . $transient_key . '%';
			$transients = $wpdb->get_results( $wpdb->prepare( "SELECT option_name AS name FROM $wpdb->options WHERE option_name LIKE %s", $transient_lookup ) );
			foreach ( $transients as $transient ) {
				if ( false === strpos( $transient->name, '_transient_timeout_' ) )
					delete_transient( str_replace( $transient_prefix, '', $transient->name ) );
			}
		}
	}

	/**
	 * get active marker types for map ID
	 *
	 * @since 1.0
	 * @param (int) $map_id the Map ID to use
	 * @return array the marker types
	 */
	public function get_marker_types( $map_id ) {
		return get_terms( self::MARKER_TAXONOMY );
	}

	public function get_marker_for_preview( $marker_id ) {

		$markers = array();
		$latitude = $this->get_marker_latitude( $marker_id );
		$longitude = $this->get_marker_longitude( $marker_id );
		if ( ! Cat_Tracker_Utils::validate_latitude( $latitude ) || ! Cat_Tracker_Utils::validate_longitude( $longitude ) )
			return;

		$markers['preview'] = array(
			'title' => $this->get_marker_type( $marker_id ),
			'slug' => 'preview',
			'sightings' => array(),
		);

		$text = __( 'This is where this sighting will be shown on the map', 'cat-tracker' );

		$markers['preview']['sightings'][] = array(
			'id' => $marker_id,
			'title' => $text,
			'latitude' => $this->get_marker_latitude( $marker_id ),
			'longitude' => $this->get_marker_longitude( $marker_id ),
			'text' => $text,
		);

		return $markers;

	}

	public function _meta_helper( $meta_key, $post_type, $post_id = null, $singular = true, $default_value = null ) {
		$post_id = ( empty( $post_id ) ) ? get_the_ID() : $post_id;
		if ( $post_type != get_post_type( $post_id ) )
			return false;

		if ( false === strpos( $meta_key, Cat_Tracker::META_PREFIX ) )
			$meta_key = Cat_Tracker::META_PREFIX . $meta_key;

		$value = get_post_meta( $post_id, $meta_key, (bool) $singular );
		if ( false == $value && ! empty( $default_value ) )
			$value = $default_value;
		return $value;
	}

	public function map_meta_helper( $meta_key, $map_id = null, $singular = true, $default_value = null ) {
		return $this->_meta_helper( $meta_key, self::MAP_POST_TYPE, $map_id, $singular, $default_value );
	}

	public function marker_meta_helper( $meta_key, $marker_id = null, $singular = true, $default_value = null ) {
		return $this->_meta_helper( $meta_key, self::MARKER_POST_TYPE, $marker_id, $singular, $default_value );
	}

	public function get_map_latitude( $map_id = null ) {
		return floatval( $this->map_meta_helper( 'latitude', $map_id ) );
	}

	public function get_map_longitude( $map_id = null ) {
		return floatval( $this->map_meta_helper( 'longitude', $map_id ) );
	}

	public function get_map_north_bounds( $map_id = null ) {
		return floatval( $this->map_meta_helper( 'north_bounds', $map_id ) );
	}

	public function get_map_south_bounds( $map_id = null ) {
		return floatval( $this->map_meta_helper( 'south_bounds', $map_id ) );
	}

	public function get_map_west_bounds( $map_id = null ) {
		return floatval( $this->map_meta_helper( 'west_bounds', $map_id ) );
	}

	public function get_map_east_bounds( $map_id = null ) {
		return floatval( $this->map_meta_helper( 'east_bounds', $map_id ) );
	}

	public function get_map_zoom_level( $map_id = null ) {
		return absint( $this->map_meta_helper( 'zoom_level', $map_id ) );
	}

	public function get_map_max_zoom_level( $map_id = null ) {
		return absint( $this->map_meta_helper( 'max_zoom_level', $map_id ) );
	}

	public function get_map_id_for_marker( $marker_id = null ) {
		return absint( apply_filters( 'cat_tracker_get_map_id_for_marker', $this->marker_meta_helper( 'map', $marker_id ) ) );
	}

	public function get_marker_description( $marker_id = null ) {
		return esc_html( $this->marker_meta_helper( 'description', $marker_id ) );
	}

	public function _get_marker_type_helper( $marker_id, $fields ) {
		$_types = wp_get_object_terms( $marker_id, Cat_Tracker::MARKER_TAXONOMY, array( 'fields' => $fields ) );

		$type = ( ! empty( $_types[0] ) ) ? $_types[0] : null;

		if ( 'ids' == $fields ) {
			$type = ( empty( $type ) ) ? false : $type;
			return $type;
		} else {
			$type = ( empty( $type ) ) ? 'n/a' : $type;
			return esc_html( $type );
		}
	}

	public function get_marker_type( $marker_id ) {
		return $this->_get_marker_type_helper( $marker_id, 'names' );
	}

	public function get_marker_type_slug( $marker_id ) {
		return $this->_get_marker_type_helper( $marker_id, 'slugs' );
	}

	public function get_marker_type_id( $marker_id ) {
		return $this->_get_marker_type_helper( $marker_id, 'ids' );
	}

	public function get_marker_text( $marker_id ) {
		$marker_text = __( 'Submission type:', 'cat-tracker' ) . ' ' . $this->get_marker_type( $marker_id ) . "<br>\n" . ' ' . __( 'Description:', 'cat-tracker' ) . ' ' . $this->get_marker_description( $marker_id );
		return apply_filters( 'cat_tracker_marker_text', $marker_text, $marker_id );
	}

	public function get_marker_latitude( $marker_id = null ) {
		return floatval( $this->marker_meta_helper( 'latitude', $marker_id ) );
	}

	public function get_marker_longitude( $marker_id = null ) {
		return floatval( $this->marker_meta_helper( 'longitude', $marker_id ) );
	}

	public function get_marker_address( $marker_id = null, $singular = true, $default = 'n/a' ) {
		return strip_tags( $this->marker_meta_helper( 'address', $marker_id, $singular, $default ) );
	}

	public function get_marker_animal_id( $marker_id = null, $singular = true, $default = 'n/a' ) {
		return absint( $this->marker_meta_helper( 'animal_id', $marker_id, $singular, $default ) );
	}

	public function get_marker_number_of_cats( $marker_id = null, $singular = true, $default = 1 ) {
		return absint( $this->marker_meta_helper( 'number_of_cats', $marker_id, $singular, $default ) );
	}

	public function get_marker_neuter_status( $marker_id = null, $singular = true, $default = 'unknown' ) {
		return strip_tags( $this->marker_meta_helper( 'cat_neuter_status', $marker_id, $singular, $default ) );
	}

	public function get_marker_year( $marker_id = null, $singular = true, $default = null ) {
		$_date = $this->marker_meta_helper( 'sighting_date', $marker_id, $singular, $default );
		return ( ! empty( $_date ) ) ? date( 'Y', intval( $_date ) ) : date( 'Y' );
	}

	public function get_marker_date( $marker_id = null, $singular = true, $default = null ) {
		$_date = $this->marker_meta_helper( 'sighting_date', $marker_id, $singular, $default );
		return ( ! empty( $_date ) ) ? date( 'Y-m-d', intval( $_date ) ) : 'n/a';
	}

	public function get_marker_name_of_reporter( $marker_id = null, $singular = true, $default = 'unknown' ) {
		return strip_tags( $this->marker_meta_helper( 'name_of_reporter', $marker_id, $singular, $default ) );
	}

	public function get_marker_email_of_reporter( $marker_id = null, $singular = true, $default = null ) {
		$email = $this->marker_meta_helper( 'email_of_reporter', $marker_id, $singular, $default );
		return ( empty( $email ) || ! is_email( $email ) ) ? 'unknown' : $email;
	}

	public function get_marker_telephone_of_reporter( $marker_id = null, $singular = true, $default = 'unknown' ) {
		return strip_tags( $this->marker_meta_helper( 'telephone_of_reporter', $marker_id, $singular, $default ) );
	}

	public function get_marker_contact_reporter( $marker_id = null, $singular = true, $default = null ) {
		$contact_reporter = $this->marker_meta_helper( 'contact_reporter', $marker_id, $singular, $default );
		return ( ! empty( $contact_reporter ) ) ? 'yes' : 'no';
	}

	public function get_marker_intake_type( $marker_id = null, $singular = true, $default = 'unknown' ) {
		return strip_tags( $this->marker_meta_helper( 'intake_type', $marker_id, $singular, $default ) );
	}

	public function get_marker_source( $marker_id = null, $singular = true, $default = 'unknown' ) {
		return strip_tags( $this->marker_meta_helper( 'source', $marker_id, $singular, $default ) );
	}

	public function get_marker_breed( $marker_id = null, $singular = true, $default = 'unknown' ) {
		return strip_tags( $this->marker_meta_helper( 'breed', $marker_id, $singular, $default ) );
	}

	public function get_marker_color( $marker_id = null, $singular = true, $default = 'unknown' ) {
		return strip_tags( $this->marker_meta_helper( 'color', $marker_id, $singular, $default ) );
	}

	public function get_marker_gender( $marker_id = null, $singular = true, $default = 'unknown' ) {
		return strip_tags( $this->marker_meta_helper( 'gender', $marker_id, $singular, $default ) );
	}

	public function get_marker_age_group( $marker_id = null, $singular = true, $default = 'unknown' ) {
		return strip_tags( $this->marker_meta_helper( 'age_group', $marker_id, $singular, $default ) );
	}

	public function get_marker_incoming_spay_neuter_status( $marker_id = null, $singular = true, $default = 'unknown' ) {
		return strip_tags( $this->marker_meta_helper( 'incoming_spay_neuter_status', $marker_id, $singular, $default ) );
	}

	public function get_marker_current_spay_neuter_status( $marker_id = null, $singular = true, $default = 'unknown' ) {
		return strip_tags( $this->marker_meta_helper( 'current_spay_neuter_status', $marker_id, $singular, $default ) );
	}

	public function get_marker_num_of_cats( $marker_id = null, $singular = true, $default = 1 ) {
		return absint( $this->marker_meta_helper( 'num_of_cats', $marker_id, $singular, $default ) );
	}

	public function get_possible_neuter_status() {
		return array( 'unknown' => __( 'Unknown', 'cat-tracker' ), 'yes' => __( 'Spayed/Neutered', 'cat-tracker' ), 'no' => __( 'Not Spayed/Neutered', 'cat-tracker' ) );
	}

	public function get_possible_years() {
		global $wpdb;
		$earliest_date = $wpdb->get_var( "SELECT meta_value FROM $wpdb->postmeta WHERE `meta_key` = 'cat_tracker_sighting_date' order by meta_value LIMIT 1" );
		$earliest_year = date( 'Y', intval( $earliest_date ) );
		$_years = range( $earliest_year, date( 'Y' ) );
		$years = array();
		foreach( $_years as $year ) {
			$years[$year] = $year;
		}
		return $years;
	}

	public function get_sortable_attributes() {
		return array(
			'neuter_status' => array( 'name' => 'spay/neuter status', 'values' => $this->get_possible_neuter_status(), 'display_any' => true, 'type' => 'radio' ),
			'year' => array( 'name' => 'year', 'values' => $this->get_possible_years(), 'display_any' => true, 'type' => 'radio' ),
		);
	}

	public function get_map_dropdown() {
		$dropdown = get_transient( 'cat_tracker_map_dropdown' );
		if ( empty( $dropdown ) )
			$dropdown = $this->_build_map_dropdown();
		return $dropdown;
	}

	public function get_marker( $marker_id = null ) {
		$marker_id = ( empty( $marker_id ) ) ? get_the_ID() : $marker_id;
		if ( self::MARKER_POST_TYPE != get_post_type( $marker_id ) )
			return false;

		return array(
			'id' => $marker_id,
			'title' => $this->get_marker_text( $marker_id ),
			'latitude' => $this->get_marker_latitude( $marker_id ),
			'longitude' => $this->get_marker_longitude( $marker_id ),
			'text' => $this->get_marker_text( $marker_id ),
		);

	}

	public function _build_map_dropdown() {
		$maps_dropdown = array();
		$maps = new WP_Query();
		$maps->query( array(
			'fields' => 'ids',
			'post_type' => Cat_Tracker::MAP_POST_TYPE,
			'posts_per_page' => 100,
		) );
		if ( empty( $maps->posts ) )
			return $maps_dropdown;
		foreach ( $maps->posts as $map_id )
			$maps_dropdown[$map_id] = esc_html( get_the_title( $map_id ) );
		set_transient( Cat_Tracker::MAP_DROPDOWN_TRANSIENT, $maps_dropdown );
		wp_reset_query();
		return $maps_dropdown;
	}

	public function _flush_map_dropdown_cache( $post_id ) {

		// don't flush if importing
		if ( defined( 'CAT_TRACKER_IS_IMPORTING' ) && CAT_TRACKER_IS_IMPORTING )
			return;


		if ( wp_is_post_revision( $post_id ) || Cat_Tracker::MAP_POST_TYPE != get_post_type( $post_id ) )
			return;

		$this->_build_map_dropdown();
	}

	public function sighting_map( $field_slug, $field, $object_type, $object_id, $value ) {
		if ( Cat_Tracker::MARKER_POST_TYPE != $object_type )
			return;

		echo '<div class="custom-metadata-field text">';
		echo '<label>' . __( 'Sighting Preview/Set-up', 'cat-tracker' ) . '</label>';

		$map = get_post( $this->get_map_id_for_marker( $object_id ) );
		if ( empty( $map ) || self::MAP_POST_TYPE != get_post_type( $map->ID ) ) {
			echo '<p>' . __( 'A map ID needs to be assigned to the sighting to get a preview', 'cat_tracker' ) . '</p>';
			return;
		}

		$latitude = $this->get_marker_latitude( $object_id );
		$longitude = $this->get_marker_longitude( $object_id );
		if ( ! empty( $latitude ) && ! empty( $longitude ) ) {
			echo '<p>' . __( 'The following is a preview of how this sighting will appear on the map, it does not include the description or colouring that the sighting will have on the internal or public map(s). To modify the location of the sighting, click on the relocate button, or modify the address or coordinates directly below. When you click on the relocate button, you will be able to click or drag the marker to place it in a new location. Once you\'re done moving it, just click done or hit the update button.' ) . '</p>';
			submit_button( __( 'Use mouse pointer to relocate sighting', 'cat-tracker' ), 'primary', 'cat-tracker-relocate', false );
			echo '<div class="clear"></div><br>';
		} else {
			echo '<p>' . __( 'The following is a blank map upon which you can place the sighting. To place the sighting, click on the locate button, or add an address or coordinates directly below. When you click on the locate button, you will be able to click or drag the marker to place it. Once you\'re done moving it, just click done or hit the publish button. Note that this preview does not include the description or colouring that the sighting will have on the internal or public map(s).' ) . '</p>';
			submit_button( __( 'Use mouse pointer to locate sighting', 'cat-tracker' ), 'primary', 'cat-tracker-relocate', false );
			echo '<div class="clear"></div><br>';
		}
		?>
		<div class="cat-tracker-map-preview" id="<?php echo esc_attr( 'map-' . $this->get_map_id_for_marker( get_the_ID() ) ); ?>"></div>
		</div>
	<?php
	}

	public function marker_publish_meta_box( $post ) {
		include_once( 'includes/marker-meta-box.php' );
	}

	/**
	 * display additional fields for sighting types
	 *
	 * @since 1.0
	 * @param (object) $term, the current term we are modifying
	 * @return void
	 */
	public function sighting_type_form_fields( $term ) {

		if ( ! function_exists( 'get_term_meta' ) )
			return;

		$color = get_term_meta( $term->term_id, 'color', true );
		$internal_type = get_term_meta( $term->term_id, 'internal_type', true );
		?>
		<tr class="form-field cat-tracker-term-fields">
			<th scope="row" valign="top"><label for="term_color"><?php _e( 'Assigned Color', 'cat-tracker' ) ?></label></th>
			<td>
				<input type="text" name="term_color" id="term_color" value="<?php echo esc_attr( $color ); ?>"><br />
			</td>
		</tr>

		<tr class="form-field cat-tracker-term-fields">
			<th scope="row" valign="top"><label for="internal_type"><?php _e( 'Internal Type?', 'cat-tracker' ) ?></label></th>
			<td>
				<input type="checkbox" name="internal_type" id="internal_type" <?php checked( $internal_type ); ?>><br />
				<span class="description"><?php _e( 'If checked, this type will not be available for users when they submit a new sighting', 'cat-tracker' ); ?></span>
			</td>
		</tr>

		<script type="text/javascript">
			jQuery(document).ready(function($) {
				$( '#term_color' ).wpColorPicker();
			});
		</script>
	<?php
	}

	/**
	 * save additional fields for sighting types
	 *
	 * @since 1.0
	 * @param (int) $term_id, the ID for the current term we are modifying
	 * @return void
	 */
	public function edited_sighting_type( $term_id ) {

		if ( ! function_exists( 'update_term_meta' ) )
			return;

		if ( isset( $_POST['term_color'] ) ) {
			$color = (string) $_POST['term_color'];
			if ( preg_match( '/^#[a-f0-9]{6}$/i', $color ) )
				update_term_meta( $term_id, 'color', $color );
		}
		if ( isset( $_POST['internal_type'] ) ) {
			$internal_type = (bool) $_POST['internal_type'];
			update_term_meta( $term_id, 'internal_type', $internal_type );
		}
	}

	/**
	 * exclude internal types
	 *
	 * @since 1.0
	 * @param (array) $args, the args passed to the dropdown field function
	 * @return (array) $args, the filtered args passed to the dropdown field function
	 */
	public function excluded_types( $args ) {
		if ( ! function_exists( 'get_term_meta' ) )
			return $args;

		$excluded_ids = wp_cache_get( 'excluded_sighting_types', 'cat_tracker' );
		if ( false === $excluded_ids )
			$excluded_ids = $this->determine_internal_type_ids();

		if ( ! empty( $excluded_ids ) )
			$args['exclude'] = $excluded_ids;

		return $args;
	}

	/**
	 * determine which sighting types are internal
	 *
	 * @since 1.0
	 * @return (array) $excluded_ids, the excluded sighting type ids
	 */
	public function determine_internal_type_ids() {
		$excluded_ids = array();

		$type_of_sightings = get_terms( self::MARKER_TAXONOMY, array( 'hide_empty' => false, 'fields' => 'ids' ) );

		if ( is_wp_error( $type_of_sightings ) )
			return false;

		if ( empty( $type_of_sightings ) )
			return $excluded_ids;

		foreach ( $type_of_sightings as $type_of_sighting_id ) {
			if ( get_term_meta( $type_of_sighting_id, 'internal_type', true ) )
				$excluded_ids[] = $type_of_sighting_id;
		}

		wp_cache_set( 'excluded_sighting_types', $excluded_ids, 'cat_tracker' );
		return $excluded_ids;
	}

	/**
	 * maybe refresh excluded type ids when taxonomy metadata is updated
	 * do so if the updated term is a valid sighting type object
	 *
	 * @since 1.0
	 * @return void
	 */
	public function updated_taxonomy_meta( $meta_id, $object_id, $meta_key, $_meta_value ) {
		$term = get_term( $object_id, self::MARKER_TAXONOMY );
		if ( empty( $term ) || is_wp_error( $term ) )
			return;

		$this->determine_internal_type_ids();
	}

	/**
	 * fetch the address using coordinates
	 * used to return an ajax response
	 *
	 * @since 1.0
	 * @return void
	 */
	public function ajax_fetch_address_using_coordinates() {
		header('Content-Type: application/json');

		if ( empty( $_REQUEST['nonce'] ) || ! wp_verify_nonce( $_REQUEST['nonce'], 'cat_tracker_fetch_address' ) ) {
			$error = new WP_Error( 'invalid-latitude', __( 'Address could not be fetched because the latitude is invalid.', 'cat-tracker' ) );
			die( json_encode( array( 'errors' => $error->get_error_message() ) ) );
		}

		if ( empty( $_REQUEST['latitude'] ) || ! Cat_Tracker_Utils::validate_latitude( $_REQUEST['latitude'] ) ) {
			$error = new WP_Error( 'invalid-latitude', __( 'Address could not be fetched because the latitude is invalid.', 'cat-tracker' ) );
			die( json_encode( array( 'errors' => $error->get_error_message() ) ) );
		}

		if ( empty( $_REQUEST['longitude'] ) || ! Cat_Tracker_Utils::validate_longitude( $_REQUEST['longitude'] ) ){
			$error = new WP_Error( 'invalid-longitude', __( 'Address could not be fetched because the longitude is invalid.', 'cat-tracker' ) );
			die( json_encode( array( 'errors' => $error->get_error_message() ) ) );
		}

		$coordinates = Cat_Tracker_Geocode::get_address_from_coordinates( $_REQUEST['latitude'], $_REQUEST['longitude'] );

		if ( is_wp_error( $coordinates ) )
			die( json_encode( array( 'errors' => $coordinates->get_error_message() ) ) );

		if ( ! empty( $coordinates['formatted_address'] ) )
			die( json_encode( array( 'coordinates' => $coordinates ) ) );

		$error = new WP_Error( 'no-address', __( 'Address could not be fetched, please try again.', 'cat-tracker' ) );
		die( json_encode( array( 'errors' => $error->get_error_message() ) ) );
	}

	/**
	 * fetch coordinates using an address
	 * used to return an ajax response
	 *
	 * @since 1.0
	 * @return void
	 */
	public function ajax_fetch_coordinates_using_address() {
		header('Content-Type: application/json');

		if ( empty( $_REQUEST['nonce'] ) || ! wp_verify_nonce( $_REQUEST['nonce'], 'cat_tracker_fetch_address' ) ) {
			$error = new WP_Error( 'invalid-latitude', __( 'Coordinates could not be fetched because the address is invalid.', 'cat-tracker' ) );
			die( json_encode( array( 'errors' => $error->get_error_message() ) ) );
		}

		$coordinates = Cat_Tracker_Geocode::get_location_by_address( $_REQUEST['address'] );

		if ( is_wp_error( $coordinates ) )
			die( json_encode( array( 'errors' => $coordinates->get_error_message() ) ) );

		if ( ! empty( $coordinates['latitude'] ) )
			die( json_encode( array( 'coordinates' => $coordinates ) ) );

		$error = new WP_Error( 'no-address', __( 'Coordinates could not be fetched, please try again.', 'cat-tracker' ) );
		die( json_encode( array( 'errors' => $error->get_error_message() ) ) );
	}


}

global $cat_tracker;
$cat_tracker = Cat_Tracker::instance();

function cat_tracker_sighting_map( $field_slug, $field, $object_type, $object_id, $value ) {
	global $cat_tracker;
	$cat_tracker->sighting_map( $field_slug, $field, $object_type, $object_id, $value );
}
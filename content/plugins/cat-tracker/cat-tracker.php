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
	 * current version # of this plugin
	 */
	const VERSION = 1.0;

	/**
	 * current Leaflet version incldued with this plugin
	 */
	const LEAFLET_VERSION = '0.4.4';

	/**
	 * current select2 version incldued with this plugin
	 */
	const SELECT2_VERSION = '3.2';

	/**
	 * cat tracker map post type
	 */
	const MAP_POST_TYPE = 'cat_tracker_map';

	/**
	 * cat tracker marker post type
	 */
	const MARKER_POST_TYPE = 'cat_tracker_marker';

	/**
	 * cat tracker sighting taxonomy
	 */
	const MARKER_TAXONOMY = 'cat_tracker_marker_type';

	/**
	 * cat tracker metadata prefix
	 */
	const META_PREFIX = 'cat_tracker_';

	/**
	 * cat tracker map drodpdown transient/cache key
	 */
	const MAP_DROPDOWN_TRANSIENT = 'cat_tracker_map_admin_dropdown_1';

	/**
	 * cat tracker limit the number of markers per map for performance reasons
	 */
	const MARKERS_LIMIT_PER_MAP = 1000;

	/**
	 * @var the one true Cat Tracker
	 */
	private static $instance;

	/**
	 * @var path to this plugin
	 */
	public $path;


	/**
	 * @var current sighting submission, if there is one
	 */
	public $sighting_submission;

	/**
	 * Singleton class for this Cat Tracker
	 *
	 * @since 1.0
	 * @return object $instance the singleton instance of this class
	 */
	public static function instance() {
		if ( isset( self::$instance ) )
			return;
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
	public function __construct() {}

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
		add_action( 'wp_enqueue_scripts', array( $this, 'frontend_enqueue' ) );
		add_action( 'wp_head', array( $this, 'enqueue_ie_styles' ) );
		add_action( 'template_redirect', array( $this, 'maybe_process_submission' ) );
		add_action( 'save_post', array( $this, '_flush_map_dropdown_cache' ) );
		add_filter( 'post_updated_messages', array( $this, 'post_updated_messages' ) );
		add_filter( 'the_content', array( $this, 'map_content' ) );
		add_filter( 'the_title', array( $this, 'submission_title' ), 10, 2 );
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
	  	$map_url = esc_url( get_permalink( $post_ID ) );

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
	  	$map_url = esc_url( get_permalink( $this->get_map_id_for_marker( $post_ID ) ) );

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

		x_add_metadata_group( 'marker_information', array( Cat_Tracker::MARKER_POST_TYPE ), array( 'label' => 'Sighting Information', 'priority' => 'high' ) );
		x_add_metadata_field( Cat_Tracker::META_PREFIX . 'description', array( Cat_Tracker::MARKER_POST_TYPE ), array( 'field_type' => 'textarea', 'group' => 'marker_information', 'label' => 'Description of the situation' ) );
		x_add_metadata_field( Cat_Tracker::MARKER_TAXONOMY, array( Cat_Tracker::MARKER_POST_TYPE ), array( 'field_type' => 'taxonomy_select', 'taxonomy' => Cat_Tracker::MARKER_TAXONOMY, 'group' => 'marker_information', 'label' => 'Sighting Type' ) );
		x_add_metadata_field( Cat_Tracker::META_PREFIX . 'sighting_date', array( Cat_Tracker::MARKER_POST_TYPE ), array( 'field_type' => 'datepicker', 'group' => 'marker_information', 'label' => 'Date of sighting' ) );
		x_add_metadata_field( Cat_Tracker::META_PREFIX . 'name_of_reporter', array( Cat_Tracker::MARKER_POST_TYPE ), array( 'field_type' => 'text', 'group' => 'marker_information', 'label' => 'Name of Reporter' ) );
		x_add_metadata_field( Cat_Tracker::META_PREFIX . 'email_of_reporter', array( Cat_Tracker::MARKER_POST_TYPE ), array( 'field_type' => 'text', 'group' => 'marker_information', 'label' => 'Email address of Reporter' ) );
		x_add_metadata_field( Cat_Tracker::META_PREFIX . 'telephone_of_reporter', array( Cat_Tracker::MARKER_POST_TYPE ), array( 'field_type' => 'text', 'group' => 'marker_information', 'label' => 'Phone number of Reporter' ) );
		x_add_metadata_field( Cat_Tracker::META_PREFIX . 'ip_address_of_reporter', array( Cat_Tracker::MARKER_POST_TYPE ), array( 'field_type' => 'text', 'group' => 'marker_information', 'label' => 'IP Address of Reporter', 'readonly' => true ) );
		x_add_metadata_field( Cat_Tracker::META_PREFIX . 'browser_info_of_reporter', array( Cat_Tracker::MARKER_POST_TYPE ), array( 'field_type' => 'text', 'group' => 'marker_information', 'label' => 'Browser Info for Reporter', 'readonly' => true ) );

		x_add_metadata_group( 'marker_geo_information', array( Cat_Tracker::MARKER_POST_TYPE ), array( 'label' => 'Geographical Information', 'priority' => 'high' ) );
		x_add_metadata_field( Cat_Tracker::META_PREFIX . 'sighting_map', array( Cat_Tracker::MARKER_POST_TYPE ), array( 'field_type' => 'text', 'group' => 'marker_geo_information', 'label' => 'Map', 'display_callback' => 'cat_tracker_sighting_map' ) );
		x_add_metadata_field( Cat_Tracker::META_PREFIX . 'address', array( Cat_Tracker::MARKER_POST_TYPE ), array( 'field_type' => 'text', 'group' => 'marker_geo_information', 'label' => 'Address' ) );
		x_add_metadata_field( Cat_Tracker::META_PREFIX . 'confidence_level', array( Cat_Tracker::MARKER_POST_TYPE ), array( 'field_type' => 'text', 'group' => 'marker_geo_information', 'label' => 'Confidence Level', 'readonly' => true ) );
		// x_add_metadata_field( Cat_Tracker::META_PREFIX . 'cross_street', array( Cat_Tracker::MARKER_POST_TYPE ), array( 'field_type' => 'text', 'group' => 'marker_geo_information', 'label' => 'Cross Street' ) );
		// x_add_metadata_field( Cat_Tracker::META_PREFIX . 'postal_code', array( Cat_Tracker::MARKER_POST_TYPE ), array( 'field_type' => 'text', 'group' => 'marker_geo_information', 'label' => 'Postal Code' ) );
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
		if ( 'post' != $current_screen->base || self::MARKER_POST_TYPE != $current_screen->id || empty( $post ) || self::MARKER_POST_TYPE != get_post_type( $post->ID ) )
			return;

		wp_enqueue_style( 'leaflet-css', plugins_url( 'resources/leaflet.css', __FILE__ ), array(), self::LEAFLET_VERSION );
		wp_enqueue_script( 'leaflet-js', plugins_url( 'resources/leaflet.js', __FILE__ ), array(), self::LEAFLET_VERSION );
		wp_enqueue_script( 'leaflet-zoomfs-js', plugins_url( 'resources/leaflet-zoomfs.js', __FILE__ ), array( 'leaflet-js' ), self::LEAFLET_VERSION, true );
		wp_enqueue_script( 'cat-tracker-js', plugins_url( 'resources/cat-tracker.js', __FILE__ ), array( 'jquery', 'underscore' ), self::VERSION, true );

		$this->setup_vars(); // setup map source + attribution
		$map_id = $this->get_map_id_for_marker( $post->ID );

		wp_localize_script( 'cat-tracker-js', 'cat_tracker_vars', array(
			'ajax_url' => esc_url( admin_url( 'admin-ajax.php' ) ),
			'map_source' => $this->map_source,
			'map_attribution' => $this->map_attribution,
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
					'markers' => ( ! Cat_Tracker::is_submission_mode() ) ? json_encode( array( $this->get_marker( $post->ID ) ) ) : array(),
				),
			),
		) );

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

		wp_localize_script( 'cat-tracker-js', 'cat_tracker_vars', array(
			'ajax_url' => esc_url( admin_url( 'admin-ajax.php' ) ),
			'map_source' => $this->map_source,
			'map_attribution' => $this->map_attribution,
			'is_submission_mode' => Cat_Tracker::is_submission_mode(),
			'new_submission_popup_text' => __( 'Your sighting', 'cat-tracker' ),
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
					'markers' => json_encode( $this->get_markers( $map_id ) ),
				),
			),
		) );
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
			$content = '<a class="cat-tracker-report-new-sighting-button" href="' . esc_url( $submission_link ) . '">' . __( 'Report a new community cat sighting', 'cat-tracker' ) . '</a>';
			$content .= '<div class="cat-tracker-map" id="' . esc_attr( 'map-' . $map_id ) . '"></div>';

			$marker_types = $this->get_markers( $map_id );
			if ( empty( $marker_types ) )
				return $content;

			$content .= '<div class="leaflet-control-layers leaflet-control leaflet-control-layers-expanded" id="cat-tracker-custom-controls"><div class="leaflet-control-layers-overlays">';
			$content .= '<span>' . __( 'Select types of sightings:', 'cat-tracker' ) . '</span>';
			$content .= '<form>';
			foreach ( $marker_types as $marker_type )
				$content .= '<label><input data-marker-type="' . esc_attr( $marker_type['slug'] ) . '" class="cat-tracker-layer-control" type="checkbox" checked="checked">' . esc_html( $marker_type['title'] ) . '</label>';
			$content .= '</div></form></div>';
			return $content;
		}

		return $content;
	}

	public static function is_showing_map() {
		return apply_filters( 'cat_tracker_is_showing_map', is_singular( Cat_Tracker::MAP_POST_TYPE ) );
	}

	public static function is_submission_mode() {
		return ( ! is_admin() && isset( $_GET['submission'] ) && 'new' == $_GET['submission'] && Cat_Tracker::is_showing_map() );
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
		$submission_form .= '<fieldset><label for="cat-tracker-submitter-phone">' . __( 'Your phone (optional):', 'cat-tracker' ) . '<input type="phone" id="cat-tracker-submitter-phone" name="cat-tracker-submitter-phone"></label></fieldset>';
		$submission_form .= '<fieldset><label for="cat-tracker-submitter-email">' . __( 'Your email address (optional):', 'cat-tracker' ) . '<input type="email" id="cat-tracker-submitter-email" name="cat-tracker-submitter-email"></label></fieldset>';
		$submission_form .= '<fieldset><label for="cat-tracker-submission-date">' . __( 'Date of sighting (optional):', 'cat-tracker' ) . '<input type="date" id="cat-tracker-submission-date" name="cat-tracker-submission-date"></label></fieldset>';
		$submission_form .= '<fieldset><label for="cat-tracker-submission-description">' . __( 'Please describe the situation*:', 'cat-tracker' ) . '<textarea id="cat-tracker-submission-description" name="cat-tracker-submission-description"></textarea> <br> <span class="cat-tracker-submission-extra-description">' . __( 'How many cats are there? Are they being fed? Are they fixed? What do they look like?', 'cat-tracker' ) . '</label></fieldset>'; // todo: adjust the description
		$submission_form .= '<fieldset><label for="cat-tracker-submission-type">' . __( 'Type of sighting:', 'cat-tracker' );
		$submission_form .= wp_dropdown_categories( apply_filters( 'cat_tracker_submission_form_dropdown_categories_args', array( 'name' => 'cat-tracker-submission-type', 'hide_empty' => false, 'id' => 'cat-tracker-submission-type', 'taxonomy' => Cat_Tracker::MARKER_TAXONOMY, 'echo' => false ) ) );
		$submission_form .= '</label></fieldset>';
		$submission_form .= '<p class="cat-tracker-mandatory-fields">' . __( "Fields marked with '*' are mandatory." ) . '</p>';
		$submission_form .= '<p>' . __( 'Please provide the location of the sighting using the map below. You can zoom in using the controls on the left-hand side, or by double clicking on the map. Click on the map once to define the location of the sighting. You can then re-click the map or click and drag the marker to re-set the location of the sighting.', 'cat-tracker' ) . '</p>';
		$submission_form .= '<div class="cat-tracker-submission-map" id="' . esc_attr( 'map-' . $map_id ) . '"></div>';
		$submission_form .= wp_nonce_field( 'cat_tracker_confirm_submission', 'cat_tracker_confirm_submission', true, false );
		$submission_form .= '<input type="hidden" name="cat-tracker-submission-latitude" id="cat-tracker-submission-latitude">';
		$submission_form .= '<input type="hidden" name="cat-tracker-submission-longitude" id="cat-tracker-submission-longitude">';
		$submission_form .= '<input type="submit" name="cat-tracker-submission-submit" id="cat-tracker-submission-submit" value="Submit">';
		$submission_form .= '</form>';
		return $submission_form;
	}

	/**
	 * @todo: add caching
	 */
	public function get_markers( $map_id = null ) {
		$map_id = ( empty( $map_id ) ) ? get_the_ID() : $map_id;

		if ( Cat_Tracker::MAP_POST_TYPE != get_post_type( $map_id ) )
			return false;

		$markers = array();

		$_markers = new WP_Query();
		$_markers->query( array(
			'post_type' => Cat_Tracker::MARKER_POST_TYPE,
			'posts_per_page' => Cat_Tracker::MARKERS_LIMIT_PER_MAP,
			'fields' => 'ids',
			'meta_query' => array(
				array(
					'key' => Cat_Tracker::META_PREFIX . 'map',
					'value' => $map_id,
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

			$array_slug = $this->get_marker_type_slug($marker_id);

			if ( ! isset( $markers[$array_slug] ) ) {
				$markers[$array_slug] = array(
					'title' => $this->get_marker_type( $marker_id ),
					'slug' => $array_slug,
					'sightings' => array(),
				);
			}


			$markers[$array_slug]['sightings'][] = array(
				'id' => $marker_id,
				'title' => $this->get_marker_text( $marker_id ),
				'latitude' => $this->get_marker_latitude( $marker_id ),
				'longitude' => $this->get_marker_longitude( $marker_id ),
				'text' => $this->get_marker_text( $marker_id ),
			);
		}

		return $markers;

	}

	public function _meta_helper( $meta_key, $post_type, $post_id = null, $singular = true ) {
		$post_id = ( empty( $post_id ) ) ? get_the_ID() : $post_id;
		if ( $post_type != get_post_type( $post_id ) )
			return false;

		if ( false === strpos( $meta_key, Cat_Tracker::META_PREFIX ) )
			$meta_key = Cat_Tracker::META_PREFIX . $meta_key;

		return get_post_meta( $post_id, $meta_key, (bool) $singular );
	}

	public function map_meta_helper( $meta_key, $map_id = null, $singular = true ) {
		return $this->_meta_helper( $meta_key, self::MAP_POST_TYPE, $map_id, $singular );
	}

	public function marker_meta_helper( $meta_key, $marker_id = null, $singular = true ) {
		return $this->_meta_helper( $meta_key, self::MARKER_POST_TYPE, $marker_id, $singular );
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
		return absint( $this->marker_meta_helper( 'map', $marker_id ) );
	}

	public function get_marker_description( $marker_id = null ) {
		return esc_html( $this->marker_meta_helper( 'description', $marker_id ) );
	}

	public function _get_marker_type_helper( $marker_id, $fields ) {
		$_types = wp_get_object_terms( $marker_id, Cat_Tracker::MARKER_TAXONOMY, array( 'fields' => $fields ) );
		$type = ( ! empty( $_types ) ) ? $_types[0] : 'n/a';
		return esc_html( $type );
	}

	public function get_marker_type( $marker_id ) {
		return $this->_get_marker_type_helper( $marker_id, 'names' );
	}

	public function get_marker_type_slug( $marker_id ) {
		return $this->_get_marker_type_helper( $marker_id, 'slugs' );
	}

	public function get_marker_text( $marker_id ) {
		return __( 'Submission type:', 'cat-tracker' ) . ' ' . $this->get_marker_type( $marker_id ) . "<br>\n" . ' ' . __( 'Description:', 'cat-tracker' ) . ' ' . $this->get_marker_description( $marker_id );
	}

	public function get_marker_latitude( $marker_id = null ) {
		return floatval( $this->marker_meta_helper( 'latitude', $marker_id ) );
	}

	public function get_marker_longitude( $marker_id = null ) {
		return floatval( $this->marker_meta_helper( 'longitude', $marker_id ) );
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
		if ( wp_is_post_revision( $post_id ) || Cat_Tracker::MAP_POST_TYPE != get_post_type( $post_id ) )
			return;

		$this->_build_map_dropdown();
	}

	public function sighting_map( $field_slug, $field, $object_type, $object_id, $value ) {
		if ( Cat_Tracker::MARKER_POST_TYPE != $object_type )
			return;

		echo '<div class="custom-metadata-field text">';
			echo '<label>Sighting Preview</label>';

			$map = get_post( $this->get_map_id_for_marker( $object_id ) );
			if ( empty( $map ) || self::MAP_POST_TYPE != get_post_type( $map->ID ) ) {
				echo '<p>' . __( 'Please select a map below and save the sighting to get a preview', 'cat_tracker' ) . '</p>';
				return;
			}

			$latitude = $this->get_marker_latitude( $object_id );
			$longitude = $this->get_marker_longitude( $object_id );
			if ( empty( $latitude ) || empty( $longitude ) ) {
				echo '<p>' . __( 'Please set a latitude and longitude below and save the sighting to get a preview', 'cat_tracker' ) . '</p>';
				return;
			}


			?>
			<div class="cat-tracker-map-preview" id="<?php echo esc_attr( 'map-' . $this->get_map_id_for_marker( get_the_ID() ) ); ?>"></div>
		</div>
		<?php
	}

	public function marker_publish_meta_box( $post ) {
		include_once( 'includes/marker-meta-box.php' );
	}


}

global $cat_tracker;
$cat_tracker = Cat_Tracker::instance();

function cat_tracker_sighting_map( $field_slug, $field, $object_type, $object_id, $value ) {
	global $cat_tracker;
	$cat_tracker->sighting_map( $field_slug, $field, $object_type, $object_id, $value );
}
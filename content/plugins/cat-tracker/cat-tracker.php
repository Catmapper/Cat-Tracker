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
	 * @var the one true Cat Tracker
	 */
	private static $instance;

	/**
	 * @var path to this plugin
	 */
	public $path;

	/**
	 * @var theme path for theme files
	 */
	public $theme_path;

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
			self::$instance->setup_vars();
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
		add_action( 'wp_enqueue_scripts', array( $this, 'frontend_enqueue' ) );
		add_action( 'wp_head', array( $this, 'enqueue_ie_styles' ) );
		add_filter( 'the_content', array( $this, 'map_content' ) );
		add_filter( 'wp_footer', array( $this, 'map_footer' ), 100 );
	}

	/**
	 * setup instance variables
	 *
	 * @since 1.0
	 * @return void
	 */
	public function setup_vars() {
		$this->path = trailingslashit( dirname( __FILE__ ) );
		$this->theme_path = $this->path . trailingslashit( 'theme-compat' );
		$this->map_source = apply_filters( 'cat_tracker_map_attribution', 'http://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png' );
		$this->map_attribution = apply_filters( 'cat_tracker_map_attribution', __( 'Map data © OpenStreetMap contributors', 'cat-tracker' ) );
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

		do_action( 'cat_tracker_custom_fields' );

		x_add_metadata_group( 'geo_information', array( 'map', 'marker' ), array( 'label' => 'Geographical Information' ) );
		x_add_metadata_field( 'latitude', array( 'map', 'marker' ), array( 'field_type' => 'text', 'group' => 'geo_information', 'label' => 'Latitude' ) );
		x_add_metadata_field( 'longitude', array( 'map', 'marker' ), array( 'field_type' => 'text', 'group' => 'geo_information', 'label' => 'Longitude' ) );
		x_add_metadata_field( 'north_bounds', array( 'map' ), array( 'field_type' => 'text', 'group' => 'geo_information', 'label' => 'North bounds' ) );
		x_add_metadata_field( 'south_bounds', array( 'map' ), array( 'field_type' => 'text', 'group' => 'geo_information', 'label' => 'South bounds' ) );
		x_add_metadata_field( 'west_bounds', array( 'map' ), array( 'field_type' => 'text', 'group' => 'geo_information', 'label' => 'West bounds' ) );
		x_add_metadata_field( 'east_bounds', array( 'map' ), array( 'field_type' => 'text', 'group' => 'geo_information', 'label' => 'East bounds' ) );
		x_add_metadata_field( 'zoom_level', array( 'map' ), array( 'field_type' => 'text', 'group' => 'geo_information', 'label' => 'Zoom Level' ) );



	}

	public function admin_enqueue() {
		wp_enqueue_style( 'cat-tracker-admin', plugins_url( 'resources/cat-tracker-admin.css', __FILE__ ), array(), self::VERSION );
	}

	public function frontend_enqueue() {

		if ( ! is_singular( 'map' ) )
			return;

		wp_enqueue_style( 'leaflet-css', plugins_url( 'resources/leaflet.css', __FILE__ ), array(), self::LEAFLET_VERSION );
		wp_enqueue_style( 'cat-tracker', plugins_url( 'resources/cat-tracker.css', __FILE__ ), array(), self::VERSION );
		wp_enqueue_script( 'leaflet-js', plugins_url( 'resources/leaflet.js', __FILE__ ), array(), self::LEAFLET_VERSION, true );
		wp_enqueue_script( 'cat-tracker-js', plugins_url( 'resources/cat-tracker.js', __FILE__ ), array( 'jquery' ), self::VERSION, true );

		wp_localize_script( 'cat-tracker-js', 'cat_tracker_vars', array(
			'ajax_url' => esc_url( admin_url( 'admin-ajax.php' ) ),
			'map_source' => $this->map_source,
			'map_attribution' => $this->map_attribution,
			'map_latitude' => $this->get_map_latitude(),
			'map_longitude' => $this->get_map_longitude(),
			'map_north_bounds' => $this->get_map_north_bounds(),
			'map_south_bounds' => $this->get_map_south_bounds(),
			'map_west_bounds' => $this->get_map_west_bounds(),
			'map_east_bounds' => $this->get_map_east_bounds(),
			'map_zoom_level' => $this->get_map_zoom_level(),
			) );
	}

	public function enqueue_ie_styles() {
		?>
		<!--[if lte IE 8]>
    	<link rel="stylesheet" href="<?php echo esc_url( add_query_arg( array( 'ver' => self::LEAFLET_VERSION ), plugins_url( 'resources/leaflet.ie.css', __FILE__ ) ) ); ?>" />
		<![endif]-->
		<?php
	}

	public function map_content( $content ) {

		if ( is_singular( 'map' ) )
			$content = '<div id="map"></div>';

		return $content;
	}

	public function map_meta_helper( $meta_key, $singular = true ) {
		if ( ! is_singular( 'map' ) )
			return false;

		return get_post_meta( get_the_ID(), $meta_key, $singular );
	}

	public function get_map_latitude() {
		return $this->map_meta_helper( 'latitude' );
	}

	public function get_map_longitude() {
		return $this->map_meta_helper( 'longitude' );
	}

	public function get_map_north_bounds() {
		return $this->map_meta_helper( 'north_bounds' );
	}

	public function get_map_south_bounds() {
		return $this->map_meta_helper( 'south_bounds' );
	}

	public function get_map_west_bounds() {
		return $this->map_meta_helper( 'west_bounds' );
	}

	public function get_map_east_bounds() {
		return $this->map_meta_helper( 'east_bounds' );
	}

	public function get_map_zoom_level() {
		return $this->map_meta_helper( 'zoom_level' );
	}

}

Cat_Tracker::instance();
<?php

/**
Plugin Name: Cat Mapper internal map
Plugin URI: https://github.com/jkudish/Cat-Tracker
Description: Internal map for Cat Mapper administrators
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

class Cat_Mapper_Internal_Map {

	/**
	 * @var the one true Cat Mapper Internal Map
	 */
	private static $instance;

	/**
	 * (float) internal map version number
	 */
	const VERSION = 1.0;

	/**
	 * Singleton instance
	 *
	 * @since 1.0
	 * @return object $instance the singleton instance of this class
	 */
	public static function instance() {
		if ( isset( self::$instance ) )
			return $instance;

		self::$instance = new Cat_Mapper_Internal_Map;
		self::$instance->run_hooks();
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
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_filter( 'cat_tracker_admin_should_enqueue_map_scripts', array( $this, 'control_admin_should_enqueue_scripts' ) );
		add_filter( 'cat_tracker_admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
		add_filter( 'cat_tracker_admin_map_id', array( $this, 'map_id' ) );
		add_filter( 'cat_tracker_admin_map_markers', array( $this, 'map_markers' ), 10, 2 );
	}

	/**
	 * register menu item
	 *
	 * @since 1.0
	 * @return void
	 */
	public function register_menu() {
		add_menu_page( 'Map', 'Map', 'edit_posts', 'internal-map', array( $this, 'render_page' ), ' ', 30 );
		$map_id = self::get_map_id_for_current_community();
		if ( ! empty( $map_id ) ) {
			add_submenu_page( 'internal-map', 'Edit Map', 'Edit Map', 'edit_posts', 'post.php?post=' . $map_id . ' &action=edit' );
		}
	}

	/**
	 * render the page
	 *
	 * @since 1.0
	 * @return void
	 */
	public function render_page() {
		$map_id = self::get_map_id_for_current_community();

		echo '<div class="wrap">';
			screen_icon();
			echo '<h2>' . sprintf( __( 'Internal Map for %s', 'catmapper' ), get_bloginfo( 'name' ) ) . '</h2>';
			if ( empty( $map_id ) ) {
				echo '<div class="catmapper-internal-map-no-map">';
					echo '<p>' . __( 'There is no map assigned for this community yet. Please contact an administrator if you are having difficulties.', 'catmapper' ) . '</p>';
				echo '</div>';
			} else {
				echo '<div id="map-' . esc_attr( $map_id ) . '" class="catmapper-internal-map"></div>';
				$marker_types = Cat_Tracker::instance()->get_markers( $map_id );
				if ( ! empty( $marker_types ) ) {
					// TODO: this should be abstracted out to a function and should have a better function to get the types, should also be cached better
					echo '<div class="leaflet-control-layers leaflet-control leaflet-control-layers-expanded" id="cat-tracker-custom-controls"><div class="leaflet-control-layers-overlays">';
					echo '<span>' . __( 'Select types of sightings:', 'cat-tracker' ) . '</span>';
					echo '<form>';
					foreach ( $marker_types as $marker_type )
						echo '<label><input data-marker-type="' . esc_attr( $marker_type['slug'] ) . '" class="cat-tracker-layer-control" type="checkbox" checked="checked"> ' . esc_html( $marker_type['title'] ) . '</label>';
					echo '</div></form></div>';
				}
			}
		echo '</div>';
	}

	/**
	 * determine if map scripts should be enqueued in the admin
	 * used to enqueue the scripts when on the internal map page
	 *
	 * @since 1.0
	 * @param (bool) $should_not_enqueue wether or not to enqueue the scripts
	 * @return (bool) $should_not_enqueue wether or not to enqueue the scripts
	 */
	public function control_admin_should_enqueue_scripts( $should_not_enqueue ) {
		if ( self::is_internal_map_page() )
			$should_not_enqueue = false;

		return $should_not_enqueue;
	}

	/**
	 * enqueue additional scripts/styles for the internal map
	 *
	 * @since 1.0
	 * @return void
	 */
	public function admin_enqueue_scripts() {
		if ( ! self::is_internal_map_page() )
			return;

		wp_enqueue_style( 'catmapper-internal-map-styles', plugins_url( 'resources/catmapper-internal-map-styles.css' , __FILE__ ), array(), self::VERSION );
		wp_enqueue_style( 'leaflet-marker-cluster-css', plugins_url( 'cat-tracker/resources/leaflet-markercluster.css' ), array( 'leaflet-css' ), Cat_Tracker::LEAFLET_VERSION );
		wp_enqueue_script( 'leaflet-marker-cluster-js', plugins_url( 'cat-tracker/resources/leaflet-markercluster.js' ), array( 'leaflet-js' ), Cat_Tracker::LEAFLET_VERSION, true );
	}

	/**
	 * determines which map ID to load
	 *
	 * @since 1.0
	 * @param (int) $map_id the map ID
	 * @return (int) $map_id the map ID
	 */
	public function map_id( $map_id ) {
		if ( self::is_internal_map_page() )
			$map_id = self::get_map_id_for_current_community();

		return $map_id;
	}

	/**
	 * loads markers for the internal map
	 *
	 * @since 1.0
	 * @param (array) $map_markers the markers (which by default are the preview marker only)
	 * @return (array) $map_markers all ther markers for the map
	 */
	public function map_markers( $markers, $map_id ) {
		if ( self::is_internal_map_page() )
			$markers = Cat_Tracker::instance()->get_markers( $map_id );

		return $markers;
	}

	/**
	 * helper function to determine the map ID for the current community
	 *
	 * @since 1.0
	 * @return (mixed) false if no map_id or int $map_id the map ID
	 */
	static function get_map_id_for_current_community() {
		$map_id = absint( get_option( 'catmapper_community_main_map_id' ) );
		if ( 0 === $map_id || 1 === $map_id )
			return false;

		return $map_id;
	}

	/**
	 * helper function to determine if the current page is the internal map or not
	 *
	 * @since 1.0
	 * @return (bool)
	 */
	static function is_internal_map_page() {
		global $current_screen;
		return ( is_admin() && isset( $current_screen->base ) && 'toplevel_page_internal-map' == $current_screen->base );
	}

}

add_action( 'plugins_loaded', function(){
	if ( is_main_site() ) // do not load on main site
			return;
	Cat_Mapper_Internal_Map::instance();
});
<?php

/**
Plugin Name: Cat Mapper Markers Admin
Plugin URI: https://github.com/jkudish/Cat-Tracker
Description: Cat Mapper better admin for markers
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

class Cat_Mapper_Markers_Admin {

	/**
	 * @var the one true class of Cat Mapper custom columns
	 */
	private static $instance;

	/**
	 * (float) class/plugin version number
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
			return self::$instance;

		self::$instance = new Cat_Mapper_Markers_Admin;
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
	 * the meat & potatoes of this plugin
	 *
	 * @since 1.0
	 * @return void
	 */
	public function run_hooks() {
		add_filter( 'admin_body_class' , array( $this, 'admin_body_class' ) );
		add_filter( 'manage_cat_tracker_marker_posts_columns' , array( $this, 'define_columns' ) );
		add_action( 'manage_cat_tracker_marker_posts_custom_column' , array( $this, 'display_column_content'), 10, 2 );
		add_filter( 'manage_edit-cat_tracker_marker_sortable_columns' , array( $this, 'register_sortables') );
		add_filter( 'request' , array( $this, 'orderby') );
		add_filter( 'pre_get_posts' , array( $this, 'filter_search') );
		add_filter( 'get_search_query' , array( $this, 'get_search_query') );
		add_action( 'admin_init', array( $this, 'approve_post' ) );
	}

	function admin_body_class( $classes ) {
		if ( Cat_Tracker::MARKER_POST_TYPE == get_post_type() )
			$classes .= 'catmapper-markers';

		if ( Cat_Tracker::MAP_POST_TYPE == get_post_type() )
			$classes .= 'catmapper-maps';

		return $classes;
	}


	/**
	 * define the columns used in WP List Table for markers
	 *
	 * @since 1.1
	 * @param (array) $columns the existing columns
	 * @return (array) $columns the updated columns
	 */
	function define_columns( $columns ) {

	    $columns = array(
			'cb' => '<input type="checkbox" />',
			'animal_id' => __('Animal ID', 'cat-mapper' ),
			'address' => __('Address', 'cat-mapper' ),
			'sighting_date' => __('Sighting Date', 'cat-mapper' ),
			'sighting_type' => __('Sighting Type', 'cat-mapper' ),
			'status' => __('Status', 'cat-mapper' ),
		);

		return $columns;
	}


	/**
	 * display custom columns in WP List Table for markers
	 *
	 * @since 1.1
	 * @param (string) $column the current column
	 * @param (int) $marker_id the current marker ID
	 * @return void
	 */
	function display_column_content( $column, $marker_id ) {

		if ( in_array( $column, array( 'animal_id', 'address' ) ) ) {
			$meta = Cat_Tracker::instance()->marker_meta_helper( $column, $marker_id );
			if ( ! empty( $meta ) )
				echo '<a href="' . esc_url( get_edit_post_link( $marker_id ) ) . '">' . esc_html( $meta ) . '</a>';
			else
				echo '&mdash;';
		}

		if ( 'animal_id' == $column ) {

			echo '<div class="row-actions">';

			if ( current_user_can( 'edit_posts' ) ) {
				echo '<span class="edit"><a href="' . esc_url( get_edit_post_link( $marker_id ) ) . '">' . __( 'Edit' ) . '</a> | <span>';
				echo '<span class="trash"><a href="' . esc_url( get_delete_post_link( $marker_id ) ) . '" class="submitdelete">' . __( 'Trash' ) . '</a> | </span>';
			}

			echo '<span class="view"><a href="' . esc_url( add_query_arg( array( 'page' => 'internal-map' ), 'admin.php' ) ) . '">' . __( 'View on Internal Map' ) . '</a></span>';
			echo '</div>';
		}

		if ( 'sighting_date' == $column ) {
			$date_time = Cat_Tracker::instance()->marker_meta_helper( $column, $marker_id );
			if ( ! empty( $date_time ) && is_numeric( $date_time ) )
				echo date( get_option( 'date_format' ), $date_time );
			else
				echo '&mdash;';
		}

		if ( 'sighting_type' == $column ) {
			if ( Cat_Tracker::instance()->get_marker_type_id( $marker_id ) ) {
				$url = esc_url( add_query_arg( array( 'taxonomy' => Cat_Tracker::MARKER_TAXONOMY, 'term' => Cat_Tracker::instance()->get_marker_type_slug( $marker_id ), 'post_type' => Cat_Tracker::MARKER_POST_TYPE ), admin_url( 'edit.php' ) ) );
				echo '<a href="' . $url . '">' . esc_html( Cat_Tracker::instance()->get_marker_type( $marker_id ) ) . '</a>';
			} else {
				echo '&mdash;';
			}
		}

		if ( 'status' == $column ) {
			$status = get_post_status( $marker_id );
			if ( 'pending' == $status )
				$status = 'Pending &mdash; <a href="' . esc_url( wp_nonce_url( add_query_arg( array( 'approve' => true ), get_edit_post_link( $marker_id ) ), 'cat_mapper_approve_marker' ) ) . '">Approve</a>';
			elseif ( 'publish' == $status )
				$status = 'Approved / Published';
			elseif ( 'draft' == $status )
				$status = 'Draft &mdash; <a href="' . esc_url( wp_nonce_url( add_query_arg( array( 'approve' => true ), get_edit_post_link( $marker_id ) ), 'cat_mapper_approve_marker' ) ) . '">Publish</a>';
			elseif ( empty( $status ) )
				echo '&mdash;';
			else
				$status = ucfirst( $status );

			echo $status;
		}

	}

	function register_sortables( $columns ) {
	    $columns['animal_id'] = 'animal_id';
	    $columns['address'] = 'address';
	    $columns['sighting_date'] = 'sighting_date';

	    return $columns;
	}

	function orderby( $vars ) {

		if ( ! isset( $vars['orderby'] ) || ! isset( $vars['post_type'] ) || Cat_Tracker::MARKER_POST_TYPE != $vars['post_type'] )
			return $vars;

	    if ( 'animal_id' == $vars['orderby'] ) {
	        $vars = array_merge( $vars, array(
	            'meta_key' => Cat_Tracker::META_PREFIX . 'animal_id',
	            'orderby' => 'meta_value_num'
	        ) );
	    }

	    if ( 'address' == $vars['orderby'] ) {
	        $vars = array_merge( $vars, array(
	            'meta_key' => Cat_Tracker::META_PREFIX . 'address',
	            'orderby' => 'meta_value'
	        ) );
	    }

	    if ( 'sighting_date' == $vars['orderby'] ) {
	        $vars = array_merge( $vars, array(
	            'meta_key' => Cat_Tracker::META_PREFIX . 'sighting_date',
	            'orderby' => 'meta_value_num'
	        ) );
	    }


	    return $vars;
	}

	function filter_search( $query ) {
		global $pagenow;
		if ( $pagenow != 'edit.php' || Cat_Tracker::MARKER_POST_TYPE != $query->get( 'post_type' ) || ! isset( $_REQUEST['s'] ) )
			return $query;

		$query->set( 'meta_query', array(
			'relation' => 'OR',
			array(
				'key' => Cat_Tracker::META_PREFIX . 'animal_id',
				'value' => $query->get( 's' ),
				'compare' => 'LIKE',
			),
			array(
				'key' => Cat_Tracker::META_PREFIX . 'address',
				'value' => $query->get( 's' ),
				'compare' => 'LIKE',
			),
		) );

		$query->is_search = false;
		$query->set( 's', null );

		return $query;
	}

	function get_search_query( $search_query ) {
		global $pagenow;
		if ( $pagenow != 'edit.php' || Cat_Tracker::MARKER_POST_TYPE != get_post_type() || ! isset( $_REQUEST['s'] ) )
			return $search_query;

		return strip_tags( $_REQUEST['s'] );
	}

	function approve_post() {
		global $pagenow;
		if ( $pagenow != 'post.php' || empty ( $_GET['post'] ) || Cat_Tracker::MARKER_POST_TYPE != get_post_type( absint( $_GET['post'] ) ) )
			return;

		if ( empty( $_GET['approve'] ) || empty( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'cat_mapper_approve_marker' ) )
			return;

		$marker_id = absint( $_GET['post'] );
		$marker = get_post( $marker_id, 'ARRAY_A' );
		$marker['post_status'] = 'publish';
		wp_update_post( $marker );

		$redirect_url = add_query_arg( array( 'action' => 'edit', 'message' => 6 ), get_edit_post_link( $marker_id ) );
		wp_redirect( $redirect_url );
	}

}

add_action( 'plugins_loaded', function(){
	if ( is_main_site() || ! is_admin() ) // do not load on main site or non admin
			return;
	Cat_Mapper_Markers_Admin::instance();
});
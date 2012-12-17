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
add_action( 'admin_menu', 'cat_mapper_remove_admin_menus', 100 );
function cat_mapper_remove_admin_menus() {
	if ( is_network_admin() )
		return;

	remove_menu_page( 'edit.php' ); // Posts
	remove_menu_page( 'edit-comments.php' ); // Comments
	remove_menu_page( 'tools.php' ); // Tools
	remove_menu_page( 'plugins.php' ); // Plugins
	remove_menu_page( 'edit.php?post_type=' . Cat_Tracker::MAP_POST_TYPE ); // maps post type
	remove_submenu_page( 'options-general.php', 'options-writing.php' ); // Writing options
	remove_submenu_page( 'options-general.php', 'options-discussion.php' ); // Discussion options
	remove_submenu_page( 'options-general.php', 'options-reading.php' ); // Reading options
	remove_submenu_page( 'options-general.php', 'options-media.php' ); // Media options
	remove_submenu_page( 'options-general.php', 'options-permalink.php' ); // Permalink options

	// move JetPack stats menu
	if ( function_exists( 'stats_reports_page' ) ) {
		remove_submenu_page( 'admin.php?page=stats', 'admin.php?page=jetpack' );
		add_dashboard_page( __( 'Site Stats', 'jetpack' ), __( 'Site Stats', 'jetpack' ), 'view_stats', 'stats', 'stats_reports_page' );
	}

	// dev only
	if ( 1 !== get_current_user_id() ) {
		remove_menu_page( 'pb_backupbuddy_multisite_export' ); // BackupBuddy
		remove_menu_page( 'johnny-cache' ); // Johny Cache
		remove_menu_page( 'jetpack' ); // Jetpack
	}

}

/**
 * remove all help screens
 *
 * @since 1.0
 * @return void
 */
add_action( 'admin_head', 'cat_mapper_remove_screen_help' );
function cat_mapper_remove_screen_help() {
	get_current_screen()->remove_help_tabs();
}

/**
 * filter the map source to display with the custom styles
 *
 * @since 1.0
 * @return void
 */
add_filter( 'cat_tracker_map_source', 'cat_mapper_map_source' );
function cat_mapper_map_source() {
	if ( Cat_Tracker::is_submission_mode() || is_admin() || Cat_Tracker::is_rural_map( get_option( 'catmapper_community_main_map_id' ) ) )
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

/**
 * do default stuff when a new community is created
 *
 * @since 1.0
 * @param (int) $blog_id, the newly created community's blog id
 * @param (int) $user_id, the newly created community's user id
 * @return void
 */
add_action( 'wpmu_new_blog', 'catmapper_new_community_created', 100, 2 );
function catmapper_new_community_created( $blog_id, $user_id ) {
	global $wp_rewrite, $wpdb, $current_site;
	switch_to_blog( $blog_id );

	// assign super admins to it
	$super_admins = get_super_admins();
	foreach ( $super_admins as $super_admin ) {
		$userdata = get_user_by( 'login', $super_admin );
		add_existing_user_to_blog( array( 'user_id' => $userdata->ID, 'role' => 'administrator' ) );
	}

	// delete default links
	foreach( range( 1, 7 ) as $link_id )
		wp_delete_link( $link_id );

	// delete first comment
	wp_delete_comment( 1 );

	// delete first post & first page
	wp_delete_post( 1 );
	wp_delete_post( 2 );

	// create default sighting types
	$community_cat = wp_create_term( 'Community cat', Cat_Tracker::MARKER_TAXONOMY );
	$community_cat_with_kittens = wp_create_term( 'Community cat with kittens', Cat_Tracker::MARKER_TAXONOMY );
	$orphaned_kittens = wp_create_term( 'Orphaned Kittens (under 8 weeks)', Cat_Tracker::MARKER_TAXONOMY );
	$group_of_comm_cats = wp_create_term( 'Group of community cats', Cat_Tracker::MARKER_TAXONOMY );
	$tnr_colony = wp_create_term( 'TNR Colony', Cat_Tracker::MARKER_TAXONOMY );
	$bcspca_cat = wp_create_term( 'BC SPCA unowned intake - Cat', Cat_Tracker::MARKER_TAXONOMY );
	$bcspca_kitten = wp_create_term( 'BC SPCA unowned intake - Kitten', Cat_Tracker::MARKER_TAXONOMY );

	// assign colors to sighting types
	add_term_meta( $tnr_colony['term_id'], 'color', '#fff61b' ); // yellow
	add_term_meta( $group_of_comm_cats['term_id'], 'color', '#ff96a5' ); // pink
	add_term_meta( $community_cat['term_id'], 'color', '#7fd771' ); // green
	add_term_meta( $community_cat_with_kittens['term_id'], 'color', '#00b4fe' ); // blue
	add_term_meta( $orphaned_kittens['term_id'], 'color', '#ac3eff' ); // purple
	add_term_meta( $bcspca_cat['term_id'], 'color', '#636363' ); // grey
	add_term_meta( $bcspca_kitten['term_id'], 'color', '#636363' ); // grey

	// assign as internal types
	add_term_meta( $bcspca_cat['term_id'], 'internal_type', true );
	add_term_meta( $bcspca_kitten['term_id'], 'internal_type', true );

	// switch to the correct theme
	switch_theme( 'catmapper' );

	// get theme mods from main site
	switch_to_blog( $current_site->blog_id );
	$main_site_theme_mods = get_theme_mods();
	restore_current_blog();

	// set default options
	$default_options = array(
		'blogdescription' => 'Cat Mapper',
		'timezone_string' => 'America/Vancouver',
		'permalink_structure' => '/%postname%/',
		'default_pingback_flag' => false,
		'default_ping_status' => false,
		'default_comment_status' => false,
		'comment_moderation' => true,
		'sidebars_widgets' => array(),
		'theme_mods_twentytwelve' => $main_site_theme_mods,
		'theme_mods_catmapper' => $main_site_theme_mods,
	);

	foreach ( $default_options as $option_key => $option_value )
		update_option( $option_key, $option_value );

	flush_rewrite_rules();

	// set a default/empty menu
	$menu_id = wp_create_nav_menu( 'blank' );
	set_theme_mod( 'nav_menu_locations', array( 'primary' => $menu_id ) );

	// unhook action which otherwise causes a notice
	remove_action( 'transition_post_status', '_update_blog_date_on_post_publish' );

	// create front page
	$front_page_id = wp_insert_post( array( 'post_type' => 'page', 'post_title' => get_bloginfo( 'name' ), 'post_status' => 'publish' ) );
	if ( $front_page_id && ! is_wp_error( $front_page_id ) ) {
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $front_page_id );
	}

	// create the map
	$map_id = wp_insert_post( array( 'post_type' => Cat_Tracker::MAP_POST_TYPE, 'post_title' => get_bloginfo( 'name' ) ) );

	// queue a "job" to refresh the blog list
 	// though not strictly requried, passing the blog id ensures the event is unique enough to run again if it's called shortly after this event has occurred already
	wp_schedule_single_event( time(), 'catmapper_refresh_all_blog_ids_event', array( 'blog_id' => $blog_id ) );

	if ( $map_id && ! is_wp_error( $map_id ) ) {
		update_option( 'catmapper_community_main_map_id', $map_id );
		wp_redirect( add_query_arg( array( 'post' => $map_id, 'action' => 'edit', 'message' => 11 ), admin_url( 'post.php' ) ) );
		exit;
	}

	wp_redirect( add_query_arg( array( 'post_type' => Cat_Tracker::MAP_POST_TYPE, 'message' => 11 ), admin_url( 'post-new.php' ) ) );
	exit;
}

/**
 * filter enter title here text on new maps to be the name of the current community
 *
 * @since 1.0
 * @param (string) $title the title to filter
 * @param (object) $post the post object for the current post
 * @return void
 */
add_filter( 'enter_title_here', 'cat_mapper_enter_title_here', 10, 2 );
function cat_mapper_enter_title_here( $title, $post ) {
	if ( Cat_Tracker::MAP_POST_TYPE == get_post_type( $post ) )
		$title = get_bloginfo( 'name' );

	return $title;
}

/**
 * adjust which dashboard widgets show and which don't
 *
 * @since 1.0
 * @return void
 */
add_action( 'wp_dashboard_setup', 'catmapper_adjust_dashboard_widgets' );
function catmapper_adjust_dashboard_widgets() {
	global $wp_meta_boxes;
	unset($wp_meta_boxes['dashboard']['normal']['core']['dashboard_right_now']); // right now [content, discussion, theme, etc]
	unset( $wp_meta_boxes['dashboard']['normal']['core']['dashboard_plugins'] ); // plugins
	unset( $wp_meta_boxes['dashboard']['normal']['core']['dashboard_incoming_links'] ); // incoming links
	unset( $wp_meta_boxes['dashboard']['side']['core']['dashboard_primary'] ); // wordpress blog
	unset( $wp_meta_boxes['dashboard']['side']['core']['dashboard_secondary'] ); // other wordpress news
	unset( $wp_meta_boxes['dashboard']['side']['core']['dashboard_quick_press'] ); // quickpress
	unset( $wp_meta_boxes['dashboard']['side']['core']['dashboard_recent_drafts'] ); // drafts
	unset( $wp_meta_boxes['dashboard']['normal']['core']['dashboard_recent_comments'] ); // comments

	if ( current_user_can( 'edit_others_markers' ) ) {
		$user = wp_get_current_user();
		$user_name = ( ! empty ( $user->first_name ) ) ? $user->first_name : $user->display_name;
		$title = sprintf( __( 'Welcome to Cat Mapper %s!', 'cat-mapper' ), $user_name );
		wp_add_dashboard_widget( 'dashboard_right_now', $title, 'catmapper_dashboard_widget' );
	}
}

/**
 * add new fields specific to catmapper
 *
 * @since 1.0
 * @return void
 */
add_action( 'cat_tracker_did_custom_fields', 'cat_mapper_custom_fields' );
function cat_mapper_custom_fields() {
	x_add_metadata_group( 'bcspca_extra_information', array( Cat_Tracker::MARKER_POST_TYPE ), array( 'label' => 'Additional Animal Information', 'priority' => 'high' ) );
	x_add_metadata_field( Cat_Tracker::META_PREFIX . 'animal_id', array( Cat_Tracker::MARKER_POST_TYPE ), array( 'field_type' => 'text', 'group' => 'bcspca_extra_information', 'label' => 'Animal ID' ) );
	x_add_metadata_field( Cat_Tracker::META_PREFIX . 'source', array( Cat_Tracker::MARKER_POST_TYPE ), array( 'field_type' => 'text', 'group' => 'bcspca_extra_information', 'label' => 'Animal source' ) );
	x_add_metadata_field( Cat_Tracker::META_PREFIX . 'breed', array( Cat_Tracker::MARKER_POST_TYPE ), array( 'field_type' => 'text', 'group' => 'bcspca_extra_information', 'label' => 'Animal breed' ) );
	x_add_metadata_field( Cat_Tracker::META_PREFIX . 'color', array( Cat_Tracker::MARKER_POST_TYPE ), array( 'field_type' => 'text', 'group' => 'bcspca_extra_information', 'label' => 'Animal color' ) );
	x_add_metadata_field( Cat_Tracker::META_PREFIX . 'gender', array( Cat_Tracker::MARKER_POST_TYPE ), array( 'field_type' => 'text', 'group' => 'bcspca_extra_information', 'label' => 'Animal gender' ) );
	x_add_metadata_field( Cat_Tracker::META_PREFIX . 'age_group', array( Cat_Tracker::MARKER_POST_TYPE ), array( 'field_type' => 'text', 'group' => 'bcspca_extra_information', 'label' => 'Animal age group' ) );
	x_add_metadata_field( Cat_Tracker::META_PREFIX . 'incoming_spay_neuter_status', array( Cat_Tracker::MARKER_POST_TYPE ), array( 'field_type' => 'text', 'group' => 'bcspca_extra_information', 'label' => 'Incoming spay/neuter status' ) );
	x_add_metadata_field( Cat_Tracker::META_PREFIX . 'current_spay_neuter_status', array( Cat_Tracker::MARKER_POST_TYPE ), array( 'field_type' => 'text', 'group' => 'bcspca_extra_information', 'label' => 'Current spay/neuter status' ) );

	remove_meta_box( 'postcustom', 'page', 'normal' );
	remove_meta_box( 'commentstatusdiv', 'page', 'normal' );
	remove_meta_box( 'commentsdiv', 'page', 'normal' );
	remove_meta_box( 'slugdiv', 'page', 'normal' );
	remove_meta_box( 'postimagediv', 'page', 'side' );
}

/**
 * filter maps CPT labels
 *
 * @since 1.0
 * @param (array) $labels the labels for the CPT
 * @return (array) $labels the filtered labels
 */
add_filter( 'cat_tracker_map_post_type_labels', 'cat_mapper_map_post_type_labels' );
function cat_mapper_map_post_type_labels( $labels ) {
	$labels['edit_item'] = sprintf( __( 'Edit %s map', 'cat-mapper' ), get_bloginfo( 'name' ) );
	return $labels;
}

/**
 * filter the admin bar
 *
 * @since 1.0
 * @return void
 */
add_action( 'init', 'cat_mapper_admin_bar', 100 );
function cat_mapper_admin_bar() {
	remove_action( 'admin_bar_menu', 'wp_admin_bar_wp_menu', 10 );
	remove_action( 'admin_bar_menu', 'wp_admin_bar_my_sites_menu', 20 );
	remove_action( 'admin_bar_menu', 'wp_admin_bar_comments_menu', 60 );
	remove_action( 'admin_bar_menu', 'wp_admin_bar_new_content_menu', 70 );

	add_action( 'admin_bar_menu', 'cat_mapper_admin_bar_sites_menu', 20 );
	add_action( 'admin_bar_menu', 'cat_mapper_admin_bar_sightings_menu', 60 );
}

/**
 * Add the "Cat Mapper" general menu and all submenus.
 *
 * @since 1.0
 * @return void
 */
function cat_mapper_admin_bar_sites_menu( $wp_admin_bar ) {
	global $wpdb;

	// Don't show for logged out users or single site mode.
	if ( ! is_user_logged_in() || ! is_multisite() )
		return;

	// Show only when the user has at least one site, or they're a super admin.
	if ( count( $wp_admin_bar->user->blogs ) < 1 && ! is_super_admin() )
		return;

	$wp_admin_bar->add_menu( array(
		'id'    => 'my-sites',
		'title' => __( 'Cat Mapper Communities', 'cat-mapper' ),
		'href'  => admin_url( 'my-sites.php' ),
	) );

	if ( is_super_admin() ) {
		$wp_admin_bar->add_group( array(
			'parent' => 'my-sites',
			'id'     => 'my-sites-super-admin',
		) );

		$wp_admin_bar->add_menu( array(
			'parent' => 'my-sites-super-admin',
			'id'     => 'network-admin',
			'title'  => __('Network Admin'),
			'href'   => network_admin_url(),
		) );

		$wp_admin_bar->add_menu( array(
			'parent' => 'network-admin',
			'id'     => 'network-admin-d',
			'title'  => __( 'Dashboard' ),
			'href'   => network_admin_url(),
		) );
		$wp_admin_bar->add_menu( array(
			'parent' => 'network-admin',
			'id'     => 'network-admin-s',
			'title'  => __( 'Sites' ),
			'href'   => network_admin_url( 'sites.php' ),
		) );
		$wp_admin_bar->add_menu( array(
			'parent' => 'network-admin',
			'id'     => 'network-admin-u',
			'title'  => __( 'Users' ),
			'href'   => network_admin_url( 'users.php' ),
		) );
		$wp_admin_bar->add_menu( array(
			'parent' => 'network-admin',
			'id'     => 'network-admin-v',
			'title'  => __( 'Visit Network' ),
			'href'   => network_home_url(),
		) );
	}

	// Add site links
	$wp_admin_bar->add_group( array(
		'parent' => 'my-sites',
		'id'     => 'my-sites-list',
		'meta'   => array(
			'class' => is_super_admin() ? 'ab-sub-secondary' : '',
		),
	) );

	foreach ( (array) $wp_admin_bar->user->blogs as $blog ) {
		switch_to_blog( $blog->userblog_id );

		$blavatar = '<div class="blavatar"></div>';

		$blogname = empty( $blog->blogname ) ? $blog->domain : $blog->blogname;
		$menu_id  = 'blog-' . $blog->userblog_id;

		$wp_admin_bar->add_menu( array(
			'parent'    => 'my-sites-list',
			'id'        => $menu_id,
			'title'     => $blavatar . $blogname,
			'href'      => admin_url(),
		) );

		$wp_admin_bar->add_menu( array(
			'parent' => $menu_id,
			'id'     => $menu_id . '-d',
			'title'  => __( 'Dashboard' ),
			'href'   => admin_url(),
		) );

		if ( ! is_main_site() && current_user_can( 'edit_markers' ) ) {
			$wp_admin_bar->add_menu( array(
				'parent' => $menu_id,
				'id'     => $menu_id . '-n',
				'title'  => __( 'New Sighting', 'cat-mapper' ),
				'href'   => add_query_arg( array( 'post_type' => Cat_Tracker::MARKER_POST_TYPE ), admin_url( 'post-new.php' ) ),
			) );
			$wp_admin_bar->add_menu( array(
				'parent' => $menu_id,
				'id'     => $menu_id . '-c',
				'title'  => __( 'Manage Sightings', 'cat-mapper' ),
				'href'   => add_query_arg( array( 'post_type' => Cat_Tracker::MARKER_POST_TYPE ), admin_url( 'edit.php' ) ),
			) );
			$wp_admin_bar->add_menu( array(
				'parent' => $menu_id,
				'id'     => $menu_id . '-e',
				'title'  => __( 'View map', 'cat-mapper' ),
				'href'   => add_query_arg( array( 'page' => 'internal-map' ), admin_url( 'admin.php' ) ),
			) );
		}

		$wp_admin_bar->add_menu( array(
			'parent' => $menu_id,
			'id'     => $menu_id . '-v',
			'title'  => __( 'Visit Site' ),
			'href'   => home_url( '/' ),
		) );

		restore_current_blog();
	}
}

/**
 * Sightings + Map edit admin bar menu
 *
 * @since 1.0
 * @return void
 */
function cat_mapper_admin_bar_sightings_menu( $wp_admin_bar ) {
	if ( is_main_site() || ! current_user_can( 'edit_markers' ) )
		return;

	$awaiting_mod = wp_count_posts( Cat_Tracker::MARKER_POST_TYPE );
	$awaiting_mod = $awaiting_mod->pending;
	$awaiting_title = esc_attr( sprintf( _n( '%s sighting awaiting moderation', '%s sightings awaiting moderation', $awaiting_mod ), number_format_i18n( $awaiting_mod ) ) );

	$class = 'ab-icon';
	if ( $awaiting_mod > 0 )
		$class .= ' has-pending';
	$icon  = '<span class="' . esc_attr( $class ) . '"></span>';
	$title = '<span id="ab-awaiting-mod" class="ab-label awaiting-mod pending-count count-' . $awaiting_mod . '">' . number_format_i18n( $awaiting_mod ) . '</span>';

	$wp_admin_bar->add_menu( array(
		'id'    => 'sightings',
		'title' => $icon . $title,
		'href'  => add_query_arg( array( 'post_type' => Cat_Tracker::MARKER_POST_TYPE, 'post_status' => 'pending' ), admin_url( 'edit.php' ) ),
		'meta'  => array( 'title' => $awaiting_title ),
	) );

	$map_id = get_option( 'catmapper_community_main_map_id' );
	if ( empty( $map_id ) )
		return;

	$wp_admin_bar->add_menu( array(
		'id'    => 'view_map',
		'title' => __( 'View Map', 'cat_mapper' ),
		'href'  => add_query_arg( array( 'page' => 'internal-map' ), admin_url( 'admin.php' ) ),
	) );

	if ( current_user_can( 'edit_maps' ) ) {
		$wp_admin_bar->add_menu( array(
			'id'    => 'edit_map',
			'title' => __( 'Edit Map', 'cat_mapper' ),
			'href'  => add_query_arg( array( 'post' => $map_id, 'action' => 'edit' ), admin_url( 'post.php' ) ),
		) );
	}
}

/**
 * load css for admin bar
 *
 * @since 1.0
 * @return void
 */
add_action( 'wp_enqueue_scripts', 'cat_mapper_admin_bar_css', 100 );
add_action( 'admin_enqueue_scripts', 'cat_mapper_admin_bar_css', 100 );
add_action( 'login_enqueue_scripts', 'cat_mapper_admin_bar_css', 100 );
function cat_mapper_admin_bar_css() {
	wp_enqueue_style( 'catmapper-universal-styles', plugins_url( 'resources/catmapper-universal-styles.css', __FILE__ ), array(), Cat_Tracker::VERSION );
}

/**
 * modify wether the map is being shown or not
 * used to show the map on the front page of subsistes
 *
 * @since 1.0
 * @param (bool) $is_showing_map wether to show the map or not
 * @return (bool) $is_showing_map filtered value of wether to show the map or not
 */
add_filter( 'cat_tracker_is_showing_map', 'cat_mapper_is_showing_map' );
function cat_mapper_is_showing_map( $is_showing_map ) {
	if ( is_main_site() )
		return $is_showing_map;

	if ( is_front_page() )
		return true;

	return $is_showing_map;
}

/**
 * filter the map ID to be used on front page of subsites
 *
 * @since 1.0
 * @param (int) $map_id map ID to use
 * @return (int) $map_id filtered value for map ID to use
 */
add_filter( 'cat_tracker_map_content_map_id', 'cat_mapper_map_content_map_id' );
function cat_mapper_map_content_map_id( $map_id ) {
	if ( is_main_site() )
		return $map_id;

	if ( is_front_page() )
		return absint( get_option( 'catmapper_community_main_map_id' ) );

	return $map_id;
}

/**
 * filter the map ID assigned to markers
 *
 * @since 1.0
 * @param (int) $map_id map ID to use
 * @return (int) $map_id filtered value for map ID to use
 */
add_filter( 'get_map_id_for_marker', 'cat_mapper_get_map_id_for_marker' );
function cat_mapper_get_map_id_for_marker( $map_id ) {
	$catmapper_community_main_map_id = get_option( 'catmapper_community_main_map_id' );
	if ( isset( $catmapper_community_main_map_id ) )
		$map_id = absint( $catmapper_community_main_map_id );

	return $map_id;
}

add_filter( 'cat_tracker_show_map_to_display_sighting_on_admin_field', '__return_false' );

/**
 * filter the title out on the home page
 *
 * @since 1.0
 * @param (string) $title the original title
 * @return (string) $title the filtered title
 */
add_filter( 'the_title', 'catmapper_home_filter_title' );
function catmapper_home_filter_title( $title ) {

	if ( is_main_site() && is_front_page() && in_the_loop() )
		$title = '';

	return $title;
}

/**
 * whenever we load a background related theme mod, load it from
 * the main site unless a mod is already set for the current site
 *
 * @since 1.0
 * @param (mixed) $theme_mod the original value for the theme mod
 * @return (mixed) $theme_mod the filtered value for the theme mod
 */
add_filter( 'theme_mod_background_image', 'catmapper_get_theme_mod_from_main_site' );
add_filter( 'theme_mod_background_image_thumb', 'catmapper_get_theme_mod_from_main_site' );
add_filter( 'theme_mod_background_color', 'catmapper_get_theme_mod_from_main_site' );
add_filter( 'theme_mod_background_position_x', 'catmapper_get_theme_mod_from_main_site' );
function catmapper_get_theme_mod_from_main_site( $theme_mod ) {
	if ( is_main_site() )
		return $theme_mod;

	if ( ! empty( $theme_mod ) )
		return $theme_mod;

	if ( catmapper_current_blog_has_own_theme_mods() )
		return $theme_mod;

	$theme_mod_name = end( explode( 'theme_mod_', current_filter() ) );
	$blog_id = get_current_blog_id();
	$cache_key = "theme_mod_{$theme_mod_name}_blog_id_{$blog_id}";
	$theme_mod = wp_cache_get( $cache_key, 'cat_mapper' );

	if ( ! empty( $theme_mod ) )
		return $theme_mod;

	global $current_site;
	switch_to_blog( $current_site->blog_id );
	$theme_mod = get_theme_mod( $theme_mod_name );
	restore_current_blog();
	wp_cache_set( $cache_key, $theme_mod, 'cat_mapper' );
	return $theme_mod;
}

/**
 * whenever we modify a theme mod on the main site
 * also modify the theme mods on every subsite
 * unless its marked as having it's own mods,
 * in which case it's ignored
 *
 * @since 1.0
 * @param (string) $option the name of the option being modified
 * @param (mixed) $oldvalue the previous value of the option
 * @param (mixed) $newvalue the newly assigned value of the option
 * @return void
 */
add_action( 'updated_option', 'catmapper_update_theme_mod_option', 10, 3 );
function catmapper_update_theme_mod_option( $option, $oldvalue, $newvalue ) {
	if ( ! in_array( $option, array( 'theme_mods_twentytwelve', 'theme_mods_catmapper' ) ) || ! is_main_site() )
		return;

	$blog_ids = catmapper_get_all_blog_ids();
	foreach ( $blog_ids as $blog_id ) {
		switch_to_blog( absint( $blog_id ) );
		if ( catmapper_current_blog_has_own_theme_mods() )
			continue;

		update_option( 'theme_mods_twentytwelve', $newvalue );
		update_option( 'theme_mods_catmapper', $newvalue );
		restore_current_blog();
	}
}

/**
 * determine if the current blog uses it's own mods
 *
 * @since 1.0
 * @return (bool)
 */
function catmapper_current_blog_has_own_theme_mods() {
	return get_option( 'cat_mapper_has_own_theme_mods' );
}

/**
 * get all blog IDs in the network
 * cached with a site transient that we update using a scheduled
 * event whenever a new subsite is created
 *
 * will warn once large network status is achieved
 *
 * @since 1.0
 * @return (array) $site_blog_ids, all blog IDs for the network
 */
function catmapper_get_all_blog_ids() {

	$site_blog_ids = get_site_transient( 'catmapper_all_blog_ids', 'cat_mapper' );

	if ( false === $site_blog_ids ) {

		// just a safety net, in case this network explodes in size some day
		if ( wp_is_large_network() ) {

			// leave a note to my future self
			_doing_it_wrong( __FUNCTION__, "Unfortunately this function cannot be used anymore for performance reasons now that there are so many sites, let's find a better solution", Cat_Tracker::VERSION );

			// queue a "job" to refresh the blog list
		 	// though not strictly requried, passing the time ensures the event is unique enough to run again if it's called shortly after this event has occurred already
			wp_schedule_single_event( time(), 'catmapper_refresh_all_blog_ids_event', array( 'time' => time() ) );
			return array();
		}

		$site_blog_ids = catmapper_refresh_all_blog_ids();
	}
	return $site_blog_ids;
}

add_action( 'delete_blog', 'catmapper_queue_refresh_all_blog_ids' );
function catmapper_queue_delete_blog() {
	// queue a "job" to refresh the blog list, with a 1 minute delay to ensure the blog is deleted by the time the event runs
 	// though not strictly requried, passing the time ensures the event is unique enough to run again if it's called shortly after this event has occurred already
 	$time_delay = time() + MINUTE_IN_SECONDS;
	wp_schedule_single_event( $time_delay, 'catmapper_refresh_all_blog_ids_event', array( 'time' => $time_delay ) );
}

/**
 * refresh array of blog IDs on demand, and set the site transient
 * usually called from a scheduled event
 *
 * will warn once large network status is achieved
 *
 * @since 1.0
 * @return (array) $site_blog_ids, all blog IDs for the network
 */
add_action( 'catmapper_refresh_all_blog_ids_event', 'catmapper_refresh_all_blog_ids' );
function catmapper_refresh_all_blog_ids() {
	global $wpdb;

	// leave a note to my future self in the event of a large network status
	if ( wp_is_large_network() )
		_doing_it_wrong( __FUNCTION__, "This function should be re-evaluated for performance reasons now that there are so many sites.", Cat_Tracker::VERSION );

	$_site_blog_ids = $wpdb->get_results( "SELECT blog_id FROM $wpdb->blogs WHERE blog_id > 1", ARRAY_A );
	if ( empty( $_site_blog_ids ) || is_wp_error( $_site_blog_ids ) )
		return array();

	$site_blog_ids = wp_list_pluck( $_site_blog_ids, 'blog_id' );
	if ( empty( $site_blog_ids ) || is_wp_error( $site_blog_ids ) )
		return array();

	set_site_transient( 'catmapper_all_blog_ids', $site_blog_ids );
	return $site_blog_ids;
}

/**
 * assign the community map ID to markers when saved
 *
 * @since 1.0
 * @param (int) $post_id the marker ID being saved
 * @return void
 */
add_action( 'save_post', 'catmapper_assign_map_id', 1000 );
function catmapper_assign_map_id( $post_id ) {
	if ( wp_is_post_revision( $post_id ) || Cat_Tracker::MARKER_POST_TYPE != get_post_type( $post_id ) )
			return;

	$assigned_map_id = Cat_Tracker::instance()->get_map_id_for_marker( $post_id );

	if ( ! empty( $assigned_map_id ) )
		return;

	update_post_meta( $post_id, Cat_Tracker::META_PREFIX . 'map', get_option( 'catmapper_community_main_map_id' ) );
}

/**
 * add an admin bar menu item to flush caches
 *
 * @since 1.0
 * @return void;
 */
add_action( 'init', 'catmapper_flush_cache_admin_bar' );
function catmapper_flush_cache_admin_bar() {

	if ( ! function_exists( 'afc_add_item' ) ) {
		_doing_it_wrong( __FUNCTION__, 'A Fresher Cache plugin is not installed', Cat_Tracker::VERSION );
		return;
	}

  afc_add_item( array(
      'id' => 'cat-mapper-flush-marker-cache',
      'title' => 'Map markers',
      'function' => 'catmapper_flush_all_markers_cache',
  ) );

  afc_add_item( array(
      'id' => 'cat-mapper-flush-blog-id-cache',
      'title' => 'Community site IDs',
      'function' => 'catmapper_refresh_all_blog_ids',
  ) );

}

/**
 * wrapper function to flush all marker cache
 *
 * @since 1.0
 * @return void
 */
function catmapper_flush_all_markers_cache() {
	do_action( 'cat_tracker_flush_all_markers_cache' );
}

/**
 * dashboard widget
 *
 * @since 1.0
 * @return void
 */
function catmapper_dashboard_widget() {
	$pages = wp_count_posts( 'page' );
	$sightings = wp_count_posts( Cat_Tracker::MARKER_POST_TYPE );
	$marker_types = wp_count_terms( Cat_Tracker::MARKER_TAXONOMY );
	?>
	<div class="table table_content">
		<p class="sub"><?php _e( 'Content', 'cat-mapper' ); ?></p>
		<table>
			<tbody>
				<tr class="first">
					<?php $sightings_url = esc_url( add_query_arg( array( 'post_type' => Cat_Tracker::MARKER_POST_TYPE ), admin_url( 'edit.php' ) ) ); ?>
					<td class="first b b-posts"><a href="<?php echo $sightings_url; ?>"><?php echo $sightings->publish ?></a></td>
					<td class="t posts"><a href="<?php echo $sightings_url; ?>"><?php _e( 'Sightings', 'cat-mapper' ); ?></a></td>
				</tr>
				<tr>
					<?php $pending_sightings_url = esc_url( add_query_arg( array( 'post_status' => 'pending', 'post_type' => Cat_Tracker::MARKER_POST_TYPE ), admin_url( 'edit.php' ) ) ); ?>
					<td class="first b b_pages"><a href="<?php echo $pending_sightings_url; ?>"><?php echo $sightings->pending ?></a></td>
					<td class="t pages"><a href="<?php echo $pending_sightings_url; ?>"><?php _e( 'Sightings to review', 'cat-mapper' ); ?></a></td>
				</tr>
				<tr>
					<?php $sighting_types_url = esc_url( add_query_arg( array( 'taxonomy' => Cat_Tracker::MARKER_TAXONOMY, 'post_type' => Cat_Tracker::MARKER_POST_TYPE ), admin_url( 'edit-tags.php' ) ) ); ?>
					<td class="first b b-cats"><a href="<?php echo $sighting_types_url ?>"><?php echo $marker_types ?></a></td>
					<td class="t cats"><a href="<?php echo $sighting_types_url ?>"><?php _e( 'Sighting types', 'cat-mapper' ); ?></a></td></tr>
				<tr>
					<?php $pages_url = esc_url( add_query_arg( array( 'post_type' => 'page' ), admin_url( 'edit.php' ) ) ); ?>
					<td class="first b b-tags"><a href="<?php echo $pages_url ?>"><?php echo $pages->publish ?></a></td>
					<td class="t tags"><a href="<?php echo $pages_url ?>"><?php _e( 'Pages', 'cat-mapper' ); ?></a></td>
				</tr>
			</tbody>
		</table>
	</div>
	<div class="clear"></div>
	<?php
	echo '<p>' . sprintf( __( '<a href="%s">Click here to view</a> the %s map', 'cat-mapper' ), esc_url( add_query_arg( array( 'page' => 'internal-map' ), admin_url( 'admin.php' ) ) ), get_bloginfo() ) . '</p>';
	echo '<p>' . sprintf( __( 'If you require any assistance, please contact <a href="%s">%s</a>.', 'cat-mapper' ), esc_url( "mailto:info@catmapper.ca" ), 'info@catmapper.ca' ) . '</p>';

}
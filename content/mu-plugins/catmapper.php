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
	remove_menu_page( 'upload.php' ); // Media
	remove_menu_page( 'edit-comments.php' ); // Comments
	remove_menu_page( 'tools.php' ); // Tools
	remove_menu_page( 'plugins.php' ); // Plugins
	remove_menu_page( 'edit.php?post_type=' . Cat_Tracker::MAP_POST_TYPE ); // maps post type
	remove_submenu_page( 'options-general.php', 'options-writing.php' ); // Writing options
	remove_submenu_page( 'options-general.php', 'options-discussion.php' ); // Discussion options
	remove_submenu_page( 'options-general.php', 'options-reading.php' ); // Reading options
	remove_submenu_page( 'options-general.php', 'options-media.php' ); // Media options
	remove_submenu_page( 'options-general.php', 'options-permalink.php' ); // Permalink options

	if ( ! is_main_site() )
		remove_menu_page( 'edit.php?post_type=page' ); // pages

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

	// delete default links
	foreach( range( 1, 7 ) as $link_id )
		wp_delete_link( $link_id );

	// delete first comment
	wp_delete_comment( 1 );

	// delete first post & first page
	wp_delete_post( 1 );
	wp_delete_post( 2 );

	// create default sighting types
	$tnr_colony = wp_create_term( 'TNR Colony', Cat_Tracker::MARKER_TAXONOMY );
	$group_of_comm_cats = wp_create_term( 'Group of community cats', Cat_Tracker::MARKER_TAXONOMY );
	$community_cat = wp_create_term( 'Community cat', Cat_Tracker::MARKER_TAXONOMY );
	$community_cat_with_kittens = wp_create_term( 'Community cat with kittens', Cat_Tracker::MARKER_TAXONOMY );
	$bcspca_cat = wp_create_term( 'BC SPCA unowned intake - Cat', Cat_Tracker::MARKER_TAXONOMY );
	$bcspca_kitten = wp_create_term( 'BC SPCA unowned intake - Kitten', Cat_Tracker::MARKER_TAXONOMY );

	// assign colors to sighting types
	add_term_meta( $tnr_colony['term_id'], 'color', '#fff61b' ); // yellow
	add_term_meta( $group_of_comm_cats['term_id'], 'color', '#ff96a5' ); // ff96a5
	add_term_meta( $community_cat['term_id'], 'color', '#7fd771' ); // green
	add_term_meta( $community_cat_with_kittens['term_id'], 'color', '#00b4fe' ); // green
	add_term_meta( $bcspca_cat['term_id'], 'color', '#636363' ); // grey
	add_term_meta( $bcspca_kitten['term_id'], 'color', '#636363' ); // grey

	// switch to the correct theme
	switch_theme( 'catmapper' );

	// set default options
	$default_options = array(
		'home' => trailingslashit( str_ireplace( '/wp', '', home_url() ) ),
		'blogdescription' => 'Cat Mapper',
		'timezone_string' => 'America/Vancouver',
		'permalink_structure' => '/%postname%/',
		'default_pingback_flag' => false,
		'default_ping_status' => false,
		'default_comment_status' => false,
		'comment_moderation' => true,
		'sidebars_widgets' => array(),
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
	$map_id = wp_insert_post( array( 'post_type' => Cat_Tracker::MAP_POST_TYPE , 'post_title' => get_bloginfo( 'name' ) ) );

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
	// unset($wp_meta_boxes['dashboard']['normal']['core']['dashboard_right_now']); // right now [content, discussion, theme, etc]
	unset( $wp_meta_boxes['dashboard']['normal']['core']['dashboard_plugins'] ); // plugins
	unset( $wp_meta_boxes['dashboard']['normal']['core']['dashboard_incoming_links'] ); // incoming links
	unset( $wp_meta_boxes['dashboard']['side']['core']['dashboard_primary'] ); // wordpress blog
	unset( $wp_meta_boxes['dashboard']['side']['core']['dashboard_secondary'] ); // other wordpress news
	unset( $wp_meta_boxes['dashboard']['side']['core']['dashboard_quick_press'] ); // quickpress
	unset( $wp_meta_boxes['dashboard']['side']['core']['dashboard_recent_drafts'] ); // drafts
	unset( $wp_meta_boxes['dashboard']['normal']['core']['dashboard_recent_comments'] ); // comments
}

/**
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
 * filter maps CPT
 *
 * @since 1.0
 * @param (array) $cpt_args the args for the CPT
 * @return (array) $cpt_args the filtered args
 */
add_filter( 'cat_tracker_map_post_type_args', 'cat_mapper_map_post_type_args' );
function cat_mapper_map_post_type_args( $cpt_args ) {
	$cpt_args['supports'] = array( 'revisions' );
	$cpt_args['rewrite'] = array();
	$cpt_args['public'] = false;
	$cpt_args['show_ui'] = false;
	return $cpt_args;
}

/**
 * exclude bcspca
 *
 * @todo: rewrite this to use meta
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

		if ( ! is_main_site() && current_user_can( 'edit_posts' ) ) {
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
 * Sightings admin bar menu
 *
 * @since 1.0
 * @return void
 */
function cat_mapper_admin_bar_sightings_menu( $wp_admin_bar ) {
	if ( is_main_site() || ! current_user_can( 'edit_posts' ) )
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
		'href'  => add_query_arg( array( 'post_type' => Cat_Tracker::MARKER_POST_TYPE ), admin_url( 'edit.php' ) ),
		'meta'  => array( 'title' => $awaiting_title ),
	) );
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
	wp_enqueue_style( 'catmapper-universal-styles', plugins_url( 'catmapper-universal-styles.css', __FILE__ ), array(), Cat_Tracker::VERSION );
}
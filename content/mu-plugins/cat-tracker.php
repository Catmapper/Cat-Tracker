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
 * @package Cat_Tracker
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
	remove_menu_page( 'edit.php' ); // Posts
	remove_menu_page( 'edit-comments.php' ); // Comments
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
		return 'http://{s}.tile.cloudmade.com/BC9A493B41014CAABB98F0471D759707/75872/256/{z}/{x}/{y}.png';

	return 'http://{s}.tile.cloudmade.com/BC9A493B41014CAABB98F0471D759707/75869/256/{z}/{x}/{y}.png';
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
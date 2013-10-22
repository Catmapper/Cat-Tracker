<?php

/**
Plugin Name: Redirect users
Plugin URI: https://github.com/jkudish/Cat-Tracker
Description: Redirect users from the front-end of sub-sites to either the internal map or the main site depending on if they are logged in or not
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

function catmapper_redirect_users() {
	if ( is_main_site() || is_network_admin() || is_admin() )
		return;

	if ( is_user_logged_in() ) {
		wp_redirect( add_query_arg( 'page', 'internal-map', get_admin_url() . 'admin.php' ) );
		exit;
	} else {
		global $current_site;
		wp_redirect( get_site_url( $current_site->blog_id ) );
		exit;
	}
}
add_action( 'template_redirect', 'catmapper_redirect_users' );
<?php

if ( ! defined('WP_CLI') || ! WP_CLI )
	return;

/**
 * Implements Cat Mapper WP CLI commands
 *
 * @package wp-cli
 * @subpackage commands/community
 */
class Cat_Mapper_Command extends WP_CLI_Command {

	/**
	 * flushes marker and blog ID caches
	 */
	function flushcache() {

		WP_CLI::line( "Going to flush Cat Mapper cache." );
		WP_CLI::line( "Generating community blog IDs list..." );
		$blog_ids = catmapper_refresh_all_blog_ids();
		foreach( $blog_ids as $blog_id ) {
			switch_to_blog( $blog_id );
			WP_CLI::line( "Flushing marker cache for " . get_bloginfo() . "..." );
			Cat_Tracker::instance()->_flush_all_markers_cache();
			restore_current_blog();
		}

		WP_CLI::success( "Done flashing cache." );

	}

	/**
	 * flushes marker cache for specified blog
	 */
	function flushmarkercache() {
		WP_CLI::line( "Flushing marker cache for " . get_bloginfo() . "..." );
		Cat_Tracker::instance()->_flush_all_markers_cache();
		WP_CLI::success( "Done flashing cache." );
	}

	/**
	 * Delete bad data
	 */
	function deletebaddata() {

		WP_CLI::line( "Going to delete sightings with bad data." );
		WP_CLI::line( "Generating community blog IDs list..." );
		$blog_ids = catmapper_refresh_all_blog_ids();
		foreach( $blog_ids as $blog_id ) {
			switch_to_blog( $blog_id );
			WP_CLI::line( "Checking data for " . get_bloginfo() . "..." );
			$q = new WP_Query( array(
				'post_type' => 'cat_tracker_marker',
				'posts_per_page' => -1,
				'meta_query' => array(
					array(
						'key' => 'cat_tracker_sighting_date',
						'compare' => 'NOT EXISTS',
						'value' => '',
					)
				)
			) );
			if ( ! $q->have_posts() ) {
				WP_CLI::line( get_bloginfo() . " didn't have any bad data." );
				restore_current_blog();
				continue;
			}

			foreach( $q->posts as $post ) {
				WP_CLI::line( 'would delete marker ID #' . $post->ID . ' with animal ID #' . get_post_meta( $post->ID, 'cat_tracker_animal_id', true ) . ', sighting date of: "' . get_post_meta( $post->ID, 'cat_tracker_sighting_date', true ) . '" and date of: "' . $post->post_date_gmt . '"' );
			}

			global $wpdb;
			$oldest_sighting_date = $wpdb->get_var( "SELECT max(cast(meta_value as unsigned)) FROM $wpdb->postmeta WHERE meta_key='cat_tracker_sighting_date'" );
			WP_CLI::line( 'the oldest marker left for ' . get_bloginfo() . ' has a sighting date of: ' . date( 'c', $oldest_sighting_date ) );

			restore_current_blog();
		}

		WP_CLI::success( "Done flashing cache." );

	}

	/**
	 * Update roles for each community
	 */
	function updateroles() {

		WP_CLI::line( "Going to update user roles/permissions for each community." );
		$blog_ids = catmapper_refresh_all_blog_ids();
		foreach( $blog_ids as $blog_id ) {
			switch_to_blog( $blog_id );
			WP_CLI::line( "Updating user roles for " . get_bloginfo() . "..." );
			catmapper_roles_and_permissions();
			restore_current_blog();
		}

		WP_CLI::success( "Done updating roles." );

	}

	/**
	 * Add superadmins as admins to existing communities
	 */
	function superadmins() {
		WP_CLI::line( 'Going to add all super admins as admins to all communities' );
		$super_admins = get_super_admins();
		$blog_ids = catmapper_refresh_all_blog_ids();
		foreach( $super_admins as $super_admin ) {
			$user = get_user_by( 'login', $super_admin );

			if ( empty( $user ) || is_wp_error( $user ) )
				continue;

			WP_CLI::line( sprintf( "\nChecking %s...", $user->user_login ) );
			foreach( $blog_ids as $_blog_id ) {
				switch_to_blog( $_blog_id );

				if ( is_user_member_of_blog( $user->ID, $_blog_id ) ) {
					WP_CLI::line( sprintf( '%s is already a member of %s', $user->user_login, get_bloginfo() ) );
				} else {
					add_user_to_blog( $_blog_id, $user->ID, 'administrator' );
					if ( is_user_member_of_blog( $user->ID, $_blog_id ) ) {
						WP_CLI::line( sprintf( '%s has been added to %s', $user->user_login, get_bloginfo() ) );
					} else {
						WP_CLI::error( sprintf( '%s has not been added to %s', $user->user_login, get_bloginfo() ) );
					}
				}

				restore_current_blog();
			}
		}

	}

	/**
	 * Update type for each sighting to SPCA intake sighting type
	 */
	function updatetype() {
		$sightings = new WP_Query( array(
			'post_type' => 'cat_tracker_marker',
			'posts_per_page' => -1,
		) );
		$sighting_type = get_term_by( 'name', 'SPCA Intake Cats', 'cat_tracker_marker_type' );
		if ( ! $sightings->have_posts() || empty( $sighting_type ) || is_wp_error( $sighting_type ) ) {
			WP_CLI::error( "Error occurred..." );
			return;
		}

		while( $sightings->have_posts() ) {
			$sightings->the_post();
			WP_CLI::line( "Updating sighting type for " . get_the_title() . " (ID #" . get_the_ID() . ")" );
			add_post_meta( get_the_ID(), 'cat_tracker_marker_type', absint( $sighting_type->term_id ), true );
			wp_set_object_terms( get_the_ID(), absint( $sighting_type->term_id ), 'cat_tracker_marker_type' );
		}
	}

}

WP_CLI::add_command( 'catmap', 'Cat_Mapper_Command' );
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
			$this->_stop_the_insanity();
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

		WP_CLI::line( "Going to delete sightings which were imported after January 26, 2013 and contain invalid date." );
		$blog_ids = catmapper_refresh_all_blog_ids();
		foreach( $blog_ids as $blog_id ) {
			$count = 0;
			switch_to_blog( $blog_id );
			$q = new WP_Query( array(
				'post_type' => 'cat_tracker_marker',
				'posts_per_page' => -1,
				'meta_query' => array(
					array(
						'key' => 'cat_tracker_sighting_date',
						'compare' => 'NOT EXISTS',
						'value' => '',
					)
				),
				'date_query' => array(
					array(
						'after' => 'January 26, 2013',
					)
				),
			) );
			if ( ! $q->have_posts() ) {
				WP_CLI::line( get_bloginfo() . " didn't have any bad data." );
				restore_current_blog();
				continue;
			}

			foreach( $q->posts as $post ) {
				wp_delete_post( $post->ID );
				$count++;
			}

			$old_q = new WP_Query( array(
				'post_type' => 'cat_tracker_marker',
				'posts_per_page' => 1,
				'meta_key' => 'cat_tracker_sighting_date',
				'orderby' => 'meta_value_num',
				'order' => 'DESC',
				'tax_query' => array( array(
					'taxonomy' => 'cat_tracker_marker_type',
					'field' => 'slug',
					'terms' => 'spca-intake-cats',
				) )
			) );
			$marker_count = wp_count_posts( 'cat_tracker_marker' );
			WP_CLI::line( get_bloginfo() . ' | Deleted ' . $count . ' sightings with empty dates | Total sightings left: ' . $marker_count->publish . ' | INTAKE sightings left: ' . $old_q->found_posts );
			if ( $old_q->have_posts() ) {
				WP_CLI::line( 'The oldest INTAKE sighting left for ' . get_bloginfo() . ' has a sighting date of: ' . date( 'Y-m-d', get_post_meta( $old_q->posts[0]->ID, 'cat_tracker_sighting_date', true ) ) );
			}

			Cat_Tracker::instance()->_flush_all_markers_cache();
			$this->_stop_the_insanity();
			restore_current_blog();
		}

		WP_CLI::success( "Done with cleanup." );

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

	function _stop_the_insanity() {
		global $wpdb, $wp_object_cache;

		$wpdb->queries = array(); // or define( 'WP_IMPORTING', true );

		if ( !is_object( $wp_object_cache ) )
			return;

		$wp_object_cache->group_ops = array();
		$wp_object_cache->stats = array();
		$wp_object_cache->memcache_debug = array();
		$wp_object_cache->cache = array();
	}

}

WP_CLI::add_command( 'catmap', 'Cat_Mapper_Command' );
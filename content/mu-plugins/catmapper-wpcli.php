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
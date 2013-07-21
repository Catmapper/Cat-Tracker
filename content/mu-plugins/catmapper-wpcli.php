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

}

WP_CLI::add_command( 'catmap', 'Cat_Mapper_Command' );
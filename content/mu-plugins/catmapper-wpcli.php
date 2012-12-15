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

		WP_CLI::line( "Going to flush Cat Mapper cache" );
		WP_CLI::line( "Generating community blog IDs list..." );
		$blog_ids = catmapper_refresh_all_blog_ids();
		foreach( $blog_ids as $blog_id ) {
			switch_to_blog( $blog_id );
			WP_CLI::line( "Flushing marker cache for " . get_bloginfo() . "..." );
			do_action( 'cat_tracker_flush_all_markers_cache' );
			restore_current_blog();
		}

		WP_CLI::success( "Done flashing cache." );

	}

}

WP_CLI::add_command( 'catmap', 'Cat_Mapper_Command' );
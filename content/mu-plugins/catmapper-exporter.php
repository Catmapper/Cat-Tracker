<?php

/**
Plugin Name: Cat Mapper Exporter
Plugin URI: https://github.com/jkudish/Cat-Tracker
Description: Exporter for the Cat Tracking Software
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

class Cat_Mapper_Exporter {

	/**
	 * @var $instance, the one true Cat Mapper Exporter
	 */
	private static $instance;


	/**
	 * Singleton instance
	 *
	 * @since 1.0
	 * @return object $instance the singleton instance of this class
	 */
	public static function instance() {
		if ( isset( self::$instance ) )
			return self::$instance;

		self::$instance = new Cat_Mapper_Exporter;
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
		add_action( 'admin_menu', array( $this, 'register_submenu' ) );
	}

	/**
	 * register submenu item which will appear under "Sightings"
	 *
	 * @since 1.0
	 * @return void
	 */
	public function register_submenu() {
		add_submenu_page( 'edit.php?post_type=cat_tracker_marker', __( 'Exporter', 'cat-tracker' ), __( 'Export', 'cat-tracker' ), 'export_markers', 'cat_tracker_exporter', array( $this, 'admin_page' ) );
	}

	/**
	 * determine if the current request is an import request
	 *
	 * @since 1.0
	 * @return bool importing or not
	 */
	public function is_exporting() {
		return ( ! empty( $_POST ) && ! empty( $_POST['catmapper-export'] ) && ! empty( $_POST['_wpnonce'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'catmapper-export' ) );
	}

	/**
	 * output the admin page
	 *
	 * @since 1.0
	 * @return void
	 */
	public function admin_page() {
		echo '<div class="wrap">';
		screen_icon();
		echo '<h2>' . __( 'Export sightings' ) . '</h2>';
		if ( $this->is_exporting() )
			$this->handle_export();
		else
			$this->export_form();
		echo '</div>';
	}

	/**
	 * output the export form
	 *
	 * @since 1.0
	 * @return void
	 */
	public function export_form() {
		?>
		<form method="post">
			<?php wp_nonce_field( 'catmapper-export' ); ?>
			<input type="hidden" name="catmapper-export" value="1">
			<p><?php _e( 'After you click Export, the export process will begin. It is normal for this process to take a long amount of time based on the number of sightings in this community. DO NOT REFRESH this page.', 'cat-tracker' ); ?></p>
			<?php submit_button( __( 'Export', 'cat-tracker' ), 'button', 'save' ); ?>
		</form>
	<?php
	}

	/**
	 * process the export
	 *
	 * @since 1.0
	 * @return void
	 */
	public function handle_export() {

		$i = 0;

		set_time_limit(0);

		$community_name = sanitize_key( get_bloginfo() );
		$filename = sprintf( 'catmapper-export-%s-%s.csv', $community_name, date( 'c' ) );
		$file_path = WP_CONTENT_DIR . '/exports/' . $filename;
		$fp = fopen( $file_path, 'w' );
		$export_file_url = WP_CONTENT_URL . '/exports/' . $filename;

		$fields = array(
			'Animal ID',
			'Number of Cats',
			'Sighting Date',
			'Sighting Type',
			'Description',
			'Neuter Status',
			'Address',
			'Latitude',
			'Longitude',
			'Intake Type',
			'Intake Source',
			'Breed',
			'Color',
			'Gender',
			'Age group',
			'Incoming neuter status (intake)',
			'Current neuter status (intake)',
			'Name of reporter',
			'Email of reporter',
			'Tel of reporter',
			'Reporter would like to be contacted regarding spay/neuter programs',
		);
		fputcsv( $fp, $fields );

		$all_the_markers = new WP_Query( array(
			'post_type' => Cat_Tracker::MARKER_POST_TYPE,
			'posts_per_page' => -1,
		) );

		if ( $all_the_markers->have_posts() ) {
			while ( $all_the_markers->have_posts() ) {
				$all_the_markers->the_post();
				$fields = array(
					Cat_Tracker::instance()->get_marker_animal_id(),
					Cat_Tracker::instance()->get_marker_number_of_cats(),
					Cat_Tracker::instance()->get_marker_date(),
					Cat_Tracker::instance()->get_marker_type( get_the_ID() ),
					Cat_Tracker::instance()->get_marker_description(),
					Cat_Tracker::instance()->get_marker_neuter_status(),
					Cat_Tracker::instance()->get_marker_address(),
					Cat_Tracker::instance()->get_marker_latitude(),
					Cat_Tracker::instance()->get_marker_longitude(),
					Cat_Tracker::instance()->get_marker_intake_type(),
					Cat_Tracker::instance()->get_marker_source(),
					Cat_Tracker::instance()->get_marker_breed(),
					Cat_Tracker::instance()->get_marker_color(),
					Cat_Tracker::instance()->get_marker_gender(),
					Cat_Tracker::instance()->get_marker_age_group(),
					Cat_Tracker::instance()->get_marker_incoming_spay_neuter_status(),
					Cat_Tracker::instance()->get_marker_current_spay_neuter_status(),
					Cat_Tracker::instance()->get_marker_name_of_reporter(),
					Cat_Tracker::instance()->get_marker_email_of_reporter(),
					Cat_Tracker::instance()->get_marker_telephone_of_reporter(),
					Cat_Tracker::instance()->get_marker_contact_reporter(),
				);
				fputcsv( $fp, $fields );
				$i++;
			}
		}

		fclose($fp);

		printf( "<br>All done, exported %d sightings. <a href='%s'>click here</a> to download the csv file.</a>", $i, esc_url( $export_file_url ) );

	}

}

Cat_Mapper_Exporter::instance();
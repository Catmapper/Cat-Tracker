<?php

/**
Plugin Name: Cat Mapper Importer
Plugin URI: https://github.com/jkudish/Cat-Tracker
Description: BCSPCA Importer for the Cat Tracking Software
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

class Cat_Mapper_Importer {

	/**
	 * @var the one true Cat Mapper Importer
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
			return;

		self::$instance = new Cat_Mapper_Importer;
		self::$instance->run_hooks();
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
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_filter( 'attachment_fields_to_edit', array( $this, 'attachment_fields_to_edit' ), 20, 2 );
	}

	/**
	 * register submenu item which will appear under "Sightings"
	 *
	 * @since 1.0
	 * @return void
	 */
	public function register_submenu() {
		add_submenu_page( 'edit.php?post_type=cat_tracker_marker', __( 'Import', 'cat-tracker' ), __( 'Import', 'cat-tracker' ), 'upload_files', 'cat_tracker_importer', array( $this, 'admin_page' ) );
	}

	/**
	 * determine if the current request is an import request
	 *
	 * @since 1.0
	 * @return void
	 */
	public function is_importing() {
		return ( ! empty( $_POST ) && ! empty( $_POST['save'] ) && ! empty( $_POST['_wpnonce'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'media-form' ) && ! empty( $_POST['attachments'] ) );
	}

	/**
	 * enqueue scripts on the importer page
	 *
	 * @since 1.0
	 * @return void
	 */
	public function enqueue_scripts() {
		if ( 'cat_tracker_marker_page_cat_tracker_importer' != get_current_screen()->id )
			return;

		wp_enqueue_script( 'plupload-handlers' );
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
			echo '<h2>' . __( 'Import sightings/cases' ) . '</h2>';
			if ( $this->is_importing() )
				$this->handle_import();
			else
				$this->upload_form();
		echo '</div>';
	}

	/**
	 * filter the plupload settings for the importer
	 *
	 * @since 1.0
	 * @see http://www.plupload.com/documentation.php
	 * @param (array) $plupload_settings plupload's initial settings
	 * @return (array) $plupload_settings plupload's filtered settings
	 */
	public function plupload_init( $plupload_settings ) {
		$plupload_settings['unique_names'] = true;
		$plupload_settings['filters'] = array( array( 'title' => __( 'CSV Files' ), 'extensions' => 'csv') );
		return $plupload_settings;
	}

	/**
	 * filter the fields to show for the uploaded csv's
	 *
	 * @since 1.0
	 * @param (array) $form_fields initial form fields
	 * @param (object) $post the attachment post object
	 * @return (array) $form_fields filtered form fields
	 */
	public function attachment_fields_to_edit( $form_fields, $post ) {
		if ( admin_url( 'edit.php?post_type=cat_tracker_marker&page=cat_tracker_importer' ) != $_SERVER['HTTP_REFERER'] || empty( $_REQUEST['fetch'] ) || 2 != $_REQUEST['fetch'] || 'text/csv' != $post->post_mime_type )
			return $form_fields;

		$unset_fields = array( 'post_title', 'image_alt', 'post_excerpt', 'post_content', 'menu_order', 'url' );
		foreach( $unset_fields as $field )
			unset( $form_fields[$field] );

		return $form_fields;
	}

	/**
	 * output the upload form
	 *
	 * @since 1.0
	 * @see media_upload_form()
	 * @return void
	 */
	public function upload_form() {
		remove_action( 'post-upload-ui', 'media_upload_text_after', 5 );
		add_action( 'post-upload-ui', array( $this, 'media_upload_text_after' ) );
		add_filter( 'plupload_init', array( $this, 'plupload_init' ) );
		?>
		<form enctype="multipart/form-data" method="post" class="media-upload-form type-form validate" id="file-form">
		<?php
		media_upload_form();
		wp_nonce_field( 'media-form' );
		?>
		<div id="media-items" class="hide-if-no-js"></div>
		<script type="text/javascript">
		jQuery(function($){
			var preloaded = $(".media-item.preloaded");
			if ( preloaded.length > 0 ) {
				preloaded.each(function(){prepareMediaItem({id:this.id.replace(/[^0-9]/g, '')},'');});
			}
			updateMediaForm();
			post_id = 0;
			shortform = 0;
		});
		</script>
		<?php
		$maps = get_posts( array( 'post_type' => Cat_Tracker::MAP_POST_TYPE, 'posts_per_page' => -1, 'fields' => 'ids' ) );
		if ( ! empty( $maps ) ) :
		?>
			<?php submit_button( __( 'Import', 'cat-tracker' ), 'button savebutton hidden', 'save' ); ?>
		<?php else : ?>
			<p><?php _e( 'You need to create at least one map before you can import sightings.', 'cat-tracker' ); ?></p>
		<?php endif; ?>
		</form>
		<?php
	}

	/**
	 * output a file type notice below the custom uploader
	 *
	 * @since 1.0
	 * @return void
	 */
	public function media_upload_text_after() {
		echo '<span class="after-file-upload">' . __( '<strong>Note</strong>: you may only select .csv files for import.', 'cat-tracker' ) . '</span>';
	}

	/**
	 * process the import
	 *
	 * @since 1.0
	 * @return void
	 */
	public function handle_import() {
		echo '<p>' . __( 'importing...', 'cat-tracker' ) . '</p>';
		$map_id = get_option( 'catmapper_community_main_map_id' );
		if ( empty( $map_id ) ) {
			echo '<p>' . __( 'There is no valid map ID set for this site. Aborting...', 'cat-tracker' ) . '</p>';
			return;
		}

		foreach ( $_REQUEST['attachments'] as $attachment_id => $attachment_data ) {
			$attachment = get_post( $attachment_id );

			if ( empty( $attachment_data['url'] ) ) {
				printf( '<p>' . __( 'Attachment ID #%d does not have a valid URL and could not be imported', 'cat-tracker' ) . '</p>', $attachment_id );
				continue;
			}

			if ( 'text/csv' != $attachment->post_mime_type ) {
				printf( '<p>' . __( '%s is not a valid csv file and could not be imported', 'cat-tracker' ) . '</p>', esc_url( $attachment_data['url'] ) );
				continue;
			}

			$open_file = fopen( $attachment_data['url'], "r" );
			if ( false === $open_file ) {
				printf( '<p>' . __( '%s could not be opened and as a result and could not be imported', 'cat-tracker' ) . '</p>', esc_url( $attachment_data['url'] ) );
				continue;
			}

			$data = fgetcsv( $open_file );
			if ( false === $data ) {
				printf( '<p>' . __( '%s is not a valid csv file and could not be imported', 'cat-tracker' ) . '</p>', esc_url( $attachment_data['url'] ) );
				fclose( $open_file );
				continue;
			}

			$row_num = 1;
			$start_importing = false;
			$excluded_sources = apply_filters( 'cat_mapper_importer_excluded_sources', array( 'return', 'owner surrender', 'returns', 'owner surrenders', 'humane officer surrendered', 'humane officer  surrendered', 'humane officer surrender', 'humane officer  surrender', 'humane officer seized', 'humane officer  seized' ) );
			$type = 'cat';
			$count_imported = $dupe = $count_excluded = $count_no_address = $count_bad_address = 0;
			while ( $row_data = fgetcsv( $open_file ) ) {
        $row_num++;

        if ( empty( $row_data[0] ) || empty( $row_data[7] ) )
        	continue;

        $animal_id = $row_data[0];
        $date = $row_data[2];
        $source = $row_data[4];
        $breed = $row_data[12];
        $color = $row_data[13];
        $age_group = $row_data[16];
        $gender = $row_data[17];
        $incoming_spay_neuter_status = $row_data[17];
        $current_spay_neuter_status = $row_data[18];
        $address = $row_data[23];

        if ( 'Type' == $row_data[0] && 'Kitten' == $row_data[1] )
					$type = 'kitten';

				$_type = ( 'kitten' == $type ) ? 'bc-spca-unowned-intake-kitten' : 'bc-spca-unowned-intake-cat';
				$type_object = get_term_by( 'slug', $_type, Cat_Tracker::MARKER_TAXONOMY );
				if ( ! is_wp_error( $type_object ) && is_object( $type_object ) )
					$type_id = absint( $type_object->term_id );

        if ( 'Animal ID' == $animal_id && 'L/F Address' == $address ) {
        	$start_importing = true;
        	continue;
        }

        if ( ! $start_importing )
        	continue;

      	$_already_exists = new WP_Query();
      	$_already_exists->query( array(
      		'post_type' => Cat_Tracker::MARKER_POST_TYPE,
        	'meta_key' => Cat_Tracker::META_PREFIX . 'animal_id',
        	'meta_value' => $animal_id,
        	'fields' => 'ids',
        ) );

        if ( $_already_exists->have_posts() ) {
        	$dupe++;
					printf( '<p>' . __( 'Animal ID #%d has already been imported and is being to be skipped from this import.' ) . '</p>', $animal_id );
        	continue;
        }

        // exclude returns & owner surrenders
        if ( in_array( strtolower( $source ), $excluded_sources ) ) {
        	$count_excluded++;
        	continue;
        }

        if ( empty( $address ) ) {
        	$count_no_address++;
        	continue;
        }

        if ( empty( $breed ) )
        	$breed = 'unknown';

				$location = Cat_Tracker_Geocode::get_location_by_address( $address );

				if ( is_wp_error( $location ) ) {
					$count_bad_address++;
					printf( '<p>' . __( 'Animal ID #%d returned an error while looking up its location. The following error occurred: %s. You may want to adjust the csv and try again.' ) . '</p>', $animal_id, $location->get_error_message() );
					continue;
				}

				if ( 'stray' == strtolower( $source ) ) {
					$description = sprintf( __( "%s %s.\nColor: %s.\nGender: %s.\nBreed: %s", 'cat-tracker' ), ucfirst( $source ), $type, $color, $gender, $breed );
				} else {
					$description = sprintf( __( "%s.\nColor: %s.\nGender: %s.\nBreed: %s", 'cat-tracker' ), ucfirst( $type ), $color, $gender, $breed );
				}

				$sighting_id = wp_insert_post( array(
						'post_title' => sprintf( _x( 'Community cat sighting from imported file at %s', 'Post title for imported sightings with the current timestamp/date', 'cat_tracker' ), date( 'Y-m-d g:i:a' ) ),
						'post_status' => 'publish',
						'post_type' => Cat_Tracker::MARKER_POST_TYPE,
						'to_ping' => false,
				) );

				if ( empty( $sighting_id ) || is_wp_error( $sighting_id ) ) {
	 				printf( '<p>' . __( 'Could not insert Animal ID #%d from attachment ID #%d.' ) . '</p>', $animal_id, $attachment_id );
	 				continue;
				}

				add_post_meta( $sighting_id, Cat_Tracker::META_PREFIX . 'description', $description, true );
				add_post_meta( $sighting_id, Cat_Tracker::META_PREFIX . 'animal_id', $animal_id, true );
				add_post_meta( $sighting_id, Cat_Tracker::META_PREFIX . 'source', $source, true );
				add_post_meta( $sighting_id, Cat_Tracker::META_PREFIX . 'breed', $breed, true );
				add_post_meta( $sighting_id, Cat_Tracker::META_PREFIX . 'color', $color, true );
				add_post_meta( $sighting_id, Cat_Tracker::META_PREFIX . 'gender', $gender, true );
				add_post_meta( $sighting_id, Cat_Tracker::META_PREFIX . 'age_group', $age_group, true );

				if ( ! empty( $date ) ) {
					$date = strtotime( $date );
					if ( ! empty( $date ) ) {
						add_post_meta( $sighting_id, Cat_Tracker::META_PREFIX . 'sighting_date', $date, true );
						if ( $date > strtotime( 'July 2011' ) ) {
							add_post_meta( $sighting_id, Cat_Tracker::META_PREFIX . 'incoming_spay_neuter_status', $incoming_spay_neuter_status, true );
							add_post_meta( $sighting_id, Cat_Tracker::META_PREFIX . 'current_spay_neuter_status', $current_spay_neuter_status, true );
						}
					}
				}

				add_post_meta( $sighting_id, Cat_Tracker::META_PREFIX . 'name_of_reporter', 'BC SPCA', true );
				add_post_meta( $sighting_id, Cat_Tracker::META_PREFIX . 'latitude', $location['latitude'], true );
				add_post_meta( $sighting_id, Cat_Tracker::META_PREFIX . 'longitude', $location['longitude'], true );
				add_post_meta( $sighting_id, Cat_Tracker::META_PREFIX . 'address', $location['formatted_address'], true );
				add_post_meta( $sighting_id, Cat_Tracker::META_PREFIX . 'confidence_level', $location['confidence'], true );
				add_post_meta( $sighting_id, Cat_Tracker::META_PREFIX . 'ip_address_of_reporter', $_SERVER['REMOTE_ADDR'], true );
				add_post_meta( $sighting_id, Cat_Tracker::META_PREFIX . 'browser_info_of_reporter', $_SERVER['HTTP_USER_AGENT'] , true );
				add_post_meta( $sighting_id, Cat_Tracker::META_PREFIX . 'imported_on', time(), true );
				add_post_meta( $sighting_id, Cat_Tracker::META_PREFIX . 'map', $map_id, true );

				// insert sighting type
				add_post_meta( $sighting_id, Cat_Tracker::MARKER_TAXONOMY, $type_id, true );
				wp_set_object_terms( $sighting_id, $type_id, Cat_Tracker::MARKER_TAXONOMY );
				printf( '<p>' . __( 'Animal ID #%d succesfully imported.' ) . '</p>', $animal_id );
				$count_imported++;
			}

			fclose( $open_file );
			wp_delete_attachment( $attachment_id );
			printf( '<p class="cat-mapper-import-result">' . __( '%d sightings succesfully imported. %d were duplicate animal IDs. %d sightings excluded because of their source, %d sightings not imported because they did not have an address at all and %d sightings not imported because they did not have a valid address.' ) . '</p>', $count_imported, $dupe, $count_excluded, $count_no_address, $count_bad_address );

		}
	}


}

Cat_Mapper_Importer::instance();
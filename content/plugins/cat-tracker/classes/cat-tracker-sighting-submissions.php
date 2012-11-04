<?php
/**
 * @package Cat_Tracker
 * @author Joachim Kudish
 * @version 1.0
 *
 * This is a utility class for
 * processing sighting submissions in the cat tracking software
*/

class Cat_Tracker_Sighting_Submission {

	/**
	 * @var (array) $errors WP_Error objects of the error that occur during submission
	 */
	var $errors;

	/**
	 * @var (array) $submission_fields the final/sanitized version of the submitted data to be insterted into the database
	 */
	var $submission_fields;

	/**
	 * @var (bool) $did_insert the sighting insertion occurred
	 */
	var $did_insert;

	/**
	 * @var (bool) $did_not_insert the sighting insertion did not occur
	 */
	var $did_not_insert;

	/**
	 * process sighting submissions
	 * architected to work with AJAX if integrated later
	 * this function should be run before headers are sent
	 *
	 * @since 1.0
	 * @param bool $redirect_on_success wether to redirect the user to the success page on completion
	 * @return void
	 */
	public function process( $redirect_on_success = true ) {

		do_action( 'cat_tracker_process_submission', $_POST );

		if ( ! isset( $_POST['cat-tracker-submisison-submit'] ) )
			return;

		if ( headers_sent() || empty( $_POST['cat_tracker_confirm_submission'] ) || ! wp_verify_nonce( $_POST['cat_tracker_confirm_submission'], 'cat_tracker_confirm_submission' ) )
			wp_die( __( 'An unexpected error has occurred. Please try again.', 'cat-tracker' ) );

		$this->errors = array();

		if ( empty( $_POST['cat-tracker-submitter-name'] ) ) {
			$this->errors[] = new WP_Error( 'no-name', __( 'Please provide your name.', 'cat-tracker' ) );
		} else {
			$this->submission_fields['name'] = wp_kses( $_POST['cat-tracker-submitter-name'], array() );
		}

		// TODO: regex for phone?
		if ( ! empty( $_POST['cat-tracker-submitter-phone'] ) ) {
			$this->submission_fields['phone'] = wp_kses( $_POST['cat-tracker-submitter-phone'], array() );
		}

		// TODO: filter_var? or other regex for email?
		if ( ! empty( $_POST['cat-tracker-submitter-email'] ) ) {
			$this->submission_fields['email'] = wp_kses( $_POST['cat-tracker-submitter-email'], array() );
		}

		if ( empty( $_POST['cat-tracker-submission-date'] ) ) {
			$this->errors[] = new WP_Error( 'no-date', __( 'Please provide a date for the sighting.', 'cat-tracker' ) );
		} else {
			// TODO: verify the correct return values here
			$this->submission_fields['date'] = strtotime( $_POST['cat-tracker-submission-date'] );
			if ( false == $this->submission_fields['date'] ) // TODO: || DEFAULT_DATE == $date & check range also
				$this->errors[] = new WP_Error( 'no-name', __( 'Please provide a valid date.', 'cat-tracker' ) );
		}

		if ( empty( $_POST['cat-tracker-submisison-type'] ) ) {
			$this->errors[] = new WP_Error( 'no-type', __( 'Please provide the type of sighting.', 'cat-tracker' ) );
		} else {
			$valid_types = get_terms( Cat_Tracker::MARKER_TAXONOMY, array( 'hide_empty' => false, 'fields' => 'ids' ) );
			if ( ! in_array( $_POST['cat-tracker-submisison-type'], $valid_types ) ) {
				$this->errors[] = new WP_Error( 'invalid-type', __( 'Please provide a valid type of sighting.', 'cat-tracker' ) );
			} else {
				$this->submission_fields['type_id'] = absint( $_POST['cat-tracker-submisison-type'] );
			}
		}

		if ( empty( $_POST['cat-tracker-submisison-description'] ) ) {
			$this->errors[] = new WP_Error( 'no-description', __( 'Please provide a description of the situation.', 'cat-tracker' ) );
		} else {
			$this->submission_fields['description'] = wp_kses( $_POST['cat-tracker-submisison-description'], array() );
		}

		if ( empty( $_POST['cat-tracker-submisison-latitude'] ) || empty( $_POST['cat-tracker-submisison-longitude'] ) ) {
			$this->errors[] = new WP_Error( 'invalid-marker', __( 'Please provide the location of the sighting using the map below', 'cat-tracker' ) );
		} else {
			$this->submission_fields['latitude'] = (float) $_POST['cat-tracker-submisison-latitude'];
			$this->submission_fields['longitude'] = (float) $_POST['cat-tracker-submisison-longitude'];
		}

		$this->errors = apply_filters( 'cat_tracker_submission_errors', $this->errors, $_POST, $this->submission_fields );
		if ( empty( $this->errors ) ) {
			$this->insert_submission( $redirect_on_success );
		} else {
			$this->did_not_insert = true;
		}

	}

	/**
	 * insert submission after initial processing
	 *
	 * @since 1.0
	 * @param bool $redirect_on_success wether to redirect the user to the success page on completion
	 * @return void
	 */
	public function insert_submission( $redirect_on_success = true ) {

		if ( ! empty( $this->errors ) )
			return;

		do_action( 'cat_tracker_inserting_submitted_sighting', $_POST, $this->submission_fields );
		$sighting_id = wp_insert_post( array(
				'post_title' => sprintf( _x( 'Cat Mapper Submission from %s &mdash; %s', 'Post title for submissions with the current timestamp/date', 'cat_tracker' ), $this->submission_fields['name'], date( 'Y-m-d g:i:a' ) ),
				'post_status' => 'pending',
				'post_type' => Cat_Tracker::MARKER_POST_TYPE,
				'to_ping' => false,
		) );

		if ( empty( $sighting_id ) || is_wp_error( $sighting_id ) ) {
		 $this->errors[] = new WP_Error( 'no-insert', __( 'Could not insert the sighting. Please try again.' ) );
		 continue;
		}

		// insert mandatory meta
		add_post_meta( $sighting_id, Cat_Tracker::META_PREFIX . 'description', $this->submission_fields['description'], true );
		add_post_meta( $sighting_id, Cat_Tracker::META_PREFIX . 'sighting_date', $this->submission_fields['date'], true );
		add_post_meta( $sighting_id, Cat_Tracker::META_PREFIX . 'name_of_reporter', $this->submission_fields['name'], true );

		// Map data
		add_post_meta( $sighting_id, Cat_Tracker::META_PREFIX . 'latitude', $this->submission_fields['latitude'], true );
		add_post_meta( $sighting_id, Cat_Tracker::META_PREFIX . 'longitude', $this->submission_fields['longitude'], true );

		// insert programmatic meta
		add_post_meta( $sighting_id, Cat_Tracker::META_PREFIX . 'ip_address_of_reporter', $_SERVER['REMOTE_ADDR'], true );
		add_post_meta( $sighting_id, Cat_Tracker::META_PREFIX . 'browser_info_of_reporter', $_SERVER['HTTP_USER_AGENT'] , true );
		add_post_meta( $sighting_id, Cat_Tracker::META_PREFIX . 'map', get_the_ID(), true );

		// insert sighting type
		add_post_meta( $sighting_id, Cat_Tracker::MARKER_TAXONOMY, $this->submission_fields['type_id'], true );
		wp_set_object_terms( $sighting_id, $this->submission_fields['type_id'], Cat_Tracker::MARKER_TAXONOMY );

		// insert optional metadata
		if ( ! empty( $this->submission_fields['email'] ) )
			add_post_meta( $sighting_id, Cat_Tracker::META_PREFIX . 'email_of_reporter', $this->submission_fields['email'], true );

		if ( ! empty( $this->submission_fields['phone'] ) )
			add_post_meta( $sighting_id, Cat_Tracker::META_PREFIX . 'telephone_of_reporter', $this->submission_fields['phone'], true );

		$this->did_insert = true;

		do_action( 'cat_tracker_inserted_submitted_sighting', $_POST, $this->submission_fields );

		if ( apply_filters( 'cat_tracker_inserted_submitted_sighting_redirect_on_success', $redirect_on_success, $_POST, $this->submission_fields ) )
			wp_safe_redirect( esc_url( wp_nonce_url( get_permalink( get_the_ID() ), 'sighting_inserted' ) ) );

	}

	/**
	 * get the markup for the errors that have occurred with
	 * the current submission
	 *
	 * @since 1.0
	 * @param bool $echo wether to echo the list of errors or return it
	 * @return string $return the errors, empty if no errors
	 */
	public function get_errors( $echo = false ) {
		if ( empty( $this->errors ) )
			return;

		$return = '<div id="cat-tracker-submission-errors">';
		if ( count( $this->errors ) > 1 ) {
			$return .= '<p>' . __( 'The following errors have occurred. Please correct the errors and try again.', 'cat-tracker' ) . '</p>';
			$return .= '<ul>';
			foreach ( $this->errors as $error ) {
				$return .= '<li>' . esc_html( $error->get_error_message() ) . '</li>';
			}
			$return .= '</ul>';
		} else {
			$return .= '<p>' . esc_html( $this->errors[0]->get_error_message() ) . '</p>';
		}
		$return .= '</div>';

		if ( $echo )
			echo $return;

		return $return;
	}

}
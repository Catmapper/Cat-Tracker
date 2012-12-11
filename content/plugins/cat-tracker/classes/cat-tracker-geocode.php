<?php
/**
 * @package Cat_Tracker
 * @author Joachim Kudish
 * @version 1.0
 *
 * This is a utility class for
 * interacting with the Bing maps API
*/

class Cat_Tracker_Geocode {

	/**
	 * the base URL to use to communicate with the bing API
	 */
	const BING_BASE_URL = 'http://dev.virtualearth.net/REST/v1/Locations';

	/**
	 * get a location based on an address
	 *
	 * @since 1.0
	 * @uses Cat_Tracker_Geocode::request()
	 * @uses Cat_Tracker_Geocode::contains_at_least_one_address()
	 * @uses Cat_Tracker_Geocode::get_coordinates_for_first_address_in_response()
	 * @param (string) $address the address to lookup
	 * @return WP_Error|array error on failure or array of latitude + longitude on success
	 */
	static function get_location_by_address( $address ) {
		$api_response = self::request( array( 'q' => (string) $address ) );
		if ( is_wp_error( $api_response ) )
			return $api_response;

		$response_data = json_decode( $api_response );
		if ( ! is_object( $response_data ) )
			return new WP_Error( 'invalid-response', __( 'Invalid reponse provided by the Bing API', 'cat-tracker' ) );

		if ( ! self::contains_at_least_one_address( $response_data ) )
			return new WP_Error( 'invalid-address', __( 'The provided address returned as an invalid address', 'cat-tracker' ) );

		return self::get_coordinates_for_first_address_in_response( $response_data );
	}

	/**
	 * get an address based on coordinates
	 *
	 * @since 1.0
	 * @uses Cat_Tracker_Geocode::request()
	 * @param (float) $latitude latitude
	 * @param (float) $longitude longitude
	 * @return WP_Error|array error on failure or array of latitude + longitude on success
	 */
	static function get_address_from_coordinates( $latitude, $longitude ) {

		if ( ! Cat_Tracker_Utils::validate_latitude( $latitude ) || ! Cat_Tracker_Utils::validate_longitude( $longitude ) )
			return new WP_Error( 'invalid-coordinates', __( 'Invalid coordinates provided', 'cat-tracker' ) );

		$api_response = self::request( array(), (string) $latitude . ',' . $longitude );
		if ( is_wp_error( $api_response ) )
			return $api_response;

		$response_data = json_decode( $api_response );
		if ( ! is_object( $response_data ) )
			return new WP_Error( 'invalid-response', __( 'Invalid reponse provided by the Bing API', 'cat-tracker' ) );

		if ( ! self::contains_at_least_one_address( $response_data ) )
			return new WP_Error( 'no-address', __( 'The provided coordinates did not return a valid address', 'cat-tracker' ) );

		return self::get_coordinates_for_first_address_in_response( $response_data );
	}

	/**
	 * given a json response of data obtained from the Bing API, make sure
	 * the response contains at least one address
	 *
	 * @since 1.0
	 * @param (object) $response_data the json response data
	 * @return bool whether there is an address or not
	 */
	static function contains_at_least_one_address( $response_data ) {
		return ( ! empty( $response_data->resourceSets[0]->resources ) );
	}

	/**
	 * given a json response of data obtained from the Bing API,
	 * return the coordinates of the first address in the response
	 *
	 * @since 1.0
	 * @param (object) $response_data the json response data
	 * @return WP_Error|array error on failure or array of coordinates, formatted address and confidence level
	 */
	static function get_coordinates_for_first_address_in_response( $response_data ) {
		if ( empty( $response_data->resourceSets[0]->resources[0]->point->coordinates ) )
			return new WP_Error( 'no-coordinates', __( 'The provided address did not return valid coordinates', 'cat-tracker' ) );

		$first_address = $response_data->resourceSets[0]->resources[0];

		return array(
			'formatted_address' => self::get_formatted_address_from_address( $first_address ),
			'confidence' => self::get_confidence_level_from_address( $first_address ),
			'latitude' => $first_address->point->coordinates[0],
			'longitude' => $first_address->point->coordinates[1]
		);
	}

	/**
	 * given an address object, return the confidence level
	 *
	 * @since 1.0
	 * @param (object) $address the address object
	 * @return null|string null on failure or confidence level
	 */
	static function get_confidence_level_from_address( $address ) {
		if ( empty( $address->confidence ) )
			return null;

		return strtolower( $address->confidence );
	}

	/**
	 * given an address object, return the formatted address
	 *
	 * @since 1.0
	 * @param (object) $address the address object
	 * @return null|string null on failure or formatted address
	 */
	static function get_formatted_address_from_address( $address ) {
		if ( empty( $address->address->formattedAddress ) )
			return null;

		return $address->address->formattedAddress;
	}

	/**
	 * helper function to make HTTP requests to the Bing maps API
	 *
	 * @since 1.0
	 * @uses WP_HTTP API
	 * @param (array) $query_args query variables to add as query arguments to the GET request
	 * @param (string) $append_to_url string to append to the default URL
	 * @return WP_Error|string error on failure or response body on success
	 */
	static function request( $query_args = array(), $append_to_url = null ) {
		if ( ! defined( 'CAT_TRACKER_BING_GEO_API_KEY' ) || '' == CAT_TRACKER_BING_GEO_API_KEY )
			return new WP_Error( 'missing-bing-api-key', __( 'No Bing API key defined. Please define CAT_TRACKER_BING_GEO_API_KEY in your wp-config.php file.', 'cat-tracker' ) );

		$default_args = array(
			'key' => CAT_TRACKER_BING_GEO_API_KEY,
			'o' => 'json',
		);
		$query_args = wp_parse_args( $query_args, $default_args );
		$query_args = urlencode_deep( $query_args );
		$query_url = ( empty( $append_to_url ) ) ? self::BING_BASE_URL : trailingslashit( self::BING_BASE_URL ) . $append_to_url;
		$query_url = esc_url_raw( add_query_arg( $query_args, $query_url ) );

		$http_response = wp_remote_get( $query_url );
		if ( is_wp_error( $http_response ) )
			return $http_response;

		$http_response_code = wp_remote_retrieve_response_code( $http_response );
		$http_response_message = wp_remote_retrieve_response_message( $http_response );

		if ( 200 != $http_response_code && ! empty( $http_response_message ) )
			return new WP_Error( $http_response_code, $http_response_message );
		elseif ( 200 != $http_response_code )
			return new WP_Error( $http_response_code, __( 'Unknown HTTP error', 'cat-tracker' ) );

		return wp_remote_retrieve_body( $http_response );
	}

}
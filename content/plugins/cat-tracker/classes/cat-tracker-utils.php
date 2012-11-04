<?php
/**
 * @package Cat_Tracker
 * @author Joachim Kudish
 * @version 1.0
 *
 * This class contains various utility functions
 * that are helful to the Cat Tracker plugin
 * This is a static class
*/

class Cat_Tracker_Utils {

	public static function validate_latitude( $maybe_a_latitude ) {
		return self::_validate_latitude_or_latitude_helper( $maybe_a_latitude, 90 );
	}

	public static function validate_longitude( $maybe_a_longitude ) {
		return self::_validate_latitude_or_latitude_helper( $maybe_a_longitude, 180 );
	}

	private static function _validate_latitude_or_latitude_helper( $maybe_a_latitude_or_longitude, $range ) {
		$range = (int) $range;
		return ( ! empty( $maybe_a_latitude_or_longitude ) && is_numeric( $maybe_a_latitude_or_longitude ) && -$range <= $maybe_a_latitude_or_longitude && $range >= $maybe_a_latitude_or_longitude );
	}

}
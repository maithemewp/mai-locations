<?php

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * If current page has any active location filters.
 *
 * @access private
 *
 * @since TBD
 *
 * @return bool
 */
function mailocations_is_filtered_locations() {
	static $filtered = null;

	if ( ! is_null( $filtered ) ) {
		return $filtered;
	}

	$filtered = false;

	if ( ! $_GET ) {
		return $filtered;
	}

	$keys = array_keys( mailocations_get_location_taxonomies_underscored() );
	$keys = array_merge( $keys, [ 'lat', 'lng' ] );

	foreach ( $keys as $key ) {
		if ( ! isset( $_GET[ $key ] ) ) {
			continue;
		}

		$filtered = true;
		break;
	}

	return $filtered;
}

/**
 * Gets valid query params, if any.
 *
 * @since TBD
 *
 * @return array
 */
function mailocations_get_query_params() {
	$params   = [];
	$defaults = mailocations_get_query_defaults();

	// Check query strings.
	foreach ( $defaults as $key => $value ) {
		// Skip if the param is not set.
		if ( ! isset( $_GET[ $key ] ) ) {
			continue;
		}

		$get              = esc_html( $_GET[ $key ] );
		$get              = is_array( $defaults[ $key ] ) ? explode( ',', $_GET[ $key ] ) : $_GET[ $key ];
		$params[ $key ] = $get;
	}

	return $params;
}

/**
 * Gets valid query param defaults.
 *
 * @since TBD
 *
 * @return array
 */
function mailocations_get_query_defaults() {
	static $defaults = null;

	if ( ! is_null( $defaults ) ) {
		return $defaults;
	}

	// Set static defaults.
	$distance = mailocations_get_option( 'distances' );
	$units    = mailocations_get_option( 'units' );
	$defaults = [
		'address'  => '',
		'lat'      => '',
		'lng'      => '',
		'distance' => reset( $distance ),
		'unit'     => reset( $units ),
		'state'    => '',
		'province' => '',
	];

	// Add taxonomies.
	foreach ( mailocations_get_location_taxonomies() as $name => $label ) {
		$defaults[ "_{$name}" ] = [];
	}

	// Add filter.
	$defaults = apply_filters( 'mailocations_location_query_defaults', $defaults );

	return $defaults;
}

/**
 * Gets the distance from the queried location.
 *
 * @since TBD
 *
 * @param  WP_Post   $post_obj
 * @param  int|false $round    The amount of decimal places to round the value to.
 *
 * @return void
 */
function mailocations_get_distance( $post_obj = null, $round = 1 ) {
	return Mai_Geo_Query::get_distance( $post_obj, $round );
}

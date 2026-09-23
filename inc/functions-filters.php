<?php

use Mai\Locations\Cache;

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
	if ( Cache::has( 'is_filtered' ) ) {
		return Cache::get( 'is_filtered' );
	}

	if ( ! $_GET ) {
		return Cache::set( 'is_filtered', false );
	}

	$filtered = false;

	$keys = array_keys( mailocations_get_location_taxonomies_underscored() );
	$keys = array_merge( $keys, [ 'lat', 'lng' ] );

	foreach ( $keys as $key ) {
		if ( ! isset( $_GET[ $key ] ) ) {
			continue;
		}

		$filtered = true;
		break;
	}

	return Cache::set( 'is_filtered', $filtered );
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

		// Escape once, then use the escaped value. Until September 16, 2026 the second line read
		// $_GET again and threw the escaped value away.
		//
		// A list param arrives as a comma string, or as an array when someone sends
		// ?_mai_location_cat[]=x. esc_html() on an array gave the string "Array" and a
		// warning, so each value is escaped on its own now. An array where one value is
		// expected is ignored. Fixed September 23, 2026.
		$raw = $_GET[ $key ];

		if ( is_array( $defaults[ $key ] ) ) {
			$list           = is_array( $raw ) ? $raw : explode( ',', (string) $raw );
			$params[ $key ] = array_map( 'esc_html', array_map( 'strval', array_filter( $list, 'is_scalar' ) ) );
		} elseif ( is_scalar( $raw ) ) {
			$params[ $key ] = esc_html( (string) $raw );
		}
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
	if ( Cache::has( 'query_defaults' ) ) {
		return Cache::get( 'query_defaults' );
	}

	// Set static defaults.
	$defaults = [
		'address'  => '',
		'lat'      => '',
		'lng'      => '',
		'distance' => mailocations_get_option( 'distance' ),
		'units'    => mailocations_get_option( 'units' ),
		'state'    => '',
		'province' => '',
	];

	// Force distance.
	if ( ! $defaults['distance'] ) {
		$defaults['distance'] = mailocations_get_option_default( 'distance' );
	}

	// Add taxonomies.
	foreach ( mailocations_get_location_taxonomies() as $name => $label ) {
		$defaults[ "_{$name}" ] = [];
	}

	// Add filter.
	$defaults = apply_filters( 'mailocations_location_query_defaults', $defaults );

	return Cache::set( 'query_defaults', $defaults );
}

/**
 * Gets filtered query args for `WP_Query`.
 *
 * @since TBD
 *
 * @param array $args Any existing args.
 *
 * @return array
 */
function mailocations_get_filtered_query_args( $args = [] ) {
	$params   = mailocations_get_query_params();
	$defaults = mailocations_get_query_defaults();
	$filters  = isset( $params['filter'] ) ? $params['filter'] : '';
	$lat      = isset( $params['lat'] ) ? $params['lat'] : '';
	$lng      = isset( $params['lng'] ) ? $params['lng'] : '';
	$dist     = isset( $params['distance'] ) ? $params['distance'] : $defaults['distance'];
	$unit     = isset( $params['units'] ) ? $params['units'] : $defaults['units'];
	$taxos    = array_intersect_key( $params, mailocations_get_location_taxonomies_underscored() );
	$taxos    = array_combine( array_map( function( $key ) {
		return ltrim( $key, '_' ); // Trim lead underscore.
	}, array_keys( $taxos ) ), $taxos );

	// If geo query. A latitude or longitude of 0 is a real coordinate, so check for a number
	// rather than for truthiness. Fixed September 16, 2026.
	if ( is_numeric( $lat ) && is_numeric( $lng ) ) {
		// Set geo query.
		$args['orderby']   = 'distance';
		$args['order']     = 'ASC';
		$args['geo_query'] = [
			'lat_field' => 'location_lat',
			'lng_field' => 'location_lng',
			'latitude'  => $lat,
			'longitude' => $lng,
			'distance'  => $dist, // @int The maximum distance to search.
			'units'     => $unit, // Supports options: miles, mi, kilometers, km
		];
	}

	// If tax query.
	if ( $taxos ) {
		// Make sure existing tax query is used, if there is one.
		$args['tax_query'] = isset( $args['tax_query'] ) ? $args['tax_query'] : [];

		// Remove relation.
		unset( $args['tax_query']['relation'] );

		// Loop though taxonomies.
		foreach ( $taxos as $name => $values ) {
			// Set query args.
			$tax_query = [
				'taxonomy' => $name,
				'field'    => 'slug',
				'terms'    => $values,
			];

			// If more than one term, add operator.
			if ( count( $values ) > 1 ) {
				$tax_query['operator'] = 'AND';
			}

			// Add to tax query.
			$args['tax_query'][] = $tax_query;
		}

		// Only use relation if more than 1, according to `WP_Query` docs.
		if ( count( $args['tax_query'] ) > 1 ) {
			$args['tax_query']['relation'] = 'AND';
		}
	}

	return $args;
}

/**
 * Gets the distance from the queried location.
 *
 * @since TBD
 *
 * @param  WP_Post   $post_obj
 * @param  int|false $round    The amount of decimal places to round the value to.
 *
 * @return float|false The distance, or false when the post carries none.
 */
function mailocations_get_distance( $post_obj = null, $round = 1 ) {
	return Mai_Geo_Query::get_distance( $post_obj, $round );
}

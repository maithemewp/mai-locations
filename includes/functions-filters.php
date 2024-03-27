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

	return $defaults;
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

	// If geo query.
	if ( $lat && $lng ) {
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
 * @return void
 */
function mailocations_get_distance( $post_obj = null, $round = 1 ) {
	return Mai_Geo_Query::get_distance( $post_obj, $round );
}

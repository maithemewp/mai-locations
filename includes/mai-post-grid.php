<?php

/**
 */
add_filter( 'mai_post_grid_query_args', function( $query_args, $args ) {
	// Bail if not a location.
	if ( ! in_array( 'mai_location', (array) $query_args['post_type'] ) ) {
		return $query_args;
	}

	// Check for coordinates.
	$lat = isset( $_GET['lat'] ) && ! empty( $_GET['lat'] ) ? esc_html( $_GET['lat'] ) : '';
	$lng = isset( $_GET['lng'] ) && ! empty( $_GET['lng'] ) ? esc_html( $_GET['lng'] ) : '';

	// Bail if no coordinates.
	if ( ! ( $lat && $lng ) ) {
		return $query_args;
	}

	$dist = 20;

	// Build the new query.
	$query_args['posts_per_page'] = -1;
	$query_args['orderby']        = 'distance';
	$query_args['order']          = 'ASC';
	$query_args['geo_query']      = [
		'lat_field' => 'location_lat',
		'lng_field' => 'location_lng',
		'latitude'  => $lat,
		'longitude' => $lng,
		'distance'  => $dist, // @int The maximum distance to search.
		'units'     => 'miles', // Supports options: miles, mi, kilometers, km
	];

	return $query_args;

}, 10, 2 );

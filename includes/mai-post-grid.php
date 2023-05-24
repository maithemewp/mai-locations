<?php

/**
 * Sets trending category archive to show trending posts.
 *
 * @return  void
 */
add_action( 'pre_get_posts', function( $query ) {
	// Bail if in the Dashboard.
	if ( is_admin() ) {
		return;
	}

	if ( ! $query->is_main_query() ) {
		return;
	}

	if ( ! $query->is_post_type_archive( 'mai_location' ) ) {
		return;
	}

	if ( ! mailocations_is_filtered_locations() ) {
		return;
	}

	$lat   = isset( $_GET['lat'] ) && ! empty( $_GET['lat'] ) ? esc_html( $_GET['lat'] ) : '';
	$lng   = isset( $_GET['lng'] ) && ! empty( $_GET['lng'] ) ? esc_html( $_GET['lng'] ) : '';
	$taxos = [];

	foreach ( array_keys( mailocations_get_location_taxonomies() ) as $name ) {
		if ( ! isset( $_GET[ $name ] ) || empty( $_GET[ $name ] ) ) {
			continue;
		}

		$taxos[ $name ] = explode( ',', esc_html( $_GET[ $name ] ) );
	}

	// Bail if no coordinates or taxonomies.
	if ( ! ( $lat && $lng ) && ! $taxos ) {
		return;
	}

	// TODO: Set/get distance somewhere.
	$dist = 1000;

	// Show all results.
	$query->set( 'posts_per_page', -1 );

	// If geo query.
	if ( $lat && $lng ) {
		// Set geo query.
		$query->set( 'orderby', 'distance' );
		$query->set( 'order', 'ASC' );
		$query->set( 'geo_query',
			[
				'lat_field' => 'location_lat',
				'lng_field' => 'location_lng',
				'latitude'  => $lat,
				'longitude' => $lng,
				'distance'  => $dist, // @int The maximum distance to search.
				'units'     => 'miles', // Supports options: miles, mi, kilometers, km.
			]
		);
	}

	// If tax query.
	if ( $taxos ) {
		// Make sure existing tax query is used, if there is one.
		$tax_query = $query->get( 'tax_query' ) ? $query->get( 'tax_query' ) : [];

		// Remove relation.
		unset( $tax_query['relation'] );

		// Loop though taxonomies.
		foreach ( $taxos as $name => $values ) {
			$tax_query[] = [
				'taxonomy' => $name,
				'field'    => 'slug',
				'terms'    => $values,
				'operator' => 'AND',
			];
		}

		// Only use relation if more than 1, according to `WP_Query` docs.
		if ( count( $tax_query ) > 1 ) {
			$query_args['tax_query']['relation'] = 'AND';
		}

		$query->set( 'tax_query', $tax_query );
	}
});

add_filter( 'mai_post_grid_query_args', 'mailocations_mai_post_grid_query_args', 10, 2 );
/**
 * Modify Mai Post Grid args with filter arguments.
 *
 * @since TBD
 *
 * @param array $query_args WP_Query args
 * @param array $args       Mai Post Grid block args.
 *
 * @return array
 */
function mailocations_mai_post_grid_query_args( $query_args, $args ) {
	// Bail if not a location.
	if ( ! in_array( 'mai_location', (array) $query_args['post_type'] ) ) {
		return $query_args;
	}

	$lat   = isset( $_GET['lat'] ) && ! empty( $_GET['lat'] ) ? esc_html( $_GET['lat'] ) : '';
	$lng   = isset( $_GET['lng'] ) && ! empty( $_GET['lng'] ) ? esc_html( $_GET['lng'] ) : '';
	$taxos = [];

	foreach ( array_keys( mailocations_get_location_taxonomies() ) as $name ) {
		if ( ! isset( $_GET[ $name ] ) || empty( $_GET[ $name ] ) ) {
			continue;
		}

		$taxos[ $name ] = explode( ',', esc_html( $_GET[ $name ] ) );
	}

	// Bail if no coordinates or taxonomies.
	if ( ! ( $lat && $lng ) && ! $taxos ) {
		return $query_args;
	}

	$dist = 1000;

	// Show all results.
	$query_args['posts_per_page'] = -1;

	// If geo query.
	if ( $lat && $lng ) {
		// Set geo query.
		$query_args['orderby']   = 'distance';
		$query_args['order']     = 'ASC';
		$query_args['geo_query'] = [
			'lat_field' => 'location_lat',
			'lng_field' => 'location_lng',
			'latitude'  => $lat,
			'longitude' => $lng,
			'distance'  => $dist, // @int The maximum distance to search.
			'units'     => 'miles', // Supports options: miles, mi, kilometers, km
		];
	}

	// If tax query.
	if ( $taxos ) {
		// Make sure existing tax query is used, if there is one.
		$query_args['tax_query'] = isset( $query_args['tax_query'] ) ? $query_args['tax_query'] : [];

		// Remove relation.
		unset( $query_args['tax_query']['relation'] );

		// Loop though taxonomies.
		foreach ( $taxos as $name => $values ) {
			$query_args['tax_query'][] = [
				'taxonomy' => $name,
				'field'    => 'slug',
				'terms'    => $values,
				'operator' => 'AND',
			];
		}

		// Only use relation if more than 1, according to `WP_Query` docs.
		if ( count( $query_args['tax_query'] ) > 1 ) {
			$query_args['tax_query']['relation'] = 'AND';
		}
	}

	return $query_args;
}

<?php

/**
 */
add_filter( 'mai_post_grid_query_args', function( $query_args, $args ) {
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

	$dist = 20;

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

		// ray( $query_args['tax_query'] );

		// $query_args['tax_query'][1]['field'] = 'ID';
		// // $query_args['tax_query']['terms'] = [ 48, 47, 136 ];
		// $query_args['tax_query'][1]['terms'] = [ 48 ];
		// ray( $query_args['tax_query'] );

		// Only use relation if more than 1, according to `WP_Query` docs.
		if ( count( $query_args['tax_query'] ) > 1 ) {
			$query_args['tax_query']['relation'] = 'AND';
		}
	}

	// $query = new WP_Query( $query_args );

	// ray( $query->post_count );

	// if ( $query->have_posts() ) {
	// 	while ( $query->have_posts() ) : $query->the_post();
	// 		// ray( get_the_title() );
	// 		$terms = get_the_terms( get_the_ID(), 'language_spoken' );
	// 		ray( $terms );
	// 	endwhile;
	// }
	// wp_reset_postdata();

	return $query_args;

}, 10, 2 );

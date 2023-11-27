<?php

add_action( 'pre_get_posts', 'mailocations_pre_get_posts_query' );
/**
* Filters the location archive page query.
*
* @since TBD
*
* @return void
*/
function mailocations_pre_get_posts_query( $query ) {
	// Bail if in the Dashboard.
	if ( is_admin() ) {
		return;
	}

	// Bail if not the main query.
	if ( ! $query->is_main_query() ) {
		return;
	}

	// Bail if not a location archive page.
	if ( ! ( is_post_type_archive( 'mai_location' ) || is_tax( array_keys( mailocations_get_location_taxonomies() ) ) ) ) {
		return;
	}

	// If filtered .
	if ( mailocations_is_filtered_locations() ) {
		// Get the filters.
		$params   = mailocations_get_query_params();
		$defaults = mailocations_get_query_defaults();
		$lat      = isset( $params['lat'] ) ? $params['lat'] : '';
		$lng      = isset( $params['lng'] ) ? $params['lng'] : '';
		$dist     = isset( $params['distance'] ) ? $params['distance'] : $defaults['distance'];
		$unit     = isset( $params['unit'] ) ? $params['unit'] : $defaults['unit'];
		$taxos    = array_intersect_key( $params, mailocations_get_location_taxonomies() );

		// Bail if no coordinates or taxonomies.
		if ( ! ( $lat && $lng ) && ! $taxos ) {
			return;
		}

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
					'units'     => $unit, // Supports options: miles, mi, kilometers, km.
				]
			);

			// Get state/province.
			$state    = isset( $params['state'] ) && $params['state'] ? $params['state'] : '';
			$province = isset( $params['province'] ) && $params['province'] ? $params['province'] : '';

			// If limiting by state.
			if ( ( $state || $province ) && mailocations_get_option( 'limit_state' ) ) {
				// Make sure existing meta query is used, if there is one.
				$meta_query = $query->get( 'meta_query' ) ? $query->get( 'meta_query' ) : [];

				// Remove relation.
				unset( $meta_query['relation'] );

				// Add new meta query item.
				$meta_query[] = [
					'key'     => $state ? 'address_state' : 'address_state_int',
					'value'   => $state ? $state : $province,
					'compare' => '=',
				];

				// Only use relation if more than 1.
				if ( count( $meta_query ) > 1 ) {
					$query_args['meta_query']['relation'] = 'AND';
				}

				$query->set( 'meta_query', $meta_query );
			}
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
	}
	// Not filtered.
	else {
		$query->set( 'orderby', 'title' );
		$query->set( 'order', 'ASC' );
	}
}

// add_filter( 'mai_post_grid_query_args', 'mailocations_mai_post_grid_query', 10, 2 );
/**
 * Modify Mai Post Grid args with filter arguments.
 * TODO: Add setting for when to hijack Mai Post Grid.
 *
 * @since TBD
 *
 * @param array $query_args WP_Query args
 * @param array $args       Mai Post Grid block args.
 *
 * @return array
 */
function mailocations_mai_post_grid_query( $query_args, $args ) {
	// Bail if not a location.
	if ( ! in_array( 'mai_location', (array) $query_args['post_type'] ) ) {
		return $query_args;
	}

	// Get the filters.
	$params   = mailocations_get_query_params();
	$defaults = mailocations_get_query_defaults();
	$lat      = isset( $params['lat'] ) ? $params['lat'] : '';
	$lng      = isset( $params['lng'] ) ? $params['lng'] : '';
	$dist     = isset( $params['distance'] ) ? $params['distance'] : $defaults['distance'];
	$unit     = isset( $params['unit'] ) ? $params['unit'] : $defaults['unit'];
	$taxos    = array_intersect_key( $params, mailocations_get_location_taxonomies() );

	// Bail if no coordinates or taxonomies.
	if ( ! ( $lat && $lng ) && ! $taxos ) {
		return $query_args;
	}

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
			'units'     => $unit, // Supports options: miles, mi, kilometers, km
		];

		// Get state/province.
		$state    = isset( $params['state'] ) && $params['state'] ? $params['state'] : '';
		$province = isset( $params['province'] ) && $params['province'] ? $params['province'] : '';

		// If limiting by state.
		if ( ( $state || $province ) && mailocations_get_option( 'limit_state' ) ) {
			// Make sure existing meta query is used, if there is one.
			$query_args['meta_query'] = isset( $query_args['meta_query'] ) ? $query_args['meta_query'] : [];

			// Remove relation.
			unset( $query_args['meta_query']['relation'] );

			// Add new meta query item.
			$query_args['meta_query'][] = [
				'key'     => $state ? 'address_state' : 'address_state_int',
				'value'   => $state ? $state : $province,
				'compare' => '=',
			];

			// Only use relation if more than 1.
			if ( count( $query_args['meta_query'] ) > 1 ) {
				$query_args['meta_query']['relation'] = 'AND';
			}
		}
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

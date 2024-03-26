<?php

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Gets a user's locations.
 * Checks if posts exist.
 *
 * @since 0.1.0
 *
 * @param int  $user_id The user ID.
 * @param bool $admin   If displaying table in admin.
 *
 * @return array Array of post IDs.
 */
function mailocation_get_user_locations( $user_id = 0 ) {
	// Get current user.
	$user_id = (int) $user_id ?: get_current_user_id();

	// Bail if no user.
	if ( ! $user_id ) {
		return [];
	}

	// Setup cache.
	static $all_locations = null;

	// Maybe return cache.
	if ( is_array( $all_locations ) && isset( $all_locations[ $user_id ] ) ) {
		return $all_locations[ $user_id ];
	}

	// Set as array.
	$all_locations = is_array( $all_locations ) ? $all_locations : [];

	// Get locations.
	$locations = (array) get_user_meta( $user_id, 'user_locations', true );
	$locations = array_unique( $locations );
	$locations = array_filter( $locations );

	if ( $locations ) {
		foreach ( $locations as $index => $location_id ) {
			if ( mailocations_post_exists( $location_id ) ) {
				continue;
			}

			unset( $locations[ $index ] );
		}
	}

	// Add to cache.
	$all_locations[ $user_id ] = $locations;

	return $all_locations[ $user_id ];
}

/**
 * Creates a location post.
 *
 * @since 0.1.0
 *
 * @param array $post_args The post array used in wp_insert_post().
 * @param array $meta_args The args used for meta_input in wp_insert_post().
 * @param int   $user_id   The user ID to add the location to.
 *
 * @return int|WP_Error The post ID on success. The value 0 or WP_Error on failure.
 */
function mailocations_create_location( $post_args, $meta_args, $user_id = 0 ) {
	$post_args = wp_parse_args( $post_args,
		[
			'post_status' => 'public',
		]
	);

	$meta_defaults           = mailocations_get_fields_defaults();
	$meta_input              = wp_parse_args( $meta_args, $meta_defaults );
	$post_args['meta_input'] = isset( $post_args['meta_input'] ) ? array_merge( $post_args['meta_input'], $meta_input ) : $meta_input;

	// Filter post args.
	$post_args = apply_filters( 'mailocations_post_args', $post_args, $user_id );

	// Force post_type.
	$post_args['post_type'] = 'mai_location';

	$post_id = wp_insert_post( $post_args );

	if ( $post_id && ! is_wp_error( $post_id ) ) {
		// Update map with location data.
		mailocations_update_google_map_from_address( $post_id );

		// Add location to user.
		mailocations_add_location_to_user( $post_id, $user_id );

		// Index FacetWP.
		if ( function_exists( 'FWP' ) ) {
			FWP()->indexer->index( $post_id );
		}
	}

	return $post_id;
}

/**
 * Adds location to user. This allows a user to manage the location.
 *
 * @since 0.1.1
 *
 * @param int $post_id The post ID.
 * @param int $user_id The user ID.
 *
 * @return void
 */
function mailocations_add_location_to_user( $post_id, $user_id ) {
	$user = get_user_by( 'id', $user_id );

	if ( ! $user ) {
		$message = sprintf( __( 'No user with the ID of %s', 'mai-locations' ), $user_id );
		return new WP_Error( 'no user', $message );
	}

	$locations   = (array) get_user_meta( $user_id, 'user_locations', true );
	$locations   = array_map( 'absint', $locations );
	$locations   = array_filter( $locations );
	$locations[] = $post_id;

	update_user_meta( $user_id, 'user_locations', $locations );

	return true;
}

/**
 * Creates a location from a user,
 * with WooCommerce field data as the location data,
 * including using Company field as the post title.
 *
 * @param int   $user_id The user ID.
 * @param array $args    The post args.
 *
 * @return void
 *
 * @return int|WP_Error The post ID on success. The value 0 or WP_Error on failure.
 */
function mailocations_create_location_from_woocommerce_user( $user_id, $args = [] ) {
	$user = get_user_by( 'id', $user_id );

	if ( ! $user ) {
		$message = sprintf( __( 'No user with the ID of %s', 'mai-locations' ), $user_id );
		return new WP_Error( 'no user', $message );
	}

	$company    = get_user_meta( $user_id, 'billing_company', true );
	$post_title = $company ?: $user->display_name;
	$post_args  = wp_parse_args( $args,
		[
			'post_title'  => $post_title,
			'post_status' => 'publish',
		]
	);

	// Force post_type.
	$post_args['post_type'] = 'mai_location';

	$meta_args = [
		'billing_first_name' => $user->first_name,
		'billing_last_name'  => $user->last_name,
	];

	$meta_keys = [
		'address_street'   => 'billing_address_1',
		'address_street_2' => 'billing_address_2',
		'address_city'     => 'billing_city',
		'address_state'    => 'billing_state',
		'address_postcode' => 'billing_postcode',
		'address_country'  => 'billing_country',
		'location_phone'   => 'billing_phone',
		'location_email'   => 'billing_email',
	];

	foreach ( $meta_keys as $location_key => $user_key ) {
		$meta_args[ $location_key ] = get_user_meta( $user_id, $user_key, true );
	}

	$meta_args['location_url'] = $user->user_url;

	return mailocations_create_location( $post_args, $meta_args, $user_id );
}

/**
 * Updates address from Google Map data.
 * TODO: Return or log WP_Error if things don't work/run.

 * @since TBD
 *
 * @param mixed $post_id
 *
 * @return void
 */
function mailocations_update_address_from_google_map( $post_id ) {
	$api_key = mailocations_get_google_maps_api_key();

	// Bail if no API key.
	if ( ! $api_key ) {
		return;
	}

	// Get lat/lng.
	$lat = get_post_meta( $post_id, 'location_lat', true );
	$lng = get_post_meta( $post_id, 'location_lng', true );

	// Bail if no lat/lng.
	if ( ! ( $lat && $lng ) ) {
		return;
	}

	// Build url.
	$url = 'https://maps.google.com/maps/api/geocode/json';
	$url = add_query_arg(
		[

			'latlng' => implode( ',', [ $lat, $lng ] ),
			'sensor' => 'false',
			'key'    => $api_key,
		],
		$url
	);

	// Get the result.
	$result = mailocations_get_google_maps_result( $url );

	// Bail if no result.
	if ( ! $result ) {
		return;
	}

	// Set vars.
	$components = isset( $result['address_components'] ) ? (array) $result['address_components'] : [];

	// Bail if no components.
	if ( ! $components ) {
		return;
	}

	// Get meta from address components.
	$meta = mailocations_get_address_meta_from_components( $components );

	// Bail if not meta.
	if ( ! $meta ) {
		return;
	}

	// Update post meta.
	foreach ( $meta as $key => $value ) {
		update_post_meta( $post_id, $key, trim( sanitize_text_field( $value ) ) );
	}
}

/**
 * Updates map field from address data.
 * TODO: Return or log WP_Error if things don't work/run.
 *
 * @since 0.1.0
 *
 * @param int $post_id The post ID to update.
 *
 * @return void
 */
function mailocations_update_google_map_from_address( $post_id ) {
	$api_key = mailocations_get_google_maps_api_key();

	// Bail if no API key.
	if ( ! $api_key ) {
		return;
	}

	// Set vars.
	$countries = mailocations_get_country_choices();
	$states    = mailocations_get_state_choices();
	$address   = [];
	$keys      = [
		'address_country',
		'address_street',
		'address_city',
		'address_state',
		'address_state_int',
		'address_postcode',
	];

	// Get values to update.
	foreach ( $keys as $key ) {
		$value = get_post_meta( $post_id, $key, true );

		switch ( $key ) {
			case 'address_country':
				$value = isset( $countries[ $key ] ) ? $value : '';
				break;
			case 'address_state':
				$value = isset( $states[ $key ] ) ? $value : '';
			break;
		}

		if ( $value ) {
			$address[] = $value;
		}
	}

	// Build address string.
	$address = array_filter( $address );
	$address = array_values( $address );
	$address = implode( ',', $address );

	// Bail if no address for API hit.
	if ( ! $address ) {
		return;
	}

	// Build url.
	$url = 'https://maps.google.com/maps/api/geocode/json';
	$url = add_query_arg(
		[

			'address' => urlencode( $address ),
			'sensor'  => 'false',
			'key'     => $api_key,
		],
		$url
	);

	// Get the result.
	$result = mailocations_get_google_maps_result( $url );

	// Bail if no result.
	if ( ! $result ) {
		return;
	}

	// Get the address components.
	$formatted  = isset( $result['formatted_address'] ) ? (array) $result['formatted_address'] : [];
	$components = isset( $result['address_components'] ) ? (array) $result['address_components'] : [];

	// Bail if no components.
	if ( ! $components ) {
		return;
	}

	// Start the location data.
	$location = [];

	// Build location field data (ACF Google Maps field).
	foreach ( $components as $component ) {
		$types = array_flip( $component['types'] );

		if ( ! $types ) {
			continue;
		}

		// Street number.
		if ( isset( $types['street_number'] ) ) {
			$location['street_number'] = $component['short_name'];
		}

		// Street name.
		if ( isset( $types['route'] ) ) {
			$location['street_name']       = $component['long_name'];
			$location['street_name_short'] = $component['short_name'];
		}

		// City.
		if ( isset( $types['locality'] ) ) {
			$location['city'] = $component['short_name'];
		}

		// State.
		if ( isset( $types['administrative_area_level_1'] ) ) {
			$location['state']       = $component['long_name'];
			$location['state_short'] = $component['short_name'];
		}

		// Country.
		if ( isset( $types['country'] ) ) {
			$location['country']       = $component['long_name'];
			$location['country_short'] = $component['short_name'];
		}

		// Zip.
		if ( isset( $types['postal_code'] ) ) {
			$location['post_code'] = $component['short_name'];
		}
	}

	// Set the formatted address, lat/lng, and other required data.
	$location['address']  = (string) reset( $formatted );
	$location['lat']      = isset( $result['geometry']['location']['lat'] ) ? $result['geometry']['location']['lat'] : '';
	$location['lng']      = isset( $result['geometry']['location']['lng'] ) ? $result['geometry']['location']['lng'] : '';
	$location['zoom']     = 4;
	$location['place_id'] = isset( $result['place_id'] ) ? $result['place_id'] : '';

	// Build street name.
	if ( isset( $location['street_number'] ) && isset( $location['street_name_short'] ) ) {
		$location['name'] = sprintf( '%s %s', $location['street_number'], $location['street_name_short'] );
	}

	// Update the location field post meta.
	update_field( 'mai_location_location', $location, $post_id );

	// Update the place ID.
	if ( isset( $result['place_id'] ) && ! empty( $result['place_id'] ) ) {
		update_post_meta( $post_id, 'place_id', $result['place_id'] );
	}

	// Index FacetWP.
	if ( function_exists( 'FWP' ) ) {
		FWP()->indexer->index( $post_id );
	}
}

/**
 * Get meta from address components from Google Places API.
 * Key is the meta key, value is the value.
 *
 * @since TBD
 *
 * @param array $components The address components.
 *
 * @return array
 */
function mailocations_get_address_meta_from_components( $components ) {
	// Get is started.
	$meta   = [];
	$street = [ 'number' => '', 'name'   => '' ];

	// Build address data.
	foreach ( $components as $component ) {
		// Skip types is not set.
		if ( ! isset( $component['types'] ) ) {
			continue;
		}

		// Get types.
		$types = array_flip( $component['types'] );

		// Skip if no types.
		if ( ! $types ) {
			continue;
		}

		if ( isset( $types['street_number'] ) ) {
			$street['number'] = $component['short_name'];
		}

		if ( isset( $types['route'] ) ) {
			$street['name'] = $component['short_name'];
		}

		if ( isset( $types['locality'] ) ) {
			$meta['address_city'] = $component['short_name'];
		}

		if ( isset( $types['administrative_area_level_1'] ) ) {
			$meta['address_state']     = $component['short_name'];
			$meta['address_state_int'] = $component['short_name'];
		}

		if ( isset( $types['country'] ) ) {
			$meta['address_country'] = $component['short_name'];
		}

		if ( isset( $types['postal_code'] ) ) {
			$meta['address_postcode'] = $component['short_name'];
		}
	}

	// Build street.
	$meta['address_street'] = sprintf( '%s %s', $street['number'], $street['name'] );

	// If US, clear international.
	if ( 'US' === $meta['address_country'] ) {
		$meta['address_state_int'] = '';
	}
	// Else clear US.
	else {
		$meta['address_state'] = '';
	}

	return $meta;
}

/**
 * Gets the Google Maps result.
 *
 * @since TBD
 *
 * @return array
 */
function mailocations_get_google_maps_result( $url ) {
	// Get it started.
	$result = [];

	// Hit the API.
	$response = wp_remote_get( $url );

	// Bail if no response data.
	if ( ! $response ) {
		return $result;
	}

	// Bail if response code is not 200.
	if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
		return $result;
	}

	// Get the body.
	$body = wp_remote_retrieve_body( $response );

	// Bail if no body.
	if ( ! $body ) {
		return $result;
	}

	// Decode the body.
	$output = json_decode( $body, true );

	// Bail if no output or status is not OK.
	if ( ! $output || ! isset( $output['status'] ) || 'OK' !== $output['status'] ) {
		return $result;
	}

	// Get results.
	$results = isset( $output['results'] ) ? $output['results'] : [];

	// Bail if no results.
	if ( ! ( is_array( $results ) && $results ) ) {
		return $result;
	}

	// Get the data we need.
	$result = reset( $results );

	return $result;
}

/**
 * Gets all taxonomies registered to locations.
 *
 * @since TBD
 *
 * @return array
 */
function mailocations_get_location_taxonomies() {
	static $taxonomies = null;

	if ( ! is_null( $taxonomies ) ) {
		return $taxonomies;
	}

	// Set vars.
	$taxonomies = is_array( $taxonomies ) ? $taxonomies : [];
	$objects    = get_object_taxonomies( 'mai_location' );

	if ( $objects ) {
		foreach ( $objects as $name ) {
			$taxonomy = get_taxonomy( $name );

			if ( $taxonomy ) {
				$taxonomies[ $name ] = $taxonomy->label;
			}
		}
	}

	// Elasticpress was adding this taxonomy somehow.
	unset( $taxonomies['ep_custom_result'] );

	return $taxonomies;
}

/**
 * Gets all taxonomies registered to locations with an underscore prefix.
 *
 * @since TBD
 *
 * @return array
 */
function mailocations_get_location_taxonomies_underscored() {
	static $taxonomies = null;

	if ( ! is_null( $taxonomies ) ) {
		return $taxonomies;
	}

	$taxonomies = mailocations_get_location_taxonomies();
	$taxonomies = array_combine( array_map( function( $key ) {
		return "_{$key}";
	}, array_keys( $taxonomies ) ), $taxonomies );

	return $taxonomies;
}

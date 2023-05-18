<?php

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Gets the post type plural label.
 *
 * @since 0.1.0
 *
 * @return string
 */
function mailocations_get_plural() {
	$label = null;
	if ( ! is_null( $label ) ) {
		return $label;
	}
	$label = get_option( 'options_location_label_plural', __( 'Locations', 'mai-location' ) );
	$label = apply_filters( 'mailocations_plural', $label );
	return esc_html( $label );
}

/**
 * Gets the post type singular label.
 *
 * @since 0.1.0
 *
 * @return string
 */
function mailocations_get_singular() {
	$label = null;
	if ( ! is_null( $label ) ) {
		return $label;
	}
	$label = get_option( 'options_location_label_singular', __( 'Location', 'mai-location' ) );
	$label = apply_filters( 'mailocations_singular', $label );
	return esc_html( $label );
}

/**
 * Gets the post type base for urls.
 *
 * @since 0.1.0
 *
 * @return string
 */
function mailocations_get_base() {
	$base = null;
	if ( ! is_null( $base ) ) {
		return $base;
	}
	$base = get_option( 'options_location_base', __( 'locations' ) );
	$base = apply_filters( 'mailocations_base', $base );
	return sanitize_html_class( $base );
}

/**
 * Gets a user's locations.
 * Checks if posts exist.
 *
 * @param int  $user_id The user ID.
 * @param bool $admin   If displaying table in admin.
 *
 * @return array Array of post IDs.
 */
function mailocation_get_user_locations( $user_id = 0 ) {
	$user_id = (int) $user_id ?: get_current_user_id();

	if ( ! $user_id ) {
		return [];
	}

	static $all_locations = null;

	if ( is_array( $all_locations ) && isset( $all_locations[ $user_id ] ) ) {
		return $all_locations[ $user_id ];
	}

	if ( ! is_array( $all_locations ) ) {
		$all_locations = [];
	}

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

	$all_locations[ $user_id ] = $locations;

	return $all_locations[ $user_id ];
}

/**
 * Determines if a post exists in the DB.
 *
 * @since 0.1.0
 *
 * @param int $post_id The post ID.
 *
 * @return bool True if the post exists; otherwise, false.
 */
function mailocations_post_exists( $post_id ) {
	return is_string( get_post_status( $post_id ) );
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
function mailocations_get_the_distance( $post_obj = null, $round = false ) {
	return Mai_Geo_Query::get_the_distance( $post_obj, $round );
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
		mailocations_update_location_from_google_maps( $post_id );

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

add_action( 'acf/save_post', 'mailocations_maybe_update_map_field', 20, 1 );
/**
 * Update the google map field from address data after a location is saved.
 *
 * @since 0.1.0
 *
 * @param int $post_id The post ID.
 *
 * @return void
 */
function mailocations_maybe_update_map_field( $post_id ) {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( wp_is_post_autosave( $post_id ) ) {
		return;
	}

	if ( wp_is_post_revision( $post_id ) ) {
		return;
	}

	if ( 'mai_location' !== get_post_type( $post_id ) ) {
		return;
	}

	mailocations_update_location_from_google_maps( $post_id );
}

/**
 * Updates map field from address data.
 * TODO: Return WP_Error if things don't work/run.
 *
 * @since 0.1.0
 *
 * @param int $post_id The post ID to update.
 *
 * @return void
 */
function mailocations_update_location_from_google_maps( $post_id ) {
	$api_key = mailocations_get_google_maps_api_key();

	if ( ! $api_key ) {
		return;
	}

	$location  = get_post_meta( $post_id, 'location', true );
	$countries = mailocations_get_country_choices();
	$states    = mailocations_get_state_choices();
	$address   = [];
	$update    = [];
	$keys      = [
		'address_country',
		'address_street',
		'address_city',
		'address_state',
		'address_state_int',
		'address_postcode',
	];

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
		} else {
			$update[] = $key;
		}
	}

	$address = array_filter( $address );
	$address = array_values( $address );
	$address = implode( ',', $address );

	if ( ! ( $address || ( $address && $location && $update ) ) ) {
		return;
	}

	$url = 'https://maps.google.com/maps/api/geocode/json';
	$url = add_query_arg(
		[

			'address' => urlencode( $address ),
			'sensor'  => 'false',
			'key'     => $api_key,
		],
		$url
	);

	$geocode = file_get_contents( $url );

	if ( ! $geocode ) {
		return;
	}

	$output = json_decode( $geocode );

	if ( 'OK' !== $output->status ) {
		return;
	}

	$results = $output->results;

	if ( ! ( is_array( $results ) && $results ) ) {
		return;
	}

	$result     = reset( $results );
	$formatted  = $result->formatted_address;
	$components = $result->address_components;
	$data       = [];

	// Build location field data (ACF Google Maps field).
	foreach ( $components as $component ) {
		$types = array_flip( $component->types );

		if ( ! $types ) {
			continue;
		}

		if ( isset( $types['street_number'] ) ) {
			$data['street_number'] = $component->short_name;
		}

		if ( isset( $types['route'] ) ) {
			$data['street_name']       = $component->long_name;
			$data['street_name_short'] = $component->short_name;
		}

		if ( isset( $types['locality'] ) ) {
			$data['city'] = $component->short_name;
		}

		if ( isset( $types['administrative_area_level_1'] ) ) {
			$data['state']       = $component->long_name;
			$data['state_short'] = $component->short_name;
		}

		if ( isset( $types['country'] ) ) {
			$data['country']       = $component->long_name;
			$data['country_short'] = $component->short_name;

			if ( 'US' === $data['country_short'] ) {
				if ( ! in_array( 'address_state', $update ) ) {
					$update[] = 'address_state';
				}
				$update = array_diff( $update, [ 'address_state_int' ] );
			} else {
				if ( ! in_array( 'address_state_int', $update ) ) {
					$update[] = 'address_state_int';
				}
				$update = array_diff( $update, [ 'address_state' ] );
			}
		}

		if ( isset( $types['postal_code'] ) ) {
			$data['post_code'] = $component->short_name;
		}
	}

	$data['address'] = $formatted;
	$data['lat']     = $result->geometry->location->lat;
	$data['lng']     = $result->geometry->location->lng;

	if ( isset( $data['street_number'] ) && isset( $data['street_name_short'] ) ) {
		$data['name'] = sprintf( '%s %s', $data['street_number'], $data['street_name_short'] );
	}

	if ( ! $location ) {
		update_post_meta( $post_id, 'location', $data );
	}

	if ( $update ) {
		$international = isset( $data['country_short'] ) && 'US' !== $data['country_short'];

		foreach ( $update as $key ) {
			$value = '';

			switch ( $key ) {
				case 'address_street':
					if ( isset( $data['name'] ) && $data['name'] ) {
						$value = $data['name'];
					}
				break;
				case 'address_city':
					if ( isset( $data['city'] ) && $data['city'] ) {
						$value = $data['city'];
					}
				break;
				case 'address_state':
					if ( ! $international && isset( $data['state_short'] ) && $data['state_short'] ) {
						$value = $data['state_short'];
					}
				break;
				case 'address_state_int':
					if ( $international && isset( $data['state_short'] ) && $data['state_short'] ) {
						$value = $data['state_short'];
					}
				break;
				case 'address_postcode':
					if ( isset( $data['post_code'] ) && $data['post_code'] ) {
						$value = $data['post_code'];
					}
				break;
				case 'address_country':
					if ( isset( $data['country_short'] ) && $data['country_short'] ) {
						$value = $data['country_short'];
					}
				break;
			}

			if ( ! $value ) {
				continue;
			}

			update_post_meta( $post_id, $key, $value );
		}
	}

	// Index FacetWP.
	if ( function_exists( 'FWP' ) ) {
		FWP()->indexer->index( $post_id );
	}
}

/**
 * Gets ACF Google Maps API key.
 * May be set multiple ways.
 *
 * @since 0.1.0
 *
 * @link https://www.advancedcustomfields.com/blog/google-maps-api-settings/
 *
 * @return string
 */
function mailocations_get_google_maps_api_key() {
	$key = '';
	if ( function_exists( 'acf_get_setting' ) ) {
		$key = acf_get_setting( 'google_api_key' );
	}
	return $key;
}

/**
 * If user can edit a location by ID.
 *
 * @param int $location_id The post ID.
 *
 * @return bool
 */
function mailocations_user_can_edit( $location_id ) {
	if ( ! is_user_logged_in() ) {
		return;
	}

	$locations = mailocation_get_user_locations();

	if ( ! $locations ) {
		return;
	}

	return in_array( $location_id, $locations );
}

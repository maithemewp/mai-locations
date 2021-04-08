<?php

function mailocations_get_post_type_plural() {
	$plural = apply_filters( 'mailocations_post_type_plural', __( 'Locations', 'mai-locations' ) );
	return esc_html( $plural );
}

function mailocations_get_post_type_singular() {
	$singular = apply_filters( 'mailocations_post_type_singular', __( 'Location', 'mai-locations' ) );
	return esc_html( $singular );
}

function mailocations_get_post_type_base() {
	$base = apply_filters( 'mailocations_post_type_base', 'locations' );
	return esc_html( $base );
}

function mailocations_create_location( $post_args, $meta_args, $user_id = 0 ) {
	$meta_defaults = mailocations_get_fields_defaults();
	$meta_input    = wp_parse_args( $meta_args, $meta_defaults );
	$post_args     = wp_parse_args( $post_args,
		[
			'post_status' => 'public',
			'meta_input'  => $meta_input
		]
	);

	$post_args = apply_filters( 'mailocations_post_args', $post_args, $user_id );

	// Force post_type.
	$post_args['post_type'] = 'mai_location';

	$post_id = wp_insert_post( $post_args );

	if ( $post_id && ! is_wp_error( $post_id ) ) {
		// Update map with location data.
		mailocations_update_location_from_google_maps( $post_id );

		if ( $user_id ) {
			$user = get_user_by( 'id', $user_id );

			if ( $user ) {
				$locations   = (array) get_user_meta( $user_id, 'user_locations', true );
				$locations[] = $post_id;
				$locations   = array_map( absint( $locations ) );

				// TODO: This is not working.

				update_user_meta( $user_id, 'user_locations', $locations );
			}
		}
	}

	return $post_id;
}

add_action( 'acf/save_post', 'mailocations_maybe_update_map_field', 20, 1 );
/**
 * Update the google map field from address data after a location is saved.
 *
 * @since 0.1.0
 *
 * @param int $post_id Post ID.
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

	$location = get_post_meta( $post_id, 'location', true );
	$address  = [];
	$update   = [];
	$keys     = [
		'address_country',
		'address_street',
		'address_city',
		'address_state',
		'address_state_int',
		'address_postcode',
	];

	foreach ( $keys as $key ) {
		$value = get_post_meta( $post_id, $key, true );

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

	$geocode = file_get_contents( 'https://maps.google.com/maps/api/geocode/json?address=' . esc_url( $address ) . '&sensor=false&key=' . $api_key );
	$output  = json_decode( $geocode );

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
 * Gets a formatted address from current post in the loop.
 *
 * @since 0.1.0
 *
 * @return string
 */
function mailocations_get_address( $args ) {
	// Atts.
	$args = shortcode_atts(
		[
			'hide' => '', // street, street2, city, state, postcode, country
		],
		$args,
		'mai_location_address'
	);

	$html      = '';
	$hide      = explode( ',', $args['hide'] );
	$hide      = array_map( 'esc_html', $hide );
	$hide      = array_map( 'trim', $hide );
	$hide      = array_flip( $hide );
	$post_id   = get_the_ID();
	$street    = ! isset( $hide['street'] ) ? get_post_meta( $post_id, 'address_street', true ) : '';
	$street_2  = ! isset( $hide['street2'] ) ? get_post_meta( $post_id, 'address_street_2', true ) : '';
	$city      = ! isset( $hide['city'] ) ? get_post_meta( $post_id, 'address_city', true ) : '';
	$state     = ! isset( $hide['state'] ) ? get_post_meta( $post_id, 'address_state', true ) : '';
	$state_int = ! isset( $hide['state'] ) ? get_post_meta( $post_id, 'address_state_int', true ) : '';
	$postcode  = ! isset( $hide['postcode'] ) ? get_post_meta( $post_id, 'address_postcode', true ) : '';
	$country   = ! isset( $hide['country'] ) ? get_post_meta( $post_id, 'address_country', true ) : '';
	$state     = $country && 'US' !== $country ? $state_int : $state; // Use state_int if non-US.

	if ( ! ( $street || $street_2 || $city || $state || $postcode || $country ) ) {
		return $html;
	}

	$html .= '<div itemprop="address" itemscope itemtype="http://schema.org/PostalAddress" class="mai-address-wrapper">';

		if ( $street ) {
			$html .= '<div class="mai-address-item"><span class="street-address" itemprop="streetAddress">' . esc_html( $street ) .'</span></div>';
		}

		if ( $street_2 ) {
			$html .= '<div class="mai-address-item"><span class="street-address-2">' . esc_html( $street_2 ) .'</span></div>';
		}

		if ( $city || $state || $postcode || $country ) {
			$html .= '<div class="mai-address-item">';

				if ( $city ) {
					$html .= '<span class="locality" itemprop="addressLocality">' . esc_html( $city ) . '</span>';
				}

				if ( $state ) {
					$html .= '<span class="region" itemprop="addressRegion">&nbsp;' . esc_html( $state ) . '</span>';
				}

				if ( $postcode ) {
					$html .= '<span class="postal-code" itemprop="postalCode">,&nbsp;' . esc_html( $postcode ) . '</span>';
				}

			$html .= '</div>';
		}

		if ( $country ) {
			$countries = mailocations_get_country_choices();
			$country   = isset( $countries[ $country ] ) ? $countries[ $country ] : $country;
			$html     .= '<div class="mai-address-item" itemprop="addressCountry">' . esc_html( $country ) . '</div>';
		}

	$html .= '</div>';

	return $html;
}

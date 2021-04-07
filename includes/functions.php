<?php

/**
 * Update the google map field from address data after a location is saved.
 *
 * @since 0.1.0
 *
 * @param int          $post_id     Post ID.
 * @param WP_Post      $post        Post object.
 * @param bool         $update      Whether this is an existing post being updated.
 * @param null|WP_Post $post_before Null for new posts, the WP_Post object prior
 *                                  to the update for updated posts.
 *
 * @return void
 */
// add_action( 'wp_after_insert_post', 'mailocations_maybe_update_map_field_og', 10, 4 );
function mailocations_maybe_update_map_field_og( $post_id, $post, $update, $post_before ) {
	if ( 'mai_location' !== $post->post_type ) {
		return;
	}

	mailocations_update_map_field( $post_id );
}

/**
 * Update the google map field from address data after a location is saved.
 *
 * @param int     $post_id Post ID.
 * @param WP_Post $post    Post object.
 * @param bool    $update  Whether this is an existing post being updated.
 */
add_action( 'save_post_mai_location', 'mailocations_maybe_update_map_field_ogg', 10, 3 );
function mailocations_maybe_update_map_field_ogg( $post_id, $post, $update ) {
	mailocations_update_map_field( $post_id );
}

// add_action( 'acf/save_post', 'mailocations_maybe_update_map_field' );
function mailocations_maybe_update_map_field( $post_id ) {
	if ( 'mai_location' !== get_post_type( $post_id ) ) {
		return;
	}

	mailocations_update_map_field( $post_id );
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
function mailocations_update_map_field( $post_id ) {
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
		'address_province',
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

	if ( ! is_array( $results ) && $results ) {
		return;
	}

	$result     = reset( $results );
	$formatted  = $result->formatted_address;
	$components = $result->address_components;

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

	ray( $update );

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
				case 'address_province':
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
function mailocations_get_address() {
	$html     = '';
	$post_id  = get_the_ID();
	$street   = get_post_meta( $post_id, 'address_street', true );
	$street_2 = get_post_meta( $post_id, 'address_street_2', true );
	$city     = get_post_meta( $post_id, 'address_city', true );
	$state    = get_post_meta( $post_id, 'address_state', true );
	$postcode = get_post_meta( $post_id, 'address_postcode', true );
	$country  = get_post_meta( $post_id, 'address_country', true );

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

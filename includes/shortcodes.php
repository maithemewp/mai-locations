<?php

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

add_shortcode( 'mai_location_address', 'mailocation_location_address_shortcode' );
/**
 * Gets formatted address.
 *
 * @since 0.1.0
 *
 * @return string
 */
function mailocation_location_address_shortcode( $atts ) {
	return mailocations_get_address( $atts );
}

add_shortcode( 'mai_location_phone', 'mailocation_location_phone_shortcode' );
/**
 * Gets formatted phone number.
 *
 * @since TBD
 *
 * @return string
 */
function mailocation_location_phone_shortcode( $atts ) {
	// Atts.
	$atts = shortcode_atts(
		[
			'before' => '',
			'link'   => true,
		],
		$atts,
		'mai_location_phone'
	);

	$atts['before'] = esc_html( $atts['before'] ); // Don't trim(). We want spaces.
	$atts['link']   = rest_sanitize_boolean( $atts['link'] );
	$phone          = esc_html( get_post_meta( get_the_ID(), 'location_phone', true ) );
	$country        = esc_html( get_post_meta( get_the_ID(), 'address_country', true ) );
	$country        = $country ?: '';

	if ( ! $phone ) {
		return;
	}

	$html = sprintf( '<div class="mai-location-phone"%s>', $atts['style'] ? sprintf( ' style="%s"', $atts['style'] ) : '' );
		$html .= $atts['before'];

		// Use country to format.
		if ( $country ) {
			$phonelib = \libphonenumber\PhoneNumberUtil::getInstance();
			$proto    = $phonelib->parse( $phone, $country );

			if ( $phonelib->isValidNumber( $proto ) ) {
				$tel = $phonelib->format( $proto, \libphonenumber\PhoneNumberFormat::INTERNATIONAL );

				if ( 'US' === $country ) {
					$formatted = $phonelib->format( $proto, \libphonenumber\PhoneNumberFormat::NATIONAL );
				} else {
					$formatted = $phonelib->format( $proto, \libphonenumber\PhoneNumberFormat::E164 );
				}
			}

		}
		// Fallback, no formatting.
		else {
			$tel       = (int) filter_var( $phone, FILTER_SANITIZE_NUMBER_INT );
			$formatted = $phone;
		}

		if ( $atts['link'] ) {
			$html .= sprintf( '<a href="tel://%s">', $tel );
		}

		$html .= $formatted;

		if ( $atts['link'] ) {
			$html .= '</a>';
		}

	$html .= '</div>';

	return $html;
}

add_shortcode( 'mai_location_url', 'mailocation_location_url_shortcode' );
/**
 * Gets formatted url.
 *
 * @since TBD
 *
 * @return string
 */
function mailocation_location_url_shortcode( $atts ) {
	// Atts.
	$atts = shortcode_atts(
		[
			'style'  => '',
			'before' => '',
		],
		$atts,
		'mai_location_url'
	);

	$atts['style']  = esc_attr( $atts['style'] );
	$atts['before'] = esc_html( $atts['before'] ); // Don't trim(). We want spaces.
	$url            = get_post_meta( get_the_ID(), 'location_url', true );

	if ( ! $url ) {
		return;
	}

	$html = sprintf( '<div class="mai-location-url"%s>', $atts['style'] ? sprintf( ' style="%s"', $atts['style'] ) : '' );
		$html .= $atts['before'];

		$parsed = wp_parse_url( $url, PHP_URL_HOST );

		if ( $parsed ) {
			$formatted = ltrim( $parsed, 'www.' );
		} else {
			$formatted = $url;
		}

		$html .= sprintf( '<a href="%s">%s</a>', esc_url( $url ), $formatted );

	$html .= '</div>';

	return $html;
}

add_shortcode( 'mai_location_email', 'mailocation_location_email_shortcode' );
/**
 * Gets formatted email number.
 *
 * @since TBD
 *
 * @return string
 */
function mailocation_location_email_shortcode( $atts ) {
	// Atts.
	$atts = shortcode_atts(
		[
			'style'  => '',
			'before' => '',
			'link'   => true,
		],
		$atts,
		'mai_location_email'
	);

	$atts['style']  = esc_attr( $atts['style'] );
	$atts['before'] = esc_html( $atts['before'] ); // Don't trim(). We want spaces.
	$email          = get_post_meta( get_the_ID(), 'location_email', true );
	$email          = sanitize_email( $email );
	$email          = antispambot( $email );

	if ( ! $email ) {
		return;
	}

	$html = sprintf( '<div class="mai-location-email"%s>', $atts['style'] ? sprintf( ' style="%s"', $atts['style'] ) : '' );
		$html .= $atts['before'];

		if ( $atts['link'] ) {
			$url   = esc_url( 'mailto:' . $email );
			$html .= sprintf( '<a href="%s">', $url );
		}

		$html .= $email;

		if ( $atts['link'] ) {
			$html .= '</a>';
		}

	$html .= '</div>';

	return $html;
}

add_shortcode( 'mai_location_distance', function( $atts ) {
	// Atts.
	$atts = shortcode_atts(
		[
			'before' => '',
			'after'  => '',
			'round'  => 2,
		],
		$atts,
		'mai_location_distance'
	);

	// Sanitize.
	$atts = [
		'before' => esc_html( $atts['before'] ),
		'after'  => esc_html( $atts['after'] ),
		'round'  => absint( $atts['round'] ),
	];

	// Get the distance.
	$post     = get_post( get_the_ID() );
	$distance = $post ? mailocations_get_distance( $post, $round = false ) : '';

	// Bail if no distance.
	if ( ! $distance ) {
		return;
	}

	// Rounding.
	if ( $atts['round'] ) {
		$distance = round( $distance, $atts['round'] );
	}

	// Add content before.
	if ( $atts['before'] ) {
		$distance = $atts['before'] . $distance;
	}

	// Add content after.
	if ( $atts['after'] ) {
		$distance .= $atts['after'];
	}

	return $distance;
});

add_shortcode( 'mai_locations_table', 'mailocation_location_table_shortcode' );
/**
 * Gets a locations table with edit location links/form.
 *
 * @since 0.1.0
 *
 * @return string
 */
function mailocation_location_table_shortcode( $atts ) {
	return mailocations_get_locations_table( 0, $atts );
}

add_shortcode( 'mai_locations_search', 'mailocation_locations_search_shortcode' );
/**
 *
 *
 * @since TBD
 *
 * @return string
 */
function mailocation_locations_search_shortcode( $atts ) {
	static $enqueued = false;

	// Atts.
	$atts = shortcode_atts(
		[
			'text' => __( 'Enter your address', 'mai-locations' ),
		],
		$atts,
		'mai_locations_search'
	);

	// Sanitize.
	$atts = [
		'text' => esc_html( $atts['text'] ),
	];

	if ( ! $enqueued ) {
		$file      = '/assets/js/mai-locations.js';
		$file_path = MAI_LOCATIONS_PLUGIN_DIR . $file;
		$file_url  = MAI_LOCATIONS_PLUGIN_URL . $file;

		if ( file_exists( $file_path ) ) {
			$version = MAI_LOCATIONS_VERSION . '.' . date( 'njYHi', filemtime( $file_path ) );
			// wp_enqueue_script( 'pfl-googlemaps', sprintf( 'https://maps.googleapis.com/maps/api/js?key=%s&libraries=places', pfl_get_googlemaps_api_key() ) );
			wp_enqueue_script( 'mailocations-autocomplete', $file_url, [], $version, true );
			wp_enqueue_script( 'mailocations-googlemaps', sprintf( 'https://maps.googleapis.com/maps/api/js?key=%s&v=quarterly&libraries=places&callback=initMap', pfl_get_googlemaps_api_key() ), [], $version, true );
			$enqueued = true;
		}
	}

	$value = isset( $_GET['address'] ) && ! empty( $_GET['address'] ) ? esc_html( $_GET['address'] ) : '';
	$lat   = isset( $_GET['lat'] ) && ! empty( $_GET['lat'] ) ? esc_html( $_GET['lat'] ) : '';
	$lng   = isset( $_GET['lng'] ) && ! empty( $_GET['lng'] ) ? esc_html( $_GET['lng'] ) : '';

	return sprintf( '<input type="text" id="mailocations-autocomplete" placeholder="%s" value="%s" />', $atts['text'], $value );
}

add_shortcode( 'mai_locations_filter_submit', 'mailocation_locations_filter_submit_shortcode' );
/**
 *
 *
 * @since TBD
 *
 * @return string
 */
function mailocation_locations_filter_submit_shortcode( $atts ) {
	// Atts.
	$atts = shortcode_atts(
		[
			'text' => __( 'Submit', 'mai-locations' ),
		],
		$atts,
		'mai_locations_filter_submit'
	);

	// Sanitize.
	$atts = [
		'text' => esc_html( $atts['text'] ),
	];

	return sprintf( '<button type="button" class="mailocations-filter-submit">%s</button>', $atts['text'] );
}

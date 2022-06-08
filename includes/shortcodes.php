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

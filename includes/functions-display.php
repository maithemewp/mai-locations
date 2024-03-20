<?php

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Gets a locations table.
 * Displays view/edit buttons.
 * When editing a location the table is replaced
 * with the ACF location fields.
 *
 * @since 0.1.0
 *
 * @param int   $user_id The user ID.
 * @param array $args    The table/form args.
 *
 * @return string
 */
function mailocations_get_locations_table( $user_id = 0, $args = [] ) {
	$table = new Mai_Locations_Locations_Table( $user_id, $args );
	return $table->get();
}

/**
 * Gets edit location form.
 *
 * @since 0.1.0
 *
 * @param array $args The form args.
 *
 * @return string
 */
function mailocations_get_location_edit_form( $args ) {
	$form = new Mai_Locations_Location_Form_Edit( $args );
	return $form->get();
}

/**
 * Gets create location form.
 *
 * @since TBD
 *
 * @param array $args The form args.
 *
 * @return string
 */
function mailocations_get_location_submission_form( $args ) {
	$form = new Mai_Locations_Location_Form_Submit( $args );
	return $form->get();
}

/**
 * Gets a formatted address from current post in the loop.
 *
 * @since 0.1.0
 *
 * @param array $args    The address args.
 * @param int   $post_id The post ID.
 *
 * @return string
 */
function mailocations_get_address( $args = [], $post_id = 0 ) {
	// Atts.
	$args = shortcode_atts(
		[
			'hide' => '', // street, street2, city, state, postcode, country
		],
		$args,
		'mai_location_address'
	);

	// Vars.
	$html      = '';
	$hide      = explode( ',', $args['hide'] );
	$hide      = array_map( 'esc_html', $hide );
	$hide      = array_map( 'trim', $hide );
	$hide      = array_flip( $hide );
	$post_id   = (int) $post_id ?: get_the_ID();
	$street    = ! isset( $hide['street'] ) ? get_post_meta( $post_id, 'address_street', true ) : '';
	$street_2  = ! isset( $hide['street2'] ) ? get_post_meta( $post_id, 'address_street_2', true ) : '';
	$city      = ! isset( $hide['city'] ) ? get_post_meta( $post_id, 'address_city', true ) : '';
	$state     = ! isset( $hide['state'] ) ? get_post_meta( $post_id, 'address_state', true ) : '';
	$state_int = ! isset( $hide['state'] ) ? get_post_meta( $post_id, 'address_state_int', true ) : '';
	$postcode  = ! isset( $hide['postcode'] ) ? get_post_meta( $post_id, 'address_postcode', true ) : '';
	$country   = ! isset( $hide['country'] ) ? get_post_meta( $post_id, 'address_country', true ) : '';
	$state     = $country && 'US' !== $country ? $state_int : $state; // Use state_int if non-US.

	// Bail if no address.
	if ( ! ( $street || $street_2 || $city || $state || $postcode || $country ) ) {
		return $html;
	}

	// Build HTML.
	$html .= '<div itemprop="address" itemscope itemtype="http://schema.org/PostalAddress" class="mai-address">';

		if ( $street ) {
			$html .= sprintf( '<div class="mai-address-item"><span class="street-address" itemprop="streetAddress">%s</span></div>', esc_html( $street ) );
		}

		if ( $street_2 ) {
			$html .= sprintf( '<div class="mai-address-item"><span class="street-address-2">%s</span></div>', esc_html( $street_2 ) );
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
			$html     .= sprintf( '<div class="mai-address-item" itemprop="addressCountry">%s</div>', esc_html( $country ) );
		}

	$html .= '</div>';

	return $html;
}

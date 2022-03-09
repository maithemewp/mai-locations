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

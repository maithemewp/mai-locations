<?php

add_shortcode( 'mai_location_address', 'mailocation_location_address_shortcode' );
/**
 * Gets formatted address.
 *
 * @return string
 */
function mailocation_location_address_shortcode( $atts ) {
	return mailocations_get_address( $atts );
}

// add_action( 'genesis_before_loop', function() {
// 	$meta = get_post_meta( get_the_ID() );
// 	vd( $meta );
// });


add_shortcode( 'mai_locations_table', 'mailocation_location_table_shortcode' );
function mailocation_location_table_shortcode( $atts ) {
	return mailocations_get_locations_table( 0, $atts );
}

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
	if ( ! is_user_logged_in() ) {
		return;
	}

	$user_id   = get_current_user_id();
	$user_id   = 120; // Temporarily 123work@comcast.net.
	$locations = new WP_Query(
		[
			'post_type'              => 'mai_location',
			'posts_per_page'         => 12,
			'post_status'            => 'publish',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'meta_query'             => [
				'relation' => 'AND',
				[
					'key'     => 'location_managers',
					'value'   => $user_id,
					'compare' => 'IN',
				],
			],
		]
	);

	$html = '';

	if ( $locations->have_posts() ) {

		$html .= '<table class="mai-locations-table">';

			$html .= '<thead>';
				$html .= '<tr>';
					$html .= sprintf( '<th colspan="2">%s</th>', __( 'Locations', 'mai-locations' ) );
					// $html .= sprintf( '<th>%s</th>', __( 'Actions', 'mai-locations' ) );
				$html .= '</tr>';
			$html .= '</thead>';

			$html .= '<tbody>';

				while ( $locations->have_posts() ) : $locations->the_post();
					$classes = 'button button-secondary button-small';

					$html .= '<tr>';
						$html .= sprintf( '<th><a href="%s">%s</a></th>',
							get_permalink(),
							get_the_title()
						);

						$html .= sprintf( '<th style="text-align:right;"><a style="margin-right:6px;" class="%s" href="%s">%s</a><a class="%s" href="%s">%s</a></th>',
							$classes,
							get_edit_post_link(),
							__( 'Edit', 'mai-locations' ),
							$classes,
							get_permalink(),
							__( 'View', 'mai-locations' )
						);
					$html .= '</tr>';

				endwhile;

			$html .= '</tbody>';
		$html .= '</table> ';
	}
	wp_reset_postdata();

	return $html;
}

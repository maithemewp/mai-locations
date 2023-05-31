<?php

// Prevent direct file access.
defined( 'ABSPATH' ) || die;

add_action( 'acf/init', 'mailocations_register_locations_map_block' );
/**
 * Register Mai Location Filter block.
 *
 * @since TBD
 *
 * @return void
 */
function mailocations_register_locations_map_block() {
	register_block_type( __DIR__ . '/block.json' );
}

/**
 * Callback function to render the block.
 *
 * @since TBD
 *
 * @param array    $attributes The block attributes.
 * @param string   $content The block content.
 * @param bool     $is_preview Whether or not the block is being rendered for editing preview.
 * @param int      $post_id The current post being edited or viewed.
 * @param WP_Block $wp_block The block instance (since WP 5.5).
 * @param array    $context The block context array.
 *
 * @return void
 */
function mailocations_do_locations_map_block( $attributes, $content, $is_preview, $post_id, $wp_block, $context ) {

	// TODO: Do static map of US if $is_preview.

	// Enqueue script.
	echo '<script src="https://unpkg.com/@googlemaps/markerclusterer/dist/index.min.js"></script>';
	// wp_enqueue_script( 'mailocations-markerclusterer' );
	wp_enqueue_script( 'mailocations-filters' );
	wp_enqueue_script( 'mailocations-googlemaps' );

	// Maybe load CSS.
	echo mailocations_get_stylesheet_link( 'mai-locations-filters' );

	if ( have_posts() ) :
		printf( '<div class="mailocations-map" data-zoom="%s">', 12 );

		while ( have_posts() ) : the_post();
			$post_id = get_the_ID();
			$lat     = get_post_meta( $post_id, 'location_lat', true );
			$lng     = get_post_meta( $post_id, 'location_lng', true );

			if ( ! ( $lat && $lng ) ) {
				continue;
			}

			printf( '<div class="marker" data-lat="%s" data-lng="%s">', esc_html( $lat ), esc_html( $lng ) );
				printf( '<strong><a href="%s">%s</a></strong>', get_permalink(), get_the_title() );
				echo mailocations_get_address();
			echo '</div>';

		endwhile;

		echo '</div>';
	endif;
}

add_action( 'acf/init', 'mailocations_register_locations_map_field_group' );
/**
 * Register field group.
 *
 * @since TBD
 *
 * @return void
 */
function mailocations_register_locations_map_field_group() {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	acf_add_local_field_group(
		[
			'key'    => 'mailocations_locations_map_field_group',
			'title'  => __( 'Mai Locations Map', 'mai-locations' ),
			'fields' => [
				// [
				// 	'key'           => 'mailocations_map_placeholder',
				// 	'label'         => __( 'Placeholder', 'mai-locations' ),
				// 	'instructions'  => ! mailocations_get_google_maps_api_key() ? __( 'Google Maps API key missing!', 'mailocations' ) : '',
				// 	'name'          => 'placeholder',
				// 	'type'          => 'text',
				// 	'placeholder'   => __( 'Enter your address', 'mai-locations' ),
				// ],
			],
			'location' => [
				[
					[
						'param'    => 'block',
						'operator' => '==',
						'value'    => 'acf/mai-locations-map',
					],
				],
			],
		]
	);
}
<?php

// Prevent direct file access.
defined( 'ABSPATH' ) || die;

add_action( 'acf/init', 'mailocations_register_locations_map_block' );
/**
 * Register Mai Location Map block.
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
	// Enqueue JS.
	wp_enqueue_script( 'mai-locations' );

	// Maybe load CSS.
	echo mailocations_get_stylesheet_link( 'mai-locations' );

	// Back end.
	if ( $is_preview ) {
		// Static image.
		printf( '<div style="aspect-ratio:3/2;"><img style="display:block;height:100%%;width:100%%;position:absolute;top:0;left:0;object-fit:cover" width="800" height="533" src="%s/assets/images/map.png"/></div>', MAI_LOCATIONS_PLUGIN_URL );
	}
	// Front end.
	else {
		global $wp_query;

		if ( $wp_query ) {
			printf( '<div class="mailocations-map" data-zoom="%s">', 7 );

			if ( $wp_query->posts ) {
				foreach ( $wp_query->posts as $post ) {
					$lat = get_post_meta( $post->ID, 'location_lat', true );
					$lng = get_post_meta( $post->ID, 'location_lng', true );

					if ( ! ( $lat && $lng ) ) {
						continue;
					}

					ray( mailocations_get_address( [], $post->ID ) );

					printf( '<div style="display:block;" class="marker" data-lat="%s" data-lng="%s">', esc_html( $lat ), esc_html( $lng ) );
						printf( '<strong><a href="%s">%s</a></strong>', get_permalink( $post->ID ), get_the_title( $post->ID ) );
						echo mailocations_get_address( [], $post->ID );
					echo '</div>';
				}
			}

			echo '</div>';
		}
	}
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
<?php

// Prevent direct file access.
defined( 'ABSPATH' ) || die;

add_action( 'acf/init', 'mailocations_register_locations_count_block' );
/**
 * Register Mai Location Count block.
 *
 * @since TBD
 *
 * @return void
 */
function mailocations_register_locations_count_block() {
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
function mailocations_do_locations_count_block( $attributes, $content, $is_preview, $post_id, $wp_block, $context ) {
	printf( '%s %s %s %s %s', __( 'Showing', 'mai-locations' ), $wp_query->post_count, __( 'of', 'mai-locations' ), $wp_query->found_posts, mailocations_get_plural() );
}

add_action( 'acf/init', 'mailocations_register_locations_count_field_group' );
/**
 * Register field group.
 *
 * @since TBD
 *
 * @return void
 */
function mailocations_register_locations_count_field_group() {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	acf_add_local_field_group(
		[
			'key'    => 'mailocations_locations_count_field_group',
			'title'  => __( 'Mai Locations Map', 'mai-locations' ),
			'fields' => [
				// [
				// 	'key'           => 'mailocations_count_placeholder',
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
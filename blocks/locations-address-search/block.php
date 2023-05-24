<?php

// Prevent direct file access.
defined( 'ABSPATH' ) || die;

add_action( 'acf/init', 'mailocations_register_locations_address_search_block' );
/**
 * Register Mai Location Filter block.
 *
 * @since TBD
 *
 * @return void
 */
function mailocations_register_locations_address_search_block() {
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
function mailocations_do_locations_address_search_block( $attributes, $content, $is_preview, $post_id, $wp_block, $context ) {
	static $enqueued = false;

	$placeholder = get_field( 'placeholder' );
	$placeholder = $placeholder ?: __( 'Enter your address', 'mai-locations' );
	$address     = isset( $_GET['address'] ) && ! empty( $_GET['address'] ) ? esc_html( $_GET['address'] ) : '';

	// Maybe enqueue scripts.
	if ( ! $enqueued && ! $is_preview ) {
		$file      = '/assets/js/mai-locations.js';
		$file_path = MAI_LOCATIONS_PLUGIN_DIR . $file;
		$file_url  = MAI_LOCATIONS_PLUGIN_URL . $file;

		if ( file_exists( $file_path ) ) {
			$version = MAI_LOCATIONS_VERSION . '.' . date( 'njYHi', filemtime( $file_path ) );
			wp_enqueue_script( 'mailocations-autocomplete', $file_url, [], $version, true );
			wp_localize_script( 'mailocations-autocomplete', 'maiLocationsVars', [ 'taxonomies' => array_keys( mailocations_get_location_taxonomies() ) ] );
			wp_enqueue_script( 'mailocations-googlemaps', sprintf( 'https://maps.googleapis.com/maps/api/js?key=%s&v=quarterly&libraries=places&callback=initMap', pfl_get_googlemaps_api_key() ), [], $version, true );
			$enqueued = true;
		}
	}

	$clear = sprintf( '<button class="mailocations-autocomplete-clear mailocations-clear">%s</button>', __( 'Clear', 'mai-locations' ) );

	printf( '<div class="mailocations-autocomplete-container"><input type="text" class="mailocations-autocomplete" placeholder="%s" value="%s" />%s</div>', $placeholder, $address, $clear );
}


add_action( 'acf/init', 'mailocations_register_locations_address_search_field_group' );
/**
 * Register field group.
 *
 * @since TBD
 *
 * @return void
 */
function mailocations_register_locations_address_search_field_group() {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	acf_add_local_field_group(
		[
			'key'    => 'mailocations_locations_address_search_field_group',
			'title'  => __( 'Mai Locations Address Search', 'mai-locations' ),
			'fields' => [
				[
					'key'           => 'mailocations_address_search_placeholder',
					'label'         => __( 'Placeholder', 'mai-locations' ),
					'name'          => 'placeholder',
					'type'          => 'text',
					'placeholder'   => __( 'Enter your address', 'mai-locations' ),
				],
			],
			'location' => [
				[
					[
						'param'    => 'block',
						'operator' => '==',
						'value'    => 'acf/mai-locations-address-search',
					],
				],
			],
		]
	);
}
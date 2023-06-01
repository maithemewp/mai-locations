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
	$params      = wp_parse_args( mailocations_get_query_params(), mailocations_get_query_defaults() );
	$placeholder = get_field( 'placeholder' );
	$placeholder = $placeholder ?: __( 'Enter your address', 'mai-locations' );
	$address     = $params['address'];
	$distances   = mailocations_get_option( 'distances' );
	$distance    = $params['distance'];
	$units       = mailocations_get_option( 'units' );
	$unit        = $params['unit'];

	// Maybe enqueue scripts.
	if ( ! $is_preview ) {
		wp_enqueue_script( 'mai-locations' );
		// wp_enqueue_script( 'mai-locations-googlemaps' );
	}

	// Maybe load CSS.
	echo mailocations_get_stylesheet_link( 'mai-locations' );

	echo '<div class="mailocations-autocomplete-container">';
		echo '<div class="mailocations-autocomplete-input-container">';
			$value = ! $is_preview ? sprintf( ' value="%s"', $address ) : ''; // Can't have value attribute or React balks.
			printf( '<input type="text" class="mailocations-autocomplete" placeholder="%s"%s>', $placeholder, $value );

			if ( ! $is_preview  ) {
				printf( '<button class="mailocations-autocomplete-clear">%s</button>', __( 'Clear', 'mai-locations' ) );
			}
		echo '</div>';

		if ( $distances && count( $distances ) > 1 ) {
			$multiple = count( $units ) > 1;

			echo '<select class="mailocations-autocomplete-distance">';
			foreach ( $distances as $value ) {
					$label    = $multiple ? $value : $value . ' ' . $unit;
					$selected = ! $is_preview && (int) $value === (int) $distance ? ' selected' : '';
					$value    = ! $is_preview ? sprintf( ' value="%s"', $value ) : ''; // Can't have value attribute or React balks.
					printf( '<option %s%s>%s</option>', $value, $selected, $label );
				}
			echo '</select>';

			if ( $units && $multiple ) {
				echo '<select class="mailocations-autocomplete-unit">';
				foreach ( $units as $value ) {
					$raw      = $value;
					$value    = ! $is_preview ? sprintf( ' value="%s"', $raw ) : '';
					$selected = ! $is_preview && $raw === $unit ? ' selected' : '';
					printf ( '<option %s%s>%s</option>', $value, $selected, $raw );

				}
				echo '</select>';
			}
		}
	echo '</div>';
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
					'instructions'  => ! mailocations_get_google_maps_api_key() ? __( 'Google Maps API key missing!', 'mailocations' ) : '',
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
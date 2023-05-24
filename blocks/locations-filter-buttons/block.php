<?php

add_filter( 'register_block_type_args', 'mailocations_render_filter_submit_button_variation', 10, 2 );
/**
 * Registers Mai Locations Filter Submit button variation.
 *
 * @param array  $args
 * @param string $block_type
 *
 * @return array
 */
function mailocations_render_filter_submit_button_variation( $args, $block_type ) {
	if ( 'core/buttons' !== $block_type ) {
		return $args;
	}

	$args['attributes']['variantType'] = [ 'type' => 'string' ];
	$args['variations']                = [
		[
			'name'       => 'mailocations-filter-submit',
			'title'      => __( 'Mai Locations Filter Submit', 'mai-locations' ),
			'attributes' => [
				'variantType' => 'mailocations-filter-submit'
			],
		],
		[
			'name'       => 'mailocations-filter-clear',
			'title'      => __( 'Mai Locations Filter Clear', 'mai-locations' ),
			'attributes' => [
				'variantType' => 'mailocations-filter-clear'
			],
		]
	];

	return $args;
}

add_filter( 'render_block_core/buttons', 'mailocations_render_filter_submit_button_block', 10, 3 );
/**
 * Convert the <a> tag to <button> and modify attributes.
 *
 * @since TBD
 *
 * @param string   $block_content The block content.
 * @param array    $block         The full block, including name and attributes.
 * @param WP_Block $instance      The block instance.
 *
 * @return string
 */
function mailocations_render_filter_submit_button_block( $block_content, $parsed_block, $wp_block ) {
	if ( ! isset( $parsed_block['attrs']['variantType'] ) || ! in_array( $parsed_block['attrs']['variantType'], [ 'mailocations-filter-submit', 'mailocations-filter-clear' ] ) ) {
		return $block_content;
	}

	// Check if submit, and convert tag.
	$clear = 'mailocations-filter-clear' === $parsed_block['attrs']['variantType'];

	// Return empty if it's a clear button without active filters.
	if ( $clear && ! mailocations_is_filtered_locations() ) {
		return '';
	}

	$block_content = str_replace( '<a ', '<button ', $block_content );
	$block_content = str_replace( '</a>', '</button>', $block_content );

	// Setup the tag processor.
	$tags = new WP_HTML_Tag_Processor( $block_content );

	// If button, modify markup.
	while ( $tags->next_tag( 'button' ) ) {
		// Remove href.
		$tags->remove_attribute( 'href' );
		// Add class.
		if ( $clear ) {
			$tags->add_class( 'mailocations-filter-clear' );
		} else {
			$tags->add_class( 'mailocations-filter-submit' );
		}
	}

	return $tags->get_updated_html();
}

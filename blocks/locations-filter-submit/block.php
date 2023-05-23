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
			'title'      => 'Mai Locations Filter Submit',
			'attributes' => [
				'variantType' => 'mailocations-filter-submit'
			],
		]
	];

	return $args;
}

add_filter( 'render_block_core/buttons', 'mailocations_render_filter_submit_button_block', 10, 3 );
/**
 * Convert the <a> tag to <button> and our custom attributes.
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
	if ( ! isset( $parsed_block['attrs']['variantType'] ) || 'mailocations-filter-submit' !== $parsed_block['attrs']['variantType'] ) {
		return $block_content;
	}

	// Swap tag.
	$block_content = str_replace( '<a ', '<button ', $block_content );
	$block_content = str_replace( '</a>', '</button>', $block_content );

	// Setup the tag processor.
	$tags = new WP_HTML_Tag_Processor( $block_content );

	// Remove href tags.
	while ( $tags->next_tag( 'button' ) ) {
		$tags->remove_attribute( 'href' );
		$tags->add_class( 'mailocations-filter-submit' );
	}

	return $tags->get_updated_html();
}

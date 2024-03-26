<?php

// Prevent direct file access.
defined( 'ABSPATH' ) || die;

/**
 * The filter button block class.
 *
 * @since TBD
 */
class Mai_Locations_Filter_Submit_Block {
	/**
	 * Construct the class.
	 */
	function __construct() {
		$this->hooks();
	}

	/**
	 * Add hooks.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	function hooks() {
		add_filter( 'register_block_type_args',  [ $this, 'render_filter_submit_button_variation' ], 10, 2 );
		add_filter( 'render_block_core/buttons', [ $this, 'render_filter_submit_button_block' ], 10, 3 );
	}

	/**
	 * Registers Mai Locations Clear Filters button variation.
	 *
	 * @since TBD
	 *
	 * @param array  $args
	 * @param string $block_type
	 *
	 * @return array
	 */
	function render_filter_submit_button_variation( $args, $block_type ) {
		if ( ! class_exists( 'WP_HTML_Tag_Processor' ) ) {
			return $args;
		}

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
			]
		];

		return $args;
	}

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
	function render_filter_submit_button_block( $block_content, $parsed_block, $wp_block ) {
		if ( ! class_exists( 'WP_HTML_Tag_Processor' ) ) {
			return $block_content;
		}

		if ( ! isset( $parsed_block['attrs']['variantType'] ) || 'mailocations-filter-submit' !== $parsed_block['attrs']['variantType'] ) {
			return $block_content;
		}

		// Maybe load CSS.
		$block_content = mailocations_get_stylesheet_link( 'mai-locations' ) . $block_content;

		// Replace tags.
		$block_content = str_replace( '<a ', '<button ', $block_content );
		$block_content = str_replace( '</a>', '</button>', $block_content );

		// Setup the tag processor.
		$tags = new WP_HTML_Tag_Processor( $block_content );

		// If button, modify markup.
		while ( $tags->next_tag( 'button' ) ) {
			// Remove href and add class.
			$tags->remove_attribute( 'href' );
			$tags->add_class( 'mailocations-filter-submit' );
		}

		return $tags->get_updated_html();
	}
}
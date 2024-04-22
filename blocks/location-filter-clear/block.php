<?php

// Prevent direct file access.
defined( 'ABSPATH' ) || die;

/**
 * The clear filters block class.
 *
 * @since TBD
 */
class Mai_Locations_Filter_Clear_Block {
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
		add_filter( 'register_block_type_args',  [ $this, 'add_block_attribute' ], 10, 2 );
		add_filter( 'get_block_type_variations', [ $this, 'add_block_variation' ], 10, 2 );
		add_filter( 'render_block_core/button',  [ $this, 'render_block_variation' ], 10, 3 );
	}

	/**
	 * Registers custom block attribute.
	 *
	 * @since TBD
	 *
	 * @param array  $args
	 * @param string $block_type
	 *
	 * @return array
	 */
	function add_block_attribute( $args, $block_type ) {
		if ( ! class_exists( 'WP_HTML_Tag_Processor' ) ) {
			return $args;
		}

		// if ( 'core/buttons' !== $block_type ) {
		if ( 'core/button' !== $block_type ) {
			return $args;
		}

		$args['attributes']['maiLocationsFilterClear'] = [ 'type' => 'boolean' ];

		return $args;
	}

	/**
	 * Registers block variation.
	 *
	 * @since TBD
	 *
	 * @link https://developer.wordpress.org/news/2024/03/14/how-to-register-block-variations-with-php/
	 *
	 * @param array  $variations
	 * @param string $block_type
	 *
	 * @return array
	 */
	function add_block_variation( $variations, $block_type ) {
		if ( 'core/button' !== $block_type->name ) {
			return $variations;
		}

		$variations[] = [
			'title'      => __( 'Mai Locations Filter Clear', 'mai-locations' ),
			'name'       => 'mailocations-filter-clear',
			'isActive'   => [ 'maiLocationsFilterClear' ],
			'attributes' => [
				'maiLocationsFilterClear' => true,
				'metadata'                => [
					'bindings' => [
						'url' => [
							'source' => 'mai/locations',
							'args'   => [
								'key' => 'filterClear',
							],
						],
					],
				],
			],
		];

		return $variations;
	}

	/**
	 * Modifies the url of the button to clear filters.
	 *
	 * @since TBD
	 *
	 * @param string   $block_content The block content.
	 * @param array    $block         The full block, including name and attributes.
	 * @param WP_Block $instance      The block instance.
	 *
	 * @return string
	 */
	function render_block_variation( $block_content, $parsed_block, $wp_block ) {
		if ( ! class_exists( 'WP_HTML_Tag_Processor' ) ) {
			return $block_content;
		}

		// Bail if not our block variation.
		if ( ! isset( $parsed_block['attrs']['maiLocationsFilterClear'] ) || ! $parsed_block['attrs']['maiLocationsFilterClear'] ) {
			return $block_content;
		}

		// Return empty (don't show) if there are no active filters.
		if ( ! mailocations_is_filtered_locations() ) {
			return '';
		}

		// Maybe load CSS.
		$block_content = mailocations_get_stylesheet_link( 'mai-locations' ) . $block_content;

		// Get current url without all query args.
		$current_url = mailocations_get_current_url_clean( $_GET );

		// Setup the tag processor.
		$tags = new WP_HTML_Tag_Processor( $block_content );

		// If button, modify markup.
		while ( $tags->next_tag( 'a' ) ) {
			$tags->set_attribute( 'href', esc_url( $current_url ) );
			$tags->add_class( 'mailocations-filter-clear' );
		}

		return $tags->get_updated_html();
	}
}
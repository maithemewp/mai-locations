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

		if ( 'core/button' !== $block_type ) {
			return $args;
		}

		$args['attributes']['maiLocationsFilterSubmit'] = [ 'type' => 'boolean' ];

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
			'title'      => __( 'Mai Locations Filter Submit', 'mai-locations' ),
			'name'       => 'mailocations-filter-submit',
			'isActive'   => [ 'maiLocationsFilterSubmit' ],
			'attributes' => [
				'maiLocationsFilterSubmit' => true,
				'metadata'                 => [
					'bindings' => [
						'url' => [
							'source' => 'mai/locations',
							'args'   => [
								'key' => 'filterSubmit',
							],
						],
					],
				],
			],
		];

		return $variations;
	}

	/**
	 * Replace the button with an input submit.
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
		if ( ! isset( $parsed_block['attrs']['maiLocationsFilterSubmit'] ) || ! $parsed_block['attrs']['maiLocationsFilterSubmit'] ) {
			return $block_content;
		}

		// Replace `<a>` with `<button>`.
		$block_content = preg_replace( '/<a\b([^>]*)>(.*?)<\/a>/i', '<button$1>$2</button>', $block_content );

		// Setup the tag processor.
		$tags  = new WP_HTML_Tag_Processor( $block_content );

		// If button, modify markup.
		while ( $tags->next_tag( [ 'tag_name' => 'button', 'class_name' => 'wp-block-button__link' ] ) ) {
			$tags->add_class( 'mailocations-filter-submit' );
			break;
		}

		// Save the updated HTML.
		$block_content = $tags->get_updated_html();

		return $block_content;
	}
}
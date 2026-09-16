<?php

declare(strict_types=1);

namespace Mai\Locations\Blocks;

use WP_HTML_Tag_Processor;

// Prevent direct file access.
defined( 'ABSPATH' ) || die;

/**
 * The clear filters button, a core/button variation.
 *
 * Was Mai_Locations_Filter_Clear_Block in blocks/location-filter-clear/block.php. That name
 * still works, via inc/aliases.php. This block has no block.json, because it is a variation of
 * a core block rather than a block of its own.
 *
 * @since TBD
 */
class FilterClearBlock {

	/**
	 * Construct the class.
	 */
	public function __construct() {
		$this->hooks();
	}

	/**
	 * Add hooks.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	public function hooks(): void {
		add_filter( 'register_block_type_args',  [ $this, 'add_block_attribute' ], 10, 2 );
		add_filter( 'get_block_type_variations', [ $this, 'add_block_variation' ], 10, 2 );
		add_filter( 'render_block_core/button',  [ $this, 'render_block_variation' ], 10, 3 );
	}

	/**
	 * Registers the custom block attribute.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $args       The block type args.
	 * @param string               $block_type The block type name.
	 *
	 * @return array<string, mixed>
	 */
	public function add_block_attribute( $args, $block_type ) {
		if ( ! class_exists( 'WP_HTML_Tag_Processor' ) ) {
			return $args;
		}

		if ( 'core/button' !== $block_type ) {
			return $args;
		}

		$args['attributes']['maiLocationsFilterClear'] = [ 'type' => 'boolean' ];

		return $args;
	}

	/**
	 * Registers the block variation.
	 *
	 * Core passes a WP_Block_Type object here, not a string, whatever the old docblock said.
	 *
	 * @since TBD
	 *
	 * @link https://developer.wordpress.org/news/2024/03/14/how-to-register-block-variations-with-php/
	 *
	 * @param array<int, array<string, mixed>> $variations The existing variations.
	 * @param \WP_Block_Type                   $block_type The block type.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function add_block_variation( $variations, $block_type ) {
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
	 * Points the button at the current URL with the filters stripped off.
	 *
	 * @since TBD
	 *
	 * @param string               $block_content The block content.
	 * @param array<string, mixed> $parsed_block  The full block, including name and attributes.
	 * @param \WP_Block            $wp_block      The block instance.
	 *
	 * @return string
	 */
	public function render_block_variation( $block_content, $parsed_block, $wp_block ) {
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

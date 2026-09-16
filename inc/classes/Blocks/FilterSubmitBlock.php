<?php

declare(strict_types=1);

namespace Mai\Locations\Blocks;

use WP_HTML_Tag_Processor;

// Prevent direct file access.
defined( 'ABSPATH' ) || die;

/**
 * The filter submit button, a core/button variation.
 *
 * Was Mai_Locations_Filter_Submit_Block in blocks/location-filter-submit/block.php. That name
 * still works, via inc/aliases.php. This block has no block.json, because it is a variation of
 * a core block rather than a block of its own.
 *
 * @since TBD
 */
class FilterSubmitBlock {

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

		$args['attributes']['maiLocationsFilterSubmit'] = [ 'type' => 'boolean' ];

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
	 * Replaces the link with a submit button.
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
		if ( ! isset( $parsed_block['attrs']['maiLocationsFilterSubmit'] ) || ! $parsed_block['attrs']['maiLocationsFilterSubmit'] ) {
			return $block_content;
		}

		// Replace `<a>` with `<button>`.
		$block_content = preg_replace( '/<a\b([^>]*)>(.*?)<\/a>/i', '<button$1>$2</button>', $block_content );

		// Setup the tag processor.
		$tags = new WP_HTML_Tag_Processor( $block_content );

		// If button, modify markup.
		while ( $tags->next_tag( [ 'tag_name' => 'button', 'class_name' => 'wp-block-button__link' ] ) ) {
			$tags->set_attribute( 'type', 'submit' );
			$tags->add_class( 'mailocations-filter-submit' );
			// The link's href came through the swap and stayed on the button, where it means
			// nothing. Fixed September 16, 2026.
			$tags->remove_attribute( 'href' );
			break;
		}

		// Save the updated HTML.
		$block_content = $tags->get_updated_html();

		return $block_content;
	}
}

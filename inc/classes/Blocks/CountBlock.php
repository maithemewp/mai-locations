<?php

declare(strict_types=1);

namespace Mai\Locations\Blocks;

// Prevent direct file access.
defined( 'ABSPATH' ) || die;

/**
 * The locations count block.
 *
 * Was Mai_Locations_Count_Block in blocks/location-count/block.php. That name still works, via
 * inc/aliases.php. block.json stays in blocks/location-count/, and register_block_type() takes
 * that folder.
 *
 * @since TBD
 */
class CountBlock {

	/**
	 * Construct the class.
	 */
	public function __construct() {
		$this->hooks();
	}

	/**
	 * Add hooks.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function hooks(): void {
		add_action( 'acf/init', [ $this, 'register_block' ] );
		add_action( 'acf/init', [ $this, 'register_field_group' ] );
	}

	/**
	 * Registers the block.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function register_block(): void {
		register_block_type( MAI_LOCATIONS_PLUGIN_DIR . 'blocks/location-count',
			[
				'render_callback' => [ $this, 'render_block' ],
			]
		);
	}

	/**
	 * Renders the block.
	 *
	 * TODO: unset settings come back as null from get_field(), so the defaults in block.json are
	 * lost and an empty block renders "0  0". See TODO.md.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $attributes The block attributes.
	 * @param string               $content    The block content.
	 * @param bool                 $is_preview Whether the block is rendering for an editor preview.
	 * @param int                  $post_id    The current post being edited or viewed.
	 * @param \WP_Block            $wp_block   The block instance.
	 * @param array<string, mixed> $context    The block context array.
	 *
	 * @return void
	 */
	public function render_block( $attributes, $content, $is_preview, $post_id, $wp_block, $context ): void {
		if ( $is_preview ) {
			$number = 123;
			$total  = 456;
		} else {
			global $wp_query;
			$number = $wp_query->post_count;
			$total  = $wp_query->found_posts;
		}

		// Values.
		$before    = wp_kses_post( (string) get_field( 'before' ) );
		$separator = wp_kses_post( (string) get_field( 'separator' ) );
		$after     = wp_kses_post( (string) get_field( 'after' ) );
		$count     = sprintf( '%s %s %s %s %s', $before, absint( $number ), $separator, absint( $total ), $after );

		printf( '<p class="mailocations-count">%s</p>', trim( $count ) );
	}

	/**
	 * Registers the block's field group.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	public function register_field_group(): void {
		if ( ! function_exists( 'acf_add_local_field_group' ) ) {
			return;
		}

		acf_add_local_field_group(
			[
				'key'    => 'mailocations_locations_count_field_group',
				'title'  => __( 'Mai Locations Count', 'mai-locations' ),
				'fields' => [
					[
						'key'           => 'mailocations_count_before',
						'label'         => __( 'Before', 'mai-locations' ),
						'name'          => 'before',
						'type'          => 'text',
						'default_value' => __( 'Showing', 'mai-locations' ),
					],
					[
						'key'           => 'mailocations_count_separator',
						'label'         => __( 'Separator', 'mai-locations' ),
						'name'          => 'separator',
						'type'          => 'text',
						'default_value' => __( 'of', 'mai-locations' ),
					],
					[
						'key'           => 'mailocations_count_after',
						'label'         => __( 'After', 'mai-locations' ),
						'name'          => 'after',
						'type'          => 'text',
						'default_value' => mailocations_get_plural(),
					],
				],
				'location' => [
					[
						[
							'param'    => 'block',
							'operator' => '==',
							'value'    => 'acf/mai-locations-count',
						],
					],
				],
			]
		);
	}
}

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
		$parts = [
			$this->setting( 'before', 'mailocations_count_before' ),
			(string) absint( $number ),
			$this->setting( 'separator', 'mailocations_count_separator' ),
			(string) absint( $total ),
			$this->setting( 'after', 'mailocations_count_after' ),
		];

		// Skip the empty parts, so a cleared setting does not leave a double space behind.
		$count = implode( ' ', array_filter( $parts, static fn( string $part ): bool => '' !== $part ) );

		printf( '<p class="mailocations-count">%s</p>', $count );
	}

	/**
	 * Gets one of the block's text settings.
	 *
	 * A setting that was never saved comes back from get_field() as null, which used to print as
	 * an empty string and lose the field's default. A setting the user cleared comes back as an
	 * empty string and stays empty. Fixed September 16, 2026.
	 *
	 * @since TBD
	 *
	 * @param string $name The field name.
	 * @param string $key  The field key, used to read its registered default.
	 *
	 * @return string
	 */
	private function setting( string $name, string $key ): string {
		$value = get_field( $name );

		if ( is_null( $value ) || false === $value ) {
			$field = acf_get_field( $key );
			$value = is_array( $field ) ? ( $field['default_value'] ?? '' ) : '';
		}

		return wp_kses_post( (string) $value );
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

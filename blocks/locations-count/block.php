<?php

// Prevent direct file access.
defined( 'ABSPATH' ) || die;

class Mai_Locations_Locations_Count_Block {
	/**
	 * Construct the class.
	 */
	function __construct() {
		$this->hooks();
	}

	/**
	 * Add hooks.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	function hooks() {
		add_action( 'acf/init', [ $this, 'register_block' ] );
		add_action( 'acf/init', [ $this, 'register_field_group' ] );
	}

	/**
	 * Registers block.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	function register_block() {
		register_block_type( __DIR__ . '/block.json',
			[
				'render_callback' => [ $this, 'render_block' ],
			]
		);
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
	function render_block( $attributes, $content, $is_preview, $post_id, $wp_block, $context ) {
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
	 * Register field group.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	function register_field_group() {
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
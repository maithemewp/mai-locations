<?php

// Prevent direct file access.
defined( 'ABSPATH' ) || die;

/**
 * The locations map block class.
 *
 * @since TBD
 */
class Mai_Locations_Table_Block {
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
	 * Callback function to render the Mai Location Table block.
	 *
	 * @since 0.1.0
	 *
	 * @param array    $attributes The block attributes.
	 * @param string   $content    The block content.
	 * @param bool     $is_preview Whether or not the block is being rendered for editing preview.
	 * @param int      $post_id    The current post being edited or viewed.
	 * @param WP_Block $block      The block instance (since WP 5.5).
	 *
	 * @return void
	 */
	function render_block( $attributes, $content, $is_preview, $post_id, $block ) {
		$args = [
			'title'      => get_field( 'locations_table_title' ),
			'header'     => get_field( 'locations_table_header' ),
			'no_results' => get_field( 'locations_no_results' ),
			'redirect'   => get_field( 'location_redirect' ),
			'fields'     => (array) get_field( 'location_fields' ),
			'class'      => isset( $attributes['className'] ) && ! empty( $attributes['className'] ) ? $attributes['className'] : '',
			'align'      => isset( $attributes['align'] ) ? esc_html( $attributes['align'] ) : '',
		];

		echo mailocations_get_locations_table( 0, $args );
	}

	/**
	 * Add field group.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	function register_field_group() {
		$plural   = mailocations_get_plural();
		$singular = mailocations_get_singular();

		// Locations Table block.
		acf_add_local_field_group(
			[
				'title'  => __( 'Locations Table', 'mai-locations' ),
				'key'    => 'mailocations_locations_table_field_group',
				'fields' => [
					[
						'label'       => __( 'Title', 'mai-locations' ),
						'key'         => 'field_6071bfebbfdab',
						'name'        => 'locations_table_title',
						'type'        => 'text',
						'placeholder' => sprintf( '%s %s', __( 'My', 'mai-locations' ), $plural ),
					],
					[
						'label'       => __( 'Table Header', 'mai-location' ),
						'key'         => 'field_6071c00cbfdac',
						'name'        => 'locations_table_header',
						'type'        => 'text',
						'placeholder' => $plural,
					],
					[
						'label' => __( 'No Results Message', 'mai-location' ),
						'key'   => 'field_6071d22cdrdbd',
						'name'  => 'locations_no_results',
						'type'  => 'textarea',
						'rows'  => 2,
					],
					[
						// This field has to match what's in locations-table/block.php.
						'label'        => sprintf( '%s %s', $singular, __( 'Submission Redirect', 'mai-locations' ) ),
						'instructions' => __( 'Redirect to this URL after submission.', 'mai-locations' ),
						'key'          => 'mai_location_redirect',
						'name'         => 'location_redirect',
						'type'         => 'text',
					],
					[
						// This field has to match what's in location-submission/block.php.
						'label'         => sprintf( '%s %s', $singular, __( 'Edit Form Fields', 'mai-locations' ) ),
						'instructions'  => __( 'Allow editing of these fields.', 'mai-locations' ),
						'key'           => 'mai_location_fields',
						'name'          => 'location_fields',
						'type'          => 'checkbox',
						'multiple'      => 1,
						'allow_null'    => 0,
						'ui'            => 1,
						'ajax'          => 1,
						'choices'       => [],
						'default_value' => [],
						'wrapper'       => [
							'class' => 'mai-locations-sortable',
						],
					],
				],
				'location' => [
					[
						[
							'param'    => 'block',
							'operator' => '==',
							'value'    => 'acf/mai-locations-table',
						],
					],
				],
			]
		);
	}
}

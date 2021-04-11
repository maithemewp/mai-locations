<?php

add_action( 'acf/init', 'mai_register_locations_table_block' );
/**
 * Register Mai Location Table block.
 *
 * @since 0.1.0
 *
 * @return void
 */
function mai_register_locations_table_block() {
	if ( ! function_exists( 'acf_register_block_type' ) ) {
		return;
	}

	$plural = mailocations_get_plural();
	$lower  = strtolower( $plural );

	acf_register_block_type(
		[
			'name'            => 'mai-locations-table',
			'title'           => sprintf( __( 'Mai %s Table', 'mai-locations' ), $plural ),
			'description'     => sprintf( __( 'Display a users %s with view and edit buttons.', 'mai-locations' ), $lower ),
			'render_callback' => 'mailocations_do_locations_table_block',
			'category'        => 'widgets',
			'keywords'        => [ 'location', 'table', $lower ],
			'icon'            => 'location',
			'mode'            => 'preview',
			'align'           => false,
			'supports'        => [
				'align' => [ 'left', 'right' ],
			],
		]
	);
}

/**
 * Callback function to render the Mai Location Table block.
 *
 * @since 0.1.0
 *
 * @param array  $block      The block settings and attributes.
 * @param string $content    The block inner HTML (empty).
 * @param bool   $is_preview True during AJAX preview.
 * @param int    $post_id    The post ID this block is saved to.
 *
 * @return void
 */
function mailocations_do_locations_table_block( $block, $content = '', $is_preview = false, $post_id = 0 ) {
	$args = [
		'title'       => get_field( 'locations_table_title' ),
		'header'      => get_field( 'locations_table_header' ),
		'no_results'  => get_field( 'locations_no_results' ),
		'edit_fields' => (array) get_field( 'location_edit_fields' ),
		'class'       => isset( $block['className'] ) && ! empty( $block['className'] ) ? $block['className'] : '',
		'align'       => isset( $block['align'] ) ? esc_html( $block['align'] ) : '',
	];

	echo mailocations_get_locations_table( 0, $args );
}


add_action( 'acf/init', 'mailocations_add_block_field_group' );
/**
 * Add field group.
 *
 * @since 0.1.0
 *
 * @return void
 */
function mailocations_add_block_field_group() {
	$plural   = mailocations_get_plural();
	$singular = mailocations_get_singular();

	// Locations Table block.
	acf_add_local_field_group(
		[
			'key'    => 'group_6071bfd60bf2b',
			'title'  => sprintf( '%s %s', $plural, __( 'Table', 'mai-locations' ) ),
			'fields' => [
				[
					'key'         => 'field_6071bfebbfdab',
					'label'       => __( 'Title', 'mai-locations' ),
					'name'        => 'locations_table_title',
					'type'        => 'text',
					'placeholder' => sprintf( '%s %s', __( 'My', 'mai-locations' ), $plural ),
				],
				[
					'key'         => 'field_6071c00cbfdac',
					'label'       => __( 'Table Header', 'mai-location' ),
					'name'        => 'locations_table_header',
					'type'        => 'text',
					'placeholder' => $plural,
				],
				[
					'key'   => 'field_6071d22cdrdbd',
					'label' => __( 'No Results Message', 'mai-location' ),
					'name'  => 'locations_no_results',
					'type'  => 'textarea',
					'rows'  => 2,
				],
				[
					'key'          => 'field_6071c076bfdae',
					'label'        => sprintf( '%s %s', $singular, __( 'Edit Form', 'mai-locations' ) ),
					'instructions' => __( 'Allow editing of the following fields in addition to all custom fields', 'mai-locations' ),
					'name'         => 'location_edit_fields',
					'type'         => 'checkbox',
					'choices'      => [
						'title'   => 'Edit title',
						'content' => 'Edit content',
					],
					'default_value' => [
						'title',
						'content',
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

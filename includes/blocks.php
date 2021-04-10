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

	acf_register_block_type(
		[
			'name'            => 'mai-locations-table',
			'title'           => __( 'Mai Locations Table', 'mai-locations' ),
			'description'     => __( 'Display a users locations with view and edit buttons.', 'mai-locations' ),
			'render_callback' => 'mailocations_do_locations_table_block',
			'category'        => 'widgets',
			'keywords'        => [ 'location', 'table' ],
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
		'class'       => isset( $block['className'] ) && ! empty( $block['className'] ) ? sanitize_html_class( $block['className'] ): '',
		'align'       => isset( $block['align'] ) ? esc_html( $block['align'] ) : '',
	];

	echo mailocations_get_locations_table( 0, $args );
}

<?php

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

// add_action( 'acf/init', 'mai_register_locations_table_block' );
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

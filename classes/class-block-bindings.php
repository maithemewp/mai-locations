<?php

// Prevent direct file access.
defined( 'ABSPATH' ) || die;

class Mai_Locations_Block_Bindings {
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
		add_action( 'init', [ $this, 'add_block_bindings_source' ], 10, 3 );
	}

	/**
	 * Registers the block bindings source.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	function add_block_bindings_source() {
		register_block_bindings_source( 'mai/locations', [
			'label'              => __( 'Mai Locations', 'mai-locations' ),
			'get_value_callback' => [ $this, 'get_source_value' ],
			'uses_context'       => [ 'postId', 'postType' ]
		] );
	}

	/**
	 * Gets the source value.
	 *
	 * @since TBD
	 *
	 * @param array    $source_args
	 * @param WP_Block $block
	 * @param string   $attribute_name
	 *
	 * @return mixed
	 */
	function get_source_value( $source_args, $block, $attribute_name ) {
		// Bail if no key.
		if ( ! isset( $source_args['key'] ) || ! $source_args['key'] ) {
			return null;
		}

		// Get value.
		switch ( $source_args['key'] ) {
			case 'filterSubmit':
			case 'filterClear':
				$value = get_permalink( $block->context['postId'] );
			break;
			default:
				$value = null;
		}

		return $value;
	}
}
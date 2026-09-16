<?php

declare(strict_types=1);

namespace Mai\Locations;

use WP_Block;

// Prevent direct file access.
defined( 'ABSPATH' ) || die;

/**
 * Registers the mai/locations block bindings source.
 *
 * Was Mai_Locations_Block_Bindings in classes/class-block-bindings.php. That name still
 * works, via inc/aliases.php.
 *
 * @since TBD
 */
class BlockBindings {

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
		// accepted_args of 3 on a callback that takes none. Harmless, since init passes nothing,
		// and kept as-is so the hook registration stays identical to before the move.
		add_action( 'init', [ $this, 'add_block_bindings_source' ], 10, 3 );
	}

	/**
	 * Registers the block bindings source.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	public function add_block_bindings_source(): void {
		register_block_bindings_source( 'mai/locations', [
			'label'              => __( 'Mai Locations', 'mai-locations' ),
			'get_value_callback' => [ $this, 'get_source_value' ],
			'uses_context'       => [ 'postId', 'postType' ],
		] );
	}

	/**
	 * Gets the source value.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $source_args    The source args.
	 * @param WP_Block             $block          The block instance.
	 * @param string               $attribute_name The attribute being filled.
	 *
	 * @return mixed
	 */
	public function get_source_value( $source_args, $block, $attribute_name ) {
		// Bail if no key.
		if ( ! isset( $source_args['key'] ) || ! $source_args['key'] ) {
			return null;
		}

		// Get value.
		return match ( $source_args['key'] ) {
			// TODO: reads postId without checking it is there, which warns and returns false.
			// Pinned by BlockBindingsTest until the fix is agreed.
			'filterSubmit', 'filterClear' => get_permalink( $block->context['postId'] ),
			default                       => null,
		};
	}
}

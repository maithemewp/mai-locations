<?php

declare(strict_types=1);

namespace Mai\Locations\Tests\Integration\Fields;

use Mai\Locations\Tests\TestCase;
use Mai_Locations_Block_Bindings;
use WP_Block;

/**
 * Pins the mai/locations block bindings source in inc/classes/BlockBindings.php.
 *
 * Deliberately still written against the old Mai_Locations_Block_Bindings name, so it also
 * proves the alias in inc/aliases.php keeps working for sites using that name.
 */
final class BlockBindingsTest extends TestCase {

	public function test_source_is_registered(): void {
		$source = get_block_bindings_source( 'mai/locations' );

		$this->assertNotNull( $source );
		$this->assertSame( 'mai/locations', $source->name );
		$this->assertSame( 'Mai Locations', $source->label );
		$this->assertSame( [ 'postId', 'postType' ], $source->uses_context );
	}

	public function test_registered_on_init_with_three_accepted_args(): void {
		// accepted_args 3 on a callback that takes none; harmless because init passes nothing.
		$found = [];

		foreach ( $GLOBALS['wp_filter']['init']->callbacks as $priority => $callbacks ) {
			foreach ( $callbacks as $callback ) {
				if ( is_array( $callback['function'] ) && $callback['function'][0] instanceof Mai_Locations_Block_Bindings ) {
					$found[] = [ $priority, $callback['function'][1], $callback['accepted_args'] ];
				}
			}
		}

		$this->assertSame( [ [ 10, 'add_block_bindings_source', 3 ] ], $found );
	}

	public function test_filter_submit_and_clear_return_permalink_of_context_post(): void {
		$post_id = $this->create_location();
		$source  = get_block_bindings_source( 'mai/locations' );

		$this->assertSame( get_permalink( $post_id ), $source->get_value( [ 'key' => 'filterSubmit' ], $this->block( [ 'postId' => $post_id ] ), 'url' ) );
		$this->assertSame( get_permalink( $post_id ), $source->get_value( [ 'key' => 'filterClear' ], $this->block( [ 'postId' => $post_id ] ), 'url' ) );
	}

	public function test_meta_keys_return_null_even_when_location_has_meta(): void {
		$post_id = $this->create_location(
			[
				'address_city'   => 'Tarrytown',
				'location_phone' => '(914) 555-0100',
				'location_url'   => 'https://example.com/',
			]
		);
		$source  = get_block_bindings_source( 'mai/locations' );
		$block   = $this->block( [ 'postId' => $post_id, 'postType' => 'mai_location' ] );

		foreach ( [ 'address_city', 'location_phone', 'location_url', 'mai_location_address_city' ] as $key ) {
			$this->assertNull( $source->get_value( [ 'key' => $key ], $block, 'content' ), $key );
		}
	}

	public function test_missing_or_empty_key_returns_null(): void {
		$bindings = new Mai_Locations_Block_Bindings();
		$block    = $this->block( [] );

		$this->assertNull( $bindings->get_source_value( [], $block, 'url' ) );
		$this->assertNull( $bindings->get_source_value( [ 'key' => '' ], $block, 'url' ) );
		$this->assertNull( $bindings->get_source_value( [ 'key' => 0 ], $block, 'url' ) );
	}

	public function test_pins_bug_filter_submit_without_post_id_context_warns(): void {
		// Should check for postId before reading it.
		$messages = [];

		set_error_handler(
			static function ( int $errno, string $errstr ) use ( &$messages ): bool {
				$messages[] = $errstr;

				return true;
			}
		);

		try {
			unset( $GLOBALS['post'] );
			$value = ( new Mai_Locations_Block_Bindings() )->get_source_value( [ 'key' => 'filterSubmit' ], $this->block( [] ), 'url' );
		} finally {
			restore_error_handler();
		}

		$this->assertSame( [ 'Undefined array key "postId"' ], $messages );
		$this->assertFalse( $value );
	}

	/**
	 * Builds a block with the given context set directly.
	 *
	 * @param array<string, mixed> $context
	 */
	private function block( array $context ): WP_Block {
		$block          = new WP_Block( [ 'blockName' => 'core/button', 'attrs' => [], 'innerBlocks' => [], 'innerHTML' => '', 'innerContent' => [] ] );
		$block->context = $context;

		return $block;
	}
}

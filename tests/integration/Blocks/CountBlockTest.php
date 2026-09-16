<?php

declare(strict_types=1);

namespace Mai\Locations\Tests\Integration\Blocks;

use Mai\Locations\Tests\TestCase;

final class CountBlockTest extends TestCase {

	/**
	 * ACF_Local_Meta adds its filters once, on first use. The test case restores hooks after every test,
	 * so they vanish after the first block render in the process and get_field() stops seeing block data.
	 */
	private function restore_acf_local_meta_filters(): void {
		$meta = acf_get_instance( 'ACF_Local_Meta' );

		if ( ! has_filter( 'acf/pre_load_meta', [ $meta, 'pre_load_meta' ] ) ) {
			add_filter( 'acf/pre_load_post_id', [ $meta, 'pre_load_post_id' ], 1, 2 );
			add_filter( 'acf/pre_load_meta', [ $meta, 'pre_load_meta' ], 1, 2 );
			add_filter( 'acf/pre_load_metadata', [ $meta, 'pre_load_metadata' ], 1, 4 );
		}
	}

	public function set_up(): void {
		parent::set_up();
		$this->restore_acf_local_meta_filters();
	}

	/**
	 * Fixed September 16, 2026. A block with no saved data rendered "0  0", because an unset
	 * setting comes back as null and the field defaults were never read.
	 */
	public function test_empty_settings_use_the_field_defaults(): void {
		$this->assertSame( '<p class="mailocations-count">Showing 0 of 0 Locations</p>', do_blocks( '<!-- wp:acf/mai-locations-count {} /-->' ) );
	}

	public function test_a_cleared_setting_stays_empty(): void {
		$this->assertSame(
			'<p class="mailocations-count">0 of 0</p>',
			do_blocks( '<!-- wp:acf/mai-locations-count {"data":{"before":"","separator":"of","after":""}} /-->' )
		);
	}

	public function test_counts_the_main_query_on_an_archive(): void {
		$this->create_location( [], [ 'post_title' => 'One' ] );
		$this->create_location( [], [ 'post_title' => 'Two' ] );
		$this->create_location( [], [ 'post_title' => 'Hidden', 'post_status' => 'pending' ] );

		$this->go_to( get_post_type_archive_link( 'mai_location' ) );

		$this->assertSame(
			'<p class="mailocations-count">Showing 2 of 2 Things</p>',
			do_blocks( '<!-- wp:acf/mai-locations-count {"data":{"before":"Showing","separator":"of","after":"Things"}} /-->' )
		);
	}

	public function test_preview_uses_placeholder_numbers(): void {
		$block = new \Mai_Locations_Count_Block();

		ob_start();
		$block->render_block( [], '', true, 0, null, [] );

		$this->assertSame( '<p class="mailocations-count">Showing 123 of 456 Locations</p>', ob_get_clean() );
	}
}

<?php

declare(strict_types=1);

namespace Mai\Locations\Tests\Integration\Blocks;

use Mai\Locations\Tests\TestCase;

final class FilterBlockTest extends TestCase {

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

	public function tear_down(): void {
		$_GET = [];
		parent::tear_down();
	}

	private function render( string $markup ): string {
		return (string) preg_replace( '/<link rel="stylesheet"[^>]*\/>/', '', do_blocks( $markup ) );
	}

	private function add_hotels_term(): void {
		$id   = $this->create_location();
		$term = wp_insert_term( 'Hotels', 'mai_location_cat', [ 'slug' => 'hotels' ] );
		wp_set_object_terms( $id, [ $term['term_id'] ], 'mai_location_cat' );

		// An empty term is hidden on the front end.
		wp_insert_term( 'Empty', 'mai_location_cat', [ 'slug' => 'empty' ] );
	}

	public function test_renders_nothing_with_no_taxonomy(): void {
		$this->assertSame( '', do_blocks( '<!-- wp:acf/mai-locations-filter {} /-->' ) );
		$this->assertSame( '', do_blocks( '<!-- wp:acf/mai-locations-filter {"data":{"filter":"nope"}} /-->' ) );
	}

	public function test_renders_nothing_when_the_taxonomy_has_no_used_terms(): void {
		wp_insert_term( 'Empty', 'mai_location_cat', [ 'slug' => 'empty' ] );

		$this->assertSame( '', do_blocks( '<!-- wp:acf/mai-locations-filter {"data":{"filter":"mai_location_cat","type":"checkbox"}} /-->' ) );
	}

	public function test_renders_a_select_by_default(): void {
		$this->add_hotels_term();

		$this->assertSame(
			'<select class="mailocations-filter" tabindex="0" data-filter="_mai_location_cat" name="mailocations_filters[_mai_location_cat]"><option value="">All Location Categories</option><option value="hotels">Hotels</option></select>',
			$this->render( '<!-- wp:acf/mai-locations-filter {"data":{"filter":"mai_location_cat"}} /-->' )
		);
	}

	public function test_renders_checkboxes(): void {
		$this->add_hotels_term();

		$this->assertSame(
			'<ul class="mailocations-filter-list"><li><label><input type="checkbox" class="mailocations-filter" tabindex="0" name="mailocations_filters[_mai_location_cat]" data-filter="_mai_location_cat" value="hotels"> Hotels</label></li></ul>',
			$this->render( '<!-- wp:acf/mai-locations-filter {"data":{"filter":"mai_location_cat","type":"checkbox"}} /-->' )
		);
	}

	public function test_marks_terms_from_the_query_string(): void {
		$this->add_hotels_term();
		$_GET['_mai_location_cat'] = 'hotels,other';

		$this->assertSame(
			'<ul class="mailocations-filter-list"><li><label><input type="radio" class="mailocations-filter" tabindex="0" name="mailocations_filters[_mai_location_cat]" data-filter="_mai_location_cat" value="hotels" checked> Hotels</label></li></ul>',
			$this->render( '<!-- wp:acf/mai-locations-filter {"data":{"filter":"mai_location_cat","type":"radio"}} /-->' )
		);
		$this->assertStringContainsString(
			'<option value="hotels" selected>Hotels</option>',
			$this->render( '<!-- wp:acf/mai-locations-filter {"data":{"filter":"mai_location_cat","type":"select"}} /-->' )
		);
	}

	/**
	 * Anyone can send the value as an array instead of a comma list. explode() on an array
	 * threw, and the whole page went down. Fixed September 23, 2026. The array form now marks
	 * the same terms the comma form does, and junk inside it is ignored.
	 */
	public function test_an_array_in_the_query_string_marks_terms_instead_of_crashing(): void {
		$this->add_hotels_term();
		$_GET['_mai_location_cat'] = [ 'hotels', [ 'nested' ], 'other' ];

		$this->assertStringContainsString(
			'<option value="hotels" selected>Hotels</option>',
			$this->render( '<!-- wp:acf/mai-locations-filter {"data":{"filter":"mai_location_cat","type":"select"}} /-->' )
		);
	}

	public function test_unknown_type_renders_only_the_stylesheet(): void {
		$this->add_hotels_term();

		$this->assertSame( '', $this->render( '<!-- wp:acf/mai-locations-filter {"data":{"filter":"mai_location_cat","type":"bogus"}} /-->' ) );
	}

	public function test_preview_with_no_taxonomy_prints_a_prompt(): void {
		$block = new \Mai_Locations_Filter_Block();

		ob_start();
		$block->render_block( [], '', true, 0, null, [] );

		$this->assertSame( '<p>Choose a location filter in the block settings.</p>', ob_get_clean() );
	}
}

<?php

declare(strict_types=1);

namespace Mai\Locations\Tests\Integration\Blocks;

use Mai\Locations\Tests\TestCase;

/**
 * Renders through do_blocks(), which is how a front-end page reaches the ACF render callback.
 */
final class AddressSearchBlockTest extends TestCase {

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

	/**
	 * The stylesheet link prints only on the first render in the whole process, so it is stripped.
	 */
	private function render( string $markup ): string {
		return (string) preg_replace( '/<link rel="stylesheet"[^>]*\/>/', '', do_blocks( $markup ) );
	}

	public function test_renders_with_no_settings(): void {
		$expected = '<div class="mailocations-autocomplete-container">'
			. '<div class="mailocations-autocomplete-input-container"><div class="mailocations-autocomplete" data-countries="" data-placeholder="Enter your address" data-value=""></div></div>'
			. '<input type="hidden" class="mailocations-address" name="mailocations_address" value="{&quot;address&quot;:&quot;&quot;,&quot;lat&quot;:&quot;&quot;,&quot;lng&quot;:&quot;&quot;,&quot;distance&quot;:100,&quot;unit&quot;:&quot;mi&quot;}">'
			// No distances setting explodes to one empty distance, so the single-distance hidden inputs print.
			. '<input type="hidden" class="mailocations-autocomplete-distance" name="mailocations_distance" value="100">'
			. '<input type="hidden" class="mailocations-autocomplete-unit" name="mailocations_unit" value="mi">'
			. '</div>';

		// Scripts register on wp_enqueue_scripts, which does not fire here, so a stand-in handle is registered.
		wp_register_script( 'mai-locations', false );

		$this->assertSame( $expected, $this->render( '<!-- wp:acf/mai-locations-address-search {} /-->' ) );
		$this->assertTrue( wp_script_is( 'mai-locations', 'enqueued' ) );
	}

	public function test_renders_distance_and_unit_selects(): void {
		$expected = '<div class="mailocations-autocomplete-container">'
			. '<div class="mailocations-autocomplete-input-container"><div class="mailocations-autocomplete" data-countries="US,CA" data-placeholder="Where?" data-value=""></div></div>'
			. '<input type="hidden" class="mailocations-address" name="mailocations_address" value="{&quot;address&quot;:&quot;&quot;,&quot;lat&quot;:&quot;&quot;,&quot;lng&quot;:&quot;&quot;,&quot;distance&quot;:100,&quot;unit&quot;:&quot;mi&quot;}">'
			// Each distance is trimmed, so "10, 20" gives 20 and not " 20". Fixed September 16, 2026.
			. '<select class="mailocations-autocomplete-distance" name="mailocations_distance" tabindex="0"><option  value="10">10</option><option  value="20">20</option></select>'
			. '<select class="mailocations-autocomplete-unit" name="mailocations_unit" tabindex="0"><option  value="mi" selected>mi</option><option  value="km">km</option></select>'
			. '</div>';

		$this->assertSame( $expected, $this->render( '<!-- wp:acf/mai-locations-address-search {"data":{"placeholder":"Where?","distances":"10, 20","units":["mi","km"],"countries":["US","CA"]}} /-->' ) );
	}

	public function test_single_unit_labels_distances_and_selects_the_queried_distance(): void {
		$_GET = [
			'address'  => 'Tarrytown, NY',
			'lat'      => '41.07',
			'lng'      => '-73.86',
			'distance' => '50',
		];

		$html = $this->render( '<!-- wp:acf/mai-locations-address-search {"data":{"distances":"25, 50","units":["km"]}} /-->' );

		$this->assertStringContainsString( 'data-value="Tarrytown, NY"', $html );
		$this->assertStringContainsString( 'value="{&quot;address&quot;:&quot;Tarrytown, NY&quot;,&quot;lat&quot;:&quot;41.07&quot;,&quot;lng&quot;:&quot;-73.86&quot;,&quot;distance&quot;:&quot;50&quot;,&quot;unit&quot;:&quot;km&quot;}"', $html );
		$this->assertStringContainsString( '<option  value="25">25 km</option><option  value="50" selected>50 km</option>', $html );
		$this->assertStringNotContainsString( 'mailocations-autocomplete-unit', $html );
	}

	public function test_preview_leaves_out_values_and_hidden_inputs(): void {
		$block = new \Mai_Locations_Address_Search_Block();

		ob_start();
		$block->render_block( [], '', true, 0, null, [] );
		$html = (string) preg_replace( '/<link rel="stylesheet"[^>]*\/>/', '', (string) ob_get_clean() );

		$expected = '<div class="mailocations-autocomplete-container">'
			. '<div class="mailocations-autocomplete-input-container"><div class="mailocations-autocomplete" data-countries="" data-placeholder="Enter your address" data-value=""></div></div>'
			. '<input type="hidden" class="mailocations-address" name="mailocations_address" value="{&quot;address&quot;:&quot;&quot;,&quot;lat&quot;:&quot;&quot;,&quot;lng&quot;:&quot;&quot;,&quot;distance&quot;:100,&quot;unit&quot;:&quot;mi&quot;}">'
			. '</div>';

		$this->assertSame( $expected, $html );
	}
}

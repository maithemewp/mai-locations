<?php

declare(strict_types=1);

namespace Mai\Locations\Tests\Integration\Blocks;

use Mai\Locations\Tests\TestCase;

final class MapBlockTest extends TestCase {

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

	private function render( string $markup ): string {
		return (string) preg_replace( '/<link rel="stylesheet"[^>]*\/>/', '', do_blocks( $markup ) );
	}

	private function located(): int {
		return $this->create_location(
			[
				'location_lat'  => '41.08',
				'location_lng'  => '-73.86',
				'address_city'  => 'Sleepy Hollow',
				'address_state' => 'NY',
			],
			[ 'post_title' => 'Hollow Inn', 'post_name' => 'hollow-inn' ]
		);
	}

	private function marker_html(): string {
		return '<div style="display:none;" class="marker" data-lat="41.08" data-lng="-73.86">'
			. '<strong style="display:block;margin-bottom:4px;"><a href="http://example.org/?mai_location=hollow-inn" target="_blank" rel="noopener nofollow">Hollow Inn</a></strong>'
			. '<div itemprop="address" itemscope itemtype="http://schema.org/PostalAddress" class="mai-address"><div class="mai-address-item"><span class="locality" itemprop="addressLocality">Sleepy Hollow</span><span class="region" itemprop="addressRegion">&nbsp;NY</span></div></div>'
			. '<p style="display:block;margin-top:4px;"><a href="https://www.google.com/maps/dir/?api=1&destination=41.08,-73.86" target="_blank" rel="noopener nofollow">Get Directions</a></p>'
			. '</div>';
	}

	public function test_get_markers_skips_posts_without_both_coordinates(): void {
		$located = $this->located();
		$lat     = $this->create_location( [ 'location_lat' => '41.0' ] );
		$none    = $this->create_location();

		$markers = ( new \Mai_Locations_Map_Block() )->get_markers( [ $located, $lat, $none ] );

		$this->assertSame(
			[
				[
					'lat'        => '41.08',
					'lng'        => '-73.86',
					'href'       => 'http://example.org/?mai_location=hollow-inn',
					'title'      => 'Hollow Inn',
					'address'    => '<div itemprop="address" itemscope itemtype="http://schema.org/PostalAddress" class="mai-address"><div class="mai-address-item"><span class="locality" itemprop="addressLocality">Sleepy Hollow</span><span class="region" itemprop="addressRegion">&nbsp;NY</span></div></div>',
					'directions' => 'https://www.google.com/maps/dir/?api=1&destination=41.08,-73.86',
				],
			],
			$markers
		);
	}

	public function test_get_markers_with_no_posts(): void {
		$this->assertSame( [], ( new \Mai_Locations_Map_Block() )->get_markers( [] ) );
	}

	public function test_renders_an_empty_map_outside_a_location_query(): void {
		// Scripts register on wp_enqueue_scripts, which does not fire here, so stand-in handles are registered.
		wp_register_script( 'mai-locations', false );
		wp_register_script( 'mai-locations-markerclusterer', false );

		$this->assertSame( '<div style="aspect-ratio:800/533;" class="mailocations-map" data-zoom="7"></div>', $this->render( '<!-- wp:acf/mai-locations-map {} /-->' ) );
		$this->assertTrue( wp_script_is( 'mai-locations', 'enqueued' ) );
		$this->assertTrue( wp_script_is( 'mai-locations-markerclusterer', 'enqueued' ) );
	}

	public function test_renders_markers_for_the_current_page(): void {
		$this->located();
		$this->create_location( [], [ 'post_title' => 'No coordinates' ] );
		$this->go_to( get_post_type_archive_link( 'mai_location' ) );

		$this->assertSame(
			'<div style="aspect-ratio:800/533;" class="mailocations-map" data-zoom="7">' . $this->marker_html() . '</div>',
			$this->render( '<!-- wp:acf/mai-locations-map {} /-->' )
		);
	}

	public function test_all_query_goes_past_the_current_page_and_caches_markers_in_a_transient(): void {
		$this->located();
		$this->create_location( [ 'location_lat' => '40.0', 'location_lng' => '-74.0' ], [ 'post_title' => 'Second' ] );
		update_option( 'posts_per_page', 1 );
		$this->go_to( get_post_type_archive_link( 'mai_location' ) );

		$this->assertSame( 1, substr_count( $this->render( '<!-- wp:acf/mai-locations-map {} /-->' ), 'class="marker"' ) );
		$this->assertSame( 2, substr_count( $this->render( '<!-- wp:acf/mai-locations-map {"data":{"query":"all"}} /-->' ), 'class="marker"' ) );

		global $wpdb;
		$this->assertSame( '1', $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_mai\\_locations\\_markers\\_%'" ) );
	}

	/**
	 * Fixed September 16, 2026. The "all" query was built from the main query's vars, so on any
	 * page that is not a location archive it looked up regular posts and found nothing.
	 */
	public function test_all_query_finds_locations_outside_a_location_query(): void {
		$this->located();
		$this->go_to( home_url( '/' ) );

		$this->assertSame(
			'<div style="aspect-ratio:800/533;" class="mailocations-map" data-zoom="7">' . $this->marker_html() . '</div>',
			$this->render( '<!-- wp:acf/mai-locations-map {"data":{"query":"all"}} /-->' )
		);
	}

	/**
	 * The home URL has no query vars, which is why the test above passed while every real page
	 * drew an empty map. A page carries pagename or page_id, and the "all" query used to keep
	 * them. Fixed September 23, 2026. Both ways a page can be addressed are covered.
	 *
	 * @dataProvider page_addresses
	 */
	public function test_all_query_finds_locations_on_an_ordinary_page( string $how ): void {
		$this->located();
		$page = self::factory()->post->create( [ 'post_type' => 'page', 'post_name' => 'find-a-location', 'post_status' => 'publish' ] );

		$this->go_to( 'page_id' === $how ? add_query_arg( 'page_id', $page, home_url( '/' ) ) : get_permalink( $page ) );

		$this->assertTrue( is_page( $page ), 'The test did not reach the page it meant to.' );
		$this->assertSame( 1, substr_count( $this->render( '<!-- wp:acf/mai-locations-map {"data":{"query":"all"}} /-->' ), 'class="marker"' ) );
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function page_addresses(): array {
		return [
			'pretty permalink' => [ 'pagename' ],
			'page_id'          => [ 'page_id' ],
		];
	}

	public function test_none_query_renders_no_markers_at_the_given_size(): void {
		$this->located();
		$this->go_to( get_post_type_archive_link( 'mai_location' ) );

		$this->assertSame(
			'<div style="aspect-ratio:400/300;" class="mailocations-map" data-zoom="7"></div>',
			$this->render( '<!-- wp:acf/mai-locations-map {"data":{"query":"none","width":"400","height":"300"}} /-->' )
		);
	}

	public function test_preview_prints_a_static_image(): void {
		ob_start();
		( new \Mai_Locations_Map_Block() )->render_block( [], '', true, 0, null, [] );
		$html = (string) preg_replace( '/<link rel="stylesheet"[^>]*\/>/', '', (string) ob_get_clean() );

		$this->assertSame(
			sprintf( '<div style="aspect-ratio:800/533;"><img style="display:block;height:100%%;width:100%%;position:absolute;top:0;left:0;object-fit:cover" width="800" height="533" src="%s/assets/images/map.png"/></div>', MAI_LOCATIONS_PLUGIN_URL ),
			$html
		);
	}
}

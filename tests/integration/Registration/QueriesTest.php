<?php

declare(strict_types=1);

namespace Mai\Locations\Tests\Integration\Registration;

use Mai\Locations\Tests\TestCase;
use Mai_Locations_Queries;
use WP_Query;

require_once __DIR__ . '/ScenarioRunner.php';

/**
 * The archive query changes in inc/classes/Query/Queries.php, still written against the old
 * Mai_Locations_Queries name so it exercises the alias in inc/aliases.php.
 *
 * Whether the page is filtered is cached in a static on first check, and in this process that
 * first check never sees filter parameters. Filtered cases run in a fresh process.
 */
final class QueriesTest extends TestCase {

	use ScenarioRunner;

	public function tear_down(): void {
		set_current_screen( 'front' );

		parent::tear_down();
	}

	public function test_hooked_on_pre_get_posts(): void {
		$this->assertSame( 10, has_action( 'pre_get_posts', [ $this->queries(), 'pre_get_posts_query' ] ) );
	}

	public function test_post_grid_query_is_unhooked_dead_code(): void {
		$this->assertFalse( has_filter( 'mai_post_grid_query_args' ) );
		$this->assertFalse( function_exists( 'mailocations_get_geo_query_args' ) );
	}

	public function test_post_grid_query_returns_args_unchanged_for_other_post_types(): void {
		$args = [ 'post_type' => 'post', 'posts_per_page' => 3 ];

		$this->assertSame( $args, $this->queries()->mai_post_grid_query( $args, [] ) );
	}

	public function test_pins_bug_post_grid_query_fatals_for_location_post_type(): void {
		$this->expectException( \Error::class );
		// Namespaced since the class moved to Mai\Locations\Query\Queries: PHP names the namespaced
		// attempt in the message. Still the same fatal, still only reachable by calling it.
		$this->expectExceptionMessage( 'Call to undefined function Mai\Locations\Query\mailocations_get_geo_query_args()' );

		// Calls a function that does not exist. Harmless only because the hook is commented out.
		$this->queries()->mai_post_grid_query( [ 'post_type' => 'mai_location' ], [] );
	}

	public function test_unfiltered_post_type_archive_orders_by_title(): void {
		$this->create_location( [], [ 'post_title' => 'Zeta Inn', 'post_date' => '2026-01-02 00:00:00' ] );
		$this->create_location( [], [ 'post_title' => 'Alpha Cafe', 'post_date' => '2026-01-01 00:00:00' ] );

		$this->go_to( get_post_type_archive_link( 'mai_location' ) );

		$this->assertSame( 'title', $GLOBALS['wp_query']->get( 'orderby' ) );
		$this->assertSame( 'ASC', $GLOBALS['wp_query']->get( 'order' ) );
		$this->assertSame( [ 'Alpha Cafe', 'Zeta Inn' ], wp_list_pluck( $GLOBALS['wp_query']->posts, 'post_title' ) );
	}

	public function test_unfiltered_category_archive_orders_by_title(): void {
		$term_id = self::factory()->term->create( [ 'taxonomy' => 'mai_location_cat' ] );

		$this->go_to( get_term_link( $term_id ) );

		$this->assertSame( 'title', $GLOBALS['wp_query']->get( 'orderby' ) );
		$this->assertSame( 'ASC', $GLOBALS['wp_query']->get( 'order' ) );
	}

	public function test_leaves_other_main_queries_alone(): void {
		$this->go_to( home_url( '/' ) );

		$this->assertSame( '', $GLOBALS['wp_query']->get( 'orderby' ) );
	}

	public function test_leaves_secondary_location_queries_alone(): void {
		$this->go_to( get_post_type_archive_link( 'mai_location' ) );

		$query = new WP_Query( [ 'post_type' => 'mai_location' ] );

		$this->assertSame( '', $query->get( 'orderby' ) );
	}

	public function test_leaves_admin_queries_alone(): void {
		set_current_screen( 'edit.php' );

		// go_to() clears the admin screen, so run the main query directly.
		$GLOBALS['wp_the_query'] = new WP_Query();
		$GLOBALS['wp_query']     = $GLOBALS['wp_the_query'];
		$GLOBALS['wp_query']->query( [ 'post_type' => 'mai_location' ] );

		$this->assertTrue( is_admin() );
		$this->assertTrue( $GLOBALS['wp_query']->is_post_type_archive() );
		$this->assertSame( '', $GLOBALS['wp_query']->get( 'orderby' ) );
	}

	public function test_filtered_archive_sets_geo_and_tax_query(): void {
		$result = $this->run_scenario(
			[
				'probe' => 'main_query',
				'query' => [ 'post_type' => 'mai_location' ],
				'get'   => [ '_mai_location_cat' => 'cafe,bar', 'lat' => '41.08', 'lng' => '-73.86' ],
			]
		);

		$this->assertTrue( $result['is_filtered'] );
		$this->assertSame( 'distance', $result['orderby'] );
		$this->assertSame( 'ASC', $result['order'] );
		$this->assertSame(
			[
				'lat_field' => 'location_lat',
				'lng_field' => 'location_lng',
				'latitude'  => '41.08',
				'longitude' => '-73.86',
				'distance'  => 100,
				'units'     => 'mi',
			],
			$result['geo_query']
		);
		$this->assertSame(
			[
				[
					'taxonomy' => 'mai_location_cat',
					'field'    => 'slug',
					'terms'    => [ 'cafe', 'bar' ],
					'operator' => 'AND',
				],
			],
			$result['tax_query']
		);
	}

	public function test_filtered_archive_with_only_lat_keeps_default_order(): void {
		$result = $this->run_scenario(
			[
				'probe' => 'main_query',
				'query' => [ 'post_type' => 'mai_location' ],
				'get'   => [ 'lat' => '41.08' ],
			]
		);

		// Counts as filtered, but builds no args, so the archive is neither geo sorted nor title sorted.
		$this->assertTrue( $result['is_filtered'] );
		$this->assertSame( '', $result['orderby'] );
		$this->assertSame( 'DESC', $result['order'] );
	}

	public function test_pins_cache_filtered_check_never_changes_after_first_call(): void {
		$result = $this->run_scenario( [ 'probe' => 'filtered_cache' ] );

		// The first answer is kept for the request, even after $_GET gains a filter key.
		$this->assertFalse( $result['first'] );
		$this->assertFalse( $result['second'] );
	}

	private function queries(): Mai_Locations_Queries {
		foreach ( $GLOBALS['wp_filter']['pre_get_posts']->callbacks[10] as $callback ) {
			if ( is_array( $callback['function'] ) && $callback['function'][0] instanceof Mai_Locations_Queries ) {
				return $callback['function'][0];
			}
		}

		$this->fail( 'Mai_Locations_Queries instance not found.' );
	}
}

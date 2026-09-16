<?php

declare(strict_types=1);

namespace Mai\Locations\Tests\Integration\Registration;

use Mai\Locations\Tests\TestCase;
use WP_Query;

require_once __DIR__ . '/ScenarioRunner.php';

/**
 * Mai_Locations_Plugin::no_results_text() on Genesis's `genesis_noposts_text` filter.
 */
final class NoResultsTextTest extends TestCase {

	use ScenarioRunner;

	public function test_hooked_on_genesis_noposts_text(): void {
		$this->assertSame( 10, has_filter( 'genesis_noposts_text', [ mai_locations_plugin(), 'no_results_text' ] ) );
	}

	public function test_location_archive_gets_lowercase_plural_text(): void {
		$this->go_to( get_post_type_archive_link( 'mai_location' ) );

		$this->assertSame( 'Sorry, no locations found.', apply_filters( 'genesis_noposts_text', 'Original' ) );
	}

	public function test_other_queries_keep_the_original_text(): void {
		$this->go_to( home_url( '/' ) );

		$this->assertSame( 'Original', apply_filters( 'genesis_noposts_text', 'Original' ) );
	}

	public function test_location_category_archive_keeps_the_original_text(): void {
		$this->go_to( get_term_link( self::factory()->term->create( [ 'taxonomy' => 'mai_location_cat' ] ) ) );

		// Only the post_type query var is checked, which a term archive does not set.
		$this->assertSame( 'Original', apply_filters( 'genesis_noposts_text', 'Original' ) );
	}

	/**
	 * Both fixed September 16, 2026. The null guard sat after $wp_query->get(), so it never
	 * helped, and an array post_type was used as an array key, which is a TypeError.
	 */
	public function test_no_query_keeps_the_original_text(): void {
		$saved               = $GLOBALS['wp_query'];
		$GLOBALS['wp_query'] = null;

		try {
			$this->assertSame( 'Original', mai_locations_plugin()->no_results_text( 'Original' ) );
		} finally {
			$GLOBALS['wp_query'] = $saved;
		}
	}

	public function test_array_post_type_query_var_keeps_the_original_text(): void {
		$saved               = $GLOBALS['wp_query'];
		$GLOBALS['wp_query'] = new WP_Query();
		$GLOBALS['wp_query']->set( 'post_type', [ 'mai_location', 'post' ] );

		try {
			$this->assertSame( 'Original', mai_locations_plugin()->no_results_text( 'Original' ) );
		} finally {
			$GLOBALS['wp_query'] = $saved;
		}
	}

	public function test_filtered_archive_adds_adjust_search_sentence(): void {
		$result = $this->run_scenario(
			[
				'probe' => 'main_query',
				'query' => [ 'post_type' => 'mai_location' ],
				'get'   => [ 'lat' => '41.08', 'lng' => '-73.86' ],
			]
		);

		$this->assertSame( 'Sorry, no locations found. Please adjust your search criteria and try again.', $result['no_results_text'] );
	}
}

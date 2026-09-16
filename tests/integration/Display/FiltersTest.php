<?php

declare(strict_types=1);

namespace Mai\Locations\Tests\Integration\Display;

use Mai\Locations\Tests\TestCase;
use ReflectionFunction;

/**
 * Pins the location filter helpers in includes/functions-filters.php.
 */
final class FiltersTest extends TestCase {

	/**
	 * @var array<string, mixed>
	 */
	private array $get_backup = [];

	public function set_up(): void {
		parent::set_up();
		$this->get_backup = $_GET;
		$_GET             = [];
	}

	public function tear_down(): void {
		$_GET = $this->get_backup;
		parent::tear_down();
	}

	public function test_query_defaults(): void {
		$this->assertSame(
			[
				'address'           => '',
				'lat'               => '',
				'lng'               => '',
				'distance'          => 100,
				'units'             => 'mi',
				'state'             => '',
				'province'          => '',
				'_mai_location_cat' => [],
			],
			mailocations_get_query_defaults()
		);
	}

	public function test_query_params_empty_without_get(): void {
		$this->assertSame( [], mailocations_get_query_params() );
	}

	public function test_query_params_reads_known_keys_and_splits_taxonomies(): void {
		$_GET = [
			'lat'               => '41.0762',
			'lng'               => '-73.8587',
			'distance'          => '25',
			'units'             => 'km',
			'_mai_location_cat' => 'hotels,dining',
			'unknown'           => 'x',
			'filter'            => 'y',
		];

		$this->assertSame(
			[
				'lat'               => '41.0762',
				'lng'               => '-73.8587',
				'distance'          => '25',
				'units'             => 'km',
				'_mai_location_cat' => [ 'hotels', 'dining' ],
			],
			mailocations_get_query_params()
		);
	}

	/**
	 * Fixed September 16, 2026. The value was escaped and then overwritten with the raw one on
	 * the next line.
	 */
	public function test_query_params_are_escaped(): void {
		$_GET = [ 'address' => '<script>alert(1)</script>' ];

		$this->assertSame( [ 'address' => '&lt;script&gt;alert(1)&lt;/script&gt;' ], mailocations_get_query_params() );
	}

	public function test_filtered_args_empty_without_get(): void {
		$this->assertSame( [], mailocations_get_filtered_query_args() );
		$this->assertSame( [ 'post_type' => 'mai_location' ], mailocations_get_filtered_query_args( [ 'post_type' => 'mai_location' ] ) );
	}

	public function test_filtered_args_geo_query_uses_defaults(): void {
		$_GET = [ 'lat' => '41.0762', 'lng' => '-73.8587' ];

		$this->assertSame(
			[
				'post_type' => 'mai_location',
				'orderby'   => 'distance',
				'order'     => 'ASC',
				'geo_query' => [
					'lat_field' => 'location_lat',
					'lng_field' => 'location_lng',
					'latitude'  => '41.0762',
					'longitude' => '-73.8587',
					'distance'  => 100,
					'units'     => 'mi',
				],
			],
			mailocations_get_filtered_query_args( [ 'post_type' => 'mai_location', 'orderby' => 'title' ] )
		);
	}

	public function test_filtered_args_geo_query_uses_get_distance_and_units(): void {
		$_GET = [ 'lat' => '41', 'lng' => '-73', 'distance' => '25', 'units' => 'km' ];

		$args = mailocations_get_filtered_query_args();

		$this->assertSame( '25', $args['geo_query']['distance'] );
		$this->assertSame( 'km', $args['geo_query']['units'] );
	}

	public function test_filtered_args_needs_both_lat_and_lng(): void {
		$_GET = [ 'lat' => '41.0762' ];
		$this->assertSame( [], mailocations_get_filtered_query_args() );

		$_GET = [ 'lat' => '', 'lng' => '-73' ];
		$this->assertSame( [], mailocations_get_filtered_query_args() );
	}

	/**
	 * Fixed September 16, 2026. A latitude of 0 used to count as missing.
	 */
	public function test_filtered_args_accept_a_coordinate_of_zero(): void {
		$_GET = [ 'lat' => '0', 'lng' => '-73' ];

		$this->assertSame( '0', mailocations_get_filtered_query_args()['geo_query']['latitude'] );
	}

	public function test_filtered_args_single_term(): void {
		$_GET = [ '_mai_location_cat' => 'hotels' ];

		$this->assertSame(
			[
				'tax_query' => [
					[
						'taxonomy' => 'mai_location_cat',
						'field'    => 'slug',
						'terms'    => [ 'hotels' ],
					],
				],
			],
			mailocations_get_filtered_query_args()
		);
	}

	public function test_filtered_args_multiple_terms_use_and_operator(): void {
		$_GET = [ '_mai_location_cat' => 'hotels,dining' ];

		$this->assertSame(
			[
				'tax_query' => [
					[
						'taxonomy' => 'mai_location_cat',
						'field'    => 'slug',
						'terms'    => [ 'hotels', 'dining' ],
						'operator' => 'AND',
					],
				],
			],
			mailocations_get_filtered_query_args()
		);
	}

	public function test_filtered_args_existing_tax_query_relation_is_forced_to_and(): void {
		$_GET = [ '_mai_location_cat' => 'hotels' ];

		$existing = [
			'tax_query' => [
				'relation' => 'OR',
				[
					'taxonomy' => 'category',
					'field'    => 'slug',
					'terms'    => [ 'news' ],
				],
			],
		];

		$this->assertSame(
			[
				'tax_query' => [
					[
						'taxonomy' => 'category',
						'field'    => 'slug',
						'terms'    => [ 'news' ],
					],
					[
						'taxonomy' => 'mai_location_cat',
						'field'    => 'slug',
						'terms'    => [ 'hotels' ],
					],
					'relation' => 'AND',
				],
			],
			mailocations_get_filtered_query_args( $existing )
		);
	}

	public function test_filtered_args_existing_single_clause_with_relation_loses_relation(): void {
		$_GET = [ 'lat' => '41', 'lng' => '-73' ];

		// No taxonomy params, so an existing tax_query is left alone.
		$existing = [ 'tax_query' => [ 'relation' => 'OR' ] ];

		$this->assertSame( [ 'relation' => 'OR' ], mailocations_get_filtered_query_args( $existing )['tax_query'] );
	}

	public function test_is_filtered_locations_caches_first_answer_for_the_process(): void {
		$statics = ( new ReflectionFunction( 'mailocations_is_filtered_locations' ) )->getStaticVariables();

		if ( null === $statics['filtered'] ) {
			$_GET = [ 'lat' => '41' ];
			$this->assertTrue( mailocations_is_filtered_locations() );

			$_GET = [];
			// Pins the static cache: a later request state is never seen.
			$this->assertTrue( mailocations_is_filtered_locations() );
			return;
		}

		// Another test already primed the cache, so $_GET no longer matters.
		$cached = $statics['filtered'];

		$_GET = [ 'lat' => '41' ];
		$this->assertSame( $cached, mailocations_is_filtered_locations() );

		$_GET = [];
		$this->assertSame( $cached, mailocations_is_filtered_locations() );
	}

	public function test_distance_helper_returns_rounded_value_despite_void_docblock(): void {
		$post                     = get_post( $this->create_location() );
		$post->geo_query_distance = '12.3456';

		$this->assertSame( 12.3, mailocations_get_distance( $post ) );
		$this->assertSame( 12.35, mailocations_get_distance( $post, 2 ) );
		$this->assertSame( '12.3456', mailocations_get_distance( $post, false ) );
	}

	public function test_distance_helper_returns_false_without_distance(): void {
		$post = get_post( $this->create_location() );

		$this->assertFalse( mailocations_get_distance( $post ) );
	}
}

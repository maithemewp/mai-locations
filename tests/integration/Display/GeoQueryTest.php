<?php

declare(strict_types=1);

namespace Mai\Locations\Tests\Integration\Display;

use Mai\Locations\Tests\TestCase;
use Mai_Geo_Query;
use WP_Query;

/**
 * Pins Mai_Geo_Query: its hooks, the SQL it adds, and real distance ordering.
 */
final class GeoQueryTest extends TestCase {

	private const TARRYTOWN = [ 41.0762, -73.8587 ];

	public function tear_down(): void {
		unset( $GLOBALS['post'] );
		parent::tear_down();
	}

	/**
	 * A query object carrying only the vars the filters read.
	 *
	 * @param array<string, mixed> $vars
	 */
	private function query_with( array $vars ): WP_Query {
		$query = new WP_Query();

		foreach ( $vars as $key => $value ) {
			$query->set( $key, $value );
		}

		return $query;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function geo( float $distance = 30, string $units = 'miles' ): array {
		return [
			'lat_field' => 'location_lat',
			'lng_field' => 'location_lng',
			'latitude'  => self::TARRYTOWN[0],
			'longitude' => self::TARRYTOWN[1],
			'distance'  => $distance,
			'units'     => $units,
		];
	}

	private function haversine_sql( int $radius ): string {
		return "( {$radius} * acos( cos( radians(41.076200) ) * cos( radians( geo_query_lat.meta_value ) ) * cos( radians( geo_query_lng.meta_value ) - radians(-73.858700) ) + sin( radians(41.076200) ) * sin( radians( geo_query_lat.meta_value ) ) ) )";
	}

	public function test_hooks_are_added_at_priority_10(): void {
		$geo = Mai_Geo_Query::instance();

		$this->assertSame( $geo, Mai_Geo_Query::instance() );
		$this->assertSame( 10, has_filter( 'posts_fields', [ $geo, 'posts_fields' ] ) );
		$this->assertSame( 10, has_filter( 'posts_join', [ $geo, 'posts_join' ] ) );
		$this->assertSame( 10, has_filter( 'posts_where', [ $geo, 'posts_where' ] ) );
		$this->assertSame( 10, has_filter( 'posts_orderby', [ $geo, 'posts_orderby' ] ) );
	}

	public function test_filters_leave_sql_alone_without_geo_query(): void {
		$geo   = Mai_Geo_Query::instance();
		$query = $this->query_with( [ 'orderby' => 'distance' ] );

		$this->assertSame( 'a', $geo->posts_fields( 'a', $query ) );
		$this->assertSame( 'b', $geo->posts_join( 'b', $query ) );
		$this->assertSame( 'c', $geo->posts_where( 'c', $query ) );
		$this->assertSame( 'd', $geo->posts_orderby( 'd', $query ) );
	}

	public function test_posts_fields_sql(): void {
		global $wpdb;

		$geo   = Mai_Geo_Query::instance();
		$query = $this->query_with( [ 'geo_query' => $this->geo() ] );

		$this->assertSame( "{$wpdb->posts}.*, " . $this->haversine_sql( 3959 ) . ' AS geo_query_distance', $geo->posts_fields( "{$wpdb->posts}.*", $query ) );
		$this->assertSame( $this->haversine_sql( 3959 ) . ' AS geo_query_distance', $geo->posts_fields( '', $query ) );
	}

	public function test_kilometer_units_use_earth_radius_in_km(): void {
		$geo = Mai_Geo_Query::instance();

		foreach ( [ 'km', 'kilometers', 'KM' ] as $units ) {
			$query = $this->query_with( [ 'geo_query' => $this->geo( 30, $units ) ] );
			$this->assertSame( $this->haversine_sql( 6371 ) . ' AS geo_query_distance', $geo->posts_fields( '', $query ), $units );
		}

		// Anything else, including the plugin's own "mi" option value, is miles.
		foreach ( [ 'mi', 'miles', 'furlongs', '' ] as $units ) {
			$query = $this->query_with( [ 'geo_query' => $this->geo( 30, $units ) ] );
			$this->assertSame( $this->haversine_sql( 3959 ) . ' AS geo_query_distance', $geo->posts_fields( '', $query ), $units );
		}
	}

	public function test_missing_lat_lng_default_to_zero(): void {
		$geo   = Mai_Geo_Query::instance();
		$query = $this->query_with( [ 'geo_query' => [ 'distance' => 5 ] ] );

		$this->assertSame(
			'( 3959 * acos( cos( radians(0.000000) ) * cos( radians( geo_query_lat.meta_value ) ) * cos( radians( geo_query_lng.meta_value ) - radians(0.000000) ) + sin( radians(0.000000) ) * sin( radians( geo_query_lat.meta_value ) ) ) ) AS geo_query_distance',
			$geo->posts_fields( '', $query )
		);
	}

	public function test_posts_join_sql(): void {
		global $wpdb;

		$geo   = Mai_Geo_Query::instance();
		$query = $this->query_with( [ 'geo_query' => $this->geo() ] );
		$p     = $wpdb->prefix;

		$join = "INNER JOIN {$p}postmeta AS geo_query_lat ON ( {$p}posts.ID = geo_query_lat.post_id ) INNER JOIN {$p}postmeta AS geo_query_lng ON ( {$p}posts.ID = geo_query_lng.post_id ) ";

		$this->assertSame( $join, $geo->posts_join( '', $query ) );
		$this->assertSame( 'JOIN x ' . $join, $geo->posts_join( 'JOIN x', $query ) );
	}

	public function test_posts_where_sql_with_distance(): void {
		$geo   = Mai_Geo_Query::instance();
		$query = $this->query_with( [ 'geo_query' => $this->geo( 30 ) ] );

		$this->assertSame(
			" AND 1=1 AND ( geo_query_lat.meta_key = 'location_lat' AND geo_query_lng.meta_key = 'location_lng' AND " . $this->haversine_sql( 3959 ) . ' <= 30.000000 )',
			$geo->posts_where( ' AND 1=1', $query )
		);
	}

	public function test_posts_where_sql_without_distance_or_fields(): void {
		$geo   = Mai_Geo_Query::instance();
		$query = $this->query_with(
			[
				'geo_query' => [
					'latitude'  => self::TARRYTOWN[0],
					'longitude' => self::TARRYTOWN[1],
					'distance'  => 0,
				],
			]
		);

		// No distance means no limit. Empty field names fall back to the plugin's meta keys.
		$this->assertSame(
			"( geo_query_lat.meta_key = 'location_lat' AND geo_query_lng.meta_key = 'location_lng' AND " . $this->haversine_sql( 3959 ) . ' >= 0 )',
			$geo->posts_where( '', $query )
		);
	}

	public function test_posts_where_custom_fields(): void {
		$geo   = Mai_Geo_Query::instance();
		$query = $this->query_with( [ 'geo_query' => array_merge( $this->geo( 5 ), [ 'lat_field' => '_lat', 'lng_field' => '_lng' ] ) ] );

		$this->assertStringStartsWith( "( geo_query_lat.meta_key = '_lat' AND geo_query_lng.meta_key = '_lng' AND ", $geo->posts_where( '', $query ) );
	}

	public function test_posts_orderby(): void {
		$geo = Mai_Geo_Query::instance();

		$this->assertSame( 'geo_query_distance ASC', $geo->posts_orderby( 'x', $this->query_with( [ 'geo_query' => $this->geo(), 'orderby' => 'distance', 'order' => 'ASC' ] ) ) );
		$this->assertSame( 'geo_query_distance DESC', $geo->posts_orderby( 'x', $this->query_with( [ 'geo_query' => $this->geo(), 'orderby' => 'distance', 'order' => 'DESC' ] ) ) );
		$this->assertSame( 'x', $geo->posts_orderby( 'x', $this->query_with( [ 'geo_query' => $this->geo(), 'orderby' => 'title' ] ) ) );
	}

	/**
	 * Fixed September 16, 2026. Concatenation bound before ?:, so the fallback never ran.
	 */
	public function test_orderby_empty_order_falls_back_to_asc(): void {
		$geo = Mai_Geo_Query::instance();

		$this->assertSame( 'geo_query_distance ASC', $geo->posts_orderby( 'x', $this->query_with( [ 'geo_query' => $this->geo(), 'orderby' => 'distance' ] ) ) );
	}

	public function test_orderby_takes_only_the_two_sql_keywords(): void {
		$geo = Mai_Geo_Query::instance();

		$this->assertSame( 'geo_query_distance DESC', $geo->posts_orderby( 'x', $this->query_with( [ 'geo_query' => $this->geo(), 'orderby' => 'distance', 'order' => 'desc' ] ) ) );
		$this->assertSame( 'geo_query_distance ASC', $geo->posts_orderby( 'x', $this->query_with( [ 'geo_query' => $this->geo(), 'orderby' => 'distance', 'order' => 'ASC, (SELECT 1)' ] ) ) );
	}

	/**
	 * @return array<string, int>
	 */
	private function create_hudson_valley_locations(): array {
		return [
			'sleepy_hollow' => $this->create_location( [ 'location_lat' => '41.0857', 'location_lng' => '-73.8585' ], [ 'post_title' => 'Sleepy Hollow' ] ),
			'nyc'           => $this->create_location( [ 'location_lat' => '40.7128', 'location_lng' => '-74.0060' ], [ 'post_title' => 'New York' ] ),
			'albany'        => $this->create_location( [ 'location_lat' => '42.6526', 'location_lng' => '-73.7562' ], [ 'post_title' => 'Albany' ] ),
			'tarrytown'     => $this->create_location( [ 'location_lat' => '41.0762', 'location_lng' => '-73.8587' ], [ 'post_title' => 'Tarrytown' ] ),
			'no_coords'     => $this->create_location( [], [ 'post_title' => 'No coordinates' ] ),
		];
	}

	/**
	 * @param array<string, mixed> $geo
	 *
	 * @return list<int>
	 */
	private function run_geo_query( array $geo, string $order = 'ASC' ): array {
		$query = new WP_Query(
			[
				'post_type'      => 'mai_location',
				'posts_per_page' => -1,
				'orderby'        => 'distance',
				'order'          => $order,
				'geo_query'      => $geo,
			]
		);

		return wp_list_pluck( $query->posts, 'ID' );
	}

	public function test_real_query_filters_by_distance_and_orders_nearest_first(): void {
		$ids = $this->create_hudson_valley_locations();

		$result = $this->run_geo_query( $this->geo( 30 ) );

		// Albany is about 110 miles away, and a location with no coordinates never joins.
		$this->assertNotContains( $ids['albany'], $result );
		$this->assertNotContains( $ids['no_coords'], $result );
		$this->assertSame( [ $ids['sleepy_hollow'], $ids['nyc'] ], array_values( array_diff( $result, [ $ids['tarrytown'] ] ) ) );
	}

	public function test_real_query_location_at_the_exact_origin_is_included_with_a_limit(): void {
		$ids = $this->create_hudson_valley_locations();

		$result = $this->run_geo_query( $this->geo( 30 ) );

		$this->assertSame( [ $ids['tarrytown'], $ids['sleepy_hollow'], $ids['nyc'] ], $result );
	}

	/**
	 * Fixed September 16, 2026. With no limit the WHERE used the distance itself as the
	 * condition, and a distance of 0 is false.
	 */
	public function test_no_distance_limit_includes_location_at_exact_origin(): void {
		$ids = $this->create_hudson_valley_locations();

		$result = $this->run_geo_query( $this->geo( 0 ), 'DESC' );

		$this->assertSame( [ $ids['albany'], $ids['nyc'], $ids['sleepy_hollow'], $ids['tarrytown'] ], $result );
	}

	public function test_real_query_kilometers_shrinks_the_radius(): void {
		$ids = $this->create_hudson_valley_locations();

		// New York is about 26 miles or 42 km away.
		$this->assertContains( $ids['nyc'], $this->run_geo_query( $this->geo( 30, 'miles' ) ) );
		$this->assertNotContains( $ids['nyc'], $this->run_geo_query( $this->geo( 30, 'km' ) ) );
	}

	public function test_real_query_applies_to_any_post_type(): void {
		$post_id = self::factory()->post->create();
		update_post_meta( $post_id, 'location_lat', '41.0857' );
		update_post_meta( $post_id, 'location_lng', '-73.8585' );

		$query = new WP_Query( [ 'post_type' => 'post', 'geo_query' => $this->geo( 5 ), 'orderby' => 'distance' ] );

		$this->assertSame( [ $post_id ], wp_list_pluck( $query->posts, 'ID' ) );
	}

	public function test_get_distance_on_queried_posts(): void {
		$ids   = $this->create_hudson_valley_locations();
		$query = new WP_Query( [ 'post_type' => 'mai_location', 'p' => $ids['nyc'], 'geo_query' => $this->geo( 0 ) ] );
		$post  = $query->posts[0];

		// The raw column comes back from MySQL as a string.
		$this->assertIsString( $post->geo_query_distance );
		$this->assertEqualsWithDelta( 25.8, (float) $post->geo_query_distance, 0.5 );

		$this->assertSame( $post->geo_query_distance, Mai_Geo_Query::get_distance( $post ) );
		$this->assertSame( round( (float) $post->geo_query_distance, 1 ), Mai_Geo_Query::get_distance( $post, 1 ) );
		$this->assertSame( round( (float) $post->geo_query_distance ), Mai_Geo_Query::get_distance( $post, 0 ) );

		$GLOBALS['post'] = $post;
		$this->assertSame( round( (float) $post->geo_query_distance, 1 ), mailocations_get_distance() );
	}

	public function test_pins_bug_get_distance_returns_false_though_typed_float(): void {
		$post = get_post( $this->create_location() );

		// Correct would match the @return float docblock, or the docblock should say float|false.
		$this->assertFalse( Mai_Geo_Query::get_distance( $post ) );
	}
}

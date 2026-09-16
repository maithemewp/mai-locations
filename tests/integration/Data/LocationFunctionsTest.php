<?php

declare(strict_types=1);

namespace Mai\Locations\Tests\Integration\Data;

use Mai\Locations\Tests\Integration\Data\Support\CapturesErrors;
use Mai\Locations\Tests\Integration\Data\Support\HttpMock;
use Mai\Locations\Tests\TestCase;
use WP_Error;

require_once __DIR__ . '/Support/HttpMock.php';
require_once __DIR__ . '/Support/CapturesErrors.php';

/**
 * Pins the location data functions in includes/functions-locations.php.
 *
 * The Google API key is read through a static options cache filled before any test runs, so it
 * is always empty here. The two geocoding updaters can only be tested up to their no-key bail.
 */
final class LocationFunctionsTest extends TestCase {

	use HttpMock;
	use CapturesErrors;

	public function set_up(): void {
		parent::set_up();

		$this->mock_http( fn( string $url ) => new WP_Error( 'unmocked', $url ) );
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	private static function us_components(): array {
		$json = json_decode( (string) file_get_contents( __DIR__ . '/fixtures/geocode-ok.json' ), true );

		return $json['results'][0]['address_components'];
	}

	public function test_api_key_is_empty_for_the_whole_run(): void {
		update_option( 'mai_locations', [ 'google_api_key' => 'SET-TOO-LATE' ] );

		$this->assertSame( '', mailocations_get_option( 'google_api_key' ) );
	}

	/*
	 * mailocation_get_user_locations()
	 */

	public function test_user_locations_are_published_and_pending_by_the_current_user_then_cached(): void {
		$user  = self::factory()->user->create();
		$other = self::factory()->user->create();
		wp_set_current_user( $user );

		$publish = self::factory()->post->create( [ 'post_author' => $user, 'post_status' => 'publish' ] );
		$pending = self::factory()->post->create( [ 'post_author' => $user, 'post_status' => 'pending' ] );
		self::factory()->post->create( [ 'post_author' => $user, 'post_status' => 'draft' ] );
		$other_post = self::factory()->post->create( [ 'post_author' => $other, 'post_status' => 'publish' ] );

		$this->assertEqualsCanonicalizing( [ $publish, $pending ], mailocation_get_user_locations( 'post' ) );

		// The answer is cached for the rest of the request, so a location added after the first
		// call is not seen.
		$extra = self::factory()->post->create( [ 'post_author' => $user, 'post_status' => 'publish' ] );
		$this->assertEqualsCanonicalizing( [ $publish, $pending ], mailocation_get_user_locations( 'post' ) );

		// Fixed September 16, 2026. The cache was keyed by post type alone, so the next user in
		// the same process got this user's locations.
		wp_set_current_user( $other );
		$this->assertSame( [ $other_post ], mailocation_get_user_locations( 'post' ) );

		// Back to the first user, whose cached answer is still there.
		wp_set_current_user( $user );
		$this->assertEqualsCanonicalizing( [ $publish, $pending ], mailocation_get_user_locations( 'post' ) );
		$this->assertNotContains( $extra, mailocation_get_user_locations( 'post' ) );
	}

	public function test_user_locations_are_empty_when_logged_out(): void {
		wp_set_current_user( 0 );
		$this->create_location( [], [ 'post_author' => self::factory()->user->create() ] );

		$this->assertSame( [], mailocation_get_user_locations() );
	}

	/*
	 * mailocations_create_location()
	 */

	public function test_create_location_defaults_status_forces_type_fills_meta_defaults_and_links_user(): void {
		$user     = self::factory()->user->create();
		$filtered = [];

		add_filter(
			'mailocations_post_args',
			static function ( array $args, $user_id ) use ( &$filtered ): array {
				$filtered   = [ $args, $user_id ];
				$args['post_type'] = 'post';

				return $args;
			},
			10,
			2
		);

		$id = mailocations_create_location(
			[ 'post_title' => 'Philipsburg Manor', 'post_type' => 'page' ],
			[ 'address_city' => 'Sleepy Hollow', 'custom_key' => 'kept' ],
			$user
		);

		$this->assertIsInt( $id );
		$post = get_post( $id );
		$this->assertSame( 'mai_location', $post->post_type );
		$this->assertSame( 'publish', $post->post_status );

		$this->assertSame( $user, $filtered[1] );
		$this->assertSame( 'publish', $filtered[0]['post_status'] );
		$this->assertSame( 'page', $filtered[0]['post_type'] );
		$this->assertSame( array_merge( mailocations_get_fields_defaults(), [ 'address_city' => 'Sleepy Hollow', 'custom_key' => 'kept' ] ), $filtered[0]['meta_input'] );

		$this->assertSame( 'Sleepy Hollow', get_post_meta( $id, 'address_city', true ) );
		$this->assertSame( 'kept', get_post_meta( $id, 'custom_key', true ) );
		$this->assertSame( 'US', get_post_meta( $id, 'address_country', true ) );
		$this->assertTrue( metadata_exists( 'post', $id, 'location' ) );

		$this->assertSame( [ $id ], get_user_meta( $user, 'user_locations', true ) );

		// No API key, so no geocoding request and no map field.
		$this->assertSame( [], $this->http_requests );
		$this->assertFalse( metadata_exists( 'post', $id, 'mai_location_location' ) );
	}

	/**
	 * Fixed September 16, 2026. The field defaults were merged last and overwrote what the
	 * caller passed, so a Canadian location was saved as US.
	 */
	public function test_create_location_meta_input_beats_the_field_defaults(): void {
		$id = mailocations_create_location(
			[ 'post_title' => 'Canada Place', 'meta_input' => [ 'address_country' => 'CA', 'extra' => 'x' ] ],
			[]
		);

		$this->assertSame( 'CA', get_post_meta( $id, 'address_country', true ) );
		$this->assertSame( 'x', get_post_meta( $id, 'extra', true ) );
	}

	public function test_create_location_meta_args_beat_post_args_meta_input(): void {
		$id = mailocations_create_location(
			[ 'post_title' => 'Both', 'meta_input' => [ 'address_city' => 'From post args' ] ],
			[ 'address_city' => 'From meta args' ]
		);

		$this->assertSame( 'From meta args', get_post_meta( $id, 'address_city', true ) );
	}

	/**
	 * Changed September 16, 2026. A failed insert returned 0 and said nothing.
	 */
	public function test_create_location_without_user_links_nobody_and_returns_an_error_on_empty_post(): void {
		$id = mailocations_create_location( [ 'post_title' => 'Nobody' ], [] );
		$this->assertGreaterThan( 0, $id );

		$result = mailocations_create_location( [], [] );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'empty_content', $result->get_error_code() );
	}

	/*
	 * mailocations_add_location_to_user()
	 */

	public function test_add_location_to_user_returns_wp_error_for_missing_user(): void {
		$result = mailocations_add_location_to_user( 5, 999999 );

		// Docblock says void.
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'no user', $result->get_error_code() );
		$this->assertSame( 'No user with the ID of 999999', $result->get_error_message() );
	}

	/**
	 * Fixed September 16, 2026. The same location was added again on every call.
	 */
	public function test_add_location_to_user_stores_each_location_once(): void {
		$user = self::factory()->user->create();

		$this->assertTrue( mailocations_add_location_to_user( 12, $user ) );
		$this->assertSame( [ 12 ], get_user_meta( $user, 'user_locations', true ) );

		$this->assertTrue( mailocations_add_location_to_user( 12, $user ) );
		$this->assertSame( [ 12 ], get_user_meta( $user, 'user_locations', true ) );

		// Every value is cast with absint, zeros are dropped, and the keys are renumbered.
		update_user_meta( $user, 'user_locations', [ '7', 'abc', 0 ] );
		$this->assertTrue( mailocations_add_location_to_user( '8', $user ) );
		$this->assertSame( [ 7, 8 ], get_user_meta( $user, 'user_locations', true ) );
	}

	/*
	 * mailocations_create_location_from_woocommerce_user()
	 */

	public function test_woocommerce_user_location_works_without_woocommerce(): void {
		$this->assertFalse( class_exists( 'WooCommerce' ) );

		$result = mailocations_create_location_from_woocommerce_user( 999999 );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'no user', $result->get_error_code() );

		$user = self::factory()->user->create(
			[
				'first_name'   => 'Katrina',
				'last_name'    => 'Van Tassel',
				'display_name' => 'Katrina VT',
				'user_url'     => 'https://farm.example',
			]
		);
		update_user_meta( $user, 'billing_company', 'Van Tassel Farm' );
		update_user_meta( $user, 'billing_city', 'Tarrytown' );
		update_user_meta( $user, 'billing_phone', '555-0100' );

		$id = mailocations_create_location_from_woocommerce_user( $user, [ 'post_status' => 'pending', 'post_type' => 'post' ] );

		$post = get_post( $id );
		$this->assertSame( 'Van Tassel Farm', $post->post_title );
		$this->assertSame( 'pending', $post->post_status );
		$this->assertSame( 'mai_location', $post->post_type );
		$this->assertSame( 'Tarrytown', get_post_meta( $id, 'address_city', true ) );
		$this->assertSame( '555-0100', get_post_meta( $id, 'location_phone', true ) );
		$this->assertSame( 'https://farm.example', get_post_meta( $id, 'location_url', true ) );
		$this->assertSame( 'Katrina', get_post_meta( $id, 'billing_first_name', true ) );
		$this->assertSame( 'Van Tassel', get_post_meta( $id, 'billing_last_name', true ) );
		// Empty billing country overrides the 'US' default.
		$this->assertSame( '', get_post_meta( $id, 'address_country', true ) );
		$this->assertSame( [ $id ], get_user_meta( $user, 'user_locations', true ) );

		delete_user_meta( $user, 'billing_company' );
		$this->assertSame( 'Katrina VT', get_post( mailocations_create_location_from_woocommerce_user( $user ) )->post_title );
	}

	/*
	 * mailocations_get_address_meta_from_components()
	 */

	public function test_address_meta_from_us_components(): void {
		$components   = self::us_components();
		$components[] = [ 'long_name' => 'no types', 'short_name' => 'no types' ];
		$components[] = [ 'long_name' => 'empty types', 'short_name' => 'empty types', 'types' => [] ];

		$this->assertSame(
			[
				'address_city'      => 'Sleepy Hollow',
				'address_state'     => 'NY',
				'address_state_int' => '',
				'address_country'   => 'US',
				'address_postcode'  => '10591',
				'address_street'    => '150 Broadway',
			],
			mailocations_get_address_meta_from_components( $components )
		);
	}

	public function test_address_meta_outside_us_clears_state_and_uses_short_names(): void {
		$components = [
			[ 'long_name' => 'Quebec', 'short_name' => 'QC', 'types' => [ 'administrative_area_level_1' ] ],
			[ 'long_name' => 'Canada', 'short_name' => 'CA', 'types' => [ 'country' ] ],
			[ 'long_name' => 'Rue Saint-Paul', 'short_name' => 'Rue St-Paul', 'types' => [ 'route' ] ],
		];

		$this->assertSame(
			[
				'address_state'     => '',
				'address_state_int' => 'QC',
				'address_country'   => 'CA',
				'address_street'    => ' Rue St-Paul',
			],
			mailocations_get_address_meta_from_components( $components )
		);
	}

	/**
	 * Fixed September 16, 2026. A result with no country warned about the missing key.
	 */
	public function test_address_meta_without_country_is_treated_as_non_us(): void {
		[ $result, $warnings ] = $this->capture_errors( fn() => mailocations_get_address_meta_from_components( [] ) );

		$this->assertSame( [ 'address_street' => ' ', 'address_state' => '' ], $result );
		$this->assertSame( [], $warnings );
	}

	/*
	 * mailocations_get_google_maps_result()
	 */

	private function geocode_response( callable $responder ): void {
		remove_all_filters( 'pre_http_request' );
		$this->http_requests = [];
		$this->mock_http( $responder );
	}

	public function test_google_maps_result_returns_first_result_on_ok(): void {
		$body = (string) file_get_contents( __DIR__ . '/fixtures/geocode-ok.json' );
		$this->geocode_response( fn() => self::response( 200, $body ) );

		$result = mailocations_get_google_maps_result( 'https://maps.google.com/maps/api/geocode/json?address=x' );

		$this->assertSame( json_decode( $body, true )['results'][0], $result );
		$this->assertSame( 'https://maps.google.com/maps/api/geocode/json?address=x', $this->http_requests[0]['url'] );
		$this->assertSame( 'GET', $this->http_requests[0]['args']['method'] );
		$this->assertSame( 5, $this->http_requests[0]['args']['timeout'] );
	}

	/**
	 * @dataProvider provide_failed_geocode_responses
	 *
	 * @param array<string, mixed>|null $response Null means a WP_Error.
	 */
	public function test_google_maps_result_is_empty_array_on_failure( ?array $response ): void {
		$this->geocode_response( fn() => $response ?? new WP_Error( 'http_request_failed', 'timeout' ) );

		$this->assertSame( [], mailocations_get_google_maps_result( 'https://maps.google.com/maps/api/geocode/json' ) );
	}

	/**
	 * @return array<string, array{0: array<string, mixed>|null}>
	 */
	public static function provide_failed_geocode_responses(): array {
		$ok = static fn( string $body, int $code = 200 ): array => [
			'headers'  => [],
			'body'     => $body,
			'response' => [ 'code' => $code, 'message' => '' ],
			'cookies'  => [],
			'filename' => null,
		];

		return [
			'wp error'        => [ null ],
			'500'             => [ $ok( '{"status":"OK","results":[{"place_id":"x"}]}', 500 ) ],
			'empty body'      => [ $ok( '' ) ],
			'invalid json'    => [ $ok( 'not json' ) ],
			'no status'       => [ $ok( '{"results":[{"place_id":"x"}]}' ) ],
			'zero results'    => [ $ok( '{"results":[],"status":"ZERO_RESULTS"}' ) ],
			'denied'          => [ $ok( '{"error_message":"The provided API key is invalid.","results":[],"status":"REQUEST_DENIED"}' ) ],
			'ok but empty'    => [ $ok( '{"results":[],"status":"OK"}' ) ],
			'ok but a string' => [ $ok( '{"results":"nope","status":"OK"}' ) ],
		];
	}

	/*
	 * The two geocoding updaters.
	 */

	public function test_update_address_from_google_map_bails_without_api_key(): void {
		$id = $this->create_location( [ 'location_lat' => '41.0857', 'location_lng' => '-73.8587' ] );

		mailocations_update_address_from_google_map( $id );

		$this->assertSame( [], $this->http_requests );
		$this->assertFalse( metadata_exists( 'post', $id, 'address_city' ) );
	}

	public function test_update_google_map_from_address_bails_without_api_key(): void {
		$id = $this->create_location( [ 'address_street' => '150 Broadway', 'address_city' => 'Sleepy Hollow' ] );

		mailocations_update_google_map_from_address( $id );

		$this->assertSame( [], $this->http_requests );
		$this->assertFalse( metadata_exists( 'post', $id, 'mai_location_location' ) );
		$this->assertFalse( metadata_exists( 'post', $id, 'place_id' ) );
	}
}

<?php

declare(strict_types=1);

namespace Mai\Locations\Tests\Integration\Data;

use Mai\Locations\Tests\Integration\Data\Support\HttpMock;
use Mai\Locations\Tests\TestCase;
use ReflectionClass;
use WP_Error;

/**
 * Filling the address fields from the map, GitHub issue #6.
 *
 * Reported September 17, 2026 on naturebasedtherapytraining.com: an admin searched the map for
 * "Victoria, British Columbia, V8X 3W1", the pin landed in Victoria, and the address fields
 * stayed empty with the country still on its default, United States. The front-end map then
 * said "United States" for a therapist in Canada.
 *
 * The map value ACF saves already holds the parts of the place that was picked: city, state,
 * post code, country. The plugin ignored them and asked Google again from the pin's
 * coordinates, which needs a server-side key and gave nothing back there. These tests take the
 * map value from that very post.
 */
final class AddressFromMapTest extends TestCase {

	use HttpMock;

	/**
	 * @var list<string>
	 */
	private array $requests = [];

	public function set_up(): void {
		parent::set_up();

		$this->requests = [];
		$this->mock_http(
			function ( string $url ) {
				$this->requests[] = $url;
				return new WP_Error( 'unmocked', $url );
			}
		);
	}

	/**
	 * Post 762's saved map value, as read from the live site on September 23, 2026.
	 *
	 * @return array<string, mixed>
	 */
	private static function victoria(): array {
		return [
			'address'       => 'Victoria, British Columbia, V8X 3W1',
			'lat'           => 48.4649434,
			'lng'           => -123.3731096,
			'zoom'          => 4,
			'place_id'      => 'ChIJ_-9pY7pzj1QROYHiqM9k12E',
			'city'          => 'Victoria',
			'state'         => 'British Columbia',
			'state_short'   => 'BC',
			'post_code'     => 'V8X 3W1',
			'country'       => 'Canada',
			'country_short' => 'CA',
		];
	}

	private function location_with_map( array $map ): int {
		$id = $this->create_location( [ 'address_country' => 'US' ] );
		update_field( 'mai_location_location', $map, $id );
		update_post_meta( $id, 'location_lat', (string) $map['lat'] );
		update_post_meta( $id, 'location_lng', (string) $map['lng'] );

		return $id;
	}

	public function test_the_address_comes_from_the_place_that_was_picked(): void {
		$id = $this->location_with_map( self::victoria() );

		mailocations_update_address_from_google_map( $id );

		$this->assertSame( 'CA', get_post_meta( $id, 'address_country', true ) );
		$this->assertSame( 'Victoria', get_post_meta( $id, 'address_city', true ) );
		$this->assertSame( 'V8X 3W1', get_post_meta( $id, 'address_postcode', true ) );
		$this->assertSame( 'BC', get_post_meta( $id, 'address_state_int', true ) );
		$this->assertSame( '', get_post_meta( $id, 'address_state', true ) );
	}

	/**
	 * No API key is needed, and no second request goes to Google: the parts are already in the
	 * value. That second request is what failed on the reporter's site.
	 */
	public function test_it_needs_no_api_key_and_asks_google_nothing(): void {
		mailocations_update_option( 'google_api_key', '' );
		$id = $this->location_with_map( self::victoria() );

		mailocations_update_address_from_google_map( $id );

		$this->assertSame( 'CA', get_post_meta( $id, 'address_country', true ) );
		$this->assertSame( [], $this->requests );
	}

	/**
	 * With a key set, the picked place still wins over a second lookup.
	 */
	public function test_with_a_key_it_still_uses_the_place_that_was_picked(): void {
		mailocations_update_option( 'google_api_key', 'a-key' );
		$id = $this->location_with_map( self::victoria() );

		mailocations_update_address_from_google_map( $id );

		$this->assertSame( 'CA', get_post_meta( $id, 'address_country', true ) );
		$this->assertSame( [], $this->requests );
	}

	/**
	 * A US pick fills the US state and clears the international one, as the Google path does.
	 */
	public function test_a_us_pick_fills_the_us_state(): void {
		$id = $this->location_with_map(
			[
				'address'           => '381 N Broadway, Sleepy Hollow, NY 10591, USA',
				'lat'               => 41.0859,
				'lng'               => -73.8590,
				'street_number'     => '381',
				'street_name'       => 'North Broadway',
				'street_name_short' => 'N Broadway',
				'city'              => 'Sleepy Hollow',
				'state'             => 'New York',
				'state_short'       => 'NY',
				'post_code'         => '10591',
				'country'           => 'United States',
				'country_short'     => 'US',
			]
		);

		mailocations_update_address_from_google_map( $id );

		$this->assertSame( 'US', get_post_meta( $id, 'address_country', true ) );
		$this->assertSame( '381 N Broadway', get_post_meta( $id, 'address_street', true ) );
		$this->assertSame( 'NY', get_post_meta( $id, 'address_state', true ) );
		$this->assertSame( '', get_post_meta( $id, 'address_state_int', true ) );
	}

	/**
	 * An older map value with no parts, only an address and a pin, still goes to Google the way
	 * it always did. Without a key that is a quiet no-op, exactly as before.
	 */
	public function test_an_old_value_with_no_parts_falls_back_to_google(): void {
		mailocations_update_option( 'google_api_key', 'a-key' );
		$id = $this->location_with_map( [ 'address' => 'Somewhere', 'lat' => 41.0, 'lng' => -73.0 ] );

		mailocations_update_address_from_google_map( $id );

		$this->assertCount( 1, $this->requests );
		$this->assertStringContainsString( 'latlng=41,-73', $this->requests[0] );
		$this->assertSame( 'US', get_post_meta( $id, 'address_country', true ) );
	}

	/**
	 * The whole reported flow, as a Dashboard save, in the order it really runs: the listener
	 * reads the fields at priority 4, ACF saves the map at 10, and the sync runs at 20. The map
	 * was searched, every address field was left as it was, and the country select still posted
	 * its default, US.
	 */
	public function test_a_save_with_only_the_map_searched_fills_the_country(): void {
		mailocations_update_option( 'google_api_key', '' );
		$id = $this->create_location( [ 'address_country' => 'US' ] );
		remove_all_actions( 'acf/save_post' );

		$_POST = [
			'_acf_post_id' => (string) $id,
			'acf'          => [
				'mai_location_address_country'  => 'US',
				'mai_location_address_street'   => '',
				'mai_location_address_city'     => '',
				'mai_location_address_postcode' => '',
				'mai_location_location'         => wp_slash( wp_json_encode( self::victoria() ) ),
			],
		];

		$listener = ( new ReflectionClass( \Mai_Locations_Location_Form_Listener::class ) )->newInstanceWithoutConstructor();
		$listener->before_save_post( $id );

		// What ACF itself does at the default priority: save the map and, through the plugin's
		// own update_value hook, the pin's coordinates.
		add_action(
			'acf/save_post',
			static function ( $post_id ): void {
				update_field( 'mai_location_location', self::victoria(), $post_id );
				update_post_meta( $post_id, 'location_lat', '48.4649434' );
				update_post_meta( $post_id, 'location_lng', '-123.3731096' );
			},
			10
		);

		do_action( 'acf/save_post', $id );

		$this->assertSame( 'CA', get_post_meta( $id, 'address_country', true ) );
		$this->assertSame( 'Victoria', get_post_meta( $id, 'address_city', true ) );
		$this->assertSame( [], $this->requests );
	}
}

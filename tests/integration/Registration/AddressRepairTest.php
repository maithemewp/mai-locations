<?php

declare(strict_types=1);

namespace Mai\Locations\Tests\Integration\Registration;

use Mai\Locations\Admin\Upgrade;
use Mai\Locations\Tests\Integration\Data\Support\HttpMock;
use Mai\Locations\Tests\TestCase;
use WP_Error;

/**
 * The one-off repair for locations saved before GitHub issue #6 was fixed.
 *
 * Those locations were placed by searching their map, so their address fields stayed empty
 * and their country kept the default, US. The fix only fills an address when the map changes,
 * so without this they would stay empty until someone moved each pin.
 */
final class AddressRepairTest extends TestCase {

	use HttpMock;

	/**
	 * @var list<string>
	 */
	private array $requests = [];

	public function set_up(): void {
		parent::set_up();

		delete_option( 'mai_locations_repairs' );
		$this->requests = [];
		$this->mock_http(
			function ( string $url ) {
				$this->requests[] = $url;
				return new WP_Error( 'unmocked', $url );
			}
		);
	}

	/**
	 * @param array<string, string> $address Address meta already saved.
	 * @param array<string, mixed>  $map     The saved map value.
	 */
	private function location( array $address, array $map ): int {
		$id = $this->create_location( array_merge( [ 'address_country' => 'US' ], $address ) );
		update_field( 'mai_location_location', $map, $id );

		return $id;
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function victoria(): array {
		return [
			'address'       => 'Victoria, British Columbia, V8X 3W1',
			'lat'           => 48.4649434,
			'lng'           => -123.3731096,
			'city'          => 'Victoria',
			'state_short'   => 'BC',
			'post_code'     => 'V8X 3W1',
			'country_short' => 'CA',
		];
	}

	public function test_an_empty_address_is_filled_from_its_map(): void {
		$id = $this->location( [], self::victoria() );

		$this->assertSame( 1, Upgrade::backfill_addresses_from_map() );

		$this->assertSame( 'CA', get_post_meta( $id, 'address_country', true ) );
		$this->assertSame( 'Victoria', get_post_meta( $id, 'address_city', true ) );
		$this->assertSame( 'V8X 3W1', get_post_meta( $id, 'address_postcode', true ) );
	}

	/**
	 * Any typed street, city or post code means a person entered this address. It is left
	 * exactly as it is, even where the map disagrees.
	 *
	 * @dataProvider typed_parts
	 */
	public function test_a_typed_address_is_never_overwritten( string $key ): void {
		$id = $this->location( [ $key => 'Typed by hand' ], self::victoria() );

		$this->assertSame( 0, Upgrade::backfill_addresses_from_map() );

		$this->assertSame( 'US', get_post_meta( $id, 'address_country', true ) );
		$this->assertSame( 'Typed by hand', get_post_meta( $id, $key, true ) );
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function typed_parts(): array {
		return [
			'street'    => [ 'address_street' ],
			'city'      => [ 'address_city' ],
			'post code' => [ 'address_postcode' ],
		];
	}

	/**
	 * An old map value with no parts is left alone, and nothing goes to Google, even with a
	 * key set: a repair on every Dashboard load must never spend API quota.
	 */
	public function test_a_map_without_parts_is_left_alone_and_google_is_never_asked(): void {
		mailocations_update_option( 'google_api_key', 'a-key' );
		$id = $this->location( [], [ 'address' => 'Somewhere', 'lat' => 41.0, 'lng' => -73.0 ] );

		$this->assertSame( 0, Upgrade::backfill_addresses_from_map() );

		$this->assertSame( 'US', get_post_meta( $id, 'address_country', true ) );
		$this->assertSame( [], $this->requests );
	}

	public function test_it_runs_once_and_then_never_again(): void {
		$this->location( [], self::victoria() );
		$this->assertSame( 1, Upgrade::backfill_addresses_from_map() );
		$this->assertArrayHasKey( 'address_from_map', (array) get_option( 'mai_locations_repairs' ) );

		// A location added later with the same gap is not this repair's job: the fix handles
		// new saves.
		$later = $this->location( [], self::victoria() );

		$this->assertSame( 0, Upgrade::backfill_addresses_from_map() );
		$this->assertSame( 'US', get_post_meta( $later, 'address_country', true ) );
	}

	/**
	 * A large site finishes over a few page loads, and the repair is only marked done once a
	 * call finds nothing left.
	 */
	public function test_it_stops_at_the_limit_and_finishes_on_the_next_call(): void {
		$ids = [];
		for ( $i = 0; $i < 3; $i++ ) {
			$ids[] = $this->location( [], self::victoria() );
		}

		$this->assertSame( 2, Upgrade::backfill_addresses_from_map( 2 ) );
		$this->assertArrayNotHasKey( 'address_from_map', (array) get_option( 'mai_locations_repairs', [] ) );

		$this->assertSame( 1, Upgrade::backfill_addresses_from_map( 2 ) );
		$this->assertArrayHasKey( 'address_from_map', (array) get_option( 'mai_locations_repairs' ) );

		foreach ( $ids as $id ) {
			$this->assertSame( 'CA', get_post_meta( $id, 'address_country', true ) );
		}
	}

	/**
	 * It runs through the normal upgrade, and on a site already at the current version,
	 * which is where the nature site was when this was written.
	 */
	public function test_it_runs_from_the_upgrade_on_a_site_already_current(): void {
		update_option( 'mai_locations', [ 'version_first' => '1.1.0', 'version_db' => MAI_LOCATIONS_VERSION ] );
		\Mai\Locations\Cache::flush();
		$id = $this->location( [], self::victoria() );

		Upgrade::run();

		$this->assertSame( 'CA', get_post_meta( $id, 'address_country', true ) );
	}
}

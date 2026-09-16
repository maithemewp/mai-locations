<?php

declare(strict_types=1);

namespace Mai\Locations\Tests\Integration\Registration;

use Mai\Locations\Tests\TestCase;

require_once __DIR__ . '/ScenarioRunner.php';

/**
 * Post type and taxonomy lookups in includes/functions-locations.php.
 */
final class LocationFunctionsTest extends TestCase {

	use ScenarioRunner;

	public function tear_down(): void {
		if ( taxonomy_exists( 'mai_test_tax' ) ) {
			unregister_taxonomy( 'mai_test_tax' );
		}

		if ( post_type_exists( 'mai_test_type' ) ) {
			unregister_post_type( 'mai_test_type' );
		}

		parent::tear_down();
	}

	/**
	 * Fixed September 16, 2026. The country and state were checked by key rather than by value,
	 * so both were always dropped and the geocoding request went out without them. The API key
	 * is cached at boot, so this runs in a child process.
	 */
	public function test_geocoding_address_includes_the_country_and_state(): void {
		$result = $this->run_scenario(
			[
				'probe'   => 'geocode_url',
				'options' => [ 'google_api_key' => 'scenario-key' ],
				'meta'    => [
					'address_street'   => '150 Broadway',
					'address_city'     => 'Sleepy Hollow',
					'address_state'    => 'NY',
					'address_country'  => 'US',
					'address_postcode' => '10591',
				],
			]
		);

		$this->assertCount( 1, $result['requests'] );
		$this->assertStringContainsString( 'address=' . urlencode( 'US,150 Broadway,Sleepy Hollow,NY,10591' ), $result['requests'][0] );
	}

	public function test_location_post_types(): void {
		$this->assertSame( [ 'mai_location' => [ 'plural' => 'Locations', 'singular' => 'Location' ] ], mailocations_get_location_post_types() );
	}

	public function test_singular_and_plural_labels(): void {
		$this->assertSame( 'Location', mailocations_get_singular_label( 'mai_location' ) );
		$this->assertSame( 'Locations', mailocations_get_plural_label( 'mai_location' ) );
		$this->assertSame( '', mailocations_get_singular_label( 'post' ) );
		$this->assertSame( '', mailocations_get_plural_label( 'post' ) );
	}

	public function test_location_taxonomies(): void {
		$this->assertSame( [ 'mai_location_cat' => 'Location Categories' ], mailocations_get_location_taxonomies() );
		$this->assertSame( [ 'mai_location_cat' => 'Location Categories' ], mailocations_get_location_taxonomies( 'mai_location' ) );
		$this->assertSame( [], mailocations_get_location_taxonomies( 'post' ) );
	}

	public function test_location_taxonomies_underscored(): void {
		$this->assertSame( [ '_mai_location_cat' => 'Location Categories' ], mailocations_get_location_taxonomies_underscored() );
	}

	public function test_pins_cache_post_type_added_later_is_not_a_location_type(): void {
		mailocations_get_location_post_types();

		register_post_type( 'mai_test_type', [ 'public' => true, 'supports' => [ 'title', 'mai-locations' ] ] );

		// Cached in a static for the request, so a type registered after the first call is missed.
		$this->assertArrayNotHasKey( 'mai_test_type', mailocations_get_location_post_types() );
		$this->assertTrue( post_type_supports( 'mai_test_type', 'mai-locations' ) );
	}

	public function test_pins_cache_taxonomy_added_later_is_not_a_location_taxonomy(): void {
		mailocations_get_location_taxonomies();

		register_taxonomy( 'mai_test_tax', [ 'mai_location' ], [ 'label' => 'Test Taxonomy' ] );

		// Cached in a static for the request, so a taxonomy registered after the first call is missed.
		$this->assertArrayNotHasKey( 'mai_test_tax', mailocations_get_location_taxonomies() );
		$this->assertArrayNotHasKey( 'mai_test_tax', mailocations_get_location_taxonomies( 'mai_location' ) );
		$this->assertArrayNotHasKey( '_mai_test_tax', mailocations_get_location_taxonomies_underscored() );
	}
}

<?php

declare(strict_types=1);

namespace Mai\Locations\Tests\Integration\Registration;

use Mai\Locations\Tests\TestCase;

/**
 * Post type and taxonomy lookups in includes/functions-locations.php.
 */
final class LocationFunctionsTest extends TestCase {

	public function tear_down(): void {
		if ( taxonomy_exists( 'mai_test_tax' ) ) {
			unregister_taxonomy( 'mai_test_tax' );
		}

		if ( post_type_exists( 'mai_test_type' ) ) {
			unregister_post_type( 'mai_test_type' );
		}

		parent::tear_down();
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

<?php

declare(strict_types=1);

namespace Mai\Locations\Tests\Integration;

use Mai\Locations\Tests\TestCase;

/**
 * Checks the harness itself, so a broken setup fails here with an obvious cause instead of
 * scattering errors through the feature tests.
 */
final class HarnessTest extends TestCase {

	public function test_wordpress_is_loaded(): void {
		$this->assertTrue( function_exists( 'register_post_type' ) );
	}

	public function test_acf_pro_is_loaded(): void {
		$this->assertTrue( function_exists( 'acf_add_local_field_group' ) );
		$this->assertTrue( function_exists( 'acf_add_options_sub_page' ), 'Options pages need ACF Pro, not free ACF' );
	}

	public function test_plugin_is_loaded(): void {
		$this->assertTrue( class_exists( 'Mai_Locations_Plugin' ) );
		$this->assertTrue( defined( 'MAI_LOCATIONS_VERSION' ) );
	}

	public function test_post_type_and_taxonomy_are_registered(): void {
		$this->assertTrue( post_type_exists( 'mai_location' ) );
		$this->assertTrue( taxonomy_exists( 'mai_location_cat' ) );
	}

	public function test_factories_and_utf8mb4(): void {
		$id = self::factory()->post->create( [ 'post_type' => 'mai_location', 'post_title' => 'Café Łódź 🎉' ] );

		$this->assertSame( 'Café Łódź 🎉', get_post( $id )->post_title );
	}
}

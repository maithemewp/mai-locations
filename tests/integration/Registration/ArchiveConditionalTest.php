<?php

declare(strict_types=1);

namespace Mai\Locations\Tests\Integration\Registration;

use Mai\Locations\Tests\TestCase;

/**
 * mailocations_is_archive() in includes/functions-conditional.php.
 */
final class ArchiveConditionalTest extends TestCase {

	public function test_true_on_post_type_archive(): void {
		$this->go_to( get_post_type_archive_link( 'mai_location' ) );

		$this->assertTrue( mailocations_is_archive() );
	}

	public function test_true_on_location_category_archive(): void {
		$term_id = self::factory()->term->create( [ 'taxonomy' => 'mai_location_cat', 'name' => 'Cafes' ] );

		$this->go_to( get_term_link( $term_id ) );

		$this->assertTrue( mailocations_is_archive() );
	}

	public function test_false_on_single_location(): void {
		$this->go_to( get_permalink( $this->create_location() ) );

		$this->assertFalse( mailocations_is_archive() );
	}

	public function test_false_on_home_and_category_archive(): void {
		$this->go_to( home_url( '/' ) );
		$this->assertFalse( mailocations_is_archive() );

		$this->go_to( get_category_link( self::factory()->category->create() ) );
		$this->assertFalse( mailocations_is_archive() );
	}
}

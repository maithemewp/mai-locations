<?php

declare(strict_types=1);

namespace Mai\Locations\Tests\Integration\Registration;

use Mai\Locations\Tests\TestCase;

/**
 * Mai_Locations_Plugin::save_post(), which clears the plugin's transients when a location saves.
 */
final class SavePostTest extends TestCase {

	public function test_hooked_on_save_post_with_three_args(): void {
		$this->assertSame( 10, has_action( 'save_post', [ mai_locations_plugin(), 'save_post' ] ) );

		foreach ( $GLOBALS['wp_filter']['save_post']->callbacks[10] as $callback ) {
			if ( [ mai_locations_plugin(), 'save_post' ] === $callback['function'] ) {
				$this->assertSame( 3, $callback['accepted_args'] );
			}
		}
	}

	public function test_saving_a_location_clears_transients(): void {
		set_transient( 'mai_locations_markers_abc', [ 1 ], HOUR_IN_SECONDS );

		$this->create_location();

		$this->assertFalse( get_transient( 'mai_locations_markers_abc' ) );
	}

	public function test_updating_a_location_clears_transients(): void {
		$post_id = $this->create_location();

		set_transient( 'mai_locations_markers_abc', [ 1 ], HOUR_IN_SECONDS );

		wp_update_post( [ 'ID' => $post_id, 'post_title' => 'Renamed' ] );

		$this->assertFalse( get_transient( 'mai_locations_markers_abc' ) );
	}

	public function test_saving_a_draft_location_also_clears_transients(): void {
		set_transient( 'mai_locations_markers_abc', [ 1 ], HOUR_IN_SECONDS );

		$this->create_location( [], [ 'post_status' => 'draft' ] );

		$this->assertFalse( get_transient( 'mai_locations_markers_abc' ) );
	}

	public function test_saving_other_post_types_keeps_transients(): void {
		set_transient( 'mai_locations_markers_abc', [ 1 ], HOUR_IN_SECONDS );

		self::factory()->post->create();
		self::factory()->post->create( [ 'post_type' => 'page' ] );

		$this->assertSame( [ 1 ], get_transient( 'mai_locations_markers_abc' ) );
	}
}

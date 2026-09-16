<?php

declare(strict_types=1);

namespace Mai\Locations\Tests;

use WP_UnitTestCase;

/**
 * Base class for the suite. Tests extend this so shared helpers have one home.
 */
abstract class TestCase extends WP_UnitTestCase {

	/**
	 * Drops the plugin's cached options before each test.
	 *
	 * The cache used to last the whole process, so every test saw whatever was read during
	 * bootstrap. Saving an option clears it now, which is the point, but it also means one
	 * test's saved value would otherwise outlive the database rollback that follows it.
	 */
	public function set_up(): void {
		parent::set_up();

		mailocations_get_options( true );
		mailocation_get_user_locations( 'mai_location', true );
	}

	/**
	 * Creates a location post with meta saved under the plugin's field names.
	 *
	 * The plugin reads location data with get_post_meta() on the field name, such as
	 * `address_city` or `location_phone`, so that is how this stores it. It does not go
	 * through ACF, so no `_address_city` reference rows are written.
	 *
	 * @param array<string, mixed> $meta      Meta values keyed by field name.
	 * @param array<string, mixed> $post_args Extra wp_insert_post() args.
	 *
	 * @return int The new post ID.
	 */
	protected function create_location( array $meta = [], array $post_args = [] ): int {
		$post_id = self::factory()->post->create(
			array_merge(
				[
					'post_type'   => 'mai_location',
					'post_status' => 'publish',
					'post_title'  => 'Test Location',
				],
				$post_args
			)
		);

		foreach ( $meta as $key => $value ) {
			update_post_meta( $post_id, $key, $value );
		}

		return $post_id;
	}
}

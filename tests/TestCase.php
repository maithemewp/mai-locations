<?php

declare(strict_types=1);

namespace Mai\Locations\Tests;

use WP_UnitTestCase;

/**
 * Base class for the suite. Tests extend this so shared helpers have one home.
 */
abstract class TestCase extends WP_UnitTestCase {

	/**
	 * Empties the plugin's per-request cache before each test.
	 *
	 * Those values used to sit in `static` variables that lasted the whole process, so every
	 * test saw whatever was read during bootstrap, one test's saved option outlived the
	 * database rollback that followed it, and a filter added inside a test did nothing.
	 */
	public function set_up(): void {
		parent::set_up();

		\Mai\Locations\Cache::flush();
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

<?php

declare(strict_types=1);

namespace Mai\Locations\Tests\Integration\Registration;

use Mai\Locations\Tests\TestCase;

/**
 * The `mai_locations` option: defaults, reading, writing and sanitizing.
 *
 * In this process the options were read and cached during bootstrap, when no option was saved,
 * so reads here return the sanitized defaults whatever the database holds.
 */
final class OptionsTest extends TestCase {

	private const DEFAULTS = [
		'label_plural'         => 'Locations',
		'label_singular'       => 'Location',
		'base'                 => 'locations',
		'category_base'        => 'location-category',
		'google_api_key'       => '',
		'google_api_signature' => '',
		'google_map_id'        => '',
		'distance'             => 100,
		'units'                => 'mi',
		'version_first'        => '',
		'version_db'           => '',
	];

	public function test_defaults(): void {
		$this->assertSame( self::DEFAULTS, mailocations_get_options_defaults() );
	}

	public function test_get_option_default_by_key(): void {
		$this->assertSame( 'locations', mailocations_get_option_default( 'base' ) );
		$this->assertSame( 100, mailocations_get_option_default( 'distance' ) );
	}

	public function test_pins_warning_get_option_default_with_unknown_key(): void {
		$warnings = $this->capture_warnings( fn() => $this->assertNull( mailocations_get_option_default( 'nope' ) ) );

		// Should return null or a fallback without a warning.
		$this->assertSame( [ 'Undefined array key "nope"' ], $warnings );
	}

	public function test_get_options_returns_sanitized_defaults_when_nothing_saved(): void {
		$this->assertSame( self::DEFAULTS, mailocations_get_options() );
	}

	public function test_get_option_falls_back_to_default_for_empty_values(): void {
		$this->assertSame( 'Locations', mailocations_get_option( 'label_plural' ) );
		$this->assertSame( 100, mailocations_get_option( 'distance' ) );

		// An empty string counts as unset, so the default comes back. Here the default is also empty.
		$this->assertSame( '', mailocations_get_option( 'google_api_key' ) );
	}

	public function test_get_option_without_fallback_returns_null_for_empty_values(): void {
		$this->assertNull( mailocations_get_option( 'google_api_key', false ) );
		$this->assertSame( 'mi', mailocations_get_option( 'units', false ) );
	}

	public function test_pins_warning_get_option_with_unknown_key(): void {
		$warnings = $this->capture_warnings( fn() => $this->assertNull( mailocations_get_option( 'nope' ) ) );

		// Should return null without a warning.
		$this->assertSame( [ 'Undefined array key "nope"' ], $warnings );
		$this->assertNull( mailocations_get_option( 'nope', false ) );
	}

	public function test_update_option_merges_one_key_into_the_raw_saved_option(): void {
		update_option( 'mai_locations', [ 'label_plural' => 'Places' ] );

		mailocations_update_option( 'base', 'places' );

		$this->assertSame( [ 'label_plural' => 'Places', 'base' => 'places' ], get_option( 'mai_locations' ) );
	}

	public function test_update_option_creates_the_option_with_only_that_key(): void {
		delete_option( 'mai_locations' );

		mailocations_update_option( 'version_db', '1.1.0' );

		$this->assertSame( [ 'version_db' => '1.1.0' ], get_option( 'mai_locations' ) );
	}

	public function test_update_option_does_not_sanitize(): void {
		delete_option( 'mai_locations' );

		mailocations_update_option( 'base', 'Not A Slug!' );

		$this->assertSame( [ 'base' => 'Not A Slug!' ], get_option( 'mai_locations' ) );
	}

	public function test_pins_stale_cache_get_option_ignores_updates_in_the_same_request(): void {
		mailocations_update_option( 'label_plural', 'Places' );

		// The static cache in mailocations_get_options() is never cleared, so the new value is not seen until the next request.
		$this->assertSame( 'Locations', mailocations_get_option( 'label_plural' ) );
	}

	public function test_sanitize_fills_missing_keys_with_empty_values(): void {
		$this->assertSame(
			[
				'label_plural'         => '',
				'label_singular'       => '',
				'base'                 => '',
				'category_base'        => '',
				'google_api_key'       => '',
				'google_api_signature' => '',
				'google_map_id'        => '',
				'distance'             => 0,
				'units'                => '',
				'version_first'        => '',
				'version_db'           => '',
			],
			mailocations_sanitize_options( [] )
		);
	}

	public function test_sanitize_cleans_each_field(): void {
		$result = mailocations_sanitize_options(
			[
				'label_plural'         => ' <b>Places</b> ',
				'label_singular'       => "Place\n",
				'base'                 => 'Our Places!',
				'category_base'        => 'Place Types',
				'google_api_key'       => ' abc<script>x</script> ',
				'google_api_signature' => 'sig',
				'google_map_id'        => 'map',
				'distance'             => '-25.7',
				'units'                => '<b>km</b>',
				'version_first'        => '1.0.0 & up',
				'version_db'           => '1.1.0',
			]
		);

		$this->assertSame( 'Places', $result['label_plural'] );
		$this->assertSame( 'Place', $result['label_singular'] );
		$this->assertSame( 'our-places', $result['base'] );
		$this->assertSame( 'place-types', $result['category_base'] );
		$this->assertSame( 'abc', $result['google_api_key'] );
		$this->assertSame( 'sig', $result['google_api_signature'] );
		$this->assertSame( 'map', $result['google_map_id'] );
		$this->assertSame( 25, $result['distance'] );
		$this->assertSame( '&lt;b&gt;km&lt;/b&gt;', $result['units'] );
		$this->assertSame( '1.0.0 &amp; up', $result['version_first'] );
		$this->assertSame( '1.1.0', $result['version_db'] );
	}

	public function test_sanitize_does_not_restrict_units_to_mi_or_km(): void {
		$this->assertSame( 'furlongs', mailocations_sanitize_options( [ 'units' => 'furlongs' ] )['units'] );
	}

	public function test_sanitize_keeps_quotes_in_labels(): void {
		// The settings page prints this into a value="" attribute without escaping.
		$this->assertSame( 'Say "hi"', mailocations_sanitize_options( [ 'label_plural' => 'Say "hi"' ] )['label_plural'] );
	}

	public function test_sanitize_keeps_unknown_keys_untouched(): void {
		$result = mailocations_sanitize_options( [ 'extra' => '<b>kept</b>' ] );

		$this->assertSame( '<b>kept</b>', $result['extra'] );
	}

	public function test_sanitize_accepts_a_query_string(): void {
		$this->assertSame( 'Places', mailocations_sanitize_options( 'label_plural=Places' )['label_plural'] );
	}

	/**
	 * Runs a callback and returns the messages of any warnings it raised.
	 *
	 * @return list<string>
	 */
	private function capture_warnings( callable $callback ): array {
		$messages = [];

		set_error_handler(
			static function ( int $errno, string $errstr ) use ( &$messages ): bool {
				$messages[] = $errstr;
				return true;
			}
		);

		try {
			$callback();
		} finally {
			restore_error_handler();
		}

		return $messages;
	}
}

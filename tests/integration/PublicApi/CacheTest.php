<?php

declare(strict_types=1);

namespace Mai\Locations\Tests\Integration\PublicApi;

use Mai\Locations\Cache;
use Mai\Locations\Tests\TestCase;

/**
 * The plugin's per-request cache.
 *
 * Every value the plugin works out once and reuses lives here: labels, the URL base, the
 * options, the field lists, the post type and taxonomy lists, the query defaults, the
 * WooCommerce tabs. They were `static` variables inside twenty functions until September 21,
 * 2026, which meant a filter added after the first call did nothing and no test could get a
 * second answer out of the same process.
 */
final class CacheTest extends TestCase {

	public function test_a_key_is_absent_until_it_is_set(): void {
		$this->assertFalse( Cache::has( 'nothing_here' ) );
		$this->assertNull( Cache::get( 'nothing_here' ) );
	}

	public function test_set_stores_the_value_and_hands_it_back(): void {
		$this->assertSame( 'kept', Cache::set( 'a_key', 'kept' ) );
		$this->assertTrue( Cache::has( 'a_key' ) );
		$this->assertSame( 'kept', Cache::get( 'a_key' ) );
	}

	/**
	 * has() checks the key rather than comparing against null, so a function whose answer
	 * really is null or an empty array still only works it out once.
	 */
	public function test_a_falsy_value_still_counts_as_cached(): void {
		Cache::set( 'empty_array', [] );
		Cache::set( 'a_null', null );

		$this->assertTrue( Cache::has( 'empty_array' ) );
		$this->assertTrue( Cache::has( 'a_null' ) );
	}

	public function test_forget_drops_one_key_and_leaves_the_rest(): void {
		Cache::set( 'keep_me', 1 );
		Cache::set( 'drop_me', 2 );

		Cache::forget( 'drop_me' );

		$this->assertTrue( Cache::has( 'keep_me' ) );
		$this->assertFalse( Cache::has( 'drop_me' ) );
	}

	public function test_forget_prefixed_drops_only_matching_keys(): void {
		Cache::set( 'user_locations_1|mai_location', [ 1 ] );
		Cache::set( 'user_locations_2|mai_location', [ 2 ] );
		Cache::set( 'options', [ 'base' => 'locations' ] );

		Cache::forget_prefixed( 'user_locations_' );

		$this->assertFalse( Cache::has( 'user_locations_1|mai_location' ) );
		$this->assertFalse( Cache::has( 'user_locations_2|mai_location' ) );
		$this->assertTrue( Cache::has( 'options' ) );
	}

	public function test_flush_empties_everything(): void {
		Cache::set( 'one', 1 );
		Cache::set( 'two', 2 );

		Cache::flush();

		$this->assertFalse( Cache::has( 'one' ) );
		$this->assertFalse( Cache::has( 'two' ) );
	}

	/**
	 * Both public functions that already took a $reset argument keep working. Other sites may
	 * call them, so the argument stays whatever the cache is made of underneath.
	 */
	public function test_the_two_public_reset_arguments_still_work(): void {
		mailocations_get_options();
		$this->assertTrue( Cache::has( 'options' ) );

		mailocations_get_options( true );
		$this->assertTrue( Cache::has( 'options' ) );
		$this->assertSame( 'locations', mailocations_get_option( 'base' ) );

		$user = self::factory()->user->create();
		wp_set_current_user( $user );
		mailocation_get_user_locations();
		$this->assertTrue( Cache::has( 'user_locations_' . $user . '|mai_location' ) );

		mailocation_get_user_locations( 'mai_location', true );
		$this->assertTrue( Cache::has( 'user_locations_' . $user . '|mai_location' ) );
	}

	/**
	 * Resetting the options drops the three values worked out from them, or a saved label would
	 * keep showing the old one for the rest of the request.
	 */
	public function test_resetting_the_options_drops_the_labels_and_base(): void {
		mailocations_get_plural();
		mailocations_get_singular();
		mailocations_get_base();

		$this->assertTrue( Cache::has( 'plural' ) );

		mailocations_get_options( true );

		$this->assertFalse( Cache::has( 'plural' ) );
		$this->assertFalse( Cache::has( 'singular' ) );
		$this->assertFalse( Cache::has( 'base' ) );
	}

	public function test_a_saved_label_is_seen_for_the_rest_of_the_request(): void {
		$this->assertSame( 'Locations', mailocations_get_plural() );

		mailocations_update_option( 'label_plural', 'Places' );

		$this->assertSame( 'Places', mailocations_get_plural() );
	}
}

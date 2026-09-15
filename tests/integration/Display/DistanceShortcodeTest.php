<?php

declare(strict_types=1);

namespace Mai\Locations\Tests\Integration\Display;

use Mai\Locations\Tests\TestCase;

/**
 * Pins the [mai_location_distance] shortcode.
 */
final class DistanceShortcodeTest extends TestCase {

	/**
	 * @var array<string, mixed>
	 */
	private array $get_backup = [];

	public function set_up(): void {
		parent::set_up();
		$this->get_backup = $_GET;
		$_GET             = [];
	}

	public function tear_down(): void {
		$_GET = $this->get_backup;
		unset( $GLOBALS['post'] );
		parent::tear_down();
	}

	private function use_post_with_distance( ?string $distance ): void {
		$post = get_post( $this->create_location() );

		if ( null !== $distance ) {
			$post->geo_query_distance = $distance;
		}

		$GLOBALS['post'] = $post;
	}

	/**
	 * Calls the shortcode callback directly. It is a closure, so it has no function name.
	 *
	 * @param array<string, mixed>|string $atts
	 *
	 * @return mixed
	 */
	private function call_callback( $atts ) {
		global $shortcode_tags;

		return call_user_func( $shortcode_tags['mai_location_distance'], $atts );
	}

	public function test_pins_bug_default_after_loses_its_leading_space(): void {
		$this->use_post_with_distance( '3.14159' );

		// Correct would be "3.1 mi away". sanitize_text_field() trims the default " mi away".
		$this->assertSame( '3.1mi away', do_shortcode( '[mai_location_distance]' ) );
		$this->assertSame( '3.1mi away', $this->call_callback( [] ) );
	}

	public function test_units_come_from_query_string(): void {
		$this->use_post_with_distance( '3.14159' );
		$_GET = [ 'units' => 'km' ];

		$this->assertSame( '3.1km away', do_shortcode( '[mai_location_distance]' ) );
	}

	public function test_before_after_are_trimmed_and_round_applies(): void {
		$this->use_post_with_distance( '3.14159' );

		$this->assertSame( 'About3.14miles', do_shortcode( '[mai_location_distance before="About " after=" miles" round="2"]' ) );
	}

	public function test_before_after_tags_are_stripped(): void {
		$this->use_post_with_distance( '3.14159' );

		$this->assertSame( 'x3.1y', $this->call_callback( [ 'before' => '<b>x</b>', 'after' => '<i>y</i>' ] ) );
	}

	public function test_pins_bug_empty_after_returns_float_not_string(): void {
		$this->use_post_with_distance( '3.14159' );

		// Correct would be the string "3.1", as the docblock says. Nothing is appended, so the float leaks out.
		$this->assertSame( 3.1, $this->call_callback( [ 'after' => '' ] ) );
		$this->assertSame( '3.1', do_shortcode( '[mai_location_distance after=""]' ) );
	}

	public function test_round_zero(): void {
		$this->use_post_with_distance( '3.6' );

		$this->assertSame( '4mi away', do_shortcode( '[mai_location_distance round="0"]' ) );
	}

	public function test_pins_bug_distance_that_rounds_to_zero_prints_nothing(): void {
		$this->use_post_with_distance( '0.04' );

		// Correct would be "0 mi away". A rounded 0.0 is falsy, so the shortcode bails.
		$this->assertSame( '', do_shortcode( '[mai_location_distance]' ) );
		$this->assertNull( $this->call_callback( [] ) );
	}

	public function test_no_distance_prints_nothing(): void {
		$this->use_post_with_distance( null );

		$this->assertSame( '', do_shortcode( '[mai_location_distance]' ) );
	}
}

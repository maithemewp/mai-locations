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

	/**
	 * Fixed September 16, 2026. sanitize_text_field() trimmed the default " mi away".
	 */
	public function test_default_after_keeps_its_leading_space(): void {
		$this->use_post_with_distance( '3.14159' );

		$this->assertSame( '3.1 mi away', do_shortcode( '[mai_location_distance]' ) );
		$this->assertSame( '3.1 mi away', $this->call_callback( [] ) );
	}

	public function test_units_come_from_query_string(): void {
		$this->use_post_with_distance( '3.14159' );
		$_GET = [ 'units' => 'km' ];

		$this->assertSame( '3.1 km away', do_shortcode( '[mai_location_distance]' ) );
	}

	public function test_before_after_keep_their_spaces_and_round_applies(): void {
		$this->use_post_with_distance( '3.14159' );

		$this->assertSame( 'About 3.14 miles', do_shortcode( '[mai_location_distance before="About " after=" miles" round="2"]' ) );
	}

	/**
	 * Changed September 16, 2026. Tags used to be stripped here and escaped in every other
	 * location shortcode. They are escaped here now too.
	 */
	public function test_before_after_tags_are_escaped(): void {
		$this->use_post_with_distance( '3.14159' );

		$this->assertSame( '&lt;b&gt;x&lt;/b&gt;3.1&lt;i&gt;y&lt;/i&gt;', $this->call_callback( [ 'before' => '<b>x</b>', 'after' => '<i>y</i>' ] ) );
	}

	/**
	 * Fixed September 16, 2026. With nothing appended, the float leaked out of the callback.
	 */
	public function test_empty_after_returns_a_string(): void {
		$this->use_post_with_distance( '3.14159' );

		$this->assertSame( '3.1', $this->call_callback( [ 'after' => '' ] ) );
		$this->assertSame( '3.1', do_shortcode( '[mai_location_distance after=""]' ) );
	}

	public function test_round_zero(): void {
		$this->use_post_with_distance( '3.6' );

		$this->assertSame( '4 mi away', do_shortcode( '[mai_location_distance round="0"]' ) );
	}

	/**
	 * Fixed September 16, 2026. A rounded 0.0 is falsy, so the shortcode printed nothing.
	 */
	public function test_distance_that_rounds_to_zero_still_prints(): void {
		$this->use_post_with_distance( '0.04' );

		$this->assertSame( '0 mi away', do_shortcode( '[mai_location_distance]' ) );
		$this->assertSame( '0 mi away', $this->call_callback( [] ) );
	}

	public function test_no_distance_prints_nothing(): void {
		$this->use_post_with_distance( null );

		$this->assertSame( '', do_shortcode( '[mai_location_distance]' ) );
	}
}

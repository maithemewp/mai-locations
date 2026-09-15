<?php

declare(strict_types=1);

namespace Mai\Locations\Tests\Integration\Display;

use Mai\Locations\Tests\TestCase;

/**
 * Pins [mai_location_url], [mai_location_email] and [mai_location_place].
 */
final class ContactShortcodesTest extends TestCase {

	public function tear_down(): void {
		unset( $GLOBALS['post'] );
		parent::tear_down();
	}

	/**
	 * @param array<string, string> $meta
	 */
	private function use_location( array $meta ): void {
		$GLOBALS['post'] = get_post( $this->create_location( $meta ) );
	}

	public function test_shortcodes_are_registered(): void {
		foreach ( [ 'mai_location_address', 'mai_location_phone', 'mai_location_url', 'mai_location_email', 'mai_location_place', 'mai_location_distance', 'mai_locations_table' ] as $tag ) {
			$this->assertTrue( shortcode_exists( $tag ), $tag );
		}
	}

	public function test_url_defaults(): void {
		$this->use_location( [ 'location_url' => 'https://www.hudsonvalley.org/historic-sites/' ] );

		$this->assertSame(
			'<div class="mai-location-url"><a href="https://www.hudsonvalley.org/historic-sites/" target="_blank" rel="noopener nofollow">hudsonvalley.org</a></div>',
			do_shortcode( '[mai_location_url]' )
		);
	}

	public function test_url_with_atts(): void {
		$this->use_location( [ 'location_url' => 'https://sleepyhollowny.gov' ] );

		$this->assertSame(
			'<div class="mai-location-url" style="color:red;">Site: <a href="https://sleepyhollowny.gov">sleepyhollowny.gov</a> &gt;</div>',
			mailocation_location_url_shortcode( [ 'style' => 'color:red;', 'target' => '', 'rel' => '', 'before' => 'Site: ', 'after' => ' >' ] )
		);
	}

	public function test_url_attributes_are_escaped(): void {
		$this->use_location( [ 'location_url' => 'https://sleepyhollowny.gov' ] );

		$this->assertSame(
			'<div class="mai-location-url" style="a&quot;b"><a href="https://sleepyhollowny.gov" target="_self&quot;" rel="me">sleepyhollowny.gov</a></div>',
			mailocation_location_url_shortcode( [ 'style' => 'a"b', 'target' => '_self"', 'rel' => 'me' ] )
		);
	}

	public function test_url_label_drops_only_the_www_prefix(): void {
		$this->use_location( [ 'location_url' => 'https://www.washingtonirving.org' ] );

		// Fixed September 15, 2026. ltrim() took a character list, so it ate the w of washington too.
		$this->assertSame(
			'<div class="mai-location-url"><a href="https://www.washingtonirving.org" target="_blank" rel="noopener nofollow">washingtonirving.org</a></div>',
			do_shortcode( '[mai_location_url]' )
		);
	}

	public function test_url_label_keeps_a_host_that_starts_with_w(): void {
		$this->use_location( [ 'location_url' => 'https://westchester.com' ] );

		$this->assertSame(
			'<div class="mai-location-url"><a href="https://westchester.com" target="_blank" rel="noopener nofollow">westchester.com</a></div>',
			do_shortcode( '[mai_location_url]' )
		);
	}

	public function test_url_label_drops_the_www_prefix_whatever_its_case(): void {
		$this->use_location( [ 'location_url' => 'https://WWW.Westchester.com' ] );

		$this->assertSame(
			'<div class="mai-location-url"><a href="https://WWW.Westchester.com" target="_blank" rel="noopener nofollow">Westchester.com</a></div>',
			do_shortcode( '[mai_location_url]' )
		);
	}

	public function test_url_without_host_prints_raw_value(): void {
		$this->use_location( [ 'location_url' => 'sleepyhollowny.gov' ] );

		$this->assertSame(
			'<div class="mai-location-url"><a href="http://sleepyhollowny.gov" target="_blank" rel="noopener nofollow">sleepyhollowny.gov</a></div>',
			do_shortcode( '[mai_location_url]' )
		);
	}

	public function test_url_empty_returns_null(): void {
		$this->use_location( [] );

		$this->assertNull( mailocation_location_url_shortcode( [] ) );
	}

	public function test_email_output_decodes_to_address(): void {
		$this->use_location( [ 'location_email' => 'info@example.com' ] );

		// antispambot() encodes at random, so seed it for a stable string.
		mt_srand( 1 );
		$html = mailocation_location_email_shortcode( [] );
		mt_srand();

		$this->assertMatchesRegularExpression( '#^<div class="mai-location-email"><a href="mailto:[^"]+">[^<]+</a></div>$#', $html );
		$this->assertSame( 'info@example.com', html_entity_decode( wp_strip_all_tags( $html ) ) );

		preg_match( '#href="mailto:([^"]+)"#', $html, $m );
		$this->assertSame( 'info@example.com', html_entity_decode( html_entity_decode( $m[1] ) ) );
	}

	public function test_email_link_false_style_before_after(): void {
		$this->use_location( [ 'location_email' => 'info@example.com' ] );

		mt_srand( 1 );
		$html = mailocation_location_email_shortcode( [ 'link' => false, 'style' => 'a"b', 'before' => 'Email: ', 'after' => ' <' ] );
		mt_srand();

		$this->assertMatchesRegularExpression( '#^<div class="mai-location-email" style="a&quot;b">Email: [^<]+ &lt;</div>$#', $html );
		$this->assertSame( 'Email: info@example.com <', html_entity_decode( $html === null ? '' : wp_strip_all_tags( $html ) ) );
	}

	public function test_pins_bug_email_link_string_false_still_links(): void {
		$this->use_location( [ 'location_email' => 'info@example.com' ] );

		// Correct would match [mai_location_phone], which runs rest_sanitize_boolean() on link.
		$this->assertStringContainsString( '<a href="mailto:', do_shortcode( '[mai_location_email link="false"]' ) );
	}

	public function test_email_invalid_or_empty_returns_null(): void {
		$this->use_location( [ 'location_email' => 'not an email' ] );
		$this->assertNull( mailocation_location_email_shortcode( [] ) );

		$this->use_location( [] );
		$this->assertNull( mailocation_location_email_shortcode( [] ) );
	}

	public function test_place_defaults(): void {
		$this->use_location( [ 'place_id' => 'ChIJN1t_tDeuEmsRUsoyG83frY4' ] );

		$this->assertSame(
			'<div class="mai-location-place"><a target="_blank" href="https://www.google.com/maps/place/?q=place_id:ChIJN1t_tDeuEmsRUsoyG83frY4">View on Google</a></div>',
			do_shortcode( '[mai_location_place]' )
		);
	}

	public function test_place_before_after_text(): void {
		$this->use_location( [ 'place_id' => 'abc' ] );

		$this->assertSame(
			'<div class="mai-location-place">&lt;i&gt; <a target="_blank" href="https://www.google.com/maps/place/?q=place_id:abc">Map it</a> !</div>',
			mailocation_location_place_shortcode( [ 'before' => '<i> ', 'after' => ' !', 'text' => '<b>Map it</b>' ] )
		);
	}

	public function test_pins_bug_place_ignores_style(): void {
		$this->use_location( [ 'place_id' => 'abc' ] );

		// Correct would output style="color:red;" on the wrapper, as the other shortcodes do.
		$this->assertSame(
			'<div class="mai-location-place"><a target="_blank" href="https://www.google.com/maps/place/?q=place_id:abc">View on Google</a></div>',
			mailocation_location_place_shortcode( [ 'style' => 'color:red;' ] )
		);
	}

	public function test_pins_bug_place_id_is_not_escaped(): void {
		$this->use_location( [ 'place_id' => 'a"><script>' ] );

		// Correct would be esc_attr() on the place ID.
		$this->assertStringContainsString( 'place_id:a"><script>">', mailocation_location_place_shortcode( [] ) );
	}

	public function test_place_empty_returns_null(): void {
		$this->use_location( [] );

		$this->assertNull( mailocation_location_place_shortcode( [] ) );
	}
}

<?php

declare(strict_types=1);

namespace Mai\Locations\Tests\Integration\Display;

use Mai\Locations\Tests\TestCase;

/**
 * Pins the [mai_location_phone] shortcode.
 */
final class PhoneShortcodeTest extends TestCase {

	public function tear_down(): void {
		unset( $GLOBALS['post'] );
		parent::tear_down();
	}

	/**
	 * @param array<string, string> $meta
	 */
	private function use_location( array $meta ): int {
		$id              = $this->create_location( $meta );
		$GLOBALS['post'] = get_post( $id );

		return $id;
	}

	/**
	 * Runs the shortcode callback and collects any warnings it raises.
	 *
	 * @param array<string, mixed>|string $atts
	 *
	 * @return array{0: mixed, 1: list<string>}
	 */
	private function run_capturing_warnings( $atts ): array {
		$warnings = [];

		set_error_handler(
			static function ( int $errno, string $message ) use ( &$warnings ): bool {
				$warnings[] = $message;
				return true;
			}
		);

		try {
			$out = mailocation_location_phone_shortcode( $atts );
		} finally {
			restore_error_handler();
		}

		return [ $out, $warnings ];
	}

	public function test_us_number_is_linked_and_formatted_national(): void {
		$this->use_location( [ 'location_phone' => '914-631-8200', 'address_country' => 'US' ] );

		$this->assertSame( '<div class="mai-location-phone"><a href="tel://+1 914-631-8200">(914) 631-8200</a></div>', do_shortcode( '[mai_location_phone]' ) );
	}

	public function test_link_false_prints_number_only(): void {
		$this->use_location( [ 'location_phone' => '914-631-8200', 'address_country' => 'US' ] );

		$this->assertSame( '<div class="mai-location-phone">(914) 631-8200</div>', do_shortcode( '[mai_location_phone link="false"]' ) );
	}

	public function test_before_after_are_escaped_and_not_trimmed(): void {
		$this->use_location( [ 'location_phone' => '914-631-8200', 'address_country' => 'US' ] );

		$this->assertSame(
			'<div class="mai-location-phone">Call &amp; &lt;b&gt; <a href="tel://+1 914-631-8200">(914) 631-8200</a> now</div>',
			mailocation_location_phone_shortcode( [ 'before' => 'Call & <b> ', 'after' => ' now' ] )
		);
	}

	public function test_style_attribute(): void {
		$this->use_location( [ 'location_phone' => '914-631-8200', 'address_country' => 'US' ] );

		$this->assertSame(
			'<div class="mai-location-phone" style="color:red;"><a href="tel://+1 914-631-8200">(914) 631-8200</a></div>',
			do_shortcode( '[mai_location_phone style="color:red;"]' )
		);
	}

	public function test_pins_bug_style_is_not_escaped(): void {
		$this->use_location( [ 'location_phone' => '914-631-8200', 'address_country' => 'US' ] );

		// Correct would be esc_attr() on style, as [mai_location_url] and [mai_location_email] do.
		$this->assertSame(
			'<div class="mai-location-phone" style="x" onclick="alert(1)">(914) 631-8200</div>',
			mailocation_location_phone_shortcode( [ 'style' => 'x" onclick="alert(1)', 'link' => 'false' ] )
		);
	}

	public function test_second_phone(): void {
		$this->use_location(
			[
				'location_phone'   => '914-631-8200',
				'location_phone_2' => '914-332-0000',
				'address_country'  => 'US',
			]
		);

		$this->assertSame( '<div class="mai-location-phone"><a href="tel://+1 914-332-0000">(914) 332-0000</a></div>', do_shortcode( '[mai_location_phone phone="2"]' ) );
		// Any value other than 2 reads the first phone.
		$this->assertSame( '<div class="mai-location-phone"><a href="tel://+1 914-631-8200">(914) 631-8200</a></div>', do_shortcode( '[mai_location_phone phone="3"]' ) );
	}

	public function test_non_us_number_is_formatted_e164(): void {
		$this->use_location( [ 'location_phone' => '020 7219 3000', 'address_country' => 'GB' ] );

		$this->assertSame( '<div class="mai-location-phone"><a href="tel://+44 20 7219 3000">+442072193000</a></div>', do_shortcode( '[mai_location_phone]' ) );
	}

	public function test_no_phone_returns_null(): void {
		$this->use_location( [ 'address_country' => 'US' ] );

		$this->assertNull( mailocation_location_phone_shortcode( [] ) );
		$this->assertSame( '', do_shortcode( '[mai_location_phone]' ) );
	}

	public function test_pins_bug_no_country_tel_link_stops_at_first_dash(): void {
		$this->use_location( [ 'location_phone' => '914-631-8200' ] );

		// Correct would be tel://9146318200. The (int) cast of "914-631-8200" keeps only 914.
		$this->assertSame( '<div class="mai-location-phone"><a href="tel://914">914-631-8200</a></div>', do_shortcode( '[mai_location_phone]' ) );
	}

	public function test_no_country_digits_only_number(): void {
		$this->use_location( [ 'location_phone' => '(914) 6318200' ] );

		$this->assertSame( '<div class="mai-location-phone"><a href="tel://9146318200">(914) 6318200</a></div>', do_shortcode( '[mai_location_phone]' ) );
	}

	public function test_pins_bug_invalid_number_for_country_warns_and_prints_empty_link(): void {
		$this->use_location( [ 'location_phone' => '123', 'address_country' => 'US' ] );

		[ $out, $warnings ] = $this->run_capturing_warnings( [] );

		// Correct would be a fallback to the raw number with no warnings.
		$this->assertSame( '<div class="mai-location-phone"><a href="tel://"></a></div>', $out );
		$this->assertSame( [ 'Undefined variable $tel', 'Undefined variable $formatted' ], $warnings );
	}

	public function test_pins_bug_invalid_number_with_link_false_warns_once(): void {
		$this->use_location( [ 'location_phone' => '123', 'address_country' => 'US' ] );

		[ $out, $warnings ] = $this->run_capturing_warnings( [ 'link' => 'false' ] );

		$this->assertSame( '<div class="mai-location-phone"></div>', $out );
		$this->assertSame( [ 'Undefined variable $formatted' ], $warnings );
	}

	public function test_pins_bug_unparseable_number_with_country_throws(): void {
		$this->use_location( [ 'location_phone' => 'Call us', 'address_country' => 'US' ] );

		// Correct would be printing the raw text. libphonenumber throws and nothing catches it.
		$this->expectException( \libphonenumber\NumberParseException::class );
		mailocation_location_phone_shortcode( [] );
	}

	public function test_phone_meta_is_escaped(): void {
		$this->use_location( [ 'location_phone' => '<b>914</b>' ] );

		$this->assertSame( '<div class="mai-location-phone"><a href="tel://914">&lt;b&gt;914&lt;/b&gt;</a></div>', do_shortcode( '[mai_location_phone]' ) );
	}
}

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

	/**
	 * Fixed September 16, 2026. style went into the attribute unescaped, so a quote in it could
	 * add attributes of its own.
	 */
	public function test_style_is_escaped(): void {
		$this->use_location( [ 'location_phone' => '914-631-8200', 'address_country' => 'US' ] );

		$this->assertSame(
			'<div class="mai-location-phone" style="x&quot; onclick=&quot;alert(1)">(914) 631-8200</div>',
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

	public function test_no_country_tel_link_keeps_every_digit(): void {
		$this->use_location( [ 'location_phone' => '914-631-8200' ] );

		// Fixed September 16, 2026. The (int) cast used to stop at the first dash, linking tel://914.
		$this->assertSame( '<div class="mai-location-phone"><a href="tel://9146318200">914-631-8200</a></div>', do_shortcode( '[mai_location_phone]' ) );
	}

	public function test_no_country_digits_only_number(): void {
		$this->use_location( [ 'location_phone' => '(914) 6318200' ] );

		$this->assertSame( '<div class="mai-location-phone"><a href="tel://9146318200">(914) 6318200</a></div>', do_shortcode( '[mai_location_phone]' ) );
	}

	/**
	 * Fixed September 16, 2026. An invalid number used to leave $tel and $formatted undefined,
	 * so the shortcode printed an empty link and raised two warnings.
	 */
	public function test_invalid_number_for_country_falls_back_to_the_raw_number(): void {
		$this->use_location( [ 'location_phone' => '123', 'address_country' => 'US' ] );

		[ $out, $warnings ] = $this->run_capturing_warnings( [] );

		$this->assertSame( '<div class="mai-location-phone"><a href="tel://123">123</a></div>', $out );
		$this->assertSame( [], $warnings );
	}

	public function test_invalid_number_with_link_false_prints_the_raw_number(): void {
		$this->use_location( [ 'location_phone' => '123', 'address_country' => 'US' ] );

		[ $out, $warnings ] = $this->run_capturing_warnings( [ 'link' => 'false' ] );

		$this->assertSame( '<div class="mai-location-phone">123</div>', $out );
		$this->assertSame( [], $warnings );
	}

	/**
	 * Fixed September 16, 2026. libphonenumber threw NumberParseException out of the shortcode,
	 * which took the whole page down.
	 */
	public function test_unparseable_number_with_country_prints_the_raw_text(): void {
		$this->use_location( [ 'location_phone' => 'Call us', 'address_country' => 'US' ] );

		[ $out, $warnings ] = $this->run_capturing_warnings( [] );

		// No digits to dial, so no link at all.
		$this->assertSame( '<div class="mai-location-phone">Call us</div>', $out );
		$this->assertSame( [], $warnings );
	}

	public function test_phone_meta_is_escaped(): void {
		$this->use_location( [ 'location_phone' => '<b>914</b>' ] );

		$this->assertSame( '<div class="mai-location-phone"><a href="tel://914">&lt;b&gt;914&lt;/b&gt;</a></div>', do_shortcode( '[mai_location_phone]' ) );
	}
}

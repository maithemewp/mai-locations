<?php

declare(strict_types=1);

namespace Mai\Locations\Tests\Integration\Display;

use Mai\Locations\Tests\TestCase;

/**
 * Pins mailocations_get_address() and the [mai_location_address] shortcode.
 */
final class AddressTest extends TestCase {

	private const OPEN = '<div itemprop="address" itemscope itemtype="http://schema.org/PostalAddress" class="mai-address">';

	public function tear_down(): void {
		unset( $GLOBALS['post'] );
		parent::tear_down();
	}

	/**
	 * A realistic Visit Sleepy Hollow record.
	 *
	 * @param array<string, string> $overrides Meta to replace or add.
	 */
	private function create_us_location( array $overrides = [] ): int {
		return $this->create_location(
			array_merge(
				[
					'address_street'   => '381 N Broadway',
					'address_city'     => 'Tarrytown',
					'address_state'    => 'NY',
					'address_postcode' => '10591',
					'address_country'  => 'US',
					'location_phone'   => '914-631-8200',
				],
				$overrides
			)
		);
	}

	public function test_visit_sleepy_hollow_theme_call_hides_country(): void {
		$id = $this->create_us_location();

		$this->assertSame(
			self::OPEN
			. '<div class="mai-address-item"><span class="street-address" itemprop="streetAddress">381 N Broadway</span></div>'
			. '<div class="mai-address-item"><span class="locality" itemprop="addressLocality">Tarrytown</span><span class="region" itemprop="addressRegion">&nbsp;NY</span><span class="postal-code" itemprop="postalCode">,&nbsp;10591</span></div>'
			. '</div>',
			mailocations_get_address( [ 'hide' => 'country' ], $id )
		);
	}

	public function test_full_us_address_shows_country_name(): void {
		$id = $this->create_us_location( [ 'address_street_2' => 'Suite 2' ] );

		$this->assertSame(
			self::OPEN
			. '<div class="mai-address-item"><span class="street-address" itemprop="streetAddress">381 N Broadway</span></div>'
			. '<div class="mai-address-item"><span class="street-address-2">Suite 2</span></div>'
			. '<div class="mai-address-item"><span class="locality" itemprop="addressLocality">Tarrytown</span><span class="region" itemprop="addressRegion">&nbsp;NY</span><span class="postal-code" itemprop="postalCode">,&nbsp;10591</span></div>'
			. '<div class="mai-address-item" itemprop="addressCountry">United States</div>'
			. '</div>',
			mailocations_get_address( [], $id )
		);
	}

	public function test_hide_each_part(): void {
		$id = $this->create_us_location( [ 'address_street_2' => 'Suite 2' ] );

		$street   = '<div class="mai-address-item"><span class="street-address" itemprop="streetAddress">381 N Broadway</span></div>';
		$street2  = '<div class="mai-address-item"><span class="street-address-2">Suite 2</span></div>';
		$city     = '<span class="locality" itemprop="addressLocality">Tarrytown</span>';
		$state    = '<span class="region" itemprop="addressRegion">&nbsp;NY</span>';
		$postcode = '<span class="postal-code" itemprop="postalCode">,&nbsp;10591</span>';
		$country  = '<div class="mai-address-item" itemprop="addressCountry">United States</div>';

		$cases = [
			'street'   => $street2 . '<div class="mai-address-item">' . $city . $state . $postcode . '</div>' . $country,
			'street2'  => $street . '<div class="mai-address-item">' . $city . $state . $postcode . '</div>' . $country,
			'city'     => $street . $street2 . '<div class="mai-address-item">' . $state . $postcode . '</div>' . $country,
			'state'    => $street . $street2 . '<div class="mai-address-item">' . $city . $postcode . '</div>' . $country,
			'postcode' => $street . $street2 . '<div class="mai-address-item">' . $city . $state . '</div>' . $country,
			'country'  => $street . $street2 . '<div class="mai-address-item">' . $city . $state . $postcode . '</div>',
			// The locations table uses this exact string, spaces included.
			'street2, postcode, country' => $street . '<div class="mai-address-item">' . $city . $state . '</div>',
			'city,state,postcode,country' => $street . $street2,
		];

		foreach ( $cases as $hide => $inner ) {
			$this->assertSame( self::OPEN . $inner . '</div>', mailocations_get_address( [ 'hide' => $hide ], $id ), "hide={$hide}" );
		}
	}

	public function test_unknown_hide_value_changes_nothing(): void {
		$id = $this->create_us_location();

		$this->assertSame( mailocations_get_address( [], $id ), mailocations_get_address( [ 'hide' => 'phone, zip' ], $id ) );
	}

	public function test_hiding_everything_returns_empty_string(): void {
		$id = $this->create_us_location();

		$this->assertSame( '', mailocations_get_address( [ 'hide' => 'street,street2,city,state,postcode,country' ], $id ) );
	}

	public function test_empty_address_returns_empty_string(): void {
		$id = $this->create_location();

		$this->assertSame( '', mailocations_get_address( [], $id ) );
	}

	public function test_missing_parts_are_skipped(): void {
		$id = $this->create_location(
			[
				'address_city'    => 'Tarrytown',
				'address_country' => 'US',
			]
		);

		$this->assertSame(
			self::OPEN
			. '<div class="mai-address-item"><span class="locality" itemprop="addressLocality">Tarrytown</span></div>'
			. '<div class="mai-address-item" itemprop="addressCountry">United States</div>'
			. '</div>',
			mailocations_get_address( [], $id )
		);
	}

	/**
	 * Fixed September 16, 2026. An empty <div class="mai-address-item"></div> printed before it.
	 */
	public function test_country_only_prints_no_empty_address_item(): void {
		$id = $this->create_location( [ 'address_country' => 'US' ] );

		$this->assertSame(
			self::OPEN
			. '<div class="mai-address-item" itemprop="addressCountry">United States</div>'
			. '</div>',
			mailocations_get_address( [], $id )
		);
	}

	public function test_non_us_uses_international_state(): void {
		$id = $this->create_location(
			[
				'address_street'    => '290 Bremner Blvd',
				'address_city'      => 'Toronto',
				'address_state'     => 'NY',
				'address_state_int' => 'Ontario',
				'address_postcode'  => 'M5V 3L9',
				'address_country'   => 'CA',
			]
		);

		$this->assertSame(
			self::OPEN
			. '<div class="mai-address-item"><span class="street-address" itemprop="streetAddress">290 Bremner Blvd</span></div>'
			. '<div class="mai-address-item"><span class="locality" itemprop="addressLocality">Toronto</span><span class="region" itemprop="addressRegion">&nbsp;Ontario</span><span class="postal-code" itemprop="postalCode">,&nbsp;M5V 3L9</span></div>'
			. '<div class="mai-address-item" itemprop="addressCountry">Canada</div>'
			. '</div>',
			mailocations_get_address( [], $id )
		);
	}

	public function test_us_ignores_international_state(): void {
		$id = $this->create_location(
			[
				'address_state'     => 'NY',
				'address_state_int' => 'Ontario',
				'address_country'   => 'US',
			]
		);

		$this->assertStringContainsString( '&nbsp;NY</span>', mailocations_get_address( [ 'hide' => 'country' ], $id ) );
	}

	public function test_no_country_uses_us_state(): void {
		$id = $this->create_location(
			[
				'address_state'     => 'NY',
				'address_state_int' => 'Ontario',
			]
		);

		$this->assertSame(
			self::OPEN . '<div class="mai-address-item"><span class="region" itemprop="addressRegion">&nbsp;NY</span></div></div>',
			mailocations_get_address( [], $id )
		);
	}

	/**
	 * Fixed September 16, 2026. Hiding the country sent a non-US record to the US state field.
	 */
	public function test_hiding_country_keeps_the_international_state(): void {
		$id = $this->create_location(
			[
				'address_city'      => 'Toronto',
				'address_state'     => 'NY',
				'address_state_int' => 'Ontario',
				'address_country'   => 'CA',
			]
		);

		$this->assertSame(
			self::OPEN . '<div class="mai-address-item"><span class="locality" itemprop="addressLocality">Toronto</span><span class="region" itemprop="addressRegion">&nbsp;Ontario</span></div></div>',
			mailocations_get_address( [ 'hide' => 'country' ], $id )
		);
	}

	public function test_unknown_country_code_prints_code(): void {
		$id = $this->create_location( [ 'address_city' => 'Nowhere', 'address_country' => 'ZZ' ] );

		$this->assertStringContainsString( '<div class="mai-address-item" itemprop="addressCountry">ZZ</div>', mailocations_get_address( [], $id ) );
	}

	public function test_values_are_escaped(): void {
		$id = $this->create_location( [ 'address_street' => '<b>Main</b> & "Co"' ] );

		$this->assertStringContainsString( '&lt;b&gt;Main&lt;/b&gt; &amp; &quot;Co&quot;', mailocations_get_address( [], $id ) );
	}

	public function test_default_post_id_is_current_post(): void {
		$id              = $this->create_us_location();
		$GLOBALS['post'] = get_post( $id );

		$this->assertSame( mailocations_get_address( [ 'hide' => 'country' ], $id ), mailocations_get_address( [ 'hide' => 'country' ] ) );
	}

	public function test_visit_sleepy_hollow_archive_shortcodes(): void {
		$id              = $this->create_us_location();
		$GLOBALS['post'] = get_post( $id );

		$this->assertSame(
			self::OPEN
			. '<div class="mai-address-item"><span class="street-address" itemprop="streetAddress">381 N Broadway</span></div>'
			. '<div class="mai-address-item"><span class="locality" itemprop="addressLocality">Tarrytown</span><span class="region" itemprop="addressRegion">&nbsp;NY</span><span class="postal-code" itemprop="postalCode">,&nbsp;10591</span></div>'
			. '</div>'
			. '<div class="mai-location-phone"><a href="tel://+1 914-631-8200">(914) 631-8200</a></div>',
			do_shortcode( '[mai_location_address hide="country"][mai_location_phone]' )
		);
	}

	public function test_address_shortcode_matches_function(): void {
		$id              = $this->create_us_location();
		$GLOBALS['post'] = get_post( $id );

		$this->assertTrue( shortcode_exists( 'mai_location_address' ) );
		$this->assertSame( mailocations_get_address( [], $id ), do_shortcode( '[mai_location_address]' ) );
		$this->assertSame( '', do_shortcode( '[mai_location_address hide="street,city,state,postcode,country"]' ) );
	}

	public function test_address_shortcode_attribute_filter_runs(): void {
		$id = $this->create_us_location();

		$filter = static function ( array $out ): array {
			$out['hide'] = 'country';
			return $out;
		};

		add_filter( 'shortcode_atts_mai_location_address', $filter );
		$html = mailocations_get_address( [], $id );
		remove_filter( 'shortcode_atts_mai_location_address', $filter );

		$this->assertStringNotContainsString( 'addressCountry', $html );
	}
}

<?php

declare(strict_types=1);

namespace Mai\Locations\Tests\Integration\Fields;

use Mai\Locations\Tests\TestCase;

/**
 * Pins includes/functions-fields.php as it behaves today.
 *
 * Every function here caches in a static for the whole process and ran at boot when ACF
 * registered the field groups, so filters added inside a test have no effect. The default
 * field set is pinned exactly instead, and did_filter() proves each filter ran once.
 */
final class FieldFunctionsTest extends TestCase {

	private const CONDITION_COUNTRY_NOT_EMPTY = [
		[
			'field'    => 'mai_location_address_country',
			'operator' => '!=empty',
		],
	];

	public function test_general_fields_are_pinned_exactly(): void {
		$expected = [
			'location_general_tab' => [
				'key'       => 'mai_location_general_tab',
				'label'     => 'General Info',
				'type'      => 'tab',
				'placement' => 'left',
			],
			'location_url' => [
				'key'   => 'mai_location_url',
				'label' => 'Website URL',
				'type'  => 'url',
			],
			'location_phone' => [
				'key'   => 'mai_location_phone',
				'label' => 'Phone',
				'type'  => 'text',
			],
			'location_phone_2' => [
				'key'   => 'mai_location_phone_2',
				'label' => 'Secondary Phone',
				'type'  => 'text',
			],
			'location_email' => [
				'key'   => 'mai_location_email',
				'label' => 'Email',
				'type'  => 'email',
			],
		];

		$this->assertSame( $expected, mailocations_get_general_fields() );
	}

	public function test_address_fields_are_pinned_exactly(): void {
		$expected = [
			'location_address_tab' => [
				'key'       => 'mai_location_address_tab',
				'label'     => 'Address & Map',
				'type'      => 'tab',
				'placement' => 'left',
			],
			'address_country' => [
				'key'           => 'mai_location_address_country',
				'label'         => 'Country',
				'type'          => 'select',
				'default_value' => 'US',
				'choices'       => mailocations_get_country_choices(),
			],
			'address_street' => [
				'key'               => 'mai_location_address_street',
				'label'             => 'Street',
				'type'              => 'text',
				'conditional_logic' => self::CONDITION_COUNTRY_NOT_EMPTY,
			],
			'address_street_2' => [
				'key'               => 'mai_location_address_street_2',
				'label'             => 'Street (2nd line)',
				'type'              => 'text',
				'conditional_logic' => self::CONDITION_COUNTRY_NOT_EMPTY,
			],
			'address_city' => [
				'key'               => 'mai_location_address_city',
				'label'             => 'City',
				'type'              => 'text',
				'wrapper'           => [ 'width' => 50 ],
				'conditional_logic' => self::CONDITION_COUNTRY_NOT_EMPTY,
			],
			'address_state' => [
				'key'               => 'mai_location_address_state',
				'label'             => 'State',
				'type'              => 'select',
				'wrapper'           => [ 'width' => 30 ],
				'choices'           => mailocations_get_state_choices(),
				'conditional_logic' => [
					[
						'field'    => 'mai_location_address_country',
						'operator' => '!=empty',
					],
					[
						'field'    => 'mai_location_address_country',
						'operator' => '==',
						'value'    => 'US',
					],
				],
			],
			'address_state_int' => [
				'key'               => 'mai_location_address_state_int',
				'label'             => 'State/Province',
				'type'              => 'text',
				'wrapper'           => [ 'width' => 30 ],
				'conditional_logic' => [
					[
						'field'    => 'mai_location_address_country',
						'operator' => '!=empty',
					],
					[
						'field'    => 'mai_location_address_country',
						'operator' => '!=',
						'value'    => 'US',
					],
				],
			],
			'address_postcode' => [
				'key'               => 'mai_location_address_postcode',
				'label'             => 'Zipcode',
				'type'              => 'text',
				'wrapper'           => [ 'width' => 20 ],
				'conditional_logic' => self::CONDITION_COUNTRY_NOT_EMPTY,
			],
			'location' => [
				'key'        => 'mai_location_location',
				'label'      => 'Location',
				'type'       => 'google_map',
				'center_lat' => '38.500000',
				'center_lng' => '-98.000000',
				'zoom'       => 4,
				'height'     => '',
			],
			'location_lat' => [
				'key'     => 'mai_location_lat',
				'label'   => 'Latitude',
				'type'    => 'text',
				'wrapper' => [ 'width' => 50 ],
			],
			'location_lng' => [
				'key'     => 'mai_location_lng',
				'label'   => 'Longitude',
				'type'    => 'text',
				'wrapper' => [ 'width' => 50 ],
			],
			'place_id' => [
				'key'   => 'mai_location_place_id',
				'label' => 'Place ID',
				'type'  => 'text',
			],
		];

		$this->assertSame( $expected, mailocations_get_address_fields() );
	}

	public function test_fields_raw_is_general_then_address_keyed_by_name(): void {
		$this->assertSame(
			array_merge( mailocations_get_general_fields(), mailocations_get_address_fields() ),
			mailocations_get_fields_raw()
		);
	}

	public function test_fields_is_a_list_with_name_added_to_each_field(): void {
		$fields   = mailocations_get_fields();
		$expected = [];

		foreach ( mailocations_get_fields_raw() as $name => $values ) {
			$values['name'] = $name;
			$expected[]     = $values;
		}

		$this->assertSame( $expected, $fields );
		$this->assertTrue( array_is_list( $fields ) );
		$this->assertSame(
			[
				'location_general_tab',
				'location_url',
				'location_phone',
				'location_phone_2',
				'location_email',
				'location_address_tab',
				'address_country',
				'address_street',
				'address_street_2',
				'address_city',
				'address_state',
				'address_state_int',
				'address_postcode',
				'location',
				'location_lat',
				'location_lng',
				'place_id',
			],
			array_column( $fields, 'name' )
		);
	}

	public function test_fields_tabs_returns_only_tab_fields_keyed_by_name(): void {
		$raw = mailocations_get_fields_raw();

		$this->assertSame(
			[
				'location_general_tab' => $raw['location_general_tab'],
				'location_address_tab' => $raw['location_address_tab'],
			],
			mailocations_get_fields_tabs()
		);
	}

	public function test_fields_defaults_drop_tabs_and_default_to_empty_string(): void {
		$this->assertSame(
			[
				'location_url'      => '',
				'location_phone'    => '',
				'location_phone_2'  => '',
				'location_email'    => '',
				'address_country'   => 'US',
				'address_street'    => '',
				'address_street_2'  => '',
				'address_city'      => '',
				'address_state'     => '',
				'address_state_int' => '',
				'address_postcode'  => '',
				'location'          => '',
				'location_lat'      => '',
				'location_lng'      => '',
				'place_id'          => '',
			],
			mailocations_get_fields_defaults()
		);
	}

	public function test_state_choices_start_empty_and_include_dc_and_puerto_rico(): void {
		$choices = mailocations_get_state_choices();

		$this->assertCount( 53, $choices );
		$this->assertSame( '', array_key_first( $choices ) );
		$this->assertSame( 'Choose a state', $choices[''] );
		$this->assertSame( 'AL', array_keys( $choices )[1] );
		$this->assertSame( 'New York', $choices['NY'] );
		$this->assertSame( 'Puerto Rico', $choices['PR'] );
		$this->assertSame( 'WY', array_key_last( $choices ) );
	}

	public function test_pins_bug_dc_label_is_misspelled_colombia(): void {
		// Should read "District of Columbia".
		// Spelling fixed September 16, 2026. The country list's "Colombia" is a different entry.
		$this->assertSame( 'District of Columbia', mailocations_get_state_choices()['DC'] );
	}

	public function test_country_choices_start_empty_and_us_is_not_first(): void {
		$choices = mailocations_get_country_choices();
		$keys    = array_keys( $choices );

		$this->assertCount( 245, $choices );
		$this->assertSame( '', $keys[0] );
		$this->assertSame( 'Choose a country', $choices[''] );
		// Åland Islands sorts before Afghanistan.
		$this->assertSame( 'AX', $keys[1] );
		$this->assertSame( 'AF', $keys[2] );
		$this->assertSame( 'United States', $choices['US'] );
		$this->assertSame( 'Canada', $choices['CA'] );
		$this->assertSame( 'United Kingdom', $choices['GB'] );
		$this->assertSame( 232, array_search( 'US', $keys, true ) );
		$this->assertSame( 'ZW', array_key_last( $choices ) );
	}

	public function test_country_field_defaults_to_us(): void {
		$this->assertSame( 'US', mailocations_get_address_fields()['address_country']['default_value'] );
	}

	public function test_field_group_fields_for_all_and_for_mai_location(): void {
		$expected = [
			'mai_location_title',
			'mai_location_excerpt',
			'mai_location_image',
			'mai_location_publish',
			'mai_location_general_tab',
			'mai_location_url',
			'mai_location_phone',
			'mai_location_phone_2',
			'mai_location_email',
			'mai_location_address_tab',
			'mai_location_address_country',
			'mai_location_address_street',
			'mai_location_address_street_2',
			'mai_location_address_city',
			'mai_location_address_state',
			'mai_location_address_state_int',
			'mai_location_address_postcode',
			'mai_location_location',
			'mai_location_lat',
			'mai_location_lng',
			'mai_location_place_id',
			'mai_location_cat',
		];

		$this->assertSame( $expected, array_column( mailocations_get_field_group_fields(), 'key' ) );
		$this->assertSame( $expected, array_column( mailocations_get_field_group_fields( 'mai_location' ), 'key' ) );
	}

	public function test_field_group_fields_for_unsupported_post_type_is_empty(): void {
		$this->assertSame( [], mailocations_get_field_group_fields( 'post' ) );
	}

	public function test_field_group_fields_are_full_acf_field_arrays(): void {
		$fields = mailocations_get_field_group_fields( 'mai_location' );
		$city   = $fields[ array_search( 'mai_location_address_city', array_column( $fields, 'key' ), true ) ];

		$this->assertSame( 'address_city', $city['name'] );
		$this->assertSame( 'mai_locations_location_field_group', $city['parent'] );
	}

	public function test_each_field_filter_ran_exactly_once_at_boot(): void {
		// One run each proves the static cache.
		$this->assertSame( 1, did_filter( 'mailocations_general_fields' ) );
		$this->assertSame( 1, did_filter( 'mailocations_address_fields' ) );
		$this->assertSame( 1, did_filter( 'mailocations_fields' ) );
	}

	public function test_general_fields_filter_added_after_boot_has_no_effect(): void {
		$add = static function ( array $fields ): array {
			$fields['location_booking_url'] = [
				'key'   => 'mai_location_booking_url',
				'label' => 'Booking URL',
				'type'  => 'url',
			];

			return $fields;
		};

		add_filter( 'mailocations_general_fields', $add );

		$this->assertArrayNotHasKey( 'location_booking_url', mailocations_get_general_fields() );
		$this->assertFalse( acf_get_field( 'mai_location_booking_url' ) );

		remove_filter( 'mailocations_general_fields', $add );
	}
}

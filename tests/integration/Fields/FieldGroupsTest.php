<?php

declare(strict_types=1);

namespace Mai\Locations\Tests\Integration\Fields;

use Mai\Locations\Tests\TestCase;

/**
 * Pins the ACF field groups Mai_Locations_Location_Fields::register_field_groups() registers,
 * and how update_field() stores values through their keys.
 */
final class FieldGroupsTest extends TestCase {

	public function test_register_field_groups_runs_on_acf_init(): void {
		$this->assertSame( 10, $this->hook_priority( 'acf/init', 'register_field_groups' ) );
	}

	public function test_expected_groups_are_registered_and_visible_group_is_not(): void {
		$this->assertIsArray( acf_get_local_field_group( 'mai_locations_core_field_group' ) );
		$this->assertIsArray( acf_get_local_field_group( 'mai_locations_core_mai_location_field_group' ) );
		$this->assertIsArray( acf_get_local_field_group( 'mai_locations_location_field_group' ) );
		// mai_locations_{$post_type}_fields returns nothing by default, so this group is skipped.
		$this->assertNull( acf_get_local_field_group( 'mai_locations_mai_location_field_group' ) );
	}

	public function test_post_type_filters_ran_once_for_mai_location(): void {
		$this->assertSame( 1, did_filter( 'mai_locations_core_mai_location_fields' ) );
		$this->assertSame( 1, did_filter( 'mai_locations_mai_location_fields' ) );
	}

	public function test_core_group(): void {
		$group = acf_get_local_field_group( 'mai_locations_core_field_group' );

		$this->assertSame( '', $group['title'] );
		$this->assertSame( 999, $group['menu_order'] );
		$this->assertFalse( $group['location'] );

		$this->assertSame(
			[
				[ 'mai_location_title', 'title', 'text', 'Title' ],
				[ 'mai_location_excerpt', 'excerpt', 'wysiwyg', 'Description' ],
				[ 'mai_location_image', '', 'image', 'Image' ],
				[ 'mai_location_publish', 'publish', 'true_false', 'Publish' ],
			],
			$this->summarise( acf_get_fields( 'mai_locations_core_field_group' ) )
		);
	}

	/**
	 * The switch text is the whole point of the field: ACF's own default reads Yes / No, which
	 * says nothing about what saving will do.
	 */
	public function test_publish_switch_says_what_it_does(): void {
		$field = acf_get_field( 'mai_location_publish' );

		$this->assertSame( 'Publish', $field['ui_on_text'] );
		$this->assertSame( 'Not yet', $field['ui_off_text'] );
		$this->assertSame( 'Makes this visible to everyone.', $field['message'] );
		$this->assertSame( 1, $field['ui'] );
	}

	public function test_core_group_field_settings(): void {
		$title = acf_get_field( 'mai_location_title' );
		$image = acf_get_field( 'mai_location_image' );

		$this->assertSame( 1, $title['required'] );
		$this->assertEmpty( acf_get_field( 'mai_location_excerpt' )['required'] );
		$this->assertSame( 'id', $image['return_format'] );
		$this->assertSame( 'medium', $image['preview_size'] );
		$this->assertSame( 'uploadedTo', $image['library'] );
		$this->assertSame( 'Only jpeg, jpg, png allowed. 5 MB max.', $image['instructions'] );
	}

	public function test_pins_image_field_has_no_name_so_update_field_writes_nothing(): void {
		// The form listener handles this field by key; with a name ('image') update_field would store it.
		$post_id = $this->create_location();

		$this->assertNull( update_field( 'mai_location_image', 123, $post_id ) );
		$this->assertSame( [], get_post_meta( $post_id ) );
	}

	public function test_core_taxonomy_group_for_mai_location(): void {
		$group = acf_get_local_field_group( 'mai_locations_core_mai_location_field_group' );

		$this->assertSame( '', $group['title'] );
		$this->assertFalse( $group['location'] );

		$fields = acf_get_fields( 'mai_locations_core_mai_location_field_group' );

		$this->assertCount( 1, $fields );

		$taxonomy = $fields[0];

		// The field key is the bare taxonomy name, with no mai_location_ prefix.
		$this->assertSame( 'mai_location_cat', $taxonomy['key'] );
		$this->assertSame( 'mai_location_cat', $taxonomy['name'] );
		$this->assertSame( 'Location Categories', $taxonomy['label'] );
		$this->assertSame( 'taxonomy', $taxonomy['type'] );
		$this->assertSame( 'mai_location_cat', $taxonomy['taxonomy'] );
		$this->assertSame( 0, $taxonomy['add_term'] );
		$this->assertSame( 1, $taxonomy['save_terms'] );
		$this->assertSame( 1, $taxonomy['load_terms'] );
		$this->assertSame( 'id', $taxonomy['return_format'] );
		$this->assertSame( 'checkbox', $taxonomy['field_type'] );
		$this->assertSame( 'horizontal', $taxonomy['layout'] );
		$this->assertSame( 1, $taxonomy['allow_null'] );
		$this->assertSame( 1, $taxonomy['multiple'] );
	}

	public function test_location_info_group(): void {
		$group = acf_get_local_field_group( 'mai_locations_location_field_group' );

		$this->assertSame( '{SINGULAR} Info', $group['title'] );
		$this->assertSame( 10, $group['menu_order'] );
		$this->assertSame(
			[
				[
					[
						'param'    => 'mailocations_supported_post_types',
						'operator' => '==',
						'value'    => true,
					],
				],
			],
			$group['location']
		);

		$this->assertSame(
			[
				[ 'mai_location_general_tab', '', 'tab', 'General Info' ],
				[ 'mai_location_url', 'location_url', 'url', 'Website URL' ],
				[ 'mai_location_phone', 'location_phone', 'text', 'Phone' ],
				[ 'mai_location_phone_2', 'location_phone_2', 'text', 'Secondary Phone' ],
				[ 'mai_location_email', 'location_email', 'email', 'Email' ],
				[ 'mai_location_address_tab', '', 'tab', 'Address & Map' ],
				[ 'mai_location_address_country', 'address_country', 'select', 'Country' ],
				[ 'mai_location_address_street', 'address_street', 'text', 'Street' ],
				[ 'mai_location_address_street_2', 'address_street_2', 'text', 'Street (2nd line)' ],
				[ 'mai_location_address_city', 'address_city', 'text', 'City' ],
				[ 'mai_location_address_state', 'address_state', 'select', 'State' ],
				[ 'mai_location_address_state_int', 'address_state_int', 'text', 'State/Province' ],
				[ 'mai_location_address_postcode', 'address_postcode', 'text', 'Zipcode' ],
				[ 'mai_location_location', 'location', 'google_map', 'Location' ],
				[ 'mai_location_lat', 'location_lat', 'text', 'Latitude' ],
				[ 'mai_location_lng', 'location_lng', 'text', 'Longitude' ],
				[ 'mai_location_place_id', 'place_id', 'text', 'Place ID' ],
			],
			$this->summarise( acf_get_fields( 'mai_locations_location_field_group' ) )
		);
	}

	public function test_tab_fields_lose_their_name_when_acf_registers_them(): void {
		// mailocations_get_fields() sets name, but ACF blanks it for tab fields.
		$this->assertSame( '', acf_get_field( 'mai_location_general_tab' )['name'] );
		$this->assertSame( '', acf_get_field( 'mai_location_address_tab' )['name'] );
	}

	public function test_country_field_as_acf_sees_it(): void {
		$country = acf_get_field( 'mai_location_address_country' );

		$this->assertSame( 'US', $country['default_value'] );
		$this->assertSame( mailocations_get_country_choices(), $country['choices'] );
	}

	public function test_acf_keeps_the_flat_conditional_logic_shape(): void {
		// ACF documents conditional_logic as groups of rules. These are flat rules, and ACF does not normalise local fields.
		$this->assertSame(
			[
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
			acf_get_field( 'mai_location_address_state' )['conditional_logic']
		);
	}

	/**
	 * Keys and plain meta names Visit Sleepy Hollow's import script depends on.
	 *
	 * @return array<string, array{string, string, string}>
	 */
	public function sleepy_hollow_fields(): array {
		return [
			'country'  => [ 'mai_location_address_country', 'address_country', 'US' ],
			'street'   => [ 'mai_location_address_street', 'address_street', '1 Main Street' ],
			'city'     => [ 'mai_location_address_city', 'address_city', 'Tarrytown' ],
			'state'    => [ 'mai_location_address_state', 'address_state', 'NY' ],
			'postcode' => [ 'mai_location_address_postcode', 'address_postcode', '10591' ],
			'phone'    => [ 'mai_location_phone', 'location_phone', '(914) 555-0100' ],
			'url'      => [ 'mai_location_url', 'location_url', 'https://example.com/' ],
		];
	}

	/**
	 * @dataProvider sleepy_hollow_fields
	 */
	public function test_update_field_by_key_stores_value_under_plain_meta_name( string $key, string $name, string $value ): void {
		$post_id = $this->create_location();

		$this->assertIsInt( update_field( $key, $value, $post_id ) );
		$this->assertSame( $value, get_post_meta( $post_id, $name, true ) );
		$this->assertSame( $key, get_post_meta( $post_id, "_{$name}", true ) );
		$this->assertSame( [ $name, "_{$name}" ], array_keys( get_post_meta( $post_id ) ) );
	}

	/**
	 * @dataProvider sleepy_hollow_fields
	 */
	public function test_field_resolves_by_key_and_by_name( string $key, string $name ): void {
		$this->assertSame( $name, acf_get_field( $key )['name'] );
		$this->assertSame( $key, acf_get_field( $name )['key'] );
	}

	public function test_get_field_reads_meta_saved_without_acf_reference_row(): void {
		$post_id = $this->create_location( [ 'address_city' => 'Sleepy Hollow' ] );

		$this->assertSame( 'Sleepy Hollow', get_field( 'mai_location_address_city', $post_id ) );
	}

	/**
	 * Reduces ACF field arrays to key, name, type and label.
	 *
	 * @param array<int, array<string, mixed>> $fields
	 *
	 * @return array<int, array{mixed, mixed, mixed, mixed}>
	 */
	private function summarise( array $fields ): array {
		return array_map( static fn( array $f ): array => [ $f['key'], $f['name'], $f['type'], $f['label'] ], $fields );
	}

	/**
	 * Returns the priority a Mai_Locations_Location_Fields method is hooked at, or false.
	 */
	private function hook_priority( string $hook, string $method ): int|false {
		foreach ( $GLOBALS['wp_filter'][ $hook ]->callbacks ?? [] as $priority => $callbacks ) {
			foreach ( $callbacks as $callback ) {
				if ( is_array( $callback['function'] ) && $callback['function'][0] instanceof \Mai_Locations_Location_Fields && $method === $callback['function'][1] ) {
					return $priority;
				}
			}
		}

		return false;
	}
}

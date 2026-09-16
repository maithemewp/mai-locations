<?php

declare(strict_types=1);

namespace Mai\Locations\Tests\Integration\Fields;

use Mai\Locations\Tests\TestCase;
use Mai_Locations_Location_Fields;
use ReflectionClass;

/**
 * Pins the ACF filter callbacks in classes/class-location-fields.php.
 */
final class LocationFieldsCallbacksTest extends TestCase {

	private Mai_Locations_Location_Fields $fields;

	public function set_up(): void {
		parent::set_up();

		// Skip the constructor so the hooks are not added a second time.
		$this->fields = ( new ReflectionClass( Mai_Locations_Location_Fields::class ) )->newInstanceWithoutConstructor();
	}

	public function tear_down(): void {
		unset( $GLOBALS['current_screen'] );

		parent::tear_down();
	}

	public function test_hooks_are_registered_with_these_priorities_and_args(): void {
		$this->assertSame( [ 10, 4 ], $this->hook( 'acf/location/rule_match/mailocations_supported_post_types', 'post_type_rule_match' ) );
		$this->assertSame( [ 10, 1 ], $this->hook( 'acf/load_field_group', 'handle_field_group_titles' ) );
		$this->assertSame( [ 10, 1 ], $this->hook( 'acf/prepare_field/key=mai_location_fields', 'load_location_fields_choices' ) );
		// The table block's own copy of the field, which needs a different key to keep its labels.
		$this->assertSame( [ 10, 1 ], $this->hook( 'acf/prepare_field/key=mailocations_table_fields', 'load_location_fields_choices' ) );
		$this->assertSame( [ 10, 1 ], $this->hook( 'acf/prepare_field/key=mai_location_lat', 'prepare_location_coordinates_field' ) );
		$this->assertSame( [ 10, 1 ], $this->hook( 'acf/prepare_field/key=mai_location_lng', 'prepare_location_coordinates_field' ) );
		$this->assertSame( [ 10, 1 ], $this->hook( 'acf/prepare_field/key=mai_location_place_id', 'prepare_location_place_id_field' ) );
		// Renamed September 16, 2026. The old, misspelled name still works.
		$this->assertSame( [ 10, 1 ], $this->hook( 'acf/prepare_field/key=mai_location_excerpt', 'prepare_location_excerpt_field' ) );
	}

	public function test_rule_match_is_true_for_supported_post_type_in_array_screen(): void {
		$this->assertTrue( $this->fields->post_type_rule_match( false, [], [ 'post_type' => 'mai_location' ], [] ) );
	}

	public function test_rule_match_ignores_incoming_result_operator_and_value(): void {
		$rule = [
			'param'    => 'mailocations_supported_post_types',
			'operator' => '!=',
			'value'    => false,
		];

		$this->assertFalse( $this->fields->post_type_rule_match( true, $rule, [ 'post_type' => 'post' ], [] ) );
		$this->assertFalse( $this->fields->post_type_rule_match( true, $rule, [], [] ) );
		$this->assertTrue( $this->fields->post_type_rule_match( false, $rule, [ 'post_type' => 'mai_location' ], [] ) );
	}

	public function test_acf_passes_screen_as_array_so_visibility_follows_post_type(): void {
		// The docblock says WP_Screen, but acf_match_location_rule() passes the screen args array.
		$group = acf_get_field_group( 'mai_locations_location_field_group' );

		$this->assertTrue( acf_get_field_group_visibility( $group, [ 'post_type' => 'mai_location' ] ) );
		$this->assertFalse( acf_get_field_group_visibility( $group, [ 'post_type' => 'post' ] ) );
		$this->assertFalse( acf_get_field_group_visibility( $group, [] ) );
	}

	public function test_only_location_info_group_shows_on_mai_location_edit_screen(): void {
		$this->assertSame(
			[ 'mai_locations_location_field_group' ],
			array_column( acf_get_field_groups( [ 'post_type' => 'mai_location' ] ), 'key' )
		);
		$this->assertSame( [], array_column( acf_get_field_groups( [ 'post_type' => 'post' ] ), 'key' ) );
	}

	public function test_pins_rule_match_fatals_on_a_real_wp_screen(): void {
		// Would matter only if something passed WP_Screen as documented; reading it as an array throws.
		require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';

		$screen = \WP_Screen::get( 'mai_location' );

		$this->expectException( \Error::class );
		$this->expectExceptionMessage( 'Cannot use object of type WP_Screen as array' );

		$this->fields->post_type_rule_match( false, [], $screen, [] );
	}

	public function test_titles_replace_placeholders_for_location_post(): void {
		$GLOBALS['post'] = get_post( $this->create_location() );

		$group = $this->fields->handle_field_group_titles(
			[
				'key'   => 'mai_locations_location_field_group',
				'title' => '{SINGULAR} Info for {PLURAL}',
			]
		);

		$this->assertSame( 'Location Info for Locations', $group['title'] );
	}

	public function test_titles_replace_on_core_group_too(): void {
		$GLOBALS['post'] = get_post( $this->create_location() );

		$group = $this->fields->handle_field_group_titles( [ 'key' => 'mai_locations_core_field_group', 'title' => '{PLURAL}' ] );

		$this->assertSame( 'Locations', $group['title'] );
	}

	/**
	 * Fixed September 16, 2026. Without a post this bailed and left the raw placeholder.
	 */
	public function test_titles_use_the_plugin_labels_without_a_post(): void {
		unset( $GLOBALS['post'] );

		$group = [ 'key' => 'mai_locations_location_field_group', 'title' => '{SINGULAR} Info' ];

		$this->assertSame( 'Location Info', $this->fields->handle_field_group_titles( $group )['title'] );
	}

	public function test_titles_use_the_plugin_labels_for_an_unsupported_post_type(): void {
		$GLOBALS['post'] = get_post( self::factory()->post->create() );

		$group = [ 'key' => 'mai_locations_location_field_group', 'title' => '{SINGULAR} Info' ];

		$this->assertSame( 'Location Info', $this->fields->handle_field_group_titles( $group )['title'] );
	}

	public function test_titles_unchanged_for_other_groups(): void {
		$GLOBALS['post'] = get_post( $this->create_location() );

		$group = [ 'key' => 'some_other_group', 'title' => '{SINGULAR} Info' ];

		$this->assertSame( $group, $this->fields->handle_field_group_titles( $group ) );
	}

	/**
	 * Fixed September 16, 2026. ACF runs acf/load_field_group once, with no post, and caches the
	 * result, so the raw placeholder was what everyone saw.
	 */
	public function test_cached_group_title_has_no_placeholder_left(): void {
		$GLOBALS['post'] = get_post( $this->create_location() );

		$this->assertSame( 'Location Info', acf_get_field_group( 'mai_locations_location_field_group' )['title'] );
	}

	public function test_fields_choices_unchanged_outside_admin(): void {
		$field = [ 'key' => 'mai_location_fields', 'value' => [ 'x' ], 'choices' => [ 'a' => 'A' ] ];

		$this->assertSame( $field, $this->fields->load_location_fields_choices( $field ) );
	}

	public function test_fields_choices_in_admin_put_selected_first_and_keep_unknown_keys(): void {
		$this->fake_admin();

		$field = $this->fields->load_location_fields_choices(
			[
				'key'           => 'mai_location_fields',
				'value'         => [ 'mai_location_url', 'stale_key', 'mai_location_lat' ],
				'choices'       => [ 'ignored' => 'Ignored' ],
				'default_value' => [],
			]
		);

		$this->assertSame(
			[
				'mai_location_url'              => 'Website URL',
				'stale_key'                     => 'stale_key',
				'mai_location_title'            => 'Title',
				'mai_location_excerpt'          => 'Description',
				'mai_location_image'            => 'Image',
				'mai_location_phone'            => 'Phone',
				'mai_location_phone_2'          => 'Secondary Phone',
				'mai_location_email'            => 'Email',
				'mai_location_address_country'  => 'Country',
				'mai_location_address_street'   => 'Street',
				'mai_location_address_street_2' => 'Street (2nd line)',
				'mai_location_address_city'     => 'City',
				'mai_location_address_state'    => 'State',
				'mai_location_address_state_int' => 'State/Province',
				'mai_location_address_postcode' => 'Zipcode',
				'mai_location_location'         => 'Location',
				'mai_location_cat'              => 'Location Categories',
			],
			$field['choices']
		);
		$this->assertSame( [ 'mai_location_title', 'mai_location_excerpt', 'mai_location_location' ], $field['default_value'] );
	}

	public function test_fields_choices_in_admin_with_no_value(): void {
		$this->fake_admin();

		$field = $this->fields->load_location_fields_choices( [ 'key' => 'mai_location_fields', 'value' => null ] );

		$this->assertSame( 'mai_location_title', array_key_first( $field['choices'] ) );
		$this->assertCount( 16, $field['choices'] );
		$this->assertArrayNotHasKey( 'mai_location_lat', $field['choices'] );
		$this->assertArrayNotHasKey( 'mai_location_lng', $field['choices'] );
		$this->assertArrayNotHasKey( 'mai_location_place_id', $field['choices'] );
		$this->assertArrayNotHasKey( 'mai_location_general_tab', $field['choices'] );
	}

	public function test_fields_choices_in_admin_drop_empty_string_value(): void {
		$this->fake_admin();

		$field = $this->fields->load_location_fields_choices( [ 'key' => 'mai_location_fields', 'value' => '' ] );

		$this->assertArrayNotHasKey( '', $field['choices'] );
		$this->assertCount( 16, $field['choices'] );
	}

	public function test_coordinates_and_place_id_fields_are_disabled(): void {
		$this->assertSame( [ 'key' => 'mai_location_lat', 'disabled' => 'disabled' ], $this->fields->prepare_location_coordinates_field( [ 'key' => 'mai_location_lat' ] ) );
		$this->assertSame( [ 'key' => 'mai_location_lng', 'disabled' => 'disabled' ], $this->fields->prepare_location_coordinates_field( [ 'key' => 'mai_location_lng' ] ) );
		$this->assertSame( [ 'key' => 'mai_location_place_id', 'disabled' => 'disabled' ], $this->fields->prepare_location_place_id_field( [ 'key' => 'mai_location_place_id' ] ) );
	}

	public function test_excerpt_field_keeps_only_the_visual_tab(): void {
		$expected = [
			'key'          => 'mai_location_excerpt',
			'tabs'         => 'visual',
			'toolbar'      => 'basic',
			'media_upload' => 0,
		];

		$this->assertSame( $expected, $this->fields->prepare_location_excerpt_field( [ 'key' => 'mai_location_excerpt', 'tabs' => 'all' ] ) );

		// The old, misspelled name still works. Renamed September 16, 2026.
		$this->assertSame( $expected, $this->fields->prepare_location_exerpt_field( [ 'key' => 'mai_location_excerpt', 'tabs' => 'all' ] ) );
	}

	public function test_prepare_field_filters_run_through_acf(): void {
		$this->assertSame( 'disabled', acf_prepare_field( acf_get_field( 'mai_location_lat' ) )['disabled'] );
		$this->assertSame( 'disabled', acf_prepare_field( acf_get_field( 'mai_location_place_id' ) )['disabled'] );
		$this->assertSame( 'basic', acf_prepare_field( acf_get_field( 'mai_location_excerpt' ) )['toolbar'] );
	}

	/**
	 * Makes is_admin() return true until tear_down.
	 */
	private function fake_admin(): void {
		$GLOBALS['current_screen'] = new class() {
			public function in_admin(): bool {
				return true;
			}
		};
	}

	/**
	 * Returns [ priority, accepted_args ] for a Mai_Locations_Location_Fields method on a hook, or [].
	 *
	 * @return array<int, int>
	 */
	private function hook( string $hook, string $method ): array {
		foreach ( $GLOBALS['wp_filter'][ $hook ]->callbacks ?? [] as $priority => $callbacks ) {
			foreach ( $callbacks as $callback ) {
				if ( is_array( $callback['function'] ) && $callback['function'][0] instanceof Mai_Locations_Location_Fields && $method === $callback['function'][1] ) {
					return [ $priority, $callback['accepted_args'] ];
				}
			}
		}

		return [];
	}
}

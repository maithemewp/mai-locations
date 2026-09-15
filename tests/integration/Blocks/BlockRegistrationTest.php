<?php

declare(strict_types=1);

namespace Mai\Locations\Tests\Integration\Blocks;

use Mai\Locations\Tests\TestCase;
use WP_Block_Type_Registry;

/**
 * Pins how each ACF block registers from its block.json, and the ACF field group behind it.
 */
final class BlockRegistrationTest extends TestCase {

	/**
	 * @return array<string, array{0: string, 1: string, 2: ?array<int, string>, 3: bool, 4: string, 5: array<int, string>}>
	 */
	public static function blocks(): array {
		return [
			'address search' => [ 'acf/mai-locations-address-search', 'Mai Locations Address Search', [ 'acf/mai-locations-filters' ], false, 'location', [ 'location', 'address', 'search' ] ],
			'count'          => [ 'acf/mai-locations-count', 'Mai Locations Count', null, true, 'location', [ 'location', 'count', 'posts' ] ],
			'filter'         => [ 'acf/mai-locations-filter', 'Mai Locations Filter', [ 'acf/mai-locations-filters' ], false, 'category', [ 'location', 'filter', 'taxonomy' ] ],
			'filters'        => [ 'acf/mai-locations-filters', 'Mai Locations Filters', null, false, 'filter', [ 'location', 'filter', 'taxonomy' ] ],
			'map'            => [ 'acf/mai-locations-map', 'Mai Locations Map', null, false, 'location', [ 'location', 'map', 'google' ] ],
			'submission'     => [ 'acf/mai-location-submission', 'Mai Location Submission Form', null, false, 'location', [ 'location', 'create', 'submit' ] ],
			'table'          => [ 'acf/mai-locations-table', 'Mai Locations Table', null, false, 'location', [ 'location', 'table' ] ],
		];
	}

	/**
	 * @dataProvider blocks
	 *
	 * @param array<int, string>|null $parent
	 * @param array<int, string>      $keywords
	 */
	public function test_block_is_registered_from_block_json( string $name, string $title, ?array $parent, bool $align, string $icon, array $keywords ): void {
		$block_type = WP_Block_Type_Registry::get_instance()->get_registered( $name );

		$this->assertNotNull( $block_type );
		$this->assertSame( $title, $block_type->title );
		$this->assertSame( 'widgets', $block_type->category );
		$this->assertSame( $parent, $block_type->parent );
		$this->assertSame( $icon, $block_type->icon );
		$this->assertSame( $keywords, $block_type->keywords );
		$this->assertSame( 3, $block_type->api_version );

		// ACF swaps the class callback for its own and defaults block.json blocks to ACF block version 2.
		$this->assertSame( 'acf_render_block_callback', $block_type->render_callback );
		$this->assertSame( 2, $block_type->acf_block_version );

		// The count block.json has no supports key, so ACF's default align support stays on.
		$this->assertSame( [ 'align' => $align, 'html' => false, 'mode' => true, 'jsx' => true, 'multiple' => true ], $block_type->supports );
		$this->assertSame( [ 'name', 'data', 'align', 'mode', 'lock', 'metadata', 'className', 'style' ], array_keys( $block_type->attributes ) );
	}

	/**
	 * @return array<string, array{0: string, 1: string, 2: string, 3: array<string, string>}>
	 */
	public static function field_groups(): array {
		return [
			'address search' => [
				'mailocations_locations_address_search_field_group',
				'Mai Locations Address Search',
				'acf/mai-locations-address-search',
				[
					'mailocations_address_search_message'     => '',
					'mailocations_address_search_placeholder' => 'placeholder',
					'mailocations_address_search_distances'   => 'distances',
					'mailocations_address_search_units'       => 'units',
					'mailocations_address_search_countries'   => 'countries',
				],
			],
			'count' => [
				'mailocations_locations_count_field_group',
				'Mai Locations Count',
				'acf/mai-locations-count',
				[
					'mailocations_count_before'    => 'before',
					'mailocations_count_separator' => 'separator',
					'mailocations_count_after'     => 'after',
				],
			],
			'filter' => [
				'mailocations_locations_filter_field_group',
				'Mai Locations Filter',
				'acf/mai-locations-filter',
				[
					'mailocations_locations_filter'      => 'filter',
					'mailocations_locations_filter_type' => 'type',
				],
			],
			'map' => [
				'mailocations_locations_map_field_group',
				'Mai Locations Map',
				'acf/mai-locations-map',
				[
					'mailocations_map_query'          => 'query',
					'mailocations_map_query_filtered' => 'query_filtered',
					'mailocations_map_width'          => 'width',
					'mailocations_map_height'         => 'height',
					'mailocations_map_description'    => '',
				],
			],
			// Titled "Locations Table" in the code, a copy of the table group's title.
			'submission' => [
				'mai_location_submission_field_group',
				'Locations Table',
				'acf/mai-location-submission',
				[
					'mai_location_post_type' => 'location_post_type',
					'mai_location_status'    => 'location_status',
					'mai_location_redirect'  => 'location_redirect',
					'mai_location_emails'    => 'location_emails',
					'mai_location_fields'    => 'location_fields',
				],
			],
			'table' => [
				'mailocations_locations_table_field_group',
				'Locations Table',
				'acf/mai-locations-table',
				[
					'field_6071bfebbddbg'   => 'locations_table_post_type',
					'field_6071bfebbfdab'   => 'locations_table_title',
					'field_6071c00cbfdac'   => 'locations_table_header',
					'field_6071d22cdrdbd'   => 'locations_no_results',
					'mai_location_redirect' => 'location_redirect',
					'mai_location_fields'   => 'location_fields',
				],
			],
		];
	}

	/**
	 * @dataProvider field_groups
	 *
	 * @param array<string, string> $fields Field key => field name.
	 */
	public function test_field_group_is_registered( string $key, string $title, string $block, array $fields ): void {
		$group = acf_get_field_group( $key );

		$this->assertIsArray( $group );
		$this->assertSame( $title, $group['title'] );
		$this->assertSame( [ [ [ 'param' => 'block', 'operator' => '==', 'value' => $block ] ] ], $group['location'] );
		$this->assertSame( $fields, wp_list_pluck( acf_get_fields( $key ), 'name', 'key' ) );
	}

	public function test_pins_bug_table_group_shows_submission_labels_for_shared_field_keys(): void {
		// The submission and table groups both define mai_location_redirect and mai_location_fields.
		// ACF keeps the first definition of a key, so the table block's settings show the submission labels.
		// Correct behaviour: each group uses unique keys, and the table shows "Redirect" and "Edit Form Fields".
		$this->assertSame( 'mai_location_submission_field_group', acf_get_field( 'mai_location_redirect' )['parent'] );
		$this->assertSame( 'mai_location_submission_field_group', acf_get_field( 'mai_location_fields' )['parent'] );

		$labels = wp_list_pluck( acf_get_fields( 'mailocations_locations_table_field_group' ), 'label', 'key' );

		$this->assertSame( 'Submission Redirect', $labels['mai_location_redirect'] );
		$this->assertSame( 'Submission Form Fields', $labels['mai_location_fields'] );
	}

	public function test_submission_status_choices_are_core_post_statuses(): void {
		$this->assertSame( get_post_statuses(), acf_get_field( 'mai_location_status' )['choices'] );
	}

	public function test_post_type_choice_fields_are_only_filled_in_admin(): void {
		$submission = new \Mai_Locations_Submission_Block();
		$table      = new \Mai_Locations_Table_Block();
		$filter     = new \Mai_Locations_Filter_Block();

		$this->assertSame( [ 'choices' => '' ], $submission->load_post_type_choices( [ 'choices' => '' ] ) );
		$this->assertSame( [ 'choices' => '' ], $table->load_post_type_choices( [ 'choices' => '' ] ) );
		$this->assertSame( [ 'choices' => [] ], $filter->load_locations_filter_field( [ 'choices' => [] ] ) );

		set_current_screen( 'edit-post' );

		$this->assertSame( [ 'choices' => [ 'mai_location' => 'Locations' ] ], $submission->load_post_type_choices( [ 'choices' => '' ] ) );
		$this->assertSame( [ 'choices' => [ 'mai_location' => 'Locations' ] ], $table->load_post_type_choices( [ 'choices' => '' ] ) );
		$this->assertSame( [ 'choices' => [ 'mai_location_cat' => 'Location Categories' ] ], $filter->load_locations_filter_field( [ 'choices' => [] ] ) );
	}

	public function tear_down(): void {
		set_current_screen( 'front' );
		parent::tear_down();
	}
}

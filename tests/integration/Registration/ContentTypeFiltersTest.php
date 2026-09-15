<?php

declare(strict_types=1);

namespace Mai\Locations\Tests\Integration\Registration;

use Mai\Locations\Tests\TestCase;

require_once __DIR__ . '/ScenarioRunner.php';

/**
 * Labels, base and slugs set through filters and the saved option.
 *
 * These values are cached in static variables during bootstrap, so each case boots a fresh
 * process with the filter in place first. Visit Sleepy Hollow uses exactly this route to get
 * Place / Places and the `places` base.
 */
final class ContentTypeFiltersTest extends TestCase {

	use ScenarioRunner;

	public function test_label_and_base_filters_rename_the_post_type_like_visit_sleepy_hollow(): void {
		$result = $this->run_scenario(
			[
				'returns' => [
					'mailocations_plural'   => 'Places',
					'mailocations_singular' => 'Place',
					'mailocations_base'     => 'places',
				],
			]
		);

		$labels = $result['post_type_labels'];

		$this->assertSame( 'Places', $labels['name'] );
		$this->assertSame( 'Place', $labels['singular_name'] );
		$this->assertSame( 'Places', $labels['menu_name'] );
		$this->assertSame( 'Place', $labels['name_admin_bar'] );
		$this->assertSame( 'New Place', $labels['new_item'] );
		$this->assertSame( 'Edit Place', $labels['edit_item'] );
		$this->assertSame( 'View Place', $labels['view_item'] );
		$this->assertSame( 'All Places', $labels['all_items'] );
		$this->assertSame( 'Search Places', $labels['search_items'] );
		$this->assertSame( 'Parent Places', $labels['parent_item_colon'] );
		$this->assertSame( 'No Places found', $labels['not_found'] );
		$this->assertSame( 'No Places found in trash', $labels['not_found_in_trash'] );
		$this->assertSame( [ 'slug' => 'places', 'with_front' => false ], $result['post_type_rewrite'] );

		// The taxonomy labels follow the singular label.
		$this->assertSame( 'Place Categories', $result['taxonomy_labels']['name'] );
		$this->assertSame( 'Place Category', $result['taxonomy_labels']['singular_name'] );
		$this->assertSame( 'Place Categories', $result['taxonomy_labels']['menu_name'] );

		// The taxonomy slug does not follow the base.
		$this->assertSame( 'location-category', $result['taxonomy_rewrite']['slug'] );

		$this->assertSame( [ 'mai_location' => [ 'plural' => 'Places', 'singular' => 'Place' ] ], $result['location_post_types'] );
	}

	public function test_saved_option_sets_labels_base_and_category_base(): void {
		$result = $this->run_scenario(
			[
				'options' => [
					'label_plural'   => 'Stores',
					'label_singular' => 'Store',
					'base'           => 'Our Stores',
					'category_base'  => 'Store Types',
				],
			]
		);

		$this->assertSame( 'Stores', $result['post_type_labels']['name'] );
		$this->assertSame( 'Store', $result['post_type_labels']['singular_name'] );
		$this->assertSame( 'our-stores', $result['post_type_rewrite']['slug'] );
		$this->assertSame( 'store-types', $result['taxonomy_rewrite']['slug'] );
		$this->assertSame( 'Store Categories', $result['taxonomy_labels']['name'] );
	}

	public function test_pins_bug_base_sanitized_on_first_call_but_raw_on_later_calls(): void {
		$result = $this->run_scenario( [ 'returns' => [ 'mailocations_base' => 'Our Places!' ] ] );

		// The option is cleaned with sanitize_title_with_dashes(), but the filtered value only with sanitize_html_class().
		$this->assertSame( 'OurPlaces', $result['post_type_rewrite']['slug'] );

		// The static caches the raw value, so later calls skip sanitizing. They should match the first call.
		$this->assertSame( 'Our Places!', $result['base_later_call'] );
	}

	public function test_empty_filtered_base_gives_an_empty_rewrite_slug(): void {
		$result = $this->run_scenario( [ 'returns' => [ 'mailocations_base' => '' ] ] );

		$this->assertSame( '', $result['post_type_rewrite']['slug'] );
	}

	public function test_pins_bug_label_escaped_on_first_call_but_raw_on_later_calls(): void {
		$result = $this->run_scenario( [ 'returns' => [ 'mailocations_plural' => 'Bed & Breakfasts' ] ] );

		// First call, during registration, returns esc_html() output.
		$this->assertSame( 'Bed &amp; Breakfasts', $result['post_type_labels']['name'] );

		// The static caches the unescaped value, so later calls return it raw. Both should be escaped the same way.
		$this->assertSame( 'Bed & Breakfasts', $result['plural_later_call'] );
	}

	public function test_taxonomy_base_filter_is_sanitized_and_empty_falls_back_to_default(): void {
		$filtered = $this->run_scenario( [ 'returns' => [ 'mailocations_taxonomy_base' => 'Place Types' ] ] );
		$emptied  = $this->run_scenario( [ 'returns' => [ 'mailocations_taxonomy_base' => '' ] ] );

		$this->assertSame( 'place-types', $filtered['taxonomy_rewrite']['slug'] );
		$this->assertSame( 'location-category', $emptied['taxonomy_rewrite']['slug'] );
	}

	public function test_taxonomy_label_filters_replace_the_labels(): void {
		$result = $this->run_scenario(
			[
				'returns' => [
					'mailocations_taxonomy_plural'   => 'Place Types',
					'mailocations_taxonomy_singular' => 'Place Type',
				],
			]
		);

		$this->assertSame( 'Place Types', $result['taxonomy_labels']['name'] );
		$this->assertSame( 'Place Type', $result['taxonomy_labels']['singular_name'] );
		$this->assertSame( 'Place Types', $result['taxonomy_labels']['menu_name'] );
	}

	public function test_args_filters_change_post_type_and_taxonomy_registration(): void {
		$result = $this->run_scenario(
			[
				'merges' => [
					'mai_location_post_type_args' => [ 'has_archive' => false, 'menu_icon' => 'dashicons-store' ],
					'mai_location_cat_args'       => [ 'hierarchical' => false ],
				],
			]
		);

		$this->assertFalse( $result['post_type_has_archive'] );
		$this->assertSame( 'dashicons-store', $result['post_type_menu_icon'] );
		$this->assertFalse( $result['taxonomy_hierarchical'] );
	}
}

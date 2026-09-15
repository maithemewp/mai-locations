<?php

declare(strict_types=1);

namespace Mai\Locations\Tests\Integration\Registration;

use Mai\Locations\Tests\TestCase;

/**
 * The `mai_location` post type and `mai_location_cat` taxonomy with no settings saved.
 */
final class PostTypeRegistrationTest extends TestCase {

	public function test_registered_on_init_before_acf(): void {
		$this->assertSame( 4, has_action( 'init', [ mai_locations_plugin(), 'register_content_types' ] ) );
	}

	public function test_post_type_args(): void {
		$post_type = get_post_type_object( 'mai_location' );

		$this->assertTrue( $post_type->public );
		$this->assertTrue( $post_type->publicly_queryable );
		$this->assertFalse( $post_type->exclude_from_search );
		$this->assertFalse( $post_type->hierarchical );
		$this->assertTrue( $post_type->has_archive );
		$this->assertTrue( $post_type->show_ui );
		$this->assertTrue( $post_type->show_in_menu );
		$this->assertTrue( $post_type->show_in_nav_menus );
		$this->assertTrue( $post_type->show_in_rest );
		$this->assertSame( 'dashicons-location', $post_type->menu_icon );
		$this->assertSame( [ 'mai_location_cat' ], $post_type->taxonomies );
		// WordPress only adds feeds, pages and ep_mask when permalinks are on, which they are not here.
		$this->assertSame( [ 'slug' => 'locations', 'with_front' => false ], $post_type->rewrite );
	}

	public function test_post_type_supports(): void {
		$this->assertSame(
			[ 'title', 'editor', 'excerpt', 'author', 'thumbnail', 'page-attributes', 'mai-locations', 'genesis-cpt-archives-settings', 'mai-archive-settings', 'mai-single-settings', 'autosave' ],
			array_keys( get_all_post_type_supports( 'mai_location' ) )
		);
	}

	public function test_post_type_labels(): void {
		$labels = get_post_type_object( 'mai_location' )->labels;

		$this->assertSame( 'Locations', $labels->name );
		$this->assertSame( 'Location', $labels->singular_name );
		$this->assertSame( 'Locations', $labels->menu_name );
		$this->assertSame( 'Location', $labels->name_admin_bar );
		$this->assertSame( 'Add New', $labels->add_new );
		$this->assertSame( 'Add New', $labels->add_new_item );
		$this->assertSame( 'New Location', $labels->new_item );
		$this->assertSame( 'Edit Location', $labels->edit_item );
		$this->assertSame( 'View Location', $labels->view_item );
		$this->assertSame( 'All Locations', $labels->all_items );
		$this->assertSame( 'Search Locations', $labels->search_items );
		$this->assertSame( 'Parent Locations', $labels->parent_item_colon );
		$this->assertSame( 'No Locations found', $labels->not_found );
		$this->assertSame( 'No Locations found in trash', $labels->not_found_in_trash );
	}

	public function test_taxonomy_args(): void {
		$taxonomy = get_taxonomy( 'mai_location_cat' );

		$this->assertSame( [ 'mai_location' ], $taxonomy->object_type );
		$this->assertTrue( $taxonomy->hierarchical );
		$this->assertTrue( $taxonomy->public );
		$this->assertTrue( $taxonomy->show_ui );
		$this->assertTrue( $taxonomy->show_admin_column );
		$this->assertTrue( $taxonomy->show_in_nav_menus );
		$this->assertTrue( $taxonomy->show_in_rest );
		$this->assertTrue( $taxonomy->show_tagcloud );
		$this->assertFalse( $taxonomy->meta_box_cb );
		$this->assertSame( [ 'slug' => 'location-category', 'with_front' => false ], $taxonomy->rewrite );
	}

	public function test_taxonomy_labels(): void {
		$labels = get_taxonomy( 'mai_location_cat' )->labels;

		$this->assertSame( 'Location Categories', $labels->name );
		$this->assertSame( 'Location Category', $labels->singular_name );
		$this->assertSame( 'Location Categories', $labels->menu_name );

		// Not set by the plugin, so WordPress's category wording shows through.
		$this->assertSame( 'Edit Category', $labels->edit_item );
	}

	public function test_label_and_base_getters_with_nothing_saved(): void {
		$this->assertSame( 'Locations', mailocations_get_plural() );
		$this->assertSame( 'Location', mailocations_get_singular() );
		$this->assertSame( 'locations', mailocations_get_base() );
	}

	public function test_activation_and_deactivation_hooks(): void {
		$basename = plugin_basename( MAI_LOCATIONS_PLUGIN_FILE );

		$this->assertSame( 10, has_action( "activate_{$basename}", [ mai_locations_plugin(), 'activate' ] ) );
		$this->assertSame( 10, has_action( "deactivate_{$basename}", 'flush_rewrite_rules' ) );
	}

	public function test_activate_registers_content_types_and_flushes_rewrite_rules(): void {
		$this->set_permalink_structure( '/%postname%/' );
		delete_option( 'rewrite_rules' );
		unregister_taxonomy( 'mai_location_cat' );
		unregister_post_type( 'mai_location' );

		try {
			mai_locations_plugin()->activate();

			$rules = get_option( 'rewrite_rules' );

			$this->assertTrue( post_type_exists( 'mai_location' ) );
			$this->assertTrue( taxonomy_exists( 'mai_location_cat' ) );
			$this->assertIsArray( $rules );
			$this->assertArrayHasKey( 'locations/?$', $rules );
			// The taxonomy is hierarchical but its rewrite is not, so child term paths get no rule.
			$this->assertArrayHasKey( 'location-category/([^/]+)/?$', $rules );
		} finally {
			// Registering under pretty permalinks bakes them into the global post type and taxonomy
			// objects, which outlive this test and change permalinks in every test that runs after it.
			$this->set_permalink_structure( '' );
			unregister_taxonomy( 'mai_location_cat' );
			unregister_post_type( 'mai_location' );
			mai_locations_plugin()->register_content_types();
		}
	}

	public function test_updater_hooked_on_plugins_loaded(): void {
		$this->assertSame( 10, has_action( 'plugins_loaded', [ mai_locations_plugin(), 'updater' ] ) );
	}
}

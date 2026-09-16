<?php

declare(strict_types=1);

namespace Mai\Locations\Tests\Integration\PublicApi;

use Mai\Locations\Tests\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Pins every name another site's code could depend on.
 *
 * The plugin runs on many sites, and any of them may call these functions, hook these
 * filters, place these shortcodes or blocks, or read these options and meta keys. The
 * rework moves code into namespaces, so this test is the tripwire: removing or renaming
 * any of these names fails here. Remove a name from a list only as a deliberate,
 * changelogged decision.
 */
final class PublicNamesTest extends TestCase {

	/**
	 * Global functions, as declared on September 15, 2026.
	 *
	 * @return array<string, array{string}>
	 */
	public static function functions(): array {
		$names = [
			'mai_locations_plugin',
			'mailocation_get_user_locations',
			'mailocation_location_address_shortcode',
			'mailocation_location_email_shortcode',
			'mailocation_location_phone_shortcode',
			'mailocation_location_place_shortcode',
			'mailocation_location_table_shortcode',
			'mailocation_location_url_shortcode',
			'mailocations_add_location_to_user',
			'mailocations_create_location',
			'mailocations_create_location_from_woocommerce_user',
			'mailocations_delete_transients',
			'mailocations_do_upgrade',
			'mailocations_get_address',
			'mailocations_get_address_fields',
			'mailocations_get_address_meta_from_components',
			'mailocations_get_asset',
			'mailocations_get_base',
			'mailocations_get_country_choices',
			'mailocations_get_current_url_clean',
			'mailocations_get_data_from_website',
			'mailocations_get_distance',
			'mailocations_get_field_group_fields',
			'mailocations_get_fields',
			'mailocations_get_fields_defaults',
			'mailocations_get_fields_raw',
			'mailocations_get_fields_tabs',
			'mailocations_get_filtered_query_args',
			'mailocations_get_general_fields',
			'mailocations_get_google_maps_result',
			'mailocations_get_location_edit_form',
			'mailocations_get_location_post_types',
			'mailocations_get_location_submission_form',
			'mailocations_get_location_taxonomies',
			'mailocations_get_location_taxonomies_underscored',
			'mailocations_get_locations_table',
			'mailocations_get_option',
			'mailocations_get_option_default',
			'mailocations_get_options',
			'mailocations_get_options_defaults',
			'mailocations_get_plural',
			'mailocations_get_plural_label',
			'mailocations_get_query_defaults',
			'mailocations_get_query_params',
			'mailocations_get_singular',
			'mailocations_get_singular_label',
			'mailocations_get_state_choices',
			'mailocations_get_stylesheet_link',
			'mailocations_is_archive',
			'mailocations_is_filtered_locations',
			'mailocations_sanitize_options',
			'mailocations_update_address_from_google_map',
			'mailocations_update_google_map_from_address',
			'mailocations_update_option',
			'mailocations_upgrade_0_7_0',
			'mailocations_upgrade_completed',
			'mailocations_upload_image',
			'mailocations_user_can_edit',
			'mailocations_woocommerce_account_tab',
		];

		return array_combine( $names, array_map( fn( $name ) => [ $name ], $names ) );
	}

	/**
	 * @dataProvider functions
	 */
	public function test_function_exists( string $name ): void {
		$this->assertTrue( function_exists( $name ), "Public function {$name}() is gone" );
	}

	/**
	 * Class names. Other code can instantiate, extend or check for these.
	 *
	 * @return array<string, array{string}>
	 */
	public static function classes(): array {
		$names = [
			'Mai_Geo_Query',
			'Mai_Locations_Address_Search_Block',
			'Mai_Locations_Block_Bindings',
			'Mai_Locations_CLI',
			'Mai_Locations_Count_Block',
			'Mai_Locations_Filter_Block',
			'Mai_Locations_Filter_Clear_Block',
			'Mai_Locations_Filter_Submit_Block',
			'Mai_Locations_Filters_Block',
			'Mai_Locations_Location_Fields',
			'Mai_Locations_Location_Form',
			'Mai_Locations_Location_Form_Edit',
			'Mai_Locations_Location_Form_Listener',
			'Mai_Locations_Location_Form_Submit',
			'Mai_Locations_Location_Import',
			'Mai_Locations_Locations_Table',
			'Mai_Locations_Map_Block',
			'Mai_Locations_Plugin',
			'Mai_Locations_Queries',
			'Mai_Locations_Scripts',
			'Mai_Locations_Settings',
			'Mai_Locations_Submission_Block',
			'Mai_Locations_Table_Block',
			'Mai_Locations_Upgrade',
			'Mai_Locations_WooCommerce_Account_Tabs',
		];

		return array_combine( $names, array_map( fn( $name ) => [ $name ], $names ) );
	}

	/**
	 * @dataProvider classes
	 */
	public function test_class_exists( string $name ): void {
		$this->assertTrue( class_exists( $name ), "Public class {$name} is gone" );
	}

	/**
	 * Classes that have moved under Mai\Locations\, each keeping its old global name as an
	 * alias in inc/aliases.php. Add a line here as each class moves.
	 */
	public function test_migrated_classes_keep_their_old_names_as_aliases(): void {
		$migrated = [
			'Mai_Locations_Block_Bindings' => \Mai\Locations\BlockBindings::class,
			'Mai_Geo_Query'               => \Mai\Locations\GeoQuery::class,
			'Mai_Locations_Location_Form'        => \Mai\Locations\LocationForm::class,
			'Mai_Locations_Location_Form_Edit'   => \Mai\Locations\LocationFormEdit::class,
			'Mai_Locations_Location_Form_Listener' => \Mai\Locations\LocationFormListener::class,
			'Mai_Locations_Location_Form_Submit' => \Mai\Locations\LocationFormSubmit::class,
			'Mai_Locations_Locations_Table' => \Mai\Locations\LocationsTable::class,
			'Mai_Locations_Queries'       => \Mai\Locations\Queries::class,
			'Mai_Locations_Scripts'       => \Mai\Locations\Scripts::class,
			'Mai_Locations_Settings'      => \Mai\Locations\Settings::class,
			'Mai_Locations_Upgrade'       => \Mai\Locations\Upgrade::class,
			'Mai_Locations_WooCommerce_Account_Tabs' => \Mai\Locations\WooCommerceAccountTabs::class,
		];

		foreach ( $migrated as $old => $new ) {
			$this->assertTrue( class_exists( $new ), "{$new} is missing" );
			$this->assertTrue( class_exists( $old ), "{$old} no longer resolves" );
			$this->assertSame( $new, ( new \ReflectionClass( $old ) )->getName(), "{$old} is not an alias of {$new}" );
		}
	}

	public function test_constants_are_defined(): void {
		foreach ( [ 'MAI_LOCATIONS_VERSION', 'MAI_LOCATIONS_PLUGIN_DIR', 'MAI_LOCATIONS_PLUGIN_URL', 'MAI_LOCATIONS_PLUGIN_FILE', 'MAI_LOCATIONS_BASENAME' ] as $name ) {
			$this->assertTrue( defined( $name ), "Constant {$name} is gone" );
		}
	}

	/**
	 * Filter and action names the plugin fires. Checked in source, because a hook only shows
	 * up at runtime once the code path that fires it runs.
	 */
	public function test_hook_names_are_still_fired(): void {
		$hooks = [
			'mai_location_cat_args',
			'mai_location_post_type_args',
			'mai_locations_core_{$post_type}_fields',
			'mai_locations_{$post_type}_fields',
			'mailocations_account_{$endpoint}_content',
			'mailocations_acf_form_args',
			'mailocations_address_fields',
			'mailocations_base',
			'mailocations_fields',
			'mailocations_general_fields',
			'mailocations_localize_script_data',
			'mailocations_location_acf_form',
			'mailocations_location_form',
			'mailocations_location_query_defaults',
			'mailocations_plural',
			'mailocations_post_args',
			'mailocations_singular',
			'mailocations_taxonomy_base',
			'mailocations_taxonomy_plural',
			'mailocations_taxonomy_singular',
			'mailocations_woocommerce_account_tabs',
		];

		$source = $this->plugin_source();

		foreach ( $hooks as $hook ) {
			$fired = preg_match( '/(apply_filters|do_action)\(\s*[\'"]' . preg_quote( $hook, '/' ) . '[\'"]/', $source );

			$this->assertSame( 1, $fired, "Hook {$hook} is no longer fired" );
		}
	}

	public function test_shortcodes_are_registered(): void {
		foreach ( [ 'mai_location_address', 'mai_location_phone', 'mai_location_url', 'mai_location_email', 'mai_location_place', 'mai_location_distance', 'mai_locations_table' ] as $tag ) {
			$this->assertTrue( shortcode_exists( $tag ), "Shortcode [{$tag}] is gone" );
		}
	}

	public function test_blocks_are_registered(): void {
		$registry = \WP_Block_Type_Registry::get_instance();

		foreach ( [ 'acf/mai-locations-address-search', 'acf/mai-locations-count', 'acf/mai-locations-filters', 'acf/mai-locations-filter', 'acf/mai-locations-map', 'acf/mai-location-submission', 'acf/mai-locations-table' ] as $name ) {
			$this->assertTrue( $registry->is_registered( $name ), "Block {$name} is gone, so saved posts using it would break" );
		}
	}

	public function test_button_block_variations_are_registered(): void {
		$names = wp_list_pluck( \WP_Block_Type_Registry::get_instance()->get_registered( 'core/button' )->get_variations(), 'name' );

		$this->assertContains( 'mailocations-filter-clear', $names );
		$this->assertContains( 'mailocations-filter-submit', $names );
	}

	public function test_block_bindings_source_is_registered(): void {
		$this->assertNotNull( get_block_bindings_source( 'mai/locations' ) );
	}

	public function test_content_types_are_registered(): void {
		$this->assertTrue( post_type_exists( 'mai_location' ) );
		$this->assertTrue( taxonomy_exists( 'mai_location_cat' ) );
	}

	public function test_settings_live_in_one_option(): void {
		mailocations_update_option( 'google_api_key', 'pinned-key' );

		$this->assertSame( 'pinned-key', get_option( 'mai_locations' )['google_api_key'] ?? null );
	}

	/**
	 * Meta keys that saved locations on every site already hold.
	 */
	public function test_meta_keys_are_unchanged(): void {
		$fields = array_filter( mailocations_get_fields_raw(), fn( $field ) => 'tab' !== $field['type'] );
		$names  = array_keys( $fields );

		foreach ( [ 'location_url', 'location_phone', 'location_phone_2', 'location_email', 'address_country', 'address_street', 'address_street_2', 'address_city', 'address_state', 'address_state_int', 'address_postcode', 'location', 'location_lat', 'location_lng', 'place_id' ] as $meta_key ) {
			$this->assertContains( $meta_key, $names, "Meta key {$meta_key} is no longer a field, so existing data would be orphaned" );
		}
	}

	/**
	 * The social fields were switched off in 1.0.0 and removed on September 15, 2026 on Mike's
	 * call, after a fleet survey found no saved data on any site. Bringing them back is a
	 * visible change everywhere and needs its own changelog entry.
	 */
	public function test_social_fields_are_gone(): void {
		$this->assertFalse( function_exists( 'mailocations_get_social_fields' ) );
		$this->assertArrayNotHasKey( 'facebook', mailocations_get_fields_raw() );
	}

	/**
	 * Query string names that bookmarked or linked filter URLs already use.
	 */
	public function test_query_params_are_unchanged(): void {
		$keys = array_keys( mailocations_get_query_defaults() );

		foreach ( [ 'address', 'lat', 'lng', 'distance', 'units', 'state', 'province', '_mai_location_cat' ] as $param ) {
			$this->assertContains( $param, $keys, "Query param {$param} is gone, so existing filter links would break" );
		}
	}

	public function test_filters_form_post_action_is_hooked(): void {
		$this->assertTrue( has_action( 'admin_post_mailocations_filters' ) );
		$this->assertTrue( has_action( 'admin_post_nopriv_mailocations_filters' ) );
	}

	public function test_cli_command_and_subcommands_are_unchanged(): void {
		$this->assertMatchesRegularExpression( '/WP_CLI::add_command\(\s*[\'"]mailocations[\'"]/', $this->plugin_source() );
		$this->assertTrue( method_exists( 'Mai_Locations_CLI', 'import_places' ) );
		$this->assertTrue( method_exists( 'Mai_Locations_CLI', 'update_locations_from_website' ) );
	}

	/**
	 * All plugin PHP as one string, excluding tests and dependencies.
	 */
	private function plugin_source(): string {
		$root   = dirname( __DIR__, 3 );
		$source = (string) file_get_contents( $root . '/mai-locations.php' );

		foreach ( [ 'inc', 'blocks' ] as $dir ) {
			if ( ! is_dir( $root . '/' . $dir ) ) {
				continue;
			}

			$files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root . '/' . $dir ) );

			foreach ( $files as $file ) {
				if ( 'php' === $file->getExtension() ) {
					$source .= (string) file_get_contents( $file->getPathname() );
				}
			}
		}

		return $source;
	}
}

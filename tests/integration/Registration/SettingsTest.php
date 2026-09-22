<?php

declare(strict_types=1);

namespace Mai\Locations\Tests\Integration\Registration;

use Mai\Locations\Tests\TestCase;
use Mai_Locations_Settings;

require_once __DIR__ . '/ScenarioRunner.php';

/**
 * The settings page class: its hooks, setting registration, menu item, action link and the ACF
 * Google Maps key.
 */
final class SettingsTest extends TestCase {

	use ScenarioRunner;

	/**
	 * @var array<string, mixed>
	 */
	private array $saved_globals = [];

	public function set_up(): void {
		parent::set_up();

		require_once ABSPATH . 'wp-admin/includes/template.php';
		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		foreach ( [ 'submenu', '_wp_real_parent_file', '_wp_submenu_nopriv', '_registered_pages', '_parent_pages', 'wp_settings_sections', 'wp_settings_fields', 'wp_registered_settings', 'new_allowed_options' ] as $name ) {
			$this->saved_globals[ $name ] = $GLOBALS[ $name ] ?? null;
		}
	}

	public function tear_down(): void {
		if ( isset( $GLOBALS['wp_registered_settings']['mai_locations'] ) ) {
			unregister_setting( 'mai_locations_group', 'mai_locations' );
		}

		foreach ( $this->saved_globals as $name => $value ) {
			$GLOBALS[ $name ] = $value;
		}

		parent::tear_down();
	}

	public function test_hooks(): void {
		$settings = $this->settings();

		$this->assertSame( 12, has_action( 'admin_menu', [ $settings, 'add_menu_item' ] ) );
		$this->assertSame( 10, has_action( 'admin_init', [ $settings, 'init' ] ) );
		$this->assertSame( 99, has_filter( 'acf/fields/google_map/api', [ $settings, 'acf_google_map_api' ] ) );

		// Fixed September 16, 2026. The hook name hardcoded the plugin folder, so the Settings
		// link went missing on a site that renamed it. It is built from the plugin file now.
		$this->assertSame( 10, has_filter( 'plugin_action_links_' . plugin_basename( MAI_LOCATIONS_PLUGIN_FILE ), [ $settings, 'add_settings_link' ] ) );
	}

	public function test_init_registers_the_setting(): void {
		$settings = $this->settings();

		$settings->init();

		$registered = get_registered_settings()['mai_locations'];

		$this->assertSame( 'mai_locations_group', $registered['group'] );
		$this->assertSame( 'string', $registered['type'] );
		$this->assertSame( [ $settings, 'sanitize_callback' ], $registered['sanitize_callback'] );
		$this->assertFalse( $registered['show_in_rest'] );
		$this->assertArrayNotHasKey( 'default', $registered );
		$this->assertContains( 'mai_locations', $GLOBALS['new_allowed_options']['mai_locations_group'] );
		$this->assertSame( 10, has_filter( 'sanitize_option_mai_locations', [ $settings, 'sanitize_callback' ] ) );
	}

	public function test_init_adds_one_section_and_ten_fields(): void {
		$this->settings()->init();

		$this->assertSame( [ 'mai_locations_settings' ], array_keys( $GLOBALS['wp_settings_sections']['mai-locations-section'] ) );
		$this->assertSame( '', $GLOBALS['wp_settings_sections']['mai-locations-section']['mai_locations_settings']['title'] );

		$fields = $GLOBALS['wp_settings_fields']['mai-locations-section']['mai_locations_settings'];

		$this->assertSame(
			[
				'label_plural'         => 'Plural Label',
				'label_singular'       => 'Singular Label',
				'base'                 => 'Permalinks',
				'category_base'        => 'Category Permalinks',
				'owners_can_publish'   => 'Publishing',
				'distance'             => 'Default Distance',
				'units'                => 'Default Units',
				'google_api_key'       => 'Google API Key',
				'google_api_signature' => 'Google API Signature',
				'google_map_id'        => 'Google Map ID',
			],
			wp_list_pluck( $fields, 'title' )
		);
	}

	public function test_saving_the_option_runs_the_sanitizer(): void {
		$this->settings()->init();

		$this->assertSame( mailocations_sanitize_options( [ 'base' => 'Our Places' ] ), sanitize_option( 'mai_locations', [ 'base' => 'Our Places' ] ) );
		$this->assertSame( 'our-places', sanitize_option( 'mai_locations', [ 'base' => 'Our Places' ] )['base'] );
	}

	public function test_sanitize_callback_wraps_sanitize_options(): void {
		$input = [ 'label_plural' => '<b>Places</b>', 'distance' => '-5' ];

		$this->assertSame( mailocations_sanitize_options( $input ), $this->settings()->sanitize_callback( $input ) );
	}

	public function test_units_select_marks_the_saved_unit(): void {
		$settings = $this->settings();
		$settings->init();

		ob_start();
		$settings->add_content();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( '<h2>Mai Locations</h2>', $html );
		$this->assertStringContainsString( '<form method="post" action="options.php">', $html );
		$this->assertStringContainsString( "name='option_page' value='mai_locations_group'", $html );
		$this->assertStringContainsString( '<input class="regular-text" type="text" name="mai_locations[label_plural]" id="label_plural" value="Locations">', $html );
		$this->assertStringContainsString( '<input class="regular-text" type="text" name="mai_locations[base]" id="base" value="locations">', $html );
		$this->assertStringContainsString( '<input type="number" name="mai_locations[distance]" id="distance" value="100">', $html );
		$this->assertStringContainsString( '<input class="regular-text" type="password" name="mai_locations[google_api_key]" id="google_api_key" value="">', $html );

		// Fixed September 16, 2026. selected() used to echo as well as return, printing a stray
		// selected='selected' between the select tag and its first option.
		$this->assertStringContainsString( "<select name=\"mai_locations[units]\"><option value=\"mi\" selected='selected'>Miles</option><option value=\"km\">Kilometers</option></select>", $html );
	}

	public function test_add_menu_item_adds_settings_submenu_for_admins(): void {
		$settings = $this->settings();

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		$settings->add_menu_item();

		$this->assertContains( [ 'Settings', 'manage_options', 'mai-locations', 'Mai Locations' ], $GLOBALS['submenu']['edit.php?post_type=mai_location'] );
		$this->assertSame( 10, has_action( get_plugin_page_hookname( 'mai-locations', 'edit.php?post_type=mai_location' ), [ $settings, 'add_content' ] ) );

		remove_action( get_plugin_page_hookname( 'mai-locations', 'edit.php?post_type=mai_location' ), [ $settings, 'add_content' ] );
	}

	public function test_add_menu_item_skips_users_without_manage_options(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'editor' ] ) );

		$this->settings()->add_menu_item();

		$this->assertFalse( isset( $GLOBALS['submenu']['edit.php?post_type=mai_location'] ) && in_array( 'mai-locations', array_column( $GLOBALS['submenu']['edit.php?post_type=mai_location'], 2 ), true ) );
	}

	public function test_add_settings_link_puts_settings_first(): void {
		$actions = $this->settings()->add_settings_link( [ 'deactivate' => '<a>Deactivate</a>' ], 'mai-locations/mai-locations.php', [], 'all' );

		$this->assertSame(
			[
				'settings'   => '<a href="http://example.org/wp-admin/edit.php?post_type=mai_location&#038;page=mai-locations">Settings</a>',
				'deactivate' => '<a>Deactivate</a>',
			],
			$actions
		);
	}

	public function test_add_settings_link_overrides_an_existing_settings_key(): void {
		$actions = $this->settings()->add_settings_link( [ 'settings' => 'theirs' ], 'mai-locations/mai-locations.php', [], 'all' );

		// array_merge() lets the incoming array win on a shared string key.
		$this->assertSame( [ 'settings' => 'theirs' ], $actions );
	}

	public function test_acf_api_left_alone_when_no_key_is_saved(): void {
		$settings = $this->settings();

		$this->assertSame( [ 'key' => 'acf-key', 'signature' => 'acf-signature' ], $settings->acf_google_map_api( [ 'key' => 'acf-key', 'signature' => 'acf-signature' ] ) );
		$this->assertSame( [], $settings->acf_google_map_api( [] ) );
	}

	/**
	 * Fixed September 16, 2026. The condition was `isset( $api['key'] ) || empty( $api['key'] )`,
	 * always true, so a key saved here replaced whatever ACF already had.
	 */
	public function test_saved_key_fills_in_only_what_acf_is_missing(): void {
		$result = $this->run_scenario(
			[
				'options' => [
					'google_api_key'       => 'plugin-key',
					'google_api_signature' => 'plugin-signature',
				],
			]
		);

		$this->assertSame( [ 'key' => 'acf-key', 'signature' => 'acf-signature' ], $result['acf_api_with_key'] );
		$this->assertSame( [ 'key' => 'plugin-key', 'signature' => 'plugin-signature' ], $result['acf_api_empty'] );
	}

	public function test_settings_values_are_escaped_into_attributes(): void {
		$result = $this->run_scenario(
			[
				'probe'   => 'settings_page',
				'options' => [ 'label_plural' => 'Say "hi"', 'base' => 'places' ],
			]
		);

		// Fixed September 16, 2026. The saved value went into value="" unescaped, so a double
		// quote in a label closed the attribute and broke the field.
		$this->assertStringContainsString( 'id="label_plural" value="Say &quot;hi&quot;">', $result['html'] );
		$this->assertStringNotContainsString( 'value="Say "hi"">', $result['html'] );
	}

	private function settings(): Mai_Locations_Settings {
		foreach ( $GLOBALS['wp_filter']['acf/fields/google_map/api']->callbacks[99] as $callback ) {
			if ( is_array( $callback['function'] ) && $callback['function'][0] instanceof Mai_Locations_Settings ) {
				return $callback['function'][0];
			}
		}

		$this->fail( 'Mai_Locations_Settings instance not found.' );
	}
}

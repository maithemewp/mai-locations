<?php

declare(strict_types=1);

namespace Mai\Locations\Tests\Integration\Registration;

use Mai\Locations\Tests\TestCase;
use Mai_Locations_Scripts;

/**
 * Script and style handles from classes/class-locations-scripts.php.
 */
final class ScriptsTest extends TestCase {

	public function set_up(): void {
		parent::set_up();

		$this->remove_handles();
	}

	public function tear_down(): void {
		$this->remove_handles();

		parent::tear_down();
	}

	public function test_hooks(): void {
		$scripts = $this->scripts();

		$this->assertSame( 10, has_action( 'wp_enqueue_scripts', [ $scripts, 'register_scripts' ] ) );
		$this->assertSame( 10, has_action( 'enqueue_block_editor_assets', [ $scripts, 'enqueue_sortable' ] ) );
	}

	public function test_registers_front_end_styles(): void {
		$this->scripts()->register_scripts();

		$form      = wp_styles()->registered['mai-locations-form'];
		$locations = wp_styles()->registered['mai-locations'];

		$this->assertSame( MAI_LOCATIONS_PLUGIN_URL . 'build/mai-locations-form-styles.css', $form->src );
		$this->assertSame( [], $form->deps );
		$this->assertSame( $this->asset( 'mai-locations-form-styles' )['version'], $form->ver );

		$this->assertSame( MAI_LOCATIONS_PLUGIN_URL . 'build/mai-locations-styles.css', $locations->src );
		$this->assertSame( [], $locations->deps );
		$this->assertSame( $this->asset( 'mai-locations-styles' )['version'], $locations->ver );

		$this->assertFalse( wp_style_is( 'mai-locations', 'enqueued' ) );
	}

	public function test_registers_front_end_scripts_in_footer_without_strategy(): void {
		$this->scripts()->register_scripts();

		$clusterer = wp_scripts()->registered['mai-locations-markerclusterer'];
		$locations = wp_scripts()->registered['mai-locations'];

		$this->assertSame( MAI_LOCATIONS_PLUGIN_URL . 'build/markerclusterer.js', $clusterer->src );
		$this->assertSame( $this->asset( 'markerclusterer' )['dependencies'], $clusterer->deps );
		$this->assertSame( $this->asset( 'markerclusterer' )['version'], $clusterer->ver );
		$this->assertSame( 1, wp_scripts()->get_data( 'mai-locations-markerclusterer', 'group' ) );
		$this->assertFalse( wp_scripts()->get_data( 'mai-locations-markerclusterer', 'strategy' ) );

		$this->assertSame( MAI_LOCATIONS_PLUGIN_URL . 'build/mai-locations.js', $locations->src );
		$this->assertSame( array_merge( $this->asset( 'mai-locations' )['dependencies'], [ 'mai-locations-markerclusterer' ] ), $locations->deps );
		$this->assertSame( $this->asset( 'mai-locations' )['version'], $locations->ver );
		$this->assertSame( 1, wp_scripts()->get_data( 'mai-locations', 'group' ) );
		$this->assertFalse( wp_scripts()->get_data( 'mai-locations', 'strategy' ) );

		// Fixed September 16, 2026. The map script clusters markers but did not declare the
		// clusterer as a dependency, so it only happened to load in time.
		$this->assertContains( 'mai-locations-markerclusterer', $locations->deps );
		$this->assertFalse( wp_script_is( 'mai-locations', 'enqueued' ) );
	}

	public function test_localizes_script_data(): void {
		$this->scripts()->register_scripts();

		$this->assertSame(
			[
				'params'     => [],
				'defaults'   => mailocations_get_query_defaults(),
				'apiKey'     => '',
				'apiSig'     => '',
				'mapId'      => '',
				'loadingSvg' => MAI_LOCATIONS_PLUGIN_URL . 'assets/svg/loading.svg',
			],
			$this->localized()
		);
	}

	public function test_localized_data_filter(): void {
		$filter = static fn( array $data ): array => array_merge( $data, [ 'extra' => 'yes' ] );

		add_filter( 'mailocations_localize_script_data', $filter );
		$this->scripts()->register_scripts();
		remove_filter( 'mailocations_localize_script_data', $filter );

		$this->assertSame( 'yes', $this->localized()['extra'] );
	}

	public function test_block_editor_enqueues_sortable_script_and_style(): void {
		$this->scripts()->enqueue_sortable();

		$script = wp_scripts()->registered['mai-locations-sortable'];
		$style  = wp_styles()->registered['mai-locations-sortable'];

		$this->assertTrue( wp_script_is( 'mai-locations-sortable', 'enqueued' ) );
		$this->assertSame( MAI_LOCATIONS_PLUGIN_URL . 'build/mai-locations-sortable.js', $script->src );
		$this->assertSame( array_merge( [ 'jquery', 'jquery-ui-sortable', 'acf-input' ], $this->asset( 'mai-locations-sortable' )['dependencies'] ), $script->deps );
		$this->assertSame( $this->asset( 'mai-locations-sortable' )['version'], $script->ver );
		$this->assertSame( 'defer', wp_scripts()->get_data( 'mai-locations-sortable', 'strategy' ) );
		$this->assertSame( 1, wp_scripts()->get_data( 'mai-locations-sortable', 'group' ) );

		$this->assertTrue( wp_style_is( 'mai-locations-sortable', 'enqueued' ) );
		$this->assertSame( MAI_LOCATIONS_PLUGIN_URL . 'build/mai-locations-sortable-styles.css', $style->src );
		$this->assertSame( [], $style->deps );
		$this->assertSame( $this->asset( 'mai-locations-sortable-styles' )['version'], $style->ver );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function localized(): array {
		$data = (string) wp_scripts()->get_data( 'mai-locations', 'data' );

		$this->assertStringStartsWith( 'var maiLocationsVars = ', $data );

		return json_decode( substr( $data, strlen( 'var maiLocationsVars = ' ), -1 ), true );
	}

	/**
	 * @return array{dependencies: list<string>, version: string}
	 */
	private function asset( string $name ): array {
		return require MAI_LOCATIONS_PLUGIN_DIR . "build/{$name}.asset.php";
	}

	private function remove_handles(): void {
		foreach ( [ 'mai-locations', 'mai-locations-markerclusterer', 'mai-locations-sortable' ] as $handle ) {
			wp_dequeue_script( $handle );
			wp_deregister_script( $handle );
		}

		foreach ( [ 'mai-locations', 'mai-locations-form', 'mai-locations-sortable' ] as $handle ) {
			wp_dequeue_style( $handle );
			wp_deregister_style( $handle );
		}
	}

	private function scripts(): Mai_Locations_Scripts {
		foreach ( $GLOBALS['wp_filter']['wp_enqueue_scripts']->callbacks[10] as $callback ) {
			if ( is_array( $callback['function'] ) && $callback['function'][0] instanceof Mai_Locations_Scripts ) {
				return $callback['function'][0];
			}
		}

		$this->fail( 'Mai_Locations_Scripts instance not found.' );
	}
}

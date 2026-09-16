<?php

declare(strict_types=1);

namespace Mai\Locations\Tests\Integration\Registration;

use Mai\Locations\Tests\TestCase;
use Mai_Locations_Upgrade;

require_once __DIR__ . '/ScenarioRunner.php';

/**
 * The upgrade routines. `includes/upgrade.php` and `classes/class-upgrade.php` hold the same two
 * routines, and both copies are hooked, so each runs twice.
 */
final class UpgradeTest extends TestCase {

	use ScenarioRunner;

	/**
	 * Fixed September 16, 2026. Both copies used to be hooked, so every upgrade ran twice. The
	 * global functions remain, as public names, and now call the class rather than holding a
	 * second copy of the logic.
	 */
	public function test_only_the_class_copy_is_hooked_on_admin_init(): void {
		$this->assertFalse( has_action( 'admin_init', 'mailocations_do_upgrade' ) );
		$this->assertSame( 10, has_action( 'admin_init', [ $this->upgrade(), 'do_upgrade' ] ) );

		$this->assertSame( [ 'do_upgrade' ], $this->plugin_callbacks( 'admin_init', [ 'mailocations_do_upgrade', 'do_upgrade' ] ) );
	}

	public function test_only_the_class_copy_is_hooked_on_upgrader_process_complete(): void {
		$this->assertFalse( has_action( 'upgrader_process_complete', 'mailocations_upgrade_completed' ) );
		$this->assertSame( 10, has_action( 'upgrader_process_complete', [ $this->upgrade(), 'upgrade_completed' ] ) );
		$this->assertSame( [ 'upgrade_completed' ], $this->plugin_callbacks( 'upgrader_process_complete', [ 'mailocations_upgrade_completed', 'upgrade_completed' ] ) );

		foreach ( $GLOBALS['wp_filter']['upgrader_process_complete']->callbacks[10] as $callback ) {
			if ( in_array( $this->callback_name( $callback['function'] ), [ 'mailocations_upgrade_completed', 'upgrade_completed' ], true ) ) {
				$this->assertSame( 2, $callback['accepted_args'] );
			}
		}
	}

	public function test_placeholder_0_7_0_routines_exist_and_do_nothing(): void {
		$this->assertNull( mailocations_upgrade_0_7_0() );
		$this->assertNull( $this->upgrade()->upgrade_0_7_0() );
	}

	public function test_do_upgrade_on_fresh_install_writes_both_versions_once(): void {
		delete_option( 'mai_locations' );

		$writes = $this->count_option_writes(
			static function (): void {
				mailocations_do_upgrade();
			}
		);

		// version_first and version_db, one write each. Before September 16, 2026 both copies were
		// hooked, so a fresh install wrote four times.
		$this->assertSame( 2, $writes );
		$this->assertSame( [ 'version_first' => MAI_LOCATIONS_VERSION, 'version_db' => MAI_LOCATIONS_VERSION ], get_option( 'mai_locations' ) );
	}

	public function test_do_upgrade_does_nothing_when_versions_are_current(): void {
		$result = $this->run_scenario(
			[
				'probe'   => 'upgrade',
				'options' => [ 'version_first' => '1.0.0', 'version_db' => MAI_LOCATIONS_VERSION ],
			]
		);

		$this->assertSame( 0, $result['writes_after_function'] );
		$this->assertSame( [], $result['writes'] );
	}

	public function test_do_upgrade_bumps_an_old_db_version_once_per_copy(): void {
		$result = $this->run_scenario(
			[
				'probe'   => 'upgrade',
				'options' => [ 'label_plural' => 'Places', 'version_first' => '1.0.0', 'version_db' => '1.0.0' ],
			]
		);

		$this->assertSame( 1, $result['writes_after_function'] );
		$this->assertCount( 2, $result['writes'] );

		// The raw saved option is merged, keeping version_first and other keys.
		$this->assertSame( [ 'label_plural' => 'Places', 'version_first' => '1.0.0', 'version_db' => MAI_LOCATIONS_VERSION ], $result['writes'][0] );
		$this->assertSame( $result['writes'][0], $result['writes'][1] );
	}

	public function test_upgrade_completed_migrates_old_acf_options_and_second_copy_bails(): void {
		delete_option( 'mai_locations' );
		update_option( 'options_location_label_plural', 'Places' );
		update_option( '_options_location_label_plural', 'field_abc' );
		update_option( 'options_location_base', 'places' );

		mailocations_upgrade_completed( null, [] );

		$this->assertFalse( get_option( 'options_location_label_plural' ) );
		$this->assertFalse( get_option( '_options_location_label_plural' ) );
		$this->assertFalse( get_option( 'options_location_base' ) );

		// The request's cached options, which are the defaults here, are saved with the migrated values on top.
		$this->assertSame(
			array_merge( mailocations_get_options_defaults(), [ 'label_plural' => 'Places', 'base' => 'places' ] ),
			get_option( 'mai_locations' )
		);

		// The class copy runs next in the same request, sees the new option and stops, leaving this one behind.
		update_option( 'options_location_label_singular', 'Place' );

		$this->upgrade()->upgrade_completed( null, [] );

		$this->assertSame( 'Place', get_option( 'options_location_label_singular' ) );
	}

	/**
	 * Fixed September 16, 2026. The migrated values were saved exactly as they were.
	 */
	public function test_upgrade_completed_sanitizes_migrated_values(): void {
		delete_option( 'mai_locations' );
		update_option( 'options_location_base', 'Our Places!' );

		$this->upgrade()->upgrade_completed( null, [] );

		$this->assertSame( 'our-places', get_option( 'mai_locations' )['base'] );
	}

	public function test_upgrade_completed_bails_when_option_exists(): void {
		update_option( 'mai_locations', [ 'base' => 'locations' ] );
		update_option( 'options_location_base', 'places' );

		mailocations_upgrade_completed( null, [] );

		$this->assertSame( 'places', get_option( 'options_location_base' ) );
		$this->assertSame( [ 'base' => 'locations' ], get_option( 'mai_locations' ) );
	}

	public function test_upgrade_completed_saves_nothing_without_old_values(): void {
		delete_option( 'mai_locations' );

		mailocations_upgrade_completed( null, [] );

		$this->assertFalse( get_option( 'mai_locations' ) );
	}

	private function count_option_writes( callable $callback ): int {
		$writes  = 0;
		$counter = static function ( $value ) use ( &$writes ) {
			++$writes;
			return $value;
		};

		add_filter( 'pre_update_option_mai_locations', $counter );
		$callback();
		remove_filter( 'pre_update_option_mai_locations', $counter );

		return $writes;
	}

	/**
	 * Names of the plugin's callbacks on a hook at priority 10, in run order.
	 *
	 * @param list<string> $names Callback names to keep.
	 *
	 * @return list<string>
	 */
	private function plugin_callbacks( string $hook, array $names ): array {
		$found = [];

		foreach ( $GLOBALS['wp_filter'][ $hook ]->callbacks[10] as $callback ) {
			$name = $this->callback_name( $callback['function'] );

			if ( in_array( $name, $names, true ) ) {
				$found[] = $name;
			}
		}

		return $found;
	}

	private function callback_name( mixed $function ): string {
		if ( is_string( $function ) ) {
			return $function;
		}

		return is_array( $function ) && $function[0] instanceof Mai_Locations_Upgrade ? $function[1] : '';
	}

	private function upgrade(): Mai_Locations_Upgrade {
		foreach ( $GLOBALS['wp_filter']['admin_init']->callbacks[10] as $callback ) {
			if ( is_array( $callback['function'] ) && $callback['function'][0] instanceof Mai_Locations_Upgrade ) {
				return $callback['function'][0];
			}
		}

		$this->fail( 'Mai_Locations_Upgrade instance not found.' );
	}
}

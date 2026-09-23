<?php

declare(strict_types=1);

namespace Mai\Locations\Tests\Integration\Registration;

use Mai\Locations\Admin\Upgrade;
use Mai\Locations\Cache;
use Mai\Locations\Tests\TestCase;

/**
 * Upgrades from a site that predates the mai_locations option.
 *
 * A site that started on 0.4.0 or earlier kept its labels and base in ACF option rows and has
 * no version_db, because version_db only arrived in 2023. Until September 23, 2026 the upgrade
 * took such a site for a fresh install and threw its settings away. pbd.heritagewebsites.com is
 * still on 0.4.0 with "Providers" and /providers/, which is what these tests are shaped on.
 *
 * These run in-process, against real options, rather than through the scenario runner, whose
 * frozen get_option() cannot show what an upgrade leaves saved.
 */
final class UpgradeFromOldVersionsTest extends TestCase {

	public function set_up(): void {
		parent::set_up();

		delete_option( 'mai_locations' );
		Cache::flush();
	}

	private function seed_0_4_0_site(): void {
		update_option( 'options_location_label_plural', 'Providers' );
		update_option( '_options_location_label_plural', 'field_abc' );
		update_option( 'options_location_label_singular', 'Provider' );
		update_option( '_options_location_label_singular', 'field_def' );
		update_option( 'options_location_base', 'providers' );
		update_option( '_options_location_base', 'field_ghi' );
	}

	public function test_a_0_4_0_site_keeps_its_labels_and_base(): void {
		$this->seed_0_4_0_site();

		Upgrade::run();

		$saved = get_option( 'mai_locations' );

		$this->assertSame( 'Providers', $saved['label_plural'] );
		$this->assertSame( 'Provider', $saved['label_singular'] );
		$this->assertSame( 'providers', $saved['base'] );
		$this->assertSame( 'providers', mailocations_get_base() );
		$this->assertSame( 'Providers', mailocations_get_plural() );
	}

	public function test_a_0_4_0_site_keeps_owner_publishing(): void {
		$this->seed_0_4_0_site();

		Upgrade::run();

		$this->assertTrue( get_option( 'mai_locations' )['owners_can_publish'] );
		$this->assertSame( MAI_LOCATIONS_VERSION, get_option( 'mai_locations' )['version_db'] );
	}

	public function test_the_old_acf_rows_go_only_once_the_new_values_are_saved(): void {
		$this->seed_0_4_0_site();

		Upgrade::run();

		$this->assertFalse( get_option( 'options_location_label_plural' ) );
		$this->assertFalse( get_option( '_options_location_label_plural' ) );
		$this->assertFalse( get_option( 'options_location_base' ) );
	}

	/**
	 * It used to delete the ACF rows before writing, so a failed write lost the site's settings
	 * for good. Now a write that does not land leaves the old rows exactly where they were.
	 */
	public function test_a_failed_write_keeps_the_old_acf_rows(): void {
		$this->seed_0_4_0_site();
		$block = static fn( $value, $old_value ) => $old_value;
		add_filter( 'pre_update_option_mai_locations', $block, 10, 2 );
		add_filter( 'pre_option_mai_locations', '__return_false' );

		$this->assertFalse( Upgrade::migrate_acf_options() );

		remove_filter( 'pre_update_option_mai_locations', $block, 10 );
		remove_filter( 'pre_option_mai_locations', '__return_false' );

		$this->assertSame( 'Providers', get_option( 'options_location_label_plural' ) );
		$this->assertSame( 'providers', get_option( 'options_location_base' ) );
	}

	/**
	 * A 0.4.0 site that never changed its labels has no ACF rows to give it away, but it does
	 * have locations, and it published on every front-end save, so it keeps that too.
	 */
	public function test_a_pre_version_db_site_with_default_labels_is_still_an_upgrade(): void {
		$this->create_location();

		Upgrade::run();

		$this->assertTrue( get_option( 'mai_locations' )['owners_can_publish'] );
	}

	/**
	 * A fresh install has no ACF rows and no locations, so it moderates by default.
	 */
	public function test_a_fresh_install_moderates_by_default(): void {
		Upgrade::run();

		$this->assertFalse( (bool) mailocations_get_option( 'owners_can_publish' ) );
		$this->assertSame( MAI_LOCATIONS_VERSION, get_option( 'mai_locations' )['version_db'] );
	}

	/**
	 * Safe to repeat: the second run changes nothing, and a site that later unticks the
	 * setting is not switched back on.
	 */
	public function test_running_twice_changes_nothing_and_respects_a_later_untick(): void {
		$this->seed_0_4_0_site();
		Upgrade::run();

		mailocations_update_option( 'owners_can_publish', false );
		$before = get_option( 'mai_locations' );

		Upgrade::run();

		$this->assertSame( $before, get_option( 'mai_locations' ) );
	}

	/**
	 * A 1.x site, which has version_db, keeps its saved values and gains owner publishing.
	 */
	public function test_a_1_x_site_keeps_its_values_and_gains_owner_publishing(): void {
		update_option( 'mai_locations', [ 'label_plural' => 'Places', 'version_first' => '1.0.0', 'version_db' => '1.1.0' ] );
		Cache::flush();

		Upgrade::run();

		$saved = get_option( 'mai_locations' );

		$this->assertSame( 'Places', $saved['label_plural'] );
		$this->assertTrue( $saved['owners_can_publish'] );
		$this->assertSame( MAI_LOCATIONS_VERSION, $saved['version_db'] );
	}
}

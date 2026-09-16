<?php

declare(strict_types=1);

namespace Mai\Locations\Admin;

// Prevent direct file access.
defined( 'ABSPATH' ) || die;

/**
 * Runs version upgrades and migrates the old ACF option values.
 *
 * Was Mai_Locations_Upgrade in classes/class-upgrade.php. That name still works, via
 * inc/aliases.php.
 *
 * TODO: inc/upgrade.php holds a second, procedural copy of these routines, hooked on the same
 * two hooks, so both run. See TODO.md; deleting one copy is still to be agreed.
 *
 * @since TBD
 */
class Upgrade {

	/**
	 * Construct the class.
	 */
	public function __construct() {
		$this->hooks();
	}

	/**
	 * Add hooks.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function hooks(): void {
		add_action( 'admin_init',                [ $this, 'do_upgrade' ] );
		add_action( 'upgrader_process_complete', [ $this, 'upgrade_completed' ], 10, 2 );
	}

	/**
	 * Runs setting upgrades during a plugin update.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	public function do_upgrade(): void {
		$version    = MAI_LOCATIONS_VERSION;
		$version_db = mailocations_get_option( 'version_db' );

		// Set first version.
		if ( ! mailocations_get_option( 'version_first' ) ) {
			mailocations_update_option( 'version_first', $version );
		}

		// Return early if current.
		if ( $version === $version_db ) {
			return;
		}

		// Only run upgrades if we have an existing version.
		if ( $version_db ) {

			// if ( version_compare( $version_db, '0.7.0', '<' ) ) {
			// 	$this->upgrade_0_7_0();
			// }
		}

		// Update database version after upgrade.
		mailocations_update_option( 'version_db', $version );
	}

	/**
	 * Placeholder for the 0.7.0 upgrade.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	public function upgrade_0_7_0(): void {
		// TODO.
	}

	/**
	 * Runs when WordPress finishes its upgrade process, and migrates old ACF option values to
	 * the new key. It checks each updated plugin to see if ours is among them.
	 *
	 * This plugin was not used in many places before the update that added this, so the code is
	 * probably safe to remove once enough time has passed.
	 *
	 * @since TBD
	 *
	 * @param mixed                $upgrader_object The upgrader.
	 * @param array<string, mixed> $options         The upgrade options.
	 *
	 * @return void
	 */
	public function upgrade_completed( $upgrader_object, $options ): void {
		// Bail if we already have an option value.
		if ( get_option( 'mai_locations' ) ) {
			return;
		}

		$values  = [];
		$migrate = [
			'options_location_label_plural'   => 'label_plural',
			'options_location_label_singular' => 'label_singular',
			'options_location_base'           => 'base',
		];

		foreach ( $migrate as $old => $new ) {
			$value = get_option( $old, false );

			if ( ! $value ) {
				continue;
			}

			$values[ $new ] = $value;
			delete_option( $old );
			delete_option( '_' . $old );
		}

		if ( ! $values ) {
			return;
		}

		$options = mailocations_get_options();

		foreach ( $values as $key => $value ) {
			$options[ $key ] = $value;
		}

		update_option( 'mai_locations', $options );
	}
}

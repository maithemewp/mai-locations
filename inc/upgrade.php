<?php

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * The upgrade routines as global functions.
 *
 * These are public names, so they stay. They are no longer hooked, and no longer hold a second
 * copy of the logic: each one calls Mai\Locations\Admin\Upgrade, which is the only copy that
 * WordPress runs. Before September 16, 2026 both copies were hooked on admin_init and
 * upgrader_process_complete, so every upgrade ran twice.
 */

/**
 * Runs setting upgrades during a plugin update.
 *
 * @since TBD
 *
 * @return void
 */
function mailocations_do_upgrade() {
	Mai\Locations\Admin\Upgrade::run();
}

/**
 * Placeholder for the 0.7.0 upgrade.
 *
 * @since TBD
 *
 * @return void
 */
function mailocations_upgrade_0_7_0() {
	// TODO.
}

/**
 * Migrates old ACF option values to the new key when WordPress finishes its upgrade process.
 *
 * @since TBD
 *
 * @param mixed $upgrader_object The upgrader.
 * @param array $options         The upgrade options.
 *
 * @return void
 */
function mailocations_upgrade_completed( $upgrader_object, $options ) {
	Mai\Locations\Admin\Upgrade::run_completed( $upgrader_object, $options );
}

<?php

// Prevent direct file access.
defined( 'ABSPATH' ) || die;

class Mai_Locations_Upgrade {
	/**
	 * Construct the class.
	 */
	function __construct() {
		$this->hooks();
	}

	/**
	 * Add hooks.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	function hooks() {
		add_action( 'admin_init',                [ $this, 'do_upgrade' ] );
		add_action( 'upgrader_process_complete', [ $this, 'upgrade_completed' ], 10, 2 );
	}

	/**
	 * Run setting upgrades during engine update.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	function do_upgrade() {
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

	function upgrade_0_7_0() {
		// TODO.
	}

	/**
	 * This function runs when WordPress completes its upgrade process.
	 * It iterates through each plugin updated to see if ours is included.
	 *
	 * Migrates old ACF option values to the new key.
	 *
	 * This plugin wasn't used in many places prior to the update where this was added,
	 * it's probably okay to remove this code after some time has passed. It has only
	 * been used on our own projects.
	 *
	 * @since TBD
	 *
	 * @param WP_Upgrader $upgrader_object
	 * @param array       $options
	 *
	 * @return void
	 */
	function upgrade_completed( $upgrader_object, $options ) {
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
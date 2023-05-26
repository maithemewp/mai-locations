<?php

add_action( 'upgrader_process_complete', 'mailocations_upgrade_completed', 10, 2 );
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
function mailocations_upgrade_completed( $upgrader_object, $options ) {
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
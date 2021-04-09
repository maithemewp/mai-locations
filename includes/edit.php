<?php

add_action( 'genesis_before', 'mailocations_maybe_show_locations_table' );
function mailocations_maybe_show_locations_table() {
	if ( ! mailocations_is_edit_page() ) {
		return;
	}

	if ( ! mailocation_get_user_locations() ) {
		return;
	}

	$location_id = filter_input( INPUT_GET, 'location_id', FILTER_SANITIZE_NUMBER_INT );

	if ( $location_id && mailocations_user_can_edit( $location_id ) && function_exists( 'advanced_form' ) ) {
		$content = advanced_form( 'form_606e20b2b5271',
			[
				'post'           => $location_id,
				'display_title'  => true,
				'echo'           => false,
				'uploader'       => 'basic',
				'exclude_fields' => [], // TODO:  Get tab fields and remove them.
			]
		);
	} else {
		$content = mailocations_get_locations_table();
	}


	if ( ! $content ) {
		return;
	}

	$is_account = class_exists( 'WooCommerce' ) && is_account_page();
	$hook       = $is_account ? 'woocommerce_before_my_account' : 'genesis_entry_content';
	$priority   = $is_account ? 8 : 10;

	/**
	 * Adds location content to My Account.
	 *
	 * @return void
	 */
	add_action( $hook, function() use ( $content ) {
		echo $content;
	});
}

function mailocations_is_edit_page() {
	static $is_edit_page = null;

	if ( ! is_null( $is_edit_page ) ) {
		return $is_edit_page;
	}

	$is_edit_page = false;

	if ( is_singular() ) {
		$page_ids = get_field( 'location_edit_pages', 'option' );

		if ( $page_ids && in_array( get_the_ID(), $page_ids ) ) {
			$is_edit_page = true;
		}
	}

	return $is_edit_page;
}

function mailocations_user_can_edit( $location_id ) {
	if ( ! is_user_logged_in() ) {
		return;
	}

	$locations = mailocation_get_user_locations();

	if ( ! $locations ) {
		return;
	}

	return in_array( $location_id, $locations );
}

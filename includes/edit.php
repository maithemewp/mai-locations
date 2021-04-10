<?php

// Enqueue CSS files.
add_action( 'wp_enqueue_scripts', 'mailocations_register_scripts' );
function mailocations_register_scripts() {
	if ( ! mailocations_is_edit_page() ) {
		return;
	}

	if ( ! mailocation_get_user_locations() ) {
		return;
	}

	wp_enqueue_style( 'mai-locations', MAI_LOCATIONS_PLUGIN_URL . 'assets/css/mai-locations.css', [], MAI_LOCATIONS_VERSION );
}

add_action( 'get_header', 'mailocations_maybe_show_locations_table', 0 );
function mailocations_maybe_show_locations_table() {
	if ( ! mailocations_is_edit_page() ) {
		return;
	}

	if ( ! mailocation_get_user_locations() ) {
		return;
	}

	acf_form_head();

	$location_id = filter_input( INPUT_GET, 'location_id', FILTER_SANITIZE_NUMBER_INT );

	if ( $location_id && mailocations_user_can_edit( $location_id ) ) {

		ray( 'two' );

		$redirect = filter_input( INPUT_GET, 'redirect', FILTER_SANITIZE_STRING );
		$content  = mailocations_get_location_edit_form( $location_id, $redirect );

		ray( 'three' );

	} else {
		ray( 'four' );
		$content = mailocations_get_locations_table();
	}

	ray( 'five' );

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

function mailocations_get_location_edit_form( $location_id, $redirect ) {
	// $fields = mailocations_get_fields();
	// $tabs   = mailocations_get_fields_tabs();
	// $fields = array_diff( $fields, $tabs );

	$singular = mailocations_get_label_singular();
	$redirect = $redirect ?: get_permalink( get_the_ID() );

	// $filter   = function( $field_group ) {
	// 	vd( $field_group );
	// 	return $field_group;
	// };

	// add_filter( 'acf/load_field_group', $filter );

	$fields = [];
	$groups = acf_get_field_groups( [ 'post_id' => $location_id ] );

	foreach ( $groups as $group ) {
		$group_fields = acf_get_fields( $group['key'] );

		foreach ( $group_fields as $index => $field ) {
			// vd( $field['type'] );
			if ( 'tab' === $field['type'] ) {
				continue;
			}
			// unset( $group_fields[ $index ] );
			$fields[] = $field['key'];
		}

		// $fields = array_merge( $fields, $group_fields );
	}

	ob_start();
	acf_form(
		[
			'id'                 => 'mai-location-edit',
			'post_id'            => $location_id,
			// 'field_groups'    => array( 'group_5d519ec8bcdb7' ),
			'fields'             => $fields,
			'post_title'         => true,
			'post_content'       => false,
			'submit_value'       => sprintf( '%s %s', __( 'Update', 'mai-locations' ), $singular ),
			'return'             => $redirect,
			'updated_message'    => sprintf( __( 'Your %s has been successfully updated.', 'mai-locations' ), strtolower( $singular ) ),
			'uploader'           => 'basic',
			'echo'               => 'false',
			'html_submit_button' => '<input type="submit" class="acf-button button" value="%s" />',
		]
	);

	$form = ob_get_clean();

	// remove_filter( 'acf/load_field_group', $filter );

	return $form;
}

function mailocations_is_edit_page() {
	static $is_edit_page = null;

	if ( ! is_null( $is_edit_page ) ) {
		return $is_edit_page;
	}

	$is_edit_page = is_singular() && get_the_ID() === absint( get_field( 'location_edit_page', 'option' ) );

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

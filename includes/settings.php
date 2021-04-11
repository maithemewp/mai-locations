<?php


add_action( 'acf/init', 'mailocations_add_settings' );
/**
 * Add settings page and fields.
 *
 * @since 0.1.0
 *
 * @return void
 */
function mailocations_add_settings() {
	// Settings Page.
	acf_add_options_sub_page(
		[
			'title'      => __( 'Settings Page', 'mai-locations' ),
			'parent'     => 'edit.php?post_type=mai_location',
			'menu_slug'  => 'location-settings',
			'capability' => 'manage_options'
		]
	);

	// Base instructions.
	$instructions = sprintf( '<a href="%s">%s</a>', get_admin_url( null, 'options-permalink.php' ), __( 'Permalinks', 'mai-locations' ) );
	$instructions = sprintf( __( 'Visit Dashboard > Settings > %s and hit "Save" if updating this setting.', 'mai-locations' ), $instructions );

	// Settings.
	acf_add_local_field_group(
		[
			'key'    => 'group_6070b71fdaf26',
			'title'  => __( 'Mai Locations Settings', 'mai-locations' ),
			'fields' => [
				[
					'key'           => 'field_6070b73a79adf',
					'label'         => __( 'Plural Label', 'mai-locations' ),
					'name'          => 'location_label_plural',
					'type'          => 'text',
					'required'      => 1,
					'default_value' => __( 'Locations', 'mai-locations' ),
					'placeholder'   => __( 'Locations', 'mai-locations' ),
				],
				[
					'key'           => 'field_6070b75c79ae0',
					'label'         => __( 'Singular Label', 'mai-locations' ),
					'name'          => 'location_label_singular',
					'type'          => 'text',
					'required'      => 1,
					'default_value' => __( 'Location', 'mai-locations' ),
					'placeholder'   => __( 'Location', 'mai-locations' ),
				],
				[
					'key'           => 'field_6070b7a379ae1',
					'label'         => __( 'Base URL', 'mai-locations' ),
					'name'          => 'location_base',
					'type'          => 'text',
					'instructions'  => $instructions,
					'required'      => 1,
					'default_value' => 'locations',
					'placeholder'   => 'locations',
				],
			],
			'location' => [
				[
					[
						'param'    => 'options_page',
						'operator' => '==',
						'value'    => 'location-settings',
					],
				],
			],
			'menu_order'  => 10,
			'description' => '',
		]
	);
}

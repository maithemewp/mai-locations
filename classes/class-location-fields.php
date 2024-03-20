<?php

// Prevent direct file access.
defined( 'ABSPATH' ) || die;

class Mai_Locations_Location_Fields {
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
		add_action( 'acf/init',                                    [ $this, 'register_field_groups' ] );
		add_filter( 'acf/load_field/key=mai_location_fields',      [ $this, 'load_location_fields_choices' ] );
		add_filter( 'acf/prepare_field/key=mai_location_lat',      [ $this, 'prepare_location_coordinates_field' ] );
		add_filter( 'acf/prepare_field/key=mai_location_lng',      [ $this, 'prepare_location_coordinates_field' ] );
		add_filter( 'acf/prepare_field/key=mai_location_place_id', [ $this, 'prepare_location_place_id_field' ] );
		add_filter( 'acf/prepare_field/key=mai_location_excerpt',  [ $this, 'prepare_location_exerpt_field' ] );
	}

	/**
	 * Add Location Info and Locations field groups.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	function register_field_groups() {
		$plural   = mailocations_get_plural();
		$singular = mailocations_get_singular();

		// Core fields for posts.
		acf_add_local_field_group(
			[
				'key'        => 'mai_locations_core_field_group',
				'title'      => '',
				'fields'     => [
					[
						'label'    => sprintf( '%s %s', $singular, __( 'Title', 'mai-locations' ) ),
						'key'      => 'mai_location_title',
						'name'     => 'title',
						'type'     => 'text',
						'required' => 1,
					],
					[
						'label'    => __( 'Short Description', 'mai-locations' ),
						'key'      => 'mai_location_excerpt',
						'name'     => 'excerpt',
						'type'     => 'wysiwyg',
						// 'required' => 1,
					],
					[
						'label'         => __( 'Featured Image', 'mai-locations' ),
						'instructions'  => __( 'Only jpeg, jpg, png allowed. 5 MB max.', 'mai-location' ),
						'key'           => 'mai_location_image',
						'type'          => 'image',
						'return_format' => 'id',
						'preview_size'  => 'medium',
						'library'       => 'uploadedTo', // 'all' or 'uploadedTo'. Make sure to check acf_form() for 'uploader' as 'wp' or 'basic'.
					],
					[
						'label'         => __( 'Categories', 'mai-locations'),
						'key'           => 'mai_location_category',
						'name'          => 'category',
						'type'          => 'taxonomy',
						'taxonomy'      => 'mai_location_cat',
						'add_term'      => 0,
						'save_terms'    => 1,
						'load_terms'    => 1,
						'return_format' => 'id',
						'field_type'    => 'checkbox',
						'layout'        => 'horizontal',
						'allow_null'    => 1,
						'multiple'      => 1,
					],
				],
				'menu_order' => 999,
				'location'   => false,
			]
		);

		// Location Info.
		acf_add_local_field_group(
			[
				'key'        => 'mai_locations_location_field_group',
				'title'      => sprintf( '%s %s', $singular, __( 'Info', 'mai-locations' ) ),
				'fields'     => mailocations_get_fields(),
				'menu_order' => 10, // Allow other field groups before or after by setting menu_order.
				'location'   => [
					[
						[
							'param'    => 'post_type',
							'operator' => '==',
							'value'    => 'mai_location',
						],
					],
				],
			]
		);

		// Locations.
		acf_add_local_field_group(
			[
				'key'         => 'mai_locations_user_locations_field_group',
				'title'       => $plural,
				'description' => sprintf( '%s %s', $plural, __( 'Locations this user can manage' ) ),
				'fields'      => [
					[
						'key'           => 'field_606f28c86abee',
						'label'         => 'Locations',
						'name'          => 'user_locations',
						'type'          => 'post_object',
						'post_type'     => [
							'mai_location',
						],
						'allow_null'    => 1,
						'multiple'      => 1,
						'ui'            => 1,
						'return_format' => 'object',
					],
				],
				'location' => [
					[
						[
							'param'    => 'user_form',
							'operator' => '==',
							'value'    => 'edit',
						],
						[
							'param'    => 'current_user_role',
							'operator' => '==',
							'value'    => 'administrator',
						],
					],
				],
			]
		);
	}

	/**
	 * Make sure the field choices are in the correct order, based on existing values.
	 *
	 * @since TBD
	 *
	 * @param array $field The field data.
	 *
	 * @return array
	 */
	function load_location_fields_choices( $field ) {
		// Get currently selected fields, so they are first. Combine so we can use the keys as values.
		$field['choices'] = array_combine( (array) $field['value'], (array) $field['value'] );

		// Get all register fields.
		$group_fields = array_merge( acf_get_fields( 'mai_locations_core_field_group' ), acf_get_fields( 'mai_locations_location_field_group' ) );

		// Set choices.
		foreach ( $group_fields as $group_field ) {
			// Skip tabs.
			if ( 'tab' === $group_field['type'] ) {
				continue;
			}

			// Adds as new choice or overrides existing and adds label.
			$field['choices'][ $group_field['key'] ] = $group_field['label'];
		}

		// Remove disabled.
		unset( $field['choices']['mai_location_lat'] );
		unset( $field['choices']['mai_location_lng'] );
		unset( $field['choices']['mai_location_place_id'] );

		// Remove empty choices.
		$field['choices'] = array_filter( $field['choices'] );

		// Set basic defaults.
		$field['default_value'] = [
			'mai_location_title',
			'mai_location_excerpt',
			'mai_location_location',
		];

		return $field;
	}

	/**
	 * Disables the location coordinates fields.
	 * If not disabled, the fields will overwrite `update_lat_lng_value()`.
	 *
	 * @since TBD
	 *
	 * @param $field array The field array containing all settings.
	 *
	 * @return array|false
	 */
	function prepare_location_coordinates_field( $field ) {
		$field['disabled'] = 'disabled';

		return $field;
	}

	/**
	 * Disables the location place ID field.
	 *
	 * @since TBD
	 *
	 * @param $field array The field array containing all settings.
	 *
	 * @return array|false
	 */
	function prepare_location_place_id_field( $field ) {
		$field['disabled'] = 'disabled';

		return $field;
	}

	/**
	 * Disabled the visual tab, media upload, and use basic toolbar.
	 *
	 * @since TBD
	 *
	 * @param array $field
	 *
	 * @return array
	 */
	function prepare_location_exerpt_field( $field ) {
		$field['tabs']         = 'visual';
		$field['toolbar']      = 'basic';
		$field['media_upload'] = 0;

		return $field;
	}
}

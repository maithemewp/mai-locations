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
		add_action( 'acf/init',                                                  [ $this, 'register_field_groups' ] );
		add_filter( 'acf/location/rule_match/mailocations_supported_post_types', [ $this, 'post_type_rule_match' ], 10, 4 );
		add_filter( 'acf/load_field_group',                                      [ $this, 'handle_field_group_titles' ] );
		add_filter( 'acf/prepare_field/key=mai_location_fields',                 [ $this, 'load_location_fields_choices' ] );
		// add_filter( 'acf/prepare_field/key=TBD',                                 [ $this, 'prepare_labels' ] );
		add_filter( 'acf/prepare_field/key=mai_location_lat',                    [ $this, 'prepare_location_coordinates_field' ] );
		add_filter( 'acf/prepare_field/key=mai_location_lng',                    [ $this, 'prepare_location_coordinates_field' ] );
		add_filter( 'acf/prepare_field/key=mai_location_place_id',               [ $this, 'prepare_location_place_id_field' ] );
		add_filter( 'acf/prepare_field/key=mai_location_excerpt',                [ $this, 'prepare_location_exerpt_field' ] );
	}

	/**
	 * Add Location Info and Locations field groups.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	function register_field_groups() {
		/**
		 * All location post type fields.
		 * These don't show in the post editor in the back end.
		 * They are used for the front end submission/edit forms,
		 * if selected via the Mai Locations Table and Mai Locations Submission blocks.
		 */
		acf_add_local_field_group(
			[
				'key'        => 'mai_locations_core_field_group',
				'title'      => '',
				'fields'     => [
					[
						'label'    => __( 'Title', 'mai-locations' ),
						'key'      => 'mai_location_title',
						'name'     => 'title',
						'type'     => 'text',
						'required' => 1,
					],
					[
						'label'    => __( 'Description', 'mai-locations' ),
						'key'      => 'mai_location_excerpt',
						'name'     => 'excerpt',
						'type'     => 'wysiwyg',
						// 'required' => 1,
					],
					[
						'label'         => __( 'Image', 'mai-locations' ),
						'instructions'  => __( 'Only jpeg, jpg, png allowed. 5 MB max.', 'mai-location' ),
						'key'           => 'mai_location_image',
						'type'          => 'image',
						'return_format' => 'id',
						'preview_size'  => 'medium',
						'library'       => 'uploadedTo', // 'all' or 'uploadedTo'. Make sure to check acf_form() for 'uploader' as 'wp' or 'basic'.
					],
					// [
					// 	'label'         => __( 'Categories', 'mai-locations'),
					// 	'key'           => 'mai_location_category',
					// 	'name'          => 'category',
					// 	'type'          => 'taxonomy',
					// 	'taxonomy'      => 'mai_location_cat',
					// 	'add_term'      => 0,
					// 	'save_terms'    => 1,
					// 	'load_terms'    => 1,
					// 	'return_format' => 'id',
					// 	'field_type'    => 'checkbox',
					// 	'layout'        => 'horizontal',
					// 	'allow_null'    => 1,
					// 	'multiple'      => 1,
					// ],
				],
				'menu_order' => 999,
				'location'   => false,
			]
		);

		// Get location post types.
		$post_types = mailocations_get_location_post_types();

		// Loop through post types to create core field groups.
		foreach ( $post_types as $post_type => $labels ) {
			// Start fields.
			$fields = [];

			// Get post type taxonomies.
			$taxos = mailocations_get_location_taxonomies( $post_type );

			// Loop through taxonomies.
			foreach ( $taxos as $name => $label ) {
				// Add taxonomy field.
				$fields[] = [
					'label'         => $label,
					'key'           => $name,
					'name'          => $name,
					'type'          => 'taxonomy',
					'taxonomy'      => $name,
					'add_term'      => 0,
					'save_terms'    => 1,
					'load_terms'    => 1,
					'return_format' => 'id',
					'field_type'    => 'checkbox',
					'layout'        => 'horizontal',
					'allow_null'    => 1,
					'multiple'      => 1,
				];
			}

			// Allow adding fields.
			$fields = apply_filters( "mai_locations_core_{$post_type}_fields", $fields );

			// Bail if no fields.
			if ( ! $fields ) {
				continue;
			}

			/**
			 * Post type specific fields.
			 * These don't show in the post editor in the back end.
			 * They are used for the front end submission/edit forms,
			 * if selected via the Mai Locations Table and Mai Locations Submission blocks.
			 */
			acf_add_local_field_group(
				[
					'key'      => "mai_locations_core_{$post_type}_field_group",
					'title'    => '',
					'fields'   => $fields,
					'location' => false,
				]
			);
		}

		// Loop through post types to create visible field groups.
		foreach ( $post_types as $post_type => $labels ) {
			// Start fields.
			$fields = [];

			// Allow adding fields.
			$fields = apply_filters( "mai_locations_{$post_type}_fields", $fields );

			// Bail if no fields.
			if ( ! $fields ) {
				continue;
			}

			/**
			 * Post type specific fields.
			 * These are public and will show in the post editor in the back end.
			 * as well as the front end submission/edit forms, if selected via the Mai Locations Table and Mai Locations Submission blocks.
			 */
			acf_add_local_field_group(
				[
					'key'      => "mai_locations_{$post_type}_field_group",
					'title'    => $labels['plural'],
					'fields'   => $fields,
					'location' => [
						[
							[
								'param'    => 'post_type',
								'operator' => '==',
								'value'    => $post_type,
							],
						],
					],
				]
			);
		}

		/**
		 * Location Info.
		 * This is the main field group that will show on all supported location post types.
		 */
		acf_add_local_field_group(
			[
				'key'        => 'mai_locations_location_field_group',
				'title'      => sprintf( '{SINGULAR} %s', __( 'Info', 'mai-locations' ) ),
				'fields'     => mailocations_get_fields(),
				'menu_order' => 10, // Allow other field groups before or after by setting menu_order.
				'location'  => [
					[
						[
							'param'    => 'mailocations_supported_post_types',
							'operator' => '==', // Currently unused.
							'value'    => true, // Currently unused.
						],
					],
				],
			]
		);

		// // Locations.
		// acf_add_local_field_group(
		// 	[
		// 		'key'         => 'mai_locations_user_locations_field_group',
		// 		'title'       => $plural,
		// 		'description' => sprintf( '%s %s', $plural, __( 'Locations this user can manage' ) ),
		// 		'fields'      => [
		// 			[
		// 				'key'           => 'field_606f28c86abee',
		// 				'label'         => 'Locations',
		// 				'name'          => 'user_locations',
		// 				'type'          => 'post_object',
		// 				'post_type'     => [
		// 					'mai_location',
		// 				],
		// 				'allow_null'    => 1,
		// 				'multiple'      => 1,
		// 				'ui'            => 1,
		// 				'return_format' => 'object',
		// 			],
		// 		],
		// 		'location' => [
		// 			[
		// 				[
		// 					'param'    => 'user_form',
		// 					'operator' => '==',
		// 					'value'    => 'edit',
		// 				],
		// 				[
		// 					'param'    => 'current_user_role',
		// 					'operator' => '==',
		// 					'value'    => 'administrator',
		// 				],
		// 			],
		// 		],
		// 	]
		// );
	}

	/**
	 * Shows location info metabox on supported post types.
	 *
	 * @since TBD
	 *
	 * @param bool      $result Whether the rule matches.
	 * @param array     $rule   Current rule to match (param, operator, value).
	 * @param WP_Screen $screen The current screen.
	 *
	 * @return bool
	 */
	function post_type_rule_match( $result, $rule, $screen, $field_group ) {
		$post_types = mailocations_get_location_post_types();

		return $post_types && isset( $screen['post_type'] ) && isset( $post_types[ $screen['post_type'] ] );
	}

	/**
	 * Change the field group title based on the post type.
	 *
	 * @since TBD
	 *
	 * @param array $field_group The field group data.
	 *
	 * @return array
	 */
	function handle_field_group_titles( $field_group ) {
		// If not the field groups we want.
		if ( ! in_array( $field_group['key'], [ 'mai_locations_core_field_group', 'mai_locations_location_field_group' ] ) ) {
			return $field_group;
		}

		// Get current post type.
		$post_type = get_post_type();

		// Bail if no post type.
		if ( ! $post_type ) {
			return $field_group;
		}

		// Get labels.
		$singular = mailocations_get_singular_label( $post_type );
		$plural   = mailocations_get_plural_label( $post_type );

		// Bail if no labels.
		if ( ! ( $singular && $plural ) ) {
			return $field_group;
		}

		// Replace the placeholder.
		$field_group['title'] = str_replace( '{SINGULAR}', $singular, $field_group['title'] );
		$field_group['title'] = str_replace( '{PLURAL}', $plural, $field_group['title'] );

		return $field_group;
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
		if ( ! is_admin() ) {
			return $field;
		}

		// Get currently selected fields, so they are first. Combine so we can use the keys as values.
		$field['choices'] = array_combine( (array) $field['value'], (array) $field['value'] );

		// Get core fields and post types.
		$group_fields = mailocations_get_field_group_fields();

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

	// /**
	//  * Replace placeholders in field labels.
	//  *
	//  * @since TBD
	//  *
	//  * @param array $field The field data.
	//  *
	//  * @return array
	//  */
	// function prepare_labels( $field ) {
	// 	$post_type = get_post_type();
	// 	$singular  = mailocations_get_singular_label( $post_type );
	// 	$plural    = mailocations_get_plural_label( $post_type );

	// 	// Bail if no labels.
	// 	if ( ! ( $singular && $plural ) ) {
	// 		return $field;
	// 	}

	// 	// Replace placeholders in field label.
	// 	$field['label'] = str_replace( '{SINGULAR}', $singular, $field['label'] );
	// 	$field['label'] = str_replace( '{PLURAL}', $plural, $field['label'] );

	// 	return $field;
	// }

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

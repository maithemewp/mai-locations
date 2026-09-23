<?php

declare(strict_types=1);

namespace Mai\Locations\Fields;

// Prevent direct file access.
defined( 'ABSPATH' ) || die;

/**
 * Registers the ACF field groups for locations, and prepares individual fields.
 *
 * Was Mai_Locations_Location_Fields in classes/class-location-fields.php. That name still
 * works, via inc/aliases.php.
 *
 * @since TBD
 */
class LocationFields {

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
		add_action( 'acf/init',                                                  [ $this, 'register_field_groups' ] );
		add_filter( 'acf/location/rule_match/mailocations_supported_post_types', [ $this, 'post_type_rule_match' ], 10, 4 );
		add_filter( 'acf/load_field_group',                                      [ $this, 'handle_field_group_titles' ] );
		add_filter( 'acf/prepare_field/key=mai_location_fields',                 [ $this, 'load_location_fields_choices' ] );
		add_filter( 'acf/prepare_field/key=mailocations_table_fields',           [ $this, 'load_location_fields_choices' ] );
		// add_filter( 'acf/prepare_field/key=TBD',                                 [ $this, 'prepare_labels' ] );
		add_filter( 'acf/prepare_field/key=mai_location_lat',                    [ $this, 'prepare_location_coordinates_field' ] );
		add_filter( 'acf/prepare_field/key=mai_location_lng',                    [ $this, 'prepare_location_coordinates_field' ] );
		add_filter( 'acf/prepare_field/key=mai_location_place_id',               [ $this, 'prepare_location_place_id_field' ] );
		add_filter( 'acf/prepare_field/key=mai_location_excerpt',                [ $this, 'prepare_location_excerpt_field' ] );
	}

	/**
	 * Adds the Location Info and Locations field groups.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function register_field_groups(): void {
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
						// TODO: no 'name', so update_field() on this key writes nothing, and the
						// text domain reads mai-location. See TODO.md.
						'label'         => __( 'Image', 'mai-locations' ),
						'instructions'  => __( 'Only jpeg, jpg, png allowed. 5 MB max.', 'mai-locations' ),
						'key'           => 'mai_location_image',
						'type'          => 'image',
						'return_format' => 'id',
						'preview_size'  => 'medium',
						'library'       => 'uploadedTo', // 'all' or 'uploadedTo'. Make sure to check acf_form() for 'uploader' as 'wp' or 'basic'.
					],
					[
						// A pseudo-field. The form listener reads it out of $_POST and promotes the
						// post status; nothing is ever saved as meta. LocationFormEdit adds it to the
						// form itself, so a site never picks it and never has to.
						// The switch text carries the meaning, because ACF's default reads Yes / No,
						// which says nothing about what happens on save. "Not yet" rather than
						// "Keep as draft", since the location may be pending review instead.
						'label'       => __( 'Publish', 'mai-locations' ),
						'key'         => 'mai_location_publish',
						'name'        => 'publish',
						'type'        => 'true_false',
						'ui'          => 1,
						'ui_on_text'  => __( 'Publish', 'mai-locations' ),
						'ui_off_text' => __( 'Not yet', 'mai-locations' ),
						'message'     => __( 'Makes this visible to everyone.', 'mai-locations' ),
					],
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
			 * These are public and will show in the post editor in the back end,
			 * as well as the front end submission/edit forms, if selected via the Mai Locations
			 * Table and Mai Locations Submission blocks.
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
	}

	/**
	 * Shows the location info metabox on supported post types.
	 *
	 * ACF passes the screen args as an array, not a WP_Screen, whatever the old docblock said.
	 *
	 * TODO: ignores the incoming result, the operator and the value. See TODO.md.
	 *
	 * @since TBD
	 *
	 * @param bool                 $result      Whether the rule matches.
	 * @param array<string, mixed> $rule        Current rule to match (param, operator, value).
	 * @param array<string, mixed> $screen      The current screen args.
	 * @param array<string, mixed> $field_group The field group.
	 *
	 * @return bool
	 */
	public function post_type_rule_match( $result, $rule, $screen, $field_group ) {
		$post_types = mailocations_get_location_post_types();

		return $post_types && isset( $screen['post_type'] ) && isset( $post_types[ $screen['post_type'] ] );
	}

	/**
	 * Changes the field group title based on the post type.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $field_group The field group data.
	 *
	 * @return array<string, mixed>
	 */
	public function handle_field_group_titles( $field_group ) {
		// If not the field groups we want.
		if ( ! in_array( $field_group['key'], [ 'mai_locations_core_field_group', 'mai_locations_location_field_group' ] ) ) {
			return $field_group;
		}

		// Get current post type.
		$post_type = get_post_type();

		// Get labels for the post type being edited, falling back to the plugin's own. ACF loads
		// and caches the group before any post exists, and this used to bail there and leave the
		// raw {SINGULAR} on screen. Fixed September 16, 2026.
		$singular = $post_type ? mailocations_get_singular_label( $post_type ) : '';
		$plural   = $post_type ? mailocations_get_plural_label( $post_type ) : '';
		$singular = $singular ?: mailocations_get_singular();
		$plural   = $plural ?: mailocations_get_plural();

		// Replace the placeholder.
		$field_group['title'] = str_replace( '{SINGULAR}', $singular, $field_group['title'] );
		$field_group['title'] = str_replace( '{PLURAL}', $plural, $field_group['title'] );

		return $field_group;
	}

	/**
	 * Keeps the field choices in the right order, based on existing values.
	 *
	 * TODO: a saved key that no longer exists stays in the list with the key as its label.
	 * See TODO.md.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $field The field data.
	 *
	 * @return array<string, mixed>
	 */
	public function load_location_fields_choices( $field ) {
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
		unset( $field['choices']['mai_location_publish'] );

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
	 * Disables the location coordinates fields. If they stay enabled they overwrite the values
	 * saved from the map field.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $field The field array containing all settings.
	 *
	 * @return array<string, mixed>
	 */
	public function prepare_location_coordinates_field( $field ) {
		$field['disabled'] = 'disabled';

		return $field;
	}

	/**
	 * Disables the location place ID field.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $field The field array containing all settings.
	 *
	 * @return array<string, mixed>
	 */
	public function prepare_location_place_id_field( $field ) {
		$field['disabled'] = 'disabled';

		return $field;
	}

	/**
	 * Sets the excerpt editor to the Visual tab only, with the basic toolbar and no media
	 * upload.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $field The field array.
	 *
	 * @return array<string, mixed>
	 */
	public function prepare_location_excerpt_field( $field ) {
		$field['tabs']         = 'visual';
		$field['toolbar']      = 'basic';
		$field['media_upload'] = 0;

		return $field;
	}

	/**
	 * The old, misspelled name of prepare_location_excerpt_field().
	 *
	 * Renamed September 16, 2026. This stays because the class is public.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $field The field array.
	 *
	 * @return array<string, mixed>
	 */
	public function prepare_location_exerpt_field( $field ) {
		return $this->prepare_location_excerpt_field( $field );
	}
}

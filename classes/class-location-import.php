<?php

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

class Mai_Locations_Location_Import {
	protected $file_id;
	protected $post_status;
	protected $create_users;
	protected $user_role;
	protected $csv;

	/**
	 * Construct the class.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	function __construct() {
		$this->hooks();
	}

	/**
	 * Run hooks.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	function hooks() {
		add_action( 'acf/init',                                                  [ $this, 'register_page' ], 12 );
		add_action( 'acf/init',                                                  [ $this, 'register_fields' ] );
		add_filter( 'acf/load_field/key=mailocations_location_import_user_role', [ $this, 'load_roles' ] );
		add_filter( 'acf/load_field/key=mailocations_location_import_status',    [ $this, 'load_statuses' ] );
		add_action( 'mai_location_page_location-import',                         [ $this, 'confirmation' ], 10 );
		add_action( 'acf/save_post',                                             [ $this, 'maybe_import_locations' ], 4 );
	}

	/**
	 * Registers settings page.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	function register_page() {
		if ( ! function_exists( 'acf_add_options_sub_page' ) ) {
			return;
		}

		acf_add_options_sub_page(
			[
				'title'           => __( 'Location Import', 'mai-locations' ),
				'parent'          => 'edit.php?post_type=mai_location',
				'menu_slug'       => 'location-import',
				'capability'      => 'manage_options',
				'update_button'   => __( 'Import Now', 'mai-locations' ),
				'updated_message' => __( 'Imported', 'mai-locations' ),
			]
		);
	}

	/**
	 * Registers fields.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	function register_fields() {
		if ( ! function_exists( 'acf_add_options_sub_page' ) ) {
			return;
		}

		// Example download link.
		$download_link = sprintf( '<a href="%sassets/csv/mai-locations-import-template.csv" target="_blank" download">%s</a>', MAI_LOCATIONS_PLUGIN_URL, __( 'Download example CSV file', 'mai-locations' ) );

		acf_add_local_field_group(
			[
				'key'    => 'mailocations_location_import_field_group',
				'title'  => 'Location Import',
				'fields' => [
					[
						'key'       => 'mailocations_import_description',
						'label'     => '',
						'message'   => sprintf( '<p>%s</p><p>%s.</p>', __( 'Import locations and optionally create users. Locations with an identical name (case-sensitive post title) will be skipped, all others will be imported. Add additional simple (text, number, etc.) post_meta data can be added to the CSV with the header as the meta key. Additional meta must have an ACF field registered in PHP via the filters available in Mai Locations.', 'mai-locations' ), $download_link ),
						'type'      => 'message',
						'new_lines' => '',
						'esc_html'  => 0,
					],
					[
						'key'           => 'mailocations_import_file',
						'label'         => __( 'File (.csv]', 'mai-locations' ),
						'name'          => 'location_import_file',
						'type'          => 'file',
						'required'      => 1,
						'return_format' => 'id',
						'library'       => 'all',
						'mime_types'    => 'csv',
					],
					[
						'key'               => 'mailocations_location_import_status',
						'label'             => 'Status',
						'name'              => 'location_status',
						'type'              => 'radio',
						'required'          => 1,
						'allow_null'        => 0,
						'other_choice'      => 0,
						'default_value'     => 'publish',
						'return_format'     => 'value',
						'save_other_choice' => 0,
					],
					[
						'key'           => 'mailocations_location_import_users',
						'label'         => __( 'Create/Update Users', 'mai-locations' ),
						'instructions'  => __( 'Create a user account to manage locations(s). If a user account exists with the same email, that user will be able to manage the new location, no other user data will be updated.', 'mai-locations' ),
						'message'       => __( 'Create or update existing user accounts', 'mai-locations' ),
						'name'          => 'location_users',
						'type'          => 'true_false',
						'default_value' => 0,
						'ui'            => 1,
						'ui_on_text'    => __( 'Yes', 'mai-locations' ),
						'ui_off_text'   => __( 'No', 'mai-locations' ),
					],
					[
						'key'               => 'mailocations_location_import_user_role',
						'label'             => __( 'User Role', 'mai-locations' ),
						'instructions'      => __( 'The role for newly created users.', 'mai-locations' ),
						'name'              => 'location_user_role',
						'type'              => 'radio',
						'conditional_logic' => [
							[
								[
									'field'    => 'mailocations_location_import_users',
									'operator' => '==',
									'value'    => '1',
								],
							],
						],
						'allow_null'        => 1,
						'other_choice'      => 0,
						'default_value'     => 'subscriber',
						'return_format'     => 'value',
						'save_other_choice' => 0,
					],
				],
				'location' => [
					[
						[
							'param'    => 'options_page',
							'operator' => '==',
							'value'    => 'location-import',
						],
					],
				],
			],
		);
	}

	/**
	 * Loads roles as field choices.
	 *
	 * @since TBD
	 *
	 * @param array $field The field data.
	 *
	 * @return array
	 */
	function load_roles( $field ) {
		$field['choices'] = [];
		$roles            = array_reverse( wp_roles()->roles );

		foreach ( $roles as $role => $details ) {
			// Skip admins.
			if ( 'administrator' === $role ) {
				continue;
			}

			// Get translated name.
			$field['choices'][ $role ] = translate_user_role( $details['name'] );

			// If subscriber, set as default.
			if ( 'subscriber' === $role ) {
				$field['default_value'] = $role;
			}
		}

		return $field;
	}

	/**
	 * Loads post statuses as field choices.
	 *
	 * @since TBD
	 *
	 * @param array $field The field data.
	 *
	 * @return array
	 */
	function load_statuses( $field ) {
		$field['choices']       = get_post_statuses();
		$field['default_value'] = 'publish';

		return $field;
	}

	/**
	 * Displays confirmation notice.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	function confirmation() {
		// Bail if no confirmation.
		if ( ! filter_input( INPUT_GET, 'confirmation', FILTER_VALIDATE_INT ) ) {
			return;
		}

		// Set results.
		$results = [
			esc_html__( 'Locations imported', 'mai-location' ) => filter_input( INPUT_GET, 'imported', FILTER_VALIDATE_INT ),
			esc_html__( 'Locations skipped', 'mai-location' )  => filter_input( INPUT_GET, 'skipped', FILTER_VALIDATE_INT ),
			esc_html__( 'Locations failed', 'mai-location' )   => filter_input( INPUT_GET, 'failed', FILTER_VALIDATE_INT ),
			esc_html__( 'Users imported', 'mai-location' )     => filter_input( INPUT_GET, 'users_imported', FILTER_VALIDATE_INT ),
			esc_html__( 'Users skipped', 'mai-location' )      => filter_input( INPUT_GET, 'users_skipped', FILTER_VALIDATE_INT ),
			esc_html__( 'Users failed', 'mai-location' )       => filter_input( INPUT_GET, 'users_failed', FILTER_VALIDATE_INT ),
		];

		// Display notice.
		echo '<div class="notice notice-info is-dismissible">';
			foreach ( $results as $text => $count ) {
				printf( '<p>%s: %s</p>', $text, $count );
			}
		echo '</div>';
	}

	/**
	 * Maybe imports locations and creates associtated users.
	 *
	 * @since TBD
	 *
	 * @param string|int $post_id The referenced ACF post, options page, etc.
	 *
	 * @return void
	 */
	function maybe_import_locations( $post_id ) {
		// Bail if no data.
		if ( ! isset( $_POST['acf'] ) || empty( $_POST['acf'] ) ) {
			return;
		}

		// Bail if not saving an options page.
		if ( 'options' !== $post_id ) {
			return;
		}

		// Current screen.
		$screen = get_current_screen();

		// Bail if not our options page.
		if ( ! $screen || ! str_contains( $screen->id, 'mai_location_page_location-import' ) ) {
			return;
		}

		// Store the submitted data.
		$this->file_id      = isset( $_POST['acf']['mailocations_import_file'] ) ? absint( $_POST['acf']['mailocations_import_file'] ) : false;
		$this->post_status  = isset( $_POST['acf']['mailocations_location_import_status'] ) ? esc_html( $_POST['acf']['mailocations_location_import_status'] ) : 'public';
		$this->create_users = isset( $_POST['acf']['mailocations_location_import_users'] ) ? absint( $_POST['acf']['mailocations_location_import_users'] ) : false;
		$this->user_role    = isset( $_POST['acf']['mailocations_location_import_user_role'] ) ? esc_html( $_POST['acf']['mailocations_location_import_user_role'] ) : 'subscriber';

		// Remove data so it's not saved in the DB.
		unset( $_POST['acf'] );

		// Bail if no file.
		if ( ! $this->file_id ) {
			return;
		}

		// Get file path.
		$file_path = get_attached_file( $this->file_id );

		// Bail if no file.
		if ( ! $file_path ) {
			return;
		}

		// Load csv into array.
		$csv = array_map( 'str_getcsv', file( $file_path ) );

		// Map header values as each item key.
		array_walk( $csv, function( &$a ) use ( $csv ) {
			$a = array_combine( $csv[0], $a );
		});

		// Remove column header.
		array_shift( $csv );

		// Set csv property.
		$this->csv = $csv;

		// Import.
		$this->import();
	}

	/**
	 * Imports locations and creates associtated users.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	function import() {
		$users_imported = [];
		$users_skipped  = [];
		$users_failed   = [];
		$imported       = [];
		$skipped        = [];
		$failed         = [];
		$fields         = $this->get_fields();

		foreach ( $this->csv as $index => $location ) {
			$user_id    = 0;
			$first_name = $last_name = $email = '';

			// If creating users.
			if ( $this->create_users ) {
				if ( isset( $location['user_first_name'] ) ) {
					$first_name = $location['user_first_name'];
				}

				if ( isset( $location['user_last_name'] ) ) {
					$last_name = $location['user_last_name'];
				}

				if ( isset( $location['user_email'] ) ) {
					$email = $location['user_email'];
				}

				// If email.
				if ( $email ) {
					// Check for existing.
					$existing = get_user_by( 'email', $email );
					$existing = $existing ?: get_user_by( 'login', $email );
					$user_id  = $existing ? $existing->ID : false;

					// If no existing user.
					if ( ! $user_id ) {
						// Create user.
						$user_id = wp_insert_user(
							[
								'user_login'   => $email,
								'user_email'   => $email,
								'display_name' => trim( $first_name . ' ' . $last_name ),
								'nickname'     => trim( $first_name . ' ' . $last_name ),
								'first_name'   => $first_name,
								'last_name'    => $last_name,
								'role'         => $this->user_role,
							]
						);

						// Failed.
						if ( is_wp_error( $user_id ) ) {
							$users_failed[] = $user_id->get_error_message();
							$user_id        = 0;
						}
						// Imported.
						else {
							$users_imported[] = $user_id;
						}
					}
					// Skipped.
					else {
						$users_skipped[] = $user_id;
					}
				} // If email.
			} // If creating users.

			// Remove user data.
			unset( $location['user_first_name'] );
			unset( $location['user_last_name'] );
			unset( $location['user_email'] );

			// Set title and remove from data.
			if ( isset( $location['location_name'] ) ) {
				$post_title = trim( $location['location_name'] );
				unset( $location['location_name'] );
			} else {
				$post_title = '';
			}

			// Set content and remove from data.
			if ( isset( $location['location_description'] ) ) {
				$post_content = trim( $location['location_description'] );
				unset( $location['location_description'] );
			} else {
				$post_content = '';
			}

			// Set location cat and remove from data.
			if ( isset( $location['location_cat'] ) ) {
				$location_cats = explode( ',', $location['location_cat'] );
				$location_cats = array_filter( $location_cats );
				$location_cats = array_map( 'trim', $location_cats );
				unset( $location['location_cat'] );
			} else {
				$location_cats = [];
			}

			// Set post args.
			$location_args = [
				'post_type'    => 'mai_location',
				'post_status'  => $this->post_status,
				'post_title'   => $post_title,
				'post_content' => $post_content,
			];

			// Set categories.
			if ( $location_cats ) {
				$term_ids = [];

				foreach ( $location_cats as $location_cat ) {
					$term = get_term_by( 'name', $location_cat, 'mai_location_cat' );

					if ( $term ) {
						$term_ids[] = $term->term_id;
					}
				}

				if ( $term_ids ) {
					$location_args['tax_input'] = [
						'mai_location_cat' => $term_ids,
					];
				}
			}

			// Set meta.
			$meta_args = [];

			foreach ( $location as $key => $value ) {
				// Skip meta that is not registered in ACF via Mai Locations PHP filters.
				if ( ! isset( $fields[ $key ] ) ) {
					continue;
				}

				$esc          = isset( $allowed[ $key ] ) && is_callable( $allowed[ $key ] ) ? $allowed[ $key ] : 'esc_html';
				$meta_args[ $key ] = $esc( $value );
			}

			// If we have a post title.
			if ( $post_title ) {
				$existing = get_page_by_title( $post_title, OBJECT, 'mai_location' );

				if ( $existing ) {
					// Add location to user.
					if ( $user_id ) {
						mailocations_add_location_to_user( $existing->ID, $user_id );
					}

					// Skipped, existing.
					$skipped[] = $existing->ID;

				} else {
					// Create location, add to user.
					$location_id = mailocations_create_location( $location_args, $meta_args, $user_id );

					// Bail if failed.
					if ( is_wp_error( $location_id ) ) {
						$failed[] = $location_id->get_error_message();
					}
					// Imported.
					else {
						$imported[] = $location_args;
					}
				}
			}
			// Skipped, no post title.
			else {
				$skipped[] = $index + 1; // Row number, I think.
			}
		}

		// Handle Confimration and redirect.
		$redirect = add_query_arg(
			[
				'confirmation'   => 1,
				'imported'       => count( $imported ),
				'skipped'        => count( $skipped ),
				'failed'         => count( $failed ),
				'users_imported' => count( $users_imported ),
				'users_skipped'  => count( $users_skipped ),
				'users_failed'   => count( $users_failed ),
			],
			admin_url( 'edit.php?post_type=mai_location&page=location-import' )
		);

		// Redirect.
		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Gets allowed fields for import, with sanitization type.
	 *
	 * @since TBD
	 *
	 * @return array
	 */
	function get_fields() {
		$fields  = mailocations_get_fields_raw();
		$allowed = [
			'email'      => 'sanitize_email',
			'number'     => 'intval',
			'radio'      => 'esc_html',
			'select'     => 'esc_html',
			'text'       => 'esc_html',
			'textarea'   => 'wp_kses_post',
			'true_false' => 'rest_sanitize_boolean',
			'url'        => 'esc_url',
		];

		foreach ( $fields as $key => $field ) {
			if ( ! isset( $field['type'] ) ) {
				unset( $fields[ $key ] );
			}

			if ( ! isset( $allowed[ $field['type'] ] ) ) {
				unset( $fields[ $key ] );
			}
		}

		return $fields;
	}
}

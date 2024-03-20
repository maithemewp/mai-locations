<?php

// Prevent direct file access.
defined( 'ABSPATH' ) || die;

class Mai_Locations_Location_Form_Listener {
	/**
	 * Construct the class.
	 */
	function __construct() {
		$this->hooks();
	}

	/**
	 * Add hooks.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	function hooks() {
		add_action( 'get_header',                                 [ $this, 'create_listener' ], 0 );
		add_action( 'get_header',                                 [ $this, 'edit_listener' ], 0 );
		add_action( 'acf/save_post',                              [ $this, 'before_save_post' ], 4 );
		add_action( 'acf/update_value/key=mai_location_location', [ $this, 'update_lat_lng_place_id_value' ], 10, 4 );
		add_action( 'pending_to_publish',                         [ $this, 'send_published_email' ] );
	}

	/**
	 * Processes create form submission.
	 * Adds acf_form_head().
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	function create_listener() {
		// Bail if not logged in or not a single post.
		if ( ! ( is_user_logged_in() && is_singular() ) ) {
			return;
		}

		// Check if has create location block.
		$has_block = has_blocks() && has_block( 'acf/mai-location-submission' );

		// Bail if no block.
		if ( ! $has_block ) {
			return;
		}

		// Add form head.
		acf_form_head();
	}

	/**
	 * Processes edit form submission.
	 * Adds acf_form_head().
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	function edit_listener() {
		// Bail if not logged in or not a single post.
		if ( ! ( is_user_logged_in() && is_singular() ) ) {
			return;
		}

		// Bail if no locations.
		if ( ! mailocation_get_user_locations() ) {
			return;
		}

		// Check if has locations table block or shortcode or is Woo account page.
		$has_block     = has_blocks() && has_block( 'acf/mai-locations-table' );
		$has_shortcode = has_shortcode( get_post_field( 'post_content', get_the_ID() ), 'mai_locations_table' );
		$is_account    = class_exists( 'WooCommerce' ) && is_account_page();

		// Bail if no block, shortcode, or account page.
		if ( ! ( $has_block || $has_shortcode || $is_account ) ) {
			return;
		}

		// Get location to edit.
		$location_id = filter_input( INPUT_GET, 'location_id', FILTER_SANITIZE_NUMBER_INT );

		// Bail if no location ID or user can't edit.
		if ( ! ( $location_id && mailocations_user_can_edit( $location_id ) ) ) {
			return;
		}

		// Add form head.
		acf_form_head();
	}

	/**
	 * Saves featured image.
	 * Forces location to public when saving, if it's not already.
	 *
	 * @since TBD
	 *
	 * @param int $post_id The post ID.
	 *
	 * @return void
 	 */
	function before_save_post( $post_id ) {
		if ( ! $this->should_update( $post_id ) ) {
			return;
		}

		// Setup data.
		$data = [];

		// Title.
		if ( isset( $_POST['acf']['mai_location_title'] ) ) {
			// Set title.
			$title = sanitize_text_field( $_POST['acf']['mai_location_title'] );

			// Add to data array.
			if ( $title ) {
				$data['title'] = $title;
			}

			// Remove this field from saving to the db.
			unset( $_POST['acf']['mai_location_title'] );
		}

		// Excerpt.
		if ( isset( $_POST['acf']['mai_location_excerpt'] ) ) {
			// Set excerpt.
			$excerpt = wp_kses_post( $_POST['acf']['mai_location_excerpt'] );

			// Add to data array.
			if ( $excerpt ) {
				$data['excerpt'] = $excerpt;
			}

			// Remove this field from saving to the db.
			unset( $_POST['acf']['mai_location_excerpt'] );
		}

		// Author.
		$author_id = get_post_field( 'post_author', $post_id );

		if ( $author_id ) {
			$data['author'] = absint( $author_id );
		}

		// Emails.
		if ( isset( $_POST['acf']['mai_location_emails'] ) && $_POST['acf']['mai_location_emails'] ) {
			// Add to data array.
			$data['emails'] = sanitize_text_field( $_POST['acf']['mai_location_emails'] );

			// Remove this field from saving to the db.
			unset( $_POST['acf']['mai_location_emails'] );
		}

		/**
		 * Run after ACF saves the post
		 * to update the post title, excerpt, and/or featured image.
		 *
		 * @since TBD
		 *
		 * @param int $post_id The post ID.
		 *
		 * @return void
		 */
		add_action( 'acf/save_post', function( $post_id ) use ( $data ) {
			if ( ! $this->should_update( $post_id ) ) {
				return;
			}

			// Image. This has to run after saving so the image ID is available.
			if ( isset( $_POST['acf']['mai_location_image'] ) ) {
				// Set image ID.
				$image_id = absint( $_POST['acf']['mai_location_image'] );

				// Add to data array.
				if ( $image_id ) {
					$data['image'] = $image_id;
				}

				// Delete the value from the db.
				delete_field( 'mai_location_image', $post_id );
			}

			// Bail if no data.
			if ( ! $data ) {
				return;
			}

			// Set post array.
			$postarr = [];

			// Loop through data.
			foreach ( $data as $key => $value ) {
				switch ( $key ) {
					case 'title':
						$postarr['post_title'] = $value;
					break;
					case 'excerpt':
						$postarr['post_excerpt'] = $value;
					break;
					case 'image':
						set_post_thumbnail( $post_id, $value );
					break;
					case 'author':
						mailocations_add_location_to_user( $post_id, $value );
					break;
				}
			}

			// Bail if no data.
			if ( ! $postarr ) {
				return;
			}

			// Add post ID.
			$postarr['ID'] = $post_id;

			// Update the post.
			$post_id = wp_update_post( $postarr );

			// If post was updated.
			if ( $post_id && ! is_wp_error( $post_id ) ) {
				// Send emails.
				if ( isset( $data['emails'] ) && $data['emails'] ) {
					$this->send_emails( $post_id, $data );
				}
			}

		}, 20 );

		// Address fields.
		$address_changing = [];
		$address_fields   = [
			'mai_location_address_country',
			'mai_location_address_street',
			'mai_location_address_city',
			'mai_location_address_state',
			'mai_location_address_state_int',
			'mai_location_address_postcode',
		];

		// Loop through fields.
		foreach ( $address_fields as $key ) {
			// Skip if this field isn't in the form.
			if ( ! isset( $_POST['acf'][ $key ] ) ) {
				continue;
			}

			// Get current and new values.
			$current = get_field( $key, $post_id );
			$new     = $_POST['acf'][ $key ];

			// Skip if no change.
			if ( $current === $new ) {
				continue;
			}

			// Add to changing array.
			$address_changing[] = $key;
		}

		// Get location data.
		$location_current  = get_field( 'mai_location_location', $post_id );
		$location_new      = isset( $_POST['acf']['mai_location_location'] ) && $_POST['acf']['mai_location_location'] ? json_decode( wp_unslash( $_POST['acf']['mai_location_location'] ), true ) : '';
		$location_changing = ! ( $location_current || $location_new ) || ( ( $location_current || $location_new ) && $location_current !== $location_new );

		/**
		 * Run after ACF saves the post
		 * to update the post title, excerpt, and/or featured image.
		 *
		 * @since TBD
		 *
		 * @param int $post_id The post ID.
		 *
		 * @return void
		 */
		add_action( 'acf/save_post', function( $post_id ) use ( $address_changing, $location_changing ) {
			// If we're updating an address but no location.
			if ( $address_changing && ! $location_changing ) {
				// Update the google map field.
				mailocations_update_google_map_from_address( $post_id );
			}
			// If we're updating a location but no address.
			elseif ( $location_changing && ! $address_changing ) {
				// Update the address fields.
				mailocations_update_address_from_google_map( $post_id );
			}

		}, 20 );
	}

	/**
	 * Sends emails.
	 *
	 * @since TBD
	 *
	 * @param int   $post_id The post ID.
	 * @param array $data    The data.
	 *
	 * @return void
	 */
	function send_emails( $post_id, $data ) {
		// Bail if no emails.
		if ( ! $data['emails'] ) {
			return;
		}

		// Set emails.
		$emails = explode( ',', $data['emails'] );
		$emails = array_map( 'sanitize_email', $emails );
		$emails = array_unique( array_filter( $emails ) );

		// Bail if no emails.
		if ( ! $emails ) {
			return;
		}

		// Get data.
		$post_type    = get_post_type( $post_id );
		$singular     = mailocations_get_singular();
		$plural       = mailocations_get_plural();
		$name         = $data['author'] ? get_the_author_meta( 'display_name', $data['author'] ) : 'N/A';
		$to           = $emails;
		$subject      = sprintf( '%s %s %s %s %s', __( 'New', 'mai-locations' ), $singular, __( 'submission', 'mai-locations' ), __( 'from', 'mai-locations' ), $name );
		$base_url     = untrailingslashit( home_url() );
		$edit_url     = sprintf( '%s/wp-admin/post.php?post=%s&action=edit', $base_url, $post_id );
		$pending_url  = sprintf( '%s/wp-admin/edit.php?post_status=pending&post_type=%s', $base_url, $post_type );
		$draft_url    = sprintf( '%s/wp-admin/edit.php?post_status=draft&post_type=%s', $base_url, $post_type );
		$publish_url  = sprintf( '%s/wp-admin/edit.php?post_status=publish&post_type=%s', $base_url, $post_type );
		$message      = sprintf( 'There is a new submission from %s.', $name ) . "\n\n";
		$message     .= sprintf( 'View the pending post: %s', get_permalink( $post_id ) ) . "\n\n";
		$message     .= sprintf( 'Edit post: %s', $edit_url ) . "\n\n";
		$message     .= sprintf( 'View all pending %s: %s', $plural, $pending_url ) . "\n";
		$message     .= sprintf( 'View all draft %s: %s', $plural, $draft_url ) . "\n";
		$message     .= sprintf( 'View all published %s: %s', $plural, $publish_url ) . "\n";

		// Send email.
		wp_mail( $to, $subject, $message );
	}

	/**
	 * Send email when a location is changed from pending to published.
	 *
	 * @since TBD
	 *
	 * @param WP_Post $post Post object.
	 */
	function send_published_email( $post ) {
		// Bail if not a location.
		if ( 'mai_location' !== $post->post_type ) {
			return;
		}

		$singular  = mailocations_get_singular();
		$to        = get_the_author_meta( 'user_email', $post->post_author );
		$subject   = sprintf( '%s %s %s %s', __( 'Your', 'mai-locations' ), untrailingslashit( home_url() ), $singular, __( 'has been published!', 'mai-locations' ) );
		$message   = __( 'Thank you for your submission!', 'mai-locations' ) . "\n\n";
		$message  .= sprintf( 'View your %s here: %s', $singular, get_permalink( $post->ID ) ) . "\n\n";

		// Send email.
		wp_mail( $to, $subject, $message );
	}

	/**
	 * If the post should be updated.
	 *
	 * @since TBD
	 *
	 * @param mixed $post_id The post ID from ACF.
	 *
	 * @return bool
	 */
	function should_update( $post_id ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return false;
		}

		if ( wp_is_post_autosave( $post_id ) ) {
			return false;
		}

		if ( wp_is_post_revision( $post_id ) ) {
			return false;
		}

		if ( ! is_numeric( $post_id ) || 'mai_location' !== get_post_type( $post_id ) ) {
			return false;
		}

		if ( ! isset( $_POST['acf'] ) || empty( $_POST['acf'] ) ) {
			return;
		}

		return true;
	}

	/**
	 * Saves separate latitude and longitude and place ID values from map field.
	 *
	 * @since TBD
	 *
	 * @param array $value
	 * @param mixed $post_id
	 * @param array $field
	 * @param JSON  $original I think it's JSON?
	 *
	 * @return array
	 */
	function update_lat_lng_place_id_value( $value, $post_id, $field, $original ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}

		if ( isset( $value['lat'] ) && $value['lat'] ) {
			update_post_meta( $post_id, 'location_lat', $value['lat'] );
		}

		if ( isset( $value['lng'] ) && $value['lng'] ) {
			update_post_meta( $post_id, 'location_lng', $value['lng'] );
		}

		if ( isset( $value['place_id'] ) && $value['place_id'] ) {
			update_post_meta( $post_id, 'place_id', $value['place_id'] );
		}

		return $value;
	}
}

<?php

// Prevent direct file access.
defined( 'ABSPATH' ) || die;

class Mai_Locations_Location_Form_Edit extends Mai_Locations_Location_Form {
	/**
	 * Gets location edit form.
	 *
	 * @since TBD
	 *
	 * @return string
	 */
	function get_form() {
		// Bail if no location ID.
		if ( ! $this->args['location_id'] ) {
			return;
		}

		// Get it started.
		$html     = '';
		$singular = mailocations_get_singular();
		$referrer = isset( $GET['referrer'] ) ? sanitize_text_field( $GET['referrer'] ) : '';

		// Maybe add back link.
		if ( $referrer ) {
			$html .= sprintf( '<p><a href="%s">← %s</a></p>', esc_url( $referrer ), __( 'Back', 'mai-locations' ) );
		}

		// Add filter to load location values. These are not stored as custom meta, so we need to load them manually.
		add_filter( 'acf/load_value/key=mai_location_title',   [ $this, 'load_location_title_value' ], 10, 3 );
		add_filter( 'acf/load_value/key=mai_location_excerpt', [ $this, 'load_location_excerpt_value' ], 10, 3 );
		add_filter( 'acf/load_value/key=mai_location_image',   [ $this, 'load_location_image_value' ], 10, 3 );

		// Form args.
		$args = [
			'id'              => 'mailocations-form',
			'post_id'         => $this->args['location_id'],
			'fields'          => $this->args['fields'],
			'submit_value'    => sprintf( '%s %s', __( 'Update', 'mai-locations' ), $singular ),
			'updated_message' => sprintf( __( '%s successfully updated.', 'mai-locations' ), $singular ),
			'uploader'        => 'basic',
			// 'uploader'        => 'wp', // Not working, needs capabilities.
		];

		// If redirect is set, add it to the form args.
		if ( $this->args['redirect'] ) {
			$args['return'] = $this->args['redirect'];
		}

		// Add filter.
		$args = apply_filters( 'mailocations_acf_form_args', $args );

		// Get form.
		ob_start();
		acf_form( $args );
		$html .= ob_get_clean();

		// Remove filters.
		remove_filter( 'acf/load_value/key=mai_location_title',   [ $this, 'load_location_title_value' ], 10, 3 );
		remove_filter( 'acf/load_value/key=mai_location_excerpt', [ $this, 'load_location_excerpt_value' ], 10, 3 );
		remove_filter( 'acf/load_value/key=mai_location_image',   [ $this, 'load_location_image_value' ], 10, 3 );

		return $html;
	}

	/**
	 * Loads location title as the title field value.
	 *
	 * @since 0.4.0
	 *
	 * @param int   $value   The existing field value.
	 * @param int   $post_id The post ID.
	 * @param array $field   The existing field array.
	 *
	 * @return string
	 */
	function load_location_title_value( $value, $post_id, $field ) {
		return get_the_title( $this->args['location_id'] );
	}

	/**
	 * Loads location excerpt as the excerpt field value.
	 *
	 * @since 0.4.0
	 *
	 * @param int   $value   The existing field value.
	 * @param int   $post_id The post ID.
	 * @param array $field   The existing field array.
	 *
	 * @return string
	 */
	function load_location_excerpt_value( $value, $post_id, $field ) {
		return get_post_field( 'post_excerpt', $this->args['location_id'] );
	}

	/**
	 * Loads featured image as the image field value.
	 *
	 * @since 0.4.0
	 *
	 * @param int   $value   The existing field value.
	 * @param int   $post_id The post ID.
	 * @param array $field   The existing field array.
	 *
	 * @return int
	 */
	function load_location_image_value( $value, $post_id, $field ) {
		return get_post_thumbnail_id( $this->args['location_id'] );
	}
}
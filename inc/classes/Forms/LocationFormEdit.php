<?php

declare(strict_types=1);

namespace Mai\Locations\Forms;

// Prevent direct file access.
defined( 'ABSPATH' ) || die;

/**
 * The front-end form for editing an existing location.
 *
 * Was Mai_Locations_Location_Form_Edit in classes/class-location-form-edit.php. That name
 * still works, via inc/aliases.php.
 *
 * @since TBD
 */
class LocationFormEdit extends LocationForm {

	/**
	 * Gets the location edit form.
	 *
	 * @since TBD
	 *
	 * @return string
	 */
	public function get_form(): string {
		// Bail if no location ID.
		if ( ! $this->args['location_id'] ) {
			return '';
		}

		// Get it started.
		$html     = '';
		$singular = mailocations_get_singular_label( get_post_type( $this->args['location_id'] ) );
		$referrer = isset( $_GET['referrer'] ) ? sanitize_text_field( wp_unslash( $_GET['referrer'] ) ) : '';

		// Maybe add back link.
		if ( $referrer ) {
			$html .= sprintf( '<p><a href="%s">← %s</a></p>', esc_url( $referrer ), __( 'Back', 'mai-locations' ) );
		}

		// Get fields.
		$group_fields = mailocations_get_field_group_fields( get_post_type( $this->args['location_id'] ) );
		$group_fields = wp_list_pluck( $group_fields, 'label', 'key' );

		// Remove fields not in the group.
		foreach ( $this->args['fields'] as $index => $field ) {
			if ( ! isset( $group_fields[ $field ] ) ) {
				unset( $this->args['fields'][ $index ] );
			}
		}

		// Add filter to load location values. These are not stored as custom meta, so we need to load them manually.
		add_filter( 'acf/load_value/key=mai_location_title',   [ $this, 'load_location_title_value' ], 10, 3 );
		add_filter( 'acf/load_value/key=mai_location_excerpt', [ $this, 'load_location_excerpt_value' ], 10, 3 );
		add_filter( 'acf/load_value/key=mai_location_image',   [ $this, 'load_location_image_value' ], 10, 3 );

		// Get post status.
		$post_status  = get_post_status( $this->args['location_id'] );
		$submit_value = sprintf( '%s %s', __( 'Update', 'mai-locations' ), $singular );

		// A location still waiting to go live gets the Publish checkbox, whatever fields the site
		// chose. It is never offered on a published one, so the front end cannot unpublish, and
		// never on private or trashed ones, which the front end has no business moving.
		if ( in_array( $post_status, [ 'draft', 'pending' ], true ) && ! in_array( 'mai_location_publish', $this->args['fields'], true ) ) {
			$this->args['fields'][] = 'mai_location_publish';
		}

		// Form args.
		$args = [
			'id'              => 'mailocations-form',
			'post_id'         => $this->args['location_id'],
			'fields'          => $this->args['fields'],
			'submit_value'    => $submit_value,
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

		// Remove filters. The extra arguments are ignored by PHP; kept as they were.
		remove_filter( 'acf/load_value/key=mai_location_title',   [ $this, 'load_location_title_value' ], 10, 3 );
		remove_filter( 'acf/load_value/key=mai_location_excerpt', [ $this, 'load_location_excerpt_value' ], 10, 3 );
		remove_filter( 'acf/load_value/key=mai_location_image',   [ $this, 'load_location_image_value' ], 10, 3 );

		return $html;
	}

	/**
	 * Loads the location title as the title field value.
	 *
	 * @since 0.4.0
	 *
	 * @param mixed                $value   The existing field value.
	 * @param int|string           $post_id The post ID.
	 * @param array<string, mixed> $field   The existing field array.
	 *
	 * @return string
	 */
	public function load_location_title_value( $value, $post_id, $field ) {
		return get_the_title( $this->args['location_id'] );
	}

	/**
	 * Loads the location excerpt as the excerpt field value.
	 *
	 * @since 0.4.0
	 *
	 * @param mixed                $value   The existing field value.
	 * @param int|string           $post_id The post ID.
	 * @param array<string, mixed> $field   The existing field array.
	 *
	 * @return string
	 */
	public function load_location_excerpt_value( $value, $post_id, $field ) {
		return get_post_field( 'post_excerpt', $this->args['location_id'] );
	}

	/**
	 * Loads the featured image as the image field value.
	 *
	 * @since 0.4.0
	 *
	 * @param mixed                $value   The existing field value.
	 * @param int|string           $post_id The post ID.
	 * @param array<string, mixed> $field   The existing field array.
	 *
	 * @return int
	 */
	public function load_location_image_value( $value, $post_id, $field ) {
		return get_post_thumbnail_id( $this->args['location_id'] );
	}
}

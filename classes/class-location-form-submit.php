<?php

// Prevent direct file access.
defined( 'ABSPATH' ) || die;

class Mai_Locations_Location_Form_Submit extends Mai_Locations_Location_Form {

	/**
	 * Gets location edit form.
	 *
	 * @since TBD
	 *
	 * @return string
	 */
	function get_form() {
		// Get it started.
		$html = '';

		if ( ! $this->args['fields'] ) {
			return $html;
		}

		// Get single name and group data.
		$post_type    = $this->args['post_type'] ? $this->args['post_type'] : 'mai_location';
		$singular     = mailocations_get_singular_label( $post_type );
		$group_fields = mailocations_get_field_group_fields( $post_type );
		$group_fields = wp_list_pluck( $group_fields, 'label', 'key' );

		// If preview or in admin. Sometimes is_preview was showing false in the editor. Hmmm.
		if ( $this->args['preview'] ) {
			$html .= '<div class="mailocations-form-preview" style="pointer-events:none;padding:36px;border:2px dashed rgba(0,0,0,0.1);">';
				$html .= sprintf( '<p style="font-size:1.25em;"><strong>%s %s</strong></p>', $singular, __( 'Submission Form', 'mai-locations' ) );

				foreach ( $this->args['fields'] as $field ) {
					if ( ! isset( $group_fields[ $field ] ) ) {
						$html .= sprintf( '<p><label>%s</label><input style="border:1px solid red;" type="text" placeholder="%s"></p>', $field, __( 'Field not available on selected post type', 'mai-locations' ) );
						continue;
					}

					$html .= sprintf( '<p><label>%s</label><input type="text" placeholder="%s"></p>', $group_fields[ $field ], __( 'Placeholder field', 'mai-locations' ) );
				}
			$html .= '</div>';

			return $html;
		}

		// Form args.
		$args = [
			'id'                => 'mailocations-form',
			'post_id'           => 'new_post',
			'new_post'          => [
				'post_type'   => $post_type,
				'post_status' => $this->args['status'] ? $this->args['status'] : 'pending',
				'post_author' => get_current_user_id(), // Returns zero if not logged in.
			],
			'fields'            => $this->args['fields'],
			'submit_value'      => sprintf( '%s %s', __( 'Submit', 'mai-locations' ), $singular ),
			'updated_message'   => sprintf( __( '%s successfully submitted.', 'mai-locations' ), $singular ),
			'uploader'          => 'basic',
			// 'uploader'          => 'wp', // Not working, needs capabilities.
			'html_after_fields' => '',
		];

		// If redirect is set, add it to the form args.
		if ( $this->args['redirect'] ) {
			$args['return'] = $this->args['redirect'];
		}

		// If emails. Add hidden field.
		if ( $this->args['emails'] ) {
			$args['html_after_fields'] .= sprintf( '<input type="hidden" name="acf[mai_location_emails]" value="%s">', esc_attr( $this->args['emails'] ) );
		}

		// Add filter.
		$args = apply_filters( 'mailocations_acf_form_args', $args );

		// Get form.
		ob_start();
		acf_form( $args );
		$html .= ob_get_clean();

		return $html;
	}
}
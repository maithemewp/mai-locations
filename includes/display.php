<?php

// Enqueue CSS files.
add_action( 'wp_enqueue_scripts', 'mailocations_register_scripts' );
function mailocations_register_scripts() {
	wp_register_style( 'mai-locations', MAI_LOCATIONS_PLUGIN_URL . 'assets/css/mai-locations.css', [], MAI_LOCATIONS_VERSION );
}

add_action( 'get_header', 'mailocations_maybe_show_locations_table', 0 );
function mailocations_maybe_show_locations_table() {
	if ( ! ( is_user_logged_in() && is_singular() ) ) {
		return;
	}

	if ( ! mailocation_get_user_locations() ) {
		return;
	}

	$has_block     = has_blocks() && has_block( 'acf/mai-locations-table' );
	$has_shortcode = has_shortcode( get_post_field( 'post_content', get_the_ID() ), 'mai_locations_table' );

	if ( ! ( $has_block || $has_shortcode ) ) {
		return;
	}

	$location_id = filter_input( INPUT_GET, 'location_id', FILTER_SANITIZE_NUMBER_INT );

	if ( ! ( $location_id && mailocations_user_can_edit( $location_id ) ) ) {
		return;
	}

	acf_form_head();
}


/**
 * Gets a locations table.
 * Displays view/edit buttons.
 * When editing a location the table is replaced
 * with the ACF location fields.
 *
 * @since 0.1.0
 *
 * @param int   $user_id The user ID.
 * @param array $args    The table/form args.
 *
 * @return string
 */
function mailocations_get_locations_table( $user_id = 0, $args ) {
	$is_admin = is_admin();
	$user_id  = $user_id ?: get_current_user_id();

	if ( ! $user_id ) {
		return;
	}

	if ( ! $is_admin ) {
		$locations = mailocation_get_user_locations( $user_id );
	} else {
		$query = new WP_Query(
			[
				'post_type'              => 'mai_location',
				'posts_per_page'         => 2,
				'post_status'            => 'publish',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'fields'                 => 'ids',
			]
		);
		$locations = $query->posts;
		wp_reset_postdata();

		if ( ! $locations ) {
			$plural  = strtolower( mailocations_get_label_plural() );
			$message = sprintf( __( 'No %s exist. Add new %s to display them here.', 'mai-locations' ), $plural, $plural );
			$message = sprintf( '<table><tr><th><em>%s</em></th></tr></table>', $message );
			$args['no_results'] = $message;
		}
	}

	if ( ! $locations ) {
		return wpautop( $args['no_results'] );
	}

	// Atts.
	$args = shortcode_atts(
		[
			'title'        => sprintf( '%s %s', __( 'My', 'mai-locations' ), mailocations_get_label_plural() ),
			'header'       => mailocations_get_label_plural(),
			'edit_fields'  => [ 'title', 'content' ],
			'edit_title'   => true, // Allow editing the location title.
			'edit_content' => true, // Allow editing the location content.
		],
		$args,
		'mai_locations_table'
	);

	// Sanitize.
	$args = [
		'title'        => esc_html( $args['title'] ),
		'header'       => esc_html( $args['header'] ),
		'edit_fields'  => array_map( 'esc_html', $args['edit_fields'] ),
		'edit_title'   => (bool) $args['edit_title'],
		'edit_content' => (bool) $args['edit_content'],
	];

	$html = '';

	$location_id = filter_input( INPUT_GET, 'location_id', FILTER_SANITIZE_NUMBER_INT );

	if ( ! $is_admin && ( $location_id && mailocations_user_can_edit( $location_id ) ) ) {

		wp_enqueue_style( 'mai-locations' );

		$html .= mailocations_get_location_edit_form( $location_id, $args );

	} else {

		if ( $is_admin ) {
			$html .= '<style>.mai-locations-table a { pointer-events: none; }</style>';
		}

		$html .= '<table class="mai-locations-table">';

			$html .= $args['title'] ? sprintf( '<h2>%s</h2>', $args['title'] ) : '';

			$html .= '<thead>';
				$html .= '<tr>';
					$html .= sprintf( '<th colspan="2">%s</th>', $args['header'] );
				$html .= '</tr>';
			$html .= '</thead>';

			$html .= '<tbody>';

				foreach ( $locations as $location_id ) {
					$classes = 'button button-secondary button-small';

					$html .= '<tr>';
						$html .= '<td>';
							$html .= sprintf( '<span class="has-md-font-size"><a href="%s">%s</a></span>',
								get_permalink( $location_id ),
								get_the_title( $location_id )
							);
							$html .= mailocations_get_address(
								[
									'hide' => 'street2, postcode, country',
								],
								$location_id
							);
						$html .= '</td>';

						$edit_url = add_query_arg(
							[
								'location_id' => $location_id,
								'redirect'    => get_permalink(),
							],
							get_permalink()
						);

						$html .= '<td style="text-align:right;">';
							$html .= sprintf( '<a class="%s" href="%s">%s</a>',
								$classes,
								get_permalink( $location_id ),
								__( 'View', 'mai-locations' )
							);
							$html .= sprintf( '<a style="margin-left:6px;" class="%s" href="%s">%s</a>',
								$classes,
								esc_url( $edit_url ),
								__( 'Edit', 'mai-locations' )
							);
						$html .= '</td>';
					$html .= '</tr>';
				}

			$html .= '</tbody>';
		$html .= '</table> ';
	}

	return $html;
}

function mailocations_get_location_edit_form( $location_id, $args ) {
	$singular = mailocations_get_label_singular();
	$redirect = esc_url( get_permalink() );
	$fields   = [];
	$groups   = acf_get_field_groups( [ 'post_id' => $location_id ] );

	foreach ( $groups as $group ) {
		$group_fields = acf_get_fields( $group['key'] );

		foreach ( $group_fields as $index => $field ) {
			if ( 'tab' === $field['type'] ) {
				continue;
			}
			$fields[] = $field['key'];
		}
	}

	// Disables text tab, media upload, and uses basic toolbar.
	$filter = function( $field ) {
		if ( 'wysiwyg' === $field['type'] ) {
			$field['tabs']         = 'visual';
			$field['toolbar']      = 'basic';
			$field['media_upload'] = 0;
		}
		return $field;
	};
	add_filter( 'acf/get_valid_field', $filter );

	$html = sprintf( '<p><a href="%s">← %s</a></p>', $redirect, __( 'Back', 'mai-engine' ) );

	ob_start();
	acf_form(
		[
			'id'                 => 'mai-location-edit',
			'post_id'            => $location_id,
			// 'field_groups'    => array( 'group_5d519ec8bcdb7' ),
			'fields'             => $fields,
			'post_title'         => $args['edit_title'],
			'post_content'       => $args['edit_content'],
			'submit_value'       => sprintf( '%s %s', __( 'Update', 'mai-locations' ), $singular ),
			'return'             => $redirect,
			'updated_message'    => sprintf( __( 'Your %s has been successfully updated.', 'mai-locations' ), strtolower( $singular ) ),
			'uploader'           => 'basic',
			'echo'               => 'false',
			'html_submit_button' => '<input type="submit" class="acf-button button" value="%s" />',
		]
	);

	$html .= ob_get_clean();

	remove_filter( 'acf/get_valid_field', $filter );

	return $html;
}


/**
 * Gets a formatted address from current post in the loop.
 *
 * @since 0.1.0
 *
 * @param array $args    The address args.
 * @param int   $post_id The post ID.
 *
 * @return string
 */
function mailocations_get_address( $args, $post_id = 0 ) {
	// Atts.
	$args = shortcode_atts(
		[
			'hide' => '', // street, street2, city, state, postcode, country
		],
		$args,
		'mai_location_address'
	);

	$html      = '';
	$hide      = explode( ',', $args['hide'] );
	$hide      = array_map( 'esc_html', $hide );
	$hide      = array_map( 'trim', $hide );
	$hide      = array_flip( $hide );
	$post_id   = (int) $post_id ?: get_the_ID();
	$street    = ! isset( $hide['street'] ) ? get_post_meta( $post_id, 'address_street', true ) : '';
	$street_2  = ! isset( $hide['street2'] ) ? get_post_meta( $post_id, 'address_street_2', true ) : '';
	$city      = ! isset( $hide['city'] ) ? get_post_meta( $post_id, 'address_city', true ) : '';
	$state     = ! isset( $hide['state'] ) ? get_post_meta( $post_id, 'address_state', true ) : '';
	$state_int = ! isset( $hide['state'] ) ? get_post_meta( $post_id, 'address_state_int', true ) : '';
	$postcode  = ! isset( $hide['postcode'] ) ? get_post_meta( $post_id, 'address_postcode', true ) : '';
	$country   = ! isset( $hide['country'] ) ? get_post_meta( $post_id, 'address_country', true ) : '';
	$state     = $country && 'US' !== $country ? $state_int : $state; // Use state_int if non-US.

	if ( ! ( $street || $street_2 || $city || $state || $postcode || $country ) ) {
		return $html;
	}

	$html .= '<div itemprop="address" itemscope itemtype="http://schema.org/PostalAddress" class="mai-address">';

		if ( $street ) {
			$html .= sprintf( '<div class="mai-address-item"><span class="street-address" itemprop="streetAddress">%s</span></div>', esc_html( $street ) );
		}

		if ( $street_2 ) {
			$html .= sprintf( '<div class="mai-address-item"><span class="street-address-2">%s</span></div>', esc_html( $street_2 ) );
		}

		if ( $city || $state || $postcode || $country ) {
			$html .= '<div class="mai-address-item">';

				if ( $city ) {
					$html .= '<span class="locality" itemprop="addressLocality">' . esc_html( $city ) . '</span>';
				}

				if ( $state ) {
					$html .= '<span class="region" itemprop="addressRegion">&nbsp;' . esc_html( $state ) . '</span>';
				}

				if ( $postcode ) {
					$html .= '<span class="postal-code" itemprop="postalCode">,&nbsp;' . esc_html( $postcode ) . '</span>';
				}

			$html .= '</div>';
		}

		if ( $country ) {
			$countries = mailocations_get_country_choices();
			$country   = isset( $countries[ $country ] ) ? $countries[ $country ] : $country;
			$html     .= sprintf( '<div class="mai-address-item" itemprop="addressCountry">%s</div>', esc_html( $country ) );
		}

	$html .= '</div>';

	return $html;
}


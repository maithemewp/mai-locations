<?php

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'wp_enqueue_scripts', 'mailocations_register_scripts' );
/**
 * Enqueues scripts and styles.
 *
 * @since 0.1.0
 *
 * @return void
 */
function mailocations_register_scripts() {
	$suffix = mailocations_get_suffix();
	wp_register_style( 'mai-locations-edit', MAI_LOCATIONS_PLUGIN_URL . "assets/css/mai-locations-edit{$suffix}.css", [], MAI_LOCATIONS_VERSION );
	wp_register_style( 'mai-locations-filters', MAI_LOCATIONS_PLUGIN_URL . "assets/css/mai-locations-filters{$suffix}.css", [], MAI_LOCATIONS_VERSION );
	wp_register_script( 'mai-locations-filters', MAI_LOCATIONS_PLUGIN_URL . "assets/js/mai-locations-filters{$suffix}.js", [], MAI_LOCATIONS_VERSION, false );
	wp_register_script( 'mai-locations-googlemaps', sprintf( 'https://maps.googleapis.com/maps/api/js?key=%s&v=quarterly&libraries=places&callback=initMap', mailocations_get_google_maps_api_key() ), [], MAI_LOCATIONS_VERSION, false );
	wp_register_script( 'mai-locations-markerclusterer', 'https://unpkg.com/@googlemaps/markerclusterer/dist/index.min.js', [], '1.0.0', true );

	$localize = [
		'params'       => mailocations_get_query_params(),
		'defaults'     => mailocations_get_query_defaults(),
		'autoComplete' => [
			'fields'       => [ 'geometry', 'name' ],
			'strictBounds' => false,
			// 'componentRestrictions' => [ 'country' => 'us' ],
			// 'types'                 => [ 'establishment' ],
		],
	];

	// Only add address_components if limiting by state/province since this adds to the API volume.
	if ( mailocations_get_option( 'limit_state' ) ) {
		$localize['autoComplete']['fields'][] = 'address_components';
	}

	// Allow filtering of script data.
	$localize = apply_filters( 'mailocations_localize_script_data', $localize );

	// Localize.
	wp_localize_script( 'mai-locations-filters', 'maiLocationsVars', $localize );
}

add_action( 'get_header', 'mailocations_location_edit_listener', 0 );
/**
 * Processes form submission.
 * Adds acf_form_head().
 *
 * @since 0.1.0
 *
 * @return void
 */
function mailocations_location_edit_listener() {
	if ( ! ( is_user_logged_in() && is_singular() ) ) {
		return;
	}

	if ( ! mailocation_get_user_locations() ) {
		return;
	}

	$has_block     = has_blocks() && has_block( 'acf/mai-locations-table' );
	$has_shortcode = has_shortcode( get_post_field( 'post_content', get_the_ID() ), 'mai_locations_table' );
	$is_account    = class_exists( 'WooCommerce' ) && is_account_page();

	if ( ! ( $has_block || $has_shortcode || $is_account ) ) {
		return;
	}

	$location_id = filter_input( INPUT_GET, 'location_id', FILTER_SANITIZE_NUMBER_INT );

	if ( ! ( $location_id && mailocations_user_can_edit( $location_id ) ) ) {
		return;
	}

	/**
	 * Saves featured image.
	 * Forces location to public when saving, if it's not already.
	 *
	 * @return void
	 */
	add_action( 'acf/save_post', function( $post_id ) {
		if ( ! is_numeric( $post_id ) || 'mai_location' !== get_post_type( $post_id ) ) {
			return;
		}

		if ( isset( $_POST['acf']['mai_location_image'] ) ) {
			$image_id = absint( $_POST['acf']['mai_location_image'] );

			if ( $image_id ) {
				set_post_thumbnail( $post_id, $image_id );
			}

			// Remove this field from saving to the db.
			unset( $_POST['acf']['mai_location_image'] );
		}

		if ( 'publish' === get_post_status( $post_id ) ) {
			return;
		}

		wp_update_post(
			[
				'ID'          => $post_id,
				'post_status' => 'publish' ,
			]
		);

	}, 4 );

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
function mailocations_get_locations_table( $user_id = 0, $args = [] ) {
	$user_id     = (int) $user_id ?: get_current_user_id();
	$is_admin    = is_admin();
	$is_viewable = is_post_type_viewable( 'mai_location' );

	if ( ! $user_id ) {
		return;
	}

	// Atts.
	$args = shortcode_atts(
		[
			'title'        => sprintf( '%s %s', __( 'My', 'mai-locations' ), mailocations_get_plural() ),
			'header'       => mailocations_get_plural(),
			'no_results'   => __( 'Sorry, no locations available.', 'mai-locations' ),
			'edit_fields'  => [ 'title', 'content' ],
			'class'        => '',
			'align'        => '',
		],
		$args,
		'mai_locations_table'
	);

	// Sanitize.
	$args = [
		'title'        => esc_html( $args['title'] ),
		'header'       => esc_html( $args['header'] ),
		'no_results'   => sanitize_text_field( $args['no_results'] ),
		'edit_fields'  => array_map( 'esc_html', $args['edit_fields'] ),
		'class'        => esc_attr( $args['class'] ),
		'align'        => esc_html( $args['align'] ),
	];

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
			$plural  = strtolower( mailocations_get_plural() );
			$message = sprintf( __( 'No %s exist. Add new %s to display them here.', 'mai-locations' ), $plural, $plural );
			$message = sprintf( '<table><tr><th><em>%s</em></th></tr></table>', $message );
			$args['no_results'] = $message;
		}
	}

	if ( ! $locations ) {
		return wpautop( $args['no_results'] );
	}

	$html         = '';
	$location_id  = filter_input( INPUT_GET, 'location_id', FILTER_SANITIZE_NUMBER_INT );

	if ( ! $is_admin && ( $location_id && mailocations_user_can_edit( $location_id ) ) ) {

		wp_enqueue_style( 'mai-locations-edit' );

		$html .= mailocations_get_location_edit_form( $location_id,
			[
				'edit_title'   => in_array( 'title', $args['edit_fields'] ),
				'edit_content' => in_array( 'content', $args['edit_fields'] ),
			]
		);

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
					$public  = 'publish' === get_post_status( $location_id );
					$classes = 'button button-secondary button-small';

					$html .= '<tr>';
						$html .= '<td>';
							// Title.
							$html .= '<span class="has-md-font-size">';
								if ( $is_viewable && $public ) {
									$html .= sprintf( '<a href="%s">', get_permalink( $location_id ) );
								}
								$html .= get_the_title( $location_id );
								if ( $is_viewable && $public ) {
									$html .= '</a>';
								}
							$html .= '</span>';

							// Address.
							$html .= mailocations_get_address(
								[
									'hide' => 'street2, postcode, country',
								],
								$location_id
							);
						$html .= '</td>';

						$edit_url = home_url( add_query_arg( null, null ) );
						$edit_url = add_query_arg(
							[
								'location_id' => $location_id,
								'referrer'    => $edit_url,
							],
							$edit_url
						);

						$html .= '<td style="text-align:right;">';
							// View.
							if ( $is_viewable && $public ) {
								$html .= sprintf( '<a class="%s" href="%s">%s</a>',
									$classes,
									get_permalink( $location_id ),
									__( 'View', 'mai-locations' )
								);
							}

							// Edit.
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

/**
 * Gets location edit form.
 *
 * @since 0.1.0
 *
 * @param int   $location_id The post ID.
 * @param array $args        The required args.
 *
 * @return string
 */
function mailocations_get_location_edit_form( $location_id, $args ) {
	// Atts.
	$args = shortcode_atts(
		[
			'edit_title'   => true,
			'edit_content' => true,
		],
		$args,
		'mai_location_edit_form'
	);

	// Sanitize.
	$args = [
		'edit_title'   => (bool) $args['edit_title'],
		'edit_content' => (bool) $args['edit_content'],
	];

	$fields = [];
	$groups = acf_get_field_groups( [ 'post_id' => $location_id ] );

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


	$html     = '';
	$singular = mailocations_get_singular();
	$referrer = filter_input( INPUT_GET, 'referrer', FILTER_SANITIZE_STRING );

	if ( $referrer ) {
		$html .= sprintf( '<p><a href="%s">← %s</a></p>', esc_url( $referrer ), __( 'Back', 'mai-locations' ) );
	}

	ob_start();
	acf_form(
		[
			'id'                 => 'mai-location-edit',
			'post_id'            => $location_id,
			'fields'             => $fields,
			'post_title'         => $args['edit_title'],
			'post_content'       => $args['edit_content'],
			'submit_value'       => sprintf( '%s %s', __( 'Update', 'mai-locations' ), $singular ),
			'updated_message'    => sprintf( __( '%s successfully updated.', 'mai-locations' ), $singular ),
			'uploader'           => 'wp',
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
function mailocations_get_address( $args = [], $post_id = 0 ) {
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

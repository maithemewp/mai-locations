<?php

// Prevent direct file access.
defined( 'ABSPATH' ) || die;

class Mai_Locations_Locations_Table {
	protected $user_id;
	protected $args;

	/**
	 * Construct the class.
	 *
	 * @since TBD
	 *
	 * @param array $args The table/form args.
	 */
	function __construct( $args = [] ) {
		$this->user_id = get_current_user_id();
		$args          = shortcode_atts(
			[
				'post_type'  => 'mai_location',
				'title'      => sprintf( '%s %s', __( 'My', 'mai-locations' ), mailocations_get_plural() ),
				'header'     => mailocations_get_plural(),
				'no_results' => __( 'Sorry, no locations available.', 'mai-locations' ),
				'redirect'   => '',
				'fields'     => [],
				'class'      => '',
			],
			$args,
			'mai_locations_table'
		);

		// Sanitize.
		$args = [
			'post_type'  => sanitize_key( $args['post_type'] ),
			'title'      => esc_html( $args['title'] ),
			'header'     => esc_html( $args['header'] ),
			'no_results' => sanitize_text_field( $args['no_results'] ),
			'redirect'   => $args['redirect'] ? esc_url( $args['redirect'] ) : '',
			'fields'     => array_map( 'sanitize_text_field', $args['fields'] ),
			'class'      => esc_attr( $args['class'] ),
		];

		// Force default post type if none.
		$args['post_type'] = $args['post_type'] ?: 'mai_location';

		// Assign.
		$this->args = $args;
	}

	/**
	 * Gets a locations table.
	 * Displays view/edit buttons.
	 * When editing a location the table is replaced
	 * with the ACF location fields.
	 *
	 * @since TBD
	 *
	 * @return string
	 */
	function get() {
		// Bail if no user.
		if ( ! $this->user_id ) {
			return;
		}

		// Set vars.
		$is_admin    = is_admin();
		$is_viewable = is_post_type_viewable( $this->args['post_type'] );

		// Get user locations for front end.
		if ( ! $is_admin ) {
			$locations = mailocation_get_user_locations( $this->args['post_type'] );
		}
		// Get first 2 locations for admin.
		else {
			$query = new WP_Query(
				[
					'post_type'              => $this->args['post_type'],
					'posts_per_page'         => 2,
					'post_status'            => [ 'publish', 'pending' ],
					'no_found_rows'          => true,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
					'fields'                 => 'ids',
				]
			);
			$locations = $query->posts;

			// No locations message.
			if ( ! $locations ) {
				$plural                   = strtolower( mailocations_get_plural_label( $this->args['post_type'] ) );
				$message                  = sprintf( __( 'No %s exist. Add new %s to display them here.', 'mai-locations' ), $plural, $plural );
				$message                  = sprintf( '<table><tr><th><em>%s</em></th></tr></table>', $message );
				$this->args['no_results'] = $message;
			}
		}

		// Bail if no locations.
		if ( ! $locations ) {
			$return  = $this->args['title'] ? sprintf( '<h2>%s</h2>%s', $this->args['title'], PHP_EOL ) : '';
			$return .= wpautop( $this->args['no_results'] );

			return $return;
		}

		// Set up HTML.
		$html         = '';
		$location_id  = filter_input( INPUT_GET, 'location_id', FILTER_SANITIZE_NUMBER_INT );

		// If on front end and user can edit this location.
		if ( ! $is_admin && ( $location_id && mailocations_user_can_edit( $location_id ) ) ) {
			// Get form HTML.
			$html = mailocations_get_location_edit_form( array_merge( [ 'location_id' => $location_id ], $this->args ) );
		}
		// Show table.
		else {
			// Disable links in the editor.
			if ( $is_admin ) {
				$html .= '<style>.mai-locations-table a { pointer-events: none; }</style>';
			} else {
				// TODO: Make this a link to a stylesheet?
				// TODO: Static var to prevent loading multiple times?
				ob_start();
				?>
				<style>
				.mai-location-item-title,
				.mai-locations-table .mai-address {
					line-height: 1.25;
				}
				.mai-location-item-title {
					display: block;
				}
				.mai-locations-table .mai-address {
					display: flex;
					flex-wrap: wrap;
					margin-top: 0.5em;
					font-size: 0.8em;
				}
				.mai-locations-table .mai-address-item:not(:last-of-type)::after {
					margin-right: 0.25em;
					content: ',';
				}
				</style>
				<?php
				$html .= ob_get_clean();
			}

			// Table.
			$html .= '<table class="mai-locations-table">';
				// Title.
				$html .= $this->args['title'] ? sprintf( '<h2>%s</h2>', $this->args['title'] ) : '';

				// Header.
				$html .= '<thead>';
					$html .= '<tr>';
						$html .= sprintf( '<th colspan="2">%s</th>', $this->args['header'] );
					$html .= '</tr>';
				$html .= '</thead>';

				// Body.
				$html .= '<tbody>';
					// Loop through locations.
					foreach ( $locations as $location_id ) {
						$status  = get_post_status( $location_id );
						$public  = 'publish' === $status;
						$classes = 'button button-secondary button-small';

						$html .= '<tr>';
							$html .= '<td>';
								// Title.
								$html .= '<span class="mai-location-item-title">';
									// Maybe add link.
									if ( $is_viewable && $public ) {
										$html .= sprintf( '<a href="%s">', get_permalink( $location_id ) );
									}

									// Add title.
									$title  = ! $public ? sprintf( '(%s) ', $status ) : '';
									$title .= get_the_title( $location_id );
									$html  .= $title;

									// Maybe close link.
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

							// Get edit url.
							$edit_url = home_url( add_query_arg( null, null ) );
							$edit_url = add_query_arg(
								[
									'location_id' => $location_id,
									'referrer'    => $edit_url,
								],
								$edit_url
							);

							// Action buttons.
							$html .= '<td style="text-align:right;white-space:nowrap;">';
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

		// If admin, check fields.
		if ( $is_admin ) {
			// Get fields.
			$group_fields = mailocations_get_field_group_fields( $this->args['post_type'] );
			$group_fields = wp_list_pluck( $group_fields, 'label', 'key' );

			// Add notice for fields not in the group.
			foreach ( $this->args['fields'] as $index => $field ) {
				if ( isset( $group_fields[ $field ] ) ) {
					continue;
				}

				$html .= sprintf( '<p style="padding:16px;border:1px solid red;">%s: %s</p>', $field, __( 'Field not available on selected post type', 'mai-locations' ) );
			}
		}

		return $html;
	}
}
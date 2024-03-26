<?php

// Prevent direct file access.
defined( 'ABSPATH' ) || die;

/**
 * The locations filter block class.
 *
 * @since TBD
 */
class Mai_Locations_Address_Search_Block {
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
		add_action( 'acf/init', [ $this, 'register_block' ] );
		add_action( 'acf/init', [ $this, 'register_field_group' ] );
	}

	/**
	 * Registers block.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	function register_block() {
		register_block_type( __DIR__ . '/block.json',
			[
				'render_callback' => [ $this, 'render_block' ],
			]
		);
	}

	/**
	 * Callback function to render the block.
	 *
	 * @since TBD
	 *
	 * @param array    $attributes The block attributes.
	 * @param string   $content The block content.
	 * @param bool     $is_preview Whether or not the block is being rendered for editing preview.
	 * @param int      $post_id The current post being edited or viewed.
	 * @param WP_Block $wp_block The block instance (since WP 5.5).
	 * @param array    $context The block context array.
	 *
	 * @return void
	 */
	function render_block( $attributes, $content, $is_preview, $post_id, $wp_block, $context ) {
		$params      = wp_parse_args( mailocations_get_query_params(), mailocations_get_query_defaults() );
		$placeholder = get_field( 'placeholder' );
		$placeholder = $placeholder ?: __( 'Enter your address', 'mai-locations' );
		$address     = $params['address'];
		$distances   = explode( ',', (string) get_field( 'distances' ) );
		$distance    = $params['distance'];
		$units       = (array) get_field( 'units' );
		$unit        = $params['units'];
		$countries   = (array) get_field( 'countries' );

		// Maybe enqueue scripts.
		if ( ! $is_preview ) {
			wp_enqueue_script( 'mai-locations' );
		}

		// Maybe load CSS.
		echo mailocations_get_stylesheet_link( 'mai-locations' );

		// Build HTML.
		echo '<div class="mailocations-autocomplete-container">';
			echo '<div class="mailocations-autocomplete-input-container">';
				$value = ! $is_preview ? sprintf( ' value="%s"', $address ) : ''; // Can't have value attribute or React balks.

				// Input field.
				printf( '<input type="text" class="mailocations-autocomplete" data-countries="%s" placeholder="%s"%s>',
					implode( ',', $countries ),
					$placeholder,
					$value
				);

				// Clear button.
				if ( ! $is_preview  ) {
					printf( '<button class="mailocations-autocomplete-clear">%s</button>', __( 'Clear', 'mai-locations' ) );
				}
			echo '</div>';

			// If we have distances.
			if ( $distances ) {
				// If we have multiple units.
				$multiple = count( $units ) > 1;

				// If we have more than one distance.
				if ( count( $distances ) > 1 ) {
					// Distance selector.
					echo '<select class="mailocations-autocomplete-distance">';
						foreach ( $distances as $value ) {
							$label    = $multiple ? $value : $value . ' ' . $unit;
							$selected = ! $is_preview && (int) $value === (int) $distance ? ' selected' : '';
							$value    = ! $is_preview ? sprintf( ' value="%s"', $value ) : ''; // Can't have value attribute or React balks.

							printf( '<option %s%s>%s</option>', $value, $selected, $label );
						}
					echo '</select>';

					// Unit selector.
					if ( $units && $multiple ) {
						echo '<select class="mailocations-autocomplete-unit">';
							foreach ( $units as $value ) {
								$raw      = $value;
								$value    = ! $is_preview ? sprintf( ' value="%s"', $raw ) : '';
								$selected = ! $is_preview && $raw === $unit ? ' selected' : '';

								printf ( '<option %s%s>%s</option>', $value, $selected, $raw );
							}
						echo '</select>';
					}
				}
				// One distance, hidden field if not preview.
				elseif ( ! $is_preview ) {
					// Distance.
					printf( '<input type="hidden" class="mailocations-autocomplete-distance" value="%s">', $distance );

					// Unit.
					printf( '<input type="hidden" class="mailocations-autocomplete-unit" value="%s">', $unit );
				}
			}
		echo '</div>';
	}

	/**
	 * Register field group.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	function register_field_group() {
		if ( ! function_exists( 'acf_add_local_field_group' ) ) {
			return;
		}

		acf_add_local_field_group(
			[
				'key'    => 'mailocations_locations_address_search_field_group',
				'title'  => __( 'Mai Locations Address Search', 'mai-locations' ),
				'fields' => [
					[
						'key'           => 'mailocations_address_search_message',
						'type'          => 'message',
						'message'       => ! mailocations_get_google_maps_api_key() ? __( 'Google Maps API key missing!', 'mailocations' ) : '',
						'esc_html'      => 0,
					],
					[
						'label'         => __( 'Placeholder', 'mai-locations' ),
						'key'           => 'mailocations_address_search_placeholder',
						'name'          => 'placeholder',
						'type'          => 'text',
						'placeholder'   => __( 'Enter your address', 'mai-locations' ),
					],
					[
						'label'         => __( 'Distances', 'mai-locations' ),
						'instructions'  => __( 'Comma-separated distance options used for proximity search. Use a single value to hide field and force one distance. Use 0 to show all results.', 'mai-locations' ),
						'key'           => 'mailocations_address_search_distances',
						'name'          => 'distances',
						'type'          => 'text',
						'default_value' => '25, 50, 100, 200',
						'placeholder'   => '25, 50, 100, 200',
					],
					[
						'label'         => __( 'Units', 'mai-locations' ),
						'instructions'  => sprintf( __( 'The distance unit options to use. If none are selected, the field will be hidden and "%s" will be used.', 'mai-locations' ), mailocations_get_option_default( 'units' ) ),
						'key'           => 'mailocations_address_search_units',
						'name'          => 'units',
						'type'          => 'checkbox',
						'default_value' => (array) mailocations_get_option_default( 'units' ),
						'choices'       => [
							'mi' => __( 'Miles', 'mai-locations' ),
							'km' => __( 'Kilometers', 'mai-locations' ),
						],
					],
					[
						'label'         => __( 'Countries', 'mai-locations' ),
						'instructions'  => __( 'Limit search autocomplete to specific countries', 'mai-locations' ),
						'key'           => 'mailocations_address_search_countries',
						'name'          => 'countries',
						'type'          => 'select',
						'choices'       => mailocations_get_country_choices(),
						'default_value' => 'US',
						'multiple'      => 1,
						'allow_null'    => 1,
						'ui'            => 1,
						'ajax'          => 1,
					],
				],
				'location' => [
					[
						[
							'param'    => 'block',
							'operator' => '==',
							'value'    => 'acf/mai-locations-address-search',
						],
					],
				],
			]
		);
	}
}
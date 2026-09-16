<?php

declare(strict_types=1);

namespace Mai\Locations\Blocks;

// Prevent direct file access.
defined( 'ABSPATH' ) || die;

/**
 * The address search block, which drives proximity search.
 *
 * Was Mai_Locations_Address_Search_Block in blocks/location-address-search/block.php. That name
 * still works, via inc/aliases.php. block.json stays in blocks/location-address-search/.
 *
 * @since TBD
 */
class AddressSearchBlock {

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
		add_action( 'acf/init', [ $this, 'register_block' ] );
		add_action( 'acf/init', [ $this, 'register_field_group' ] );
	}

	/**
	 * Registers the block.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function register_block(): void {
		register_block_type( MAI_LOCATIONS_PLUGIN_DIR . 'blocks/location-address-search',
			[
				'render_callback' => [ $this, 'render_block' ],
			]
		);
	}

	/**
	 * Renders the block.
	 *
	 * TODO: distance options keep the space from a "10, 20" setting, so one option's value is
	 * " 20". See TODO.md.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $attributes The block attributes.
	 * @param string               $content    The block content.
	 * @param bool                 $is_preview Whether the block is rendering for an editor preview.
	 * @param int                  $post_id    The current post being edited or viewed.
	 * @param \WP_Block            $wp_block   The block instance.
	 * @param array<string, mixed> $context    The block context array.
	 *
	 * @return void
	 */
	public function render_block( $attributes, $content, $is_preview, $post_id, $wp_block, $context ): void {
		$params      = wp_parse_args( mailocations_get_query_params(), mailocations_get_query_defaults() );
		$placeholder = get_field( 'placeholder' );
		$placeholder = $placeholder ?: __( 'Enter your address', 'mai-locations' );
		$address     = $params['address'];
		$lat         = $params['lat'];
		$lng         = $params['lng'];
		$distances   = explode( ',', (string) get_field( 'distances' ) );
		$distance    = $params['distance'];
		$units       = (array) get_field( 'units' );
		$unit        = 1 === count( $units ) ? reset( $units ) : $params['units']; // Fallback to default.
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
				// Autocomplete container. PlaceAutocompleteElement is appended here via JS.
				printf( '<div class="mailocations-autocomplete" data-countries="%s" data-placeholder="%s" data-value="%s"></div>',
					implode( ',', $countries ),
					esc_attr( $placeholder ),
					! $is_preview ? esc_attr( $address ) : ''
				);
			echo '</div>';

			// Encode the address.
			$address_encoded = wp_json_encode(
				[
					'address'  => $address,
					'lat'      => $lat,
					'lng'      => $lng,
					'distance' => $distance,
					'unit'     => $unit,
				]
			);

			// Hidden input for $_POST data, outside of input-container so the clear button CSS hides correctly.
			printf( '<input type="hidden" class="mailocations-address" name="mailocations_address" value="%s">', esc_attr( $address_encoded ) );

			// If we have distances.
			if ( $distances ) {
				// If we have multiple units.
				$multiple = count( $units ) > 1;

				// If we have more than one distance.
				if ( count( $distances ) > 1 ) {
					// Distance selector.
					echo '<select class="mailocations-autocomplete-distance" name="mailocations_distance" tabindex="0">';
						foreach ( $distances as $value ) {
							$label    = $multiple ? $value : $value . ' ' . $unit;
							$selected = ! $is_preview && (int) $value === (int) $distance ? ' selected' : '';
							$value    = ! $is_preview ? sprintf( ' value="%s"', $value ) : ''; // Can't have value attribute or React balks.

							printf( '<option %s%s>%s</option>', $value, $selected, $label );
						}
					echo '</select>';

					// Unit selector.
					if ( $units && $multiple ) {
						echo '<select class="mailocations-autocomplete-unit" name="mailocations_unit" tabindex="0">';
							foreach ( $units as $value ) {
								$raw      = $value;
								$value    = ! $is_preview ? sprintf( ' value="%s"', $raw ) : '';
								$selected = ! $is_preview && $raw === $unit ? ' selected' : '';

								printf( '<option %s%s>%s</option>', $value, $selected, $raw );
							}
						echo '</select>';
					}
				}
				// One distance, hidden field if not preview. These are for the JS to use when filtering.
				elseif ( ! $is_preview ) {
					// Distance.
					printf( '<input type="hidden" class="mailocations-autocomplete-distance" name="mailocations_distance" value="%s">', $distance );

					// Unit.
					printf( '<input type="hidden" class="mailocations-autocomplete-unit" name="mailocations_unit" value="%s">', $unit );
				}
			}
		echo '</div>';
	}

	/**
	 * Registers the block's field group.
	 *
	 * TODO: the missing-key message uses the text domain "mailocations". See TODO.md.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	public function register_field_group(): void {
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
						'message'       => ! mailocations_get_option( 'google_api_key' ) ? __( 'Google Maps API key missing!', 'mailocations' ) : '',
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

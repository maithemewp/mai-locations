<?php

declare(strict_types=1);

namespace Mai\Locations\Blocks;

// Prevent direct file access.
defined( 'ABSPATH' ) || die;

/**
 * The filters form block, which wraps the search, filter and button blocks in a form.
 *
 * Was Mai_Locations_Filters_Block in blocks/location-filters/block.php. That name still works,
 * via inc/aliases.php. block.json stays in blocks/location-filters/.
 *
 * @since TBD
 */
class FiltersBlock {

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
		add_action( 'acf/init',                               [ $this, 'register_block' ] );
		add_action( 'admin_post_mailocations_filters',        [ $this, 'post_action' ] );
		add_action( 'admin_post_nopriv_mailocations_filters', [ $this, 'post_action' ] );
	}

	/**
	 * Registers the block.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function register_block(): void {
		register_block_type( MAI_LOCATIONS_PLUGIN_DIR . 'blocks/location-filters',
			[
				'render_callback' => [ $this, 'render_block' ],
			]
		);
	}

	/**
	 * Renders the block.
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
		// Output the form.
		printf( '<form class="mai-locations-filters" method="post" action="%s">', esc_url( admin_url( 'admin-post.php' ) ) );
			// Hidden inputs and nonce.
			echo '<input type="hidden" name="action" value="mailocations_filters">';
			printf( '<input type="hidden" name="redirect" value="%s">', esc_attr( mailocations_get_current_url_clean() ) );
			wp_nonce_field( 'mailocations_filters', 'mailocations_filters_nonce' );

			// Inner blocks.
			printf( '<InnerBlocks template="%s" />', esc_attr( wp_json_encode( $this->get_template() ) ) );
		echo '</form>';
	}

	/**
	 * Handles the filter form submission, and redirects with the chosen filters as query args.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	public function post_action(): void {
		// Bail if not a valid request.
		if ( ! ( isset( $_POST['mailocations_filters_nonce'] ) && wp_verify_nonce( $_POST['mailocations_filters_nonce'], 'mailocations_filters' ) ) ) {
			return;
		}

		// Get existing query strings and new values.
		$redirect = isset( $_POST['redirect'] ) ? esc_url_raw( $_POST['redirect'] ) : '';
		$filters  = isset( $_POST['mailocations_filters'] ) ? (array) $_POST['mailocations_filters'] : [];
		$location = isset( $_POST['mailocations_address'] ) ? (array) json_decode( stripslashes( $_POST['mailocations_address'] ), true ) : [];
		$address  = isset( $location['address'] ) && $location['address'] ? $location['address'] : '';
		$lat      = isset( $location['lat'] ) && $location['lat'] ? $location['lat'] : '';
		$lng      = isset( $location['lng'] ) && $location['lng'] ? $location['lng'] : '';
		$distance = isset( $_POST['mailocations_distance'] ) ? absint( $_POST['mailocations_distance'] ) : 100;
		$unit     = isset( $_POST['mailocations_unit'] ) ? sanitize_text_field( $_POST['mailocations_unit'] ) : 'mi';
		$args     = $filters;

		// Bail if no redirect.
		if ( ! $redirect ) {
			return;
		}

		// If address, add it.
		if ( $address ) {
			$args['address']  = $address;
		}

		// If address, lat, and lng, add it all.
		if ( $address && $lat && $lng ) {
			$args['lat']      = $lat;
			$args['lng']      = $lng;
			$args['distance'] = $distance;
			$args['unit']     = $unit;
		}

		// Remove empty values.
		$args = array_filter( $args );

		// Build new url.
		$redirect = add_query_arg( $args, $redirect );

		// Redirect. Don't `esc_url()` as this was screwing up the query string.
		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Gets the inner block template.
	 *
	 * @since TBD
	 *
	 * @return array<int, mixed>
	 */
	public function get_template() {
		return [
			[
				'acf/mai-locations-address-search',
				[
					'data' => [
						'distances' => '25, 50, 100, 200',
						'countries' => [ 'US' ],
					],
				],
				[],
			],
			[
				'core/spacer',
				[ 'height' => '16px' ],
				[],
			],
			[
				'acf/mai-locations-filter',
				[
					'data' => [
						'filter' => 'mai_location_cat',
						'type'   => 'select',
					],
				],
				[],
			],
			[
				'core/spacer',
				[ 'height' => '10px' ],
				[],
			],
			[
				'core/buttons',
				[
					'layout' => [
						'type'           => 'flex',
						'orientation'    => 'vertical',
						'justifyContent' => 'center',
					]
				],
				[
					[
						'core/button',
						[
							'maiLocationsFilterSubmit' => true,
							'text'                     => __( 'Search/Filter', 'mai-locations' ),
							'width'                    => 100,
							'metadata'                 => [
								'bindings' => [
									'url' => [
										'source' => 'mai/locations',
										'args'   => [
											'key' => 'filterSubmit',
										],
									],
								],
							],
							'lock'                     => [
								'move'   => false,
								'remove' => true,
							],
						],
						[],
					],
					[
						'core/button',
						[
							'maiLocationsFilterClear' => true,
							'text'                    => __( 'Clear Filters', 'mai-locations' ),
							'className'               => 'is-style-link',
							'fontSize'                => 'sm',
							'style'                   => [
								'color' => [
									'text' => '#ff0000'
								],
								'elements' => [
									'link' => [
										'color' => [
											'text' => '#ff0000'
										],
									],
								],
							],
							'metadata'               => [
								'bindings' => [
									'url' => [
										'source' => 'mai/locations',
										'args'   => [
											'key' => 'filterClear',
										],
									],
								],
							],
							'lock'     => [
								'move'   => false,
								'remove' => true,
							],
						],
						[],
					],
				],
			],
		];
	}
}

<?php

// Prevent direct file access.
defined( 'ABSPATH' ) || die;

/**
 * The locations map block class.
 *
 * @since TBD
 */
class Mai_Locations_Map_Block {
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
		// Maybe enqueue scripts.
		if ( ! $is_preview ) {
			wp_enqueue_script( 'mai-locations-markerclusterer' );
			wp_enqueue_script( 'mai-locations' );
		}

		// Maybe load CSS.
		echo mailocations_get_stylesheet_link( 'mai-locations' );

		// Values.
		$q_default  = get_field( 'query' );
		$q_filtered = get_field( 'query_filtered' );
		$width      = get_field( 'width' );
		$width      = $width ? absint( $width ) : 800;
		$height     = get_field( 'height' );
		$height     = $height ? absint( $height ) : 533;

		// Back end.
		if ( $is_preview ) {
			// Static image.
			printf( '<div style="aspect-ratio:%s/%s;"><img style="display:block;height:100%%;width:100%%;position:absolute;top:0;left:0;object-fit:cover" width="%s" height="%s" src="%s/assets/images/map.png"/></div>', $width, $height, $width, $height, MAI_LOCATIONS_PLUGIN_URL );

			// Bail.
			return;
		}

		global $wp_query;

		// If showing all.
		$filtered = mailocations_is_filtered_locations();
		$show_all = false;
		$show_all = $show_all || ( ! $filtered && $q_default && 'all' === $q_default );
		$show_all = $show_all || ( $filtered && $q_filtered && 'all' === $q_filtered );

		// If showing all.
		if ( $show_all ) {
			// Get filtered args.
			$filtered_args = mailocations_get_filtered_query_args( $wp_query->query );

			// Add new args.
			$filtered_args['fields']                 = 'ids';
			$filtered_args['nopaging']               = true;
			$filtered_args['no_found_rows']          = true;
			$filtered_args['update_post_meta_cache'] = false;
			$filtered_args['update_post_term_cache'] = false;

			// Remove `posts_per_page`.
			unset( $filtered_args['posts_per_page'] );

			// Build transient key.
			$transient_key = 'mai_locations_markers_' . md5( serialize( $filtered_args ) );

			// Check transient.
			if ( false === ( $markers = get_transient( $transient_key ) ) ) {
				// Get posts as array of ids.
				$new   = new WP_Query( $filtered_args );
				$posts = $new->posts;

				// Get markers.
				$markers = $this->get_markers( $posts );

				// Reset post data.
				wp_reset_postdata();

				// Set transient.
				set_transient( $transient_key, $markers, 1 * HOUR_IN_SECONDS );
			}
		}
		// Use existing query.
		else {
			// Get posts as array of ids.
			$posts = (array) $wp_query->posts;
			$posts = array_map( function( $post ) {
				return $post->ID;
			}, $posts );

			// Get markers.
			$markers = $this->get_markers( $posts );
		}

		// Open map.
		printf( '<div style="aspect-ratio:%s/%s;" class="mailocations-map" data-zoom="%s">', $width, $height, 7 );

		// If markers.
		if ( $markers ) {
			// Loop through and build markers.
			foreach ( $markers as $marker ) {
				printf( '<div style="display:none;" class="marker" data-lat="%s" data-lng="%s">', esc_html( $marker['lat'] ), esc_html( $marker['lng'] ) );
					printf( '<strong><a href="%s">%s</a></strong>', $marker['href'], $marker['title'] );
					echo $marker['address'];
				echo '</div>';
			}
		}

		// Close map.
		echo '</div>';
	}

	/**
	 * Get markers data.
	 *
	 * @since TBD
	 *
	 * @param array $posts Array of post ids.
	 *
	 * @return array
	 */
	function get_markers( $posts ) {
		$data = [];

		// Loop through posts to build marker data.
		foreach ( $posts as $post_id ) {
			$lat = get_post_meta( $post_id, 'location_lat', true );
			$lng = get_post_meta( $post_id, 'location_lng', true );

			// Skip if we don't have the data we want.
			if ( ! ( $lat && $lng ) ) {
				continue;
			}

			// Add to data array.
			$data[] = [
				'lat'     => $lat,
				'lng'     => $lng,
				'href'    => get_permalink( $post_id ),
				'title'   => get_the_title( $post_id ),
				'address' => mailocations_get_address( [], $post_id ),
			];
		}

		return $data;
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
				'key'    => 'mailocations_locations_map_field_group',
				'title'  => __( 'Mai Locations Map', 'mai-locations' ),
				'fields' => [
					[
						'key'          => 'mailocations_map_query',
						'label'        => __( 'Locations to show by default', 'mai-locations' ),
						'name'         => 'query',
						'type'         => 'select',
						'choices'      => [
							''    => __( 'Current page', 'mai-locations' ),
							'all' => __( 'All Locations', 'mai-locations' ),
						],
					],
					[
						'key'          => 'mailocations_map_query_filtered',
						'label'        => __( 'Locations to show when filtered', 'mai-locations' ),
						'name'         => 'query_filtered',
						'type'         => 'select',
						'choices'      => [
							''    => __( 'Current page', 'mai-locations' ),
							'all' => __( 'All Locations', 'mai-locations' ),
						],
					],
					[
						'key'         => 'mailocations_map_width',
						'label'       => __( 'Width', 'mai-locations' ),
						'name'        => 'width',
						'type'        => 'number',
						'min'         => 1,
						'step'        => 1,
						'placeholder' => 800,
					],
					[
						'key'         => 'mailocations_map_height',
						'label'       => __( 'Height', 'mai-locations' ),
						'name'        => 'height',
						'type'        => 'number',
						'min'         => 1,
						'step'        => 1,
						'placeholder' => 533,
					],
					[
						'key'         => 'mailocations_map_description',
						'type'        => 'message',
						'message'     => __( 'Width and height values are used to set the aspect ratio and prevent CLS.', 'mai-locations' ),
					],
				],
				'location' => [
					[
						[
							'param'    => 'block',
							'operator' => '==',
							'value'    => 'acf/mai-locations-map',
						],
					],
				],
			]
		);
	}
}
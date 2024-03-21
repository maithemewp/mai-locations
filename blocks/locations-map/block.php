<?php

// Prevent direct file access.
defined( 'ABSPATH' ) || die;

class Mai_Locations_Locations_Map_Block {
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
		$get    = get_field( 'query' );
		$width  = get_field( 'width' );
		$width  = $width ? absint( $width ) : 800;
		$height = get_field( 'height' );
		$height = $height ? absint( $height ) : 533;

		// Back end.
		if ( $is_preview ) {
			// Static image.
			printf( '<div style="aspect-ratio:%s/%s;"><img style="display:block;height:100%%;width:100%%;position:absolute;top:0;left:0;object-fit:cover" width="%s" height="%s" src="%s/assets/images/map.png"/></div>', $width, $height, $width, $height, MAI_LOCATIONS_PLUGIN_URL );
		}
		// Front end.
		else {
			global $wp_query;

			// Get all posts.
			$posts = $wp_query->posts;

			// If showing all.
			if ( $get && 'all' === $get && ! mailocations_is_filtered_locations() ) {
				$args             = $wp_query->query;
				$args['nopaging'] = true;

				unset( $args['posts_per_page'] );

				$all   = new WP_Query( $args );
				$posts = $all->posts;

				wp_reset_postdata();
			}

			// Open map.
			printf( '<div style="aspect-ratio:%s/%s;" class="mailocations-map" data-zoom="%s">', $width, $height, 7 );

			// ray( $posts );

			// If posts.
			if ( $posts ) {
				// Loop through posts to build markers.
				foreach ( $posts as $post ) {
					$lat = get_post_meta( $post->ID, 'location_lat', true );
					$lng = get_post_meta( $post->ID, 'location_lng', true );

					// Skip if we don't have the data we want.
					if ( ! ( $lat && $lng ) ) {
						continue;
					}

					printf( '<div style="display:none;" class="marker" data-lat="%s" data-lng="%s">', esc_html( $lat ), esc_html( $lng ) );
						printf( '<strong><a href="%s">%s</a></strong>', get_permalink( $post->ID ), get_the_title( $post->ID ) );
						echo mailocations_get_address( [], $post->ID );
					echo '</div>';
				}
			}

			// Close map.
			echo '</div>';
		}
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
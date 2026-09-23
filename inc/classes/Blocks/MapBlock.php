<?php

declare(strict_types=1);

namespace Mai\Locations\Blocks;

use WP_Query;

// Prevent direct file access.
defined( 'ABSPATH' ) || die;

/**
 * The locations map block.
 *
 * Was Mai_Locations_Map_Block in blocks/location-map/block.php. That name still works, via
 * inc/aliases.php. block.json stays in blocks/location-map/.
 *
 * @since TBD
 */
class MapBlock {

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
		register_block_type( MAI_LOCATIONS_PLUGIN_DIR . 'blocks/location-map',
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

		// Get the existing query.
		global $wp_query;

		// If showing all.
		$filtered  = mailocations_is_filtered_locations();
		$show_all  = false;
		$show_all  = $show_all || ( ! $filtered && $q_default && 'all' === $q_default );
		$show_all  = $show_all || ( $filtered && $q_filtered && 'all' === $q_filtered );
		$show_none = ! $filtered && 'none' === $q_default;

		// If showing all.
		if ( $show_all ) {
			// Get filtered args.
			$filtered_args = mailocations_get_filtered_query_args( $wp_query->query );

			// "All" means all locations. On any page that is not a location archive the current
			// query has no location post type of its own, so the map used to look up regular
			// posts and find nothing. Fixed September 16, 2026.
			$post_types = array_keys( mailocations_get_location_post_types() );

			// A location query is one that names a location post type, or a location taxonomy.
			// A category archive such as /shop/antiques-and-galleries/ carries only the term,
			// mai_location_cat, and no post_type at all, so checking post_type alone took it for
			// an ordinary page and dropped the term: its map showed all 141 places, not its 5.
			$taxonomies     = array_keys( mailocations_get_location_taxonomies() );
			$names_a_type   = (bool) array_intersect( (array) ( $filtered_args['post_type'] ?? [] ), $post_types );
			$names_a_term   = (bool) array_intersect( array_keys( (array) $wp_query->query ), $taxonomies ) || ( $taxonomies && is_tax( $taxonomies ) );

			if ( ! $names_a_type && ! $names_a_term ) {
				// Not a location query, so nothing in it is worth keeping. On a page it holds
				// pagename or page_id, which asked for "the location with this page's slug" and
				// found none, so the September 16 fix above still drew an empty map on every
				// ordinary page. Start clean, keeping only the visitor's own search and filter
				// params, which come from the request rather than the query. Fixed September 23,
				// 2026.
				$filtered_args              = mailocations_get_filtered_query_args( [] );
				$filtered_args['post_type'] = $post_types;
			} elseif ( ! $names_a_type ) {
				// A location taxonomy archive: keep its term, and name the post types.
				$filtered_args['post_type'] = $post_types;
			}

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
		// If not showing any markers.
		elseif ( $show_none ) {
			// No markers.
			$markers = [];
		}
		// If showing existing query.
		else {
			// Get posts as array of ids.
			$posts = (array) $wp_query->posts;
			$posts = array_map( function ( $post ) {
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
					printf( '<strong style="display:block;margin-bottom:4px;"><a href="%s" target="_blank" rel="noopener nofollow">%s</a></strong>', $marker['href'], $marker['title'] );
					echo $marker['address'];
					printf( '<p style="display:block;margin-top:4px;"><a href="%s" target="_blank" rel="noopener nofollow">%s</a></p>', $marker['directions'], __( 'Get Directions', 'mai-locations' ) );
				echo '</div>';
			}
		}

		// Close map.
		echo '</div>';
	}

	/**
	 * Gets the marker data.
	 *
	 * @since TBD
	 *
	 * @param array<int, int> $posts Array of post ids.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_markers( $posts ) {
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
				'lat'        => $lat,
				'lng'        => $lng,
				'href'       => get_permalink( $post_id ),
				'title'      => get_the_title( $post_id ),
				'address'    => mailocations_get_address( [], $post_id ),
				'directions' => "https://www.google.com/maps/dir/?api=1&destination={$lat},$lng"
			];
		}

		return $data;
	}

	/**
	 * Registers the block's field group.
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
				'key'    => 'mailocations_locations_map_field_group',
				'title'  => __( 'Mai Locations Map', 'mai-locations' ),
				'fields' => [
					[
						'key'          => 'mailocations_map_query',
						'label'        => __( 'Locations to show by default', 'mai-locations' ),
						'name'         => 'query',
						'type'         => 'select',
						'choices'      => [
							''     => __( 'Current page', 'mai-locations' ),
							'all'  => __( 'All Locations', 'mai-locations' ),
							'none' => __( 'None', 'mai-locations' ),
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

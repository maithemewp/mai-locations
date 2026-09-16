<?php

declare(strict_types=1);

namespace Mai\Locations;

use WP_Query;

// Prevent direct file access.
defined( 'ABSPATH' ) || die;

/**
 * Changes the location archive query.
 *
 * Was Mai_Locations_Queries in classes/class-locations-queries.php. That name still works,
 * via inc/aliases.php.
 *
 * @since TBD
 */
class Queries {

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
		add_action( 'pre_get_posts', [ $this, 'pre_get_posts_query' ] );
		// add_filter( 'mai_post_grid_query_args', [ $this, 'mai_post_grid_query' ], 10, 2 );
	}

	/**
	 * Filters the location archive page query.
	 *
	 * @since TBD
	 *
	 * @param WP_Query $query The query.
	 *
	 * @return void
	 */
	public function pre_get_posts_query( $query ): void {
		// Bail if in the Dashboard.
		if ( is_admin() ) {
			return;
		}

		// Bail if not the main query.
		if ( ! $query->is_main_query() ) {
			return;
		}

		// Bail if not a location archive page.
		if ( ! mailocations_is_archive() ) {
			return;
		}

		// If filtered.
		if ( mailocations_is_filtered_locations() ) {
			// Get filtered args.
			$filtered_args = mailocations_get_filtered_query_args();

			// Bail if no filtered args.
			if ( ! $filtered_args ) {
				return;
			}

			// Loop through filtered args and set query args.
			foreach ( $filtered_args as $key => $value ) {
				$query->set( $key, $value );
			}
		}
		// Not filtered.
		else {
			$query->set( 'orderby', 'title' );
			$query->set( 'order', 'ASC' );
		}
	}

	/**
	 * Modifies Mai Post Grid args with filter arguments.
	 *
	 * Dead code: its hook is commented out above, and it calls
	 * mailocations_get_geo_query_args(), which does not exist, so it fatals for a location post
	 * type. Carried over unchanged, with its tests, until deleting it is agreed.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $query_args WP_Query args.
	 * @param array<string, mixed> $args       Mai Post Grid block args.
	 *
	 * @return array<string, mixed>
	 */
	public function mai_post_grid_query( $query_args, $args ) {
		// Check if post types intersect.
		$post_types = mailocations_get_location_post_types();
		$post_types = array_keys( $post_types );
		$post_types = array_intersect( $post_types, (array) $query_args['post_type'] );

		// Bail if no post types.
		if ( ! $post_types ) {
			return $query_args;
		}

		$query_args = mailocations_get_geo_query_args( $query_args );

		return $query_args;
	}
}

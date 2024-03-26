<?php

// Prevent direct file access.
defined( 'ABSPATH' ) || die;

class Mai_Locations_Queries {
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
		add_action( 'pre_get_posts', [ $this, 'pre_get_posts_query' ] );
		// add_filter( 'mai_post_grid_query_args', [ $this, 'mai_post_grid_query' ], 10, 2 );
	}


	/**
	* Filters the location archive page query.
	*
	* @since TBD
	*
	* @return void
	*/
	function pre_get_posts_query( $query ) {
		// Bail if in the Dashboard.
		if ( is_admin() ) {
			return;
		}

		// Bail if not the main query.
		if ( ! $query->is_main_query() ) {
			return;
		}

		// Bail if not a location archive page.
		if ( ! ( is_post_type_archive( 'mai_location' ) || is_tax( array_keys( mailocations_get_location_taxonomies() ) ) ) ) {
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
	 * Modify Mai Post Grid args with filter arguments.
	 * TODO: Add setting for when to hijack Mai Post Grid.
	 *
	 * @since TBD
	 *
	 * @param array $query_args WP_Query args
	 * @param array $args       Mai Post Grid block args.
	 *
	 * @return array
	 */
	function mai_post_grid_query( $query_args, $args ) {
		// Bail if not a location.
		if ( ! in_array( 'mai_location', (array) $query_args['post_type'] ) ) {
			return $query_args;
		}

		$query_args = mailocations_get_geo_query_args( $query_args );

		return $query_args;
	}
}

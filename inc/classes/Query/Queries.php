<?php

declare(strict_types=1);

namespace Mai\Locations\Query;

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

}

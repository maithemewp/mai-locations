<?php

declare(strict_types=1);

namespace Mai\Locations\Query;

// Prevent direct file access.
defined( 'ABSPATH' ) || die;

/**
 * Distance searching and sorting for WP_Query, through a `geo_query` argument.
 *
 * Was Mai_Geo_Query in classes/class-geo-query.php. That name still works, via
 * inc/aliases.php. The instance is started from the plugin bootstrap now, rather than by the
 * class file loading itself.
 *
 * Originally taken from GJSGeoQuery.
 *
 * @link https://gist.github.com/akshuvo/4c37df4bd128eb801b7739748ee3cd65
 * @link https://gschoppe.com/wordpress/geo-searches/
 *
 * Example:
 *
 * $query = new WP_Query(
 *     [
 *         'geo_query' => [
 *             'lat_field' => 'location_lat', // meta field holding latitude
 *             'lng_field' => 'location_lng', // meta field holding longitude
 *             'latitude'  => 44.485261,      // latitude of the point to measure from
 *             'longitude' => -73.218952,     // longitude of the point to measure from
 *             'distance'  => 20,             // maximum distance to search
 *             'units'     => 'miles',        // miles, mi, kilometers, km
 *         ],
 *         'orderby' => 'distance',
 *         'order'   => 'ASC',
 *     ]
 * );
 *
 * @since 0.1.0
 */
class GeoQuery {

	/**
	 * Gets the one instance, creating it on the first call.
	 *
	 * @since 0.1.0
	 *
	 * @return self
	 */
	public static function instance(): self {
		static $instance = null;

		if ( is_null( $instance ) ) {
			$instance = new self();
		}

		return $instance;
	}

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 */
	private function __construct() {
		add_filter( 'posts_fields',  [ $this, 'posts_fields' ], 10, 2 );
		add_filter( 'posts_join',    [ $this, 'posts_join' ], 10, 2 );
		add_filter( 'posts_where',   [ $this, 'posts_where' ], 10, 2 );
		add_filter( 'posts_orderby', [ $this, 'posts_orderby' ], 10, 2 );
	}

	/**
	 * Gets the distance from a post object.
	 *
	 * @since 0.1.0
	 *
	 * @param \WP_Post|null $post_obj The post object.
	 * @param bool|int      $round    Decimal places, or false for none.
	 *
	 * @return float|string|false Rounded float, the raw MySQL string when $round is false, or
	 *                            false when the post has no distance.
	 */
	public static function get_distance( $post_obj = null, $round = false ) {
		global $post;

		$post_obj = $post_obj ?: $post;

		if ( ! property_exists( $post_obj, 'geo_query_distance' ) ) {
			return false;
		}

		$distance = $post_obj->geo_query_distance;

		if ( false !== $round ) {
			// Cast here, not above. MySQL hands the computed column back as a string, and under
			// declare(strict_types=1) round() refuses it rather than coercing as it used to.
			// Casting above would change the unrounded return from that string to a float, which
			// is a behaviour change, not a move.
			$distance = round( (float) $distance, (int) $round );
		}

		return $distance;
	}

	/**
	 * Adds a calculated distance to the SELECT clause, using a haversine formula.
	 *
	 * @since 0.1.0
	 *
	 * @param string    $sql   The SELECT clause of the query.
	 * @param \WP_Query $query The WP_Query instance.
	 *
	 * @return string
	 */
	public function posts_fields( $sql, $query ) {
		$geo_query = $query->get( 'geo_query' );

		if ( ! $geo_query ) {
			return $sql;
		}

		if ( $sql ) {
			$sql .= ', ';
		}

		$sql .= $this->haversine_term( $geo_query ) . ' AS geo_query_distance';

		return $sql;
	}

	/**
	 * Joins the postmeta table twice, once for latitude and once for longitude.
	 *
	 * @since 0.1.0
	 *
	 * @param string    $sql   The JOIN clause of the query.
	 * @param \WP_Query $query The WP_Query instance.
	 *
	 * @return string
	 */
	public function posts_join( $sql, $query ) {
		global $wpdb;

		$geo_query = $query->get( 'geo_query' );

		if ( ! $geo_query ) {
			return $sql;
		}

		if ( $sql ) {
			$sql .= ' ';
		}

		$sql .= "INNER JOIN " . $wpdb->prefix . "postmeta AS geo_query_lat ON ( " . $wpdb->prefix . "posts.ID = geo_query_lat.post_id ) ";
		$sql .= "INNER JOIN " . $wpdb->prefix . "postmeta AS geo_query_lng ON ( " . $wpdb->prefix . "posts.ID = geo_query_lng.post_id ) ";

		return $sql;
	}

	/**
	 * Adds a WHERE clause filtering by distance.
	 *
	 * @since 0.1.0
	 *
	 * @param string    $sql   The WHERE clause of the query.
	 * @param \WP_Query $query The WP_Query instance.
	 *
	 * @return string
	 */
	public function posts_where( $sql, $query ) {
		global $wpdb;

		$geo_query = $query->get( 'geo_query' );

		if ( ! $geo_query ) {
			return $sql;
		}

		$lat_field = 'location_lat';
		$lng_field = 'location_lng';
		$distance  = 0;

		if ( ! empty( $geo_query['lat_field'] ) ) {
			$lat_field = $geo_query['lat_field'];
		}

		if ( ! empty( $geo_query['lng_field'] ) ) {
			$lng_field = $geo_query['lng_field'];
		}

		if ( isset( $geo_query['distance'] ) ) {
			$distance = $geo_query['distance'];
		}

		if ( $sql ) {
			$sql .= " AND ";
		}

		$haversine = $this->haversine_term( $geo_query );
		// With no limit the haversine term used to be the condition on its own, and a location at
		// exactly the search point computes to 0, which MySQL reads as false. Fixed September 16,
		// 2026.
		$additional = $distance ? ' <= %f' : ' >= 0';
		$new_sql    = "( geo_query_lat.meta_key = %s AND geo_query_lng.meta_key = %s AND {$haversine}{$additional} )";

		if ( $distance ) {
			$sql .= $wpdb->prepare( $new_sql, $lat_field, $lng_field, $distance );
		} else {
			$sql .= $wpdb->prepare( $new_sql, $lat_field, $lng_field );
		}

		return $sql;
	}

	/**
	 * Orders the query by distance.
	 *
	 * @since 0.1.0
	 *
	 * @param string    $sql   The ORDER BY clause of the query.
	 * @param \WP_Query $query The WP_Query instance.
	 *
	 * @return string
	 */
	public function posts_orderby( $sql, $query ) {
		$geo_query = $query->get( 'geo_query' );

		if ( ! $geo_query ) {
			return $sql;
		}

		$orderby = $query->get( 'orderby' );
		$order   = $query->get( 'order' );

		if ( 'distance' === $orderby ) {
			// The concatenation used to bind before ?:, so an empty order gave a trailing space
			// instead of ASC. The direction goes straight into SQL, so only the two keywords are
			// accepted. Fixed September 16, 2026.
			$sql = 'geo_query_distance ' . ( 'DESC' === strtoupper( (string) $order ) ? 'DESC' : 'ASC' );
		}

		return $sql;
	}

	/**
	 * Builds the haversine term for a given geo query.
	 *
	 * @since 0.1.0
	 *
	 * @param array<string, mixed> $geo_query The geo query array.
	 *
	 * @return string
	 */
	private function haversine_term( $geo_query ) {
		global $wpdb;

		$units = 'miles';

		// Maybe set units.
		if ( ! empty( $geo_query['units'] ) ) {
			$units = strtolower( $geo_query['units'] );
		}

		// Radius in miles.
		$radius = 3959;

		// Radius in kilometers.
		if ( in_array( $units, [ 'km', 'kilometers' ] ) ) {
			$radius = 6371;
		}

		$lat_field = "geo_query_lat.meta_value";
		$lng_field = "geo_query_lng.meta_value";
		$lat       = 0;
		$lng       = 0;

		// Maybe add latitude.
		if ( isset( $geo_query['latitude'] ) ) {
			$lat = $geo_query['latitude'];
		}

		// Maybe add longitude.
		if ( isset( $geo_query['longitude'] ) ) {
			$lng = $geo_query['longitude'];
		}

		// Build the haversine formula.
		$haversine  = "( " . $radius . " * ";
		$haversine .=     "acos( cos( radians(%f) ) * cos( radians( " . $lat_field . " ) ) * ";
		$haversine .=     "cos( radians( " . $lng_field . " ) - radians(%f) ) + ";
		$haversine .=     "sin( radians(%f) ) * sin( radians( " . $lat_field . " ) ) ) ";
		$haversine .= ")";
		$haversine  = $wpdb->prepare( $haversine, [ $lat, $lng, $lat ] );

		return $haversine;
	}
}

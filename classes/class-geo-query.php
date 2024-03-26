<?php

/**
 * Originally taken from GJSGeoQuery.
 *
 * @link https://gist.github.com/akshuvo/4c37df4bd128eb801b7739748ee3cd65
 * @link https://gschoppe.com/wordpress/geo-searches/
 */

// $query = new WP_Query(
// 	[
// 		// Include other WP_Query args as usual.
// 		'geo_query' => [
// 			'lat_field' => '_latitude',  // this is the name of the meta field storing latitude
// 			'lng_field' => '_longitude', // this is the name of the meta field storing longitude
// 			'latitude'  => 44.485261,    // this is the latitude of the point we are getting distance from
// 			'longitude' => -73.218952,   // this is the longitude of the point we are getting distance from
// 			'distance'  => 20,           // this is the maximum distance to search
// 			'units'     => 'miles'       // this supports options: miles, mi, kilometers, km
// 		],
// 		'orderby' => 'distance', // this tells WP Query to sort by distance
// 		'order'   => 'ASC'
// 	]
// );

Mai_Geo_Query::instance();

class Mai_Geo_Query {
	public static function instance() {
		static $instance = null;

		if ( is_null( $instance ) ) {
			$instance = new self();
		}

		return $instance;
	}

	private function __construct() {
		add_filter( 'posts_fields',  [ $this, 'posts_fields' ], 10, 2 );
		add_filter( 'posts_join',    [ $this, 'posts_join' ], 10, 2 );
		add_filter( 'posts_where',   [ $this, 'posts_where' ], 10, 2 );
		add_filter( 'posts_orderby', [ $this, 'posts_orderby' ], 10, 2 );
	}

	// add a calculated "distance" parameter to the sql query, using a haversine formula
	public function posts_fields( $sql, $query ) {
		global $wpdb;

		$geo_query = $query->get( 'geo_query' );

		if ( ! $geo_query ) {
			return $sql;
		}

		if ( $sql ) {
			$sql .= ', ';
		}

		$sql .= $this->haversine_term( $geo_query ) . " AS geo_query_distance";

		return $sql;
	}

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

	// match on the right metafields, and filter by distance
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

		$haversine  = $this->haversine_term( $geo_query );
		$additional = $distance ? ' <= %f' : '';
		$new_sql    = "( geo_query_lat.meta_key = %s AND geo_query_lng.meta_key = %s AND {$haversine}{$additional} )";
		// $new_sql   = "( geo_query_lat.meta_key = %s AND geo_query_lng.meta_key = %s AND " . $haversine . " <= %f )";

		if ( $distance ) {
			$sql .= $wpdb->prepare( $new_sql, $lat_field, $lng_field, $distance );
		} else {
			$sql .= $wpdb->prepare( $new_sql, $lat_field, $lng_field );
		}

		return $sql;
	}

	// handle ordering
	public function posts_orderby( $sql, $query ) {
		$geo_query = $query->get( 'geo_query' );

		if ( ! $geo_query ) {
			return $sql;
		}

		$orderby = $query->get( 'orderby' );
		$order   = $query->get( 'order' );

		if ( 'distance' === $orderby ) {
			$sql = 'geo_query_distance ' . $order ?: 'ASC';
		}

		return $sql;
	}

	public static function get_distance( $post_obj = null, $round = false ) {
		global $post;

		$post_obj = $post_obj ?: $post;

		if ( ! property_exists( $post_obj, 'geo_query_distance' ) ) {
			return false;
		}

		$distance = $post_obj->geo_query_distance;

		if ( false !== $round ) {
			$distance = round( $distance, (int) $round );
		}

		return $distance;
	}

	private function haversine_term( $geo_query ) {
		global $wpdb;

		$units = 'miles';

		if ( ! empty( $geo_query['units'] ) ) {
			$units = strtolower( $geo_query['units'] );
		}

		$radius = 3959;

		if ( in_array( $units, array( 'km', 'kilometers' ) ) ) {
			$radius = 6371;
		}

		$lat_field = "geo_query_lat.meta_value";
		$lng_field = "geo_query_lng.meta_value";
		$lat       = 0;
		$lng       = 0;

		if ( isset( $geo_query['latitude'] ) ) {
			$lat = $geo_query['latitude' ];
		}

		if ( isset( $geo_query['longitude'] ) ) {
			$lng = $geo_query['longitude'];
		}

		$haversine  = "( " . $radius . " * ";
		$haversine .=     "acos( cos( radians(%f) ) * cos( radians( " . $lat_field . " ) ) * ";
		$haversine .=     "cos( radians( " . $lng_field . " ) - radians(%f) ) + ";
		$haversine .=     "sin( radians(%f) ) * sin( radians( " . $lat_field . " ) ) ) ";
		$haversine .= ")";
		$haversine  = $wpdb->prepare( $haversine, array( $lat, $lng, $lat ) );

		return $haversine;
	}
}
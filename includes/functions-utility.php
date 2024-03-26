<?php

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Gets the post type plural label.
 *
 * @since 0.1.0
 *
 * @return string
 */
function mailocations_get_plural() {
	static $label = null;

	if ( ! is_null( $label ) ) {
		return $label;
	}

	$label = mailocations_get_option( 'label_plural' );
	$label = apply_filters( 'mailocations_plural', $label );

	return esc_html( $label );
}

/**
 * Gets the post type singular label.
 *
 * @since 0.1.0
 *
 * @return string
 */
function mailocations_get_singular() {
	static $label = null;

	if ( ! is_null( $label ) ) {
		return $label;
	}

	$label = mailocations_get_option( 'label_singular' );
	$label = apply_filters( 'mailocations_singular', $label );

	return esc_html( $label );
}

/**
 * Gets the post type base for urls.
 *
 * @since 0.1.0
 *
 * @return string
 */
function mailocations_get_base() {
	static $base = null;

	if ( ! is_null( $base ) ) {
		return $base;
	}

	$base = mailocations_get_option( 'base' );
	$base = apply_filters( 'mailocations_base', $base );

	return sanitize_html_class( $base );
}

/**
 * Gets a single option value by key.
 *
 * @since TBD
 *
 * @param string $key      The option key.
 * @param mixed  $fallback Fallback value if option doesn't exist.
 *
 * @return mixed
 */
function mailocations_get_option( $key, $fallback = true ) {
	$defaults = mailocations_get_options_defaults();
	$options  = mailocations_get_options();
	$return   = isset( $options[ $key ] ) && '' !== $options[ $key ] && ! is_null( $options[ $key ] ) ? $options[ $key ] : null;

	return is_null( $return ) && $fallback ? $defaults[ $key ] : $return;
}

/**
 * Gets all options.
 *
 * @since TBD
 *
 * @return array
 */
function mailocations_get_options() {
	static $cache = null;

	if ( ! is_null( $cache ) ) {
		return $cache;
	}

	// Get all options, with defaults if option does not exist.
	$options = (array) get_option( 'mai_locations', mailocations_get_options_defaults() );

	// Sanitize.
	$cache = mailocations_sanitize_options( $options );

	return $cache;
}

/**
 * Gets a single option default value by key.
 *
 * @since TBD
 *
 * @param string $key The option key.
 *
 * @return mixed
 */
function mailocations_get_option_default( $key ) {
	$defaults = mailocations_get_options_defaults();

	return $defaults[ $key ];
}

/**
 * Gets default options.
 *
 * @since TBD
 *
 * @return array
 */
function mailocations_get_options_defaults() {
	static $cache = null;

	if ( ! is_null( $cache ) ) {
		return $cache;
	}

	// Set cache.
	$cache = [
		'label_plural'   => __( 'Locations', 'mai-location' ),
		'label_singular' => __( 'Location', 'mai-location' ),
		'base'           => 'locations',
		'category_base'  => 'location-category',
		'distance'       => 100,
		'units'          => 'mi',
		'version_first'  => '',
		'version_db'     => '',
	];

	return $cache;
}

/**
 * Update a single option from mai_locations array of options.
 *
 * @since TBD
 *
 * @param string $option Option name.
 * @param mixed  $value  Option value.
 *
 * @return void
 */
function mailocations_update_option( $option, $value ) {
	$options            = (array) get_option( 'mai_locations', [] );
	$options[ $option ] = $value;

	update_option( 'mai_locations', $options );
}

/**
 * Parses and sanitize all options.
 * Not cached for use when saving values in settings page.
 *
 * @since TBD
 *
 * @return array
 */
function mailocations_sanitize_options( $options ) {
	// Parse.
	$options = wp_parse_args( $options, [
		'label_plural'   => '',
		'label_singular' => '',
		'base'           => '',
		'category_base'  => '',
		'distance'       => '',
		'units'          => '',
		'version_first'  => '',
		'version_db'     => '',
	] );

	// Sanitize.
	$options['label_plural']   = sanitize_text_field( $options['label_plural'] );
	$options['label_singular'] = sanitize_text_field( $options['label_singular'] );
	$options['base']           = sanitize_title_with_dashes( $options['base'] );
	$options['category_base']  = sanitize_title_with_dashes( $options['category_base'] );
	$options['distance']       = absint( $options['distance'] );
	$options['units']          = esc_html( $options['units'] );
	$options['version_first']  = esc_html( $options['version_first'] );
	$options['version_db']     = esc_html( $options['version_db'] );

	return $options;
}

/**
 * Deletes all transient keys in the database with `mai_locations`.
 *
 * Note that this doesn't work for sites that use a persistent object
 * cache, since in that case, transients are stored in memory.
 *
 * @since TBD
 *
 * @link https://gist.github.com/kellenmace/7d8f3b4c48cef3fd68ebc8606415d7dd
 *
 * @param string $prefix Prefix to search for.
 *
 * @return array Transient keys with prefix, or empty array on error.
 */
function mailocations_delete_transients() {
	global $wpdb;

	$prefix = 'mai_locations';
	$prefix = $wpdb->esc_like( '_transient_' . $prefix );
	$sql    = "SELECT `option_name` FROM $wpdb->options WHERE `option_name` LIKE '%s'";
	$keys   = $wpdb->get_results( $wpdb->prepare( $sql, $prefix . '%' ), ARRAY_A );

	// Bail if no keys or error.
	if ( ! $keys || is_wp_error( $keys ) ) {
		return;
	}

	// Get all transient keys.
	$transients = array_map( function( $key ) {
		// Remove '_transient_' from the option name.
		return substr( $key['option_name'], strlen( '_transient_' ) );
	}, $keys );

	// Loop through and delete.
	foreach ( $transients as $key ) {
		delete_transient( $key );
	}
}

/**
 * Determines if a post exists in the DB.
 *
 * @since 0.1.0
 *
 * @param int $post_id The post ID.
 *
 * @return bool True if the post exists; otherwise, false.
 */
function mailocations_post_exists( $post_id ) {
	return is_string( get_post_status( $post_id ) );
}

/**
 * Gets ACF Google Maps API key.
 * May be set multiple ways.
 *
 * @since 0.1.0
 *
 * @link https://www.advancedcustomfields.com/blog/google-maps-api-settings/
 *
 * @return string
 */
function mailocations_get_google_maps_api_key() {
	static $key = null;

	if ( ! is_null( $key ) ) {
		return $key;
	}

	$key = '';

	if ( function_exists( 'acf_get_setting' ) ) {
		$key = acf_get_setting( 'google_api_key' );
	}

	return $key;
}

/**
 * If user can edit a location by ID.
 *
 * @since 0.1.0
 *
 * @param int $location_id The post ID.
 *
 * @return bool
 */
function mailocations_user_can_edit( $location_id ) {
	if ( ! is_user_logged_in() ) {
		return;
	}

	$locations = mailocation_get_user_locations();

	if ( ! $locations ) {
		return;
	}

	return in_array( $location_id, $locations );
}

/**
 * Gets a stylesheet link.
 * Returns empty if the same file was already called,
 * so it's only loaded once on a page.
 *
 * @since TBD
 *
 * @param string $filename
 *
 * @return string
 */
function mailocations_get_stylesheet_link( $filename ) {
	static $loaded = [];

	// Bail if loaded.
	if ( is_admin() || isset( $loaded[ $filename ] ) ) {
		return;
	}

	$suffix              = mailocations_get_suffix();
	$loaded[ $filename ] = MAI_LOCATIONS_PLUGIN_URL . "assets/css/{$filename}{$suffix}.css";

	return sprintf( '<link rel="stylesheet" href="%s" />', $loaded[ $filename ] );
}

/**
 * Gets suffix for scripts.
 *
 * @since TBD
 *
 * @return string
 */
function mailocations_get_suffix() {
	return defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
}

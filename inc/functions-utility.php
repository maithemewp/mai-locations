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

	// An unknown key used to warn here. It returns null now. Fixed September 16, 2026.
	return is_null( $return ) && $fallback ? ( $defaults[ $key ] ?? null ) : $return;
}

/**
 * Gets all options.
 *
 * @since TBD
 *
 * @param bool $reset Whether to drop the cached copy first.
 *
 * @return array
 */
function mailocations_get_options( bool $reset = false ) {
	static $cache = null;

	// The cache lasts the whole request, so a value saved during it was never seen again.
	// mailocations_update_option() clears it now. Fixed September 16, 2026.
	if ( $reset ) {
		$cache = null;
	}

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

	// An unknown key used to warn here. It returns null now. Fixed September 16, 2026.
	return $defaults[ $key ] ?? null;
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
		'label_plural'         => __( 'Locations', 'mai-location' ),
		'label_singular'       => __( 'Location', 'mai-location' ),
		'base'                 => 'locations',
		'category_base'        => 'location-category',
		'google_api_key'       => '',
		'google_api_signature' => '',
		'google_map_id'        => '',
		'distance'             => 100,
		'units'                => 'mi',
		'version_first'        => '',
		'version_db'           => '',
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

	// Drop the cached copy, so a later read in this request sees the new value.
	mailocations_get_options( true );
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
	$defaults = [
		'label_plural'         => '',
		'label_singular'       => '',
		'base'                 => '',
		'category_base'        => '',
		'google_api_key'       => '',
		'google_api_signature' => '',
		'google_map_id'        => '',
		'distance'             => '',
		'units'                => '',
		'version_first'        => '',
		'version_db'           => '',
	];
	$options  = wp_parse_args( $options, $defaults );

	// Sanitize.
	$options['label_plural']         = sanitize_text_field( $options['label_plural'] );
	$options['label_singular']       = sanitize_text_field( $options['label_singular'] );
	$options['base']                 = sanitize_title_with_dashes( $options['base'] );
	$options['category_base']        = sanitize_title_with_dashes( $options['category_base'] );
	$options['google_api_key']       = sanitize_text_field( $options['google_api_key'] );
	$options['google_api_signature'] = sanitize_text_field( $options['google_api_signature'] );
	$options['google_map_id']        = sanitize_text_field( $options['google_map_id'] );
	// A blank distance used to be stored as 0, which means no limit at all. Blank now means the
	// default, while a 0 typed on purpose still means no limit. Fixed September 16, 2026.
	$options['distance']             = '' === trim( (string) $options['distance'] ) ? (int) mailocations_get_option_default( 'distance' ) : absint( $options['distance'] );
	// Only the two units the settings page offers. Anything else used to be stored as typed.
	$options['units']                = in_array( $options['units'], [ 'mi', 'km' ], true ) ? $options['units'] : (string) mailocations_get_option_default( 'units' );
	$options['version_first']        = esc_html( $options['version_first'] );
	$options['version_db']           = esc_html( $options['version_db'] );

	// Anything a filter or an older version added is kept, but no longer stored as typed.
	foreach ( $options as $key => $value ) {
		if ( isset( $defaults[ $key ] ) || ! is_scalar( $value ) ) {
			continue;
		}

		$options[ $key ] = sanitize_text_field( (string) $value );
	}

	return $options;
}

/**
 * Gets the current URL without query strings.
 *
 * @since TBD
 *
 * @param array $get The $_GET array.
 *
 * @return string
 */
function mailocations_get_current_url_clean( $get = null ) {
	$get = ! is_null( $get ) ? $get : $_GET;

	return remove_query_arg( array_keys( wp_parse_args( $get ) ), home_url( add_query_arg( [] ) ) );
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
		return false;
	}

	$location_id = (int) $location_id;

	// The author, whatever their role. Location owners are often subscriber level,
	// so a capability check alone would lock out the people this form is built for.
	if ( get_current_user_id() === (int) get_post_field( 'post_author', $location_id ) ) {
		return true;
	}

	// Anyone WordPress already lets edit this location, such as an editor or administrator.
	return current_user_can( 'edit_post', $location_id );
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

	$asset_name          = "{$filename}-styles";
	$asset               = mailocations_get_asset( $asset_name );
	$loaded[ $filename ] = MAI_LOCATIONS_PLUGIN_URL . "build/{$asset_name}.css";

	return sprintf( '<link rel="stylesheet" href="%s?ver=%s" />', $loaded[ $filename ], $asset['version'] );
}

/**
 * Gets asset data from wp-scripts build.
 *
 * @since 1.1.0
 *
 * @param string $name The asset name (without extension).
 *
 * @return array
 */
function mailocations_get_asset( $name ) {
	$file = MAI_LOCATIONS_PLUGIN_DIR . "build/{$name}.asset.php";

	return file_exists( $file ) ? require $file : [ 'dependencies' => [], 'version' => MAI_LOCATIONS_VERSION ];
}

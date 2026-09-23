<?php

use Mai\Locations\Cache;

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
	if ( Cache::has( 'plural' ) ) {
		return Cache::get( 'plural' );
	}

	// Escape before caching. Only the first call used to be escaped, because the raw value was
	// what got stored. Fixed September 16, 2026.
	$label = mailocations_get_option( 'label_plural' );
	$label = apply_filters( 'mailocations_plural', $label );

	return Cache::set( 'plural', esc_html( $label ) );
}

/**
 * Gets the post type singular label.
 *
 * @since 0.1.0
 *
 * @return string
 */
function mailocations_get_singular() {
	if ( Cache::has( 'singular' ) ) {
		return Cache::get( 'singular' );
	}

	// Escape before caching. Only the first call used to be escaped. Fixed September 16, 2026.
	$label = mailocations_get_option( 'label_singular' );
	$label = apply_filters( 'mailocations_singular', $label );

	return Cache::set( 'singular', esc_html( $label ) );
}

/**
 * Gets the post type base for urls.
 *
 * @since 0.1.0
 *
 * @return string
 */
function mailocations_get_base() {
	if ( Cache::has( 'base' ) ) {
		return Cache::get( 'base' );
	}

	// Clean before caching, and clean it the same way the saved setting is cleaned. Only the
	// first call used to be cleaned, and with sanitize_html_class(), which keeps characters a
	// URL base should not have. Fixed September 16, 2026.
	$base = mailocations_get_option( 'base' );
	$base = apply_filters( 'mailocations_base', $base );

	return Cache::set( 'base', sanitize_title_with_dashes( $base ) );
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
	// The cache lasts the whole request, so a value saved during it was never seen again.
	// mailocations_update_option() clears it now. Fixed September 16, 2026. The labels and the
	// base are worked out from these options, so they go too.
	if ( $reset ) {
		Cache::forget( 'options' );
		Cache::forget( 'plural' );
		Cache::forget( 'singular' );
		Cache::forget( 'base' );
	}

	if ( Cache::has( 'options' ) ) {
		return Cache::get( 'options' );
	}

	// Get all options, with defaults if option does not exist.
	$options = (array) get_option( 'mai_locations', mailocations_get_options_defaults() );

	return Cache::set( 'options', mailocations_sanitize_options( $options ) );
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
	if ( Cache::has( 'options_defaults' ) ) {
		return Cache::get( 'options_defaults' );
	}

	$defaults = [
		'label_plural'         => __( 'Locations', 'mai-locations' ),
		'label_singular'       => __( 'Location', 'mai-locations' ),
		'base'                 => 'locations',
		'category_base'        => 'location-category',
		'google_api_key'       => '',
		'google_api_signature' => '',
		'google_map_id'        => '',
		'owners_can_publish'   => false,
		'distance'             => 100,
		'units'                => 'mi',
		'version_first'        => '',
		'version_db'           => '',
	];

	return Cache::set( 'options_defaults', $defaults );
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
		'owners_can_publish'   => false,
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
	// A checkbox, so an unchecked box sends nothing at all and has to read as false.
	$options['owners_can_publish']   = rest_sanitize_boolean( is_scalar( $options['owners_can_publish'] ) ? (string) $options['owners_can_publish'] : '' );
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
 * Deletes every transient whose key starts with `mai_locations_`.
 *
 * Note that this doesn't work for sites that use a persistent object
 * cache, since in that case, transients are stored in memory.
 *
 * @since TBD
 *
 * @link https://gist.github.com/kellenmace/7d8f3b4c48cef3fd68ebc8606415d7dd
 *
 * @return void
 */
function mailocations_delete_transients() {
	global $wpdb;

	// The trailing underscore matters. Without it this also matched a transient named, say,
	// mai_locationsother. Fixed September 16, 2026.
	$prefix = 'mai_locations_';
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
 * Whether a user may publish a location themselves, from the front-end edit form.
 *
 * This is the one question that separates the three ways sites use this plugin:
 *
 * - Moderated. Submissions arrive pending, owners edit, a manager publishes in the Dashboard.
 * - Self-manage. Submissions arrive as drafts, owners publish their own when they are ready.
 * - Approve to draft. Submissions arrive pending, a manager moves an approved one to draft, and
 *   its owner publishes when they are ready.
 *
 * Status alone cannot tell them apart, because "pending" means waiting for a human on one site
 * and not finished yet on another. So the plugin asks about the person instead.
 *
 * @since 2.0.0
 *
 * @param int $location_id The location ID.
 * @param int $user_id     The user ID. Defaults to the current user.
 *
 * @return bool
 */
function mailocations_user_can_publish( $location_id, $user_id = 0 ): bool {
	$location_id = (int) $location_id;
	$user_id     = $user_id ? (int) $user_id : get_current_user_id();

	if ( ! $user_id ) {
		$can = false;
	}
	// Anyone WordPress already lets publish this location, such as an editor or administrator.
	// They can do it in the Dashboard, so the front-end form should not pretend otherwise.
	elseif ( user_can( $user_id, 'publish_post', $location_id ) ) {
		$can = true;
	}
	// Otherwise it is the site's call, and only for the owner. Owners are often subscriber
	// level, so there is no capability that would say yes for them.
	else {
		$can = (bool) mailocations_get_option( 'owners_can_publish' )
			&& $user_id === (int) get_post_field( 'post_author', $location_id );
	}

	/**
	 * Filters whether a user may publish a location from the front-end edit form.
	 *
	 * @since 2.0.0
	 *
	 * @param bool $can         Whether they may.
	 * @param int  $location_id The location ID.
	 * @param int  $user_id     The user ID.
	 */
	return (bool) apply_filters( 'mailocations_user_can_publish', $can, $location_id, $user_id );
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
	$key = 'stylesheet_link_' . $filename;

	// Bail if loaded.
	if ( is_admin() || Cache::has( $key ) ) {
		return;
	}

	$asset_name = "{$filename}-styles";
	$asset      = mailocations_get_asset( $asset_name );
	$url        = Cache::set( $key, MAI_LOCATIONS_PLUGIN_URL . "build/{$asset_name}.css" );

	return sprintf( '<link rel="stylesheet" href="%s?ver=%s" />', $url, $asset['version'] );
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

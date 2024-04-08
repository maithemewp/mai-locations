<?php

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * If the current page is a location archive.
 *
 * @since TBD
 *
 * @return bool
 */
function mailocations_is_archive() {
	return is_post_type_archive( 'mai_location' ) || is_tax( array_keys( mailocations_get_location_taxonomies() ) );
}
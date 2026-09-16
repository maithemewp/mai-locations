<?php

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Reads a location's own website for an og:image and og:description.
 *
 * Deliberately global, not namespaced: Visit Sleepy Hollow and other sites call this directly
 * from their own scripts. It shared a file with the CLI class until September 15, 2026.
 *
 * TODO: uses WordPress's default user agent and a 5 second timeout, so many hotel and chain
 * sites return nothing. The twitter: fallback below can never run, because the check before it
 * is always false. An unknown $key warns and returns null. See TODO.md.
 *
 * @access private
 *
 * @since TBD
 *
 * @param string $url The website URL.
 * @param string $key Optional single key to return: image or desc.
 *
 * @return array|string
 */
function mailocations_get_data_from_website( $url, $key = '' ) {
	// Start data.
	$data = [
		'image' => '',
		'desc'  => '',
	];

	// Request.
	$response = wp_remote_get( $url );
	$code     = wp_remote_retrieve_response_code( $response );

	// Bail if error. 403 is a valid response, but sometimes we were blocked.
	if ( ! in_array( $code, [ 200, 403 ] ) ) {
		return $key ? $data[ $key ] : $data;
	}

	// Get body.
	$body = wp_remote_retrieve_body( $response );
	$body = str_replace( '<!DOCTYPE html>', '', $body );

	// Bail if no body.
	if ( ! $body ) {
		return $key ? $data[ $key ] : $data;
	}

	// Set up tag processor.
	$tags = new WP_HTML_Tag_Processor( $body );

	// Loop through tags.
	while ( $tags->next_tag( [ 'tag_name' => 'meta' ] ) ) {
		// Get property.
		$property = $tags->get_attribute( 'property' );

		// Skip if no property or not the right property.
		if ( ! $property || ! in_array( $property, [ 'og:description', 'og:image' ] ) ) {
			continue;
		}

		// Try for data.
		switch ( $property ) {
			case 'og:description':
				$data['desc'] = (string) $tags->get_attribute( 'content' );
				break;
			case 'og:image':
				$data['image'] = (string) $tags->get_attribute( 'content' );
				break;
		}
	}

	// Maybe try for fallbacks.
	if ( ! array_values( $data ) ) {
		// Set up tag processor.
		$tags = new WP_HTML_Tag_Processor( $body );

		// Loop through tags.
		while ( $tags->next_tag( [ 'tag_name' => 'meta' ] ) ) {
			// Get name.
			$name = $tags->get_attribute( 'name' );

			// Skip if no name or not the right name.
			if ( ! $name || ! in_array( $name, [ 'twitter:description', 'twitter:image' ] ) ) {
				continue;
			}

			// Try for name.
			switch ( $name ) {
				case 'twitter:description':
					$data['desc'] = $data['desc'] ?: (string) $tags->get_attribute( 'content' );
					break;
				case 'twitter:image':
					$data['image'] = $data['image'] ?: (string) $tags->get_attribute( 'content' );
					break;
			}
		}
	}

	return $key ? $data[ $key ] : $data;
}

/**
 * Downloads a remote file and inserts it into the WP Media Library.
 *
 * Deliberately global, not namespaced, for the same reason as above.
 *
 * TODO: fetches with file_get_contents(), then re-downloads the saved copy through the site's
 * own uploads URL, which fails on a self-signed certificate. Stages every image as .jpg
 * whatever its type, and hands a WP_Error to wp_delete_file() on failure, which crashes the
 * run. See TODO.md.
 *
 * @access private
 *
 * @see https://developer.wordpress.org/reference/functions/media_handle_sideload/
 *
 * @param string $ref_uri   The reference URI of a remote file.
 * @param string $ref_key   The reference key of a remote file.
 * @param string $image_url HTTP URL address of a remote file.
 * @param int    $post_id   The post ID the media is associated with.
 *
 * @return int|WP_Error The ID of the attachment or a WP_Error on failure.
 */
function mailocations_upload_image( $ref_uri, $ref_key, $image_url, $post_id ) {
	// Make sure we have the functions we need.
	if ( ! function_exists( 'download_url' ) || ! function_exists( 'media_handle_sideload' ) ) {
		require_once( ABSPATH . 'wp-admin/includes/media.php' );
		require_once( ABSPATH . 'wp-admin/includes/file.php' );
		require_once( ABSPATH . 'wp-admin/includes/image.php' );
	}

	// Check if there is an attachment with places_url meta key and value of $image_url.
	$existing_ids = get_posts(
		[
			'post_type'    => 'attachment',
			'post_status'  => 'any',
			'meta_key'     => $ref_key,
			'meta_value'   => $ref_uri,
			'meta_compare' => '=',
			'fields'       => 'ids',
		]
	);

	// Get existing ID.
	$existing_id = $existing_ids && isset( $existing_ids[0] ) ? $existing_ids[0] : 0;

	// Bail if the image already exists.
	if ( $existing_id ) {
		return $existing_id;
	}

	// Get contents of the image url.
	$image_hashed   = md5( $image_url ) . '.jpg';
	$image_contents = file_get_contents( $image_url );

	// If contents.
	if ( $image_contents ) {
		// Get the uploads directory.
		$upload_dir = wp_get_upload_dir();
		$upload_url = $upload_dir['baseurl'];

		// Specify the path to the destination directory within uploads.
		$destination_dir = $upload_dir['basedir'] . '/mai-locations/';

		// Create the destination directory if it doesn't exist.
		if ( ! file_exists( $destination_dir ) ) {
			mkdir( $destination_dir, 0755, true );
		}

		// Specify the path to the destination file.
		$destination_file = $destination_dir . $image_hashed;

		// Save the image to the destination file.
		file_put_contents( $destination_file, $image_contents );

		// Bail if the file doesn't exist.
		if ( ! file_exists( $destination_file ) ) {
			return 0;
		}

		$image_url = $image_hashed;
	}
	// Bail, no image contents.
	else {
		return 0;
	}

	// Build the image url.
	$image_url = untrailingslashit( $upload_url ) . '/mai-locations/' . $image_hashed;

	// Build a temp url.
	$tmp = download_url( $image_url );

	// Remove the temp file.
	wp_delete_file( $destination_file );

	// Bail if error.
	if ( is_wp_error( $tmp ) ) {
		// Remove the original image and return the error.
		wp_delete_file( $tmp );

		return 0;
	}

	// Build the file array.
	$file_array = [
		'name'     => basename( $image_url ),
		'tmp_name' => $tmp,
	];

	// Add the image to the media library.
	$image_id = media_handle_sideload( $file_array, $post_id );

	// Bail if error.
	if ( is_wp_error( $image_id ) ) {
		// Remove the original image and return the error.
		wp_delete_file( $file_array[ 'tmp_name' ] );
		return $image_id;
	}

	// Remove the original image.
	wp_delete_file( $file_array[ 'tmp_name' ] );

	// Set the reference url for possible reference later.
	update_post_meta( $image_id, $ref_key, $ref_uri );

	return $image_id;
}

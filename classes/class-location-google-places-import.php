<?php

// Prevent direct file access.
defined( 'ABSPATH' ) || die;

/**
 * Instantiate the class.
 *
 * @since 0.1.0
 *
 * @return void
 */
new Mai_Locations_Google_Places_Import;

/**
 * Gets it started.
 *
 * @since 0.1.0
 *
 * @link https://docs.wpvip.com/how-tos/write-custom-wp-cli-commands/
 * @link https://webdevstudios.com/2019/10/08/making-wp-cli-commands/
 *
 * @return void
 */
add_action( 'cli_init', function() {
	WP_CLI::add_command( 'mailocations', 'Mai_Locations_Google_Places_Import' );
});

/**
 * Main Mai_Locations_Google_Places_Import Class.
 *
 * @since 0.1.0
 */
class Mai_Locations_Google_Places_Import {
	/**
	 * Gets environment.
	 *
	 * Usage: wp mailocations get_environment
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	function get_environment() {
		WP_CLI::log( sprintf( 'Environment: %s', wp_get_environment_type() ) );
	}

	/**
	 * Imports locations from google places search.
	 *
	 * Usage: wp mailocations import_places --post_status=pending --search='Birth Centers in Myrtle Beach SC' --set_cats="Birth Centers" --max=20
	 *
	 * @link https://developers.google.com/maps/documentation/places/web-service/?apix=true
	 * @link https://developers.google.com/maps/documentation/places/web-service/reference/rest/v1/places/searchText
	 *
	 * @since 0.1.0
	 *
	 * @param array $args       Standard command args.
	 * @param array $assoc_args Keyed args like --search and --fields.
	 *
	 * @return void
	 */
	function import_places( $args, $assoc_args ) {
		$api_key = mailocations_get_google_maps_api_key();

		// Bail if no API key.
		if ( ! $api_key ) {
			WP_CLI::line( 'No API key' );
			return;
		}

		// Parse args.
		$assoc_args = wp_parse_args(
			$assoc_args,
			[
				'search'      => '',
				'fields'      => '*',
				'region'      => 'US',
				'max'         => 20, // 1-20 max.
				'post_status' => 'publish',
				'set_cats'    => '',
				'append_cats' => true, // Whether to replace or append to existing categories.
				'skip_update' => false, // Whether to skip updating existing posts.
			]
		);

		// If no search term, bail.
		if ( ! $assoc_args['search'] ) {
			WP_CLI::line( 'No search term' );
			return;
		}

		// Build url.
		$url = 'https://places.googleapis.com/v1/places:searchText';
		$url = add_query_arg(
			[
				'textQuery'      => $assoc_args['search'],
				'fields'         => $assoc_args['fields'],
				'regionCode'     => $assoc_args['region'],
				'maxResultCount' => $assoc_args['max'],
				'key'            => $api_key,
			],
			$url
		);

		// Send request.
		$response = wp_remote_post( $url );

		// Bail if no response data.
		if ( ! $response ) {
			WP_CLI::line( 'No response' );
			return;
		}

		// Get vars.
		$body = wp_remote_retrieve_body( $response );
		$body = json_decode( $body, true );
		$code = wp_remote_retrieve_response_code( $response );

		// Bail if response code is not 200.
		if ( 200 !== $code ) {
			// Build error message.
			$message = $code;

			// Add error message if available.
			if ( isset( $body['error']['message'] ) ) {
				$message .= ' ' . $body['error']['message'];
			}

			WP_CLI::line( $message );
			return;
		}

		// Get places.
		$places = isset( $body['places'] ) ? $body['places'] : [];

		// Bail if no places.
		if ( ! $places ) {
			WP_CLI::line( 'No places' );
			return;
		}

		// Log total.
		WP_CLI::line( count( $places ) . ' found' );

		// Loop through places.
		foreach ( $places as $index => $place ) {
			// Get title.
			$title = isset( $place['displayName']['text'] ) ? $place['displayName']['text'] : '';

			// Bail if no title.
			if ( ! $title ) {
				WP_CLI::line( 'No display name' );
				continue;
			}

			// Get place ID.
			$place_id = isset( $place['id'] ) ? $place['id'] : '';

			// Bail if no place ID.
			if ( ! $place_id ) {
				WP_CLI::line( 'No place ID' );
				continue;
			}

			// Build post data.
			$post_data = [
				'post_type'   => 'mai_location',
				'post_title'  => $title,
			];

			// Get post with a meta key of place_id and meta value of the $place_id.
			$post_ids = get_posts(
				[
					'post_type'    => 'mai_location',
					'post_status'  => 'any',
					'meta_key'     => 'place_id',
					'meta_value'   => $place_id,
					'meta_compare' => '=',
					'fields'       => 'ids',
					'numberposts'  => 1,
				]
			);

			// Get post ID.
			$post_id = $post_ids && isset( $post_ids[0] ) ? $post_ids[0] : 0;

			// Maybe update existing.
			if ( $post_id ) {
				if ( $assoc_args['skip_update'] ) {
					WP_CLI::line( sprintf( 'Post exists: %s', get_the_title( $post_id ) ) );
					continue;
				}

				$post_data['ID'] = $post_id;
			} else {
				$post_data['post_status'] = $assoc_args['post_status'];
			}

			// Helper function to transform keys.
			$transform_keys = function( $array ) {
				$keys = [];

				foreach ( $array as $key => $value ) {
					// Transform key from camelCase to snake_case.
					$transformed = strtolower( preg_replace('/(?<!^)[A-Z]/', '_$0', $key ) );

					// Manually transform some keys.
					switch ( $transformed ) {
						case 'long_text':
							$transformed = 'long_name';
						break;
						case 'short_text':
							$transformed = 'short_name';
						break;
					}

					$keys[ $transformed ] = $value;
				}

				return $keys;
			};

			// Get address components.
			$components = isset( $place['addressComponents'] ) ? $place['addressComponents'] : [];

			// Transform keys.
			foreach ( $components as $index => $values ) {
				$components[ $index ] = $transform_keys( $values );
			}

			// Build meta.
			$meta                 = $components ? mailocations_get_address_meta_from_components( $components ) : [];
			$meta['place_id']     = $place_id;
			$meta['location_lat'] = isset( $place['location']['latitude'] ) ? $place['location']['latitude'] : '';
			$meta['location_lng'] = isset( $place['location']['longitude'] ) ? $place['location']['longitude'] : '';

			// If we have a country.
			if ( isset( $meta['address_country'] ) && ! empty( $meta['address_country'] ) ) {
				// National.
				if ( 'US' === $meta['address_country'] ) {
					if ( isset( $place['nationalPhoneNumber'] ) ) {
						$meta['location_phone'] = $place['nationalPhoneNumber'];
					}
				}
				// International.
				else {
					if ( isset( $place['internationalPhoneNumber'] ) ) {
						$meta['location_phone'] = $place['internationalPhoneNumber'];
					}
				}
			}

			// If we have a website.
			if ( isset( $place['websiteUri'] ) ) {
				$meta['location_url'] = $place['websiteUri'];
			}

			// Maybe add post meta.
			if ( $meta ) {
				$post_data['meta_input'] = $meta;
			}

			// Insert or update post.
			$post_id = wp_insert_post( $post_data );

			// If error.
			if ( is_wp_error( $post_id ) ) {
				WP_CLI::line( sprintf( 'Error: %s', $post_id->get_error_message() ) );
				continue;
			}
			// We have a post ID.
			elseif ( $post_id ) {
				// Maybe set category.
				if ( $assoc_args['set_cats'] ) {
					// Build array from comma-separated string.
					$cats = explode( ',', $assoc_args['set_cats'] );
					$cats = array_map( 'trim', $cats );

					// Append to existing categories.
					wp_set_object_terms( $post_id, $cats, 'mai_location_cat', $append = false );
				}

				// If we have a photo.
				if ( isset( $place['photos'] ) ) {
					// Loop through photos.
					foreach ( $place['photos'] as $photo ) {
						// This reference seems to stay the same.
						$ref_uri = isset( $photo['authorAttributions'][0]['photoUri'] ) && $photo['authorAttributions'][0]['photoUri'] ? $photo['authorAttributions'][0]['photoUri'] : '';

						// Skip if no photo URI.
						if ( ! $ref_uri ) {
							WP_CLI::line( 'No photo URI' );
							continue;
						}

						// Get photo reference. This expires so we can't use it to check for existing.
						$reference = isset( $photo['name'] ) && $photo['name'] ? $photo['name'] : '';

						// Skip if no reference.
						if ( ! $reference ) {
							WP_CLI::line( 'No photo reference' );
							continue;
						}

						// Make sure the end does not have a slash.
						$reference = untrailingslashit( $reference );

						// https://places.googleapis.com/v1/places/PLACE_ID/photos/PHOTO_REFERENCE/media?maxWidthPx=400&key=API_KEY
						// https://places.googleapis.com/v1/places/ChIJ2fzCmcW7j4AR2JzfXBBoh6E/photos/AUacShh3_Dd8yvV2JZMtNjjbbSbFhSv-0VmUN-uasQ2Oj00XB63irPTks0-A_1rMNfdTunoOVZfVOExRRBNrupUf8TY4Kw5iQNQgf2rwcaM8hXNQg7KDyvMR5B-HzoCE1mwy2ba9yxvmtiJrdV-xBgO8c5iJL65BCd0slyI1/media?maxHeightPx=400&maxWidthPx=400&key=API_KEY
						$image_url = sprintf( 'https://places.googleapis.com/v1/%s/media', $reference );
						$image_url = add_query_arg(
							[
								'maxWidthPx' => '1600',
								'key'        => $api_key,
							],
							$image_url
						);

						// Maybe upload the image.
						$image_id = mailocations_upload_image( $ref_uri, $image_url, $post_id );

						// If we have an image ID.
						if ( $image_id ) {
							// Set the featured image.
							set_post_thumbnail( $post_id, $image_id );
						}

						// Only one photo for now.
						break;
					}
				}

				// Log.
				if ( isset( $post_data['ID'] ) ) {
					WP_CLI::line( sprintf( 'Post updated: %s', get_the_title( $post_id ) ) );
				} else {
					WP_CLI::line( sprintf( 'Post inserted: %s', get_the_title( $post_id ) ) );
				}
			}
			// No post ID. May be 0.
			else {
				WP_CLI::line( 'Error inserting post' );
			}
		}

		WP_CLI::success( 'Done.' );
	}
}

/**
 * Downloads a remote file and inserts it into the WP Media Library.
 *
 * @access private
 *
 * @see https://developer.wordpress.org/reference/functions/media_handle_sideload/
 *
 * @param string $ref_uri The reference URI of a remote file.
 * @param string $url     HTTP URL address of a remote file.
 * @param int    $post_id The post ID the media is associated with.
 *
 * @return int|WP_Error The ID of the attachment or a WP_Error on failure.
 */
function mailocations_upload_image( $ref_uri, $image_url, $post_id ) {
	// Make sure we have the functions we need.
	if ( ! function_exists( 'download_url' ) || ! function_exists( 'media_handle_sideload' ) ) {
		require_once( ABSPATH . 'wp-admin/includes/media.php' );
		require_once( ABSPATH . 'wp-admin/includes/file.php' );
		require_once( ABSPATH . 'wp-admin/includes/image.php' );
	}

	// Check if there is an attachment with unitedrobots_url meta key and value of $image_url.
	$existing_ids = get_posts(
		[
			'post_type'    => 'attachment',
			'post_status'  => 'any',
			'meta_key'     => 'places_url',
			'meta_value'   => $ref_uri,
			'meta_compare' => '=',
			'fields'       => 'ids',
		]
	);

	// Bail if the image already exists.
	if ( $existing_ids ) {
		return $existing_ids[0];
	}

	// Get contents of the image url.
	$image_contents = file_get_contents( $image_url );
	$image_hashed   = md5( $image_url ) . '.jpg';

	// If contents.
	if ( $image_contents ) {
		// Get the uploads directory.
		$upload_dir = wp_get_upload_dir();
		$upload_url = $upload_dir['baseurl'];

		// Specify the path to the destination directory within uploads.
		$destination_dir = $upload_dir['basedir'] . '/mai-publisher/';

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

	// Build the image url.
	$image_url = untrailingslashit( $upload_url ) . '/mai-publisher/' . $image_hashed;

	// Build a temp url.
	$tmp = download_url( $image_url );

	// Remove the temp file.
	@unlink( $destination_file );

	// Bail if error.
	if ( is_wp_error( $tmp ) ) {
		// ray( $tmp->get_error_code() . ': upload_image() 1 ' . $image_url . ' ' . $tmp->get_error_message() );

		// Remove the original image and return the error.
		@unlink( $tmp );
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
		// ray( $image_id->get_error_code() . ': upload_image() 2 ' . $image_url . ' ' . $image_id->get_error_message() );

		// Remove the original image and return the error.
		@unlink( $file_array[ 'tmp_name' ] );
		return $image_id;
	}

	// Remove the original image.
	@unlink( $file_array[ 'tmp_name' ] );

	// Set the reference url for possible reference later.
	update_post_meta( $image_id, 'places_url', $ref_uri );

	return $image_id;
}
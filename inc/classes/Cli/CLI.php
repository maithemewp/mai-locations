<?php

declare(strict_types=1);

namespace Mai\Locations\Cli;

use WP_CLI;
use WP_Query;

// Prevent direct file access.
defined( 'ABSPATH' ) || die;

/**
 * The `wp mailocations` commands.
 *
 * Was Mai_Locations_CLI in classes/class-locations-cli.php. That name still works, via
 * inc/aliases.php. The command registration and the discarded instantiation that used to sit
 * at the top of that file now live in the plugin bootstrap, which autoloading does not run.
 *
 * The two global functions that shared the old file, mailocations_get_data_from_website() and
 * mailocations_upload_image(), moved to inc/functions-website.php and stay global. Visit
 * Sleepy Hollow calls both from its own scripts.
 *
 * @since 0.1.0
 */
class CLI {

	/**
	 * Gets the environment.
	 *
	 * Usage: wp mailocations get_environment
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function get_environment(): void {
		WP_CLI::log( sprintf( 'Environment: %s', wp_get_environment_type() ) );
	}

	/**
	 * Imports locations from a Google Places search.
	 *
	 * Usage: wp mailocations import_places --post_status=pending --search='Birth Centers in Myrtle Beach SC' --set_cats="Birth Centers" --max=20
	 * Usage: wp mailocations import_places --post_type=practitioner --post_status=pending --search='Rock Climbing Gym Myrtle Beach SC" --max=20
	 *
	 * @link https://developers.google.com/maps/documentation/places/web-service/?apix=true
	 * @link https://developers.google.com/maps/documentation/places/web-service/reference/rest/v1/places/searchText
	 *
	 * @since 0.1.0
	 *
	 * @param array<int, string>   $args       Standard command args.
	 * @param array<string, mixed> $assoc_args Keyed args like --search and --fields.
	 *
	 * @return void
	 */
	public function import_places( $args, $assoc_args ): void {
		$api_key = mailocations_get_option( 'google_api_key' );

		// Bail if no API key.
		if ( ! $api_key ) {
			WP_CLI::line( 'No API key' );
			return;
		}

		// Parse args.
		$assoc_args = wp_parse_args(
			$assoc_args,
			[
				'post_type'   => 'mai_location',
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

		// Get code and body.
		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$body = json_decode( $body, true );

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
				'post_type'   => $assoc_args['post_type'],
				'post_title'  => $title,
			];

			// Get post with a meta key of place_id and meta value of the $place_id.
			$existing_ids = get_posts(
				[
					'post_type'    => $assoc_args['post_type'],
					'post_status'  => 'any',
					'meta_key'     => 'place_id',
					'meta_value'   => $place_id,
					'meta_compare' => '=',
					'fields'       => 'ids',
					'numberposts'  => 1,
				]
			);

			// Get post ID.
			$existing_id = $existing_ids && isset( $existing_ids[0] ) ? $existing_ids[0] : 0;

			// Maybe update existing.
			if ( $existing_id ) {
				if ( rest_sanitize_boolean( $assoc_args['skip_update'] ) ) {
					WP_CLI::line( sprintf( 'Post exists: %s', get_the_title( $existing_id ) ) );
					continue;
				}

				$post_data['ID'] = $existing_id;
			} else {
				$post_data['post_status'] = $assoc_args['post_status'];
			}

			// Helper function to transform keys.
			$transform_keys = function ( $array ) {
				$keys = [];

				foreach ( $array as $key => $value ) {
					// Transform key from camelCase to snake_case.
					$transformed = strtolower( preg_replace( '/(?<!^)[A-Z]/', '_$0', $key ) );

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

			// Set website.
			$website_url = '';

			// If we have a website.
			if ( isset( $place['websiteUri'] ) ) {
				$website_url  = esc_url( $place['websiteUri'] );
				$website_vars = wp_parse_url( $website_url, PHP_URL_QUERY );

				// If we have vars, parse them to array.
				if ( $website_vars ) {
					parse_str( $website_vars, $website_vars );

					// If we have query variables, remove them.
					if ( $website_vars ) {
						$website_url = remove_query_arg( array_keys( $website_vars ), $website_url );
					}
				}

				// Set meta.
				$meta['location_url'] = $website_url;
			}

			// Maybe add post meta.
			if ( $meta ) {
				$post_data['meta_input'] = $meta;
			}

			// Get data from website.
			$data = $website_url ? mailocations_get_data_from_website( $website_url ) : false;

			// If we have an excerpt, add it.
			if ( $data && $data['desc'] ) {
				$post_data['post_excerpt'] = $data['desc'];
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

					// TODO: Check taxonomy of post type?

					// Append to existing categories.
					wp_set_object_terms( $post_id, $cats, 'mai_location_cat', $assoc_args['append_cats'] );
				}

				// If we need a featured image from places.
				$needs_image = true;

				// If we have an existing post.
				if ( $existing_id ) {
					// Get featured image.
					$image_id = get_post_thumbnail_id( $existing_id );

					// Skip if we have a featured image.
					if ( $image_id ) {
						$needs_image = false;
					}
				}

				// If we need an image and have a website url.
				if ( $needs_image && $data && $data['image'] ) {
					// Maybe upload the image.
					$image_id = mailocations_upload_image( $data['image'], 'original_url', $data['image'], $post_id );

					// If we have an image ID.
					if ( $image_id ) {
						$needs_image = false;

						// Set the featured image.
						set_post_thumbnail( $post_id, $image_id );

						WP_CLI::line( sprintf( 'Featured image updated from website: %s', get_permalink( $post_id ) ) );
					}
				}

				// If we need a featured image and have a photo.
				if ( $needs_image && isset( $place['photos'] ) ) {
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
						$image_url = sprintf( 'https://places.googleapis.com/v1/%s/media', $reference );
						$image_url = add_query_arg(
							[
								'maxWidthPx' => '1600',
								'key'        => $api_key,
							],
							$image_url
						);

						// Maybe upload the image.
						$image_id = mailocations_upload_image( $ref_uri, 'original_url', $image_url, $post_id );

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

		// Flush all transients.
		mailocations_delete_transients();

		WP_CLI::success( 'Done.' );
	}

	/**
	 * Updates locations from their website.
	 *
	 * Usage: wp mailocations update_locations_from_website --posts_per_page="50" --offset="0"
	 *
	 * Sets the post excerpt from og:description and the featured image from og:image, on any
	 * location that has neither. --force_excerpt and --force_image overwrite what is already
	 * there. --skip_excerpt and --skip_image leave that half alone, so the command can fetch
	 * only images or only excerpts.
	 *
	 * TODO: a failed image download crashes the run, and a failed sideload is logged as
	 * success. See TODO.md.
	 *
	 * @link https://developers.google.com/maps/documentation/places/web-service/reference/rest/v1/places/get
	 *
	 * @since 0.1.0
	 *
	 * @param array<int, string>   $args       Standard command args.
	 * @param array<string, mixed> $assoc_args Keyed args like --search and --fields.
	 *
	 * @return void
	 */
	public function update_locations_from_website( $args, $assoc_args ): void {
		// Parse args.
		$assoc_args = wp_parse_args(
			$assoc_args,
			[
				'post_type'      => 'mai_location',
				'post_status'    => 'any',
				'posts_per_page' => 50,
				'offset'         => 0,
				'force_excerpt'  => false, // Whether to force update the post excerpt if it already exists.
				'force_image'    => false, // Whether to force update the featured image if it already exists.
				'skip_excerpt'   => false, // Whether to leave post excerpts alone entirely.
				'skip_image'     => false, // Whether to leave featured images alone entirely.
			]
		);

		// Get query.
		$query = new WP_Query(
			[
				'post_type'              => $assoc_args['post_type'],
				'post_status'            => $assoc_args['post_status'],
				'posts_per_page'         => $assoc_args['posts_per_page'],
				'offset'                 => $assoc_args['offset'],
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'meta_query'             => [
					[
						'key'     => 'location_url',
						'value'   => '',
						'compare' => '!=',
					],
				],
			]
		);

		if ( $query->have_posts() ) {
			// Log total.
			WP_CLI::line( count( $query->posts ) . ' found' );

			while ( $query->have_posts() ) : $query->the_post();
				// Get place ID.
				$post_id = get_the_ID();
				$url     = get_post_meta( $post_id, 'location_url', true );

				// Bail if no location url.
				if ( ! $url ) {
					WP_CLI::line( 'No location url' );
					continue;
				}

				// Get website data.
				$data = mailocations_get_data_from_website( $url );

				// Bail if no data.
				// TODO: always false, because the array always has two keys. See TODO.md.
				if ( ! array_values( $data ) ) {
					// WP_CLI::line( sprintf( 'No description or image: %s', get_permalink( $post_id ) ) );
					continue;
				}

				// Log if no description.
				if ( ! $data['desc'] ) {
					// WP_CLI::line( sprintf( 'No description: %s', get_permalink( $post_id ) ) );
				}
				// Update the post excerpt.
				else {
					// If not skipping excerpts, and there is no excerpt or we're forcing the update.
					if ( ! rest_sanitize_boolean( (string) $assoc_args['skip_excerpt'] ) && ( ! has_excerpt( $post_id ) || rest_sanitize_boolean( $assoc_args['force_excerpt'] ) ) ) {
						// Update the post excerpt.
						$post_id = wp_update_post(
							[
								'ID'           => $post_id,
								'post_excerpt' => $data['desc'],
							]
						);

						// If error.
						if ( is_wp_error( $post_id ) ) {
							WP_CLI::line( sprintf( 'Error: %s', $post_id->get_error_message() ) );
							continue;
						}
						// Success.
						else {
							WP_CLI::line( sprintf( 'Excerpt updated: %s', get_permalink( $post_id ) ) );
						}
					}
				}

				// Log if no image.
				if ( ! $data['image'] ) {
					// WP_CLI::line( sprintf( 'No image: %s', $url ) );
				}
				// Update featured image.
				else {
					// Get featured image.
					$featured_id = get_post_thumbnail_id( $post_id );

					// If not skipping images, and there is no featured image or we're forcing the update.
					if ( ! rest_sanitize_boolean( (string) $assoc_args['skip_image'] ) && ( ! $featured_id || rest_sanitize_boolean( $assoc_args['force_image'] ) ) ) {
						// Maybe upload the image.
						$image_id = mailocations_upload_image( $data['image'], 'original_url', $data['image'], $post_id );

						// If we have an image ID.
						if ( $image_id && $image_id !== $featured_id ) {
							// Set the featured image.
							set_post_thumbnail( $post_id, $image_id );

							// Log.
							WP_CLI::line( sprintf( 'Image updated: %s', get_permalink( $post_id ) ) );
						}
					}
				}
			endwhile;

			// Flush all transients.
			mailocations_delete_transients();

			WP_CLI::success( 'Done.' );
		} else {
			WP_CLI::line( 'No locations found' );
		}

		wp_reset_postdata();
	}
}

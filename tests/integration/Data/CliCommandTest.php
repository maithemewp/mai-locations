<?php

declare(strict_types=1);

namespace Mai\Locations\Tests\Integration\Data;

use Mai\Locations\Tests\Integration\Data\Support\CapturesErrors;
use Mai\Locations\Tests\Integration\Data\Support\HttpMock;
use Mai\Locations\Tests\TestCase;
use Mai_Locations_CLI;
use WP_CLI;
use WP_Error;

require_once __DIR__ . '/Support/FakeWpCli.php';
require_once __DIR__ . '/Support/HttpMock.php';
require_once __DIR__ . '/Support/CapturesErrors.php';

/**
 * Pins the `wp mailocations` command. Visit Sleepy Hollow runs update_locations_from_website.
 *
 * The Google API key comes from mailocations_get_options(), which is cached in a static before
 * any test runs, so the key is always empty here and import_places can only be tested up to its
 * "No API key" bail.
 */
final class CliCommandTest extends TestCase {

	use HttpMock;
	use CapturesErrors;

	private const FIXTURES = __DIR__ . '/fixtures';

	/**
	 * @var array<string, array{0: int, 1: string}> Canned website responses keyed by URL.
	 */
	private array $sites = [];

	public function set_up(): void {
		parent::set_up();

		WP_CLI::reset();
		$this->sites = [];

		$this->mock_http(
			fn( string $url, array $args ) => self::serve_upload( $url, $args )
				?? ( isset( $this->sites[ $url ] ) ? self::response( $this->sites[ $url ][0], $this->sites[ $url ][1] ) : new WP_Error( 'unmocked', $url ) )
		);
	}

	public function tear_down(): void {
		$this->clean_uploads();

		parent::tear_down();
	}

	private static function page( string $desc = '', string $image = '' ): string {
		$meta = '';

		if ( '' !== $desc ) {
			$meta .= sprintf( '<meta property="og:description" content="%s">', esc_attr( $desc ) );
		}

		if ( '' !== $image ) {
			$meta .= sprintf( '<meta property="og:image" content="%s">', esc_attr( $image ) );
		}

		return "<!DOCTYPE html>\n<html><head>{$meta}</head><body></body></html>";
	}

	/**
	 * @param array<string, mixed> $assoc_args
	 *
	 * @return array<int, array{0: string, 1: mixed}>
	 */
	private function update( array $assoc_args = [] ): array {
		( new Mai_Locations_CLI() )->update_locations_from_website( [], $assoc_args );

		return WP_CLI::$calls;
	}

	/**
	 * @return list<string>
	 */
	private function requested_urls(): array {
		return array_column( $this->http_requests, 'url' );
	}

	public function test_registers_mailocations_command_on_cli_init(): void {
		global $wp_filter;

		$found = [];

		foreach ( $wp_filter['cli_init']->callbacks as $priority => $callbacks ) {
			foreach ( $callbacks as $callback ) {
				if ( $callback['function'] instanceof \Closure && str_ends_with( (string) ( new \ReflectionFunction( $callback['function'] ) )->getFileName(), 'inc/classes/class-locations-cli.php' ) ) {
					$found[] = [ $priority, $callback['function'] ];
				}
			}
		}

		$this->assertCount( 1, $found );
		$this->assertSame( 10, $found[0][0] );

		$found[0][1]();

		$this->assertSame( [ [ 'add_command', [ 'mailocations', 'Mai_Locations_CLI' ] ] ], WP_CLI::$calls );
	}

	public function test_subcommands_are_the_three_public_methods_and_there_is_no_constructor(): void {
		$class   = new \ReflectionClass( Mai_Locations_CLI::class );
		$methods = array_map( fn( \ReflectionMethod $method ) => $method->getName(), $class->getMethods( \ReflectionMethod::IS_PUBLIC ) );

		$this->assertSame( [ 'get_environment', 'import_places', 'update_locations_from_website' ], $methods );
		// The file also runs `new Mai_Locations_CLI;` at load and discards it. Without a constructor that does nothing.
		$this->assertNull( $class->getConstructor() );
	}

	/**
	 * The post factory fills in an excerpt, and the command skips locations that have one.
	 *
	 * @param array<string, mixed> $meta
	 * @param array<string, mixed> $post_args
	 */
	protected function create_location( array $meta = [], array $post_args = [] ): int {
		return parent::create_location( $meta, array_merge( [ 'post_excerpt' => '' ], $post_args ) );
	}

	public function test_get_environment_logs_the_environment_type(): void {
		( new Mai_Locations_CLI() )->get_environment();

		$this->assertSame( [ [ 'log', 'Environment: ' . wp_get_environment_type() ] ], WP_CLI::$calls );
		$this->assertSame( [ [ 'log', 'Environment: production' ] ], WP_CLI::$calls );
	}

	public function test_import_places_bails_without_api_key_before_checking_search_or_requesting(): void {
		( new Mai_Locations_CLI() )->import_places( [], [ 'search' => 'Inns in Sleepy Hollow NY' ] );
		( new Mai_Locations_CLI() )->import_places( [], [] );

		$this->assertSame( [ [ 'line', 'No API key' ], [ 'line', 'No API key' ] ], WP_CLI::$calls );
		$this->assertSame( [], $this->http_requests );
	}

	public function test_update_logs_no_locations_found_when_none_have_a_url(): void {
		$this->create_location();
		$this->create_location( [ 'location_url' => '' ] );

		$this->assertSame( [ [ 'line', 'No locations found' ] ], $this->update() );
		$this->assertSame( [], $this->http_requests );
	}

	public function test_update_defaults_to_any_status_mai_location_with_a_url(): void {
		$publish = $this->create_location( [ 'location_url' => 'https://publish.example/' ] );
		$draft   = $this->create_location( [ 'location_url' => 'https://draft.example/' ], [ 'post_status' => 'draft' ] );
		$this->create_location( [ 'location_url' => 'https://trash.example/' ], [ 'post_status' => 'trash' ] );
		$this->create_location( [ 'location_url' => '' ] );
		$post = self::factory()->post->create( [ 'post_type' => 'post' ] );
		update_post_meta( $post, 'location_url', 'https://post.example/' );

		$this->assertSame( [ [ 'line', '2 found' ], [ 'success', 'Done.' ] ], $this->update() );
		$this->assertEqualsCanonicalizing( [ 'https://publish.example/', 'https://draft.example/' ], $this->requested_urls() );
		$this->assertNotSame( $publish, $draft );
	}

	public function test_update_post_type_and_post_status_args(): void {
		$this->create_location( [ 'location_url' => 'https://publish.example/' ] );
		$this->create_location( [ 'location_url' => 'https://draft.example/' ], [ 'post_status' => 'draft' ] );
		$post = self::factory()->post->create( [ 'post_type' => 'post' ] );
		update_post_meta( $post, 'location_url', 'https://post.example/' );

		$this->assertSame( [ [ 'line', '1 found' ], [ 'success', 'Done.' ] ], $this->update( [ 'post_type' => 'post' ] ) );
		$this->assertSame( [ 'https://post.example/' ], $this->requested_urls() );

		WP_CLI::reset();
		$this->http_requests = [];

		$this->assertSame( [ [ 'line', '1 found' ], [ 'success', 'Done.' ] ], $this->update( [ 'post_status' => 'draft' ] ) );
		$this->assertSame( [ 'https://draft.example/' ], $this->requested_urls() );
	}

	public function test_update_posts_per_page_and_offset_page_through_newest_first(): void {
		$this->create_location( [ 'location_url' => 'https://oldest.example/' ], [ 'post_date' => '2026-01-01 00:00:00' ] );
		$this->create_location( [ 'location_url' => 'https://middle.example/' ], [ 'post_date' => '2026-02-01 00:00:00' ] );
		$this->create_location( [ 'location_url' => 'https://newest.example/' ], [ 'post_date' => '2026-03-01 00:00:00' ] );

		$this->assertSame( [ [ 'line', '1 found' ], [ 'success', 'Done.' ] ], $this->update( [ 'posts_per_page' => '1', 'offset' => '1' ] ) );
		$this->assertSame( [ 'https://middle.example/' ], $this->requested_urls() );
	}

	public function test_update_failed_website_changes_nothing_and_logs_only_totals(): void {
		$id                                    = $this->create_location( [ 'location_url' => 'https://down.example/' ] );
		$this->sites['https://down.example/'] = [ 500, self::page( 'Desc', self::FIXTURES . '/image.jpg' ) ];

		$this->assertSame( [ [ 'line', '1 found' ], [ 'success', 'Done.' ] ], $this->update() );
		$this->assertFalse( has_excerpt( $id ) );
		$this->assertSame( 0, get_post_thumbnail_id( $id ) );
	}

	public function test_update_sets_excerpt_from_og_description_when_location_has_none(): void {
		$id                                   = $this->create_location( [ 'location_url' => 'https://inn.example/' ] );
		$this->sites['https://inn.example/'] = [ 200, self::page( 'Rooms & suites by the river' ) ];

		$this->assertSame(
			[
				[ 'line', '1 found' ],
				[ 'line', 'Excerpt updated: ' . get_permalink( $id ) ],
				[ 'success', 'Done.' ],
			],
			$this->update()
		);
		// Runs as no user, so kses encodes the ampersand on save.
		$this->assertSame( 'Rooms &amp; suites by the river', get_post( $id )->post_excerpt );
	}

	public function test_update_never_overwrites_an_existing_excerpt_unless_forced(): void {
		$id                                   = $this->create_location( [ 'location_url' => 'https://inn.example/' ], [ 'post_excerpt' => 'Keep me' ] );
		$this->sites['https://inn.example/'] = [ 200, self::page( 'From the website' ) ];

		$this->assertSame( [ [ 'line', '1 found' ], [ 'success', 'Done.' ] ], $this->update() );
		$this->assertSame( 'Keep me', get_post( $id )->post_excerpt );

		WP_CLI::reset();
		$this->assertSame( [ [ 'line', '1 found' ], [ 'success', 'Done.' ] ], $this->update( [ 'force_excerpt' => 'false' ] ) );
		$this->assertSame( 'Keep me', get_post( $id )->post_excerpt );

		WP_CLI::reset();
		$this->assertSame(
			[
				[ 'line', '1 found' ],
				[ 'line', 'Excerpt updated: ' . get_permalink( $id ) ],
				[ 'success', 'Done.' ],
			],
			$this->update( [ 'force_excerpt' => 'true' ] )
		);
		$this->assertSame( 'From the website', get_post( $id )->post_excerpt );
	}

	/**
	 * Added September 15, 2026 on Mike's call, so a run can fetch missing photos without
	 * writing excerpts onto every location that lacks one.
	 */
	public function test_update_skip_excerpt_leaves_excerpts_alone_and_still_sets_the_image(): void {
		$path                                = self::FIXTURES . '/image.jpg';
		$id                                  = $this->create_location( [ 'location_url' => 'https://inn.example/' ], [ 'post_excerpt' => '' ] );
		$this->sites['https://inn.example/'] = [ 200, self::page( 'From the website', $path ) ];

		$this->update( [ 'skip_excerpt' => 'true' ] );

		$this->assertSame( '', get_post( $id )->post_excerpt );
		$this->assertNotSame( 0, get_post_thumbnail_id( $id ) );
	}

	public function test_update_skip_image_leaves_featured_images_alone_and_still_sets_the_excerpt(): void {
		$path                                = self::FIXTURES . '/image.jpg';
		$id                                  = $this->create_location( [ 'location_url' => 'https://inn.example/' ], [ 'post_excerpt' => '' ] );
		$this->sites['https://inn.example/'] = [ 200, self::page( 'From the website', $path ) ];

		$this->update( [ 'skip_image' => 'true' ] );

		$this->assertSame( 'From the website', get_post( $id )->post_excerpt );
		$this->assertSame( 0, get_post_thumbnail_id( $id ) );
	}

	public function test_update_sets_featured_image_from_og_image_when_location_has_none(): void {
		$path                                 = self::FIXTURES . '/image.jpg';
		$id                                   = $this->create_location( [ 'location_url' => 'https://inn.example/' ] );
		$this->sites['https://inn.example/'] = [ 200, self::page( '', $path ) ];

		$this->assertSame(
			[
				[ 'line', '1 found' ],
				[ 'line', 'Image updated: ' . get_permalink( $id ) ],
				[ 'success', 'Done.' ],
			],
			$this->update()
		);

		$thumbnail = get_post_thumbnail_id( $id );
		$this->assertGreaterThan( 0, $thumbnail );
		$this->assertSame( $path, get_post_meta( $thumbnail, 'original_url', true ) );
		$this->assertSame(
			[ 'https://inn.example/', wp_get_upload_dir()['baseurl'] . '/mai-locations/' . md5( $path ) . '.jpg' ],
			$this->requested_urls()
		);
		$this->assertFalse( has_excerpt( $id ) );
	}

	public function test_update_never_overwrites_an_existing_featured_image_unless_forced(): void {
		$path     = self::FIXTURES . '/image.jpg';
		$id       = $this->create_location( [ 'location_url' => 'https://inn.example/' ] );
		$original = self::factory()->attachment->create_object( [ 'file' => 'original.jpg', 'post_mime_type' => 'image/jpeg' ] );
		set_post_thumbnail( $id, $original );
		$this->sites['https://inn.example/'] = [ 200, self::page( '', $path ) ];

		$this->assertSame( [ [ 'line', '1 found' ], [ 'success', 'Done.' ] ], $this->update() );
		$this->assertSame( $original, get_post_thumbnail_id( $id ) );
		$this->assertSame( [ 'https://inn.example/' ], $this->requested_urls() );

		WP_CLI::reset();
		$this->assertSame(
			[
				[ 'line', '1 found' ],
				[ 'line', 'Image updated: ' . get_permalink( $id ) ],
				[ 'success', 'Done.' ],
			],
			$this->update( [ 'force_image' => '1' ] )
		);
		$this->assertNotSame( $original, get_post_thumbnail_id( $id ) );
	}

	public function test_update_forced_image_already_featured_logs_nothing_and_downloads_nothing(): void {
		$path                                 = self::FIXTURES . '/image.jpg';
		$id                                   = $this->create_location( [ 'location_url' => 'https://inn.example/' ] );
		$this->sites['https://inn.example/'] = [ 200, self::page( '', $path ) ];
		$this->update();
		$thumbnail = get_post_thumbnail_id( $id );

		WP_CLI::reset();
		$this->http_requests = [];

		$this->assertSame( [ [ 'line', '1 found' ], [ 'success', 'Done.' ] ], $this->update( [ 'force_image' => true ] ) );
		$this->assertSame( $thumbnail, get_post_thumbnail_id( $id ) );
		$this->assertSame( [ 'https://inn.example/' ], $this->requested_urls() );
	}

	public function test_update_reuses_one_attachment_for_locations_sharing_an_og_image(): void {
		$path  = self::FIXTURES . '/image.jpg';
		$first = $this->create_location( [ 'location_url' => 'https://one.example/' ], [ 'post_date' => '2026-02-01 00:00:00' ] );
		$other = $this->create_location( [ 'location_url' => 'https://two.example/' ], [ 'post_date' => '2026-01-01 00:00:00' ] );
		$this->sites['https://one.example/'] = [ 200, self::page( '', $path ) ];
		$this->sites['https://two.example/'] = [ 200, self::page( '', $path ) ];

		$this->assertSame(
			[
				[ 'line', '2 found' ],
				[ 'line', 'Image updated: ' . get_permalink( $first ) ],
				[ 'line', 'Image updated: ' . get_permalink( $other ) ],
				[ 'success', 'Done.' ],
			],
			$this->update()
		);
		$this->assertSame( get_post_thumbnail_id( $first ), get_post_thumbnail_id( $other ) );
		$this->assertCount( 3, $this->requested_urls() );
	}

	public function test_update_sets_excerpt_and_image_in_one_pass(): void {
		$path                                 = self::FIXTURES . '/image.jpg';
		$id                                   = $this->create_location( [ 'location_url' => 'https://inn.example/' ] );
		$this->sites['https://inn.example/'] = [ 200, self::page( 'Riverside inn', $path ) ];

		$this->assertSame(
			[
				[ 'line', '1 found' ],
				[ 'line', 'Excerpt updated: ' . get_permalink( $id ) ],
				[ 'line', 'Image updated: ' . get_permalink( $id ) ],
				[ 'success', 'Done.' ],
			],
			$this->update()
		);
	}

	public function test_pins_bug_twitter_only_website_sets_nothing(): void {
		$id                                   = $this->create_location( [ 'location_url' => 'https://inn.example/' ] );
		$this->sites['https://inn.example/'] = [ 200, (string) file_get_contents( self::FIXTURES . '/page-twitter-only.html' ) ];

		// Correct behaviour: the twitter:description fallback would set the excerpt.
		$this->assertSame( [ [ 'line', '1 found' ], [ 'success', 'Done.' ] ], $this->update() );
		$this->assertFalse( has_excerpt( $id ) );
	}

	public function test_pins_bug_sideload_error_passes_wp_error_to_set_post_thumbnail_and_logs_success(): void {
		$id                                   = $this->create_location( [ 'location_url' => 'https://inn.example/' ] );
		$this->sites['https://inn.example/'] = [ 200, self::page( '', self::FIXTURES . '/not-an-image.jpg' ) ];

		[ $calls, $warnings ] = $this->capture_errors( fn() => $this->update() );

		// Correct behaviour: treat the WP_Error as a failure, log it, and leave the thumbnail alone.
		$this->assertSame(
			[
				[ 'line', '1 found' ],
				[ 'line', 'Image updated: ' . get_permalink( $id ) ],
				[ 'success', 'Done.' ],
			],
			$calls
		);
		$this->assertSame( [ 'Object of class WP_Error could not be converted to int' ], $warnings );
		$this->assertSame( 0, get_post_thumbnail_id( $id ) );
	}
}

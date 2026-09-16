<?php

declare(strict_types=1);

namespace Mai\Locations\Tests\Integration\Data;

use Mai\Locations\Tests\Integration\Data\Support\CapturesErrors;
use Mai\Locations\Tests\Integration\Data\Support\HttpMock;
use Mai\Locations\Tests\TestCase;
use WP_Error;

require_once __DIR__ . '/Support/HttpMock.php';
require_once __DIR__ . '/Support/CapturesErrors.php';

/**
 * Pins mailocations_upload_image().
 *
 * It reads $image_url with file_get_contents(), which the HTTP API cannot intercept, so these
 * tests pass local fixture paths. The second fetch goes through download_url() and is mocked.
 */
final class UploadImageTest extends TestCase {

	use HttpMock;
	use CapturesErrors;

	private const FIXTURES = __DIR__ . '/fixtures';
	private const REF      = 'https://inn.example/og-image.jpg';

	private int $post_id;

	public function set_up(): void {
		parent::set_up();

		$this->post_id = $this->create_location();
	}

	public function tear_down(): void {
		$this->clean_uploads();

		parent::tear_down();
	}

	private function serve_uploads(): void {
		$this->mock_http( fn( string $url, array $args ) => self::serve_upload( $url, $args ) ?? new WP_Error( 'unmocked', $url ) );
	}

	public function test_returns_existing_attachment_with_matching_reference_without_fetching(): void {
		$this->serve_uploads();
		$existing = self::factory()->attachment->create_object( [ 'file' => 'existing.jpg', 'post_mime_type' => 'image/jpeg' ] );
		update_post_meta( $existing, 'original_url', self::REF );

		$this->assertSame( $existing, mailocations_upload_image( self::REF, 'original_url', '/does/not/exist.jpg', $this->post_id ) );
		$this->assertSame( [], $this->http_requests );
	}

	public function test_reference_lookup_uses_the_given_meta_key(): void {
		$this->serve_uploads();
		$existing = self::factory()->attachment->create_object( [ 'file' => 'existing.jpg', 'post_mime_type' => 'image/jpeg' ] );
		update_post_meta( $existing, 'original_url', self::REF );

		// Same value under another key is not a match, so it goes on to read the (empty) file.
		$this->assertSame( 0, mailocations_upload_image( self::REF, 'places_ref', self::FIXTURES . '/empty.jpg', $this->post_id ) );
	}

	public function test_saves_image_by_re_downloading_it_from_the_sites_own_uploads_url(): void {
		$this->serve_uploads();
		$path    = self::FIXTURES . '/image.jpg';
		$hashed  = md5( $path ) . '.jpg';
		$uploads = wp_get_upload_dir();

		$id = mailocations_upload_image( self::REF, 'original_url', $path, $this->post_id );

		$this->assertIsInt( $id );
		$this->assertGreaterThan( 0, $id );

		// Known problem: a needless round trip, and it fails on sites with self-signed certificates.
		$this->assertSame( [ $uploads['baseurl'] . '/mai-locations/' . $hashed ], array_column( $this->http_requests, 'url' ) );
		$this->assertSame( 300, $this->http_requests[0]['args']['timeout'] );
		$this->assertTrue( $this->http_requests[0]['args']['stream'] );

		$this->assertSame( $this->post_id, get_post( $id )->post_parent );
		$this->assertSame( md5( $path ), get_post( $id )->post_title );
		$this->assertSame( 'image/jpeg', get_post_mime_type( $id ) );
		$this->assertSame( $hashed, wp_basename( (string) get_attached_file( $id ) ) );
		$this->assertSame( self::REF, get_post_meta( $id, 'original_url', true ) );

		// The staging copy is deleted, the folder stays.
		$this->assertFileDoesNotExist( $uploads['basedir'] . '/mai-locations/' . $hashed );
		$this->assertDirectoryExists( $uploads['basedir'] . '/mai-locations' );

		// Not set as the featured image; callers do that.
		$this->assertSame( 0, get_post_thumbnail_id( $this->post_id ) );
	}

	public function test_pins_bug_png_is_staged_as_jpg(): void {
		$this->serve_uploads();
		$path   = self::FIXTURES . '/image.png';
		$hashed = md5( $path ) . '.jpg';

		$id = mailocations_upload_image( self::REF, 'original_url', $path, $this->post_id );

		// Correct behaviour: keep the real extension. WordPress renames it on sideload.
		$this->assertStringEndsWith( '/mai-locations/' . $hashed, $this->http_requests[0]['url'] );
		$this->assertIsInt( $id );
		$this->assertSame( 'image/png', get_post_mime_type( $id ) );
		$this->assertSame( md5( $path ) . '.png', wp_basename( (string) get_attached_file( $id ) ) );
	}

	public function test_empty_file_returns_zero_without_a_request(): void {
		$this->serve_uploads();

		$this->assertSame( 0, mailocations_upload_image( self::REF, 'original_url', self::FIXTURES . '/empty.jpg', $this->post_id ) );
		$this->assertSame( [], $this->http_requests );
	}

	public function test_pins_bug_unreadable_image_url_warns_and_returns_zero(): void {
		$this->serve_uploads();

		[ $result, $warnings ] = $this->capture_errors( fn() => mailocations_upload_image( self::REF, 'original_url', self::FIXTURES . '/missing.jpg', $this->post_id ) );

		// Correct behaviour: fetch through the HTTP API and fail quietly with a WP_Error or 0.
		$this->assertSame( 0, $result );
		$this->assertCount( 1, $warnings );
		$this->assertStringContainsString( 'Failed to open stream: No such file or directory', $warnings[0] );
		$this->assertSame( [], $this->http_requests );
	}

	/**
	 * Fixed September 16, 2026. The WP_Error from download_url() was passed to wp_delete_file(),
	 * and unlink() threw a TypeError that took the whole CLI run with it. This is the failure
	 * Herd's self-signed certificate produces.
	 */
	public function test_failed_download_deletes_only_the_staged_file_and_returns_zero(): void {
		$this->mock_http( fn() => new WP_Error( 'http_request_failed', 'cURL error 60: SSL certificate problem' ) );
		$path     = self::FIXTURES . '/image.jpg';
		$uploads  = wp_get_upload_dir();
		$deleted  = [];

		add_filter(
			'wp_delete_file',
			static function ( $file ) use ( &$deleted ) {
				$deleted[] = is_object( $file ) ? get_class( $file ) : $file;

				return $file;
			}
		);

		$result = mailocations_upload_image( self::REF, 'original_url', $path, $this->post_id );

		// Only the staged file is deleted, and the run carries on to the next location.
		$this->assertSame( [ $uploads['basedir'] . '/mai-locations/' . md5( $path ) . '.jpg' ], $deleted );
		$this->assertSame( 0, $result );
		$this->assertSame( [], get_posts( [ 'post_type' => 'attachment', 'post_status' => 'any', 'fields' => 'ids' ] ) );
	}

	public function test_sideload_error_returns_wp_error_and_saves_no_reference(): void {
		$this->serve_uploads();

		$result = mailocations_upload_image( self::REF, 'original_url', self::FIXTURES . '/not-an-image.jpg', $this->post_id );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'upload_error', $result->get_error_code() );
		$this->assertSame( [], get_posts( [ 'post_type' => 'attachment', 'post_status' => 'any', 'fields' => 'ids' ] ) );
	}
}

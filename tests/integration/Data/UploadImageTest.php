<?php

declare(strict_types=1);

namespace Mai\Locations\Tests\Integration\Data;

use Mai\Locations\Tests\Integration\Data\Support\HttpMock;
use Mai\Locations\Tests\TestCase;
use WP_Error;

require_once __DIR__ . '/Support/HttpMock.php';

/**
 * Pins mailocations_upload_image().
 *
 * Since September 16, 2026 it fetches the image once, over the HTTP API, so these tests serve
 * the fixture bytes through the mock rather than passing local paths.
 */
final class UploadImageTest extends TestCase {

	use HttpMock;

	private const FIXTURES = __DIR__ . '/fixtures';
	private const REF      = 'https://inn.example/og-image.jpg';
	private const URL      = 'https://inn.example/photo.jpg';

	private int $post_id;

	public function set_up(): void {
		parent::set_up();

		$this->post_id = $this->create_location();
	}

	public function tear_down(): void {
		$this->clean_uploads();

		parent::tear_down();
	}

	/**
	 * Answers every request with the given fixture, under the given content type.
	 */
	private function serve_image( string $fixture, string $mime = 'image/jpeg', int $code = 200 ): void {
		$this->mock_http(
			static fn() => self::response( $code, (string) file_get_contents( self::FIXTURES . '/' . $fixture ), [ 'content-type' => $mime ] )
		);
	}

	public function test_returns_existing_attachment_with_matching_reference_without_fetching(): void {
		$this->serve_image( 'image.jpg' );
		$existing = self::factory()->attachment->create_object( [ 'file' => 'existing.jpg', 'post_mime_type' => 'image/jpeg' ] );
		update_post_meta( $existing, 'original_url', self::REF );

		$this->assertSame( $existing, mailocations_upload_image( self::REF, 'original_url', self::URL, $this->post_id ) );
		$this->assertSame( [], $this->http_requests );
	}

	public function test_reference_lookup_uses_the_given_meta_key(): void {
		$this->serve_image( 'image.jpg' );
		$existing = self::factory()->attachment->create_object( [ 'file' => 'existing.jpg', 'post_mime_type' => 'image/jpeg' ] );
		update_post_meta( $existing, 'original_url', self::REF );

		// The same value under another key is not a match, so it fetches and makes a new one.
		$id = mailocations_upload_image( self::REF, 'places_ref', self::URL, $this->post_id );

		$this->assertNotSame( $existing, $id );
		$this->assertSame( self::REF, get_post_meta( $id, 'places_ref', true ) );
	}

	/**
	 * Fixed September 16, 2026. It used to write the image into uploads and then download that
	 * copy back through the site's own URL, which is a needless round trip and fails outright on
	 * a self-signed certificate.
	 */
	public function test_fetches_the_image_once_and_sideloads_it(): void {
		$this->serve_image( 'image.jpg' );

		$id = mailocations_upload_image( self::REF, 'original_url', self::URL, $this->post_id );

		$this->assertIsInt( $id );
		$this->assertGreaterThan( 0, $id );

		// One request, to the image itself, with a browser user agent.
		$this->assertSame( [ self::URL ], array_column( $this->http_requests, 'url' ) );
		$this->assertStringStartsWith( 'Mozilla/5.0 ', $this->http_requests[0]['args']['user-agent'] );

		$this->assertSame( $this->post_id, get_post( $id )->post_parent );
		$this->assertSame( 'image/jpeg', get_post_mime_type( $id ) );
		$this->assertSame( md5( self::URL ) . '.jpg', wp_basename( (string) get_attached_file( $id ) ) );
		$this->assertSame( self::REF, get_post_meta( $id, 'original_url', true ) );

		// Not set as the featured image; callers do that.
		$this->assertSame( 0, get_post_thumbnail_id( $this->post_id ) );
	}

	/**
	 * Fixed September 16, 2026. Every image was staged as .jpg whatever it was.
	 */
	public function test_png_keeps_its_own_extension(): void {
		$this->serve_image( 'image.png', 'image/png' );

		$id = mailocations_upload_image( self::REF, 'original_url', 'https://inn.example/photo.png', $this->post_id );

		$this->assertIsInt( $id );
		$this->assertSame( 'image/png', get_post_mime_type( $id ) );
		$this->assertSame( md5( 'https://inn.example/photo.png' ) . '.png', wp_basename( (string) get_attached_file( $id ) ) );
	}

	public function test_empty_body_returns_zero(): void {
		$this->mock_http( static fn() => self::response( 200, '' ) );

		$this->assertSame( 0, mailocations_upload_image( self::REF, 'original_url', self::URL, $this->post_id ) );
		$this->assertSame( [], get_posts( [ 'post_type' => 'attachment', 'post_status' => 'any', 'fields' => 'ids' ] ) );
	}

	public function test_failed_request_returns_zero_quietly(): void {
		$this->mock_http( static fn() => new WP_Error( 'http_request_failed', 'cURL error 60: SSL certificate problem' ) );

		$this->assertSame( 0, mailocations_upload_image( self::REF, 'original_url', self::URL, $this->post_id ) );
		$this->assertSame( [], get_posts( [ 'post_type' => 'attachment', 'post_status' => 'any', 'fields' => 'ids' ] ) );
	}

	public function test_non_200_response_returns_zero(): void {
		$this->serve_image( 'image.jpg', 'image/jpeg', 404 );

		$this->assertSame( 0, mailocations_upload_image( self::REF, 'original_url', self::URL, $this->post_id ) );
		$this->assertSame( [], get_posts( [ 'post_type' => 'attachment', 'post_status' => 'any', 'fields' => 'ids' ] ) );
	}

	public function test_sideload_error_returns_wp_error_and_saves_no_reference(): void {
		// A file type WordPress will not accept, so media_handle_sideload() refuses it.
		$this->serve_image( 'not-an-image.jpg', 'application/octet-stream' );

		$result = mailocations_upload_image( self::REF, 'original_url', 'https://inn.example/payload.exe', $this->post_id );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( [], get_posts( [ 'post_type' => 'attachment', 'post_status' => 'any', 'fields' => 'ids' ] ) );
	}
}

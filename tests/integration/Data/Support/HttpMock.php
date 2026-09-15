<?php

declare(strict_types=1);

namespace Mai\Locations\Tests\Integration\Data\Support;

use WP_Error;

/**
 * Intercepts every HTTP request so no test touches the network, and records what was sent.
 */
trait HttpMock {

	/**
	 * @var array<int, array{url: string, args: array<string, mixed>}>
	 */
	protected array $http_requests = [];

	/**
	 * @param callable(string, array<string, mixed>): (array<string, mixed>|WP_Error) $responder
	 */
	protected function mock_http( callable $responder ): void {
		add_filter(
			'pre_http_request',
			function ( $pre, $args, $url ) use ( $responder ) {
				$this->http_requests[] = [ 'url' => (string) $url, 'args' => (array) $args ];

				return $responder( (string) $url, (array) $args );
			},
			10,
			3
		);
	}

	/**
	 * @param array<string, string> $headers
	 *
	 * @return array<string, mixed>
	 */
	protected static function response( int $code, string $body = '', array $headers = [] ): array {
		return [
			'headers'  => $headers,
			'body'     => $body,
			'response' => [
				'code'    => $code,
				'message' => get_status_header_desc( $code ),
			],
			'cookies'  => [],
			'filename' => null,
		];
	}

	/**
	 * Answers requests for the test site's uploads URL from disk, writing streamed downloads to
	 * the requested file the way a real round trip would. Returns null for any other URL.
	 *
	 * @param array<string, mixed> $args
	 *
	 * @return array<string, mixed>|null
	 */
	protected static function serve_upload( string $url, array $args ): ?array {
		$uploads = wp_get_upload_dir();

		if ( ! str_starts_with( $url, $uploads['baseurl'] . '/' ) ) {
			return null;
		}

		$path = $uploads['basedir'] . substr( $url, strlen( $uploads['baseurl'] ) );

		if ( ! is_file( $path ) ) {
			return self::response( 404, 'Not Found' );
		}

		$bytes = (string) file_get_contents( $path );

		if ( ! empty( $args['stream'] ) && ! empty( $args['filename'] ) ) {
			file_put_contents( (string) $args['filename'], $bytes );

			return array_merge( self::response( 200 ), [ 'filename' => $args['filename'] ] );
		}

		return self::response( 200, $bytes );
	}

	/**
	 * Deletes attachments and their files, plus the plugin's staging folder in uploads.
	 */
	protected function clean_uploads(): void {
		$ids = get_posts(
			[
				'post_type'   => 'attachment',
				'post_status' => 'any',
				'numberposts' => -1,
				'fields'      => 'ids',
			]
		);

		foreach ( $ids as $id ) {
			wp_delete_attachment( (int) $id, true );
		}

		$dir = wp_get_upload_dir()['basedir'] . '/mai-locations';

		if ( is_dir( $dir ) ) {
			array_map( 'unlink', glob( $dir . '/*' ) ?: [] );
			rmdir( $dir );
		}
	}
}

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
 * Pins mailocations_get_data_from_website(). Visit Sleepy Hollow calls it directly with 'image'.
 */
final class GetDataFromWebsiteTest extends TestCase {

	use HttpMock;
	use CapturesErrors;

	private const URL = 'https://inn.example/';

	private function serve( int $code, string $body ): void {
		$this->mock_http( fn() => self::response( $code, $body ) );
	}

	private static function fixture( string $name ): string {
		return (string) file_get_contents( __DIR__ . '/fixtures/' . $name );
	}

	public function test_reads_og_description_and_og_image_ignoring_twitter_tags(): void {
		$this->serve( 200, self::fixture( 'page-og.html' ) );

		$this->assertSame(
			[
				'image' => 'https://inn.example/og-image.jpg',
				'desc'  => 'Rooms & suites by the river',
			],
			mailocations_get_data_from_website( self::URL )
		);
	}

	public function test_single_key_returns_just_that_string(): void {
		$this->serve( 200, self::fixture( 'page-og.html' ) );

		$this->assertSame( 'https://inn.example/og-image.jpg', mailocations_get_data_from_website( self::URL, 'image' ) );
		$this->assertSame( 'Rooms & suites by the river', mailocations_get_data_from_website( self::URL, 'desc' ) );
	}

	/**
	 * Changed September 16, 2026. WordPress's default agent and 5 second timeout left 44 of Visit
	 * Sleepy Hollow's 106 sites returning nothing.
	 */
	public function test_sends_one_get_with_a_browser_user_agent_and_a_longer_timeout(): void {
		$this->serve( 200, '' );

		mailocations_get_data_from_website( self::URL );

		$this->assertCount( 1, $this->http_requests );
		$request = $this->http_requests[0];

		$this->assertSame( self::URL, $request['url'] );
		$this->assertSame( 'GET', $request['args']['method'] );
		$this->assertSame( 15, $request['args']['timeout'] );
		$this->assertStringStartsWith( 'Mozilla/5.0 ', $request['args']['user-agent'] );
		$this->assertSame( 5, $request['args']['redirection'] );
	}

	public function test_request_args_are_filterable(): void {
		$this->serve( 200, '' );

		add_filter(
			'mailocations_website_request_args',
			static function ( array $args ): array {
				$args['timeout'] = 30;

				return $args;
			}
		);

		mailocations_get_data_from_website( self::URL );

		$this->assertSame( 30, $this->http_requests[0]['args']['timeout'] );
	}

	public function test_failed_request_returns_empty_values(): void {
		$this->mock_http( fn() => new WP_Error( 'http_request_failed', 'cURL error 28: Operation timed out' ) );

		$this->assertSame( [ 'image' => '', 'desc' => '' ], mailocations_get_data_from_website( self::URL ) );
		$this->assertSame( '', mailocations_get_data_from_website( self::URL, 'image' ) );
		$this->assertSame( '', mailocations_get_data_from_website( self::URL, 'desc' ) );
	}

	/**
	 * @dataProvider provide_rejected_codes
	 */
	public function test_codes_other_than_200_and_403_return_empty_values_without_parsing( int $code ): void {
		$this->serve( $code, self::fixture( 'page-og.html' ) );

		$this->assertSame( [ 'image' => '', 'desc' => '' ], mailocations_get_data_from_website( self::URL ) );
		$this->assertSame( '', mailocations_get_data_from_website( self::URL, 'image' ) );
	}

	/**
	 * @return array<string, array{0: int}>
	 */
	public static function provide_rejected_codes(): array {
		return [
			'301' => [ 301 ],
			'404' => [ 404 ],
			'500' => [ 500 ],
		];
	}

	public function test_403_body_is_still_parsed(): void {
		$this->serve( 403, self::fixture( 'page-og.html' ) );

		$this->assertSame( 'https://inn.example/og-image.jpg', mailocations_get_data_from_website( self::URL, 'image' ) );
	}

	public function test_empty_body_returns_empty_values(): void {
		$this->serve( 200, '' );

		$this->assertSame( [ 'image' => '', 'desc' => '' ], mailocations_get_data_from_website( self::URL ) );
		$this->assertSame( '', mailocations_get_data_from_website( self::URL, 'image' ) );
	}

	public function test_body_of_only_an_uppercase_doctype_counts_as_empty(): void {
		$this->serve( 200, '<!DOCTYPE html>' );

		$this->assertSame( '', mailocations_get_data_from_website( self::URL, 'desc' ) );
	}

	/**
	 * Fixed September 16, 2026. The fallback sat behind `! array_values( $data )`, always false,
	 * so twitter: tags were never read.
	 */
	public function test_twitter_tags_fill_in_what_og_tags_did_not(): void {
		$this->serve( 200, self::fixture( 'page-twitter-only.html' ) );

		$this->assertSame(
			[
				'image' => 'https://inn.example/twitter-image.jpg',
				'desc'  => 'Twitter description',
			],
			mailocations_get_data_from_website( self::URL )
		);
	}

	public function test_ignores_og_in_name_twitter_in_property_secure_url_and_plain_description(): void {
		$this->serve( 200, self::fixture( 'page-wrong-attributes.html' ) );

		$this->assertSame( [ 'image' => '', 'desc' => '' ], mailocations_get_data_from_website( self::URL ) );
	}

	public function test_last_og_tag_of_each_kind_wins(): void {
		$this->serve( 200, self::fixture( 'page-two-og-images.html' ) );

		$this->assertSame(
			[
				'image' => 'https://inn.example/second.jpg',
				'desc'  => 'Second description',
			],
			mailocations_get_data_from_website( self::URL )
		);
	}

	public function test_missing_content_attribute_gives_empty_string(): void {
		$this->serve( 200, '<html><head><meta property="og:image"><meta property="og:description" content="Desc"></head></html>' );

		$this->assertSame( [ 'image' => '', 'desc' => 'Desc' ], mailocations_get_data_from_website( self::URL ) );
	}

	public function test_value_is_not_trimmed(): void {
		$this->serve( 200, '<meta property="og:description" content="  Spaced  ">' );

		$this->assertSame( '  Spaced  ', mailocations_get_data_from_website( self::URL, 'desc' ) );
	}

	/**
	 * Fixed September 16, 2026. An unknown key used to warn and return null.
	 */
	public function test_unknown_key_returns_an_empty_string( ): void {
		$this->serve( 200, self::fixture( 'page-og.html' ) );

		[ $result, $warnings ] = $this->capture_errors( fn() => mailocations_get_data_from_website( self::URL, 'title' ) );

		$this->assertSame( '', $result );
		$this->assertSame( [], $warnings );
	}
}

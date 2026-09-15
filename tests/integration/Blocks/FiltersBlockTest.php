<?php

declare(strict_types=1);

namespace Mai\Locations\Tests\Integration\Blocks;

use Mai\Locations\Tests\TestCase;
use RuntimeException;

final class FiltersBlockTest extends TestCase {

	/**
	 * ACF_Local_Meta adds its filters once, on first use. The test case restores hooks after every test,
	 * so they vanish after the first block render in the process and get_field() stops seeing block data.
	 */
	private function restore_acf_local_meta_filters(): void {
		$meta = acf_get_instance( 'ACF_Local_Meta' );

		if ( ! has_filter( 'acf/pre_load_meta', [ $meta, 'pre_load_meta' ] ) ) {
			add_filter( 'acf/pre_load_post_id', [ $meta, 'pre_load_post_id' ], 1, 2 );
			add_filter( 'acf/pre_load_meta', [ $meta, 'pre_load_meta' ], 1, 2 );
			add_filter( 'acf/pre_load_metadata', [ $meta, 'pre_load_metadata' ], 1, 4 );
		}
	}

	public function set_up(): void {
		parent::set_up();
		$this->restore_acf_local_meta_filters();

		// post_action() calls exit after redirecting, so the redirect is thrown instead.
		add_filter(
			'wp_redirect',
			static function ( $location ): void {
				throw new RuntimeException( (string) $location );
			}
		);
	}

	public function tear_down(): void {
		$_POST = [];
		parent::tear_down();
	}

	private function redirect_from_post_action(): ?string {
		try {
			( new \Mai_Locations_Filters_Block() )->post_action();
		} catch ( RuntimeException $e ) {
			return $e->getMessage();
		}

		return null;
	}

	public function test_admin_post_hooks_are_registered_for_guests_and_users(): void {
		$this->assertTrue( has_action( 'admin_post_mailocations_filters' ) );
		$this->assertTrue( has_action( 'admin_post_nopriv_mailocations_filters' ) );
	}

	public function test_renders_a_post_form_around_inner_blocks(): void {
		// The redirect field is the current URL with the query string removed.
		$this->go_to( home_url( '/?post_type=mai_location&foo=bar' ) );

		$html = do_blocks( '<!-- wp:acf/mai-locations-filters {} --><!-- wp:paragraph --><p>Inner</p><!-- /wp:paragraph --><!-- /wp:acf/mai-locations-filters -->' );

		$this->assertMatchesRegularExpression(
			'#^<form class="mai-locations-filters" method="post" action="http://example.org/wp-admin/admin-post.php">'
			. '<input type="hidden" name="action" value="mailocations_filters">'
			. '<input type="hidden" name="redirect" value="http://example.org/">'
			. '<input type="hidden" id="mailocations_filters_nonce" name="mailocations_filters_nonce" value="[a-f0-9]{10}" />'
			. '<input type="hidden" name="_wp_http_referer" value="/\?post_type=mai_location&\#038;foo=bar" />'
			. '<div class="acf-innerblocks-container"><p class="wp-block-paragraph">Inner</p></div></form>$#',
			$html
		);
	}

	public function test_template_lists_the_default_inner_blocks(): void {
		$template = ( new \Mai_Locations_Filters_Block() )->get_template();

		$this->assertSame( [ 'acf/mai-locations-address-search', 'core/spacer', 'acf/mai-locations-filter', 'core/spacer', 'core/buttons' ], array_column( $template, 0 ) );
		$this->assertSame( [ 'core/button', 'core/button' ], array_column( $template[4][2], 0 ) );
		$this->assertTrue( $template[4][2][0][1]['maiLocationsFilterSubmit'] );
		$this->assertTrue( $template[4][2][1][1]['maiLocationsFilterClear'] );
	}

	public function test_bails_without_a_valid_nonce(): void {
		$_POST = [ 'redirect' => 'http://example.org/locations/' ];
		$this->assertNull( $this->redirect_from_post_action() );

		$_POST['mailocations_filters_nonce'] = 'bad';
		$this->assertNull( $this->redirect_from_post_action() );
	}

	public function test_bails_without_a_redirect(): void {
		$_POST = [ 'mailocations_filters_nonce' => wp_create_nonce( 'mailocations_filters' ) ];

		$this->assertNull( $this->redirect_from_post_action() );
	}

	public function test_redirect_carries_filters_address_and_distance(): void {
		$_POST = [
			'mailocations_filters_nonce' => wp_create_nonce( 'mailocations_filters' ),
			'redirect'                   => 'http://example.org/locations/?page=2',
			'mailocations_filters'       => [ '_mai_location_cat' => 'hotels', '_empty' => '' ],
			'mailocations_address'       => wp_slash( wp_json_encode( [ 'address' => 'Sleepy Hollow, NY', 'lat' => 41.08, 'lng' => -73.86 ] ) ),
			'mailocations_distance'      => '50',
			'mailocations_unit'          => 'km',
		];

		$this->assertSame(
			'http://example.org/locations/?page=2&_mai_location_cat=hotels&address=Sleepy%20Hollow,%20NY&lat=41.08&lng=-73.86&distance=50&unit=km',
			$this->redirect_from_post_action()
		);
	}

	public function test_address_without_coordinates_skips_distance(): void {
		$_POST = [
			'mailocations_filters_nonce' => wp_create_nonce( 'mailocations_filters' ),
			'redirect'                   => 'http://example.org/locations/',
			'mailocations_address'       => wp_json_encode( [ 'address' => 'Somewhere' ] ),
			'mailocations_distance'      => '50',
		];

		$this->assertSame( 'http://example.org/locations/?address=Somewhere', $this->redirect_from_post_action() );
	}

	public function test_defaults_distance_and_unit_when_not_posted(): void {
		$_POST = [
			'mailocations_filters_nonce' => wp_create_nonce( 'mailocations_filters' ),
			'redirect'                   => 'http://example.org/locations/',
			'mailocations_address'       => wp_json_encode( [ 'address' => 'Here', 'lat' => '1', 'lng' => '2' ] ),
		];

		$this->assertSame( 'http://example.org/locations/?address=Here&lat=1&lng=2&distance=100&unit=mi', $this->redirect_from_post_action() );
	}

	public function test_external_redirect_falls_back_to_admin(): void {
		$_POST = [
			'mailocations_filters_nonce' => wp_create_nonce( 'mailocations_filters' ),
			'redirect'                   => 'https://elsewhere.test/',
		];

		$this->assertSame( 'http://example.org/wp-admin/', $this->redirect_from_post_action() );
	}
}

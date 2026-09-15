<?php

declare(strict_types=1);

namespace Mai\Locations\Tests\Integration\Blocks;

use Mai\Locations\Tests\TestCase;

final class TableBlockTest extends TestCase {

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
	}

	public function tear_down(): void {
		set_current_screen( 'front' );
		parent::tear_down();
	}

	public function test_pins_bug_empty_settings_pass_null_to_kses_and_render_nothing_for_guests(): void {
		// get_field() returns null for unset fields, and shortcode_atts() keeps the null over the defaults.
		// Correct behaviour: unset settings fall back to the table defaults without a deprecation notice.
		$errors = [];
		set_error_handler(
			static function ( int $errno, string $errstr ) use ( &$errors ): bool {
				$errors[] = $errstr;
				return true;
			}
		);

		try {
			$html = do_blocks( '<!-- wp:acf/mai-locations-table {} /-->' );
		} finally {
			restore_error_handler();
		}

		$this->assertSame( '', $html );
		$this->assertSame( [ 'preg_replace(): Passing null to parameter #3 ($subject) of type array|string is deprecated' ], $errors );
	}

	public function test_renders_the_users_locations_on_the_front_end(): void {
		$user = self::factory()->user->create( [ 'role' => 'author' ] );
		wp_set_current_user( $user );

		$public  = $this->create_location( [ 'address_city' => 'Sleepy Hollow', 'address_state' => 'NY', 'address_postcode' => '10591' ], [ 'post_author' => $user, 'post_title' => 'Hollow Inn', 'post_name' => 'hollow-inn' ] );
		$pending = $this->create_location( [], [ 'post_author' => $user, 'post_title' => 'Pending One', 'post_status' => 'pending' ] );
		$this->create_location( [], [ 'post_author' => $user, 'post_title' => 'Draft', 'post_status' => 'draft' ] );

		// The Edit link is built from the current request URL.
		$this->go_to( home_url( '/' ) );

		$html = do_blocks( '<!-- wp:acf/mai-locations-table {"data":{"locations_table_title":"Mine","locations_table_header":"Places","locations_no_results":"None"}} /-->' );

		$this->assertStringStartsWith( "\t\t\t\t<style>", $html );

		// The title h2 sits inside the table element, and a space follows the closing tag.
		$table = '<table class="mai-locations-table"><h2>Mine</h2><thead><tr><th colspan="2">Places</th></tr></thead><tbody>'
			. '<tr><td><span class="mai-location-item-title"><a href="http://example.org/?mai_location=hollow-inn">Hollow Inn</a></span>'
			. '<div itemprop="address" itemscope itemtype="http://schema.org/PostalAddress" class="mai-address"><div class="mai-address-item"><span class="locality" itemprop="addressLocality">Sleepy Hollow</span><span class="region" itemprop="addressRegion">&nbsp;NY</span></div></div></td>'
			. '<td style="text-align:right;white-space:nowrap;"><a class="button button-secondary button-small" href="http://example.org/?mai_location=hollow-inn">View</a>'
			. '<a style="margin-left:6px;" class="button button-secondary button-small" href="http://example.org/?location_id=' . $public . '&#038;referrer=http://example.org/">Edit</a></td></tr>'
			. '<tr><td><span class="mai-location-item-title">(pending) Pending One</span></td>'
			. '<td style="text-align:right;white-space:nowrap;"><a style="margin-left:6px;" class="button button-secondary button-small" href="http://example.org/?location_id=' . $pending . '&#038;referrer=http://example.org/">Edit</a></td></tr>'
			. '</tbody></table> ';

		$this->assertStringEndsWith( $table, $html );
	}

	public function test_admin_preview_with_no_locations_shows_a_notice_and_skips_the_field_check(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		set_current_screen( 'edit-post' );

		// It returns before the "Field not available" check, so the bogus field is not reported.
		$html = mailocations_get_locations_table( [ 'fields' => [ 'mai_location_title', 'bogus' ] ] );

		$this->assertSame(
			"<h2>My Locations</h2>\n<table>\n<tr>\n<th><em>No locations exist. Add new locations to display them here.</em></th>\n</tr>\n</table>\n",
			$html
		);
	}
}

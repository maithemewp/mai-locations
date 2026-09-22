<?php

declare(strict_types=1);

namespace Mai\Locations\Tests\Integration\Display;

use Mai\Locations\Tests\TestCase;
use Mai_Locations_Locations_Table;

/**
 * Pins Mai_Locations_Locations_Table::get() and the [mai_locations_table] shortcode.
 *
 * mailocation_get_user_locations() caches per post type for the whole process, so each
 * front end test registers its own throwaway post type. That keeps results independent of
 * test order and leaves the real mai_location cache unprimed for other suites.
 */
final class LocationsTableTest extends TestCase {

	private const ACTIONS_TD = '<td style="text-align:right;white-space:nowrap;">';
	private const BUTTON     = 'button button-secondary button-small';

	private string $post_type = '';

	private string $request_uri_backup = '';

	public function set_up(): void {
		parent::set_up();
		$this->request_uri_backup = $_SERVER['REQUEST_URI'] ?? '';
		$_SERVER['REQUEST_URI']   = '/account/';
	}

	public function tear_down(): void {
		if ( $this->post_type ) {
			unregister_post_type( $this->post_type );
		}

		$_SERVER['REQUEST_URI']     = $this->request_uri_backup;
		$GLOBALS['current_screen'] = null;
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	private function register_throwaway_type( string $name ): string {
		register_post_type( $name, [ 'public' => true ] );
		$this->post_type = $name;

		return $name;
	}

	private function log_in(): int {
		$user_id = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $user_id );

		return $user_id;
	}

	private function go_admin(): void {
		if ( ! function_exists( 'set_current_screen' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
			require_once ABSPATH . 'wp-admin/includes/screen.php';
		}

		set_current_screen( 'edit' );
	}

	private function edit_url( int $id ): string {
		// The referrer is URL encoded, so a referrer carrying its own query string survives.
		return 'http://example.org/account/?location_id=' . $id . '&#038;referrer=' . rawurlencode( 'http://example.org/account/' );
	}

	/**
	 * Changed September 16, 2026. It used to return null.
	 */
	public function test_logged_out_returns_an_empty_string(): void {
		$this->assertSame( '', ( new Mai_Locations_Locations_Table() )->get() );
		$this->assertSame( '', mailocations_get_locations_table() );
		$this->assertSame( '', do_shortcode( '[mai_locations_table post_type="mlt_logged_out"]' ) );
	}

	public function test_front_end_without_locations(): void {
		$type = $this->register_throwaway_type( 'mlt_front_none' );
		$this->log_in();

		// Someone else's location does not count.
		$this->create_location( [], [ 'post_type' => $type, 'post_author' => self::factory()->user->create() ] );

		$this->assertSame( "<h2>My Locations</h2>\n<p>Sorry, no locations available.</p>\n", mailocations_get_locations_table( [ 'post_type' => $type ] ) );
	}

	public function test_front_end_without_locations_custom_text(): void {
		$type = $this->register_throwaway_type( 'mlt_front_none_txt' );
		$this->log_in();

		// wp_kses_post() drops the script tags but keeps their text.
		$this->assertSame(
			'<p>None <strong>yet</strong>.x</p>' . "\n",
			mailocations_get_locations_table( [ 'post_type' => $type, 'title' => '', 'no_results' => 'None <strong>yet</strong>.<script>x</script>' ] )
		);
	}

	public function test_front_end_with_locations(): void {
		$type    = $this->register_throwaway_type( 'mlt_front_with' );
		$user_id = $this->log_in();

		$published = $this->create_location(
			[
				'address_street'   => '381 N Broadway',
				'address_street_2' => 'Suite 2',
				'address_city'     => 'Tarrytown',
				'address_state'    => 'NY',
				'address_postcode' => '10591',
				'address_country'  => 'US',
			],
			[ 'post_type' => $type, 'post_author' => $user_id, 'post_title' => 'Lyndhurst', 'post_date' => '2026-01-02 00:00:00' ]
		);
		$pending = $this->create_location( [], [ 'post_type' => $type, 'post_author' => $user_id, 'post_title' => 'Kykuit', 'post_status' => 'pending', 'post_date' => '2026-01-01 00:00:00' ] );
		// Drafts are listed since 2.0.0, so their owner can reach one a site set to arrive as a
		// draft. It sorts first here, having no post_date of its own.
		$draft = $this->create_location( [], [ 'post_type' => $type, 'post_author' => $user_id, 'post_title' => 'Draft', 'post_status' => 'draft' ] );

		$html = mailocations_get_locations_table( [ 'post_type' => $type ] );

		[ $style, $table ] = explode( '</style>', $html, 2 );

		$this->assertSame(
			'<style> .mai-location-item-title, .mai-locations-table .mai-address { line-height: 1.25; } .mai-location-item-title { display: block; } .mai-locations-table .mai-address { display: flex; flex-wrap: wrap; margin-top: 0.5em; font-size: 0.8em; } .mai-locations-table .mai-address-item:not(:last-of-type)::after { margin-right: 0.25em; content: \',\'; }',
			trim( (string) preg_replace( '/\s+/', ' ', $style ) )
		);

		$link = get_permalink( $published );

		// The template whitespace after </style> and the space after </table> are pinned as they are.
		$this->assertSame(
			"\n\t\t\t\t" . '<h2>My Locations</h2><table class="mai-locations-table"><thead><tr><th colspan="2">Locations</th></tr></thead><tbody>'
			. '<tr><td><span class="mai-location-item-title">(draft) Draft</span></td>'
			. self::ACTIONS_TD . '<a style="margin-left:6px;" class="' . self::BUTTON . '" href="' . $this->edit_url( $draft ) . '">Edit</a></td></tr>'
			. '<tr><td><span class="mai-location-item-title"><a href="' . $link . '">Lyndhurst</a></span>'
			. '<div itemprop="address" itemscope itemtype="http://schema.org/PostalAddress" class="mai-address">'
			. '<div class="mai-address-item"><span class="street-address" itemprop="streetAddress">381 N Broadway</span></div>'
			. '<div class="mai-address-item"><span class="locality" itemprop="addressLocality">Tarrytown</span><span class="region" itemprop="addressRegion">&nbsp;NY</span></div>'
			. '</div></td>'
			. self::ACTIONS_TD . '<a class="' . self::BUTTON . '" href="' . $link . '">View</a><a style="margin-left:6px;" class="' . self::BUTTON . '" href="' . $this->edit_url( $published ) . '">Edit</a></td></tr>'
			. '<tr><td><span class="mai-location-item-title">(pending) Kykuit</span></td>'
			. self::ACTIONS_TD . '<a style="margin-left:6px;" class="' . self::BUTTON . '" href="' . $this->edit_url( $pending ) . '">Edit</a></td></tr>'
			. '</tbody></table> ',
			$table
		);
	}

	/**
	 * The class arg was sanitized and never printed until September 16, 2026.
	 */
	public function test_front_end_header_title_and_class(): void {
		$type    = $this->register_throwaway_type( 'mlt_front_args' );
		$user_id = $this->log_in();
		$this->create_location( [], [ 'post_type' => $type, 'post_author' => $user_id, 'post_title' => 'Philipsburg' ] );

		$html = mailocations_get_locations_table( [ 'post_type' => $type, 'title' => '', 'header' => '<b>Mine</b>', 'class' => 'my-table' ] );

		$this->assertStringContainsString( '<table class="mai-locations-table my-table"><thead><tr><th colspan="2">&lt;b&gt;Mine&lt;/b&gt;</th></tr></thead>', $html );
		$this->assertStringNotContainsString( '<h2>', $html );
	}

	public function test_non_viewable_post_type_has_no_links_or_view_button(): void {
		register_post_type( 'mlt_private', [ 'public' => false ] );
		$this->post_type = 'mlt_private';
		$user_id         = $this->log_in();
		$id              = $this->create_location( [], [ 'post_type' => 'mlt_private', 'post_author' => $user_id, 'post_title' => 'Hidden' ] );

		$html = mailocations_get_locations_table( [ 'post_type' => 'mlt_private' ] );

		$this->assertStringContainsString(
			'<tr><td><span class="mai-location-item-title">Hidden</span></td>' . self::ACTIONS_TD . '<a style="margin-left:6px;" class="' . self::BUTTON . '" href="' . $this->edit_url( $id ) . '">Edit</a></td></tr>',
			$html
		);
	}

	public function test_empty_post_type_falls_back_to_mai_location(): void {
		$this->log_in();
		$table = new Mai_Locations_Locations_Table( [ 'post_type' => '' ] );

		$args = ( fn() => $this->args )->call( $table );

		$this->assertSame( 'mai_location', $args['post_type'] );
	}

	public function test_admin_without_locations(): void {
		$this->log_in();
		$this->go_admin();

		$this->assertSame(
			"<h2>My Locations</h2>\n<table>\n<tr>\n<th><em>No locations exist. Add new locations to display them here.</em></th>\n</tr>\n</table>\n",
			mailocations_get_locations_table( [ 'fields' => [ 'not_a_field' ] ] )
		);
	}

	public function test_admin_shows_first_two_of_any_author_and_field_notice(): void {
		$this->log_in();
		$other = self::factory()->user->create();

		$newest = $this->create_location( [], [ 'post_author' => $other, 'post_title' => 'Newest', 'post_date' => '2026-01-03 00:00:00' ] );
		$middle = $this->create_location( [], [ 'post_author' => $other, 'post_title' => 'Middle', 'post_status' => 'pending', 'post_date' => '2026-01-02 00:00:00' ] );
		$this->create_location( [], [ 'post_author' => $other, 'post_title' => 'Oldest', 'post_date' => '2026-01-01 00:00:00' ] );

		$this->go_admin();

		$html = mailocations_get_locations_table( [ 'fields' => [ 'not_a_field' ] ] );
		$link = get_permalink( $newest );

		$this->assertSame(
			'<style>.mai-locations-table a { pointer-events: none; }</style>'
			. '<h2>My Locations</h2><table class="mai-locations-table"><thead><tr><th colspan="2">Locations</th></tr></thead><tbody>'
			. '<tr><td><span class="mai-location-item-title"><a href="' . $link . '">Newest</a></span></td>'
			. self::ACTIONS_TD . '<a class="' . self::BUTTON . '" href="' . $link . '">View</a><a style="margin-left:6px;" class="' . self::BUTTON . '" href="' . $this->edit_url( $newest ) . '">Edit</a></td></tr>'
			. '<tr><td><span class="mai-location-item-title">(pending) Middle</span></td>'
			. self::ACTIONS_TD . '<a style="margin-left:6px;" class="' . self::BUTTON . '" href="' . $this->edit_url( $middle ) . '">Edit</a></td></tr>'
			. '</tbody></table> '
			. '<p style="padding:16px;border:1px solid red;">not_a_field: Field not available on selected post type</p>',
			$html
		);
	}

	public function test_shortcode_matches_function(): void {
		$type    = $this->register_throwaway_type( 'mlt_shortcode' );
		$user_id = $this->log_in();
		$this->create_location( [], [ 'post_type' => $type, 'post_author' => $user_id, 'post_title' => 'Van Cortlandt' ] );

		$this->assertSame(
			mailocations_get_locations_table( [ 'post_type' => $type, 'title' => 'Places' ] ),
			do_shortcode( '[mai_locations_table post_type="mlt_shortcode" title="Places"]' )
		);
	}
}

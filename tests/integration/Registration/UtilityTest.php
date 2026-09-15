<?php

declare(strict_types=1);

namespace Mai\Locations\Tests\Integration\Registration;

use Mai\Locations\Tests\TestCase;

/**
 * Helpers in includes/functions-utility.php other than labels and options.
 */
final class UtilityTest extends TestCase {

	/**
	 * @var array<string, mixed>
	 */
	private array $saved_server = [];

	/**
	 * @var array<string, mixed>
	 */
	private array $saved_get = [];

	public function set_up(): void {
		parent::set_up();

		$this->saved_server = $_SERVER;
		$this->saved_get    = $_GET;
	}

	public function tear_down(): void {
		$_SERVER = $this->saved_server;
		$_GET    = $this->saved_get;

		set_current_screen( 'front' );

		parent::tear_down();
	}

	public function test_current_url_clean_removes_only_the_given_keys(): void {
		$_SERVER['REQUEST_URI'] = '/locations/?lat=41&foo=bar';

		$this->assertSame( 'http://example.org/locations/?foo=bar', mailocations_get_current_url_clean( [ 'lat' => '41' ] ) );
	}

	public function test_current_url_clean_defaults_to_get_keys(): void {
		$_SERVER['REQUEST_URI'] = '/locations/?lat=41&foo=bar';
		$_GET                   = [ 'lat' => '41', 'foo' => 'bar' ];

		$this->assertSame( 'http://example.org/locations/', mailocations_get_current_url_clean() );
	}

	public function test_current_url_clean_with_empty_array_keeps_the_query(): void {
		$_SERVER['REQUEST_URI'] = '/locations/?lat=41';
		$_GET                   = [ 'lat' => '41' ];

		$this->assertSame( 'http://example.org/locations/?lat=41', mailocations_get_current_url_clean( [] ) );
	}

	public function test_delete_transients_removes_only_mai_locations_prefixed_transients(): void {
		set_transient( 'mai_locations_markers_abc', [ 1 ], HOUR_IN_SECONDS );
		set_transient( 'mai_locationsother', 'x' );
		set_transient( 'mailocations_markers', 'x' );
		set_transient( 'other_markers', 'x' );

		$this->assertNull( mailocations_delete_transients() );

		$this->assertFalse( get_transient( 'mai_locations_markers_abc' ) );
		$this->assertFalse( get_option( '_transient_timeout_mai_locations_markers_abc' ) );

		// A plain prefix match, so no underscore is needed after `mai_locations`.
		$this->assertFalse( get_transient( 'mai_locationsother' ) );
		$this->assertSame( 'x', get_transient( 'mailocations_markers' ) );
		$this->assertSame( 'x', get_transient( 'other_markers' ) );
	}

	public function test_delete_transients_with_nothing_to_delete(): void {
		$this->assertNull( mailocations_delete_transients() );
	}

	public function test_user_can_edit_only_for_the_logged_in_author(): void {
		$author  = self::factory()->user->create();
		$other   = self::factory()->user->create();
		$post_id = $this->create_location( [], [ 'post_author' => $author ] );

		wp_set_current_user( 0 );
		$this->assertFalse( mailocations_user_can_edit( $post_id ) );

		wp_set_current_user( $author );
		$this->assertTrue( mailocations_user_can_edit( $post_id ) );
		$this->assertTrue( mailocations_user_can_edit( (string) $post_id ) );

		wp_set_current_user( $other );
		$this->assertFalse( mailocations_user_can_edit( $post_id ) );
	}

	public function test_user_can_edit_ignores_capabilities(): void {
		$post_id = $this->create_location( [], [ 'post_author' => self::factory()->user->create() ] );

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		$this->assertFalse( mailocations_user_can_edit( $post_id ) );
	}

	public function test_user_can_edit_missing_post(): void {
		wp_set_current_user( self::factory()->user->create() );

		$this->assertFalse( mailocations_user_can_edit( 999999 ) );
	}

	public function test_get_asset_reads_the_build_asset_file(): void {
		$this->assertSame( require MAI_LOCATIONS_PLUGIN_DIR . 'build/mai-locations.asset.php', mailocations_get_asset( 'mai-locations' ) );
	}

	public function test_get_asset_falls_back_to_plugin_version(): void {
		$this->assertSame( [ 'dependencies' => [], 'version' => MAI_LOCATIONS_VERSION ], mailocations_get_asset( 'does-not-exist' ) );
	}

	public function test_stylesheet_link_is_returned_once_per_filename(): void {
		$asset = require MAI_LOCATIONS_PLUGIN_DIR . 'build/mai-locations-sortable-styles.asset.php';

		$this->assertSame(
			sprintf( '<link rel="stylesheet" href="%sbuild/mai-locations-sortable-styles.css?ver=%s" />', MAI_LOCATIONS_PLUGIN_URL, $asset['version'] ),
			mailocations_get_stylesheet_link( 'mai-locations-sortable' )
		);
		$this->assertNull( mailocations_get_stylesheet_link( 'mai-locations-sortable' ) );
	}

	public function test_stylesheet_link_is_empty_in_admin_without_using_up_the_once(): void {
		$asset = require MAI_LOCATIONS_PLUGIN_DIR . 'build/mai-locations-form-styles.asset.php';

		set_current_screen( 'edit.php' );
		$this->assertNull( mailocations_get_stylesheet_link( 'mai-locations-form' ) );

		set_current_screen( 'front' );
		$this->assertSame(
			sprintf( '<link rel="stylesheet" href="%sbuild/mai-locations-form-styles.css?ver=%s" />', MAI_LOCATIONS_PLUGIN_URL, $asset['version'] ),
			mailocations_get_stylesheet_link( 'mai-locations-form' )
		);
		$this->assertNull( mailocations_get_stylesheet_link( 'mai-locations-form' ) );
	}
}

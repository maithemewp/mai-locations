<?php

declare(strict_types=1);

namespace Mai\Locations\Tests\Integration\Data;

use Mai\Locations\Tests\Integration\Data\Support\CapturesErrors;
use Mai\Locations\Tests\Integration\Data\Support\RedirectException;
use Mai\Locations\Tests\TestCase;
use Mai_Locations_Location_Import;

require_once __DIR__ . '/Support/CapturesErrors.php';
require_once __DIR__ . '/Support/RedirectException.php';

/**
 * Pins the CSV importer on the Location Import options page.
 *
 * The import runs from acf/save_post on that page, reads the CSV attachment named in
 * $_POST['acf'], then redirects and exits. These tests drive maybe_import_locations() the same
 * way, as an administrator, and stop the exit by throwing from the wp_redirect filter.
 */
final class LocationImportTest extends TestCase {

	use CapturesErrors;

	private const FIXTURES = __DIR__ . '/fixtures';
	private const SCREEN   = 'mai_location_page_location-import';

	private const NO_PASSWORD_WARNING = 'wp_insert_user(): The user_pass field is required when creating a new user. The user will need to reset their password before logging in.';

	public function set_up(): void {
		parent::set_up();

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		add_filter(
			'wp_redirect',
			static function ( string $location ): never {
				throw new RedirectException( $location );
			}
		);
	}

	public function tear_down(): void {
		$_POST = [];
		$_GET  = [];
		unset( $GLOBALS['current_screen'] );

		parent::tear_down();
	}

	/**
	 * Submits the import page with the given CSV and returns the redirect and deprecations raised.
	 *
	 * @param array<string, mixed> $acf               Extra or replacement $_POST['acf'] values.
	 * @param list<string>         $expected_warnings Warnings, other than the str_getcsv deprecations, the import must raise.
	 *
	 * @return array{redirect: string|null, query: array<string, string>, deprecations: list<string>}
	 */
	private function run_import( string $csv, array $acf = [], array $expected_warnings = [] ): array {
		$file_id = self::factory()->attachment->create_object( [ 'file' => $csv, 'post_mime_type' => 'text/csv' ] );

		set_current_screen( self::SCREEN );

		$_POST['acf'] = array_merge(
			[
				'mailocations_import_file'            => (string) $file_id,
				'mailocations_location_import_status' => 'publish',
			],
			$acf
		);

		$redirect = null;

		[ , $errors ] = $this->capture_errors(
			static function () use ( &$redirect ): void {
				try {
					( new Mai_Locations_Location_Import() )->maybe_import_locations( 'options' );
				} catch ( RedirectException $e ) {
					$redirect = $e->location;
				}
			},
			E_DEPRECATED | E_USER_WARNING
		);

		$deprecations = array_values( array_filter( $errors, fn( string $message ) => str_starts_with( $message, 'str_getcsv():' ) ) );

		$this->assertSame( $expected_warnings, array_values( array_diff( $errors, $deprecations ) ) );

		$query = [];

		if ( $redirect ) {
			parse_str( (string) wp_parse_url( $redirect, PHP_URL_QUERY ), $query );
		}

		return [
			'redirect'     => $redirect,
			'query'        => $query,
			'deprecations' => $deprecations,
		];
	}

	private function location_by_title( string $title ): \WP_Post {
		$posts = get_posts(
			[
				'post_type'   => 'mai_location',
				'post_status' => 'any',
				'title'       => $title,
				'numberposts' => -1,
			]
		);

		$this->assertCount( 1, $posts, "Expected one location titled {$title}" );

		return $posts[0];
	}

	public function test_hooks_and_priorities(): void {
		$import = new Mai_Locations_Location_Import();

		$this->assertSame( 12, has_action( 'acf/init', [ $import, 'register_page' ] ) );
		$this->assertSame( 10, has_action( 'acf/init', [ $import, 'register_fields' ] ) );
		$this->assertSame( 10, has_filter( 'acf/load_field/key=mailocations_location_import_user_role', [ $import, 'load_roles' ] ) );
		$this->assertSame( 10, has_filter( 'acf/load_field/key=mailocations_location_import_status', [ $import, 'load_statuses' ] ) );
		$this->assertSame( 10, has_action( 'mai_location_page_location-import', [ $import, 'confirmation' ] ) );
		$this->assertSame( 4, has_action( 'acf/save_post', [ $import, 'maybe_import_locations' ] ) );
	}

	public function test_register_page_adds_location_import_options_sub_page(): void {
		$page = acf_get_options_page( 'location-import' );

		$this->assertIsArray( $page );
		$this->assertSame( 'Location Import', $page['page_title'] );
		$this->assertSame( 'edit.php?post_type=mai_location', $page['parent_slug'] );
		$this->assertSame( 'manage_options', $page['capability'] );
		$this->assertSame( 'Import Now', $page['update_button'] );
		$this->assertSame( 'Imported', $page['updated_message'] );
	}

	public function test_register_fields_adds_the_import_field_group(): void {
		$group = acf_get_local_field_group( 'mailocations_location_import_field_group' );

		$this->assertIsArray( $group );
		$this->assertSame( [ [ [ 'param' => 'options_page', 'operator' => '==', 'value' => 'location-import' ] ] ], $group['location'] );

		$fields = [];

		foreach ( acf_get_fields( 'mailocations_location_import_field_group' ) as $field ) {
			$fields[ $field['key'] ] = [ $field['name'], $field['type'], $field['label'] ];
		}

		$this->assertSame(
			[
				'mailocations_import_description'         => [ '', 'message', '' ],
				// The label's mismatched bracket is in the code today.
				'mailocations_import_file'                => [ 'location_import_file', 'file', 'File (.csv]' ],
				'mailocations_location_import_status'     => [ 'location_status', 'radio', 'Status' ],
				'mailocations_location_import_users'      => [ 'location_users', 'true_false', 'Create/Update Users' ],
				'mailocations_location_import_user_role'  => [ 'location_user_role', 'radio', 'User Role' ],
			],
			$fields
		);

		$message = acf_get_local_field( 'mailocations_import_description' )['message'];
		// The download link has a stray quote after the download attribute.
		$this->assertStringContainsString( 'assets/csv/mai-locations-import-template.csv" target="_blank" download">Download example CSV file</a>', $message );
	}

	public function test_load_roles_and_statuses_leave_field_alone_outside_admin(): void {
		$import = new Mai_Locations_Location_Import();
		$field  = [ 'key' => 'x', 'choices' => [ 'old' => 'Old' ] ];

		$this->assertSame( $field, $import->load_roles( $field ) );
		$this->assertSame( $field, $import->load_statuses( $field ) );
	}

	public function test_load_roles_in_admin_lists_roles_reversed_without_administrator(): void {
		set_current_screen( 'edit-mai_location' );

		$field = ( new Mai_Locations_Location_Import() )->load_roles( [ 'key' => 'x', 'choices' => [ 'old' => 'Old' ] ] );

		$this->assertSame(
			[
				'subscriber'  => 'Subscriber',
				'contributor' => 'Contributor',
				'author'      => 'Author',
				'editor'      => 'Editor',
			],
			$field['choices']
		);
		$this->assertSame( 'subscriber', $field['default_value'] );
	}

	public function test_load_statuses_in_admin_uses_get_post_statuses(): void {
		set_current_screen( 'edit-mai_location' );

		$field = ( new Mai_Locations_Location_Import() )->load_statuses( [ 'key' => 'x' ] );

		$this->assertSame( get_post_statuses(), $field['choices'] );
		$this->assertSame( [ 'draft', 'pending', 'private', 'publish' ], array_keys( $field['choices'] ) );
		$this->assertSame( 'publish', $field['default_value'] );
	}

	public function test_confirmation_reads_the_real_request_not_the_get_superglobal(): void {
		$_GET = [ 'confirmation' => '1', 'imported' => '3' ];

		// filter_input() ignores $_GET, and there is no request on the command line, so nothing prints.
		$this->expectOutputString( '' );

		( new Mai_Locations_Location_Import() )->confirmation();
	}

	public function test_maybe_import_does_nothing_without_acf_post_data(): void {
		set_current_screen( self::SCREEN );

		( new Mai_Locations_Location_Import() )->maybe_import_locations( 'options' );

		$this->assertSame( [], $_POST );
	}

	public function test_maybe_import_leaves_post_data_when_not_options_or_not_our_screen(): void {
		$_POST['acf'] = [ 'mailocations_import_file' => '1' ];

		set_current_screen( self::SCREEN );
		( new Mai_Locations_Location_Import() )->maybe_import_locations( 123 );
		$this->assertSame( [ 'mailocations_import_file' => '1' ], $_POST['acf'] );

		unset( $GLOBALS['current_screen'] );
		( new Mai_Locations_Location_Import() )->maybe_import_locations( 'options' );
		$this->assertSame( [ 'mailocations_import_file' => '1' ], $_POST['acf'] );

		set_current_screen( 'edit-mai_location' );
		( new Mai_Locations_Location_Import() )->maybe_import_locations( 'options' );
		$this->assertSame( [ 'mailocations_import_file' => '1' ], $_POST['acf'] );
	}

	public function test_maybe_import_removes_post_data_and_stops_without_a_file(): void {
		set_current_screen( self::SCREEN );

		$_POST['acf'] = [ 'mailocations_location_import_status' => 'publish' ];
		( new Mai_Locations_Location_Import() )->maybe_import_locations( 'options' );
		$this->assertArrayNotHasKey( 'acf', $_POST );

		// A post that is not an attachment has no attached file.
		$_POST['acf'] = [ 'mailocations_import_file' => (string) self::factory()->post->create() ];
		( new Mai_Locations_Location_Import() )->maybe_import_locations( 'options' );
		$this->assertArrayNotHasKey( 'acf', $_POST );
	}

	public function test_imports_the_shipped_template_csv(): void {
		$this->setExpectedDeprecated( 'get_page_by_title' );

		$result = $this->run_import( MAI_LOCATIONS_PLUGIN_DIR . 'assets/csv/mai-locations-import-template.csv' );

		$this->assertSame(
			admin_url( 'edit.php?post_type=mai_location&page=location-import' ) . '&confirmation=1&imported=1&skipped=0&failed=0&users_imported=0&users_skipped=0&users_failed=0',
			$result['redirect']
		);
		$this->assertArrayNotHasKey( 'acf', $_POST );

		$post = $this->location_by_title( 'Example Location' );

		$this->assertSame( 'publish', $post->post_status );
		$this->assertSame( '', $post->post_content );
		$this->assertSame( (int) get_current_user_id(), (int) $post->post_author );

		// The template repeats the address_street header, so the second column ("Suite 2") wins.
		$this->assertSame( 'Suite 2', get_post_meta( $post->ID, 'address_street', true ) );
		$this->assertSame( '', get_post_meta( $post->ID, 'address_street_2', true ) );
		$this->assertSame( 'Hackettstown', get_post_meta( $post->ID, 'address_city', true ) );
		$this->assertSame( 'NJ', get_post_meta( $post->ID, 'address_state', true ) );
		$this->assertSame( '07840', get_post_meta( $post->ID, 'address_postcode', true ) );
		$this->assertSame( 'US', get_post_meta( $post->ID, 'address_country', true ) );
		$this->assertSame( 'https://bizbudding.com', get_post_meta( $post->ID, 'location_url', true ) );
		$this->assertSame( '(555) 555-1234', get_post_meta( $post->ID, 'location_phone', true ) );
		$this->assertSame( 'team@examplelocation.com', get_post_meta( $post->ID, 'location_email', true ) );

		// User columns are dropped, and the categories do not exist, so none are set.
		$this->assertFalse( metadata_exists( 'post', $post->ID, 'user_email' ) );
		$this->assertFalse( get_user_by( 'email', 'name@example.com' ) );
		$this->assertSame( [], wp_get_object_terms( $post->ID, 'mai_location_cat', [ 'fields' => 'names' ] ) );
	}

	public function test_pins_bug_str_getcsv_raises_a_deprecation_per_csv_line_on_php_84(): void {
		$this->setExpectedDeprecated( 'get_page_by_title' );

		$result = $this->run_import( MAI_LOCATIONS_PLUGIN_DIR . 'assets/csv/mai-locations-import-template.csv' );

		// Correct behaviour: pass the $escape argument so PHP 8.4 raises nothing.
		$this->assertSame(
			array_fill( 0, 2, 'str_getcsv(): the $escape parameter must be provided as its default value will change' ),
			$result['deprecations']
		);
	}

	public function test_pins_bug_every_meta_value_goes_through_esc_html(): void {
		$this->setExpectedDeprecated( 'get_page_by_title' );

		$this->run_import( self::FIXTURES . '/import-escaping.csv' );

		$post = $this->location_by_title( 'Ichabod & Co' );

		// $allowed is undefined in import(), so the per-type callbacks from get_fields() are never used.
		// Correct behaviour: text through esc_html, url through esc_url, email through sanitize_email.
		$this->assertSame( 'Sleepy &lt;Hollow&gt;', get_post_meta( $post->ID, 'address_city', true ) );
		$this->assertSame( 'https://example.com/?a=1&amp;b=2', get_post_meta( $post->ID, 'location_url', true ) );
		$this->assertSame( '555 &amp; 1234', get_post_meta( $post->ID, 'location_phone', true ) );
		$this->assertSame( 'Info@Example.com', get_post_meta( $post->ID, 'location_email', true ) );

		// Title and description are not escaped.
		$this->assertSame( '<p>Long text</p>', $post->post_content );

		// Columns without a registered field are dropped; unset fields get their defaults.
		$this->assertFalse( metadata_exists( 'post', $post->ID, 'not_a_field' ) );
		$this->assertSame( 'US', get_post_meta( $post->ID, 'address_country', true ) );
		$this->assertTrue( metadata_exists( 'post', $post->ID, 'location_phone_2' ) );
		$this->assertSame( '', get_post_meta( $post->ID, 'location_phone_2', true ) );
	}

	public function test_get_fields_keeps_only_fields_with_an_allowed_type(): void {
		$fields = ( new Mai_Locations_Location_Import() )->get_fields();

		$this->assertSame(
			[
				'location_url'      => 'url',
				'location_phone'    => 'text',
				'location_phone_2'  => 'text',
				'location_email'    => 'email',
				'address_country'   => 'select',
				'address_street'    => 'text',
				'address_street_2'  => 'text',
				'address_city'      => 'text',
				'address_state'     => 'select',
				'address_state_int' => 'text',
				'address_postcode'  => 'text',
				'location_lat'      => 'text',
				'location_lng'      => 'text',
				'place_id'          => 'text',
			],
			array_map( fn( array $field ) => $field['type'], $fields )
		);
	}

	public function test_creates_and_links_users_when_enabled(): void {
		$this->setExpectedDeprecated( 'get_page_by_title' );
		$katrina = self::factory()->user->create( [ 'user_email' => 'katrina@example.com', 'role' => 'subscriber' ] );

		$result = $this->run_import(
			self::FIXTURES . '/import-users.csv',
			[
				'mailocations_location_import_users'     => '1',
				'mailocations_location_import_user_role' => 'editor',
			],
			// Users are created without a password, which WordPress warns about for both new users.
			[ self::NO_PASSWORD_WARNING, self::NO_PASSWORD_WARNING ]
		);

		$this->assertSame(
			[
				'post_type'      => 'mai_location',
				'page'           => 'location-import',
				'confirmation'   => '1',
				'imported'       => '3',
				'skipped'        => '0',
				'failed'         => '0',
				'users_imported' => '1',
				'users_skipped'  => '1',
				'users_failed'   => '1',
			],
			$result['query']
		);

		$school  = $this->location_by_title( 'Crane School' );
		$farm    = $this->location_by_title( 'Van Tassel Farm' );
		$stable  = $this->location_by_title( 'Bones Stable' );
		$ichabod = get_user_by( 'email', 'ichabod@example.com' );

		$this->assertInstanceOf( \WP_User::class, $ichabod );
		$this->assertSame( 'ichabod@example.com', $ichabod->user_login );
		$this->assertSame( 'Ichabod Crane', $ichabod->display_name );
		$this->assertSame( 'Ichabod Crane', $ichabod->nickname );
		$this->assertSame( [ 'editor' ], $ichabod->roles );
		$this->assertSame( [ $school->ID ], get_user_meta( $ichabod->ID, 'user_locations', true ) );

		// An existing user is linked but not changed.
		$this->assertSame( [ 'subscriber' ], get_userdata( $katrina )->roles );
		$this->assertSame( [ $farm->ID ], get_user_meta( $katrina, 'user_locations', true ) );

		// A failed user still gets the location imported, just unlinked. The author is the importer.
		$this->assertSame( get_current_user_id(), (int) $school->post_author );
		$this->assertSame( 'Tarrytown', get_post_meta( $stable->ID, 'address_city', true ) );
	}

	public function test_skips_existing_titles_and_rows_without_a_title(): void {
		$this->setExpectedDeprecated( 'get_page_by_title' );
		$church  = $this->create_location( [], [ 'post_title' => 'Old Dutch Church', 'post_status' => 'draft' ] );
		$katrina = self::factory()->user->create( [ 'user_email' => 'katrina@example.com' ] );

		$result = $this->run_import( self::FIXTURES . '/import-skips.csv', [ 'mailocations_location_import_users' => '1' ] );

		$this->assertSame( '1', $result['query']['imported'] );
		$this->assertSame( '2', $result['query']['skipped'] );
		$this->assertSame( '1', $result['query']['users_skipped'] );

		// The existing location is untouched apart from being linked to the user.
		$this->assertSame( $church, $this->location_by_title( 'Old Dutch Church' )->ID );
		$this->assertSame( '', get_post_meta( $church, 'address_city', true ) );
		$this->assertSame( [ $church ], get_user_meta( $katrina, 'user_locations', true ) );
		$this->location_by_title( 'New Place' );
	}

	public function test_assigns_only_categories_that_already_exist_by_name(): void {
		$this->setExpectedDeprecated( 'get_page_by_title' );
		$inns  = self::factory()->term->create( [ 'taxonomy' => 'mai_location_cat', 'name' => 'Inns' ] );
		$parks = self::factory()->term->create( [ 'taxonomy' => 'mai_location_cat', 'name' => 'Parks' ] );

		$this->run_import( self::FIXTURES . '/import-categories.csv' );

		$post = $this->location_by_title( 'Headless Horseman Bridge' );

		$this->assertEqualsCanonicalizing( [ $inns, $parks ], wp_get_object_terms( $post->ID, 'mai_location_cat', [ 'fields' => 'ids' ] ) );
		$this->assertNull( term_exists( 'Missing', 'mai_location_cat' ) );
	}

	public function test_pins_bug_default_status_is_public_when_status_not_posted(): void {
		$this->setExpectedDeprecated( 'get_page_by_title' );
		$file_id = self::factory()->attachment->create_object( [ 'file' => self::FIXTURES . '/import-categories.csv', 'post_mime_type' => 'text/csv' ] );

		set_current_screen( self::SCREEN );
		$_POST['acf'] = [ 'mailocations_import_file' => (string) $file_id ];

		$this->capture_errors(
			static function (): void {
				try {
					( new Mai_Locations_Location_Import() )->maybe_import_locations( 'options' );
				} catch ( RedirectException $e ) {
					unset( $e );
				}
			},
			E_DEPRECATED
		);

		// Correct behaviour: default to 'publish', which the status field also defaults to.
		$this->assertSame( 'public', $this->location_by_title( 'Headless Horseman Bridge' )->post_status );
	}

	public function test_pins_bug_blank_line_in_csv_throws_value_error(): void {
		$this->expectException( \ValueError::class );
		$this->expectExceptionMessage( 'array_combine(): Argument #1 ($keys) and argument #2 ($values) must have the same number of elements' );

		// Correct behaviour: skip blank lines.
		$this->run_import( self::FIXTURES . '/import-blank-line.csv' );
	}
}

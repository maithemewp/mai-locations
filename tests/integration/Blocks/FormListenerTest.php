<?php

declare(strict_types=1);

namespace Mai\Locations\Tests\Integration\Blocks;

use Mai\Locations\Tests\TestCase;
use ReflectionClass;

final class FormListenerTest extends TestCase {

	private \Mai_Locations_Location_Form_Listener $listener;

	private ?object $original_form_front = null;

	/**
	 * @var array<int, string>
	 */
	private array $errors = [];

	public function set_up(): void {
		parent::set_up();

		// Built without the constructor so the plugin's own hooks are not added a second time.
		$this->listener = ( new ReflectionClass( \Mai_Locations_Location_Form_Listener::class ) )->newInstanceWithoutConstructor();

		reset_phpmailer_instance();
	}

	public function tear_down(): void {
		if ( $this->original_form_front ) {
			acf()->form_front = $this->original_form_front;
		}

		$_POST = [];
		$_GET  = [];

		// ACF's front-end submit handler sets this. Left behind, it would make the next test look
		// like a form save.
		unset( $GLOBALS['acf_form'] );

		parent::tear_down();
	}

	/**
	 * acf_form_head() only calls acf()->form_front->enqueue_form(), so a stub counts the calls.
	 */
	private function spy_on_acf_form_head(): object {
		$spy = new class() {
			public int $calls = 0;

			public function enqueue_form(): void {
				$this->calls++;
			}
		};

		$this->original_form_front = acf()->form_front;
		acf()->form_front          = $spy;

		return $spy;
	}

	private function capture_errors( callable $callback ): void {
		set_error_handler(
			function ( int $errno, string $errstr ): bool {
				$this->errors[] = $errstr;
				return true;
			}
		);

		try {
			$callback();
		} finally {
			restore_error_handler();
		}
	}

	/**
	 * @return array<int, array{subject: string, body: string, to: array<int, string>}>
	 */
	private function sent_mail(): array {
		return array_map(
			static fn( array $mail ): array => [
				'to'      => array_column( $mail['to'], 0 ),
				'subject' => $mail['subject'],
				'body'    => $mail['body'],
			],
			$GLOBALS['phpmailer']->mock_sent
		);
	}

	private function page_with( string $content ): int {
		return self::factory()->post->create( [ 'post_type' => 'page', 'post_content' => $content ] );
	}

	public function test_plugin_registers_its_hooks(): void {
		global $wp_filter;

		$found = [];
		foreach ( [ 'get_header', 'acf/save_post', 'acf/update_value/key=mai_location_location', 'pending_to_publish' ] as $hook ) {
			foreach ( $wp_filter[ $hook ]->callbacks as $priority => $callbacks ) {
				foreach ( $callbacks as $callback ) {
					if ( is_array( $callback['function'] ) && $callback['function'][0] instanceof \Mai_Locations_Location_Form_Listener ) {
						$found[] = [ $hook, $callback['function'][1], $priority, $callback['accepted_args'] ];
					}
				}
			}
		}

		$this->assertSame(
			[
				[ 'get_header', 'create_listener', 0, 1 ],
				[ 'get_header', 'edit_listener', 0, 1 ],
				[ 'acf/save_post', 'before_save_post', 4, 1 ],
				[ 'acf/update_value/key=mai_location_location', 'update_lat_lng_place_id_value', 10, 4 ],
				[ 'pending_to_publish', 'send_published_email', 10, 1 ],
			],
			$found
		);
	}

	public function test_create_listener_loads_acf_form_head_on_a_page_with_the_submission_block(): void {
		$spy  = $this->spy_on_acf_form_head();
		$page = $this->page_with( '<!-- wp:acf/mai-location-submission /-->' );
		$this->go_to( get_permalink( $page ) );

		$this->listener->create_listener();
		$this->assertSame( 0, $spy->calls, 'Guests are skipped' );

		wp_set_current_user( self::factory()->user->create() );
		$this->listener->create_listener();
		$this->assertSame( 1, $spy->calls );
	}

	public function test_create_listener_skips_pages_without_the_block_and_non_singular_views(): void {
		$spy = $this->spy_on_acf_form_head();
		wp_set_current_user( self::factory()->user->create() );

		$this->go_to( get_permalink( $this->page_with( '<!-- wp:paragraph --><p>Hi</p><!-- /wp:paragraph -->' ) ) );
		$this->listener->create_listener();

		$this->go_to( home_url( '/' ) );
		$this->listener->create_listener();

		$this->assertSame( 0, $spy->calls );
	}

	public function test_edit_listener_reads_location_id_from_the_real_request_not_get(): void {
		// filter_input( INPUT_GET ) ignores $_GET, so under PHPUnit the location ID is always missing.
		$spy  = $this->spy_on_acf_form_head();
		$user = self::factory()->user->create();
		wp_set_current_user( $user );
		$_GET['location_id'] = (string) $this->create_location( [], [ 'post_author' => $user ] );

		$this->go_to( get_permalink( $this->page_with( '<!-- wp:acf/mai-locations-table /-->' ) ) );
		$this->listener->edit_listener();

		$this->go_to( get_permalink( $this->page_with( '[mai_locations_table]' ) ) );
		$this->listener->edit_listener();

		$this->assertSame( 0, $spy->calls );
	}

	public function test_edit_listener_without_woocommerce_never_calls_is_account_page(): void {
		// class_exists( 'WooCommerce' ) short-circuits, so the missing is_account_page() is never reached.
		$spy = $this->spy_on_acf_form_head();
		wp_set_current_user( self::factory()->user->create() );
		$this->go_to( get_permalink( $this->page_with( 'Plain page' ) ) );

		$this->listener->edit_listener();

		$this->assertFalse( function_exists( 'is_account_page' ) );
		$this->assertSame( 0, $spy->calls );
	}

	public function test_should_update(): void {
		$location = $this->create_location();
		$revision = self::factory()->post->create( [ 'post_type' => 'revision', 'post_parent' => $location, 'post_status' => 'inherit' ] );

		$this->assertFalse( $this->listener->should_update( 'options' ) );
		$this->assertFalse( $this->listener->should_update( self::factory()->post->create() ) );
		$this->assertFalse( $this->listener->should_update( $revision ) );

		// Returns null, not false, when no ACF data was posted.
		$this->assertNull( $this->listener->should_update( $location ) );

		$_POST['acf'] = [ 'field' => 'value' ];
		$this->assertTrue( $this->listener->should_update( $location ) );
		$this->assertTrue( $this->listener->should_update( (string) $location ) );
	}

	public function test_update_lat_lng_place_id_value_copies_map_values_to_meta(): void {
		$id    = $this->create_location();
		$value = [ 'lat' => 41.08, 'lng' => -73.86, 'place_id' => 'abc', 'address' => 'x' ];

		$this->assertSame( $value, $this->listener->update_lat_lng_place_id_value( $value, $id, [], null ) );
		$this->assertSame( '41.08', get_post_meta( $id, 'location_lat', true ) );
		$this->assertSame( '-73.86', get_post_meta( $id, 'location_lng', true ) );
		$this->assertSame( 'abc', get_post_meta( $id, 'place_id', true ) );
	}

	public function test_update_lat_lng_place_id_value_skips_empty_and_non_array_values(): void {
		$id = $this->create_location();

		$this->assertSame( 'text', $this->listener->update_lat_lng_place_id_value( 'text', $id, [], null ) );
		$this->assertSame( [ 'lat' => 0, 'lng' => '' ], $this->listener->update_lat_lng_place_id_value( [ 'lat' => 0, 'lng' => '' ], $id, [], null ) );
		$this->assertSame( [], get_post_meta( $id ) );
	}

	public function test_send_emails(): void {
		$user = self::factory()->user->create( [ 'display_name' => 'Ann Author' ] );
		$id   = $this->create_location( [], [ 'post_name' => 'hollow-inn' ] );

		$this->listener->send_emails( $id, [ 'emails' => 'x@example.org, bad, x@example.org,y@example.org', 'author' => $user ] );

		$this->assertSame(
			[
				[
					'to'      => [ 'x@example.org', 'y@example.org' ],
					'subject' => 'New Location submission from Ann Author',
					'body'    => "There is a new submission from Ann Author.\r\n\r\n"
						. "View the pending post: http://example.org/?mai_location=hollow-inn\r\n\r\n"
						. "Edit post: http://example.org/wp-admin/post.php?post={$id}&action=edit\r\n\r\n"
						. "View all pending Locations: http://example.org/wp-admin/edit.php?post_status=pending&post_type=mai_location\r\n"
						. "View all draft Locations: http://example.org/wp-admin/edit.php?post_status=draft&post_type=mai_location\r\n"
						. "View all published Locations: http://example.org/wp-admin/edit.php?post_status=publish&post_type=mai_location\r\n",
				],
			],
			$this->sent_mail()
		);
	}

	public function test_send_emails_uses_na_without_an_author(): void {
		$id = $this->create_location();

		$this->listener->send_emails( $id, [ 'emails' => 'x@example.org', 'author' => 0 ] );

		$this->assertSame( 'New Location submission from N/A', $this->sent_mail()[0]['subject'] );
	}

	public function test_send_emails_bails_without_valid_addresses(): void {
		$id = $this->create_location();

		$this->listener->send_emails( $id, [ 'emails' => '' ] );
		$this->listener->send_emails( $id, [ 'emails' => 'bad, worse' ] );

		$this->assertSame( [], $this->sent_mail() );
	}

	/**
	 * Fixed September 16, 2026. It read an undefined $post_type, so the label came out empty and
	 * PHP warned on every published location.
	 */
	public function test_published_email_names_the_location_type(): void {
		$user = self::factory()->user->create( [ 'user_email' => 'ann@example.org' ] );
		$id   = $this->create_location( [], [ 'post_author' => $user, 'post_name' => 'hollow-inn' ] );

		$this->capture_errors( fn() => $this->listener->send_published_email( get_post( $id ) ) );

		$this->assertSame( [], $this->errors );
		$this->assertSame(
			[
				[
					'to'      => [ 'ann@example.org' ],
					'subject' => 'Your http://example.org Location has been published!',
					'body'    => "Thank you for your submission!\r\n\r\nView your Location here: http://example.org/?mai_location=hollow-inn\r\n\r\n",
				],
			],
			$this->sent_mail()
		);
	}

	public function test_published_email_skips_other_post_types(): void {
		$this->listener->send_published_email( get_post( self::factory()->post->create() ) );

		$this->assertSame( [], $this->sent_mail() );
	}

	public function test_before_save_post_does_nothing_when_it_should_not_update(): void {
		$id = $this->create_location();
		remove_all_actions( 'acf/save_post' );

		$this->listener->before_save_post( $id );

		$this->assertFalse( has_action( 'acf/save_post' ) );
	}

	public function test_before_save_post_publishes_updates_the_post_and_sends_both_emails(): void {
		$user = self::factory()->user->create( [ 'display_name' => 'Ann Author', 'user_email' => 'ann@example.org' ] );
		$id   = $this->create_location( [], [ 'post_author' => $user, 'post_status' => 'draft', 'post_title' => 'Old' ] );
		mailocations_update_option( 'owners_can_publish', true );
		wp_set_current_user( $user );

		// Only this listener's closures should run, not ACF's own save or the plugin instance's copy.
		remove_all_actions( 'acf/save_post' );

		$GLOBALS['acf_form'] = [ 'post_id' => $id, 'mailocations_emails' => 'x@example.org' ];

		$_POST = [
			'_acf_form'    => 'front-end',
			'_acf_post_id' => (string) $id,
			'acf'          => [
				'mai_location_title'   => '<b>New Title</b>',
				'mai_location_excerpt' => '<p>Desc</p><script>x</script>',
				'mai_location_publish' => '1',
				'other'                => 'kept',
			],
		];

		$this->listener->before_save_post( $id );

		// The post fields are pulled out of $_POST so ACF does not save them as meta.
		$this->assertSame( [ 'other' => 'kept' ], $_POST['acf'] );
		$this->assertCount( 2, $GLOBALS['wp_filter']['acf/save_post']->callbacks[20] );

		$this->capture_errors( static fn() => do_action( 'acf/save_post', $id ) );

		$post = get_post( $id );
		$this->assertSame( 'publish', $post->post_status );
		$this->assertSame( 'New Title', $post->post_title );
		$this->assertSame( '<p>Desc</p>x', $post->post_excerpt );
		$this->assertSame( [ $id ], get_user_meta( $user, 'user_locations', true ) );

		// Only the submission notice goes out. The "has been published" email is hooked on
		// pending_to_publish, so it fires when a manager approves a pending location, not when
		// an owner publishes their own draft, who hardly needs telling.
		$this->assertSame( [], $this->errors );
		$this->assertSame(
			[
				[ [ 'x@example.org' ], 'New Location submission from Ann Author' ],
			],
			array_map( static fn( array $mail ): array => [ $mail['to'], $mail['subject'] ], $this->sent_mail() )
		);
	}

	/**
	 * ACF renders _acf_post_id on the Dashboard edit screen too. What separates a wp-admin save
	 * from a form one is $GLOBALS['acf_form'], which ACF sets in its front-end submit handler and
	 * nowhere else. wp-admin has its own Publish and Save Draft buttons and the plugin no longer
	 * overrides them.
	 */
	public function test_a_dashboard_save_leaves_a_draft_alone(): void {
		$id = $this->create_location( [], [ 'post_status' => 'draft' ] );
		remove_all_actions( 'acf/save_post' );
		set_current_screen( 'edit-post' );

		unset( $GLOBALS['acf_form'] );

		$_POST = [
			'_acf_post_id' => (string) $id,
			'acf'          => [ 'mai_location_publish' => '1', 'other' => 'kept' ],
		];

		$this->assertTrue( is_admin() );

		$this->listener->before_save_post( $id );
		do_action( 'acf/save_post', $id );

		$this->assertSame( 'draft', get_post( $id )->post_status );

		set_current_screen( 'front' );
	}

	/**
	 * A whitelist, not a blacklist, and draft alone. A pending location is with a manager, so
	 * publishing it from the front end would step over the approval the site asked for. Private
	 * and trashed are never dragged onto the front page, and a published one is never demoted.
	 *
	 * @dataProvider promotable_statuses
	 */
	public function test_a_ticked_publish_box_promotes_a_draft_and_nothing_else( string $status, string $expected ): void {
		$user = self::factory()->user->create();
		$id   = $this->create_location( [], [ 'post_status' => $status, 'post_author' => $user ] );
		mailocations_update_option( 'owners_can_publish', true );
		wp_set_current_user( $user );
		remove_all_actions( 'acf/save_post' );

		$GLOBALS['acf_form'] = [ 'post_id' => $id ];

		$_POST = [
			'_acf_form'    => 'front-end',
			'_acf_post_id' => (string) $id,
			'acf'          => [ 'mai_location_publish' => '1', 'other' => 'kept' ],
		];

		$this->listener->before_save_post( $id );
		$this->capture_errors( static fn() => do_action( 'acf/save_post', $id ) );

		$this->assertSame( $expected, get_post( $id )->post_status );
	}

	/**
	 * @return array<string, array{string, string}>
	 */
	public static function promotable_statuses(): array {
		return [
			'draft'   => [ 'draft', 'publish' ],
			'pending' => [ 'pending', 'pending' ],
			'private' => [ 'private', 'private' ],
			'trash'   => [ 'trash', 'trash' ],
			'publish' => [ 'publish', 'publish' ],
		];
	}

	/**
	 * @dataProvider promotable_statuses
	 */
	public function test_an_unticked_publish_box_leaves_every_status_alone( string $status ): void {
		$id = $this->create_location( [], [ 'post_status' => $status ] );
		remove_all_actions( 'acf/save_post' );

		$GLOBALS['acf_form'] = [ 'post_id' => $id ];

		$_POST = [
			'_acf_form'    => 'front-end',
			'_acf_post_id' => (string) $id,
			'acf'          => [ 'mai_location_publish' => '0', 'other' => 'kept' ],
		];

		$this->listener->before_save_post( $id );
		$this->capture_errors( static fn() => do_action( 'acf/save_post', $id ) );

		$this->assertSame( $status, get_post( $id )->post_status );
	}

	/**
	 * The save path checks edit permission itself, rather than trusting that the form was only
	 * ever drawn for someone who may edit.
	 *
	 * This is the case that needs it. `publish_post` maps to the post type's `publish_posts`,
	 * which answers "may publish locations at all", not "may publish this one". An author-role
	 * user has it, and without the edit check could publish a draft belonging to someone else,
	 * which they cannot even open in the Dashboard.
	 *
	 * @dataProvider passer_by_roles
	 */
	public function test_someone_who_cannot_edit_the_location_cannot_publish_it( string $role ): void {
		mailocations_update_option( 'owners_can_publish', true );
		$owner     = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		$passer_by = self::factory()->user->create( [ 'role' => $role ] );
		$id        = $this->create_location( [], [ 'post_status' => 'draft', 'post_author' => $owner ] );
		remove_all_actions( 'acf/save_post' );
		wp_set_current_user( $passer_by );

		$GLOBALS['acf_form'] = [ 'post_id' => $id ];

		$_POST = [
			'_acf_form'    => 'front-end',
			'_acf_post_id' => (string) $id,
			'acf'          => [ 'mai_location_publish' => '1', 'other' => 'kept' ],
		];

		$this->listener->before_save_post( $id );
		$this->capture_errors( static fn() => do_action( 'acf/save_post', $id ) );

		$this->assertSame( 'draft', get_post( $id )->post_status );
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function passer_by_roles(): array {
		return [
			// No publish capability at all, so the ownership check alone stops them.
			'subscriber' => [ 'subscriber' ],
			// Has publish_posts but not edit_others_posts. Only the edit check stops them.
			'author'     => [ 'author' ],
		];
	}

	/**
	 * And a logged-out request, which has no author and no capability.
	 */
	public function test_a_logged_out_request_cannot_publish(): void {
		mailocations_update_option( 'owners_can_publish', true );
		$id = $this->create_location( [], [ 'post_status' => 'draft' ] );
		remove_all_actions( 'acf/save_post' );
		wp_set_current_user( 0 );

		$GLOBALS['acf_form'] = [ 'post_id' => $id ];

		$_POST = [
			'_acf_form'    => 'front-end',
			'_acf_post_id' => (string) $id,
			'acf'          => [ 'mai_location_publish' => '1', 'other' => 'kept' ],
		];

		$this->listener->before_save_post( $id );
		$this->capture_errors( static fn() => do_action( 'acf/save_post', $id ) );

		$this->assertSame( 'draft', get_post( $id )->post_status );
	}

	/**
	 * The emails come from the decrypted form, so a submitter who adds or edits a posted value
	 * cannot choose who the site emails. The posted key is still dropped so it never reaches
	 * meta.
	 */
	public function test_notification_emails_come_from_the_form_never_from_post(): void {
		$id = $this->create_location( [], [ 'post_status' => 'pending', 'post_title' => 'Old' ] );
		remove_all_actions( 'acf/save_post' );

		$GLOBALS['acf_form'] = [ 'post_id' => 'new_post', 'mailocations_emails' => 'manager@example.org' ];

		$_POST = [
			'_acf_form' => 'front-end',
			'acf'       => [
				'mai_location_title'  => 'New',
				'mai_location_emails' => 'attacker@example.org',
				'other'               => 'kept',
			],
		];

		$this->listener->before_save_post( $id );

		$this->assertArrayNotHasKey( 'mai_location_emails', $_POST['acf'] );

		$this->capture_errors( static fn() => do_action( 'acf/save_post', $id ) );

		$to = array_merge( ...array_map( static fn( array $mail ): array => (array) $mail['to'], $this->sent_mail() ) );

		$this->assertContains( 'manager@example.org', $to );
		$this->assertNotContains( 'attacker@example.org', $to );
	}

	/**
	 * With no emails in the form, a posted value sends nothing at all.
	 */
	public function test_a_posted_email_alone_sends_nothing(): void {
		$id = $this->create_location( [], [ 'post_status' => 'pending', 'post_title' => 'Old' ] );
		remove_all_actions( 'acf/save_post' );

		$GLOBALS['acf_form'] = [ 'post_id' => 'new_post' ];

		$_POST = [
			'_acf_form' => 'front-end',
			'acf'       => [ 'mai_location_title' => 'New', 'mai_location_emails' => 'attacker@example.org' ],
		];

		$this->listener->before_save_post( $id );
		$this->capture_errors( static fn() => do_action( 'acf/save_post', $id ) );

		$this->assertSame( [], $this->sent_mail() );
	}

	public function test_the_publish_box_never_reaches_the_meta_table(): void {
		$id = $this->create_location( [], [ 'post_status' => 'draft' ] );
		mailocations_update_option( 'owners_can_publish', true );
		remove_all_actions( 'acf/save_post' );

		$GLOBALS['acf_form'] = [ 'post_id' => $id ];

		$_POST = [
			'_acf_form'    => 'front-end',
			'_acf_post_id' => (string) $id,
			'acf'          => [ 'mai_location_publish' => '1', 'other' => 'kept' ],
		];

		$this->listener->before_save_post( $id );

		$this->assertSame( [ 'other' => 'kept' ], $_POST['acf'] );
	}

	/**
	 * ACF renders _acf_post_id as a plain hidden input and never reads it back: the post it
	 * really saves comes from the encrypted _acf_form. So a hand-edited _acf_post_id naming some
	 * other draft must not be what the status whitelist is measured against, or a private or
	 * trashed location could be published by pointing at an unrelated post.
	 */
	public function test_a_named_post_id_cannot_stand_in_for_the_one_being_saved(): void {
		mailocations_update_option( 'owners_can_publish', true );
		$private = $this->create_location( [], [ 'post_status' => 'private' ] );
		$draft   = $this->create_location( [], [ 'post_status' => 'draft' ] );
		remove_all_actions( 'acf/save_post' );

		$GLOBALS['acf_form'] = [ 'post_id' => $private ];

		$_POST = [
			'_acf_form'    => 'front-end',
			'_acf_post_id' => (string) $draft,
			'acf'          => [ 'mai_location_publish' => '1', 'other' => 'kept' ],
		];

		$this->listener->before_save_post( $private );
		$this->capture_errors( static fn() => do_action( 'acf/save_post', $private ) );

		$this->assertSame( 'private', get_post( $private )->post_status );
	}

	/**
	 * The submission block has its own Status setting, which is how a site moderates what
	 * arrives. Its form saves with post_id 'new_post', so it can never promote itself, even
	 * though the listener reads the publish key out of $_POST whether or not the form drew it.
	 */
	public function test_a_new_submission_cannot_publish_itself(): void {
		mailocations_update_option( 'owners_can_publish', true );
		$id = $this->create_location( [], [ 'post_status' => 'draft' ] );
		remove_all_actions( 'acf/save_post' );

		$GLOBALS['acf_form'] = [ 'post_id' => 'new_post' ];

		$_POST = [
			'_acf_form'    => 'front-end',
			'_acf_post_id' => (string) $id,
			'acf'          => [ 'mai_location_publish' => '1', 'other' => 'kept' ],
		];

		$this->listener->before_save_post( $id );
		$this->capture_errors( static fn() => do_action( 'acf/save_post', $id ) );

		$this->assertSame( 'draft', get_post( $id )->post_status );
	}

	/**
	 * $GLOBALS['acf_form'] is set in ACF's front-end submit handler and nowhere else, so its
	 * absence is what tells a Dashboard save apart from a form one.
	 */
	public function test_a_forged_acf_form_field_alone_publishes_nothing(): void {
		mailocations_update_option( 'owners_can_publish', true );
		$id = $this->create_location( [], [ 'post_status' => 'draft' ] );
		remove_all_actions( 'acf/save_post' );

		unset( $GLOBALS['acf_form'] );

		$_POST = [
			'_acf_form'    => 'anyone can post this',
			'_acf_post_id' => (string) $id,
			'acf'          => [ 'mai_location_publish' => '1', 'other' => 'kept' ],
		];

		$this->listener->before_save_post( $id );
		$this->capture_errors( static fn() => do_action( 'acf/save_post', $id ) );

		$this->assertSame( 'draft', get_post( $id )->post_status );
	}

	public function test_before_save_post_on_a_published_location_adds_the_user_but_sends_nothing(): void {
		$user = self::factory()->user->create();
		$id   = $this->create_location( [], [ 'post_author' => $user ] );
		remove_all_actions( 'acf/save_post' );

		$_POST = [
			'_acf_post_id' => (string) $id,
			'acf'          => [ 'other' => 'kept' ],
		];

		$this->listener->before_save_post( $id );
		$this->capture_errors( static fn() => do_action( 'acf/save_post', $id ) );

		$this->assertSame( [ $id ], get_user_meta( $user, 'user_locations', true ) );
		$this->assertSame( [], $this->errors );
		$this->assertSame( [], $this->sent_mail() );
	}
}

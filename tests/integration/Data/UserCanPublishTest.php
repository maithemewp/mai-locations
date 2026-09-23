<?php

declare(strict_types=1);

namespace Mai\Locations\Tests\Integration\Data;

use Mai\Locations\Tests\TestCase;

/**
 * mailocations_user_can_publish(), the one question that separates a moderated site from one
 * whose owners run their own listings. Each part of it is pinned on its own: the capability
 * route, the owner route behind the setting, the logged-out guard, the explicit $user_id, and
 * the filter. Any one of them could be removed with the suite green until September 23, 2026.
 */
final class UserCanPublishTest extends TestCase {

	/**
	 * @dataProvider cases
	 */
	public function test_who_may_publish( string $role, bool $is_owner, bool $setting, bool $expected ): void {
		$owner = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		$user  = 'nobody' === $role ? 0 : self::factory()->user->create( [ 'role' => $role ] );
		$id    = $this->create_location( [], [ 'post_status' => 'draft', 'post_author' => $is_owner ? $user : $owner ] );

		mailocations_update_option( 'owners_can_publish', $setting );

		$this->assertSame( $expected, mailocations_user_can_publish( $id, $user ) );
	}

	/**
	 * @return array<string, array{string, bool, bool, bool}>
	 */
	public static function cases(): array {
		return [
			'subscriber owner, setting on'       => [ 'subscriber', true, true, true ],
			'subscriber owner, setting off'      => [ 'subscriber', true, false, false ],
			'subscriber, not owner, setting on'  => [ 'subscriber', false, true, false ],
			// publish_post is a real meta capability since WordPress 6.1, mapped to the post
			// type's publish_posts, which an author and an editor have and a subscriber lacks.
			'author owner, setting off'          => [ 'author', true, false, true ],
			'editor, not owner, setting off'     => [ 'editor', false, false, true ],
			'contributor owner, setting off'     => [ 'contributor', true, false, false ],
			'contributor owner, setting on'      => [ 'contributor', true, true, true ],
		];
	}

	/**
	 * A logged-out request has user 0, and a location with no author also has author 0. The
	 * guard stops those matching as "the owner".
	 */
	public function test_nobody_logged_in_is_never_the_owner_of_an_authorless_location(): void {
		$id = $this->create_location( [], [ 'post_status' => 'draft', 'post_author' => 0 ] );
		mailocations_update_option( 'owners_can_publish', true );
		wp_set_current_user( 0 );

		$this->assertFalse( mailocations_user_can_publish( $id ) );
		$this->assertFalse( mailocations_user_can_publish( $id, 0 ) );
	}

	/**
	 * The $user_id argument is used, not quietly replaced by the current user.
	 */
	public function test_the_user_id_argument_is_the_user_asked_about(): void {
		$owner = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		$other = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		$id    = $this->create_location( [], [ 'post_status' => 'draft', 'post_author' => $owner ] );
		mailocations_update_option( 'owners_can_publish', true );

		wp_set_current_user( $other );

		$this->assertTrue( mailocations_user_can_publish( $id, $owner ) );
		$this->assertFalse( mailocations_user_can_publish( $id ) );
	}

	public function test_the_filter_can_say_yes_and_no(): void {
		$owner = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		$id    = $this->create_location( [], [ 'post_status' => 'draft', 'post_author' => $owner ] );
		mailocations_update_option( 'owners_can_publish', false );

		add_filter( 'mailocations_user_can_publish', '__return_true' );
		$this->assertTrue( mailocations_user_can_publish( $id, $owner ) );
		remove_filter( 'mailocations_user_can_publish', '__return_true' );

		$editor = self::factory()->user->create( [ 'role' => 'editor' ] );
		add_filter( 'mailocations_user_can_publish', '__return_false' );
		$this->assertFalse( mailocations_user_can_publish( $id, $editor ) );
		remove_filter( 'mailocations_user_can_publish', '__return_false' );
	}

	public function test_the_filter_is_told_the_location_and_the_user(): void {
		$owner = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		$id    = $this->create_location( [], [ 'post_status' => 'draft', 'post_author' => $owner ] );
		$seen  = [];
		$spy   = static function ( bool $can, int $location_id, int $user_id ) use ( &$seen ): bool {
			$seen = [ $location_id, $user_id ];
			return $can;
		};

		add_filter( 'mailocations_user_can_publish', $spy, 10, 3 );
		mailocations_user_can_publish( $id, $owner );
		remove_filter( 'mailocations_user_can_publish', $spy, 10 );

		$this->assertSame( [ $id, $owner ], $seen );
	}
}

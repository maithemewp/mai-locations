<?php

declare(strict_types=1);

namespace Mai\Locations\Tests\Integration\Blocks;

use Mai\Locations\Tests\TestCase;
use ReflectionProperty;

/**
 * Covers the base form, the submission form and the edit form, as a logged-in author sees them.
 */
final class LocationFormTest extends TestCase {

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

	private int $user;

	/**
	 * @var array<int, array<string, mixed>>
	 */
	private array $captured = [];

	public function set_up(): void {
		parent::set_up();
		$this->restore_acf_local_meta_filters();
		$this->user = self::factory()->user->create( [ 'role' => 'author' ] );
		wp_set_current_user( $this->user );
	}

	public function tear_down(): void {
		$_GET = [];
		parent::tear_down();
	}

	/**
	 * Captures the acf_form() args each form builds, since acf_form() encrypts them into the markup.
	 *
	 */
	private function capture_form_args(): void {
		$this->captured = [];
		add_filter(
			'mailocations_acf_form_args',
			function ( array $args ): array {
				$this->captured[] = $args;
				return $args;
			}
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function args_of( \Mai_Locations_Location_Form $form ): array {
		$property = new ReflectionProperty( \Mai_Locations_Location_Form::class, 'args' );
		$property->setAccessible( true );

		return $property->getValue( $form );
	}

	public function test_constructor_sanitizes_args(): void {
		$form = new \Mai_Locations_Location_Form(
			[
				'location_id' => '12abc',
				'fields'      => 'mai_location_title',
				'post_type'   => 'Mai Location!',
				'status'      => 'Publish',
				'redirect'    => 'javascript:alert(1)',
				'emails'      => ' <b>a@b.com</b> ',
				'class'       => ' one <two> ',
				'preview'     => 'false',
			]
		);

		$this->assertSame(
			[
				'location_id' => 12,
				'fields'      => [ 'mai_location_title' ],
				'post_type'   => 'mailocation',
				'status'      => 'publish',
				'redirect'    => '',
				'emails'      => 'a@b.com',
				'class'       => 'one',
				'preview'     => false,
			],
			$this->args_of( $form )
		);
	}

	public function test_base_get_returns_null_without_fields(): void {
		$this->assertNull( ( new \Mai_Locations_Location_Form( [] ) )->get() );
	}

	public function test_base_get_wraps_an_empty_form(): void {
		$this->assertSame( '<div class="mailocations-form"></div>', ( new \Mai_Locations_Location_Form( [ 'fields' => [ 'mai_location_title' ] ] ) )->get() );
	}

	/**
	 * Fixed September 16, 2026. trim() removed the space it had just added.
	 */
	public function test_extra_class_is_joined_with_a_space(): void {
		$this->assertSame( '<div class="mailocations-form extra"></div>', ( new \Mai_Locations_Location_Form( [ 'fields' => [ 'x' ], 'class' => 'extra' ] ) )->get() );
	}

	public function test_get_applies_both_filters(): void {
		add_filter( 'mailocations_location_acf_form', static fn( $form, $args ) => 'inner:' . implode( ',', $args['fields'] ), 10, 2 );
		add_filter( 'mailocations_location_form', static fn( $html ) => "[{$html}]" );

		$this->assertSame( '[<div class="mailocations-form">inner:x</div>]', ( new \Mai_Locations_Location_Form( [ 'fields' => [ 'x' ] ] ) )->get() );
	}

	public function test_state_conditions_are_removed_only_while_rendering_without_country(): void {
		$seen = [];
		add_filter(
			'mailocations_location_acf_form',
			static function ( $form ) use ( &$seen ) {
				$seen[] = has_filter( 'acf/prepare_field/key=mai_address_state' ) && has_filter( 'acf/prepare_field/key=mai_address_state_int' );
				return $form;
			}
		);

		( new \Mai_Locations_Location_Form( [ 'fields' => [ 'mai_location_title' ] ] ) )->get();
		( new \Mai_Locations_Location_Form( [ 'fields' => [ 'mai_location_address_country' ] ] ) )->get();

		$this->assertSame( [ true, false ], $seen );
		$this->assertFalse( has_filter( 'acf/prepare_field/key=mai_address_state' ) );
		$this->assertFalse( has_filter( 'acf/prepare_field/key=mai_address_state_int' ) );
		$this->assertSame( [ 'conditional_logic' => 0 ], ( new \Mai_Locations_Location_Form( [] ) )->remove_conditions( [ 'conditional_logic' => [ 1 ] ] ) );
	}

	public function test_submit_form_preview_lists_fields(): void {
		$this->assertSame(
			'<div class="mailocations-form"><div class="mailocations-form-preview" style="pointer-events:none;padding:36px;border:2px dashed rgba(0,0,0,0.1);">'
			. '<p style="font-size:1.25em;"><strong>Location Submission Form</strong></p>'
			. '<p><label>Title</label><input type="text" placeholder="Placeholder field"></p>'
			. '<p><label>bogus</label><input style="border:1px solid red;" type="text" placeholder="Field not available on selected post type"></p>'
			. '</div></div>',
			mailocations_get_location_submission_form( [ 'fields' => [ 'mai_location_title', 'bogus' ], 'preview' => true ] )
		);
	}

	public function test_submit_form_without_fields_returns_null(): void {
		$this->assertNull( mailocations_get_location_submission_form( [] ) );
	}

	public function test_submit_form_builds_acf_form_args_and_markup(): void {
		$this->capture_form_args();

		$html = mailocations_get_location_submission_form( [ 'fields' => [ 'mai_location_title' ], 'emails' => 'a@b.com', 'redirect' => 'https://example.org/thanks' ] );

		$this->assertSame(
			[
				[
					'id'                => 'mailocations-form',
					'post_id'           => 'new_post',
					'new_post'          => [
						'post_type'   => 'mai_location',
						'post_status' => 'pending',
						'post_author' => $this->user,
					],
					'fields'            => [ 'mai_location_title' ],
					'submit_value'      => 'Submit Location',
					'updated_message'   => 'Location successfully submitted.',
					'uploader'          => 'basic',
					'html_after_fields' => '<input type="hidden" name="acf[mai_location_emails]" value="a@b.com">',
					'return'            => 'https://example.org/thanks',
				],
			],
			$this->captured
		);

		$this->assertStringStartsWith( '<div class="mailocations-form">', $html );
		$this->assertStringContainsString( '<form id="mailocations-form" class="acf-form" action="" method="post">', $html );
		$this->assertStringContainsString( 'name="_acf_post_id" value="new_post"', $html );
		$this->assertStringContainsString( '<input type="text" id="acf-mai_location_title" name="acf[mai_location_title]" required="required"/>', $html );
		$this->assertStringContainsString( '<input type="hidden" name="acf[mai_location_emails]" value="a@b.com">', $html );
		// Mai_Engine is not loaded, so the ACF button classes are left as they are.
		$this->assertStringContainsString( '<input type="submit" class="acf-button button button-primary button-large" value="Submit Location" />', $html );
		$this->assertStringEndsWith( '</div>', $html );
	}

	public function test_submission_block_passes_its_settings_to_the_form(): void {
		$this->capture_form_args();

		do_blocks( '<!-- wp:acf/mai-location-submission {"data":{"location_fields":["mai_location_title",""],"location_post_type":"mai_location","location_status":"draft"}} /-->' );

		$this->assertCount( 1, $this->captured );
		$this->assertSame( [ 'mai_location_title' ], $this->captured[0]['fields'] );
		$this->assertSame( 'draft', $this->captured[0]['new_post']['post_status'] );
		$this->assertSame( '', $this->captured[0]['html_after_fields'] );
		$this->assertArrayNotHasKey( 'return', $this->captured[0] );
	}

	public function test_submission_block_with_no_fields_renders_nothing(): void {
		$this->assertSame( '', do_blocks( '<!-- wp:acf/mai-location-submission {} /-->' ) );
	}

	public function test_edit_form_without_location_id_wraps_nothing(): void {
		$this->assertSame( '<div class="mailocations-form"></div>', mailocations_get_location_edit_form( [ 'fields' => [ 'mai_location_title' ] ] ) );
	}

	public function test_edit_form_builds_args_drops_unknown_fields_and_loads_the_title(): void {
		$id       = $this->create_location( [], [ 'post_author' => $this->user, 'post_title' => 'Hollow Inn' ] );
		$this->capture_form_args();

		$html = mailocations_get_location_edit_form( [ 'location_id' => $id, 'fields' => [ 'bogus', 'mai_location_title' ], 'redirect' => 'https://example.org/done' ] );

		$this->assertSame(
			[
				[
					'id'              => 'mailocations-form',
					'post_id'         => $id,
					// unset() keeps the original index.
					'fields'          => [ 1 => 'mai_location_title' ],
					'submit_value'    => 'Update Location',
					'updated_message' => 'Location successfully updated.',
					'uploader'        => 'basic',
					'return'          => 'https://example.org/done',
				],
			],
			$this->captured
		);
		$this->assertStringContainsString( 'name="_acf_post_id" value="' . $id . '"', $html );
		$this->assertStringContainsString( 'name="acf[mai_location_title]" value="Hollow Inn" required="required"', $html );
	}

	/**
	 * The button always says Update now. Publishing is the checkbox's job, so a button that
	 * says Publish while the box sits unticked would be telling the owner the wrong thing.
	 *
	 * @dataProvider every_status
	 */
	public function test_edit_form_button_always_says_update( string $status ): void {
		$id = $this->create_location( [], [ 'post_author' => $this->user, 'post_status' => $status ] );
		$this->capture_form_args();

		mailocations_get_location_edit_form( [ 'location_id' => $id, 'fields' => [ 'mai_location_title' ] ] );

		$this->assertSame( 'Update Location', $this->captured[0]['submit_value'] );
	}

	/**
	 * The checkbox is added by the form itself, so a site never has to pick it. It shows only
	 * where publishing is the right next step: draft and pending, never publish or private.
	 *
	 * @dataProvider every_status
	 */
	public function test_edit_form_adds_the_publish_checkbox_only_to_draft_and_pending( string $status ): void {
		$id = $this->create_location( [], [ 'post_author' => $this->user, 'post_status' => $status ] );
		$this->capture_form_args();

		mailocations_get_location_edit_form( [ 'location_id' => $id, 'fields' => [ 'mai_location_title' ] ] );

		$expected = in_array( $status, [ 'draft', 'pending' ], true )
			? [ 'mai_location_title', 'mai_location_publish' ]
			: [ 'mai_location_title' ];

		$this->assertSame( $expected, array_values( $this->captured[0]['fields'] ) );
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function every_status(): array {
		return [
			'draft'   => [ 'draft' ],
			'pending' => [ 'pending' ],
			'publish' => [ 'publish' ],
			'private' => [ 'private' ],
		];
	}

	public function test_edit_form_removes_its_load_value_filters_afterwards(): void {
		// remove_filter() takes three arguments; the fourth passed at lines 76-78 is ignored, and removal still works.
		$id   = $this->create_location( [], [ 'post_author' => $this->user ] );
		$seen = [];
		add_filter(
			'mailocations_acf_form_args',
			static function ( array $args ) use ( &$seen ): array {
				$seen = [
					has_filter( 'acf/load_value/key=mai_location_title' ),
					has_filter( 'acf/load_value/key=mai_location_excerpt' ),
					has_filter( 'acf/load_value/key=mai_location_image' ),
				];
				return $args;
			}
		);

		mailocations_get_location_edit_form( [ 'location_id' => $id, 'fields' => [ 'mai_location_title' ] ] );

		$this->assertSame( [ true, true, true ], $seen );
		$this->assertFalse( has_filter( 'acf/load_value/key=mai_location_title' ) );
		$this->assertFalse( has_filter( 'acf/load_value/key=mai_location_excerpt' ) );
		$this->assertFalse( has_filter( 'acf/load_value/key=mai_location_image' ) );
	}

	public function test_edit_form_load_value_callbacks_read_the_location(): void {
		$image = self::factory()->attachment->create();
		$id    = $this->create_location( [ '_thumbnail_id' => $image ], [ 'post_title' => 'Title', 'post_excerpt' => 'Excerpt' ] );

		$form = new \Mai_Locations_Location_Form_Edit( [ 'location_id' => $id ] );

		$this->assertSame( 'Title', $form->load_location_title_value( 'x', 1, [] ) );
		$this->assertSame( 'Excerpt', $form->load_location_excerpt_value( 'x', 1, [] ) );
		$this->assertSame( $image, $form->load_location_image_value( 'x', 1, [] ) );
	}

	public function test_edit_form_back_link_points_at_the_referrer(): void {
		$id               = $this->create_location( [], [ 'post_author' => $this->user ] );
		$_GET['referrer'] = 'https://example.org/account/';

		$html = mailocations_get_location_edit_form( [ 'location_id' => $id, 'fields' => [ 'mai_location_title' ] ] );

		$this->assertStringContainsString( '<a href="https://example.org/account/">← Back</a>', $html );

		unset( $_GET['referrer'] );
	}

	public function test_edit_form_has_no_back_link_without_a_referrer(): void {
		$id = $this->create_location( [], [ 'post_author' => $this->user ] );

		$html = mailocations_get_location_edit_form( [ 'location_id' => $id, 'fields' => [ 'mai_location_title' ] ] );

		$this->assertStringNotContainsString( 'Back', $html );
		$this->assertStringStartsWith( '<div class="mailocations-form">' . "\t\t\t\t<form", $html );
	}
}

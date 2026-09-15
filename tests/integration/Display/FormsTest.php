<?php

declare(strict_types=1);

namespace Mai\Locations\Tests\Integration\Display;

use Mai\Locations\Tests\TestCase;

/**
 * Light checks that the form helpers in functions-display.php return without a fatal.
 * The blocks suite covers the forms in detail.
 */
final class FormsTest extends TestCase {

	public function test_submission_form_without_fields_returns_null(): void {
		$this->assertNull( mailocations_get_location_submission_form( [] ) );
	}

	public function test_edit_form_without_fields_returns_null(): void {
		$this->assertNull( mailocations_get_location_edit_form( [] ) );
	}

	public function test_submission_form_preview(): void {
		$this->assertSame(
			'<div class="mailocations-form"><div class="mailocations-form-preview" style="pointer-events:none;padding:36px;border:2px dashed rgba(0,0,0,0.1);">'
			. '<p style="font-size:1.25em;"><strong>Location Submission Form</strong></p>'
			. '<p><label>not_a_field</label><input style="border:1px solid red;" type="text" placeholder="Field not available on selected post type"></p>'
			. '</div></div>',
			mailocations_get_location_submission_form( [ 'fields' => [ 'not_a_field' ], 'preview' => true ] )
		);
	}

	public function test_pins_bug_form_class_is_glued_to_default_class(): void {
		$html = mailocations_get_location_submission_form( [ 'fields' => [ 'not_a_field' ], 'preview' => true, 'class' => 'extra' ] );

		// Correct would be class="mailocations-form extra". trim() removes the separating space.
		$this->assertStringStartsWith( '<div class="mailocations-formextra">', $html );
	}

	public function test_edit_form_without_location_id_is_an_empty_wrapper(): void {
		$this->assertSame( '<div class="mailocations-form"></div>', mailocations_get_location_edit_form( [ 'fields' => [ 'not_a_field' ] ] ) );
	}

	public function test_submission_form_renders_acf_form(): void {
		$html = mailocations_get_location_submission_form( [ 'fields' => [ 'mai_location_address_city' ] ] );

		$this->assertIsString( $html );
		$this->assertStringStartsWith( '<div class="mailocations-form">', $html );
		$this->assertStringEndsWith( '</div>', $html );
	}

	public function test_edit_form_renders_acf_form_for_location(): void {
		$id   = $this->create_location( [ 'address_city' => 'Tarrytown' ] );
		$html = mailocations_get_location_edit_form( [ 'location_id' => $id, 'fields' => [ 'mai_location_address_city' ] ] );

		$this->assertIsString( $html );
		$this->assertStringStartsWith( '<div class="mailocations-form">', $html );
		$this->assertStringEndsWith( '</div>', $html );
	}
}

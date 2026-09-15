<?php

declare(strict_types=1);

namespace Mai\Locations\Tests\Integration\Blocks;

use Mai\Locations\Tests\TestCase;
use WP_Block_Type;
use WP_Block_Type_Registry;

/**
 * The submit button is a core/button variation, not an ACF block.
 */
final class FilterSubmitBlockTest extends TestCase {

	private \Mai_Locations_Filter_Submit_Block $block;

	public function set_up(): void {
		parent::set_up();
		$this->block = new \Mai_Locations_Filter_Submit_Block();
	}

	public function test_core_button_gets_the_boolean_attribute(): void {
		$attributes = WP_Block_Type_Registry::get_instance()->get_registered( 'core/button' )->attributes;

		$this->assertSame( [ 'type' => 'boolean' ], $attributes['maiLocationsFilterSubmit'] );
	}

	public function test_add_block_attribute_ignores_other_blocks(): void {
		$this->assertSame( [ 'attributes' => [] ], $this->block->add_block_attribute( [ 'attributes' => [] ], 'core/buttons' ) );
		$this->assertSame( [ 'attributes' => [ 'maiLocationsFilterSubmit' => [ 'type' => 'boolean' ] ] ], $this->block->add_block_attribute( [ 'attributes' => [] ], 'core/button' ) );
	}

	public function test_variation_is_added_to_core_button(): void {
		$names = wp_list_pluck( WP_Block_Type_Registry::get_instance()->get_registered( 'core/button' )->get_variations(), 'name' );

		$this->assertContains( 'mailocations-filter-submit', $names );
	}

	public function test_add_block_variation_receives_a_block_type_object_from_core(): void {
		// Core passes WP_Block_Type (class-wp-block-type.php get_variations()), so the ->name read works.
		// The docblock says string, which is what PHPStan flags.
		$this->assertSame( [], $this->block->add_block_variation( [], new WP_Block_Type( 'core/paragraph' ) ) );
		$this->assertSame(
			[
				[
					'title'      => 'Mai Locations Filter Submit',
					'name'       => 'mailocations-filter-submit',
					'isActive'   => [ 'maiLocationsFilterSubmit' ],
					'attributes' => [
						'maiLocationsFilterSubmit' => true,
						'metadata'                 => [ 'bindings' => [ 'url' => [ 'source' => 'mai/locations', 'args' => [ 'key' => 'filterSubmit' ] ] ] ],
					],
				],
			],
			$this->block->add_block_variation( [], new WP_Block_Type( 'core/button' ) )
		);
	}

	public function test_add_block_variation_warns_when_given_a_string(): void {
		$errors = [];
		set_error_handler(
			static function ( int $errno, string $errstr ) use ( &$errors ): bool {
				$errors[] = $errstr;
				return true;
			}
		);

		try {
			$result = $this->block->add_block_variation( [], 'core/button' );
		} finally {
			restore_error_handler();
		}

		$this->assertSame( [], $result );
		$this->assertSame( [ 'Attempt to read property "name" on string' ], $errors );
	}

	public function test_turns_the_link_into_a_submit_button(): void {
		// The href attribute is carried over onto the button element.
		$this->assertSame(
			'<div class="wp-block-button"><button type="submit" class="wp-block-button__link wp-element-button mailocations-filter-submit" href="http://x">Go</button></div>',
			do_blocks( '<!-- wp:button {"maiLocationsFilterSubmit":true} --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="http://x">Go</a></div><!-- /wp:button -->' )
		);
	}

	public function test_leaves_other_buttons_alone(): void {
		$markup = '<div class="wp-block-button"><a class="wp-block-button__link wp-element-button">Plain</a></div>';

		$this->assertSame( $markup, do_blocks( '<!-- wp:button -->' . $markup . '<!-- /wp:button -->' ) );
		$this->assertSame( $markup, $this->block->render_block_variation( $markup, [ 'attrs' => [] ], null ) );
	}
}

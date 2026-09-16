<?php

declare(strict_types=1);

namespace Mai\Locations\Blocks;

// Prevent direct file access.
defined( 'ABSPATH' ) || die;

/**
 * The locations table block, which shows a user their own locations.
 *
 * Was Mai_Locations_Table_Block in blocks/location-table/block.php. That name still works, via
 * inc/aliases.php. block.json stays in blocks/location-table/.
 *
 * @since TBD
 */
class TableBlock {

	/**
	 * Construct the class.
	 */
	public function __construct() {
		$this->hooks();
	}

	/**
	 * Add hooks.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function hooks(): void {
		add_action( 'acf/init',                               [ $this, 'register_block' ] );
		add_action( 'acf/init',                               [ $this, 'register_field_group' ] );
		add_filter( 'acf/load_field/key=field_6071bfebbddbg', [ $this, 'load_post_type_choices' ] );
	}

	/**
	 * Registers the block.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function register_block(): void {
		register_block_type( MAI_LOCATIONS_PLUGIN_DIR . 'blocks/location-table',
			[
				'render_callback' => [ $this, 'render_block' ],
			]
		);
	}

	/**
	 * Renders the Mai Locations Table block.
	 *
	 * @since 0.1.0
	 *
	 * @param array<string, mixed> $attributes The block attributes.
	 * @param string               $content    The block content.
	 * @param bool                 $is_preview Whether the block is rendering for an editor preview.
	 * @param int                  $post_id    The current post being edited or viewed.
	 * @param \WP_Block            $block      The block instance.
	 *
	 * @return void
	 */
	public function render_block( $attributes, $content, $is_preview, $post_id, $block ): void {
		$args = [
			'post_type'  => get_field( 'locations_table_post_type' ),
			'title'      => get_field( 'locations_table_title' ),
			'header'     => get_field( 'locations_table_header' ),
			'no_results' => get_field( 'locations_no_results' ),
			'redirect'   => get_field( 'location_redirect' ),
			'fields'     => (array) get_field( 'location_fields' ),
			'class'      => isset( $attributes['className'] ) && ! empty( $attributes['className'] ) ? $attributes['className'] : '',
			'align'      => isset( $attributes['align'] ) ? esc_html( $attributes['align'] ) : '',
		];

		// Force default post type if none.
		$args['post_type'] = $args['post_type'] ?: 'mai_location';

		echo mailocations_get_locations_table( $args );
	}

	/**
	 * Registers the block's field group.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function register_field_group(): void {
		// Locations Table block.
		acf_add_local_field_group(
			[
				'title'  => __( 'Locations Table', 'mai-locations' ),
				'key'    => 'mailocations_locations_table_field_group',
				'fields' => [
					[
						'label'       => __( 'Post Type', 'mai-locations' ),
						'key'         => 'field_6071bfebbddbg',
						'name'        => 'locations_table_post_type',
						'type'        => 'select',
					],
					[
						'label'       => __( 'Title', 'mai-locations' ),
						'key'         => 'field_6071bfebbfdab',
						'name'        => 'locations_table_title',
						'type'        => 'text',
						'placeholder' => __( 'My Location', 'mai-locations' ),
					],
					[
						'label'       => __( 'Table Header', 'mai-locations' ),
						'key'         => 'field_6071c00cbfdac',
						'name'        => 'locations_table_header',
						'type'        => 'text',
						'placeholder' => __( 'Locations', 'mai-locations' ),
					],
					[
						'label' => __( 'No Results Message', 'mai-locations' ),
						'key'   => 'field_6071d22cdrdbd',
						'name'  => 'locations_no_results',
						'type'  => 'textarea',
						'rows'  => 2,
					],
					[
						// The name matches the submission block's field, so saved values still
						// load. The key must not, or ACF keeps whichever group registers first
						// and this block shows the submission block's labels. Fixed September
						// 16, 2026.
						'label'        => __( 'Redirect', 'mai-locations' ),
						'instructions' => __( 'Redirect to this URL after saving.', 'mai-locations' ),
						'key'          => 'mailocations_table_redirect',
						'name'         => 'location_redirect',
						'type'         => 'text',
					],
					[
						'label'         => __( 'Edit Form Fields', 'mai-locations' ),
						'instructions'  => __( 'Allow editing of these fields.', 'mai-locations' ),
						'key'           => 'mailocations_table_fields',
						'name'          => 'location_fields',
						'type'          => 'checkbox',
						'multiple'      => 1,
						'allow_null'    => 0,
						'ui'            => 1,
						'ajax'          => 1,
						'choices'       => [],
						'default_value' => [],
						'wrapper'       => [
							'class' => 'mai-locations-sortable',
						],
					],
				],
				'location' => [
					[
						[
							'param'    => 'block',
							'operator' => '==',
							'value'    => 'acf/mai-locations-table',
						],
					],
				],
			]
		);
	}

	/**
	 * Loads the post type choices.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $field The field.
	 *
	 * @return array<string, mixed>
	 */
	public function load_post_type_choices( $field ) {
		if ( ! is_admin() ) {
			return $field;
		}

		$field['choices'] = array_map( function ( $name ) {
			return $name['plural'];
		}, mailocations_get_location_post_types() );

		return $field;
	}
}

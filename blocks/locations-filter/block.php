<?php

// Prevent direct file access.
defined( 'ABSPATH' ) || die;

add_action( 'acf/init', 'mailocations_register_locations_filter_block' );
/**
 * Register Mai Location Filter block.
 *
 * @since TBD
 *
 * @return void
 */
function mailocations_register_locations_filter_block() {
	register_block_type( __DIR__ . '/block.json' );
}

/**
 * Callback function to render the block.
 *
 * @since TBD
 *
 * @param array    $attributes The block attributes.
 * @param string   $content The block content.
 * @param bool     $is_preview Whether or not the block is being rendered for editing preview.
 * @param int      $post_id The current post being edited or viewed.
 * @param WP_Block $wp_block The block instance (since WP 5.5).
 * @param array    $context The block context array.
 *
 * @return void
 */
function mailocations_do_locations_filter_block( $attributes, $content, $is_preview, $post_id, $wp_block, $context ) {

	// TODO: Show note in preview/editor so it's not blank when no filter is chosen.

	$taxonomy = get_field( 'filter' );
	$type     = get_field( 'type' );
	$type     = $type ?: 'select';

	if ( ! $taxonomy || ! taxonomy_exists( $taxonomy ) ) {
		if ( $is_preview || is_admin() ) {
			printf( '<p>%s</p>', __( 'Choose a location filter in the block settins.', 'mai-locations' ) );
		}
		return;
	}

	// Get terms from taxonomy.
	$terms = get_terms(
		[
			'taxonomy'   => $taxonomy,
			'hide_empty' => true,
		]
	);

	// Bail if no terms.
	if ( ! $terms || is_wp_error( $terms ) ) {
		return;
	}

	// Get any selected items.
	$selected = isset( $_GET[ $taxonomy ] ) && ! empty( $_GET[ $taxonomy ] ) ? $_GET[ $taxonomy ] : [];
	$selected = $selected ? array_flip( array_filter( explode( '-', $selected ) ) ) : $selected;

	echo '<ul class="mailocations-filter-list">';
	foreach ( $terms as $term ) {
		$checked = $selected && isset( $selected[ $term->slug ] ) ? ' checked' : '';

		printf( '<li><label><input type="checkbox" class="mailocations-filter" name="%s[]" data-filter="%s" value="%s"%s> %s</label></li>',
			$taxonomy,
			$taxonomy,
			$term->slug,
			$checked,
			$term->name
		);
	}
	echo '</ul>';
}

add_action( 'acf/init', 'mailocations_register_locations_filter_field_group' );
/**
 * Register field group.
 *
 * @since TBD
 *
 * @return void
 */
function mailocations_register_locations_filter_field_group() {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	acf_add_local_field_group(
		[
			'key'    => 'mailocations_locations_filter_field_group',
			'title'  => __( 'Mai Locations Filter', 'mai-locations' ),
			'fields' => [
				[
					'key'           => 'mailocations_locations_filter',
					'label'         => __( 'Filter by', 'mai-locations' ),
					'name'          => 'filter',
					'type'          => 'select',
					'choices'       => [],
					'default_value' => [],
					'return_format' => 'value',
					'multiple'      => 0,
					'allow_null'    => 1,
					'ui'            => 0,
					'ajax'          => 1,
					'placeholder'   => '',
				],
				[
					'key'           => 'mailocations_locations_filter_type',
					'label'         => __( 'Field type', 'mai-locations' ),
					'name'          => 'type',
					'type'          => 'radio',
					'choices'       => [
						'select'   => __( 'Select box', 'mai-locations' ),
						'checkbox' => __( 'Checkboxes (choose multiple)', 'mai-locations' ),
						'radio'    => __( 'Radio buttons (choose one)', 'mai-locations' ),
					],
					'default_value' => [],
					'multiple'      => 0,
					'allow_null'    => 0,
					'ui'            => 0,
				],
			],
			'location' => [
				[
					[
						'param'    => 'block',
						'operator' => '==',
						'value'    => 'acf/mai-locations-filter',
					],
				],
			],
		]
	);
}

add_filter( 'acf/load_field/key=mailocations_locations_filter', 'mailocations_load_locations_filter_field' );
/**
 * Load the taxonomy filter with all taxonomies registered to locations.
 *
 * @param  array $field
 *
 * @return array
 */
function mailocations_load_locations_filter_field( $field ) {
	$field['choices'] = mailocations_get_location_taxonomies();

	return $field;
}

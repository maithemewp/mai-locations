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
	$taxonomy = get_field( 'filter' );
	$type     = get_field( 'type' );
	$type     = $type ?: 'select';

	if ( ! $taxonomy || ! taxonomy_exists( $taxonomy ) ) {
		if ( $is_preview || is_admin() ) {
			printf( '<p>%s</p>', __( 'Choose a location filter in the block settings.', 'mai-locations' ) );
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

	// Maybe enqueue scripts.
	if ( ! $is_preview ) {
		wp_enqueue_script( 'mai-locations' );
	}

	// Maybe load CSS.
	echo mailocations_get_stylesheet_link( 'mai-locations' );

	// Get any selected items.
	$selected = isset( $_GET[ $taxonomy ] ) && ! empty( $_GET[ $taxonomy ] ) ? $_GET[ $taxonomy ] : [];
	$selected = $selected ? array_flip( array_filter( explode( ',', $selected ) ) ) : $selected;

	switch ( $type ) {
		case 'checkbox':
		case 'radio':
			echo mailocations_get_choice_filter( $taxonomy, $terms, $selected, $type );
		break;
		case 'select':
			echo mailocations_get_select_filter( $taxonomy, $terms, $selected );
		break;
	}
}

/**
 * Gets checkbox and radio filter markup.
 *
 * @access private
 *
 * @since TBD
 *
 * @param  string    $taxonomy
 * @param  WP_Term[] $terms
 * @param  array     $selected
 * @param  string    $type
 *
 * @return string
 */
function mailocations_get_choice_filter( $taxonomy, $terms, $selected, $type ) {
	$html = sprintf( '<ul class="mailocations-filter-list"%s>', is_admin() ? ' style="list-style-type:none;margin-left:0;padding-left:0;"' : '' );

	foreach ( $terms as $term ) {
		$html .= sprintf( '<li><label><input type="%s" class="mailocations-filter" name="%s[]" data-filter="%s" value="%s"%s> %s</label></li>',
			$type,
			$taxonomy,
			$taxonomy,
			$term->slug,
			$selected && isset( $selected[ $term->slug ] ) ? ' checked' : '',
			$term->name
		);
	}

	$html .= '</ul>';

	return $html;
}

/**
 * Gets choice filter markup.
 *
 * @access private
 *
 * @since TBD
 *
 * @param  string    $taxonomy
 * @param  WP_Term[] $terms
 * @param  array     $selected
 * @param  string    $type
 *
 * @return string
 */
function mailocations_get_select_filter( $taxonomy, $terms, $selected ) {
	$html = sprintf( '<select class="mailocations-filter" data-filter="%s" name="%s[]">', $taxonomy, $taxonomy );

		$html .= sprintf( '<option value="">%s %s</option>', __( 'All', 'mai-locations' ), get_taxonomy( $taxonomy )->labels->name );

		foreach ( $terms as $term ) {
			$html .= sprintf( '<option value="%s"%s>%s</option>',
				$term->slug,
				$selected && isset( $selected[ $term->slug ] ) ? ' selected' : '',
				$term->name
			);
		}

	$html .= '</select>';

	return $html;
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
					'type'          => 'select',
					'choices'       => [
						'select'   => __( 'Select box (choose one)', 'mai-locations' ),
						'radio'    => __( 'Radio buttons (choose one)', 'mai-locations' ),
						'checkbox' => __( 'Checkboxes (choose multiple)', 'mai-locations' ),
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

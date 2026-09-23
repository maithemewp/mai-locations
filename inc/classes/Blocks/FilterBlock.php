<?php

declare(strict_types=1);

namespace Mai\Locations\Blocks;

// Prevent direct file access.
defined( 'ABSPATH' ) || die;

/**
 * A single taxonomy filter, as a select, radio group or checkbox list.
 *
 * Was Mai_Locations_Filter_Block in blocks/location-filter/block.php. That name still works,
 * via inc/aliases.php. block.json stays in blocks/location-filter/.
 *
 * @since TBD
 */
class FilterBlock {

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
		add_action( 'acf/init',                                         [ $this, 'register_block' ] );
		add_action( 'acf/init',                                         [ $this, 'register_field_group' ] );
		add_filter( 'acf/load_field/key=mailocations_locations_filter', [ $this, 'load_locations_filter_field' ] );
	}

	/**
	 * Registers the block.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function register_block(): void {
		register_block_type( MAI_LOCATIONS_PLUGIN_DIR . 'blocks/location-filter',
			[
				'render_callback' => [ $this, 'render_block' ],
			]
		);
	}

	/**
	 * Renders the block.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $attributes The block attributes.
	 * @param string               $content    The block content.
	 * @param bool                 $is_preview Whether the block is rendering for an editor preview.
	 * @param int                  $post_id    The current post being edited or viewed.
	 * @param \WP_Block            $wp_block   The block instance.
	 * @param array<string, mixed> $context    The block context array.
	 *
	 * @return void
	 */
	public function render_block( $attributes, $content, $is_preview, $post_id, $wp_block, $context ): void {
		$taxonomy = get_field( 'filter' );
		$type     = get_field( 'type' );
		$type     = $type ?: 'select';
		$text     = sprintf( '<p>%s</p>', __( 'Choose a location filter in the block settings.', 'mai-locations' ) );

		if ( ! $taxonomy || ! taxonomy_exists( $taxonomy ) ) {
			if ( $is_preview ) {
				echo $text;
			}
			return;
		}

		if ( ! $type && $is_preview ) {
			echo $text;
			return;
		}

		// Get terms from taxonomy.
		$terms = get_terms(
			[
				'taxonomy'   => $taxonomy,
				'hide_empty' => ! $is_preview, // Hide empty on front-end.
			]
		);

		// Bail if no terms.
		if ( ! $terms || is_wp_error( $terms ) ) {
			if ( ! $terms && $is_preview ) {
				echo $text;
			}
			return;
		}

		// Maybe enqueue scripts.
		if ( ! $is_preview ) {
			wp_enqueue_script( 'mai-locations' );
		}

		// Maybe load CSS.
		echo mailocations_get_stylesheet_link( 'mai-locations' );

		// Get any selected items.
		$prefixed = "_{$taxonomy}";
		// The value is normally a comma list, but anyone can send it as an array instead
		// (?_mai_location_cat[]=x), and explode() on an array took the whole page down.
		// Accept either, and keep only plain values. Fixed September 23, 2026.
		$raw      = isset( $_GET[ $prefixed ] ) ? wp_unslash( $_GET[ $prefixed ] ) : [];
		$list     = is_array( $raw ) ? $raw : explode( ',', (string) $raw );
		$list     = array_map( 'sanitize_text_field', array_map( 'strval', array_filter( $list, 'is_scalar' ) ) );
		$selected = array_flip( array_filter( $list ) );

		switch ( $type ) {
			case 'checkbox':
			case 'radio':
				echo $this->get_choice_filter( $taxonomy, $terms, $selected, $type );
			break;
			case 'select':
				echo $this->get_select_filter( $taxonomy, $terms, $selected );
			break;
		}
	}

	/**
	 * Gets the checkbox and radio filter markup.
	 *
	 * @access private
	 *
	 * @since TBD
	 *
	 * @param string               $taxonomy The taxonomy name.
	 * @param array<int, \WP_Term> $terms    The terms.
	 * @param array<string, int>   $selected The selected term slugs.
	 * @param string               $type     checkbox or radio.
	 *
	 * @return string
	 */
	public function get_choice_filter( $taxonomy, $terms, $selected, $type ) {
		$html = sprintf( '<ul class="mailocations-filter-list"%s>', is_admin() ? ' style="list-style-type:none;margin-left:0;padding-left:0;"' : '' );

		foreach ( $terms as $term ) {
			$html .= sprintf( '<li><label><input type="%s" class="mailocations-filter" tabindex="0" name="mailocations_filters[_%s]" data-filter="_%s" value="%s"%s> %s</label></li>',
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
	 * Gets the select filter markup.
	 *
	 * @access private
	 *
	 * @since TBD
	 *
	 * @param string               $taxonomy The taxonomy name.
	 * @param array<int, \WP_Term> $terms    The terms.
	 * @param array<string, int>   $selected The selected term slugs.
	 *
	 * @return string
	 */
	public function get_select_filter( $taxonomy, $terms, $selected ) {
		$html = sprintf( '<select class="mailocations-filter" tabindex="0" data-filter="_%s" name="mailocations_filters[_%s]">', $taxonomy, $taxonomy );
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

	/**
	 * Registers the block's field group.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	public function register_field_group(): void {
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

	/**
	 * Loads the taxonomy filter with every taxonomy registered to locations.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $field The field.
	 *
	 * @return array<string, mixed>
	 */
	public function load_locations_filter_field( $field ) {
		if ( ! is_admin() ) {
			return $field;
		}

		$field['choices'] = mailocations_get_location_taxonomies();

		return $field;
	}
}

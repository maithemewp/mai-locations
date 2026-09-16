<?php

declare(strict_types=1);

namespace Mai\Locations\Blocks;

// Prevent direct file access.
defined( 'ABSPATH' ) || die;

/**
 * The location submission block, which renders the front-end submission form.
 *
 * Was Mai_Locations_Submission_Block in blocks/location-submission/block.php. That name still
 * works, via inc/aliases.php. block.json stays in blocks/location-submission/.
 *
 * @since TBD
 */
class SubmissionBlock {

	/**
	 * Construct the class.
	 */
	public function __construct() {
		$this->hooks();
	}

	/**
	 * Add hooks.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	public function hooks(): void {
		add_action( 'acf/init',                                  [ $this, 'register_block' ] );
		add_action( 'acf/init',                                  [ $this, 'register_field_group' ] );
		add_filter( 'acf/load_field/key=mai_location_post_type', [ $this, 'load_post_type_choices' ] );
	}

	/**
	 * Registers the block.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	public function register_block(): void {
		register_block_type( MAI_LOCATIONS_PLUGIN_DIR . 'blocks/location-submission',
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
		$args = [
			'fields'    => array_filter( (array) get_field( 'location_fields' ) ),
			'post_type' => get_field( 'location_post_type' ),
			'status'    => get_field( 'location_status' ),
			'redirect'  => get_field( 'location_redirect' ),
			'emails'    => get_field( 'location_emails' ),
			'class'     => isset( $attributes['className'] ) && ! empty( $attributes['className'] ) ? $attributes['className'] : '',
			'preview'   => $is_preview,
		];

		echo mailocations_get_location_submission_form( $args );
	}

	/**
	 * Registers the block's field group.
	 *
	 * TODO: mai_location_redirect and mai_location_fields are the same keys the table block
	 * registers, so ACF keeps whichever loads first. See TODO.md.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	public function register_field_group(): void {
		// Register the field group.
		acf_add_local_field_group(
			[
				'title'  => __( 'Locations Table', 'mai-locations' ),
				'key'    => 'mai_location_submission_field_group',
				'fields' => [
					[
						'label'    => __( 'Location Post Type', 'mai-locations'),
						'key'      => 'mai_location_post_type',
						'name'     => 'location_post_type',
						'type'     => 'select',
						'choices'  => '', // Added via filter because it's too early to get the post types.
					],
					[
						'label'    => __( 'Location Status', 'mai-locations'),
						'key'      => 'mai_location_status',
						'name'     => 'location_status',
						'type'     => 'select',
						'choices'  => get_post_statuses(),
					],
					[
						// This field has to match what's in locations-table/block.php.
						'label'        => __( 'Submission Redirect', 'mai-locations' ),
						'instructions' => __( 'Redirect to this URL after submission.', 'mai-locations' ),
						'key'          => 'mai_location_redirect',
						'name'         => 'location_redirect',
						'type'         => 'text',
					],
					[
						'label'        => __( 'Submission Notifications', 'mai-locations' ),
						'instructions' => __( 'Send notificaiton of submission the following comma-separated email addresses.', 'mai-locations' ),
						'key'          => 'mai_location_emails',
						'name'         => 'location_emails',
						'type'         => 'text',
					],
					[
						// This field has to match what's in locations-table/block.php.
						'label'         => __( 'Submission Form Fields', 'mai-locations' ),
						'instructions'  => __( 'Allow editing of these fields.', 'mai-locations' ),
						'key'           => 'mai_location_fields',
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
							'value'    => 'acf/mai-location-submission',
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

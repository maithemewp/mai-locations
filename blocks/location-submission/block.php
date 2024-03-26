<?php

// Prevent direct file access.
defined( 'ABSPATH' ) || die;

/**
 * The location submission block class.
 *
 * @since TBD
 */
class Mai_Locations_Submission_Block {
	/**
	 * Construct the class.
	 */
	function __construct() {
		$this->hooks();
	}

	/**
	 * Add hooks.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	function hooks() {
		add_action( 'acf/init', [ $this, 'register_block' ] );
		add_action( 'acf/init', [ $this, 'register_field_group' ] );
	}

	/**
	 * Registers block.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	function register_block() {
		register_block_type( __DIR__ . '/block.json',
			[
				'render_callback' => [ $this, 'render_block' ],
			]
		);
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
	function render_block( $attributes, $content, $is_preview, $post_id, $wp_block, $context ) {
		$args = [
			'fields'   => array_filter( (array) get_field( 'location_fields' ) ),
			'status'   => get_field( 'location_status' ),
			'redirect' => get_field( 'location_redirect' ),
			'emails'   => get_field( 'location_emails' ),
			'class'    => isset( $attributes['className'] ) && ! empty( $attributes['className'] ) ? $attributes['className'] : '',
			'preview'  => $is_preview,
		];

		echo mailocations_get_location_submission_form( $args );
	}

	/**
	 * Add field group.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	function register_field_group() {
		$plural   = mailocations_get_plural();
		$singular = mailocations_get_singular();

		acf_add_local_field_group(
			[
				'title'  => __( 'Locations Table', 'mai-locations' ),
				'key'    => 'mai_location_submission_field_group',
				'fields' => [
					[
						'label'    => __( 'Location Status', 'mai-locations'),
						'key'      => 'mai_location_status',
						'name'     => 'location_status',
						'type'     => 'select',
						'required' => 1,
						'choices'  => get_post_statuses(),
					],
					[
						// This field has to match what's in locations-table/block.php.
						'label'        => sprintf( '%s %s', $singular, __( 'Submission Redirect', 'mai-locations' ) ),
						'instructions' => __( 'Redirect to this URL after submission.', 'mai-locations' ),
						'key'          => 'mai_location_redirect',
						'name'         => 'location_redirect',
						'type'         => 'text',
					],
					[
						'label'        => sprintf( '%s %s', $singular, __( 'Submission Notifications', 'mai-locations' ) ),
						'instructions' => __( 'Send notificaiton of submission the following comma-separated email addresses.', 'mai-locations' ),
						'key'          => 'mai_location_emails',
						'name'         => 'location_emails',
						'type'         => 'text',
					],
					[
						// This field has to match what's in locations-table/block.php.
						'label'         => sprintf( '%s %s', $singular, __( 'Submission Form Fields', 'mai-locations' ) ),
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
}
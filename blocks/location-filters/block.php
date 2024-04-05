<?php

// Prevent direct file access.
defined( 'ABSPATH' ) || die;

/**
 * The locations filter block class.
 *
 * @since TBD
 */
class Mai_Locations_Filters_Block {
	/**
	 * Construct the class.
	 */
	function __construct() {
		$this->hooks();
	}

	/**
	 * Add hooks.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	function hooks() {
		add_action( 'acf/init',                               [ $this, 'register_block' ] );
		add_action( 'admin_post_mailocations_filters',        [ $this, 'post_action' ] );
		add_action( 'admin_post_nopriv_mailocations_filters', [ $this, 'post_action' ] );
	}

	/**
	 * Registers block.
	 *
	 * @since 0.1.0
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
		// Output the form.
		printf( '<form class="mai-locations-filters" method="post" action="%s">', esc_url( admin_url( 'admin-post.php' ) ) );
			// Hidden inputs and nonce.
			echo '<input type="hidden" name="action" value="mailocations_filters">';
			printf( '<input type="hidden" name="gets" value="%s">', esc_attr( json_encode( $_GET ) ) );
			wp_nonce_field( 'mailocations_filters', 'mailocations_filters_nonce' );
			// Inner blocks.
			printf( '<InnerBlocks template="%s" />', esc_attr( wp_json_encode( $this->get_template() ) ) );
		echo '</form>';
	}

	/**
	 * Listener for generating default ads.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	function post_action() {
		// Bail if not a valid request.
		if ( ! ( isset( $_POST['mailocations_filters_nonce'] ) && wp_verify_nonce( $_POST['mailocations_filters_nonce'], 'mailocations_filters' ) ) ) {
			return;
		}

		// Get the current url, includes query params.
		$redirect = wp_get_referer();

		// Get existing query strings and new values.
		$gets    = isset( $_POST['gets'] ) ? (array) json_decode( stripslashes( $_POST['gets'] ), true ) : [];
		$filters = isset( $_POST['mailocations_filters'] ) ? (array) $_POST['mailocations_filters'] : [];
		$address = isset( $_POST['mailocations_address'] ) ? (array) json_decode( stripslashes( $_POST['mailocations_address'] ), true ) : [];

		// Build new query strings, merging or overriding existing.
		$args = array_merge( $gets, $filters, $address );

		// Build new url.
		$redirect = add_query_arg( $args, $redirect );


		wp_safe_redirect( esc_url( $redirect ) );
		exit;
	}

	/**
	 * Get the block template.
	 *
	 * @since TBD
	 *
	 * @return array
	 */
	function get_template() {
		return [
			[
				'acf/mai-locations-address-search',
				[
					'data' => [
						'distances' => '25, 50, 100, 200',
						'countries' => [ 'US' ],
					],
				],
				[],
			],
			[
				'core/spacer',
				[ 'height' => '16px' ],
				[],
			],
			[
				'acf/mai-locations-filter',
				[
					'data' => [
						'filter' => 'mai_location_cat',
						'type'   => 'select',
					],
				],
				[],
			],
			[
				'core/spacer',
				[ 'height' => '10px' ],
				[],
			],
			[
				'core/buttons',
				[
					'variantType' => 'mailocations-filter-submit',
				],
				[
					[
						'core/button',
						[
							'text'  => __( 'Search/Filter', 'mai-locations' ),
							'width' => 100,
						],
						[],
					],
				],
			],
			[
				'core/buttons',
				[
					'variantType' => 'mailocations-filter-clear',
					'layout'      => [
						'type'           => 'flex',
						'justifyContent' => 'center',
					],
				],
				[
					[
						'core/button',
						[
							'text'      => __( 'Clear Filters', 'mai-locations' ),
							'fontSize'  => 'sm',
							'className' => 'is-style-link',
							'style'     => [
								'color' => [
									'text' => '#ff0000'
								],
								'elements' => [
									'link' => [
										'color' => [
											'text' => '#ff0000'
										],
									],
								],
							],
						],
						[],
					],
				],
			],
		];
	}
}
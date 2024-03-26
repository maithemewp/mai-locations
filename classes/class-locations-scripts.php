<?php

// Prevent direct file access.
defined( 'ABSPATH' ) || die;

class Mai_Locations_Scripts {
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
		add_action( 'wp_enqueue_scripts',          [ $this, 'register_scripts' ] );
		add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_sortable' ] );
	}

	/**
	 * Enqueues scripts and styles.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	function register_scripts() {
		$suffix = mailocations_get_suffix();
		wp_register_style( 'mai-locations-form', MAI_LOCATIONS_PLUGIN_URL . "assets/css/mai-locations-form{$suffix}.css", [], MAI_LOCATIONS_VERSION );
		wp_register_style( 'mai-locations', MAI_LOCATIONS_PLUGIN_URL . "assets/css/mai-locations{$suffix}.css", [], MAI_LOCATIONS_VERSION );
		wp_register_script( 'mai-locations-markerclusterer', MAI_LOCATIONS_PLUGIN_URL . "assets/js/markerclusterer{$suffix}.js", [], '2.1.4', true );
		wp_register_script( 'mai-locations', MAI_LOCATIONS_PLUGIN_URL . "assets/js/mai-locations{$suffix}.js", [], MAI_LOCATIONS_VERSION, true );

		$localize = [
			'params'     => mailocations_get_query_params(),
			'defaults'   => mailocations_get_query_defaults(),
			'apiKey'     => mailocations_get_google_maps_api_key(),
			'loadingSvg' => MAI_LOCATIONS_PLUGIN_URL . 'assets/svg/loading.svg',
			// 'autoComplete' => [
			// 	'fields'                => [ 'geometry', 'name' ],
			// 	'strictBounds'          => false,
			// 	'componentRestrictions' => [ 'country' => 'us' ],
			// 	'types'                 => [ 'establishment' ],
			// ],
		];

		// Allow filtering of script data.
		$localize = apply_filters( 'mailocations_localize_script_data', $localize );

		// Localize.
		wp_localize_script( 'mai-locations', 'maiLocationsVars', $localize );
	}

	/**
	 * Add sortable scripts and styles.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	function enqueue_sortable() {
		wp_enqueue_script( 'mai-locations-sortable', MAI_LOCATIONS_PLUGIN_URL . 'assets/js/mai-locations-sortable.js', [ 'jquery', 'jquery-ui-sortable', 'acf-input' ], MAI_LOCATIONS_VERSION, true );
		wp_enqueue_style( 'mai-locations-sortable', MAI_LOCATIONS_PLUGIN_URL . 'assets/css/mai-locations-sortable.css', [], MAI_LOCATIONS_VERSION );
	}
}
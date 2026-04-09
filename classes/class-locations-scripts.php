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
		// Asset data for cache busting.
		$locations_asset       = mailocations_get_asset( 'mai-locations' );
		$locations_style_asset = mailocations_get_asset( 'mai-locations-styles' );
		$form_style_asset      = mailocations_get_asset( 'mai-locations-form-styles' );
		$clusterer_asset       = mailocations_get_asset( 'markerclusterer' );

		// Styles.
		wp_register_style( 'mai-locations-form', MAI_LOCATIONS_PLUGIN_URL . 'build/mai-locations-form-styles.css', [], $form_style_asset['version'] );
		wp_register_style( 'mai-locations', MAI_LOCATIONS_PLUGIN_URL . 'build/mai-locations-styles.css', [], $locations_style_asset['version'] );

		// Scripts — no defer on map scripts due to Google Maps callback timing.
		wp_register_script( 'mai-locations-markerclusterer', MAI_LOCATIONS_PLUGIN_URL . 'build/markerclusterer.js', $clusterer_asset['dependencies'], $clusterer_asset['version'], [ 'in_footer' => true ] );
		wp_register_script( 'mai-locations', MAI_LOCATIONS_PLUGIN_URL . 'build/mai-locations.js', $locations_asset['dependencies'], $locations_asset['version'], [ 'in_footer' => true ] );

		$localize = [
			'params'     => mailocations_get_query_params(),
			'defaults'   => mailocations_get_query_defaults(),
			'apiKey'     => mailocations_get_option( 'google_api_key' ),
			'apiSig'     => mailocations_get_option( 'google_api_signature' ),
			'mapId'      => mailocations_get_option( 'google_map_id' ),
			'loadingSvg' => MAI_LOCATIONS_PLUGIN_URL . 'assets/svg/loading.svg',
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
		$sortable_asset       = mailocations_get_asset( 'mai-locations-sortable' );
		$sortable_style_asset = mailocations_get_asset( 'mai-locations-sortable-styles' );
		wp_enqueue_script( 'mai-locations-sortable', MAI_LOCATIONS_PLUGIN_URL . 'build/mai-locations-sortable.js', array_merge( [ 'jquery', 'jquery-ui-sortable', 'acf-input' ], $sortable_asset['dependencies'] ), $sortable_asset['version'], [ 'strategy' => 'defer', 'in_footer' => true ] );
		wp_enqueue_style( 'mai-locations-sortable', MAI_LOCATIONS_PLUGIN_URL . 'build/mai-locations-sortable-styles.css', [], $sortable_style_asset['version'] );
	}
}
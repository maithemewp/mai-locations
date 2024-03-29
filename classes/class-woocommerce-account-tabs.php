<?php

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Locations WooCommerce account tab.
 * Should only be instantiated if WooCommerce is active.
 *
 * @since TBD
 *
 * @return void
 */
class Mai_Locations_WooCommerce_Account_Tabs {
	/**
	 * Gets is started.
	 *
	 * @since TBD
	 */
	function __construct() {
		// Hooks.
		$this->hooks();
	}

	/**
	 * Gets tabs.
	 *
	 * @since TBD
	 *
	 * @return array
	 */
	function get_tabs() {
		// Set static tabs.
		static $tabs = null;

		// Return if cached.
		if ( ! is_null( $tabs ) ) {
			return $tabs;
		}

		// Default tabs.
		$tabs = [ mailocations_get_base() => mailocations_get_plural() ];

		// Set filtereable tabs.
		$tabs = apply_filters( 'mailocations_woocommerce_account_tabs', $tabs );

		return $tabs;
	}

	/**
	 * Runs hooks.
	 *
	 * @since TBD
	 */
	function hooks() {
		add_action( 'init',                           [ $this, 'add_endpoint' ] );
		add_filter( 'query_vars',                     [ $this, 'add_query_vars' ], 0 );
		add_filter( 'woocommerce_get_query_vars',     [ $this, 'add_woo_query_vars' ] );
		add_filter( 'mai_template-parts_config',      [ $this, 'add_content_areas' ] );
		add_filter( 'woocommerce_account_menu_items', [ $this, 'add_menu_items' ] );
		add_action( 'get_header',                     [ $this, 'add_acf_form_head' ] );
	}

	/**
	 * Adds account nav endpoints.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	function add_endpoint() {
		foreach ( $this->get_tabs() as $endpoint => $label ) {
			// Add endpoint.
			add_rewrite_endpoint( $endpoint, EP_ROOT | EP_PAGES );

			// Add action.
			add_action( "woocommerce_account_{$endpoint}_endpoint", function() use ( $endpoint ) {
				// Add action hook.
				do_action( "mailocations_account_{$endpoint}_content" );
			});
		}
	}

	/**
	 * Adds query vars.
	 *
	 * @since TBD
	 *
	 * @param array $vars The existing query vars.
	 *
	 * @return array
	 */
	function add_query_vars( $vars ) {
		foreach ( $this->get_tabs() as $endpoint => $label ) {
			$vars[] = $endpoint;
		}

		return $vars;
	}

	/**
	 * Adds WooCommerce query vars.
	 *
	 * @since TBD
	 *
	 * @param array $vars The existing query vars.
	 *
	 * @return array
	 */
	function add_woo_query_vars( $vars ) {
		foreach ( $this->get_tabs() as $endpoint => $label ) {
			$vars[ $endpoint ] = $endpoint;
		}

		return $vars;
	}

	/**
	 * Adds content area for Mai Theme v2.
	 *
	 * @since TBD
	 *
	 * @param array $config The existing config array.
	 *
	 * @return array
	 */
	function add_content_areas( $config ) {
		foreach ( $this->get_tabs() as $endpoint => $label ) {
			// Add to config.
			$config["woo-{$endpoint}"] = [
				'hook'   => "mailocations_account_{$endpoint}_content",
				'before' => "<div class=\"mai-locations-woo-{$endpoint}\">",
				'after'  => '</div>',
			];
		}

		return $config;
	}

	/**
	 * Adds account menu item.
	 * Is not added if Mai Theme v2 Content Area doesn't exist or doesn't have content.
	 *
	 * @since TBD
	 *
	 * @param array $items The existing items.
	 *
	 * @return array
	 */
	function add_menu_items( $items ) {
		foreach ( $this->get_tabs() as $endpoint => $label ) {
			// Skip if Mai Theme v2 and no template part.
			if ( function_exists( 'mai_has_template_part' ) && ! mai_has_template_part( "woo-{$endpoint}" ) ) {
				continue;
			}

			// Add to menu.
			$items = $this->insert_before( $items, 'customer-logout', [ $endpoint => $label ] );
		}

		return $items;
	}

	/**
	 * Adds ACF form head if needed.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	function add_acf_form_head() {
		if ( ! is_account_page() ) {
			return;
		}

		// Loop through tabs.
		foreach ( $this->get_tabs() as $endpoint => $label ) {
			// Bail if not this endpoint.
			if ( ! ( $endpoint && is_wc_endpoint_url( $endpoint ) ) ) {
				continue;
			}

			// If Mai Theme v2.
			if ( function_exists( 'mai_get_template_part' ) ) {
				$content = mai_get_template_part( "woo-{$endpoint}" );

				// If we have an acf form, load ACF form head.
				if ( has_block( 'acf/mai-location-submission', $content ) || has_block( 'acf/mai-locations-table', $content ) ) {
					acf_form_head();
				}
			}
			// Not Mai Theme v2, should we always load `acf_form_head()`?
			else {
				acf_form_head();
			}
		}
	}

	/**
	 * Insert a value or key/value pair before a specific key in an array.
	 * If key doesn't exist, value is appended to the end of the array.
	 *
	 * @since TBD
	 *
	 * @param array  $array
	 * @param string $key
	 * @param array  $new
	 *
	 * @return array
	 */
	function insert_before( array $array, $key, array $new ) {
		$keys  = array_keys( $array );
		$index = array_search( $key, $keys );
		$pos   = $index !== false ? $index : count( $array ); // If key doesn't exist, insert at the end.

		return array_merge( array_slice( $array, 0, $pos ), $new, array_slice( $array, $pos ) );
	}

	/**
	 * Insert a value or key/value pair after a specific key in an array.
	 * If key doesn't exist, value is appended to the end of the array.
	 *
	 * @since TBD
	 *
	 * @param array  $array
	 * @param string $key
	 * @param array  $new
	 *
	 * @return array
	 */
	function insert_after( array $array, $key, array $new ) {
		$keys  = array_keys( $array );
		$index = array_search( $key, $keys );
		$pos   = false === $index ? count( $array ) : $index + 1;

		return array_merge( array_slice( $array, 0, $pos ), $new, array_slice( $array, $pos ) );
	}
}

<?php

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Locations WooCommerce account tab.
 *
 * @since TBD
 *
 * @return void
 */
class Mai_Locations_WooCommerce_Account_Tab {
	/**
	 * Tabs.
	 *
	 * @since TBD
	 *
	 * @var $tabs
	 */
	protected $tabs;

	/**
	 * Gets is started.
	 *
	 * @since TBD
	 */
	function __construct() {
		// Default tabs.
		$tabs = [ mailocations_get_base() => mailocations_get_plural() ];

		// Set filtereable tabs.
		$this->tabs = apply_filters( 'mai_locations_woocommerce_account_tabs', $tabs );

		// Hooks.
		$this->hooks();
	}

	/**
	 * Runs hooks.
	 *
	 * @since TBD
	 */
	function hooks() {
		add_action( 'init',                           [ $this, 'add_endpoint' ] );
		add_filter( 'query_vars',                     [ $this, 'add_query_vars' ], 0 );
		add_filter( 'mai_template-parts_config',      [ $this, 'add_content_areas' ] );
		add_filter( 'woocommerce_account_menu_items', [ $this, 'add_menu_items' ] );

		// Add endpoint content.
		foreach ( $this->tabs as $endpoint => $label ) {
			// Add action.
			add_action( "woocommerce_account_{$endpoint}_endpoint", function() use ( $endpoint ) {
				// Add action hook.
				do_action( "mailocations_account_{$endpoint}_content" );
			});
		}
	}

	/**
	 * Adds account nav endpoints.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	function add_endpoint() {
		foreach ( $this->tabs as $endpoint => $label ) {
			add_rewrite_endpoint( $endpoint, EP_ROOT | EP_PAGES );
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
		foreach ( $this->tabs as $endpoint => $label ) {
			$vars[] = $endpoint;
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
		foreach ( $this->tabs as $endpoint => $label ) {
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
		foreach ( $this->tabs as $endpoint => $label ) {
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

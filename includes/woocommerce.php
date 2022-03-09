<?php

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'plugins_loaded', 'mailocations_woocommerce_account_tab' );
/**
 * Adds locations table to WooCommerce account menu.
 *
 * @since 0.1.0
 *
 * @return void
 */
function mailocations_woocommerce_account_tab() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	new Mai_Locations_WooCommerce_Account_Tab;
}

/**
 * Locations WooCommerce account tab.
 *
 * @since 0.1.0
 *
 * @return void
 */
class Mai_Locations_WooCommerce_Account_Tab {
	/**
	 * Tab endpoint.
	 *
	 * @since 0.1.0
	 *
	 * @var $endpoint
	 */
	protected $endpoint;

	/**
	 * Tab slug.
	 *
	 * @since 0.1.0
	 *
	 * @var $slug
	 */
	protected $slug;

	/**
	 * Gets is started.
	 *
	 * @since 0.1.0
	 */
	function __construct() {
		$this->endpoint = mailocations_get_base();
		$this->hooks();
	}

	/**
	 * Runs hooks.
	 *
	 * @since 0.1.0
	 */
	function hooks() {
		add_action( 'init',                                           [ $this, 'add_endpoint' ] );
		add_filter( 'query_vars',                                     [ $this, 'add_query_vars' ], 0 );
		add_filter( 'woocommerce_account_menu_items',                 [ $this, 'add_menu_item' ] );
		add_action( "woocommerce_account_{$this->endpoint}_endpoint", [ $this, 'add_content' ] );
	}

	/**
	 * Adds account nav endpoint.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	function add_endpoint() {
		add_rewrite_endpoint( $this->endpoint, EP_ROOT | EP_PAGES );
	}

	/**
	 * Adds account nav endpoint.
	 *
	 * @since 0.1.0
	 *
	 * @param array $vars The existing query vars.
	 *
	 * @return array
	 */
	function add_query_vars( $vars ) {
		$vars[] = $this->endpoint;
		return $vars;
	}

	/**
	 * Adds account menu item.
	 *
	 * @since 0.1.0
	 *
	 * @param array $items The existing items.
	 *
	 * @return array
	 */
	function add_menu_item( $items ) {
		$locations = mailocation_get_user_locations();
		if ( ! $locations ) {
			return $items;
		}
		$logout = false;
		if ( isset( $items['customer-logout'] ) ) {
			$logout = $items['customer-logout'];
			unset( $items['customer-logout'] );
		}
		$items[ $this->endpoint ] = mailocations_get_plural();
		if ( $logout ) {
			$items['customer-logout'] = $logout;
		}
		return $items;
	}

	/**
	 * Adds account menu content.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	function add_content() {
		echo mailocations_get_locations_table();
	}
}

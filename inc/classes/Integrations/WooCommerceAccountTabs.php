<?php

declare(strict_types=1);

namespace Mai\Locations\Integrations;

use Mai\Locations\Cache;

// Prevent direct file access.
defined( 'ABSPATH' ) || die;

/**
 * Adds a Locations tab to the WooCommerce account pages.
 *
 * Only safe to instantiate when WooCommerce is active: add_acf_form_head() calls
 * is_account_page() and is_wc_endpoint_url(), which WooCommerce defines. The plugin does not
 * instantiate this today; the call sits commented out in the bootstrap.
 *
 * Was Mai_Locations_WooCommerce_Account_Tabs in classes/class-woocommerce-account-tabs.php.
 * That name still works, via inc/aliases.php.
 *
 * @since TBD
 */
class WooCommerceAccountTabs {

	/**
	 * Gets it started.
	 *
	 * @since TBD
	 */
	public function __construct() {
		// Hooks.
		$this->hooks();
	}

	/**
	 * Gets the tabs, endpoint to label.
	 *
	 * Worked out once per request and held in the plugin's cache.
	 *
	 * @since TBD
	 *
	 * @return array<string, string>
	 */
	public function get_tabs() {
		// Return if cached.
		if ( Cache::has( 'woocommerce_account_tabs' ) ) {
			return Cache::get( 'woocommerce_account_tabs' );
		}

		// Default tabs.
		$tabs = [ mailocations_get_base() => mailocations_get_plural() ];

		// Set filterable tabs.
		$tabs = apply_filters( 'mailocations_woocommerce_account_tabs', $tabs );

		return Cache::set( 'woocommerce_account_tabs', $tabs );
	}

	/**
	 * Runs hooks.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	public function hooks(): void {
		add_action( 'init',                           [ $this, 'add_endpoint' ] );
		add_filter( 'query_vars',                     [ $this, 'add_query_vars' ], 0 );
		add_filter( 'woocommerce_get_query_vars',     [ $this, 'add_woo_query_vars' ] );
		add_filter( 'mai_template-parts_config',      [ $this, 'add_content_areas' ] );
		add_filter( 'woocommerce_account_menu_items', [ $this, 'add_menu_items' ] );
		add_action( 'get_header',                     [ $this, 'add_acf_form_head' ] );
	}

	/**
	 * Adds the account nav endpoints.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	public function add_endpoint(): void {
		foreach ( $this->get_tabs() as $endpoint => $label ) {
			// Add endpoint.
			add_rewrite_endpoint( $endpoint, EP_ROOT | EP_PAGES );

			// Add action.
			add_action( "woocommerce_account_{$endpoint}_endpoint", function () use ( $endpoint ) {
				// Add action hook.
				do_action( "mailocations_account_{$endpoint}_content" );
			} );
		}
	}

	/**
	 * Adds query vars.
	 *
	 * @since TBD
	 *
	 * @param array<int, string> $vars The existing query vars.
	 *
	 * @return array<int, string>
	 */
	public function add_query_vars( $vars ) {
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
	 * @param array<string, string> $vars The existing query vars.
	 *
	 * @return array<string, string>
	 */
	public function add_woo_query_vars( $vars ) {
		foreach ( $this->get_tabs() as $endpoint => $label ) {
			$vars[ $endpoint ] = $endpoint;
		}

		return $vars;
	}

	/**
	 * Adds a content area for Mai Theme v2.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $config The existing config array.
	 *
	 * @return array<string, mixed>
	 */
	public function add_content_areas( $config ) {
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
	 * Adds the account menu item. Skipped on Mai Theme v2 when the content area has no content.
	 *
	 * @since TBD
	 *
	 * @param array<string, string> $items The existing items.
	 *
	 * @return array<string, string>
	 */
	public function add_menu_items( $items ) {
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
	 * Loads the ACF form head where the account page needs it.
	 *
	 * Checks WooCommerce is there before asking it anything. Until September 16, 2026 this called
	 * is_account_page() outright, so the class fataled on any site without WooCommerce.
	 *
	 * The class is still not loaded by the plugin: the hook in mai-locations.php is commented out
	 * until its menu items can be added conditionally. Nothing here changes that.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	public function add_acf_form_head(): void {
		if ( ! function_exists( 'is_account_page' ) || ! function_exists( 'is_wc_endpoint_url' ) ) {
			return;
		}

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
	 * Inserts a key/value pair before a specific key. Appends when the key is not there.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $array The array to insert into.
	 * @param string               $key   The key to insert before.
	 * @param array<string, mixed> $new   What to insert.
	 *
	 * @return array<string, mixed>
	 */
	public function insert_before( array $array, $key, array $new ) {
		$keys  = array_keys( $array );
		$index = array_search( $key, $keys );
		$pos   = $index !== false ? $index : count( $array ); // If key doesn't exist, insert at the end.

		return array_merge( array_slice( $array, 0, $pos ), $new, array_slice( $array, $pos ) );
	}

	/**
	 * Inserts a key/value pair after a specific key. Appends when the key is not there.
	 *
	 * @since TBD
	 *
	 * @param array<string, mixed> $array The array to insert into.
	 * @param string               $key   The key to insert after.
	 * @param array<string, mixed> $new   What to insert.
	 *
	 * @return array<string, mixed>
	 */
	public function insert_after( array $array, $key, array $new ) {
		$keys  = array_keys( $array );
		$index = array_search( $key, $keys );
		$pos   = false === $index ? count( $array ) : $index + 1;

		return array_merge( array_slice( $array, 0, $pos ), $new, array_slice( $array, $pos ) );
	}
}

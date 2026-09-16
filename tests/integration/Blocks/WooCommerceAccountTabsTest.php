<?php

declare(strict_types=1);

namespace Mai\Locations\Tests\Integration\Blocks;

use Mai\Locations\Tests\TestCase;

/**
 * The class is loaded but never instantiated by the plugin. Constructing it only adds hooks,
 * which the test case restores after each test, so it is safe without WooCommerce.
 */
final class WooCommerceAccountTabsTest extends TestCase {

	private \Mai_Locations_WooCommerce_Account_Tabs $tabs;

	public function set_up(): void {
		parent::set_up();
		$this->tabs = new \Mai_Locations_WooCommerce_Account_Tabs();
	}

	public function test_plugin_does_not_instantiate_it(): void {
		global $wp_filter;

		$instances = [];
		foreach ( $wp_filter['woocommerce_account_menu_items']->callbacks as $callbacks ) {
			foreach ( $callbacks as $callback ) {
				if ( is_array( $callback['function'] ) && $callback['function'][0] instanceof \Mai_Locations_WooCommerce_Account_Tabs ) {
					$instances[] = $callback['function'][0];
				}
			}
		}

		// Only the instance made in set_up() is hooked in.
		$this->assertSame( [ $this->tabs ], $instances );
		$this->assertFalse( function_exists( 'mailocations_woocommerce_account_tab' ) && has_action( 'plugins_loaded', 'mailocations_woocommerce_account_tab' ) );
	}

	public function test_constructor_adds_hooks(): void {
		$this->assertSame( 10, has_action( 'init', [ $this->tabs, 'add_endpoint' ] ) );
		$this->assertSame( 0, has_filter( 'query_vars', [ $this->tabs, 'add_query_vars' ] ) );
		$this->assertSame( 10, has_filter( 'woocommerce_get_query_vars', [ $this->tabs, 'add_woo_query_vars' ] ) );
		$this->assertSame( 10, has_filter( 'mai_template-parts_config', [ $this->tabs, 'add_content_areas' ] ) );
		$this->assertSame( 10, has_filter( 'woocommerce_account_menu_items', [ $this->tabs, 'add_menu_items' ] ) );
		$this->assertSame( 10, has_action( 'get_header', [ $this->tabs, 'add_acf_form_head' ] ) );
	}

	public function test_get_tabs_defaults_to_the_base_and_plural_label(): void {
		// Cached in a static for the process, so the mailocations_woocommerce_account_tabs filter cannot be tested here.
		$this->assertSame( [ 'locations' => 'Locations' ], $this->tabs->get_tabs() );
	}

	public function test_insert_before(): void {
		$array = [ 'a' => 1, 'b' => 2 ];

		$this->assertSame( [ 'a' => 1, 'x' => 9, 'b' => 2 ], $this->tabs->insert_before( $array, 'b', [ 'x' => 9 ] ) );
		$this->assertSame( [ 'x' => 9, 'a' => 1, 'b' => 2 ], $this->tabs->insert_before( $array, 'a', [ 'x' => 9 ] ) );
		$this->assertSame( [ 'a' => 1, 'b' => 2, 'x' => 9 ], $this->tabs->insert_before( $array, 'missing', [ 'x' => 9 ] ) );
		$this->assertSame( [ 'x' => 9 ], $this->tabs->insert_before( [], 'a', [ 'x' => 9 ] ) );
	}

	public function test_insert_before_renumbers_numeric_keys(): void {
		$this->assertSame( [ 0 => 'a', 1 => 'new', 2 => 'b' ], $this->tabs->insert_before( [ 5 => 'a', 6 => 'b' ], 6, [ 'new' ] ) );
	}

	public function test_insert_after(): void {
		$array = [ 'a' => 1, 'b' => 2 ];

		$this->assertSame( [ 'a' => 1, 'x' => 9, 'b' => 2 ], $this->tabs->insert_after( $array, 'a', [ 'x' => 9 ] ) );
		$this->assertSame( [ 'a' => 1, 'b' => 2, 'x' => 9 ], $this->tabs->insert_after( $array, 'b', [ 'x' => 9 ] ) );
		$this->assertSame( [ 'a' => 1, 'b' => 2, 'x' => 9 ], $this->tabs->insert_after( $array, 'missing', [ 'x' => 9 ] ) );
	}

	public function test_add_query_vars(): void {
		$this->assertSame( [ 'existing', 'locations' ], $this->tabs->add_query_vars( [ 'existing' ] ) );
		$this->assertSame( [ 'orders' => 'orders', 'locations' => 'locations' ], $this->tabs->add_woo_query_vars( [ 'orders' => 'orders' ] ) );
	}

	public function test_add_menu_items_goes_before_logout(): void {
		$items = [ 'dashboard' => 'Dashboard', 'customer-logout' => 'Log out' ];

		$this->assertSame( [ 'dashboard' => 'Dashboard', 'locations' => 'Locations', 'customer-logout' => 'Log out' ], $this->tabs->add_menu_items( $items ) );
		$this->assertSame( [ 'dashboard' => 'Dashboard', 'locations' => 'Locations' ], $this->tabs->add_menu_items( [ 'dashboard' => 'Dashboard' ] ) );
	}

	public function test_add_content_areas(): void {
		$this->assertSame(
			[
				'woo-locations' => [
					'hook'   => 'mailocations_account_locations_content',
					'before' => '<div class="mai-locations-woo-locations">',
					'after'  => '</div>',
				],
			],
			$this->tabs->add_content_areas( [] )
		);
	}

	public function test_add_endpoint_hooks_the_account_endpoint_content(): void {
		$this->tabs->add_endpoint();

		$fired = 0;
		add_action( 'mailocations_account_locations_content', static function () use ( &$fired ): void { $fired++; } );
		do_action( 'woocommerce_account_locations_endpoint' );

		$this->assertSame( 1, $fired );
	}

	public function test_add_acf_form_head_fatals_without_woocommerce(): void {
		$this->expectException( \Error::class );
		// Namespaced since the class moved to Mai\Locations\WooCommerceAccountTabs. Same fatal:
		// PHP names the namespaced attempt when the global function does not exist.
		$this->expectExceptionMessage( 'Call to undefined function Mai\Locations\is_account_page()' );

		$this->tabs->add_acf_form_head();
	}
}

<?php
/**
 * Boots WordPress and the plugin in a fresh PHP process, with filters in place before the plugin
 * loads, then prints one JSON result after a marker line.
 *
 * The plugin caches labels, the base, options and query flags in static variables on its first
 * call, which happens during bootstrap. A filter added inside a normal test is too late, so tests
 * that need those values changed run this script instead. See ScenarioRunner.
 *
 * The scenario arrives as JSON in the MAILOC_REG_SCENARIO environment variable:
 * - options: array returned for get_option( 'mai_locations' ).
 * - returns: hook name => value the filter returns.
 * - merges:  hook name => array merged over the filter's first argument.
 * - probe:   which result to print.
 */

declare(strict_types=1);

$scenario  = json_decode( (string) getenv( 'MAILOC_REG_SCENARIO' ), true ) ?: [];
$tests_dir = dirname( __DIR__, 3 );

require_once $tests_dir . '/vendor/autoload.php';
require_once getenv( 'WP_PHPUNIT__DIR' ) . '/includes/functions.php';

if ( isset( $scenario['options'] ) ) {
	tests_add_filter( 'pre_option_mai_locations', static fn() => $scenario['options'] );
}

foreach ( $scenario['returns'] ?? [] as $hook => $value ) {
	tests_add_filter( $hook, static fn() => $value, 10, 0 );
}

foreach ( $scenario['merges'] ?? [] as $hook => $value ) {
	tests_add_filter( $hook, static fn( $args ) => array_merge( $args, $value ) );
}

require $tests_dir . '/bootstrap.php';

/**
 * Finds the object a plugin class hooked onto a hook.
 */
function mailoc_reg_hooked_instance( string $hook, string $class ): ?object {
	foreach ( $GLOBALS['wp_filter'][ $hook ]->callbacks as $callbacks ) {
		foreach ( $callbacks as $callback ) {
			if ( is_array( $callback['function'] ) && $callback['function'][0] instanceof $class ) {
				return $callback['function'][0];
			}
		}
	}

	return null;
}

$result = [];

switch ( $scenario['probe'] ?? 'content_types' ) {
	case 'content_types':
		$post_type = get_post_type_object( 'mai_location' );
		$taxonomy  = get_taxonomy( 'mai_location_cat' );

		$result = [
			'post_type_labels' => (array) $post_type->labels,
			'post_type_rewrite' => $post_type->rewrite,
			'post_type_public' => $post_type->public,
			'post_type_menu_icon' => $post_type->menu_icon,
			'post_type_has_archive' => $post_type->has_archive,
			'taxonomy_labels' => (array) $taxonomy->labels,
			'taxonomy_rewrite' => $taxonomy->rewrite,
			'taxonomy_hierarchical' => $taxonomy->hierarchical,
			'plural_later_call' => mailocations_get_plural(),
			'singular_later_call' => mailocations_get_singular(),
			'base_later_call' => mailocations_get_base(),
			'location_post_types' => mailocations_get_location_post_types(),
			'options' => mailocations_get_options(),
			'acf_api_with_key' => apply_filters( 'acf/fields/google_map/api', [ 'key' => 'acf-key', 'signature' => 'acf-signature' ] ),
			'acf_api_empty' => apply_filters( 'acf/fields/google_map/api', [] ),
		];
		break;

	case 'main_query':
		$_GET = $scenario['get'] ?? [];

		$GLOBALS['wp_query']->query( $scenario['query'] );

		$result = [
			'is_filtered' => mailocations_is_filtered_locations(),
			'orderby' => $GLOBALS['wp_query']->get( 'orderby' ),
			'order' => $GLOBALS['wp_query']->get( 'order' ),
			'tax_query' => $GLOBALS['wp_query']->get( 'tax_query' ),
			'geo_query' => $GLOBALS['wp_query']->get( 'geo_query' ),
			'no_results_text' => apply_filters( 'genesis_noposts_text', 'Original' ),
		];
		break;

	case 'filtered_cache':
		$_GET  = [];
		$first = mailocations_is_filtered_locations();
		$_GET  = [ 'lat' => '41.08' ];

		$result = [
			'first' => $first,
			'second' => mailocations_is_filtered_locations(),
		];
		break;

	case 'settings_page':
		require_once ABSPATH . 'wp-admin/includes/template.php';

		$settings = mailoc_reg_hooked_instance( 'admin_init', 'Mai_Locations_Settings' );
		$settings->init();

		ob_start();
		$settings->add_content();

		$result = [ 'html' => (string) ob_get_clean() ];
		break;

	case 'upgrade':
		$writes = [];

		add_filter(
			'pre_update_option_mai_locations',
			static function ( $value ) use ( &$writes ) {
				$writes[] = $value;
				return $value;
			}
		);

		mailocations_do_upgrade();
		$after_function = count( $writes );

		mailoc_reg_hooked_instance( 'admin_init', 'Mai_Locations_Upgrade' )->do_upgrade();

		$result = [
			'writes_after_function' => $after_function,
			'writes' => $writes,
		];
		break;
}

echo "\n@@MAILOC_REG@@" . json_encode( $result );

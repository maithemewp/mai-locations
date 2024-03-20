<?php

/**
 * Plugin Name:       Mai Locations
 * Plugin URI:        https://bizbudding.com
 * Description:       A custom post type with info/address/map fields to manage locations.
 * Version:           0.4.0
 * Requires at least: 6.4
 * Author:            BizBudding
 * Author URI:        https://bizbudding.com
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

// Must be at the top of the file.
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

/**
 * Main Mai_Locations_Plugin Class.
 *
 * @since 0.1.0
 */
final class Mai_Locations_Plugin {

	/**
	 * @var   Mai_Locations_Plugin The one true Mai_Locations_Plugin
	 * @since 0.1.0
	 */
	private static $instance;

	/**
	 * Main Mai_Locations_Plugin Instance.
	 *
	 * Insures that only one instance of Mai_Locations_Plugin exists in memory at any one
	 * time. Also prevents needing to define globals all over the place.
	 *
	 * @since   0.1.0
	 * @static  var array $instance
	 * @uses    Mai_Locations_Plugin::setup_constants() Setup the constants needed.
	 * @uses    Mai_Locations_Plugin::includes() Include the required files.
	 * @uses    Mai_Locations_Plugin::hooks() Activate, deactivate, etc.
	 * @see     Mai_Locations_Plugin()
	 * @return  object | Mai_Locations_Plugin The one true Mai_Locations_Plugin
	 */
	public static function instance() {
		if ( ! isset( self::$instance ) ) {
			// Setup the setup.
			self::$instance = new Mai_Locations_Plugin;
			// Methods.
			self::$instance->setup_constants();
			self::$instance->includes();
			self::$instance->hooks();
		}
		return self::$instance;
	}

	/**
	 * Throw error on object clone.
	 *
	 * The whole idea of the singleton design pattern is that there is a single
	 * object therefore, we don't want the object to be cloned.
	 *
	 * @since   0.1.0
	 * @access  protected
	 * @return  void
	 */
	function __clone() {
		// Cloning instances of the class is forbidden.
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'mai-locations' ), '1.0' );
	}

	/**
	 * Disable unserializing of the class.
	 *
	 * @since   0.1.0
	 * @access  protected
	 * @return  void
	 */
	function __wakeup() {
		// Unserializing instances of the class is forbidden.
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'mai-locations' ), '1.0' );
	}

	/**
	 * Setup plugin constants.
	 *
	 * @access  private
	 * @since   0.1.0
	 * @return  void
	 */
	private function setup_constants() {
		// Plugin version.
		if ( ! defined( 'MAI_LOCATIONS_VERSION' ) ) {
			define( 'MAI_LOCATIONS_VERSION', '0.4.0' );
		}

		// Plugin Folder Path.
		if ( ! defined( 'MAI_LOCATIONS_PLUGIN_DIR' ) ) {
			define( 'MAI_LOCATIONS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
		}

		// Plugin Folder URL.
		if ( ! defined( 'MAI_LOCATIONS_PLUGIN_URL' ) ) {
			define( 'MAI_LOCATIONS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
		}

		// Plugin Root File.
		if ( ! defined( 'MAI_LOCATIONS_PLUGIN_FILE' ) ) {
			define( 'MAI_LOCATIONS_PLUGIN_FILE', __FILE__ );
		}

		// Plugin Base Name
		if ( ! defined( 'MAI_LOCATIONS_BASENAME' ) ) {
			define( 'MAI_LOCATIONS_BASENAME', dirname( plugin_basename( __FILE__ ) ) );
		}
	}

	/**
	 * Include required files.
	 *
	 * @access  private
	 * @since   0.1.0
	 * @return  void
	 */
	private function includes() {
		// Dependencies.
		require_once __DIR__ . '/vendor/autoload.php';

		// Includes.
		foreach ( glob( MAI_LOCATIONS_PLUGIN_DIR . 'includes/' . '*.php' ) as $file ) { include $file; }

		// Classes.
		include_once __DIR__ . '/classes/class-geo-query.php';
		include_once __DIR__ . '/classes/class-location-fields.php';
		include_once __DIR__ . '/classes/class-location-form.php'; // Must be before create/edit.
		include_once __DIR__ . '/classes/class-location-form-edit.php';
		include_once __DIR__ . '/classes/class-location-form-listener.php';
		include_once __DIR__ . '/classes/class-location-form-submit.php';
		include_once __DIR__ . '/classes/class-location-google-places-import.php';
		include_once __DIR__ . '/classes/class-location-import.php';
		include_once __DIR__ . '/classes/class-locations-table.php';
		include_once __DIR__ . '/classes/class-settings.php';

		// Blocks.
		include_once __DIR__ . '/blocks/location-submission/block.php';
		include_once __DIR__ . '/blocks/locations-address-search/block.php';
		include_once __DIR__ . '/blocks/locations-count/block.php';
		include_once __DIR__ . '/blocks/locations-filter/block.php';
		include_once __DIR__ . '/blocks/locations-filter-clear/block.php';
		include_once __DIR__ . '/blocks/locations-map/block.php';
		include_once __DIR__ . '/blocks/locations-table/block.php';

		// Instantiate classes.
		new Mai_Locations_Location_Fields;
		new Mai_Locations_Location_Form_Listener;
		new Mai_Locations_Location_Import;

		// Instantiate blocks.
		new Mai_Locations_Location_Submission_Block;
		new Mai_Locations_Locations_Address_Search_Block;
		new Mai_Locations_Locations_Count_Block;
		new Mai_Locations_Locations_Filter_Block;
		new Mai_Locations_Locations_Map_Block;
		new Mai_Locations_Locations_Table_Block;
	}

	/**
	 * Run the hooks.
	 *
	 * @since   0.1.0
	 * @return  void
	 */
	function hooks() {
		add_action( 'plugins_loaded', [ $this, 'updater' ] );
		add_action( 'init',           [ $this, 'register_content_types' ] );

		register_activation_hook( __FILE__, [ $this, 'activate' ] );
		register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );
	}

	/**
	 * Setup the updater.
	 *
	 * composer require yahnis-elsts/plugin-update-checker
	 *
	 * @since 0.1.0
	 *
	 * @uses https://github.com/YahnisElsts/plugin-update-checker/
	 *
	 * @return void
	 */
	function updater() {
		// Bail if plugin updater is not loaded.
		if ( ! class_exists( 'YahnisElsts\PluginUpdateChecker\v5\PucFactory' ) ) {
			return;
		}

		// Setup the updater.
		$updater = PucFactory::buildUpdateChecker( 'https://github.com/maithemewp/mai-locations/', __FILE__, 'mai-locations' );

		// Maybe set github api token.
		if ( defined( 'MAI_GITHUB_API_TOKEN' ) ) {
			$updater->setAuthentication( MAI_GITHUB_API_TOKEN );
		}

		// Add icons for Dashboard > Updates screen.
		if ( function_exists( 'mai_get_updater_icons' ) && $icons = mai_get_updater_icons() ) {
			$updater->addResultFilter(
				function ( $info ) use ( $icons ) {
					$info->icons = $icons;
					return $info;
				}
			);
		}
	}

	/**
	 * Register content types.
	 *
	 * @return  void
	 */
	function register_content_types() {

		/***********************
		 *  Custom Post Types  *
		 ***********************/

		$plural   = mailocations_get_plural();
		$singular = mailocations_get_singular();
		$base     = mailocations_get_base();

		/**
		 * Registers custom post type.
		 *
		 * @return void
		 */
		register_post_type( 'mai_location', apply_filters( 'mai_location_post_type_args',
			[
				'exclude_from_search' => false,
				'has_archive'         => true,
				'hierarchical'        => false,
				'labels'              => [
					'name'               => $plural,
					'singular_name'      => $singular,
					'menu_name'          => $plural,
					'name_admin_bar'     => $singular,
					'add_new'            => __( 'Add New', 'mai-user-post' ),
					'add_new_item'       => __( 'Add New', 'mai-user-post' ),
					'new_item'           => __( 'New', 'mai-user-post' ) . ' ' . $singular,
					'edit_item'          => __( 'Edit', 'mai-user-post' ) . ' ' . $singular,
					'view_item'          => __( 'View', 'mai-user-post' ) . ' ' . $singular,
					'all_items'          => __( 'All', 'mai-user-post' ) . ' ' . $plural,
					'search_items'       => __( 'Search', 'mai-user-post' ) . ' ' . $plural,
					'parent_item_colon'  => __( 'Parent', 'mai-user-post' ) . ' ' . $plural,
					'not_found'          => __( 'No', 'mai-user-post' ) . ' ' . $plural . ' ' . __( 'found', 'mai-user-post' ),
					'not_found_in_trash' => __( 'No', 'mai-user-post' ) . ' ' . $plural . ' ' . __( 'found in trash', 'mai-user-post' ),
				],
				'menu_icon'          => 'dashicons-location',
				'public'             => true,
				'publicly_queryable' => true,
				'show_in_menu'       => true,
				'show_in_nav_menus'  => true,
				'show_in_rest'       => true,
				'show_ui'            => true,
				'rewrite'            => [ 'slug' => $base, 'with_front' => false ],
				'supports'           => [ 'title', 'editor', 'excerpt', 'author', 'thumbnail', 'page-attributes', 'genesis-cpt-archives-settings', 'mai-archive-settings', 'mai-single-settings' ],
				'taxonomies'         => [ 'mai_location_cat' ],
			]
		) );

		/***********************
		 *  Custom Taxonomies  *
		 ***********************/

		$cat_plural   = apply_filters( 'mailocations_taxonomy_plural', __( 'Location Categories', 'mai-locations' ) );
		$cat_singular = apply_filters( 'mailocations_taxonomy_singular', __( 'Location Category', 'mai-locations' ) );
		$cat_base     = mailocations_get_option( 'category_base' );
		$cat_base     = apply_filters( 'mailocations_taxonomy_base', $cat_base );
		$cat_base     = $cat_base ? sanitize_title_with_dashes( $cat_base ) : 'location-category';

		/**
		 * Registers custom taxonomy.
		 *
		 * @return void
		 */
		register_taxonomy( 'mai_location_cat', [ 'mai_location' ], apply_filters( 'mai_location_cat_args',
			[
				'hierarchical' => true,
				'labels'       => [
					'name'          => $cat_plural,
					'singular_name' => $cat_singular,
					'menu_name'     => $cat_plural,
				],
				'meta_box_cb'                => false,
				'public'                     => true,
				'show_admin_column'          => true,
				'show_in_nav_menus'          => true,
				'show_in_rest'               => true,
				'show_tagcloud'              => true,
				'show_ui'                    => true,
				'rewrite'                    => [ 'slug' => $cat_base, 'with_front' => false ],
			]
		) );
	}

	/**
	 * Plugin activation.
	 *
	 * @return void
	 */
	function activate() {
		$this->register_content_types();
		flush_rewrite_rules();
	}
}

/**
 * The main function for that returns Mai_Locations_Plugin
 *
 * The main function responsible for returning the one true Mai_Locations_Plugin
 * Instance to functions everywhere.
 *
 * Use this function like you would a global variable, except without needing
 * to declare the global.
 *
 * Example: <?php $plugin = Mai_Locations_Plugin(); ?>
 *
 * @since 0.1.0
 *
 * @return object|Mai_Locations_Plugin The one true Mai_Locations_Plugin Instance.
 */
function mai_locations_plugin() {
	return Mai_Locations_Plugin::instance();
}

// Get Mai_Locations_Plugin Running.
mai_locations_plugin();

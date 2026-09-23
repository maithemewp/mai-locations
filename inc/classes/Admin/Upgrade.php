<?php

declare(strict_types=1);

namespace Mai\Locations\Admin;

// Prevent direct file access.
defined( 'ABSPATH' ) || die;

/**
 * Runs version upgrades and migrates the old ACF option values.
 *
 * Was Mai_Locations_Upgrade in classes/class-upgrade.php. That name still works, via
 * inc/aliases.php.
 *
 * inc/upgrade.php holds the old global function names, which now call this class rather than
 * repeating it. Only this class is hooked, so the routines run once.
 *
 * @since TBD
 */
class Upgrade {

	/**
	 * Construct the class.
	 */
	public function __construct() {
		$this->hooks();
	}

	/**
	 * Add hooks.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	public function hooks(): void {
		add_action( 'admin_init',                [ $this, 'do_upgrade' ] );
		add_action( 'upgrader_process_complete', [ $this, 'upgrade_completed' ], 10, 2 );
	}

	/**
	 * Runs setting upgrades during a plugin update.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	public function do_upgrade(): void {
		self::run();
	}

	/**
	 * The upgrade itself, callable without an instance.
	 *
	 * The global mailocations_do_upgrade() calls this, so the two public entry points share one
	 * implementation instead of holding a copy each. Only this class is hooked.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	public static function run(): void {
		$version = MAI_LOCATIONS_VERSION;

		// A site that started on 0.4.0 or earlier kept its labels and base in ACF option rows and
		// has no mai_locations option. The migration used to live only in run_completed(), which
		// fires during the update request while the old code is still loaded, so it never ran. The
		// version_first write below then created the option and the migration bailed forever:
		// the site quietly fell back to "Locations" and /locations/. It runs here first now.
		// Found September 23, 2026, on pbd.heritagewebsites.com, still on 0.4.0 with "Providers".
		$migrated = self::migrate_acf_options();

		$version_db = mailocations_get_option( 'version_db' );

		// Set first version.
		if ( ! mailocations_get_option( 'version_first' ) ) {
			mailocations_update_option( 'version_first', $version );
		}

		// Return early if current.
		if ( $version === $version_db ) {
			return;
		}

		// Only run upgrades on a site that already had the plugin. version_db has only existed
		// since 2023, so a site from before then has none, and would otherwise be taken for a
		// fresh install. Its ACF option rows, or any location at all, give it away.
		if ( $version_db || $migrated || self::has_locations() ) {

			if ( ! $version_db || version_compare( $version_db, '2.0.0', '<' ) ) {
				self::upgrade_2_0_0();
			}
		}

		// Update database version after upgrade.
		mailocations_update_option( 'version_db', $version );
	}

	/**
	 * Keeps an existing site publishing the way it already did.
	 *
	 * Before 2.0.0, any front-end save of an unpublished location published it, on every site,
	 * with nothing to switch it off. 2.0.0 replaces that with an opt-in Publish switch and a
	 * setting, which defaults to off so a new site moderates by default.
	 *
	 * Defaulting an existing site to off would quietly take something away from owners who
	 * publish their own listings today. So an upgrade turns it on, and the site decides for
	 * itself whether to untick it. The release notes say so.
	 *
	 * A fresh install never reaches this, because it has no version_db.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	public static function upgrade_2_0_0(): void {
		mailocations_update_option( 'owners_can_publish', true );
	}

	/**
	 * Placeholder for the 0.7.0 upgrade.
	 *
	 * @since TBD
	 *
	 * @return void
	 */
	public function upgrade_0_7_0(): void {
		// TODO.
	}

	/**
	 * Runs when WordPress finishes its upgrade process, and migrates old ACF option values to
	 * the new key. It checks each updated plugin to see if ours is among them.
	 *
	 * This plugin was not used in many places before the update that added this, so the code is
	 * probably safe to remove once enough time has passed.
	 *
	 * @since TBD
	 *
	 * @param mixed                $upgrader_object The upgrader.
	 * @param array<string, mixed> $options         The upgrade options.
	 *
	 * @return void
	 */
	public function upgrade_completed( $upgrader_object, $options ): void {
		self::run_completed( $upgrader_object, $options );
	}

	/**
	 * The migration itself, callable without an instance. See run() above.
	 *
	 * @since TBD
	 *
	 * @param mixed                $upgrader_object The upgrader.
	 * @param array<string, mixed> $options         The upgrade options.
	 *
	 * @return void
	 */
	public static function run_completed( $upgrader_object, $options ): void {
		self::migrate_acf_options();
	}

	/**
	 * Marks a brand-new install as current, so run() never takes it for an old site.
	 *
	 * run() treats a site with no version_db as an upgrade when it finds old ACF option rows or
	 * any location, because a site from before 2023 has no version_db. But a fresh install can
	 * have locations before any Dashboard page loads: activate and import from the command line,
	 * and WP-CLI never fires admin_init. That site would have been upgraded, and owner publishing
	 * switched on without anyone choosing it. Stamping it on activation settles it first.
	 *
	 * Only a site with no settings, no old ACF rows and no locations counts as new. Re-activating
	 * a real old site leaves it alone, so run() still upgrades it. The location check reads the
	 * database directly: during activation the post type list can still be cached empty.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	public static function mark_fresh_install(): void {
		global $wpdb;

		if ( false !== get_option( 'mai_locations' ) ) {
			return;
		}

		foreach ( [ 'options_location_label_plural', 'options_location_label_singular', 'options_location_base' ] as $old ) {
			if ( false !== get_option( $old ) ) {
				return;
			}
		}

		$has_locations = (bool) $wpdb->get_var( $wpdb->prepare( "SELECT 1 FROM {$wpdb->posts} WHERE post_type = %s LIMIT 1", 'mai_location' ) );

		if ( $has_locations ) {
			return;
		}

		mailocations_update_option( 'version_first', MAI_LOCATIONS_VERSION );
		mailocations_update_option( 'version_db', MAI_LOCATIONS_VERSION );
	}

	/**
	 * Moves the labels and base a pre-1.0 site kept in ACF option rows into mai_locations.
	 *
	 * Writes first and deletes the old rows only once the write is confirmed. It used to delete
	 * them before writing, so a failed write lost the site's settings for good.
	 *
	 * @since 2.0.0
	 *
	 * @return bool Whether anything was migrated.
	 */
	public static function migrate_acf_options(): bool {
		// Bail if we already have an option value.
		if ( get_option( 'mai_locations' ) ) {
			return false;
		}

		$values  = [];
		$migrate = [
			'options_location_label_plural'   => 'label_plural',
			'options_location_label_singular' => 'label_singular',
			'options_location_base'           => 'base',
		];

		foreach ( $migrate as $old => $new ) {
			$value = get_option( $old, false );

			if ( ! $value ) {
				continue;
			}

			$values[ $new ] = $value;
		}

		if ( ! $values ) {
			return false;
		}

		// Fresh, not cached: nothing has been saved yet and a cached copy could be stale.
		$options = mailocations_get_options( true );

		foreach ( $values as $key => $value ) {
			$options[ $key ] = $value;
		}

		// Clean the migrated values, which came from ACF option rows and were saved exactly as
		// they were, so a base of "Our Places!" went in as-is. Fixed September 16, 2026.
		$written = update_option( 'mai_locations', mailocations_sanitize_options( $options ) );
		mailocations_get_options( true );

		// Only once the new values are really there. The option did not exist, so a write that
		// landed always reports true.
		if ( ! $written ) {
			return false;
		}

		foreach ( $migrate as $old => $new ) {
			if ( isset( $values[ $new ] ) ) {
				delete_option( $old );
				delete_option( '_' . $old );
			}
		}

		return true;
	}

	/**
	 * Whether any location exists, in any status, which only a site that already used the
	 * plugin can have.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	private static function has_locations(): bool {
		foreach ( array_keys( mailocations_get_location_post_types() ) as $post_type ) {
			$ids = get_posts(
				[
					'post_type'              => $post_type,
					'post_status'            => 'any',
					'numberposts'            => 1,
					'fields'                 => 'ids',
					'no_found_rows'          => true,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
					'suppress_filters'       => true,
				]
			);

			if ( $ids ) {
				return true;
			}
		}

		return false;
	}
}

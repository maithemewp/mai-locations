<?php

declare(strict_types=1);

namespace Mai\Locations;

// Prevent direct file access.
defined( 'ABSPATH' ) || die;

/**
 * The plugin's per-request cache.
 *
 * Most of the plugin's lookups are worked out once and reused for the rest of the request:
 * labels, the URL base, the option array, the field lists, the post type and taxonomy lists,
 * the query defaults. They used to live in `static` variables inside each function, which meant
 * a filter added after the first call did nothing, and a test could not get a second answer out
 * of the same process. Holding them here instead makes every one of them resettable through a
 * single flush, without adding a $reset argument to twenty public functions.
 *
 * @internal
 *
 * This class is internal. Nothing outside the plugin should call it, and it is not on the list
 * of public names other sites may depend on.
 *
 * @since 2.0.0
 */
final class Cache {
	/**
	 * The cached values, keyed by name.
	 *
	 * @since 2.0.0
	 *
	 * @var array<string, mixed>
	 */
	private static array $store = [];

	/**
	 * Whether a value has been worked out already.
	 *
	 * Checked rather than comparing against null, so a function whose answer really is null
	 * still only works it out once.
	 *
	 * @since 2.0.0
	 *
	 * @param string $key The cache key.
	 *
	 * @return bool
	 */
	public static function has( string $key ): bool {
		return array_key_exists( $key, self::$store );
	}

	/**
	 * Gets a cached value.
	 *
	 * @since 2.0.0
	 *
	 * @param string $key The cache key.
	 *
	 * @return mixed Null if nothing is cached under that key.
	 */
	public static function get( string $key ) {
		return self::$store[ $key ] ?? null;
	}

	/**
	 * Caches a value and hands it straight back, so a function can end on one line.
	 *
	 * @since 2.0.0
	 *
	 * @param string $key   The cache key.
	 * @param mixed  $value The value to cache.
	 *
	 * @return mixed The value, unchanged.
	 */
	public static function set( string $key, $value ) {
		self::$store[ $key ] = $value;

		return $value;
	}

	/**
	 * Drops one cached value.
	 *
	 * @since 2.0.0
	 *
	 * @param string $key The cache key.
	 *
	 * @return void
	 */
	public static function forget( string $key ): void {
		unset( self::$store[ $key ] );
	}

	/**
	 * Drops every cached value whose key starts with this prefix.
	 *
	 * For the keys that carry a variable in them, such as one per post type.
	 *
	 * @since 2.0.0
	 *
	 * @param string $prefix The start of the keys to drop.
	 *
	 * @return void
	 */
	public static function forget_prefixed( string $prefix ): void {
		foreach ( array_keys( self::$store ) as $key ) {
			if ( str_starts_with( $key, $prefix ) ) {
				unset( self::$store[ $key ] );
			}
		}
	}

	/**
	 * Empties the cache. The test suite calls this between tests, so one test's saved option or
	 * added filter cannot outlive the database rollback.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	public static function flush(): void {
		self::$store = [];
	}
}

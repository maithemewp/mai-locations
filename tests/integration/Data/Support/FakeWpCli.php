<?php
/**
 * Stand-in for WP-CLI, which the suite does not load.
 *
 * Records every call so tests can assert what a command printed. error() throws, because the
 * real one exits.
 */

declare(strict_types=1);

if ( ! class_exists( 'WP_CLI' ) ) {
	// phpcs:ignore
	class WP_CLI {

		/**
		 * @var array<int, array{0: string, 1: mixed}>
		 */
		public static array $calls = [];

		public static function reset(): void {
			self::$calls = [];
		}

		public static function log( mixed $message ): void {
			self::$calls[] = [ 'log', $message ];
		}

		public static function line( mixed $message = '' ): void {
			self::$calls[] = [ 'line', $message ];
		}

		public static function success( mixed $message ): void {
			self::$calls[] = [ 'success', $message ];
		}

		public static function warning( mixed $message ): void {
			self::$calls[] = [ 'warning', $message ];
		}

		public static function error( mixed $message ): void {
			self::$calls[] = [ 'error', $message ];

			throw new RuntimeException( (string) $message );
		}

		/**
		 * @param array<string, mixed> $args
		 */
		public static function add_command( string $name, mixed $callable, array $args = [] ): void {
			self::$calls[] = [ 'add_command', [ $name, $callable ] ];
		}
	}
}

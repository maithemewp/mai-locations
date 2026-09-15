<?php

declare(strict_types=1);

namespace Mai\Locations\Tests\Integration\Data\Support;

/**
 * Lets a test pin a PHP warning or deprecation instead of PHPUnit turning it into a failure.
 */
trait CapturesErrors {

	/**
	 * Runs the callback and collects the messages of errors of the given types.
	 *
	 * Other error types go to the previous handler (PHPUnit's), so they still fail the test.
	 * Errors silenced with @ are left to PHP and not collected.
	 *
	 * @param callable(): mixed $callback
	 *
	 * @return array{0: mixed, 1: list<string>} The callback's return value and the messages.
	 */
	protected function capture_errors( callable $callback, int $types = E_WARNING ): array {
		$messages = [];
		$previous = null;

		$previous = set_error_handler(
			static function ( int $errno, string $errstr, string $errfile = '', int $errline = 0 ) use ( &$messages, &$previous, $types ): bool {
				if ( ! ( $errno & $types ) ) {
					return is_callable( $previous ) ? (bool) $previous( $errno, $errstr, $errfile, $errline ) : false;
				}

				if ( ! ( error_reporting() & $errno ) ) {
					return false;
				}

				$messages[] = $errstr;

				return true;
			}
		);

		try {
			$result = $callback();
		} finally {
			restore_error_handler();
		}

		return [ $result, $messages ];
	}
}

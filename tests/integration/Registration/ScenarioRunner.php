<?php

declare(strict_types=1);

namespace Mai\Locations\Tests\Integration\Registration;

/**
 * Runs fixtures/boot-scenario.php in a fresh PHP process and returns its decoded result.
 *
 * Used where the plugin's static caches make a filter or option added inside a test too late.
 * The child reuses this run's test database without reinstalling it.
 */
trait ScenarioRunner {

	/**
	 * @param array<string, mixed> $scenario See fixtures/boot-scenario.php.
	 *
	 * @return array<string, mixed>
	 */
	private function run_scenario( array $scenario ): array {
		$env = array_merge(
			getenv(),
			[
				'WP_TESTS_SKIP_INSTALL' => '1',
				'MAILOC_REG_SCENARIO'   => (string) json_encode( $scenario ),
			]
		);

		$process = proc_open(
			[ PHP_BINARY, __DIR__ . '/fixtures/boot-scenario.php' ],
			[ 1 => [ 'pipe', 'w' ], 2 => [ 'pipe', 'w' ] ],
			$pipes,
			null,
			$env
		);

		$this->assertIsResource( $process );

		$stdout = (string) stream_get_contents( $pipes[1] );
		$stderr = (string) stream_get_contents( $pipes[2] );

		fclose( $pipes[1] );
		fclose( $pipes[2] );
		proc_close( $process );

		$marker = strrpos( $stdout, '@@MAILOC_REG@@' );

		$this->assertNotFalse( $marker, "Scenario process gave no result.\nSTDOUT:\n{$stdout}\nSTDERR:\n{$stderr}" );

		return json_decode( substr( $stdout, $marker + strlen( '@@MAILOC_REG@@' ) ), true );
	}
}

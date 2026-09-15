<?php

declare(strict_types=1);

namespace Mai\Locations\Tests\Integration\Data\Support;

use RuntimeException;

/**
 * Thrown from the wp_redirect filter so code that redirects and then exits can be tested.
 */
final class RedirectException extends RuntimeException {

	public function __construct( public readonly string $location ) {
		parent::__construct( $location );
	}
}

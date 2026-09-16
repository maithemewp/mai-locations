<?php

declare(strict_types=1);

// Prevent direct file access.
defined( 'ABSPATH' ) || die;

/**
 * Old class names, kept working while the plugin moves into namespaces.
 *
 * The plugin runs on many sites and any of them may reference these names, so every class
 * that moves under Mai\Locations\ leaves its old global name here. A name only disappears as
 * a deliberate, changelogged decision. tests/integration/PublicApi/PublicNamesTest.php fails
 * if one goes missing by accident.
 */
$mailocations_aliases = [
	'Mai_Locations_Block_Bindings' => Mai\Locations\BlockBindings::class,
];

foreach ( $mailocations_aliases as $mailocations_old => $mailocations_new ) {
	if ( ! class_exists( $mailocations_old, false ) ) {
		class_alias( $mailocations_new, $mailocations_old );
	}
}

unset( $mailocations_aliases, $mailocations_old, $mailocations_new );

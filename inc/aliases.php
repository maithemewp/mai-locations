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
	'Mai_Geo_Query'               => Mai\Locations\GeoQuery::class,
	'Mai_Locations_CLI'           => Mai\Locations\CLI::class,
	'Mai_Locations_Location_Fields'      => Mai\Locations\LocationFields::class,
	'Mai_Locations_Location_Form'        => Mai\Locations\LocationForm::class,
	'Mai_Locations_Location_Import'      => Mai\Locations\LocationImport::class,
	'Mai_Locations_Location_Form_Edit'   => Mai\Locations\LocationFormEdit::class,
	'Mai_Locations_Location_Form_Listener' => Mai\Locations\LocationFormListener::class,
	'Mai_Locations_Location_Form_Submit' => Mai\Locations\LocationFormSubmit::class,
	'Mai_Locations_Locations_Table' => Mai\Locations\LocationsTable::class,
	'Mai_Locations_Queries'       => Mai\Locations\Queries::class,
	'Mai_Locations_Scripts'       => Mai\Locations\Scripts::class,
	'Mai_Locations_Settings'      => Mai\Locations\Settings::class,
	'Mai_Locations_Upgrade'       => Mai\Locations\Upgrade::class,
	'Mai_Locations_WooCommerce_Account_Tabs' => Mai\Locations\WooCommerceAccountTabs::class,
];

foreach ( $mailocations_aliases as $mailocations_old => $mailocations_new ) {
	if ( ! class_exists( $mailocations_old, false ) ) {
		class_alias( $mailocations_new, $mailocations_old );
	}
}

unset( $mailocations_aliases, $mailocations_old, $mailocations_new );

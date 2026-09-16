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
	'Mai_Locations_Block_Bindings' => Mai\Locations\Display\BlockBindings::class,
	'Mai_Geo_Query'               => Mai\Locations\Query\GeoQuery::class,
	'Mai_Locations_Address_Search_Block' => Mai\Locations\Blocks\AddressSearchBlock::class,
	'Mai_Locations_Count_Block'          => Mai\Locations\Blocks\CountBlock::class,
	'Mai_Locations_Filter_Block'         => Mai\Locations\Blocks\FilterBlock::class,
	'Mai_Locations_Filter_Clear_Block'   => Mai\Locations\Blocks\FilterClearBlock::class,
	'Mai_Locations_Filter_Submit_Block'  => Mai\Locations\Blocks\FilterSubmitBlock::class,
	'Mai_Locations_Filters_Block'        => Mai\Locations\Blocks\FiltersBlock::class,
	'Mai_Locations_Map_Block'            => Mai\Locations\Blocks\MapBlock::class,
	'Mai_Locations_Submission_Block'     => Mai\Locations\Blocks\SubmissionBlock::class,
	'Mai_Locations_Table_Block'          => Mai\Locations\Blocks\TableBlock::class,
	'Mai_Locations_CLI'           => Mai\Locations\Cli\CLI::class,
	'Mai_Locations_Location_Fields'      => Mai\Locations\Fields\LocationFields::class,
	'Mai_Locations_Location_Form'        => Mai\Locations\Forms\LocationForm::class,
	'Mai_Locations_Location_Import'      => Mai\Locations\Admin\LocationImport::class,
	'Mai_Locations_Location_Form_Edit'   => Mai\Locations\Forms\LocationFormEdit::class,
	'Mai_Locations_Location_Form_Listener' => Mai\Locations\Forms\LocationFormListener::class,
	'Mai_Locations_Location_Form_Submit' => Mai\Locations\Forms\LocationFormSubmit::class,
	'Mai_Locations_Locations_Table' => Mai\Locations\Display\LocationsTable::class,
	'Mai_Locations_Queries'       => Mai\Locations\Query\Queries::class,
	'Mai_Locations_Scripts'       => Mai\Locations\Display\Scripts::class,
	'Mai_Locations_Settings'      => Mai\Locations\Admin\Settings::class,
	'Mai_Locations_Upgrade'       => Mai\Locations\Admin\Upgrade::class,
	'Mai_Locations_WooCommerce_Account_Tabs' => Mai\Locations\Integrations\WooCommerceAccountTabs::class,
];

foreach ( $mailocations_aliases as $mailocations_old => $mailocations_new ) {
	if ( ! class_exists( $mailocations_old, false ) ) {
		class_alias( $mailocations_new, $mailocations_old );
	}
}

unset( $mailocations_aliases, $mailocations_old, $mailocations_new );

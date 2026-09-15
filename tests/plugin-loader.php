<?php
/**
 * Loads ACF Pro and the whole plugin on muplugins_loaded, the way a real site does.
 *
 * ACF Pro is not composer-installable without a licence key, so the suite loads a copy from
 * disk. Set MAI_LOCATIONS_ACF_DIR to point elsewhere. The default is the copy mai-engine
 * installs, which is what every Mai site runs.
 */

$acf_dir = getenv( 'MAI_LOCATIONS_ACF_DIR' ) ?: getenv( 'HOME' ) . '/Plugins/mai-engine/vendor/wpengine/advanced-custom-fields-pro';

if ( ! is_readable( $acf_dir . '/acf.php' ) ) {
	fwrite( STDERR, "ACF Pro not found at {$acf_dir}. Set MAI_LOCATIONS_ACF_DIR to a folder containing acf.php.\n" );
	exit( 1 );
}

require_once $acf_dir . '/acf.php';
require_once dirname( __DIR__ ) . '/mai-locations.php';

# Changelog

## 1.1.0 (4/8/26)
* Added: Google Map ID setting for advanced markers (required for vector maps).
* Changed: Migrated Places Autocomplete to PlaceAutocompleteElement (new Google Maps API).
* Changed: Replaced Laravel Mix with @wordpress/scripts for build tooling.
* Changed: JS assets now output to `build/` with content-hash versioning via `.asset.php`.
* Changed: Bumped ACF block apiVersion to 3 for all blocks.
* Updated: MarkerClusterer library from v2.1.4 to v2.6.2 (fixes gmp-click deprecation).
* Updated: Composer dependencies (libphonenumber v9.0, plugin-update-checker v5.6).
* Fixed: Marker click events now use gmp-click for AdvancedMarkerElement compatibility.

## 1.0.0 (12/5/24)
* Major rewrite adding Google Maps integration, location finder, a ton of blocks, etc.

## 0.5.0 (6/8/22)
* Added: `[mai_location_phone]`, `[mai_location_url]`, and `[mai_location_email]` shortcodes.

## 0.4.0 (3/9/22)
* Added: Support for adding a featured image via the front end location edit form.
* Changed: Field names are now humnan readable instead of auto-generated key.

## 0.3.0 (7/23/21)
* Added: Default text if no results are available for a user's location table.
* Changed: Post status now forces to `publish` when saving the location edit form on the front end.
* Changed: Helper function `mailocations_create_location_from_woocommerce_user()` function now accepts post args.

## 0.2.1 (6/28/21)
* Fixed: Location category slug wasn't using readable name.
* Fixed: Don’t require args in address helper function.

## 0.2.0 (4/15/21)
* Added: New mailocations_create_location_from_woocommerce_user() helper function to create a location programmatically from WooCommerce user data.

## 0.1.0 (4/12/21)
* Initial release

# Changelog

## Unreleased
* Changed: PHP 8.3 or newer is now required. WordPress will not offer the update on a site below that.
* Removed: `Mai_Locations_Queries::mai_post_grid_query()`, which was never hooked up and called a function that does not exist.
* Removed: Social media fields (Facebook, Twitter, YouTube, LinkedIn, Instagram, Pinterest, TikTok) and the `mailocations_social_fields` filter. They were switched off in 1.0.0 and never displayed anywhere. No site had any data saved in them.
* Added: `--skip_excerpt` and `--skip_image` for `wp mailocations update_locations_from_website`, so a run can fetch only images or only excerpts.
* Changed: Administrators and editors can now use the front-end edit form and see Edit buttons on any location, matching what they can already do in the Dashboard. Location owners keep editing their own locations whatever their role.
* Fixed: The "your location has been published" email left the location's label out of its subject and body, and logged a warning each time.
* Fixed: Filter values taken from the address bar were escaped and then replaced with the raw value.
* Fixed: A filter button inside a template with no post context logged a warning instead of doing nothing.
* Fixed: The state list spelled Washington DC as "District of Colombia".
* Fixed: Settings values went into the form unescaped, so a label containing a double quote broke its field.
* Fixed: The Default Units dropdown printed a stray `selected='selected'` before its first option.
* Fixed: A Google API key saved in the settings replaced the key ACF already had. It now fills in only what ACF is missing.
* Fixed: The upgrade routines ran twice on every Dashboard page load, because a function copy and a class copy were both hooked. One copy runs now, and the old function names still work.
* Fixed: `[mai_location_place]` now prints its `style` attribute, which it accepted and ignored, and escapes the Place ID it puts in the link.
* Fixed: `[mai_location_phone]` escapes its `style` attribute, as the other shortcodes already did.
* Fixed: A CSV import submitted without choosing a status saved every location as "public", which is not a real post status. It now falls back to Published.
* Fixed: `[mai_location_phone]` could take a page down when a location's phone field held text rather than a number, such as "Call us". The text now prints as entered.
* Fixed: `[mai_location_phone]` linked to only the first group of digits when the location had no country set, so `914-631-8200` dialled `914`.
* Fixed: a phone number that is not valid for its country printed an empty link and logged warnings. It now falls back to the number as entered.
* Fixed: `wp mailocations update_locations_from_website` stopped with a fatal error when an image download failed, for example on a local site with a self-signed certificate. It now skips that location and carries on.
* Fixed: `[mai_location_url]` removed the first letter of any web address starting with "w", so `www.washingtonirving.org` displayed as `ashingtonirving.org`.
* Fixed: The CSV importer stored every value as escaped HTML, so imported website addresses came in with `&amp;` in them. Each value is now sanitized by its field type. Values imported before this update are unchanged.

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

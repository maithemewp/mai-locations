# Mai Locations rework

*Started September 14, 2026*

Mike's calls, September 14, 2026:

- **Incremental.** Tests around current behaviour first, then move code into namespaces file by file, fixing bugs as the tests find them.
- **No release until the whole rework is done.** Work lands on `develop` in steps; nothing is tagged or shipped midway.
- **PHP floor 8.2, or 8.3.** Raise `Requires PHP` and `composer.json` together as the last step before the release.
- **Started September 15, 2026, earlier than first planned.** Mike brought it forward while Visit Sleepy Hollow waits on SiteGround SSH.

## Resume here

A fresh session should read this file first, then `README.md`, then `CHANGES.md`. The test harness is in (September 15, 2026): `composer test-setup`, `composer test`, `composer stan`, setup in the README's Tests section. The next step is characterisation tests. Visit Sleepy Hollow (`~/Herd/visitsleepyhollow`, symlinked to this folder) is a real site using the plugin: 141 locations, the `update_locations_from_website` CLI command, the facts block in its theme, and archive shortcodes `[mai_location_address]` and `[mai_location_phone]`. Use it as a manual check that nothing it relies on changes.

Follow `wp-plugin-scaffold` for layout and the global modern PHP rules: `declare(strict_types=1)`, namespaces, typed everything, enums, `match`, cast at the boundary.

## Bugs found

- [x] `mailocations_get_data_from_website()` returned `$data['key']` instead of `$data[ $key ]` on a failed request or empty body, so a caller asking for one key got null and a warning. `classes/class-locations-cli.php`. Patched September 14, 2026.
- [x] The Twitter card fallback in the same function switched on `$property` instead of `$name`, so `twitter:image` and `twitter:description` were never read. Patched September 14, 2026.
- [ ] `mailocations_upload_image()` re-downloads the saved image through the site's own uploads URL with `download_url()`. That fails on local sites with self-signed certificates (`cURL error 60` on Herd) and is a needless round trip everywhere. Sideload from the fetched bytes instead.
- [ ] `mailocations_upload_image()` saves every image as `md5(url).jpg`, whatever its real type, so a PNG or WebP gets a `.jpg` name.
- [ ] `update_locations_from_website` overwrites nothing, but it always sets the excerpt from `og:description` on a location without one, with no way to turn that off. Add a flag or split the image and excerpt jobs.
- [ ] `mailocations_get_data_from_website()` uses WordPress's default user agent and a 5 second timeout. Many hotel and chain sites return nothing to it but answer a browser user agent. Seen on Visit Sleepy Hollow: 44 of 106 sites gave no image until retried.

Found by PHPStan when the harness went in, each confirmed by reading the code. All sit in `tests/phpstan-baseline.neon` until fixed.

- [ ] The location edit form reads `$GET['referrer']` instead of `$_GET['referrer']`, so its Back link never shows. `classes/class-location-form-edit.php:23`.
- [ ] `send_published_email()` passes an undefined `$post_type` to `mailocations_get_singular_label()` instead of `$post->post_type`, so the "has been published" email gets the wrong label and a warning. `classes/class-location-form-listener.php:369`.
- [ ] The importer looks up a per-field escaping callback in `$allowed`, which is never defined, so every imported meta value goes through `esc_html()`. `classes/class-location-import.php:457`. Decide whether to define the map or delete the lookup.
- [ ] `[mai_location_phone]` leaves `$tel` and `$formatted` undefined when a country is set but the number is not valid for it, giving warnings and an empty link. `includes/shortcodes.php:82`.
- [ ] `acf_google_map_api()` tests `isset( $api['key'] ) || empty( $api['key'] )`, which is always true, so the plugin's key always replaces whatever key ACF already had. `classes/class-settings.php:332`.
- [ ] `mailocations_upload_image()` passes a `WP_Error` to `wp_delete_file()` when the download fails. `classes/class-locations-cli.php:733`. Goes away with the sideload fix above.
- [ ] `Mai_Locations_Queries::mai_post_grid_query()` calls `mailocations_get_geo_query_args()`, which does not exist. Its hook is commented out, so it is dead code today. Delete it during the move.
- [ ] Several functions document `@return void` but return a value (`mailocations_get_distance()`, `mailocations_add_location_to_user()`), and `[mai_location_distance]` uses that value. Docblocks only, fix while typing the functions.

## Steps

- [x] Test harness: PHPUnit 9.6 with the WordPress 7.1 test suite and ACF Pro loaded, plus PHPStan level 6 with WordPress, WP-CLI and ACF Pro stubs and a baseline of 224 existing errors. September 15, 2026.
- [ ] Characterisation tests for the post type, taxonomy, settings, fields, import, CLI commands and blocks as they behave today.
- [ ] Composer PSR-4 autoload under a namespace, following `wp-plugin-scaffold`.
- [ ] Move classes one at a time behind the tests, keeping public function names working until the release.
- [ ] Fix the open bugs above, each with a test.
- [ ] Raise the PHP floor and write the changelog.
- [ ] Release.

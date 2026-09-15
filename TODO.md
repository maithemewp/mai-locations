# Mai Locations rework

*Started September 14, 2026*

Mike's calls, September 14, 2026:

- **Incremental.** Tests around current behaviour first, then move code into namespaces file by file, fixing bugs as the tests find them.
- **No release until the whole rework is done.** Work lands on `develop` in steps; nothing is tagged or shipped midway.
- **PHP floor 8.2, or 8.3.** Raise `Requires PHP` and `composer.json` together as the last step before the release.
- **Started September 15, 2026, earlier than first planned.** Mike brought it forward while Visit Sleepy Hollow waits on SiteGround SSH.

## Resume here

A fresh session should read this file first, then `README.md`, then `CHANGES.md`. Nothing of the rework is written yet beyond the two bug fixes below. The first step is the test harness. Visit Sleepy Hollow (`~/Herd/visitsleepyhollow`, symlinked to this folder) is a real site using the plugin: 141 locations, the `update_locations_from_website` CLI command, the facts block in its theme, and archive shortcodes `[mai_location_address]` and `[mai_location_phone]`. Use it as a manual check that nothing it relies on changes.

Follow `wp-plugin-scaffold` for layout and the global modern PHP rules: `declare(strict_types=1)`, namespaces, typed everything, enums, `match`, cast at the boundary.

## Bugs found

- [x] `mailocations_get_data_from_website()` returned `$data['key']` instead of `$data[ $key ]` on a failed request or empty body, so a caller asking for one key got null and a warning. `classes/class-locations-cli.php`. Patched September 14, 2026.
- [x] The Twitter card fallback in the same function switched on `$property` instead of `$name`, so `twitter:image` and `twitter:description` were never read. Patched September 14, 2026.
- [ ] `mailocations_upload_image()` re-downloads the saved image through the site's own uploads URL with `download_url()`. That fails on local sites with self-signed certificates (`cURL error 60` on Herd) and is a needless round trip everywhere. Sideload from the fetched bytes instead.
- [ ] `mailocations_upload_image()` saves every image as `md5(url).jpg`, whatever its real type, so a PNG or WebP gets a `.jpg` name.
- [ ] `update_locations_from_website` overwrites nothing, but it always sets the excerpt from `og:description` on a location without one, with no way to turn that off. Add a flag or split the image and excerpt jobs.
- [ ] `mailocations_get_data_from_website()` uses WordPress's default user agent and a 5 second timeout. Many hotel and chain sites return nothing to it but answer a browser user agent. Seen on Visit Sleepy Hollow: 44 of 106 sites gave no image until retried.

## Steps

- [ ] Test harness: PHPUnit with a WordPress test suite, plus PHPStan with WordPress stubs.
- [ ] Characterisation tests for the post type, taxonomy, settings, fields, import, CLI commands and blocks as they behave today.
- [ ] Composer PSR-4 autoload under a namespace, following `wp-plugin-scaffold`.
- [ ] Move classes one at a time behind the tests, keeping public function names working until the release.
- [ ] Fix the open bugs above, each with a test.
- [ ] Raise the PHP floor and write the changelog.
- [ ] Release.

# Mai Locations rework

*Started September 14, 2026. Updated September 15, 2026.*

Mike's calls, September 14, 2026:

- **Incremental.** Tests around current behaviour first, then move code into namespaces file by file, fixing bugs as the tests find them.
- **No release until the whole rework is done.** Work lands on `develop` in steps; nothing is tagged or shipped midway.
- **PHP floor 8.2, or 8.3.** Raise `Requires PHP` and `composer.json` together as the last step before the release.
- **Started September 15, 2026, earlier than first planned.** Mike brought it forward while Visit Sleepy Hollow waits on SiteGround SSH.
- **The plugin runs on many sites, so be careful.** Mike, September 15, 2026. Every name another site could depend on stays working: functions, classes, hooks, shortcodes, block names, option and meta keys, query params, the CLI command. `tests/integration/PublicApi/PublicNamesTest.php` pins the full list and fails if any disappears. Drop a name only as a deliberate, changelogged decision.

## Resume here

A fresh session should read this file first, then `README.md`, then `CHANGES.md`. The harness and the characterisation tests are in (September 15, 2026): 578 tests, passing in default and random order. Run `composer test` and `composer stan`; setup is in the README's Tests section. The next step is the PSR-4 autoload and moving classes one at a time.

Visit Sleepy Hollow (`~/Herd/visitsleepyhollow`, symlinked to this folder) is a real site using the plugin: 141 locations, the `update_locations_from_website` CLI command, `mailocations_get_data_from_website()` from its own scripts, `mailocations_get_address()` in its theme's facts block, the `mailocations_general_fields` filter, and archive shortcodes `[mai_location_address]` and `[mai_location_phone]`. Use it as a manual check that nothing it relies on changes.

Follow `wp-plugin-scaffold` for layout and the global modern PHP rules: `declare(strict_types=1)`, namespaces, typed everything, enums, `match`, cast at the boundary.

## Who else runs this plugin

`docs/fleet-survey-2026-09-15.md` has the full survey. The short version: 11 fleet sites, biggest is pregnancybydesign.com with 3,655 locations. Third-party code calls `mailocations_update_google_map_from_address()` on 5 sites, `[mai_location_address]` in 4 themes, and on naturesoma.com the field filters `mailocations_general_fields` and `mailocations_address_fields`, which it uses to hide the phone and email fields and rename a tab. No site has social field data, and naturesoma's `mailocations_social_fields` callback returns an empty array too. Two sites are behind, on 0.4.0 and 1.0.0, so the upgrade path from 0.4.0 has to work.

## Agreed design, not built yet

**Publishing from the front-end edit form.** Mike's call, September 15, 2026. Review this again before building it.

Today any ACF save of a location forces every status but publish to publish. Two pinned tests in `tests/integration/Blocks/FormListenerTest.php` show how far that reaches: it fires on Dashboard saves, not only the front-end form the 0.4.0 changelog describes, and it sweeps up private and trashed locations along with drafts and pending ones.

The design:

- A Publish checkbox on the **edit form only**, added automatically whenever the location is not published. Not an optional field a site picks, so no site has to change anything and no site silently stops publishing.
- Hidden once the location is published, so there is no way to unpublish from the front end. Same pattern as the existing `acf/prepare_field` filters.
- Checked and saved promotes `draft` or `pending` to `publish`. Unchecked saves the edit and leaves the status alone.
- A whitelist, never a blacklist: `private` and `trash` are never touched, and `publish` is never demoted.
- Front-end form only. A Dashboard save leaves the status alone.
- New submissions do not get the checkbox. The submission block already has its own Status setting (`location_status`), so the site decides what a new submission arrives as.

It follows the existing pseudo-field pattern: like `mai_location_title`, `mai_location_excerpt` and `mai_location_image`, the checkbox is an ACF field the listener pulls out of `$_POST` and applies to the post rather than saving as meta.

## How the tests work

- **They pin today's behaviour, bugs included.** A test named `test_pins_bug_...` asserts the buggy output and says in a comment what correct looks like. Fixing the bug means changing that test in the same commit.
- **One folder per area** under `tests/integration/`: `Registration`, `Fields`, `Display`, `Data` (CLI, importer, location functions), `Blocks` (blocks and front-end forms), `PublicApi`.
- **Static caches are the biggest obstacle.** Labels, base, options, field lists, post type and taxonomy lists, query defaults, "is filtered" and user locations are all cached in `static` variables for the whole request. Filters added after boot do nothing, so the filter paths (including `mailocations_general_fields`, which Sleepy Hollow uses) cannot be tested in-process. `Registration/ScenarioRunner.php` works around it by booting WordPress in a child process with the filter already in place. The rework should make these caches resettable or drop them.
- **ACF block data filters are lost after the first test.** The Blocks tests re-add them in `set_up()` with `restore_acf_local_meta_filters()`.
- **Paths untested because they need a Google API key or a real request:** `import_places`, the two Google Maps address functions, the edit-form listener, and the import confirmation notice (both read `filter_input()`, which is empty on the command line).

## Bugs found

Every item below was confirmed in code or by a test. Unless marked otherwise, a `test_pins_bug_` test holds the current behaviour.

### Crashes

- [ ] A failed image re-download crashes `update_locations_from_website`. `wp_delete_file()` gets a `WP_Error` and `unlink()` throws a `TypeError`. `classes/class-locations-cli.php:733`. This is the re-download that fails on Herd's certificate.
- [ ] `[mai_location_phone]` throws an uncaught `NumberParseException` when a country is set and the phone text cannot be parsed, such as "Call us". `includes/shortcodes.php:62`.
- [ ] One blank line in an import CSV stops the import with a `ValueError`. `classes/class-location-import.php:303`.
- [ ] `no_results_text()` fatals on a null `$wp_query`, because `->get()` runs before the null check, and throws a `TypeError` on an array `post_type`. `mai-locations.php:399`.
- [ ] `add_acf_form_head()` in the WooCommerce tabs class calls `is_account_page()` without checking WooCommerce is active. Not live today, since the class is not instantiated.

### Wrong output on the front end

- [x] `[mai_location_url]` stripped every leading "w", so `www.washingtonirving.org` showed as `ashingtonirving.org`. `ltrim()` takes a character list, not a prefix. Now `preg_replace( '#^www\.#i', ... )`. `includes/shortcodes.php:138`. Fixed September 15, 2026 on Mike's call, with the pinned test flipped and cases added for a host starting with "w" and for an uppercase WWW.
- [ ] `[mai_location_phone]` with no country links to `tel://914` for `914-631-8200`, because `(int)` stops at the first dash. `includes/shortcodes.php:77`.
- [ ] `[mai_location_phone]` leaves `$tel` and `$formatted` undefined when a country is set but the number is not valid for it, giving warnings and an empty link. `includes/shortcodes.php:82`.
- [ ] `[mai_location_email link="false"]` still links, because the string "false" is truthy. `includes/shortcodes.php:190`.
- [ ] `[mai_location_distance]` loses the spaces in `before` and `after` (`3.1mi away`), returns a float when `after` is empty, and prints nothing for a distance that rounds to 0. `includes/shortcodes.php:294`.
- [ ] `mailocations_get_address()` with `hide="country"` on a non-US record shows the US state field, not the international one. `includes/functions-display.php:88`.
- [ ] An address with only a country prints an empty `mai-address-item` div. `includes/functions-display.php:106`.
- [ ] A geo query with no distance limit drops a location sitting exactly at the search point, because the WHERE clause treats a distance of 0 as false. `classes/class-geo-query.php:191`.
- [ ] The geo query order fallback never applies, because concatenation runs before `?:`. `classes/class-geo-query.php:225`.
- [ ] A filter latitude of `0` counts as no geo query. `includes/functions-filters.php:133`.
- [ ] The location edit form reads `$GET['referrer']` instead of `$_GET['referrer']`, so its Back link never shows. `classes/class-location-form-edit.php:23`.
- [ ] A custom form class runs into the default one (`mailocations-formextra`). `classes/class-location-form.php:87`.
- [ ] The locations table puts its `<h2>` inside `<table>`, never prints its `class` arg, and does not URL-encode the referrer in Edit links. `classes/class-locations-table.php:152`.
- [ ] An empty locations table block passes null to `wp_kses_post()` and loses its title, header and no-results defaults. `classes/class-locations-table.php:38`.
- [ ] The table block's settings show the submission block's labels, because both field groups use the keys `mai_location_redirect` and `mai_location_fields` and ACF keeps the first.
- [ ] The count block ignores its field defaults and renders `0  0`.
- [ ] The map's directions link uses `ref=` instead of `rel=`. The map's "All locations" setting queries regular posts on any page that is not a location archive.
- [ ] The submit button variation keeps the link's `href` on the `<button>`. Distance options keep a leading space (`value=" 20"`).
- [ ] The "District of Colombia" state label is misspelled. `includes/functions-fields.php:415`.

### Wrong data saved

- [x] The CSV importer ran every meta value through `esc_html()`, so URLs were stored with `&amp;`. The per-type sanitizers lived in a variable that only existed inside `get_fields()`. Now a shared `get_sanitizers()` map, looked up by the field's type, with `url` on `esc_url_raw` rather than `esc_url` because the value is stored, not printed. `get_fields()` also stops reading the type of a field it just unset. Mike's call, September 15, 2026. Values imported before this stay mangled; a repair command is a separate job if anyone wants one.
- [ ] The importer's default status is `public`, which is not a post status (`:279`). Its failed count can never rise, because `wp_insert_post()` is called without `$wp_error` (`includes/functions-locations.php:82`). The shipped template CSV repeats `address_street`, so the second line overwrites the street.
- [ ] `mailocations_create_location()` lets field defaults override `meta_input` passed in, so an explicit `CA` becomes `US`. `includes/functions-locations.php:74`.
- [ ] `mailocations_add_location_to_user()` adds duplicates.
- [ ] The Google geocoding address never includes country or state, because it tests `$countries[ $key ]` instead of the value. `includes/functions-locations.php:291`. Read in code only.
- [ ] Saving an empty settings form stores a distance of 0; units are not limited to `mi` and `km`; unknown keys are kept unsanitised.
- [ ] Both upgrade routines run on every hook: `includes/upgrade.php` and `classes/class-upgrade.php`. A fresh install writes the version option four times, and migrated values are saved unsanitised. Delete one copy.

### CLI and website data

- [x] `mailocations_get_data_from_website()` returned `$data['key']` instead of `$data[ $key ]` on a failed request or empty body. Patched September 14, 2026.
- [ ] **The Twitter card fallback still never runs.** The September 14 patch fixed the `$property`/`$name` switch, but the fallback sits behind `! array_values( $data )`, which is always false because the array always has two keys. The same check at `:479` means locations with no data are never skipped. `classes/class-locations-cli.php:612`. Corrected September 15, 2026; it was wrongly marked fixed.
- [ ] A failed sideload is logged as "Image updated": `mailocations_upload_image()` returns a `WP_Error`, the check at `:527` treats it as success, and `set_post_thumbnail()` gets the error.
- [ ] `mailocations_upload_image()` fetches with `file_get_contents()` (no timeout, no user agent, warns on failure), then re-downloads the saved copy through the site's own uploads URL with `download_url()`. That fails on local sites with self-signed certificates (`cURL error 60` on Herd). Sideload from the fetched bytes instead. `:685`.
- [ ] `mailocations_upload_image()` stages every image as `md5(url).jpg`. WordPress renames a PNG on sideload, but the staging name is still wrong.
- [x] `update_locations_from_website` always set the excerpt from `og:description` on a location without one, with no way to turn that off. Added `--skip_excerpt` and `--skip_image`, alongside the existing `--force_excerpt` and `--force_image`. Mike's call, September 15, 2026.
- [ ] `mailocations_get_data_from_website()` uses WordPress's default user agent and a 5 second timeout. Many hotel and chain sites answer only a browser user agent. Seen on Visit Sleepy Hollow: 44 of 106 sites gave no image until retried. An unknown `$key` warns and returns null.

### Settings page

- [ ] `acf_google_map_api()` tests `isset( $api['key'] ) || empty( $api['key'] )`, always true, so the plugin's key always replaces ACF's. The signature check has the same bug. `classes/class-settings.php:332`.
- [ ] The units dropdown prints `selected='selected'` outside the tag, because `selected()` echoes inside `printf()`. `classes/class-settings.php:269`.
- [ ] Settings values go into `value=""` unescaped, so a label with a double quote breaks the field. `classes/class-settings.php:197`.
- [ ] `mailocations_get_option()` returns stale values after `mailocations_update_option()` in the same request, and warns on an unknown key.
- [ ] Labels and base are sanitised only on first call; later calls return the raw filtered value. The filtered base uses `sanitize_html_class()`, the saved one `sanitize_title_with_dashes()`.

### Security

- [ ] `[mai_location_phone]` prints `style` unescaped (`includes/shortcodes.php:56`). `[mai_location_place]` prints `place_id` unescaped (`:262`).
- [ ] `mailocations_get_query_params()` escapes the value and then overwrites it with the raw `$_GET` value. `includes/functions-filters.php:61`.

### Smaller

- [ ] `send_published_email()` uses an undefined `$post_type`, so the email reads "Your http://example.org  has been published!". `classes/class-location-form-listener.php:369`.
- [ ] The block binding source warns on a missing `postId` context for `filterSubmit` and `filterClear`, and returns nothing for location meta. `classes/class-block-bindings.php:61`.
- [x] `mailocations_user_can_edit()` was true only for the post author, so administrators and editors saw no front-end Edit button on locations they could already edit in wp-admin. Now the author, whatever their role, or anyone who passes `current_user_can( 'edit_post' )`. Mike's call, September 15, 2026. The author branch stays first because location owners are often subscriber level and would fail a capability check.
- [ ] `mailocations_delete_transients()` prefix-matches, so it also deletes transients like `mai_locationsother`.
- [ ] The taxonomy is hierarchical but its rewrite is not, so child term URLs get no rule.
- [ ] The map script does not list the marker clusterer as a dependency. The settings link hook hardcodes the folder name `mai-locations`.
- [ ] Field group titles keep the `{SINGULAR}` placeholder on real screens, because ACF caches the group before a post exists.
- [ ] The excerpt field keeps only the Visual tab, the opposite of its docblock; the method name is misspelled `prepare_location_exerpt_field`.
- [ ] PHP 8.4 deprecations: `str_getcsv()` without `$escape` (`class-location-import.php:300`), `get_page_by_title()` (`:463`). Users are created without a password (`:361`).
- [ ] Typos: `File (.csv]` label (`:96`), stray quote in the download link (`:77`), text domain `mai-location` in two places.
- [ ] Wrong docblocks: `mailocations_get_distance()` and `mailocations_add_location_to_user()` say void but return values; `Mai_Geo_Query::get_distance()` says float but returns false; `should_update()` can return null; the rule-match screen is an array, not `WP_Screen`.
- [ ] Dead code: `Mai_Locations_Queries::mai_post_grid_query()` is unhooked and calls `mailocations_get_geo_query_args()`, which does not exist. Social fields: removed September 15, 2026 on Mike's call. They were switched off in 1.0.0, displayed nowhere, and the fleet survey found no saved data on any site, including the one still on 0.4.0 where they were live. naturesoma.com's `mailocations_social_fields` callback returned an empty array too, so its hook can go whenever that theme is touched.

## Steps

- [x] Test harness: PHPUnit 9.6 with the WordPress 7.1 test suite and ACF Pro loaded, plus PHPStan level 6 with WordPress, WP-CLI and ACF Pro stubs and a baseline of 224 existing errors. September 15, 2026.
- [x] Characterisation tests for the post type, taxonomy, settings, fields, import, CLI commands, blocks, forms and the public names. 578 tests. September 15, 2026.
- [x] PHP moved into `inc/`, and Composer PSR-4 autoload wired for `Mai\Locations\` to `inc/classes/`, following `wp-plugin-scaffold`. September 15, 2026. Two commits, kept apart: the directory move, then the autoload wiring.
- [ ] Move classes one at a time behind the tests, keeping public names working until the release. Make the static caches resettable as each file moves.
  - Each migrated class keeps its old global name in `inc/aliases.php`, which the bootstrap picks up through its `inc/*.php` glob. `PublicNamesTest::test_migrated_classes_keep_their_old_names_as_aliases()` fails if an alias breaks.
  - [x] `Mai_Locations_Block_Bindings` to `Mai\Locations\BlockBindings`. September 15, 2026.
  - [x] `Mai_Locations_Scripts` to `Mai\Locations\Scripts`, and `Mai_Locations_Queries` to `Mai\Locations\Queries`. September 15, 2026.
  - [x] `Mai_Locations_Upgrade` to `Mai\Locations\Upgrade`, and `Mai_Geo_Query` to `Mai\Locations\GeoQuery`. September 15, 2026. The geo query instance now starts from the bootstrap.
  - [x] `Mai_Locations_Locations_Table` to `Mai\Locations\LocationsTable`, and `Mai_Locations_Settings` to `Mai\Locations\Settings`. September 15, 2026. Settings used to instantiate itself at the top of its own file; the bootstrap does that now. Its nine `add_settings_field()` calls became one loop over a field list, since every callback is named after its id.
  - [ ] The remaining 8 classes in `inc/classes/`: the CLI, the importer, the location fields, the four form classes and the WooCommerce account tabs. Then the 9 block classes.
  - **Watch for files that run code on load.** Several classes instantiate themselves at the top or bottom of their file, which autoloading never runs. Each one moves to the bootstrap as its class moves.
  - **Watch for values arriving as strings.** `declare(strict_types=1)` turns coercion that used to happen silently into a `TypeError`. `GeoQuery::get_distance()` caught it: MySQL returns the computed distance column as a string, and `round()` then refused it, breaking nine tests. Cast at the boundary as each file moves.
  - **Watch for namespaced function calls.** A missing global function called from a namespaced class reports as `Mai\Locations\the_function()`, which is what the `mai_post_grid_query()` fatal test caught. Same behaviour, different message.
  - **Dead code found on the way, not deleted yet:** `Queries::mai_post_grid_query()` is unhooked and calls a function that does not exist. Deleting it removes a public method, so it needs Mike's yes. Three tests pin it today.
- [ ] Fix the open bugs above, each by flipping its pinned test.
- [ ] Raise the PHP floor and write the changelog.
- [ ] Release.

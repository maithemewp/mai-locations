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

- [x] A failed image re-download crashed `update_locations_from_website`: `wp_delete_file()` got the `WP_Error` from `download_url()` and `unlink()` threw a `TypeError`. Now it deletes only the staged file and returns 0, so the run carries on. `inc/functions-website.php`. Fixed September 16, 2026. The underlying re-download through the site's own uploads URL is still there, below.
- [x] `[mai_location_phone]` threw an uncaught `NumberParseException` when a country was set and the phone text could not be parsed, such as "Call us", taking the page with it. Now caught, and the raw text prints with no link, because there is nothing to dial. `inc/shortcodes.php`. Fixed September 16, 2026.
- [x] One blank line in an import CSV stopped the whole import with a `ValueError` out of `array_combine()`. Rows that do not line up with the header are skipped now. `inc/classes/Admin/LocationImport.php`. Fixed September 16, 2026.
- [x] `no_results_text()` fataled on a null `$wp_query`, because `->get()` ran before the null check, and threw a `TypeError` on an array `post_type`. The guard comes first now, and a query for several post types keeps the original text. `mai-locations.php`. Fixed September 16, 2026.
- [x] `add_acf_form_head()` in the WooCommerce tabs class called `is_account_page()` without checking WooCommerce is active, so the class fataled on any site without it. It checks first and returns quietly now. `inc/classes/Integrations/WooCommerceAccountTabs.php`. Fixed September 16, 2026. The class is still not instantiated by the plugin.

### Wrong output on the front end

- [x] `[mai_location_url]` stripped every leading "w", so `www.washingtonirving.org` showed as `ashingtonirving.org`. `ltrim()` takes a character list, not a prefix. Now `preg_replace( '#^www\.#i', ... )`. `includes/shortcodes.php:138`. Fixed September 15, 2026 on Mike's call, with the pinned test flipped and cases added for a host starting with "w" and for an uppercase WWW.
- [x] `[mai_location_phone]` with no country linked to `tel://914` for `914-631-8200`, because `(int)` stopped at the first dash. Now every digit is kept. `inc/shortcodes.php`. Fixed September 16, 2026.
- [x] `[mai_location_phone]` left `$tel` and `$formatted` undefined when a country was set but the number was not valid for it, giving two warnings and an empty link. Both now default to the raw value before the country branch runs. `inc/shortcodes.php`. Fixed September 16, 2026.
- [x] `[mai_location_email link="false"]` still links, because the string "false" is truthy. It runs `rest_sanitize_boolean()` now, like the phone shortcode always has. `inc/shortcodes.php`. Fixed September 16, 2026.
- [x] `[mai_location_distance]` loses the spaces in `before` and `after` (`3.1mi away`), returns a float when `after` is empty, and prints nothing for a distance that rounds to 0. All three fixed September 16, 2026. `before` and `after` are escaped now instead of stripped, matching every other location shortcode. `inc/shortcodes.php`.
- [x] `mailocations_get_address()` with `hide="country"` on a non-US record shows the US state field, not the international one. The country is read whether or not it is displayed. `inc/functions-display.php`. Fixed September 16, 2026.
- [x] An address with only a country prints an empty `mai-address-item` div. The country has its own item below. `inc/functions-display.php`. Fixed September 16, 2026.
- [x] A geo query with no distance limit drops a location sitting exactly at the search point, because the WHERE clause treats a distance of 0 as false. The unlimited clause is now `>= 0`. `inc/classes/Query/GeoQuery.php`. Fixed September 16, 2026.
- [x] The geo query order fallback never applies, because concatenation runs before `?:`. Fixed September 16, 2026, and the direction is now limited to ASC or DESC, since the `order` query var went straight into the SQL. `inc/classes/Query/GeoQuery.php`.
- [x] A filter latitude of `0` counts as no geo query. Now `is_numeric()`. `inc/functions-filters.php`. Fixed September 16, 2026.
- [ ] The location edit form reads `$GET['referrer']` instead of `$_GET['referrer']`, so its Back link never shows. `classes/class-location-form-edit.php:23`.
- [x] A custom form class runs into the default one (`mailocations-formextra`). The `trim()` took the separating space with it. `inc/classes/Forms/LocationForm.php`. Fixed September 16, 2026.
- [x] The locations table puts its `<h2>` inside `<table>`, never prints its `class` arg, and does not URL-encode the referrer in Edit links. All three fixed September 16, 2026. The title now prints above the table, the class is appended to `mai-locations-table`, and the referrer is `rawurlencode()`d. `inc/classes/Display/LocationsTable.php`.
- [x] An empty locations table block passes null to `wp_kses_post()` and loses its title, header and no-results defaults. Null settings are dropped before `shortcode_atts()` now, so the defaults apply. `get()` also returns `''` rather than null when there is no user. `inc/classes/Display/LocationsTable.php`. Fixed September 16, 2026.
- [x] The table block's settings show the submission block's labels, because both field groups use the keys `mai_location_redirect` and `mai_location_fields` and ACF keeps the first. The table block's two fields are now `mailocations_table_redirect` and `mailocations_table_fields`, keeping the same field names so saved blocks still load their values. The choices loader is hooked on both keys. `inc/classes/Blocks/TableBlock.php`. Fixed September 16, 2026.
- [x] The count block ignores its field defaults and renders `0  0`. An unset setting now falls back to the field's registered default, a setting the user cleared stays empty, and the empty parts no longer leave double spaces. `inc/classes/Blocks/CountBlock.php`. Fixed September 16, 2026.
- [x] The map's directions link uses `ref=` instead of `rel=`. The map's "All locations" setting queries regular posts on any page that is not a location archive. Both fixed September 16, 2026. The "all" query now forces the location post types when the current query has none of them. `inc/classes/Blocks/MapBlock.php`.
- [x] The submit button variation keeps the link's `href` on the `<button>`. Distance options keep a leading space (`value=" 20"`). Both fixed September 16, 2026. `inc/classes/Blocks/FilterSubmitBlock.php` and `inc/classes/Blocks/AddressSearchBlock.php`. Removing the href leaves a stray space before the `>`, which the test pins.
- [x] The "District of Colombia" state label was misspelled, now "District of Columbia". The country list's own "Colombia" is a different entry and stays. Fixed September 16, 2026.

### Wrong data saved

- [x] The CSV importer ran every meta value through `esc_html()`, so URLs were stored with `&amp;`. The per-type sanitizers lived in a variable that only existed inside `get_fields()`. Now a shared `get_sanitizers()` map, looked up by the field's type, with `url` on `esc_url_raw` rather than `esc_url` because the value is stored, not printed. `get_fields()` also stops reading the type of a field it just unset. Mike's call, September 15, 2026. Values imported before this stay mangled; a repair command is a separate job if anyone wants one.
- [x] The importer's default status was `public`, which is not a post status, so an import posted without the status field saved locations under a status WordPress does not know. Now `publish`, with a test. Fixed September 16, 2026.
- [ ] The importer's failed count can never rise, because `wp_insert_post()` is called without `$wp_error` (`inc/functions-locations.php:82`). The shipped template CSV repeats `address_street`, so the second line overwrites the street.
- [ ] `mailocations_create_location()` lets field defaults override `meta_input` passed in, so an explicit `CA` becomes `US`. `includes/functions-locations.php:74`.
- [ ] `mailocations_add_location_to_user()` adds duplicates.
- [ ] The Google geocoding address never includes country or state, because it tests `$countries[ $key ]` instead of the value. `includes/functions-locations.php:291`. Read in code only.
- [ ] Saving an empty settings form stores a distance of 0; units are not limited to `mi` and `km`; unknown keys are kept unsanitised.
- [x] Both upgrade routines ran on every hook, one copy in `includes/upgrade.php` and one in `classes/class-upgrade.php`, so a fresh install wrote the version option four times. Only `Mai\Locations\Admin\Upgrade` is hooked now, and the global functions stay as public names that call it, so there is one implementation rather than two. Fixed September 16, 2026.
- [ ] Migrated option values are still saved unsanitised, so a base of `Our Places!` goes in as-is.

### CLI and website data

- [x] `mailocations_get_data_from_website()` returned `$data['key']` instead of `$data[ $key ]` on a failed request or empty body. Patched September 14, 2026.
- [x] **The Twitter card fallback now runs.** The September 14 patch fixed the `$property`/`$name` switch, but the fallback sat behind `! array_values( $data )`, always false because the array always has two keys, so twitter: tags were never read. It now runs whenever either value is still missing. The same check in the CLI loop, which was meant to skip locations with no data, is now `! $data['desc'] && ! $data['image']`. `inc/functions-website.php` and `inc/classes/Cli/CLI.php`. Fixed September 16, 2026.
- [x] A failed sideload was logged as "Image updated", because the `WP_Error` counted as success and `set_post_thumbnail()` then got the error. The CLI checks `is_wp_error()` first now and logs "Image failed: ..." instead. Fixed September 16, 2026.
- [x] `mailocations_upload_image()` fetched with `file_get_contents()` and then re-downloaded the saved copy through the site's own uploads URL, which failed on local sites with self-signed certificates (`cURL error 60` on Herd). It now fetches once over the HTTP API, with the same browser user agent and the `mailocations_website_request_args` filter, and sideloads from those bytes. Fixed September 16, 2026.
- [x] `mailocations_upload_image()` named every image `md5(url).jpg` whatever it was. The extension now comes from the response's content type, falling back to the URL's own. Fixed September 16, 2026.
- [x] `update_locations_from_website` always set the excerpt from `og:description` on a location without one, with no way to turn that off. Added `--skip_excerpt` and `--skip_image`, alongside the existing `--force_excerpt` and `--force_image`. Mike's call, September 15, 2026.
- [x] `mailocations_get_data_from_website()` used WordPress's default user agent and a 5 second timeout, and many hotel and chain sites answer only a browser agent. Seen on Visit Sleepy Hollow: 44 of 106 sites gave no image until retried. Now a browser user agent with a 15 second timeout, filterable through the new `mailocations_website_request_args`. An unknown `$key` returns an empty string instead of warning and returning null. Fixed September 16, 2026.

### Settings page

- [x] `acf_google_map_api()` tested `isset( $api['key'] ) || empty( $api['key'] )`, always true, so a key saved in the settings replaced whatever ACF already had, and the signature check had the same bug. Both are plain `empty()` now, so the saved values fill in only what ACF is missing. Fixed September 16, 2026.
- [x] The units dropdown printed `selected='selected'` between the select tag and its first option, because `selected()` echoes as well as returning. Now passed `false` as its third argument. Fixed September 16, 2026.
- [x] Settings values went into `value=""` unescaped, so a label with a double quote broke its field. All eight printed values are wrapped in `esc_attr()` now, with a scenario test that renders the page from a saved option holding a quote. Fixed September 16, 2026.
- [ ] `mailocations_get_option()` returns stale values after `mailocations_update_option()` in the same request, and warns on an unknown key.
- [ ] Labels and base are sanitised only on first call; later calls return the raw filtered value. The filtered base uses `sanitize_html_class()`, the saved one `sanitize_title_with_dashes()`.

### Security

- [x] `[mai_location_phone]` printed `style` unescaped, and `[mai_location_place]` printed `place_id` unescaped while ignoring `style` entirely. Both escape now, and the place shortcode prints its style like the others. `inc/shortcodes.php`. Fixed September 16, 2026.
- [x] `mailocations_get_query_params()` escaped the value and then overwrote it with the raw `$_GET` value on the next line. It escapes once and uses that now. `inc/functions-filters.php`. Fixed September 16, 2026.

### Smaller

- [x] `send_published_email()` read an undefined `$post_type`, so the email read "Your http://example.org  has been published!" and PHP warned. Now `$post->post_type`. `inc/classes/Forms/LocationFormListener.php`. Fixed September 16, 2026.
- [x] The block binding source warned on a missing `postId` context for `filterSubmit` and `filterClear`, handing the block a `false`. It checks the context first and returns null now. `inc/classes/Display/BlockBindings.php`. Fixed September 16, 2026. It still returns nothing for location meta keys, which is by design: the source only provides permalinks.
- [x] `mailocations_user_can_edit()` was true only for the post author, so administrators and editors saw no front-end Edit button on locations they could already edit in wp-admin. Now the author, whatever their role, or anyone who passes `current_user_can( 'edit_post' )`. Mike's call, September 15, 2026. The author branch stays first because location owners are often subscriber level and would fail a capability check.
- [ ] `mailocation_get_user_locations()` caches per post type for the whole request but not per user, so a second user's table in the same process gets the first user's locations. Found September 16, 2026 when a test that logged in twice returned an empty table. Real-world impact is small, since a page serves one user, but it bites the CLI and any loop over users.
- [ ] `mailocations_delete_transients()` prefix-matches, so it also deletes transients like `mai_locationsother`.
- [ ] The taxonomy is hierarchical but its rewrite is not, so child term URLs get no rule.
- [ ] The map script does not list the marker clusterer as a dependency. The settings link hook hardcodes the folder name `mai-locations`.
- [ ] Field group titles keep the `{SINGULAR}` placeholder on real screens, because ACF caches the group before a post exists.
- [ ] The excerpt field keeps only the Visual tab, the opposite of its docblock; the method name is misspelled `prepare_location_exerpt_field`.
- [ ] PHP 8.4 deprecations: `str_getcsv()` without `$escape` (`class-location-import.php:300`), `get_page_by_title()` (`:463`). Users are created without a password (`:361`).
- [ ] Typos: `File (.csv]` label (`:96`), stray quote in the download link (`:77`). The two `mai-location` text domains in the table block's labels were fixed September 16, 2026.
- [ ] Wrong docblocks: `mailocations_get_distance()` and `mailocations_add_location_to_user()` say void but return values; `Mai_Geo_Query::get_distance()` says float but returns false; `should_update()` can return null; the rule-match screen is an array, not `WP_Screen`.
- [x] Dead code: `Mai_Locations_Queries::mai_post_grid_query()` was unhooked and called `mailocations_get_geo_query_args()`, which does not exist, so it could only fatal. Deleted September 16, 2026, on Mike's call, with its three pinned tests replaced by one that checks it stays gone. The fleet survey found no caller. Social fields: removed September 15, 2026 on Mike's call. They were switched off in 1.0.0, displayed nowhere, and the fleet survey found no saved data on any site, including the one still on 0.4.0 where they were live. naturesoma.com's `mailocations_social_fields` callback returned an empty array too, so its hook can go whenever that theme is touched.

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
  - [x] `Mai_Locations_WooCommerce_Account_Tabs` to `Mai\Locations\WooCommerceAccountTabs`. September 15, 2026.
  - [x] The three form classes: `Mai_Locations_Location_Form` to `Mai\Locations\LocationForm`, plus `LocationFormEdit` and `LocationFormSubmit`. September 15, 2026.
  - [x] `Mai_Locations_Location_Form_Listener` to `Mai\Locations\LocationFormListener`. September 15, 2026.
  - [x] `Mai_Locations_Location_Fields` to `Mai\Locations\LocationFields`. September 15, 2026. Two long commented-out field blocks went with it, a Categories field and a user-locations group; git history has them.
  - [x] `Mai_Locations_Location_Import` to `Mai\Locations\LocationImport`. September 15, 2026.
  - [x] `Mai_Locations_CLI` to `Mai\Locations\CLI`. September 15, 2026. Its two public global functions, `mailocations_get_data_from_website()` and `mailocations_upload_image()`, moved to `inc/functions-website.php` and stay global, because Visit Sleepy Hollow calls both from its own scripts. The command registration and the discarded instantiation that used to run at the top of the class file moved to the bootstrap. `wp mailocations` is unchanged; only the class the command is registered with changed.
  - [x] **All 15 classes in `inc/classes/` are namespaced.**
  - [x] **Grouped into role subfolders**, September 15, 2026, on Mike's call after surveying all 26 plugins: `Admin/`, `Cli/`, `Display/`, `Fields/`, `Forms/`, `Integrations/`, `Query/`, with `Blocks/` to come. Every recent plugin groups by role (`mai-text-to-speech`, `mai-post-aggregator`, the three springwire plugins), and `inc/classes/` with subfolders already exists in `eurweb-plugin` and `hmg-sharpspring`. So this follows both the scaffold's root and the newer plugins' grouping. Namespaces gained a level, for example `Mai\Locations\Forms\LocationFormEdit`; the old global names are unchanged.
  - [x] **The 9 block classes**, now `Mai\Locations\Blocks\*` in `inc/classes/Blocks/`. September 15, 2026. Each `block.json` stays in `blocks/<name>/`, and `register_block_type()` takes that folder path, which is what mai-auth does. The two button variations have no `block.json` at all.
  - [x] **Every class in the plugin is namespaced.** 24 classes, all with their old global names kept in `inc/aliases.php`.

## Blocks stay on ACF for this rework

Mike asked, September 15, 2026, whether to convert to PHP-only core blocks. Not in this rework.

- All seven `block.json` files already declare `"apiVersion": 3`, so there is nothing to bump. The other two blocks are `core/button` variations with no `block.json`.
- Six of the nine read `get_field()`, in 24 places. Converting means new block names, a different attribute payload, a JS editor script per block, and a deprecation path, because saved content on live pages is `<!-- wp:acf/mai-locations-map {"data":{...}} /-->`.
- That is a migration across 11 sites, one of them holding 3,655 locations, so it is its own project with its own release, not part of a rework whose rule is that behaviour does not change.
  - **The CLI file also holds two public global functions**, `mailocations_get_data_from_website()` and `mailocations_upload_image()`, which Visit Sleepy Hollow calls from its own scripts. They stay global, in a procedural file under `inc/`, when that class moves.
  - **Watch for files that run code on load.** Several classes instantiate themselves at the top or bottom of their file, which autoloading never runs. Each one moves to the bootstrap as its class moves.
  - **Watch for values arriving as strings.** `declare(strict_types=1)` turns coercion that used to happen silently into a `TypeError`. `GeoQuery::get_distance()` caught it: MySQL returns the computed distance column as a string, and `round()` then refused it, breaking nine tests. Cast at the boundary as each file moves.
  - **Watch for namespaced function calls.** A missing global function called from a namespaced class reports as `Mai\Locations\the_function()`, which is what the `mai_post_grid_query()` fatal test caught. Same behaviour, different message.
  - **Dead code found on the way, not deleted yet:** `Queries::mai_post_grid_query()` is unhooked and calls a function that does not exist. Deleting it removes a public method, so it needs Mike's yes. Three tests pin it today.
- [ ] Fix the open bugs above, each by flipping its pinned test.
- [x] Raise the PHP floor. 8.3, Mike's call, September 16, 2026, after checking two things: PHPStan analysing the whole plugin with `phpVersion: 80200` reports no errors, so nothing in the code needs 8.3, and no bundled dependency asks for more than `^8.1`. Every fleet site measured that day ran 8.3.30 or newer, three on 8.4. So the floor is a forward-looking choice about what we may write, not a requirement. `Requires PHP` and `composer.json` moved together.
- [ ] Write the changelog and release.
- [ ] Release.

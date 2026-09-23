# Changelog

## 2.0.0 (9/23/26)

A large release. Every bug the rework found is fixed, the front-end edit form no longer publishes a location behind its owner's back, and PHP 8.3 is now the minimum.

**Two things to check after updating.** Check any site whose editors publish locations by saving them in the Dashboard: that no longer changes the status, and the Publish and Save Draft buttons do what they say instead. And look at the new Publishing setting, under **Locations > Settings** in the Dashboard (your own plural label, if you renamed it), which the update turns **on** so nothing changes for you: uncheck it if a manager is meant to approve every listing.

### Requirements

* Changed: PHP 8.3 or newer is now required. WordPress will not offer the update on a site below that.

### Security

* Security: A location's owner could publish a private or trashed location of theirs, and a new submission could publish itself past the submission block's Status setting, by editing hidden form values in the browser. Both are closed.
* Security: Anyone able to publish locations at all, an author-role user for example, could have published a draft belonging to someone else, which they cannot even open in the Dashboard. Publishing a location from the front end now requires permission to edit that location as well.
* Fixed: Filter values taken from the address bar were escaped and then replaced with the raw value.
* Fixed: Settings values went into the form unescaped, so a label containing a double quote broke its field.
* Fixed: `[mai_location_place]` now prints its `style` attribute, which it accepted and ignored, and escapes the Place ID it puts in the link.
* Fixed: `[mai_location_phone]` escapes its `style` attribute, as the other shortcodes already did.

* Fixed: Submission notification emails were never sent on ACF Pro 6.8.2 or newer. The list of addresses now travels inside ACF's own encrypted form data instead of a hidden field, which ACF had started stripping.
* Security: A submitter could change who the site sent submission notifications to, by editing a hidden field in the browser. The addresses can no longer be changed from the browser at all.

### Crashes

* Fixed: `[mai_locations_table fields="..."]` took the page down, because the shortcode passes its fields as text and the table expected a list.
* Fixed: Anyone could take down a page holding a Mai Locations Filter block by adding `?_mai_location_cat[]=x` to its address. A filter sent as a list now works the same as one sent comma-separated.
* Fixed: A Locations Table block saved with no fields checked took the whole page down with a critical error the moment anyone pressed Edit. The form now renders as nothing at all, and the rest of the page is unaffected.
* Fixed: A blank line in an import CSV, which most editors leave at the end of a file, stopped the whole import with a fatal error. Those lines are skipped now.
* Fixed: The "no locations found" text could fatal on an archive listing several post types, or where the page had no query of its own.
* Fixed: `[mai_location_phone]` could take a page down when a location's phone field held text rather than a number, such as "Call us". The text now prints as entered.
* Fixed: `wp mailocations update_locations_from_website` stopped with a fatal error when an image download failed, for example on a local site with a self-signed certificate. It now skips that location and carries on.

### Publishing a location from the front end

Until now, any front-end save of an unpublished location published it, with nothing to switch it off. That is replaced by one setting and one switch, which cover the three ways sites actually use this plugin. Locations > Settings explains all three where you choose between them.

| How you work | Publishing setting | Submission block status |
| --- | --- | --- |
| A manager approves everything | off | Pending |
| Owners run their own listing | on | Draft |
| You approve, the owner picks the moment | on | Pending, then you change it to Draft |

* Added: A **Publishing** setting, "Let location owners publish their own locations". Off on a new site, so a new site moderates by default. **Updating an existing site turns it on**, because every site published on a front-end save before this, and defaulting to off would quietly take that away. Uncheck it if you moderate.
* Added: A Publish switch on the front-end edit form, shown to a location's owner while the location is a draft, and only if the setting allows it. It reads Publish or Not yet. It never appears on a published location, so nobody can take a listing down from the front end.
* Added: `mailocations_user_can_publish( $location_id, $user_id )` and a filter of the same name, for sites that want to decide this per user or per location in code.
* Changed: A **pending** location can no longer be published from the front end at all. Pending now means it is with a manager. An approved one can be moved to Draft, and its owner then publishes when they are ready.
* Changed: Editing a location on the front end no longer publishes it on its own. This replaces the old behaviour, where any save of an unpublished location made it live whether or not that was the intention.
* Changed: Anyone whose role can already publish in the Dashboard sees the Publish switch whatever the setting says. That is editors and administrators, and also authors, so on a site where owners have the Author role they can publish their own drafts even with the setting off. Owners are usually subscribers.
* Note: Moderation covers publishing, not later edits. Once a listing is live, its owner's front-end edits go live straight away.
* Fixed: A location owner could not see their own drafts. The locations table lists them now, so a site can set its submission block to Draft without the listing disappearing from its owner. Private and trashed locations stay out.
* Fixed: Saving a location in the Dashboard published it, even from the Save Draft button. The Dashboard now leaves the status exactly where you put it.
* Fixed: A location that was private or in the trash was made live by any save. Only a draft can be published now, and a published one is never taken back down.

### The front-end forms and the locations table

* Changed: The image field on the front-end forms now accepts only .jpg, .jpeg, .png and .webp files, up to 5 MB. Its help already said so, but nothing enforced it, so any image of any size went through. An upload outside those limits now gets an error.
* Fixed: The Back link above the front-end edit form never appeared.
* Fixed: A class added to a location form ran into the form's own class, so `class="extra"` came out as `mailocations-formextra` and never matched any styles.
* Fixed: The locations table printed its heading inside the `<table>` element, which is not valid HTML. The heading now sits above the table.
* Fixed: The locations table accepted a `class` and never printed it.
* Fixed: Edit links in the locations table did not encode the page they return to, so a Back link lost anything after a question mark.
* Changed: Administrators and editors can now open the front-end edit form for any location, matching what they can already do in the Dashboard. Location owners keep editing their own locations whatever their role.
* Fixed: The "your location has been published" email left the location's label out of its subject and body, and logged a warning each time.

### Blocks

* Fixed: A locations count block added without changing its settings printed "0  0" instead of "Showing 0 of 0 Locations". Clearing a setting on purpose still leaves it out, without the double space it used to print.
* Fixed: The locations table block's settings were labelled "Submission Redirect" and "Submission Form Fields", because both blocks registered two fields under the same ACF keys. Saved settings are unaffected.
* Fixed: A locations table block with no settings saved printed nothing and logged a deprecation notice. It now falls back to its default heading, header and no-results text.
* Fixed: Two locations table block labels were untranslatable, because they used the wrong text domain.
* Fixed: The filter submit button kept the link address it was built from, on an element that cannot use one.
* Fixed: Distance options written the natural way, "10, 20", searched a distance of " 20" with a leading space.
* Fixed: A filter button inside a template with no post context logged a warning instead of doing nothing.
* Fixed: A map set to show all locations showed none on an ordinary page, and the empty map was cached for an hour.
* Fixed: The Get Directions link in a map marker wrote `ref` where it meant `rel`, so it opened without the usual link protections.
* Fixed: The map's marker grouping script was not declared as a requirement of the map script, so it only happened to load in time.

### Importing

* Fixed: The example import CSV used the `address_street` heading twice, so anyone following it lost the street and imported the suite number in its place.
* Fixed: The import page's file field was labelled "File (.csv]", its example CSV link carried a stray quote, and six labels used the wrong text domain, so they were never translated.
* Fixed: CSV imports logged a PHP 8.4 deprecation notice for every line of the file.
* Changed: Checking whether a location already exists no longer uses a function WordPress deprecated. A location in the trash with the same title no longer counts as existing, so importing that row creates a new location.
* Fixed: Users created by a CSV import were created with no password at all, which WordPress warns about. They now get a generated one and set their own through the lost password form.
* Fixed: A CSV import that could not create a location counted it as imported. Failed rows are now counted.
* Fixed: A CSV import submitted without choosing a status saved every location as "public", which is not a real post status. It now falls back to Published.
* Fixed: The CSV importer stored every value as escaped HTML, so imported website addresses came in with `&amp;` in them. Website addresses are now stored as they are. Values imported before this update are unchanged.

### Settings and upgrading

* Fixed: The default "Locations" and "Location" labels, the photo field's help and the missing API key warning used the wrong text domain, so they were never translated.
* Fixed: A site updating straight from 0.4.0 or earlier lost its labels and URL base, fell back to "Locations" and `/locations/`, and every one of its old location addresses stopped working. The old settings are now carried over on the first Dashboard visit after updating. A site that already went from 0.4.0 to 1.x is not changed: it has been running on whatever it fell back to since then, and moving it now would change its live addresses.
* Fixed: Carrying those old settings over deleted them before saving the new copy, so a failed save lost them for good. They are only removed once the new copy is saved.
* Fixed: A site from before 2023 was taken for a brand new install, because it had no record of which version it was on, and so did not keep owner publishing. Its old settings, or any location at all, now mark it as an existing site.
* Fixed: A new install built from the command line, activated and then filled with locations before anyone opened the Dashboard, could be taken for an old site and have owner publishing switched on. Activation now marks a new install as current.
* Fixed: A setting saved in code during a request, such as a new label, was not seen again until the next page load.
* Fixed: A location category nested under another had no working URL. The update refreshes the permalinks itself on the first Dashboard visit, so there is nothing to save by hand.
* Fixed: The Settings link on the Plugins page was missing on any site where the plugin folder had been renamed.
* Fixed: Upgrading from an older version carried the old settings over exactly as they were, so a URL base with spaces or punctuation in it was saved unusable.
* Fixed: Saving the settings with the distance field empty stored a distance of 0, which searches with no limit at all. An empty field now keeps the default, and a 0 entered on purpose still means no limit.
* Fixed: The Default Units setting accepted any text. It now accepts only miles or kilometres.
* Fixed: Asking for a setting that does not exist logged a warning.
* Fixed: The Default Units dropdown printed a stray `selected='selected'` before its first option.
* Fixed: A Google API key saved in the settings replaced the key ACF already had. It now fills in only what ACF is missing.
* Fixed: The upgrade routines ran twice on every Dashboard page load, because a function copy and a class copy were both hooked. One copy runs now, and the old function names still work.
* Fixed: Clearing the plugin's cached map data also cleared any other site transient whose name happened to start with the same letters.
* Fixed: The location fields panel in the editor was titled "{SINGULAR} Info" instead of "Location Info".

### Shortcodes

* Fixed: `[mai_location_email link="false"]` still printed a link. `[mai_location_phone]` already handled this.
* Fixed: `[mai_location_distance]` printed "3.1mi away" instead of "3.1 mi away". Spaces in `before` and `after` are kept now.
* Fixed: `[mai_location_distance]` printed nothing for a location whose distance rounded to 0. It now prints "0 mi away".
* Changed: `[mai_location_distance]` escapes HTML in `before` and `after` instead of stripping it, matching every other location shortcode.
* Fixed: `[mai_location_phone]` linked to only the first group of digits when the location had no country set, so `914-631-8200` dialled `914`.
* Fixed: a phone number that is not valid for its country printed an empty link and logged warnings. It now falls back to the number as entered.
* Fixed: `[mai_location_url]` removed the first letter of any web address starting with "w", so `www.washingtonirving.org` displayed as `ashingtonirving.org`.

### Addresses, distance and searching

* Fixed: Placing a location by searching its map filled none of the address fields, so the country stayed on its default, United States, even when the pin was in Canada. The address now comes from the place that was picked on the map, which also means it no longer needs a server-side Google API key or a second lookup. [#6](https://github.com/maithemewp/mai-locations/issues/6)
* Fixed: Locations already saved that way are repaired automatically on the first Dashboard visit after updating. Only a location whose street, city and post code are all empty is filled, from its own map, so an address someone typed is never changed and no request goes to Google.
* Fixed: Geocoding left the country and state out of the address it sent to Google, so an address could be matched in the wrong country.
* Fixed: A geocoding result with no country logged a warning.
* Fixed: A location sitting exactly on the searched point was left out of the results when the search had no distance limit.
* Fixed: A location search ordered by distance with no direction set produced broken SQL. The direction is also limited to ascending or descending now, rather than passed through to the database.
* Fixed: A search on the equator or the prime meridian, where a coordinate is 0, ran as though no location had been given.
* Fixed: An address hiding the country printed the US state on a location outside the US, so a Canadian address showed a US state code.
* Fixed: An address with nothing but a country printed an empty div above it.
* Fixed: The state list spelled Washington DC as "District of Colombia".

### Fetching descriptions and photos from a location's website

* Changed: `wp mailocations update_locations_from_website` now reports every location. A site that gave nothing back and an image that would not download each get a line, the run ends with a count of what was updated and what failed, and it ends in a warning instead of "Done." when anything failed. It used to print nothing for either, so a quiet run was no proof every site answered.
* Fixed: The same command printed "Excerpt updated" for an excerpt that failed to save, then carried on as though the location were post 0.
* Fixed: `wp mailocations import_places` reported a photo that failed to save as "Featured image updated", and gave the location the wrong image on a site whose first attachment is a photo.
* Added: `--skip_excerpt` and `--skip_image` for `wp mailocations update_locations_from_website`, so a run can fetch only images or only excerpts.
* Fixed: Every fetched image was saved as `.jpg` whatever it really was.
* Fixed: When saving a fetched image failed, the run logged "Image updated" anyway. It now says the image failed and leaves the featured image alone.
* Changed: `wp mailocations update_locations_from_website` now asks each site with a browser user agent and a 15 second timeout, instead of WordPress's own agent and 5 seconds. Many hotel and chain sites answered the old request with nothing. The request arguments are filterable through `mailocations_website_request_args`, which runs for image downloads too; check its `$url` argument to tell them apart.
* Fixed: A site with only Twitter card tags gave the command nothing, because the fallback that reads them could never run. It now fills in whatever the Open Graph tags did not provide.
* Fixed: `mailocations_get_data_from_website()` warned and returned null when asked for a key it does not have. It returns an empty string.
* Fixed: Images fetched from a location's website were downloaded twice, the second time through the site's own uploads URL, which fails outright on a local site with a self-signed certificate. They are fetched once now.

### For developers

* Changed: Every class moved into the `Mai\Locations\` namespace. The old names, such as `Mai_Locations_Location_Form`, still work as aliases, but `get_class()` now reports the new name. Many methods gained return types, so a subclass that overrides one must declare the same type. No site on our fleet subclasses any of them.
* Fixed: Asking for a second user's locations in the same request returned the first user's. A page serves one person, so this showed up in WP-CLI runs and anything looping over users.
* Changed: `Mai_Locations_Location_Fields::prepare_location_exerpt_field()` is now spelled `prepare_location_excerpt_field()`. The old name still works.
* Changed: A plural label, singular label or URL base set through a filter is now cleaned every time it is read, not only the first time. A filtered base is cleaned the same way a saved one is, so `Our Places!` gives `our-places` where it used to give `OurPlaces`.
* Fixed: A location created in code with a country of its own was saved as US, because the field defaults overwrote what the caller passed.
* Fixed: A user's list of locations gained a duplicate entry every time the same location was added again.

### Removed

* Removed: `Mai_Locations_Queries::mai_post_grid_query()`, which was never hooked up and called a function that does not exist.
* Removed: Social media fields (Facebook, Twitter, YouTube, LinkedIn, Instagram, Pinterest, TikTok) `mailocations_get_social_fields()` and the `mailocations_social_fields` filter. They were switched off in 1.0.0 and never displayed anywhere. No site had any data saved in them.

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

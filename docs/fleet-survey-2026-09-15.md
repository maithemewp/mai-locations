# Who uses Mai Locations, September 15, 2026

Read-only survey of the whole hosting fleet with `mai-sites run all`. 235 sites probed, 0 failed, twice. Mike approved it so the rework knows what it can safely change.

## The 11 sites that have the plugin

| Site | Version | Locations |
| --- | --- | --- |
| pregnancybydesign.com | 1.1.0 | 3,655 |
| pregnancybydesign.heritagewebsites.com | 1.0.0 | 3,655 |
| naturesoma.com | 1.1.0 | 86 |
| Iladistrict.com | 1.1.0 | 57 |
| naturebasedtherapytraining.com | 1.1.0 | 28 |
| agi.heritagewebsites.com | 1.1.0 | 16 |
| agiindustries.com | 1.1.0 | 16 |
| staging.agiindustries.com | 1.1.0 | 16 |
| pbd.heritagewebsites.com | 0.4.0 | 4 |
| sugarmakers.org | 1.1.0 | 1 |
| notanews.springwire.ai | 1.0.0 | 0 |

All active. Visit Sleepy Hollow is not on this fleet; it is on SiteGround and holds 141 locations.

Two sites are behind: `pbd.heritagewebsites.com` on 0.4.0 and `pregnancybydesign.heritagewebsites.com` on 1.0.0. An upgrade from 0.4.0 runs the 0.7.0 upgrade routine, which is the one that exists twice and runs twice.

## What their own code calls

- `mailocations_update_google_map_from_address()`: 5 sites (the three AGI sites, naturebasedtherapytraining, naturesoma). This is the geocoding path that cannot be tested without a Google API key, and whose address string never includes state or country. It has the most third-party callers of anything in the plugin.
- `[mai_location_address]` in theme templates: 4 sites.
- `mailocations_is_filtered_locations()` and `mailocations_is_archive()`: both Pregnancy By Design sites, in `includes/helpers.php`.
- naturesoma.com alone: `mailocations_general_fields`, `mailocations_address_fields`, `mailocations_social_fields`, `mailocations_location_acf_form`, `mailocations_woocommerce_account_tabs`, plus the table and submission blocks. Its theme adds a whole events layer on top of locations (`event_url`, `event_type`, coordinators repeater), stored as location meta through those field filters.

Nothing on the fleet calls the CLI commands, the importer, the geo query, or the phone, url, email, place, distance or table shortcodes from theme code.

## Two things the survey settles

**The field filters are used to trim the edit screen, not to extend it.** naturesoma's code, read September 15, 2026:

```php
function social_fields( $fields ) {
	return [];
}

function general_fields( $fields ) {
	unset( $fields['location_phone'] );
	unset( $fields['location_phone_2'] );
	unset( $fields['location_email'] );
	return $fields;
}

function address_fields( $fields ) {
	$fields['location_address_tab']['label'] = __( 'Location', 'mai-locations' );
	return $fields;
}
```

So the filters hide three fields and rename a tab. That site's event fields (`event_url`, `event_type`, coordinators) come from its own ACF field group, not from ours. The filters still have to keep working, because a site that loses them suddenly shows fields its editors were told to ignore, and the tests cannot exercise them while the field functions cache in statics.

**Nobody has social data, and the one site hooking the filter also switches the fields off.** No location on any site has a `facebook`, `twitter` or `instagram` meta value, including the site still running 0.4.0, where the fields were live. naturesoma's `social_fields` returns an empty array, the same result the plugin already produces. Deleting the social fields matches what the only interested site wants.

## What the survey cannot see

Shortcode use inside post content came back as zero on every site, and the probe was verified working on a site with 86 locations. But a shortcode placed in a Mai Engine grid setting, a custom content area, a widget or a template part is stored outside `post_content` and would not be counted. Visit Sleepy Hollow uses `[mai_location_address hide="country"][mai_location_phone]` in exactly that way, in a grid's custom content setting. So treat "no shortcode use" as unproven rather than false.

## How to repeat it

```
mai-sites run all --safe-to-rerun --yes --parallel --on-failure=continue -- sh -c '<probe>'
```

The probe checks for `wp-content/plugins/mai-locations`, reads the version from the plugin header, asks wp-cli for status and location count, and greps `wp-content/themes` and `wp-content/mu-plugins` for plugin identifiers. Keep it read-only, and run wp-cli with `--skip-plugins --skip-themes --skip-packages`.

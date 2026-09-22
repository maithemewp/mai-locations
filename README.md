# Mai Locations

A location post type with info, address and map fields. Map, filter and search blocks included. Requires ACF Pro.

## Blocks

| Block | What it does |
| --- | --- |
| Mai Locations Map | A Google map of the locations on the page. |
| Mai Locations Filter | One taxonomy filter. |
| Mai Locations Filters | Every taxonomy filter at once. |
| Mai Locations Address Search | A search by address, with a distance. |
| Mai Locations Count | "Showing 12 of 40 Locations". |
| Mai Location Submission | A front-end form for adding a location. |
| Mai Locations Table | A person's own locations, with View and Edit buttons. |

## Shortcodes

These print one location's details. They have no block equivalent yet.

| Shortcode | Parameters |
| --- | --- |
| `[mai_location_address]` | `hide` |
| `[mai_location_phone]` | `before` `after` `link` `style` |
| `[mai_location_url]` | `before` `after` `style` |
| `[mai_location_email]` | `before` `after` `link` `style` |
| `[mai_location_place]` | `before` `after` `style` |
| `[mai_location_distance]` | `before` `after` `round` |
| `[mai_locations_table]` | Same as the Mai Locations Table block. |

`before` and `after` print text either side of the value, spaces included. `style` adds inline CSS. `link` takes `false` to print the value without a link. `round` sets how many decimal places a distance keeps.

`hide` takes a comma-separated list of address parts to leave out: `street`, `street2`, `city`, `state`, `postcode`, `country`. So `[mai_location_address hide="country"]`. The address shortcode has no `before` or `after`.

The locations table also appears in the WooCommerce account area when WooCommerce is active.

## Who may publish

Three ways sites run this, all set from Settings > Mai Locations and the submission block's own Status setting. The settings page explains them where you choose:

| Model | Publishing setting | Submission status |
| --- | --- | --- |
| A manager approves everything | off | Pending |
| Owners run their own listing | on | Draft |
| You approve, the owner picks the moment | on | Pending, then you change it to Draft |

An owner only ever sees the Publish switch while their location is a draft. Editors and administrators can publish whatever the setting says. `mailocations_user_can_publish` filters the answer.

## Tests

The tests boot a real WordPress with ACF Pro and this plugin loaded. They need MySQL on `127.0.0.1` and a copy of ACF Pro on disk. By default they use the copy mai-engine installs at `~/Plugins/mai-engine/vendor/wpengine/advanced-custom-fields-pro`. Set `MAI_LOCATIONS_ACF_DIR` to use another.

Test-only dependencies install into `tests/vendor/`, so the committed `vendor/` never picks up dev packages.

**Warning:** the suite drops its tables on every run. Never point it at a real site's database.

1. Create the test database.

   ```
   mysql -h127.0.0.1 -uroot -e 'CREATE DATABASE IF NOT EXISTS mai_locations_tests'
   ```

2. Install the test dependencies.

   ```
   composer test-setup
   ```

3. Run the tests.

   ```
   composer test
   ```

4. Run static analysis.

   ```
   composer stan
   ```

Override the database with `WP_TESTS_DB_NAME`, `WP_TESTS_DB_USER`, `WP_TESTS_DB_PASS` and `WP_TESTS_DB_HOST`.

PHPStan runs at level 6 against `tests/phpstan-baseline.neon`, which records the errors that existed when the harness was added. New code has to pass clean. When a fix removes a baselined error, regenerate the baseline so it cannot come back unnoticed:

```
tests/vendor/bin/phpstan analyse --configuration tests/phpstan.neon.dist --memory-limit=1G --generate-baseline tests/phpstan-baseline.neon
```

# Mai Locations

A custom post type with info/address/map fields to manage locations. Map and location finder/filter blocks included. Requires ACF Pro.

Display location info with `[mai_location_phone]`, `[mai_location_url]`, `[mai_location_email]`, `[mai_location_place]` shortcodes. All have a `before` parameter to show text before the value, a `style` parameter to add inline CSS styles, and phone/email shortcodes have a `link` parameter where you can disable the link via `link="false"`.

Display a table of a users locations via `[mai_locations_table]`. This is automatically displayed in WooCommerce Account if WooCommerce is active. The table allows logged in users to edit their location(s).

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

# Mai Locations

A custom post type with info/address/map fields to manage locations. Works great with FacetWP Map/Proximity facets.

Display location info with `[mai_location_phone]`, `[mai_location_url]`, `[mai_location_email]`, `[mai_location_place]` shortcodes. All have a `before` parameter to show text before the value, a `style` parameter to add inline CSS styles, and phone/email shortcodes have a `link` parameter where you can disable the link via `link="false"`.

Display a table of a users locations via `[mai_locations_table]`. This is automatically displayed in WooCommerce Account if WooCommerce is active. The table allows logged in users to edit their location(s).

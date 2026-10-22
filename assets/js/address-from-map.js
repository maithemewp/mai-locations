/**
 * Fills a location's address fields the moment its map pin is chosen.
 *
 * ACF fires google_map_change whenever someone picks a place, drags the pin or uses Locate,
 * with the place's parts: city, state, post code, country. Filling the fields here means the
 * person sees the right address before saving, and the form they save agrees with the pin.
 *
 * The server used to do this after the save, which the block editor never shows: it saves meta
 * boxes without reloading them, so the Country field went on saying United States for a pin in
 * Victoria, BC, and a second save sent that back. GitHub issue #6, September 23, 2026.
 *
 * Street line 2 is left alone: a suite number is never part of a map result.
 */
( function () {
	if ( 'undefined' === typeof window.acf ) {
		return;
	}

	var acf = window.acf;

	function field( key ) {
		var found = acf.getFields( { key: key } );
		return found.length ? found[ 0 ] : null;
	}

	// Sets a field and fires change. field.val() alone does not, and the State and
	// State/Province fields only switch when ACF's conditional logic hears the Country change:
	// without it the State/Province box stayed disabled, and a disabled field is never saved.
	function set( key, value ) {
		var target = field( key );

		if ( ! target ) {
			return;
		}

		target.val( value );
		target.$input().trigger( 'change' );
	}

	acf.addAction( 'google_map_change', function ( value, map, mapField ) {
		if ( ! mapField || 'mai_location_location' !== mapField.get( 'key' ) ) {
			return;
		}

		// Without a country the parts are not worth trusting; leave the fields alone. The
		// server fills them after saving if they are empty.
		if ( ! value || ! value.country_short ) {
			return;
		}

		var country = value.country_short;
		var state   = value.state_short || value.state || '';
		var street  = [ value.street_number || '', value.street_name_short || value.street_name || '' ].join( ' ' ).trim();

		// Country first: it decides which of the two state fields is shown.
		set( 'mai_location_address_country', country );
		set( 'mai_location_address_street', street );
		set( 'mai_location_address_city', value.city || '' );
		set( 'mai_location_address_postcode', value.post_code || '' );

		// The same rule as the server: a US state goes in the US field, anything else in the
		// international one, and the other is cleared.
		if ( 'US' === country ) {
			set( 'mai_location_address_state', state );
			set( 'mai_location_address_state_int', '' );
		} else {
			set( 'mai_location_address_state', '' );
			set( 'mai_location_address_state_int', state );
		}
	} );
}() );
